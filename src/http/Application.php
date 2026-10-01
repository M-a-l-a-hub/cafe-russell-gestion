<?php

declare(strict_types=1);

/**
 * Chaîne complète d'une requête : route → session → CSRF → rôle →
 * contrôleur → réponse JSON. Toute exception est convertie en erreur
 * JSON ; les détails techniques ne sortent que si APP_DEBUG=true.
 */
final class Application
{
    public function __construct(private Routeur $routeur)
    {
    }

    public function executer(): void
    {
        $requete = null;

        try {
            $requete = Requete::depuisGlobales();
            $this->traiter($requete);
        } catch (Throwable $e) {
            $this->repondreErreur($e, $requete);
        }
    }

    private function traiter(Requete $requete): void
    {
        [$route, $params] = $this->routeur->trouver(
            $requete->methode,
            $requete->chemin
        );

        $requete->params = $params;
        $options = $route['options'];

        if (!($options['sans_session'] ?? false)) {
            SessionHttp::demarrer();
        }

        if (!($options['public'] ?? false)) {
            $utilisateur = SessionHttp::utilisateurConnecte();

            if ($utilisateur === null) {
                throw new HttpException(401, 'non_connecte', 'Connexion requise.');
            }

            $requete->utilisateur = $utilisateur;

            if ($requete->methode !== 'GET') {
                SessionHttp::verifierCsrf($requete->entete('x-csrf-token'));
            }

            $role = $options['role'] ?? null;

            if ($role !== null && $utilisateur['role'] !== $role) {
                throw new HttpException(
                    403,
                    'acces_refuse',
                    'Cette opération est réservée au super administrateur.'
                );
            }
        }

        $cle = $requete->methode === 'POST' && $requete->utilisateur !== null
            ? $requete->entete('idempotency-key')
            : null;

        if ($cle !== null) {
            if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $cle)) {
                throw new HttpException(400, 'cle_invalide', 'Clé d’idempotence invalide.');
            }

            $this->executerUneSeuleFois($cle, $route, $requete);

            return;
        }

        $this->executerGestionnaire($route, $requete);
    }

    private function executerGestionnaire(array $route, Requete $requete): void
    {
        $resultat = ($route['gestionnaire'])($requete);

        if ($resultat instanceof Resultat) {
            Reponse::succes($resultat->donnees, $resultat->statut);

            return;
        }

        Reponse::succes($resultat);
    }

    /**
     * Saisies envoyées depuis la file d'attente hors ligne : une même clé
     * (en-tête Idempotency-Key) n'est exécutée qu'une fois. Un rejeu
     * (réponse perdue, réseau instable) renvoie la réponse d'origine.
     * Seules les réussites sont mémorisées : un refus peut être retenté.
     */
    private function executerUneSeuleFois(string $cle, array $route, Requete $requete): void
    {
        $db = getDatabaseConnection();
        $userId = (int) $requete->utilisateur['id'];

        // Deux requêtes simultanées avec la même clé passent l'une après l'autre.
        $verrou = $db->prepare('SELECT pg_advisory_lock(hashtext(:cle))');
        $verrou->execute(['cle' => $cle]);

        try {
            $stmt = $db->prepare(
                'SELECT user_id, statut_http, reponse FROM requetes_idempotentes WHERE cle = :cle'
            );
            $stmt->execute(['cle' => $cle]);
            $existant = $stmt->fetch();

            if ($existant) {
                if ((int) $existant['user_id'] !== $userId) {
                    throw new HttpException(409, 'cle_deja_utilisee', 'Clé d’idempotence déjà utilisée.');
                }

                http_response_code((int) $existant['statut_http']);
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store');
                header('X-Content-Type-Options: nosniff');
                echo $existant['reponse'];

                return;
            }

            ob_start();

            try {
                $this->executerGestionnaire($route, $requete);
            } catch (Throwable $e) {
                ob_end_clean();

                throw $e;
            }

            $corps = (string) ob_get_contents();
            $statut = http_response_code();
            $statut = is_int($statut) ? $statut : 200;

            ob_end_flush();

            if ($statut >= 200 && $statut < 300) {
                $insertion = $db->prepare(
                    'INSERT INTO requetes_idempotentes (cle, user_id, methode, chemin, statut_http, reponse)
                     VALUES (:cle, :user_id, :methode, :chemin, :statut, :reponse)
                     ON CONFLICT (cle) DO NOTHING'
                );
                $insertion->execute([
                    'cle' => $cle,
                    'user_id' => $userId,
                    'methode' => $requete->methode,
                    'chemin' => substr($requete->chemin, 0, 255),
                    'statut' => $statut,
                    'reponse' => $corps,
                ]);
            }
        } finally {
            $deverrou = $db->prepare('SELECT pg_advisory_unlock(hashtext(:cle))');
            $deverrou->execute(['cle' => $cle]);
        }
    }

    private function repondreErreur(Throwable $e, ?Requete $requete): void
    {
        if ($e instanceof HttpException) {
            Reponse::erreur($e->statutHttp(), $e->codeErreur(), $e->getMessage());

            return;
        }

        if ($e instanceof AccesRefuseException) {
            Reponse::erreur(403, 'acces_refuse', $e->getMessage());

            return;
        }

        // PDOException hérite de RuntimeException : à tester avant.
        if ($e instanceof PDOException) {
            $this->journaliser($e, $requete);

            $etat = (string) $e->getCode();

            // Classe 23 = contrainte d'intégrité ; P0001 = déclencheur.
            if (str_starts_with($etat, '23') || $etat === 'P0001') {
                Reponse::erreur(
                    409,
                    'operation_refusee',
                    'Opération refusée : elle ne respecte pas les règles d’intégrité des données.',
                    $this->detailSiDebug($e)
                );

                return;
            }

            $this->erreurInterne($e);

            return;
        }

        if ($e instanceof InvalidArgumentException) {
            Reponse::erreur(422, 'donnees_invalides', $e->getMessage());

            return;
        }

        if ($e instanceof RuntimeException) {
            $introuvable = mb_stripos($e->getMessage(), 'introuvable') !== false;

            Reponse::erreur(
                $introuvable ? 404 : 409,
                $introuvable ? 'introuvable' : 'regle_metier',
                $e->getMessage()
            );

            return;
        }

        $this->journaliser($e, $requete);
        $this->erreurInterne($e);
    }

    private function erreurInterne(Throwable $e): void
    {
        Reponse::erreur(
            500,
            'erreur_interne',
            'Erreur interne du serveur.',
            $this->detailSiDebug($e)
        );
    }

    private function detailSiDebug(Throwable $e): ?string
    {
        $debug = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

        return $debug ? get_class($e) . ' : ' . $e->getMessage() : null;
    }

    private function journaliser(Throwable $e, ?Requete $requete): void
    {
        $dossier = dirname(__DIR__, 2) . '/storage/logs';

        if (!is_dir($dossier)) {
            @mkdir($dossier, 0775, true);
        }

        $ligne = sprintf(
            "[%s] %s %s — %s : %s (%s:%d)\n",
            date('c'),
            $requete?->methode ?? '-',
            $requete?->chemin ?? '-',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        if (@file_put_contents($dossier . '/app.log', $ligne, FILE_APPEND | LOCK_EX) === false) {
            error_log(trim($ligne));
        }
    }
}

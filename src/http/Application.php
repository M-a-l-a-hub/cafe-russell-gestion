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

        $resultat = ($route['gestionnaire'])($requete);

        if ($resultat instanceof Resultat) {
            Reponse::succes($resultat->donnees, $resultat->statut);

            return;
        }

        Reponse::succes($resultat);
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

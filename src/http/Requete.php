<?php

declare(strict_types=1);

require_once __DIR__ . '/HttpException.php';

/**
 * Requête HTTP entrante : méthode, chemin, en-têtes, corps JSON, et des
 * accesseurs qui valident et convertissent chaque champ.
 *
 * Les accesseurs sans suffixe exigent le champ ; ceux qui finissent par
 * « Optionnel » renvoient null quand il est absent ou vide.
 */
final class Requete
{
    private const TAILLE_MAX_CORPS = 1048576; // 1 Mo

    /** @var array<string, string> Paramètres du chemin ({id}). */
    public array $params = [];

    /** Utilisateur connecté (renseigné par Application). */
    public ?array $utilisateur = null;

    private ?array $corps = null;

    /**
     * @param array<string, mixed>  $query
     * @param array<string, string> $entetes clés en minuscules
     */
    public function __construct(
        public readonly string $methode,
        public readonly string $chemin,
        private readonly array $query,
        private readonly string $contenuBrut,
        private readonly array $entetes,
        private readonly string $ip
    ) {
    }

    public static function depuisGlobales(): self
    {
        $methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        $chemin = (string) parse_url(
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            PHP_URL_PATH
        );
        $chemin = '/' . trim($chemin, '/');

        $entetes = [];

        foreach ($_SERVER as $cle => $valeur) {
            if (!is_string($valeur)) {
                continue;
            }

            if (str_starts_with($cle, 'HTTP_')) {
                $nom = strtolower(str_replace('_', '-', substr($cle, 5)));
                $entetes[$nom] = $valeur;
            } elseif ($cle === 'CONTENT_TYPE' || $cle === 'CONTENT_LENGTH') {
                $entetes[strtolower(str_replace('_', '-', $cle))] = $valeur;
            }
        }

        if ((int) ($entetes['content-length'] ?? 0) > self::TAILLE_MAX_CORPS) {
            throw new HttpException(413, 'corps_trop_gros', 'Requête trop volumineuse.');
        }

        $brut = '';
        $flux = fopen('php://input', 'rb');

        if ($flux !== false) {
            $brut = (string) stream_get_contents($flux, self::TAILLE_MAX_CORPS + 1);
            fclose($flux);
        }

        if (strlen($brut) > self::TAILLE_MAX_CORPS) {
            throw new HttpException(413, 'corps_trop_gros', 'Requête trop volumineuse.');
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        // Derrière un proxy de confiance (Render, etc.) : la dernière
        // adresse de X-Forwarded-For est celle vue par le proxy.
        $confianceProxy = filter_var(
            $_ENV['TRUST_PROXY'] ?? 'false',
            FILTER_VALIDATE_BOOLEAN
        );

        if ($confianceProxy && isset($entetes['x-forwarded-for'])) {
            $liste = array_map('trim', explode(',', $entetes['x-forwarded-for']));
            $derniere = end($liste);

            if ($derniere !== false && filter_var($derniere, FILTER_VALIDATE_IP) !== false) {
                $ip = $derniere;
            }
        }

        return new self($methode, $chemin, $_GET, $brut, $entetes, $ip);
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function entete(string $nom): ?string
    {
        return $this->entetes[strtolower($nom)] ?? null;
    }

    // ------------------------------------------------------------------
    // Chemin
    // ------------------------------------------------------------------

    /** Paramètre numérique du chemin ({id}). */
    public function parametreChemin(string $nom): int
    {
        $valeur = $this->params[$nom] ?? '';

        if (preg_match('/^\d{1,18}$/', $valeur) !== 1) {
            throw new HttpException(404, 'introuvable', 'Ressource introuvable.');
        }

        return (int) $valeur;
    }

    // ------------------------------------------------------------------
    // Corps JSON
    // ------------------------------------------------------------------

    public function corps(): array
    {
        if ($this->corps !== null) {
            return $this->corps;
        }

        if (trim($this->contenuBrut) === '') {
            return $this->corps = [];
        }

        $type = strtolower($this->entete('content-type') ?? '');

        if (!str_starts_with($type, 'application/json')) {
            throw new HttpException(
                415,
                'type_non_supporte',
                'Le corps doit être du JSON (Content-Type: application/json).'
            );
        }

        try {
            $donnees = json_decode(
                $this->contenuBrut,
                true,
                16,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw new HttpException(400, 'json_invalide', 'Le corps de la requête n’est pas un JSON valide.');
        }

        if (!is_array($donnees)) {
            throw new HttpException(400, 'json_invalide', 'Le corps doit être un objet JSON.');
        }

        return $this->corps = $donnees;
    }

    public function entier(string $cle): int
    {
        return self::exiger(
            self::versEntier($this->corps()[$cle] ?? null, $cle),
            $cle
        );
    }

    public function entierOptionnel(string $cle): ?int
    {
        return self::versEntier($this->corps()[$cle] ?? null, $cle);
    }

    public function texte(string $cle, int $max = 2000): string
    {
        return self::exiger($this->texteOptionnel($cle, $max), $cle);
    }

    public function texteOptionnel(string $cle, int $max = 2000): ?string
    {
        return self::versTexte($this->corps()[$cle] ?? null, $cle, $max);
    }

    /** Mot de passe : jamais trimé, jamais journalisé. */
    public function secret(string $cle): string
    {
        $valeur = $this->corps()[$cle] ?? null;

        if (!is_string($valeur) || $valeur === '' || strlen($valeur) > 200) {
            throw new HttpException(422, 'champ_invalide', "Le champ « {$cle} » est obligatoire.");
        }

        return $valeur;
    }

    /** Montant en FC : le service en valide le format exact. */
    public function montant(string $cle): int|float|string
    {
        $valeur = $this->corps()[$cle] ?? null;

        if (is_int($valeur) || is_float($valeur)) {
            return $valeur;
        }

        if (is_string($valeur) && trim($valeur) !== '') {
            return $valeur;
        }

        throw new HttpException(422, 'champ_invalide', "Le champ « {$cle} » doit être un montant.");
    }

    public function date(string $cle): string
    {
        return self::exiger($this->dateOptionnelle($cle), $cle);
    }

    public function dateOptionnelle(string $cle): ?string
    {
        $texte = self::versTexte($this->corps()[$cle] ?? null, $cle, 10);

        return $texte === null ? null : self::validerDate($texte, $cle);
    }

    /**
     * @param string[] $valeurs
     */
    public function choix(string $cle, array $valeurs): string
    {
        return self::exiger($this->choixOptionnel($cle, $valeurs), $cle);
    }

    /**
     * @param string[] $valeurs
     */
    public function choixOptionnel(string $cle, array $valeurs): ?string
    {
        return self::versChoix(
            self::versTexte($this->corps()[$cle] ?? null, $cle, 50),
            $cle,
            $valeurs
        );
    }

    /** Liste non vide (les lignes sont validées par le service). */
    public function liste(string $cle): array
    {
        $valeur = $this->corps()[$cle] ?? null;

        if (!is_array($valeur) || $valeur === [] || !array_is_list($valeur)) {
            throw new HttpException(422, 'champ_invalide', "Le champ « {$cle} » doit être une liste non vide.");
        }

        return $valeur;
    }

    // ------------------------------------------------------------------
    // Paramètres d'URL (?cle=valeur)
    // ------------------------------------------------------------------

    /** Paramètre d'URL brut, sans validation (ex. un simple indicateur ?tous=1). */
    public function query(string $cle): ?string
    {
        $valeur = $this->query[$cle] ?? null;

        return is_string($valeur) && $valeur !== '' ? $valeur : null;
    }

    public function queryEntier(string $cle): ?int
    {
        return self::versEntier($this->query[$cle] ?? null, $cle);
    }

    public function queryEntierRequis(string $cle): int
    {
        return self::exiger($this->queryEntier($cle), $cle);
    }

    public function queryDate(string $cle): ?string
    {
        $texte = self::versTexte($this->query[$cle] ?? null, $cle, 10);

        return $texte === null ? null : self::validerDate($texte, $cle);
    }

    /**
     * @param string[] $valeurs
     */
    public function queryChoix(string $cle, array $valeurs): ?string
    {
        return self::versChoix(
            self::versTexte($this->query[$cle] ?? null, $cle, 50),
            $cle,
            $valeurs
        );
    }

    // ------------------------------------------------------------------
    // Conversions
    // ------------------------------------------------------------------

    private static function exiger(mixed $valeur, string $cle): mixed
    {
        if ($valeur === null) {
            throw new HttpException(422, 'champ_obligatoire', "Le champ « {$cle} » est obligatoire.");
        }

        return $valeur;
    }

    private static function versEntier(mixed $valeur, string $cle): ?int
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        if (is_int($valeur)) {
            return $valeur;
        }

        if (is_string($valeur) && preg_match('/^\d{1,18}$/', $valeur) === 1) {
            return (int) $valeur;
        }

        throw new HttpException(422, 'champ_invalide', "Le champ « {$cle} » doit être un entier.");
    }

    private static function versTexte(mixed $valeur, string $cle, int $max): ?string
    {
        if ($valeur === null) {
            return null;
        }

        if (!is_string($valeur)) {
            throw new HttpException(422, 'champ_invalide', "Le champ « {$cle} » doit être du texte.");
        }

        $valeur = trim($valeur);

        if ($valeur === '') {
            return null;
        }

        if (mb_strlen($valeur) > $max) {
            throw new HttpException(422, 'champ_invalide', "Le champ « {$cle} » dépasse {$max} caractères.");
        }

        return $valeur;
    }

    /**
     * @param string[] $valeurs
     */
    private static function versChoix(?string $texte, string $cle, array $valeurs): ?string
    {
        if ($texte !== null && !in_array($texte, $valeurs, true)) {
            throw new HttpException(
                422,
                'champ_invalide',
                "Le champ « {$cle} » doit valoir : " . implode(', ', $valeurs) . '.'
            );
        }

        return $texte;
    }

    private static function validerDate(string $texte, string $cle): string
    {
        if (
            preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $texte, $m) !== 1
            || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])
        ) {
            throw new HttpException(422, 'champ_invalide', "Le champ « {$cle} » doit être une date AAAA-MM-JJ.");
        }

        return $texte;
    }
}

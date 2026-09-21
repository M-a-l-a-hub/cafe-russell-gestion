<?php

declare(strict_types=1);

/**
 * Résultat renvoyé par un contrôleur quand le statut n'est pas 200
 * (par exemple 201 pour une création).
 */
final class Resultat
{
    public function __construct(
        public readonly int $statut,
        public readonly mixed $donnees
    ) {
    }
}

/**
 * Réponses JSON. Forme unique :
 *   succès : {"ok": true,  "donnees": ...}
 *   erreur : {"ok": false, "erreur": {"code": "...", "message": "..."}}
 */
final class Reponse
{
    public static function succes(mixed $donnees, int $statut = 200): void
    {
        self::envoyer($statut, ['ok' => true, 'donnees' => $donnees]);
    }

    public static function erreur(
        int $statut,
        string $code,
        string $message,
        ?string $detail = null
    ): void {
        $erreur = ['code' => $code, 'message' => $message];

        if ($detail !== null) {
            $erreur['detail'] = $detail;
        }

        self::envoyer($statut, ['ok' => false, 'erreur' => $erreur]);
    }

    private static function envoyer(int $statut, array $charge): void
    {
        http_response_code($statut);

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');

        echo json_encode(
            $charge,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_THROW_ON_ERROR
        );
    }
}

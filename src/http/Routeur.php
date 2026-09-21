<?php

declare(strict_types=1);

require_once __DIR__ . '/HttpException.php';

/**
 * Table de routes. Les segments {nom} ne correspondent qu'à des
 * nombres (identifiants).
 *
 * Options d'une route :
 *   public       => true : pas de connexion requise
 *   sans_session => true : ne démarre pas de session
 *   role         => 'super_admin' : réservé à ce rôle
 */
final class Routeur
{
    /** @var array<int, array{methode: string, regex: string, gestionnaire: callable, options: array}> */
    private array $routes = [];

    public function get(string $modele, callable $gestionnaire, array $options = []): void
    {
        $this->ajouter('GET', $modele, $gestionnaire, $options);
    }

    public function post(string $modele, callable $gestionnaire, array $options = []): void
    {
        $this->ajouter('POST', $modele, $gestionnaire, $options);
    }

    private function ajouter(
        string $methode,
        string $modele,
        callable $gestionnaire,
        array $options
    ): void {
        $regex = preg_replace_callback(
            '#\{(\w+)\}|[^{]+#',
            static fn (array $m): string => isset($m[1])
                ? '(?P<' . $m[1] . '>\d+)'
                : preg_quote($m[0], '#'),
            $modele
        );

        $this->routes[] = [
            'methode' => $methode,
            'regex' => '#^' . $regex . '$#',
            'gestionnaire' => $gestionnaire,
            'options' => $options,
        ];
    }

    /**
     * @return array{0: array, 1: array<string, string>} route et paramètres du chemin
     */
    public function trouver(string $methode, string $chemin): array
    {
        $autresMethodes = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $chemin, $m) !== 1) {
                continue;
            }

            if ($route['methode'] !== $methode) {
                $autresMethodes[] = $route['methode'];

                continue;
            }

            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

            return [$route, $params];
        }

        if ($autresMethodes !== []) {
            header('Allow: ' . implode(', ', array_unique($autresMethodes)));

            throw new HttpException(
                405,
                'methode_non_autorisee',
                'Méthode non autorisée pour cette adresse.'
            );
        }

        throw new HttpException(404, 'route_introuvable', 'Adresse introuvable.');
    }
}

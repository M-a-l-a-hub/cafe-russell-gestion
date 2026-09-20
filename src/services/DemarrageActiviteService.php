<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';

/**
 * Démarrage explicite de l'activité d'un vendeur.
 *
 * Le plus souvent, l'activité est créée automatiquement par le premier
 * ravitaillement du vendeur (voir RavitaillementService::enregistrerPourVendeur).
 */
class DemarrageActiviteService extends ServiceBase
{
    /**
     * Démarre l'activité d'un vendeur avec un sac, dans une journée ouverte.
     *
     * @return int Identifiant de l'activité créée.
     */
    public function demarrer(
        int $journeeId,
        int $vendeurId,
        int $sacId,
        string $periode,
        int $userId
    ): int {
        $this->verifierPeriodeActivite($periode);

        return $this->transaction(
            function () use ($journeeId, $vendeurId, $sacId, $periode, $userId): int {
                $this->verifierUtilisateur($userId);

                return $this->ouvrirActivite(
                    $journeeId,
                    $vendeurId,
                    $sacId,
                    $periode,
                    $userId
                );
            }
        );
    }
}

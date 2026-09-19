<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Activite.php';
require_once __DIR__ . '/../models/Ravitaillement.php';
require_once __DIR__ . '/../models/RavitaillementLigne.php';
require_once __DIR__ . '/../models/Retour.php';
require_once __DIR__ . '/../models/RetourLigne.php';
require_once __DIR__ . '/../models/Commission.php';

class ActiviteService
{
    private Activite $activiteModel;
    private Ravitaillement $ravitaillementModel;
    private RavitaillementLigne $ravitaillementLigneModel;
    private Retour $retourModel;
    private RetourLigne $retourLigneModel;
    private Commission $commissionModel;

    public function __construct()
    {
        $this->activiteModel = new Activite();
        $this->ravitaillementModel = new Ravitaillement();
        $this->ravitaillementLigneModel = new RavitaillementLigne();
        $this->retourModel = new Retour();
        $this->retourLigneModel = new RetourLigne();
        $this->commissionModel = new Commission();
    }

    public function calculer(int $activiteId): array
    {
        $activite = $this->activiteModel->findById($activiteId);

        if ($activite === null) {
            throw new RuntimeException(
                "Activité introuvable : {$activiteId}"
            );
        }

        $ravitaillements =
            $this->ravitaillementLigneModel
                ->getByActiviteId($activiteId);

        $retour =
            $this->retourModel
                ->findByActiviteId($activiteId);

        $retours = [];

        if ($retour !== null) {
            $retours =
                $this->retourLigneModel
                    ->getByActiviteId($activiteId);
        }

        $quantites = [];

        /*
         * 1. Agrégation de tous les ravitaillements.
         */
        foreach ($ravitaillements as $ligne) {
            $produitId = (int) $ligne['produit_id'];

            if (!isset($quantites[$produitId])) {
                $quantites[$produitId] = [
                    'produit_id' => $produitId,
                    'code' => $ligne['produit_code'],
                    'nom' => $ligne['produit_nom'],
                    'unite' => $ligne['produit_unite'],
                    'recue' => 0,
                    'retournee' => 0,
                    'vendue' => 0,
                    'ca' => 0.00,
                    'consommables' => 0.00,
                ];
            }

            $quantite = (int) $ligne['quantite'];

            $quantites[$produitId]['recue'] += $quantite;

            /*
             * Le prix et le coût sont ceux enregistrés
             * au moment du ravitaillement.
             */
            $quantites[$produitId]['ca'] +=
                $quantite * (float) $ligne['prix_unitaire'];

            $quantites[$produitId]['consommables'] +=
                $quantite * (float) $ligne['cout_unitaire'];
        }

        /*
         * 2. Agrégation de tous les retours.
         */
        foreach ($retours as $ligne) {
            $produitId = (int) $ligne['produit_id'];

            if (!isset($quantites[$produitId])) {
                $quantites[$produitId] = [
                    'produit_id' => $produitId,
                    'code' => $ligne['produit_code'],
                    'nom' => $ligne['produit_nom'],
                    'unite' => $ligne['produit_unite'],
                    'recue' => 0,
                    'retournee' => 0,
                    'vendue' => 0,
                    'ca' => 0.00,
                    'consommables' => 0.00,
                ];
            }

            $quantites[$produitId]['retournee'] +=
                (int) $ligne['quantite'];
        }

        /*
         * 3. Calcul des quantités réellement vendues.
         */
        $caTotal = 0.00;
        $consommablesTotal = 0.00;

        foreach ($quantites as &$quantite) {
            $quantite['vendue'] =
                $quantite['recue']
                - $quantite['retournee'];

            if ($quantite['vendue'] < 0) {
                throw new RuntimeException(
                    "Retour supérieur à la quantité reçue "
                    . "pour le produit {$quantite['nom']}."
                );
            }

            /*
             * Le CA et le coût doivent être recalculés
             * sur les quantités réellement vendues.
             */
            $prixMoyen = 0.00;
            $coutMoyen = 0.00;

            if ($quantite['recue'] > 0) {
                $prixMoyen =
                    $quantite['ca']
                    / $quantite['recue'];

                $coutMoyen =
                    $quantite['consommables']
                    / $quantite['recue'];
            }

            $quantite['ca'] =
                $quantite['vendue'] * $prixMoyen;

            $quantite['consommables'] =
                $quantite['vendue'] * $coutMoyen;

            $caTotal += $quantite['ca'];
            $consommablesTotal +=
                $quantite['consommables'];
        }

        unset($quantite);

        /*
         * 4. Transport.
         */
        $transportTotal =
            $this->ravitaillementModel
                ->getTotalTransport($activiteId);

        /*
         * 5. Commission.
         */
        $tauxCommission = 25.00;

        $commissionTotal =
            round(
                $caTotal * $tauxCommission / 100,
                2
            );

        /*
         * 6. Bénéfice.
         */
        $benefice =
            round(
                $caTotal
                - $transportTotal
                - $commissionTotal
                - $consommablesTotal,
                2
            );

        /*
         * 7. Bénéficiaire du bénéfice.
         */
        $beneficiaire = (
            $activite['sac_categorie'] === 'russell'
        )
            ? 'russell'
            : 'entreprise';

        return [
            'activite' => $activite,

            'quantites' => array_values($quantites),

            'ca' => round($caTotal, 2),

            'transport' => round(
                $transportTotal,
                2
            ),

            'commission' => $commissionTotal,

            'taux_commission' => $tauxCommission,

            'consommables' => round(
                $consommablesTotal,
                2
            ),

            'benefice' => $benefice,

            'sac_categorie' =>
                $activite['sac_categorie'],

            'beneficiaire_benefice' =>
                $beneficiaire,
        ];
    }
}


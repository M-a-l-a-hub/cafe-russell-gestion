<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/AuditService.php';

/**
 * Changement historisé des prix de vente, des coûts de consommables
 * et des tarifs de transport.
 *
 * Les trois historiques ont la même forme (valeur + date_debut +
 * date_fin) : la période en cours est fermée la veille de la nouvelle
 * date, puis une nouvelle période ouverte est créée. Rien n'est jamais
 * supprimé ni modifié rétroactivement.
 *
 * Exemples :
 *   $s = new TarifService();
 *   $s->changer(TarifService::PRIX, $produitId, 600, '2026-10-01', $userId);
 *   $s->changer(TarifService::COUT, $produitId, '175,50', '2026-10-01', $userId, 'Hausse du lait');
 *   $s->changer(TarifService::TRANSPORT, $categorieId, 2500, '2026-10-01', $userId);
 */
class TarifService extends ServiceBase
{
    public const PRIX = 'prix';
    public const COUT = 'cout';
    public const TRANSPORT = 'transport';

    /**
     * Noms de tables et de colonnes : liste fermée, jamais issue
     * d'une saisie utilisateur.
     */
    private const TARIFS = [
        self::PRIX => [
            'table' => 'produit_prix',
            'fk' => 'produit_id',
            'valeur' => 'prix',
            'entite_table' => 'produits',
            'minimum_strict' => true,
            'action' => 'prix_modifie',
        ],
        self::COUT => [
            'table' => 'produit_couts',
            'fk' => 'produit_id',
            'valeur' => 'cout_unitaire',
            'entite_table' => 'produits',
            'minimum_strict' => true,
            'action' => 'cout_modifie',
        ],
        self::TRANSPORT => [
            'table' => 'transport_prix',
            'fk' => 'categorie_transport_id',
            'valeur' => 'montant_par_ravitaillement',
            'entite_table' => 'categories_transport',
            'minimum_strict' => false,
            'action' => 'transport_modifie',
        ],
    ];

    /**
     * Ouvre une nouvelle période de tarif à partir de $dateDebut.
     *
     * @param string           $type      PRIX, COUT ou TRANSPORT
     * @param int              $entiteId  produit (PRIX, COUT) ou catégorie de transport
     * @param int|float|string $valeur    nouveau montant en FC
     * @param string           $dateDebut AAAA-MM-JJ
     *
     * @return array{id: int, ancienne_valeur: ?string, nouvelle_valeur: string, date_debut: string}
     */
    public function changer(
        string $type,
        int $entiteId,
        int|float|string $valeur,
        string $dateDebut,
        int $userId,
        ?string $motif = null
    ): array {
        if (!isset(self::TARIFS[$type])) {
            throw new InvalidArgumentException("Type de tarif inconnu : {$type}.");
        }

        $config = self::TARIFS[$type];

        $this->verifierDate($dateDebut);

        $nouvelle = $this->normaliserMontant(
            $valeur,
            (bool) $config['minimum_strict']
        );

        $motif = $motif !== null ? trim($motif) : null;

        if ($motif === '') {
            $motif = null;
        }

        return $this->transaction(
            function () use ($type, $config, $entiteId, $nouvelle, $dateDebut, $userId, $motif): array {
                $this->verifierUtilisateur($userId);

                $entite = $this->fetchOne(
                    "SELECT id FROM {$config['entite_table']} WHERE id = :id FOR UPDATE",
                    ['id' => $entiteId]
                );

                if ($entite === null) {
                    throw new RuntimeException('Élément introuvable pour ce tarif.');
                }

                // Dernière période connue (verrouillée).
                $derniere = $this->fetchOne(
                    "
                    SELECT id, {$config['valeur']} AS valeur, date_debut, date_fin
                    FROM {$config['table']}
                    WHERE {$config['fk']} = :entite_id
                    ORDER BY date_debut DESC
                    LIMIT 1
                    FOR UPDATE
                    ",
                    ['entite_id' => $entiteId]
                );

                $ancienne = null;

                if ($derniere !== null) {
                    $ancienne = (string) $derniere['valeur'];
                    $dateDebutDerniere = (string) $derniere['date_debut'];

                    if ($dateDebut <= $dateDebutDerniere) {
                        throw new RuntimeException(
                            "Une période commence déjà le {$dateDebutDerniere} : "
                            . 'choisis une date postérieure.'
                        );
                    }

                    if ($derniere['date_fin'] === null) {
                        if ($this->versCentimes($ancienne) === $this->versCentimes($nouvelle)) {
                            throw new RuntimeException(
                                "Le montant actuel est déjà {$ancienne} FC."
                            );
                        }

                        // Ferme la période en cours la veille de la nouvelle.
                        $stmt = $this->db->prepare(
                            "
                            UPDATE {$config['table']}
                            SET date_fin = CAST(:date_debut AS DATE) - 1
                            WHERE id = :id
                            "
                        );

                        $stmt->execute([
                            'date_debut' => $dateDebut,
                            'id' => $derniere['id'],
                        ]);
                    } elseif ($dateDebut <= (string) $derniere['date_fin']) {
                        throw new RuntimeException(
                            "La dernière période se termine le {$derniere['date_fin']} : "
                            . 'choisis une date postérieure.'
                        );
                    }
                }

                $stmt = $this->db->prepare(
                    "
                    INSERT INTO {$config['table']} (
                        {$config['fk']},
                        {$config['valeur']},
                        date_debut
                    )
                    VALUES (
                        :entite_id,
                        :valeur,
                        :date_debut
                    )
                    RETURNING id
                    "
                );

                $stmt->execute([
                    'entite_id' => $entiteId,
                    'valeur' => $nouvelle,
                    'date_debut' => $dateDebut,
                ]);

                $nouvelId = (int) $stmt->fetchColumn();

                AuditService::enregistrer(
                    $this->db,
                    $userId,
                    $config['action'],
                    $config['table'],
                    $nouvelId,
                    null,
                    $ancienne === null ? null : ['valeur' => $ancienne],
                    ['valeur' => $nouvelle, 'date_debut' => $dateDebut],
                    $motif
                );

                return [
                    'id' => $nouvelId,
                    'ancienne_valeur' => $ancienne,
                    'nouvelle_valeur' => $nouvelle,
                    'date_debut' => $dateDebut,
                ];
            }
        );
    }

    private function verifierDate(string $date): void
    {
        if (
            !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)
            || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])
        ) {
            throw new InvalidArgumentException(
                "Date invalide : {$date} (format attendu AAAA-MM-JJ)."
            );
        }
    }

    /**
     * Accepte 500, 500.5, "500,50", "1 500" et retourne "500.50".
     */
    private function normaliserMontant(
        int|float|string $valeur,
        bool $strictementPositif
    ): string {
        if (is_string($valeur)) {
            $propre = str_replace([' ', "\u{00A0}"], '', trim($valeur));
            $propre = str_replace(',', '.', $propre);

            if (!preg_match('/^\d+(\.\d{1,2})?$/', $propre)) {
                throw new InvalidArgumentException("Montant invalide : {$valeur}");
            }

            $valeur = (float) $propre;
        }

        $centimes = (int) round($valeur * 100);

        if ($centimes < 0 || ($strictementPositif && $centimes === 0)) {
            throw new InvalidArgumentException(
                $strictementPositif
                    ? 'Le montant doit être strictement positif.'
                    : 'Le montant ne peut pas être négatif.'
            );
        }

        return sprintf('%d.%02d', intdiv($centimes, 100), $centimes % 100);
    }

    private function versCentimes(string $montant): int
    {
        return (int) round((float) $montant * 100);
    }
}

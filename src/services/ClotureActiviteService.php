<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/ActiviteService.php';

class ClotureActiviteService extends ServiceBase
{
    /**
     * Règle confirmée : le retour de stock (éventuellement à zéro)
     * est enregistré avant la clôture de l'activité.
     *
     * false : sans retour, tout ce qui a été reçu serait compté vendu.
     */
    private const RETOUR_OBLIGATOIRE = true;

    private ActiviteService $activiteService;

    public function __construct()
    {
        parent::__construct();

        $this->activiteService = new ActiviteService();
    }

    /**
     * Clôture une activité : contrôle financier, commission,
     * fin de l'activité et libération du sac.
     *
     * Le retour de stock doit avoir été enregistré au préalable.
     * Si le montant remis diffère du CA théorique, un motif est
     * obligatoire et le contrôle est marqué « ecart_a_expliquer ».
     *
     * @return array<string, mixed> Résumé financier de l'activité.
     */
    public function cloturer(
        int $activiteId,
        int $userId,
        int|float|string $montantRemis,
        ?string $motifEcart = null,
        ?string $note = null
    ): array {
        $remisCentimes = $this->versCentimes($montantRemis);

        return $this->transaction(
            function () use (
                $activiteId,
                $userId,
                $remisCentimes,
                $motifEcart,
                $note
            ): array {
                $this->verifierUtilisateur($userId);

                // Verrouille l'activité pendant toute la clôture.
                $activite = $this->fetchOne(
                    "
                    SELECT
                        a.id,
                        a.statut,
                        a.vendeur_id,
                        a.sac_id,
                        j.site_id,
                        j.date_jour
                    FROM activites a
                    INNER JOIN journees j
                        ON j.id = a.journee_id
                    WHERE a.id = :id
                    FOR UPDATE OF a
                    ",
                    ['id' => $activiteId]
                );

                if ($activite === null) {
                    throw new RuntimeException('Activité introuvable.');
                }

                if ($activite['statut'] !== 'en_cours') {
                    throw new RuntimeException(
                        'Cette activité n’est pas en cours '
                        . "(statut : {$activite['statut']})."
                    );
                }

                $this->verifierAccesSite($userId, (int) $activite['site_id']);

                $retour = $this->fetchOne(
                    "
                    SELECT id
                    FROM retours
                    WHERE activite_id = :id
                    ",
                    ['id' => $activiteId]
                );

                if ($retour === null && self::RETOUR_OBLIGATOIRE) {
                    throw new RuntimeException(
                        'Enregistre d’abord le retour de stock '
                        . 'avant de clôturer l’activité.'
                    );
                }

                $calcul = $this->activiteService->calculer($activiteId);

                $caCentimes = (int) round($calcul['ca'] * 100);
                $ecartCentimes = $remisCentimes - $caCentimes;

                $motif = $motifEcart !== null ? trim($motifEcart) : '';

                if ($ecartCentimes !== 0 && $motif === '') {
                    throw new InvalidArgumentException(
                        'Écart de ' . $this->formaterCentimes($ecartCentimes)
                        . ' FC constaté : un motif est obligatoire.'
                    );
                }

                $statutControle = $ecartCentimes === 0
                    ? 'aucun_ecart'
                    : 'ecart_a_expliquer';

                // 1. Contrôle financier.
                $stmt = $this->db->prepare(
                    "
                    INSERT INTO controles_financiers (
                        activite_id,
                        ca_theorique,
                        montant_remis,
                        ecart,
                        statut,
                        motif_ecart,
                        controle_par,
                        note
                    )
                    VALUES (
                        :activite_id,
                        :ca_theorique,
                        :montant_remis,
                        :ecart,
                        :statut,
                        :motif_ecart,
                        :controle_par,
                        :note
                    )
                    "
                );

                $stmt->execute([
                    'activite_id' => $activiteId,
                    'ca_theorique' => $this->formaterCentimes($caCentimes),
                    'montant_remis' => $this->formaterCentimes($remisCentimes),
                    'ecart' => $this->formaterCentimes($ecartCentimes),
                    'statut' => $statutControle,
                    'motif_ecart' => $ecartCentimes === 0 ? null : $motif,
                    'controle_par' => $userId,
                    'note' => $note,
                ]);

                // 2. Commission (le montant est calculé par PostgreSQL
                //    pour respecter exactement la contrainte de la table).
                $stmt = $this->db->prepare(
                    "
                    INSERT INTO commissions (
                        activite_id,
                        vendeur_id,
                        ca,
                        taux,
                        montant,
                        date
                    )
                    VALUES (
                        :activite_id,
                        :vendeur_id,
                        CAST(:ca AS NUMERIC),
                        CAST(:taux AS NUMERIC),
                        ROUND(
                            CAST(:ca_calcul AS NUMERIC)
                            * CAST(:taux_calcul AS NUMERIC)
                            / 100,
                            2
                        ),
                        :date_jour
                    )
                    RETURNING montant
                    "
                );

                $taux = number_format(
                    (float) $calcul['taux_commission'],
                    2,
                    '.',
                    ''
                );

                $stmt->execute([
                    'activite_id' => $activiteId,
                    'vendeur_id' => $activite['vendeur_id'],
                    'ca' => $this->formaterCentimes($caCentimes),
                    'taux' => $taux,
                    'ca_calcul' => $this->formaterCentimes($caCentimes),
                    'taux_calcul' => $taux,
                    'date_jour' => $activite['date_jour'],
                ]);

                $commission = (float) $stmt->fetchColumn();

                // 3. Fin de l'activité.
                $stmt = $this->db->prepare(
                    "
                    UPDATE activites
                    SET statut = 'terminee',
                        date_fin = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                      AND statut = 'en_cours'
                    "
                );

                $stmt->execute(['id' => $activiteId]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'L’activité n’a pas pu être clôturée.'
                    );
                }

                // 4. Libération du sac.
                $stmt = $this->db->prepare(
                    "
                    UPDATE sacs
                    SET statut = 'libre',
                        updated_at = NOW()
                    WHERE id = :id
                      AND statut = 'en_utilisation'
                    "
                );

                $stmt->execute(['id' => $activite['sac_id']]);

                $resume = [
                    'activite_id' => $activiteId,
                    'ca_theorique' => $caCentimes / 100,
                    'montant_remis' => $remisCentimes / 100,
                    'ecart' => $ecartCentimes / 100,
                    'statut_controle' => $statutControle,
                    'transport' => $calcul['transport'],
                    'commission' => $commission,
                    'consommables' => $calcul['consommables'],
                    'benefice' => $calcul['benefice'],
                    'beneficiaire_benefice' => $calcul['beneficiaire_benefice'],
                ];

                AuditService::enregistrer(
                    $this->db,
                    $userId,
                    'activite_cloturee',
                    'activites',
                    $activiteId,
                    (int) $activite['site_id'],
                    ['statut' => 'en_cours'],
                    $resume,
                    $ecartCentimes === 0 ? null : $motif
                );

                return $resume;
            }
        );
    }

    /**
     * Convertit un montant saisi (12500, 12500.5, "12 500,50") en centimes.
     */
    private function versCentimes(int|float|string $montant): int
    {
        if (is_string($montant)) {
            $propre = str_replace([' ', "\u{00A0}"], '', trim($montant));
            $propre = str_replace(',', '.', $propre);

            if (!preg_match('/^\d+(\.\d{1,2})?$/', $propre)) {
                throw new InvalidArgumentException(
                    'Montant remis invalide : ' . $montant
                );
            }

            $montant = (float) $propre;
        }

        if ($montant < 0) {
            throw new InvalidArgumentException(
                'Le montant remis ne peut pas être négatif.'
            );
        }

        return (int) round($montant * 100);
    }

    /**
     * Formate des centimes en décimal texte : 1250050 -> "12500.50".
     */
    private function formaterCentimes(int $centimes): string
    {
        $signe = $centimes < 0 ? '-' : '';
        $absolu = abs($centimes);

        return sprintf(
            '%s%d.%02d',
            $signe,
            intdiv($absolu, 100),
            $absolu % 100
        );
    }
}

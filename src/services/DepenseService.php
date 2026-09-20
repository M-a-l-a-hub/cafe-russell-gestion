<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/AuditService.php';

/**
 * Enregistrement des dépenses générales (loyer, matériel, lait, sucre,
 * café, etc.).
 *
 * Une dépense est liée à un site ou globale à l'entreprise ($siteId = null).
 * Elle est enregistrée séparément : elle n'est jamais retirée du bénéfice
 * d'une activité vendeur. Elle interviendra dans les rapports globaux.
 *
 * Volontairement absent : la correction ou l'annulation d'une dépense.
 * Les règles (qui, avec quel motif) ne sont pas encore définies.
 */
class DepenseService extends ServiceBase
{
    /**
     * @param int|float|string $montant     montant en FC, strictement positif
     * @param int|null         $siteId      null = dépense globale
     * @param string|null      $dateDepense AAAA-MM-JJ, aujourd'hui par défaut
     *
     * @return int Identifiant de la dépense créée.
     */
    public function enregistrer(
        int $userId,
        int $categorieId,
        string $libelle,
        int|float|string $montant,
        ?int $siteId = null,
        ?string $dateDepense = null,
        ?string $justificatif = null,
        ?string $note = null
    ): int {
        $libelle = trim($libelle);

        if ($libelle === '') {
            throw new InvalidArgumentException('Le libellé de la dépense est obligatoire.');
        }

        if (mb_strlen($libelle) > 255) {
            throw new InvalidArgumentException(
                'Le libellé ne doit pas dépasser 255 caractères (utilise la note pour le détail).'
            );
        }

        $montantTexte = $this->normaliserMontant($montant);

        if ($dateDepense !== null) {
            $this->verifierDate($dateDepense);
        }

        return $this->transaction(
            function () use (
                $userId,
                $categorieId,
                $libelle,
                $montantTexte,
                $siteId,
                $dateDepense,
                $justificatif,
                $note
            ): int {
                $this->verifierUtilisateur($userId);

                $categorie = $this->fetchOne(
                    "
                    SELECT id, code, nom, actif
                    FROM categories_depenses
                    WHERE id = :id
                    ",
                    ['id' => $categorieId]
                );

                if ($categorie === null) {
                    throw new RuntimeException('Catégorie de dépense introuvable.');
                }

                if (!(bool) $categorie['actif']) {
                    throw new RuntimeException(
                        "La catégorie {$categorie['nom']} est désactivée."
                    );
                }

                if ($siteId !== null) {
                    $site = $this->fetchOne(
                        "SELECT id FROM sites WHERE id = :id",
                        ['id' => $siteId]
                    );

                    if ($site === null) {
                        throw new RuntimeException('Site introuvable.');
                    }
                }

                $stmt = $this->db->prepare(
                    "
                    INSERT INTO depenses (
                        site_id,
                        categorie_id,
                        libelle,
                        montant,
                        date_depense,
                        effectue_par,
                        justificatif,
                        note
                    )
                    VALUES (
                        :site_id,
                        :categorie_id,
                        :libelle,
                        :montant,
                        COALESCE(CAST(:date_depense AS DATE), CURRENT_DATE),
                        :effectue_par,
                        :justificatif,
                        :note
                    )
                    RETURNING id, date_depense
                    "
                );

                $stmt->execute([
                    'site_id' => $siteId,
                    'categorie_id' => $categorieId,
                    'libelle' => $libelle,
                    'montant' => $montantTexte,
                    'date_depense' => $dateDepense,
                    'effectue_par' => $userId,
                    'justificatif' => $justificatif,
                    'note' => $note,
                ]);

                $ligne = $stmt->fetch();
                $depenseId = (int) $ligne['id'];

                AuditService::enregistrer(
                    $this->db,
                    $userId,
                    'depense_creee',
                    'depenses',
                    $depenseId,
                    $siteId,
                    null,
                    [
                        'categorie' => $categorie['code'],
                        'libelle' => $libelle,
                        'montant' => $montantTexte,
                        'date_depense' => (string) $ligne['date_depense'],
                        'globale' => $siteId === null,
                    ]
                );

                return $depenseId;
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
    private function normaliserMontant(int|float|string $valeur): string
    {
        if (is_string($valeur)) {
            $propre = str_replace([' ', "\u{00A0}"], '', trim($valeur));
            $propre = str_replace(',', '.', $propre);

            if (!preg_match('/^\d+(\.\d{1,2})?$/', $propre)) {
                throw new InvalidArgumentException("Montant invalide : {$valeur}");
            }

            $valeur = (float) $propre;
        }

        $centimes = (int) round($valeur * 100);

        if ($centimes <= 0) {
            throw new InvalidArgumentException('Le montant doit être strictement positif.');
        }

        return sprintf('%d.%02d', intdiv($centimes, 100), $centimes % 100);
    }
}

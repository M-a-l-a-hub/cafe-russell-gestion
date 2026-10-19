<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';

/**
 * Vendeurs : création, catégorie de transport, activation.
 *
 * Un vendeur n'appartient à aucun site (il peut travailler sur
 * plusieurs) : ces opérations ne sont donc pas limitées par site.
 * Décision prise pour ce service, à confirmer avec Russell si besoin :
 * la création et le changement de catégorie sont ouverts à tout
 * utilisateur connecté (Russell ou un responsable), puisque c'est le
 * responsable qui accueille les vendeurs sur le terrain. Seuls les
 * tarifs par catégorie restent réservés à Russell (TarifService).
 */
final class VendeurService extends ServiceBase
{
    public function creer(
        string $nom,
        ?string $prenom,
        ?string $telephone,
        int $categorieTransportId,
        ?string $periodeHabituelle,
        int $userId,
        int $siteId
    ): int {
        $this->verifierUtilisateur($userId);
        $this->verifierCategorieTransport($categorieTransportId);

        if ($periodeHabituelle !== null) {
            $this->verifierPeriodeActivite($periodeHabituelle);
        }

        return $this->transaction(function () use (
            $nom,
            $prenom,
            $telephone,
            $categorieTransportId,
            $periodeHabituelle,
            $userId,
            $siteId
        ): int {
            $code = $this->genererCode($nom, $prenom);

            $stmt = $this->db->prepare(
                '
                INSERT INTO vendeurs (
                    code, nom, prenom, telephone,
                    periode_habituelle, categorie_transport_id
                )
                VALUES (
                    :code, :nom, :prenom, :telephone,
                    :periode_habituelle, :categorie_transport_id
                )
                RETURNING id
                '
            );
            $stmt->execute([
                'code' => $code,
                'nom' => $nom,
                'prenom' => $prenom,
                'telephone' => $telephone,
                'periode_habituelle' => $periodeHabituelle,
                'categorie_transport_id' => $categorieTransportId,
            ]);

            $id = (int) $stmt->fetchColumn();

            $affectation = $this->db->prepare(
                'INSERT INTO site_vendeurs (site_id, vendeur_id, affecte_par) VALUES (:site_id, :vendeur_id, :user_id)'
            );
            $affectation->execute(['site_id' => $siteId, 'vendeur_id' => $id, 'user_id' => $userId]);

            AuditService::enregistrer(
                $this->db,
                $userId,
                'vendeur_cree',
                'vendeurs',
                $id,
                null,
                null,
                ['code' => $code, 'nom' => $nom, 'prenom' => $prenom]
            );

            return $id;
        });
    }

    /**
     * Construit un code lisible (ex. « KABILA-DAVID »), rendu unique en
     * ajoutant un numéro si nécessaire (« KABILA-DAVID-2 »).
     */
    private function genererCode(string $nom, ?string $prenom): string
    {
        $translit = static function (string $texte): string {
            $texte = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texte);
            $texte = strtoupper((string) preg_replace('/[^A-Za-z]+/', '-', $texte));

            return trim($texte, '-');
        };

        $base = $translit($nom);

        if ($prenom !== null && $prenom !== '') {
            $base .= '-' . $translit($prenom);
        }

        if ($base === '') {
            $base = 'VENDEUR';
        }

        $code = $base;
        $suffixe = 1;

        while ($this->fetchOne('SELECT 1 FROM vendeurs WHERE code = :code', ['code' => $code]) !== null) {
            $suffixe++;
            $code = $base . '-' . $suffixe;
        }

        return $code;
    }

    /**
     * Change la catégorie de transport d'un vendeur. S'applique à tous
     * les sites où il travaille : le tarif payé dépend de la catégorie
     * au moment de chaque ravitaillement, l'historique n'est donc pas
     * affecté rétroactivement.
     */
    public function changerCategorieTransport(
        int $vendeurId,
        int $categorieTransportId,
        int $userId,
        ?string $motif
    ): void {
        $this->verifierUtilisateur($userId);
        $this->verifierCategorieTransport($categorieTransportId);

        $this->transaction(function () use ($vendeurId, $categorieTransportId, $userId, $motif): void {
            $vendeur = $this->fetchOne(
                'SELECT id, categorie_transport_id, actif FROM vendeurs WHERE id = :id FOR UPDATE',
                ['id' => $vendeurId]
            );

            if ($vendeur === null) {
                throw new RuntimeException('Vendeur introuvable.');
            }

            if (!(bool) $vendeur['actif']) {
                throw new RuntimeException('Ce vendeur est désactivé.');
            }

            if ((int) $vendeur['categorie_transport_id'] === $categorieTransportId) {
                return;
            }

            $stmt = $this->db->prepare(
                '
                UPDATE vendeurs
                SET categorie_transport_id = :categorie_transport_id, updated_at = NOW()
                WHERE id = :id
                '
            );
            $stmt->execute([
                'categorie_transport_id' => $categorieTransportId,
                'id' => $vendeurId,
            ]);

            AuditService::enregistrer(
                $this->db,
                $userId,
                'vendeur_categorie_transport_modifiee',
                'vendeurs',
                $vendeurId,
                null,
                ['categorie_transport_id' => (int) $vendeur['categorie_transport_id']],
                ['categorie_transport_id' => $categorieTransportId],
                $motif
            );
        });
    }

    public function desactiver(int $vendeurId, int $siteId, int $userId, string $motif): void
    {
        $this->verifierUtilisateur($userId);

        $this->transaction(function () use ($vendeurId, $siteId, $userId, $motif): void {
            $vendeur = $this->fetchOne(
                'SELECT id, actif FROM vendeurs WHERE id = :id FOR UPDATE',
                ['id' => $vendeurId]
            );

            if ($vendeur === null) {
                throw new RuntimeException('Vendeur introuvable.');
            }

            if (!(bool) $vendeur['actif']) {
                return;
            }

            $affectation = $this->fetchOne(
                'SELECT id, actif FROM site_vendeurs WHERE vendeur_id = :vendeur_id AND site_id = :site_id FOR UPDATE',
                ['vendeur_id' => $vendeurId, 'site_id' => $siteId]
            );
            if ($affectation === null || !(bool) $affectation['actif']) {
                throw new RuntimeException('Ce vendeur n’est pas affecté à ce site.');
            }

            $enCours = $this->fetchOne(
                "SELECT a.id FROM activites a INNER JOIN journees j ON j.id = a.journee_id WHERE a.vendeur_id = :id AND j.site_id = :site_id AND a.statut = 'en_cours' LIMIT 1",
                ['id' => $vendeurId, 'site_id' => $siteId]
            );

            if ($enCours !== null) {
                throw new RuntimeException(
                    'Ce vendeur a une activité en cours : clôture-la avant de le désactiver.'
                );
            }

            $stmt = $this->db->prepare(
                'UPDATE site_vendeurs SET actif = FALSE, desaffecte_par = :user_id, date_desaffectation = NOW() WHERE vendeur_id = :vendeur_id AND site_id = :site_id'
            );
            $stmt->execute(['user_id' => $userId, 'vendeur_id' => $vendeurId, 'site_id' => $siteId]);

            AuditService::enregistrer(
                $this->db,
                $userId,
                'vendeur_desactive',
                'vendeurs',
                $vendeurId,
                null,
                ['site_id' => $siteId, 'affectation_active' => true],
                ['site_id' => $siteId, 'affectation_active' => false],
                $motif
            );
        });
    }

    private function verifierCategorieTransport(int $id): void
    {
        $categorie = $this->fetchOne(
            'SELECT id FROM categories_transport WHERE id = :id AND actif = TRUE',
            ['id' => $id]
        );

        if ($categorie === null) {
            throw new InvalidArgumentException('Catégorie de transport introuvable ou inactive.');
        }
    }
}

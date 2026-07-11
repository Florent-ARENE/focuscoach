<?php
/**
 * ============================================
 * CLASSE PACKAGE — Forfaits à jetons (§7)
 * ============================================
 * Un `package` = N séances (`sessions_count`) d'UN service, à un prix forfait.
 * Un `package_purchase` = l'achat d'un forfait par un client : porte les jetons
 * (`credits_total`/`credits_used`), un `manage_token` d'accès à l'espace pack
 * (`pack.php?token=…`), et un cycle de vie :
 *
 *   pending_payment ──[Stripe OK]──► active
 *                                      ├─[credits_used == credits_total]──► exhausted
 *                                      └─[NOW() > expires_at]────────────► expired
 *
 * `expires_at = purchased_at + packages.validity_days`.
 *
 * DÉCISION (2.8.x) — mode paiement, cohérent avec le tunnel à l'unité :
 *   - `stripe`  (price_id + Stripe actif) → achat créé `pending_payment`,
 *     activé par le webhook après paiement ;
 *   - `bypass`  (pas de price_id / Stripe off) → achat créé **directement
 *     `active`** (crédits utilisables tout de suite, paiement réconcilié
 *     hors-ligne par le coach) — même esprit que le booking `pending` validé
 *     en admin. Tant que les produits Stripe n'existent pas, c'est ce mode.
 */

namespace App;

use PDO;

class Package
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** SELECT de base : le forfait + la prestation incluse. */
    private const BASE_SELECT =
        "SELECT p.*, s.name AS service_name, s.slug AS service_slug,
                s.duration_min, s.buffer_after_min
           FROM packages p
           JOIN services s ON s.id = p.service_id";

    /** Forfaits actifs (pour l'affichage / l'achat), triés. */
    public function getActivePackages(): array
    {
        return $this->db->query(
            self::BASE_SELECT . " WHERE p.is_active = 1 ORDER BY p.sort_order, p.id"
        )->fetchAll();
    }

    /** Un forfait par id (avec sa prestation), ou null. */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(self::BASE_SELECT . " WHERE p.id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Crée un achat de forfait. `$mode` = paymentMode() du forfait :
     *   'stripe' → statut `pending_payment` (activé au webhook) ;
     *   'bypass' → statut `active` (crédits dispo, paiement hors-ligne).
     *
     * @return array{success:bool, id?:int, token?:string, status?:string, message?:string}
     */
    public function createPurchase(int $packageId, string $name, string $email, string $mode): array
    {
        $pkg = $this->getById($packageId);
        if (!$pkg || (int) $pkg['is_active'] !== 1) {
            return ['success' => false, 'message' => 'Forfait introuvable ou inactif.'];
        }

        $token     = bin2hex(random_bytes((int) (MANAGE_TOKEN_LENGTH / 2)));
        $status    = ($mode === 'stripe') ? 'pending_payment' : 'active';
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int) $pkg['validity_days'] . ' days'));

        $stmt = $this->db->prepare(
            "INSERT INTO package_purchases
                (package_id, client_name, client_email, credits_total, credits_used,
                 manage_token, status, expires_at)
             VALUES (:pid, :name, :email, :total, 0, :token, :status, :expires)"
        );
        $stmt->execute([
            ':pid'     => $packageId,
            ':name'    => $name,
            ':email'   => $email,
            ':total'   => (int) $pkg['sessions_count'],
            ':token'   => $token,
            ':status'  => $status,
            ':expires' => $expiresAt,
        ]);

        return [
            'success' => true,
            'id'      => (int) $this->db->lastInsertId(),
            'token'   => $token,
            'status'  => $status,
        ];
    }

    /**
     * Achat par token (espace pack). Ajoute les champs dérivés :
     * `credits_remaining` et `is_usable` (active + crédits restants + non expiré).
     */
    public function getByToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT pp.*, p.name AS package_name, p.slug AS package_slug,
                    p.service_id, p.sessions_count,
                    s.name AS service_name, s.duration_min, s.buffer_after_min
               FROM package_purchases pp
               JOIN packages p ON p.id = pp.package_id
               JOIN services s ON s.id = p.service_id
              WHERE pp.manage_token = :token"
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['credits_remaining'] = max(0, (int) $row['credits_total'] - (int) $row['credits_used']);
        $notExpired = empty($row['expires_at']) || strtotime($row['expires_at']) > time();
        $row['is_usable'] = ($row['status'] === 'active' && $row['credits_remaining'] > 0 && $notExpired);
        return $row;
    }

    /** Attache l'id de session Stripe à un achat (avant redirection Checkout). */
    public function attachStripeSession(int $purchaseId, string $sessionId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE package_purchases SET stripe_session_id = :sid WHERE id = :id"
        );
        $stmt->execute([':sid' => $sessionId, ':id' => $purchaseId]);
    }

    /** Achat par session Stripe (retour navigateur / webhook). */
    public function getByStripeSession(string $sessionId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM package_purchases WHERE stripe_session_id = :sid"
        );
        $stmt->execute([':sid' => $sessionId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Active un achat après paiement Stripe (`pending_payment` → `active`).
     * Idempotent : ne touche que les lignes encore `pending_payment`.
     *
     * @return bool true si une ligne a été activée (false = déjà traité / absent).
     */
    public function activateByStripeSession(string $sessionId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE package_purchases
                SET status = 'active'
              WHERE stripe_session_id = :sid AND status = 'pending_payment'"
        );
        $stmt->execute([':sid' => $sessionId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Consomme 1 jeton pour une séance. Décrément ATOMIQUE : n'incrémente que si
     * l'achat est `active`, a des crédits restants et n'est pas expiré ; bascule
     * en `exhausted` sur le dernier jeton. Évite toute sur-consommation (race).
     *
     * @return array{success:bool, message?:string}
     */
    public function consumeCredit(int $purchaseId): array
    {
        // ⚠️ `status` AVANT `credits_used` : en MySQL une assignation voit la
        // NOUVELLE valeur des colonnes assignées à sa gauche. En mettant status
        // en premier, le CASE lit l'ANCIEN credits_used → pas de double-comptage.
        $stmt = $this->db->prepare(
            "UPDATE package_purchases
                SET status = CASE WHEN credits_used + 1 >= credits_total
                                  THEN 'exhausted' ELSE status END,
                    credits_used = credits_used + 1
              WHERE id = :id
                AND status = 'active'
                AND credits_used < credits_total
                AND (expires_at IS NULL OR expires_at > NOW())"
        );
        $stmt->execute([':id' => $purchaseId]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Forfait épuisé, expiré ou inactif.'];
        }
        return ['success' => true];
    }

    /** Rend 1 jeton (annulation d'une séance issue d'un pack). Ré-active si besoin. */
    public function refundCredit(int $purchaseId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE package_purchases
                SET credits_used = GREATEST(0, credits_used - 1),
                    status = CASE WHEN status = 'exhausted'
                                       AND (expires_at IS NULL OR expires_at > NOW())
                                  THEN 'active' ELSE status END
              WHERE id = :id"
        );
        $stmt->execute([':id' => $purchaseId]);
    }

    /**
     * Bascule en `expired` les achats actifs dont la validité est dépassée.
     * Appelé par le cron (lecture/écriture sans effet de bord côté client).
     *
     * @return int nombre d'achats expirés.
     */
    public function expireStalePurchases(): int
    {
        $stmt = $this->db->query(
            "UPDATE package_purchases
                SET status = 'expired'
              WHERE status IN ('active', 'pending_payment')
                AND expires_at IS NOT NULL
                AND expires_at < NOW()"
        );
        return $stmt->rowCount();
    }
}

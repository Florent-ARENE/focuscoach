<?php
/**
 * ============================================
 * FORFAITS (§7) — ESPACE PACK (dashboard client)
 * ============================================
 * Le client arrive par le lien reçu à l'achat : pack.php?token=…
 * (ou retour Stripe : pack.php?cs={CHECKOUT_SESSION_ID}).
 *
 * Affiche : jetons restants/utilisés, validité, statut, séances déjà
 * posées, et un bouton « Réserver une séance » (consomme 1 jeton — le
 * tunnel taggé `pack=<token>` gère la consommation, cf. §7 phase 4).
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/_shell.php';

use App\Package;
use App\Database;
use App\Helpers;
use App\Icons;

$packageModel = new Package();

// Retour Stripe (?cs=…) → on retrouve l'achat par sa session, sinon par token.
$cs    = isset($_GET['cs']) ? Helpers::sanitize((string) $_GET['cs']) : '';
$token = isset($_GET['token']) ? Helpers::sanitize((string) $_GET['token']) : '';

$purchase = null;
if ($cs !== '' && $cs !== '{CHECKOUT_SESSION_ID}') {
    $byCs = $packageModel->getByStripeSession($cs);
    if ($byCs) {
        $token    = $byCs['manage_token'];
        $purchase = $packageModel->getByToken($token);
    }
} elseif ($token !== '' && strlen($token) === MANAGE_TOKEN_LENGTH) {
    $purchase = $packageModel->getByToken($token);
}

$pageTitle = 'Mon forfait';

// Séances déjà réservées avec ce forfait (lecture directe — page unique).
$sessions = [];
if ($purchase) {
    $stmt = Database::getInstance()->prepare(
        "SELECT slot_date, slot_time_start, slot_time_end, status
           FROM bookings WHERE package_purchase_id = :id ORDER BY slot_date, slot_time_start"
    );
    $stmt->execute([':id' => (int) $purchase['id']]);
    $sessions = $stmt->fetchAll();
}

// [icône, libellé, classe badge réutilisée de main.css (couleurs existantes)]
$statusMeta = [
    'pending_payment' => ['hourglass',    'Paiement en attente', 'pending'],
    'active'          => ['circle-check', 'Actif',               'confirmed'],
    'exhausted'       => ['check',        'Épuisé',              'completed'],
    'expired'         => ['circle-x',     'Expiré',              'cancelled'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Helpers::escape($pageTitle) ?> | <?= Helpers::escape(siteConfig()['site_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/booking-v3.css">
    <link rel="stylesheet" href="../../assets/css/manage.css">
    <?= pwaHead() ?>
</head>
<body class="booking-page">
    <?= booking_header(Helpers::escape(BASE_URL), 'Retour au site') ?>

    <main class="booking-main">
        <h1 class="page-title text-center"><?= Helpers::escape($pageTitle) ?></h1>

        <?php if ($purchase): ?>
        <p class="text-center bv3-help-note"><a href="espace.php?token=<?= Helpers::escape($purchase['manage_token']) ?>"><?= Icons::svg('arrow-left', 14, 'icon-inline') ?>Voir tout mon espace</a></p>
        <?php endif; ?>

        <?php if (!empty($_GET['booked'])): ?>
        <div class="bv3-pack-banner"><?= Icons::svg('circle-check', 16, 'icon-inline') ?> Séance réservée ! Elle apparaît ci-dessous et 1 jeton a été consommé.</div>
        <?php endif; ?>

        <?php if (!$purchase): ?>
            <div class="bv3-empty">
                Forfait introuvable ou lien invalide.
                <a href="index.php">Voir les forfaits</a>.
            </div>
        <?php else:
            [$stIcon, $stLabel, $stBadge] = $statusMeta[$purchase['status']] ?? ['circle', ucfirst($purchase['status']), 'pending'];
            $remaining = (int) $purchase['credits_remaining'];
            $total     = (int) $purchase['credits_total'];
            $used      = (int) $purchase['credits_used'];
        ?>
            <div class="booking-card">
                <div class="booking-card-header">
                    <h2><?= Helpers::escape($purchase['package_name']) ?></h2>
                    <span class="status-badge <?= Helpers::escape($stBadge) ?>">
                        <?= Icons::svg($stIcon, 14, 'icon-inline') ?><?= Helpers::escape($stLabel) ?>
                    </span>
                </div>

                <div class="booking-card-body">
                    <p class="pack-credits">
                        <strong class="pack-credits-num"><?= $remaining ?></strong>
                        jeton<?= $remaining > 1 ? 's' : '' ?> restant<?= $remaining > 1 ? 's' : '' ?>
                        <span class="text-muted">sur <?= $total ?> (<?= $used ?> utilisé<?= $used > 1 ? 's' : '' ?>)</span>
                    </p>

                    <div class="booking-info-grid">
                        <div class="info-item">
                            <span class="info-icon"><?= Icons::svg('clipboard-list', 18) ?></span>
                            <div class="info-content">
                                <span class="info-label">Prestation</span>
                                <span class="info-value"><?= Helpers::escape($purchase['service_name']) ?> (<?= Helpers::escape((string) (int) $purchase['duration_min']) ?> min)</span>
                            </div>
                        </div>
                        <div class="info-item">
                            <span class="info-icon"><?= Icons::svg('calendar', 18) ?></span>
                            <div class="info-content">
                                <span class="info-label">Valable jusqu'au</span>
                                <span class="info-value"><?= $purchase['expires_at'] ? Helpers::escape(Helpers::formatDateFr(substr($purchase['expires_at'], 0, 10))) : '—' ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($purchase['status'] === 'pending_payment'): ?>
                        <div class="pending-notice">
                            <span class="notice-icon"><?= Icons::svg('hourglass', 18, 'icon-inline') ?></span>
                            <span>Votre paiement est en cours de confirmation. Vos jetons seront disponibles dès qu'il sera validé.</span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($purchase['is_usable']): ?>
                <div class="booking-card-actions">
                    <a class="btn btn-primary" href="date.php?service=<?= Helpers::escape((string) (int) $purchase['service_id']) ?>&amp;pack=<?= Helpers::escape($purchase['manage_token']) ?>">
                        <?= Icons::svg('calendar', 18, 'icon-inline') ?>Réserver une séance
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($sessions)): ?>
            <div class="booking-card">
                <div class="booking-card-header"><h2>Séances réservées</h2></div>
                <div class="booking-card-body">
                    <ul class="bv3-dates">
                        <?php foreach ($sessions as $s): ?>
                            <li class="bv3-date">
                                <span class="bv3-date-day"><?= Helpers::escape(Helpers::formatDateFr($s['slot_date'])) ?></span>
                                <span class="bv3-date-count"><?= Helpers::escape(Helpers::formatTimeSlot($s['slot_time_start'], $s['slot_time_end'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?= booking_footer() ?>

    <script src="../../assets/js/icons.js"></script>
    <?= pwaRegister() ?>
</body>
</html>

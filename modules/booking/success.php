<?php
/**
 * ============================================
 * BOOKING v3 — CONFIRMATION
 * ============================================
 * Affiche le récap du booking créé. Lien vers l'espace de gestion
 * (manage.php?token=…) — toujours côté booking legacy tant que §7
 * n'a pas ajouté pack.php / refacto manage.
 */
require_once __DIR__ . '/../../includes/init.php';

use App\Helpers;
use App\Icons;

$result = $_SESSION['booking_result'] ?? null;
if (!$result || empty($result['booking_id'])) {
    header('Location: index.php');
    exit;
}
$draft = $result['draft'];

$pageTitle = 'Demande envoyée';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= Helpers::csrfMeta() ?>
    <title><?= Helpers::escape($pageTitle) ?> | <?= Helpers::escape(siteConfig()['site_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/booking-v3.css">
    <?= pwaHead() ?>
</head>
<body>
    <header class="booking-header">
        <div class="booking-header-content">
            <a href="../../" class="booking-logo"><?= brandWordmark() ?></a>
            <a href="../../" class="back-link"><?= Icons::svg('arrow-left', 20, 'icon-inline') ?>Retour au site</a>
        </div>
    </header>

    <main class="booking-main">
        <div class="bv3-success">
            <div class="bv3-success-icon"><?= Icons::svg('check', 32) ?></div>
            <h1 class="page-title"><?= Helpers::escape($pageTitle) ?></h1>
            <p class="page-subtitle">
                Votre demande de rendez-vous a été enregistrée. Vous recevrez un email
                de confirmation dès que votre créneau sera validé.
            </p>

            <dl class="bv3-recap-list">
                <dt>Prestation</dt>
                <dd><?= Helpers::escape($draft['service_name']) ?> · <?= Helpers::escape((string) (int) $draft['duration_min']) ?> min</dd>
                <dt>Date</dt>
                <dd><?= Helpers::escape(Helpers::formatDateFr($draft['slot_date'])) ?></dd>
                <dt>Horaire</dt>
                <dd><?= Helpers::escape(Helpers::formatTimeSlot($draft['slot_time_start'], $draft['slot_time_end'])) ?></dd>
                <dt>Statut</dt>
                <dd>
                    <span class="status-badge pending"><?= Icons::svg('hourglass', 14, 'icon-inline') ?>En attente</span>
                </dd>
            </dl>

            <?php if (!empty($result['manage_token'])): ?>
            <p class="bv3-manage-link">
                <a class="btn btn-secondary" href="manage.php?token=<?= Helpers::escape($result['manage_token']) ?>">
                    Gérer mon rendez-vous
                </a>
            </p>
            <?php endif; ?>
        </div>
    </main>

    <footer class="booking-footer">
        <p>© <?= date('Y') ?> <?= Helpers::escape(siteConfig()['site_name']) ?></p>
    </footer>

    <script src="../../assets/js/icons.js"></script>
    <?= pwaRegister() ?>
</body>
</html>

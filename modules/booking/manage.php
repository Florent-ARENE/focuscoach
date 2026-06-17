<?php
/**
 * ============================================
 * BOOKING v3 — ESPACE CLIENT (gestion du RDV par token)
 * ============================================
 * Le client arrive via le lien envoyé par mail / affiché à la
 * confirmation : modules/booking/manage.php?token=…
 *
 * v3 minimal : affichage du récap + annulation + RGPD.
 *
 * ⚠️ Reschedule client temporairement désactivé depuis la purge
 * 2.6.0 (suppression de `calendar-module.js` legacy et du tunnel
 * v2 qui partageait son endpoint). Le rebranchement du reschedule
 * sur l'algo v3 (`api/booking-v3-slots.php?service=<id>&month=…`)
 * est noté comme dette technique — à traiter quand §6/§7 auront
 * stabilisé le pipeline. Pendant ce temps : l'admin peut faire le
 * reschedule côté back-office (`Booking::reschedule()` reste en
 * place), et le client peut annuler puis re-réserver.
 */
require_once __DIR__ . '/../../includes/init.php';

use App\Booking;
use App\Helpers;
use App\Icons;

$pageTitle = 'Gérer mon rendez-vous';
$token     = $_GET['token'] ?? '';
$booking   = null;
$error     = null;

if (empty($token) || strlen($token) !== 64) {
    $error = 'Lien invalide ou expiré.';
} else {
    $bookingModel = new Booking();
    $booking      = $bookingModel->getByToken($token);
    if (!$booking) {
        $error = 'Réservation non trouvée.';
    }
}

$canCancel = $booking && in_array($booking['status'], ['pending', 'confirmed'], true);

$statusIcons = [
    'pending'         => 'hourglass',
    'pending_payment' => 'hourglass',
    'confirmed'       => 'circle-check',
    'cancelled'       => 'circle-x',
    'completed'       => 'check',
    'expired'         => 'circle-x',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= Helpers::csrfMeta() ?>
    <title><?= Helpers::escape($pageTitle) ?> | <?= Helpers::escape(siteConfig()['site_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/booking-v3.css">
    <link rel="stylesheet" href="../../assets/css/manage.css">
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
        <h1 class="page-title text-center"><?= Helpers::escape($pageTitle) ?></h1>

        <?php if ($error): ?>
            <div class="manage-container">
                <div class="error-card">
                    <div class="error-icon"><?= Icons::svg('circle-x', 32) ?></div>
                    <h2>Oups !</h2>
                    <p><?= Helpers::escape($error) ?></p>
                    <a href="./" class="btn btn-primary">Prendre un nouveau rendez-vous</a>
                </div>
            </div>
        <?php else: ?>
            <div class="manage-container" id="manage-app" data-token="<?= Helpers::escape($token) ?>">

                <div class="booking-card" id="booking-details">
                    <div class="booking-card-header">
                        <h2>Votre rendez-vous</h2>
                        <?php $iconName = $statusIcons[$booking['status']] ?? 'circle'; ?>
                        <span class="status-badge <?= Helpers::escape($booking['status']) ?>">
                            <?= Icons::svg($iconName, 14, 'icon-inline') ?>
                            <?= Helpers::escape($booking['status_info']['label'] ?? ucfirst($booking['status'])) ?>
                        </span>
                    </div>

                    <div class="booking-card-body">
                        <div class="booking-info-grid">
                            <div class="info-item">
                                <span class="info-icon"><?= Icons::svg('calendar', 18) ?></span>
                                <div class="info-content">
                                    <span class="info-label">Date</span>
                                    <span class="info-value"><?= Helpers::escape($booking['formatted_date']) ?></span>
                                </div>
                            </div>

                            <div class="info-item">
                                <span class="info-icon"><?= Icons::svg('clock', 18) ?></span>
                                <div class="info-content">
                                    <span class="info-label">Horaire</span>
                                    <span class="info-value"><?= Helpers::escape($booking['formatted_time']) ?></span>
                                </div>
                            </div>

                            <div class="info-item">
                                <span class="info-icon"><?= Icons::svg('clipboard-list', 18) ?></span>
                                <div class="info-content">
                                    <span class="info-label">Prestation</span>
                                    <span class="info-value"><?= Helpers::escape($booking['service_name']) ?></span>
                                </div>
                            </div>

                            <div class="info-item">
                                <span class="info-icon"><?= Icons::svg('user', 18) ?></span>
                                <div class="info-content">
                                    <span class="info-label">Nom</span>
                                    <span class="info-value"><?= Helpers::escape($booking['visitor_name']) ?></span>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($booking['subject'])): ?>
                        <div class="booking-subject">
                            <strong>Objet :</strong> <?= Helpers::escape($booking['subject']) ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($booking['status'] === 'pending'): ?>
                        <div class="pending-notice">
                            <span class="notice-icon"><?= Icons::svg('hourglass', 18, 'icon-inline') ?></span>
                            <span>Votre demande est en attente de validation. Vous recevrez un email de confirmation.</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($canCancel): ?>
                    <div class="booking-card-actions">
                        <button type="button" class="btn btn-danger" id="btn-cancel">
                            <?= Icons::svg('circle-x', 18, 'icon-inline') ?>Annuler le rendez-vous
                        </button>
                    </div>
                    <p class="text-muted text-center" style="margin-top:0.75rem; font-size:0.9rem;">
                        Pour déplacer ce rendez-vous, contactez directement <?= Helpers::escape(siteConfig()['admin_email']) ?>.
                    </p>
                    <?php else: ?>
                    <div class="booking-card-footer">
                        <p class="text-muted">Ce rendez-vous ne peut plus être modifié.</p>
                        <a href="./" class="btn btn-primary">Prendre un nouveau rendez-vous</a>
                    </div>
                    <?php endif; ?>

                    <!-- RGPD — Bouton suppression des données (art. 17) -->
                    <div class="rgpd-section">
                        <details>
                            <summary>
                                <svg class="rgpd-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                </svg>
                                <span>Protection des données personnelles</span>
                                <svg class="details-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </summary>
                            <div class="rgpd-panel">
                                <p>
                                    Conformément au RGPD (art. 17), vous pouvez demander la suppression de vos données personnelles associées à ce rendez-vous.
                                </p>
                                <button type="button" class="btn btn-sm btn-rgpd-delete" id="btn-delete-data">
                                    Supprimer mes données
                                </button>
                            </div>
                        </details>
                    </div>
                </div>

                <!-- Modale suppression RGPD -->
                <div id="delete-data-modal" class="delete-modal" style="display:none;">
                    <div class="delete-modal__box">
                        <h3>Supprimer mes données personnelles</h3>
                        <p class="delete-modal__intro">
                            Cette action est <strong>irréversible</strong>. Les données suivantes seront supprimées :
                        </p>
                        <ul class="delete-modal__list">
                            <li>Votre nom, email, téléphone, organisation</li>
                            <li>L'objet et le message de votre demande</li>
                            <li>Votre adresse IP et données de navigation</li>
                        </ul>
                        <p class="delete-modal__retain">
                            Seuls la date du créneau et l'identifiant de prestation seront conservés de manière anonyme à des fins statistiques.
                        </p>
                        <div class="delete-modal__field">
                            <label class="delete-modal__label">
                                Pour confirmer, saisissez votre adresse email :
                            </label>
                            <input type="email" id="delete-confirm-email" class="delete-modal__input" placeholder="votre@email.fr">
                        </div>
                        <div class="delete-modal__actions">
                            <button type="button" id="btn-delete-modal-close" class="btn btn-secondary btn-sm">Annuler</button>
                            <button type="button" id="btn-confirm-delete" class="btn btn-danger btn-sm">Supprimer définitivement</button>
                        </div>
                    </div>
                </div>

                <!-- Modal de confirmation d'annulation -->
                <div class="modal-overlay" id="cancel-modal" style="display:none;">
                    <div class="modal-content">
                        <h3>Confirmer l'annulation</h3>
                        <p>Êtes-vous sûr de vouloir annuler ce rendez-vous ?</p>
                        <p class="text-muted">Cette action est irréversible.</p>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" id="btn-cancel-modal-close">Non, garder le RDV</button>
                            <button type="button" class="btn btn-danger" id="btn-confirm-cancel">Oui, annuler</button>
                        </div>
                    </div>
                </div>

                <!-- Message de succès -->
                <div class="success-card" id="success-card" style="display:none;">
                    <div class="success-icon"><?= Icons::svg('check', 32) ?></div>
                    <h2 id="success-title">Opération réussie</h2>
                    <p id="success-message"></p>
                    <div class="mt-3">
                        <a href="../../" class="btn btn-primary">Retour au site</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer class="booking-footer">
        <p>&copy; <?= date('Y') ?> <?= Helpers::escape(siteConfig()['site_name']) ?></p>
    </footer>

    <?php if ($booking && $canCancel): ?>
    <script src="../../assets/js/icons.js"></script>
    <script src="../../assets/js/manage.js"></script>
    <?php endif; ?>
    <?= pwaRegister() ?>
</body>
</html>

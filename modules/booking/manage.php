<?php
/**
 * ============================================
 * BOOKING v3 — ESPACE CLIENT (gestion du RDV par token)
 * ============================================
 * Le client arrive via le lien envoyé par mail / affiché à la
 * confirmation : modules/booking/manage.php?token=…
 *
 * v3 : affichage du récap + déplacement (reschedule) + annulation + RGPD.
 *
 * Reschedule client RÉACTIVÉ en 2.8.17 (était désactivé depuis la purge
 * 2.6.0, le temps que l'algo v3 se stabilise) : modale dédiée → créneaux
 * via `api/booking-v3-slots.php?service=<id>&date=…` (service-aware, comme
 * l'admin) → `api/manage.php?action=reschedule` → `Booking::clientReschedule()`
 * (remet le RDV en `pending` + email de notification + garde anti-double-booking).
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/_shell.php';

use App\Booking;
use App\Helpers;
use App\Icons;

$pageTitle = 'Gérer mon rendez-vous';
$token     = $_GET['token'] ?? '';
$booking   = null;
$error     = null;

if (empty($token) || strlen($token) !== MANAGE_TOKEN_LENGTH) {
    $error = 'Lien invalide ou expiré.';
} else {
    $bookingModel = new Booking();
    $booking      = $bookingModel->getByToken($token);
    if (!$booking) {
        $error = 'Réservation non trouvée.';
    }
}

$canCancel = $booking && in_array($booking['status'], ['pending', 'confirmed'], true);

// Icônes de statut : SOURCE = BOOKING_STATUS['icon'] (4 statuts exposés) + les
// 2 transitionnels (pending_payment/expired) absents de la table (cf. config).
$statusIcons = ['pending_payment' => 'hourglass', 'expired' => 'circle-x'];
foreach (BOOKING_STATUS as $statusKey => $statusInfo) {
    $statusIcons[$statusKey] = $statusInfo['icon'];
}
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
<body class="booking-page">
    <?= booking_header(Helpers::escape(BASE_URL), 'Retour au site') ?>

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
            <div class="manage-container" id="manage-app" data-token="<?= Helpers::escape($token) ?>" data-service-id="<?= (int) ($booking['service_id'] ?? 0) ?>">

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
                        <?php if (!empty($booking['service_id'])): ?>
                        <button type="button" class="btn btn-secondary" id="btn-reschedule">
                            <?= Icons::svg('calendar', 18, 'icon-inline') ?>Déplacer le rendez-vous
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-danger" id="btn-cancel">
                            <?= Icons::svg('circle-x', 18, 'icon-inline') ?>Annuler le rendez-vous
                        </button>
                    </div>
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

                <!-- Modal de déplacement (reschedule client — même mécanisme
                     .modal-overlay/.active ; créneaux via l'API v3 service-aware,
                     comme l'admin) -->
                <div class="modal-overlay" id="reschedule-modal">
                    <div class="modal-content">
                        <h3>Déplacer mon rendez-vous</h3>
                        <p class="text-muted reschedule-current">
                            Actuel : <?= Helpers::escape($booking['formatted_date']) ?> · <?= Helpers::escape($booking['formatted_time']) ?>
                        </p>
                        <div class="form-group">
                            <label class="form-label" for="reschedule-date">Nouvelle date</label>
                            <input type="date" class="form-input" id="reschedule-date">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="reschedule-slot">Nouveau créneau</label>
                            <select class="form-input" id="reschedule-slot" disabled>
                                <option value="">Choisissez d'abord une date</option>
                            </select>
                        </div>
                        <p class="text-muted bv3-help-note">Le coach sera informé du changement par email.</p>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" id="btn-reschedule-modal-close">Annuler</button>
                            <button type="button" class="btn btn-primary" id="btn-confirm-reschedule" disabled>Déplacer</button>
                        </div>
                    </div>
                </div>

                <!-- Modal de confirmation d'annulation (caché par défaut via
                     main.css .modal-overlay ; affiché par .active depuis manage.js) -->
                <div class="modal-overlay" id="cancel-modal">
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

    <?= booking_footer() ?>

    <?php if ($booking && $canCancel): ?>
    <script src="../../assets/js/icons.js"></script>
    <script src="../../assets/js/manage.js"></script>
    <?php endif; ?>
    <?= pwaRegister() ?>
</body>
</html>

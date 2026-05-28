<?php
/**
 * ============================================
 * PAGE DE GESTION - ESPACE CLIENT
 * ============================================
 * Permet au client de voir, déplacer ou annuler son RDV
 */
require_once __DIR__ . '/../includes/init.php';

use App\Booking;
use App\Helpers;

$pageTitle = 'Gérer mon rendez-vous';
$token = $_GET['token'] ?? '';
$booking = null;
$error = null;

// Vérifier le token
if (empty($token) || strlen($token) !== 64) {
    $error = 'Lien invalide ou expiré.';
} else {
    $bookingModel = new Booking();
    $booking = $bookingModel->getByToken($token);
    
    if (!$booking) {
        $error = 'Réservation non trouvée.';
    }
}

// Statuts qui permettent la modification
$canModify = $booking && in_array($booking['status'], ['pending', 'confirmed']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= siteConfig()['site_name'] ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/booking.css">
    <link rel="stylesheet" href="../assets/css/manage.css">
</head>
<body>
    <!-- Header -->
    <header class="booking-header">
        <div class="booking-header-content">
            <a href="../" class="booking-logo"><?= brandWordmark() ?></a>
            <a href="../" class="back-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Retour au site
            </a>
        </div>
    </header>
    
    <!-- Main -->
    <main class="booking-main">
        <h1 class="page-title text-center"><?= $pageTitle ?></h1>
        
        <?php if ($error): ?>
            <!-- Erreur -->
            <div class="manage-container">
                <div class="error-card">
                    <div class="error-icon">❌</div>
                    <h2>Oups !</h2>
                    <p><?= htmlspecialchars($error) ?></p>
                    <a href="../booking/" class="btn btn-primary">Prendre un nouveau rendez-vous</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Détails du RDV -->
            <div class="manage-container" id="manage-app" data-token="<?= htmlspecialchars($token) ?>">
                
                <!-- Card principale -->
                <div class="booking-card" id="booking-details">
                    <div class="booking-card-header">
                        <h2>Votre rendez-vous</h2>
                        <span class="status-badge <?= $booking['status'] ?>">
                            <?= $booking['status_info']['icon'] ?? '' ?> 
                            <?= $booking['status_info']['label'] ?? ucfirst($booking['status']) ?>
                        </span>
                    </div>
                    
                    <div class="booking-card-body">
                        <div class="booking-info-grid">
                            <div class="info-item">
                                <span class="info-icon">📅</span>
                                <div class="info-content">
                                    <span class="info-label">Date</span>
                                    <span class="info-value" id="display-date"><?= htmlspecialchars($booking['formatted_date']) ?></span>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-icon">🕐</span>
                                <div class="info-content">
                                    <span class="info-label">Horaire</span>
                                    <span class="info-value" id="display-time"><?= htmlspecialchars($booking['formatted_time']) ?></span>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-icon">📋</span>
                                <div class="info-content">
                                    <span class="info-label">Service</span>
                                    <span class="info-value"><?= htmlspecialchars($booking['service_label']) ?></span>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-icon">👤</span>
                                <div class="info-content">
                                    <span class="info-label">Nom</span>
                                    <span class="info-value"><?= htmlspecialchars($booking['visitor_name']) ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($booking['subject'])): ?>
                        <div class="booking-subject">
                            <strong>Objet :</strong> <?= htmlspecialchars($booking['subject']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($booking['status'] === 'pending'): ?>
                        <div class="pending-notice">
                            <span class="notice-icon">⏳</span>
                            <span>Votre demande est en attente de validation. Vous recevrez un email de confirmation.</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($canModify): ?>
                    <div class="booking-card-actions">
                        <button type="button" class="btn btn-secondary" id="btn-reschedule">
                            📅 Déplacer le rendez-vous
                        </button>
                        <button type="button" class="btn btn-danger" id="btn-cancel">
                            ❌ Annuler
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="booking-card-footer">
                        <p class="text-muted">Ce rendez-vous ne peut plus être modifié.</p>
                        <a href="../booking/" class="btn btn-primary">Prendre un nouveau rendez-vous</a>
                    </div>
                    <?php endif; ?>
                    
                    <!-- RGPD — Bouton suppression des données (art. 17) -->
                    <div class="rgpd-section">
                        <details class="rgpd-details">
                            <summary class="rgpd-toggle">
                                <svg class="rgpd-shield" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                </svg>
                                <span>Protection des données personnelles</span>
                                <svg class="rgpd-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </summary>
                            <div class="rgpd-panel">
                                <p class="rgpd-panel-text">
                                    Conformément au RGPD (art. 17), vous pouvez demander la suppression de vos données personnelles associées à ce rendez-vous.
                                </p>
                                <button type="button" class="btn btn-sm rgpd-delete-btn" id="btn-delete-data">
                                    Supprimer mes données
                                </button>
                            </div>
                        </details>
                    </div>
                </div>

                <!-- Modale suppression RGPD -->
                <div id="delete-data-modal" class="rgpd-modal" style="display:none;">
                    <div class="rgpd-modal__box">
                        <h3 class="rgpd-modal__title">Supprimer mes données personnelles</h3>
                        <p class="rgpd-modal__text">
                            Cette action est <strong>irréversible</strong>. Les données suivantes seront supprimées :
                        </p>
                        <ul class="rgpd-modal__list">
                            <li>Votre nom, email, téléphone, organisation</li>
                            <li>L'objet et le message de votre demande</li>
                            <li>Votre adresse IP et données de navigation</li>
                        </ul>
                        <p class="rgpd-modal__note">
                            Seuls la date du créneau et le type de service seront conservés de manière anonyme à des fins statistiques.
                        </p>
                        <div class="rgpd-modal__field">
                            <label class="rgpd-modal__label" for="delete-confirm-email">
                                Pour confirmer, saisissez votre adresse email :
                            </label>
                            <input type="email" id="delete-confirm-email" class="rgpd-modal__input" placeholder="votre@email.fr">
                        </div>
                        <div class="rgpd-modal__actions">
                            <button type="button" id="btn-delete-modal-close" class="btn btn-secondary btn-sm">Annuler</button>
                            <button type="button" id="btn-confirm-delete" class="btn btn-danger btn-sm">Supprimer définitivement</button>
                        </div>
                    </div>
                </div>
                
                <!-- Panel de déplacement (caché par défaut) -->
                <div class="reschedule-panel" id="reschedule-panel" style="display: none;">
                    <div class="panel-header">
                        <h3>Choisir un nouveau créneau</h3>
                        <button type="button" class="btn-close" id="btn-close-reschedule">✕</button>
                    </div>
                    
                    <div class="panel-body">
                        <!-- Calendrier -->
                        <div class="calendar-section">
                            <div class="calendar-header">
                                <h4 class="calendar-title" id="calendar-month-title">-</h4>
                                <div class="calendar-nav">
                                    <button type="button" id="prev-month" aria-label="Mois précédent">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M15 18l-6-6 6-6"/>
                                        </svg>
                                    </button>
                                    <button type="button" id="next-month" aria-label="Mois suivant">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M9 18l6-6-6-6"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="calendar-grid" id="calendar-grid">
                                <div class="calendar-day-header">Lun</div>
                                <div class="calendar-day-header">Mar</div>
                                <div class="calendar-day-header">Mer</div>
                                <div class="calendar-day-header">Jeu</div>
                                <div class="calendar-day-header">Ven</div>
                                <div class="calendar-day-header">Sam</div>
                                <div class="calendar-day-header">Dim</div>
                            </div>
                        </div>
                        
                        <!-- Créneaux -->
                        <div class="slots-section" id="slots-section" style="display: none;">
                            <h4 class="slots-title">Créneaux disponibles le <span id="selected-date-label">-</span></h4>
                            <div class="slots-grid" id="slots-grid"></div>
                        </div>
                    </div>
                    
                    <div class="panel-footer">
                        <p class="notice">
                            <strong>Note :</strong> Après modification, votre rendez-vous sera à nouveau soumis à validation.
                        </p>
                        <div class="panel-actions">
                            <button type="button" class="btn btn-secondary" id="btn-cancel-reschedule">Annuler</button>
                            <button type="button" class="btn btn-primary" id="btn-confirm-reschedule" disabled>
                                Confirmer le déplacement
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Modal de confirmation d'annulation -->
                <div class="modal-overlay" id="cancel-modal" style="display: none;">
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
                <div class="success-card" id="success-card" style="display: none;">
                    <div class="success-icon">✓</div>
                    <h2 id="success-title">Opération réussie</h2>
                    <p id="success-message"></p>
                    
                    <!-- Récapitulatif (affiché si déplacement) -->
                    <div class="booking-summary" id="success-summary" style="display: none;">
                        <h4>Nouveau créneau</h4>
                        <div class="booking-summary-row">
                            <span class="booking-summary-label">Date</span>
                            <span class="booking-summary-value" id="success-date">-</span>
                        </div>
                        <div class="booking-summary-row">
                            <span class="booking-summary-label">Horaire</span>
                            <span class="booking-summary-value" id="success-time">-</span>
                        </div>
                        <div class="booking-summary-row">
                            <span class="booking-summary-label">Statut</span>
                            <span class="booking-summary-value">
                                <span class="status-badge pending">🟡 En attente</span>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <a href="../" class="btn btn-primary">Retour au site</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Footer -->
    <footer class="booking-footer">
        <p>&copy; <?= date('Y') ?> <?= siteConfig()['site_name'] ?></p>
    </footer>
    
    <?php if ($booking && $canModify): ?>
    <script src="../assets/js/calendar-module.js"></script>
    <script src="../assets/js/manage.js"></script>
    <?php endif; ?>
</body>
</html>

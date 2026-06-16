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
                    <div class="rgpd-section" style="margin: 2.5rem 1.5rem 1.5rem; padding-top: 1.5rem; border-top: 1px dashed var(--gray-200);">
                        <details style="cursor: pointer;">
                            <summary style="font-size: 0.8rem; color: var(--gray-400); user-select: none; display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--gold, #c9a227)" stroke-width="2" style="flex-shrink:0;">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                </svg>
                                <span>Protection des données personnelles</span>
                                <svg class="details-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0; margin-left:auto; transition: transform 0.2s ease;">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </summary>
                            <div style="margin-top: 0.75rem; padding: 1.25rem; background: var(--gray-100); border-radius: var(--radius-md); border-left: 3px solid var(--gold, #c9a227);">
                                <p style="font-size: 0.8rem; color: var(--gray-500); line-height: 1.7; margin: 0 0 1rem 0;">
                                    Conformément au RGPD (art. 17), vous pouvez demander la suppression de vos données personnelles associées à ce rendez-vous.
                                </p>
                                <button type="button" class="btn btn-sm" id="btn-delete-data"
                                        style="color: var(--red, #ef4444); border: 1px solid var(--red-light, #fee2e2); background: var(--red-light, #fee2e2); font-size: 0.8rem; padding: 0.4rem 0.75rem;">
                                    Supprimer mes données
                                </button>
                            </div>
                        </details>
                    </div>
                </div>
                
                <!-- Modale suppression RGPD -->
                <div id="delete-data-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
                    <div style="background:white; border-radius:16px; padding:2rem; max-width:500px; width:90%; margin:auto; position:relative; top:50%; transform:translateY(-50%);">
                        <h3 style="color:var(--navy-900); margin-bottom:1rem;">Supprimer mes données personnelles</h3>
                        <p style="color:var(--gray-600); font-size:0.9rem; line-height:1.6; margin-bottom:0.5rem;">
                            Cette action est <strong>irréversible</strong>. Les données suivantes seront supprimées :
                        </p>
                        <ul style="color:var(--gray-600); font-size:0.85rem; line-height:1.6; margin-bottom:1rem; padding-left:1.25rem;">
                            <li>Votre nom, email, téléphone, organisation</li>
                            <li>L'objet et le message de votre demande</li>
                            <li>Votre adresse IP et données de navigation</li>
                        </ul>
                        <p style="color:var(--gray-500); font-size:0.8rem; margin-bottom:1.5rem;">
                            Seuls la date du créneau et le type de service seront conservés de manière anonyme à des fins statistiques.
                        </p>
                        <div style="margin-bottom:1rem;">
                            <label style="display:block; font-size:0.85rem; color:var(--navy-800); margin-bottom:0.5rem; font-weight:500;">
                                Pour confirmer, saisissez votre adresse email :
                            </label>
                            <input type="email" id="delete-confirm-email" placeholder="votre@email.fr"
                                   style="width:100%; padding:0.75rem 1rem; border:1px solid var(--gray-200); border-radius:8px; font-size:0.95rem; font-family:var(--font-sans);">
                        </div>
                        <div style="display:flex; gap:0.75rem; justify-content:flex-end;">
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

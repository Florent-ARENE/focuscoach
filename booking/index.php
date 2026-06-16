<?php
/**
 * ============================================
 * PAGE DE RÉSERVATION - VISITEUR
 * ============================================
 */
require_once __DIR__ . '/../includes/init.php';

use App\Helpers;
use App\Icons;

$pageTitle = 'Prendre rendez-vous';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= Helpers::csrfMeta() ?>
    <title><?= $pageTitle ?> | <?= siteConfig()['site_name'] ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/booking.css">
    <?= pwaHead() ?>
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
        <p class="page-subtitle text-center">Choisissez un créneau et je vous recontacte pour confirmer notre entretien</p>
        
        <!-- Légende du calendrier -->
        <div class="calendar-legend">
            <div class="legend-item">
                <span class="legend-dot available"></span>
                <span>Créneaux disponibles</span>
            </div>
            <div class="legend-item">
                <span class="legend-badge confirmed">1</span>
                <span>RDV confirmé</span>
            </div>
            <div class="legend-item">
                <span class="legend-badge pending">1</span>
                <span>En attente</span>
            </div>
        </div>
        
        <div class="booking-container">
            <!-- Steps -->
            <div class="booking-steps">
                <div class="step active" data-step="1">
                    <span class="step-number">1</span>
                    <span class="step-label">Date & Horaire</span>
                </div>
                <div class="step" data-step="2">
                    <span class="step-number">2</span>
                    <span class="step-label">Vos informations</span>
                </div>
                <div class="step" data-step="3">
                    <span class="step-number">3</span>
                    <span class="step-label">Confirmation</span>
                </div>
            </div>
            
            <!-- Content -->
            <div class="booking-content">
                <!-- Panel 1: Calendar -->
                <div class="booking-panel active" id="panel-1">
                    <div class="calendar-header">
                        <h2 class="calendar-title" id="calendar-month-title">-</h2>
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
                    
                    <div class="slots-container" id="slots-container" style="display: none;">
                        <h3 class="slots-title">Créneaux disponibles le <span id="selected-date-label"></span></h3>
                        <div class="slots-grid" id="slots-grid"></div>
                    </div>
                    
                    <div class="booking-actions">
                        <div></div>
                        <button type="button" class="btn btn-primary" id="btn-to-step-2" disabled>
                            Continuer
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Panel 2: Form -->
                <div class="booking-panel" id="panel-2">
                    <div class="selected-slot-summary">
                        <div class="selected-slot-icon"><?= Icons::svg('calendar', 24) ?></div>
                        <div class="selected-slot-info">
                            <div class="selected-slot-date" id="summary-date">-</div>
                            <div class="selected-slot-time" id="summary-time">-</div>
                        </div>
                        <button type="button" class="change-slot-btn" id="btn-change-slot">Modifier</button>
                    </div>
                    
                    <form id="booking-form" class="booking-form">
                        <?= Helpers::csrfField() ?>
                        <input type="hidden" name="slot_date" id="input-slot-date">
                        <input type="hidden" name="slot_time_start" id="input-slot-time-start">
                        <input type="hidden" name="slot_time_end" id="input-slot-time-end">
                        
                        <div class="form-group">
                            <label class="form-label" for="visitor_name">
                                Nom complet <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" id="visitor_name" name="visitor_name" required placeholder="Jean Dupont">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="visitor_email">
                                Email <span class="required">*</span>
                            </label>
                            <input type="email" class="form-input" id="visitor_email" name="visitor_email" required placeholder="jean.dupont@email.fr">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="visitor_phone">Téléphone</label>
                            <input type="tel" class="form-input" id="visitor_phone" name="visitor_phone" placeholder="06 12 34 56 78">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="visitor_organization">Organisation</label>
                            <input type="text" class="form-input" id="visitor_organization" name="visitor_organization" placeholder="Nom de votre structure">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="service_type">Type de prestation</label>
                            <select class="form-select" id="service_type" name="service_type">
                                <?php foreach (SERVICE_TYPES as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="subject">Objet du rendez-vous</label>
                            <input type="text" class="form-input" id="subject" name="subject" placeholder="Ex: Diagnostic équipe commerciale">
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label" for="message">Message (optionnel)</label>
                            <textarea class="form-textarea" id="message" name="message" placeholder="Décrivez brièvement votre contexte ou vos attentes..."></textarea>
                        </div>
                    </form>
                    
                    <!-- Mention RGPD (art. 13 — transparence, niveau 1) -->
                    <div class="rgpd-notice">
                        <p>
                            En soumettant ce formulaire, vous demandez à être recontacté(e) dans le cadre de votre demande de rendez-vous.
                            Vos données sont traitées par <?= Helpers::escape(siteConfig()['full_name'] ?: siteConfig()['logo_name']) ?> pour la gestion de votre demande, sur la base de l'exécution de mesures
                            précontractuelles (art. 6.1.b RGPD). Elles sont conservées 365 jours.
                            Pour en savoir plus, consultez notre <a href="../confidentialite.php" target="_blank">Politique de confidentialité</a>.
                        </p>
                    </div>
                    
                    <div class="booking-actions">
                        <button type="button" class="btn btn-secondary" id="btn-back-step-1">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 12H5M12 19l-7-7 7-7"/>
                            </svg>
                            Retour
                        </button>
                        <button type="submit" form="booking-form" class="btn btn-primary" id="btn-submit">
                            Envoyer ma demande
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Panel 3: Success -->
                <div class="booking-panel" id="panel-3">
                    <div class="success-panel">
                        <div class="success-icon">✓</div>
                        <h2 class="success-title">Demande envoyée !</h2>
                        <p class="success-message">
                            Votre demande de rendez-vous a été enregistrée. Vous recevrez un email de confirmation dès que votre créneau sera validé.
                        </p>
                        
                        <div class="booking-summary">
                            <h4>Récapitulatif</h4>
                            <div class="booking-summary-row">
                                <span class="booking-summary-label">Date</span>
                                <span class="booking-summary-value" id="confirm-date">-</span>
                            </div>
                            <div class="booking-summary-row">
                                <span class="booking-summary-label">Horaire</span>
                                <span class="booking-summary-value" id="confirm-time">-</span>
                            </div>
                            <div class="booking-summary-row">
                                <span class="booking-summary-label">Statut</span>
                                <span class="booking-summary-value">
                                    <span class="status-badge pending"><?= Icons::svg('hourglass', 14, 'icon-inline') ?>En attente</span>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <a href="../" class="btn btn-primary">Retour au site</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="booking-footer">
        <p>© <?= date('Y') ?> <?= siteConfig()['site_name'] ?></p>
    </footer>
    
    <script src="../assets/js/icons.js"></script>
    <script src="../assets/js/calendar-module.js"></script>
    <script src="../assets/js/booking.js"></script>
    <?= pwaRegister() ?>
</body>
</html>

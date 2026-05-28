/**
 * ============================================
 * MANAGE APP - Espace client
 * ============================================
 * Gestion du RDV par le client (déplacement, annulation)
 * Utilise CalendarModule pour le calendrier
 */

const ManageApp = {
    // Instance du calendrier
    calendar: null,
    
    // State
    state: {
        token: null
    },
    
    // DOM Elements
    elements: {},
    
    /**
     * Initialisation
     */
    init() {
        const app = document.getElementById('manage-app');
        if (!app) return;
        
        this.state.token = app.dataset.token;
        this.cacheElements();
        this.bindEvents();
    },
    
    /**
     * Cache des éléments DOM
     */
    cacheElements() {
        this.elements = {
            bookingDetails: document.getElementById('booking-details'),
            reschedulePanel: document.getElementById('reschedule-panel'),
            cancelModal: document.getElementById('cancel-modal'),
            successCard: document.getElementById('success-card'),
            
            // Buttons
            btnReschedule: document.getElementById('btn-reschedule'),
            btnCancel: document.getElementById('btn-cancel'),
            btnCloseReschedule: document.getElementById('btn-close-reschedule'),
            btnCancelReschedule: document.getElementById('btn-cancel-reschedule'),
            btnConfirmReschedule: document.getElementById('btn-confirm-reschedule'),
            btnCancelModalClose: document.getElementById('btn-cancel-modal-close'),
            btnConfirmCancel: document.getElementById('btn-confirm-cancel'),
            
            // Calendar elements (pour le module)
            calendarGrid: document.getElementById('calendar-grid'),
            monthTitle: document.getElementById('calendar-month-title'),
            prevMonth: document.getElementById('prev-month'),
            nextMonth: document.getElementById('next-month'),
            
            // Slots
            slotsSection: document.getElementById('slots-section'),
            slotsGrid: document.getElementById('slots-grid'),
            selectedDateLabel: document.getElementById('selected-date-label'),
            
            // Success
            successTitle: document.getElementById('success-title'),
            successMessage: document.getElementById('success-message')
        };
    },
    
    /**
     * Bindding des événements
     */
    bindEvents() {
        // Bouton déplacer
        this.elements.btnReschedule?.addEventListener('click', () => this.showReschedulePanel());
        
        // Bouton annuler
        this.elements.btnCancel?.addEventListener('click', () => this.showCancelModal());
        
        // Fermer le panel de déplacement
        this.elements.btnCloseReschedule?.addEventListener('click', () => this.hideReschedulePanel());
        this.elements.btnCancelReschedule?.addEventListener('click', () => this.hideReschedulePanel());
        
        // Confirmer le déplacement
        this.elements.btnConfirmReschedule?.addEventListener('click', () => this.confirmReschedule());
        
        // Modal d'annulation
        this.elements.btnCancelModalClose?.addEventListener('click', () => this.hideCancelModal());
        this.elements.btnConfirmCancel?.addEventListener('click', () => this.confirmCancel());
        
        // Fermer modal au clic sur overlay
        this.elements.cancelModal?.addEventListener('click', (e) => {
            if (e.target === this.elements.cancelModal) {
                this.hideCancelModal();
            }
        });
        
        // --- RGPD : Suppression des données personnelles ---
        this.bindDeleteDataEvents();
    },
    
    /**
     * Événements pour la suppression RGPD (art. 17)
     */
    bindDeleteDataEvents() {
        const btnDeleteData = document.getElementById('btn-delete-data');
        const deleteModal = document.getElementById('delete-data-modal');
        const btnDeleteModalClose = document.getElementById('btn-delete-modal-close');
        const btnConfirmDelete = document.getElementById('btn-confirm-delete');
        
        if (!btnDeleteData || !deleteModal) return;
        
        btnDeleteData.addEventListener('click', () => {
            deleteModal.style.display = 'flex';
        });
        
        btnDeleteModalClose.addEventListener('click', () => {
            deleteModal.style.display = 'none';
            document.getElementById('delete-confirm-email').value = '';
        });
        
        deleteModal.addEventListener('click', (e) => {
            if (e.target === deleteModal) {
                deleteModal.style.display = 'none';
            }
        });
        
        btnConfirmDelete.addEventListener('click', async () => {
            const email = document.getElementById('delete-confirm-email').value.trim();
            if (!email) {
                alert('Veuillez saisir votre adresse email pour confirmer.');
                return;
            }
            
            btnConfirmDelete.disabled = true;
            btnConfirmDelete.textContent = 'Suppression...';
            
            try {
                const response = await fetch('../api/manage.php?action=delete_data', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        token: ManageApp.state.token,
                        confirm_email: email
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('manage-app').innerHTML = `
                        <div class="rgpd-success">
                            <div class="rgpd-success__icon">✓</div>
                            <h2 class="rgpd-success__title">Données supprimées</h2>
                            <p class="rgpd-success__text">
                                Vos données personnelles ont été supprimées conformément à votre demande.
                                Un email de confirmation vous a été envoyé.
                            </p>
                            <a href="../" class="btn btn-primary">Retour au site</a>
                        </div>
                    `;
                } else {
                    alert(data.message || 'Erreur lors de la suppression.');
                    btnConfirmDelete.disabled = false;
                    btnConfirmDelete.textContent = 'Supprimer définitivement';
                }
            } catch (err) {
                alert('Erreur de connexion. Veuillez réessayer.');
                btnConfirmDelete.disabled = false;
                btnConfirmDelete.textContent = 'Supprimer définitivement';
            }
        });
    },
    
    /**
     * Initialiser le calendrier avec le module partagé
     */
    initCalendar() {
        if (this.calendar) return; // Déjà initialisé
        
        this.calendar = CalendarModule.create({
            apiBase: '../api/',
            showStats: true,  // Afficher les indicateurs de RDV (comme côté booking)
            onDateSelect: (dateStr) => {
                // Désactiver le bouton de confirmation
                this.elements.btnConfirmReschedule.disabled = true;
            },
            onSlotSelect: (slot) => {
                // Activer le bouton quand un créneau est sélectionné
                this.elements.btnConfirmReschedule.disabled = false;
            }
        });
        
        // Initialiser avec les éléments DOM
        this.calendar.init({
            calendarGrid: this.elements.calendarGrid,
            monthTitle: this.elements.monthTitle,
            prevMonth: this.elements.prevMonth,
            nextMonth: this.elements.nextMonth,
            slotsSection: this.elements.slotsSection,
            slotsGrid: this.elements.slotsGrid,
            selectedDateLabel: this.elements.selectedDateLabel
        });
    },
    
    /**
     * Afficher le panel de déplacement
     */
    showReschedulePanel() {
        this.elements.reschedulePanel.style.display = 'block';
        
        // Initialiser le calendrier (une seule fois)
        this.initCalendar();
        
        // Scroll vers le panel
        this.elements.reschedulePanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },
    
    /**
     * Cacher le panel de déplacement
     */
    hideReschedulePanel() {
        this.elements.reschedulePanel.style.display = 'none';
        if (this.calendar) {
            this.calendar.reset();
        }
        this.elements.btnConfirmReschedule.disabled = true;
    },
    
    /**
     * Afficher la modal d'annulation
     */
    showCancelModal() {
        this.elements.cancelModal.style.display = 'flex';
    },
    
    /**
     * Cacher la modal d'annulation
     */
    hideCancelModal() {
        this.elements.cancelModal.style.display = 'none';
    },
    
    /**
     * Confirmer le déplacement
     */
    async confirmReschedule() {
        const selection = this.calendar.getSelection();
        if (!selection.date || !selection.slot) {
            return;
        }
        
        const btn = this.elements.btnConfirmReschedule;
        btn.disabled = true;
        btn.textContent = 'Traitement...';
        
        try {
            const response = await fetch('../api/manage.php?action=reschedule', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    token: this.state.token,
                    new_date: selection.date,
                    new_time_start: selection.slot.time_start,
                    new_time_end: selection.slot.time_end
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Récupérer les données pour le récapitulatif
                const selection = this.calendar.getSelection();
                const summaryData = {
                    date: this.calendar.formatDateFr(selection.date),
                    time: selection.slot.label
                };
                
                this.showSuccess(
                    'Demande enregistrée !',
                    'Votre demande de déplacement a été prise en compte. Elle est maintenant en attente de validation. Vous recevrez un email de confirmation.',
                    summaryData
                );
            } else {
                alert(result.message || 'Une erreur est survenue');
                btn.disabled = false;
                btn.textContent = 'Confirmer le déplacement';
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('Erreur de connexion');
            btn.disabled = false;
            btn.textContent = 'Confirmer le déplacement';
        }
    },
    
    /**
     * Confirmer l'annulation
     */
    async confirmCancel() {
        const btn = this.elements.btnConfirmCancel;
        btn.disabled = true;
        btn.textContent = 'Traitement...';
        
        try {
            const response = await fetch('../api/manage.php?action=cancel', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    token: this.state.token
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.hideCancelModal();
                this.showSuccess(
                    'Rendez-vous annulé',
                    'Votre rendez-vous a été annulé. Vous recevrez un email de confirmation.'
                );
            } else {
                alert(result.message || 'Une erreur est survenue');
                btn.disabled = false;
                btn.textContent = 'Oui, annuler';
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('Erreur de connexion');
            btn.disabled = false;
            btn.textContent = 'Oui, annuler';
        }
    },
    
    /**
     * Afficher le message de succès
     * @param {string} title - Titre
     * @param {string} message - Message
     * @param {Object} summaryData - Données du récapitulatif (optionnel)
     */
    showSuccess(title, message, summaryData = null) {
        this.elements.bookingDetails.style.display = 'none';
        this.elements.reschedulePanel.style.display = 'none';
        this.elements.successCard.style.display = 'block';
        
        this.elements.successTitle.textContent = title;
        this.elements.successMessage.textContent = message;
        
        // Afficher le récapitulatif si des données sont fournies
        const summaryEl = document.getElementById('success-summary');
        if (summaryData && summaryEl) {
            document.getElementById('success-date').textContent = summaryData.date;
            document.getElementById('success-time').textContent = summaryData.time;
            summaryEl.style.display = 'block';
        } else if (summaryEl) {
            summaryEl.style.display = 'none';
        }
        
        // Scroll vers le haut
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

// Initialisation
document.addEventListener('DOMContentLoaded', () => ManageApp.init());

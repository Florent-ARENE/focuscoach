/**
 * ============================================
 * BOOKING APP - Interface de réservation
 * ============================================
 * Utilise CalendarModule pour le calendrier
 */

const BookingApp = {
    // Instance du calendrier
    calendar: null,
    
    // DOM Elements cache
    elements: {},
    
    /**
     * Initialisation
     */
    init() {
        this.cacheElements();
        this.initCalendar();
        this.bindEvents();
    },
    
    /**
     * Cache des éléments DOM
     */
    cacheElements() {
        this.elements = {
            // Panels (étapes)
            step1: document.getElementById('panel-1'),
            step2: document.getElementById('panel-2'),
            
            // Calendar
            calendarGrid: document.getElementById('calendar-grid'),
            monthTitle: document.getElementById('calendar-month-title'),
            prevMonth: document.getElementById('prev-month'),
            nextMonth: document.getElementById('next-month'),
            
            // Slots
            slotsContainer: document.getElementById('slots-container'),
            slotsGrid: document.getElementById('slots-grid'),
            slotsTitle: document.querySelector('.slots-title'),
            
            // Navigation
            btnToStep2: document.getElementById('btn-to-step-2'),
            btnBackStep1: document.getElementById('btn-back-step-1'),
            btnChangeSlot: document.getElementById('btn-change-slot'),
            
            // Récapitulatif
            summaryDate: document.getElementById('summary-date'),
            summaryTime: document.getElementById('summary-time'),
            
            // Formulaire
            bookingForm: document.getElementById('booking-form')
        };
    },
    
    /**
     * Initialiser le calendrier avec le module partagé
     */
    initCalendar() {
        this.calendar = CalendarModule.create({
            apiBase: '../api/',
            showStats: true,  // Afficher les indicateurs de RDV
            onDateSelect: (dateStr) => {
                // Mettre à jour le titre des créneaux
                if (this.elements.slotsTitle) {
                    const dateFr = this.calendar.formatDateFr(dateStr);
                    this.elements.slotsTitle.innerHTML = 'Créneaux disponibles le <strong>' + dateFr + '</strong>';
                }
                // Désactiver le bouton tant qu'aucun créneau n'est sélectionné
                this.elements.btnToStep2.disabled = true;
            },
            onSlotSelect: (slot) => {
                // Activer le bouton quand un créneau est sélectionné
                this.elements.btnToStep2.disabled = false;
            }
        });
        
        // Initialiser avec les éléments DOM
        this.calendar.init({
            calendarGrid: this.elements.calendarGrid,
            monthTitle: this.elements.monthTitle,
            prevMonth: this.elements.prevMonth,
            nextMonth: this.elements.nextMonth,
            slotsSection: this.elements.slotsContainer,
            slotsGrid: this.elements.slotsGrid
        });
    },
    
    /**
     * Bindding des événements
     */
    bindEvents() {
        this.elements.btnToStep2?.addEventListener('click', () => this.goToStep(2));
        this.elements.btnBackStep1?.addEventListener('click', () => this.goToStep(1));
        this.elements.btnChangeSlot?.addEventListener('click', () => this.goToStep(1));
        this.elements.bookingForm?.addEventListener('submit', (e) => this.handleSubmit(e));
    },
    
    /**
     * Naviguer entre les étapes
     */
    goToStep(step) {
        if (step === 2) {
            const selection = this.calendar.getSelection();
            if (!selection.date || !selection.slot) return;
            
            // Mettre à jour le récapitulatif
            this.elements.summaryDate.textContent = this.calendar.formatDateFr(selection.date);
            this.elements.summaryTime.textContent = selection.slot.label;
            
            // Champs cachés
            document.getElementById('input-slot-date').value = selection.date;
            document.getElementById('input-slot-time-start').value = selection.slot.time_start;
            document.getElementById('input-slot-time-end').value = selection.slot.time_end;
        }
        
        // Afficher/masquer les panels avec la classe active
        this.elements.step1.classList.toggle('active', step === 1);
        this.elements.step2.classList.toggle('active', step === 2);
        
        // Scroll en haut
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },
    
    /**
     * Soumettre le formulaire
     */
    async handleSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = document.getElementById('btn-submit');
        const originalText = submitBtn.innerHTML;
        
        // Désactiver le bouton
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Envoi en cours...';
        
        // Préparer les données
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        try {
            const response = await fetch('../api/booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess();
            } else {
                this.showError(result.message || 'Une erreur est survenue');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        } catch (error) {
            console.error('Erreur:', error);
            this.showError('Erreur de connexion');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    },
    
    /**
     * Afficher le message de succès
     */
    showSuccess() {
        const selection = this.calendar.getSelection();
        
        // Remplir le récapitulatif de confirmation
        document.getElementById('confirm-date').textContent = this.calendar.formatDateFr(selection.date);
        document.getElementById('confirm-time').textContent = selection.slot.label;
        
        // Masquer panel-2 et afficher panel-3
        this.elements.step2.classList.remove('active');
        document.getElementById('panel-3').classList.add('active');
    },
    
    /**
     * Afficher un message d'erreur
     */
    showError(message) {
        // Supprimer l'ancien message d'erreur s'il existe
        const oldError = this.elements.step2.querySelector('.error-message');
        if (oldError) oldError.remove();
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.innerHTML = '<p>❌ ' + message + '</p>';
        
        this.elements.bookingForm.insertBefore(errorDiv, this.elements.bookingForm.firstChild);
        
        // Auto-suppression après 5s
        setTimeout(() => errorDiv.remove(), 5000);
    }
};

// Initialisation
document.addEventListener('DOMContentLoaded', () => BookingApp.init());

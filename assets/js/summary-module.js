/**
 * ============================================
 * SUMMARY MODULE - Récapitulatif de réservation
 * ============================================
 * Génère un récapitulatif uniforme partout
 * Utilisé par : booking.js, manage.js
 */

const SummaryModule = {
    
    /**
     * Générer le HTML d'un récapitulatif
     * @param {Object} data - Données de la réservation
     * @param {Object} options - Options d'affichage
     * @returns {string} HTML du récapitulatif
     */
    render(data, options = {}) {
        const {
            showStatus = true,
            showService = false,
            showName = false,
            showSubject = false,
            compact = false
        } = options;
        
        if (compact) {
            return this.renderCompact(data);
        }
        
        let html = '<div class="booking-summary-card">';
        
        // Date
        html += this.renderItem('📅', 'Date', data.date || '-');
        
        // Horaire
        html += this.renderItem('🕐', 'Horaire', data.time || '-');
        
        // Service (optionnel)
        if (showService && data.service) {
            html += this.renderItem('📋', 'Service', data.service);
        }
        
        // Nom (optionnel)
        if (showName && data.name) {
            html += this.renderItem('👤', 'Nom', data.name);
        }
        
        // Sujet (optionnel)
        if (showSubject && data.subject) {
            html += this.renderItem('📝', 'Objet', data.subject);
        }
        
        // Statut (optionnel)
        if (showStatus && data.status) {
            html += this.renderStatus(data.status);
        }
        
        html += '</div>';
        
        return html;
    },
    
    /**
     * Générer un item du récapitulatif
     */
    renderItem(icon, label, value) {
        return `
            <div class="summary-item">
                <span class="summary-icon">${icon}</span>
                <div class="summary-content">
                    <span class="summary-label">${label}</span>
                    <span class="summary-value">${value}</span>
                </div>
            </div>
        `;
    },
    
    /**
     * Générer le badge de statut
     */
    renderStatus(status) {
        const statuses = {
            pending: { icon: '⏳', label: 'En attente', class: 'pending' },
            confirmed: { icon: '✅', label: 'Confirmé', class: 'confirmed' },
            cancelled: { icon: '❌', label: 'Annulé', class: 'cancelled' },
            completed: { icon: '✓', label: 'Terminé', class: 'completed' }
        };
        
        const s = statuses[status] || statuses.pending;
        
        return `
            <div class="summary-item">
                <span class="summary-icon">📌</span>
                <div class="summary-content">
                    <span class="summary-label">Statut</span>
                    <span class="summary-value">
                        <span class="status-badge ${s.class}">${s.icon} ${s.label}</span>
                    </span>
                </div>
            </div>
        `;
    },
    
    /**
     * Version compacte (une ligne)
     */
    renderCompact(data) {
        return `
            <div class="summary-compact">
                <span class="summary-icon">📅</span>
                <span class="summary-date">${data.date || '-'}</span>
                <span class="summary-separator">•</span>
                <span class="summary-icon">🕐</span>
                <span class="summary-time">${data.time || '-'}</span>
            </div>
        `;
    },
    
    /**
     * Mettre à jour un récapitulatif existant (par IDs)
     * @param {Object} data - Données
     * @param {Object} ids - IDs des éléments à mettre à jour
     */
    update(data, ids = {}) {
        if (ids.date && data.date) {
            const el = document.getElementById(ids.date);
            if (el) el.textContent = data.date;
        }
        if (ids.time && data.time) {
            const el = document.getElementById(ids.time);
            if (el) el.textContent = data.time;
        }
        if (ids.service && data.service) {
            const el = document.getElementById(ids.service);
            if (el) el.textContent = data.service;
        }
        if (ids.name && data.name) {
            const el = document.getElementById(ids.name);
            if (el) el.textContent = data.name;
        }
        if (ids.status && data.status) {
            const el = document.getElementById(ids.status);
            if (el) el.textContent = data.status;
        }
    },
    
    /**
     * Afficher un message de succès avec récapitulatif
     * @param {HTMLElement} container - Conteneur où afficher
     * @param {Object} data - Données de la réservation
     * @param {Object} options - Options
     */
    showSuccess(container, data, options = {}) {
        const {
            title = 'Demande envoyée !',
            message = 'Votre demande a été enregistrée.',
            buttonText = 'Retour au site',
            buttonUrl = '../'
        } = options;
        
        container.innerHTML = `
            <div class="success-panel">
                <div class="success-icon-large">✅</div>
                <h2 class="success-title">${title}</h2>
                <p class="success-message">${message}</p>
                ${this.render(data, { showStatus: true })}
                <div class="mt-3">
                    <a href="${buttonUrl}" class="btn btn-primary">${buttonText}</a>
                </div>
            </div>
        `;
    }
};

// Export pour utilisation en module ES6 si nécessaire
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SummaryModule;
}

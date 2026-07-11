/**
 * ============================================
 * ADMIN APP - Module d'administration
 * ============================================
 */

/**
 * Lit le jeton CSRF depuis <meta name="csrf-token"> et le renvoie sous
 * la forme d'un objet headers à fusionner dans fetch(). Centralisé pour
 * que tous les POST passent par la même mécanique.
 */
function csrfHeaders() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? { 'X-CSRF-Token': meta.content } : {};
}

const AdminApp = {
    // Configuration
    config: {
        apiBase: '../api/'
    },

    // State
    state: {
        bookings: [],
        currentBooking: null
    },
    
    /**
     * Icône Lucide d'un statut — lue depuis BOOKING_STATUS['icon'] côté serveur
     * (SOURCE UNIQUE), exposée via status_info. Plus de map dupliquée ici.
     */
    statusIcon(statusInfo) {
        return statusInfo?.icon ? Icons.svg(statusInfo.icon, 14) : '';
    },

    /**
     * Modale de confirmation STYLÉE (remplace les confirm() natifs du navigateur).
     * Réutilise le système .modal-overlay/.modal de main.css + #confirm-modal.
     * @returns {Promise<boolean>} true si confirmé, false si annulé/fond cliqué.
     */
    confirmModal({ title = 'Confirmer', message = '', confirmLabel = 'Confirmer', danger = false } = {}) {
        return new Promise((resolve) => {
            const modal  = document.getElementById('confirm-modal');
            const okBtn  = document.getElementById('confirm-ok');
            const noBtn  = document.getElementById('confirm-cancel');
            document.getElementById('confirm-title').textContent   = title;
            document.getElementById('confirm-message').textContent = message;
            okBtn.textContent = confirmLabel;
            okBtn.className   = 'btn ' + (danger ? 'btn-danger' : 'btn-primary');

            const close = (result) => {
                modal.classList.remove('active');
                okBtn.removeEventListener('click', onOk);
                noBtn.removeEventListener('click', onNo);
                modal.removeEventListener('click', onBackdrop);
                resolve(result);
            };
            const onOk = () => close(true);
            const onNo = () => close(false);
            const onBackdrop = (e) => { if (e.target === modal) close(false); };
            okBtn.addEventListener('click', onOk);
            noBtn.addEventListener('click', onNo);
            modal.addEventListener('click', onBackdrop);
            modal.classList.add('active');
            okBtn.focus();
        });
    },

    /**
     * Initialisation
     */
    async init() {
        // Charger les réservations si on est sur la page bookings
        if (document.getElementById('bookings-table-body')) {
            await this.loadBookings();
        }
        this.bindEvents();
    },
    
    /**
     * Binding des événements
     */
    bindEvents() {
        document.getElementById('filter-status')?.addEventListener('change', () => this.loadBookings());
        document.getElementById('filter-date-from')?.addEventListener('change', () => this.loadBookings());
        document.getElementById('filter-date-to')?.addEventListener('change', () => this.loadBookings());
        
        // Toggle Google Calendar
        document.getElementById('google_calendar_enabled')?.addEventListener('change', (e) => {
            document.getElementById('sync-status').textContent = e.target.checked ? 'Activée' : 'Désactivée';
        });

        // Toggle validation des RDV
        document.getElementById('booking_auto_confirm')?.addEventListener('change', (e) => {
            document.getElementById('auto-confirm-status').textContent = e.target.checked ? 'Automatique' : 'En attente de validation';
        });
        
        // Fermer la modal au clic sur l'overlay
        document.getElementById('detail-modal')?.addEventListener('click', (e) => {
            if (e.target.id === 'detail-modal') {
                this.closeModal();
            }
        });
    },
    
    /**
     * Charger les réservations
     */
    async loadBookings() {
        const status = document.getElementById('filter-status')?.value || '';
        const dateFrom = document.getElementById('filter-date-from')?.value || '';
        const dateTo = document.getElementById('filter-date-to')?.value || '';
        
        let url = this.config.apiBase + 'admin.php?action=list';
        if (status) url += `&status=${status}`;
        if (dateFrom) url += `&date_from=${dateFrom}`;
        if (dateTo) url += `&date_to=${dateTo}`;
        
        try {
            const response = await fetch(url);
            const data = await response.json();
            
            this.state.bookings = data.bookings || [];
            
            // Mettre à jour les stats
            if (data.stats) {
                document.getElementById('stat-pending').textContent = data.stats.pending || 0;
                document.getElementById('stat-confirmed').textContent = data.stats.confirmed || 0;
                document.getElementById('stat-today').textContent = data.stats.today || 0;
                
                const badge = document.getElementById('pending-count');
                if (badge) badge.textContent = data.stats.pending || 0;
            }
            
            this.renderTable();
        } catch (error) {
            console.error('Erreur:', error);
        }
    },
    
    /**
     * Afficher le tableau
     */
    renderTable() {
        const tbody = document.getElementById('bookings-table-body');
        if (!tbody) return;
        
        if (this.state.bookings.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="empty-state">
                        <div class="empty-state-icon">${Icons.svg('inbox', 40)}</div>
                        <p>Aucune réservation</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = this.state.bookings.map(b => `
            <tr>
                <td>${this.escapeHtml(b.formatted_date)}</td>
                <td>${this.escapeHtml(b.formatted_time)}</td>
                <td>
                    <strong>${this.escapeHtml(b.visitor_name)}</strong><br>
                    <small class="text-muted">${this.escapeHtml(b.visitor_email)}</small>
                </td>
                <td>${this.escapeHtml(b.service_name || '')}${b.package_purchase_id ? ` <small class="text-muted">(forfait ${parseInt(b.pkg_index, 10) || '?'}/${parseInt(b.pkg_total, 10) || '?'})</small>` : ''}</td>
                <td>
                    <span class="status-badge ${b.status}">
                        ${this.statusIcon(b.status_info)} ${b.status_info?.label || b.status}
                    </span>
                </td>
                <td>
                    <div class="action-btns">
                        <button class="action-btn view" onclick="AdminApp.viewBooking(${b.id})" title="Voir" aria-label="Voir">${Icons.svg('eye', 16)}</button>
                        ${b.status === 'pending' ? `
                            <button class="action-btn confirm" onclick="AdminApp.confirmBooking(${b.id})" title="Confirmer" aria-label="Confirmer">${Icons.svg('check', 16)}</button>
                            <button class="action-btn cancel" onclick="AdminApp.cancelBooking(${b.id})" title="Refuser" aria-label="Refuser">${Icons.svg('x', 16)}</button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
    },
    
    /**
     * Voir les détails d'une réservation
     */
    viewBooking(id) {
        this.state.currentBooking = this.state.bookings.find(b => b.id === id);
        if (!this.state.currentBooking) return;
        
        const b = this.state.currentBooking;
        
        document.getElementById('modal-body').innerHTML = `
            <div class="detail-row">
                <span class="detail-label">Statut</span>
                <span class="detail-value">
                    <span class="status-badge ${b.status}">
                        ${this.statusIcon(b.status_info)} ${b.status_info?.label || b.status}
                    </span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date</span>
                <span class="detail-value">${this.escapeHtml(b.formatted_date)}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Horaire</span>
                <span class="detail-value">${this.escapeHtml(b.formatted_time)}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Nom</span>
                <span class="detail-value">${this.escapeHtml(b.visitor_name)}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value"><a href="mailto:${this.escapeHtml(b.visitor_email)}">${this.escapeHtml(b.visitor_email)}</a></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Téléphone</span>
                <span class="detail-value">${b.visitor_phone ? `<a href="tel:${this.escapeHtml(b.visitor_phone)}">${this.escapeHtml(b.visitor_phone)}</a>` : '-'}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Organisation</span>
                <span class="detail-value">${b.visitor_organization ? this.escapeHtml(b.visitor_organization) : '-'}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Service</span>
                <span class="detail-value">${this.escapeHtml(b.service_name || '')}</span>
            </div>
            ${b.subject ? `
            <div class="detail-row">
                <span class="detail-label">Objet</span>
                <span class="detail-value">${this.escapeHtml(b.subject)}</span>
            </div>
            ` : ''}
            ${b.message ? `
            <div class="detail-row">
                <span class="detail-label">Message</span>
                <span class="detail-value">${this.escapeHtml(b.message)}</span>
            </div>
            ` : ''}
            <div class="detail-row">
                <span class="detail-label">Créé le</span>
                <span class="detail-value">${new Date(b.created_at).toLocaleString('fr-FR')}</span>
            </div>
        `;
        
        let footerHtml = '<button class="btn btn-secondary" onclick="AdminApp.closeModal()">Fermer</button>';
        
        // Bouton Déplacer pour toutes les réservations non annulées/terminées
        if (b.status === 'pending' || b.status === 'confirmed') {
            footerHtml = `
                <button class="btn btn-secondary" onclick="AdminApp.showRescheduleForm(${b.id})">${Icons.svg('calendar', 16, 'icon-inline')}Déplacer</button>
                <button class="btn btn-danger" onclick="AdminApp.deleteBooking(${b.id})">${Icons.svg('trash-2', 16, 'icon-inline')}Supprimer</button>
            `;
            
            if (b.status === 'pending') {
                footerHtml += `<button class="btn btn-success" onclick="AdminApp.confirmBooking(${b.id})">${Icons.svg('check-circle', 16, 'icon-inline')}Confirmer</button>`;
            } else if (b.status === 'confirmed') {
                footerHtml += `<button class="btn btn-warning" onclick="AdminApp.cancelBooking(${b.id})">${Icons.svg('x-circle', 16, 'icon-inline')}Annuler</button>`;
            }
        }
        
        document.getElementById('modal-footer').innerHTML = footerHtml;
        document.getElementById('detail-modal').classList.add('active');
    },
    
    /**
     * Afficher le formulaire de déplacement
     */
    showRescheduleForm(id) {
        const booking = this.state.bookings.find(b => b.id === id);
        if (!booking) return;
        // Le calcul des créneaux v3 est SERVICE-AWARE (durée + buffer) → on
        // mémorise la prestation du RDV pour interroger booking-v3-slots.php.
        this.state.rescheduleServiceId = booking.service_id;

        document.getElementById('modal-body').innerHTML = `
            <h3>Déplacer le rendez-vous</h3>
            <p class="reschedule-current">
                RDV actuel : ${this.escapeHtml(booking.formatted_date)} à ${this.escapeHtml(booking.formatted_time)}
            </p>

            <div class="form-group">
                <label for="new-date">Nouvelle date</label>
                <input type="date" id="new-date" class="form-input"
                       min="${new Date().toISOString().split('T')[0]}"
                       onchange="AdminApp.loadAvailableSlots()">
            </div>

            <div class="form-group">
                <label for="new-slot">Nouveau créneau</label>
                <select id="new-slot" class="form-input" disabled>
                    <option value="">Sélectionnez d'abord une date</option>
                </select>
            </div>

            <div id="reschedule-warning" class="reschedule-warning" style="display:none;">
                <strong>${Icons.svg('alert-triangle', 16, 'icon-inline')}Attention :</strong> Le visiteur sera informé du changement par email.
            </div>
        `;
        
        document.getElementById('modal-footer').innerHTML = `
            <button class="btn btn-secondary" onclick="AdminApp.viewBooking(${id})">Retour</button>
            <button class="btn btn-primary" id="btn-reschedule" onclick="AdminApp.rescheduleBooking(${id})" disabled>Déplacer</button>
        `;
    },
    
    /**
     * Charger les créneaux disponibles pour une date
     */
    async loadAvailableSlots() {
        const dateInput = document.getElementById('new-date');
        const slotSelect = document.getElementById('new-slot');
        const btnReschedule = document.getElementById('btn-reschedule');
        const warning = document.getElementById('reschedule-warning');
        
        if (!dateInput.value) {
            slotSelect.innerHTML = '<option value="">Sélectionnez d\'abord une date</option>';
            slotSelect.disabled = true;
            btnReschedule.disabled = true;
            return;
        }
        
        slotSelect.innerHTML = '<option value="">Chargement...</option>';
        slotSelect.disabled = true;
        
        try {
            const response = await fetch(this.config.apiBase + `booking-v3-slots.php?service=${this.state.rescheduleServiceId}&date=${dateInput.value}`);
            const data = await response.json();
            
            if (data.slots && data.slots.length > 0) {
                slotSelect.innerHTML = '<option value="">Choisir un créneau</option>' +
                    data.slots.map(s => `<option value="${s.time_start}|${s.time_end}">${s.label}</option>`).join('');
                slotSelect.disabled = false;
                slotSelect.onchange = () => {
                    btnReschedule.disabled = !slotSelect.value;
                    warning.style.display = slotSelect.value ? 'block' : 'none';
                };
            } else {
                slotSelect.innerHTML = '<option value="">Aucun créneau disponible</option>';
            }
        } catch (error) {
            slotSelect.innerHTML = '<option value="">Erreur de chargement</option>';
            console.error(error);
        }
    },
    
    /**
     * Effectuer le déplacement
     */
    async rescheduleBooking(id) {
        const newDate = document.getElementById('new-date').value;
        const slotValue = document.getElementById('new-slot').value;
        
        if (!newDate || !slotValue) {
            this.showToast('Veuillez sélectionner une date et un créneau', 'error');
            return;
        }
        
        const [newTimeStart, newTimeEnd] = slotValue.split('|');
        
        if (!await this.confirmModal({
            title: 'Déplacer le rendez-vous',
            message: `Déplacer ce rendez-vous au ${newDate} de ${newTimeStart} à ${newTimeEnd} ?`,
            confirmLabel: 'Déplacer',
        })) {
            return;
        }
        
        try {
            const response = await fetch(this.config.apiBase + 'admin.php?action=reschedule', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
                body: JSON.stringify({ 
                    id, 
                    new_date: newDate, 
                    new_time_start: newTimeStart,
                    new_time_end: newTimeEnd
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.closeModal();
                await this.loadBookings();
                this.showToast('Rendez-vous déplacé avec succès', 'success');
            } else {
                this.showToast(result.message || 'Erreur lors du déplacement', 'error');
            }
        } catch (error) {
            console.error('Erreur:', error);
            this.showToast('Erreur de connexion', 'error');
        }
    },
    
    /**
     * Supprimer une réservation
     */
    async deleteBooking(id) {
        if (!await this.confirmModal({
            title: 'Supprimer la réservation',
            message: 'Êtes-vous sûr de vouloir supprimer cette réservation ? Cette action est irréversible.',
            confirmLabel: 'Supprimer',
            danger: true,
        })) {
            return;
        }
        
        try {
            const response = await fetch(this.config.apiBase + 'admin.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
                body: JSON.stringify({ id })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.closeModal();
                await this.loadBookings();
                this.showToast('Réservation supprimée', 'success');
            } else {
                this.showToast(result.message || 'Erreur', 'error');
            }
        } catch (error) {
            console.error('Erreur:', error);
            this.showToast('Erreur de connexion', 'error');
        }
    },
    
    /**
     * Confirmer une réservation
     */
    async confirmBooking(id) {
        if (!await this.confirmModal({ title: 'Confirmer la réservation', message: 'Confirmer cette réservation ?', confirmLabel: 'Confirmer' })) return;
        await this.updateStatus(id, 'confirmed');
    },
    
    /**
     * Annuler une réservation
     */
    async cancelBooking(id) {
        if (!await this.confirmModal({ title: 'Refuser la réservation', message: 'Refuser cette réservation ?', confirmLabel: 'Refuser', danger: true })) return;
        await this.updateStatus(id, 'cancelled');
    },
    
    /**
     * Mettre à jour le statut
     */
    async updateStatus(id, status) {
        try {
            const response = await fetch(this.config.apiBase + 'admin.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
                body: JSON.stringify({ id, status })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.closeModal();
                await this.loadBookings();
                this.showToast('Statut mis à jour', 'success');
            } else {
                this.showToast(result.message || 'Erreur', 'error');
            }
        } catch (error) {
            console.error('Erreur:', error);
            this.showToast('Erreur de connexion', 'error');
        }
    },
    
    /**
     * Sauvegarder les paramètres
     */
    async saveSettings() {
        const settings = {
            booking_auto_confirm: document.getElementById('booking_auto_confirm')?.checked ? '1' : '0',
            google_calendar_enabled: document.getElementById('google_calendar_enabled')?.checked ? '1' : '0',
            google_calendar_id: document.getElementById('google_calendar_id')?.value || '',
            site_name: document.getElementById('site_name')?.value || '',
            admin_name: document.getElementById('admin_name')?.value || '',
            admin_lastname: document.getElementById('admin_lastname')?.value || '',
            admin_email: document.getElementById('admin_email')?.value || '',
            admin_phone: document.getElementById('admin_phone')?.value || '',
            admin_address: document.getElementById('admin_address')?.value || '',
            admin_siret: document.getElementById('admin_siret')?.value || '',
            legal_status: document.getElementById('legal_status')?.value || ''
        };
        
        try {
            const response = await fetch(this.config.apiBase + 'admin.php?action=settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
                body: JSON.stringify(settings)
            });
            
            const text = await response.text();
            let result;
            
            try {
                result = JSON.parse(text);
            } catch (parseError) {
                console.error('Réponse non-JSON:', text);
                this.showToast('Erreur serveur (voir console)', 'error');
                return;
            }
            
            if (result.success) {
                this.showToast('Paramètres enregistrés', 'success');
            } else {
                const errorMsg = result.debug || result.message || result.error || 'Erreur';
                console.error('Erreur API:', result);
                this.showToast(errorMsg, 'error');
            }
        } catch (error) {
            console.error('Erreur réseau:', error);
            this.showToast('Erreur de connexion', 'error');
        }
    },
    
    /**
     * Afficher un toast
     */
    showToast(message, type = 'info') {
        const toast = document.getElementById('toast');
        if (!toast) return;
        
        toast.textContent = message;
        toast.className = 'toast show ' + type;
        
        setTimeout(() => {
            toast.className = 'toast';
        }, 3000);
    },
    
    /**
     * Fermer la modal
     */
    closeModal() {
        document.getElementById('detail-modal')?.classList.remove('active');
    },
    
    /**
     * Échapper le HTML
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Init au chargement du DOM
document.addEventListener('DOMContentLoaded', () => AdminApp.init());

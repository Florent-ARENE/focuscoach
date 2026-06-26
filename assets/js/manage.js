/**
 * ============================================
 * MANAGE APP — Espace client (Booking v3)
 * ============================================
 * Gestion du RDV par le client : déplacement (reschedule),
 * annulation et droit à l'effacement RGPD. Le reschedule utilise
 * l'algo v3 service-aware (api/booking-v3-slots.php pour les créneaux
 * + api/manage.php?action=reschedule → Booking::clientReschedule),
 * réactivé en 2.8.17 (était désactivé depuis la purge 2.6.0).
 *
 * Chemins relatifs : la page vit dans /modules/booking/manage.php
 * (deux niveaux sous la racine), donc l'API est en `../../api/...`.
 */

const ManageApp = {
    state: {
        token: null,
        serviceId: null,
    },

    elements: {},

    init() {
        const app = document.getElementById('manage-app');
        if (!app) return;

        this.state.token     = app.dataset.token;
        this.state.serviceId = app.dataset.serviceId;
        this.cacheElements();
        this.bindEvents();
    },

    cacheElements() {
        this.elements = {
            bookingDetails: document.getElementById('booking-details'),
            cancelModal:    document.getElementById('cancel-modal'),
            successCard:    document.getElementById('success-card'),

            btnCancel:           document.getElementById('btn-cancel'),
            btnCancelModalClose: document.getElementById('btn-cancel-modal-close'),
            btnConfirmCancel:    document.getElementById('btn-confirm-cancel'),

            rescheduleModal:        document.getElementById('reschedule-modal'),
            btnReschedule:          document.getElementById('btn-reschedule'),
            btnRescheduleClose:     document.getElementById('btn-reschedule-modal-close'),
            btnConfirmReschedule:   document.getElementById('btn-confirm-reschedule'),
            rescheduleDate:         document.getElementById('reschedule-date'),
            rescheduleSlot:         document.getElementById('reschedule-slot'),

            successTitle:   document.getElementById('success-title'),
            successMessage: document.getElementById('success-message'),
        };
    },

    bindEvents() {
        this.elements.btnCancel?.addEventListener('click', () => this.showCancelModal());
        this.elements.btnCancelModalClose?.addEventListener('click', () => this.hideCancelModal());
        this.elements.btnConfirmCancel?.addEventListener('click', () => this.confirmCancel());

        this.elements.cancelModal?.addEventListener('click', (e) => {
            if (e.target === this.elements.cancelModal) {
                this.hideCancelModal();
            }
        });

        this.elements.btnReschedule?.addEventListener('click', () => this.showRescheduleModal());
        this.elements.btnRescheduleClose?.addEventListener('click', () => this.hideRescheduleModal());
        this.elements.btnConfirmReschedule?.addEventListener('click', () => this.confirmReschedule());
        this.elements.rescheduleDate?.addEventListener('change', () => this.loadRescheduleSlots());
        this.elements.rescheduleSlot?.addEventListener('change', () => {
            this.elements.btnConfirmReschedule.disabled = !this.elements.rescheduleSlot.value;
        });
        this.elements.rescheduleModal?.addEventListener('click', (e) => {
            if (e.target === this.elements.rescheduleModal) {
                this.hideRescheduleModal();
            }
        });

        this.bindDeleteDataEvents();
    },

    bindDeleteDataEvents() {
        const btnDeleteData      = document.getElementById('btn-delete-data');
        const deleteModal        = document.getElementById('delete-data-modal');
        const btnDeleteModalClose = document.getElementById('btn-delete-modal-close');
        const btnConfirmDelete   = document.getElementById('btn-confirm-delete');

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

            btnConfirmDelete.disabled    = true;
            btnConfirmDelete.textContent = 'Suppression...';

            try {
                const response = await fetch('../../api/manage.php?action=delete_data', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({
                        token:         ManageApp.state.token,
                        confirm_email: email,
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    document.getElementById('manage-app').innerHTML = `
                        <div class="delete-success">
                            <div class="delete-success__icon">${Icons.svg('check-circle', 56)}</div>
                            <h2 class="delete-success__title">Données supprimées</h2>
                            <p class="delete-success__text">
                                Vos données personnelles ont été supprimées conformément à votre demande.
                                Un email de confirmation vous a été envoyé.
                            </p>
                            <a href="../../" class="btn btn-primary">Retour au site</a>
                        </div>
                    `;
                } else {
                    alert(data.message || 'Erreur lors de la suppression.');
                    btnConfirmDelete.disabled    = false;
                    btnConfirmDelete.textContent = 'Supprimer définitivement';
                }
            } catch (err) {
                alert('Erreur de connexion. Veuillez réessayer.');
                btnConfirmDelete.disabled    = false;
                btnConfirmDelete.textContent = 'Supprimer définitivement';
            }
        });
    },

    showCancelModal() {
        // Mécanisme unifié main.css : .modal-overlay caché par défaut
        // (visibility/opacity), affiché par la classe d'état .active.
        this.elements.cancelModal.classList.add('active');
    },

    hideCancelModal() {
        this.elements.cancelModal.classList.remove('active');
    },

    // ── Déplacement (reschedule) — même algo v3 que l'admin ──
    showRescheduleModal() {
        this.elements.rescheduleDate.min = new Date().toISOString().split('T')[0];
        this.elements.rescheduleModal.classList.add('active');
    },

    hideRescheduleModal() {
        this.elements.rescheduleModal.classList.remove('active');
    },

    /** Charge les créneaux de la date choisie pour la prestation du RDV (API v3). */
    async loadRescheduleSlots() {
        const date = this.elements.rescheduleDate.value;
        const sel  = this.elements.rescheduleSlot;
        this.elements.btnConfirmReschedule.disabled = true;

        if (!date) {
            sel.innerHTML = '<option value="">Choisissez d\'abord une date</option>';
            sel.disabled = true;
            return;
        }
        sel.innerHTML = '<option value="">Chargement…</option>';
        sel.disabled = true;
        try {
            const response = await fetch(`../../api/booking-v3-slots.php?service=${this.state.serviceId}&date=${date}`);
            const data = await response.json();
            if (data.slots && data.slots.length > 0) {
                sel.innerHTML = '<option value="">Choisir un créneau</option>' +
                    data.slots.map(s => `<option value="${s.time_start}|${s.time_end}">${s.label}</option>`).join('');
                sel.disabled = false;
            } else {
                sel.innerHTML = '<option value="">Aucun créneau disponible</option>';
            }
        } catch (error) {
            sel.innerHTML = '<option value="">Erreur de chargement</option>';
            console.error(error);
        }
    },

    async confirmReschedule() {
        const date = this.elements.rescheduleDate.value;
        const slot = this.elements.rescheduleSlot.value;
        if (!date || !slot) { return; }
        const [start, end] = slot.split('|');
        const btn = this.elements.btnConfirmReschedule;
        btn.disabled = true;
        btn.textContent = 'Déplacement…';

        try {
            const response = await fetch('../../api/manage.php?action=reschedule', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ token: this.state.token, new_date: date, new_time_start: start, new_time_end: end }),
            });
            const result = await response.json();
            if (result.success) {
                this.hideRescheduleModal();
                this.showSuccess(
                    'Rendez-vous déplacé',
                    'Votre rendez-vous a été déplacé. Vous recevrez un email de confirmation.'
                );
            } else {
                alert(result.message || result.error || 'Une erreur est survenue');
                btn.disabled = false;
                btn.textContent = 'Déplacer';
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('Erreur de connexion');
            btn.disabled = false;
            btn.textContent = 'Déplacer';
        }
    },

    async confirmCancel() {
        const btn = this.elements.btnConfirmCancel;
        btn.disabled    = true;
        btn.textContent = 'Traitement...';

        try {
            const response = await fetch('../../api/manage.php?action=cancel', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ token: this.state.token }),
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
                btn.disabled    = false;
                btn.textContent = 'Oui, annuler';
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('Erreur de connexion');
            btn.disabled    = false;
            btn.textContent = 'Oui, annuler';
        }
    },

    showSuccess(title, message) {
        this.elements.bookingDetails.style.display = 'none';
        this.elements.successCard.style.display    = 'block';

        this.elements.successTitle.textContent   = title;
        this.elements.successMessage.textContent = message;

        window.scrollTo({ top: 0, behavior: 'smooth' });
    },
};

document.addEventListener('DOMContentLoaded', () => ManageApp.init());

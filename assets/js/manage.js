/**
 * ============================================
 * MANAGE APP — Espace client (Booking v3)
 * ============================================
 * Gestion du RDV par le client : annulation et droit à
 * l'effacement RGPD. Le reschedule client est désactivé depuis
 * la purge 2.6.0 (le calendrier legacy a été retiré avec le
 * tunnel v2) — le rebranchement sur l'algo v3 sera fait dans un
 * patch ultérieur. En attendant, le client annule et re-réserve,
 * ou contacte l'admin.
 *
 * Chemins relatifs : la page vit dans /modules/booking/manage.php
 * (deux niveaux sous la racine), donc l'API est en `../../api/...`.
 */

const ManageApp = {
    state: {
        token: null,
    },

    elements: {},

    init() {
        const app = document.getElementById('manage-app');
        if (!app) return;

        this.state.token = app.dataset.token;
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
        this.elements.cancelModal.style.display = 'flex';
    },

    hideCancelModal() {
        this.elements.cancelModal.style.display = 'none';
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

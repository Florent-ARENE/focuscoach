/**
 * ============================================
 * CALENDAR MODULE - Module calendrier partagé
 * ============================================
 * Utilisé par : booking.js, manage.js
 */

const CalendarModule = {
    // Configuration par défaut
    config: {
        apiBase: '../api/',
        months: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 
                 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
        monthsFr: ['janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                   'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
        days: ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi']
    },
    
    /**
     * Créer une instance de calendrier
     * @param {Object} options - Options de configuration
     * @returns {Object} Instance du calendrier
     */
    create(options = {}) {
        const instance = {
            // State
            state: {
                currentMonth: new Date().getMonth(),
                currentYear: new Date().getFullYear(),
                selectedDate: null,
                selectedSlot: null,
                availableDates: {},
                bookingStats: {},  // Stats par date: { '2026-02-15': { pending: 1, confirmed: 2 } }
            },
            
            // Options (fusionnées avec les defaults)
            options: {
                apiBase: options.apiBase || CalendarModule.config.apiBase,
                showStats: options.showStats !== false,  // Afficher les stats par défaut
                onDateSelect: options.onDateSelect || null,
                onSlotSelect: options.onSlotSelect || null,
                onMonthChange: options.onMonthChange || null,
            },
            
            // Éléments DOM
            elements: {
                calendarGrid: null,
                monthTitle: null,
                prevMonth: null,
                nextMonth: null,
                slotsSection: null,
                slotsGrid: null,
                selectedDateLabel: null,
            },
            
            /**
             * Initialiser le calendrier
             */
            init(elements) {
                this.elements = { ...this.elements, ...elements };
                this.bindEvents();
                this.loadMonthData();
            },
            
            /**
             * Bindind des événements navigation
             */
            bindEvents() {
                this.elements.prevMonth?.addEventListener('click', () => this.changeMonth(-1));
                this.elements.nextMonth?.addEventListener('click', () => this.changeMonth(1));
            },
            
            /**
             * Charger les données du mois (disponibilités + stats)
             */
            async loadMonthData() {
                const { currentYear, currentMonth } = this.state;
                const monthStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}`;
                
                try {
                    const response = await fetch(this.options.apiBase + `slots.php?month=${monthStr}`);
                    const data = await response.json();
                    
                    // Réinitialiser pour ce mois
                    this.state.availableDates = {};
                    
                    // Disponibilités
                    if (data.dates) {
                        Object.entries(data.dates).forEach(([dateStr, info]) => {
                            if (info.available && info.slots_count > 0) {
                                this.state.availableDates[dateStr] = info.slots_count;
                            }
                        });
                    }
                    
                    // Stats de réservations par date
                    if (data.booking_stats) {
                        Object.assign(this.state.bookingStats, data.booking_stats);
                    }
                    
                    this.renderCalendar();
                    
                    // Callback
                    if (this.options.onMonthChange) {
                        this.options.onMonthChange(currentYear, currentMonth);
                    }
                } catch (error) {
                    console.error('Erreur chargement données mois:', error);
                    this.renderCalendar();
                }
            },
            
            /**
             * Changer de mois
             */
            changeMonth(delta) {
                this.state.currentMonth += delta;
                
                if (this.state.currentMonth > 11) {
                    this.state.currentMonth = 0;
                    this.state.currentYear++;
                } else if (this.state.currentMonth < 0) {
                    this.state.currentMonth = 11;
                    this.state.currentYear--;
                }
                
                this.loadMonthData();
            },
            
            /**
             * Afficher le calendrier
             */
            renderCalendar() {
                const { currentMonth, currentYear } = this.state;
                
                // Titre du mois
                if (this.elements.monthTitle) {
                    this.elements.monthTitle.textContent = 
                        `${CalendarModule.config.months[currentMonth]} ${currentYear}`;
                }
                
                if (!this.elements.calendarGrid) return;
                
                // Garder les headers
                const headers = this.elements.calendarGrid.querySelectorAll('.calendar-day-header');
                this.elements.calendarGrid.innerHTML = '';
                headers.forEach(h => this.elements.calendarGrid.appendChild(h));
                
                const firstDay = new Date(currentYear, currentMonth, 1);
                const lastDay = new Date(currentYear, currentMonth + 1, 0);
                const startingDay = (firstDay.getDay() + 6) % 7; // Lundi = 0
                
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                // Jours du mois précédent
                const prevMonthLastDay = new Date(currentYear, currentMonth, 0).getDate();
                for (let i = startingDay - 1; i >= 0; i--) {
                    this.createDayElement(prevMonthLastDay - i, true, null, today);
                }
                
                // Jours du mois courant
                for (let day = 1; day <= lastDay.getDate(); day++) {
                    const date = new Date(currentYear, currentMonth, day);
                    const dateStr = this.formatDate(date);
                    this.createDayElement(day, false, dateStr, today, date);
                }
                
                // Jours du mois suivant
                const totalCells = startingDay + lastDay.getDate();
                const remaining = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
                for (let i = 1; i <= remaining; i++) {
                    this.createDayElement(i, true, null, today);
                }
            },
            
            /**
             * Créer un élément jour
             */
            createDayElement(dayNum, isOtherMonth, dateStr, today, date = null) {
                const dayEl = document.createElement('div');
                dayEl.className = 'calendar-day';
                
                // Numéro du jour
                const dayNumber = document.createElement('span');
                dayNumber.className = 'day-number';
                dayNumber.textContent = dayNum;
                dayEl.appendChild(dayNumber);
                
                if (isOtherMonth) {
                    dayEl.classList.add('other-month', 'disabled');
                } else if (date) {
                    dayEl.dataset.date = dateStr;
                    
                    // Marqueur aujourd'hui
                    if (date.getTime() === today.getTime()) {
                        dayEl.classList.add('today');
                    }
                    
                    // Disponibilité
                    if (date < today || !this.state.availableDates[dateStr]) {
                        dayEl.classList.add('disabled');
                    } else {
                        dayEl.classList.add('available');
                        dayEl.addEventListener('click', () => this.selectDate(dateStr));
                    }
                    
                    // Sélectionné
                    if (this.state.selectedDate === dateStr) {
                        dayEl.classList.add('selected');
                    }
                    
                    // Afficher les stats de réservation pour ce jour (si activé)
                    if (this.options.showStats) {
                        const stats = this.state.bookingStats[dateStr];
                        if (stats && (stats.pending > 0 || stats.confirmed > 0)) {
                            const indicatorsEl = document.createElement('div');
                            indicatorsEl.className = 'day-indicators';
                            
                            if (stats.confirmed > 0) {
                                const confirmedEl = document.createElement('span');
                                confirmedEl.className = 'indicator confirmed';
                                confirmedEl.title = `${stats.confirmed} confirmé${stats.confirmed > 1 ? 's' : ''}`;
                                confirmedEl.textContent = stats.confirmed;
                                indicatorsEl.appendChild(confirmedEl);
                            }
                            
                            if (stats.pending > 0) {
                                const pendingEl = document.createElement('span');
                                pendingEl.className = 'indicator pending';
                                pendingEl.title = `${stats.pending} en attente`;
                                pendingEl.textContent = stats.pending;
                                indicatorsEl.appendChild(pendingEl);
                            }
                            
                            dayEl.appendChild(indicatorsEl);
                        }
                    }
                }
                
                this.elements.calendarGrid.appendChild(dayEl);
            },
            
            /**
             * Sélectionner une date
             */
            async selectDate(dateStr) {
                this.state.selectedDate = dateStr;
                this.state.selectedSlot = null;
                
                // Mettre à jour le calendrier
                this.elements.calendarGrid.querySelectorAll('.calendar-day').forEach(el => {
                    el.classList.remove('selected');
                    if (el.dataset.date === dateStr) {
                        el.classList.add('selected');
                    }
                });
                
                // Charger les créneaux
                await this.loadSlots(dateStr);
                
                // Callback
                if (this.options.onDateSelect) {
                    this.options.onDateSelect(dateStr);
                }
            },
            
            /**
             * Charger les créneaux pour une date
             */
            async loadSlots(dateStr) {
                if (!this.elements.slotsSection || !this.elements.slotsGrid) return;
                
                this.elements.slotsSection.style.display = 'block';
                this.elements.slotsGrid.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
                
                if (this.elements.selectedDateLabel) {
                    this.elements.selectedDateLabel.textContent = this.formatDateFr(dateStr);
                }
                
                try {
                    const response = await fetch(this.options.apiBase + `slots.php?date=${dateStr}`);
                    const data = await response.json();
                    
                    if (data.slots && data.slots.length > 0) {
                        this.renderSlots(data.slots);
                    } else {
                        this.elements.slotsGrid.innerHTML = '<div class="no-slots">Aucun créneau disponible pour cette date</div>';
                    }
                } catch (error) {
                    console.error('Erreur chargement créneaux:', error);
                    this.elements.slotsGrid.innerHTML = '<div class="no-slots">Erreur de chargement</div>';
                }
            },
            
            /**
             * Afficher les créneaux
             */
            renderSlots(slots) {
                this.elements.slotsGrid.innerHTML = '';
                
                slots.forEach(slot => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'slot-btn';
                    btn.textContent = slot.label;
                    btn.dataset.start = slot.time_start;
                    btn.dataset.end = slot.time_end;
                    
                    btn.addEventListener('click', () => this.selectSlot(slot, btn));
                    
                    this.elements.slotsGrid.appendChild(btn);
                });
            },
            
            /**
             * Sélectionner un créneau
             */
            selectSlot(slot, btn) {
                this.state.selectedSlot = slot;
                
                // Mettre à jour la sélection visuelle
                this.elements.slotsGrid.querySelectorAll('.slot-btn').forEach(b => {
                    b.classList.remove('selected');
                });
                btn.classList.add('selected');
                
                // Callback
                if (this.options.onSlotSelect) {
                    this.options.onSlotSelect(slot);
                }
            },
            
            /**
             * Formater une date en YYYY-MM-DD
             */
            formatDate(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            },
            
            /**
             * Formater une date en français
             */
            formatDateFr(dateStr) {
                const date = new Date(dateStr);
                return `${CalendarModule.config.days[date.getDay()]} ${date.getDate()} ${CalendarModule.config.monthsFr[date.getMonth()]}`;
            },
            
            /**
             * Réinitialiser la sélection
             */
            reset() {
                this.state.selectedDate = null;
                this.state.selectedSlot = null;
                if (this.elements.slotsSection) {
                    this.elements.slotsSection.style.display = 'none';
                }
                this.renderCalendar();
            },
            
            /**
             * Obtenir la sélection actuelle
             */
            getSelection() {
                return {
                    date: this.state.selectedDate,
                    slot: this.state.selectedSlot
                };
            }
        };
        
        return instance;
    }
};

// Export pour utilisation en module ES6 si nécessaire
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CalendarModule;
}

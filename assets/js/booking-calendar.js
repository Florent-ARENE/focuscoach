/**
 * ============================================
 * BOOKING v3 — Calendrier de choix de date (vanilla, zéro lib)
 * ============================================
 * Progressive enhancement : la page `date.php` rend une LISTE server-side
 * (fallback sans JS). Si ce script tourne, il montre un calendrier mensuel
 * à la place et masque la liste.
 *
 * Données : API mois existante
 *   GET ../../api/booking-v3-slots.php?service=<id>&month=YYYY-MM
 *   → { dates: { "YYYY-MM-DD": { available: bool, slots_count: int }, … } }
 * (l'API borne déjà à [aujourd'hui, aujourd'hui + MAX_HORIZON_DAYS] ; les
 *  jours hors plage sont simplement absents → rendus « indisponibles ».)
 *
 * Icônes via Icons.svg() (icons.js chargé avant). Aucune valeur interpolée
 * n'est d'origine utilisateur (dates/compteurs construits côté code) → le
 * `innerHTML` est sûr ici.
 */
(function () {
    'use strict';

    var mount = document.getElementById('bv3-calendar');
    if (!mount) { return; }

    var serviceId = parseInt(mount.getAttribute('data-service'), 10);
    var minMonth  = mount.getAttribute('data-min-month'); // "YYYY-MM"
    var maxMonth  = mount.getAttribute('data-max-month');
    var today     = mount.getAttribute('data-today');     // "YYYY-MM-DD" (serveur)
    var fallback  = document.getElementById('bv3-dates-fallback');

    var DOW       = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
    var DOW_FULL  = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    var MONTHS    = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                     'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    var cache   = {};
    var current = minMonth;

    function pad(n) { return n < 10 ? '0' + n : String(n); }

    function icon(name) {
        return (typeof Icons !== 'undefined' && Icons.svg) ? Icons.svg(name, 20, 'icon-inline') : '';
    }

    /** Décale un mois "YYYY-MM" de ±1. */
    function shiftMonth(month, dir) {
        var y = parseInt(month.slice(0, 4), 10);
        var m = parseInt(month.slice(5, 7), 10) + dir;
        if (m < 1)       { m = 12; y -= 1; }
        else if (m > 12) { m = 1;  y += 1; }
        return y + '-' + pad(m);
    }

    function fetchMonth(month) {
        if (cache[month]) { return Promise.resolve(cache[month]); }
        var url = '../../api/booking-v3-slots.php?service=' + serviceId + '&month=' + month;
        return fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : { dates: {} }; })
            .then(function (j) { cache[month] = (j && j.dates) || {}; return cache[month]; })
            .catch(function () { cache[month] = {}; return cache[month]; });
    }

    function buildGrid(month, dates) {
        var y     = parseInt(month.slice(0, 4), 10);
        var m     = parseInt(month.slice(5, 7), 10);          // 1-12
        var first = new Date(y, m - 1, 1);
        var offset = (first.getDay() + 6) % 7;                // lundi = 0
        var days   = new Date(y, m, 0).getDate();

        var html = '<div class="bv3-cal-dow" aria-hidden="true">';
        for (var w = 0; w < 7; w++) { html += '<span>' + DOW[w] + '</span>'; }
        html += '</div><div class="bv3-cal-grid" role="grid">';

        for (var o = 0; o < offset; o++) {
            html += '<span class="bv3-cal-pad" aria-hidden="true"></span>';
        }

        for (var d = 1; d <= days; d++) {
            var dateStr = y + '-' + pad(m) + '-' + pad(d);
            var info    = dates[dateStr];
            var todayCls = (dateStr === today) ? ' is-today' : '';
            if (info && info.available) {
                var n   = parseInt(info.slots_count, 10) || 0;
                var dow = DOW_FULL[(new Date(y, m - 1, d).getDay() + 6) % 7];
                var lbl = dow + ' ' + d + ' ' + MONTHS[m - 1] + ' ' + y + ', '
                        + n + ' créneau' + (n > 1 ? 'x' : '') + ' disponible' + (n > 1 ? 's' : '');
                html += '<a class="bv3-cal-day is-available' + todayCls + '" role="gridcell" '
                      + 'href="slot.php?service=' + serviceId + '&date=' + dateStr + '" '
                      + 'title="' + n + ' créneaux" aria-label="' + lbl + '">'
                      + '<span class="bv3-cal-num">' + d + '</span></a>';
            } else {
                html += '<span class="bv3-cal-day is-off' + todayCls + '" role="gridcell" aria-disabled="true">'
                      + '<span class="bv3-cal-num">' + d + '</span></span>';
            }
        }
        return html + '</div>';
    }

    function render() {
        mount.setAttribute('aria-busy', 'true');
        fetchMonth(current).then(function (dates) {
            var y = parseInt(current.slice(0, 4), 10);
            var m = parseInt(current.slice(5, 7), 10);

            var head = '<div class="bv3-cal-head">'
                + '<button type="button" class="bv3-cal-nav" data-dir="-1" aria-label="Mois précédent"'
                + (current <= minMonth ? ' disabled' : '') + '>' + icon('arrow-left') + '</button>'
                + '<span class="bv3-cal-title" aria-live="polite">' + MONTHS[m - 1] + ' ' + y + '</span>'
                + '<button type="button" class="bv3-cal-nav" data-dir="1" aria-label="Mois suivant"'
                + (current >= maxMonth ? ' disabled' : '') + '>' + icon('arrow-right') + '</button>'
                + '</div>';

            mount.innerHTML = head + buildGrid(current, dates);
            mount.removeAttribute('aria-busy');

            var btns = mount.querySelectorAll('.bv3-cal-nav');
            for (var i = 0; i < btns.length; i++) {
                btns[i].addEventListener('click', function () {
                    if (this.disabled) { return; }
                    var next = shiftMonth(current, parseInt(this.getAttribute('data-dir'), 10));
                    if (next < minMonth || next > maxMonth) { return; }
                    current = next;
                    render();
                });
            }
        });
    }

    // JS présent → calendrier visible, liste de secours masquée.
    if (fallback) { fallback.hidden = true; }
    mount.hidden = false;
    render();
})();

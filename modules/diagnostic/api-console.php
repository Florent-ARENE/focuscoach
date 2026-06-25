<?php
/**
 * ============================================
 * DIAGNOSTIC — CONSOLE API (AD-11)
 * ============================================
 * Interroge un endpoint JSON de l'app en LECTURE SEULE (GET) et affiche le
 * JSON formaté. La requête est faite CÔTÉ NAVIGATEUR (fetch) : c'est le
 * client naturel d'une console, et ça évite un loopback serveur→Apache
 * (non fiable en imbriqué sur cet hôte). Aucun POST, aucune écriture.
 * Un paramètre invalide → l'endpoint renvoie un JSON d'erreur, affiché tel quel.
 */

require_once __DIR__ . '/_diagnostic.php';

use App\Helpers;
use App\Icons;

diag_require_admin();

$endpoint = 'api/booking-v3-slots.php';

echo diag_head('Console API');
?>
<p class="diag-lead">Lecture seule (GET). Un paramètre invalide renvoie un JSON d'erreur, affiché tel quel — rien n'est écrit.</p>

<form id="diag-api-form" class="diag-form">
    <input class="diag-input" id="diag-svc" type="number" min="1" value="1" placeholder="service (id)">
    <input class="diag-input" id="diag-date" type="text" value="" placeholder="date YYYY-MM-DD (option.)">
    <button class="diag-btn" type="submit"><?= Icons::svg('terminal', 16) ?><span>Interroger</span></button>
</form>

<?= diag_render_card('ok', 'GET ' . Helpers::escape($endpoint),
    '<pre class="diag-console" id="diag-out">Prêt. Renseigne un service puis « Interroger ».</pre>') ?>

<script>
(function () {
    var base = <?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES) ?>;
    var endpoint = <?= json_encode($endpoint) ?>;
    var form = document.getElementById('diag-api-form');
    var out  = document.getElementById('diag-out');

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var svc  = encodeURIComponent(document.getElementById('diag-svc').value || '1');
        var date = document.getElementById('diag-date').value.trim();
        var url  = base + endpoint + '?service=' + svc;
        if (date) { url += '&date=' + encodeURIComponent(date); }

        out.textContent = 'GET ' + url + ' …';
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.text().then(function (t) { return { status: r.status, text: t }; }); })
            .then(function (res) {
                var body = res.text;
                try { body = JSON.stringify(JSON.parse(res.text), null, 2); } catch (err) { /* brut si non-JSON */ }
                out.textContent = 'HTTP ' + res.status + '\n\n' + body;
            })
            .catch(function (err) { out.textContent = 'Erreur réseau : ' + err; });
    });
})();
</script>
<?php
echo diag_foot();

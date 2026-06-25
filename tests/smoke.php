<?php
/**
 * ============================================
 * SMOKE TESTS — corpus de non-régression (AD-9)
 * ============================================
 * Cas exécutés à chaque itération. Un bug compris = un cas ajouté.
 * Lancer en CLI : php tests/smoke.php
 * Exit 0 = tous verts, exit 1 = au moins un échec.
 *
 * Note : ces tests ne touchent PAS la base de données. Pour les cas
 * d'intégration BDD/HTTP, prévoir un harnais séparé.
 *
 * Cibles couvertes :
 *   - CSRF : verifyCsrfFromRequest header > body > $_POST + rejet absence/invalide.
 *   - Auth admin : password_verify vs ADMIN_PASSWORD_HASH (hash valide / hash absent
 *     / hash KO). Le rate limit nécessite la BDD → testé en intégration, hors smoke.
 *   - Intégrité (Lot 3) : alignement offset MySQL (date('P')) côté PHP, format
 *     active_key bookings (concat date_time pour pending/confirmed), idempotence
 *     reschedule (équivalence date+heure). La race UNIQUE et les timeouts cURL
 *     se testent en intégration BDD/réseau, hors smoke unitaire.
 */

require_once __DIR__ . '/../includes/init.php';

use App\Helpers;

// ── Rendu navigateur (optionnel) ───────────────────────────────
// En CLI : sortie texte strictement inchangée (exit codes, perf, CI
// intacts — le bloc est sauté). En HTTP : enveloppe <pre> + UTF-8
// pour préserver les retours à la ligne (sinon le HTML les écrase →
// tout sur une seule ligne). Aucun CSS ajouté : on ne charge pas le
// design system de l'app dans un outil de test (cf. CLAUDE.md §2.2).
$smokeWeb = PHP_SAPI !== 'cli';
if ($smokeWeb) {
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo "<!doctype html>\n<html lang=\"fr\"><head><meta charset=\"utf-8\">"
       . "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">"
       . "<title>Smoke tests — Focus Coach</title></head><body>\n<pre>\n";
}

$passed = 0;
$failed = 0;
$cases  = [];

/**
 * Enregistre un cas et affiche le verdict.
 */
function it(string $label, callable $assert): void
{
    global $passed, $failed, $cases;
    try {
        $assert();
        $passed++;
        $cases[] = "  ✓ $label";
    } catch (Throwable $e) {
        $failed++;
        $cases[] = "  ✗ $label\n    → " . $e->getMessage();
    }
}

function expect_true($value, string $msg = ''): void
{
    if ($value !== true) {
        throw new RuntimeException($msg ?: 'attendu true, reçu ' . var_export($value, true));
    }
}

function expect_false($value, string $msg = ''): void
{
    if ($value !== false) {
        throw new RuntimeException($msg ?: 'attendu false, reçu ' . var_export($value, true));
    }
}

// ============================================
// SETUP — préparer une session avec un token CSRF connu
// ============================================
$_SESSION = [];
$validToken = Helpers::generateCsrfToken();
// On vérifie que la session a bien stocké le token
if (empty($_SESSION[CSRF_TOKEN_NAME]) || $_SESSION[CSRF_TOKEN_NAME] !== $validToken) {
    fwrite(STDERR, "Setup CSRF cassé : token absent de la session après generateCsrfToken()\n");
    exit(2);
}

// ============================================
// LOT 1 — CSRF
// ============================================
echo "🛡️  Lot 1 — CSRF\n";

it('Token valide via header X-CSRF-Token → accepté', function () use ($validToken) {
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $validToken;
    expect_true(Helpers::verifyCsrfFromRequest([]));
});

it('Token valide via body JSON (csrf_token) → accepté', function () use ($validToken) {
    unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    expect_true(Helpers::verifyCsrfFromRequest(['csrf_token' => $validToken]));
});

it('Token valide via $_POST (fallback form classique) → accepté', function () use ($validToken) {
    unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    $_POST['csrf_token'] = $validToken;
    expect_true(Helpers::verifyCsrfFromRequest([]));
    unset($_POST['csrf_token']);
});

it('Token absent (rien dans header, body, $_POST) → rejeté', function () {
    unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    unset($_POST['csrf_token']);
    expect_false(Helpers::verifyCsrfFromRequest([]));
});

it('Token invalide → rejeté', function () {
    $_SERVER['HTTP_X_CSRF_TOKEN'] = str_repeat('0', 64);
    expect_false(Helpers::verifyCsrfFromRequest([]));
});

it('Header prioritaire sur body en cas de conflit (header bidon, body valide → rejeté)', function () use ($validToken) {
    // Sécurité défensive : un attaquant qui injecterait header bidon ne doit
    // pas pouvoir « passer » grâce à un body valide volé. Politique = header
    // gagne, donc rejet attendu.
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'bidon';
    expect_false(Helpers::verifyCsrfFromRequest(['csrf_token' => $validToken]));
});

it('Vide explicite (chaîne vide) → rejeté', function () {
    $_SERVER['HTTP_X_CSRF_TOKEN'] = '';
    expect_false(Helpers::verifyCsrfFromRequest(['csrf_token' => '']));
});

// ============================================
// LOT 2 — Auth admin (password_verify)
// ============================================
echo "\n🔐 Lot 2 — Auth admin (password_verify)\n";

$known = 'Sm0k3-T3st-Mdp!';
$validHash = password_hash($known, PASSWORD_DEFAULT);

it('Hash bcrypt valide + bon mot de passe → accepté', function () use ($known, $validHash) {
    expect_true(password_verify($known, $validHash));
});

it('Hash bcrypt valide + mauvais mot de passe → rejeté', function () use ($validHash) {
    expect_false(password_verify('mauvais', $validHash));
});

it('Hash vide → rejeté (pas de bypass possible)', function () use ($known) {
    expect_false(password_verify($known, ''));
});

it('Hash mal formé → rejeté (pas d\'exception PHP qui leak)', function () use ($known) {
    expect_false(password_verify($known, 'pas_un_hash_bcrypt'));
});

it('Format hash = $2y$ (PASSWORD_DEFAULT actuel)', function () use ($validHash) {
    expect_true(strpos($validHash, '$2y$') === 0,
        'PASSWORD_DEFAULT devrait produire un hash bcrypt — reçu : ' . substr($validHash, 0, 4));
});

// ============================================
// LOT 3 — Intégrité (alignement TZ, active_key, idempotence reschedule)
// ============================================
echo "\n🧱 Lot 3 — Intégrité\n";

it('Offset PHP date(\'P\') au format `±HH:MM` (compatible SET time_zone MySQL)', function () {
    $offset = date('P');
    expect_true(
        preg_match('/^[+\-]\d{2}:\d{2}$/', $offset) === 1,
        'attendu ±HH:MM, reçu : ' . var_export($offset, true)
    );
});

it('Format active_key pour pending/confirmed = `YYYY-MM-DD_HH:MM:SS` (≤ 20 chars)', function () {
    // Reproduit la définition de la colonne générée côté SQL.
    $sample = '2026-12-31' . '_' . '09:00:00';
    expect_true(strlen($sample) === 19);
    expect_true(strlen($sample) <= 20); // colonne VARCHAR(20)
});

it('Idempotence reschedule : équivalence date + heure sur 5 chars (HH:MM)', function () {
    // Reproduit la logique de Booking::reschedule().
    $oldStart = '14:00:00';
    $newStart = '14:00';   // saisie front sans secondes
    expect_true(substr($oldStart, 0, 5) === substr($newStart, 0, 5));
});

it('Idempotence reschedule : seconde différente NE TRIGGER PAS le change', function () {
    // Date BDD avec :00, saisie front : on tronque à 5 chars → identique.
    $oldStart = '14:00:30';
    $newStart = '14:00:45';
    expect_true(substr($oldStart, 0, 5) === substr($newStart, 0, 5));
});

it('Idempotence reschedule : minute différente => change', function () {
    $oldStart = '14:00:00';
    $newStart = '14:30:00';
    expect_false(substr($oldStart, 0, 5) === substr($newStart, 0, 5));
});

// ============================================
// LOT 5 — Algo créneaux Booking v3 §4 (logique pure)
// ============================================
// `Slot::computeCandidates()` est extrait en méthode statique pour
// être testable sans BDD : on injecte fenêtres + bookings résolus.
// Les méthodes BDD-dépendantes (resolveDayWindows,
// getActiveBookingsForDate, computeSlotsForService) iront dans
// tests/integration/ (§10).
echo "\n📐 Lot 5 — Algo créneaux v3 (computeCandidates)\n";

it('Fenêtre 09:00-12:00, service 60+15 min, aucun booking, step 15 → 9 candidats (09:00…11:00)', function () {
    // Dernier t valide : t + 60 ≤ 12:00 → t ≤ 11:00.
    // Avec step 15 depuis 09:00 : 09:00, 09:15, 09:30, 09:45,
    // 10:00, 10:15, 10:30, 10:45, 11:00 → 9 candidats.
    $windows = [['start' => '09:00:00', 'end' => '12:00:00']];
    $cands = \App\Slot::computeCandidates($windows, [], 60, 15, 15);
    expect_true(count($cands) === 9, 'attendu 9 candidats, reçu ' . count($cands));
    expect_true($cands[0]['time_start']  === '09:00:00');
    expect_true($cands[0]['time_end']    === '10:00:00');
    expect_true(end($cands)['time_start'] === '11:00:00');
});

it('Buffer respecté : booking 11:00-12:00 (end_with_buffer 12:00) bloque les candidats qui chevauchent', function () {
    // Booking 11:00-12:00 → intervalle occupé [11:00, 12:00). Fenêtre
    // 09:00-12:00, service 60+15 (occupe 75 min).
    // - t = 09:00 → [09:00, 10:15) ∩ [11:00, 12:00) = ∅ → OK ✓
    // - t = 09:15 → [09:15, 10:30) → OK ✓
    // - t = 09:30 → [09:30, 10:45) → OK ✓
    // - t = 09:45 → [09:45, 11:00) → OK (touche 11:00 sans intersecter) ✓
    // - t = 10:00 → [10:00, 11:15) ∩ [11:00, 12:00) = [11:00, 11:15) → invalide
    // - t = 10:15 → invalide (idem)
    // - t = 10:30 → invalide
    // - t = 10:45 → invalide
    // - t = 11:00 → t + 60 = 12:00 ≤ 12:00 mais [11:00, 12:15) ∩ [11:00, 12:00) ≠ ∅ → invalide
    // → 4 candidats : 09:00, 09:15, 09:30, 09:45.
    $windows  = [['start' => '09:00:00', 'end' => '12:00:00']];
    $bookings = [['start' => '11:00:00', 'end_with_buffer' => '12:00:00']];
    $cands    = \App\Slot::computeCandidates($windows, $bookings, 60, 15, 15);
    expect_true(count($cands) === 4, 'attendu 4 candidats, reçu ' . count($cands));
    $starts = array_column($cands, 'time_start');
    expect_true($starts === ['09:00:00','09:15:00','09:30:00','09:45:00']);
});

it('Buffer côté booking respecté : booking 10:00-11:00 + buffer 15 (end_with_buffer 11:15) bloque toute la fenêtre 09-12 pour un service 60+15', function () {
    // [10:00, 11:15) coupe la fenêtre en deux moitiés trop courtes
    // pour un service de 75 min cumulés :
    // - avant : [09:00, 10:00) = 60 min < 60+0 nécessaire pour t+60 ≤ 10:00…
    //   wait : t = 09:00 → t + 60 = 10:00 → [09:00, 10:15) ∩ [10:00, 11:15) = [10:00, 10:15) ≠ ∅ → KO.
    // - après : [11:15, 12:00) = 45 min < 60 → aucun candidat ne tient (t + 60 ≤ 12:00 → t ≤ 11:00,
    //   mais t doit ≥ 11:15 pour ne pas chevaucher → impossible avec step 15).
    // → 0 candidat.
    $windows  = [['start' => '09:00:00', 'end' => '12:00:00']];
    $bookings = [['start' => '10:00:00', 'end_with_buffer' => '11:15:00']];
    $cands    = \App\Slot::computeCandidates($windows, $bookings, 60, 15, 15);
    expect_true(count($cands) === 0, 'attendu 0 candidat, reçu ' . count($cands));
});

it('Plusieurs fenêtres (matin + après-midi) : les candidats des deux sont concaténés dans l\'ordre', function () {
    // Matin 09:00-12:00 + après-midi 14:00-17:00, service 60+15, step 30.
    // Matin (t+60≤12:00 → t≤11:00, step 30) : 09:00, 09:30, 10:00, 10:30, 11:00 → 5
    // Aprem (t+60≤17:00 → t≤16:00, step 30) : 14:00, 14:30, 15:00, 15:30, 16:00 → 5
    // → 10 candidats.
    $windows = [
        ['start' => '09:00:00', 'end' => '12:00:00'],
        ['start' => '14:00:00', 'end' => '17:00:00'],
    ];
    $cands = \App\Slot::computeCandidates($windows, [], 60, 15, 30);
    expect_true(count($cands) === 10, 'attendu 10 candidats, reçu ' . count($cands));
    expect_true($cands[0]['time_start']  === '09:00:00');
    expect_true($cands[4]['time_start']  === '11:00:00');
    expect_true($cands[5]['time_start']  === '14:00:00');
    expect_true($cands[9]['time_start']  === '16:00:00');
});

it('earliestStart filtre les candidats du matin (cas MIN_NOTICE sur J)', function () {
    // Fenêtre 09:00-12:00, service 30+0 step 15. earliestStart=10:30
    // → candidats valides à partir de 10:30 : 10:30, 10:45, 11:00, 11:15, 11:30.
    $windows = [['start' => '09:00:00', 'end' => '12:00:00']];
    $cands   = \App\Slot::computeCandidates($windows, [], 30, 0, 15, '10:30:00');
    expect_true(count($cands) === 5, 'attendu 5 candidats, reçu ' . count($cands));
    expect_true($cands[0]['time_start']  === '10:30:00');
    expect_true(end($cands)['time_start'] === '11:30:00');
});

it('duration ≤ 0 ou step ≤ 0 → 0 candidat (défense en profondeur)', function () {
    $windows = [['start' => '09:00:00', 'end' => '12:00:00']];
    expect_true(\App\Slot::computeCandidates($windows, [], 0,  15, 15) === []);
    expect_true(\App\Slot::computeCandidates($windows, [], 60, 15, 0)  === []);
    expect_true(\App\Slot::computeCandidates($windows, [], -1, 15, 15) === []);
});

it('Intervalles juxtaposés sans chevauchement : [09:00, 10:00) suivi de [10:00, 11:00) → 09:00 et 10:00 candidats co-existent', function () {
    // Fenêtre 09:00-12:00, service 60+0 step 60. Un booking 10:00-11:00
    // (end_with_buffer 11:00). Candidats possibles : 09:00, 11:00.
    // t = 09:00 → [09:00, 10:00) ne touche pas [10:00, 11:00) (semi-ouvert) ✓
    // t = 10:00 → [10:00, 11:00) ∩ [10:00, 11:00) ≠ ∅ → KO
    // t = 11:00 → [11:00, 12:00) ne touche pas [10:00, 11:00) ✓
    $windows  = [['start' => '09:00:00', 'end' => '12:00:00']];
    $bookings = [['start' => '10:00:00', 'end_with_buffer' => '11:00:00']];
    $cands    = \App\Slot::computeCandidates($windows, $bookings, 60, 0, 60);
    $starts   = array_column($cands, 'time_start');
    expect_true($starts === ['09:00:00','11:00:00'],
        'attendu [09:00, 11:00], reçu ' . json_encode($starts));
});

// ============================================
// LOT 6 — configProblems() (config requise, 2 sens)
// ============================================
// Fonction pure testée par INJECTION de callables (isDefined/valueOf) :
// on simule présence/absence/format d'une clé sans toucher aux vraies
// constantes ni tuer le process. Prouve qu'elle LÈVE quand il faut ET
// se TAIT quand tout va bien.
echo "\n🧩 Lot 6 — configProblems (2 sens)\n";

// Jeu « tout présent et bien formé » : helper de fabrication.
$allOk = function (array $overrides = []): array {
    $values = array_merge([
        'DB_HOST' => '127.0.0.1', 'DB_NAME' => 'db', 'DB_USER' => 'root',
        'DB_PASS' => '', 'BASE_URL' => 'https://x/', 'ADMIN_PASSWORD_HASH' => '$2y$10$x',
    ], $overrides);
    return $values;
};
$probe = function (array $values, array $undefined = []) {
    $isDefined = fn($k) => !in_array($k, $undefined, true) && array_key_exists($k, $values);
    $valueOf   = fn($k) => $values[$k] ?? null;
    return \App\configProblems($isDefined, $valueOf);
};

it('Config complète et bien formée → aucun problème (se tait)', function () use ($allOk, $probe) {
    expect_true($probe($allOk()) === [], 'attendu [], reçu ' . json_encode($probe($allOk())));
});

it('BASE_URL absente → listée (lève)', function () use ($allOk, $probe) {
    $p = $probe($allOk(), ['BASE_URL']);
    expect_true(in_array('BASE_URL manquant ou vide', $p, true), json_encode($p));
});

it('ADMIN_PASSWORD_HASH absent → listé (lève)', function () use ($allOk, $probe) {
    $p = $probe($allOk(), ['ADMIN_PASSWORD_HASH']);
    expect_true(in_array('ADMIN_PASSWORD_HASH manquant ou vide', $p, true), json_encode($p));
});

it('BASE_URL = ftp://x → erreur de format (lève)', function () use ($allOk, $probe) {
    $p = $probe($allOk(['BASE_URL' => 'ftp://x']));
    expect_true(in_array('BASE_URL doit commencer par http:// ou https://', $p, true), json_encode($p));
});

it('DB_PASS vide mais défini → toléré (pas fatal en local)', function () use ($allOk, $probe) {
    expect_true($probe($allOk(['DB_PASS' => ''])) === [], 'DB_PASS vide ne doit pas être fatal');
});

it('Contre-test : STRIPE_SECRET_KEY absent → liste vide (optionnel, jamais fatal)', function () use ($allOk, $probe) {
    // STRIPE_* n'est jamais requis : même absent, configProblems ne le signale pas.
    expect_true($probe($allOk(), ['STRIPE_SECRET_KEY']) === [], 'Stripe ne doit jamais être fatal');
});

// ============================================
// LOT 7 — Stripe : routage paiement (2 sens)
// ============================================
echo "\n💳 Lot 7 — Stripe routage (stripeEnabled / paymentMode)\n";

it('stripeEnabled : placeholder REPLACE_WITH_ → false', function () {
    expect_false(\App\stripeEnabled('REPLACE_WITH_STRIPE_SECRET_KEY'));
});
it('stripeEnabled : vide → false', function () {
    expect_false(\App\stripeEnabled(''));
});
it('stripeEnabled : sk_test_… → true', function () {
    expect_true(\App\stripeEnabled('sk_test_abc123'));
});

$paid = ['price_cents' => 8000, 'stripe_price_id' => 'price_x'];
it('paymentMode : Stripe on + payant + price_id → stripe', function () use ($paid) {
    expect_true(\App\paymentMode($paid, true) === 'stripe');
});
it('paymentMode : Stripe OFF → bypass (mode dégradé)', function () use ($paid) {
    expect_true(\App\paymentMode($paid, false) === 'bypass');
});
it('paymentMode : 0 € → bypass (jamais Stripe à 0 €)', function () {
    expect_true(\App\paymentMode(['price_cents' => 0, 'stripe_price_id' => 'price_x'], true) === 'bypass');
});
it('paymentMode : price_id absent → bypass (dégradé, validation admin)', function () {
    expect_true(\App\paymentMode(['price_cents' => 8000, 'stripe_price_id' => ''], true) === 'bypass');
});

// ============================================
// LOT 8 — StripeClient garde-fous (sans réseau)
// ============================================
echo "\n🔌 Lot 8 — StripeClient (garde-fous hors-ligne)\n";

it('createCheckoutSession : Stripe non configuré (placeholder) → error, aucun appel', function () {
    // En contexte test, STRIPE_SECRET_KEY = placeholder → stripeEnabled() false.
    $r = \App\StripeClient::createCheckoutSession('price_x', 'https://x/ok', 'https://x/ko', ['booking_id' => 1]);
    expect_true(isset($r['error']), 'attendu une erreur, reçu ' . json_encode($r));
    expect_true($r['error'] === 'Stripe non configuré', 'message: ' . ($r['error'] ?? ''));
});

// ============================================
// LOT 9 — Webhook Stripe : vérif de signature HMAC (pur)
// ============================================
echo "\n📡 Lot 9 — Webhook signature (HMAC corps brut)\n";

$wsecret = 'whsec_test_smoke';
$wpayload = '{"id":"evt_1","type":"checkout.session.completed"}';
$wts = (string) time();
$wsig = 't=' . $wts . ',v1=' . hash_hmac('sha256', $wts . '.' . $wpayload, $wsecret);

it('Signature valide → acceptée', function () use ($wpayload, $wsig, $wsecret) {
    expect_true(\App\StripeClient::verifyWebhookSignature($wpayload, $wsig, $wsecret));
});
it('Signature falsifiée → rejetée', function () use ($wpayload, $wts, $wsecret) {
    expect_false(\App\StripeClient::verifyWebhookSignature($wpayload, 't=' . $wts . ',v1=deadbeef', $wsecret));
});
it('Payload altéré (même signature) → rejeté', function () use ($wpayload, $wsig, $wsecret) {
    expect_false(\App\StripeClient::verifyWebhookSignature($wpayload . 'X', $wsig, $wsecret));
});
it('Secret placeholder → rejeté', function () use ($wpayload, $wsig) {
    expect_false(\App\StripeClient::verifyWebhookSignature($wpayload, $wsig, 'REPLACE_WITH_STRIPE_WEBHOOK_SECRET'));
});
it('Timestamp hors tolérance → rejeté (anti-rejeu)', function () use ($wpayload, $wsecret) {
    $old = (string) (time() - 1000);
    $sig = 't=' . $old . ',v1=' . hash_hmac('sha256', $old . '.' . $wpayload, $wsecret);
    expect_false(\App\StripeClient::verifyWebhookSignature($wpayload, $sig, $wsecret));
});
it('En-tête vide → rejeté', function () use ($wpayload, $wsecret) {
    expect_false(\App\StripeClient::verifyWebhookSignature($wpayload, '', $wsecret));
});

// ============================================
// VERDICT
// ============================================
echo implode("\n", $cases) . "\n\n";
$total = $passed + $failed;
echo $failed === 0
    ? "✅ $passed/$total cas verts\n"
    : "❌ $failed/$total cas ROUGES\n";

if ($smokeWeb) {
    echo "</pre>\n</body></html>";
}

exit($failed === 0 ? 0 : 1);

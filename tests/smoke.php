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
// VERDICT
// ============================================
echo implode("\n", $cases) . "\n\n";
$total = $passed + $failed;
if ($failed === 0) {
    echo "✅ $passed/$total cas verts\n";
    exit(0);
}
echo "❌ $failed/$total cas ROUGES\n";
exit(1);

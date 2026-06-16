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

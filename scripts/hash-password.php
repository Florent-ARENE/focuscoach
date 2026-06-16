<?php
/**
 * ============================================
 * HASH PASSWORD — génération bcrypt pour ADMIN_PASSWORD_HASH
 * ============================================
 * Génère le hash bcrypt (PASSWORD_BCRYPT, cost = PASSWORD_DEFAULT) à
 * coller dans `config/config.local.php` pour la constante
 * `ADMIN_PASSWORD_HASH` consommée par le login admin (Lot 2 v2.4.4).
 *
 * Deux modes :
 *
 *  ▸ CLI (recommandé si SSH dispo — saisie masquée, rien dans l'historique) :
 *      php scripts/hash-password.php
 *      php scripts/hash-password.php 'monMotDePasse'   # rapide, déconseillé
 *
 *  ▸ WEB (si pas de SSH — VM de test, OVH mutualisé) :
 *      https://.../scripts/hash-password.php
 *      → formulaire HTML, hash retourné en clair.
 *      ⚠  À SUPPRIMER après usage : un script qui produit des hashs bcrypt
 *      ne doit pas vivre en prod (vector de bruteforce / spam logs).
 *
 * IMPORTANT :
 *   - Ne jamais commiter le hash : il vit dans `config/config.local.php`
 *     (déjà protégé par .htaccess et .gitignore).
 *   - Le hash retourné par password_hash() change à chaque appel (sel
 *     aléatoire interne) — normal, password_verify() le gère.
 *   - Cost par défaut PHP (10 actuellement) suffisant ; OVH mutualisé
 *     est lent, ne pas surenchérir sous peine d'allonger chaque login.
 *   - Le mot de passe n'est NI loggé NI stocké : il ne sort jamais de
 *     l'invocation courante.
 */

// ============================================================
// Mode CLI
// ============================================================
if (php_sapi_name() === 'cli') {
    runCli($argv);
    exit(0);
}

// ============================================================
// Mode WEB
// ============================================================
runWeb();
exit(0);


// ─── CLI ─────────────────────────────────────────────────────

function runCli(array $argv): void
{
    $password = $argv[1] ?? null;

    if ($password === null) {
        $password = readPasswordSilently("Mot de passe admin : ");
        $confirm  = readPasswordSilently("Confirmer            : ");
        if ($password !== $confirm) {
            fwrite(STDERR, "Les deux saisies ne correspondent pas. Abandon.\n");
            exit(1);
        }
    }

    if ($password === '') {
        fwrite(STDERR, "Mot de passe vide. Abandon.\n");
        exit(1);
    }

    if (strlen($password) < 8) {
        fwrite(STDERR, "Mot de passe < 8 caractères — c'est faible. Continuer ? [y/N] ");
        $answer = strtolower(trim((string) fgets(STDIN)));
        if ($answer !== 'y' && $answer !== 'o') {
            fwrite(STDERR, "Abandon.\n");
            exit(1);
        }
    }

    $hash = generateHash($password);
    if ($hash === null) {
        fwrite(STDERR, "Échec génération hash. Abandon.\n");
        exit(1);
    }

    fwrite(STDERR, "\nHash bcrypt généré (verify OK) — à coller dans config/config.local.php :\n\n");
    fwrite(STDOUT, $hash . "\n");
    fwrite(STDERR, "\nExemple de ligne :\n");
    fwrite(STDERR, "    define('ADMIN_PASSWORD_HASH', '" . $hash . "');\n\n");
    fwrite(STDERR, "Rappel : ne jamais commiter config.local.php.\n");
}

/**
 * Lit un mot de passe sans le réafficher à l'écran.
 * Fallback fgets() si stty indisponible.
 */
function readPasswordSilently(string $prompt): string
{
    fwrite(STDERR, $prompt);

    $hasStty = function_exists('shell_exec')
        && @shell_exec('command -v stty 2>/dev/null') !== null;

    if ($hasStty) {
        $previous = shell_exec('stty -g 2>/dev/null');
        shell_exec('stty -echo 2>/dev/null');
        $password = trim((string) fgets(STDIN));
        if ($previous !== null) {
            shell_exec('stty ' . escapeshellarg(trim($previous)) . ' 2>/dev/null');
        } else {
            shell_exec('stty echo 2>/dev/null');
        }
        fwrite(STDERR, "\n");
        return $password;
    }

    fwrite(STDERR, "(saisie visible — stty indisponible)\n");
    return trim((string) fgets(STDIN));
}


// ─── WEB ─────────────────────────────────────────────────────

function runWeb(): void
{
    $hash    = null;
    $error   = null;
    $weakOk  = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['confirm']  ?? '');
        $weakOk   = !empty($_POST['weak_ok']);

        if ($password === '') {
            $error = 'Mot de passe vide.';
        } elseif ($password !== $confirm) {
            $error = 'Les deux saisies ne correspondent pas.';
        } elseif (strlen($password) < 8 && !$weakOk) {
            $error = 'Mot de passe < 8 caractères. Coche « j\'accepte » pour passer outre.';
        } else {
            $hash = generateHash($password);
            if ($hash === null) {
                $error = 'Échec génération hash (password_hash a retourné false).';
            }
        }

        // Effacer le mdp côté serveur dès qu'on n'en a plus besoin.
        $password = '';
        $confirm  = '';
        unset($_POST['password'], $_POST['confirm']);
    }

    renderWebPage($hash, $error);
}

function renderWebPage(?string $hash, ?string $error): void
{
    $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    // Headers anti-cache + anti-indexation (le hash ne doit pas traîner).
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Robots-Tag: noindex, nofollow');
    header('Content-Type: text/html; charset=utf-8');
    ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Générer hash bcrypt — ADMIN_PASSWORD_HASH</title>
<style>
:root{
  --navy:#2E2A5E;--orange:#F0A500;--sand:#F7F5F0;
  --red-strong:#991b1b;--red-soft:#fee2e2;--red-border:#fecaca;
  --success-bg:#d1fae5;--success-text:#065f46;
  --text:#1f2937;--muted:#6b7280;--border:#e5e7eb;--white:#fff;
}
*{box-sizing:border-box}
body{font-family:system-ui,-apple-system,sans-serif;background:var(--sand);color:var(--text);margin:0;padding:1.5rem;line-height:1.5}
.wrap{max-width:640px;margin:0 auto}
h1{color:var(--navy);font-size:1.5rem;margin:0 0 .25rem}
.lead{color:var(--muted);margin:0 0 1.5rem;font-size:.95rem}
.alert{background:var(--red-soft);border:1px solid var(--red-border);color:var(--red-strong);padding:1rem;border-radius:8px;margin-bottom:1.5rem;font-size:.9rem}
.alert strong{display:block;margin-bottom:.25rem}
.card{background:var(--white);border:1px solid var(--border);border-radius:8px;padding:1.5rem;margin-bottom:1rem}
label{display:block;font-weight:600;margin:0 0 .35rem;font-size:.9rem}
input[type=password]{width:100%;padding:.65rem .75rem;border:1px solid var(--border);border-radius:6px;font-size:1rem;font-family:inherit}
input[type=password]:focus{outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(46,42,94,.15)}
.field{margin-bottom:1rem}
.checkbox{display:flex;align-items:flex-start;gap:.5rem;font-size:.85rem;color:var(--muted);margin-bottom:1rem}
.checkbox input{margin-top:.2rem}
button{background:var(--navy);color:var(--white);border:none;padding:.75rem 1.25rem;border-radius:6px;font-size:1rem;font-weight:600;cursor:pointer;font-family:inherit}
button:hover{background:var(--orange)}
.error{background:var(--red-soft);border:1px solid var(--red-border);color:var(--red-strong);padding:.75rem 1rem;border-radius:6px;margin-bottom:1rem;font-size:.9rem}
.result{background:var(--success-bg);border:1px solid var(--success-text);color:var(--success-text);padding:1rem;border-radius:8px;margin-bottom:1rem}
.result strong{display:block;margin-bottom:.5rem}
.hash{display:block;background:var(--white);color:var(--text);padding:.75rem;border-radius:6px;font-family:ui-monospace,Menlo,Monaco,monospace;font-size:.85rem;word-break:break-all;border:1px solid var(--border)}
.copy{margin-top:.5rem;background:var(--success-text);font-size:.85rem;padding:.4rem .8rem}
.snippet{background:#1f2937;color:#f3f4f6;padding:.75rem;border-radius:6px;font-family:ui-monospace,Menlo,Monaco,monospace;font-size:.8rem;word-break:break-all;margin-top:.75rem}
.foot{font-size:.8rem;color:var(--muted);margin-top:1.5rem;text-align:center}
</style>
</head>
<body>
<div class="wrap">
  <h1>Générer hash bcrypt</h1>
  <p class="lead">Pour la constante <code>ADMIN_PASSWORD_HASH</code> dans <code>config/config.local.php</code>.</p>

  <div class="alert">
    <strong>⚠ À SUPPRIMER après usage</strong>
    Un endpoint public qui produit des hashs bcrypt ne doit pas survivre en prod
    (vecteur de bruteforce, pollution des logs). Une fois ton hash collé, supprime
    <code>scripts/hash-password.php</code> du serveur.
  </div>

  <?php if ($error !== null): ?>
    <div class="error"><?= $esc($error) ?></div>
  <?php endif; ?>

  <?php if ($hash !== null): ?>
    <div class="result">
      <strong>Hash généré (password_verify OK)</strong>
      <code class="hash" id="hash"><?= $esc($hash) ?></code>
      <button type="button" class="copy" onclick="navigator.clipboard.writeText(document.getElementById('hash').textContent);this.textContent='Copié ✓'">Copier le hash</button>
      <div class="snippet">define('ADMIN_PASSWORD_HASH', '<?= $esc($hash) ?>');</div>
    </div>
  <?php endif; ?>

  <form method="post" class="card" autocomplete="off">
    <div class="field">
      <label for="password">Mot de passe</label>
      <input type="password" id="password" name="password" required autofocus autocomplete="new-password">
    </div>
    <div class="field">
      <label for="confirm">Confirmer</label>
      <input type="password" id="confirm" name="confirm" required autocomplete="new-password">
    </div>
    <label class="checkbox">
      <input type="checkbox" name="weak_ok" value="1">
      <span>Je passe outre l'avertissement « moins de 8 caractères » (déconseillé).</span>
    </label>
    <button type="submit">Générer le hash</button>
  </form>

  <p class="foot">Le mot de passe n'est ni loggé ni stocké côté serveur.</p>
</div>
</body>
</html><?php
}


// ─── Cœur partagé ────────────────────────────────────────────

function generateHash(string $password): ?string
{
    $hash = password_hash($password, PASSWORD_BCRYPT);
    if ($hash === false || !is_string($hash)) {
        return null;
    }
    if (!password_verify($password, $hash)) {
        return null;
    }
    return $hash;
}

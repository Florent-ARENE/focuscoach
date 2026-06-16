<?php
/**
 * ============================================
 * HASH PASSWORD — génération bcrypt pour ADMIN_PASSWORD_HASH
 * ============================================
 * Génère le hash bcrypt (PASSWORD_BCRYPT, cost = PASSWORD_DEFAULT) à
 * coller dans `config/config.local.php` pour la constante
 * `ADMIN_PASSWORD_HASH` consommée par le login admin (Lot 2 v2.4.4).
 *
 * Usage CLI (recommandé — saisie masquée, rien dans l'historique shell) :
 *   php scripts/hash-password.php
 *
 * Usage CLI (mot de passe en argument — DÉCONSEILLÉ, reste dans l'historique) :
 *   php scripts/hash-password.php 'monMotDePasse'
 *
 * Sortie : le hash seul sur stdout (collable tel quel), instructions sur stderr.
 *
 * IMPORTANT :
 *   - Ne jamais commiter le hash : il vit dans `config/config.local.php`
 *     (déjà protégé par .htaccess et .gitignore).
 *   - Le hash retourné par password_hash() change à chaque appel (sel
 *     aléatoire interne) — c'est normal, password_verify() le gère.
 *   - Cost par défaut PHP (10 actuellement) suffisant ; OVH mutualisé
 *     est lent, ne pas surenchérir sous peine d'allonger chaque login.
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Ce script doit être exécuté en CLI.\n");
    exit(2);
}

/**
 * Lit un mot de passe sans le réafficher à l'écran.
 * Fallback fgets() si stty indisponible (Windows pur, etc.).
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
    fwrite(STDERR, "⚠  Mot de passe < 8 caractères — c'est faible. Continuer ? [y/N] ");
    $answer = strtolower(trim((string) fgets(STDIN)));
    if ($answer !== 'y' && $answer !== 'o') {
        fwrite(STDERR, "Abandon.\n");
        exit(1);
    }
}

$hash = password_hash($password, PASSWORD_BCRYPT);
if ($hash === false) {
    fwrite(STDERR, "Échec password_hash(). Vérifier ext-bcrypt.\n");
    exit(1);
}

if (!password_verify($password, $hash)) {
    fwrite(STDERR, "Vérification interne KO — hash inutilisable. Abandon.\n");
    exit(1);
}

fwrite(STDERR, "\nHash bcrypt généré (verify OK) — à coller dans config/config.local.php :\n\n");
fwrite(STDOUT, $hash . "\n");
fwrite(STDERR, "\nExemple de ligne :\n");
fwrite(STDERR, "    define('ADMIN_PASSWORD_HASH', '" . $hash . "');\n\n");
fwrite(STDERR, "Rappel : ne jamais commiter config.local.php.\n");

exit(0);

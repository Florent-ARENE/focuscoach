<?php
/**
 * ============================================
 * BUMP VERSION — outil de release (AD-1)
 * ============================================
 * Bumpe la version dans la source unique (VERSION) ET propage
 * automatiquement à TOUS les stamps dérivés en un seul geste, pour
 * que le pre-commit (AD-8) reste vert.
 *
 * Usage CLI :
 *   php scripts/bump-version.php 2.4.3
 *
 * Cibles mises à jour :
 *   - VERSION                 (source)
 *   - README.md               (Version: 2.4.X)
 *   - CLAUDE.md               (État courant — v2.4.X)
 *   - README_TECHNIQUE.md     (Version: 2.4.X)
 *   - sw.js                   (CACHE_VERSION = 'v2.4.X')
 *
 * NE TOUCHE PAS aux changelogs : les entrées datées de version sont
 * historiques, pas des stamps.
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Ce script doit être exécuté en CLI.\n");
    exit(2);
}

$new = $argv[1] ?? '';
if (!preg_match('/^\d+\.\d+\.\d+$/', $new)) {
    fwrite(STDERR, "Usage : php scripts/bump-version.php <X.Y.Z>\n");
    fwrite(STDERR, "Ex.   : php scripts/bump-version.php 2.4.3\n");
    exit(2);
}

$root = dirname(__DIR__);

// 1) Source
$versionPath = "$root/VERSION";
$old = is_readable($versionPath) ? trim((string) file_get_contents($versionPath)) : '';
file_put_contents($versionPath, $new . "\n");
echo "VERSION : $old → $new\n";

/**
 * Remplace dans un fichier et retourne le nombre de substitutions.
 */
function bump(string $path, string $pattern, string $replacement): int
{
    if (!is_readable($path)) {
        echo "  · skip (absent)  : $path\n";
        return 0;
    }
    $content  = file_get_contents($path);
    $updated  = preg_replace($pattern, $replacement, $content, -1, $count);
    if ($count > 0 && $updated !== $content) {
        file_put_contents($path, $updated);
        echo "  · $count remplacement(s) : $path\n";
    } else {
        echo "  · aucune occurrence    : $path\n";
    }
    return (int) $count;
}

// 2) Stamps dérivés
echo "Stamps :\n";
bump("$root/README.md",
    '/(\*\*Version:\*\*\s+)\d+\.\d+\.\d+/',
    '${1}' . $new);

bump("$root/CLAUDE.md",
    '/(État courant\s+—\s+v)\d+\.\d+\.\d+/u',
    '${1}' . $new);

bump("$root/README_TECHNIQUE.md",
    '/(\*\*Version:\*\*\s+)\d+\.\d+\.\d+/',
    '${1}' . $new);

bump("$root/sw.js",
    "/(CACHE_VERSION\s*=\s*['\"]v)\\d+\\.\\d+\\.\\d+(['\"])/",
    '${1}' . $new . '${2}');

echo "\nBump terminé. Pense à :\n";
echo "  1. Ajouter l'entrée CHANGELOG datée pour v$new.\n";
echo "  2. Vérifier que le pre-commit passe VERT.\n";
echo "  3. git add -A && git commit.\n";

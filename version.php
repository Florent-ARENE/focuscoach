<?php
/**
 * ============================================
 * SOURCE DE VÉRITÉ — VERSION (AD-1)
 * ============================================
 * Lit le fichier `VERSION` racine et expose la version courante.
 * Toute autre représentation de version dans le projet (`sw.js`
 * CACHE_VERSION, `README.md`, `CLAUDE.md`, `manifest.json`, etc.) DOIT
 * dériver de cette source — jamais l'inverse. L'alignement est vérifié
 * par `.git/hooks/pre-commit` (cf. CADRAGE_UNIVERSEL AD-1 + AD-8).
 *
 * Usage :
 *   require_once __DIR__ . '/version.php';
 *   echo appVersion();        // "2.4.2"
 *   echo cacheVersion();      // "v2.4.2"  (préfixé pour les caches)
 */

if (!function_exists('appVersion')) {
    function appVersion(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $path = __DIR__ . '/VERSION';
        if (!is_readable($path)) {
            throw new RuntimeException('Fichier VERSION introuvable — source unique manquante (AD-1).');
        }
        $cached = trim((string) file_get_contents($path));
        if ($cached === '') {
            throw new RuntimeException('Fichier VERSION vide.');
        }
        return $cached;
    }
}

if (!function_exists('cacheVersion')) {
    function cacheVersion(): string
    {
        return 'v' . appVersion();
    }
}

<?php
/**
 * ============================================
 * BOOKING v3 — SHELL MUTUALISÉ (header + footer)
 * ============================================
 * Header et footer du tunnel étaient recopiés à l'identique dans les 6
 * pages (index/date/slot/confirm/success/manage) → duplication (AD-3).
 * Factorisés ici : une seule source.
 *
 * - Footer : strictement identique partout → aucun paramètre.
 * - Header : logo + structure identiques ; seul le lien « retour » varie
 *   par étape (href + libellé) → passés en paramètres.
 *
 * Le wordmark vient de brandWordmark() (helper centralisé) ; sa typo
 * (DM Sans MAJUSCULES bicolore) est dans main.css (.brand-half).
 */

use App\Helpers;
use App\Icons;

/**
 * Header du tunnel. $backHref : déjà sûr (construit par la page) ;
 * $backLabel : texte simple (échappé ici).
 */
function booking_header(string $backHref, string $backLabel): string
{
    return '<header class="booking-header">'
        . '<div class="booking-header-content">'
        . '<a href="' . Helpers::escape(BASE_URL) . '" class="booking-logo">' . brandWordmark() . '</a>'
        . '<a href="' . $backHref . '" class="back-link">'
        . Icons::svg('arrow-left', 20, 'icon-inline') . Helpers::escape($backLabel)
        . '</a>'
        . '</div></header>';
}

/**
 * Footer du tunnel = LE footer du site, variante allégée (composant unique
 * siteFooter() dans init.php). Plus de footer booking divergent : même navy
 * que l'accueil, marque + liens légaux + ©.
 */
function booking_footer(): string
{
    return siteFooter(false);
}

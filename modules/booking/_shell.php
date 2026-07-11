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

/**
 * Barre d'étapes (1 Prestation · 2 Date · 3 Créneau · 4 Vos informations).
 * Mutualisée (était recopiée dans date/slot/confirm — AD-3). Les étapes
 * DÉJÀ franchies deviennent des liens cliquables pour revenir en arrière.
 *
 * @param int                $current Étape courante (1-4).
 * @param array<int,string>  $hrefs   href (déjà sûrs) des étapes franchies,
 *                                     indexés par numéro d'étape.
 */
function booking_steps(int $current, array $hrefs = []): string
{
    $labels = [1 => 'Prestation', 2 => 'Date', 3 => 'Créneau', 4 => 'Vos informations'];
    $html   = '<ol class="bv3-steps" aria-label="Étapes de la réservation">';
    foreach ($labels as $n => $label) {
        $body = '<span class="bv3-step-num">' . $n . '</span> ' . $label;
        if ($n < $current) {
            $inner = isset($hrefs[$n])
                ? '<a class="bv3-step-link" href="' . $hrefs[$n] . '">' . $body . '</a>'
                : $body;
            $html .= '<li class="bv3-step is-done">' . $inner . '</li>';
        } elseif ($n === $current) {
            $html .= '<li class="bv3-step is-current" aria-current="step">' . $body . '</li>';
        } else {
            $html .= '<li class="bv3-step">' . $body . '</li>';
        }
    }
    return $html . '</ol>';
}

/**
 * Contexte FORFAIT du tunnel (§7). Valide le token de pack pour ce service :
 * retourne l'achat s'il est UTILISABLE (actif, crédits restants, non expiré) ET
 * qu'il porte bien ce `service_id` ; sinon null (le tunnel retombe alors sur le
 * paiement à l'unité). Mutualisé — appelé par date.php / slot.php / pack-book.php.
 */
function pack_context(string $token, int $serviceId): ?array
{
    if ($token === '' || strlen($token) !== MANAGE_TOKEN_LENGTH) {
        return null;
    }
    $purchase = (new \App\Package())->getByToken($token);
    if (!$purchase || empty($purchase['is_usable']) || (int) $purchase['service_id'] !== $serviceId) {
        return null;
    }
    return $purchase;
}

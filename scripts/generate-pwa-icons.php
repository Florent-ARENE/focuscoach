<?php
/**
 * ============================================
 * GÉNÉRATEUR D'ICÔNES PWA
 * ============================================
 * Génère les icônes maskable 192×192 et 512×512 dans assets/img/
 * à partir d'une icône Lucide redessinée en primitives GD.
 *
 * Icône choisie : Lucide "focus" — un cercle central + 4 coins de cible.
 * Sémantique en phase avec la marque « Focus Coach ».
 *
 * Format : maskable (padding interne ≥ 20% — safe zone PWA).
 *   - fond plein navy-deep (--navy-900 = #2E2A5E) pour le contraste foncé
 *     attendu par les masques iOS/Android.
 *   - traits orange (--gold = #F0A500).
 *
 * Usage CLI : php scripts/generate-pwa-icons.php
 * Sortie : assets/img/icon-192.png et assets/img/icon-512.png
 */

if (php_sapi_name() !== 'cli') {
    die("Ce script doit être exécuté en CLI.\n");
}

if (!extension_loaded('gd')) {
    die("Extension GD manquante.\n");
}

$outDir = __DIR__ . '/../assets/img';
if (!is_dir($outDir)) {
    die("Dossier introuvable : $outDir\n");
}

/**
 * Dessine l'icône Lucide "focus" centrée sur un canevas $size×$size.
 * Maskable : tout le contenu visible reste dans un cercle de rayon $size * 0.4.
 *
 * @param int $size  Côté du PNG carré.
 * @param string $path Chemin de sortie.
 */
function renderFocusIcon(int $size, string $path): void
{
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);

    // Palette projet
    $navyDeep = imagecolorallocate($img, 0x2E, 0x2A, 0x5E); // #2E2A5E
    $gold     = imagecolorallocate($img, 0xF0, 0xA5, 0x00); // #F0A500

    // Fond plein navy (les masques iOS/Android peuvent rogner les coins,
    // donc on remplit tout le carré — pas juste un disque).
    imagefilledrectangle($img, 0, 0, $size, $size, $navyDeep);

    // Géométrie de l'icône (proportions du SVG Lucide focus, 24×24).
    // Le tout doit tenir dans la safe zone (rayon = $size * 0.4 autour du centre).
    $cx = $size / 2;
    $cy = $size / 2;
    $safeRadius = $size * 0.40;

    // Rayon du cercle central : 4/24 du viewBox Lucide -> scale sur safeRadius
    $centerR = $safeRadius * (4 / 12);
    // Trait épais (3% du size, mais au moins 4px)
    $stroke = max(4, (int) round($size * 0.03));
    imagesetthickness($img, $stroke);

    // Cercle central — dessiné comme anneau (filledellipse externe - interne)
    // car imageellipse() ignore imagesetthickness().
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagealphablending($img, false);
    imagefilledellipse($img, (int) round($cx), (int) round($cy),
                       (int) round(($centerR + $stroke / 2) * 2),
                       (int) round(($centerR + $stroke / 2) * 2), $gold);
    imagealphablending($img, true);
    imagefilledellipse($img, (int) round($cx), (int) round($cy),
                       (int) round(($centerR - $stroke / 2) * 2),
                       (int) round(($centerR - $stroke / 2) * 2), $navyDeep);

    // Quatre angles de cible (coins arrondis du cadre Lucide).
    // Position : à 9/24 du centre, longueur 3/24 du viewBox.
    $cornerOffset = $safeRadius * (9 / 12);
    $cornerLen    = $safeRadius * (3 / 12);

    // Helper : segment épais
    $drawSeg = function ($x1, $y1, $x2, $y2) use ($img, $gold) {
        imageline($img, (int) round($x1), (int) round($y1),
                       (int) round($x2), (int) round($y2), $gold);
    };

    // Coin haut-gauche : L horizontale puis verticale
    $drawSeg($cx - $cornerOffset, $cy - $cornerOffset,
             $cx - $cornerOffset + $cornerLen, $cy - $cornerOffset);
    $drawSeg($cx - $cornerOffset, $cy - $cornerOffset,
             $cx - $cornerOffset, $cy - $cornerOffset + $cornerLen);

    // Coin haut-droit
    $drawSeg($cx + $cornerOffset, $cy - $cornerOffset,
             $cx + $cornerOffset - $cornerLen, $cy - $cornerOffset);
    $drawSeg($cx + $cornerOffset, $cy - $cornerOffset,
             $cx + $cornerOffset, $cy - $cornerOffset + $cornerLen);

    // Coin bas-droit
    $drawSeg($cx + $cornerOffset, $cy + $cornerOffset,
             $cx + $cornerOffset - $cornerLen, $cy + $cornerOffset);
    $drawSeg($cx + $cornerOffset, $cy + $cornerOffset,
             $cx + $cornerOffset, $cy + $cornerOffset - $cornerLen);

    // Coin bas-gauche
    $drawSeg($cx - $cornerOffset, $cy + $cornerOffset,
             $cx - $cornerOffset + $cornerLen, $cy + $cornerOffset);
    $drawSeg($cx - $cornerOffset, $cy + $cornerOffset,
             $cx - $cornerOffset, $cy + $cornerOffset - $cornerLen);

    if (!imagepng($img, $path)) {
        die("Échec écriture : $path\n");
    }
    imagedestroy($img);
    echo "  ✓ Généré : $path (" . filesize($path) . " octets)\n";
}

echo "Génération des icônes PWA Focus Coach (Lucide focus)...\n";
renderFocusIcon(192, "$outDir/icon-192.png");
renderFocusIcon(512, "$outDir/icon-512.png");
echo "Terminé.\n";

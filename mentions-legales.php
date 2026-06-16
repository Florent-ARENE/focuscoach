<?php
/**
 * ============================================
 * PAGE : MENTIONS LÉGALES
 * ============================================
 * Obligatoire en vertu de la LCEN (loi n° 2004-575, art. 6)
 * 
 * Dépend de : includes/init.php
 */
require_once __DIR__ . '/includes/init.php';

use App\Helpers;

$cfg = siteConfig();
$pageTitle = 'Mentions légales';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Helpers::escape($pageTitle) ?> | <?= Helpers::escape(siteConfig()['site_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/home.css">
    <?= pwaHead() ?>
    <!-- Réutilise les styles de confidentialite.php -->
</head>
<body class="legal-page">
    <!-- Header -->
    <header class="legal-header">
        <div class="legal-header-content">
            <a href="./" class="legal-logo"><?= brandWordmark() ?></a>
            <a href="./" class="legal-back">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Retour au site
            </a>
        </div>
    </header>
    
    <!-- Contenu principal -->
    <main class="legal-main">
        <h1><?= Helpers::escape($pageTitle) ?></h1>
        
        
        <h2>1. Éditeur du site</h2>
        <p>Le présent site est édité par :</p>
        <ul>
            <li><strong>Nom :</strong> <?= cfgField($cfg['full_name']) ?></li>
            <li><strong>Statut :</strong> <?= cfgField($cfg['legal_status'], 'Statut juridique à compléter') ?></li>
            <li><strong>SIRET :</strong> <?= cfgField($cfg['admin_siret']) ?></li>
            <li><strong>Adresse :</strong> <?= cfgField($cfg['admin_address']) ?></li>
            <li><strong>Téléphone :</strong> <?= cfgField($cfg['admin_phone']) ?></li>
            <li><strong>Email :</strong> <?= Helpers::escape($cfg['admin_email']) ?></li>
            <li><strong>Directeur de la publication :</strong> <?= cfgField($cfg['full_name']) ?></li>
        </ul>
        
        
        <h2>2. Hébergeur</h2>
        <ul>
            <li><strong>Raison sociale :</strong> OVH SAS</li>
            <li><strong>Adresse :</strong> 2 rue Kellermann, 59100 Roubaix, France</li>
            <li><strong>Téléphone :</strong> 1007 (depuis la France)</li>
            <li><strong>Site web :</strong> <a href="https://www.ovhcloud.com" target="_blank" rel="noopener">www.ovhcloud.com</a></li>
        </ul>
        
        
        <h2>3. Propriété intellectuelle</h2>
        <p>L'ensemble des contenus présents sur ce site (textes, images, graphismes, logo, icônes, mise en page) est la propriété exclusive de <?= cfgField($cfg['full_name']) ?> ou fait l'objet d'une autorisation d'utilisation, sauf mention contraire.</p>
        <p>Toute reproduction, représentation, modification, publication ou adaptation de tout ou partie de ces éléments, quel que soit le moyen ou le procédé utilisé, est interdite sans l'autorisation écrite préalable de l'éditeur.</p>
        
        
        <h2>4. Protection des données personnelles</h2>
        <p>Les informations relatives à la collecte et au traitement de vos données personnelles sont détaillées dans notre <a href="confidentialite.php">Politique de confidentialité</a>.</p>
        <p>Conformément au Règlement Général sur la Protection des Données (RGPD) et à la loi Informatique et Libertés du 6 janvier 1978 modifiée, vous disposez de droits sur vos données personnelles (accès, rectification, effacement, portabilité, opposition). Pour les exercer, contactez-nous à : <a href="mailto:<?= Helpers::escape($cfg['admin_email']) ?>"><?= Helpers::escape($cfg['admin_email']) ?></a>.</p>
        
        
        <h2>5. Cookies</h2>
        <p>Ce site n'utilise aucun cookie publicitaire, analytique ou de tracking. Seul un cookie technique de session peut être utilisé pour le fonctionnement de l'espace d'administration. Pour plus d'informations, consultez notre <a href="confidentialite.php">Politique de confidentialité</a>.</p>
        
        
        <h2>6. Limitation de responsabilité</h2>
        <p>Les informations contenues sur ce site sont présentées à titre indicatif et n'ont pas de valeur contractuelle. L'éditeur ne peut être tenu responsable des erreurs ou omissions, ni des résultats qui pourraient être obtenus par l'usage de ces informations.</p>
        <p>L'éditeur ne peut également être tenu responsable des dommages directs ou indirects résultant de l'accès ou de l'utilisation du site, y compris l'inaccessibilité, les pertes de données, les détériorations, les destructions ou les virus pouvant affecter l'équipement informatique de l'utilisateur.</p>
        
        
        <h2>7. Liens hypertextes</h2>
        <p>Ce site peut contenir des liens vers d'autres sites internet. L'éditeur n'exerce aucun contrôle sur ces sites et décline toute responsabilité quant à leur contenu.</p>
        
        
        <h2>8. Droit applicable</h2>
        <p>Les présentes mentions légales sont régies par le droit français. En cas de litige, les tribunaux français seront seuls compétents.</p>
    </main>
    
    <!-- Footer -->
    <footer class="legal-footer">
        <p>&copy; <?= date('Y') ?> <?= Helpers::escape($cfg['site_name']) ?> &bull; <a href="confidentialite.php">Politique de confidentialité</a></p>
    </footer>
    <?= pwaRegister() ?>
</body>
</html>

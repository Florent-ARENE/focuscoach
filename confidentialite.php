<?php
/**
 * ============================================
 * PAGE : POLITIQUE DE CONFIDENTIALITÉ
 * ============================================
 * Conforme RGPD art. 12-13 (12 catégories d'information)
 * Approche 2 niveaux CNIL : page dédiée complète (niveau 2)
 * 
 * Dépend de : includes/init.php
 */
require_once __DIR__ . '/includes/init.php';

use App\Helpers;

$cfg = siteConfig();
$pageTitle = 'Politique de confidentialité';
$lastUpdate = '13 février 2026';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= siteConfig()['site_name'] ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/home.css">
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
        <h1><?= $pageTitle ?></h1>
        <p class="legal-update">Dernière mise à jour : <?= $lastUpdate ?></p>
        
        
        <!-- 1. RESPONSABLE DU TRAITEMENT -->
        <h2>1. Responsable du traitement</h2>
        <p>
            Le responsable du traitement de vos données personnelles est :
        </p>
        <ul>
            <li><strong>Nom :</strong> <?= cfgField($cfg['full_name']) ?></li>
            <li><strong>Activité :</strong> <?= cfgField($cfg['admin_activity'], 'Activité à compléter') ?></li>
            <li><strong>Adresse :</strong> <?= cfgField($cfg['admin_address']) ?></li>
            <li><strong>Email :</strong> <?= Helpers::escape($cfg['admin_email']) ?></li>
            <li><strong>SIRET :</strong> <?= cfgField($cfg['admin_siret']) ?></li>
        </ul>
        
        
        <!-- 2. DONNÉES COLLECTÉES -->
        <h2>2. Données collectées</h2>
        
        <h3>Données fournies directement par vous</h3>
        <p>Lors de votre demande de rendez-vous, nous collectons :</p>
        <table class="legal-table">
            <thead>
                <tr>
                    <th>Donnée</th>
                    <th>Obligatoire</th>
                    <th>Finalité</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Nom complet</td>
                    <td>Oui</td>
                    <td>Identification, communication</td>
                </tr>
                <tr>
                    <td>Adresse email</td>
                    <td>Oui</td>
                    <td>Confirmation, notifications, gestion du RDV</td>
                </tr>
                <tr>
                    <td>Téléphone</td>
                    <td>Non</td>
                    <td>Contact complémentaire</td>
                </tr>
                <tr>
                    <td>Organisation</td>
                    <td>Non</td>
                    <td>Contextualisation de la demande</td>
                </tr>
                <tr>
                    <td>Type de service</td>
                    <td>Non</td>
                    <td>Orientation de la demande</td>
                </tr>
                <tr>
                    <td>Objet / Message</td>
                    <td>Non</td>
                    <td>Compréhension de vos attentes</td>
                </tr>
            </tbody>
        </table>
        
        <h3>Données collectées automatiquement</h3>
        <p>Pour assurer la sécurité de notre site, nous collectons automatiquement :</p>
        <ul>
            <li><strong>Adresse IP</strong> — identifiant technique de votre connexion internet</li>
            <li><strong>User agent</strong> — informations sur votre navigateur et système d'exploitation</li>
        </ul>
        <p>Ces données techniques sont traitées séparément de vos données de contact et conservées pour une durée plus courte (voir section 5).</p>
        
        
        <!-- 3. FINALITÉS ET BASES LÉGALES -->
        <h2>3. Finalités et bases légales</h2>
        
        <p>Nous ne collectons vos données que pour des finalités déterminées, chacune reposant sur une base légale spécifique :</p>
        
        <table class="legal-table">
            <thead>
                <tr>
                    <th>Finalité</th>
                    <th>Base légale</th>
                    <th>Fondement RGPD</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Traitement de votre demande de rendez-vous</td>
                    <td>Mesures précontractuelles</td>
                    <td>Art. 6.1.b</td>
                </tr>
                <tr>
                    <td>Communication liée au RDV (confirmation, rappels, annulation)</td>
                    <td>Exécution du contrat</td>
                    <td>Art. 6.1.b</td>
                </tr>
                <tr>
                    <td>Sécurité du site (prévention des abus, détection de bots)</td>
                    <td>Intérêt légitime</td>
                    <td>Art. 6.1.f</td>
                </tr>
            </tbody>
        </table>
        
        <div class="legal-highlight">
            <p><strong>Bon à savoir :</strong> votre demande de rendez-vous constitue une démarche précontractuelle à votre initiative. Le traitement de vos données ne repose donc pas sur votre consentement, mais sur l'exécution de mesures précontractuelles (article 6.1.b du RGPD).</p>
        </div>
        
        
        <!-- 4. DESTINATAIRES -->
        <h2>4. Destinataires de vos données</h2>
        <p>Vos données personnelles sont accessibles uniquement par :</p>
        <ul>
            <li><strong><?= cfgField($cfg['full_name']) ?></strong> — responsable du traitement, pour la gestion de votre demande</li>
            <li><strong>OVH SAS</strong> — hébergeur du site (2 rue Kellermann, 59100 Roubaix, France)</li>
        </ul>
        <p><strong>Aucune donnée n'est vendue, louée ou cédée à des tiers.</strong></p>
        
        
        <!-- 5. DURÉE DE CONSERVATION -->
        <h2>5. Durée de conservation</h2>
        
        <table class="legal-table">
            <thead>
                <tr>
                    <th>Type de données</th>
                    <th>Durée de conservation</th>
                    <th>Justification</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Données de rendez-vous (nom, email, téléphone, organisation, message)</td>
                    <td>365 jours après la date du rendez-vous</td>
                    <td>Suivi d'activité et bilan annuel</td>
                </tr>
                <tr>
                    <td>Adresse IP (complète)</td>
                    <td>3 mois, puis troncature</td>
                    <td>Sécurité immédiate</td>
                </tr>
                <tr>
                    <td>Adresse IP (tronquée) et user agent</td>
                    <td>6 mois, puis suppression totale</td>
                    <td>Reco. CNIL journalisation</td>
                </tr>
                <tr>
                    <td>Données de facturation (si prestation réalisée)</td>
                    <td>10 ans</td>
                    <td>Obligation légale — Code de commerce (art. L123-22)</td>
                </tr>
            </tbody>
        </table>
        
        <p>À l'expiration des durées ci-dessus, les données sont supprimées définitivement ou anonymisées de manière irréversible (statistiques agrégées uniquement).</p>
        
        
        <!-- 6. TRANSFERTS HORS UE -->
        <h2>6. Transferts hors Union européenne</h2>
        <p>Aucune de vos données n'est transférée en dehors de l'Union européenne. Notre hébergeur OVH est une société française dont les serveurs sont situés en France.</p>
        
        
        <!-- 7. VOS DROITS -->
        <h2>7. Vos droits</h2>
        <p>Conformément au RGPD (articles 15 à 21), vous disposez des droits suivants sur vos données personnelles :</p>
        
        <table class="legal-table">
            <thead>
                <tr>
                    <th>Droit</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Accès</strong> (art. 15)</td>
                    <td>Obtenir une copie de toutes les données que nous détenons sur vous</td>
                </tr>
                <tr>
                    <td><strong>Rectification</strong> (art. 16)</td>
                    <td>Faire corriger des données inexactes ou incomplètes</td>
                </tr>
                <tr>
                    <td><strong>Effacement</strong> (art. 17)</td>
                    <td>Demander la suppression de vos données (sauf obligations légales)</td>
                </tr>
                <tr>
                    <td><strong>Limitation</strong> (art. 18)</td>
                    <td>Restreindre temporairement le traitement de vos données</td>
                </tr>
                <tr>
                    <td><strong>Portabilité</strong> (art. 20)</td>
                    <td>Recevoir vos données dans un format structuré et lisible par machine (JSON ou CSV)</td>
                </tr>
                <tr>
                    <td><strong>Opposition</strong> (art. 21)</td>
                    <td>Vous opposer au traitement fondé sur l'intérêt légitime</td>
                </tr>
            </tbody>
        </table>
        
        <h3>Comment exercer vos droits</h3>
        <ul>
            <li><strong>Par email :</strong> envoyez votre demande à <a href="mailto:<?= Helpers::escape($cfg['admin_email']) ?>"><?= Helpers::escape($cfg['admin_email']) ?></a></li>
            <li><strong>En ligne :</strong> si vous disposez de votre lien de gestion de rendez-vous, vous pouvez supprimer vos données directement depuis votre espace client</li>
        </ul>
        <p>Nous répondons à toute demande dans un délai maximum de <strong>1 mois</strong>. La première copie de vos données est gratuite.</p>
        
        <div class="legal-highlight">
            <p><strong>Données conservées malgré une demande d'effacement :</strong> les données nécessaires au respect d'obligations légales (notamment les données de facturation pendant 10 ans) et la trace de votre demande d'effacement elle-même (à des fins de preuve de conformité) ne peuvent pas être supprimées.</p>
        </div>
        
        
        <!-- 8. RÉCLAMATION -->
        <h2>8. Réclamation auprès de la CNIL</h2>
        <p>Si vous estimez que le traitement de vos données ne respecte pas la réglementation, vous avez le droit d'introduire une réclamation auprès de la CNIL :</p>
        <ul>
            <li><strong>En ligne :</strong> <a href="https://www.cnil.fr/fr/plaintes" target="_blank" rel="noopener">www.cnil.fr/fr/plaintes</a></li>
            <li><strong>Par courrier :</strong> CNIL — 3 Place de Fontenoy, TSA 80715, 75334 Paris Cedex 07</li>
        </ul>
        
        
        <!-- 9. COOKIES -->
        <h2>9. Cookies</h2>
        <p>Ce site <strong>n'utilise aucun cookie publicitaire, analytique ou de tracking</strong>.</p>
        <p>Seul un cookie technique de session peut être utilisé pour le fonctionnement de l'interface d'administration. Ce cookie est strictement nécessaire et ne requiert pas votre consentement (directive ePrivacy, art. 5.3).</p>
        
        
        <!-- 10. SÉCURITÉ -->
        <h2>10. Mesures de sécurité</h2>
        <p>Nous mettons en œuvre les mesures techniques et organisationnelles suivantes pour protéger vos données :</p>
        <ul>
            <li>Connexion sécurisée <strong>HTTPS/TLS</strong> sur l'ensemble du site</li>
            <li>Accès restreint à la base de données (identifiants protégés, fichier de configuration non accessible publiquement)</li>
            <li>Validation et sanitisation de toutes les entrées utilisateur</li>
            <li>Tokens de gestion uniques et non prédictibles (64 caractères)</li>
            <li>Protection des dossiers sensibles par règles d'accès serveur</li>
            <li>Purge automatique des données à échéance</li>
        </ul>
        
        
        <!-- 11. SOUS-TRAITANTS -->
        <h2>11. Sous-traitants</h2>
        <table class="legal-table">
            <thead>
                <tr>
                    <th>Sous-traitant</th>
                    <th>Rôle</th>
                    <th>Localisation</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>OVH SAS</td>
                    <td>Hébergement du site et de la base de données</td>
                    <td>France (Roubaix)</td>
                </tr>
            </tbody>
        </table>
        
        
        <!-- 12. MODIFICATION -->
        <h2>12. Modification de cette politique</h2>
        <p>Cette politique de confidentialité peut être mise à jour pour refléter des évolutions réglementaires ou des changements dans nos pratiques. La date de dernière mise à jour est indiquée en haut de cette page. Nous vous encourageons à la consulter régulièrement.</p>
    </main>
    
    <!-- Footer -->
    <footer class="legal-footer">
        <p>&copy; <?= date('Y') ?> <?= Helpers::escape($cfg['site_name']) ?> &bull; <a href="mentions-legales.php">Mentions légales</a></p>
    </footer>
</body>
</html>

<?php
/**
 * ============================================
 * PAGE D'ACCUEIL (SITE VITRINE)
 * ============================================
 * Identité, coordonnées et mentions = paramétrables depuis /admin
 * via siteConfig() (settings BDD + fallbacks config.php).
 */
require_once __DIR__ . '/includes/init.php';
use App\Helpers;
use App\Icons;
$cfg = siteConfig();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Helpers::escape($cfg['site_name']) ?> — <?= Helpers::escape($cfg['full_name']) ?></title>
    <meta name="description" content="Renaud Heitz, Focus Coach à Bordeaux : coaching, accompagnement de transformations, préparation mentale et formation. Créer l'alchimie collective.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300&display=swap" rel="stylesheet">
    <!-- Point d'entrée CSS : design system global puis module page d'accueil -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/home.css">
    <?= pwaHead() ?>
</head>
<body class="home">

    <!-- ═══ NAV ═══ -->
    <nav class="home-nav">
        <a class="nav-brand" href="#home">
            <img src="assets/img/logo_Focus_Coach.png" alt="Focus Coach">
            <span class="nav-brand-text"><?= brandWordmark() ?></span>
        </a>
        <ul class="nav-links">
            <li><a href="#services">Accompagnements</a></li>
            <li><a href="#philosophie">Philosophie</a></li>
            <li><a href="#about">À propos</a></li>
            <li><a href="#parcours">Parcours</a></li>
            <li class="nav-cta-item"><a href="modules/booking/" class="nav-cta">Prendre RDV <?= Icons::svg('zap', 16, 'icon-cta') ?></a></li>
        </ul>
    </nav>

    <!-- ═══ 1. HERO ═══ -->
    <section class="hero" id="home">
        <div class="hero-left">
            <p class="hero-eyebrow"><?= Helpers::escape($cfg['full_name']) ?> · Bordeaux, Nouvelle-Aquitaine</p>
            <h1 class="hero-title">Créer<br><em>l'alchimie</em><br>collective</h1>
            <p class="hero-subtitle">Coach, accompagnateur de transformations, préparateur mental et formateur — j'aide individus, équipes et organisations à trouver le sens, impulser le mouvement et agir avec justesse.</p>
            <div class="hero-cta-group">
                <a href="#contact" class="btn-primary">Parlons de votre projet</a>
                <a href="#services" class="btn-ghost">Ce que je propose</a>
            </div>
        </div>
        <figure class="hero-right hero-figure">
            <img src="assets/img/RH_photo_nb.jpg" alt="Renaud Heitz">
            <figcaption class="figure-caption">
                <p>« L'alchimie collective, c'est mon terrain depuis vingt ans. »</p>
            </figcaption>
        </figure>
    </section>

    <!-- ═══ 1b. VOUS VOUS RECONNAISSEZ ═══ -->
    <section class="section section--sand section--compact">
        <p class="section-eyebrow">À qui je m'adresse</p>
        <h2 class="section-title section-title--tight">Vous vous reconnaissez ?</h2>
        <div class="audience-grid">
            <article class="audience-card">
                <p class="audience-kicker">Manager ou dirigeant</p>
                <p class="audience-lead">Vous portez une équipe, une vision, une organisation — et parfois vous portez seul.</p>
                <p class="audience-text">Vous cherchez à mieux vous positionner, à prendre du recul sur vos décisions, à insuffler une dynamique collective qui tienne dans la durée.</p>
            </article>
            <article class="audience-card audience-card--navy">
                <p class="audience-kicker">Organisation en transformation</p>
                <p class="audience-lead">Votre structure évolue, fusionne, se réorganise — et les humains ont du mal à suivre.</p>
                <p class="audience-text">Vous avez besoin d'un regard extérieur pour accompagner le changement, créer de la cohésion et donner du sens à ce qui se construit.</p>
            </article>
            <article class="audience-card audience-card--blue">
                <p class="audience-kicker">Sportif ou équipe de compétition</p>
                <p class="audience-lead">Vous avez le physique, la technique — mais la tête ne suit pas toujours le jour J.</p>
                <p class="audience-text">Vous cherchez à mieux gérer la pression, construire une cohésion d'équipe solide et transformer le stress en carburant de performance.</p>
            </article>
            <article class="audience-card">
                <p class="audience-kicker">Particulier en transition</p>
                <p class="audience-lead">Vous traversez une période charnière — professionnelle, personnelle, ou les deux.</p>
                <p class="audience-text">Vous avez besoin d'un espace pour clarifier, décider, reprendre confiance et avancer avec plus de légèreté et d'alignement.</p>
            </article>
            <article class="audience-card audience-card--navy">
                <p class="audience-kicker">Réseau ou collectif d'entrepreneurs</p>
                <p class="audience-lead">Vous animez ou appartenez à un réseau qui veut créer plus que du networking.</p>
                <p class="audience-text">Vous voulez faire émerger une vraie intelligence collective, des échanges qui nourrissent, des liens qui durent au-delà des événements.</p>
            </article>
            <article class="audience-card audience-card--cta">
                <p>Pas sûr de vous reconnaître dans ces profils ? Écrivez-moi en deux lignes — on trouvera ensemble.</p>
                <a href="#contact">Me contacter <?= Icons::svg('arrow-right', 16, 'icon-cta') ?></a>
            </article>
        </div>
    </section>

    <!-- ═══ 2. SERVICES ═══ -->
    <section id="services" class="section section--white">
        <div class="media-band">
            <img src="assets/img/coaching_007.jpg" alt="Renaud Heitz animant un séminaire">
        </div>
        <div class="services-header">
            <div>
                <p class="section-eyebrow">Accompagnements</p>
                <h2 class="section-title">Ce que je propose</h2>
            </div>
            <p class="section-intro">Chaque mission est construite sur-mesure. Ces grandes familles donnent une idée de l'étendue de ce que nous pouvons faire ensemble.</p>
        </div>
        <div class="callout">
            <p class="callout-kicker">Ce qu'est le coaching</p>
            <p class="callout-quote">« L'accompagnement d'une personne ou d'une équipe, dans une situation professionnelle, pour l'aider à trouver ses propres solutions et développer ses compétences, dans une perspective de développement durable et global. »</p>
            <p class="callout-source">— Inspiré de l'école Vincent Lenhardt</p>
        </div>
        <div class="services-grid">
            <article class="service-card">
                <img class="service-card__img" src="assets/img/coaching_007.jpg" alt="Renaud Heitz en formation">
                <p class="service-num">01</p>
                <h3 class="service-title">Transformation organisationnelle &amp; managériale</h3>
                <p class="service-text">Conduite du changement, culture managériale, intelligence collective. Ateliers de co-construction, séminaires managers — pour traverser les mutations avec cohésion et sens.</p>
                <details class="acc"><summary>Exemples d'interventions</summary>
                    <ul class="acc-body">
                        <li>Coaching de dirigeant en prise de poste ou en transition</li>
                        <li>Séminaire de cohésion pour une équipe de managers</li>
                        <li>Construction d'un dispositif d'accompagnement interne</li>
                        <li>Élaboration d'indicateurs de performance et de bien-être</li>
                        <li>Suivi et reporting des plans d'action</li>
                    </ul>
                </details>
                <div class="service-tags"><span class="service-tag">Collectivités</span><span class="service-tag">ESS</span><span class="service-tag">Entreprises</span></div>
                <a href="#contact" class="parlons">Parlons-en <?= Icons::svg('arrow-right', 16, 'icon-cta') ?></a>
            </article>
            <article class="service-card">
                <p class="service-num">02</p>
                <h3 class="service-title">Coaching individuel — dirigeants, managers &amp; particuliers</h3>
                <p class="service-text">Prise de poste, vision, alignement personnel, traversée d'une période difficile. Un espace de recul pour agir en conscience, d'adulte à adulte, dans un rapport de responsabilité partagée.</p>
                <details class="acc"><summary>Exemples d'interventions</summary>
                    <ul class="acc-body">
                        <li>Accompagnement sur 6 à 12 séances autour d'un objectif défini</li>
                        <li>Coaching de prise de poste sur 3 à 6 mois</li>
                        <li>Travail sur la posture, la confiance, la gestion du stress</li>
                        <li>Clarification d'un projet professionnel ou de vie</li>
                        <li>Accompagnement en période de transition personnelle</li>
                    </ul>
                </details>
                <div class="service-tags"><span class="service-tag">Coaching</span><span class="service-tag">Managers</span><span class="service-tag">Gestion émotions</span></div>
                <a href="#contact" class="parlons">Parlons-en <?= Icons::svg('arrow-right', 16, 'icon-cta') ?></a>
            </article>
            <article class="service-card">
                <img class="service-card__img" src="assets/img/team_focus_coach.jpg" alt="Renaud entraîneur volley">
                <p class="service-num">03</p>
                <h3 class="service-title">Préparation mentale &amp; performance sportive</h3>
                <p class="service-text">Gestion du stress de compétition, fixation d'objectifs, visualisation, cohésion d'équipe. Issu du sport de haut niveau (volley, 20 ans), je parle la même langue que vous.</p>
                <details class="acc"><summary>Exemples d'interventions</summary>
                    <ul class="acc-body">
                        <li>Séances individuelles en visio ou en présentiel avant compétition</li>
                        <li>Travail sur le terrain pendant les entraînements</li>
                        <li>Accompagnement du staff, de l'entraîneur et des leaders</li>
                        <li>Sessions de cohésion pour construire la dynamique collective</li>
                        <li>Suivi à distance entre les phases de compétition</li>
                    </ul>
                </details>
                <div class="service-tags"><span class="service-tag">Sport collectif</span><span class="service-tag">Compétition</span><span class="service-tag">Individuel</span></div>
                <a href="#contact" class="parlons">Parlons-en <?= Icons::svg('arrow-right', 16, 'icon-cta') ?></a>
            </article>
            <article class="service-card">
                <p class="service-num">04</p>
                <h3 class="service-title">Formation thématique</h3>
                <p class="service-text">Gestion du stress, gestion des conflits, communication, régulation émotionnelle. Formats courts ou longs, intra ou inter-organisations. Depuis 2011, plus de dix ans d'interventions auprès de managers, sportifs et particuliers.</p>
                <details class="acc"><summary>Exemples d'interventions</summary>
                    <ul class="acc-body">
                        <li>Atelier d'une journée sur la gestion du stress au travail</li>
                        <li>Formation sur 2 jours à la communication non-violente</li>
                        <li>Module gestion des conflits pour une équipe managériale</li>
                        <li>Initiation à la sophrologie en entreprise (4 × 2h)</li>
                        <li>Parcours de formation sur-mesure sur plusieurs mois</li>
                    </ul>
                </details>
                <div class="service-tags"><span class="service-tag">Intra / Inter</span><span class="service-tag">Managers</span><span class="service-tag">Tous publics</span><span class="service-tag">Depuis 2011</span></div>
                <a href="#contact" class="parlons">Parlons-en <?= Icons::svg('arrow-right', 16, 'icon-cta') ?></a>
            </article>
            <article class="service-card service-card--featured">
                <img class="service-card__img" src="assets/img/coaching_007.jpg" alt="Le coach impulse le mouvement">
                <p class="service-num service-num--icon"><?= Icons::svg('zap', 32) ?></p>
                <h3 class="service-title">Coaching flash — 30 minutes pour changer de regard</h3>
                <p class="service-text">Une session courte et ciblée pour faire évoluer un système de représentation bloquant : prise de décision, conflit, doute, cap à franchir. Vous repartez avec un regard neuf et des leviers concrets.</p>
                <details class="acc"><summary>Exemples d'interventions</summary>
                    <ul class="acc-body">
                        <li>Décision difficile à prendre — clarifier en 30 min</li>
                        <li>Blocage relationnel avec un collaborateur ou un pair</li>
                        <li>Doute sur une orientation professionnelle</li>
                        <li>Préparation à un entretien ou une prise de parole</li>
                    </ul>
                </details>
                <div class="service-tags"><span class="service-tag">Visio ou présentiel</span><span class="service-tag">Réservation en ligne</span></div>
                <div class="service-price-row">
                    <span class="service-price">80 €</span>
                    <a href="modules/booking/" class="parlons">Réserver <?= Icons::svg('arrow-right', 16, 'icon-cta') ?></a>
                </div>
            </article>
        </div>
    </section>

    <!-- ═══ 3. PHILOSOPHIE ═══ -->
    <section id="philosophie" class="section section--relative">
        <div class="philo-bg">
            <img src="assets/img/coaching_007.jpg" alt="Paysage de Gironde">
            <div class="philo-overlay"></div>
        </div>
        <div class="philo-content">
            <p class="philo-eyebrow">Ma philosophie</p>
            <blockquote class="philo-quote">« La performance durable naît du lien — entre les personnes, entre les idées, entre ce qu'on vit et ce qu'on fait. »</blockquote>
            <p class="philo-text">Que ce soit sur un terrain de volley, dans une salle de séminaire ou face à une épreuve de vie, c'est toujours la même alchimie que je cherche à créer : celle qui transforme un groupe d'individus en un collectif capable de traverser ensemble.</p>
            <div class="philo-trio">
                <div class="philo-item"><p class="philo-verb">Lier</p><p class="philo-sub">Créer des connexions</p></div>
                <div class="philo-divider"></div>
                <div class="philo-item"><p class="philo-verb">Concilier</p><p class="philo-sub">Mettre en dialogue</p></div>
                <div class="philo-divider"></div>
                <div class="philo-item"><p class="philo-verb">Réconcilier</p><p class="philo-sub">Réparer ce qui s'éloigne</p></div>
            </div>
            <p class="philo-credit">Dune du Pilat · Gironde</p>
        </div>
    </section>

    <!-- ═══ 4. CHIFFRES ═══ -->
    <section id="chiffres" class="section section--sand">
        <p class="section-eyebrow">Pourquoi agir maintenant</p>
        <h2 class="section-title">Des chiffres qui parlent</h2>
        <p class="section-lead">Ces données issues d'études sérieuses (ICF, PwC, OMS, Opinion Way) disent pourquoi l'accompagnement n'est plus un luxe — c'est une nécessité stratégique.</p>
        <div class="stats-grid">
            <article class="stat-card stat-card--t-orange">
                <p class="stat-number">87%</p>
                <p class="stat-label">des organisations constatent un ROI positif du coaching</p>
                <p class="stat-source">avec un retour moyen de <strong>7 fois le coût initial</strong> — étude PricewaterhouseCoopers / ICF.</p>
            </article>
            <article class="stat-card stat-card--navy">
                <p class="stat-number">61%</p>
                <p class="stat-label">des actifs français stressés au moins une fois par semaine</p>
                <p class="stat-source">et 45% des managers ont vécu un burnout modéré ou sévère. <em>Empreinte Humaine / Opinion Way 2024.</em></p>
            </article>
            <article class="stat-card stat-card--t-blue">
                <p class="stat-number">72%</p>
                <p class="stat-label">des entreprises voient les compétences s'améliorer après coaching</p>
                <p class="stat-source">corrélation forte avec l'engagement des collaborateurs. <em>ICF / HCI 2023.</em></p>
            </article>
            <article class="stat-card stat-card--navy">
                <p class="stat-number">12 Mds</p>
                <p class="stat-label">de jours de travail perdus chaque année dans le monde</p>
                <p class="stat-source">à cause de la dépression et de l'anxiété — 1 000 milliards de perte de productivité. <em>OMS 2024.</em></p>
            </article>
            <article class="stat-card stat-card--t-orange">
                <p class="stat-number">2×</p>
                <p class="stat-label">moins de problèmes de santé mentale quand des pratiques de prévention existent</p>
                <p class="stat-source">La formation des managers est le levier prioritaire identifié. <em>Baromètre Empreinte Humaine.</em></p>
            </article>
            <article class="stat-card stat-card--t-navy">
                <p class="stat-quote">« La préparation mentale devient une compétence stratégique, directement liée aux enjeux de performance. »</p>
                <p class="stat-source">Depuis les JO de Paris 2024, ce qui relevait du sport s'impose dans les organisations performantes.</p>
            </article>
        </div>
    </section>

    <!-- ═══ 5. QUI JE SUIS ═══ -->
    <section id="about" class="section section--white">
        <p class="section-eyebrow">Qui je suis</p>
        <h2 class="section-title">Architecte de liens —<br>au service du sens et du mouvement</h2>
        <div class="grid-2 grid-2--mt">
            <figure class="about-photo">
                <img src="assets/img/RH_photo.jpg" alt="Renaud Heitz">
                <figcaption class="figure-caption figure-caption--sm">
                    <p>« Ce qui me passionne, c'est le moment où une équipe devient plus grande que la somme de ses membres. »</p>
                </figcaption>
            </figure>
            <div class="about-content">
                <p class="about-lead">Volleyeur de haut niveau pendant vingt ans, j'ai appris sur le terrain que la victoire n'appartient jamais à un seul homme — elle naît des liens qu'on tisse entre joueurs, de la confiance qu'on construit dans l'adversité.</p>
                <p class="about-text">Cette conviction, je la retrouve partout où j'interviens : dans les collectivités territoriales où j'accompagne les managers depuis plus de quinze ans, dans les entreprises que j'aide à traverser leurs transformations, dans les séances individuelles de coaching ou de gestion des émotions.</p>
                <p class="about-text">J'ai aussi traversé une épreuve personnelle sérieuse qui m'a profondément transformé — et qui nourrit aujourd'hui ma pratique d'accompagnement. On ne parle bien que de ce qu'on a soi-même traversé.</p>
                <p class="about-text">Ergonome de formation (Master CNAM), coach certifié, spécialiste de la gestion des émotions et du stress, préparateur mental et formateur, je n'accumule pas les casquettes par hasard : chacune m'apporte un regard différent sur les mêmes questions essentielles — comment les humains fonctionnent, comment ils se relient, comment ils grandissent ensemble, et comment analyser cette complexité pour en faire une force.</p>
                <p class="about-text">Ma pratique s'ancre dans l'<strong>analyse transactionnelle</strong> — je travaille d'adulte à adulte, dans un rapport de responsabilité partagée et d'égale dignité. Et parce que l'apprentissage durable passe aussi par le plaisir, j'apporte systématiquement une dimension <strong>ludique</strong> à mes interventions : on mobilise mieux ce qu'on a vécu avec légèreté.</p>
                <p class="about-text">Une conviction traverse toute ma pratique : <strong>nos vulnérabilités, bien comprises et bien travaillées, deviennent nos puissances les plus durables.</strong></p>
                <div class="about-pills">
                    <span class="pill">Coach certifié</span>
                    <span class="pill">Gestion des émotions &amp; stress</span>
                    <span class="pill">Préparateur mental</span>
                    <span class="pill">Ergonome CNAM</span>
                    <span class="pill">Formateur</span>
                    <span class="pill-light">Entraîneur volley</span>
                    <span class="pill-light">Musicien</span>
                    <span class="pill-light">Attaché territorial</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══ 6. PARCOURS ═══ -->
    <section id="parcours" class="section section--sand">
        <p class="section-eyebrow">Parcours</p>
        <h2 class="section-title">Une trajectoire cohérente,<br>au service de l'humain</h2>
        <div class="timeline">
            <div class="timeline-item"><p class="timeline-year">Depuis 2025</p><div class="timeline-content"><p class="timeline-title">Chef de projet — Cellule de crise, Département de la Gironde</p><p class="timeline-text">Dialogue de gestion entre la Direction Générale et les 40 directions. Suivi des trajectoires financières et mobilisation des ressources transverses.</p></div></div>
            <div class="timeline-item"><p class="timeline-year">Depuis 2018</p><div class="timeline-content"><p class="timeline-title">Conduite du changement &amp; accompagnement managérial — DGA Ressources, Gironde</p><p class="timeline-text">Transformation organisationnelle, animation de séminaires managers, accompagnement de dirigeants, formation managériale. Intervenant auprès du Comité de Direction Générale.</p></div></div>
            <div class="timeline-item"><p class="timeline-year">Depuis 2011</p><div class="timeline-content"><p class="timeline-title">Formateur &amp; Coach indépendant — autoentrepreneur</p><p class="timeline-text">Gestion du stress, gestion des conflits, communication, gestion des émotions, coaching individuel et collectif, préparation mentale. Interventions auprès de sportifs, managers et particuliers.</p></div></div>
            <div class="timeline-item"><p class="timeline-year">2020–2021</p><div class="timeline-content"><p class="timeline-title">Certification coaching individuel, collectif et organisation — Alliance Coach</p><p class="timeline-text">Pratique du coaching en contexte professionnel et personnel. Approches systémiques et centrées sur la personne.</p></div></div>
            <div class="timeline-item"><p class="timeline-year">2015–2018</p><div class="timeline-content"><p class="timeline-title">Master 2 Ergonomie de l'activité — CNAM Paris</p><p class="timeline-text">Sciences humaines et sociales, travail et développement. Approche systémique des organisations, analyse du travail réel, intervention organisationnelle.</p></div></div>
            <div class="timeline-item"><p class="timeline-year">Jusqu'en 2020</p><div class="timeline-content"><p class="timeline-title">Sportif de haut niveau &amp; entraîneur — Volley-Ball (IDF et Gironde)</p><p class="timeline-text">Vingt ans de pratique compétitive et d'entraînement. La cohésion d'équipe comme laboratoire permanent de ce que j'applique ensuite dans les organisations.</p></div></div>
        </div>
    </section>

    <!-- ═══ 7. COACHING FLASH — appel à réservation (vers le module /booking/) ═══ -->
    <section id="reservation" class="section section--sand">
        <p class="section-eyebrow">Coaching Flash</p>
        <h2 class="section-title">30 minutes pour changer de regard</h2>
        <p class="section-lead">Une décision à prendre, un blocage, un cap à franchir ? Réservez une session courte et ciblée. Choisissez votre créneau en ligne — je confirme sous 24 h avec le lien de visio ou l'adresse à Bordeaux.</p>
        <div class="flash-cta">
            <div class="flash-cta-body">
                <p class="flash-cta-kicker">Comment ça marche</p>
                <h3 class="flash-cta-title">Un format court, un impact immédiat</h3>
                <p class="flash-cta-text">Le coaching flash s'attaque à un système de représentation bloquant. En 30 minutes, on clarifie la situation et on repart avec des leviers concrets.</p>
                <ul class="flash-cta-list">
                    <li>Choisissez une date et un horaire disponibles</li>
                    <li>Laissez vos coordonnées et votre sujet en quelques mots</li>
                    <li>Recevez la confirmation et les modalités sous 24 h</li>
                    <li>Annulation possible jusqu'à 24 h avant la séance</li>
                </ul>
            </div>
            <div class="flash-cta-aside">
                <p class="flash-cta-price">80 €<span> / séance</span></p>
                <p class="flash-cta-duration">Session de 30 minutes · visio ou présentiel</p>
                <a href="modules/booking/" class="btn-primary">Réserver un créneau <?= Icons::svg('arrow-right', 18, 'icon-cta') ?></a>
                <p class="flash-cta-note">Réservation sécurisée. Paiement demandé par email à la confirmation.</p>
            </div>
        </div>
    </section>

    <!-- ═══ 8. CONTACT ═══ -->
    <section id="contact" class="section section--white">
        <p class="section-eyebrow">Contact</p>
        <h2 class="section-title">Parlons de votre projet</h2>
        <div class="contact-grid">
            <div class="contact-left">
                <p class="contact-lead">Chaque accompagnement commence par une conversation. Que vous soyez une organisation en transformation, un sportif qui prépare une compétition ou un particulier en période de questionnement — prenez contact, c'est sans engagement.</p>
                <div class="contact-infos">
                    <div class="contact-info"><span class="contact-icon"><?= Icons::svg('map-pin', 18) ?></span><span>Bordeaux — interventions en Nouvelle-Aquitaine et France entière</span></div>
                    <div class="contact-info"><span class="contact-icon"><?= Icons::svg('phone', 18) ?></span><span><?= cfgField($cfg['admin_phone'], 'Téléphone à renseigner') ?></span></div>
                    <div class="contact-info"><span class="contact-icon"><?= Icons::svg('mail', 18) ?></span><span><?= cfgField($cfg['admin_email']) ?></span></div>
                    <div class="contact-info"><span class="contact-icon"><?= Icons::svg('clock', 18) ?></span><span>Réponse sous 24 heures</span></div>
                </div>
            </div>
            <form class="contact-right" id="contact-form" data-dest="<?= Helpers::escape($cfg['admin_email']) ?>">
                <div class="form-group">
                    <label class="form-label" for="ct-name">Nom complet</label>
                    <input type="text" id="ct-name" class="form-input" placeholder="Prénom Nom">
                </div>
                <div class="form-group">
                    <label class="form-label" for="ct-email">Email</label>
                    <input type="email" id="ct-email" class="form-input" placeholder="vous@organisation.fr">
                </div>
                <div class="form-group">
                    <label class="form-label" for="ct-profile">Vous êtes</label>
                    <select id="ct-profile" class="form-select">
                        <option value="">Sélectionnez votre profil</option>
                        <option>Une organisation publique ou privée</option>
                        <option>Un dirigeant ou manager</option>
                        <option>Un sportif ou une équipe</option>
                        <option>Un particulier</option>
                        <option>Autre</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="ct-message">Votre message</label>
                    <textarea id="ct-message" class="form-textarea" placeholder="Décrivez en quelques mots votre situation ou votre besoin..."></textarea>
                </div>
                <button type="submit" class="form-submit">Envoyer ma demande <?= Icons::svg('arrow-right', 18, 'icon-cta') ?></button>
            </form>
        </div>
    </section>

    <!-- ═══ FOOTER ═══ -->
    <footer class="home-footer">
        <div class="footer-top">
            <div>
                <div class="footer-brand">
                    <img src="assets/img/logo_Focus_Coach.png" alt="Focus Coach">
                    <p class="footer-brand-text"><?= brandWordmark() ?></p>
                </div>
                <p class="footer-tagline">Architecte de liens · Coach, accompagnateur de transformations, préparateur mental et formateur. Bordeaux, Nouvelle-Aquitaine.</p>
                <p class="footer-legal">SIRET : <span class="value"><?= cfgField($cfg['admin_siret']) ?></span></p>
                <p class="footer-legal"><?= cfgField($cfg['legal_status'], 'Statut juridique à compléter') ?></p>
            </div>
            <div>
                <p class="footer-col-title">Navigation</p>
                <div class="footer-links">
                    <a href="#services">Accompagnements</a>
                    <a href="#philosophie">Philosophie</a>
                    <a href="#about">Qui je suis</a>
                    <a href="#parcours">Parcours</a>
                    <a href="modules/booking/">Prendre rendez-vous</a>
                </div>
            </div>
            <div>
                <p class="footer-col-title">Informations</p>
                <div class="footer-links">
                    <a href="mentions-legales.php">Mentions légales</a>
                    <a href="confidentialite.php">Confidentialité &amp; RGPD</a>
                    <a href="mailto:<?= Helpers::escape($cfg['admin_email']) ?>"><?= Helpers::escape($cfg['admin_email']) ?></a>
                    <?php if (!empty($cfg['admin_phone'])): ?><a href="tel:<?= Helpers::escape(preg_replace('/\s+/', '', $cfg['admin_phone'])) ?>"><?= Helpers::escape($cfg['admin_phone']) ?></a><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© <?= date('Y') ?> <?= brandWordmark() ?> · <?= Helpers::escape($cfg['full_name']) ?> · Tous droits réservés</p>
            <p class="footer-note">Membre engagé dans les principes éthiques ICF &amp; EMCC</p>
        </div>
    </footer>

    <!-- ═══ JS — formulaire de contact (mailto, sans backend) ═══ -->
    <script src="assets/js/icons.js"></script>
    <script src="assets/js/home.js"></script>
    <?= pwaRegister() ?>
</body>
</html>

# CADRAGE UNIVERSEL — Règles communes à tous les projets

> Socle de développement commun. Chaque projet **dérive** son `CADRAGE_<PROJET>.md` de ce document : il **ajoute** ses règles métier/techniques, il ne contredit pas le socle.
> **Distribution mono-source (le socle s'applique à lui-même, AD-2)** : il existe **un seul exemplaire de référence** (repo dédié / submodule / lien). Les projets le **référencent**, ne le **copient pas** — sinon le document anti-dérive devient sa propre première victime. Une surcouche projet n'override jamais le §1.
> **Hypothèse par défaut (récurrente, pas systématique)** : **PHP vanilla + JS natif (sans build) sur OVH mutualisé**, livré en **PWA mobile-first** (installable, capable hors-ligne). → Si un projet diffère (Node, build, VPS, autre hébergeur, ou pas de PWA), l'indiquer explicitement dans son cadrage ; **les règles anti-dérive du §1 restent valables quel que soit le stack.**
> Principe directeur transversal : **une seule autorité par sujet**, et **« prouver » plutôt que « se souvenir »** (garde-fous exécutables).

---

## 1. RÈGLES ANTI-DÉRIVE (« AD ») — FIGÉES (valent pour TOUS les projets)

> **AD = règles Anti-Dérive d'architecture.** Ce sont les règles non négociables. Toute exception est une **décision explicite, tracée dans le `CLAUDE.md` du projet**, jamais un glissement silencieux.
> *(AD-9 non-régression et AD-10 vérification-avant-confiance sont les disciplines qui produisent le plus de valeur à l'usage — les traiter comme aussi prioritaires qu'AD-1.)*

**AD-1 — Version à source unique.**
La version vit dans **un seul fichier** (`VERSION` → un `version.php`/équivalent), dont **tout le reste dérive**. **Aucun numéro de version codé en dur** dans le code. Un **garde-fou** vérifie que les `.md` (`README`, `README_TECHNIQUE`, `CHANGELOG`, `CLAUDE`) portent exactement cette version. Bumper = éditer la source + les stamps + le CHANGELOG, puis garde-fou VERT.

**AD-2 — Une seule source de vérité par domaine.**
Configuration, listes, corpus de test, table d'endpoints, design tokens : **chaque donnée vit à un seul endroit**. Toutes les surfaces (UI, tests, doc) **en dérivent** — jamais l'inverse, jamais de copie locale qui diverge.

**AD-3 — Zéro duplication de code.**
`grep` avant d'écrire toute fonction. Si elle existe → la **réutiliser ou l'étendre**, **jamais la recréer**. Pas de logique dupliquée entre services, pas de helper recréé.

**AD-4 — CSS sous contrôle strict.**
Un **seul point d'entrée design system** (ex. `common.css`/`main.css`). **Tokens centralisés** (variables CSS ou config unique) = seule source des couleurs/espacements/typo. **Interdits** : couleur en dur hors tokens, `<style>` par page (au-delà d'une micro-animation locale), CSS module chargé en `<link>` séparé, **`!important`**. Pages outils/admin = **design figé** (pas de thème maison ad-hoc par page).

**AD-5 — Librairies front décidées une fois, puis figées.**
Le choix d'une lib par rôle (**icônes**, **framework/utilitaires CSS**, **carte**, **charts**) se discute **en début de construction**, puis **ne dérive plus**. **Une seule lib par rôle, jamais de mélange** (ex. pas de deux jeux d'icônes cohabitant), **zéro emoji Unicode** en guise d'icône. *Nuance phase d'exploration* : on peut **comparer** deux libs avant de trancher, mais on **fige dès que le choix est stabilisé** — jamais de cohabitation durable. Changer de lib après coup = **décision tracée dans `CLAUDE.md`**, appliquée partout, puis **exclusive**.

**AD-6 — Modularité.**
Un **module = une unité autonome, activable/désactivable**, déclarée dans la **config centrale** (sinon : pas de toggle, jamais activé). **« désactivé » ≠ « indisponible »** : ne jamais présenter un module éteint comme rendu, ni un échec comme une absence.

**AD-7 — Documentation obligatoire à chaque itération.**
À chaque modification : `README_TECHNIQUE.md` (toujours : changelog + arborescence + carte des fonctions + relations), `CHANGELOG.md` (toujours), la doc de référence concernée, `CLAUDE.md` si nouvelle règle/architecture. **Grep `CHANGELOG` avant toute suppression** : si c'est documenté, c'est intentionnel.

**AD-8 — Garde-fous exécutables ET automatiques.**
Préférer un script qui **prouve** l'alignement (version↔docs, config↔surfaces, endpoints↔doc) à une règle qu'on « se souvient » de respecter. **Crucial** : un garde-fou qu'on doit *penser* à lancer réintroduit le « se souvenir » qu'il combat. Il doit donc **mordre automatiquement** — **pre-commit hook**, **CI**, ou **hook Claude Code** (exécuté sans intervention). **Ne jamais committer avec un garde-fou rouge** ; un garde-fou non câblé à un déclencheur automatique est un vœu, pas un mécanisme. *Concrètement* : un kit compagnon `cadrage-tools/` = **squelette d'audits** (version/CSS/doc/duplication) + **hook `pre-commit` générique**, **référencé** par le projet (AD-2), que **chaque projet câble à ses propres chemins** (où vivent les tokens, le fichier `VERSION`, le schéma). Pas de scripts « magiques » universels — un audit dépend de la config du projet.
*Pendant l'exécution (runtime)* : le **pendant runtime** des garde-fous build-time est un endpoint d'auto-diagnostic **`health.php`** (accès protégé : admin connecté **ou** token de santé en `config.local`), qui **prouve** l'état du déploiement plutôt que de le supposer. Statut global **VERT / ORANGE / ROUGE** + détail : version PHP ≥ requis & extensions nécessaires présentes ; connexion BDD OK + charset `utf8mb4` ; **tables et fichiers attendus présents** (`config.local.php`, service worker & `manifest.json` *(noms selon projet)*, socle référencé…) ; **cohérence mono-source (AD-2)** — `VERSION` ↔ stamps `.md`, et **config ↔ surfaces ↔ BDD** (ex. chaque entité payante a un identifiant de prix valide là où vit la source de vérité ; endpoints ↔ table d'endpoints) ; absence d'incohérence de données (doublons interdits, compteurs bornés). **Le health doit rester rapide et ne pas devenir lui-même un point de fragilité** : séparer un check **liveness bon marché** (PHP/BDD/fichiers/cohérence locale) d'un check **profond** à la demande, et **ne jamais appeler une API tierce en synchrone** (la joignabilité externe est un check séparé, en cache/à la demande — cf. résilience §3), sinon une panne externe passe le statut ROUGE pour une raison hors déploiement. Le `health.php` **respecte AD-3 et AD-4 comme tout le reste** : helpers existants réutilisés (zéro duplication), vue HTML en tokens CSS centralisés (aucun style ad-hoc).

**AD-9 — Filet de non-régression.**
Un **corpus de cas à source unique** (les entrées de test vivent à un seul endroit, comme toute donnée — AD-2) est **rejoué à chaque itération**. **Tout cas étudié devient une entrée du corpus** (un bug compris = un cas ajouté, qui ne reviendra plus). Un changement qui **casse un cas connu est bloquant** — le verdict de non-régression est câblé au garde-fou automatique (AD-8), pas laissé au jugement.
Le corpus couvre explicitement les **API/endpoints** : pour chaque endpoint, au moins un cas par chemin critique — **auth requise → 401/403**, **cas nominal → 200 + forme de réponse attendue**, **cas d'erreur → code attendu**. La **table d'endpoints à source unique** (AD-2) alimente ces tests et le diagnostic `health.php` (AD-8).

**AD-10 — Vérification avant confiance.**
**Étiqueter les affirmations factuelles porteuses** — celles sur lesquelles une décision repose — par leur niveau de preuve (`[CODE]` lu dans le fichier / `[WEB]` source en ligne / `[O1]` observé en exécution / `[?]` supposé). Inutile de taguer le texte courant : tout étiqueter = bruit qui ne prouve plus rien. **Lire la source entière avant de conclure** — jamais d'un extrait tronqué. En cas de doute ou de manque : **« non disponible / à vérifier », jamais une valeur inférée** ; **les conclusions ne s'appuient jamais sur du `[?]` non levé**. **Absence ≠ négation** (pas de résultat ≠ « ça n'existe pas »). **S'applique aux sorties d'un LLM autant qu'au texte humain** : toute affirmation d'un agent (Claude Code inclus) sur une API/lib/dépendance externe doit être étiquetée + sourcée — « je suis sûr » sans source = `[?]`. C'est la discipline qui protège le plus contre les conclusions fausses ; elle prime sur la rapidité.

**AD-11 — Module diagnostic standard.**
Tout projet fournit un module `modules/diagnostic/` : un **hub** + des **pages de test** autonomes (santé runtime, contrôle de config, alignement version/doc/données, consoles API en **lecture seule**, smoke navigateur). **Protégé par auth, LECTURE SEULE** : aucune action destructive, **aucun effet de bord en GET**, **secrets toujours masqués** (jamais une clé/token/mot de passe dans le HTML, **même tronqué**). **Un seul design system** : écrit **exclusivement en tokens CSS**, zéro valeur en dur (couleur/hex) hors **un unique bloc de fallback délimité** (`var(--token, défaut)` neutre sobre) — garde-fou exécutable (AD-8) ; **jamais deux feuilles concurrentes ni détection JS du thème** (la cascade CSS suffit). **Zéro duplication** (AD-3) : auth, rendu des cartes `ok`/`attention`/`bloquant` et masquage des secrets vivent dans un helper commun, réutilisé par toutes les pages. Les détections (config, alignement) **dérivent d'une source unique** (AD-2) partagée avec les garde-fous build-time. **Vérifié dans les 2 sens** (AD-9/AD-10) : chaque garde prouve qu'il **lève** sur anomalie **et** se **tait** quand tout va bien. *Recoupe AD-8 (pendant runtime des garde-fous) : `health.php` y devient une page du module plutôt qu'un endpoint racine.*

---

## 2. Avant d'écrire la première ligne de code (rituel)

1. `grep -r "nom_de_la_fonction"` dans tout le projet (AD-3).
2. Lire `README_TECHNIQUE.md` : arborescence + carte des fonctions.
3. Vérifier les helpers globaux existants (JS et PHP) avant d'en créer un.
4. Pour le SQL : vérifier l'orthographe exacte des colonnes dans le **schéma** (la source de vérité), pas dans la mémoire.
5. Après modification → mettre à jour la doc (AD-7).

## 3. Stack par défaut & contraintes (OVH mutualisé)

> À adapter si le projet diffère ; le §1 reste valable.

- **PHP 8.x vanilla**, **JS natif ES6+ sans build**, **MySQL via PDO** (requêtes préparées, `FETCH_ASSOC`, singleton de connexion).
- **Pas de** : SSH, Composer, npm/webpack/vite/babel, framework CSS externe lourd, Node serveur. Déploiement **FTP**.
- **Timeout Apache ~60 s** : les traitements lourds passent en **CLI/cron** (panneau OVH), jamais en HTTP synchrone.
- **Pas de shell serveur** → quand une donnée « ne s'affiche pas », **instrumenter un encadré Debug** (présence, flags, params envoyés) **avant** de toucher au rendu. **Diagnostic temporaire = cycle complet** : ajouter des logs précis → lire → conclure → **les retirer dans un commit cleanup dédié** (le retrait est ce qui sauve — sinon le diag traîne).
- **Résilience des dépendances externes** : timeout, fallback/miroir, « indisponible ≠ vide », ne jamais bloquer le pipeline sur un service tiers.
- **Déploiement FTP = pas d'atomicité** → prévoir une **stratégie de rollback** (version précédente conservée, bascule rapide) ; un upload partiel ne doit pas laisser l'app dans un état cassé.
- **JS** : `const`/`let` (jamais `var`) ; tous les appels backend via **un seul module** (`api.js`) ; debounce ~300 ms sur autocomplétions/recherches ; **drag & drop = events touch obligatoires**.

## 4. Sécurité (transversal)

- **Données payantes / restreintes JAMAIS envoyées** au navigateur sans **vérification PHP serveur** — le JS masque, il ne protège pas.
- **Échappement de sortie systématique (anti-XSS)** : **toute** donnée rendue dans le HTML passe par `htmlspecialchars()` côté PHP / un `escapeHtml()` côté JS — c'est une **règle**, pas un usage. Aucune donnée utilisateur ou externe injectée brute dans le DOM.
- **Validation des entrées** côté serveur (type, format, bornes) — **distincte** de l'échappement de sortie : valider en entrée *et* échapper en sortie, jamais l'un à la place de l'autre.
- **CSRF** : tout POST qui **change l'état** (paiement, confirmation, CRUD) exige un **jeton CSRF** vérifié côté serveur.
- **Rate limiting** serveur sur les POST sensibles (login, paiement) contre le brute-force/abus.
- **HTTPS forcé** (acquis sur OVH, mais à poser explicitement) ; cookies de session `Secure` + `HttpOnly`.
- **Secrets dans `config.local.php`** (BDD, clés API, Stripe, OAuth) — **jamais versionné** ; fournir un `.template`. Vérifier le `.gitignore`.
- **Requêtes préparées** uniquement — jamais de SQL par concaténation.
- **Sessions PHP natives** — pas de JWT applicatif, pas d'auth en localStorage.
- Backoffice/admin **protégé** (`.htpasswd` ou login serveur).

## 5. Données & conventions

- **Codes = chaînes (VARCHAR)**, jamais INT : préserver les **zéros préfixes** dans tout le pipeline (PHP, JS, SQL, import/export). Jamais de cast int/float sur un champ code.
- **Identifiants/codes structurants = strings** (codes cadastraux, IDU, codes client/article, codes postaux…).
- **Pluralisation/accords** : tout libellé contenant un compteur passe par un helper dédié (cas N=0, N=1, N>1) — jamais de `(s)` ni de concaténation manuelle.
- **Nommage** : métier en français, technique en anglais ; nom explicite suffisant à comprendre le rôle.
- **Commentaires** : en-tête de fichier (rôle, dépendances), description par fonction, sections CSS balisées.
- **Migrations BDD non destructives** : `schema.sql` (cible, reconstruction propre) ≠ `migrations/` (chemin d'upgrade d'une prod) ; ALTER idempotents, **jamais de reset silencieux** (réimporter le schéma ne doit pas effacer une prod).

## 6. Mobile first & PWA — cible par défaut (complète AD-4)

**Responsive (mobile first) :**

- **Mobile first** : styles de base = mobile, media queries pour desktop.
- **Breakpoints définis une seule fois**, utilisés partout pareil ; media queries **centralisées** (pas dispersées) ; **jamais de doublon**.
- **Jamais de logique responsive dans le JS** — c'est le rôle exclusif du CSS.
- Feedback tactile (`:active`, `-webkit-tap-highlight-color`) sur les éléments interactifs.

**PWA (objectif par défaut, sauf projet explicitement non-PWA) :**

- **`manifest.json`** (nom, icônes, `display: standalone`, couleurs de thème) + **`service-worker.js`** enregistré → application **installable**.
- **Stratégies de cache différenciées** (durables) :
  | Type | Stratégie | Raison |
  |---|---|---|
  | HTML / PHP | **network-first** (fallback cache offline) | pages dynamiques |
  | APIs (`/api/`) | **network-only** | données toujours fraîches |
  | Assets (CSS, JS, fonts, images) | **cache-first** + maj background | performance |
- **Bumper `CACHE_NAME`** à chaque release touchant CSS/JS (sinon cache obsolète servi) — à intégrer au geste de version (AD-1).
- **URLs canoniques** : sur Apache mutualisé, une URL `/chemin/` avec query string peut être redirigée 301 en perdant la query → préférer `/chemin/index.php?query=…`.
- Offline = **dégradation gracieuse** : informer l'utilisateur, ne jamais présenter une donnée périmée comme fraîche (cohérent avec AD-6 « indisponible ≠ absence »).

## 7. Documentation de référence (standard inter-projets)

| Fichier | Contenu |
|---|---|
| `README.md` | Présentation + guide utilisateur + changelog |
| `README_TECHNIQUE.md` | Arborescence · carte des fonctions (PHP **et** JS) · relations entre fichiers · design system · conventions |
| `CHANGELOG.md` | Tableau daté par version (Keep a Changelog) |
| `CLAUDE.md` | Directives opérationnelles pour Claude Code + règles d'or numérotées |
| `CADRAGE_<PROJET>.md` | Résumé lisible dérivé de ce socle |
| `docs/GUIDE_TECHNIQUE_ERREURS.md` | Erreurs à ne pas refaire |
| **ADR** (journal de décisions) | Tracer **pourquoi** chaque décision d'architecture a été prise — ce qui sauve à 6 mois. Fichier dédié (ex. `docs/DECISIONS.md`) ou journal existant faisant office (ex. un `IDENTIFICATION_PARCELLE.md`). |

## 8. Checklist avant livraison

- [ ] `grep` + carte des fonctions vérifiés → zéro doublon (AD-3)
- [ ] Doc à jour : `README_TECHNIQUE` + `CHANGELOG` (+ `README`/`CLAUDE` si besoin) (AD-7)
- [ ] Version : source unique éditée + stamps `.md` alignés + garde-fou VERT (AD-1)
- [ ] Non-régression : corpus rejoué, nouveau cas ajouté si bug compris, verdict VERT (AD-9)
- [ ] Endpoints couverts par le corpus (auth → 401/403, nominal → 200, erreur → code attendu) (AD-9)
- [ ] Affirmations étiquetées par niveau de preuve ; aucun « inféré » présenté comme certain (AD-10)
- [ ] Modules indépendants (activables/désactivables), déclarés en config centrale (AD-6)
- [ ] `health.php` présent et VERT (runtime, BDD, structure, cohérence mono-source) ; respecte AD-3/AD-4 (AD-8)
- [ ] CSS : tokens utilisés, pas de couleur en dur, pas de `<style>` évitable, pas de `!important` (AD-4)
- [ ] Une seule lib par rôle, pas de mélange, pas d'emoji-icône (AD-5)
- [ ] Codes/zéros préfixes préservés ; identifiants = strings (§5)
- [ ] Données restreintes protégées côté serveur ; **sortie échappée (XSS) ; CSRF sur POST d'état** ; secrets non versionnés (§4)
- [ ] Mobile first vérifié ; breakpoints uniques (§6)
- [ ] PWA OK si applicable : `manifest` + service worker, stratégies de cache, `CACHE_NAME` bumpé (§6)
- [ ] Compatible cible d'hébergement (par défaut OVH mutualisé : PHP pur, pas de dépendance serveur) (§3)

---

## 9. Ce qu'il ne faut JAMAIS faire (rappel condensé)

- Recréer une fonction/un helper qui existe déjà.
- Coder un numéro de version en dur (hors la source unique).
- Dupliquer une liste/un corpus/une config qui a déjà une source de vérité.
- Mettre une couleur hex en dur hors des tokens, un `<style>` par page, un `!important`.
- Mélanger deux libs pour un même rôle, ou ajouter une lib sans décision tracée.
- Envoyer des données restreintes sans vérification serveur ; **rendre une donnée dans le DOM sans l'échapper**.
- Commiter `config.local.php` / des secrets.
- Committer avec un garde-fou rouge, ou casser un cas connu du corpus (AD-9).
- Créer un fichier sans l'ajouter à l'arborescence de `README_TECHNIQUE.md`.
- Conclure d'un extrait tronqué, ou présenter une valeur **inférée** comme certaine (AD-10).

---

## 10. Gouvernance & évolution du socle

- **Verdict humain souverain** : un agent LLM (Claude Code) **audite, propose, code** ; **l'humain tranche** toute décision d'architecture. Seules des conclusions vérifiées circulent de l'audit vers l'implémentation — pas des hypothèses.
- **v1.x figé = base stable.** Le socle n'évolue pas par relecture mais par **usage** : un projet réel qui bute sur un angle mort → presque toujours une entrée de `GUIDE_TECHNIQUE_ERREURS.md`, parfois un nouvel AD.
- **Le socle se soumet à ses propres règles** : **versionné** (AD-1) et **mono-source** (AD-2). Un changement = proposition tracée → verdict humain → **atterrit dans l'unique source** → les projets re-référencent (jamais de patch local divergent) → bump de version + entrée changelog. Pas de modification silencieuse, sinon le socle violerait son propre AD-2.
- **Test de complétude = empirique**, pas une N+1ᵉ passe : la prochaine vraie lacune se révélera sur le terrain.

---

*CADRAGE UNIVERSEL — **v1.3 (figé)**. Socle commun, distribution mono-source (référencé, jamais copié — cf. en-tête). Hypothèse par défaut : PHP vanilla / JS natif / OVH mutualisé, PWA mobile-first (à adapter par projet). §1 (AD-1→AD-11) figé quel que soit le stack.*
*Changelog : **v1.3** — ajout d'**AD-11 (module diagnostic standard)** sur verdict humain : hub + pages de test en lecture seule, auth, secrets masqués, tokens CSS exclusifs (bloc fallback unique), zéro duplication, vérifié dans les 2 sens. Recoupe AD-8 (`health.php` devient une page du module). **§1 passe à 11 AD** — ajout, aucune renumérotation. **v1.2** — fichier compagnon `INSTRUCTIONS_DEMARRAGE` : sources d'autorité recentrées sur l'**état réel du repo**, **ZIP repositionné** comme réamorçage manuel quand la synchro de l'instance est en retard, pipeline Claude↔Claude Code explicité. Socle : **AD-8 étendu au pendant runtime** (endpoint `health.php` d'auto-diagnostic) ; **AD-9 étendu aux tests d'API/endpoints** ; deux lignes ajoutées à la checklist §8. Substance **fondue dans l'existant**, **§1 maintenu à 10 AD**, **aucune renumérotation**. Raffinements de revue : `health.php` = liveness rapide / check profond séparés + **aucun appel tiers synchrone** (résilience) ; noms PWA (`service-worker`/`sw.js`) traités comme exemples project-specific ; `INSTRUCTIONS_DEMARRAGE_SESSION_UNIVERSEL` versionné v1.2 en parallèle. **v1.1** — affinages §1 (clause AD-5 phase prototype ; clause AD-10 « s'applique aux sorties LLM » ; AD-8 référence le squelette `cadrage-tools/` à câbler) ; substance des revues **fondue dans l'existant** plutôt qu'en nouveaux AD : §3 (diag-cleanup, résilience dépendances, rollback FTP), §4 (validation entrée, rate limiting, HTTPS), §5 (migrations non destructives), §7 (ADR) ; ajout §10 Gouvernance & évolution (seule section structurelle nouvelle). §1 maintenu à 10 AD. **v1.0** — socle initial AD-1→AD-10.*

# Registre des activités de traitement

> **Document obligatoire** — Article 30 du RGPD  
> **Responsable du traitement :** Renaud Heitz  
> **Date de création :** 13 février 2026  
> **Dernière mise à jour :** 28 mai 2026

---

## Informations générales

| Champ | Valeur |
|-------|--------|
| **Organisme** | Renaud Heitz |
| **Activité** | Coach, accompagnateur de transformations, préparateur mental et formateur |
| **Statut juridique** | Entrepreneur individuel — profession libérale non réglementée |
| **SIRET** | 799 008 313 00022 |
| **Adresse** | Bordeaux, Nouvelle-Aquitaine |
| **Email de contact** | renaud@focuscoach.fr |
| **DPO désigné** | Non (non obligatoire — art. 37, seuils non atteints) |
| **Personne référente données** | Renaud Heitz |

---

## Traitement n°1 : Gestion des demandes de rendez-vous

| Champ | Détail |
|-------|--------|
| **Nom du traitement** | Gestion des demandes de rendez-vous |
| **Responsable** | Renaud Heitz |
| **Finalité(s)** | Réception, traitement et suivi des demandes de rendez-vous coaching/consulting ; communication liée au RDV (confirmation, rappels, annulation, déplacement) ; bilan annuel d'activité |
| **Base légale** | Exécution de mesures précontractuelles / Exécution du contrat (art. 6.1.b RGPD) |
| **Catégories de personnes** | Prospects et clients (personnes physiques ayant soumis une demande de RDV) |
| **Catégories de données** | **Obligatoires :** nom complet, adresse email · **Optionnelles :** téléphone, organisation, type de service souhaité, objet du RDV, message libre |
| **Source des données** | Collecte directe auprès de la personne concernée (formulaire de réservation en ligne) |
| **Destinataires internes** | Renaud Heitz (responsable du traitement — seul accès) |
| **Sous-traitants** | OVH SAS (hébergement web et BDD — Roubaix, France) |
| **Transferts hors UE** | Aucun |
| **Durée de conservation — base active** | 365 jours après la date du rendez-vous |
| **Durée de conservation — archivage** | Suppression totale ou anonymisation irréversible à J+365 |
| **Durée de conservation — facturation** | 10 ans (obligation légale — Code de commerce art. L123-22) |
| **Mesures de sécurité techniques** | HTTPS/TLS sur l'ensemble du site ; paramétrage PDO avec requêtes préparées (prévention injection SQL) ; tokens de gestion uniques 64 caractères (SHA-256) ; sanitisation de toutes les entrées utilisateur ; protection des dossiers sensibles par .htaccess |
| **Mesures de sécurité organisationnelles** | Accès restreint à la base de données (un seul administrateur) ; identifiants BDD stockés dans fichier de configuration non accessible publiquement ; procédure de purge automatique (CRON) ; registre des traitements documenté |
| **Droits des personnes** | Accès (art. 15), rectification (art. 16), effacement (art. 17), limitation (art. 18), portabilité (art. 20), opposition (art. 21) — exercice par email ou via l'espace client (lien token) — délai de réponse : 1 mois maximum |

---

## Traitement n°2 : Journalisation technique (sécurité)

| Champ | Détail |
|-------|--------|
| **Nom du traitement** | Journalisation technique — Sécurité du site |
| **Responsable** | Renaud Heitz |
| **Finalité(s)** | Prévention des abus (soumissions frauduleuses, bots, spam) ; détection d'anomalies de sécurité ; résolution d'incidents techniques |
| **Base légale** | Intérêt légitime du responsable de traitement (art. 6.1.f RGPD) — cf. document « Test d'intérêt légitime » joint |
| **Catégories de personnes** | Visiteurs du site ayant soumis le formulaire de réservation |
| **Catégories de données** | Adresse IP, user agent (navigateur, OS) |
| **Source des données** | Collecte automatique lors de la soumission du formulaire |
| **Destinataires** | Renaud Heitz (seul accès) |
| **Sous-traitants** | OVH SAS (hébergement — France) |
| **Transferts hors UE** | Aucun |
| **Durée de conservation** | IP complète : 90 jours → troncature du dernier octet ; IP tronquée + user agent : 180 jours → suppression totale |
| **Mesures de sécurité** | Données stockées dans la même base que les réservations avec accès restreint ; purge automatique programmée ; données non exposées via l'API client (filtrées avant réponse) |
| **Droits des personnes** | Accès, rectification, effacement, opposition — exercice par email — délai : 1 mois. Droit d'opposition spécifique à l'intérêt légitime (art. 21.1) |

---

## Historique des mises à jour du registre

| Date | Modification |
|------|-------------|
| 13/02/2026 | Création initiale — 2 traitements documentés |

---

*Ce registre est tenu conformément à l'article 30 du RGPD et est mis à la disposition de la CNIL sur demande.*

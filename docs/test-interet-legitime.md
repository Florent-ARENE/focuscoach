# Test de mise en balance — Intérêt légitime

> **Document de conformité** — Article 6.1.f du RGPD  
> **Traitement concerné :** Journalisation technique (IP et user agent)  
> **Date de réalisation :** 13 février 2026  
> **Responsable :** Renaud Heitz

---

## 1. Identification de l'intérêt légitime

**Intérêt poursuivi :** Sécurité du site web et prévention des abus.

L'adresse IP et le user agent sont collectés lors de la soumission du formulaire de réservation afin de :

- Détecter et bloquer les soumissions automatisées (bots, spam)
- Identifier les tentatives de soumissions frauduleuses ou abusives (multiples réservations fictives)
- Permettre le diagnostic technique en cas d'incident
- Répondre à d'éventuelles obligations légales (réquisition judiciaire)

**Fondement juridique :** Les considérants 47 et 49 du RGPD mentionnent expressément la prévention de la fraude et la sécurité réseau comme des intérêts légitimes. L'arrêt Breyer (CJUE, C-582/14) a reconnu ce fondement pour un éditeur de site web.

---

## 2. Test de nécessité

**La collecte est-elle nécessaire pour atteindre l'objectif ?**

Oui. Sans l'adresse IP, il est impossible de distinguer un utilisateur légitime d'un bot ou d'une source de spam. Le user agent complète cette information en permettant d'identifier les robots automatisés (qui utilisent des signatures spécifiques).

**Existe-t-il un moyen moins intrusif d'atteindre le même objectif ?**

Les alternatives envisagées et leur évaluation :

| Alternative | Évaluation |
|-------------|-----------|
| CAPTCHA seul (sans log IP) | Ne permet pas le diagnostic a posteriori ni la détection de patterns d'abus |
| Honeypot seul | Moins fiable que la combinaison honeypot + log IP, contournable par des bots sophistiqués |
| Aucune collecte | Incompatible avec l'obligation de sécurité (art. 32 RGPD) et expose le site aux abus |

**Conclusion :** La collecte d'IP et de user agent est le moyen le moins intrusif permettant d'assurer un niveau de sécurité adéquat. Les données collectées sont limitées (deux champs techniques seulement).

---

## 3. Mise en balance avec les droits des personnes

### Intérêts et droits de la personne concernée

| Critère | Évaluation |
|---------|-----------|
| **Nature des données** | Faible sensibilité — données techniques, pas de données de catégories particulières (art. 9) |
| **Attente raisonnable** | L'utilisateur s'attend raisonnablement à ce qu'un site web collecte son IP — c'est un fonctionnement standard d'internet |
| **Impact sur la personne** | Minimal — l'IP seule ne permet pas d'identifier directement une personne (identification indirecte via le FAI) |
| **Relation avec la personne** | La personne est à l'initiative de la démarche (formulaire de réservation) |
| **Catégories de personnes** | Adultes (professionnels, pas de mineurs visés) |
| **Volume** | Faible volume (site d'un consultant indépendant, quelques dizaines de soumissions/mois) |

### Garanties mises en place pour limiter l'impact

| Garantie | Détail |
|----------|--------|
| **Durée de conservation réduite** | IP complète conservée 90 jours seulement, puis tronquée, puis supprimée à 180 jours |
| **Troncature progressive** | Après 3 mois, le dernier octet de l'IP est remplacé par 0 (ex : 192.168.1.45 → 192.168.1.0), réduisant le pouvoir d'identification |
| **Séparation des durées** | Les données techniques ont une durée de conservation distincte et plus courte que les données de contact |
| **Transparence** | La collecte, sa finalité, sa base légale et sa durée sont mentionnées dans la politique de confidentialité |
| **Non-exposition** | L'API client filtre l'IP et le user agent — ces données ne sont jamais renvoyées au client |
| **Droit d'opposition** | Les personnes peuvent exercer leur droit d'opposition spécifique à l'intérêt légitime (art. 21.1) |
| **Purge automatique** | Script CRON programmé pour appliquer automatiquement les durées de conservation |

---

## 4. Conclusion

**L'intérêt légitime du responsable de traitement (sécurité du site) prévaut** sur les droits et libertés des personnes concernées, compte tenu :

- De la faible sensibilité des données collectées (IP et user agent uniquement)
- De l'attente raisonnable des utilisateurs
- De l'impact minimal sur leur vie privée
- Des garanties mises en place (troncature, durée réduite, purge automatique, transparence)
- De l'impossibilité d'atteindre l'objectif de sécurité par un moyen moins intrusif

Le traitement est proportionné et conforme à l'article 6.1.f du RGPD.

---

*Ce test de mise en balance est conservé à titre de preuve de conformité (accountability, art. 24 RGPD) et sera mis à jour en cas d'évolution des pratiques.*

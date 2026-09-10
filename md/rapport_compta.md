# Refonte du volet comptable de DIAGO

**Projet :** DIAGO, progiciel de gestion destiné à la commercialisation, y compris à l'international
**Période :** 9 et 10 septembre 2026
**Auteur :** Razak
**Documents liés :** `Audit_code_comptabilité.md` (constat initial), `migration_psg.md`, `razak_audit.md`

---

## 1. Point de départ

Un audit du module commercial et comptable avait relevé onze défauts, dont trois critiques. Le
constat central tenait en une phrase : **le logiciel se comportait comme un outil de suivi de
trésorerie, pas comme un système comptable.**

Trois exemples de ce qui a été trouvé :

- Le taux de TVA de 18 % était écrit dans le code source. Le produit n'était vendable qu'en
  Côte d'Ivoire.
- La table des factures de vente ne stockait aucune TVA, alors que c'est elle qui est certifiée
  auprès de l'administration fiscale.
- Le régime déclaré au fisc était choisi selon que le montant de la facture était positif. Comme
  toute facture réelle l'est, un client exonéré était systématiquement déclaré comme taxable.

À cela s'ajoutait un point relevé pendant les travaux, que l'audit n'avait pas vu : **l'unicité
des références de document était globale à toute la base alors que la numérotation était propre
à chaque entreprise.** Concrètement, la deuxième entreprise cliente ne pouvait pas créer sa
première facture du jour. Pour un logiciel mutualisé, c'était bloquant en production.

---

## 2. Ce qui a été construit

Le travail a été mené en huit étapes, chacune validée avant de passer à la suivante.

### Étape 1 — Taux de TVA paramétrables

Le taux ne vient plus du code mais d'une table propre à chaque entreprise, avec libellé, valeur
à trois décimales, régime fiscal, date d'entrée en vigueur et taux par défaut. Un écran de
réglage permet d'ajouter, modifier et désactiver les taux.

Une entreprise reçoit automatiquement le barème de son pays : 18 % et 9 % en zone franc,
20 / 10 / 5,5 % en euros, 20 / 14 / 7 % au Maroc.

Trois choix structurants ont été faits ici :

- **La facture mémorise une copie figée du taux appliqué**, et pas seulement un lien vers le
  paramétrage. Sans cela, une réédition après un changement de taux afficherait un montant faux.
- **Un taux ne se supprime pas, il se désactive.** Les documents émis doivent rester lisibles.
- **Le régime prime sur la valeur.** Un taux marqué exonéré, export ou autoliquidation ne
  facture rien, même si une valeur est saisie.

### Étape 2 — La ventilation fiscale circule dans toute la chaîne

L'information de TVA existait sur le devis puis disparaissait. Elle se perdait dès la commande,
qui ne retenait qu'un total toutes taxes comprises.

Les commandes, factures de vente et ventes au comptoir portent désormais montant hors taxes,
montant de taxe, taux, régime et devise. Les montants existants n'ont pas bougé : la colonne
déjà présente reste le TTC.

Pour le point de vente, **la taxe est extraite du prix affiché, elle ne s'y ajoute pas.** En
caisse, le prix affiché est celui que le client paie ; ajouter la taxe aurait changé le montant
encaissé et faussé le rapprochement de caisse.

### Étape 3 — Ce qui est déclaré au fisc reflète la réalité

Le régime transmis vient maintenant du taux appliqué, transporté depuis le devis. La remise
réellement accordée est déclarée, alors qu'elle était envoyée à zéro.

Un second cas a été découvert : les factures fournisseurs souffraient du même travers, le régime
y étant déduit du montant de taxe extrait d'un PDF. Or un zéro peut signifier « exonéré » ou
« lecture ratée ». Un régime explicite a été ajouté, confirmé par un humain sur la fiche.

**Un document dont le régime est inconnu ne part plus au fisc.** La certification est bloquée
avec un message expliquant quoi corriger. Mieux vaut bloquer que déclarer une supposition.

### Étape 4 — Numérotation fiable

Le numéro était construit en comptant les documents existants et en ajoutant un. Un compteur
verrouillé le remplace, ce qui met les demandes simultanées en file.

L'effet est mesurable. Avec six utilisateurs créant vingt factures chacun :

| Méthode | Tentatives | Réussies | Collisions |
|---|---|---|---|
| Ancienne, par comptage | 120 | 45 | **75** |
| Nouvelle, par compteur | 120 | 120 | 0 |

L'unicité des références a par ailleurs été ramenée au périmètre de l'entreprise, ce qui débloque
le multi-entreprises.

### Étape 5 — Intégrité des documents et avoirs

Le logiciel n'avait aucune notion de facture « émise ». Une facture remise au client restait
modifiable, et supprimable tant qu'aucun paiement n'était enregistré. La notion d'avoir n'existait
pas.

Une facture se verrouille désormais dans trois cas : elle a été émise, elle porte un paiement, ou
elle est certifiée. Une fois verrouillée, la seule correction possible est l'avoir : motif
obligatoire, montant plafonné au reste à créditer, cumul suivi. **La facture d'origine n'est
jamais touchée**, les deux documents restent lisibles.

L'annulation d'une facture de vente, qui se contentait de changer son statut et effaçait la trace
du montant, émet maintenant un avoir total.

### Étape 6 — Déclaration de TVA

Un écran calcule sur une période libre la taxe collectée, la taxe déductible et le solde à payer
ou le crédit reportable, ventilés par origine et par taux.

Trois règles y sont appliquées : un brouillon n'est jamais déclaré, les avoirs viennent en
diminution, les factures annulées sont exclues. Un bandeau signale les documents dont le régime
n'est pas renseigné, car ils faussent le total.

### Étape 7 — Comptabilité en partie double

La table d'écritures existante n'avait qu'un sens par ligne, sans rien reliant un débit à son
crédit. Aucune vérification d'équilibre n'était possible, et surtout **aucun code applicatif n'y
écrivait** : seul le jeu de démonstration l'alimentait.

Une pièce comptable et ses lignes ont été séparées, ce qui permet d'exiger l'équilibre. Un plan
comptable par entreprise, aligné sur le référentiel SYSCOHADA que le projet utilisait déjà, reste
configurable pour un usage hors zone OHADA.

Six événements alimentent automatiquement la comptabilité : facture de vente, facture
personnalisée, vente au comptoir, facture fournisseur, avoir et règlement client.

Deux décisions méritent d'être connues :

- **L'écriture d'une facture fournisseur est passée quand un humain confirme le régime**, pas à
  l'import. Des données lues dans un PDF ne suffisent pas à engager la comptabilité.
- **Si l'écriture échoue, l'opération commerciale continue.** Une comptabilité en retard se
  rattrape, une vente perdue non. L'erreur est journalisée.

### Étape 8 — Bilan financier annuel automatique

À la clôture d'un exercice, le dirigeant voit à sa connexion une fenêtre présentant le bilan et
le compte de résultat, avec un bouton de téléchargement. **S'il ferme sans télécharger, le bilan
reste signalé comme non lu** dans ses notifications. Le téléchargement du PDF fait disparaître le
signalement.

Le bilan est calculé une fois puis figé : une écriture passée en retard ne modifiera pas un
document déjà présenté. Aucune tâche planifiée n'est nécessaire, le calcul se fait au premier
affichage suivant la clôture.

### Étape 9 — Tests automatisés

Le projet n'avait que les deux tests d'exemple livrés avec le framework. Il en compte désormais
**69**, couvrant les sujets que l'audit désignait comme prioritaires.

| Domaine | Tests |
|---|---|
| Calcul de taxe | 10 |
| Intégrité des factures et avoirs | 10 |
| Paie | 10 |
| TVA sur les encaissements | 8 |
| Déclaration de TVA | 8 |
| Partie double | 7 |
| Stock | 6 |
| Isolation multi-entreprises | 5 |
| Numérotation | 5 |

Ils tournent sur PostgreSQL dans un schéma séparé, et non sur une base allégée : plusieurs
migrations utilisent du SQL propre à PostgreSQL, et un autre moteur masquerait des erreurs qui se
produiraient en production. La suite complète s'exécute en moins de trois secondes.

### Étape 10 — TVA sur les encaissements

Ce régime était incalculable : les factures ne gardaient qu'un montant payé cumulé, sans date par
règlement. Un journal des règlements a été créé, une ligne datée par encaissement.

Le fait générateur se règle par entreprise et peut être basculé ponctuellement depuis la
déclaration. Sous ce régime, un règlement partiel ne rend exigible que sa fraction : une facture
de 118 000 dont 59 000 sont réglés rend exigible 9 000 de taxe, contre 18 000 sur les débits.

---

## 3. Ce qui a été livré, en chiffres

| Élément | Quantité |
|---|---|
| Migrations de base de données | 12 |
| Services métier créés | 8 |
| Modèles créés | 7 |
| Écrans créés | 6 |
| Tests automatisés | 69 |
| Lignes de code de service | ~1 230 |
| Lignes de code de test | ~1 400 |

Les huit services sont : calcul de taxe, numérotation, intégrité des factures, journal comptable,
report des écritures, déclaration de TVA, bilan annuel, journal des règlements.

---

## 4. Comment cela a été vérifié

Aucune étape n'a été déclarée terminée sur la seule base d'une compilation réussie. Chacune a été
éprouvée par un parcours réel dans l'application, session ouverte avec un compte administrateur.

Le contrôle le plus parlant est le suivant : création d'un client, devis à 18 %, validation,
livraison, puis règlement de 60 000 en caisse, entièrement par l'interface. La comptabilité s'est
écrite seule, et de façon équilibrée :

| Pièce | Journal | Écriture |
|---|---|---|
| EC-2026-000001 | Ventes | Clients 118 000 au débit, Ventes 100 000 et TVA 18 000 au crédit |
| EC-2026-000002 | Caisse | Caisse 60 000 au débit, Clients 60 000 au crédit |

Un recoupement final confirme la cohérence entre les modules : la TVA collectée annoncée par la
déclaration et le solde du compte de TVA au journal donnent tous deux 18 000.

Pour la certification fiscale, la couche réseau a été interceptée plutôt qu'appelée : **aucune
donnée de test n'a été envoyée à l'administration fiscale.**

---

## 5. Défauts découverts en cours de route

Trois problèmes non signalés par l'audit ont été trouvés pendant les travaux.

**Collision de références entre entreprises.** L'unicité était globale alors que la numérotation
était par entreprise. La deuxième société cliente ne pouvait pas créer sa première facture du
jour. Corrigé.

**Régime déduit du montant sur les factures fournisseurs.** Même défaut que celui signalé pour les
ventes, mais sur un autre module. Corrigé.

**Champ de retenue de paie ignoré.** La génération d'un bulletin accepte un champ
`other_deductions` que le calcul ne lit jamais, car il cherche la clé `deductions`. Le formulaire
n'envoie ni l'un ni l'autre, donc rien n'est cassé aujourd'hui, mais un appel direct à cette route
verrait sa retenue disparaître en silence. **Le comportement réel a été figé dans un test et
commenté ; le calcul de paie n'a pas été modifié sans validation.**

Par ailleurs, une observation de portée plus large : les enregistrements créés hors requête HTTP
(commande en ligne de commande, tâche de fond) se retrouvent sans entreprise, le remplissage
automatique dépendant d'un utilisateur connecté. Ce point n'a pas été traité.

---

## 6. Ce qui reste ouvert

**Le taux de TVA par ligne.** Le taux est aujourd'hui au niveau du document. Une facture mêlant un
produit à taux normal et un service à taux réduit n'est pas représentable. C'est le seul écart
structurant qui subsiste, et il touchera des tables déjà remplies.

**La facturation en devise étrangère.** Chaque entreprise a sa devise, mais rien ne permet de
facturer un client dans une autre, ni de conserver le taux de change à la date de l'opération.

**Le champ de retenue de paie** signalé plus haut, qui attend une décision.

---

## 7. Recommandation

Un point de sécurité, sans rapport direct avec la comptabilité mais plus urgent que tout ce qui
précède : **le fichier de configuration contenant les clés d'accès est toujours suivi dans
l'historique du code**, sur deux commits, avec trois clés non vides. La clé de l'administration
fiscale, celle du prestataire de paiement et le mot de passe de messagerie doivent être révoqués
et l'historique purgé. Le détail figure dans `razak_audit.md`.

Sur le volet comptable lui-même, le socle est désormais solide et protégé par des tests. Le taux
par ligne peut être attaqué sereinement : c'est précisément ce que le filet de tests permet.

# Audit du code - Chaîne commerciale et comptable, traitement de la TVA

**Projet :** DIAGO (ERP destiné à la commercialisation, y compris à l'international)
**Date :** 9 septembre 2026
**Périmètre :** module Commercial (devis, proforma, commande, livraison, facture, point de
vente), module Comptabilité (caisse, banque, écritures, factures fournisseurs), et le
traitement de la TVA de bout en bout.
**Méthode :** lecture du code des deux contrôleurs concernés (3 800 lignes), des 12 modèles
commerciaux et comptables, des tables correspondantes et des vues d'impression.

---

## Synthèse

La chaîne commerciale est fonctionnelle sur le plan opérationnel : on peut créer un devis, le
transformer en commande, livrer, facturer et encaisser. La partie **fiscale et comptable, en
revanche, n'est pas aboutie**. Le logiciel se comporte aujourd'hui comme un outil de suivi de
trésorerie, pas comme un système comptable.

Trois constats structurants :

1. La TVA est **calculée à l'affichage mais jamais suivie comptablement**. Aucun état de TVA
   ne peut être produit.
2. Le taux de 18 % est **codé en dur** dans le code source, ce qui bloque toute vente hors
   Côte d'Ivoire.
3. Le grand livre existe en base mais **n'est alimenté par rien**.

Ces points sont bloquants pour une commercialisation internationale.

---

## 1. Ce qui devrait être fait

Référentiel attendu d'un ERP vendu dans plusieurs pays.

### 1.1 Paramétrage fiscal, jamais de valeur codée en dur

Chaque entreprise cliente doit disposer de ses propres taux, gérés depuis l'interface :

- **Plusieurs taux simultanés.** La Côte d'Ivoire applique 18 % et 9 %. La France applique
  20 %, 10 %, 5,5 % et 2,1 %. Le Maroc applique 20 %, 14 %, 10 % et 7 %. Un taux unique ne
  suffit dans aucun de ces pays.
- **Un taux par ligne de document**, pas un taux global. Une même facture peut mêler un produit
  à taux normal et un service à taux réduit.
- **Une date d'effet par taux.** Les taux changent par décision politique. Une facture émise
  avant un changement doit conserver le taux en vigueur à sa date d'émission, y compris si elle
  est rééditée des années plus tard.
- **Un régime de taxation par article et par client** : taux normal, taux réduit, exonéré,
  export, autoliquidation.

### 1.2 Suivi comptable de la TVA

- Distinction entre **TVA collectée** (sur les ventes) et **TVA déductible** (sur les achats).
- Un **état de déclaration** par période : total collecté, total déductible, solde à payer ou
  crédit reporté.
- Le rattachement de chaque montant de TVA à un **compte comptable** dédié.
- Le choix du **fait générateur** : TVA sur les débits (à la facturation) ou sur les
  encaissements. Ce choix varie selon le pays et selon la nature de l'activité.

### 1.3 Comptabilité en partie double

Toute opération doit produire des écritures équilibrées. Une vente de 100 hors taxes à 18 %
génère trois lignes : client débité de 118, vente créditée de 100, TVA collectée créditée de 18.
La somme des débits doit égaler la somme des crédits. Sans cela, aucun bilan ni compte de
résultat n'est possible.

### 1.4 Intégrité des documents fiscaux

- **Numérotation continue, sans trou et sans doublon**, garantie même si plusieurs utilisateurs
  facturent en même temps.
- **Immuabilité** : une facture émise ne se modifie pas et ne se supprime pas. On l'annule par
  un avoir.
- Conservation de la **piste d'audit** : qui a fait quoi et quand.

### 1.5 Multidevise

- Une devise par entreprise, et la possibilité de facturer dans une autre devise.
- Le **nombre de décimales dépend de la devise** : le franc CFA n'en a aucune, l'euro en a deux,
  le dinar koweïtien en a trois. Une règle unique produit des écarts.
- Conservation du **taux de change à la date de l'opération**.

### 1.6 Règles d'arrondi explicites

L'arrondi doit être décidé et appliqué au même endroit pour tous les documents. Un calcul en
virgule flottante non arrondi produit des écarts de quelques centimes qui font échouer les
rapprochements comptables.

---

## 2. Ce qui n'est pas fait

Classé du plus grave au moins grave.

### 2.1 Le taux de TVA est codé en dur - CRITIQUE

Dans `CommercialController.php`, **lignes 425 et 541**, la TVA des factures personnalisées est
calculée ainsi :

```php
$vat = $netAfterDiscount * 0.18;
```

Le taux ivoirien est écrit dans le code source. Aucun réglage ne permet de le changer.

Sur le formulaire de devis, la liste déroulante ne propose que deux choix figés : « Aucune taxe
(0 %) » et « Appliquer la TVA (18 %) ». Un client français, marocain ou sénégalais ne peut pas
utiliser le produit.

**Conséquence :** l'ERP n'est vendable que sur un seul marché.

### 2.2 La table des factures de vente ne stocke aucune TVA - CRITIQUE

La table `commercial_invoices` ne contient que trois colonnes de montant : `amount`,
`paid_amount`, et rien d'autre. Pas de montant hors taxes, pas de TVA, pas de taux.

C'est pourtant **cette table qui est certifiée auprès de l'administration fiscale**. Une facture
de vente est donc enregistrée comme un montant unique dont on ne sait pas s'il est hors taxes ou
toutes taxes comprises, ni quelle TVA il contient.

Le point de vente présente le même défaut : la table `pos_sales` ne stocke qu'un `total`, sans
aucune ventilation.

À l'inverse, les devis et proformas, eux, stockent bien le détail. **L'information fiscale est
donc perdue au moment précis où le document devient une facture.**

### 2.3 Le régime de taxation déclaré au fisc est déterminé par une règle absurde  CRITIQUE

`CommercialController.php`, **ligne 1651**, au moment de transmettre la facture à la plateforme
de certification fiscale :

```php
'taxes' => [(float) $invoice->amount > 0 ? 'TVA' : 'TVAE'],
```

Le code choisit entre le régime taxable et le régime exonéré **selon que le montant de la
facture est positif ou non**. Comme toute facture réelle a un montant positif, le régime
« exonéré » n'est jamais atteint.

Une exonération dépend du statut du client ou de la nature du bien vendu, jamais du montant.
**Un client légitimement exonéré est donc déclaré comme taxable à l'administration fiscale.**

Sur la même transmission, la remise est envoyée en dur à zéro (`'discount' => 0`), alors que
l'application gère les remises. Le montant déclaré peut donc différer du montant facturé.

### 2.4 Le grand livre n'est alimenté par rien - MAJEUR

La table `account_entries` existe et le tableau de bord affiche des totaux de débits et de
crédits. Mais la recherche sur l'ensemble du projet ne révèle qu'**une seule écriture dans cette
table, et elle provient du jeu de données de démonstration**. Aucun code applicatif n'y écrit
jamais. Le module commercial ne la référence pas une seule fois.

Autrement dit, les chiffres de débits et de crédits affichés sur le tableau de bord comptable
proviennent exclusivement de données de démonstration et ne reflètent aucune activité réelle.

Par ailleurs, sa structure ne permet pas la partie double : chaque ligne porte un sens unique,
débit ou crédit, sans identifiant de regroupement ni de journal. Rien ne relie un débit à son
crédit, et rien ne vérifie l'équilibre.

Ce qui est réellement alimenté par les ventes, ce sont les mouvements de caisse et les
transactions bancaires, c'est-à-dire de la **trésorerie**, pas de la comptabilité.

**Conséquence :** ni bilan, ni compte de résultat, ni balance, ni grand livre.

### 2.5 Aucun état de TVA - MAJEUR

Aucune notion de TVA collectée ni de TVA déductible n'existe dans le code. Aucun écran, aucun
export, aucune requête ne permet de produire une déclaration périodique.

Les factures fournisseurs stockent pourtant un montant de taxe, qui constituerait la TVA
déductible. Cette donnée est enregistrée puis inutilisée.

### 2.6 Numérotation des factures exposée aux doublons - MAJEUR

`CommercialController.php`, **lignes 432 et 600** :

```php
$reference = 'FC-' . now()->format('Ymd') . '-' . str_pad((CustomInvoice::whereDate(...)->count() + 1), 4, '0', ...);
```

Le numéro est construit en comptant les factures du jour et en ajoutant un. Si deux
utilisateurs valident une facture au même instant, ils obtiennent le même numéro. Aucune
contrainte d'unicité en base ne l'empêche.

Une numérotation en double sur des documents fiscaux est un défaut de conformité dans toutes les
juridictions concernées.

### 2.7 Les factures restent modifiables et supprimables - MAJEUR

La modification d'une facture personnalisée ne vérifie pas si elle a déjà été certifiée
fiscalement. La suppression n'est bloquée que si un paiement a été enregistré. Une facture
émise, transmise au fisc mais impayée peut donc être supprimée sans laisser de trace.

La notion d'avoir n'existe pas dans le code.

### 2.8 La devise est codée en dur - MAJEUR pour l'international

Il existe bien un réglage de devise et de symbole par entreprise. Mais il n'est **jamais
utilisé** : le symbole n'apparaît que dans le formulaire de réglages lui-même.

| Constat | Occurrences |
|---|---|
| « FCFA » écrit en dur dans les vues | 84 |
| « XOF » écrit en dur dans le code | 14 |
| Utilisation réelle du réglage de devise | 0 |

Le nombre de décimales est également figé à deux partout, alors que le franc CFA n'en utilise
aucune. Toutes les impressions afficheront « FCFA » quel que soit le pays du client.

### 2.9 Aucun arrondi explicite dans les calculs financiers - MOYEN

Les calculs de TVA et de remise sont effectués en virgule flottante sans aucun arrondi. Les
seuls appels d'arrondi du code servent à dimensionner des graphiques.

Sur une facture à plusieurs lignes, la somme des lignes et le total calculé globalement peuvent
différer de quelques centimes, ce qui bloque les rapprochements.

### 2.10 Un catalogue de prix métier codé en dur - MOYEN

Aux lignes 405 à 415, la facture personnalisée contient un catalogue de services d'édition avec
leurs tarifs en dur : mise en page 50 000, conception de couverture 30 000, dépôt légal 25 000.
Ces valeurs appartiennent à un client précis et n'ont pas leur place dans le code d'un produit
destiné à être revendu.

### 2.11 Incohérences de nommage et de précision - MINEUR

Le montant de TVA s'appelle `tax_amount` sur les devis et proformas, mais `vat_amount` sur les
factures personnalisées. Les colonnes monétaires utilisent tantôt une précision de 15 chiffres,
tantôt 12, sans logique apparente. Ces écarts compliquent toute reprise ultérieure.

---

## 3. Deux suggestions

### Suggestion 1 - Introduire un moteur de taxes paramétrable et le rendre obligatoire

**Le problème traité :** les points 2.1, 2.2, 2.3, 2.8 et 2.9 ont tous la même cause. Le calcul
de la taxe est éparpillé dans les contrôleurs, réécrit à chaque endroit, avec des valeurs figées.

**Ce qu'il faut construire :**

Une table de taux de taxe rattachée à l'entreprise, portant un libellé, une valeur, une date
d'effet et un indicateur de taux par défaut. Chaque ligne de document référence le taux
appliqué, et le document conserve une copie figée de la valeur du taux au moment de son
émission.

Un service unique, appelé par tous les modules sans exception, qui reçoit les lignes, la remise,
le régime du client et la devise, et retourne le détail hors taxes, la ventilation par taux, et
le total toutes taxes comprises, arrondi selon les décimales de la devise.

Les tables `commercial_invoices` et `pos_sales` doivent recevoir les colonnes manquantes :
montant hors taxes, ventilation de taxe, montant toutes taxes comprises et devise.

Enfin, le régime transmis au fisc doit être déterminé par le statut fiscal du client et la
nature de l'article, jamais par le montant.

**Pourquoi en priorité :** sans cela le produit ne peut être vendu que dans un seul pays, et les
factures déjà émises ne contiennent pas l'information fiscale qu'elles devraient contenir. C'est
le point qui conditionne tous les autres.

**Effort estimé :** deux à trois semaines, dont une reprise des données existantes.

### Suggestion 2 - Construire un vrai journal comptable en partie double, alimenté automatiquement

**Le problème traité :** les points 2.4, 2.5, 2.6 et 2.7.

**Ce qu'il faut construire :**

Remplacer la table actuelle par deux niveaux : une pièce comptable, qui porte la date, le
journal, la référence et la source, et ses lignes, qui portent chacune un compte, un sens et un
montant. Une vérification refuse l'enregistrement d'une pièce dont les débits et les crédits ne
s'équilibrent pas.

Chaque événement commercial déclenche automatiquement sa pièce : la facturation crée le débit
client, le crédit de vente et le crédit de TVA collectée. L'encaissement solde le client. La
facture fournisseur crée la TVA déductible.

Une fois ce journal en place, l'état de TVA devient une simple somme sur les comptes de taxe
pour une période donnée, et le bilan comme le compte de résultat deviennent accessibles.

Ce chantier est l'occasion de corriger la numérotation, en la confiant à une séquence en base
plutôt qu'à un comptage, et d'interdire la modification d'un document déjà comptabilisé en
introduisant l'avoir.

**Pourquoi ensuite :** ce module dépend des montants de taxe produits par la suggestion 1. Le
faire dans l'autre sens obligerait à le reprendre.

**Effort estimé :** trois à quatre semaines.

---

## 4. Recommandation finale

Aucun de ces deux chantiers ne peut être mené sans **tests automatisés**, aujourd'hui totalement
absents du projet. Il est indispensable de couvrir, avant toute modification, les cas suivants :
facture à taux multiples, facture avec remise, client exonéré, arrondi sur plusieurs lignes,
numérotation sous accès concurrent, et équilibre des écritures.

Sur un logiciel qui calcule de la TVA déclarée à des administrations fiscales, une régression
silencieuse expose directement les entreprises clientes à un redressement.

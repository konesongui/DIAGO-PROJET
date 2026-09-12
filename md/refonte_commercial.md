# Refonte de l'interface de DIAGO — Module Commercial (point d'étape)

**Projet :** DIAGO, progiciel de gestion multi-entreprises (Laravel 9, PostgreSQL)
**Date :** 11 septembre 2026
**Branche de travail :** `refonte_compta_complete`
**Maquette de référence :** `Interface UX-DIAGO.pdf` (12 pages, charte CME Expertises)
**Documents liés :** `compta_refonte_complete.md` (volet Comptabilité et documents imprimables),
`compt_refont_2.md` (point d'étape n° 2)

Ce rapport fait le point sur l'étape 5 de la refonte, le module **Commercial**, commencée après la
clôture du volet Comptabilité. Il résume aussi l'ensemble de la refonte à ce jour.

---

## 1. Résumé

- **Comptabilité terminée** : tous les écrans et les quatre documents imprimables (facture de vente,
  facture fournisseur, fiche FNE, bilan PDF) sont sous la charte. Détail dans
  `compta_refonte_complete.md`.
- **Commercial en cours** : dix écrans et documents refaits (clients, fournisseurs, services, devis,
  éditeur de devis, devis imprimé, livraisons, factures personnalisées et leur éditeur, plus la
  facture de vente déjà faite avec la Comptabilité).
- **Deux décisions fonctionnelles prises** : le prix du catalogue est un prix HT, calculé TTC en
  caisse ; la livraison partielle est supprimée.
- **Trente-quatre défauts existants** corrigés dans le module Commercial, dont plusieurs bloquants :
  aucun devis de services ne pouvait être livré, la suppression d'un devis validé effaçait ses
  factures, l'éditeur de facture personnalisée ne fonctionnait pas du tout (section 5).
- **124 tests automatisés, tous au vert** (94 à la clôture de la Comptabilité, 30 ajoutés depuis).
- **Données de démonstration** enrichies pour tester chaque écran à la main (section 7).

---

## 2. Avancement du plan de refonte

| # | Étape | Référence maquette | État |
|---|---|---|---|
| 0 à 3 | Emojis, fondations, layout, tableau de bord | p. 2, 12 | Fait |
| 4a à 4f | Comptabilité : tous les écrans | p. 5, 7, 8, 10 | Fait |
| 4g | Documents imprimables de la Comptabilité | p. 6 et charte | Fait |
| 5 | **Commercial** | p. 4, 5, 6, 9 | **En cours** (section 3) |
| 6 | RH | charte | À faire |
| 7 | Administration | charte | À faire |
| 8 | Succursales / Entreprises | p. 3 | À faire |
| 9 | Utilisateurs et Paramètres | p. 11 | À faire |
| 10 | Console super-admin | charte | À faire |
| 11 | Pages publiques : connexion, landing, démo | charte | À faire |

---

## 3. Écrans du module Commercial

| Écran | Vue | Maquette | État |
|---|---|---|---|
| Accueil Commercial | `commercial.blade.php` | — | Présentation d'origine conservée (cartes colorées) |
| Clients | `commercial-clients.blade.php` | p. 4 | **Fait** |
| Fournisseurs | `commercial-suppliers.blade.php` | — | **Fait** |
| Mes services | `commercial-services.blade.php` | p. 9 | **Fait** |
| Point de vente : saisie d'une vente | `commercial-pos-create.blade.php` | — | Prix TTC calculé (décision 4.1) ; écran à refaire |
| Devis : liste | `commercial-quotes.blade.php` | p. 5 | **Fait** |
| Devis : éditeur | `commercial-quote-create.blade.php` | p. 6 | **Fait** |
| Devis : impression | `commercial-quote-print.blade.php` | charte | **Fait** |
| Bons de livraison | `commercial-deliveries.blade.php` | — | **Fait** |
| Factures de ventes | `commercial-invoices.blade.php` | p. 5 | Fait (avec la Comptabilité) |
| Factures personnalisées : liste | `commercial-custom-invoice-list.blade.php` | p. 5 | **Fait** |
| Factures personnalisées : éditeur | `commercial-custom-invoice.blade.php` | p. 6 | **Fait, à valider** |
| Factures personnalisées : fiche | `commercial-custom-invoice-show.blade.php` | — | Prochaine étape |
| Factures personnalisées : impression | `commercial-custom-invoice-print.blade.php` | charte | À faire |
| Proformas (liste, éditeur, impression) | `commercial-proforma*.blade.php` | p. 5, 6 | À faire |
| Point de vente (liste, fiche) | `commercial-pos*.blade.php` | — | À faire |
| Stock : entrées, sorties, état, inventaire | `commercial-stock-*.blade.php`, `commercial-inventory.blade.php` | — | À faire |
| Objectifs commerciaux | `commercial-objectives.blade.php` | — | À faire |
| Tableau commercial | `commercial-tableau.blade.php` | — | À faire |

---

## 4. Décisions fonctionnelles

### 4.1 Prix du catalogue : HT, calculé TTC en caisse

**Constat.** Un même prix de service était compté HT sur les devis (la TVA s'y ajoute) et TTC au point
de vente (la TVA en est extraite) : un service à 100 000 FCFA revenait à 118 000 FCFA sur un devis et
à 100 000 FCFA en caisse.

**Décision : prix HT unique.** Le catalogue affiche « Prix HT ». Au point de vente, choisir un service
propose son **prix TTC** au taux choisi (100 000 HT → 118 000 à 18 %, 109 000 à 9 %). Changer de
taux recalcule les prix repris du catalogue, pas ceux saisis à la main. Le serveur n'a pas changé :
il extrait la taxe du prix payé, et le HT enregistré retombe exactement sur le prix du catalogue.

### 4.2 Livraison partielle supprimée

**Constat.** Une commande n'avait qu'une seule livraison, reprenant toutes les lignes en quantité
complète. La valider « partielle » sortait tout le stock des articles, sans créer de facture ni de
livraison pour le reste : la commande ne pouvait plus jamais être facturée.

**Décision : une livraison est toujours complète.** Le serveur refuse le type « partielle ». Les
anciennes livraisons partielles, déjà sorties du stock, affichent un bouton **« Facturer »** qui crée
la facture sans ressortir le stock.

---

## 5. Ce qui a été fait, écran par écran

Tous les écrans suivent le même modèle, validé sur la Comptabilité : bouton retour, quatre
indicateurs colorés, carte de tableau avec recherche (et onglets quand il y a des états), menu
d'actions, fenêtre unique de saisie à en-tête marine qui se rouvre avec la saisie conservée en cas
d'erreur, état vide soigné, montants dans la devise de l'entreprise, tableau qui tient sans
défilement en 1366 px.

### Clients (page 4)

- Indicateurs : clients, achats cumulés, solde impayé (et nombre de débiteurs), NCC manquant
  (factures certifiées FNE en B2C).
- Tableau : client et NCC, contact, achats cumulés, solde impayé, dernier achat, bouton **« Voir »**.
- **Fiche client** en fenêtre : coordonnées, cumuls et factures de ventes (statut, reste dû,
  impression).
- Cumuls : factures de ventes non annulées (rattachées par le devis d'origine) et ventes comptoir.

Défauts corrigés : on ne pouvait pas modifier un client sans remplir **tous** les champs
(`required=""` sur chaque champ) ; fenêtre de modification sans bouton « Annuler » ; fenêtres placées
dans le tableau (HTML invalide).

Écarts assumés : pas de colonne « Catégorie » (VIP, Grands comptes), la donnée n'existe pas ; les
factures personnalisées ne sont pas cumulées, faute d'identifiant client fiable.

### Fournisseurs

- Indicateurs : fournisseurs, achats cumulés, factures rattachées, factures « hors carnet ».
- Factures fournisseurs rattachées **par NCC exact** (espaces et majuscules ignorés), jamais par le
  nom ; fiche fournisseur avec lien vers chaque facture en Comptabilité.

Défauts corrigés : fenêtre de modification sans fermeture, fenêtres dans le tableau, saisie perdue en
cas d'erreur. Écart assumé : pas de solde fournisseur, les factures fournisseurs n'ont pas de suivi de
paiement.

### Mes services (page 9)

- Indicateurs : services, actifs, inactifs, forfaits ; colonnes code, service, unité, prix HT, forfait,
  disponibilité.

Défauts corrigés : **les forfaits de la facture personnalisée étaient ingérables** (aucun champ pour
les proposer) ; une case le permet maintenant, avec un code généré à la première activation puis figé ;
devise « XOF » écrite en dur.

Écart assumé : la maquette mélange produits et services ; dans DIAGO, les produits relèvent du stock.

### Devis : liste (page 5)

- Indicateurs : devis, en attente (dont expirés), validés (taux de transformation), expirés.
- Onglets Tous, En attente, Validés, **Expirés** (en attente, date limite dépassée).
- Validation du devis dans une fenêtre qui demande le bon de commande du client.

Défauts corrigés :
- **Supprimer un devis validé effaçait en cascade sa commande, ses livraisons et ses factures**, y
  compris payées, journalisées ou certifiées FNE. Le serveur refuse désormais.
- « Modifier » proposé pour un devis validé (erreur 422).
- « Dupliquer » recopiait la date d'origine : la copie naissait expirée.
- Bouton en double, recherche en anglais, devise en dur.

### Devis : éditeur (page 6)

- Cartes « Informations du devis », « Lignes du devis » (type, désignation, unité, quantité, prix HT,
  total de ligne) et encadré des totaux (HT, remise, net HT, TVA, TTC).

Défauts corrigés :
- **Un devis contenant un service ne pouvait jamais être livré** : chaque ligne devait exister en
  stock. Le type de ligne est maintenant conservé et les services ne passent plus par le stock.
- Toute la saisie perdue en cas d'erreur.
- Unité des lignes jamais enregistrée.

### Devis imprimé

- Même mise en page que la facture : client, références, lignes, Total HT brut, remise, **Total HT
  net**, TVA, Total TTC, conditions, encadré **« Bon pour accord »** tant que le devis n'est pas validé.

Défauts corrigés : totaux sans HT net (la TVA ne se recoupait pas) ; NIF toujours « - » ; taux
affiché brut (« 18.00% »).

### Bons de livraison

- Indicateurs : livraisons, à livrer, facturées, articles à sortir (ou livraisons partielles à
  terminer s'il en reste).
- Fenêtre de validation listant chaque ligne et son type (service, ou article qui sort du stock).
- Lien direct vers la facture créée.

Défauts corrigés : « Facture créée » affiché même sans facture ; un article manquant en stock
interrompait la page par une erreur 422 brute (message lisible désormais) ; titre « Bons de
livraisons ».

### Factures personnalisées : liste

- Indicateurs : montant facturé (net des avoirs), encaissé, reste à encaisser, brouillons.
- État déduit des montants et des dates : brouillon, émise impayée, partielle, payée, annulée par
  avoir.
- Menu selon l'état : émettre, enregistrer un paiement, modifier et supprimer tant que la facture
  n'est pas verrouillée.

Défauts corrigés :
- **Un paiement « Banque » ne créditait aucun compte bancaire** alors que le journal enregistrait une
  entrée en banque.
- Statut qui ignorait l'émission et les avoirs ; reste à payer qui ignorait les avoirs ; paiement
  accepté sur une facture annulée ou pour 0.
- Une requête par facture pour lister les caisses ; recherche en anglais ; devise en dur.

### Factures personnalisées : éditeur (page 6) — à valider

- Cartes « Client et dates », « Conditions », « Lignes de la facture » (caractéristiques d'impression
  repliables), « Forfaits du catalogue », « Paiement reçu à la création » et totaux avec choix du
  taux de TVA.

Défauts corrigés :
- **Le script de la page ne s'exécutait pas** (erreur de syntaxe) : aucun total, « Ajouter un
  article » inopérant, champs « Nouveau client » jamais visibles.
- **Liste de clients inventée et écrite en dur** (« Boutique Kévin », « Média CI »…), et le client
  choisi n'était pas enregistré.
- **Seule la première ligne s'affichait en modification** : réenregistrer perdait les autres.
- **Le paiement saisi à la création n'entrait dans aucune trésorerie** ; il passe maintenant par la
  caisse ou la banque choisie, avec règlement daté et écriture au journal.
- Deux champs `payment_method` concurrents ; TVA figée à 18 % à l'écran ; remise en montant relue
  comme un pourcentage ; erreurs de saisie jamais affichées.

---

## 6. Tests et vérifications

**124 tests, tous au vert.** Ajoutés depuis la clôture de la Comptabilité :

| Fichier | Tests | Ce qui est protégé |
|---|---|---|
| `ClientsPageTest` | 3 | Cumuls (hors annulées, ventes comptoir comprises), champs facultatifs, cloisonnement |
| `SuppliersPageTest` | 2 | Rattachement par NCC, jamais par le nom ; cloisonnement |
| `ServicesPageTest` | 4 | Code des forfaits, forfait sur la facture personnalisée, devise, aller-retour HT/TTC en caisse |
| `QuotesPageTest` | 4 | États et expiration, devis validé ni supprimable ni modifiable, duplication datée du jour |
| `QuoteEditorTest` | 4 | Type et unité conservés, saisie reprise, livraison d'un devis de services sans stock |
| `DeliveriesPageTest` | 4 | État réel, refus lisible, livraison partielle retirée, ancienne partielle terminée sans stock |
| `CustomInvoiceListTest` | 4 | États, paiement bancaire encaissé, reste net des avoirs, actions verrouillées |
| `CustomInvoiceEditorTest` | 4 | Vrais clients, toutes les lignes, taux choisi, paiement immédiat encaissé |
| `InvoicePrintTest` | +1 | Devis imprimé : totaux, « Bon pour accord », statuts |

Chaque écran a aussi été rendu dans un navigateur sans interface avec des données fictives
(1366 px, 1440 px, 420 px), avec mesure de la largeur des tableaux, exécution des filtres, des
onglets et des calculs, et export PDF pour les documents imprimables.

---

## 7. Données de démonstration

`database/seeders/DemoComptaSeeder.php` (connexion `admin@diagoma.local`) crée désormais aussi :

- **5 fournisseurs** dont les NCC correspondent aux factures fournisseurs importées ;
- **7 services**, dont deux forfaits (*Rédaction des statuts*, *Domiciliation annuelle*) et un inactif ;
- **2 devis en attente**, dont un expiré ;
- **1 livraison à valider** (*Société Générale CI*, BC-2026-131, services uniquement) ;
- **4 factures personnalisées** : brouillon, émise impayée, partiellement payée par banque, payée en
  espèces ;
- des lignes de devis typées « service ».

```bash
php artisan db:seed --class=DemoComptaSeeder              # crée ou recrée les données
DEMO_PURGE=1 php artisan db:seed --class=DemoComptaSeeder # retire les données
```

---

## 8. Ce qui reste à faire

### Module Commercial

1. Fiche de la facture personnalisée (émission, avoir, paiements), puis son impression.
2. Proformas : liste, éditeur, impression.
3. Point de vente : liste, saisie (déjà corrigée pour le prix TTC), fiche.
4. Stock : entrées, sorties, état, inventaire.
5. Objectifs commerciaux et tableau commercial.

### Points ouverts

- **E-mails « Envoyer au client »** (factures et devis) : simple texte, « XOF » écrit en dur, sans le
  document joint.
- **Écran du point de vente** : pas encore sous la charte (« XOF » en dur notamment).
- **Paiements de factures personnalisées non émises** : ils sont passés au journal comme les autres
  règlements, alors que la facture n'y figure qu'à son émission. À confirmer avec l'expert-comptable.
- **Retouches de ponctuation non faites par moi** dans `DashboardController.php`,
  `commercial-quote-print.blade.php` (avant sa refonte) et des bundles JS du thème (tiret long
  remplacé par un tiret simple) : laissées telles quelles.

### Code non commité

Depuis le commit `97e1345`, ne sont pas encore commités : les documents imprimables de la
Comptabilité (4g), l'annulation avec motif, tout le travail sur le module Commercial, le seeder de
démonstration, les nouveaux fichiers de tests et les rapports `compta_refonte_complete.md` et
`refonte_commercial.md`.

---

## 9. Repères techniques ajoutés

- **Blade découpe les arguments de `@json` sur les virgules** : préparer la valeur dans un bloc
  `@php` avant de l'écrire (`@json($clientData)`), jamais `@json($client->only([...]))`.
- **Documents imprimables** : mise en page commune `print/layout`, vues partielles `print/item-lines`
  (lignes d'un document de vente), `print/sale-body`, `print/supplier-body`.
- **Fenêtre unique** remplie depuis les attributs `data-*` du bouton, rouverte après une erreur
  grâce à un champ caché (`client_id`, `validate_quote_id`, `payment_invoice_id`…).
- **Refus métier lisibles** : lever une `ValidationException` plutôt qu'`abort(422)`, pour rester sur
  l'écran avec le message.
- **Encaissement d'une facture personnalisée** : une seule fonction, `collectCustomInvoicePayment`
  (caisse ou banque, règlement daté, écriture au journal), utilisée par l'éditeur et la liste.

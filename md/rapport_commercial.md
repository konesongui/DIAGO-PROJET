# Refonte de l'interface de DIAGO — Module Commercial (suite et fin du volet ventes et stock)

**Projet :** DIAGO, progiciel de gestion multi-entreprises (Laravel 9, PostgreSQL)
**Date :** 11 septembre 2026
**Branche de travail :** `refonte_compta_complete`
**Maquette de référence :** `Interface UX-DIAGO.pdf` (12 pages, charte CME Expertises)
**Documents liés :** `refonte_commercial.md` (point d'étape précédent du module Commercial),
`compta_refonte_complete.md` (volet Comptabilité et documents imprimables)

Ce rapport reprend là où `refonte_commercial.md` s'arrêtait (fiche de la facture personnalisée) et
couvre tout ce qui a été fait depuis : factures personnalisées, proformas, point de vente et stock.
Chaque écran a été validé avant de passer au suivant.

---

## 1. Résumé

- **Seize écrans et documents refaits** depuis le dernier rapport : fiche et impression de la
  facture personnalisée, proformas (liste, éditeur, impression), point de vente (liste, saisie,
  fiche), stock (entrées et leur saisie, sorties et leur saisie, état du stock, inventaire).
- **Sept décisions prises** avec toi (section 4), dont trois qui changent le fonctionnement :
  indicateurs de la caisse réservés à l'encadrement, fournisseur obligatoire à chaque réception,
  inventaire qui corrige le stock.
- **Défauts graves corrigés**, entre autres :
  - aucune proforma ne pouvait être enregistrée ;
  - une vente au comptoir annulée restait au journal comptable ;
  - une modification de vente ne touchait ni la TVA ni le journal ;
  - l'inventaire ne corrigeait jamais le stock ;
  - des livraisons étaient refusées à tort pour « stock insuffisant ».

  Détail en section 5.
- **Traçabilité du stock** : chaque produit est suivi du fournisseur qui l'a livré jusqu'à sa sortie
  (livraison à un client, casse, usage interne, écart d'inventaire…).
- **168 tests automatisés, tous au vert** (124 au dernier rapport, 44 ajoutés).
- **Cinq migrations** ajoutées (section 7), déjà passées sur la base locale.

---

## 2. Avancement du plan de refonte

| # | Étape | Référence maquette | État |
|---|---|---|---|
| 0 à 3 | Emojis, fondations, layout, tableau de bord | p. 2, 12 | Fait |
| 4a à 4g | Comptabilité : écrans et documents imprimables | p. 5, 6, 7, 8, 10 | Fait |
| 5 | **Commercial** | p. 4, 5, 6, 9 | **Presque terminé** : restent les objectifs et le tableau commercial |
| 6 | RH | charte | À faire |
| 7 | Administration | charte | À faire |
| 8 | Succursales / Entreprises | p. 3 | À faire |
| 9 | Utilisateurs et Paramètres | p. 11 | À faire |
| 10 | Console super-admin | charte | À faire |
| 11 | Pages publiques : connexion, landing, démo | charte | À faire |

---

## 3. Écrans du module Commercial

| Écran | Vue | État |
|---|---|---|
| Accueil Commercial | `commercial.blade.php` | Présentation d'origine conservée (cartes colorées) |
| Clients, fournisseurs, services | `commercial-clients`, `-suppliers`, `-services` | Fait (rapport précédent) |
| Devis : liste, éditeur, impression | `commercial-quotes`, `-quote-create`, `-quote-print` | Fait (rapport précédent) |
| Bons de livraison | `commercial-deliveries.blade.php` | Fait (rapport précédent) ; validation revue (section 5.4) |
| Factures de ventes | `commercial-invoices.blade.php` | Fait (avec la Comptabilité) |
| Factures personnalisées : liste, éditeur | `commercial-custom-invoice-list`, `-custom-invoice` | Fait (rapport précédent) |
| Factures personnalisées : fiche | `commercial-custom-invoice-show.blade.php` | **Fait** |
| Factures personnalisées : impression | `commercial-custom-invoice-print.blade.php` | **Fait** |
| Proformas : liste | `commercial-proformas.blade.php` | **Fait** |
| Proformas : éditeur | `commercial-proforma-create.blade.php` | **Fait** |
| Proformas : impression | `commercial-proforma-print.blade.php` | **Fait** |
| Point de vente : liste | `commercial-pos.blade.php` | **Fait** |
| Point de vente : saisie et modification | `commercial-pos-create.blade.php` | **Fait** |
| Point de vente : fiche d'une vente | `commercial-pos-show.blade.php` | **Fait** |
| Stock : entrées (liste) | `commercial-stock-entries.blade.php` | **Fait** |
| Stock : saisie d'une réception | `commercial-stock-entry-create.blade.php` | **Fait** |
| Stock : sorties (liste) | `commercial-stock-exits.blade.php` | **Fait** |
| Stock : saisie d'une sortie | `commercial-stock-exit-create.blade.php` | **Fait** |
| Stock : état du stock | `commercial-stock-status.blade.php` | **Fait** |
| Stock : inventaire | `commercial-inventory.blade.php` | **Fait** |
| Objectifs commerciaux | `commercial-objectives.blade.php` | À faire |
| Tableau commercial | `commercial-tableau.blade.php` | À faire |

---

## 4. Décisions prises

### 4.1 Indicateurs de la caisse réservés à l'encadrement

La caisse n'a pas à voir le chiffre d'affaires ni les encaissements cumulés. Sur la liste du point de
vente, les quatre indicateurs (chiffre d'affaires, espèces, banque, ventes annulées) ne s'affichent
que pour les rôles **super_admin, admin et manager** (nouvelle méthode `User::isManager()`). Un
caissier voit les ventes, sans ces chiffres. La fiche d'une vente ne montre ni indicateur ni
écriture comptable.

### 4.2 Les montants du stock restent visibles par tous

Question posée pour les écrans de stock : le stock est un écran de gestion. Quantités, prix
d'achat, valeurs, bénéfice et marge sont visibles par tous ceux qui ont accès au Commercial.

### 4.3 Chaque réception est rattachée à son fournisseur

À ta demande : « chaque produit entré en stock doit être suivi, on doit savoir quel fournisseur a
livré le produit ». Une réception correspond à la livraison d'un fournisseur, et tous ses articles
remontent à ce fournisseur. Le fournisseur est **obligatoire** pour toute nouvelle réception et doit
faire partie du carnet de l'entreprise. Son nom est recopié au moment de la réception : il reste
lisible si le fournisseur quitte le carnet. Le numéro du bon de livraison est facultatif. Les
réceptions saisies avant ce changement apparaissent « Non renseigné ».

### 4.4 L'inventaire validé corrige le stock

Question posée : un inventaire validé passe ses écarts au stock.
- **Manquant** : sortie « Écart d'inventaire », prise dans les lots les plus anciens d'abord et
  valorisée au prix d'achat de chaque lot.
- **Surplus** : réception « Régularisation d'inventaire », au dernier prix d'achat de l'article.

Chaque mouvement reste relié à son inventaire, et l'historique garde tous les comptages.

### 4.5 Le mode de paiement d'une vente au comptoir ne se modifie pas

L'argent est déjà dans une caisse ou sur un compte. En modification, le mode et la caisse sont
affichés en lecture seule ; pour les changer, on annule la vente et on la ressaisit.

### 4.6 Pas de ticket de caisse pour l'instant

Proposé puis écarté : aucune impression de ticket n'a été ajoutée.

### 4.7 Pas de traduction globale des messages de validation pour l'instant

Laravel n'a pas de fichier `lang/fr/validation.php` : ses messages standard sortent en anglais.
Tu as préféré ne pas l'ajouter maintenant. Les écrans refaits traduisent leurs propres messages,
formulaire par formulaire.

### Choix de présentation validés

- Filigrane « BROUILLON » sur une facture personnalisée imprimée non émise. Titre « FACTURE » plutôt
  que « Facture personnalisée ».
- Proforma imprimée : mention « Offre de prix : ce document ne vaut pas facture ». Encadré « Bon
  pour accord » masqué quand la proforma a expiré. Titre « PROFORMA ».
- Proformas : remise par ligne conservée. Actions groupées réduites à Imprimer et Supprimer : l'envoi
  groupé mêlait les clients vers une seule adresse.
- Point de vente : liste filtrée par période côté serveur (le mois en cours par défaut). Retour sur un
  écran de saisie vide après chaque vente, pour enchaîner. Suppression réservée aux ventes annulées.
- Stock : sortie par article, du lot le plus ancien au plus récent (premier entré, premier sorti).
  Motif obligatoire pour une sortie manuelle.

---

## 5. Ce qui a été fait, écran par écran

Tous les écrans suivent le modèle validé :
- bouton retour, indicateurs colorés (sauf la caisse), carte de tableau avec recherche et filtres ;
- fenêtres à en-tête coloré, rouvertes avec la saisie conservée en cas d'erreur ;
- état vide soigné, montants dans la devise de l'entreprise ;
- tableau qui tient sans défilement en 1366 et 1440 px.

### 5.1 Factures personnalisées

**Fiche.**
- Quatre indicateurs : TTC, encaissé, reste à payer avec l'échéance, avoirs.
- Bandeau d'état portant l'action qui convient : émettre un brouillon, ou émettre un avoir sur une
  facture émise.
- Cartes Client et Informations, puis les lignes avec les forfaits et les caractéristiques
  d'impression en clair.
- Totaux présentés comme en p. 6 de la maquette : HT, remise, HT net, TVA avec son taux, TTC,
  avoirs, déjà payé, bandeau « Reste à payer », et le trop-perçu éventuel.
- Paiements reçus (datés, caisse ou banque) et avoirs (référence, date, motif).
- La fenêtre de paiement est partagée avec la liste (`partials/custom-invoice-payment-modal`) ;
  la fenêtre d'avoir propose un avoir total ou partiel.

Défauts corrigés :
- **Une facture payée mais jamais émise ne pouvait plus être émise** : elle ne pouvait donc jamais
  aller au journal des ventes ni recevoir d'avoir.
- **Les forfaits manquaient dans les lignes** alors qu'ils sont comptés dans le total HT.
- **« Envoyer par e-mail » annonçait « envoyée » sans rien envoyer.**
- **Dupliquer une facture émise recopiait son émission, ses avoirs et sa date.**
- **Avoirs et règlements de ces factures enregistrés en XOF quelle que soit la devise** de
  l'entreprise, au journal compris. La table n'a pas de colonne devise ; c'est maintenant celle de
  l'entreprise, dans `InvoiceIntegrityService`, `PaymentRecorder` et `AccountingPoster`.
- Messages WhatsApp et e-mail avec « XOF » en dur et un état qui ignorait les avoirs.
- Mode de paiement affiché en code brut, TVA sans taux, messages d'erreur en anglais.

L'état de la facture et les libellés des caractéristiques d'impression sont définis une seule fois
dans le modèle `CustomInvoice`, et partagés par la liste, l'éditeur, la fiche et l'impression.

**Impression.**
- Mise en page commune des documents : en-tête d'entreprise, encadrés client et références.
- Lignes et forfaits avec leurs caractéristiques, puis HT brut, remise, HT net, TVA, TTC, avoirs,
  déjà payé et reste à payer.
- Notes (conditions, coordonnées bancaires, avoirs émis) et mentions légales.
- Filigrane « BROUILLON » tant que la facture n'est pas émise.
- Tient sur une page A4.

Défauts corrigés : forfaits absents des lignes, pas de HT net ni de taux, « CFA » en dur, ni en-tête
ni mentions légales, page HTML brute en Arial.

### 5.2 Proformas

**Liste.**
- Indicateurs : proformas, montant proposé, envoyées, expirées.
- Onglets Brouillons / Envoyées / Expirées.
- Envoi par e-mail dans une fenêtre, avec l'adresse du client proposée.
- Impression et suppression groupées.

Défauts corrigés :
- **Les proformas n'avaient aucun numéro** : migration, et numérotation « PRO » des proformas
  existantes.
- L'adresse e-mail n'était jamais proposée.
- E-mail avec le montant brut en « XOF ».
- Une proforma dépassée n'était jamais « expirée ».

**Éditeur.**
- Client existant ou nouveau client créé à la volée.
- Lignes avec type, catégorie, unité, quantité, prix unitaire HT et remise par ligne (en % ou en
  montant).
- Suggestions tirées du stock et du catalogue des services.
- TVA choisie parmi les taux paramétrés.

Défauts corrigés :
- **Aucune proforma ne pouvait être enregistrée** : le serveur exigeait un champ que le formulaire
  n'envoyait jamais.
- **Régime fiscal perdu** : un taux « Exonéré » devenait « sans taxe ».
- **Remises non plafonnées** : un HT net faux, voire négatif.
- Client manquant : page d'erreur 422 brute.
- Saisie perdue en cas d'erreur.

**Impression.**
- En-tête et mentions légales, client lu dans le carnet.
- Colonne « Remise » affichée seulement si une ligne en a une.
- TVA avec taux ou régime, mention « ne vaut pas facture », encadré « Bon pour accord ».
- Tient sur une page A4.

Le partiel commun des lignes (`print/item-lines`) accepte maintenant des caractéristiques, une
remise et un total par ligne, sans rien changer pour les autres documents. Le libellé de TVA est
partagé par un petit trait (`HasTaxLabel`).

### 5.3 Point de vente

**Liste.**
- Barre de période avec raccourcis : aujourd'hui, 7 jours, ce mois, mois dernier.
- Indicateurs réservés à l'encadrement (4.1).
- Onglets Terminées / Annulées, recherche par ticket, client ou article.
- Paiement affiché avec sa caisse ou son compte.
- Annulation dans une fenêtre qui dit combien ressort, et d'où.

Défauts corrigés :
- **Une vente annulée restait au journal** : chiffre d'affaires, TVA et encaissement faux.
- **Aucun numéro de ticket** : migration, et numérotation « POS » des ventes existantes.
- Annulation en un clic, sans confirmation.
- Actions menant à une erreur 422 : modifier ou annuler une vente annulée, supprimer une vente
  terminée.
- Seulement les 20 dernières ventes visibles.

**Saisie et modification.**
- Services du catalogue en boutons (un second clic augmente la quantité) et lignes libres.
- Total à payer en évidence, TVA incluse extraite.
- Mode de paiement en deux boutons (espèces ou banque).
- Montants rapides (exact, puis coupures rondes) et monnaie à rendre, en rouge « Il manque » si le
  montant reçu est insuffisant.
- Bandeau après chaque vente : ticket et monnaie à rendre.

Défauts corrigés :
- **Une modification ne touchait ni la TVA ni le journal.**
- Mode, caisse et taux proposés en modification mais ignorés par le serveur, qui exigeait même une
  caisse pour enregistrer.
- Montant insuffisant : page d'erreur 422.
- Saisie perdue en cas d'erreur.
- « Monnaie rendue » possible sur un paiement par banque.
- On ne pouvait vendre qu'un service du catalogue.

**Journal comptable d'une vente.** Toutes les écritures d'une vente lui sont rattachées. À chaque
modification ou annulation, seul l'écart entre le journal et l'état réel de la vente est passé
(`AccountingPoster::syncPosSale`, `LedgerService::netBySource`). Une vente modifiée puis annulée
ramène bien la caisse, les ventes et la TVA à zéro.

**Fiche d'une vente.**
- Actions selon l'état.
- Bandeau d'état, articles, HT, TVA et TTC (barré si la vente est annulée).
- Encaissement (mode, caisse, reçu, rendu) et client.
- Fenêtre d'annulation partagée avec la liste (`partials/pos-cancel-modal`).
- Supprimer depuis la fiche ramène à la liste (c'était une erreur 404).

### 5.4 Stock

**Service partagé `StockService`.** Chaque ligne de réception est un lot, rattaché à son
fournisseur. Le service calcule :
- les lots encore en stock, les articles disponibles et l'état complet par article ;
- la **répartition d'une sortie sur les lots, du plus ancien au plus récent**, chacun à son prix
  d'achat ;
- l'application d'un inventaire.

Les sorties manuelles, les livraisons, l'état du stock et l'inventaire l'utilisent tous.

**Entrées (liste et saisie).**
- Indicateurs : réceptions, valeur achetée, bénéfice attendu, articles et fournisseurs.
- Colonne fournisseur avec le bon de livraison, filtre par fournisseur.
- Fenêtre de détail : lignes, prix d'achat et de vente, marge.
- Saisie : fournisseur obligatoire (lien pour l'ajouter au carnet), bon de livraison, articles déjà
  reçus proposés avec leurs derniers prix, prix de vente calculé, totaux et marge.

Défauts corrigés :
- Articles d'une réception invisibles dans la liste.
- Tri par date de saisie au lieu de la date de réception.
- Saisie perdue en cas d'erreur.
- Aucun suivi du fournisseur.

**Sorties (liste et saisie).**
- Indicateurs : sorties, valeur, livré aux clients, pertes et casse.
- Destination de chaque sortie : livraison à un client, ou motif (vente hors devis, usage interne,
  casse, perte, retour fournisseur, don, autre, écart d'inventaire).
- Détail : lot d'origine et fournisseur de chaque quantité sortie.
- Saisie : motif obligatoire, articles regroupés avec leur disponible, valeur estimée par le même
  calcul que le serveur, alerte si deux lignes du même article dépassent le stock.

Défauts corrigés :
- **Sortie lot par lot** : impossible de sortir en une fois une quantité répartie sur deux
  réceptions.
- **Livraisons de commandes refusées à tort** (« stock insuffisant ») quand le stock total suffisait
  mais était réparti sur plusieurs réceptions.
- Refus en page d'erreur 422.
- Destination des sorties manuelles inconnue.

**État du stock.**
- Indicateurs : articles en stock, valeur du stock au prix d'achat de chaque lot, bénéfice
  potentiel, articles à réapprovisionner (en rupture, ou à 5 unités ou moins, le seuil des
  notifications).
- Onglets par état, filtre par fournisseur.
- Fiche article : lots (date, fournisseur, bon de livraison, reçu, restant, prix) et sorties
  (date, destination, quantité), une ligne par sortie.

Défauts corrigés :
- Quantités affichées comme des montants (« 50,000 FCFA »).
- Prix d'achat moyen incluant les lots déjà vendus : valeur du stock fausse.
- Bénéfice au format anglais, filtre par catégorie approximatif, aucun fournisseur.

**Inventaire.**
- Feuille de comptage : théorique, compté, écart et sa valeur en direct. Un champ vide signifie
  « non compté » ; un bouton marque tout conforme.
- Fenêtre de confirmation qui récapitule manquants et surplus.
- Correction du stock (4.4), historique des inventaires avec leur détail.

Défauts corrigés :
- **L'inventaire ne corrigeait jamais le stock.**
- **Le stock théorique était lu dans un champ caché du formulaire** : il est maintenant recalculé
  par le serveur.
- « Stock réel » prérempli avec un comptage périmé.
- Aucun historique.

---

## 6. Tests et vérifications

**168 tests, tous au vert.** Ajoutés depuis le dernier rapport :

| Fichier | Tests | Ce qui est protégé |
|---|---|---|
| `CustomInvoiceShowTest` | 6 | Forfaits et caractéristiques affichés ; émission d'une facture payée non émise ; paiements datés et avoirs ; devise de l'entreprise pour l'avoir ; duplication en brouillon vierge ; e-mail réellement envoyé |
| `InvoicePrintTest` | +4 | Facture personnalisée imprimée (brouillon, avoirs, reste à payer) ; proforma imprimée (remises par ligne, exonération, « Bon pour accord ») |
| `ProformasPageTest` | 4 | Numéro et état ; e-mail avec montant formaté ; numérotation à la création ; suppression groupée cloisonnée |
| `ProformaEditorTest` | 6 | Enregistrement possible ; régime exonéré conservé ; remises plafonnées ; nouveau client ; saisie reprise ; cloisonnement |
| `PosSalesPageTest` | 6 | Numéro de ticket et période ; annulation contrepassée au journal ; actions selon l'état ; cloisonnement ; indicateurs réservés à l'encadrement ; fiche d'une vente |
| `PosSaleEntryTest` | 4 | Montant insuffisant refusé lisiblement ; banque au montant exact ; modification qui met à jour la TVA et le journal, annulation qui les remet à zéro ; mode de paiement figé |
| `StockEntriesPageTest` | 4 | Détail et fournisseur de chaque réception ; fournisseur obligatoire et du carnet ; nom conservé si le fournisseur est supprimé ; cloisonnement |
| `StockExitsPageTest` | 4 | Sortie sur plusieurs lots avec fournisseur d'origine ; motif obligatoire et refus lisible ; livraison répartie sur plusieurs réceptions ; cloisonnement |
| `StockStatusPageTest` | 3 | Disponible, valeur et lots par article ; états faible et rupture ; cloisonnement |
| `InventoryPageTest` | 3 | Inventaire qui corrige le stock (manquants et surplus valorisés) ; inventaire conforme ; comptage vide refusé |

Une assertion de `ServicesPageTest` a été mise à jour : le prix HT du catalogue arrive maintenant à
l'écran sous la forme « 100000 » au lieu de « 100000.00 ».

Chaque écran a aussi été rendu dans un navigateur sans interface avec des données fictives :
- largeurs 1366, 1440 et 420 px, avec mesure de la largeur des tableaux ;
- exécution des filtres, des onglets et des calculs : totaux de la proforma identiques au serveur,
  répartition sur les lots, monnaie rendue, écarts d'inventaire ;
- export PDF des documents imprimables, chacun sur une page A4.

---

## 7. Données et structure

### Migrations ajoutées (déjà passées sur la base locale)

| Migration | Contenu |
|---|---|
| `2026_09_11_000001_add_reference_to_commercial_proformas_table` | Numéro « PRO » des proformas, existantes numérotées |
| `2026_09_11_000002_add_reference_to_pos_sales_table` | Numéro de ticket « POS », ventes existantes numérotées |
| `2026_09_11_000003_add_supplier_to_stock_entries_table` | Fournisseur, nom du fournisseur et bon de livraison d'une réception |
| `2026_09_11_000004_add_reason_to_stock_exits_table` | Motif et précision d'une sortie |
| `2026_09_11_000005_create_stock_inventories_table` | Inventaires ; lien vers l'inventaire sur les comptages, entrées et sorties |

### Nouveaux fichiers

- `app/Services/StockService.php` : lots, articles, état du stock, répartition des sorties,
  application d'un inventaire.
- `app/Models/StockInventory.php`, `app/Models/Concerns/HasTaxLabel.php`.
- `resources/views/admin/partials/custom-invoice-payment-modal.blade.php` et `pos-cancel-modal.blade.php`.

### Données de démonstration

`database/seeders/DemoComptaSeeder.php` (connexion `admin@diagoma.local`) crée désormais aussi :
- une facture personnalisée avec caractéristiques d'impression, une avec avoir, une partielle en
  deux règlements et échue ;
- **4 proformas** : brouillon, envoyée, expirée, sans date limite ;
- **5 ventes au comptoir** du mois, dont une annulée ;
- **3 réceptions** rattachées à Ivoire Bureau, MacStore Abidjan et Librairie de France ;
- **2 sorties manuelles**, dont 60 ramettes réparties sur deux réceptions, et une casse ;
- **1 inventaire** avec un manquant et un surplus.

```bash
php artisan db:seed --class=DemoComptaSeeder              # crée ou recrée les données
DEMO_PURGE=1 php artisan db:seed --class=DemoComptaSeeder # retire les données
```

Le compteur des numéros de documents n'est pas remis à zéro quand on relance le seeder : les
numéros de démo avancent d'un cran à chaque exécution (PRO-…-0002, POS-…-0002…).

---

## 8. Ce qui reste à faire

### Module Commercial

1. Objectifs commerciaux.
2. Tableau commercial.

### Points ouverts

- **Point de vente et stock** : un article stocké vendu au comptoir ne sort pas du stock, car la
  caisse a été pensée pour les services du catalogue. À décider : la caisse doit-elle pouvoir vendre
  des articles en stock, avec sortie automatique ?
- **Comptabilité du stock** : les réceptions, sorties et écarts d'inventaire ne passent aucune
  écriture au journal (achats de marchandises, variation de stock). À voir avec l'expert-comptable.
- **Fiche fournisseur** : proposition d'y lister les réceptions (articles, quantités, dates) pour
  voir d'un coup tout ce qu'un fournisseur a livré.
- **Messages de validation en anglais** hors des formulaires refaits (4.7).
- **E-mails « Envoyer au client »** (factures, devis, proformas) : texte seul, sans le document joint.
- **Paiements de factures personnalisées non émises** : passés au journal comme les autres
  règlements, alors que la facture n'y figure qu'à son émission. À confirmer avec l'expert-comptable.

### Code non commité

Depuis le commit `97e1345`, rien n'est commité :
- les documents imprimables de la Comptabilité ;
- tout le travail sur le module Commercial ;
- les migrations ci-dessus, le seeder de démonstration et les nouveaux tests ;
- les rapports `compta_refonte_complete.md`, `refonte_commercial.md` et `rapport_commercial.md`.

---

## 9. Repères techniques ajoutés

- **Stock** : toujours passer par `StockService` pour lire le disponible ou sortir du stock. Il
  répartit sur les lots du plus ancien au plus récent et garde le lien lot, réception, fournisseur.
  La clé d'un article est désignation, référence et unité (`StockService::key`).
- **Journal d'une vente au comptoir** : toutes ses écritures lui sont rattachées. Après toute
  modification, appeler `AccountingPoster::syncPosSale`, qui ne passe que l'écart. Ne jamais
  recalculer « à la main » une contrepassation.
- **Chiffres de gestion** : `User::isManager()` (super_admin, admin, manager) décide de l'affichage
  des indicateurs sur les écrans de caisse.
- **`@json` et les tests** : les accents et « / » sont encodés (`\u00e9` pour « é », `\/` pour « / »). Dans un test, lire
  les données JSON d'une page avec une expression régulière et `json_decode`, et non
  `assertSee`. `Str::between` s'arrête au **dernier** délimiteur de la page.
- **Numéros de documents** : `DocumentNumberService` fournit les formats FC, DEV, PRO, POS, FAC, AV,
  EC. Tout nouveau document numéroté passe par lui.

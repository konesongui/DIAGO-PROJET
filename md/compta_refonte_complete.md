# Refonte de l'interface de DIAGO — Comptabilité terminée

**Projet :** DIAGO, progiciel de gestion multi-entreprises (Laravel 9, PostgreSQL)
**Période :** du 10 au 11 septembre 2026
**Branches de travail :** `refonte_compta` (issue de `dev`), puis `refonte_compta_complete`
**Maquette de référence :** `Interface UX-DIAGO.pdf` (12 pages, charte CME Expertises)
**Documents liés :** `compt_refont_2.md` (point d'étape n° 2), `rapport_compta.md` (refonte fonctionnelle
du volet comptable), `Audit_code_comptabilité.md`

Ce rapport clôt le volet Comptabilité de la refonte de l'interface. Il reprend le point d'étape n° 2
et le complète avec les deux derniers écrans, **immobilisations** et **factures de ventes**, puis avec
les **documents imprimables** (factures, fiche FNE, bilan PDF).

---

## 1. Résumé

- **Tous les écrans accessibles depuis la Comptabilité sont passés sous la charte** de la maquette,
  à l'exception de l'accueil du module, gardé volontairement dans sa présentation d'origine.
- **Les quatre documents imprimables** de la Comptabilité (facture de vente, facture fournisseur,
  fiche FNE, bilan annuel PDF) suivent la même charte.
- **Vingt-cinq défauts existants** ont été corrigés en chemin : chiffres inventés, filtres
  inopérants, fonctions sans bouton, mentions légales jamais imprimées, devise écrite en dur, etc.
  (section 5).
- **94 tests automatisés, tous au vert**, dont 25 ajoutés pendant la refonte de la Comptabilité.
- **Données de démonstration** rechargeables pour tester à la main (section 7).
- **Prochaine étape** : le module Commercial.

---

## 2. Avancement du plan de refonte

| # | Étape | Référence maquette | État |
|---|---|---|---|
| 0 | Suppression des emojis de l'interface | — | Fait |
| 1 | Fondations : charte, police, composants, page de référence | p. 12 | Fait |
| 2 | Layout : barre latérale, barre du haut, pied utilisateur | toutes | Fait |
| 3 | Tableau de bord | p. 2 | Fait |
| 4a | Comptabilité : accueil du module et tableau comptable | — | Fait |
| 4b | Comptabilité : trésorerie (caisses, banques, transferts) | p. 8 | Fait |
| 4c | Comptabilité : dépenses et achats (factures fournisseurs, catégories) | p. 7 | Fait |
| 4d | Comptabilité : rapports (état de trésorerie, rapport financier, journal, TVA, bilans) | p. 10 | Fait |
| 4e | Comptabilité : immobilisations | — | **Fait** |
| 4f | Comptabilité : factures de ventes (écran du module Commercial) | p. 5 | **Fait** |
| 4g | Documents imprimables (factures, fiche FNE, bilan PDF) | p. 6 et charte | **Fait** |
| 5 | Commercial : clients, livraisons, factures personnalisées et éditeur, produits et services, devis, proformas, stock, point de vente, et leurs documents imprimables | p. 4, 6, 9 | **Prochaine étape** |
| 6 | RH | charte | À faire |
| 7 | Administration | charte | À faire |
| 8 | Succursales / Entreprises | p. 3 | À faire |
| 9 | Utilisateurs et Paramètres | p. 11 | À faire |
| 10 | Console super-admin | charte | À faire |
| 11 | Pages publiques : connexion, landing, démo | charte | À faire |

---

## 3. Inventaire des écrans de la Comptabilité

| Écran | Vue | Étape | Traitement |
|---|---|---|---|
| Accueil Comptabilité | `comptabilite.blade.php` | 4a | Présentation d'origine conservée ; icônes et lien cassé corrigés |
| Tableau comptable | `comptabilite-tableau.blade.php` | 4a | Refait |
| Caisses | `comptabilite-module.blade.php` | 4b | Refait |
| Banques | `comptabilite-module.blade.php` | 4b | Refait |
| Transferts | `transfer.blade.php` | 4b | Refait |
| Factures fournisseurs (liste) | `supplier-invoices.blade.php` | 4c | Refait |
| Fiche facture fournisseur | `supplier-invoice-show.blade.php` | 4c | Refait |
| Catégories de dépenses | `expense-categories.blade.php` | 4c | Refait |
| État de trésorerie | `comptabilite-module.blade.php` | 4d | Refait |
| Rapport financier | `comptabilite-module.blade.php` | 4d | Refait |
| Journal comptable | `comptabilite-journal.blade.php` | 4d | Refait |
| Déclaration de TVA | `comptabilite-declaration-tva.blade.php` | 4d | Refait |
| Bilans (liste et fiche) | `annual-report-index.blade.php`, `annual-report-show.blade.php` | 4d | Refaits |
| Immobilisations | `fixed-assets.blade.php` | 4e | Refait |
| Factures de ventes | `commercial-invoices.blade.php` | 4f | Refait |

Les quatre écrans servis par `comptabilite-module.blade.php` (caisses, banques, état de trésorerie,
rapport financier) passent tous par la version refaite. Ce qui reste en style Metronic dans ce
fichier est l'ancien code, que plus aucune page n'utilise (voir section 7).

---

## 4. Ce qui a été fait, étape par étape

### Étapes 0 à 3 — Socle commun

- **Emojis supprimés** (environ 70) et remplacés par des icônes Bootstrap Icons.
- **Design system** : feuille `public/css/diago.css` (classes `dg-`), police Poppins, composants Blade
  `x-dg.page-header`, `x-dg.card`, `x-dg.kpi`, `x-dg.badge`, page de référence `/admin/design-system`.
- **Layout** : barre latérale marine, élément actif jaune, barre du haut blanche avec recherche
  globale, établissement, langue et notifications ; thème « Diago (charte CME) » par défaut.
- **Tableau de bord** : indicateurs comparés à la période précédente, courbe des recettes, anneau des
  dépenses.

### Étape 4a — Accueil Comptabilité et tableau comptable

- **Accueil** : grille de cartes colorées d'origine conservée, à ta demande ; seules les icônes
  invisibles et la carte « Factures achats » en erreur 404 ont été corrigées.
- **Tableau comptable** : indicateurs colorés avec fenêtres de détail, graphique des flux sur six
  mois.

### Étape 4b — Trésorerie (page 8)

- Caisses, banques et transferts : organisation et fonctions conservées, charte appliquée.
- Cartes de comptes au style de la page 8 (liseré de couleur, solde, numéro, statut, actions).
- Solde affiché dans la barre du haut.
- Couche de compatibilité `.dg-scope` : les champs, boutons, badges, fenêtres et tableaux Bootstrap
  existants prennent la charte sans réécriture de leur balisage.

### Étape 4c — Dépenses et achats (page 7)

- **Factures fournisseurs** : indicateurs, justificatif PDF en pastille, statuts en badges, état vide
  avec bouton d'import.
- **Fiche d'une facture** : encadré « Régime fiscal » orange tant qu'il n'est pas confirmé.
- **Catégories de dépenses** : une pastille de couleur par catégorie.

### Étape 4d — Rapports (page 10)

- **État de trésorerie** et **rapport financier** : vrais graphiques à la place des faux graphiques
  CSS et des valeurs inventées.
- **Journal** et **TVA** : titre, bouton retour, filtre compact, indicateurs, encadrés explicatifs.
- **Bilans** : actif, passif, compte de résultat et résultat mis en avant (bénéfice ou perte).

### Étape 4e — Immobilisations

- Quatre indicateurs : nombre d'immobilisations (violet), valeur brute (bleu), valeur nette
  comptable (vert), actifs en service (sarcelle).
- Carte « Registre des immobilisations » avec recherche et filtre par état dans son en-tête.
- Une pastille colorée par catégorie (véhicule en bleu, informatique en indigo, mobilier en
  sarcelle…), grise pour un bien cédé ou mis au rebut.
- **Pourcentage amorti** sous le montant de l'amortissement ; durée d'amortissement sous la date
  d'acquisition.
- La colonne « Catégorie » a été fondue dans la ligne grise sous le nom (« Véhicule · VEH-001 · Parc
  Abidjan ») pour que le tableau tienne sans défilement sur un portable en 1366 px.
- Menu d'actions (Modifier, Supprimer) et fenêtre de saisie à en-tête marine, commune à la création
  et à la modification.

### Étape 4f — Factures de ventes (page 5)

L'écran appartient au module Commercial, mais la carte « Factures ventes » de l'accueil Comptabilité
y mène ; il a donc été traité avec la Comptabilité.

- Quatre indicateurs : montant facturé (bleu), encaissé (vert), reste à encaisser (rouge), certifiées
  FNE (sarcelle). Les factures annulées sont exclues du facturé et de l'encaissé.
- **Onglets de statut avec compteurs**, comme la page 5 : Toutes, Payées, Partielles, Impayées,
  Annulées. Recherche et période conservées.
- Tableau : numéro et date de facture, client et bon de commande, montant TTC avec la ligne « payé »,
  reste à payer (rouge, ou vert à zéro, « — » pour une facture annulée), statut et FNE en badges.
- Une **seule fenêtre de paiement** à en-tête marine, avec un encadré vert rappelant la facture et le
  reste à payer.

Écarts assumés avec la maquette :

- **Pas de colonne Échéance ni d'onglet « En retard »** : les factures de ventes n'ont pas de date
  d'échéance en base ; rien n'a été inventé.
- **Onglets dans la carte du tableau**, et non dans la barre du haut : là-haut, ils auraient remplacé
  la recherche globale.
- **Pas de bouton jaune « Nouvelle facture »** : une facture naît de la validation d'une livraison
  complète. Le bouton « Voir les livraisons » est en contour.
- **Bouton retour vers « Commercial »**, module auquel l'écran appartient.

L'annulation d'une facture passe par une fenêtre à en-tête rouge qui demande le **motif**
(obligatoire) et rappelle qu'un avoir total est émis.

### Étape 4g — Documents imprimables

Une **mise en page d'impression commune** (`resources/views/admin/print/layout.blade.php`) sert aux
documents imprimés depuis le navigateur : feuille A4 à l'écran avec une barre « Imprimer / Fermer »,
impression automatique une fois la police chargée, liseré marine et jaune, Poppins, bloc de
l'entreprise (logo, coordonnées) et pied de page avec les mentions légales. Les lignes et les totaux
sont dans deux vues partielles (`print/sale-body`, `print/supplier-body`), partagées entre une facture
et sa version FNE.

| Document | Vue | Contenu |
|---|---|---|
| Facture de vente | `commercial-invoice-print.blade.php` | Encadrés « Facturé à » et « Références », lignes, Total HT brut, remise, Total HT, TVA, Total TTC, déjà payé, bandeau « Reste à payer », conditions de règlement et coordonnées bancaires, rappel FNE |
| Facture fournisseur | `supplier-invoice-print.blade.php` | Fournisseur et NCC, références (date, échéance, régime), lignes extraites du PDF, totaux, justificatif, régime à confirmer signalé |
| Fiche FNE (vente et achat) | `fne-invoice-print.blade.php` | « Facture normalisée », cadre fiscal (NCC, régime, centre des impôts, RCCM), lignes et totaux, bloc de certification avec **QR code** de l'adresse de vérification |
| Bilan annuel PDF | `annual-report-pdf.blade.php` | Rendu par dompdf (tableaux, DejaVu Sans) : en-tête de l'entreprise, quatre indicateurs, bilan et compte de résultat en deux colonnes, **contrôle d'équilibre** actif/passif, résultat encadré, pied de page numéroté |

Choix assumés : une ancienne facture sans régime fiscal n'affiche que son TTC ; une facture annulée
n'affiche pas de reste à payer ; le numéro d'une facture de vente reste « N° » suivi de son
identifiant, faute de numérotation propre ; dompdf ne sait pas afficher le nombre total de pages.

---

## 5. Défauts corrigés en chemin

La refonte a mis au jour des défauts qui existaient avant elle. Ils ont tous été corrigés.

| Écran | Défaut | Correction |
|---|---|---|
| État de trésorerie | Graphique rempli de **valeurs inventées** pour les mois sans opération | Vrais montants des 6 derniers mois |
| État de trésorerie | Anneau figé (42/30/17/11 %) et catégorie « Autres » inventée | Vrais volumes, types vides omis |
| État de trésorerie | « +4,8 % vs période précédente » écrit en dur | Texte honnête |
| Rapport financier | « Déjà payé » = total des dépenses, « Taux : 100 % » écrit en dur | Part réellement décaissée |
| Tous | `window.formatMoney` défini trop tard : les graphiques ne s'affichaient pas | Défini dans l'en-tête du layout |
| Caisses | Alerte « DataTables warning » sur une période sans mouvement | Tableau vide géré par DataTables |
| Transferts | La fenêtre ne se rouvrait pas en cas d'erreur de saisie | Réouverture après chargement de Bootstrap |
| Tableau comptable | Les fenêtres de détail ne correspondaient pas à leur montant | Chaque détail liste ce qui compose son montant |
| Accueil Comptabilité | Icônes invisibles, carte « Factures achats » en erreur 404 | Icônes et lien corrigés |
| Journal, bilans | Le tri par colonne séparait les lignes de leur pièce | Tri désactivé sur ces tableaux |
| Immobilisations | **Aucun bouton ne permettait de modifier** une immobilisation (la route existait) | « Modifier » dans le menu de chaque ligne |
| Immobilisations | Une **acquisition datée dans le futur était déjà amortie** (écart de mois calculé en valeur absolue) | Aucun amortissement avant la date d'acquisition |
| Immobilisations | Catégorie et état perdus en cas d'erreur de saisie | Saisie conservée, fenêtre rouverte |
| Factures de ventes | Le filtre « Impayée » ne trouvait jamais rien (statut `pending` au lieu de `unpaid`) | Onglets sur les vrais statuts |
| Factures de ventes | Devise « XOF » écrite en dur | Devise de l'entreprise (`money()`) |
| Factures de ventes | Une fenêtre de paiement par facture, placée dans le tableau (HTML invalide) | Une seule fenêtre, hors du tableau |
| Factures de ventes | **L'annulation échouait toujours** : le motif exigé par le serveur n'était pas envoyé | Fenêtre d'annulation avec motif obligatoire |
| Facture de vente imprimée | Le NIF sortait toujours à « - » (clé `nif` jamais enregistrée) | Mentions lues dans les vrais paramètres |
| Facture de vente imprimée | Statut en code anglais brut (« Partially_paid ») | Statut en français, en pastille |
| Facture de vente imprimée | La TVA n'apparaissait pas, seul le TTC était imprimé | Ventilation HT, remise, TVA, TTC |
| Facture de vente imprimée | Date de création de l'enregistrement au lieu de la date d'émission | Date d'émission |
| Fiche FNE | Cadre fiscal entièrement à « - » (clés `ncc`, `regime_imposition`, `centre_impot`, `rccm` jamais enregistrées) | Vraies clés, anciennes en repli |
| Fiche FNE | Facture de vente certifiée imprimée sans HT ni TVA | Ventilation complète |
| Bilan PDF | Ni identité ni mentions légales de l'entreprise | En-tête et pied de page complets |
| Bilan PDF | Fichier de 1,2 Mo (police entière embarquée) | Sous-ensemble de police : 43 Ko |

S'y ajoutent deux corrections hors Comptabilité : le débordement horizontal de toutes les pages sur
mobile, et les effectifs RH affichés comme des montants en FCFA sur le tableau de bord.

---

## 6. Tests et vérifications

**94 tests, tous au vert.** Ajoutés pendant la refonte de la Comptabilité :

| Fichier | Tests | Ce qui est protégé |
|---|---|---|
| `tests/Feature/TreasuryReportTest.php` | 2 | Aucun chiffre inventé dans l'état de trésorerie |
| `tests/Feature/FixedAssetTest.php` | 5 | Amortissement linéaire, acquisition future, modification, cloisonnement par entreprise |
| `tests/Feature/SalesInvoicesPageTest.php` | 4 | Indicateurs hors factures annulées, compteurs des onglets, paiement partiel, annulation avec motif et avoir |
| `tests/Feature/GlobalSearchTest.php` | 5 | Recherche globale limitée aux droits et à l'entreprise |
| `tests/Feature/InvoicePrintTest.php` | 7 | Ventilation fiscale, statut en français, mentions légales, facture fournisseur complète ou incomplète, fiche FNE (cadre fiscal, TVA, QR), fiche réservée aux factures certifiées |
| `tests/Feature/AnnualReportPdfTest.php` | 2 | PDF téléchargé et marqué comme lu, poids réduit, comptes, équilibre, résultat et mentions légales |

Chaque écran a en outre été vérifié par :

- compilation de toutes les vues et contrôle de syntaxe des vues compilées ;
- rendu réel dans un navigateur sans interface, avec des données fictives, en **1366 px** (portable),
  **1440 px** (bureau) et **420 px** (téléphone) ; mesure de la largeur des tableaux pour éviter tout
  défilement horizontal sur ordinateur ;
- pour les filtres côté navigateur (factures de ventes), exécution des onglets et de la recherche ;
- pour les documents imprimables, export PDF réel et contrôle qu'ils tiennent sur une page A4.

---

## 7. Ce qui reste à faire

### Prochaine étape : module Commercial (5)

Écrans à reprendre un par un : clients (page 4), livraisons, factures personnalisées et éditeur de
facture (page 6), produits et services (page 9), devis, proformas, stock et point de vente, ainsi que
leurs gabarits d'impression (devis, proformas, factures personnalisées), qui reprendront la mise en
page d'impression commune. L'accueil du module garde sa grille de cartes colorées.

### Données de démonstration

`database/seeders/DemoComptaSeeder.php` remplit l'entreprise « Diagoma Demo » pour tester à la main
(connexion `admin@diagoma.local`) : caisses, compte bancaire, catégories et six mois de mouvements,
clients, factures de ventes de tous statuts (dont une certifiée FNE et une annulée par avoir),
factures fournisseurs avec leur PDF, immobilisations, et un **exercice 2025 clos** avec son bilan.
Les écritures passent par les services de l'application (TVA, journal, règlements).

```bash
php artisan db:seed --class=DemoComptaSeeder              # crée ou recrée les données
DEMO_PURGE=1 php artisan db:seed --class=DemoComptaSeeder # retire les données
```

Tout ce qui est créé est noté dans `storage/app/demo-compta.json` ; une relance retire d'abord ces
enregistrements, et eux seuls. Les paramètres de l'entreprise ne sont complétés que s'ils sont vides.

### Nettoyage

- `comptabilite-module.blade.php` : supprimer l'ancien en-tête et l'ancien tableau générique, qui ne
  servent plus.
- Route `admin.rh.employees.search` : plus appelée par l'interface depuis la recherche globale.

### Points ouverts

- **Bandeau de l'accueil Comptabilité** : garde son bleu d'origine (`#1d4ed8`), différent du marine de
  la charte. À aligner si tu le souhaites.
- **« Rapprochement bancaire »** (bouton de la page 8) : non ajouté, la fonction n'existe pas.
- **Échéance des factures de ventes** : ajouter une date d'échéance permettrait l'onglet « En retard »
  de la maquette ; c'est une évolution fonctionnelle, pas une question d'interface.
- **Messages de validation en anglais** (« The acquisition value field is required ») sur toute
  l'application : les traductions françaises des règles de validation manquent.
- **Données réelles** : tous les écrans ont été vérifiés avec des données fictives (tests et seeder de
  démonstration). Une vérification sur des données réelles reste souhaitable avant mise en production.
- **E-mail « Envoyer au client »** d'une facture de vente : simple texte, avec « XOF » écrit en dur et
  sans la facture jointe.

### Code non commité

Le commit `97e1345` couvre la Comptabilité jusqu'aux factures de ventes. L'étape 4g (documents
imprimables), l'annulation avec motif, le seeder de démonstration, les tests `InvoicePrintTest` et
`AnnualReportPdfTest` et ce rapport ne sont pas encore commités sur `refonte_compta_complete`.

---

## 8. Règles de design retenues

Ces règles sont issues de tes validations et de tes corrections. Elles s'appliquent à toutes les
étapes restantes.

### Méthode

- **Rester fidèle à la maquette** `Interface UX-DIAGO.pdf`, en relisant la page concernée avant chaque
  écran.
- **Avancer écran par écran**, jamais tout en même temps, et faire valider chaque étape.
- **Garder l'organisation et les fonctions** des écrans existants.
- **Libellés honnêtes** et **aucun chiffre inventé** : un graphique sans données affiche un état vide ;
  une information absente de la base (échéance, par exemple) n'est pas simulée.

### Typographie et navigation

- **Poppins partout**.
- **Navigation par modules actuels** ; seul le style suit la maquette.
- **Aucun emoji** : icônes Bootstrap Icons uniquement.

### Couleurs

| Rôle | Couleur |
|---|---|
| Bleu dominant (barre latérale, fenêtres de formulaire) | `#273772` |
| Jaune accent (action principale, élément actif du menu) | `#FADF2F` |
| Fond principal | `#F4F6FB` |
| Texte principal | `#172033` |

Palette d'accents, une couleur par indicateur : vert `#059669` (entrées, encaissements), rouge
`#dc2626` (sorties, impayés), orange `#ea580c` (dépenses, alertes), bleu `#2563eb` (liquidités,
montants facturés), violet `#7c3aed` (flux, immobilisations), indigo `#4f46e5` (effectifs, totaux),
sarcelle `#0d9488` (achats, certifications), cyan `#0891b2` (ventes, congés), rose `#db2777`
(justificatifs).

- **Pastilles d'icônes pleines** : fond de couleur franche, icône blanche, légère ombre de la même
  couleur.
- **Pas de dégradé, de halo ni de liseré coloré** sur les cartes d'indicateurs ; seules les cartes de
  comptes bancaires et de caisses gardent le liseré gauche de la page 8.
- **Fenêtres modales jamais toutes blanches** : en-tête coloré, titre blanc, total ou rappel mis en
  avant.
- **Graphiques** : palette variée ; état vide soigné quand il n'y a pas de données.

### Composants et ergonomie

- **Bouton retour** au-dessus du titre, avec l'apparence d'un vrai bouton.
- **Pages d'accueil de modules** : grille de cartes colorées d'origine conservée.
- **Une seule action principale en jaune** par écran, et aucune quand l'écran ne crée rien.
- **Recherche globale** conservée dans la barre du haut ; les filtres propres à un écran vont dans
  l'en-tête de sa carte.
- **Tableaux** : actions regroupées dans un menu ; tenir sans défilement horizontal en 1366 px, quitte
  à regrouper une information secondaire sous une autre.

### Gestion du code

- **Aucune mention de Claude** dans les messages de commit.
- **Les commits et les push sont faits par toi.**

---

## 9. Repères techniques

- Les écrans refaits s'enveloppent dans `<div class="dg-font dg-scope">`.
- Indicateur : `<x-dg.kpi … color="green" />` ; carte à pastille : `<x-dg.card … icon="bi-…" color="blue">`.
- **Piège Blade** : ne jamais placer un bloc `@php … @endphp` après un `@php(...)` court dans le même
  fichier.
- **DataTables** est appliqué automatiquement par le layout à tous les tableaux `.table` (boutons
  d'export Excel et PDF, largeurs de colonnes calculées). Il n'est pas initialisé si le `tbody`
  contient une cellule fusionnée (`colspan`) ; un message « aucun résultat » de filtre doit donc être
  placé **hors** du tableau.
- Une fenêtre modale unique, remplie depuis les attributs `data-*` du bouton qui l'ouvre, remplace
  les fenêtres répétées par ligne ; après une erreur de validation, un champ caché (`asset_id`,
  `payment_invoice_id`) permet de la rouvrir sur le bon élément.
- Chaque étape se termine par : rendu des pages concernées, contrôle des vues compilées et suite de
  tests complète.

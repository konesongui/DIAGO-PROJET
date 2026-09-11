# Refonte de l'interface de DIAGO — point d'étape n° 2

**Projet :** DIAGO, progiciel de gestion multi-entreprises (Laravel 9, PostgreSQL)
**Période :** 10 et 11 septembre 2026
**Branche de travail :** `refonte_compta` (issue de `dev`)
**Maquette de référence :** `Interface UX-DIAGO.pdf` (12 pages, charte CME Expertises)
**Documents liés :** `rapport_compta.md` (refonte fonctionnelle du volet comptable), `Audit_code_comptabilité.md`

---

## 1. Point de départ

Après la refonte fonctionnelle du volet comptable, il fallait refaire l'**interface** de toutes les
vues en suivant la maquette `Interface UX-DIAGO.pdf`. Celle-ci décrit une charte (bleu marine
`#273772`, jaune `#FADF2F`, fond `#F4F6FB`, texte `#172033`) et une dizaine d'écrans : tableau de
bord, entreprises, clients, factures, éditeur de facture, dépenses, trésorerie, produits et
services, rapports, paramètres, et une page « Design System ».

Deux constats de départ :

- L'application compte **99 vues**, alors que la maquette n'en dessine qu'une dizaine. Des modules
  entiers n'y figurent pas (RH, Administration, point de vente, stock, devis, proformas, console
  super-admin).
- L'interface mélangeait plusieurs styles (Metronic, dégradés violets, emojis servant d'icônes).

Consigne de méthode : **rester fidèle à la maquette et avancer module par module**, chaque étape
étant validée avant de passer à la suivante.

---

## 2. Ce qui devait être fait (plan de la refonte)

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
| 4e | Comptabilité : immobilisations | — | À faire |
| 5 | Commercial : clients, factures et éditeur, produits et services, devis, proformas, stock, point de vente | p. 4, 5, 6, 9 | À faire |
| 6 | RH | charte | À faire |
| 7 | Administration | charte | À faire |
| 8 | Succursales / Entreprises | p. 3 | À faire |
| 9 | Utilisateurs et Paramètres | p. 11 | À faire |
| 10 | Console super-admin | charte | À faire |
| 11 | Pages publiques : connexion, landing, démo | charte | À faire |

S'y ajoutent deux chantiers transversaux menés en cours de route, à la demande : la **mise en
couleur** de l'interface et la **recherche globale** de la barre du haut (voir section 4).

---

## 3. Ce qui est fait, étape par étape

### Étape 0 — Suppression des emojis

Environ 70 emojis servaient d'icônes (menu, notifications, cartes de modules, indicateurs). Ils
ont été remplacés par des icônes **Bootstrap Icons**, déjà fournies par le thème Metronic. Les
emojis décoratifs dans les textes ont été retirés. Les icônes de la landing, enregistrées en base,
sont converties automatiquement à la lecture.

### Étape 1 — Fondations du design system

- Feuille de style dédiée `public/css/diago.css`, toutes les classes préfixées `dg-` pour
  cohabiter avec Metronic pendant la refonte.
- Police **Poppins** (700 titres, 600 cartes et boutons, 500 menus et libellés, 400 textes).
- Composants Blade réutilisables : `x-dg.page-header`, `x-dg.card`, `x-dg.kpi`, `x-dg.badge`.
- Page de référence **`/admin/design-system`**, qui reproduit la page 12 du PDF et documente la
  palette.
- Tailles exprimées en **pixels** : Metronic force la taille racine à 13 px, ce qui faussait les
  `rem`.

### Étape 2 — Layout

- Barre latérale marine avec la marque « DIAGO by CME Expertises », élément actif en jaune avec
  liseré, utilisateur (initiales dans un rond jaune) et menu du compte en pied de barre.
- Barre du haut blanche : recherche, établissement, langue, notifications.
- Une page peut placer son propre contenu dans la barre du haut (`@section('topbar')`) et ses
  actions (`@section('topbar-actions')`).
- Nouveau thème « Diago (charte CME) », utilisé par défaut ; les autres thèmes restent
  disponibles.
- Correction du débordement horizontal sur mobile, sur toutes les pages.

### Étape 3 — Tableau de bord

- En-tête « Bonjour, bienvenue sur Diago », filtre de période et bouton **Action rapide** (menu
  des créations autorisées selon les droits).
- Quatre indicateurs comparés à la période précédente : **Recettes**, Dépenses, **Solde net**,
  Factures impayées.
- Courbe d'évolution des recettes depuis janvier (dernier point jaune, comme la maquette) et
  répartition des dépenses en anneau.
- Sections trésorerie, commercial et RH conservées sous la charte.

### Étape 4a — Accueil Comptabilité et tableau comptable

- **Accueil du module** : conservé dans sa présentation d'origine, à ta demande (cartes colorées).
  Seules les icônes, qui ne s'affichaient pas, et un lien cassé ont été corrigés.
- **Tableau comptable** : indicateurs colorés avec fenêtres de détail, graphique des flux sur
  six mois, carte d'indicateurs.

### Étape 4b — Trésorerie (page 8)

- Caisses, banques et transferts : organisation et fonctions conservées, charte appliquée.
- Cartes de comptes au style de la page 8 (liseré de couleur, solde, numéro, statut, actions).
- Solde affiché dans la barre du haut (« Solde total des banques : … »).
- Une **couche de compatibilité `.dg-scope`** donne automatiquement la charte aux champs,
  boutons, badges, fenêtres et tableaux Bootstrap existants, sans réécrire leur balisage.

### Étape 4c — Dépenses et achats (page 7)

- **Factures fournisseurs** : indicateurs, justificatif PDF en pastille cliquable, statuts en
  badges (Importée, À vérifier, FNE), état vide avec bouton d'import.
- **Fiche d'une facture** : encadré « Régime fiscal » orange tant qu'il n'est pas confirmé,
  données extraites traduites et formatées.
- **Catégories de dépenses** : une pastille de couleur par catégorie.

### Étape 4d — Rapports (page 10)

- **État de trésorerie** et **rapport financier** : même ordre de sections qu'avant, bandeaux en
  dégradé remplacés par l'en-tête standard, faux graphiques CSS remplacés par de vrais graphiques.
- **Journal comptable** et **déclaration de TVA** : ajout d'un titre et d'un bouton retour (les
  pages n'en avaient pas), filtre compact, indicateurs, encadrés explicatifs.
- **Bilans** : liste et fiche d'exercice avec actif, passif, compte de résultat et résultat mis en
  avant (bénéfice ou perte).

---

## 4. Chantiers transversaux

### Mise en couleur

L'interface a été jugée trop monochrome, puis corrigée en trois passes jusqu'à la règle validée :
**pastilles d'icônes pleines** (couleur franche, icône blanche, légère ombre de la même couleur),
titres de cartes avec pastille colorée, liens « Voir le détail » dans la couleur de leur
indicateur, fenêtres modales à en-tête coloré, états vides soignés pour les graphiques. Le détail
est dans la section 7.

### Recherche globale

La barre de recherche ne cherchait que des employés, sur toutes les pages (y compris en
Comptabilité, où elle ne servait à rien). Elle est devenue une **recherche globale** :

- employés, clients, fournisseurs, factures clients, factures fournisseurs, caisses et banques ;
  entreprises pour le super admin ;
- résultats regroupés par type avec pastilles colorées, terme surligné en jaune ;
- clavier : `Ctrl+K`, flèches, Entrée, Échap ;
- chaque type n'apparaît que si l'utilisateur a le droit de consulter le module et si la rubrique
  est activée ; les résultats sont limités à son entreprise.

Fichiers : `app/Services/GlobalSearchService.php`, `app/Http/Controllers/Admin/GlobalSearchController.php`,
route `admin.search`, tests `tests/Feature/GlobalSearchTest.php`.

### Barre du haut

Établissement dans une pastille marine à icône jaune, langue en pastille bleue (codes FR / EN),
notifications en pastille orange avec compteur rouge chiffré et pastille de couleur par type de
demande.

---

## 5. Défauts corrigés en chemin

La refonte a mis au jour des défauts qui existaient avant elle. Ils ont été corrigés :

| Écran | Défaut | Correction |
|---|---|---|
| État de trésorerie | Graphique rempli de **valeurs inventées** (150 000 à 420 000 FCFA) pour les mois sans opération, toujours de janvier à juin | Vrais montants des 6 derniers mois |
| État de trésorerie | Anneau figé (42/30/17/11 %) et catégorie « Autres » de 150 000 FCFA inventée | Vrais volumes, types vides omis |
| État de trésorerie | « +4,8 % vs période précédente » écrit en dur | Texte honnête |
| Rapport financier | « Déjà payé » = total des dépenses, « Taux : 100 % » écrit en dur | Part réellement décaissée (caisse + banque) |
| Tous | `window.formatMoney` défini trop tard : les graphiques ne s'affichaient pas | Défini dans l'en-tête du layout |
| Caisses | Alerte « DataTables warning: Incorrect column count » sur une période sans mouvement | Message de tableau vide géré par DataTables |
| Transferts | La fenêtre ne se rouvrait pas en cas d'erreur de saisie | Réouverture après chargement de Bootstrap |
| Tableau comptable | Les fenêtres de détail ne correspondaient pas à leur montant | Chaque détail liste ce qui compose son montant |
| Accueil Comptabilité | Icônes invisibles, carte « Factures achats » en erreur 404 | Icônes et lien corrigés |
| Journal, bilans | Le tri par colonne séparait les lignes de leur pièce | Tri désactivé sur ces tableaux |
| Mobile | Débordement horizontal de toutes les pages | Correction dans le layout |
| RH (tableau de bord) | Effectifs affichés comme des montants en FCFA | Corrigé |

Deux tests protègent l'état de trésorerie contre les chiffres inventés
(`tests/Feature/TreasuryReportTest.php`). La suite complète compte **76 tests, tous au vert**.

---

## 6. Ce qui reste à faire

### Étapes restantes du plan

1. **4e — Immobilisations** : dernier écran de la Comptabilité.
2. **5 — Commercial** (pages 4, 5, 6 et 9) : clients, factures et éditeur de facture, produits et
   services, puis devis, proformas, stock et point de vente. Module le plus lourd après la
   Comptabilité, à découper en sous-étapes.
3. **6 — RH**, **7 — Administration** : pages d'accueil à garder dans leur présentation d'origine
   (cartes colorées), écrans fonctionnels à passer sous la charte.
4. **8 — Succursales / Entreprises** (page 3).
5. **9 — Utilisateurs et Paramètres** (page 11) : y compris la page de choix du thème.
6. **10 — Console super-admin**.
7. **11 — Pages publiques** : connexion, inscription, landing, démo.

### Points ouverts à trancher ou à nettoyer

- **Documents imprimables** (factures, bilans PDF, fiches FNE) : pas encore refaits ; ce sont des
  gabarits d'impression à traiter à part.
- **Bandeau de l'accueil Comptabilité** : garde son bleu d'origine (`#1d4ed8`), différent du
  marine de la charte. À aligner si tu le souhaites.
- **« Rapprochement bancaire »** (bouton de la page 8) : non ajouté, la fonction n'existe pas.
- **Clients, fournisseurs, factures de livraison** : pas de fiche individuelle ; la recherche
  globale mène à la liste.
- **Code mort** : l'ancienne route `admin.rh.employees.search` n'est plus appelée par l'interface ;
  dans `comptabilite-module.blade.php`, l'ancien en-tête et l'ancien tableau générique ne servent
  plus. À supprimer lors d'un passage de nettoyage.
- **Données de démonstration** : la base locale ne contient ni ventes, ni dépenses, ni écritures ;
  les écrans ont été vérifiés avec des données fictives en mémoire. Une vérification sur des
  données réelles reste souhaitable avant mise en production.

---

## 7. Tes goûts et préférences de design

Ces règles sont issues de tes validations et de tes corrections pendant la refonte. Elles
s'appliquent à toutes les étapes restantes.

### Méthode

- **Rester fidèle à la maquette** `Interface UX-DIAGO.pdf`, en relisant la page concernée avant
  chaque écran.
- **Avancer module par module**, jamais tout en même temps, et faire valider chaque étape.
- **Garder l'organisation et les fonctions** des écrans existants : on applique la charte, on ne
  réorganise pas les pages (le regroupement thématique des cartes de l'accueil Comptabilité a été
  refusé).
- **Libellés honnêtes** : ne pas appeler « Chiffre d'affaires » des recettes encaissées, ni
  « Bénéfice net » un simple solde recettes moins dépenses.
- **Aucun chiffre inventé** dans un écran : un graphique sans données affiche un état vide.

### Typographie et navigation

- **Poppins partout** (et non Inter / Manrope).
- **Navigation par modules actuels** (Tableau de bord, Comptabilité, Commercial, RH,
  Administration, Succursales, Utilisateurs, Paramètres) ; seul le style suit la maquette.
- **Aucun emoji** dans l'interface : icônes Bootstrap Icons uniquement.

### Couleurs

Charte de base :

| Rôle | Couleur |
|---|---|
| Bleu dominant (barre latérale, boutons secondaires) | `#273772` |
| Jaune accent (bouton d'action principal, élément actif du menu) | `#FADF2F` |
| Fond principal | `#F4F6FB` |
| Texte principal | `#172033` |

**Mais l'interface ne doit pas être monochrome.** Palette d'accents, une couleur par indicateur :

| Couleur | Code | Usage type |
|---|---|---|
| Vert | `#059669` | entrées, recettes, succès |
| Rouge | `#dc2626` | sorties, impayés, erreurs |
| Orange | `#ea580c` | dépenses, alertes, à vérifier |
| Bleu | `#2563eb` | liquidités, soldes |
| Violet | `#7c3aed` | flux, transferts |
| Indigo | `#4f46e5` | effectifs, totaux |
| Sarcelle | `#0d9488` | achats, rapports |
| Cyan | `#0891b2` | mobile money, congés |
| Rose | `#db2777` | justificatifs, documents |

Règles d'application :

- **La couleur est portée par la pastille d'icône** : fond de couleur franche, icône blanche,
  légère ombre de la même couleur. Des pastilles pâles (fond teinté à 12 %) ont été jugées « mal
  coloriées ».
- **Pas de dégradé, pas de halo, pas de liseré coloré sur les cartes d'indicateurs** : « juste
  l'icône en couleur suffit ». Seules les cartes de comptes bancaires et de caisses gardent le
  liseré gauche prévu par la page 8 de la maquette.
- Le lien « Voir le détail » d'un indicateur prend sa couleur.
- Les **titres de cartes** portent une petite pastille colorée.
- **Fenêtres modales jamais toutes blanches** : en-tête coloré (couleur de l'indicateur pour les
  détails, marine pour les formulaires), titre blanc, en-tête de tableau teinté, total mis en avant.
- **Graphiques** : palette variée ; un graphique vide affiche un état vide soigné (pastille,
  titre, explication), jamais une grille grise.
- **Barre du haut colorée** : établissement marine et jaune, boutons en pastilles pleines,
  compteur de notifications rouge.

### Composants et ergonomie

- Le **bouton retour** au-dessus d'un titre doit ressembler à un vrai bouton (bordure, fond
  blanc), pas à un simple lien.
- **Pages d'accueil de modules** (Comptabilité, RH, Commercial, Administration…) : garder la
  grille de cartes colorées d'origine (pastille colorée, badge, statut, bouton « Ouvrir ») ; seuls
  les vrais défauts y sont corrigés.
- **Une seule action principale en jaune** par écran ; les autres boutons en contour.
- **Recherche globale** dans la barre du haut plutôt qu'une recherche limitée à un module.

### Gestion du code

- **Aucune mention de Claude** dans les messages de commit.
- **Pas de commit sans demande explicite** : tu commits et pousses toi-même sur tes branches
  (actuellement `refonte_compta`).

---

## 8. Repères techniques pour la suite

- Les écrans refaits s'enveloppent dans `<div class="dg-font dg-scope">` : les composants
  Bootstrap existants y prennent la charte automatiquement.
- Indicateur coloré : `<x-dg.kpi … color="green" />` ; carte à pastille :
  `<x-dg.card … icon="bi-…" color="blue">`.
- **Piège Blade** : ne jamais placer un bloc `@php … @endphp` après un `@php(...)` court dans le
  même fichier ; Blade prend alors tout l'intervalle pour du PHP brut et la vue casse.
- Un tableau DataTables ne doit pas contenir de ligne « vide » fusionnée (`colspan`) si la page
  l'initialise elle-même.
- Chaque étape se termine par : rendu de toutes les pages concernées, vérification de la syntaxe
  des vues compilées, et suite de tests complète.

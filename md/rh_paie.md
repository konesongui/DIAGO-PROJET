# Refonte de l'interface de DIAGO — Module RH & Paie

**Projet :** DIAGO, progiciel de gestion multi-entreprises (Laravel 9, PostgreSQL)
**Date :** 12 septembre 2026
**Branche de travail :** `refonte_commercial_complete`
**Maquette de référence :** `Interface UX-DIAGO.pdf` (charte CME Expertises)
**Documents liés :** `rapport_commercial.md` (module Commercial), `compta_refonte_complete.md` (Comptabilité et documents imprimables)

Ce rapport couvre la refonte complète du module **RH & Paie**, menée en quatre étapes validées
l'une après l'autre : accueil et personnel, congés, présences et permissions, tableau RH et
catégories salariales, puis la paie.

---

## 1. Résumé

- **Quinze écrans refaits** : accueil RH, liste du personnel, congés (liste, paramétrage,
  calendrier), permissions, présences du jour, QR de pointage, rapport de présence, tableau RH,
  catégories salariales, bulletins de paie (liste, génération, fiche), bulletin imprimable et
  livre de paie. Plusieurs tenaient sur **une seule ligne de code** avant la refonte.
- **Défauts graves corrigés**, entre autres :
  - supprimer un employé effaçait ses bulletins de paie, congés, permissions et pointages ;
  - le solde annuel de congés n'était jamais vérifié ;
  - l'effectif du tableau RH ne comptait que les employés créés pendant la période choisie ;
  - la recherche du rapport de présence ne fonctionnait jamais ;
  - le QR de pointage changeait à chaque affichage de la page ;
  - regénérer un bulletin laissait la caisse sur l'ancien montant.

  Détail en section 4.
- **Vingt-neuf tests automatisés ajoutés**, **212 tests au total, tous au vert** (183 avant la
  refonte RH).
- **Aucune migration** : le module a été refait à structure de base inchangée.
- Données de démonstration enrichies (employés, congés, permissions, pointages, catégories
  salariales, bulletins) pour tester à la main.

---

## 2. Avancement du plan de refonte

| # | Étape | État |
|---|---|---|
| 0 à 3 | Emojis, fondations, layout, tableau de bord | Fait |
| 4a à 4g | Comptabilité : écrans et documents imprimables | Fait |
| 5 | Commercial | Fait |
| 6 | **RH & Paie** | **Fait** |
| 7 | Administration | Fait (voir `administration.md`) |
| 8 | Succursales / Entreprises | À faire |
| 9 | Utilisateurs et Paramètres | À faire |
| 10 | Console super-admin | À faire |
| 11 | Pages publiques : connexion, landing, démo | À faire |

---

## 3. Écrans du module

| Écran | Vue | État |
|---|---|---|
| Accueil RH & Paie | `rh.blade.php` | Fait (charte : bandeau marine, boutons jaunes, pastilles colorées) |
| Liste du personnel | `rh-employees.blade.php` | **Nouveau fichier** (l'écran vivait dans une page fourre-tout) |
| Liste des congés | `rh-leave-requests.blade.php` | Fait |
| Paramétrage des congés | `rh-leave-types.blade.php` | Fait |
| Calendrier des congés | `rh-leave-calendar.blade.php` | Fait (vraie vue mensuelle) |
| Demandes de permission | `rh-permission-requests.blade.php` | Fait |
| Présences du jour | `rh-attendance-today.blade.php` | Fait |
| Affichage du QR de pointage | `rh-qr-display.blade.php` | Fait |
| Rapport de présence | `rh-attendance-report.blade.php` | Fait |
| Tableau RH | `rh-tableau.blade.php` | Fait |
| Catégories salariales | `rh-salary-categories.blade.php` | Fait |
| Bulletins de paie (liste) | `rh-payroll.blade.php` | Fait |
| Génération d'un bulletin | `rh-payroll-form.blade.php` | Fait |
| Fiche d'un bulletin | `rh-payroll-show.blade.php` | Fait (écran de l'application) |
| Bulletin imprimable (PDF) | `rh-payroll-print.blade.php` | **Nouveau fichier**, une page A4 |
| Page d'attente générique | `rh-module.blade.php` | **Supprimée** : chaque rubrique a son écran |

Tous les écrans suivent le modèle validé : bouton retour, indicateurs colorés, carte de tableau avec
recherche et filtres, fenêtres à en-tête coloré rouvertes avec la saisie en cas d'erreur, états vides
soignés, montants dans la devise de l'entreprise, tableaux qui tiennent sans défilement en 1366 et
1440 px.

---

## 4. Défauts corrigés

### 4.1 Personnel

- **Supprimer un employé effaçait tout son historique** : bulletins de paie, congés, permissions et
  pointages partaient en cascade, et son compte utilisateur était supprimé — ce qui effaçait aussi
  le vendeur sur toutes les ventes qu'il avait créées, donc les objectifs commerciaux. La
  suppression est refusée dès qu'il existe un historique, avec le détail dans le message.
- **Le compte utilisateur n'est plus supprimé mais désactivé** : il signe les documents créés.
- **Mettre fin à un contrat déjà clôturé** était possible et réécrivait la date de sortie.
- **La colonne Photo affichait toujours « - »** alors que les photos sont enregistrées.
- Statuts en clair (« En poste », « En congé », « Contrat clôturé ») au lieu de « Inactif » pour
  tout ce qui n'était pas actif ; colonne de fin de tableau (`colspan`) fausse.

### 4.2 Congés

- **Le solde annuel n'était jamais vérifié** : le contrôle portait sur une seule demande, si bien
  qu'un employé pouvait prendre 30 jours de congé annuel autant de fois qu'il le voulait. Le solde
  de l'année est décompté et affiché.
- **Deux congés pouvaient se chevaucher** pour le même employé, y compris à la validation de deux
  demandes concurrentes.
- **Une demande déjà traitée pouvait être re-validée ou re-refusée** indéfiniment.
- **Les états s'affichaient en anglais** (« pending », « approved »).
- **Impossible d'enregistrer un congé pour un employé** : seul un employé connecté et relié à une
  fiche pouvait déposer une demande, alors que beaucoup d'employés ne se connectent pas.
- Deux types de congé pouvaient porter le même nom, ce qui rendait les soldes illisibles.
- Le calendrier affichait tous les congés validés de toutes les années, sans période.

### 4.3 Présences

- **La recherche du rapport de présence ne fonctionnait jamais** : le formulaire postait vers la
  même route que les paramètres horaires, la validation des horaires échouait et renvoyait une
  erreur. Recherche (par l'URL) et horaires (formulaire dédié) sont séparés.
- **Le QR de pointage changeait à chaque affichage de la page** : revenir sur l'écran invalidait le
  code déjà affiché ou imprimé, alors que la page affirmait le contraire. Le code est stable, un
  bouton explicite le renouvelle.
- **L'écart d'heures était faux** : chaque employé se voyait opposer les 173 h mensuelles quelle que
  soit la période (un rapport sur trois jours affichait −165 h). Les heures attendues comptent la
  journée de référence pour chaque jour réellement pointé.
- **L'indicateur « Arrivées » comptait tous les pointages**, y compris ceux sans heure d'arrivée.
- **Aucune vue des absents** : impossible de savoir qui n'avait pas pointé.

### 4.4 Permissions

- **Le motif était un champ libre** : cinq motifs normalisés, les anciennes saisies restant lisibles.
- **Une demande déjà traitée pouvait être re-validée**, et **deux permissions pouvaient se
  chevaucher**.
- **Seul un employé connecté pouvait demander une permission** : l'administration peut l'enregistrer.

### 4.5 Tableau RH et catégories salariales

- **L'effectif ne comptait que les employés créés pendant la période** : avec la période par défaut
  (depuis le 1er janvier), une entreprise sans embauche de l'année affichait un effectif de 0 et
  toutes les répartitions vides. L'effectif est un état du jour ; seuls mouvements, congés et
  bulletins suivent la période.
- **« En congé » venait d'une colonne de statut jamais renseignée** : rien, dans l'application, ne
  fait passer un employé « en congé ». L'état est lu dans les congés validés qui couvrent la journée
  (liste du personnel, présences, tableau RH).
- **Supprimer une catégorie salariale la retirait en silence de toutes les fiches employés** ;
  **renommer une catégorie** laissait les fiches sur l'ancien nom et cassait le rattachement.
- Deux catégories pouvaient porter le même nom ; la modification ouvrait une page séparée.
- Indicateurs sans intérêt retirés du tableau (nombre de jetons QR générés, nombre de types de congé).

### 4.6 Paie

- **Regénérer un bulletin laissait la caisse ou la banque sur l'ancien montant** : le mouvement
  d'argent n'était créé que la première fois. Il est repris (ancien montant rendu au compte, nouveau
  mouvement enregistré), y compris si le mode de paiement change.
- **La fiche d'un bulletin sortait de l'application** : la page affichée était le document PDF
  lui-même, sans menu ni navigation. L'écran et le document sont désormais distincts.
- **L'estimation du formulaire ignorait l'impôt** laissé à zéro et annonçait un net trop élevé ;
  elle applique le barème ITS et la réduction pour parts IGR, comme le serveur.
- **Devise « XOF » en dur** dans le formulaire (estimation, soldes des comptes) ; montants du PDF
  séparés par des points (« 1.032.000 ») contre l'espace partout ailleurs.
- **Le mois par défaut de la liste** associait le mois précédent à l'année en cours : en janvier, la
  liste pointait sur décembre de l'année à venir.
- Refus en pages d'erreur (employé au contrat clôturé, compte de paiement invalide, période du livre
  de paie) devenus des messages lisibles.

---

## 5. Décisions prises

1. **« En congé » est déduit des congés validés** qui couvrent la journée, et non d'un statut sur la
   fiche. L'autre possibilité — faire basculer le statut à l'approbation puis le remettre à la fin —
   est plus lourde et sujette aux oublis.
2. **Une fiche employé avec historique ne se supprime jamais** ; seule une fiche créée par erreur
   (sans bulletin, congé, permission ni pointage) peut l'être, et le compte utilisateur est alors
   désactivé, pas supprimé.
3. **Les jours de congé sont comptés en jours calendaires**, week-ends compris (comportement
   d'origine). Des jours ouvrés supposeraient de décider du traitement des samedis et des jours fériés.
4. **Un congé ou une permission saisi par l'administration est validé d'emblée**, avec la mention
   « enregistré par l'administration ».
5. **Le solde de congés est décompté par année civile**, sur la date de début.
6. **Les retards** sont les arrivées après l'heure de début paramétrée, sans tolérance.
7. **« Présence » (temps sur place) sur l'écran du jour, « Travaillé » (pause déduite) dans le
   rapport** : deux colonnes distinctes, chacune expliquée.
8. **Les heures attendues sont comptées par jour pointé**, pas par jour ouvré : sans calendrier des
   jours fériés, compter les jours ouvrés produirait des écarts faux.
9. **Deux mesures de la masse salariale** coexistent, toutes deux affichées et expliquées : la somme
   des salaires de base des employés en poste (fiche) et le net versé (bulletins de la période).
10. **Le montant d'une catégorie salariale reste un repère** : il n'est pas appliqué automatiquement
    au salaire de l'employé.
11. **L'accueil RH garde ses cartes colorées**, remises à la charte : bandeau marine #273772,
    boutons « Ouvrir » jaunes #FADF2F, pastilles d'icône pleines.

---

## 6. Tests et vérifications

**212 tests, tous au vert.** Ajoutés pour le module RH :

| Fichier | Tests | Ce qui est protégé |
|---|---|---|
| `EmployeesPageTest` | 5 | État du jour lu dans les congés validés ; suppression refusée s'il y a un historique ; compte désactivé et non supprimé ; fin de contrat non répétable ; cloisonnement |
| `LeavesPageTest` | 7 | Congé enregistré et validé par l'administration ; solde annuel et chevauchements ; demande traitée une seule fois ; demande d'employé et solde affiché ; type utilisé non supprimable et nom unique ; calendrier du mois ; cloisonnement |
| `AttendancePageTest` | 4 | Code de pointage stable et renouvellement ; présents, absents et congés du jour ; recherche du rapport et horaires réglés à part ; cloisonnement |
| `PermissionRequestsPageTest` | 3 | Permission enregistrée par l'administration et chevauchement refusé ; demande d'employé traitée une seule fois ; cloisonnement |
| `RhDashboardTest` | 4 | Effectif indépendant de la période ; congés et masse salariale de la période ; catégorie utilisée non supprimable et renommage qui suit les fiches ; cloisonnement |
| `PayrollPageTest` | 6 | Génération et sortie d'argent ; bulletin regénéré qui corrige la caisse ; contrat clôturé refusé ; employés sans bulletin signalés et envoi par e-mail ; livre de paie ; cloisonnement |

Chaque écran a aussi été rendu dans un navigateur sans interface avec des données fictives :
- largeurs 1366, 1440 et 420 px, avec mesure de la largeur des tableaux ;
- exécution des filtres, onglets, fenêtres et calculs (soldes de congés, jours d'un congé, estimation
  d'un bulletin, choix des colonnes du personnel) ;
- export PDF du bulletin de paie, vérifié sur **une page A4**.

---

## 7. Données et structure

### Fichiers

- **Nouveaux** : `resources/views/admin/rh-employees.blade.php`,
  `resources/views/admin/rh-payroll-print.blade.php`.
- **Supprimé** : `resources/views/admin/rh-module.blade.php` (page d'attente générique).
- **Modifiés** : `RhController`, `EmployeeController`, les modèles `LeaveRequest`
  (relation `reviewer`) et `PermissionRequest` (motifs normalisés), et les douze vues du module.
- **Route ajoutée** : `admin.rh.qr.renew` (renouvellement du code de pointage).
- **Aucune migration** : la structure de la base n'a pas changé.

### Données de démonstration

`database/seeders/DemoComptaSeeder.php` (connexion `admin@diagoma.local`) crée désormais aussi :
- trois employés commerciaux, dont un sans compte utilisateur ;
- deux types de congé, quatre demandes (une en cours, une passée, une en attente) ;
- deux permissions (une acceptée, une en attente) ;
- les pointages QR des cinq derniers jours ouvrés, dont un départ manquant ;
- trois catégories salariales rattachées aux fiches ;
- les bulletins de paie des trois derniers mois.

```bash
php artisan db:seed --class=DemoComptaSeeder              # crée ou recrée les données
DEMO_PURGE=1 php artisan db:seed --class=DemoComptaSeeder # retire les données
```

---

## 8. Ce qui reste à faire

### Points ouverts du module RH

- **Comptabilité des salaires** : le paiement alimente la trésorerie (sortie de caisse ou de banque)
  mais **aucune écriture n'est passée au journal comptable** (661 salaires, 431 CNPS, 447 impôts
  retenus…). Même sujet que les mouvements de stock, à voir avec l'expert-comptable.
- **Accès des employés à leurs propres écrans** : congés et permissions passent par un système de
  permissions par module (colonne `permissions` de l'utilisateur). Un employé non administrateur doit
  recevoir la permission correspondante pour voir ses demandes ; à traiter dans l'écran Utilisateurs.
- **Jours fériés** : aucune table ne les porte, d'où le comptage en jours calendaires et les heures
  attendues par jour pointé.
- **Tolérance de retard** au pointage : non gérée, à décider si besoin.

### Modules suivants

1. Administration.
2. Succursales / Entreprises.
3. Utilisateurs et Paramètres.
4. Console super-admin.
5. Pages publiques : connexion, landing, démo.

À reprendre aussi : les **bandeaux hors charte** des accueils **Comptabilité** et **Administration**
(bleu vif #1d4ed8 au lieu du marine), signalés lors de la remise à la charte de l'accueil Commercial.

### Code non commité

Les documents imprimables de la Comptabilité et le module Commercial sont commités
(`f52e407`). Reste non commité le module **RH & Paie** : `RhController`, `EmployeeController`,
les modèles `LeaveRequest` et `PermissionRequest`, les quinze vues du module, la route de
renouvellement du QR, le seeder de démonstration, les six fichiers de tests et ce rapport.

---

## 9. Repères techniques ajoutés

- **État d'un employé** : ne jamais se fier à `employees.status` pour le congé ; lire les demandes
  de congé validées qui couvrent la journée. Le statut ne distingue que « en poste » et « contrat
  clôturé ».
- **Suppression d'une fiche** : toujours vérifier l'historique (paie, congés, permissions, pointages)
  avant de supprimer, et désactiver le compte utilisateur plutôt que le supprimer.
- **Bulletin regénéré** : passer par la reprise du mouvement de trésorerie existant
  (`PAY-<id>` en référence) avant d'en créer un nouveau.
- **PDF de la paie** : `rh-payroll-print` est rendu par DomPDF, qui ne connaît ni flexbox ni grille —
  mise en page en tableaux uniquement. Les écrans, eux, utilisent les composants `x-dg.*`.
- **Estimation du bulletin** : la copie JavaScript du barème ITS et des parts IGR doit rester
  synchronisée avec `RhController::calculateIncomeTax()` et `igrReduction()`.

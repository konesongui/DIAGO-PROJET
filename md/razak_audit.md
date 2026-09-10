# Audit du projet DIAGO

Date : 9 septembre 2026
Branche auditée : `main` (commit `12a9772`)

---

## 1. Vue d'ensemble

DIAGO est un **ERP web multi-entreprises** écrit en **Laravel 9**, en français, orienté marché
ivoirien. L'application est un monolithe Blade classique (pas de SPA, pas de framework JS front).

### Modules fonctionnels

| Module | Périmètre |
|---|---|
| **Commercial** | Devis, proformas, factures, bons de livraison, point de vente (POS), clients, fournisseurs, articles/services, objectifs commerciaux |
| **Stock** | Entrées, sorties, état du stock, inventaires |
| **Comptabilité** | Caisses, comptes bancaires, transferts, écritures, catégories de dépenses, immobilisations, factures fournisseurs |
| **RH** | Employés, départements, désignations, catégories salariales, paie et livre de paie, congés, demandes de permission, pointage par QR code |
| **Administration** | Visiteurs, appels, correspondances, réunions, documents |
| **Console super-admin** | Gestion des entreprises clientes, suivi des comptes, demandes de packs, réglages de la page vitrine, impersonation d'administrateurs |

### Intégrations externes

- **CinetPay** — encaissement de paiements (mobile money / carte), via `App\Services\CinetPayService`.
- **FNE** — certification fiscale des factures (Facture Normalisée Électronique), via `App\Services\FneCertificationService`, avec journal de certification dédié.
- **SMTP** `mail.diagomap.com` — envoi des bulletins de paie par courriel.

### Métriques

| Indicateur | Valeur |
|---|---|
| Code applicatif (app, resources, routes, database) | ~24 400 lignes PHP |
| Routes enregistrées | 238 |
| Modèles Eloquent | 51 |
| Contrôleurs | 22 |
| Migrations | 66 |
| Vues Blade | ~100 |
| Tests réels | **0** |
| Poids du dépôt Git | 143 Mo |

### Pile technique

- PHP 8.4 installé en local (le projet déclare `^8.0.2`)
- Laravel 9.52.22
- Sanctum 3, dompdf 2.0 (génération PDF des factures et bulletins)
- MySQL
- Vite 4 configuré mais **non utilisé** : aucune vue n'appelle `@vite`. Le front repose sur un
  thème Metronic pré-compilé servi depuis `public/assets/` (74 Mo).

---

## 2. Problèmes bloquants

### 2.1 Secrets exposés dans Git — CRITIQUE

Le projet **n'a aucun fichier `.gitignore`** et le fichier `.env` est **versionné dans l'historique
Git**. Il contient des secrets réels et non vides :

- `APP_KEY` — clé de chiffrement applicative (51 caractères)
- `FNE_API_KEY` — clé API de certification fiscale (46 caractères)
- `CINETPAY_API_KEY` et `CINETPAY_SECRET_KEY` (54 caractères chacune)
- `CINETPAY_SITE_ID`
- `MAIL_USERNAME` / `MAIL_PASSWORD` — boîte `info@diagomap.com`

Un fichier `cookies.txt` est également commité et peut contenir des jetons de session.

Ces secrets sont présents dans l'historique Git et doivent être considérés comme **compromis** dès
lors que le dépôt a été partagé ou hébergé chez un tiers.

**Actions requises, dans cet ordre :**

1. Créer un `.gitignore` Laravel standard (`.env`, `/vendor`, `/node_modules`, `/storage/*.key`,
   `cookies.txt`, `*.zip`).
2. Révoquer et régénérer **toutes** les clés listées ci-dessus côté fournisseurs (CinetPay, FNE,
   SMTP), puis `php artisan key:generate`.
3. Retirer `.env` et `cookies.txt` du suivi Git (`git rm --cached`).
4. Purger l'historique (`git filter-repo` ou BFG) si le dépôt est ou sera partagé.

### 2.2 Dépôt Git pollué

Le dossier `.git` pèse **143 Mo** pour un projet dont le code utile fait moins de 1 Mo. Sont
commités à tort :

| Élément | Poids | Fichiers |
|---|---|---|
| `vendor/` | 82 Mo | 7 687 |
| `laravel.zip` | 76 Mo | 1 |
| `public/assets/` (thème Metronic) | 74 Mo | — |

`laravel.zip` est un doublon complet du projet et n'a rien à faire dans le dépôt. `vendor/` doit
être reconstruit par `composer install`. Le thème `public/assets/` peut légitimement rester
versionné s'il n'est pas récupérable par npm, mais mérite d'être allégé (47 Mo de médias de
démonstration du thème, largement inutilisés).

### 2.3 Environnement local non fonctionnel

La base de données n'est pas joignable avec la configuration actuelle :

```
SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: NO)
```

Le `.env` pointe vers une base `laravel` avec l'utilisateur `root` sans mot de passe. Rien ne peut
être lancé ni testé tant qu'une base locale n'est pas provisionnée et les 66 migrations exécutées.

---

## 3. Risques techniques

### 3.1 Version de framework en fin de vie

**Laravel 9 n'est plus supporté depuis février 2024** : aucun correctif de sécurité n'est publié.
Par ailleurs, le PHP installé en local est **8.4**, au-delà de ce que Laravel 9 supporte
officiellement (8.0 à 8.2). Des dépréciations silencieuses sont probables.

Une montée vers Laravel 11 ou 12 est à planifier. Le chemin est franchissable : le code n'utilise
quasiment aucune API exotique, mais la structure `app/Http/Kernel.php` et `app/Console/Kernel.php`
devra être migrée vers le nouveau format `bootstrap/app.php`.

### 3.2 Configuration de production dangereuse

Le `.env` livré contient :

```
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
```

En production, `APP_DEBUG=true` expose la trace complète des erreurs **y compris les variables
d'environnement**, donc les secrets ci-dessus, à tout visiteur déclenchant une exception.

Autres réglages à revoir avant mise en production :

- `QUEUE_CONNECTION=sync` — les envois de courriel (bulletins de paie) bloquent la requête HTTP.
- `SESSION_DRIVER=file` et `CACHE_DRIVER=file` — acceptable sur un serveur unique, à passer sur
  Redis ou base en cas de montée en charge ou de déploiement multi-instances.
- `FNE_ENVIRONMENT=test` et `CINETPAY_MODE=test` — à basculer en production, avec les clés
  correspondantes.

### 3.3 Isolation multi-entreprises partiellement appliquée

L'isolation repose sur le trait `App\Models\Concerns\BelongsToEntreprise`, qui applique une portée
globale Eloquent filtrant sur `entreprise_id` et remplit automatiquement ce champ à la création.
Le mécanisme est propre et bien conçu.

Il est appliqué à **44 modèles sur 51**. Les 7 exceptions :

| Modèle | Statut |
|---|---|
| `Entreprise`, `User`, `Role`, `Succursale`, `LandingSetting` | Exclusion légitime (tables pivots ou globales) |
| `PackRequest` | **À vérifier** — demandes de packs commerciaux, potentiellement inter-entreprises |
| `AuditLog` | **À vérifier** — porte un `entreprise_id` mais n'est pas filtré |

Ces deux modèles méritent une revue manuelle pour confirmer qu'aucune fuite de données entre
entreprises clientes n'est possible.

### 3.4 Requête lourde sur chaque page

Le middleware `App\Http\Middleware\TenantContext` s'exécute sur **toutes** les routes
authentifiées. À chaque requête HTTP, il :

1. charge en mémoire **toutes** les lignes d'entrée de stock de l'entreprise (`StockEntryLine`),
2. charge **toutes** les lignes de sortie (`StockExitLine`),
3. recalcule les totaux article par article en PHP,
4. filtre les articles sous le seuil d'alerte de 5 unités.

Aucune agrégation SQL, aucune mise en cache, aucune limite. Le coût croît linéairement avec
l'historique de stock. Sur une entreprise à quelques milliers de mouvements, chaque affichage de
page paiera cette facture.

**Correctif recommandé :** remplacer par une requête `SELECT ... GROUP BY` agrégée côté base, et
mettre le résultat en cache pour quelques minutes par entreprise.

Le même middleware exécute aussi deux `COUNT` (permissions et congés en attente) et une requête
`Employee` par page, ce qui est plus acceptable mais reste cumulable.

---

## 4. Qualité du code

### 4.1 Absence totale de tests

Les deux seuls fichiers de test présents sont les exemples générés par Laravel :

- `tests/Unit/ExampleTest.php` → `test_that_true_is_true()`
- `tests/Feature/ExampleTest.php` → `test_the_application_returns_a_successful_response()`

C'est le manque le plus coûteux à moyen terme. Le logiciel calcule des **bulletins de paie**, des
**totaux de stock** et **certifie des factures auprès de l'administration fiscale**. Une régression
silencieuse sur l'un de ces calculs a des conséquences légales et financières directes.

**Priorité de couverture :**

1. Calcul de paie (`RhController`, modèle `Payroll`) — salaires, primes, retenues.
2. Calcul de stock (entrées moins sorties, seuils d'alerte).
3. Totaux de facturation (HT, TVA, TTC) sur devis, proformas, factures et POS.
4. Isolation multi-entreprises — un test vérifiant qu'un utilisateur d'une entreprise A ne peut
   accéder à aucune donnée d'une entreprise B.

### 4.2 Contrôleurs obèses

| Fichier | Lignes |
|---|---|
| `app/Http/Controllers/Admin/CommercialController.php` | 1 975 |
| `app/Http/Controllers/Admin/ComptabiliteController.php` | 1 824 |
| `app/Http/Controllers/Admin/RhController.php` | 899 |
| `app/Http/Controllers/Admin/SettingController.php` | 649 |

La logique métier (calculs de totaux, gestion de stock, génération de documents) est mélangée à la
couche HTTP. Ces classes sont difficiles à tester et à faire évoluer à plusieurs.

**Refactoring recommandé :** découper par ressource (un contrôleur pour les devis, un pour les
factures, un pour le stock, etc.) et extraire les calculs dans des classes de service dédiées,
sur le modèle de ce qui est déjà fait pour `CinetPayService` et `FneCertificationService`.

### 4.3 Vues

- `resources/views/admin/comptabilite-module.blade.php` fait **2 320 lignes** et devrait être
  découpé en composants Blade.
- `resources/views/admin/layout.blade.php` fait 1 568 lignes.
- `resources/views/dashboard.blade copy.php` est un résidu de copier-coller **à supprimer**.

### 4.4 Points positifs

Le code n'est pas négligé, plusieurs bonnes pratiques sont respectées :

- **Validation systématique** — 88 appels à `validate()` répartis sur 15 contrôleurs. Aucune
  affectation de masse via `$request->all()`.
- **Aucun `dd()`, `dump()` ou `var_dump()` oublié** dans le code applicatif ou les vues.
- **Quasi aucune requête SQL brute** — un seul fichier utilise `DB::raw` ou `whereRaw`.
- **Impersonation correctement encadrée** — la fonction permettant au super-admin de prendre la
  main sur un compte administrateur vérifie le rôle et l'état actif de la cible, interdit les
  sessions imbriquées, **exige un motif écrit** de 5 caractères minimum, régénère la session, et
  journalise début et fin dans un journal d'audit avec l'adresse IP. C'est bien fait.
- **Middlewares dédiés** `SuperAdmin` et `TenantContext`, correctement appliqués par groupes de
  routes.
- **Internationalisation** amorcée (français et anglais, avec bascule par entreprise).

---

## 5. Plan d'action recommandé

### Immédiat (sécurité)

1. Créer le `.gitignore`.
2. Révoquer et régénérer tous les secrets exposés (CinetPay, FNE, SMTP, `APP_KEY`).
3. Retirer `.env`, `cookies.txt`, `vendor/` et `laravel.zip` du suivi Git.
4. Purger l'historique Git si le dépôt est partagé.

### Court terme (mise en route)

5. Provisionner une base MySQL locale, un `.env` propre, exécuter les migrations et les seeders.
6. Vérifier que `APP_DEBUG=false` en production.
7. Supprimer `dashboard.blade copy.php`.

### Moyen terme (solidité)

8. Écrire les tests de non-régression sur la paie, le stock, la facturation et l'isolation
   multi-entreprises.
9. Corriger le calcul de stock du middleware `TenantContext` (agrégation SQL plus cache).
10. Vérifier l'isolation des modèles `PackRequest` et `AuditLog`.
11. Découper `CommercialController` et `ComptabiliteController`.

### Long terme

12. Migrer de Laravel 9 vers Laravel 11 ou 12.
13. Alléger `public/assets/` des médias de démonstration du thème.
14. Passer les files d'attente en asynchrone pour les envois de courriel.

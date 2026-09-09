# Nettoyage du projet — Fichiers supprimés

**Date :** 9 septembre 2026
**Auteur :** Razak

Ce document liste les fichiers retirés du projet, la raison de chaque suppression, et la
vérification qui a été faite avant de supprimer. Il indique aussi ce que j'ai **volontairement
conservé** malgré les apparences.

---

## Résultat

| | Avant | Après |
|---|---|---|
| Taille du projet (hors `vendor` et historique) | 152 Mo | **52 Mo** |

**100 Mo libérés**, sans aucune perte de fonctionnalité.

---

## 1. Méthode

Aucun fichier n'a été supprimé sur sa seule apparence. Pour chacun, j'ai d'abord cherché s'il
était référencé quelque part dans le code, les routes, les vues ou les feuilles de style. Seuls
les fichiers à **zéro référence** ont été retirés.

Après suppression, j'ai relancé l'application, ouvert une session avec un compte administrateur
et parcouru toutes les pages des modules pour confirmer que rien n'était cassé.

---

## 2. Fichiers supprimés

### 2.1 Archive en double — 76 Mo

**`laravel.zip`**

Une archive contenant une copie complète du projet, enregistrée dans le projet lui-même. Elle
faisait à elle seule la moitié du poids du dépôt. Le code est déjà versionné, cette copie
n'apportait rien.

### 2.2 Photos de démonstration du thème — 25 Mo

**`public/assets/media/stock/`**

Le thème graphique acheté est livré avec une banque de photos génériques destinées à ses pages
de démonstration, rangées dans des dossiers nommés par dimensions (`1600x800`, `500x600`).

**Vérification :** j'ai cherché le dossier dans les vues, les feuilles de style, les scripts et
les greffons du thème. Zéro référence. J'ai également vérifié qu'aucune feuille de style
n'utilisait de chemin relatif vers ce dossier, et testé plusieurs noms de fichiers un par un.
Aucun n'apparaît nulle part.

### 2.3 Fichier de session laissé à la racine

**`cookies.txt`**

Un fichier de session de test oublié à la racine. Au-delà de l'inutilité, il pouvait contenir un
jeton de connexion valide. Sa suppression était recommandée dans le rapport d'audit.

### 2.4 Code mort : un tableau de bord jamais utilisé

**`app/Http/Controllers/DashboardController.php`**
**`resources/views/dashboard.blade.php`**

Ce contrôleur était bien importé dans le fichier des routes, ce qui donnait l'impression qu'il
servait. En réalité **aucune route ne pointait dessus** : les quatre adresses de tableau de bord
sont toutes servies par le contrôleur du dossier `Admin`.

La vue qu'il affichait n'était donc jamais rendue non plus.

**Vérification :** j'ai listé les 238 routes de l'application et confirmé qu'aucune ne
référençait ce contrôleur. J'ai ensuite retiré son import devenu inutile dans le fichier des
routes, puis rechargé la liste : toujours 238 routes, aucune erreur.

### 2.5 Résidus de copier-coller et pages orphelines

**`resources/views/dashboard.blade copy.php`**

Un fichier dupliqué dont le nom contient le mot « copy » et un espace. Aucune référence.

**`resources/views/welcome.blade.php`**

La page d'accueil livrée par défaut avec le socle technique. Le projet a sa propre page d'accueil
qui, elle, est bien utilisée. Cette page-ci n'était appelée nulle part.

**Vérification :** après suppression, j'ai chargé la page d'accueil publique en visiteur non
connecté. Elle s'affiche correctement, avec son titre et son contenu complet.

### 2.6 Sauvegarde devenue redondante

**`.env.backup-mysql`**

La sauvegarde de la configuration MySQL que j'avais faite avant la migration vers PostgreSQL.

**Vérification :** l'historique du code contient déjà cette configuration d'origine, elle reste
donc récupérable. La sauvegarde faisait doublon, et contenait des mots de passe.

### 2.7 Caches et journaux régénérables

**`storage/framework/views/*.php`** — 21 pages compilées, reconstruites automatiquement à la
première visite.
**`bootstrap/cache/*.php`** — cache de démarrage, reconstruit automatiquement.
**`storage/logs/laravel.log`** — journal d'erreurs accumulé pendant le développement.
**`.phpunit.result.cache`** — cache de l'outil de tests.

Ces fichiers se recréent seuls. Les fichiers `.gitignore` internes à ces dossiers ont été
préservés, car ils sont nécessaires au bon fonctionnement du projet.

---

## 3. Ce que j'ai choisi de NE PAS supprimer

Je préfère signaler que supprimer à l'aveugle. Les éléments suivants sont probablement inutiles,
mais je n'ai pas pu le prouver, et une suppression erronée casserait l'affichage sans message
d'erreur visible.

### 3.1 Autres médias du thème — environ 10 Mo

`public/assets/media/svg`, `misc`, `flags`, `icons`

Ma recherche ne trouve aucune référence à ces dossiers. Mais contrairement aux photos, ce type
de ressource est souvent appelé dynamiquement par les scripts du thème, par exemple un drapeau
choisi selon la langue. Le risque est une image manquante quelque part dans l'interface.

**Recommandation :** les supprimer seulement après une vérification visuelle page par page.

### 3.2 Les deux fichiers de tests d'exemple

`tests/Unit/ExampleTest.php` et `tests/Feature/ExampleTest.php`

Ce sont les exemples fournis par défaut, sans valeur métier. Je les ai conservés car ils
constituent le point de départ des tests à écrire, dont le projet a un besoin urgent. Les
supprimer reviendrait à repartir d'un dossier vide.

### 3.3 Le fichier de présentation du projet

`README.md`

Il contient encore le texte de présentation du socle technique, sans rien de spécifique à DIAGO.
Il ne faut pas le supprimer mais le **réécrire** pour décrire le projet, son installation et sa
configuration.

### 3.4 L'outil de reprise des anciennes données

`app/Console/Commands/ImportLegacyCme.php`

Il n'est appelé par aucune route et pourrait passer pour du code mort. C'est en réalité un outil
lancé manuellement pour récupérer les données de l'ancien système. Son sort dépend de la
décision à prendre sur la reprise de ces données.

---

## 4. Vérification finale

Après toutes les suppressions, application relancée et testée :

| Contrôle | Résultat |
|---|---|
| Nombre de routes chargées | 238, identique à avant |
| Page d'accueil publique (visiteur) | Affichée correctement |
| Connexion administrateur | Fonctionnelle |
| Tableau de bord, Comptabilité, Commercial | Accessibles |
| Ressources humaines, Administration | Accessibles |
| Utilisateurs, Réglages | Accessibles |

Un incident est survenu pendant l'opération : le serveur de développement s'est arrêté au moment
du vidage des caches. Il s'agit du comportement normal du serveur de test, qui ne supporte pas
que ses fichiers compilés disparaissent sous lui. Un simple redémarrage a suffi, et tous les
contrôles ci-dessus ont ensuite été repassés avec succès.

---

## 5. Point restant

Les fichiers sont supprimés du dossier de travail, mais **l'archive de 76 Mo et les 25 Mo de
photos restent présents dans l'historique du code**. Le dépôt continuera donc de peser lourd au
téléchargement tant que cet historique n'aura pas été nettoyé.

Cette opération est à mener en même temps que le retrait des mots de passe de l'historique,
décrit dans le rapport d'audit `razak_audit.md`.

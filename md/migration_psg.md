# Migration de la base de données : MySQL vers PostgreSQL

**Projet :** DIAGO
**Date :** 9 septembre 2026
**Auteur :** Razak

---

## 1. Objectif

Faire fonctionner l'application DIAGO sur PostgreSQL à la place de MySQL.

DIAGO est un logiciel de gestion d'entreprise qui regroupe la facturation, la comptabilité,
les ressources humaines, le stock et la paie. Toutes ces données sont stockées dans une base
de données. Il fallait changer le moteur de cette base sans rien casser.

---

## 2. Ce qui n'était pas encore fait

Au moment où j'ai repris le projet, **rien n'avait été commencé** sur cette migration.

L'application était entièrement configurée pour MySQL :

- La configuration pointait vers MySQL.
- Le code contenait plusieurs instructions écrites dans le langage propre à MySQL, que
  PostgreSQL ne sait pas lire.
- Aucun environnement PostgreSQL n'était en place pour le projet.
- Aucun test n'avait été fait pour vérifier ce qui allait poser problème.

Autrement dit, l'application ne pouvait pas démarrer du tout sur PostgreSQL.

---

## 3. Ce que j'ai fait

### Analyse préalable

J'ai passé en revue l'ensemble du code pour repérer tout ce qui dépendait de MySQL. Cela
représente environ 24 000 lignes de code, 66 fichiers de structure de base et une centaine
de pages. Cette étape était indispensable pour ne rien laisser passer.

### Corrections apportées

**Le calcul du solde de caisse.** Une instruction utilisait des guillemets qui n'ont pas le
même sens dans les deux systèmes. Sous PostgreSQL, le logiciel cherchait une information qui
n'existe pas et s'arrêtait en erreur. Deux endroits corrigés, dans le module comptabilité.

**Les barres de recherche.** C'est le problème le plus important que j'ai trouvé. Sous MySQL,
chercher « MARIAM » ou « mariam » donne le même résultat. Sous PostgreSQL, non : la recherche
distingue les majuscules des minuscules. Sans correction, les 12 barres de recherche de
l'application auraient renvoyé « aucun résultat » sans afficher la moindre erreur. Un
utilisateur aurait simplement cru que la donnée n'existait pas. J'ai corrigé les 12.

**Une instruction de structure incompatible.** Un fichier de structure utilisait une commande
qui n'existe que sous MySQL. Je l'ai réécrite pour qu'elle s'adapte automatiquement au moteur
utilisé, ce qui permet de revenir en arrière si besoin.

**La configuration.** J'ai basculé les fichiers de configuration vers PostgreSQL et rendu
certains réglages modifiables sans toucher au code.

### Un bug découvert au passage

En testant l'installation des données de démonstration, j'ai trouvé une erreur qui n'a rien à
voir avec la migration : le programme créait des employés rattachés à une entreprise qui
n'existait pas encore. L'ordre des opérations était inversé. Ce bug était déjà présent sous
MySQL. Je l'ai corrigé, car sans cela l'application ne peut pas s'installer.

### Vérifications

Je n'ai pas voulu me contenter de « ça compile ». J'ai :

- installé la structure complète de la base sur PostgreSQL,
- chargé les données de démonstration,
- démarré l'application et **ouvert une vraie session** avec un compte administrateur,
- parcouru les pages de chaque module : tableau de bord, comptabilité, commercial, ressources
  humaines, administration, utilisateurs, réglages,
- testé une recherche en majuscules, en minuscules, en casse mélangée et avec un accent.

Tout répond correctement.

---

## 4. Difficultés rencontrées

**Des pannes invisibles.** La difficulté principale n'était pas de faire fonctionner
l'application, mais de repérer ce qui allait échouer *en silence*. Le problème des recherches
en est l'exemple type : aucune erreur, aucun message, juste des résultats vides. Ce genre de
défaut ne se voit pas en testant vite fait, il se voit en cherchant volontairement à le
provoquer. C'est pour cela que j'ai fait des tests en conditions réelles plutôt que de me fier
au démarrage de l'application.

**Des droits d'accès limités.** Mon compte sur le serveur PostgreSQL n'a pas l'autorisation de
créer une nouvelle base de données. J'ai contourné en créant un espace de travail séparé à
l'intérieur d'une base existante. Cela m'a permis de tout valider immédiatement. Pour la mise
en production, il faudra créer une base dédiée, ce qui demande les accès administrateur du
serveur.

**Une base ancienne déjà présente.** J'ai découvert sur le serveur une base contenant 78 tables
aux noms français, qui correspond à l'**ancien** système, pas à ce projet. Elle semble déjà
avoir été transférée vers PostgreSQL par quelqu'un d'autre. Le projet contient par ailleurs un
outil de récupération de ces anciennes données, mais il est toujours réglé sur MySQL et ne
correspond pas exactement à ce que j'ai trouvé. Je n'ai rien touché, car cela sort du cadre de
ma mission telle que je l'ai comprise.

**Un environnement de départ inutilisable.** Avant de pouvoir tester quoi que ce soit, il a
fallu remettre le projet en état de marche : aucune base n'était accessible avec la
configuration fournie.

---

## 5. Résultat

L'application fonctionne sur PostgreSQL 16.

| Élément | État |
|---|---|
| Structure de la base (66 étapes) | Installée sans erreur |
| Données de démonstration | Chargées |
| Connexion à l'application | Fonctionnelle |
| Pages des modules testées | Toutes accessibles |
| Recherches | Corrigées et vérifiées |

Huit fichiers ont été modifiés, pour environ 70 lignes ajoutées et 30 retirées. C'est une
intervention volontairement légère : je n'ai touché qu'à ce qui bloquait réellement.

---

## 6. Ce qui reste à faire

1. **Créer une base PostgreSQL dédiée** pour la production. Demande les accès administrateur
   du serveur.
2. **Décider du sort des anciennes données** présentes sur le serveur : faut-il les reprendre
   dans le nouveau système ou non ?
3. **Sécurité, indépendant de cette migration mais urgent :** le fichier contenant les mots de
   passe et clés d'accès du projet est enregistré dans l'historique du code. Ces clés doivent
   être changées. Le détail figure dans le rapport d'audit `razak_audit.md`.

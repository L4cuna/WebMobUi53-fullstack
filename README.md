# HEIG-VD DévProdMéd Course - Mini-projet

Ce dépôt contient le mini-projet à réaliser dans le cadre du cours
_"[Développement de produit média (DévProdMéd)](https://github.com/heig-vd-devprodmed-course/heig-vd-devprodmed-course)"_
enseigné à la
[Haute Ecole d'Ingénierie et de Gestion du Canton de Vaud (HEIG-VD)](https://heig-vd.ch),
Suisse.

## Objectif du mini-projet

L'objectif de ce mini-projet est de créer un réseau social simple en utilisant le
framework [Laravel](https://laravel.com/). Ce projet permettra de mettre en pratique les concepts
appris dans le cours.

## Pré-requis

Afin de lancer ce projet, une stack compatible avec Laravel, est requise.

Voici les pré-requis nécessaires :

- PHP >= 8.0.
- Composer.
- Node.js et npm.
- Une base de données (MySQL, PostgreSQL, SQLite, etc.).
- Un serveur web (Apache, Nginx, etc.).

[Laravel Herd](https://helm.sh/docs/charts/laravel/) est recommandé pour une installation facile de Laravel et de ses dépendances.

## Développement local

Pour développer et tester le mini-projet en local, voici les étapes à suivre :

1. Forker ce dépôt

2. Installer les dépendances avec npm et Composer :

    ```bash
    npm install && npm run build

    composer install
    ```

3. Copier le fichier `.env.example` en `.env`.
4. Modifier les variables d'environnement si nécessaire (optionnel).
5. Générer la clé d'application Laravel :

    ```bash
    php artisan key:generate
    ```

6. Créer le lien symbolique pour les fichiers téléversés :

    ```bash
    php artisan storage:link
    ```

7. Créer la base de données et exécuter les migrations :

    ```bash
    php artisan migrate
    ```

    S'il est nécessaire de réinitialiser la base de données, utiliser la commande `php artisan migrate:reset` puis `php artisan migrate` à nouveau.

8. Optionnel : en mode développement, il est possible de peupler la base de données avec des données fictives :

    ```bash
    php artisan db:seed
    ```

9. Démarrer le serveur de développement Laravel :

    ```bash
    composer run dev
    ```

L'application sera accessible à l'adresse <http://127.0.0.1:8000>.

## Système de sondages

Ce projet inclut un système de sondages complet développé avec Vue.js 3 et Laravel 12.

### Fonctionnalités

- Création, édition et suppression de sondages
- Gestion des options de réponse
- Paramètres configurables : brouillon, choix multiples, résultats publics, durée
- Lien de partage avec token unique
- Vote avec unicité garantie (côté frontend et API)
- Résultats en temps réel via polling (toutes les 5 secondes)
- Graphique à barres des résultats
- Accès anonyme aux résultats si publics
- Modification du vote si autorisée par le créateur

### Architecture frontend

Deux applications Vue.js distinctes :
- **Dashboard** (`/polls/dashboard`) — gestion des sondages, réservé aux utilisateurs connectés
- **Page de vote** (`/polls/{token}`) — voter et consulter les résultats, accessible publiquement

### Comptes de test

Après `php artisan db:seed` :

| Email                | Mot de passe |
|----------------------|--------------|
| john.doe@example.com | password     |
| jane.doe@example.com | password     |

### Stack technique

- Backend : Laravel 12 + Sanctum (authentification par cookie de session)
- Frontend : Vue.js 3 + Vite + Tailwind CSS 4
- Base de données : SQLite
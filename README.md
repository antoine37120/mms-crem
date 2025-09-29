
# MMS-CREM

Une application Laravel de gestion de collections et d'archives utilisant Filament pour l'interface d'administration.
Le système permet de gérer des fonds, collections, corpus et éléments avec leurs types associés.

## Installation
```
bash
# Cloner le projet
git clone https://git.artefacts.coop/adupre/mms-crem.git
cd mms-crem

# Installation avec Sail (Docker)
composer install
cp .env.example .env
php artisan key:generate
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate

# Installation classique
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
```
## Technologies utilisées

- Laravel 12.31.1
- Filament 4.0 (interface d'administration)
- Livewire 3.6 avec Flux UI
- MariaDB
- Tailwind CSS 4.1
```


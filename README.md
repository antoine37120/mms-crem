
# MMS-CREM

Une application Laravel de gestion de collections et d'archives utilisant Filament pour l'interface d'administration.
Le système permet de gérer des fonds, collections, corpus et éléments avec leurs types associés.

## Prérequis Serveur (Linux)

Pour faire fonctionner l'application sur un serveur Linux, les dépendances suivantes sont nécessaires :

- **PHP 8.3** ou supérieur
- **Extensions PHP** : `bcmath`, `curl`, `exif`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`
- **Utilitaires Système** : 
    - `FFmpeg` & `FFprobe` (pour le traitement vidéo/audio et l'extraction de métadonnées)
    - `audiowaveform` (optionnel, pour la génération de formes d'onde audio)
- **Serveur de base de données** : MariaDB 10.11+ ou MySQL 8.0+
- **Gestionnaire de paquets** : Composer 2.x
- **Runtime** : Node.js 20.x & NPM
- **Serveur Web** : Nginx ou Apache
- **Utilitaire** : Supervisor (pour la gestion des jobs en arrière-plan)

## Installation

### 1. Cloner le projet
```bash
git clone https://git.artefacts.coop/adupre/mms-crem.git
cd mms-crem
```

### 2. Installation avec Sail (Docker)
```bash
composer install
cp .env.example .env
php artisan key:generate
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```

### 3. Installation classique sur serveur Linux
```bash
# Installation des dépendances PHP
composer install --optimize-autoloader --no-dev

# Installation des dépendances Node et build des assets
npm install
npm run build

# Configuration de l'environnement
cp .env.example .env
# /!\ Pensez à configurer les accès base de données dans le fichier .env
php artisan key:generate

# Migrations et liens de stockage
php artisan migrate --force
php artisan storage:link

# Optimisation du cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4. Configuration de FFmpeg

L'application nécessite FFmpeg pour le traitement des médias.

**Sur Ubuntu/Debian :**
```bash
sudo apt update
sudo apt install ffmpeg
```

**Configuration dans l'application :**
Les chemins vers les binaires FFmpeg peuvent être configurés :
1. Soit dans le fichier `.env` via les variables `FFMPEG_BINARIES` et `FFPROBE_BINARIES`.
2. Soit directement dans l'interface d'administration sous **Administration > MMS Settings**.

Si FFmpeg est installé globalement et accessible dans le PATH du serveur, la configuration peut rester vide.

## Gestion des Jobs avec Supervisor

L'application utilise des files d'attente (queues) pour le traitement des médias. Il est fortement recommandé d'utiliser **Supervisor** sur un serveur de production pour maintenir le worker actif.

### Exemple de configuration Supervisor
Créez un fichier `/etc/supervisor/conf.d/mms-crem-worker.conf` :

```ini
[program:mms-crem-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mms-crem/artisan queue:work --queue=media_processing,default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/mms-crem/storage/logs/worker.log
stopwaitsecs=3600
```

*Note : Ajustez le chemin `/var/www/mms-crem` et l'utilisateur `www-data` selon votre configuration.*

Après avoir créé le fichier, activez la configuration :
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mms-crem-worker:*
```

## Technologies utilisées

- Laravel 12.31.1
- Filament 4.0 (interface d'administration)
- Livewire 3.6 avec Flux UI
- MariaDB
- Tailwind CSS 4.1


## Commandes utiles 
Recalculer le md5 des fichiers : 
```bash
php artisan items:calculate-md5 --force
```

#!/usr/bin/env bash
# ==============================================================================
# Script de déploiement — mms-crem (Huma-Num / Rocky Linux)
# Usage : bash deploy.sh [options]
#         bash /var/www/mms-crem/deploy.sh [options]
#         bash /home/antdupre/deploy-mms-crem.sh [options]
#
# Options :
#   --no-assets      Saute npm ci + npm run build
#   --no-maintenance Ne pas passer en mode maintenance
#   --fresh          Utilise migrate:fresh (⚠ supprime toutes les données)
#   --seed           Seed la base après migration (nécessite --fresh)
#   --no-rollback    Pas de rollback si migration échoue
# ==============================================================================

set -euo pipefail

# ─── Couleurs ─────────────────────────────────────────────────────────────────
ROUGE='\033[0;31m'
VERT='\033[0;32m'
JAUNE='\033[1;33m'
BLEU='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

info()    { echo -e "${BLEU}[INFO]${NC}  $1"; }
success() { echo -e "${VERT}[OK]${NC}    $1"; }
warn()    { echo -e "${JAUNE}[WARN]${NC}  $1"; }
error()   { echo -e "${ROUGE}[ERR]${NC}   $1"; }
step()    { echo -e "\n${CYAN}━━━ $1 ━━━${NC}"; }

# ─── Options ──────────────────────────────────────────────────────────────────
SKIP_ASSETS=false
NO_MAINTENANCE=false
MIGRATE_FRESH=false
SEED=false
ROLLBACK=true

for arg in "$@"; do
    case "$arg" in
        --no-assets)      SKIP_ASSETS=true ;;
        --no-maintenance)  NO_MAINTENANCE=true ;;
        --fresh)          MIGRATE_FRESH=true ;;
        --seed)           SEED=true ;;
        --no-rollback)    ROLLBACK=false ;;
        *)                warn "Option ignorée : $arg" ;;
    esac
done

# ─── Variables ────────────────────────────────────────────────────────────────
PROJECT_DIR="/var/www/mms-crem"
PHP_USER="nginx"
LOG_FILE="/tmp/deploy-$(date +%Y%m%d-%H%M%S).log"

# ─── Trap : toujours remettre en ligne, même en cas d'erreur ──────────────────
cleanup() {
    local exit_code=$?
    cd "$PROJECT_DIR" 2>/dev/null || true
    if [[ "$NO_MAINTENANCE" == false ]]; then
        php artisan up --quiet 2>/dev/null || true
        echo -e "${VERT}[OK]${NC}    Application remise en ligne (cleanup)."
    fi
    if [[ $exit_code -ne 0 ]]; then
        echo -e "${ROUGE}[ERR]${NC}   Script interrompu (code $exit_code)."
        echo -e "${JAUNE}[WARN]${NC}  Log : $LOG_FILE"
    fi
    exit $exit_code
}
trap cleanup EXIT

# ─── Vérifications ────────────────────────────────────────────────────────────
cd "$PROJECT_DIR"

# Vérifier l'accès au dépôt git (après chown, l'utilisateur courant n'a pas les droits)
if [[ ! -r ".git/HEAD" ]]; then
    if sudo -u nginx git rev-parse HEAD &>/dev/null; then
        GIT="sudo -u nginx git"
        info "Accès git via sudo -u nginx."
    else
        error "Impossible d'accéder au dépôt git."
        exit 1
    fi
else
    GIT="git"
fi

if [[ ! -d "$PROJECT_DIR/.git" ]]; then
    error "Le dossier $PROJECT_DIR n'est pas un dépôt git."
    exit 1
fi

cd "$PROJECT_DIR"

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║           Déploiement mms-crem                          ║"
echo "║           $(date '+%Y-%m-%d %H:%M:%S')                    ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

# ─── 1. Mode maintenance ─────────────────────────────────────────────────────
if [[ "$NO_MAINTENANCE" == false ]]; then
    step "Mode maintenance"
    php artisan down --retry=10 --quiet 2>&1 | tee -a "$LOG_FILE"
    success "Application en maintenance."
fi

# ─── 2. Git pull ─────────────────────────────────────────────────────────────
step "Récupération des dernières modifications"

CURRENT_HASH=$($GIT rev-parse HEAD)

if ! $GIT pull --rebase 2>&1 | tee -a "$LOG_FILE"; then
    error "git pull a échoué. Restauration..."
    $GIT reset --hard "$CURRENT_HASH"
    exit 1
fi

NEW_HASH=$($GIT rev-parse HEAD)

if [[ "$CURRENT_HASH" == "$NEW_HASH" ]]; then
    warn "Aucun changement détecté (déjà à jour)."
else
    success "Nouveau commit : $($GIT log --oneline -1)"
fi

# ─── 3. Composer ─────────────────────────────────────────────────────────────
step "Installation des dépendances PHP"

if [[ -f "composer.lock" ]]; then
    CHANGED=$($GIT diff "$CURRENT_HASH" -- composer.lock 2>/dev/null || echo "")
    if [[ -z "$CHANGED" && "$CURRENT_HASH" == "$NEW_HASH" ]]; then
        info "composer.lock inchangé — dump-autoload seulement."
        composer dump-autoload --no-dev --optimize 2>&1 | tee -a "$LOG_FILE"
    else
        info "composer.lock modifié — installation complète."
        composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tee -a "$LOG_FILE"
    fi
else
    composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tee -a "$LOG_FILE"
fi
success "Dépendances PHP à jour."

# ─── 4. Migrations ────────────────────────────────────────────────────────────
step "Migrations"

MIGRATE_ARGS=(migrate --force)
if [[ "$MIGRATE_FRESH" == true ]]; then
    MIGRATE_ARGS=(migrate:fresh --force)
    if [[ "$SEED" == true ]]; then
        MIGRATE_ARGS+=(--seed)
    fi
    warn "⚠ Migration fraîche ! Toutes les données seront supprimées."
    sleep 2
fi

if [[ "$ROLLBACK" == true && "$MIGRATE_FRESH" == false ]]; then
    DB_PASSWORD=$(grep '^DB_PASSWORD=' /var/www/mms-crem/.env | head -1 | cut -d= -f2-)
    DB_USER=$(grep '^DB_USERNAME=' /var/www/mms-crem/.env | head -1 | cut -d= -f2-)
    DB_NAME=$(grep '^DB_DATABASE=' /var/www/mms-crem/.env | head -1 | cut -d= -f2-)
    CURRENT_BATCH=$(mysql -h 127.0.0.1 -u "${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" -N -e "SELECT COALESCE(MAX(batch), 0) FROM migrations" 2>/dev/null || echo "0")
fi

if php artisan "${MIGRATE_ARGS[@]}" 2>&1 | tee -a "$LOG_FILE"; then
    success "Migrations exécutées."
else
    error "Migration échouée."
    if [[ "$ROLLBACK" == true && "$CURRENT_BATCH" -gt 0 ]]; then
        warn "Rollback du batch $CURRENT_BATCH..."
        php artisan migrate:rollback --batch="$CURRENT_BATCH" --force 2>&1 | tee -a "$LOG_FILE"
    fi
    exit 1
fi

# ─── 5. Assets (npm) ──────────────────────────────────────────────────────────
if [[ "$SKIP_ASSETS" == false ]]; then
    step "Compilation des assets"

    if [[ -f "package-lock.json" || -f "yarn.lock" ]]; then
        CHANGED=$($GIT diff "$CURRENT_HASH" -- package.json package-lock.json yarn.lock 2>/dev/null || echo "")

        if [[ -z "$CHANGED" && "$CURRENT_HASH" == "$NEW_HASH" ]] && [[ -d "public/build" ]]; then
            info "Package.json inchangé et build existant — on saute."
        else
            if [[ -n "$CHANGED" ]]; then
                info "Package.json modifié — réinstallation complète."
            fi
            npm ci --no-fund --no-audit 2>&1 | tee -a "$LOG_FILE" || {
                warn "npm ci a échoué, tentative npm install..."
                npm install 2>&1 | tee -a "$LOG_FILE"
            }
            npm run build 2>&1 | tee -a "$LOG_FILE"
            success "Assets compilés."
        fi
    else
        warn "Aucun lockfile npm/yarn trouvé. Build ignoré."
    fi
else
    info "Build des assets ignoré (--no-assets)."
fi

# ─── 6. Cache ─────────────────────────────────────────────────────────────────
step "Optimisation du cache"
php artisan optimize:clear 2>&1 | tee -a "$LOG_FILE" || true
php artisan optimize 2>&1 | tee -a "$LOG_FILE"
success "Cache optimisé."

# ─── 7. Permissions ──────────────────────────────────────────────────────────
step "Permissions"
sudo chown -R ${PHP_USER}:${PHP_USER} "${PROJECT_DIR}" 2>/dev/null || {
    warn "Impossible de changer le propriétaire — vérifier sudo."
}
sudo chmod -R 755 storage bootstrap/cache public/build 2>/dev/null || true
success "Permissions appliquées."

# ─── 8. Queue worker ──────────────────────────────────────────────────────────
step "Redémarrage des workers queue"
if command -v supervisorctl &>/dev/null; then
    sudo supervisorctl restart mms-worker:* 2>&1 | tee -a "$LOG_FILE" || {
        warn "supervisorctl a échoué — tentative redémarrage du service..."
        sudo systemctl restart supervisord 2>/dev/null || true
    }
    success "Workers queue redémarrés."
elif systemctl is-active --quiet supervisord 2>/dev/null; then
    sudo systemctl restart supervisord
    success "Supervisord redémarré."
else
    warn "Supervisor non trouvé — redémarrage manuel nécessaire pour la queue."
fi

# ─── 9. PHP-FPM ───────────────────────────────────────────────────────────────
step "Redémarrage PHP-FPM"
sudo systemctl reload php-fpm 2>/dev/null || sudo systemctl restart php-fpm || {
    warn "Impossible de redémarrer PHP-FPM — vérifier manuellement."
}
success "PHP-FPM rechargé."

# ─── 10. Sortie du mode maintenance ───────────────────────────────────────────
step "Sortie du mode maintenance"
if [[ "$NO_MAINTENANCE" == false ]]; then
    php artisan up --quiet
fi
success "Application de nouveau en ligne."

# ─── Résumé ───────────────────────────────────────────────────────────────────
echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║              DÉPLOIEMENT TERMINÉ ✓                       ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""
echo "  Commit     : $($GIT log --oneline -1)"
echo "  Date       : $(date '+%Y-%m-%d %H:%M:%S')"
echo "  Log        : $LOG_FILE"
echo ""
echo "  Pour voir le log :"
echo "    cat $LOG_FILE"
echo ""

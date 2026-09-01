#!/usr/bin/env bash
# Idempotent local shop bring-up. Never deletes volumes, config, or var/.
set -euo pipefail
if [[ "$-" == *x* ]]; then
    printf '%s\n' 'dev-setup: refusing to run with xtrace enabled (passwords would leak).' >&2
    exit 1
fi
set +x

# Git Bash otherwise rewrites /var/www/... into C:/Program Files/Git/var/www/...
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

MODULE_YAML="var/configuration/shops/1/modules/oxidshipping.yaml"
SHOP_URL="http://127.0.0.1:8080"
SHOP_ID="1"

die() {
    printf '%s\n' "dev-setup: $*" >&2
    exit 1
}

log() {
    printf '%s\n' "$*"
}

is_sql_ident() {
    case "$1" in
        ''|*[!A-Za-z0-9_]*) return 1 ;;
    esac
    return 0
}

is_placeholder_secret() {
    local folded
    [ -n "$1" ] || return 0
    folded="$(printf '%s' "$1" | tr '[:upper:]' '[:lower:]')"
    case "$folded" in
        changeme|admin|password) return 0 ;;
    esac
    return 1
}

strip_quotes() {
    local val="$1"
    case "$val" in
        \"*\") val="${val#\"}"; val="${val%\"}" ;;
        \'*\') val="${val#\'}"; val="${val%\'}" ;;
    esac
    printf '%s' "$val"
}

load_dot_env() {
    local line key val
    MYSQL_ROOT_PASSWORD=""
    MYSQL_DATABASE=""
    MYSQL_USER=""
    MYSQL_PASSWORD=""
    ADMIN_EMAIL=""
    ADMIN_PASSWORD=""
    while IFS= read -r line || [ -n "$line" ]; do
        line="${line%$'\r'}"
        case "$line" in
            ''|'#'*) continue ;;
        esac
        key="${line%%=*}"
        val="${line#*=}"
        [ "$key" != "$line" ] || continue
        val="$(strip_quotes "$val")"
        case "$key" in
            MYSQL_ROOT_PASSWORD) MYSQL_ROOT_PASSWORD="$val" ;;
            MYSQL_DATABASE) MYSQL_DATABASE="$val" ;;
            MYSQL_USER) MYSQL_USER="$val" ;;
            MYSQL_PASSWORD) MYSQL_PASSWORD="$val" ;;
            ADMIN_EMAIL) ADMIN_EMAIL="$val" ;;
            ADMIN_PASSWORD) ADMIN_PASSWORD="$val" ;;
        esac
    done < .env
}

require_mysql_env() {
    local name
    for name in MYSQL_ROOT_PASSWORD MYSQL_DATABASE MYSQL_USER MYSQL_PASSWORD; do
        if [ -z "${!name}" ]; then
            die "fill ${name} in .env, then run this script again."
        fi
    done
    is_sql_ident "$MYSQL_DATABASE" || die "MYSQL_DATABASE must be a SQL identifier."
    is_sql_ident "$MYSQL_USER" || die "MYSQL_USER must be a SQL identifier."
}

config_db_name() {
    local name=""
    [ -f source/config.inc.php ] || { printf '%s' ""; return 0; }
    name="$(sed -n "s/^\$this->dbName[[:space:]]*=[[:space:]]*['\"]\\([^'\"]*\\)['\"].*/\\1/p" source/config.inc.php | head -n 1)"
    case "$name" in
        ''|'<'*) printf '%s' "" ;;
        *) printf '%s' "$name" ;;
    esac
}

mysql_query() {
    local out
    out="$(
        docker compose exec -T \
            -e MYSQL_PWD="$MYSQL_PASSWORD" \
            mysql \
            mysql --protocol=TCP -h127.0.0.1 -u"$MYSQL_USER" -N -s --database="$MYSQL_DATABASE" -e "$1"
    )"
    out="${out//$'\r'/}"
    printf '%s' "$out"
}

table_exists() {
    local n
    n="$(mysql_query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$1'")"
    [ "$n" = "1" ]
}

shop_row_count() {
    if ! table_exists oxshops; then
        printf '%s' "0"
        return 0
    fi
    mysql_query "SELECT COUNT(*) FROM oxshops"
}

admin_count() {
    if ! table_exists oxuser; then
        printf '%s' "0"
        return 0
    fi
    mysql_query "SELECT COUNT(*) FROM oxuser WHERE OXRIGHTS = 'malladmin'"
}

tariff_table_exists() {
    table_exists oxidshipping_tariff
}

tariff_active_count() {
    if ! tariff_table_exists; then
        printf '%s' "0"
        return 0
    fi
    mysql_query "SELECT COUNT(*) FROM oxidshipping_tariff WHERE OXACTIVEFLAG = 1 AND OXSHOPID = ${SHOP_ID}"
}

yaml_activated() {
    [ -f "$MODULE_YAML" ] || return 1
    grep -Eq '^activated:[[:space:]]*true[[:space:]]*$' "$MODULE_YAML"
}

compose_php() {
    docker compose exec -T php "$@"
}

php_console() {
    compose_php vendor/bin/oe-console "$@"
}

ensure_env_file() {
    if [ -f .env ]; then
        return 0
    fi
    cp .env.example .env
    die "created .env from .env.example. Fill MYSQL_ROOT_PASSWORD, MYSQL_PASSWORD, and ADMIN_PASSWORD, then run this script again."
}

report_shop_mismatch() {
    local db_name="$1"
    local shops="$2"
    if [ -f source/config.inc.php ]; then
        if [ -z "$db_name" ]; then
            log "source/config.inc.php exists but dbName is empty."
        else
            log "source/config.inc.php exists (dbName is set)."
        fi
    else
        log "source/config.inc.php is missing."
    fi
    log "oxshops row count: ${shops}."
    die "disk and database diverged for the shop install. Delete source/config.inc.php or restore the MySQL volume; this script will not reinstall over a mismatch."
}

report_module_mismatch() {
    local yaml_state="$1"
    local table_state="$2"
    local active="$3"
    log "module yaml (${MODULE_YAML}): ${yaml_state}."
    log "oxidshipping_tariff table: ${table_state}; active rows for shop ${SHOP_ID}: ${active}."
    die "disk and database diverged for oxidshipping. Delete var/configuration or restore the MySQL volume; this script will not install over a mismatch."
}

ensure_env_file
load_dot_env
require_mysql_env

log "Starting containers…"
docker compose up -d --wait --wait-timeout 180

shops="$(shop_row_count)"
db_name="$(config_db_name)"
config_ok=0
[ -f source/config.inc.php ] && [ -n "$db_name" ] && config_ok=1
db_ok=0
[ "$shops" -gt 0 ] && db_ok=1

if [ "$config_ok" -eq 1 ] && [ "$db_ok" -eq 1 ]; then
    log "Shop already installed; skipping oe:setup:shop."
    shop_needed=0
elif [ "$db_ok" -eq 0 ] && { [ ! -f source/config.inc.php ] || [ -z "$db_name" ]; }; then
    shop_needed=1
else
    report_shop_mismatch "$db_name" "$shops"
fi

log "Installing Composer dependencies…"
# Bind mounts on Docker Desktop/Windows plus the OXID composer plugin
# corrupt parallel zip downloads; one HTTP job and a pre-created vendor/.
mkdir -p vendor
docker compose exec -T -e COMPOSER_MAX_PARALLEL_HTTP=1 php composer install --no-interaction --prefer-dist

if [ "$shop_needed" -eq 1 ]; then
    [ -f source/config.inc.php.dist ] || die "source/config.inc.php.dist is missing."
    # oe-console bootstraps the shop and will not start without this file.
    cp source/config.inc.php.dist source/config.inc.php
    log "Installing the shop…"
    php_console oe:setup:shop \
        --db-host=mysql \
        --db-port=3306 \
        --db-name="$MYSQL_DATABASE" \
        --db-user="$MYSQL_USER" \
        --db-password="$MYSQL_PASSWORD" \
        --shop-url="$SHOP_URL" \
        --shop-directory=/var/www/html/source \
        --compile-directory=/var/www/html/source/tmp \
        --language=de
fi

admins="$(admin_count)"
if [ "$admins" -gt 0 ]; then
    log "Admin user already exists; skipping oe:admin:create-user."
else
    if [ -z "$ADMIN_EMAIL" ] || is_placeholder_secret "$ADMIN_PASSWORD"; then
        die "fill ADMIN_PASSWORD in .env (and ADMIN_EMAIL if empty), then run this script again. An admin account will not be created with an empty or default password."
    fi
    log "Creating the admin user…"
    php_console oe:admin:create-user \
        --admin-email="$ADMIN_EMAIL" \
        --admin-password="$ADMIN_PASSWORD"
fi

log "Activating the Apex theme…"
php_console oe:theme:activate apex

yaml_ok=0
yaml_activated && yaml_ok=1
tariff_ok=0
active_tariffs="$(tariff_active_count)"
[ "$active_tariffs" -gt 0 ] && tariff_ok=1
table_ok=0
tariff_table_exists && table_ok=1

if [ "$yaml_ok" -eq 1 ] && [ "$tariff_ok" -eq 1 ]; then
    log "Module oxidshipping already installed and active; skipping install/activate."
    module_needed=0
elif [ "$yaml_ok" -eq 0 ] && [ "$tariff_ok" -eq 0 ]; then
    # Composer already writes the yaml with activated: false; that is not a leftover stand.
    module_needed=1
else
    yaml_state="missing"
    [ -f "$MODULE_YAML" ] && yaml_state="present, activated=$( [ "$yaml_ok" -eq 1 ] && echo true || echo false )"
    table_state="missing"
    [ "$table_ok" -eq 1 ] && table_state="present"
    report_module_mismatch "$yaml_state" "$table_state" "$active_tariffs"
fi

if [ "$module_needed" -eq 1 ]; then
    log "Installing module oxidshipping…"
    php_console oe:module:install vendor/oxid-shipping/module
fi

log "Running oxidshipping migrations…"
# OXID 7.5 does not register oe:migration:* on oe-console; this is the same Doctrine wrapper.
compose_php vendor/bin/oe-eshop-db_migrate migrations:migrate oxidshipping

if [ "$module_needed" -eq 1 ]; then
    log "Activating module oxidshipping…"
    php_console oe:module:activate oxidshipping
fi

log "Seeding the catalog…"
compose_php php bin/seed-catalog.php

log "Clearing the shop cache…"
php_console oe:cache:clear

log ""
log "Shop:  ${SHOP_URL}"
log "Admin: ${SHOP_URL}/admin/"
log "Done."

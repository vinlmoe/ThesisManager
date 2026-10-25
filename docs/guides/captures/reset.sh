#!/bin/sh
# Remet à zéro le Moodle de test des captures puis charge le jeu de données.
# Usage : sh docs/guides/captures/reset.sh /chemin/vers/moodle
# Prérequis : config.php du Moodle de test (base PostgreSQL « moodle »), modules copiés dans mod/.
set -e
MOODLE=${1:-/home/user/moodle/moodle}
HERE=$(cd "$(dirname "$0")" && pwd)
DATAROOT=$(php -r "define('CLI_SCRIPT', true); define('ABORT_AFTER_CONFIG', true); require '$MOODLE/config.php'; echo \$CFG->dataroot;")
su postgres -c "psql -h /tmp -q -c 'DROP DATABASE IF EXISTS moodle;' -c \"CREATE DATABASE moodle OWNER moodle ENCODING 'UTF8' TEMPLATE template0;\""
find "$DATAROOT" -mindepth 1 -maxdepth 1 ! -name lang -exec rm -rf {} +
php "$MOODLE/admin/cli/install_database.php" --agree-license --lang=en --fullname="Thèses de test" \
    --shortname="Moodle test" --adminuser=admin --adminpass='Admin1234!' --adminemail=admin@example.com > /dev/null
MOODLE_DEBUG=1 php "$HERE/seed.php" "$MOODLE"

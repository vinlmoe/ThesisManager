#!/bin/sh
# Remet à zéro le Moodle de test des captures puis charge le jeu de données.
# Usage : sh docs/guides/captures/reset.sh /chemin/vers/moodle
# Prérequis : config.php du Moodle de test (base PostgreSQL), modules copiés dans mod/.
# La base, son propriétaire et l'hôte sont lus dans config.php : seule la base de ce Moodle est
# supprimée puis recréée.
set -e
MOODLE=${1:-/home/user/moodle/moodle}
HERE=$(cd "$(dirname "$0")" && pwd)
cfg() {
    php -r "define('CLI_SCRIPT', true); define('ABORT_AFTER_CONFIG', true); require '$MOODLE/config.php'; echo \$CFG->$1;"
}
DATAROOT=$(cfg dataroot)
DBNAME=$(cfg dbname)
DBUSER=$(cfg dbuser)
# Administration de la base par le compte postgres, sur le socket local (PGHOST=<répertoire du
# socket> si ce n'est pas celui par défaut, par exemple /tmp).
su postgres -c "PGHOST=${PGHOST:-} psql -q -c 'DROP DATABASE IF EXISTS \"$DBNAME\";' \
    -c \"CREATE DATABASE \\\"$DBNAME\\\" OWNER \\\"$DBUSER\\\" ENCODING 'UTF8' TEMPLATE template0;\""
find "$DATAROOT" -mindepth 1 -maxdepth 1 ! -name lang -exec rm -rf {} +
php "$MOODLE/admin/cli/install_database.php" --agree-license --lang=en --fullname="Thèses de test" \
    --shortname="Moodle test" --adminuser=admin --adminpass='Admin1234!' --adminemail=admin@example.com > /dev/null
MOODLE_DEBUG=1 php "$HERE/seed.php" "$MOODLE"

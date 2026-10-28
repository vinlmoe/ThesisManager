#!/usr/bin/env python3
"""Extraction en lecture seule des documents définitifs de la gestion des thèses (mod_these), pour
leur versement dans une GED.

Le script lit la base Moodle (PostgreSQL, par psql) et le dossier moodledata, copie chaque document
définitif dans un répertoire d'export et écrit un manifeste CSV. Il n'écrit jamais ni dans la base ni
dans moodledata.

- Contrôle d'intégrité : l'empreinte SHA-1 de chaque fichier doit être son contenthash Moodle.
- Documents scellés : l'empreinte SHA-256 du PDF est comparée à celles inscrites à l'historique du
  dossier (action « documentsealed »).
- Relances : un fichier déjà exporté avec la même empreinte est ignoré ; une nouvelle version (scellé
  refait après une invalidation) est exportée à côté, sans écraser l'ancienne.

Usage :
  PGHOST=... PGUSER=... PGPASSWORD=... PGDATABASE=moodle \\
  python3 extraction_ged.py --moodledata /var/moodledata --dest /srv/export-ged [--prefix mdl_]
                            [--since AAAA-MM-JJ]
"""

import argparse
import csv
import datetime
import hashlib
import os
import re
import shutil
import subprocess
import sys

# Zones dont le contenu ne change plus. Le manuscrit initial n'est retenu que sans manuscrit corrigé.
ZONES = ('sealed', 'pv', 'pvadjourned', 'subjectarchive', 'signedscan', 'corrections', 'manuscript')

QUERY = """
SELECT d.id AS dossierid, COALESCE(d.devenumber, '') AS devenumber, d.title, d.defensedate,
       d.defenseresult, COALESCE(d.mention, '') AS mention, u.username, u.lastname, u.firstname,
       f.filearea, f.filename, f.contenthash, f.filesize, f.timecreated, f.timemodified
  FROM {p}files f
  JOIN {p}context c ON c.id = f.contextid AND c.contextlevel = 70
  JOIN {p}course_modules cm ON cm.id = c.instanceid
  JOIN {p}modules m ON m.id = cm.module AND m.name = 'these'
  JOIN {p}these_dossier d ON d.id = f.itemid AND d.theseid = cm.instance
  JOIN {p}user u ON u.id = d.userid
 WHERE f.component = 'mod_these' AND f.filename <> '.'
   AND f.filearea IN ({zones})
   AND f.timemodified >= {since}
 ORDER BY d.id, f.filearea, f.filename
"""

SEALS = """
SELECT dossierid, remark FROM {p}these_log WHERE logaction = 'documentsealed'
"""

MANIFEST = ['dossierid', 'devenumber', 'nom', 'prenom', 'identifiant', 'titre', 'date_soutenance',
            'resultat', 'mention', 'zone', 'fichier', 'chemin_export', 'taille', 'sha256',
            'scelle_verifie', 'date_fichier', 'date_export']


def psql(sql):
    """Résultat d'une requête, en lecture seule, sous forme de dictionnaires."""
    command = ['psql', '--no-psqlrc', '--csv', '-v', 'ON_ERROR_STOP=1',
               '-c', 'SET default_transaction_read_only = on', '-c', sql]
    output = subprocess.run(command, check=True, capture_output=True, text=True).stdout
    lines = output.splitlines()
    # La première ligne est la réponse au SET.
    return list(csv.DictReader(lines[1:]))


def digest(path, algorithm):
    """Empreinte d'un fichier."""
    h = hashlib.new(algorithm)
    with open(path, 'rb') as stream:
        for block in iter(lambda: stream.read(1 << 20), b''):
            h.update(block)
    return h.hexdigest()


def safe(name):
    """Nom utilisable comme répertoire."""
    return re.sub(r'[^A-Za-z0-9._-]+', '_', name).strip('_') or 'sans-nom'


def main():
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument('--moodledata', required=True, help='dossier moodledata ($CFG->dataroot)')
    parser.add_argument('--dest', required=True, help="répertoire d'export")
    parser.add_argument('--prefix', default='mdl_', help='préfixe des tables ($CFG->prefix)')
    parser.add_argument('--since', help='seulement les fichiers modifiés depuis cette date (AAAA-MM-JJ)')
    args = parser.parse_args()

    since = 0
    if args.since:
        since = int(datetime.datetime.strptime(args.since, '%Y-%m-%d').timestamp())
    zones = ', '.join("'%s'" % zone for zone in ZONES)
    rows = psql(QUERY.format(p=args.prefix, zones=zones, since=since))

    # Empreintes SHA-256 des scellés, par dossier.
    seals = {}
    for row in psql(SEALS.format(p=args.prefix)):
        for value in re.findall(r'SHA-256 ([0-9a-f]{64})', row['remark'] or ''):
            seals.setdefault(row['dossierid'], set()).add(value)

    corrected = {row['dossierid'] for row in rows if row['filearea'] == 'corrections'}
    os.makedirs(args.dest, exist_ok=True)
    manifest = os.path.join(args.dest, 'manifeste.csv')
    new = not os.path.exists(manifest)
    errors = exported = skipped = 0
    now = datetime.datetime.now().isoformat(timespec='seconds')

    with open(manifest, 'a', newline='', encoding='utf-8') as out:
        writer = csv.DictWriter(out, fieldnames=MANIFEST)
        if new:
            writer.writeheader()
        for row in rows:
            if row['filearea'] == 'manuscript' and row['dossierid'] in corrected:
                continue
            h = row['contenthash']
            source = os.path.join(args.moodledata, 'filedir', h[0:2], h[2:4], h)
            if not os.path.isfile(source) or digest(source, 'sha1') != h:
                print('ERREUR dossier %s : %s/%s absent ou altéré' % (row['dossierid'], row['filearea'],
                                                                       row['filename']), file=sys.stderr)
                errors += 1
                continue
            sha256 = digest(source, 'sha256')
            verified = ''
            if row['filearea'] == 'sealed':
                verified = 'oui' if sha256 in seals.get(row['dossierid'], set()) else 'NON'
                if verified == 'NON':
                    print('ATTENTION dossier %s : empreinte de %s absente de l\'historique'
                          % (row['dossierid'], row['filename']), file=sys.stderr)

            folder = os.path.join(args.dest, safe(row['devenumber'] or 'dossier-' + row['dossierid']),
                                  row['filearea'])
            os.makedirs(folder, exist_ok=True)
            target = os.path.join(folder, row['filename'])
            if os.path.exists(target):
                if digest(target, 'sha256') == sha256:
                    skipped += 1
                    continue
                # Nouvelle version : exportée à côté de la précédente, jamais par-dessus.
                stem, ext = os.path.splitext(row['filename'])
                target = os.path.join(folder, '%s.%s%s' % (stem, sha256[:12], ext))
                if os.path.exists(target):
                    skipped += 1
                    continue
            shutil.copyfile(source, target)
            exported += 1
            writer.writerow({
                'dossierid': row['dossierid'], 'devenumber': row['devenumber'], 'nom': row['lastname'],
                'prenom': row['firstname'], 'identifiant': row['username'], 'titre': row['title'],
                'date_soutenance': row['defensedate'], 'resultat': row['defenseresult'],
                'mention': row['mention'], 'zone': row['filearea'], 'fichier': row['filename'],
                'chemin_export': os.path.relpath(target, args.dest), 'taille': row['filesize'],
                'sha256': sha256, 'scelle_verifie': verified,
                'date_fichier': datetime.datetime.fromtimestamp(int(row['timemodified'])).isoformat(),
                'date_export': now,
            })

    print('%d fichier(s) exporté(s), %d déjà présent(s), %d erreur(s).' % (exported, skipped, errors))
    return 1 if errors else 0


if __name__ == '__main__':
    sys.exit(main())

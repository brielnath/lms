#!/bin/bash
# Dry-run: samakan LMS production dengan kerapian 2026/2027 Ganjil di laptop.
# Jalankan dari folder Moodle (tempat config.php).
# Unggah dulu peserta_20262027Ganjil.json ke folder yang sama (jangan lewat git).
#
#   bash admin/cli/ush_production_dryrun.sh
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

if [ ! -f config.php ]; then
  echo "Jalankan dari folder LMS (config.php tidak ada)."
  exit 1
fi

echo "Site folder: $ROOT"
php -r "define('CLI_SCRIPT', true); require 'config.php'; echo 'wwwroot=' . \$CFG->wwwroot . PHP_EOL;"

echo
echo "=== 1/6 Cangkang kelas (dry-run) ==="
php admin/cli/ush_prepare_semester_production.php --from-file=katalog_20262027Ganjil.json

if [ ! -f peserta_20262027Ganjil.json ]; then
  echo
  echo "File peserta_20262027Ganjil.json belum ada di $ROOT"
  echo "Unggah dulu dari laptop, lalu ulangi skrip ini."
  exit 1
fi

echo
echo "=== 2/6 Mahasiswa (dry-run) ==="
php admin/cli/ush_enrol_mahasiswa_production.php --from-file=peserta_20262027Ganjil.json --allow-incomplete --max-new-users=700

echo
echo "=== 3/6 Dosen (dry-run) ==="
php admin/cli/ush_enrol_dosen_production.php --from-file=peserta_20262027Ganjil.json --allow-incomplete

echo
echo "=== 4/6 Pecah Pancasila (dry-run) ==="
php admin/cli/ush_split_pancasila_prodi_production.php

echo
echo "=== 5/6 Pecah MKU + rapikan anggota (dry-run) ==="
php admin/cli/ush_finish_kaprodi_sync_20262027_production.php --from-file=peserta_20262027Ganjil.json

echo
echo "=== 6/6 Kaprodi + cohort (dry-run) ==="
php admin/cli/ush_assign_kaprodi_production.php
php admin/cli/ush_rebuild_cohorts.php

echo
echo "Selesai DRY-RUN. Belum ada data yang ditulis."
echo "Kalau wwwroot=https://lms.ush.ac.id dan angkanya wajar, baru --confirm urut:"
echo "  php admin/cli/ush_prepare_semester_production.php --from-file=katalog_20262027Ganjil.json --confirm"
echo "  php admin/cli/ush_enrol_mahasiswa_production.php --from-file=peserta_20262027Ganjil.json --allow-incomplete --max-new-users=700 --confirm"
echo "  php admin/cli/ush_enrol_dosen_production.php --from-file=peserta_20262027Ganjil.json --allow-incomplete --confirm"
echo "  php admin/cli/ush_split_pancasila_prodi_production.php --confirm"
echo "  php admin/cli/ush_finish_kaprodi_sync_20262027_production.php --from-file=peserta_20262027Ganjil.json --confirm"
echo "  php admin/cli/ush_assign_kaprodi_production.php --confirm"
echo "  php admin/cli/ush_rebuild_cohorts.php --confirm"
echo "  php admin/cli/upgrade.php --non-interactive"
echo "  php admin/cli/purge_caches.php"

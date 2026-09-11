#!/bin/bash
# Dry-run sinkronisasi 2026/2027 Ganjil di LMS production.
# Jalankan dari folder Moodle (tempat config.php).
# File peserta HARUS sudah diunggah ke folder yang sama:
#   peserta_20262027Ganjil.json
#
#   bash admin/cli/ush_production_dryrun.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

if [ ! -f config.php ]; then
  echo "Jalankan dari folder LMS (config.php tidak ada)."
  exit 1
fi

echo "Site folder: $ROOT"
php -r "define('CLI_SCRIPT', true); require 'config.php'; echo 'wwwroot=' . \$CFG->wwwroot . PHP_EOL;"

echo
echo "=== 1/4 Cangkang kelas (dry-run) ==="
php admin/cli/ush_prepare_semester_production.php --from-file=katalog_20262027Ganjil.json

if [ ! -f peserta_20262027Ganjil.json ]; then
  echo
  echo "File peserta_20262027Ganjil.json belum ada di $ROOT"
  echo "Unggah dulu dari laptop (jangan lewat git), lalu ulangi skrip ini."
  exit 1
fi

echo
echo "=== 2/4 Mahasiswa (dry-run) ==="
php admin/cli/ush_enrol_mahasiswa_production.php --from-file=peserta_20262027Ganjil.json --allow-incomplete --max-new-users=700

echo
echo "=== 3/4 Dosen (dry-run) ==="
php admin/cli/ush_enrol_dosen_production.php --from-file=peserta_20262027Ganjil.json --allow-incomplete

echo
echo "=== 4/4 Kaprodi (dry-run) ==="
php admin/cli/ush_assign_kaprodi_production.php

echo
echo "Selesai DRY-RUN. Belum ada data yang ditulis."
echo "Kalau angkanya masuk akal, jalankan satu per satu dengan --confirm:"
echo "  php admin/cli/ush_prepare_semester_production.php --from-file=katalog_20262027Ganjil.json --confirm"
echo "  php admin/cli/ush_enrol_mahasiswa_production.php --from-file=peserta_20262027Ganjil.json --allow-incomplete --max-new-users=700 --confirm"
echo "  php admin/cli/ush_enrol_dosen_production.php --from-file=peserta_20262027Ganjil.json --allow-incomplete --confirm"
echo "  php admin/cli/ush_assign_kaprodi_production.php --confirm"

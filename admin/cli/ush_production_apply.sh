#!/bin/bash
# Samakan production dengan kerapian lokal 2026/2027 Ganjil.
# Tidak menyalin database laptop. Jalankan dari folder Moodle (tempat config.php).
#
# Unggah dulu ke folder LMS:
#   peserta_20262027Ganjil.json
#   pengampu_target_20262027Ganjil.json
#   jadwal_slots_20262027Ganjil.json
#
#   bash admin/cli/ush_production_apply.sh
#   bash admin/cli/ush_production_apply.sh --confirm
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

if [ ! -f config.php ]; then
  echo "Jalankan dari folder LMS (config.php tidak ada)."
  exit 1
fi

CONFIRM=""
for arg in "$@"; do
  if [ "$arg" = "--confirm" ]; then
    CONFIRM="--confirm"
  fi
done

echo "Site folder: $ROOT"
php -r "define('CLI_SCRIPT', true); require 'config.php'; echo 'wwwroot=' . \$CFG->wwwroot . PHP_EOL;"

for f in peserta_20262027Ganjil.json pengampu_target_20262027Ganjil.json jadwal_slots_20262027Ganjil.json; do
  if [ ! -f "$f" ]; then
    echo "File belum ada: $f"
    echo "Unggah dulu dari laptop ke $ROOT"
    exit 1
  fi
done

echo
echo "=== 1/6 Smart City + AR/VR ==="
php admin/cli/ush_fix_informatika_sif1001_sif1002.php $CONFIRM

echo
echo "=== 2/6 Mandarin Informatika 2023 ==="
php admin/cli/ush_fix_informatika_mandarin_2023.php $CONFIRM

echo
echo "=== 3/6 Pecah Business Intelligence A2 ==="
php admin/cli/ush_fix_dwi_idm0515.php $CONFIRM

echo
echo "=== 4/6 Pengampu SIAKAD (multi pengajar) ==="
php admin/cli/ush_fix_pengampu_production.php --from-file=pengampu_target_20262027Ganjil.json $CONFIRM

echo
echo "=== 5/6 Jadwal pertemuan + presensi ==="
php admin/cli/ush_apply_jadwal_sequential_production.php --from-file=jadwal_slots_20262027Ganjil.json $CONFIRM

echo
echo "=== 6/6 Upgrade plugin + cache ==="
if [ -n "$CONFIRM" ]; then
  php admin/cli/upgrade.php --non-interactive
  php admin/cli/purge_caches.php
else
  echo "DRY-RUN: skip upgrade.php / purge_caches.php"
fi

echo
if [ -n "$CONFIRM" ]; then
  echo "Selesai LIVE. Cek ERP, Game, Smart City, BI A2 di https://lms.ush.ac.id"
else
  echo "Selesai DRY-RUN. Belum ada data yang ditulis."
  echo "Kalau wwwroot=https://lms.ush.ac.id dan angkanya wajar:"
  echo "  bash admin/cli/ush_production_apply.sh --confirm"
fi

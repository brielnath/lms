@echo off
cd /d C:\wamp64\www\lms
set PHP=C:\wamp64\bin\php\php8.2.29\php.exe
echo Export pengampu...
"%PHP%" admin\cli\ush_fix_pengampu_local.php --export=pengampu_target_20262027Ganjil.json
echo Export jadwal...
"%PHP%" admin\cli\ush_apply_jadwal_sequential_local.php --export=jadwal_slots_20262027Ganjil.json
echo Selesai.

@echo off
cd /d C:\wamp64\www\lms
C:\wamp64\bin\php\php8.2.29\php.exe admin\cli\ush_sync_cron_local.php --live

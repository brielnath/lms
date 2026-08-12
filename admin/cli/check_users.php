<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

$users = $DB->get_records_sql('SELECT id, username, firstname, lastname, email FROM {user} WHERE deleted = 0 AND id > 1 ORDER BY firstname ASC');
mtrace("TOTAL USERS: " . count($users));
foreach ($users as $u) {
    mtrace("ID: {$u->id} | Username: {$u->username} | Name: {$u->firstname} {$u->lastname}");
}

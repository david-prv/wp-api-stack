<?php

define('WP_ADMIN', true);

include('../bp-load.php');
// include('../wp-admin/includes/admin.php');
// include('../wp-admin/menu.php');

$action = $_GET['action'] ?? '';
$actions = [];

foreach($_registered_pages as $key => $value) {
    $actions[] = $key;
}

if(!in_array($action, $actions)) {
    die("Unknown action provided");
}

$action_args = explode(",", $_GET['args'] ?? '');
if (count($action_args) == 1 && $action_args[0] == "") {
    $action_args = null;
}

do_action('admin_init');
do_action($action, ...$action_args);
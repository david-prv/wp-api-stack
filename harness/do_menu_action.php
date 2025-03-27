<?php

define('WP_ADMIN', true);

// include('../wp-load.php');
// include('../wp-admin/includes/admin.php');
// include('../wp-admin/menu.php');

include("../bp-load.php");

$action = $_GET['action'] ?? '';
$actions = [];

foreach($_registered_pages as $key => $value) {
    $actions[] = $key;
}

if(!in_array($action, $actions)) {
    die("Unknown action provided");
}

do_action('admin_init');
do_action($action);
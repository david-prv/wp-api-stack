<?php

define('DOING_AJAX', true);
define('WP_ADMIN', true);

// include('../wp-load.php');
// include('../wp-admin/includes/admin.php');

include("../bp-load.php");

$action = $_GET['action'] ?? '';
$actions = [];

foreach ($GLOBALS['wp_filter'] as $k => $v) {
    if (substr($k, 0, 8) == "wp_ajax_") {
        echo $k . "<br>";
        $actions[] = $k;
    }
}

if (!in_array($action, $actions)) {
    die("Unknown action provided");
}

$_SERVER['SCRIPT_FILENAME'] = "/wp-admin/admin-ajax.php";

do_action('admin_init');
do_action($action);

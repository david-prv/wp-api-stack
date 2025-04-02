<?php

define('DOING_AJAX', true);
define('WP_ADMIN', true);

include('../bp-load.php');
// include('../wp-admin/includes/admin.php');

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

$action_args = explode(",", $_GET['args'] ?? '');
if (count($action_args) == 1 && $action_args[0] == "") {
    $action_args = null;
}

$_SERVER['SCRIPT_FILENAME'] = "/wp-admin/admin-ajax.php";

do_action('admin_init');
do_action($action, ...$action_args);

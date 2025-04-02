<?php

include('../bp-load.php');

$shortcode = $_GET['shortcode'] ?? '';
$shortcodes = [];

$shortcode_args = explode(",", $_GET['args'] ?? '');
if (count($shortcode_args) == 1 && $shortcode_args[0] == "") {
    $shortcode_args = null;
}

foreach ($shortcode_tags as $k => $v) {
        $shortcodes[] = $k;
}

if (!in_array($shortcode, $shortcodes)) {
    die("Unknown shortcode provided");
}

echo call_user_func($shortcode_tags[$shortcode], $shortcode_args);

<?php

// include('../wp-load.php');

include("../bp-load.php");

$route = explode("@", $_GET['route'] ?? '');
$routes = [];

function startswith($text, $prefix ) {
    return strpos($text, $prefix) === 0;
}
  
foreach (rest_get_server()->get_routes() as $key=>$handlers) {
    if (
        $key == '/' ||
        startswith($key, "/batch/") ||
        startswith($key, "/oembed/") ||
        startswith($key, "/wp/") ||
        startswith($key, "/wp-site-health/")) {
        continue;
    }
    foreach($handlers as $handler_key => $handler) {
        echo $key . "@" . $handler_key . "<br>";
        $routes[] = $key . "@" . $handler_key;
    }
}

if (!isset($_GET['route']) || !in_array($route[0] . "@" . $route[1], $routes)) {
    die("Unknown route provided");
}

function user_has_permission($result) {
    if ($result instanceof WP_Error) {
        return false;
    }

    return $result;
}

function do_rest_route($route) {
    foreach (rest_get_server()->get_routes() as $key => $handlers) {
	    if ((string) $key != $route[0]) {
    		continue;
	    }

    	foreach($handlers as $handler_key => $handler) {
	    	if ((string) $handler_key != $route[1]) {
		    	continue;
    		}

            if (getenv("TOP_LEVEL_NAVIGATION_ONLY")) {
                if (is_array($handler['methods']) && !in_array("GET", $handler['methods'])) {
                    continue;
                }
                if (!is_array($handler['methods']) && $handler['methods'] != "GET") {
                    continue;
                }
            }

	    	$request = new WP_REST_Request($key);
		    if (
                    (!array_key_exists('permission_callback', $handler)) ||
                    (user_has_permission(call_user_func($handler['permission_callback'], $request)))) {
                call_user_func($handler['callback'], $request);
                // print_r($res);
	    	}
        }
    }
}

// do_rest_route_with_user(2, "subscriber"); 
do_rest_route($route);
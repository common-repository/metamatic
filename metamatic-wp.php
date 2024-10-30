<?php

function metamatic_db_insert($query) {
    global $wpdb;
    $args = func_get_args();
    array_shift($args);
    $wpdb->insert_id = null;
    metamatic_db_query($query, $args);
    return $wpdb->insert_id;
}

// Returns array of objects
function metamatic_db_query($query) {
    global $wpdb;
    return $wpdb->get_results(metamatic_db_prepare(func_get_args()));
}

// Returns array of objects
function metamatic_db_query_row($query) {
    global $wpdb;
    return $wpdb->get_row(metamatic_db_prepare(func_get_args()));
}

function metamatic_db_query_values($query) {
    global $wpdb;
    return $wpdb->get_col(metamatic_db_prepare(func_get_args()));
}

function metamatic_db_update($query) {
    global $wpdb;
    return $wpdb->query(metamatic_db_prepare(func_get_args()));
}

function metamatic_db_prepare($args) {
    global $wpdb;
    $query = array_shift($args);
    $query = metamatic_db_prefix_tables($query);
    if (isset($args[0]) && is_array($args[0])) { // 'All arguments in one array' syntax
        $args = $args[0];
    }
    $result = $wpdb->prepare($query, $args);
    return $result;
}

function metamatic_db_prefix_tables($sql) {
    global $wpdb;
    $db_prefix = $wpdb->prefix;
    return strtr($sql, array('{' => $db_prefix, '}' => ''));
}

function metamatic_get_key() {
    return get_option('metamatic_key', md5(mt_rand() . mt_rand() . mt_rand()));
}

function metamatic_hub_enabled() {
    return get_option('metamatic_use_hub', true);
}


/**
 * Checks if the current user is wllowed to administer metamatic stuff.
 */
function metamatic_is_admin() {
    return current_user_can('manage_options')? true: false;
}

function metamatic_get_style_dir() {
    return plugins_url('style', __FILE__);
}

function metamatic_get_isite_url() {
    return plugins_url('interface.php', __FILE__);
}

function metamatic_get_hub_url() {
    $url = get_option('metamatic_hub_url');
    if(empty($url)) {
        $url = 'http://metamatic.net/metamatic-server';
    }
    return $url;
}

function metamatic_add_admin_javascript() {
    wp_enqueue_script("json2");
    wp_enqueue_script("jquery");
    wp_enqueue_script("jquery-ui-core");
    wp_enqueue_script("jquery-ui-dialog");

    wp_enqueue_script('metamatic-ui', plugins_url('js/ui.js', __FILE__));
    wp_enqueue_script('jquery-printf', plugins_url('js/jquery-printf.js', __FILE__));
    wp_enqueue_style('jquery-ui-start', plugins_url('style/start/jquery-ui.css', __FILE__));
}

function metamatic_e($str) {
    _e($str, 'metamatic');
}

function metamatic_t($str) {
    return __($str, 'metamatic');
}

function metamatic_http_get($url) {
    $retval = null;
    $req = new WP_Http();
    $result = @$req->request($url, array('timeout' => 60));
    if(is_array($result)) {
        $retval = new stdClass();
        $retval->data = $result['body'];
        $retval->headers = $result['headers'];
        $retval->code = @$result['response']['code'];
    }
    return $retval;
}

function metamatic_http_post($url, $postParams) {
    $body = http_build_query($postParams, null, '&');
    
    $retval = null;
    $req = new WP_Http();
    $result = @$req->request($url, array(
        'method' => 'POST',
        'body' => $body,
        'timeout' => 60,
        'headers' => array(
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Content-Length' => strlen($body),
        ),
        ));
    if(is_array($result)) {
        $retval = new stdClass();
        $retval->data = $result['body'];
        $retval->headers = $result['headers'];
        $retval->code = @$result['response']['code'];
    }
    return $retval;
}

function metamatic_get_interests() {
    $val = get_option('metamatic_interests');
    return $val;
}
function metamatic_get_about() {
    $val = get_option('metamatic_about');
    return $val;
}

function metamatic_is_poweredby_enabled() {
    return get_option('metamatic_poweredby_enabled')? true: false;
}

function metamatic_get_page_url($name) {
    return get_option('metamatic_' . $name . '_url');
    switch($name) {
        default:
            return plugins_url('pages/' . $name . '.php', __FILE__);
    }
}

// Taken from Drupal sources:
function valid_url($url, $absolute = FALSE) {
    if ($absolute) {
        return (bool) preg_match("
  /^                                                      # Start at the beginning of the text
  (?:ftp|https?):\/\/                                     # Look for ftp, http, or https schemes
  (?:                                                     # Userinfo (optional) which is typically
    (?:(?:[\w\.\-\+!$&'\(\)*\+,;=]|%[0-9a-f]{2})+:)*      # a username or a username and password
    (?:[\w\.\-\+%!$&'\(\)*\+,;=]|%[0-9a-f]{2})+@          # combination
  )?
  (?:
    (?:[a-z0-9\-\.]|%[0-9a-f]{2})+                        # A domain name or a IPv4 address
    |(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\])         # or a well formed IPv6 address
  )
  (?::[0-9]+)?                                            # Server port number (optional)
  (?:[\/|\?]
    (?:[\w#!:\.\?\+=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})   # The path and query (optional)
  *)?
$/xi", $url);
    } else {
        return (bool) preg_match("/^(?:[\w#!:\.\?\+=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})+$/i", $url);
    }
}

<?php

function metamatic_install_or_update() {
    // Add delay-loaded options:
    add_option('metamatic_about', null, null, 'no');
    add_option('metamatic_interests', null, null, 'no');

    // Auto-generate key:
    $key = trim(base64_encode(md5(time() . mt_rand() . mt_rand() . mt_rand(), true)), '=');
    add_option('metamatic_key', $key, null, 'no');
    add_option('metamatic_use_hub', true, null, 'no');
    add_option('metamatic_hub_url', null, null, 'no');
    add_option('metamatic_db_ver', 0, null, 'no');
    add_option('metamatic_poweredby_enabled', 0);

    metamatic_create_update_tables();
    metamatic_create_pages();

    metamatic_reschedule_hub_operations();
}

function metamatic_create_pages() {
    global $wpdb, $wp_rewite;

    $post_date = date("Y-m-d H:i:s");
    $post_date_gmt = gmdate("Y-m-d H:i:s");

    $pages = array();

    $pages[] = array(
        'name' => 'favorites',
        'title' => metamatic_t('Favorite sites'),
        'tag' => '[metamatic_favorites]',
        'option' => 'metamatic_favorites_url',
    );
    $pages[] = array(
        'name' => 'friendfeed',
        'title' => metamatic_t('Friends feed'),
        'tag' => '[metamatic_friendfeed]',
        'option' => 'metamatic_friendfeed_url',
    );

    $haveNewPages = false;
    foreach ($pages as $page) {
        $existing = $wpdb->get_row("SELECT * FROM `" . $wpdb->posts . "` WHERE `post_content` LIKE '%" . $page['tag'] . "%'  AND `post_type` NOT IN('revision') LIMIT 1", ARRAY_A);
        if (!$existing) {
            $post_parent = 0;
            $sql = "INSERT INTO " . $wpdb->posts . "(post_author, post_date, post_date_gmt, post_content, post_content_filtered, post_title, post_excerpt,  post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_parent, menu_order, post_type)"
                . " VALUES ('1', '$post_date', '$post_date_gmt', '" . $page['tag'] . "', '', '" . $page['title'] . "', '', 'publish', 'closed', 'closed', '', '" . $page['name'] . "', '', '', '$post_date', '$post_date_gmt', '$post_parent', '0', 'page')";
            $wpdb->query($sql);
            $post_id = $wpdb->insert_id;
            $wpdb->query("UPDATE $wpdb->posts SET guid = '" . get_permalink($post_id) . "' WHERE ID = '$post_id'");
            update_option($page['option'], get_permalink($post_id));
            $haveNewPages = true;
        }
    }
    if ($haveNewPages) {
        wp_cache_delete('all_page_ids', 'pages');
        $wp_rewrite->flush_rules();
    }
}

function metamatic_create_update_tables() {
    global $wpdb;
    $prefix = $wpdb->prefix;
    $version = (int) get_option('metamatic_db_ver');
    $sql = array();

    $newVersion = 1;
    if ($version < 1) {
        $sql[] = "
CREATE TABLE  `{$prefix}metamatic_assets` (
  `aid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(60) NOT NULL,
  `title` varchar(80) DEFAULT NULL,
  `feed` varchar(200) DEFAULT NULL,
  `feed_enabled` tinyint(1) unsigned DEFAULT NULL,
  `feed_last_refresh` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`aid`),
  UNIQUE KEY `value` (`value`),
  KEY `title` (`title`),
  KEY `feed_last_refresh` (`feed_last_refresh`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE  `{$prefix}metamatic_feed_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(10) unsigned NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `content` text NOT NULL,
  `url` varchar(200) DEFAULT NULL,
  `time_imported` int(10) unsigned NOT NULL,
  `uid` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `aid` (`aid`),
  UNIQUE KEY `uid` (`uid`),
  KEY `time_imported` (`time_imported`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
";
    }

    // Add updates here...

    if(count($sql) > 0) {
        foreach ($sql as $s) {
            $s2 = explode(';', $s);
            foreach($s2 as $query) {
                $query = trim($query);
                if(strlen($query) > 0) {
                    metamatic_db_update($query);
                }
            }
        }
        update_option('metamatic_db_ver', $newVersion);
    }
}
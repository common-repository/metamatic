<?php

function metamatic_hub_update() {
    // Ping the hub
    metamatic_invoke_method(
        metamatic_get_hub_url(),
        'synchronize',
        array(
            'source' => metamatic_get_isite_url(),
            'key' => metamatic_get_key(),
        )
    );
    return;
}

function metamatic_feed_task() {
    $start = time();
    $time = metamatic_db_query('SELECT time_imported FROM {metamatic_feed_items} ORDER BY time_imported DESC LIMIT 100,1');
    $time = (int) @$time->time_imported;
    if($time > 0) {
        metamatic_db_query('DELETE FROM {metamatic_feed_items} WHERE time_imported<%d', $time);
    }
    $assets = metamatic_db_query('SELECT * FROM {metamatic_assets} WHERE (feed_enabled>0) AND (feed_last_refresh<%d) ORDER BY feed_last_refresh LIMIT 20', time() - 86400);
    foreach ($assets as $ass) {
        metamatic_db_query('UPDATE {metamatic_assets} SET feed_last_refresh=%d WHERE aid=%d', time(), $ass->aid);
        metamatic_load_asset_feed($ass);

        if (time() - $start > 20) {
            break;
        }
    }
}

function metamatic_load_asset_feed($ass) {
    require_once(dirname(__FILE__) . '/feeds.php');

    $assetUrl = Asset::getUrl($ass);
    if (!$ass->feed) {
        $feedUrl = metamatic_discoverSiteFeed($assetUrl);
        if(strlen($feedUrl) > 0) {
            // Store feed URL to database
            metamatic_db_query('UPDATE {metamatic_assets} SET feed=%s WHERE aid=%d', $feedUrl, $ass->aid);
            $ass->feed = $feedUrl;
        } else {
            // Disable feed
            //metamatic_db_query('UPDATE {metamatic_assets} SET feed_enabled=0 WHERE aid=%d', $ass->aid);
        }
    }
    if($ass->feed) {
        $url = metamatic_convertUrlToAbsolute($ass->feed, $assetUrl);
        $feed = metamatic_loadStandardFeed($url);
        if ($feed && is_array(@$feed['items'])) {
            foreach ($feed['items'] as $item) {
                $item['description'] = metamatic_filterFeedItemContent($item['description'], $ass->feed);
                metamatic_add_feed_item($ass->aid, $item);
                break; // Add only one item at a time
            }
        }
    }
}

function metamatic_add_feed_item($aid, $item) {
    $uid = md5($item['title'] . $item['description']);
    metamatic_db_query('INSERT IGNORE INTO {metamatic_feed_items} (aid, uid, title, content, url, time_imported) ' .
        'VALUES (%d, %s, %s, %s, %s, %d)', $aid, $uid, $item['title'], $item['description'], $item['url'], time());
}
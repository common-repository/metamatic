<?php

function metamatic_import_site_foaf($site) {
    $foaf = metamatic_load_site_foaf($site);
    if ($foaf) {
        foreach ($foaf->friends as $friend) {
            if ($friend->site) {
                $ass = Asset::parse(Asset::ASS_SITE, $friend->site);
                if ($ass) {
                    metamatic_add_asset($ass, null, true);
                }
            }
        }
    }
}

function metamatic_add_asset($ass, $feedUrl, $enableFeed) {
    $aid = metamatic_db_insert(
            'INSERT IGNORE INTO {metamatic_assets} ' .
            ' (value, title, feed, feed_enabled) VALUES (%s, %s, %s, %d)',
            $ass->value, @$ass->title, $feedUrl, $enableFeed ? 1 : 0);
}

function metamatic_after_hub_config_updated() {
    // Inform hub about my new key
    if (metamatic_hub_enabled ()) {
        metamatic_invoke_method(
            metamatic_get_hub_url(),
            'notifyKey',
            array(
                'source' => metamatic_get_isite_url(),
                'key' => metamatic_get_key(),
            )
        );
    }
    return;
}

function metamatic_split_words_csv($str) {
    $parts = array();
    $ss = explode(',', $str);
    foreach ($ss as $s) {
        $s = trim($s);
        $s = preg_replace('/\s+/', ' ', $s);
        if (strlen($s) > 0) {
            $parts[] = $s;
        }
    }
    return $parts;
}
function metamatic_cleanup_words_csv($str) {
    $ii = metamatic_split_words_csv($str);
    $str = implode(', ', $ii);
    return $str;
}
function metamatic_logo($type = null) {
    if(metamatic_is_poweredby_enabled()) {
        $class = 'metamatic-logo';
        if($type == 'small') {
            $class .= ' small';
            $text = metamatic_t('Metamatic');
        } else {
            $text = metamatic_t('Powered by Metamatic');
        }
        ?><a class="<?php echo $class ?>" title="<?php metamatic_e('Visit Metamatic site') ?>" href="http://metamatic.net"><?php echo $text ?></a><?php
    }
}
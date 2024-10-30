<?php
require_once(dirname(__FILE__) . '/../../../wp-load.php');
if(!function_exists('metamatic_activate')) {
    return;
}
require_once(dirname(__FILE__) . '/metamatic-wp.php');
require_once(dirname(__FILE__) . '/client.php');
require_once(dirname(__FILE__) . '/feeds.php');

/**
 * Provides methods that are accessible via Metamatic Site interface.
 * All methods implemented by this class require signature except those
 * listed by getOpenMethods().
 */
class MetamaticSiteMethods {

    /**
     * Returns names of methods that should be accessible without authentication.
     */
    public function getOpenMethods() {
        return array('getSiteStatus');
    }

    public function importFavoritesFromSite($params) {
        $url = @$params->url;
        // Find URL to query
        $siteUrl = null;
        if(strpos($url, '://') !== false) {
            $siteUrl = $url;
        } else {
            $ass = Asset::parse(Asset::ASS_SITE, $url);
            if($ass) {
                $siteUrl = Asset::getUrl($ass);
            }
        }
        if($siteUrl) {
            metamatic_import_site_foaf($siteUrl);
            $result['result'] = 'ok';
        } else {
            $result['error'] = metamatic_t('Invalid site address specified');
        }
        return $result;
    }

    /**
     * Returns status of specific URL
     * @param <type> $params
     * @return result
     */
    public function getSiteStatus($params) {
        $url = @$params->url;
        if ($url) {
            $ass = Asset::parse(Asset::ASS_SITE, $url);
            $row = metamatic_db_query_row("SELECT COUNT(*) c FROM {metamatic_assets} WHERE value=%s",
                    $ass->value);
            $fav = $row->c > 0 ? 1 : 0;
            $result['isFavorite'] = $fav;
            $result['result'] = 'ok';
        } else {
            $result['error'] = 'No url specified';
        }
        return $result;
    }

    /**
     * Adds specified site to favorites.
     * @param <type> $params
     * @return string
     */
    public function siteFavorite($params) {
        $url = @$params->url;
        if ($url) {
            $ass = Asset::parse(Asset::ASS_SITE, $url);
            if ($ass) {

                $feedUrl = @$params->feedUrl;
                if (@$params->discoverFeedUrl) {
                    $assetUrl = Asset::getUrl($ass);
                    $feedUrl = metamatic_discoverSiteFeed($assetUrl);
                }
                metamatic_add_asset($ass, $feedUrl, true);

                $result['isFavorite'] = 1;
                $result['result'] = 'ok';
            } else {
                $result['error'] = metamatic_t('Invalid site address specified');
            }
        } else {
            $result['error'] = 'No url specified';
        }
        return $result;
    }

    /**
     * Removes specified site from favorites.
     * @param <type> $params
     * @return string
     */
    public function siteUnfavorite($params) {
        $url = @$params->url;
        if ($url) {
            $ass = Asset::parse(Asset::ASS_SITE, $url);
            if ($ass) {
                $asset = metamatic_db_query_row("SELECT aid FROM {metamatic_assets} WHERE value=%s",
                        $ass->value);
                if ($asset && $asset->aid) {
                    metamatic_db_query('DELETE FROM {metamatic_feed_items} WHERE aid=%d', $asset->aid);
                    metamatic_db_query('DELETE FROM {metamatic_assets} WHERE aid=%d', $asset->aid);

                    $result['isFavorite'] = 0;
                    $result['result'] = 'ok';
                } else {
                    $result['error'] = 'Site is not in favorites list';
                }
            } else {
                $result['error'] = 'Invalid site address specified';
            }
        } else {
            $result['error'] = 'No url specified';
        }
        return $result;
    }

    public function unfavoriteSites($ids) {
        $ids2 = array();
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids2[] = $id;
            }
        }
        if (count($ids2) > 0) {
            $str = implode(',', $ids2);
            metamatic_db_query('DELETE FROM {metamatic_feed_items} WHERE aid IN (' . $str . ')');
            metamatic_db_query('DELETE FROM {metamatic_assets} WHERE aid IN (' . $str . ')');
        }
    }

    public function getDetails($params) {
        $result['interests'] = metamatic_split_words_csv(metamatic_get_interests());
        $result['about'] = metamatic_get_about();

        $favorites = metamatic_get_asset_list();
        $list = '';
        $first = true;
        foreach($favorites as $ass) {
            if(!$first) {
                $list .= "\n";
            }
            $first = false;
            $list .= Asset::encode($ass);
        }
        $result['result'] = 'ok';
        $result['favorites'] = $list;
        return $result;
    }

    // This method is used to check signature (called by Hub)
    public function checkSignature($params) {
        return array('result' => 'ok');
    }
}


/**
 * This is JSON endpoint for metamatic queries.
 */
$params = $_POST + $_GET;

function unescapeMagicQuotes($params) {
    if (get_magic_quotes_gpc ()) {
        $unescaped = array();
        foreach ($params as $name => $value) {
            $unescaped[$name] = stripslashes($value);
        }
    } else {
        $unescaped = $params;
    }
    return $unescaped;
}

$params = unescapeMagicQuotes($params);


$obj = new MetamaticSiteMethods();
$error = null;

$method = @$params['method'];
if ($method && method_exists($obj, $method)) {

    $parArgs = @$params['args'];
    // Now I know which method to invoke.

    // Now check if the user has permission to invoke the method.
    $open = @$obj->getOpenMethods();
    if (!is_array($open) || !in_array($method, $open)) {

        // Need to check signature
        $admin = metamatic_is_admin();
        if (!$admin) {
            $theirSignature = @$params['signature'];
            if (!$theirSignature) {
                // Signature not specified
                $error = 'need signature';

            } else {
                // Check signature
                $key = metamatic_get_key();
                $nonce = @$params['nonce'];
                $str = $nonce . $key . $method . $parArgs;
                $mySignature = base64_encode(md5($str, true));
                if ($mySignature !== $theirSignature) {
                    $error = 'bad signature';
                }
            }
        }
    }

    if (!$error) {
        $args = $parArgs ? json_decode($parArgs) : null;
        $result = $obj->$method($args);
    }

} else {
    $error = 'unknown method';
}
if($error) {
    $result = array('error' => $error);
}
echo json_encode($result);
exit;
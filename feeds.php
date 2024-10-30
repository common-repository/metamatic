<?php

function metamatic_convertUrlToAbsolute($link, $baseUrl) {
    if (strlen($baseUrl) < 1) {
        return $link;
    }
    if (strpos($link, "://") !== false) {
        return $link;
    }
    $baseSlash = endsWith($baseUrl, "/");
    if (!$baseSlash) {
        // Remove last element of base URL to make it point to the directory (will end with slash).
        $slash = strrpos($baseUrl, "/");
        $hostStart = strpos($baseUrl, "://");
        if($hostStart !== false) {
            $hostStart += 3;
        }
        if ($slash > $hostStart) {
            $baseUrl = substr($baseUrl, 0, $slash + 1);
            $baseSlash = true;
        }
    }
    $linkSlash = startsWith($link, "/");
    if ($linkSlash) {
        // Relative to site root
        $schemaPos = strpos($baseUrl, "://");
        if ($schemaPos !== false) {
            $hostEnd = strpos($baseUrl, "/", $schemaPos + 3);
            if ($hostEnd !== false) {
                $baseUrl = substr($baseUrl, 0, $hostEnd + 1);
            }
        }
    }

    $baseSlash = endsWith($baseUrl, "/");

    if ($baseSlash && $linkSlash) {
        $link = substr($link, 1); // Remove slash
    } else if (!$baseSlash && !$linkSlash) {
        $baseUrl = $baseUrl . "/";
    }

    $absolute = $baseUrl . $link;
    return $absolute;
}

function metamatic_xpFindElements($xp, $root, $name) {
    return $xp->query("//*[translate(name(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') = '$name']");
}

function metamatic_xmlRemove($nodes) {
    foreach ($nodes as $node) {
        $parent = $node->parentNode;
        if ($parent) {
            $parent->removeChild($node);
        }
    }
}

function metamatic_filterFeedItemContent($text, $baseUrl) {
    $doc = new DOMDocument("1.0", "UTF-8");
    $html = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body>';
    $html .= $text;
    $html .= "</body></html>";
    @$doc->loadHTML($html);

    $xp = new DOMXPath($doc);
    metamatic_xmlRemove(metamatic_xpFindElements($xp, $doc, 'script'));
    metamatic_xmlRemove(metamatic_xpFindElements($xp, $doc, 'object'));
    metamatic_xmlRemove(metamatic_xpFindElements($xp, $doc, 'embed'));

    metamatic_forEachNode($doc, $xp, '//@src', 'metamatic_toAbsolute', $baseUrl);
    metamatic_forEachNode($doc, $xp, '//@href', 'metamatic_toAbsolute', $baseUrl);

    $body = $xp->query("/html/body", $doc);
    $body = $body->item(0);

    $html = $doc->saveHTML();

    $start = strpos($html, "<body>") + strlen("<body>");
    $end = strrpos($html, "</body>");
    $html = substr($html, $start, $end - $start);

    $text = trim($html);
    //$text = str_replace("\r", " ", $text);
    //$text = str_replace("\n", " ", $text);
    return $text;
}

function metamatic_forEachNode($context, $xp, $expression, $callback, $arg) {
    $nodes = $xp->query($expression, $context);
    foreach ($nodes as $node) {
        $callback($node, $arg);
    }
}

function metamatic_toAbsolute($attr, $base) {
    $srcOrig = $attr->value;
    $src = metamatic_convertUrlToAbsolute($srcOrig, $base);
    @$attr->value = $src;
}

function metamatic_detectEncodingByHttpHeaders($headers, $default = null) {
    $charset = $default;
    if(is_array($headers)) {
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, "content-type") == 0) {
                $parts = explode(';', $value);
                foreach ($parts as $part) {
                    $keyval = explode('=', $part);
                    if (strcasecmp(trim(@$keyval[0]), "charset") == 0) {
                        $charset = trim(@$keyval[1]);
                        break 2; // break both loops
                    }
                }
            }
        }
    }
    return $charset;
}

function metamatic_loadDom($url) {
    $response = metamatic_http_get($url);
    $doc = null;
    if($response) {
        $doc = metamatic_loadHtmlResponseIntoDom(@$response->data, $response->headers);
    }
    return $doc;
}

// html must be in UTF8 encoding
function metamatic_loadHtmlIntoDom($html) {
    // Ugly hack to force UTF-8 encoding inside the FUCKING PHP DOM
    /*
      $html = str_replace('xml:lang="ru"', '', $html);
      $html = str_replace('lang="ru"', '', $html);
      // Remove fuking stuff (motoforum.ru bug)
      $mm = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
      while(substr_count($html, $mm) > 1) {
      $pos = stripos($html, $mm);
      if($pos !== false) {
      $html = substr($html, 0, $pos) . substr($html, $pos + strlen($mm));
      }
      }
     */
    if (stripos($html, "charset=UTF-8") === false) {
        $pos = stripos($html, "<meta");
        if ($pos > 0) {
            $meta = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
            $html = substr($html, 0, $pos) . $meta . substr($html, $pos);
        }
    }
    $html = '<?xml version="1.0" encoding="UTF-8"?>' . $html;

    $doc = new DOMDocument("1.0", "UTF-8");
    $doc->encoding = "UTF-8";
    @$doc->loadHTML($html);

    //$x = $doc->saveXML();
    //$win = mb_convert_encoding($x, "Windows-1251", "utf-8");

    return $doc;
}

function metamatic_loadHtmlResponseIntoDom($html, $headers) {
    $charset = null;
    $marker = " charset=";
    $pos = stripos($html, $marker);
    $head = stripos($html, "</head>");
    if ($head && $pos && ($pos < $head)) {
        $pos += strlen($marker);
        $quote = strpos($html, "\"", $pos);
        if ($quote) {
            $charset = trim(substr($html, $pos, $quote - $pos));
            $html = str_replace("charset=$charset", "charset=utf-8", $html);
        }
    }
    if (!$charset) {
        $charset = metamatic_detectEncodingByHttpHeaders($headers, $charset);
    }

    if (function_exists('mb_substitute_character') && function_exists('mb_convert_encoding')) {
        mb_substitute_character(0x23); // #
        mb_substitute_character("long");

        if ($charset && strcasecmp("utf-8", $charset) != 0) {
            $html = mb_convert_encoding($html, "UTF-8", $charset);
        } else {
            // Cleanup invalid UTF-8 chars:
            //$htl = iconv("UTF-8","UTF-8//IGNORE",$html);
            $utf = mb_convert_encoding($html, "utf-8", "utf-8");
            $html = $utf;
        }
    }
    $doc = metamatic_loadHtmlIntoDom($html);
    return $doc;
}

function metamatic_loadStandardFeed($url) {
    $feed = null;
    $response = metamatic_http_get($url);
    if ($response && $response->data) {
        $feed = common_syndication_parser_parse($response->data, $url);
    }
    return $feed;
}

function metamatic_discoverSiteFeed($url) {
    $result = metamatic_discover_site_meta($url, '//*[name()="link" and @rel="alternate"]/@href');
    return $result ? $result : null;
}

function metamatic_discover_site_meta($url, $xpath) {
    $doc = metamatic_loadDom($url);
    if (!$doc) {
        return null;
    }
    $xp = new DOMXPath($doc);
    $nn = $xp->evaluate($xpath);
    $href = null;
    if($nn->length > 0) {
        $n = $nn->item(0);
        $href = $n->textContent;
    }
    return $href;
}

function metamatic_load_foaf($foafUrl) {
    $foaf = null;
    $resp = metamatic_http_get($foafUrl);
    if($resp && @$resp->data) {
        $xml = new DOMDocument();
        if(@$xml->loadXML($resp->data)) {
            $foaf = metamatic_parse_foaf($xml);
        }
    }
    return $foaf;
}

function metamatic_load_site_foaf($siteUrl) {
    $foaf = null;
    $foafUrl = metamatic_discover_site_meta($siteUrl, '//*[name()="link" and @rel="meta" and @type="application/rdf+xml"]/@href');
    if($foafUrl) {
        $foaf = metamatic_load_foaf($foafUrl);
    }
    return $foaf;
}

function metamatic_parse_foaf($xml, $options = null) {

    $xp = new DOMXPath($xml);

    $full = $options === null;
    $needFriends = $full || in_array('friends', $options);
    $needInterests = $full || in_array('interests', $options);

    $xp->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
    $xp->registerNamespace('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
    $xp->registerNamespace('foaf', 'http://xmlns.com/foaf/0.1/');
    $xp->registerNamespace('ya', 'http://blogs.yandex.ru/schema/foaf/');
    $xp->registerNamespace('lj', 'http://www.livejournal.org/rss/lj/1.0/');
    $xp->registerNamespace('geo', 'http://www.w3.org/2003/01/geo/wgs84_pos#');
    $xp->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');

    $me = $xp->query('rdf:RDF/foaf:Person', $xml);
    if($me->length > 0) {
        $me = $me->item(0);
    } else {
        return null; // WTF
    }

    $foaf = new stdClass();
    $foaf->userpic = xmlNodeListGetTextContent($xp->evaluate('foaf:img/@rdf:resource', $me));
    $foaf->bio = xmlNodeListGetTextContent($xp->evaluate('ya:bio', $me));

    $homepage = xmlNodeListGetTextContent($xp->evaluate('foaf:homepage/@rdf:resource', $me));
    if($homepage) {
        $title = xmlNodeListGetTextContent($xp->evaluate('foaf:homepage/@dc:title', $me));
        $site = new stdClass();
        $site->url = $homepage;
        $site->title = $title;
        $foaf->ownSites[] = $site;
    }

    if($needInterests) {
        $interestNodes = $xp->query('foaf:interest/@dc:title', $me);
        $interests = array();
        foreach($interestNodes as $interest) {
            $interests[] = $interest->value;
        }
        $foaf->interests = $interests;
    }

    if($needFriends) {
        $friends = array();
        $persons = $xp->query('foaf:knows/foaf:Person', $me);
        foreach($persons as $person) {
            $nick = xmlNodeListGetTextContent($xp->evaluate('foaf:nick', $person));
            $name = xmlNodeListGetTextContent($xp->evaluate('foaf:member_name', $person));
            $site = xmlNodeListGetTextContent($xp->evaluate('foaf:weblog/@rdf:resource', $person));

            $friend = new stdClass();
            $friend->nick = $nick;
            $friend->site = $site;
            if($nick != $name) {
                $friend->name = $name;
            }
            $friends[] = $friend;
        }
        $foaf->friends = $friends;
    }
    return $foaf;
}


/**
 * Parse the feed into a data structure.
 *
 * @param $feed
 *  The feed object (contains the URL or the parsed XML structure.
 * @return
 *  stdClass The structured datas extracted from the feed.
 */
function common_syndication_parser_parse($string, $url) {
    $doc = @DOMDocument::loadXML($string);
    if (!$doc) {
        return FALSE;
    }
    $xml = simplexml_import_dom($doc);

    // Got a malformed XML.
    if ($xml === FALSE || is_null($xml)) {
        return FALSE;
    }
    $feed_type = _parser_common_syndication_feed_format_detect($xml);
    if ($feed_type == "atom1.0") {
        return _parser_common_syndication_atom10_parse($xml);
    }
    if ($feed_type == "RSS2.0" || $feed_type == "RSS0.91" || $feed_type == "RSS0.92") {
        return _parser_common_syndication_RSS20_parse($xml);
    }
    if ($feed_type == "RDF") {
        return _parser_common_syndication_RDF10_parse($xml);
    }
    return FALSE;
}

/**
 * Get the cached version of the <var>$url</var>
 */
function _parser_common_syndication_cache_get($url) {
    $cache_file = _parser_common_syndication_sanitize_cache() . '/' . md5($url);
    if (file_exists($cache_file)) {
        $file_content = file_get_contents($cache_file);
        return unserialize($file_content);
    }
    return FALSE;
}

/**
 * Determine the feed format of a SimpleXML parsed object structure.
 *
 * @param $xml
 *  SimpleXML-preprocessed feed.
 * @return
 *  The feed format short description or FALSE if not compatible.
 */
function _parser_common_syndication_feed_format_detect($xml) {
    if (!is_object($xml)) {
        return FALSE;
    }
    $attr = $xml->attributes();
    $type = strtolower($xml->getName());
    if (isset($xml->entry) && $type == "feed") {
        return "atom1.0";
    }
    if ($type == "rss" && $attr["version"] == "2.0") {
        return "RSS2.0";
    }
    if ($type == "rdf" && isset($xml->channel)) {
        return "RDF";
    }
    if ($type == "rss" && $attr["version"] == "0.91") {
        return "RSS0.91";
    }
    if ($type == "rss" && $attr["version"] == "0.92") {
        return "RSS0.92";
    }
    return FALSE;
}

/**
 * Parse atom feeds.
 */
function _parser_common_syndication_atom10_parse($feed_XML) {
    $parsed_source = array();

    $base = (string) array_shift($feed_XML->xpath("@base"));
    if (!$base) {
        $base = FALSE;
    }

    // Detect the title
    $parsed_source['title'] = isset($feed_XML->title) ? _parser_common_syndication_title("{$feed_XML->title}") : "";
    // Detect the description
    $parsed_source['description'] = isset($feed_XML->subtitle) ? "{$feed_XML->subtitle}" : "";

    $parsed_source['link'] = _parser_common_syndication_link($feed_XML->link);
    if (valid_url($parsed_source['link']) && !valid_url($parsed_source['link'], TRUE) && !empty($base)) {
        $parsed_source['link'] = $base . $parsed_source['link'];
    }

    $parsed_source['items'] = array();

    foreach ($feed_XML->entry as $news) {
        $original_url = NULL;

        $guid = !empty($news->id) ? "{$news->id}" : NULL;

        // I don't know how standard this is, but sometimes the id is the URL.
        if (valid_url($guid, TRUE)) {
            $original_url = $guid;
        }

        $additional_taxonomies = array();

        if (isset($news->category)) {
            $additional_taxonomies['ATOM Categories'] = array();
            $additional_taxonomies['ATOM Domains'] = array();
            foreach ($news->category as $category) {
                if (isset($category['scheme'])) {
                    $domain = "{$category['scheme']}";
                    if (!empty($domain)) {
                        if (!isset($additional_taxonomies['ATOM Domains'][$domain])) {
                            $additional_taxonomies['ATOM Domains'][$domain] = array();
                        }
                        $additional_taxonomies['ATOM Domains'][$domain][] = count($additional_taxonomies['ATOM Categories']) - 1;
                    }
                }
                $additional_taxonomies['ATOM Categories'][] = "{$category['term']}";
            }
        }
        $title = "{$news->title}";

        $body = '';
        if (!empty($news->content)) {
            foreach ($news->content->children() as $child) {
                $body .= $child->asXML();
            }
            $body .= "{$news->content}";
        } else if (!empty($news->summary)) {
            foreach ($news->summary->children() as $child) {
                $body .= $child->asXML();
            }
            $body .= "{$news->summary}";
        }

        if (!empty($news->content['src'])) {
            // some src elements in some valid atom feeds contained no urls at all
            if (valid_url("{$news->content['src']}", TRUE)) {
                $original_url = "{$news->content['src']}";
            }
        }

        $author_found = FALSE;

        if (!empty($news->source->author->name)) {
            $original_author = "{$news->source->author->name}";
            $author_found = TRUE;
        } else if (!empty($news->author->name)) {
            $original_author = "{$news->author->name}";
            $author_found = TRUE;
        }

        if (!empty($feed_XML->author->name) && !$author_found) {
            $original_author = "{$feed_XML->author->name}";
        }

        $original_url = _parser_common_syndication_link($news->link);

        $item = array();
        $item['title'] = _parser_common_syndication_title($title, $body);
        $item['description'] = $body;
        $item['author'] = $original_author;
        $item['timestamp'] = _parser_common_syndication_parse_date(isset($news->published) ? "{$news->published}" : "{$news->issued}");
        $item['url'] = trim($original_url);
        if (valid_url($item['url']) && !valid_url($item['url'], TRUE) && !empty($base)) {
            $item['url'] = $base . $item['url'];
        }
        // Fall back on URL if GUID is empty.
        if (!empty($guid)) {
            $item['guid'] = $guid;
        } else {
            $item['guid'] = $item['url'];
        }
        $item['tags'] = isset($additional_taxonomies['ATOM Categories']) ? $additional_taxonomies['ATOM Categories'] : array();
        $item['domains'] = isset($additional_taxonomies['ATOM Domains']) ? $additional_taxonomies['ATOM Domains'] : array();
        $parsed_source['items'][] = $item;
    }
    return $parsed_source;
}

/**
 * Parse RDF Site Summary (RSS) 1.0 feeds in RDF/XML format.
 *
 * @see http://web.resource.org/rss/1.0/
 */
function _parser_common_syndication_RDF10_parse($feed_XML) {
    // Declare some canonical standard prefixes for well-known namespaces:
    static $canonical_namespaces = array(
    'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
    'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
    'xsi' => 'http://www.w3.org/2001/XMLSchema-instance#',
    'xsd' => 'http://www.w3.org/2001/XMLSchema#',
    'owl' => 'http://www.w3.org/2002/07/owl#',
    'dc' => 'http://purl.org/dc/elements/1.1/',
    'dcterms' => 'http://purl.org/dc/terms/',
    'dcmitype' => 'http://purl.org/dc/dcmitype/',
    'foaf' => 'http://xmlns.com/foaf/0.1/',
    'rss' => 'http://purl.org/rss/1.0/',
    );

    // Get all namespaces declared in the feed element, with special handling
    // for PHP versions prior to 5.1.2 as they don't handle namespaces.
    $namespaces = version_compare(phpversion(), '5.1.2', '<') ? array() : $feed_XML->getNamespaces(TRUE);

    // Process the <rss:channel> resource containing feed metadata:
    foreach ($feed_XML->children($canonical_namespaces['rss'])->channel as $rss_channel) {
        $parsed_source = (object) array(
            'title' => _parser_common_syndication_title((string) $rss_channel->title),
            'description' => (string) $rss_channel->description,
            'options' => (object) array('link' => (string) $rss_channel->link),
            'items' => array(),
        );
        break;
    }

    // Process each <rss:item> resource contained in the feed:
    foreach ($feed_XML->children($canonical_namespaces['rss'])->item as $rss_item) {

        // Extract all available RDF statements from the feed item's RDF/XML
        // tags, allowing for both the item's attributes and child elements to
        // contain RDF properties:
        $rdf_data = array();
        foreach ($namespaces as $ns => $ns_uri) {
            // Note that we attempt to normalize the found property name
            // namespaces to well-known 'standard' prefixes where possible, as the
            // feed may in principle use any arbitrary prefixes and we should
            // still be able to correctly handle it.
            foreach ($rss_item->attributes($ns_uri) as $attr_name => $attr_value) {
                $ns_prefix = ($ns_prefix = array_search($ns_uri, $canonical_namespaces)) ? $ns_prefix : $ns;
                $rdf_data[$ns_prefix . ':' . $attr_name][] = (string) $attr_value;
            }
            foreach ($rss_item->children($ns_uri) as $rss_property) {
                $ns_prefix = ($ns_prefix = array_search($ns_uri, $canonical_namespaces)) ? $ns_prefix : $ns;
                $rdf_data[$ns_prefix . ':' . $rss_property->getName()][] = (string) $rss_property;
            }
        }

        // Declaratively define mappings that determine how to construct the result object.
        $item = _parser_common_syndication_RDF10_item($rdf_data, (object) array(
                'title' => array('rss:title', 'dc:title'),
                'description' => array('rss:description', 'dc:description', 'content:encoded'),
                'options' => (object) array(
                'guid' => 'rdf:about',
                'timestamp' => 'dc:date',
                'author' => array('dc:creator', 'dc:publisher'),
                'url' => array('rss:link', 'rdf:about'),
                'tags' => 'dc:subject',
                ),
            ));

        // Special handling for the title:
        $item['title'] = _parser_common_syndication_title($item['title'], $item['description']);

        // Parse any date/time values into Unix timestamps:
        $item['timestamp'] = _parser_common_syndication_parse_date($item['timestamp']);

        // If no author name found, use the feed title:
        if (empty($item['author'])) {
            $item['author'] = $parsed_source['title'];
        }

        // If no GUID found, use the URL of the feed.
        if (empty($item['guid'])) {
            $item['guid'] = $item['url'];
        }

        // Add every found RDF property to the feed item.
        $item['rdf'] = (object) array();
        foreach ($rdf_data as $rdf_property => $rdf_value) {
            // looks nicer in the mapper UI
            // @todo: revisit, not used with feedapi mapper anymore.
            $rdf_property = str_replace(':', '_', $rdf_property);
            $item['rdf'][$rdf_property] = $rdf_value;
        }

        $parsed_source['items'][] = $item;
    }

    return $parsed_source;
}

function _parser_common_syndication_RDF10_property($rdf_data, $rdf_properties = array()) {
    $rdf_properties = is_array($rdf_properties) ? $rdf_properties : array_slice(func_get_args(), 1);
    foreach ($rdf_properties as $rdf_property) {
        if ($rdf_property && !empty($rdf_data[$rdf_property])) {
            // remove empty strings
            return array_filter($rdf_data[$rdf_property], 'strlen');
        }
    }
}

function _parser_common_syndication_RDF10_item($rdf_data, $mappings) {
    foreach (get_object_vars($mappings) as $k => $v) {
        if (is_object($v)) {
            $mappings->$k = _parser_common_syndication_RDF10_item($rdf_data, $v);
        } else {
            $values = _parser_common_syndication_RDF10_property($rdf_data, $v);
            $mappings->$k = !is_array($values) || count($values) > 1 ? $values : reset($values);
        }
    }
    return (object) $mappings;
}

/**
 * Parse RSS2.0 feeds.
 */
function _parser_common_syndication_RSS20_parse($feed_XML) {
    $parsed_source = array();
    // Detect the title.
    $parsed_source['title'] = isset($feed_XML->channel->title) ? _parser_common_syndication_title("{$feed_XML->channel->title}") : "";
    // Detect the description.
    $parsed_source['description'] = isset($feed_XML->channel->description) ? "{$feed_XML->channel->description}" : "";
    // Detect the link.
    $parsed_source['link'] = isset($feed_XML->channel->link) ? "{$feed_XML->channel->link}" : "";
    $parsed_source['items'] = array();

    foreach ($feed_XML->xpath('//item') as $news) {
        $category = $news->xpath('category');
        // Get children for current namespace.
        if (version_compare(phpversion(), '5.1.2', '>')) {
            $content = (array) $news->children('http://purl.org/rss/1.0/modules/content/');
        }
        $news = (array) $news;
        $news['category'] = $category;

        if (isset($news['title'])) {
            $title = "{$news['title']}";
        } else {
            $title = '';
        }

        $body = null;
        if (isset($news['description'])) {
            $body = "{$news['description']}";
        }
        // Some sources use content:encoded as description i.e.
        // PostNuke PageSetter module.
        if (isset($news['encoded'])) {  // content:encoded for PHP < 5.1.2.
            if (strlen($body) < strlen("{$news['encoded']}")) {
                $body = "{$news['encoded']}";
            }
        }
        if (isset($content['encoded'])) { // content:encoded for PHP >= 5.1.2.
            if (strlen($body) < strlen("{$content['encoded']}")) {
                $body = "{$content['encoded']}";
            }
        }

        if (!isset($body)) {
            $body = "{$news['title']}";
        }
        $original_author = null;
        if (!empty($feed_XML->channel->title)) {
            $original_author = "{$feed_XML->channel->title}";
        }

        if (!empty($news['link'])) {
            $original_url = "{$news['link']}";
        } else {
            $original_url = NULL;
        }

        if (isset($news['guid'])) {
            $guid = "{$news['guid']}";
        } else {
            // Attempt to fall back on original URL if GUID is not present.
            $guid = $original_url;
        }

        $additional_taxonomies = array();
        $additional_taxonomies['RSS Categories'] = array();
        $additional_taxonomies['RSS Domains'] = array();
        if (isset($news['category'])) {
            foreach ($news['category'] as $category) {
                $additional_taxonomies['RSS Categories'][] = "{$category}";
                if (isset($category['domain'])) {
                    $domain = "{$category['domain']}";
                    if (!empty($domain)) {
                        if (!isset($additional_taxonomies['RSS Domains'][$domain])) {
                            $additional_taxonomies['RSS Domains'][$domain] = array();
                        }
                        $additional_taxonomies['RSS Domains'][$domain][] = count($additional_taxonomies['RSS Categories']) - 1;
                    }
                }
            }
        }

        $item = array();
        $item['title'] = _parser_common_syndication_title($title, $body);
        $item['description'] = $body;
        $item['author'] = $original_author;
        if (isset($news['pubDate'])) {
            $item['timestamp'] = _parser_common_syndication_parse_date($news['pubDate']);
        } else {
            $item['timestamp'] = time();
        }
        $item['url'] = trim($original_url);
        $item['guid'] = $guid;
        $item['domains'] = $additional_taxonomies['RSS Domains'];
        $item['tags'] = $additional_taxonomies['RSS Categories'];
        $parsed_source['items'][] = $item;
    }
    return $parsed_source;
}

/**
 * Parse a date comes from a feed.
 *
 * @param $date_string
 *  The date string in various formats.
 * @return
 *  The timestamp of the string or the current time if can't be parsed
 */
function _parser_common_syndication_parse_date($date_str) {
    $parsed_date = strtotime($date_str);
    if ($parsed_date === FALSE || $parsed_date == -1) {
        $parsed_date = _parser_common_syndication_parse_w3cdtf($date_str);
    }
    return $parsed_date === FALSE ? time() : $parsed_date;
}

/**
 * Parse the W3C date/time format, a subset of ISO 8601.
 *
 * PHP date parsing functions do not handle this format.
 * See http://www.w3.org/TR/NOTE-datetime for more information.
 * Originally from MagpieRSS (http://magpierss.sourceforge.net/).
 *
 * @param $date_str
 *   A string with a potentially W3C DTF date.
 * @return
 *   A timestamp if parsed successfully or FALSE if not.
 */
function _parser_common_syndication_parse_w3cdtf($date_str) {
    if (preg_match('/(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(:(\d{2}))?(?:([-+])(\d{2}):?(\d{2})|(Z))?/', $date_str, $match)) {
        list($year, $month, $day, $hours, $minutes, $seconds) = array($match[1], $match[2], $match[3], $match[4], $match[5], $match[6]);
        // Calculate the epoch for current date assuming GMT.
        $epoch = gmmktime($hours, $minutes, $seconds, $month, $day, $year);
        if ($match[10] != 'Z') { // Z is zulu time, aka GMT
            list($tz_mod, $tz_hour, $tz_min) = array($match[8], $match[9], $match[10]);
            // Zero out the variables.
            if (!$tz_hour) {
                $tz_hour = 0;
            }
            if (!$tz_min) {
                $tz_min = 0;
            }
            $offset_secs = (($tz_hour * 60) + $tz_min) * 60;
            // Is timezone ahead of GMT?  If yes, subtract offset.
            if ($tz_mod == '+') {
                $offset_secs *= - 1;
            }
            $epoch += $offset_secs;
        }
        return $epoch;
    } else {
        return FALSE;
    }
}

/**
 * Extract the link that points to the original content (back to site or
 * original article)
 *
 * @param $links
 *  Array of SimpleXML objects
 */
function _parser_common_syndication_link($links) {
    $to_link = '';
    if (count($links) > 0) {
        foreach ($links as $link) {
            $link = $link->attributes();
            $to_link = isset($link["href"]) ? "{$link["href"]}" : "";
            if (isset($link["rel"])) {
                if ("{$link["rel"]}" == 'alternate') {
                    break;
                }
            }
        }
    }
    return $to_link;
}

/**
 * Prepare raw data to be a title
 */
function _parser_common_syndication_title($title, $body = FALSE) {
    $title = strip_tags($title);
    if (empty($title) && !empty($body)) {
        // Explode to words and use the first 3 words.
        $words = preg_split("/[\s,]+/", $body);
        $title = implode(' ', $words);
    }
    return $title;
}
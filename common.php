<?php

function mmtlog($str) {
    if (!is_string($str)) {
        ob_start();
        var_dump($str);
        $str = ob_get_contents();
        ob_end_clean();
    }
    $fd = @fopen(dirname(__FILE__) . '/log.txt', 'a');
    if ($fd) {
        fwrite($fd, $str . "\n");
    }
    @fclose($fd);
}

class Asset {
    const ASS_SITE = "http";
    const ASS_LJ = "lj";
    const ASS_BLOGSPOT = "blogspot";
    const ASS_LIVEINTERNET = "li";

    static $types = array(
        self::ASS_SITE, self::ASS_LJ, self::ASS_BLOGSPOT, self::ASS_LIVEINTERNET
    );

    // Converts the asset to string representation
    public static function encode($ass) {
        return $ass->value;
    }

    // Parses string representation and returns asset object
    public static function decode($text) {
        if (strlen($text) < 1) {
            return null;
        }

        $pos = strpos($text, '://');
        if ($pos === false) {
            $ass = parse(self::ASS_SITE, $text);
        } else {
            //$type = substr($text, 0, $pos);
            //$value = substr($text, $pos + 3);
            //$ass = self::createParsed($type, $value);
            $ass = new Asset();
            $ass->value = $text;
        }
        self::prepare($ass);
        return $ass;
    }

    public static function parse($type, $value) {
        $url = realParseUrl($value);
        $level = @count($url['domains']);
        if ($url) {

            if ($level > 6) {
                return false;
            }

            if (!isset($url['scheme'])) {
                $url['scheme'] = 'http';
            }
            $host = $url['host'];
            $hostLow = strtolower($host);

            if ($level == 3) {
                $sub = strtolower($url['domains'][2]);
                if ($sub != 'www' && $sub != 'ftp') {

                    if (false !== ($val = beforeSuffix($hostLow, ".livejournal.com"))) {
                        return self::createParsed(self::ASS_LJ, $val);
                    }
                    if (false !== ($val = beforeSuffix($hostLow, ".blogspot.com"))) {
                        return self::createParsed(self::ASS_BLOGSPOT, $val);
                    }
                    if (false !== ($val = beforeSuffix($hostLow, ".liveinternet.ru"))) {
                        return self::createParsed(self::ASS_LIVEINTERNET, $val);
                    }
                }
            }

            $val = $url['host'];
            $ass = self::createParsed(self::ASS_SITE, $val);
            return $ass;
        }
        return null;
    }

    private static function createParsed($type, $value) {
        $ass = new Asset();
        $ass->value = $type . '://' . $value;
        $ass->title = $value;
        self::prepare($ass);
        return $ass;
    }

    /*
    public function getUrl() {
        self::prepare($this);
        return $this->url;
    }
     */

    public static function getUrl($ass) {
        self::prepare($ass);
        return $ass->url;
    }

    public function getType($ass) {
        self::prepare($ass);
        return $ass->type;
    }

    public function getName($ass) {
        self::prepare($ass);
        return $ass->name;
    }

    public static function prepare($ass) {
        if (!isset($ass->url)) {
            self::getUrlTitleType($ass, $url, $title, $type);
            $ass->url = $url;
            $ass->title = $title;
            $ass->type = $type;
        }
    }

    private static function getUrlTitleType($ass, &$url, &$title, &$type) {
        $val = $ass->value;
        $pos = strpos($val, '://');

        if ($pos !== false) {
            $type = substr($val, 0, $pos);
            $val = substr($val, $pos + 3);
        } else {
            $type = Asset::ASS_SITE;
        }
        $ass->name = $val;
        $title = $val;

        switch ($type) {
            case 'lj':
                $url = 'http://' . $val . '.livejournal.com';
                break;
            case 'blogspot':
                $url = 'http://' . $val . '.blogspot.com';
                break;
            case 'liveinternet':
                $url = 'http://' . $val . '.liveinternet.ru';
                break;
            default: // site
                $url = $type . '://' . $val;
                break;
        }
        if (@$ass->title) {
            $title = $ass->title;
        }
    }

    public static function getLink($ass) {
        self::prepare($ass);
        return '<a href="' . $ass->url . '" class="ass a_' . $ass->type . '">' . $ass->title . '</a>';
    }

}

// If the string contains the specified substring,
// the part before this string is returned.
function substringBefore($string, $end) {
    $pos = strpos($string, $end);
    if ($pos !== false) {
        return substr($string, 0, $pos);
    }
    return $string;
}

// checks whether specified string starts woth another string
function startsWith($str, $start) {
    if (strlen($start) < 1) {
        return true;
    }
    return strpos($str, $start) === 0;
}

// Checks whether string ends with specified string
function endsWith($str, $end) {
    $pos = strlen($str) - strlen($end);
    if ($pos < 0) {
        return false;
    }
    return (strpos($str, $end, $pos) === $pos);
}

// Returns beginning of the str if it ends with the suffix, or false if suffix mismatch or
// string beginning before suffix is empty.
function beforeSuffix($str, $suffix) {
    $result = false;
    $len = strlen($str);
    $slen = strlen($suffix);
    if (($len > $slen) && endsWith($str, $suffix)) {
        $result = substr($str, 0, $len - $slen);
    }
    return $result;
}

// $nn is DOMNodeList or simple type
function xmlNodeListGetTextContent($nn) {
    $result = null;
    if (is_object($nn)) {
        $result = "";
        foreach ($nn as $n) {
            $result .= $n->textContent;
        }
    } else {
        $result = (string) $nn;
    }
    return $result;
}

/**
 * Parses user-entered URL as user would expect (test.com will be parsed as http://test.com)
 * @param string $text to parse
 * @return URL data as associative array.
 */
function realParseUrl($text) {
    // TODO: Check invalid characters!
    $url = @parse_url($text);
    if ($url) {
        if (!isset($url['host']) && isset($url['path'])) {
            // very bad
            $path = $url['path'];
            $host = substringBefore($path, '/');
            if (strlen($host) > 0) {
                $path = substr($path, strlen($host));
                $text = 'http://' . $host . $path;
                $url = @parse_url($text);
            }
        }
        $host = $url['host'];
        $domains = explode('.', $host);
        foreach ($domains as $domain) {
            if (strlen($domain) < 1) {
                return false;
            }
        }
        // Invert order of domains so that domain[0] is the TLD
        $domains2 = array();
        for ($i = count($domains) - 1; $i >= 0; $i--) {
            $domains2[] = $domains[$i];
        }
        $url['domains'] = $domains2;
    }
    return $url;
}

function metamatic_invoke_method($url, $method, $args, $key = null) {
    $argstr = json_encode($args);
    $postParams = array(
        'method' => $method,
        'args' => $argstr,
    );
    if ($key !== null) {
        $nonce = mt_rand() . mt_rand() . mt_rand();
        $signstr = $nonce . $key . $method . $argstr;
        $signature = base64_encode(md5($signstr, true));
        $postParams['nonce'] = $nonce;
        $postParams['signature'] = $signature;
    }

    $retval = null;
    $response = metamatic_http_post($url, $postParams);
    if ($response && @$response->data) {
        $pos = strpos($response->data, '}<!--');
        if($pos !== false) {
            $response->data = substr($response->data, 0, $pos + 1);
        }
        $retval = @json_decode($response->data);
    }
    return $retval;
}

function metamatic_get_this_site() {
    $scheme = (@$_SERVER['HTTPS'] == "on") ? 'https://' : '';
    $host = $_SERVER['SERVER_NAME'];
    $pos = strpos($host, ':');
    if ($pos !== false) {
        $host = substr($host, 0, $pos);
    }
    $port = (int) $_SERVER['SERVER_PORT'];
    if ($port && $port != 80) {
        $port = ':' . $port;
    } else {
        $port = '';
    }
    $result = $scheme . $host . $port;
    return $result;
}

function metamatic_esc($str) {
    return htmlspecialchars($str, ENT_COMPAT | ENT_NOQUOTES, "UTF-8");
}

function metamatic_esc_attr($str) {
    return htmlspecialchars($str, ENT_COMPAT, "UTF-8");
}
<?php
namespace PoP\ComponentModel\Misc;

class GeneralUtils
{
    // Taken from http://stackoverflow.com/questions/4356289/php-random-string-generator
    public static function generateRandomString($length = 6, $addtime = true, $characters = 'abcdefghijklmnopqrstuvwxyz')
    {
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }

        if ($addtime) {
            $randomString .= time();
        }
        return $randomString;
    }

    public static function isError($thing)
    {
        return $thing && $thing instanceof \PoP\ComponentModel\Error;
    }

    // Taken from https://gist.github.com/SeanCannon/6585889
    public static function arrayFlatten($items, $deep = false)
    {
        if (!is_array($items)) {
            return [$items];
        }

        return array_reduce($items, function ($carry, $item) use ($deep) {
            return array_merge($carry, $deep ? self::arrayFlatten($item) : (is_array($item) ? $item : [$item]));
        }, []);
    }

    /**
     * Add paramters "key" => "value" to the URL
     * Implementation based on that from https://stackoverflow.com/a/5809881
     *
     * @param array $keyValues
     * @param string $url
     * @return string
     */
    public static function addQueryArgs(array $keyValues, string $url): string
    {
        if (!$keyValues) {
            return $url;
        }

        $url_parts = parse_url($url);
        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $params);
        } else {
            $params = array();
        }

        $params = array_merge(
            $params,
            $keyValues
        );

        // Note that this will url_encode all values
        $url_parts['query'] = http_build_query($params);
        $port = ($url_parts['port'] == "80") ? "" : (":" . $url_parts['port']);
        return $url_parts['scheme'] . '://' . $url_parts['host'] . $port . $url_parts['path'] . '?' . $url_parts['query'];
    }

    /**
     * Add paramters "key" => "value" to the URL
     * Implementation based on that from https://stackoverflow.com/a/5809881
     *
     * @param array $keyValues
     * @param string $url
     * @return string
     */
    public static function removeQueryArgs(array $keys, string $url): string
    {
        if (!$keys) {
            return $url;
        }

        $url_parts = parse_url($url);
        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $params);
        } else {
            $params = array();
        }

        // Remove the indicated keys
        $params = array_filter(
            $params,
            function ($param) use ($keys) {
                return in_array($param, $keys);
            },
            ARRAY_FILTER_USE_KEY
        );

        // Note that this will url_encode all values
        $url_parts['query'] = http_build_query($params);
        $port = ($url_parts['port'] == "80") ? "" : (":" . $url_parts['port']);
        return $url_parts['scheme'] . '://' . $url_parts['host'] . $port . $url_parts['path'] . ($url_parts['query'] ? '?' . $url_parts['query'] : '');
    }

    public static function maybeAddTrailingSlash(string $text): string
    {
        return rtrim($text, '/\\') . '/';
    }
}

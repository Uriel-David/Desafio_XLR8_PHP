<?php

namespace XLR8;

class Cache
{
    private static $cache = [];

    public static function get($key)
    {
        if (isset(self::$cache[$key])) {
            $item = self::$cache[$key];

            if ($item['expire_time'] >= time()) {
                return $item['data'];
            } else {
                self::delete($key);
            }
        }

        return null;
    }

    public static function set($key, $value, $expireSeconds = 43200)
    {
        $expireTime = time() + $expireSeconds;
        self::$cache[$key] = [
            'data' => $value,
            'expire_time' => $expireTime,
        ];
    }

    public static function delete($key)
    {
        if (isset(self::$cache[$key])) {
            unset(self::$cache[$key]);
        }
    }

    public static function clear()
    {
        self::$cache = [];
    }
}

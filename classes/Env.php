<?php

class Env
{  
    protected static $env = [];
    
    public static function get($key, $default_value = null)
    {
        return isset(self::$env[$key]) ? self::$env[$key] : $default_value;
    }
    
    public static function init($path)
    {
        if (!file_exists($path)) {
            throw new \Exception("Файл .env не найден по указанному пути: $path");
        }

        $env = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, '"');
            $value = trim($value, "'");

            $env[$key] = $value;
        }

        self::$env = $env;
    }
}


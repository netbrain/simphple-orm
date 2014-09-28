<?php

namespace SimphpleOrm\Config;


class Config {
    private static $config = null;

    public static function get($section = null){
        if(is_null(self::$config)){
            self::$config = parse_ini_file(getcwd().'/config.ini', true);
        }

        if(is_null($section)){
            return self::$config;
        }

        return self::$config[$section];
    }

    public static function getMysql() {
        $config = self::get('mysql');
        if(!isset($config['port'])){
            $config['port'] = null;
        }

        if(!isset($config['socket'])){
            $config['socket'] = null;
        }

        return $config;
    }
}
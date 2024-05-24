<?php

class Config
{
    public static function get($name)
    {
        $configFilePath = ROOT . "/conf.php";

        if (!file_exists($configFilePath)) {
            return false;
        }

        $config = require $configFilePath;

        return $config[$name] ?? false;
    }
}
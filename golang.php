<?php

class Golang
{
    public static function check(): bool
    {
        $version = shell_exec('go version');
        return !empty($version) && mb_substr($version, 0, 13) === 'go version go';
    }

    public static function dir()
    {
        $go_env = shell_exec('go env GOROOT');
        return empty($go_env) ? false : mb_substr($go_env, 0, -1);
    }

    public static function check_extdoc(string $dir): bool
    {
        return is_dir($dir);
    }
}
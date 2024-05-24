<?php

function dirtofilelist($dir, $ext = null)
{
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $file)) {
                    yield from dirtofilelist($dir . DIRECTORY_SEPARATOR . $file, $ext);
                }
                else {
                    $fileExt = pathinfo($file, PATHINFO_EXTENSION);
                    if ($ext === null || $fileExt === $ext) {
                        yield $dir . DIRECTORY_SEPARATOR . $file;
                    }
                }
            }
        }
    }
}

function curl_require($url, $data = null, $header = null)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    if ($header !== null) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    }
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $output = curl_exec($ch);
    if (curl_errno($ch)) {
        log_message('system_error', 'CURL ERROR: ' . curl_error($ch) . ' URL: ' . $url . ' DATA: ' . var_export($data, true));
        return false;
    }
    curl_close($ch);
    return $output;
}

function log_message($type, $message)
{
    $log_types = [
        'run' => ['color' => '32', 'file' => 'run.log'],
        'error' => ['color' => '33', 'file' => 'error.log'],
        'system_error' => ['color' => '34', 'file' => 'system_error.log'],
        'exit' => ['color' => '31', 'file' => 'exit.log'],
    ];

    if (isset($log_types[$type])) {
        $log = date('Y-m-d H:i:s ') . "\033[" . $log_types[$type]['color'] . "m" . $message . "\033[0m";
        echo $log . PHP_EOL;
        file_put_contents(ROOT . '/' . $log_types[$type]['file'], $log . PHP_EOL, FILE_APPEND);
    }
}

function exit_log($log)
{
    log_message('exit', $log);
    exit;
}
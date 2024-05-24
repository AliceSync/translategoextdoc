<?php

date_default_timezone_set('Asia/Shanghai');

define('ROOT', __DIR__);

$required_files = ['config.php', 'function.php', 'translation.php', 'log.php', 'golang.php'];
foreach ($required_files as $file) {
    require_once ROOT . '/' . $file;
}

if (golang::check() === false) {
    exit_log('Go is not installed.');
}

$dir = golang::dir();
if ($dir === false) {
    exit_log('Go is not installed.');
}

$extdoc = $dir . DIRECTORY_SEPARATOR . 'src';
if (!golang::check_extdoc($extdoc)) {
    exit_log('Go source code is not found.');
}

echo '请确定您已备份好SRC目录[y/N]';
if (strtolower(trim(fgets(STDIN))) !== 'y') {
    exit_log('用户取消操作');
}

$translation = new Translation('baidu_free');
$log         = new Log();

foreach (dirtofilelist($extdoc, 'go') as $file) {
    if ($log->check($file)) {
        log_message('run', 'FILE: [' . $file . '] 已翻译');
        continue;
    }
    log_message('run', 'FILE: [' . $file . '] 开始翻译');
    $fileContent = file_get_contents($file);
    if ($fileContent === false) {
        exit_log('读取文件失败,FILE: ' . $file);
    }
    $file_md5    = md5_file($file);
    $fileContent = explode("\n", $fileContent);
    $fileTmp     = [];
    foreach ($fileContent as $keycontent => $linecontent) {
        $fileTmp[] = $linecontent;
        if (mb_strpos(trim($linecontent), '//') === 0) {
            $temp        = mb_substr($linecontent, 0, mb_strpos($linecontent, '/') + 2);
            $linecontent = trim(mb_substr(ltrim($linecontent), 2));
            $err         = 0;
            while (true) {
                $zh_linecontent = $translation->get($linecontent);
                if ($zh_linecontent === false) {
                    $err++;
                    if ($err > 3) {
                        exit_log('翻译失败,FILE: ' . $file . ' LINE:' . $keycontent . ' CONTENT:' . $linecontent);
                    }
                    sleep(1);
                    log_message('run', '翻译失败,重试第[' . $err . ']次');
                }
                else {
                    $fileTmp[] = $temp . ' ' . $zh_linecontent;
                    break;
                }
            }
        }
    }
    if (@file_put_contents($file, implode("\n", $fileTmp)) === false) {
        exit_log('写入文件失败,FILE: ' . $file);
    }
    $log->set($file, $file_md5);
    log_message('run', 'FILE: [' . $file . '] 翻译完成');
}

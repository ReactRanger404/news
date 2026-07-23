<?php
/**
 * 调试页面：绕过错误处理器，直接显示错误
 * 用完后请删除或重命名
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h2>系统环境</h2>";
echo "<p>PHP 版本: " . phpversion() . "</p>";
echo "<p>操作系统: " . PHP_OS_FAMILY . "</p>";
echo "<p>服务器软件: " . ($_SERVER['SERVER_SOFTWARE'] ?? '未知') . "</p>";

echo "<h2>关键函数可用性</h2>";
echo "exec(): " . (function_exists('exec') ? '✅ 可用' : '❌ 不可用') . "<br>";
echo "opendir(): " . (function_exists('opendir') ? '✅ 可用' : '❌ 不可用') . "<br>";
echo "glob(): " . (function_exists('glob') ? '✅ 可用' : '❌ 不可用') . "<br>";
echo "file_get_contents(): " . (function_exists('file_get_contents') ? '✅ 可用' : '❌ 不可用') . "<br>";

echo "<h2>文件检查</h2>";
$files_to_check = [
    'common.php',
    'config.json',
    'data.json',
    'archive/',
];
foreach ($files_to_check as $f) {
    $full = __DIR__ . '/' . $f;
    if (is_dir($full)) {
        echo "{$f}: ✅ 目录存在 (可读: " . (is_readable($full) ? '是' : '否') . ")<br>";
    } elseif (file_exists($full)) {
        echo "{$f}: ✅ 文件存在 (可读: " . (is_readable($full) ? '是' : '否') . ", 大小: " . filesize($full) . ")<br>";
    } else {
        echo "{$f}: ❌ 不存在<br>";
    }
}

echo "<h2>config.json</h2>";
$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
if ($config) {
    echo "✅ 解析成功，archive_dir = " . ($config['archive_dir'] ?? '未设置') . "<br>";
} else {
    echo "❌ 解析失败<br>";
}

echo "<h2>尝试加载 show.php</h2>";
try {
    require_once __DIR__ . '/show.php';
    echo "✅ show.php 正常执行<br>";
} catch (Throwable $e) {
    echo "❌ 错误: " . $e->getMessage() . "<br>";
    echo "位置: " . $e->getFile() . ":" . $e->getLine() . "<br>";
}

echo "<h2>归档日期测试</h2>";
require_once __DIR__ . '/common.php';
$dates = get_archive_dates();
echo "get_archive_dates() 返回 " . count($dates) . " 个日期<br>";
if (!empty($dates)) {
    echo "最近: " . $dates[0] . " ~ 最旧: " . $dates[count($dates)-1] . "<br>";
}

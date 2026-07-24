<?php
/**
 * 后台爬取守护脚本
 *
 * 使用方法：
 *   Windows:     php background_crawler.php
 *   Linux/Mac:   nohup php background_crawler.php > /dev/null 2>&1 &
 *
 * 默认每5小时（300分钟）自动执行一次爬取任务
 * 按 Ctrl+C 停止
 *
 * 本系统仅用于学习和研究目的
 */

// 直接运行，不依赖 common.php（避免 session 冲突）
$config_file = __DIR__ . '/config.json';

function read_config($file) {
    if (!file_exists($file)) return [];
    $fp = fopen($file, 'r');
    if (!$fp) return [];
    flock($fp, LOCK_SH);
    $content = '';
    while (!feof($fp)) $content .= fread($fp, 8192);
    flock($fp, LOCK_UN);
    fclose($fp);
    return json_decode($content, true) ?: [];
}

$config = read_config($config_file);
$interval = ($config['crawl_interval'] ?? 300) * 60; // 默认300分钟 = 5小时

echo "============================================\n";
echo "  开源情报系统 - 后台爬取守护脚本\n";
echo "  每 " . ($interval / 60) . " 分钟自动执行一次爬取\n";
echo "  按 Ctrl+C 停止\n";
echo "============================================\n\n";

// 归档使用 archive.json 单文件（无需目录）
$archive_file = __DIR__ . '/archive.json';
echo "[初始化] 归档文件: {$archive_file}\n";

$run_count = 0;

while (true) {
    $run_count++;
    $now = date('Y-m-d H:i:s');
    echo "[$now] === 第 {$run_count} 次爬取开始 ===\n";

    try {
        ob_start();
        include __DIR__ . '/crawler.php';
        $output = ob_get_clean();
        echo $output;
    } catch (Exception $e) {
        echo "爬取异常: " . $e->getMessage() . "\n";
    }

    $next_time = date('Y-m-d H:i:s', time() + $interval);
    echo "[完成] 等待 " . ($interval / 60) . " 分钟，下一次爬取: {$next_time}\n\n";

    // 使用睡眠循环，每60秒检测一次，支持优雅退出
    for ($i = 0; $i < $interval; $i += 60) {
        sleep(min(60, $interval - $i));
    }
}

/**
 * 读取JSON文件（简化版）
 */
function json_read($file) {
    if (!file_exists($file)) return [];
    $fp = fopen($file, 'r');
    if (!$fp) return [];
    flock($fp, LOCK_SH);
    $content = '';
    while (!feof($fp)) $content .= fread($fp, 8192);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

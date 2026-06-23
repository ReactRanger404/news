<?php
/**
 * 后台爬取守护脚本
 *
 * 使用方法：在终端中运行 php background_crawler.php
 * 会自动每15分钟执行一次爬取任务
 * 关闭终端即停止
 */

echo "============================================\n";
echo "  开源情报系统 - 后台爬取守护脚本\n";
echo "  每15分钟自动执行一次爬取\n";
echo "  按 Ctrl+C 停止\n";
echo "============================================\n\n";

$config = json_read(__DIR__ . '/config.json');
$interval = ($config['crawl_interval'] ?? 15) * 60;

while (true) {
    $now = date('Y-m-d H:i:s');
    echo "[$now] 开始执行爬取任务...\n";

    try {
        // 引入爬取脚本
        ob_start();
        include __DIR__ . '/crawler.php';
        $output = ob_get_clean();
        echo $output;
    } catch (Exception $e) {
        echo "爬取异常: " . $e->getMessage() . "\n";
    }

    echo "等待 {$interval} 秒后下一次爬取...\n\n";
    sleep($interval);
}

/**
 * 读取JSON文件（简化版，避免依赖common.php）
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

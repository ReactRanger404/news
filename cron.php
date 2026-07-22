<?php
/**
 * 远程爬虫触发器 - 通用方案
 *
 * 配合外部定时服务（如 cron-job.org）使用：
 * 每5小时访问 https://你的域名/cron.php?token=你的密钥
 *
 * 使用方法：
 * 1. 在 cron-job.org 添加定时任务
 * 2. URL填：https://你的域名/cron.php?token=你的密钥
 * 3. 频率：Every 5 hours
 * 4. token 在 config.json 的 cron_token 字段，默认：osint_4261e763
 *
 * 本系统仅用于学习和研究目的，请遵守相关法律法规
 */

require_once __DIR__ . '/common.php';

// 安全验证：必须带正确的token才能触发
$token = get('token', '');
$expected_token = getenv('CRON_TOKEN');

if (empty($expected_token)) {
    // 如果环境变量没设置，使用配置文件中的默认token
    $config = json_read(__DIR__ . '/config.json');
    $expected_token = $config['cron_token'] ?? '';
}

if (empty($token) || $token !== $expected_token) {
    http_response_code(403);
    echo "Forbidden: invalid or missing token\n";
    $remote_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    log_message("❌ 爬虫触发失败：token无效 (IP: {$remote_ip})");
    exit;
}

$start_time = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] 爬虫开始执行...\n";
log_message("🔄 远程爬虫被触发");

try {
    // 先检测可用性（只检查上次未检测或超24小时的源）
    $sources = json_read(__DIR__ . '/sources.json');
    $checked = 0;
    $updated = 0;
    $one_day_ago = time() - 86400;

    foreach ($sources as &$src) {
        $last_check = !empty($src['last_check']) ? strtotime($src['last_check']) : 0;
        if ($last_check < $one_day_ago) {
            $result = test_url($src['url'], 5);
            $src['last_check'] = date('Y-m-d H:i:s');
            $src['is_alive'] = $result['reachable'];
            $checked++;
            if ($result['reachable'] !== !empty($src['is_alive'])) $updated++;
            usleep(100000);
        }
    }

    if ($checked > 0) {
        json_write(__DIR__ . '/sources.json', $sources);
        echo "连通性检测: {$checked} 个 (更新 {$updated} 个)\n";
    }

    // 执行爬取
    $crawler_output = '';
    ob_start();
    require __DIR__ . '/crawler.php';
    $crawler_output = ob_get_clean();
    echo $crawler_output;

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    log_message("❌ 远程爬虫异常: " . $e->getMessage());
}

$elapsed = round(microtime(true) - $start_time, 2);
echo "[" . date('Y-m-d H:i:s') . "] 爬虫完成，耗时 {$elapsed}s\n";
log_message("✅ 远程爬虫完成，耗时 {$elapsed}s");

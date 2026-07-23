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

log_message("🔄 远程爬虫被触发（后台执行）");

// 后台启动爬虫，不阻塞 HTTP 响应（cron-job.org 超时很短）
$crawler_path = __DIR__ . '/crawler.php';
$started = false;

if (PHP_OS_FAMILY === 'Windows') {
    if (function_exists('popen')) {
        pclose(popen("start /B php \"{$crawler_path}\"", 'r'));
        $started = true;
    }
} else {
    if (function_exists('exec')) {
        exec("php \"{$crawler_path}\" > /dev/null 2>&1 &");
        $started = true;
    }
}

if ($started) {
    echo "[" . date('Y-m-d H:i:s') . "] 爬虫已在后台启动\n";
} else {
    echo "[" . date('Y-m-d H:i:s') . "] 错误：exec/popen 函数不可用，无法启动爬虫\n";
    log_message("❌ 远程爬虫启动失败：exec/popen 函数不可用");
}

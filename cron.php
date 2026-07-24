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

// 开启输出缓冲，确保 header() 在任何输出后仍能正常工作
ob_start();

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

log_message("🔄 远程爬虫被触发");

// 策略A：尝试用 exec 后台启动（独立进程，不阻塞）
$crawler_path = __DIR__ . '/crawler.php';
$started = false;

if (PHP_OS_FAMILY === 'Windows') {
    if (function_exists('popen')) {
        pclose(popen("start /B php \"{$crawler_path}\"", 'r'));
        $started = true;
    }
} else {
    // function_exists('exec') 在 disable_functions 时依然返回 true
    $exec_ok = function_exists('exec');
    if ($exec_ok) {
        $exec_ok = !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions') ?: '')));
    }
    if ($exec_ok) {
        exec("php \"{$crawler_path}\" > /dev/null 2>&1 &", $exec_output, $exit_code);
        $started = ($exit_code === 0);
    }
}

if ($started) {
    echo "[" . date('Y-m-d H:i:s') . "] 爬虫已在后台启动\n";
    exit;
}

// 策略B：exec 不可用，在当前进程执行（先断开 HTTP 连接再跑）
log_message("⚠️ exec 不可用，改用进程内执行");
ignore_user_abort(true);
set_time_limit(0);

// 立即给 cron-job.org 返回响应，关闭连接
$msg = "[" . date('Y-m-d H:i:s') . "] 爬虫开始执行（进程内）...\n";
echo $msg;

// 用 strlen 精确计算已输出字节数，避免 ob_get_length 在无缓冲时返回 false
$size = strlen($msg);
header("Content-Length: $size");
header("Connection: close");
if (ob_get_level()) ob_flush();
flush();
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// 在这里真正跑爬虫（HTTP 连接已断开，cron-job.org 不会超时）
$crawler_output = '';
$GLOBALS['_CRON_MODE'] = true; // 让 crawler.php 跳过登录检查
ob_start();
require $crawler_path;
$crawler_output = ob_get_clean();
log_message("✅ 远程爬虫完成（进程内执行）");

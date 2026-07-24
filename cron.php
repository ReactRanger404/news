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
    $exec_disabled_by_ini = false;
    if ($exec_ok) {
        $exec_ok = !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions') ?: '')));
        if (!$exec_ok) $exec_disabled_by_ini = true;
    }
    if ($exec_ok) {
        exec("php \"{$crawler_path}\" > /dev/null 2>&1 &", $exec_output, $exit_code);
        // 某些环境(如Railway)的shell对后台命令(&)返回非0退出码
        // 只要不是 php 找不到(127)，就认为启动成功
        $started = ($exit_code !== 127 && $exit_code !== -1);
    }
}

if ($started) {
    echo "[" . date('Y-m-d H:i:s') . "] 爬虫已在后台启动\n";
    exit;
}

// 策略B：exec 用不了时的后备方案
// 如果 exec 是被 php.ini 禁用的（老师服务器），进程内跑爬虫
// 如果 exec 可用但 php 命令找不到（Railway），跳过爬取避免 output too large
if (!$exec_disabled_by_ini) {
    log_message("⚠️ exec 可用但 php 命令不存在，跳过爬取（等待页面访问触发）");
    echo "[" . date('Y-m-d H:i:s') . "] php 命令不存在，跳过爬取\n";
    exit;
}

log_message("⚠️ exec 被禁用，改用进程内执行");
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
// 用回调吞噬所有输出，确保爬虫的日志不会回流到 HTTP 响应
while (ob_get_level()) ob_end_clean();
$GLOBALS['_CRON_MODE'] = true; // 让 crawler.php 跳过登录检查
ob_start(function($buf) { return ''; }); // 吞噬回调：任何输出都变空字符串
require $crawler_path;
ob_end_clean();
log_message("✅ 远程爬虫完成（进程内执行）");

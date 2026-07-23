<?php
/**
 * 开源情报系统 - 公共函数库
 *
 * 本系统仅用于学习和研究目的
 * 请遵守相关法律法规，不得用于非法用途
 */

// 错误处理：不抛出PHP原生报错
set_error_handler(function ($severity, $message, $file, $line) {
    log_message("PHP Error: [$severity] $message in $file:$line");
    return true;
});

// ---------- JSON 读写（带文件锁） ----------

/**
 * 自动爬取调度：每次页面加载时检查是否需要爬取
 */
function check_auto_crawl() {
    $config = json_read(__DIR__ . '/config.json');
    $interval = ($config['crawl_interval'] ?? 15) * 60;
    $marker_file = __DIR__ . '/.last_crawl';

    $last_crawl = 0;
    if (file_exists($marker_file)) {
        $last_crawl = (int) file_get_contents($marker_file);
    }

    $now = time();
    if (($now - $last_crawl) >= $interval) {
        // 在后台启动爬虫，不阻塞当前页面
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

        // 只有实际启动了爬虫才更新标记，防止反复尝试执行禁用函数导致白屏
        if ($started) {
            file_put_contents($marker_file, (string) $now, LOCK_EX);
        } else {
            log_message("⚠️ 无法启动后台爬虫：exec/popen 函数不可用，请改用 cron-job.org 触发");
            // 更新标记避免每次访问都重试刷屏
            file_put_contents($marker_file, (string) $now, LOCK_EX);
        }
    }
}

/**
 * 读取JSON文件（带文件锁）
 */
function json_read($file) {
    if (!file_exists($file)) {
        return [];
    }
    $fp = fopen($file, 'r');
    if (!$fp) {
        log_message("无法打开文件: $file");
        return [];
    }
    flock($fp, LOCK_SH);
    $content = '';
    while (!feof($fp)) {
        $content .= fread($fp, 8192);
    }
    flock($fp, LOCK_UN);
    fclose($fp);

    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

/**
 * 写入JSON文件（带文件锁）
 */
function json_write($file, $data) {
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $fp = fopen($file, 'w');
    if (!$fp) {
        log_message("无法写入文件: $file");
        return false;
    }
    flock($fp, LOCK_EX);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

// ---------- 登录认证 ----------

/**
 * 启动会话
 */
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * 检查是否已登录
 */
function is_logged_in() {
    init_session();
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * 要求登录，未登录则跳转
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: source.php?action=login');
        exit;
    }
}

/**
 * 执行登录
 */
function do_login($username, $password) {
    $config = json_read(__DIR__ . '/config.json');
    if ($username === $config['admin_user'] && md5($password) === $config['admin_pwd']) {
        init_session();
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        return true;
    }
    return false;
}

/**
 * 登出
 */
function do_logout() {
    init_session();
    session_destroy();
}

// ---------- HTTP 请求 ----------

/**
 * 获取随机 User-Agent
 */
function get_random_ua() {
    $uas = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    ];
    return $uas[array_rand($uas)];
}

/**
 * HTTP GET 请求
 */
function http_get($url, $timeout = 10) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_USERAGENT => get_random_ua(),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
        ],
    ]);

    $body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'body' => $body,
        'http_code' => $http_code,
        'total_time' => round($total_time, 2),
        'error' => $error,
    ];
}

/**
 * 测试URL连通性
 */
function test_url($url, $timeout = 10) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_NOBODY => false,
        CURLOPT_RANGE => '0-2048',
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_USERAGENT => get_random_ua(),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    curl_exec($ch);
    $result = [
        'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'total_time' => round(curl_getinfo($ch, CURLINFO_TOTAL_TIME), 2),
        'error' => curl_error($ch),
    ];
    curl_close($ch);

    $result['reachable'] = ($result['http_code'] > 0 && $result['http_code'] < 500);
    return $result;
}

// ---------- 日志 ----------

/**
 * 记录日志
 */
function log_message($msg) {
    $log_file = __DIR__ . '/logs/crawl.log';
    $dir = dirname($log_file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $time = date('Y-m-d H:i:s');
    $line = "[$time] $msg" . PHP_EOL;
    file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
}

/**
 * 读取日志
 */
function get_logs($lines = 100) {
    $log_file = __DIR__ . '/logs/crawl.log';
    if (!file_exists($log_file)) {
        return [];
    }
    $content = file_get_contents($log_file);
    $logs = explode("\n", trim($content));
    $logs = array_reverse(array_filter($logs));
    return array_slice($logs, 0, $lines);
}

// ---------- 安全过滤 ----------

/**
 * XSS过滤
 */
function xss_clean($str) {
    if (is_array($str)) {
        return array_map('xss_clean', $str);
    }
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * 获取POST参数（已过滤）
 */
function post($key, $default = '') {
    return isset($_POST[$key]) ? xss_clean(trim($_POST[$key])) : $default;
}

/**
 * 获取GET参数（已过滤）
 */
function get($key, $default = '') {
    return isset($_GET[$key]) ? xss_clean(trim($_GET[$key])) : $default;
}

// ---------- 响应输出 ----------

/**
 * JSON响应
 */
function json_response($data, $code = 200) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 生成唯一ID
 */
function gen_id($prefix = 'news') {
    return $prefix . '_' . uniqid() . '_' . substr(md5(mt_rand()), 0, 8);
}

/**
 * 生成分页HTML
 */
function pagination_html($current, $total, $base_url) {
    $total_pages = ceil($total / 80);
    if ($total_pages <= 1) return '';

    $html = '<nav><ul class="pagination justify-content-center">';

    if ($current > 1) {
        $prev = $current - 1;
        $html .= "<li class='page-item'><a class='page-link' href='{$base_url}&page={$prev}'>&laquo; 上一页</a></li>";
    }

    $start = max(1, $current - 3);
    $end = min($total_pages, $current + 3);

    if ($start > 1) {
        $html .= "<li class='page-item'><a class='page-link' href='{$base_url}&page=1'>1</a></li>";
        if ($start > 2) $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $current) ? 'active' : '';
        $html .= "<li class='page-item {$active}'><a class='page-link' href='{$base_url}&page={$i}'>{$i}</a></li>";
    }

    if ($end < $total_pages) {
        if ($end < $total_pages - 1) $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
        $html .= "<li class='page-item'><a class='page-link' href='{$base_url}&page={$total_pages}'>{$total_pages}</a></li>";
    }

    if ($current < $total_pages) {
        $next = $current + 1;
        $html .= "<li class='page-item'><a class='page-link' href='{$base_url}&page={$next}'>下一页 &raquo;</a></li>";
    }

    $html .= '</ul></nav>';
    return $html;
}

// ---------- 图片处理 ----------

/**
 * 判断新闻是否有真实图片（不是占位图、不是logo/默认图）
 */
function has_real_image($item) {
    $img = $item['image'] ?? '';
    if (empty($img)) return false;
    if (strpos($img, 'data:image/svg') !== false) return false;
    // 过滤 logo/图标/默认图
    if (preg_match('/\b(logo|icon|avatar|favicon|default|wx-test|placeholder|banner)\b/i', $img)) return false;
    // 过滤文件归档路径的默认占位图（同一URL被多篇文章共用）
    if (preg_match('#/fileftp/\d{4}/\d{2}/#i', $img)) return false;
    // 过滤站点主题/模板图（非文章内容）
    if (preg_match('#/(?:homepage|theme|template|assets)/#i', $img)) return false;
    // 过滤小尺寸UI元素
    if (preg_match('/\b(toparr|btn|sprite|thumb)\b/i', $img)) return false;
    // 过滤小尺寸图片（疑似头像/图标）
    if (preg_match('/[?&](w|width|size)=\d{1,2}/i', $img)) return false;
    if (preg_match('/[\/_](?:w_?\d{1,2}|h_?\d{1,2}|s_?\d{1,2})[\/_]/i', $img)) return false;
    return true;
}

/**
 * 获取新闻图片URL，无图片时返回内联SVG占位图（无需外部请求）
 */
function get_news_image($item) {
    if (has_real_image($item)) {
        return $item['image'];
    }
    // 生成一个简单的内联SVG占位图（无外部依赖，国内网络友好）
    $seed = crc32($item['id'] ?? $item['title'] ?? '0');
    $hue = $seed % 360;
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="250" viewBox="0 0 400 250">'
        . '<rect width="400" height="250" fill="hsl(' . $hue . ',20%,95%)"/>'
        . '<text x="200" y="120" text-anchor="middle" font-size="64" fill="hsl(' . $hue . ',30%,75%)">📰</text>'
        . '<text x="200" y="170" text-anchor="middle" font-size="14" fill="hsl(' . $hue . ',20%,70%)" font-family="sans-serif">暂无图片</text>'
        . '</svg>';
    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
}

// ---------- 归档系统 ----------

/**
 * 归档超出上限的旧新闻
 * 将最旧的新闻按发布日期分组写入 archive/YYYY-MM-DD.json
 */
function archive_old_news(&$news, $max_news) {
    $config = json_read(__DIR__ . '/config.json');
    if (empty($config['archive_enabled'])) return 0;

    if (count($news) <= $max_news) return 0;

    // 按发布时间升序排列（最旧的在前）
    usort($news, function ($a, $b) {
        $ta = strtotime($a['publish_time'] ?? $a['crawl_time'] ?? 'now');
        $tb = strtotime($b['publish_time'] ?? $b['crawl_time'] ?? 'now');
        return $ta - $tb;
    });

    $overflow = count($news) - $max_news;
    $to_archive = array_slice($news, 0, $overflow);
    $news = array_slice($news, $overflow);

    $archive_dir = __DIR__ . '/' . ($config['archive_dir'] ?? 'archive');
    if (!is_dir($archive_dir)) {
        mkdir($archive_dir, 0755, true);
    }

    // 按日期分组归档
    $grouped = [];
    foreach ($to_archive as $item) {
        $date = date('Y-m-d', strtotime($item['publish_time'] ?? $item['crawl_time'] ?? 'now'));
        $grouped[$date][] = $item;
    }

    $archived_count = 0;
    foreach ($grouped as $date => $items) {
        $archive_file = $archive_dir . '/' . $date . '.json';
        $existing = [];
        if (file_exists($archive_file)) {
            $existing = json_read($archive_file);
        }
        // 去重合并（按URL去重）
        $existing_urls = [];
        foreach ($existing as $e) {
            $existing_urls[md5($e['url'])] = true;
        }
        foreach ($items as $item) {
            if (!isset($existing_urls[md5($item['url'])])) {
                $existing[] = $item;
                $existing_urls[md5($item['url'])] = true;
            }
        }
        json_write($archive_file, $existing);
        $archived_count += count($items);
    }

    log_message("📦 已归档 {$archived_count} 条旧新闻至 {$archive_dir}/");
    return $archived_count;
}

/**
 * 读取归档新闻（支持日期范围）
 * $start_date / $end_date: YYYY-MM-DD 格式，为空则不限制
 */
function read_archive_news($start_date = '', $end_date = '') {
    $config = json_read(__DIR__ . '/config.json');
    $archive_dir = __DIR__ . '/' . ($config['archive_dir'] ?? 'archive');
    if (!is_dir($archive_dir)) return [];

    $all = [];
    $files = glob($archive_dir . '/*.json');
    if (empty($files)) return [];

    // 如果没指定日期范围，默认只读最近30天（性能优化）
    if (empty($start_date) && empty($end_date)) {
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));
    }

    foreach ($files as $file) {
        $fname = basename($file, '.json');
        // 验证文件名是日期格式
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fname)) continue;

        // 日期范围过滤
        if (!empty($start_date) && $fname < $start_date) continue;
        if (!empty($end_date) && $fname > $end_date) continue;

        $items = json_read($file);
        if (!empty($items)) {
            $all = array_merge($all, $items);
        }
    }

    return $all;
}

/**
 * 获取归档中所有可用的日期列表
 */
function get_archive_dates() {
    $config = json_read(__DIR__ . '/config.json');
    $archive_dir = __DIR__ . '/' . ($config['archive_dir'] ?? 'archive');
    if (!is_dir($archive_dir)) return [];

    $dates = [];
    $dh = opendir($archive_dir);
    if ($dh) {
        while (($file = readdir($dh)) !== false) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2})\.json$/', $file, $m)) {
                $dates[] = $m[1];
            }
        }
        closedir($dh);
        rsort($dates);
    }
    return $dates;
}

/**
 * 版权声明
 */
function copyright_notice() {
    echo '<!-- 本系统仅用于学习和研究目的，请遵守相关法律法规 -->' . "\n";
}

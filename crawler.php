<?php
/**
 * 开源情报系统 - 新闻爬取模块
 *
 * 定时执行：建议通过系统 crontab / 计划任务 每15分钟调用一次
 * 命令行：php crawler.php
 * Web：通过浏览器访问 crawler.php（需登录）
 *
 * 本系统仅用于学习和研究目的
 */

require_once __DIR__ . '/common.php';
copyright_notice();

// 允许命令行和Web两种运行模式
$is_web = (php_sapi_name() !== 'cli');

if ($is_web) {
    require_login();
}

// ---------- 主流程 ----------

$start_time = microtime(true);
log_message("========== 开始爬取任务 ==========");

$sources = json_read(__DIR__ . '/sources.json');
$news = json_read(__DIR__ . '/data.json');

// 只爬取启用且可用的数据源
$active_sources = array_filter($sources, function ($s) {
    return ($s['status'] ?? '') === 'enable' && !empty($s['is_alive']);
});

log_message("有效数据源: " . count($active_sources) . "/" . count($sources));

$total_fetched = 0;
$total_errors = 0;

foreach ($active_sources as $source) {
    try {
        $result = fetch_source($source);
        if ($result['success']) {
            $news = merge_news($news, $result['items'], $source);
            $total_fetched += $result['count'];
            log_message("✅ [{$source['name']}] 获取成功，新增 {$result['count']} 条");
        } else {
            $total_errors++;
            log_message("❌ [{$source['name']}] 获取失败: {$result['error']}");
        }
    } catch (Exception $e) {
        $total_errors++;
        log_message("❌ [{$source['name']}] 异常: " . $e->getMessage());
    }

    // 间隔1秒，防止被封
    if ($total_fetched > 0) {
        usleep(1000000);
    }
}

// 限制最大存储数
$max_news = json_read(__DIR__ . '/config.json')['max_news'] ?? 2000;
if (count($news) > $max_news) {
    // 按爬取时间排序，保留最新的
    usort($news, function ($a, $b) {
        return strtotime($b['crawl_time']) - strtotime($a['crawl_time']);
    });
    $news = array_slice($news, 0, $max_news);
    log_message("新闻数超限，已裁剪至最新 {$max_news} 条");
}

json_write(__DIR__ . '/data.json', $news);

$elapsed = round(microtime(true) - $start_time, 2);
log_message("========== 爬取完成: 新增 {$total_fetched} 条, 失败 {$total_errors} 个, 耗时 {$elapsed}s ==========");

if ($is_web) {
    header('Location: source.php?msg=爬取完成，新增 ' . $total_fetched . ' 条新闻');
} else {
    echo "[" . date('Y-m-d H:i:s') . "] 爬取完成: 新增 {$total_fetched} 条, 耗时 {$elapsed}s\n";
}

// ========== 核心函数 ==========

/**
 * 获取单个数据源的新闻
 */
function fetch_source($source) {
    $config = json_read(__DIR__ . '/config.json');
    $timeout = $config['request_timeout'] ?? 10;

    $response = http_get($source['url'], $timeout);

    if (!empty($response['error'])) {
        return [
            'success' => false,
            'error' => $response['error'],
            'items' => [],
            'count' => 0,
        ];
    }

    if ($response['http_code'] >= 400) {
        return [
            'success' => false,
            'error' => "HTTP {$response['http_code']}",
            'items' => [],
            'count' => 0,
        ];
    }

    $body = $response['body'];
    if (empty($body)) {
        return [
            'success' => false,
            'error' => '返回内容为空',
            'items' => [],
            'count' => 0,
        ];
    }

    // 尝试解析为RSS/Atom
    $items = parse_rss($body, $source);

    // 如果不是RSS格式，尝试HTML解析
    if (empty($items)) {
        $items = parse_html($body, $source);
    }

    return [
        'success' => true,
        'items' => $items,
        'count' => count($items),
    ];
}

/**
 * 解析RSS/Atom feed
 */
function parse_rss($xml_content, $source) {
    $items = [];

    // 禁用libxml错误，防止无效XML报错
    libxml_use_internal_errors(true);

    $xml = simplexml_load_string($xml_content);
    if ($xml === false) {
        libxml_clear_errors();
        return $items;
    }

    $namespaces = $xml->getNamespaces(true);

    // 支持RSS 2.0
    if (!empty($xml->channel->item) && $xml->channel->item->count() > 0) {
        foreach ($xml->channel->item as $item) {
            $title = trim((string)$item->title);
            $link = trim((string)$item->link);
            $pub_date = trim((string)$item->pubDate);
            $description = trim(strip_tags((string)$item->description));

            if (empty($title) || empty($link)) continue;

            $items[] = [
                'title' => $title,
                'url' => $link,
                'publish_time' => parse_rss_date($pub_date),
                'description' => $description,
            ];
        }
    }

    // 支持Atom
    if (!empty($xml->entry) && $xml->entry->count() > 0) {
        foreach ($xml->entry as $entry) {
            $title = trim((string)$entry->title);

            $link = '';
            if (!empty($entry->link) && $entry->link->count() > 0) {
                foreach ($entry->link as $l) {
                    $attrs = $l->attributes();
                    if ((string)$attrs['rel'] === 'alternate' || empty($link)) {
                        $link = (string)($attrs['href'] ?? $l);
                    }
                }
            }

            $pub_date = trim((string)$entry->published);
            if (empty($pub_date)) {
                $pub_date = trim((string)$entry->updated);
            }

            $description = trim(strip_tags((string)$entry->summary ?? (string)$entry->content ?? ''));

            if (empty($title) || empty($link)) continue;

            $items[] = [
                'title' => $title,
                'url' => $link,
                'publish_time' => parse_rss_date($pub_date),
                'description' => $description,
            ];
        }
    }

    // 支持RSS 1.0 (RDF)
    if (!empty($xml->item) && $xml->item->count() > 0) {
        foreach ($xml->item as $item) {
            $title = trim((string)$item->title);
            $link = trim((string)$item->link);
            $pub_date = '';
            $description = trim(strip_tags((string)$item->description ?? ''));

            $dc_ns = $item->children('http://purl.org/dc/elements/1.1/');
            if ($dc_ns && $dc_ns->count() > 0) {
                $dc = $item->children('http://purl.org/dc/elements/1.1/');
                if (empty($pub_date)) $pub_date = trim((string)$dc->date);
            }

            if (empty($title) || empty($link)) continue;

            $items[] = [
                'title' => $title,
                'url' => $link,
                'publish_time' => parse_rss_date($pub_date),
                'description' => $description,
            ];
        }
    }

    libxml_clear_errors();
    return $items;
}

/**
 * 解析RSS日期格式为时间戳
 */
function parse_rss_date($date_str) {
    if (empty($date_str)) return date('Y-m-d H:i:s');

    // 常见RSS日期格式
    $formats = [
        'D, d M Y H:i:s O',
        'D, d M Y H:i:s T',
        'Y-m-d\TH:i:sP',
        'Y-m-d\TH:i:s\Z',
        'Y-m-d H:i:s',
        'Y-m-d\TH:i:s.uP',
        'Y-m-d',
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $date_str);
        if ($dt !== false) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    // 尝试strtotime兜底
    $ts = strtotime($date_str);
    if ($ts !== false && $ts > 0) {
        return date('Y-m-d H:i:s', $ts);
    }

    return date('Y-m-d H:i:s');
}

/**
 * 解析HTML页面中的新闻链接
 */
function parse_html($html, $source) {
    $items = [];
    $config = json_read(__DIR__ . '/config.json');

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // 尝试多种选择器提取新闻链接
    $link_xpaths = [
        '//a[contains(@href, "news") or contains(@href, "article") or contains(@href, "story")]',
        '//h1//a | //h2//a | //h3//a | //h4//a',
        '//a[contains(@class, "title") or contains(@class, "headline")]',
        '//li//a',
    ];

    $seen_urls = [];
    foreach ($link_xpaths as $xp) {
        $nodes = $xpath->query($xp);
        if ($nodes === false) continue;

        foreach ($nodes as $node) {
            $title = trim($node->textContent);
            $url = $node->getAttribute('href');

            if (empty($title) || empty($url)) continue;

            // 过滤无效链接
            if (strlen($title) < 5 || strlen($title) > 200) continue;
            if (strpos($url, 'javascript:') === 0 || strpos($url, '#') === 0) continue;
            if (preg_match('/\.(jpg|jpeg|png|gif|css|js|ico|svg)$/i', $url)) continue;

            // 补全相对URL
            $url = resolve_url($source['url'], $url);

            // 去重
            $url_key = md5($url);
            if (isset($seen_urls[$url_key])) continue;
            $seen_urls[$url_key] = true;

            $items[] = [
                'title' => $title,
                'url' => $url,
                'publish_time' => date('Y-m-d H:i:s'),
                'description' => '',
            ];

            // 限制每页最大提取数
            if (count($items) >= 30) break 2;
        }
    }

    return $items;
}

/**
 * 补全相对URL为绝对URL
 */
function resolve_url($base_url, $relative_url) {
    if (filter_var($relative_url, FILTER_VALIDATE_URL)) {
        return $relative_url;
    }

    $parts = parse_url($base_url);
    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? '';

    if (strpos($relative_url, '//') === 0) {
        return $scheme . ':' . $relative_url;
    }

    if (strpos($relative_url, '/') === 0) {
        return "{$scheme}://{$host}{$relative_url}";
    }

    $path = dirname($parts['path'] ?? '');
    return "{$scheme}://{$host}{$path}/{$relative_url}";
}

/**
 * 合并新闻到已有数据，根据URL去重
 */
function merge_news($existing, $new_items, $source) {
    // 建立已有URL索引
    $existing_urls = [];
    foreach ($existing as $item) {
        $existing_urls[md5($item['url'])] = true;
    }

    $added = 0;
    foreach ($new_items as $item) {
        $url_key = md5($item['url']);
        if (isset($existing_urls[$url_key])) continue;

        $existing[] = [
            'id' => gen_id('news'),
            'industry' => $source['industry'],
            'source_name' => $source['name'],
            'source_url' => $source['url'],
            'title' => $item['title'],
            'url' => $item['url'],
            'description' => $item['description'] ?? '',
            'publish_time' => $item['publish_time'],
            'crawl_time' => date('Y-m-d H:i:s'),
            'status' => 'active',
        ];

        $existing_urls[$url_key] = true;
        $added++;
    }

    return $existing;
}

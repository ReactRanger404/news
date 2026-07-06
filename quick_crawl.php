<?php
/**
 * 快速爬取 - 仅爬取国内可达的中文源
 * 每爬完一个源立即保存，防止中途崩溃丢数据
 */
require_once __DIR__ . '/common.php';
// 引入爬虫核心函数（但不执行主逻辑）
$GLOBALS['_CRAWLER_SKIP_MAIN'] = true;
require_once __DIR__ . '/crawler.php';

$sources = json_read(__DIR__ . '/sources.json');
$config = json_read(__DIR__ . '/config.json');
$timeout = $config['request_timeout'] ?? 5;

// 国外源列表（跳过）
$foreign_pattern = '/nature|wired|cnbc|nasa|techcrunch|theverge|arstechnica|hackernews|bbc|reuters|bloomberg|wsj|nytimes|guardian|smashingmagazine|dzone|sitepoint|css-tricks|alistapart|codeship|codeproject|devto|infoq|freecodecamp/i';

$targets = [];
foreach ($sources as $s) {
    if (($s['status'] ?? '') !== 'enable' || empty($s['is_alive'])) continue;
    // 跳过国外源
    if (preg_match($foreign_pattern, $s['name']) || preg_match($foreign_pattern, $s['url'])) continue;
    // 只保留中文行业
    $cn_inds = ['政治','经济','军事','金融','股票','科技','医疗','娱乐','体育','社会'];
    if (!in_array($s['industry'] ?? '', $cn_inds)) continue;
    // 额外校验: 确认确实返回RSS内容
    $test = http_get($s['url'], 4);
    if (!empty($test['body'])) {
        $xml = @simplexml_load_string($test['body']);
        if ($xml !== false) {
            $cnt = 0;
            if (!empty($xml->channel->item)) $cnt = $xml->channel->item->count();
            elseif (!empty($xml->entry)) $cnt = $xml->entry->count();
            elseif (!empty($xml->item)) $cnt = $xml->item->count();
            if ($cnt > 0) $targets[] = $s;
        }
    }
}

echo "国内可爬源: " . count($targets) . "/" . count($sources) . PHP_EOL . PHP_EOL;

$news = json_read(__DIR__ . '/data.json');
echo "当前数据: " . count($news) . " 条" . PHP_EOL . PHP_EOL;

$total_new = 0; $total_skip = 0;
$start_all = microtime(true);

foreach ($targets as $src) {
    $start = microtime(true);

    // 先获取内容
    $resp = http_get($src['url'], $timeout);
    if (!empty($resp['error']) || $resp['http_code'] >= 400 || empty($resp['body'])) {
        echo "❌ [{$src['name']}] {$resp['error']}" . PHP_EOL;
        $total_skip++;
        continue;
    }

    // 解析RSS
    $items = parse_rss($resp['body'], $src);

    // 合并
    $before = count($news);
    if (!empty($items)) {
        $news = merge_news($news, $items, $src);
    }
    $added = count($news) - $before;
    $total_new += $added;

    $elapsed = round(microtime(true) - $start, 1);
    echo ($added > 0 ? "✅" : "➖") . " [{$src['name']}] +{$added}条 ({$elapsed}s)" . PHP_EOL;

    // 每10个源保存一次
    static $save_counter = 0;
    $save_counter++;
    if ($save_counter % 10 === 0) {
        json_write(__DIR__ . '/data.json', $news);
        echo "  └─ 已保存 ({$save_counter}/" . count($targets) . ")" . PHP_EOL;
    }

    usleep(300000);
}

// 最终归档+保存
$max = $config['max_news'] ?? 3500;
archive_old_news($news, $max);
json_write(__DIR__ . '/data.json', $news);

$elapsed_all = round(microtime(true) - $start_all, 1);
echo PHP_EOL . "=== 完成! ===" . PHP_EOL;
echo "新增: {$total_new} 条 | 跳过: {$total_skip} 个" . PHP_EOL;
echo "总计: " . count($news) . " 条 | 耗时: {$elapsed_all}s" . PHP_EOL;

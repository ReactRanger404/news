<?php
/**
 * 精准爬取 - 只爬确认有效的RSS源
 */
require_once __DIR__ . '/common.php';
$GLOBALS['_CRAWLER_SKIP_MAIN'] = true;
require_once __DIR__ . '/crawler.php';

$sources = json_read(__DIR__ . '/sources.json');
$config = json_read(__DIR__ . '/config.json');
$news = json_read(__DIR__ . '/data.json');

$alive = array_filter($sources, fn($s) => ($s['status']??'')==='enable' && !empty($s['is_alive']));
echo "有效源: " . count($alive) . " 个\n当前数据: " . count($news) . " 条\n\n";

$total_new = 0; $success = 0;
foreach ($alive as $src) {
    $resp = http_get($src['url'], 6);
    if (empty($resp['body'])) continue;

    $items = parse_rss($resp['body'], $src);
    if (empty($items)) continue;

    $before = count($news);
    $news = merge_news($news, $items, $src);
    $added = count($news) - $before;
    $total_new += $added;
    $success++;
    echo ($added > 0 ? '✅' : '➖') . " [{$src['industry']}] {$src['name']} +{$added}条\n";
    usleep(200000);
}

$max = $config['max_news'] ?? 3500;
archive_old_news($news, $max);
json_write(__DIR__ . '/data.json', $news);

echo "\n=== 完成 ===\n";
echo "成功: {$success}个源, 新增: {$total_new}条\n";
echo "总计: " . count($news) . " 条\n";

// 行业分布
$inds = []; $recent = 0; $cut = time() - 86400;
foreach ($news as $n) {
    $i = $n['industry'] ?? '?';
    $inds[$i] = ($inds[$i] ?? 0) + 1;
    if (strtotime($n['publish_time'] ?? $n['crawl_time'] ?? 'now') >= $cut) $recent++;
}
ksort($inds);
echo "24h: {$recent} 条\n\n行业分布:\n";
foreach ($inds as $i => $c) echo "  {$i}: {$c}条\n";

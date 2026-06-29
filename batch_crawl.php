<?php
/**
 * 数据源批量检测 + 爬取执行器
 *
 * 步骤1：检测全部172个数据源的连通性
 * 步骤2：自动爬取可访问的数据源
 * 步骤3：返回检测结果
 *
 * 使用方法：php batch_crawl.php
 */

require_once __DIR__ . '/common.php';

echo "============================================\n";
echo "  开源情报系统 - 批量检测 + 自动爬取\n";
echo "============================================\n\n";

// ========== 步骤1：检测全部数据源 ==========

$sources = json_read(__DIR__ . '/sources.json');
$total = count($sources);
echo "数据源总数: {$total}\n";
echo "开始连通性检测...\n\n";

$alive_count = 0;
$dead_count = 0;
$alive_sources = [];

foreach ($sources as &$src) {
    echo "检测: {$src['name']} ... ";
    $result = test_url($src['url'], 8);

    $src['last_check'] = date('Y-m-d H:i:s');
    $src['is_alive'] = $result['reachable'];

    if ($result['reachable']) {
        $alive_count++;
        $alive_sources[] = $src['name'];
        echo "✅ HTTP {$result['http_code']} ({$result['total_time']}s)\n";
    } else {
        $dead_count++;
        $err = $result['error'] ?: "HTTP {$result['http_code']}";
        echo "❌ {$err}\n";
    }

    // 200ms间隔，防止被封
    usleep(200000);
}

// 保存检测结果
json_write(__DIR__ . '/sources.json', $sources);

echo "\n========== 检测结果 ==========\n";
echo "可访问: {$alive_count} / {$total}\n";
echo "不可达: {$dead_count} / {$total}\n\n";

if ($alive_count > 0) {
    echo "可访问的数据源列表:\n";
    foreach ($alive_sources as $name) {
        echo "  ✅ {$name}\n";
    }
}

// ========== 步骤2：自动爬取可访问的数据源 ==========

if ($alive_count > 0) {
    echo "\n========== 开始爬取可访问的数据源 ==========\n";
    echo "共 {$alive_count} 个数据源将参与爬取\n\n";

    // 直接引入爬虫执行
    require __DIR__ . '/crawler.php';

    echo "\n========== 爬取完成 ==========\n";
    echo "建议：下次运行 php batch_crawl.php 即可重新检测+爬取\n";
    echo "或者使用 php background_crawler.php 持续后台自动爬取\n\n";
} else {
    echo "\n⚠️ 没有可访问的数据源，跳过爬取步骤\n";
    echo "请检查网络连接或手动在管理后台添加可用数据源\n\n";
}

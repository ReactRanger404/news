<?php
/**
 * 回填文章图片：为现有的缺图数据从原文页面抓取og:image
 *
 * 使用方法：php backfill_images.php
 * 建议在后台运行，预计耗时 30-60分钟
 */

require_once __DIR__ . '/common.php';

/**
 * 从文章页面提取 Open Graph 图片
 */
function fetch_og_image($url) {
    if (empty($url)) return '';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_BUFFERSIZE => 128000,
        CURLOPT_RANGE => '0-128000',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
        ],
    ]);
    $body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 400 || empty($body)) return '';

    // 1. og:image
    if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)["\']/is', $body, $m)) {
        return $m[1];
    }
    // 2. twitter:image
    if (preg_match('/<meta\s+name=["\']twitter:image["\']\s+content=["\']([^"\']+)["\']/is', $body, $m)) {
        return $m[1];
    }
    // 3. 第一张图片
    if (preg_match('/<img[^>]+src=["\']([^"\']+\.(?:jpg|jpeg|png|gif|webp))["\']/i', $body, $m)) {
        return $m[1];
    }
    return '';
}

$data = json_read(__DIR__ . '/data.json');
$total = count($data);
$backfilled = 0;
$skipped = 0;
$failed = 0;

echo "总条数: $total\n";
echo "开始回填图片...\n\n";

foreach ($data as &$item) {
    if (!empty($item['image'])) {
        $skipped++;
        continue;
    }
    if (empty($item['url'])) {
        $skipped++;
        continue;
    }

    echo "抓取: {$item['source_name']} - " . mb_substr($item['title'], 0, 30) . "... ";
    $img = fetch_og_image($item['url']);

    if (!empty($img)) {
        $item['image'] = $img;
        $backfilled++;
        echo "✅\n";
    } else {
        $failed++;
        echo "❌ 无图\n";
    }

    usleep(300000);
}

json_write(__DIR__ . '/data.json', $data);

echo "\n========== 完成 ==========\n";
echo "总处理: $total\n";
echo "已跳过(已有图): $skipped\n";
echo "回填成功: $backfilled\n";
echo "失败(无图): $failed\n";
echo "当前有图率: " . round(($total - $failed) / $total * 100, 1) . "%\n";

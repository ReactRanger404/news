<?php
/**
 * 通用新闻爬虫 - 直接从网站首页提取新闻链接（不依赖RSS）
 * 用于那些关闭了RSS但仍然在更新网页的中文新闻网站
 */
require_once __DIR__ . '/common.php';
$GLOBALS['_CRAWLER_SKIP_MAIN'] = true;
require_once __DIR__ . '/crawler.php';

/**
 * 从HTML页面中提取新闻链接（通用方法）
 */
function extract_news_from_html($html, $source_url) {
    $items = [];
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    // 找所有链接
    $links = $xpath->query('//a[@href]');
    $seen = [];

    foreach ($links as $node) {
        $title = trim($node->textContent);
        $url = $node->getAttribute('href');
        if (empty($title) || empty($url)) continue;

        // 过滤：标题太短或太长
        $len = mb_strlen($title);
        if ($len < 8 || $len > 120) continue;

        // 过滤导航文字
        if (preg_match('/^(首页|新闻|历史|体育|娱乐|财经|科技|军事|社会|教育|健康|汽车|房产|旅游|时尚|女人|男人|游戏|动漫|数码|手机|阅读|视频|图片|论坛|博客|微博|微信|注册|登录|关于|联系我们|广告|服务|帮助|更多|下一页|上一页)$/u', $title)) continue;
        if (preg_match('/^\d+$/', $title)) continue;

        // 补全URL
        $url = resolve_url($source_url, $url);

        // 过滤无效链接
        if (strpos($url, 'javascript:') === 0) continue;
        if (preg_match('/\.(css|js|ico|svg|json|xml)$/i', $url)) continue;

        // 去重
        $key = md5($url);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        // 尝试找附近的图片
        $image = '';
        $parent = $node->parentNode;
        if ($parent) {
            $imgs = $xpath->query('.//img', $parent);
            foreach ($imgs as $img) {
                $src = $img->getAttribute('src') ?: $img->getAttribute('data-src') ?: '';
                if (!empty($src) && strpos($src, 'data:image') === false) {
                    $image = resolve_url($source_url, $src);
                    // 跳过小图
                    if (preg_match('/logo|icon|avatar|banner/i', $image)) $image = '';
                    else break;
                }
            }
        }

        // 提取描述（附近的文本）
        $description = '';
        $current = $node->parentNode;
        for ($i = 0; $i < 3 && $current; $i++) {
            $description = '';
            foreach ($current->childNodes as $child) {
                if ($child->nodeType === XML_TEXT_NODE) {
                    $text = trim($child->textContent);
                    if (mb_strlen($text) > 10 && $text !== $title) {
                        $description = $text;
                        break 2;
                    }
                }
            }
            $current = $current->parentNode;
        }
        if (empty($description)) $description = $title;

        $items[] = [
            'title' => $title,
            'url' => $url,
            'publish_time' => date('Y-m-d H:i:s'),
            'description' => mb_substr($description, 0, 200),
            'image' => $image,
        ];

        if (count($items) >= 20) break;
    }

    return $items;
}

// ====== 要抓取的新闻站点 ======
$sites = [
    // 门户新闻
    ['name' => '新浪-新闻', 'url' => 'https://news.sina.com.cn/', 'industry' => '社会'],
    ['name' => '新浪-国内', 'url' => 'https://news.sina.com.cn/china/', 'industry' => '政治'],
    ['name' => '新浪-国际', 'url' => 'https://news.sina.com.cn/world/', 'industry' => '政治'],
    ['name' => '新浪-军事', 'url' => 'https://mil.sina.com.cn/', 'industry' => '军事'],
    ['name' => '新浪-财经', 'url' => 'https://finance.sina.com.cn/', 'industry' => '经济'],
    ['name' => '新浪-科技', 'url' => 'https://tech.sina.com.cn/', 'industry' => '科技'],
    ['name' => '新浪-体育', 'url' => 'https://sports.sina.com.cn/', 'industry' => '体育'],
    ['name' => '新浪-娱乐', 'url' => 'https://ent.sina.com.cn/', 'industry' => '娱乐'],

    ['name' => '网易-新闻', 'url' => 'https://news.163.com/', 'industry' => '社会'],
    ['name' => '网易-国内', 'url' => 'https://news.163.com/domestic/', 'industry' => '政治'],
    ['name' => '网易-国际', 'url' => 'https://news.163.com/world/', 'industry' => '政治'],
    ['name' => '网易-军事', 'url' => 'https://war.163.com/', 'industry' => '军事'],
    ['name' => '网易-财经', 'url' => 'https://money.163.com/', 'industry' => '经济'],
    ['name' => '网易-科技', 'url' => 'https://tech.163.com/', 'industry' => '科技'],
    ['name' => '网易-体育', 'url' => 'https://sports.163.com/', 'industry' => '体育'],
    ['name' => '网易-娱乐', 'url' => 'https://ent.163.com/', 'industry' => '娱乐'],

    ['name' => '搜狐-新闻', 'url' => 'https://news.sohu.com/', 'industry' => '社会'],
    ['name' => '搜狐-军事', 'url' => 'https://mil.sohu.com/', 'industry' => '军事'],
    ['name' => '搜狐-财经', 'url' => 'https://business.sohu.com/', 'industry' => '经济'],
    ['name' => '搜狐-科技', 'url' => 'https://it.sohu.com/', 'industry' => '科技'],
    ['name' => '搜狐-体育', 'url' => 'https://sports.sohu.com/', 'industry' => '体育'],
    ['name' => '搜狐-娱乐', 'url' => 'https://yule.sohu.com/', 'industry' => '娱乐'],
    ['name' => '搜狐-健康', 'url' => 'https://health.sohu.com/', 'industry' => '医疗'],

    ['name' => '腾讯-新闻', 'url' => 'https://news.qq.com/', 'industry' => '社会'],
    ['name' => '腾讯-财经', 'url' => 'https://finance.qq.com/', 'industry' => '经济'],
    ['name' => '腾讯-科技', 'url' => 'https://tech.qq.com/', 'industry' => '科技'],
    ['name' => '腾讯-体育', 'url' => 'https://sports.qq.com/', 'industry' => '体育'],
    ['name' => '腾讯-娱乐', 'url' => 'https://ent.qq.com/', 'industry' => '娱乐'],

    // 新闻聚合
    ['name' => '澎湃新闻', 'url' => 'https://www.thepaper.cn/', 'industry' => '社会'],
    ['name' => '澎湃-时事', 'url' => 'https://www.thepaper.cn/news_center', 'industry' => '政治'],
    ['name' => '观察者网', 'url' => 'https://www.guancha.cn/', 'industry' => '政治'],
    ['name' => '环球网', 'url' => 'https://www.huanqiu.com/', 'industry' => '政治'],
    ['name' => '中国新闻网', 'url' => 'https://www.chinanews.com.cn/', 'industry' => '社会'],
    ['name' => '中国青年网', 'url' => 'https://www.youth.cn/', 'industry' => '社会'],
    ['name' => '光明网', 'url' => 'https://www.gmw.cn/', 'industry' => '社会'],

    // 财经
    ['name' => '东方财富', 'url' => 'https://finance.eastmoney.com/', 'industry' => '金融'],
    ['name' => '同花顺', 'url' => 'https://www.10jqka.com.cn/', 'industry' => '股票'],
    ['name' => '雪球', 'url' => 'https://xueqiu.com/', 'industry' => '股票'],
    ['name' => '财联社', 'url' => 'https://www.cls.cn/', 'industry' => '股票'],
    ['name' => '华尔街见闻', 'url' => 'https://wallstreetcn.com/', 'industry' => '经济'],
    ['name' => '和讯网', 'url' => 'http://www.hexun.com/', 'industry' => '经济'],
    ['name' => '第一财经', 'url' => 'https://www.yicai.com/', 'industry' => '经济'],

    // 军事
    ['name' => '凤凰-军事', 'url' => 'https://mil.ifeng.com/', 'industry' => '军事'],
    ['name' => '中华网-军事', 'url' => 'https://military.china.com/', 'industry' => '军事'],
    ['name' => '铁血网', 'url' => 'https://www.tiexue.net/', 'industry' => '军事'],

    // 体育
    ['name' => '虎扑', 'url' => 'https://www.hupu.com/', 'industry' => '体育'],
    ['name' => '直播吧', 'url' => 'https://www.zhibo8.cc/', 'industry' => '体育'],
    ['name' => '懂球帝', 'url' => 'https://www.dongqiudi.com/', 'industry' => '体育'],
    ['name' => '央视体育', 'url' => 'https://sports.cctv.com/', 'industry' => '体育'],

    // 科技
    ['name' => '驱动之家', 'url' => 'https://news.mydrivers.com/', 'industry' => '科技'],
    ['name' => 'cnBeta', 'url' => 'https://www.cnbeta.com/', 'industry' => '科技'],
    ['name' => 'ZOL', 'url' => 'https://www.zol.com.cn/', 'industry' => '科技'],
    ['name' => 'IT168', 'url' => 'https://www.it168.com/', 'industry' => '科技'],
    ['name' => '中关村在线', 'url' => 'https://www.zol.com.cn/', 'industry' => '科技'],

    // 娱乐
    ['name' => '1905电影网', 'url' => 'https://www.1905.com/', 'industry' => '娱乐'],
    ['name' => '时光网', 'url' => 'https://www.mtime.com/', 'industry' => '娱乐'],
    ['name' => '哔哩哔哩-热门', 'url' => 'https://www.bilibili.com/', 'industry' => '娱乐'],
];

$news = json_read(__DIR__ . '/data.json');
$config = json_read(__DIR__ . '/config.json');

echo '开始抓取 ' . count($sites) . ' 个网站...' . PHP_EOL;
$total_new = 0; $success = 0;

foreach ($sites as $site) {
    echo "⏳ [{$site['industry']}] {$site['name']}... ";
    $r = http_get($site['url'], 6);

    if (empty($r['body']) || $r['http_code'] >= 400) {
        echo "❌ ({$r['http_code']})\n";
        continue;
    }

    $extracted = extract_news_from_html($r['body'], $site['url']);
    if (empty($extracted)) {
        echo "❌ (0条)\n";
        continue;
    }

    $before = count($news);
    foreach ($extracted as $item) {
        // 构造源数据
        $src = ['name' => $site['name'], 'url' => $site['url'], 'industry' => $site['industry']];
        $news = merge_news($news, [$item], $src);
    }
    $added = count($news) - $before;
    $total_new += $added;
    $success++;
    echo "✅ +{$added}条\n";

    usleep(500000);

    // 每5个站保存一次
    static $cnt = 0;
    if (++$cnt % 5 === 0) json_write(__DIR__ . '/data.json', $news);
}

$max = $config['max_news'] ?? 3500;
archive_old_news($news, $max);
json_write(__DIR__ . '/data.json', $news);

echo PHP_EOL . "=== 完成 ===\n";
echo "成功抓取: {$success}/" . count($sites) . " 个站\n";
echo "新增: {$total_new} 条\n";

$inds = []; $recent = 0; $cut = time() - 86400;
foreach ($news as $n) {
    $i = $n['industry'] ?? '?';
    $inds[$i] = ($inds[$i] ?? 0) + 1;
    if (strtotime($n['publish_time'] ?? $n['crawl_time'] ?? 'now') >= $cut) $recent++;
}
ksort($inds);
echo "24h: {$recent} 条\n\n行业分布:\n";
foreach ($inds as $i => $c) echo "  {$i}: {$c}条\n";

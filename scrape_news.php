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

// ====== 要抓取的新闻站点 (目标150+) ======
$sites = array_merge(
    // === 新浪系 ===
    [
        ['name' => '新浪-新闻', 'url' => 'https://news.sina.com.cn/', 'industry' => '社会'],
        ['name' => '新浪-国内', 'url' => 'https://news.sina.com.cn/china/', 'industry' => '政治'],
        ['name' => '新浪-国际', 'url' => 'https://news.sina.com.cn/world/', 'industry' => '政治'],
        ['name' => '新浪-军事', 'url' => 'https://mil.sina.com.cn/', 'industry' => '军事'],
        ['name' => '新浪-财经', 'url' => 'https://finance.sina.com.cn/', 'industry' => '经济'],
        ['name' => '新浪-科技', 'url' => 'https://tech.sina.com.cn/', 'industry' => '科技'],
        ['name' => '新浪-体育', 'url' => 'https://sports.sina.com.cn/', 'industry' => '体育'],
        ['name' => '新浪-娱乐', 'url' => 'https://ent.sina.com.cn/', 'industry' => '娱乐'],
        ['name' => '新浪-教育', 'url' => 'https://edu.sina.com.cn/', 'industry' => '社会'],
        ['name' => '新浪-汽车', 'url' => 'https://auto.sina.com.cn/', 'industry' => '科技'],
        ['name' => '新浪-房产', 'url' => 'https://house.sina.com.cn/', 'industry' => '经济'],
        ['name' => '新浪-收藏', 'url' => 'https://collection.sina.com.cn/', 'industry' => '娱乐'],
        ['name' => '新浪-博客', 'url' => 'https://blog.sina.com.cn/', 'industry' => '社会'],
        ['name' => '新浪-图片', 'url' => 'https://photo.sina.com.cn/', 'industry' => '社会'],
    ],
    // === 网易系 ===
    [
        ['name' => '网易-新闻', 'url' => 'https://news.163.com/', 'industry' => '社会'],
        ['name' => '网易-国内', 'url' => 'https://news.163.com/domestic/', 'industry' => '政治'],
        ['name' => '网易-国际', 'url' => 'https://news.163.com/world/', 'industry' => '政治'],
        ['name' => '网易-军事', 'url' => 'https://war.163.com/', 'industry' => '军事'],
        ['name' => '网易-财经', 'url' => 'https://money.163.com/', 'industry' => '经济'],
        ['name' => '网易-科技', 'url' => 'https://tech.163.com/', 'industry' => '科技'],
        ['name' => '网易-体育', 'url' => 'https://sports.163.com/', 'industry' => '体育'],
        ['name' => '网易-娱乐', 'url' => 'https://ent.163.com/', 'industry' => '娱乐'],
        ['name' => '网易-教育', 'url' => 'https://edu.163.com/', 'industry' => '社会'],
        ['name' => '网易-健康', 'url' => 'https://health.163.com/', 'industry' => '医疗'],
        ['name' => '网易-汽车', 'url' => 'https://auto.163.com/', 'industry' => '科技'],
        ['name' => '网易-房产', 'url' => 'https://house.163.com/', 'industry' => '经济'],
        ['name' => '网易-数码', 'url' => 'https://digi.163.com/', 'industry' => '科技'],
        ['name' => '网易-游戏', 'url' => 'https://game.163.com/', 'industry' => '娱乐'],
    ],
    // === 搜狐系 ===
    [
        ['name' => '搜狐-新闻', 'url' => 'https://news.sohu.com/', 'industry' => '社会'],
        ['name' => '搜狐-军事', 'url' => 'https://mil.sohu.com/', 'industry' => '军事'],
        ['name' => '搜狐-财经', 'url' => 'https://business.sohu.com/', 'industry' => '经济'],
        ['name' => '搜狐-科技', 'url' => 'https://it.sohu.com/', 'industry' => '科技'],
        ['name' => '搜狐-体育', 'url' => 'https://sports.sohu.com/', 'industry' => '体育'],
        ['name' => '搜狐-娱乐', 'url' => 'https://yule.sohu.com/', 'industry' => '娱乐'],
        ['name' => '搜狐-健康', 'url' => 'https://health.sohu.com/', 'industry' => '医疗'],
        ['name' => '搜狐-教育', 'url' => 'https://learning.sohu.com/', 'industry' => '社会'],
        ['name' => '搜狐-汽车', 'url' => 'https://auto.sohu.com/', 'industry' => '科技'],
        ['name' => '搜狐-旅游', 'url' => 'https://travel.sohu.com/', 'industry' => '娱乐'],
        ['name' => '搜狐-时尚', 'url' => 'https://fashion.sohu.com/', 'industry' => '娱乐'],
        ['name' => '搜狐-母婴', 'url' => 'https://parenting.sohu.com/', 'industry' => '社会'],
        ['name' => '搜狐-房产', 'url' => 'https://house.sohu.com/', 'industry' => '经济'],
        ['name' => '搜狐-文化', 'url' => 'https://cul.sohu.com/', 'industry' => '社会'],
        ['name' => '搜狐-历史', 'url' => 'https://history.sohu.com/', 'industry' => '社会'],
    ],
    // === 腾讯系 ===
    [
        ['name' => '腾讯-新闻', 'url' => 'https://news.qq.com/', 'industry' => '社会'],
        ['name' => '腾讯-财经', 'url' => 'https://finance.qq.com/', 'industry' => '经济'],
        ['name' => '腾讯-科技', 'url' => 'https://tech.qq.com/', 'industry' => '科技'],
        ['name' => '腾讯-体育', 'url' => 'https://sports.qq.com/', 'industry' => '体育'],
        ['name' => '腾讯-娱乐', 'url' => 'https://ent.qq.com/', 'industry' => '娱乐'],
        ['name' => '腾讯-汽车', 'url' => 'https://auto.qq.com/', 'industry' => '科技'],
        ['name' => '腾讯-游戏', 'url' => 'https://games.qq.com/', 'industry' => '娱乐'],
        ['name' => '腾讯-教育', 'url' => 'https://edu.qq.com/', 'industry' => '社会'],
        ['name' => '腾讯-时尚', 'url' => 'https://fashion.qq.com/', 'industry' => '娱乐'],
        ['name' => '腾讯-房产', 'url' => 'https://house.qq.com/', 'industry' => '经济'],
        ['name' => '腾讯-数码', 'url' => 'https://digi.tech.qq.com/', 'industry' => '科技'],
    ],
    // === 凤凰系 ===
    [
        ['name' => '凤凰-新闻', 'url' => 'https://news.ifeng.com/', 'industry' => '社会'],
        ['name' => '凤凰-军事', 'url' => 'https://mil.ifeng.com/', 'industry' => '军事'],
        ['name' => '凤凰-财经', 'url' => 'https://finance.ifeng.com/', 'industry' => '经济'],
        ['name' => '凤凰-科技', 'url' => 'https://tech.ifeng.com/', 'industry' => '科技'],
        ['name' => '凤凰-娱乐', 'url' => 'https://ent.ifeng.com/', 'industry' => '娱乐'],
        ['name' => '凤凰-体育', 'url' => 'https://sports.ifeng.com/', 'industry' => '体育'],
        ['name' => '凤凰-汽车', 'url' => 'https://auto.ifeng.com/', 'industry' => '科技'],
    ],
    // === 全国新闻媒体 ===
    [
        ['name' => '新华网', 'url' => 'https://www.xinhuanet.com/', 'industry' => '政治'],
        ['name' => '人民网', 'url' => 'http://www.people.com.cn/', 'industry' => '政治'],
        ['name' => '央视网', 'url' => 'https://news.cctv.com/', 'industry' => '政治'],
        ['name' => '央广网', 'url' => 'https://www.cnr.cn/', 'industry' => '社会'],
        ['name' => '国际在线', 'url' => 'https://www.cri.cn/', 'industry' => '政治'],
        ['name' => '中国日报', 'url' => 'https://www.chinadaily.com.cn/', 'industry' => '社会'],
        ['name' => '中国新闻网', 'url' => 'https://www.chinanews.com.cn/', 'industry' => '社会'],
        ['name' => '中国青年网', 'url' => 'https://www.youth.cn/', 'industry' => '社会'],
        ['name' => '光明网', 'url' => 'https://www.gmw.cn/', 'industry' => '社会'],
        ['name' => '参考消息', 'url' => 'https://www.cankaoxiaoxi.com/', 'industry' => '政治'],
        ['name' => '环球网', 'url' => 'https://www.huanqiu.com/', 'industry' => '政治'],
        ['name' => '观察者网', 'url' => 'https://www.guancha.cn/', 'industry' => '政治'],
        ['name' => '澎湃新闻', 'url' => 'https://www.thepaper.cn/', 'industry' => '社会'],
        ['name' => '澎湃-时事', 'url' => 'https://www.thepaper.cn/news_center', 'industry' => '政治'],
        ['name' => '中国网', 'url' => 'https://www.china.com.cn/', 'industry' => '社会'],
        ['name' => '中青在线', 'url' => 'https://www.cyol.com/', 'industry' => '社会'],
        ['name' => '中国军网', 'url' => 'https://www.81.cn/', 'industry' => '军事'],
    ],
    // === 财经 ===
    [
        ['name' => '东方财富', 'url' => 'https://finance.eastmoney.com/', 'industry' => '金融'],
        ['name' => '同花顺', 'url' => 'https://www.10jqka.com.cn/', 'industry' => '股票'],
        ['name' => '雪球', 'url' => 'https://xueqiu.com/', 'industry' => '股票'],
        ['name' => '财联社', 'url' => 'https://www.cls.cn/', 'industry' => '股票'],
        ['name' => '华尔街见闻', 'url' => 'https://wallstreetcn.com/', 'industry' => '经济'],
        ['name' => '和讯网', 'url' => 'http://www.hexun.com/', 'industry' => '经济'],
        ['name' => '第一财经', 'url' => 'https://www.yicai.com/', 'industry' => '经济'],
        ['name' => '财新网', 'url' => 'https://www.caixin.com/', 'industry' => '经济'],
        ['name' => '每日经济', 'url' => 'https://www.nbd.com.cn/', 'industry' => '经济'],
        ['name' => '21经济', 'url' => 'https://www.21jingji.com/', 'industry' => '经济'],
        ['name' => '经济观察', 'url' => 'https://www.eeo.com.cn/', 'industry' => '经济'],
        ['name' => '中国经济网', 'url' => 'https://www.ce.cn/', 'industry' => '经济'],
        ['name' => '证券时报', 'url' => 'https://www.stcn.com/', 'industry' => '股票'],
        ['name' => '中国证券报', 'url' => 'https://www.cs.com.cn/', 'industry' => '股票'],
        ['name' => '上海证券报', 'url' => 'https://www.cnstock.com/', 'industry' => '股票'],
        ['name' => '证券之星', 'url' => 'https://www.stockstar.com/', 'industry' => '股票'],
        ['name' => '金融界', 'url' => 'https://www.jrj.com.cn/', 'industry' => '金融'],
        ['name' => '投资界', 'url' => 'https://www.pedaily.cn/', 'industry' => '金融'],
        ['name' => '亿欧网', 'url' => 'https://www.iyiou.com/', 'industry' => '经济'],
        ['name' => '钛媒体', 'url' => 'https://www.tmtpost.com/', 'industry' => '经济'],
        ['name' => '虎嗅', 'url' => 'https://www.huxiu.com/', 'industry' => '经济'],
    ],
    // === 军事 ===
    [
        ['name' => '中华网-军事', 'url' => 'https://military.china.com/', 'industry' => '军事'],
        ['name' => '铁血网', 'url' => 'https://www.tiexue.net/', 'industry' => '军事'],
        ['name' => '环球军事', 'url' => 'https://mil.huanqiu.com/', 'industry' => '军事'],
        ['name' => '新浪军事综合', 'url' => 'https://mil.news.sina.com.cn/', 'industry' => '军事'],
        ['name' => '搜狐军事', 'url' => 'https://mil.sohu.com/', 'industry' => '军事'],
        ['name' => '西陆军事', 'url' => 'https://www.xilu.com/', 'industry' => '军事'],
    ],
    // === 体育 ===
    [
        ['name' => '虎扑', 'url' => 'https://www.hupu.com/', 'industry' => '体育'],
        ['name' => '直播吧', 'url' => 'https://www.zhibo8.cc/', 'industry' => '体育'],
        ['name' => '懂球帝', 'url' => 'https://www.dongqiudi.com/', 'industry' => '体育'],
        ['name' => '央视体育', 'url' => 'https://sports.cctv.com/', 'industry' => '体育'],
        ['name' => '体坛网', 'url' => 'https://www.titan24.com/', 'industry' => '体育'],
        ['name' => '新浪高尔夫', 'url' => 'https://golf.sina.com.cn/', 'industry' => '体育'],
        ['name' => '新浪网球', 'url' => 'https://tennis.sina.com.cn/', 'industry' => '体育'],
        ['name' => 'NBA中文网', 'url' => 'https://china.nba.com/', 'industry' => '体育'],
        ['name' => '新浪体育综合', 'url' => 'https://sports.sina.com.cn/', 'industry' => '体育'],
        ['name' => '腾讯NBA', 'url' => 'https://sports.qq.com/nba/', 'industry' => '体育'],
        ['name' => '搜狐体育', 'url' => 'https://sports.sohu.com/', 'industry' => '体育'],
        ['name' => '网易体育', 'url' => 'https://sports.163.com/', 'industry' => '体育'],
        ['name' => '腾讯体育', 'url' => 'https://sports.qq.com/', 'industry' => '体育'],
    ],
    // === 科技 ===
    [
        ['name' => '驱动之家', 'url' => 'https://news.mydrivers.com/', 'industry' => '科技'],
        ['name' => 'cnBeta', 'url' => 'https://www.cnbeta.com/', 'industry' => '科技'],
        ['name' => '太平洋电脑', 'url' => 'https://www.pconline.com.cn/', 'industry' => '科技'],
        ['name' => '天极网', 'url' => 'https://www.yesky.com/', 'industry' => '科技'],
        ['name' => '果壳网', 'url' => 'https://www.guokr.com/', 'industry' => '科技'],
        ['name' => '科学网', 'url' => 'https://www.sciencenet.cn/', 'industry' => '科技'],
        ['name' => '创业邦', 'url' => 'https://www.cyzone.cn/', 'industry' => '科技'],
        ['name' => '36氪', 'url' => 'https://www.36kr.com/', 'industry' => '科技'],
        ['name' => 'IT之家', 'url' => 'https://www.ithome.com/', 'industry' => '科技'],
        ['name' => '爱范儿', 'url' => 'https://www.ifanr.com/', 'industry' => '科技'],
        ['name' => '少数派', 'url' => 'https://sspai.com/', 'industry' => '科技'],
        ['name' => '开源中国', 'url' => 'https://www.oschina.net/', 'industry' => '科技'],
        ['name' => '雷锋网', 'url' => 'https://www.leiphone.com/', 'industry' => '科技'],
        ['name' => 'Solidot', 'url' => 'https://www.solidot.org/', 'industry' => '科技'],
        ['name' => '机器之心', 'url' => 'https://www.jiqizhixin.com/', 'industry' => '科技'],
        ['name' => 'InfoQ中文', 'url' => 'https://www.infoq.cn/', 'industry' => '科技'],
        ['name' => '威锋网', 'url' => 'https://www.feng.com/', 'industry' => '科技'],
        ['name' => '超能网', 'url' => 'https://www.expreview.com/', 'industry' => '科技'],
        ['name' => '爱活网', 'url' => 'https://www.evolife.cn/', 'industry' => '科技'],
        ['name' => '数字尾巴', 'url' => 'https://www.dgtle.com/', 'industry' => '科技'],
        ['name' => '机锋网', 'url' => 'https://www.gfan.com/', 'industry' => '科技'],
        ['name' => '安卓网', 'url' => 'https://www.hiapk.com/', 'industry' => '科技'],
        ['name' => '电脑爱好者', 'url' => 'https://www.cfan.com.cn/', 'industry' => '科技'],
        ['name' => '站长之家', 'url' => 'https://www.chinaz.com/', 'industry' => '科技'],
        ['name' => 'LUPA', 'url' => 'https://www.lupaworld.com/', 'industry' => '科技'],
        ['name' => '开源中国-博客', 'url' => 'https://my.oschina.net/', 'industry' => '科技'],
    ],
    // === 医疗/健康 ===
    [
        ['name' => '丁香园', 'url' => 'https://www.dxy.cn/', 'industry' => '医疗'],
        ['name' => '梅斯医学', 'url' => 'https://www.medsci.cn/', 'industry' => '医疗'],
        ['name' => '生物谷', 'url' => 'https://www.bioon.com/', 'industry' => '医疗'],
        ['name' => '医脉通', 'url' => 'https://www.medlive.cn/', 'industry' => '医疗'],
        ['name' => '健康界', 'url' => 'https://www.cn-healthcare.com/', 'industry' => '医疗'],
        ['name' => '医学界', 'url' => 'https://www.yxj.org.cn/', 'industry' => '医疗'],
        ['name' => '搜狐健康', 'url' => 'https://health.sohu.com/', 'industry' => '医疗'],
        ['name' => '新浪健康', 'url' => 'https://health.sina.com.cn/', 'industry' => '医疗'],
    ],
    // === 娱乐 ===
    [
        ['name' => '1905电影网', 'url' => 'https://www.1905.com/', 'industry' => '娱乐'],
        ['name' => '时光网', 'url' => 'https://www.mtime.com/', 'industry' => '娱乐'],
        ['name' => '哔哩哔哩', 'url' => 'https://www.bilibili.com/', 'industry' => '娱乐'],
        ['name' => '猫眼电影', 'url' => 'https://www.maoyan.com/', 'industry' => '娱乐'],
        ['name' => '豆瓣', 'url' => 'https://www.douban.com/', 'industry' => '娱乐'],
        ['name' => '爱奇艺', 'url' => 'https://www.iqiyi.com/', 'industry' => '娱乐'],
        ['name' => '腾讯视频', 'url' => 'https://v.qq.com/', 'industry' => '娱乐'],
        ['name' => '芒果TV', 'url' => 'https://www.mgtv.com/', 'industry' => '娱乐'],
        ['name' => 'AcFun', 'url' => 'https://www.acfun.cn/', 'industry' => '娱乐'],
        ['name' => '搜狐娱乐', 'url' => 'https://yule.sohu.com/', 'industry' => '娱乐'],
        ['name' => '网易娱乐', 'url' => 'https://ent.163.com/', 'industry' => '娱乐'],
        ['name' => '腾讯娱乐', 'url' => 'https://ent.qq.com/', 'industry' => '娱乐'],
        ['name' => '新浪娱乐', 'url' => 'https://ent.sina.com.cn/', 'industry' => '娱乐'],
    ],
    // === 地方新闻 ===
    [
        ['name' => '南方网', 'url' => 'https://www.southcn.com/', 'industry' => '社会'],
        ['name' => '金羊网', 'url' => 'https://www.ycwb.com/', 'industry' => '社会'],
        ['name' => '深圳新闻网', 'url' => 'https://www.sznews.com/', 'industry' => '社会'],
        ['name' => '北方网', 'url' => 'https://www.enorth.com.cn/', 'industry' => '社会'],
        ['name' => '东方网', 'url' => 'https://www.eastday.com/', 'industry' => '社会'],
        ['name' => '浙江在线', 'url' => 'https://www.zjol.com.cn/', 'industry' => '社会'],
        ['name' => '华龙网', 'url' => 'https://www.cqnews.net/', 'industry' => '社会'],
        ['name' => '红网', 'url' => 'https://www.rednet.cn/', 'industry' => '社会'],
        ['name' => '大河网', 'url' => 'https://www.dahe.cn/', 'industry' => '社会'],
        ['name' => '齐鲁网', 'url' => 'https://www.iqilu.com/', 'industry' => '社会'],
        ['name' => '四川在线', 'url' => 'https://www.scol.com.cn/', 'industry' => '社会'],
        ['name' => '华商网', 'url' => 'https://www.hsw.cn/', 'industry' => '社会'],
        ['name' => '湖南在线', 'url' => 'https://www.hnol.net/', 'industry' => '社会'],
        ['name' => '大江网', 'url' => 'https://www.jxnews.com.cn/', 'industry' => '社会'],
        ['name' => '中安在线', 'url' => 'https://www.anhuinews.com/', 'industry' => '社会'],
        ['name' => '东北网', 'url' => 'https://www.dbw.cn/', 'industry' => '社会'],
        ['name' => '云南网', 'url' => 'https://www.yunnan.cn/', 'industry' => '社会'],
        ['name' => '西部网', 'url' => 'https://www.cnwest.com/', 'industry' => '社会'],
        ['name' => '新民网', 'url' => 'https://www.xinmin.cn/', 'industry' => '社会'],
        ['name' => '新民晚报', 'url' => 'https://newsxmwb.xinmin.cn/', 'industry' => '社会'],
        ['name' => '北京日报', 'url' => 'https://www.bjd.com.cn/', 'industry' => '社会'],
        ['name' => '北京晚报', 'url' => 'https://www.takefoto.cn/', 'industry' => '社会'],
        ['name' => '解放日报', 'url' => 'https://www.jfdaily.com/', 'industry' => '社会'],
        ['name' => '文汇报', 'url' => 'https://www.whb.cn/', 'industry' => '社会'],
        ['name' => '新民晚报', 'url' => 'https://www.xinmin.cn/', 'industry' => '社会'],
        ['name' => '广州日报', 'url' => 'https://www.gzdaily.com/', 'industry' => '社会'],
        ['name' => '南方日报', 'url' => 'https://www.nanfangdaily.com.cn/', 'industry' => '社会'],
    ],
);

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

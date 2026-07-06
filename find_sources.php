<?php
/**
 * 深度搜索 - 更多类型的中文RSS源
 */
require_once __DIR__ . '/common.php';

$feeds = [];

// ====== 博客/技术社区 ======
$feeds['果壳网'] = 'https://www.guokr.com/rss/';
$feeds['果壳-科技'] = 'https://www.guokr.com/rss/technology/';
$feeds['知乎-编辑推荐'] = 'https://www.zhihu.com/rss';
$feeds['掘金-热门'] = 'https://rsshub.app/juejin/trending/javascript';
$feeds['SegmentFault'] = 'https://segmentfault.com/feeds';
$feeds['CSDN-推荐'] = 'https://blog.csdn.net/rss/list/1001';
$feeds['简书-热门'] = 'https://www.jianshu.com/rss/';
$feeds['V2EX-最新'] = 'https://www.v2ex.com/index.xml';
$feeds['开发者头条'] = 'https://toutiao.io/rss';
$feeds['InfoQ英文'] = 'https://feed.infoq.com/';
$feeds['CSS-Tricks'] = 'https://css-tricks.com/feed/';

// ====== 国际中文媒体 ======
$feeds['BBC中文'] = 'https://www.bbc.com/zhongwen/simp/index.xml';
$feeds['BBC中文-国际'] = 'https://www.bbc.com/zhongwen/simp/world/index.xml';
$feeds['BBC中文-中国'] = 'https://www.bbc.com/zhongwen/simp/chinese_news/index.xml';
$feeds['德国之声中文'] = 'https://rss.dw.com/rdf/rss-zh-hans';
$feeds['法国国际广播中文'] = 'https://www.rfi.fr/cn/rss.xml';
$feeds['纽约时报中文'] = 'https://cn.nytimes.com/rss/';
$feeds['FT中文网'] = 'https://www.ftchinese.com/rss/news';
$feeds['日经中文网'] = 'https://cn.nikkei.com/rss.html';
$feeds['亚洲周刊'] = 'https://www.yzzk.com/rss';

// ====== 独立媒体/深度报道 ======
$feeds['端传媒'] = 'https://theinitium.com/rss/';
$feeds['棱镜深度'] = 'https://www.lengjing.com/rss/';
$feeds['界面新闻'] = 'https://www.jiemian.com/rss.xml';
$feeds['虎嗅'] = 'https://www.huxiu.com/rss/0.xml';
$feeds['钛媒体-最新'] = 'https://www.tmtpost.com/rss';
$feeds['品玩'] = 'https://www.pingwest.com/feed';

// ====== 行业垂直 ======
$feeds['亿欧'] = 'https://www.iyiou.com/rss';
$feeds['投资界'] = 'https://www.pedaily.cn/feed';
$feeds['动点科技'] = 'https://cn.technode.com/feed/';
$feeds['极客公园'] = 'https://www.geekpark.net/rss';
$feeds['PingWest品玩'] = 'https://www.pingwest.com/feed';

// ====== 更多新闻 ======
$feeds['澎湃-时事'] = 'https://m.thepaper.cn/rss/news.xml';
$feeds['观察者-最新'] = 'https://www.guancha.cn/rss/1.xml';
$feeds['光明-时评'] = 'https://guancha.gmw.cn/rss/1.xml';
$feeds['中国青年报'] = 'http://zqb.cyol.com/rss/1.xml';
$feeds['南方周末'] = 'http://www.infzm.com/rss/';
$feeds['新京报'] = 'https://www.bjnews.com.cn/rss/1.xml';
$feeds['北京日报'] = 'https://www.bjd.com.cn/rss/1.xml';
$feeds['解放日报'] = 'https://www.jfdaily.com/rss/1.xml';
$feeds['文汇报'] = 'https://www.whb.cn/rss/1.xml';
$feeds['新民晚报'] = 'https://www.xinmin.cn/rss/1.xml';

// ====== 地方新闻 ======
$feeds['南方网'] = 'http://www.southcn.com/rss/1.xml';
$feeds['金羊网'] = 'http://www.ycwb.com/rss/1.xml';
$feeds['大洋网'] = 'http://www.dayoo.com/rss/1.xml';
$feeds['深圳新闻网'] = 'http://www.sznews.com/rss/1.xml';

// ====== 财经 ======
$feeds['财富中文网'] = 'https://www.fortunechina.com/rss/';
$feeds['福布斯中国'] = 'http://www.forbeschina.com/rss/';
$feeds['商业周刊'] = 'https://www.businessweekly.com.tw/rss';
$feeds['经济参考报'] = 'http://www.jjckb.cn/rss/1.xml';
$feeds['中国金融信息网'] = 'http://www.cnfin.com/rss/1.xml';

// ====== 行业 ======
$feeds['汽车之家-资讯'] = 'https://www.autohome.com.cn/rss/';
$feeds['爱卡汽车'] = 'http://www.xcar.com.cn/rss/';
$feeds['手机中国'] = 'http://www.cnmo.com/rss/';
$feeds['天极网'] = 'http://www.yesky.com/rss/';
$feeds['太平洋电脑'] = 'http://www.pconline.com.cn/rss/';
$feeds['中关村在线'] = 'http://www.zol.com.cn/rss/';
$feeds['蜂鸟网'] = 'http://www.fengniao.com/rss/';
$feeds['IT168'] = 'http://www.it168.com/rss/';

// ====== 体育 ======
$feeds['直播吧'] = 'https://www.zhibo8.cc/rss/';
$feeds['雪球体育'] = 'http://www.xueqiu.com/rss/';

// ====== 高校/学术 ======
$feeds['科学网'] = 'http://www.sciencenet.cn/rss/';
$feeds['生物谷'] = 'http://www.bioon.com/rss/';
$feeds['中国科技网'] = 'http://www.stdaily.com/rss/1.xml';

// ====== 金融/投资 ======
$feeds['财联社'] = 'https://www.cls.cn/rss/1.xml';
$feeds['华尔街见闻-视频'] = 'https://wallstreetcn.com/rss/video';
$feeds['金十数据'] = 'https://www.jin10.com/rss/';

// ====== 替代域名尝试 ======
$feeds['新华网(www)'] = 'http://www.xinhuanet.com/rss/news.xml';
$feeds['新华网(news)'] = 'http://news.xinhuanet.com/rss/news.xml';
$feeds['人民网(专题)'] = 'http://politics.people.com.cn/rss/politics.xml';
$feeds['央视网(cctv)'] = 'http://news.cctv.com/rss/1.xml';

// ====== 已经验证过的源（确保加入） ======
$feeds['36氪'] = 'https://www.36kr.com/feed';
$feeds['IT之家'] = 'https://www.ithome.com/rss/';
$feeds['开源中国'] = 'https://www.oschina.net/news/rss';
$feeds['爱范儿'] = 'https://www.ifanr.com/feed';
$feeds['钛媒体'] = 'https://www.tmtpost.com/rss';
$feeds['Solidot'] = 'https://www.solidot.org/index.rss';
$feeds['InfoQ中文'] = 'https://www.infoq.cn/feed';
$feeds['少数派'] = 'https://sspai.com/feed';
$feeds['量子位'] = 'https://www.qbitai.com/feed';
$feeds['雷锋网'] = 'https://www.leiphone.com/feed';
$feeds['机器之心'] = 'https://www.jiqizhixin.com/rss';
$feeds['小众软件'] = 'https://feed.appinn.com/';
$feeds['博客园'] = 'https://feed.cnblogs.com/news/rss';
$feeds['美团技术'] = 'https://tech.meituan.com/feed/';
$feeds['腾讯CDC'] = 'https://cdc.tencent.com/feed/';
$feeds['腾讯研究院'] = 'https://www.tisi.org/rss/';
$feeds['张鑫旭博客'] = 'https://www.zhangxinxu.com/wordpress/feed/';
$feeds['未央网'] = 'https://www.weiyangx.com/feed';
$feeds['人民网-时政'] = 'http://www.people.com.cn/rss/politics.xml';
$feeds['人民网-社会'] = 'http://www.people.com.cn/rss/society.xml';
$feeds['人民网-财经'] = 'http://www.people.com.cn/rss/finance.xml';
$feeds['中国新闻网'] = 'http://www.chinanews.com/rss/scroll-news.xml';

$working = [];
$checked = 0;
echo "开始测试 " . count($feeds) . " 个RSS源...\n\n";

foreach ($feeds as $name => $url) {
    $checked++;
    $r = http_get($url, 5);
    if (empty($r['body'])) continue;
    $xml = @simplexml_load_string($r['body']);
    if ($xml === false) continue;
    $count = 0;
    if (!empty($xml->channel->item)) $count = $xml->channel->item->count();
    elseif (!empty($xml->entry)) $count = $xml->entry->count();
    if ($count > 0) $working[] = ['name' => $name, 'url' => $url, 'count' => $count, 'code' => $r['http_code']];
}

echo "测试了 {$checked} 个, 有效: " . count($working) . " 个\n\n";

echo "=== 有效源列表 ===\n";
foreach ($working as $w) {
    echo "✅ {$w['name']} ({$w['count']}条) HTTP {$w['code']}\n";
    echo "   {$w['url']}\n";
}

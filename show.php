<?php
/**
 * 开源情报系统 - 情报展示模块（新浪风格）
 *
 * 功能：列表展示、行业筛选、关键词检索、分页导航、响应式适配
 * 本系统仅用于学习和研究目的
 */

require_once __DIR__ . '/common.php';
copyright_notice();

// 自动触发爬取检查（到时间自动在后台抓取）
check_auto_crawl();

$config = json_read(__DIR__ . '/config.json');
$page_size = $config['page_size'] ?? 80;

// ========== 参数处理 ==========

$page = max(1, intval(get('page', '1')));
$industry = get('industry', '');
$keyword = get('keyword', '');
$ajax = get('ajax', '');
$load_more = get('load_more', '');

// ========== AJAX加载更多 ==========

if ($ajax === '1' || $load_more === '1') {
    handle_ajax($page, $industry, $keyword, $page_size);
    exit;
}

// ========== 主页面 ==========

$news = json_read(__DIR__ . '/data.json');

// 筛选
$filtered = filter_news($news, $industry, $keyword);

// 分页
$total = count($filtered);
$total_pages = max(1, ceil($total / $page_size));
$offset = ($page - 1) * $page_size;
$page_news = array_slice($filtered, $offset, $page_size);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>开源情报系统 - OSINT</title>
    <style>
        /* ========== 基础重置 ========== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "SimSun", sans-serif;
            background: #FFF8E1; /* 淡黄色背景 */
            color: #333;
            min-height: 100vh;
        }
        a { text-decoration: none; }
        ul { list-style: none; }

        /* ========== 新浪风格头部 ========== */
        .sina-header {
            background: linear-gradient(135deg, #CC0000, #E71A1A);
            color: white;
            position: relative;
            overflow: hidden;
        }
        .sina-header::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #FFD700, #FF6B00, #FFD700);
        }
        .sina-header-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 18px 20px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            position: relative;
            z-index: 1;
        }
        .sina-logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sina-logo .logo-icon {
            width: 38px;
            height: 38px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            color: #CC0000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .sina-logo .logo-text {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 2px;
            text-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .sina-logo .logo-sub {
            font-size: 12px;
            opacity: 0.85;
            letter-spacing: 1px;
            font-weight: 300;
        }
        .sina-header-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .sina-header-right a {
            color: rgba(255,255,255,0.85);
            font-size: 13px;
            transition: color 0.2s;
        }
        .sina-header-right a:hover { color: #FFD700; }

        /* ========== 导航栏 ========== */
        .sina-nav {
            background: white;
            border-bottom: 1px solid #e8e0d0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .sina-nav-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 16px;
            display: flex;
            align-items: center;
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .sina-nav-inner::-webkit-scrollbar { display: none; }
        .sina-nav-item {
            flex-shrink: 0;
            padding: 12px 16px;
            font-size: 14px;
            color: #555;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            cursor: pointer;
            white-space: nowrap;
        }
        .sina-nav-item:hover {
            color: #CC0000;
            background: #FFF5F0;
        }
        .sina-nav-item.active {
            color: #CC0000;
            border-bottom-color: #CC0000;
            font-weight: 600;
        }
        .sina-nav-search {
            margin-left: auto;
            flex-shrink: 0;
            padding: 6px 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .sina-nav-search input {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 6px 10px;
            font-size: 13px;
            width: 160px;
            transition: border-color 0.2s;
            outline: none;
        }
        .sina-nav-search input:focus {
            border-color: #CC0000;
        }
        .sina-nav-search button {
            background: #CC0000;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 14px;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .sina-nav-search button:hover {
            background: #990000;
        }

        /* ========== 统计信息条 ========== */
        .sina-stats {
            max-width: 1200px;
            margin: 16px auto 8px;
            padding: 0 16px;
            font-size: 13px;
            color: #888;
        }

        /* ========== 主体内容 ========== */
        .sina-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 16px 40px;
        }

        /* ========== 情报列表 ========== */
        .news-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .news-item {
            background: white;
            border-radius: 8px;
            padding: 16px;
            display: flex;
            gap: 16px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            transition: box-shadow 0.2s, transform 0.15s;
            cursor: pointer;
            border: 1px solid #f0e8d8;
        }
        .news-item:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.10);
            transform: translateY(-1px);
            border-color: #e0d0b8;
        }

        /* 缩略图 */
        .news-thumb {
            width: 160px;
            min-width: 160px;
            height: 110px;
            border-radius: 6px;
            overflow: hidden;
            flex-shrink: 0;
            background: #f5f0e8;
            position: relative;
        }
        .news-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.3s;
        }
        .news-item:hover .news-thumb img {
            transform: scale(1.05);
        }
        .news-thumb .img-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #ccc;
            background: linear-gradient(135deg, #f5f0e8, #e8e0d0);
        }

        /* 内容区域 */
        .news-content {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .news-title {
            font-size: 17px;
            font-weight: 600;
            line-height: 1.5;
            margin-bottom: 6px;
        }
        .news-title a {
            color: #1a1a1a;
            transition: color 0.2s;
        }
        .news-title a:hover { color: #CC0000; }

        .news-desc {
            font-size: 13px;
            color: #888;
            line-height: 1.6;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .news-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 16px;
            align-items: center;
            font-size: 12px;
            color: #999;
        }
        .news-meta .tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
        }
        .tag-industry {
            background: #FFF0E8;
            color: #CC0000;
            border: 1px solid #fdd;
        }
        .tag-source {
            background: #f5f5f5;
            color: #888;
            border: 1px solid #e8e8e8;
        }
        .news-time {
            color: #aaa;
        }

        /* ========== 分页导航（新浪风格） ========== */
        .sina-pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 4px;
            margin: 28px 0 10px;
            flex-wrap: wrap;
        }
        .sina-pagination .page-info {
            font-size: 13px;
            color: #888;
            margin-right: 8px;
        }
        .sina-pagination a, .sina-pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border-radius: 4px;
            font-size: 13px;
            color: #555;
            background: white;
            border: 1px solid #e0d8c8;
            transition: all 0.2s;
        }
        .sina-pagination a:hover {
            background: #CC0000;
            color: white;
            border-color: #CC0000;
        }
        .sina-pagination .active {
            background: #CC0000;
            color: white;
            border-color: #CC0000;
            font-weight: 600;
        }
        .sina-pagination .ellipsis {
            border: none;
            color: #aaa;
            background: transparent;
            min-width: 20px;
        }
        .sina-pagination .prev-next {
            font-weight: 500;
        }

        /* ========== 空状态 ========== */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #bbb;
        }
        .empty-state .icon { font-size: 64px; margin-bottom: 16px; }
        .empty-state p { font-size: 16px; color: #999; }

        /* ========== 弹窗 ========== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white;
            border-radius: 12px;
            width: 100%;
            max-width: 660px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalIn 0.2s ease-out;
        }
        @keyframes modalIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px 24px 12px;
            border-bottom: 1px solid #f0e8e0;
        }
        .modal-header h3 {
            font-size: 18px;
            line-height: 1.5;
            flex: 1;
            margin-right: 16px;
            color: #1a1a1a;
        }
        .modal-close {
            width: 30px;
            height: 30px;
            border: none;
            background: #f0f0f0;
            border-radius: 50%;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: background 0.2s;
        }
        .modal-close:hover { background: #e0e0e0; }
        .modal-body { padding: 16px 24px 24px; }
        .modal-image {
            width: 100%;
            max-height: 300px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 16px;
            background: #f5f0e8;
        }
        .modal-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 16px;
            align-items: center;
            margin-bottom: 14px;
        }
        .modal-meta .tag {
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 12px;
        }
        .modal-desc {
            font-size: 15px;
            line-height: 1.8;
            color: #444;
            white-space: pre-wrap;
        }
        .modal-footer-action {
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid #f0e8e0;
        }
        .btn-open-url {
            display: inline-block;
            padding: 9px 22px;
            background: #CC0000;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            transition: background 0.2s;
        }
        .btn-open-url:hover { background: #990000; }

        /* ========== 返回顶部 ========== */
        .back-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 40px;
            height: 40px;
            background: #CC0000;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 18px;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(204,0,0,0.3);
            transition: all 0.2s;
            z-index: 100;
        }
        .back-top:hover { transform: translateY(-3px); }

        /* ========== 页脚 ========== */
        .sina-footer {
            text-align: center;
            padding: 24px 20px;
            color: #bbb;
            font-size: 12px;
            border-top: 1px solid #f0e8d8;
            margin-top: 20px;
            background: white;
        }
        .sina-footer a {
            color: #888;
            margin: 0 6px;
        }
        .sina-footer a:hover { color: #CC0000; }

        /* ========== 响应式 ========== */
        @media (max-width: 900px) {
            .news-thumb {
                width: 130px;
                min-width: 130px;
                height: 95px;
            }
            .news-title { font-size: 16px; }
        }
        @media (max-width: 768px) {
            .sina-header-inner { padding: 14px 16px 10px; }
            .sina-logo .logo-text { font-size: 20px; }
            .sina-logo .logo-sub { display: none; }
            .sina-nav-search input { width: 120px; }
            .news-item { padding: 12px; gap: 12px; }
            .news-thumb {
                width: 100px;
                min-width: 100px;
                height: 75px;
            }
            .news-title { font-size: 15px; }
            .news-desc { -webkit-line-clamp: 1; }
            .news-meta .tag-source { display: none; }
            .back-top { bottom: 16px; right: 16px; width: 36px; height: 36px; font-size: 16px; }
        }
        @media (max-width: 480px) {
            .news-thumb {
                width: 80px;
                min-width: 80px;
                height: 60px;
            }
            .news-title { font-size: 14px; }
            .news-title a { -webkit-line-clamp: 2; display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden; }
            .sina-nav-search input { width: 90px; font-size: 12px; }
            .sina-nav-item { padding: 10px 10px; font-size: 13px; }
        }
    </style>
</head>
<body>

<!-- ========== 新浪风格头部 ========== -->
<div class="sina-header">
    <div class="sina-header-inner">
        <div class="sina-logo">
            <div class="logo-icon">📰</div>
            <div>
                <div class="logo-text">开源情报系统</div>
                <div class="logo-sub">Open Source Intelligence · 实时聚合多源情报</div>
            </div>
        </div>
        <div class="sina-header-right">
            <a href="source.php" target="_blank">⚙️ 管理后台</a>
        </div>
    </div>
</div>

<!-- ========== 导航栏（含搜索） ========== -->
<div class="sina-nav">
    <div class="sina-nav-inner">
        <a href="show.php" class="sina-nav-item <?php echo empty($industry) ? 'active' : ''; ?>">
            🏠 首页
        </a>
        <?php foreach ($config['allow_industries'] as $ind): ?>
        <a href="?industry=<?php echo urlencode($ind); ?><?php echo $keyword ? '&keyword=' . urlencode($keyword) : ''; ?>"
           class="sina-nav-item <?php echo $industry === $ind ? 'active' : ''; ?>">
            <?php echo $ind; ?>
        </a>
        <?php endforeach; ?>
        <div class="sina-nav-search">
            <form method="get" action="show.php" style="display:flex;gap:6px;align-items:center;">
                <?php if ($industry): ?>
                <input type="hidden" name="industry" value="<?php echo xss_clean($industry); ?>">
                <?php endif; ?>
                <input type="text" name="keyword" placeholder="搜索情报..."
                       value="<?php echo xss_clean($keyword); ?>">
                <button type="submit">🔍 搜索</button>
            </form>
        </div>
    </div>
</div>

<!-- ========== 统计信息 ========== -->
<div class="sina-stats">
    共 <strong><?php echo $total; ?></strong> 条情报
    <?php if ($industry): ?> · 行业：<strong style="color:#CC0000;"><?php echo xss_clean($industry); ?></strong><?php endif; ?>
    <?php if ($keyword): ?> · 搜索：<strong style="color:#CC0000;"><?php echo xss_clean($keyword); ?></strong><?php endif; ?>
</div>

<!-- ========== 情报列表 ========== -->
<div class="sina-container">

    <!-- 顶部翻页 -->
    <?php if ($total_pages > 1): ?>
    <div class="sina-pagination">
        <?php
        $base_url = 'show.php?page=';
        if ($industry) $base_url .= '&industry=' . urlencode($industry);
        if ($keyword) $base_url .= '&keyword=' . urlencode($keyword);
        ?>
        <span class="page-info">第 <?php echo $page; ?>/<?php echo $total_pages; ?> 页</span>
        <?php if ($page > 1): ?>
        <a href="<?php echo $base_url . ($page - 1); ?>" class="prev-next">&laquo; 上一页</a>
        <?php endif; ?>
        <?php
        $start = max(1, $page - 3);
        $end = min($total_pages, $page + 3);
        if ($start > 1): ?>
        <a href="<?php echo $base_url . '1'; ?>">1</a>
        <?php if ($start > 2): ?><span class="ellipsis">…</span><?php endif;
        endif;
        for ($i = $start; $i <= $end; $i++): ?>
        <a href="<?php echo $base_url . $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor;
        if ($end < $total_pages):
            if ($end < $total_pages - 1): ?><span class="ellipsis">…</span><?php endif; ?>
        <a href="<?php echo $base_url . $total_pages; ?>"><?php echo $total_pages; ?></a>
        <?php endif; ?>
        <?php if ($page < $total_pages): ?>
        <a href="<?php echo $base_url . ($page + 1); ?>" class="prev-next">下一页 &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 新闻列表 -->
    <div class="news-list" id="newsList">
        <?php render_news_list($page_news, $offset, $page); ?>
    </div>

    <?php if (empty($page_news) && $page === 1): ?>
    <div class="empty-state">
        <div class="icon">📭</div>
        <p>暂无情报数据</p>
        <p style="font-size:14px;margin-top:8px;color:#ccc">请先在管理后台添加数据源并执行爬取</p>
    </div>
    <?php endif; ?>

    <!-- 底部翻页 -->
    <?php if ($total_pages > 1): ?>
    <div class="sina-pagination">
        <?php if ($page > 1): ?>
        <a href="<?php echo $base_url . ($page - 1); ?>" class="prev-next">&laquo; 上一页</a>
        <?php endif; ?>
        <?php
        $start = max(1, $page - 3);
        $end = min($total_pages, $page + 3);
        if ($start > 1): ?>
        <a href="<?php echo $base_url . '1'; ?>">1</a>
        <?php if ($start > 2): ?><span class="ellipsis">…</span><?php endif;
        endif;
        for ($i = $start; $i <= $end; $i++): ?>
        <a href="<?php echo $base_url . $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor;
        if ($end < $total_pages):
            if ($end < $total_pages - 1): ?><span class="ellipsis">…</span><?php endif; ?>
        <a href="<?php echo $base_url . $total_pages; ?>"><?php echo $total_pages; ?></a>
        <?php endif; ?>
        <?php if ($page < $total_pages): ?>
        <a href="<?php echo $base_url . ($page + 1); ?>" class="prev-next">下一页 &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ========== 情报详情弹窗 ========== -->
<div class="modal-overlay" id="newsModal" onclick="closeNews()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 id="modalTitle"></h3>
            <button class="modal-close" onclick="closeNews()">✕</button>
        </div>
        <div class="modal-body">
            <img id="modalImage" class="modal-image" src="" alt="新闻图片" style="display:none;">
            <div class="modal-meta">
                <span class="tag tag-industry" id="modalIndustry"></span>
                <span class="tag tag-source" id="modalSource"></span>
                <span class="news-time" id="modalTime"></span>
            </div>
            <div class="modal-desc" id="modalDesc"></div>
            <div class="modal-footer-action" id="modalLinkWrap" style="display:none">
                <a id="modalLink" href="" target="_blank" rel="noopener" class="btn-open-url">🌐 查看原文</a>
            </div>
        </div>
    </div>
</div>

<!-- ========== 返回顶部 ========== -->
<button class="back-top" id="backTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑</button>

<!-- ========== 页脚 ========== -->
<div class="sina-footer">
    开源情报系统 · 本系统仅用于学习和研究目的 · 请遵守相关法律法规
</div>

<script>
// 打开新闻详情
function openNews(el) {
    var url = el.getAttribute('data-url') || '';
    if (url) {
        window.open(url, '_blank');
        return;
    }
    var title = el.getAttribute('data-title') || '无标题';
    var desc = el.getAttribute('data-desc') || '暂无详细内容';
    var industry = el.getAttribute('data-industry') || '';
    var source = el.getAttribute('data-source') || '';
    var time = el.getAttribute('data-time') || '';
    var image = el.getAttribute('data-image') || '';

    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalDesc').textContent = desc || '暂无详细内容';
    document.getElementById('modalIndustry').textContent = industry;
    document.getElementById('modalSource').textContent = source;
    document.getElementById('modalTime').textContent = '🕐 ' + time;
    document.getElementById('modalLinkWrap').style.display = 'none';

    var imgEl = document.getElementById('modalImage');
    if (image) {
        imgEl.src = image;
        imgEl.style.display = 'block';
    } else {
        imgEl.style.display = 'none';
    }

    document.getElementById('newsModal').classList.add('active');
}

function closeNews() {
    document.getElementById('newsModal').classList.remove('active');
}

// ESC关闭弹窗
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeNews();
});

// 返回顶部按钮
window.addEventListener('scroll', function() {
    var btn = document.getElementById('backTop');
    btn.style.display = window.scrollY > 400 ? 'flex' : 'none';
});
</script>

</body>
</html>
<?php

// ========== 函数定义 ==========

/**
 * 渲染新闻列表HTML
 */
function render_news_list($news_items, $offset, $page) {
    $index = $offset + 1;
    foreach ($news_items as $item):
        $time = format_time($item['publish_time'] ?? $item['crawl_time']);
        $has_url = !empty($item['url']);
        $img = get_news_image($item);
    ?>
    <div class="news-item" onclick="openNews(this)"
         data-title="<?php echo xss_clean($item['title']); ?>"
         data-desc="<?php echo xss_clean($item['description'] ?? ''); ?>"
         data-industry="<?php echo xss_clean($item['industry'] ?? ''); ?>"
         data-source="<?php echo xss_clean($item['source_name'] ?? ''); ?>"
         data-time="<?php echo xss_clean($item['publish_time'] ?? $item['crawl_time'] ?? ''); ?>"
         data-url="<?php echo xss_clean($item['url'] ?? ''); ?>"
         data-image="<?php echo xss_clean($img); ?>">
        <!-- 缩略图 -->
        <div class="news-thumb">
            <img src="<?php echo xss_clean($img); ?>"
                 alt="<?php echo xss_clean($item['title']); ?>"
                 loading="lazy"
                 onerror="this.parentElement.innerHTML='<div class=\'img-placeholder\'>📰</div>'">
        </div>
        <!-- 内容 -->
        <div class="news-content">
            <div class="news-title">
                <?php if ($has_url): ?>
                <a href="<?php echo xss_clean($item['url']); ?>" target="_blank" rel="noopener" onclick="event.stopPropagation()">
                    <?php echo xss_clean($item['title']); ?>
                </a>
                <?php else: ?>
                <span><?php echo xss_clean($item['title']); ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($item['description'])): ?>
            <div class="news-desc"><?php echo xss_clean(mb_substr($item['description'], 0, 200)); ?></div>
            <?php endif; ?>
            <div class="news-meta">
                <span class="tag tag-industry"><?php echo xss_clean($item['industry'] ?? '未分类'); ?></span>
                <span class="tag tag-source"><?php echo xss_clean($item['source_name'] ?? '未知来源'); ?></span>
                <span class="news-time">🕐 <?php echo $time; ?></span>
            </div>
        </div>
    </div>
    <?php
    endforeach;
}

/**
 * 格式化时间显示
 */
function format_time($time_str) {
    if (empty($time_str)) return '未知时间';
    $timestamp = strtotime($time_str);
    if (!$timestamp) return $time_str;
    $diff = time() - $timestamp;
    if ($diff < 60) return '刚刚';
    if ($diff < 3600) return floor($diff / 60) . ' 分钟前';
    if ($diff < 86400) return floor($diff / 3600) . ' 小时前';
    if ($diff < 259200) return floor($diff / 86400) . ' 天前';
    return date('Y-m-d H:i', $timestamp);
}

/**
 * 筛选新闻
 */
function filter_news($news, $industry, $keyword) {
    $result = $news;
    $result = array_filter($result, function ($item) {
        return ($item['status'] ?? 'active') === 'active';
    });
    if (!empty($industry)) {
        $result = array_filter($result, function ($item) use ($industry) {
            return ($item['industry'] ?? '') === $industry;
        });
    }
    if (!empty($keyword)) {
        $result = array_filter($result, function ($item) use ($keyword) {
            return mb_stripos($item['title'] ?? '', $keyword) !== false ||
                   mb_stripos($item['description'] ?? '', $keyword) !== false;
        });
    }
    return array_values($result);
}

/**
 * AJAX加载更多
 */
function handle_ajax($page, $industry, $keyword, $page_size) {
    $news = json_read(__DIR__ . '/data.json');
    $filtered = filter_news($news, $industry, $keyword);
    $total = count($filtered);
    $offset = ($page - 1) * $page_size;
    $page_news = array_slice($filtered, $offset, $page_size);
    if (empty($page_news)) { echo ''; exit; }
    render_news_list($page_news, $offset, $page);
    exit;
}

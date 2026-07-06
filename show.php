<?php
/**
 * 开源情报系统 - 情报展示模块（新浪风格）
 *
 * 功能：列表展示、行业筛选、关键词检索、时间窗口、24h热点追踪、收藏、分页
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
$date_from = get('date_from', '');
$date_to = get('date_to', '');
$view = get('view', ''); // 'favorites' 收藏视图, '' 正常视图
$fav_ids_str = get('ids', '');

// ========== 加载数据 ==========

$news = json_read(__DIR__ . '/data.json');

// 收藏视图、日期范围或关键词搜索时，同时从归档中加载数据
$include_archive = !empty($date_from) || !empty($date_to) || !empty($keyword) || $view === 'favorites';
if ($include_archive) {
    $archive_news = read_archive_news($date_from, $date_to);
    $news = array_merge($news, $archive_news);
}

// ========== 收藏视图 ==========
$fav_ids = [];
if ($view === 'favorites' && !empty($fav_ids_str)) {
    $fav_ids = array_filter(explode(',', $fav_ids_str));
    $fav_ids = array_unique($fav_ids);
}

// ========== 筛选 ==========
$filtered = filter_news($news, $industry, $keyword, $date_from, $date_to);

// 在收藏视图中，只保留收藏的新闻并标记
if ($view === 'favorites' && !empty($fav_ids)) {
    $filtered = array_values(array_filter($filtered, function ($item) use ($fav_ids) {
        return in_array($item['id'] ?? '', $fav_ids);
    }));
    // 标记为已收藏
    foreach ($filtered as &$item) {
        $item['_fav'] = true;
    }
    unset($item);
}

// ========== 24h热点统计 ==========
$hot_24h = get_hot_24h($news);

// ========== 分页 ==========
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
            background: #FFF8E1;
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

        /* ========== 24h热点追踪条 ========== */
        .hot-track {
            background: linear-gradient(135deg, #FFF5F0, #FFE8E0);
            border-bottom: 1px solid #fdd;
            overflow: hidden;
        }
        .hot-track-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 10px 16px;
        }
        .hot-track-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        .hot-track-header .hot-icon {
            display: inline-block;
            background: #CC0000;
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 3px;
            letter-spacing: 1px;
        }
        .hot-track-header .hot-title {
            font-size: 14px;
            font-weight: 600;
            color: #CC0000;
        }
        .hot-track-header .hot-update {
            font-size: 11px;
            color: #999;
            margin-left: auto;
        }
        .hot-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .hot-stat-item {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            background: white;
            border: 1px solid #f0d8c8;
            color: #666;
            cursor: pointer;
            transition: all 0.2s;
        }
        .hot-stat-item:hover {
            background: #CC0000;
            color: white;
            border-color: #CC0000;
        }
        .hot-stat-item .stat-count {
            font-weight: 700;
            font-size: 13px;
        }
        .hot-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }
        .hot-tag {
            font-size: 11px;
            color: #CC0000;
            background: rgba(204,0,0,0.06);
            padding: 2px 8px;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .hot-tag:hover {
            background: rgba(204,0,0,0.15);
        }

        /* ========== 导航栏（含搜索） ========== */
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
            padding: 12px 14px;
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
        .sina-nav-item.fav-active {
            color: #FF8C00;
            border-bottom-color: #FF8C00;
            font-weight: 600;
        }
        .sina-nav-search {
            margin-left: auto;
            flex-shrink: 0;
            padding: 6px 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .sina-nav-search input, .sina-nav-search select {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 6px 10px;
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
        }
        .sina-nav-search input:focus, .sina-nav-search select:focus {
            border-color: #CC0000;
        }
        .sina-nav-search input[type="text"] { width: 120px; }
        .sina-nav-search input[type="date"] { width: 120px; }
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
        .sina-nav-search button:hover { background: #990000; }
        .sina-nav-search .search-hint {
            font-size: 11px;
            color: #aaa;
        }

        /* ========== 统计信息条 ========== */
        .sina-stats {
            max-width: 1200px;
            margin: 16px auto 8px;
            padding: 0 16px;
            font-size: 13px;
            color: #888;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .fav-badge {
            font-size: 12px;
            color: #FF8C00;
            cursor: pointer;
        }
        .fav-badge:hover { text-decoration: underline; }
        .clear-fav-btn {
            font-size: 11px;
            color: #aaa;
            cursor: pointer;
            border: none;
            background: none;
            padding: 2px 6px;
        }
        .clear-fav-btn:hover { color: #CC0000; }

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
            position: relative;
        }
        .news-item:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.10);
            transform: translateY(-1px);
            border-color: #e0d0b8;
        }

        /* 缩略图：无图时完全不显示 */
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
            font-size: 36px;
            color: #ddd;
            background: linear-gradient(135deg, #f9f5ee, #f0e8d8);
            border-radius: 6px;
            user-select: none;
        }

        /* 无图模式：内容占满 */
        .news-item.no-image .news-content {
            width: 100%;
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
            padding-right: 36px;
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
        .news-archive-badge {
            font-size: 10px;
            color: #999;
            background: #f5f5f5;
            padding: 1px 6px;
            border-radius: 3px;
        }

        /* ========== 收藏按钮 ========== */
        .fav-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 28px;
            height: 28px;
            border: none;
            background: rgba(255,255,255,0.85);
            border-radius: 50%;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            z-index: 2;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            color: #ddd;
        }
        .fav-btn:hover {
            transform: scale(1.15);
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }
        .fav-btn.active {
            color: #FF8C00;
            background: #FFF8E8;
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
            display: flex;
            gap: 10px;
            align-items: center;
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
        .modal-fav-btn {
            display: inline-block;
            padding: 9px 18px;
            background: #FFF8E8;
            color: #FF8C00;
            border: 1px solid #FFD7A0;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .modal-fav-btn:hover {
            background: #FF8C00;
            color: white;
            border-color: #FF8C00;
        }
        .modal-fav-btn.active {
            background: #FF8C00;
            color: white;
            border-color: #FF8C00;
        }

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
            .sina-nav-search input[type="text"] { width: 90px; }
            .sina-nav-search input[type="date"] { width: 100px; }
            .news-item { padding: 12px; gap: 12px; }
            .news-thumb {
                width: 100px;
                min-width: 100px;
                height: 75px;
            }
            .news-title { font-size: 15px; padding-right: 30px; }
            .news-desc { -webkit-line-clamp: 1; }
            .news-meta .tag-source { display: none; }
            .back-top { bottom: 16px; right: 16px; width: 36px; height: 36px; font-size: 16px; }
            .fav-btn { top: 8px; right: 8px; width: 24px; height: 24px; font-size: 12px; }
        }
        @media (max-width: 480px) {
            .news-thumb {
                width: 80px;
                min-width: 80px;
                height: 60px;
            }
            .news-title { font-size: 14px; }
            .news-title a { -webkit-line-clamp: 2; display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden; }
            .sina-nav-search input[type="text"] { width: 70px; font-size: 12px; }
            .sina-nav-search input[type="date"] { width: 85px; font-size: 11px; }
            .sina-nav-item { padding: 10px 8px; font-size: 13px; }
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
            <a href="javascript:void(0)" onclick="toggleFavView()">⭐ 我的收藏</a>
            <a href="source.php" target="_blank">⚙️ 管理后台</a>
        </div>
    </div>
</div>

<!-- ========== 24小时热点追踪 ========== -->
<div class="hot-track">
    <div class="hot-track-inner">
        <div class="hot-track-header">
            <span class="hot-icon">🔥 24h热点</span>
            <span class="hot-title">实时追踪 · <?php echo date('Y-m-d H:i'); ?> 更新</span>
            <span class="hot-update">过去24小时共 <strong><?php echo $hot_24h['total']; ?></strong> 条情报</span>
        </div>
        <div class="hot-stats">
            <?php foreach ($hot_24h['industries'] as $ind => $count): ?>
            <a href="?industry=<?php echo urlencode($ind); ?>" class="hot-stat-item">
                <span class="stat-count"><?php echo $count; ?></span>
                <?php echo $ind; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($hot_24h['hot_keywords'])): ?>
        <div class="hot-tags">
            <?php foreach ($hot_24h['hot_keywords'] as $kw => $cnt): ?>
            <a href="?keyword=<?php echo urlencode($kw); ?>" class="hot-tag"><?php echo $kw; ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========== 导航栏（含搜索） ========== -->
<div class="sina-nav">
    <div class="sina-nav-inner">
        <a href="show.php" class="sina-nav-item <?php echo (empty($industry) && empty($view)) ? 'active' : ''; ?>">
            🏠 首页
        </a>
        <?php foreach ($config['allow_industries'] as $ind): ?>
        <a href="?industry=<?php echo urlencode($ind); ?><?php echo $keyword ? '&keyword=' . urlencode($keyword) : ''; ?><?php echo $date_from ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . urlencode($date_to) : ''; ?>"
           class="sina-nav-item <?php echo $industry === $ind ? 'active' : ''; ?>">
            <?php echo $ind; ?>
        </a>
        <?php endforeach; ?>
        <a href="javascript:void(0)" onclick="toggleFavView()"
           class="sina-nav-item <?php echo $view === 'favorites' ? 'fav-active' : ''; ?>"
           id="favNavItem">⭐ 收藏</a>
        <div class="sina-nav-search">
            <form method="get" action="show.php" style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">
                <input type="text" name="keyword" placeholder="搜索关键词..."
                       value="<?php echo xss_clean($keyword); ?>">
                <input type="date" name="date_from" value="<?php echo xss_clean($date_from); ?>"
                       title="开始日期">
                <input type="date" name="date_to" value="<?php echo xss_clean($date_to); ?>"
                       title="结束日期">
                <button type="submit">🔍 搜索</button>
                <?php if ($keyword || $date_from || $date_to): ?>
                <a href="show.php" style="font-size:12px;color:#aaa;padding:4px;">✕ 清除</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<!-- ========== 统计信息 ========== -->
<div class="sina-stats">
    共 <strong><?php echo $total; ?></strong> 条情报
    <?php if ($industry): ?> · 行业：<strong style="color:#CC0000;"><?php echo xss_clean($industry); ?></strong><?php endif; ?>
    <?php if ($keyword): ?> · 搜索：<strong style="color:#CC0000;"><?php echo xss_clean($keyword); ?></strong><?php endif; ?>
    <?php if ($date_from): ?> · 从：<strong><?php echo xss_clean($date_from); ?></strong><?php endif; ?>
    <?php if ($date_to): ?> · 到：<strong><?php echo xss_clean($date_to); ?></strong><?php endif; ?>
    <?php if ($view === 'favorites'): ?> · <strong style="color:#FF8C00;">⭐ 我的收藏</strong><?php endif; ?>
    <span class="fav-badge" id="favBadge" style="display:none;" onclick="toggleFavView()"></span>
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
        if ($date_from) $base_url .= '&date_from=' . urlencode($date_from);
        if ($date_to) $base_url .= '&date_to=' . urlencode($date_to);
        if ($view) $base_url .= '&view=' . urlencode($view);
        if ($view === 'favorites' && $fav_ids_str) $base_url .= '&ids=' . urlencode($fav_ids_str);
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
        <div class="icon"><?php echo $view === 'favorites' ? '⭐' : '📭'; ?></div>
        <p><?php echo $view === 'favorites' ? '还没有收藏的情报' : '暂无情报数据'; ?></p>
        <p style="font-size:14px;margin-top:8px;color:#ccc">
            <?php echo $view === 'favorites' ? '点击新闻右上角的 ⭐ 按钮收藏感兴趣的情报' : '请先在管理后台添加数据源并执行爬取'; ?>
        </p>
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
            <div id="modalImageWrap" style="display:none;">
                <img id="modalImage" class="modal-image" src="" alt="新闻图片">
            </div>
            <div class="modal-meta">
                <span class="tag tag-industry" id="modalIndustry"></span>
                <span class="tag tag-source" id="modalSource"></span>
                <span class="news-time" id="modalTime"></span>
            </div>
            <div class="modal-desc" id="modalDesc"></div>
            <div class="modal-footer-action" id="modalLinkWrap" style="display:none">
                <a id="modalLink" href="" target="_blank" rel="noopener" class="btn-open-url">🌐 查看原文</a>
                <button id="modalFavBtn" class="modal-fav-btn" onclick="toggleModalFav()">⭐ 收藏</button>
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

<!-- ========== 隐藏的收藏数据桥 ========== -->
<span id="viewMode" data-view="<?php echo xss_clean($view); ?>" style="display:none;"></span>

<script>
// ==================== 收藏系统 (localStorage) ====================

/**
 * 获取所有收藏ID
 */
function getFavorites() {
    try {
        var stored = localStorage.getItem('osint_favorites');
        return stored ? JSON.parse(stored) : [];
    } catch(e) { return []; }
}

/**
 * 保存收藏ID列表
 */
function saveFavorites(ids) {
    localStorage.setItem('osint_favorites', JSON.stringify(ids));
}

/**
 * 切换收藏状态
 */
function toggleFav(id, el) {
    var favs = getFavorites();
    var idx = favs.indexOf(id);
    if (idx === -1) {
        favs.push(id);
    } else {
        favs.splice(idx, 1);
    }
    saveFavorites(favs);
    if (el) {
        el.classList.toggle('active');
        el.textContent = favs.indexOf(id) !== -1 ? '⭐' : '☆';
    }
    updateFavBadge();
    return favs.indexOf(id) !== -1;
}

/**
 * 检查是否已收藏
 */
function isFav(id) {
    return getFavorites().indexOf(id) !== -1;
}

/**
 * 初始化所有收藏按钮状态
 */
function initFavButtons() {
    var favs = getFavorites();
    var buttons = document.querySelectorAll('.fav-btn');
    buttons.forEach(function(btn) {
        var id = btn.getAttribute('data-id');
        if (id && favs.indexOf(id) !== -1) {
            btn.classList.add('active');
            btn.textContent = '⭐';
        } else {
            btn.textContent = '☆';
        }
    });
    updateFavBadge();
}

/**
 * 更新收藏数量角标
 */
function updateFavBadge() {
    var favs = getFavorites();
    var badge = document.getElementById('favBadge');
    if (badge) {
        if (favs.length > 0) {
            badge.style.display = 'inline';
            badge.textContent = '⭐ 已收藏 ' + favs.length + ' 条';
        } else {
            badge.style.display = 'none';
        }
    }
}

/**
 * 切换收藏视图
 */
function toggleFavView() {
    var favs = getFavorites();
    var currentView = document.getElementById('viewMode').getAttribute('data-view');
    if (currentView === 'favorites') {
        // 当前在收藏视图，回到首页
        window.location.href = 'show.php';
        return;
    }
    if (favs.length === 0) {
        alert('你还没有收藏任何情报');
        return;
    }
    // 跳转到收藏视图
    window.location.href = 'show.php?view=favorites&ids=' + favs.join(',');
}

// ==================== 弹窗收藏 ====================

var _currentModalId = null;

function openNews(el) {
    var url = el.getAttribute('data-url') || '';
    if (url && el.getAttribute('data-has-modal') !== '1') {
        window.open(url, '_blank');
        return;
    }
    var title = el.getAttribute('data-title') || '无标题';
    var desc = el.getAttribute('data-desc') || '暂无详细内容';
    var industry = el.getAttribute('data-industry') || '';
    var source = el.getAttribute('data-source') || '';
    var time = el.getAttribute('data-time') || '';
    var image = el.getAttribute('data-image') || '';
    var id = el.getAttribute('data-id') || '';
    var url = el.getAttribute('data-url') || '';

    _currentModalId = id;

    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalDesc').textContent = desc || '暂无详细内容';
    document.getElementById('modalIndustry').textContent = industry;
    document.getElementById('modalSource').textContent = source;
    document.getElementById('modalTime').textContent = '🕐 ' + time;

    var imgEl = document.getElementById('modalImage');
    var imgWrap = document.getElementById('modalImageWrap');
    if (image && image.indexOf('data:image/svg') === -1 && image.indexOf('data:image/svg') === -1) {
        imgEl.src = image;
        imgWrap.style.display = 'block';
    } else {
        imgWrap.style.display = 'none';
    }

    var linkWrap = document.getElementById('modalLinkWrap');
    var linkEl = document.getElementById('modalLink');
    if (url) {
        linkEl.href = url;
        linkWrap.style.display = 'flex';
    } else {
        linkWrap.style.display = 'none';
    }

    // 弹窗收藏按钮状态
    var favBtn = document.getElementById('modalFavBtn');
    if (id) {
        favBtn.style.display = 'inline-block';
        if (isFav(id)) {
            favBtn.classList.add('active');
            favBtn.textContent = '⭐ 已收藏';
        } else {
            favBtn.classList.remove('active');
            favBtn.textContent = '☆ 收藏';
        }
    } else {
        favBtn.style.display = 'none';
    }

    document.getElementById('newsModal').classList.add('active');
}

function closeNews() {
    document.getElementById('newsModal').classList.remove('active');
}

function toggleModalFav() {
    if (!_currentModalId) return;
    var nowFav = toggleFav(_currentModalId, null);
    var favBtn = document.getElementById('modalFavBtn');
    if (nowFav) {
        favBtn.classList.add('active');
        favBtn.textContent = '⭐ 已收藏';
    } else {
        favBtn.classList.remove('active');
        favBtn.textContent = '☆ 收藏';
    }
    // 同步列表中的按钮
    var listBtn = document.querySelector('.fav-btn[data-id="' + _currentModalId + '"]');
    if (listBtn) {
        listBtn.classList.toggle('active', nowFav);
        listBtn.textContent = nowFav ? '⭐' : '☆';
    }
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

// 初始化
document.addEventListener('DOMContentLoaded', function() {
    initFavButtons();
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
        $has_real_image = has_real_image($item);
        $is_archived = !empty($item['_archived']);
    ?>
    <div class="news-item <?php echo $has_real_image ? '' : 'no-image'; ?>"
         onclick="openNews(this)"
         data-title="<?php echo xss_clean($item['title']); ?>"
         data-desc="<?php echo xss_clean($item['description'] ?? ''); ?>"
         data-industry="<?php echo xss_clean($item['industry'] ?? ''); ?>"
         data-source="<?php echo xss_clean($item['source_name'] ?? ''); ?>"
         data-time="<?php echo xss_clean($item['publish_time'] ?? $item['crawl_time'] ?? ''); ?>"
         data-url="<?php echo xss_clean($item['url'] ?? ''); ?>"
         data-image="<?php echo $has_real_image ? xss_clean($item['image']) : ''; ?>"
         data-id="<?php echo xss_clean($item['id'] ?? ''); ?>"
         data-has-modal="1">
        <!-- 收藏按钮 -->
        <button class="fav-btn <?php echo isset($item['_fav']) && $item['_fav'] ? 'active' : ''; ?>"
                data-id="<?php echo xss_clean($item['id'] ?? ''); ?>"
                onclick="event.stopPropagation(); toggleFav('<?php echo xss_clean($item['id'] ?? ''); ?>', this)">
            <?php echo (isset($item['_fav']) && $item['_fav']) ? '⭐' : '☆'; ?>
        </button>
        <!-- 缩略图：仅当有真实图片时展示 -->
        <?php if ($has_real_image): ?>
        <div class="news-thumb">
            <img src="<?php echo xss_clean($item['image']); ?>"
                 alt="<?php echo xss_clean($item['title']); ?>"
                 loading="lazy"
                 onerror="this.parentElement.innerHTML='<div class=\'img-placeholder\'>📰</div>'">
        </div>
        <?php endif; ?>
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
                <?php if ($is_archived): ?>
                <span class="news-archive-badge">📦 归档</span>
                <?php endif; ?>
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
 * 筛选新闻（支持行业、关键词、日期范围）
 */
function filter_news($news, $industry, $keyword, $date_from = '', $date_to = '') {
    $result = $news;
    // 过滤已删除的
    $result = array_filter($result, function ($item) {
        return ($item['status'] ?? 'active') === 'active';
    });
    // 行业筛选
    if (!empty($industry)) {
        $result = array_filter($result, function ($item) use ($industry) {
            return ($item['industry'] ?? '') === $industry;
        });
    }
    // 关键词搜索（标题 + 描述）
    if (!empty($keyword)) {
        $result = array_filter($result, function ($item) use ($keyword) {
            return mb_stripos($item['title'] ?? '', $keyword) !== false ||
                   mb_stripos($item['description'] ?? '', $keyword) !== false;
        });
    }
    // 日期范围筛选（按发布时间）
    if (!empty($date_from)) {
        $result = array_filter($result, function ($item) use ($date_from) {
            $t = $item['publish_time'] ?? $item['crawl_time'] ?? '';
            return $t >= $date_from;
        });
    }
    if (!empty($date_to)) {
        $result = array_filter($result, function ($item) use ($date_to) {
            $t = $item['publish_time'] ?? $item['crawl_time'] ?? '';
            // 日期到 23:59:59 结束
            return $t <= ($date_to . ' 23:59:59');
        });
    }
    // 按发布时间倒序排列（最新的在前）
    $result = array_values($result);
    usort($result, function ($a, $b) {
        $ta = strtotime($a['publish_time'] ?? $a['crawl_time'] ?? 'now');
        $tb = strtotime($b['publish_time'] ?? $b['crawl_time'] ?? 'now');
        return $tb - $ta;
    });
    return $result;
}

/**
 * 获取24小时热点数据
 * 返回：['total' => 总数, 'industries' => [行业=>数量], 'hot_keywords' => [词=>次数]]
 */
function get_hot_24h($all_news) {
    $now = time();
    $cutoff = $now - 86400;

    $recent = array_filter($all_news, function ($item) use ($cutoff) {
        $t = strtotime($item['publish_time'] ?? $item['crawl_time'] ?? 'now');
        return $t >= $cutoff;
    });

    if (empty($recent)) {
        return ['total' => 0, 'industries' => [], 'hot_keywords' => []];
    }

    // 按行业统计
    $industries = [];
    $word_freq = [];
    // 停用词
    $stopwords = ['的', '了', '在', '是', '我', '有', '和', '就', '不', '人', '都', '一',
                  '一个', '上', '也', '很', '到', '说', '要', '去', '你', '会', '着',
                  '没有', '看', '好', '自己', '这', '他', '她', '它', '们', '与', '及',
                  '为', '以', '被', '让', '从', '对', '其', '或', '但'];

    foreach ($recent as $item) {
        $ind = $item['industry'] ?? '未分类';
        if (!isset($industries[$ind])) $industries[$ind] = 0;
        $industries[$ind]++;

        // 提取标题中的关键词（按长度取2-6字的词）
        $title = $item['title'] ?? '';
        if (preg_match_all('/[\x{4e00}-\x{9fff}]{2,6}/u', $title, $matches)) {
            foreach ($matches[0] as $word) {
                if (in_array($word, $stopwords)) continue;
                if (!isset($word_freq[$word])) $word_freq[$word] = 0;
                $word_freq[$word]++;
            }
        }
    }

    // 排序
    arsort($industries);
    arsort($word_freq);

    // 取前10个关键词
    $hot_keywords = array_slice($word_freq, 0, 10, true);

    return [
        'total' => count($recent),
        'industries' => $industries,
        'hot_keywords' => $hot_keywords,
    ];
}

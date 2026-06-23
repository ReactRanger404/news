<?php
/**
 * 开源情报系统 - 新闻展示模块
 *
 * 功能：列表展示、行业筛选、关键词检索、加载更多、响应式适配
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

// 数据已在 data.json 中按时间倒序排列，无需再反转
// $filtered = array_reverse($filtered);

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
    <title>开源情报系统 - 情报展示</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "PingFang SC", "Microsoft YaHei", sans-serif; background: #f0f2f5; color: #333; }

        /* 头部 */
        .header {
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            color: white;
            padding: 40px 20px 30px;
            text-align: center;
        }
        .header h1 { font-size: 28px; margin-bottom: 8px; }
        .header p { font-size: 14px; opacity: 0.7; }
        .header .admin-link {
            position: absolute; top: 16px; right: 20px;
            color: rgba(255,255,255,0.6); text-decoration: none; font-size: 13px;
        }
        .header .admin-link:hover { color: #ffd700; }

        /* 搜索和筛选 */
        .filter-bar {
            max-width: 1200px; margin: -20px auto 20px; padding: 0 16px;
            position: relative; z-index: 10;
        }
        .filter-inner {
            background: white; border-radius: 12px; padding: 16px 20px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            display: flex; flex-wrap: wrap; gap: 12px; align-items: center;
        }
        .search-box {
            flex: 1; min-width: 200px; position: relative;
        }
        .search-box input {
            width: 100%; padding: 10px 14px 10px 38px;
            border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;
            transition: border-color 0.2s;
        }
        .search-box input:focus { border-color: #302b63; outline: none; }
        .search-box .icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #999; font-size: 16px; }
        .industry-tabs { display: flex; flex-wrap: wrap; gap: 6px; }
        .industry-tab {
            padding: 6px 14px; border-radius: 20px; font-size: 13px;
            cursor: pointer; border: 1px solid #e0e0e0; background: white;
            color: #666; transition: all 0.2s; text-decoration: none;
        }
        .industry-tab:hover { border-color: #302b63; color: #302b63; }
        .industry-tab.active { background: #302b63; color: white; border-color: #302b63; }
        .search-btn {
            padding: 10px 24px; background: #302b63; color: white;
            border: none; border-radius: 8px; font-size: 14px; cursor: pointer;
            transition: background 0.2s;
        }
        .search-btn:hover { background: #1a1a2e; }

        /* 统计 */
        .stats-bar {
            max-width: 1200px; margin: 0 auto 16px; padding: 0 16px;
            font-size: 13px; color: #888;
        }

        /* 新闻列表 */
        .container { max-width: 1200px; margin: 0 auto; padding: 0 16px 40px; }

        .news-list { display: flex; flex-direction: column; gap: 10px; }

        .news-item {
            background: white; border-radius: 10px; padding: 16px 20px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            transition: box-shadow 0.2s, transform 0.15s;
            display: flex; gap: 12px; align-items: flex-start;
        }
        .news-item { cursor: pointer; }
        .news-item:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); transform: translateY(-1px); }

        .news-index {
            width: 28px; flex-shrink: 0; text-align: center;
            color: #bbb; font-size: 14px; font-weight: 500; padding-top: 2px;
        }

        .news-content { flex: 1; min-width: 0; }

        .news-title {
            font-size: 16px; font-weight: 500; line-height: 1.5; margin-bottom: 6px;
        }
        .news-title a {
            color: #1a1a2e; text-decoration: none;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .news-title a:hover { color: #302b63; }

        .news-desc {
            font-size: 13px; color: #777; margin-top: 4px; margin-bottom: 4px;
            line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2;
            -webkit-box-orient: vertical; overflow: hidden;
        }

        .news-meta {
            display: flex; flex-wrap: wrap; gap: 8px 16px; font-size: 13px; color: #888;
        }
        .news-meta .tag {
            display: inline-block; padding: 1px 8px; border-radius: 4px; font-size: 12px;
        }
        .tag-industry {
            background: #e8f0fe; color: #1a73e8;
        }
        .tag-source {
            background: #f0f0f0; color: #666;
        }
        .news-time { color: #999; }
        .news-time .time-icon { margin-right: 3px; }

        /* 分页导航 */
        .pagination-nav { display: flex; justify-content: center; align-items: center; gap: 16px; margin: 30px 0; flex-wrap: wrap; }
        .pagination-list { display: flex; gap: 4px; list-style: none; padding: 0; margin: 0; }
        .page-link { display: inline-block; padding: 8px 14px; border-radius: 6px; font-size: 14px; color: #302b63; text-decoration: none; background: white; border: 1px solid #e0e0e0; transition: all 0.2s; }
        .page-link:hover { background: #302b63; color: white; border-color: #302b63; }
        .page-link.active { background: #302b63; color: white; border-color: #302b63; font-weight: 600; }
        .page-ellipsis { border: none; color: #999; cursor: default; background: transparent; }
        .page-info { font-size: 13px; color: #888; margin-right: 4px; }
        .pagination-top { margin-bottom: 20px; }
        .pagination-top .page-info { color: #302b63; font-weight: 500; }

        /* 空状态 */
        .empty-state { text-align: center; padding: 80px 20px; color: #999; }
        .empty-state .icon { font-size: 56px; margin-bottom: 16px; }
        .empty-state p { font-size: 16px; }

        /* 新闻详情弹窗 */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 20px; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; border-radius: 16px; width: 100%; max-width: 640px; max-height: 80vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: modalIn 0.2s ease-out; }
        @keyframes modalIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .modal-header { display: flex; justify-content: space-between; align-items: flex-start; padding: 24px 28px 12px; border-bottom: 1px solid #f0f0f0; }
        .modal-header h3 { font-size: 18px; line-height: 1.5; flex: 1; margin-right: 16px; color: #1a1a2e; }
        .modal-close { width: 32px; height: 32px; border: none; background: #f0f0f0; border-radius: 50%; font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: background 0.2s; }
        .modal-close:hover { background: #e0e0e0; }
        .modal-body { padding: 16px 28px 28px; }
        .modal-meta { display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: center; margin-bottom: 16px; }
        .modal-meta .tag { padding: 2px 10px; border-radius: 4px; font-size: 13px; }
        .modal-desc { font-size: 15px; line-height: 1.8; color: #444; white-space: pre-wrap; }
        .modal-footer-action { margin-top: 20px; padding-top: 16px; border-top: 1px solid #f0f0f0; }
        .btn-open-url { display: inline-block; padding: 10px 24px; background: #302b63; color: white; text-decoration: none; border-radius: 8px; font-size: 14px; transition: background 0.2s; }
        .btn-open-url:hover { background: #1a1a2e; }

        /* 返回顶部 */
        .back-top {
            position: fixed; bottom: 30px; right: 30px; width: 44px; height: 44px;
            background: #302b63; color: white; border: none; border-radius: 50%;
            font-size: 20px; cursor: pointer; display: none; align-items: center; justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2); transition: all 0.2s; z-index: 100;
        }
        .back-top:hover { transform: translateY(-3px); }

        /* 页脚 */
        .footer { text-align: center; padding: 20px; color: #999; font-size: 12px; border-top: 1px solid #eee; margin-top: 20px; }

        /* 响应式 */
        @media (max-width: 768px) {
            .header { padding: 30px 16px 24px; }
            .header h1 { font-size: 22px; }
            .filter-inner { flex-direction: column; }
            .industry-tabs { justify-content: center; }
            .news-item { padding: 12px 14px; }
            .news-title { font-size: 15px; }
            .news-index { display: none; }
            .back-top { bottom: 16px; right: 16px; width: 40px; height: 40px; font-size: 18px; }
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>🔍 开源情报系统</h1>
        <p>实时聚合多源情报，助力信息决策</p>
        <a href="source.php" class="admin-link" target="_blank">⚙️ 管理后台</a>
    </div>

    <!-- 筛选栏 -->
    <div class="filter-bar">
        <div class="filter-inner">
            <div class="search-box">
                <span class="icon">🔎</span>
                <form id="searchForm" method="get" action="show.php" style="display:contents">
                    <input type="text" name="keyword" id="keywordInput"
                           placeholder="搜索新闻标题..."
                           value="<?php echo xss_clean($keyword); ?>"
                           onkeydown="if(event.key==='Enter') this.form.submit()">
                </form>
            </div>
            <div class="industry-tabs">
                <a href="?industry=<?php echo urlencode($keyword ? "&keyword={$keyword}" : ''); ?>"
                   class="industry-tab <?php echo empty($industry) ? 'active' : ''; ?>">🌐 全部</a>
                <?php foreach ($config['allow_industries'] as $ind): ?>
                <a href="?industry=<?php echo urlencode($ind); ?><?php echo $keyword ? '&keyword=' . urlencode($keyword) : ''; ?>"
                   class="industry-tab <?php echo $industry === $ind ? 'active' : ''; ?>">
                    <?php echo $ind; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <button class="search-btn" onclick="document.getElementById('searchForm').submit()">搜索</button>
        </div>
    </div>

    <!-- 统计 -->
    <div class="stats-bar">
        共 <strong><?php echo $total; ?></strong> 条情报
        <?php if ($industry): ?> · 行业：<strong><?php echo xss_clean($industry); ?></strong><?php endif; ?>
        <?php if ($keyword): ?> · 关键词：<strong><?php echo xss_clean($keyword); ?></strong><?php endif; ?>
    </div>

    <!-- 新闻列表 -->
    <div class="container">
        <!-- 顶部翻页 -->
        <?php if ($total_pages > 1): ?>
        <nav class="pagination-nav pagination-top">
            <?php
            $base_url = 'show.php?page=';
            if ($industry) $base_url .= '&industry=' . urlencode($industry);
            if ($keyword) $base_url .= '&keyword=' . urlencode($keyword);
            ?>
            <ul class="pagination-list">
                <li><span class="page-info">第 <?php echo $page; ?>/<?php echo $total_pages; ?> 页</span></li>
                <?php if ($page > 1): ?>
                <li><a href="<?php echo $base_url . ($page - 1); ?>" class="page-link">&laquo; 上一页</a></li>
                <?php endif; ?>
                <?php
                $start = max(1, $page - 3);
                $end = min($total_pages, $page + 3);
                if ($start > 1): ?>
                <li><a href="<?php echo $base_url . '1'; ?>" class="page-link">1</a></li>
                <?php if ($start > 2): ?><li><span class="page-link page-ellipsis">...</span></li><?php endif;
                endif;
                for ($i = $start; $i <= $end; $i++):
                ?>
                <li><a href="<?php echo $base_url . $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a></li>
                <?php endfor;
                if ($end < $total_pages):
                    if ($end < $total_pages - 1): ?><li><span class="page-link page-ellipsis">...</span></li><?php endif; ?>
                <li><a href="<?php echo $base_url . $total_pages; ?>" class="page-link"><?php echo $total_pages; ?></a></li>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                <li><a href="<?php echo $base_url . ($page + 1); ?>" class="page-link">下一页 &raquo;</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>

        <div class="news-list" id="newsList">
            <?php render_news_list($page_news, $offset, $page); ?>
        </div>

        <?php if (empty($page_news) && $page === 1): ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <p>暂无情报数据</p>
            <p style="font-size:14px;margin-top:8px;color:#bbb">请先在管理后台添加数据源并执行爬取</p>
        </div>
        <?php endif; ?>

        <!-- 底部翻页 -->
        <?php if ($total_pages > 1): ?>
        <nav class="pagination-nav">
            <ul class="pagination-list">
                <?php if ($page > 1): ?>
                <li><a href="<?php echo $base_url . ($page - 1); ?>" class="page-link">&laquo; 上一页</a></li>
                <?php endif; ?>
                <?php
                $start = max(1, $page - 3);
                $end = min($total_pages, $page + 3);
                if ($start > 1): ?>
                <li><a href="<?php echo $base_url . '1'; ?>" class="page-link">1</a></li>
                <?php if ($start > 2): ?><li><span class="page-link page-ellipsis">...</span></li><?php endif;
                endif;
                for ($i = $start; $i <= $end; $i++):
                ?>
                <li><a href="<?php echo $base_url . $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a></li>
                <?php endfor;
                if ($end < $total_pages):
                    if ($end < $total_pages - 1): ?><li><span class="page-link page-ellipsis">...</span></li><?php endif; ?>
                <li><a href="<?php echo $base_url . $total_pages; ?>" class="page-link"><?php echo $total_pages; ?></a></li>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                <li><a href="<?php echo $base_url . ($page + 1); ?>" class="page-link">下一页 &raquo;</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <!-- 新闻详情弹窗 -->
    <div class="modal-overlay" id="newsModal" onclick="closeNews()">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modalTitle"></h3>
                <button class="modal-close" onclick="closeNews()">✕</button>
            </div>
            <div class="modal-body">
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

    <!-- 返回顶部 -->
    <button class="back-top" id="backTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑</button>

    <!-- 页脚 -->
    <div class="footer">
        开源情报系统 · 本系统仅用于学习和研究目的 · 请遵守相关法律法规
    </div>

    <script>
    // 打开新闻详情
    function openNews(el) {
        var url = el.getAttribute('data-url') || '';
        // 如果有原文链接，直接在新窗口打开
        if (url) {
            window.open(url, '_blank');
            return;
        }
        // 没有原文链接，弹窗显示描述
        var title = el.getAttribute('data-title') || '无标题';
        var desc = el.getAttribute('data-desc') || '暂无详细内容';
        var industry = el.getAttribute('data-industry') || '';
        var source = el.getAttribute('data-source') || '';
        var time = el.getAttribute('data-time') || '';

        document.getElementById('modalTitle').textContent = title;
        document.getElementById('modalDesc').textContent = desc || '暂无详细内容';
        document.getElementById('modalIndustry').textContent = industry;
        document.getElementById('modalSource').textContent = source;
        document.getElementById('modalTime').textContent = '🕐 ' + time;
        document.getElementById('modalLinkWrap').style.display = 'none';

        document.getElementById('newsModal').classList.add('active');
    }

    function closeNews() {
        document.getElementById('newsModal').classList.remove('active');
    }

    // ESC键关闭弹窗
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeNews();
    });

    // 返回顶部按钮显隐
    window.addEventListener('scroll', function() {
        const btn = document.getElementById('backTop');
        if (window.scrollY > 400) {
            btn.style.display = 'flex';
        } else {
            btn.style.display = 'none';
        }
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
        $has_desc = !empty($item['description']);
    ?>
    <div class="news-item" onclick="openNews(this)"
         data-title="<?php echo xss_clean($item['title']); ?>"
         data-desc="<?php echo xss_clean($item['description'] ?? ''); ?>"
         data-industry="<?php echo xss_clean($item['industry'] ?? ''); ?>"
         data-source="<?php echo xss_clean($item['source_name'] ?? ''); ?>"
         data-time="<?php echo xss_clean($item['publish_time'] ?? $item['crawl_time'] ?? ''); ?>"
         data-url="<?php echo xss_clean($item['url'] ?? ''); ?>">
        <div class="news-index"><?php echo $index++; ?></div>
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
            <?php if ($has_desc): ?>
            <div class="news-desc"><?php echo xss_clean(mb_substr($item['description'], 0, 120)); ?></div>
            <?php endif; ?>
            <div class="news-meta">
                <span class="tag tag-industry"><?php echo xss_clean($item['industry'] ?? '未分类'); ?></span>
                <span class="tag tag-source"><?php echo xss_clean($item['source_name'] ?? '未知来源'); ?></span>
                <span class="news-time">
                    <span class="time-icon">🕐</span>
                    <?php echo $time; ?>
                </span>
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

    // 只显示active状态的
    $result = array_filter($result, function ($item) {
        return ($item['status'] ?? 'active') === 'active';
    });

    // 行业筛选
    if (!empty($industry)) {
        $result = array_filter($result, function ($item) use ($industry) {
            return ($item['industry'] ?? '') === $industry;
        });
    }

    // 关键词搜索
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

    // 数据已在 data.json 中按时间倒序排列

    $total = count($filtered);
    $offset = ($page - 1) * $page_size;
    $page_news = array_slice($filtered, $offset, $page_size);

    if (empty($page_news)) {
        echo '';
        exit;
    }

    render_news_list($page_news, $offset, $page);
    exit;
}

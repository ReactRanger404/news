<?php
/**
 * 开源情报系统 - 数据源管理模块
 *
 * 功能：管理员登录、数据源CRUD、批量操作、导入导出、连通性测试
 * 本系统仅用于学习和研究目的
 */

require_once __DIR__ . '/common.php';
copyright_notice();

// 自动触发爬取检查（到时间自动在后台抓取）
check_auto_crawl();

$action = get('action', 'dashboard');
$config = json_read(__DIR__ . '/config.json');

// ========== 路由分发 ==========

switch ($action) {
    case 'login':
        handle_login();
        break;
    case 'logout':
        do_logout();
        header('Location: source.php?action=login');
        exit;
    case 'add':
        require_login();
        handle_add();
        break;
    case 'edit':
        require_login();
        handle_edit();
        break;
    case 'delete':
        require_login();
        handle_delete();
        break;
    case 'batch_delete':
        require_login();
        handle_batch_delete();
        break;
    case 'batch_enable':
        require_login();
        handle_batch_status('enable');
        break;
    case 'batch_disable':
        require_login();
        handle_batch_status('disable');
        break;
    case 'test':
        require_login();
        handle_test();
        break;
    case 'test_batch':
        require_login();
        handle_test_batch();
        break;
    case 'export':
        require_login();
        handle_export();
        break;
    case 'import':
        require_login();
        handle_import();
        break;
    case 'check_alive':
        require_login();
        handle_check_alive();
        break;
    case 'dashboard':
    default:
        require_login();
        show_dashboard();
        break;
}

// ========== 功能处理函数 ==========

/**
 * 登录页面
 */
function handle_login() {
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = post('username');
        $password = post('password');

        if (do_login($username, $password)) {
            header('Location: source.php');
            exit;
        } else {
            $error = '账号或密码错误！';
            log_message("登录失败: $username");
        }
    }

    // 如果已登录，直接跳转
    if (is_logged_in()) {
        header('Location: source.php');
        exit;
    }

    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>开源情报系统 - 管理员登录</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: linear-gradient(135deg, #0f0c29, #302b63, #24243e); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .login-box { background: rgba(255,255,255,0.95); padding: 40px; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); width: 380px; max-width: 90%; }
            .login-box h1 { text-align: center; font-size: 24px; margin-bottom: 8px; color: #1a1a2e; }
            .login-box .subtitle { text-align: center; color: #666; font-size: 14px; margin-bottom: 28px; }
            .form-group { margin-bottom: 18px; }
            .form-group label { display: block; font-size: 14px; color: #333; margin-bottom: 6px; font-weight: 500; }
            .form-group input { width: 100%; padding: 12px 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: border-color 0.2s; }
            .form-group input:focus { border-color: #302b63; outline: none; }
            .btn-login { width: 100%; padding: 12px; background: #302b63; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; transition: background 0.2s; }
            .btn-login:hover { background: #1a1a2e; }
            .error { background: #fff0f0; color: #d32f2f; padding: 10px; border-radius: 8px; font-size: 14px; text-align: center; margin-bottom: 16px; border: 1px solid #ffcdd2; }
            .copyright { text-align: center; color: #999; font-size: 12px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>🔍 开源情报系统</h1>
            <p class="subtitle">管理员登录</p>
            <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label>账号</label>
                    <input type="text" name="username" required autofocus placeholder="请输入管理员账号">
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" name="password" required placeholder="请输入管理员密码">
                </div>
                <button type="submit" class="btn-login">登 录</button>
            </form>
            <div class="copyright">本系统仅用于学习和研究</div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * 主仪表盘：数据源管理
 */
function show_dashboard() {
    $sources = json_read(__DIR__ . '/sources.json');
    $config = json_read(__DIR__ . '/config.json');

    // 按行业分组
    $grouped = [];
    foreach ($sources as $src) {
        $industry = $src['industry'] ?? '未分类';
        $grouped[$industry][] = $src;
    }

    // 统计
    $total = count($sources);
    $enabled = count(array_filter($sources, fn($s) => ($s['status'] ?? '') === 'enable'));
    $alive = count(array_filter($sources, fn($s) => !empty($s['is_alive'])));

    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>开源情报系统 - 数据源管理</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f6fa; color: #333; }
            .header { background: #1a1a2e; color: white; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
            .header h1 { font-size: 20px; }
            .header .user-info { font-size: 14px; opacity: 0.8; }
            .header .user-info a { color: #ffd700; text-decoration: none; margin-left: 12px; }
            .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
            .stats { display: flex; gap: 16px; margin-bottom: 24px; }
            .stat-card { background: white; border-radius: 12px; padding: 18px 24px; flex: 1; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
            .stat-card .num { font-size: 28px; font-weight: bold; color: #1a1a2e; }
            .stat-card .label { font-size: 13px; color: #888; margin-top: 4px; }
            .stat-card:nth-child(1) .num { color: #302b63; }
            .stat-card:nth-child(2) .num { color: #27ae60; }
            .stat-card:nth-child(3) .num { color: #2980b9; }
            .toolbar { background: white; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
            .toolbar .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; transition: all 0.2s; }
            .btn-primary { background: #302b63; color: white; }
            .btn-primary:hover { background: #1a1a2e; }
            .btn-success { background: #27ae60; color: white; }
            .btn-success:hover { background: #219a52; }
            .btn-danger { background: #e74c3c; color: white; }
            .btn-danger:hover { background: #c0392b; }
            .btn-warning { background: #f39c12; color: white; }
            .btn-warning:hover { background: #d68910; }
            .btn-info { background: #3498db; color: white; }
            .btn-info:hover { background: #2980b9; }
            .btn-sm { padding: 4px 10px; font-size: 12px; }
            .industry-group { background: white; border-radius: 12px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden; }
            .industry-header { padding: 14px 20px; font-size: 16px; font-weight: 600; background: #f8f9fa; border-bottom: 2px solid #302b63; display: flex; justify-content: space-between; align-items: center; }
            .industry-header .count { font-size: 13px; color: #888; font-weight: normal; }
            .source-table { width: 100%; border-collapse: collapse; }
            .source-table th { background: #fafafa; padding: 10px 14px; text-align: left; font-size: 13px; color: #666; border-bottom: 1px solid #eee; font-weight: 500; }
            .source-table td { padding: 10px 14px; font-size: 14px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
            .source-table tr:hover { background: #fafbff; }
            .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 500; }
            .badge-enable { background: #e8f8ef; color: #27ae60; }
            .badge-disable { background: #fce8e6; color: #e74c3c; }
            .badge-alive { background: #e8f0fe; color: #2980b9; }
            .badge-dead { background: #fef3e2; color: #f39c12; }
            .checkbox-col { width: 36px; text-align: center; }
            .url-cell { max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .url-cell a { color: #3498db; text-decoration: none; }
            .url-cell a:hover { text-decoration: underline; }
            .actions { display: flex; gap: 6px; flex-wrap: wrap; }
            .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
            .modal-overlay.active { display: flex; }
            .modal { background: white; border-radius: 16px; padding: 30px; width: 90%; max-width: 520px; max-height: 80vh; overflow-y: auto; }
            .modal h3 { margin-bottom: 16px; }
            .modal .form-group { margin-bottom: 14px; }
            .modal .form-group label { display: block; font-size: 13px; color: #666; margin-bottom: 4px; }
            .modal .form-group input, .modal .form-group select, .modal .form-group textarea { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; }
            .modal .form-group input:focus, .modal .form-group select:focus, .modal .form-group textarea:focus { border-color: #302b63; outline: none; }
            .modal .form-group textarea { resize: vertical; min-height: 60px; }
            .modal .btn-row { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
            .modal .btn-cancel { padding: 8px 20px; background: #eee; border: none; border-radius: 6px; cursor: pointer; }
            .toast { position: fixed; top: 20px; right: 20px; padding: 12px 20px; border-radius: 8px; color: white; font-size: 14px; z-index: 2000; display: none; animation: slideIn 0.3s; }
            .toast.success { background: #27ae60; }
            .toast.error { background: #e74c3c; }
            @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
            .form-inline { display: flex; gap: 8px; align-items: center; }
            .form-inline input { padding: 6px 10px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 13px; }
            .import-area { display: none; margin-top: 10px; }
            .import-area textarea { width: 100%; min-height: 120px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-family: monospace; font-size: 13px; }
            .test-result { font-size: 12px; margin-top: 4px; }
            .test-result.ok { color: #27ae60; }
            .test-result.fail { color: #e74c3c; }
            .remark-cell { max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #888; font-size: 13px; }
            @media (max-width: 768px) {
                .stats { flex-direction: column; }
                .source-table { font-size: 13px; }
                .source-table th, .source-table td { padding: 8px 10px; }
                .url-cell { max-width: 120px; }
                .hide-mobile { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>🔍 开源情报系统 - 数据源管理</h1>
            <div class="user-info">
                <?php echo xss_clean($_SESSION['username'] ?? '管理员'); ?>
                <a href="?action=logout">退出</a>
                <a href="show.php" target="_blank">查看新闻</a>
            </div>
        </div>

        <div class="container">
            <!-- 统计 -->
            <div class="stats">
                <div class="stat-card"><div class="num"><?php echo $total; ?></div><div class="label">数据源总数</div></div>
                <div class="stat-card"><div class="num"><?php echo $enabled; ?></div><div class="label">已启用</div></div>
                <div class="stat-card"><div class="num"><?php echo $alive; ?></div><div class="label">可访问</div></div>
            </div>

            <!-- 工具栏 -->
            <div class="toolbar">
                <button class="btn btn-primary" onclick="showModal('add')">➕ 新增数据源</button>
                <button class="btn btn-success" onclick="batchAction('batch_enable')">✅ 批量启用</button>
                <button class="btn btn-warning" onclick="batchAction('batch_disable')">⏸️ 批量禁用</button>
                <button class="btn btn-danger" onclick="batchAction('batch_delete')">🗑️ 批量删除</button>
                <button class="btn btn-info" onclick="location.href='?action=test_batch'">📡 批量测速</button>
                <button class="btn btn-info" onclick="location.href='?action=check_alive'">🔄 检查可用性</button>
                <button class="btn btn-primary" onclick="location.href='?action=export'">📤 导出JSON</button>
                <button class="btn btn-primary" onclick="toggleImport()">📥 导入JSON</button>
                <div class="import-area" id="importArea">
                    <form method="post" action="?action=import" onsubmit="return confirm('确认导入？将替换当前所有数据源')">
                        <textarea name="import_data" placeholder="粘贴JSON数据..." required></textarea>
                        <div style="margin-top:8px"><button type="submit" class="btn btn-success btn-sm">确认导入</button></div>
                    </form>
                </div>
            </div>

            <!-- 消息提示 -->
            <?php
            $msg = get('msg', '');
            $err = get('err', '');
            if ($msg): ?><div class="toast success" style="display:block"><?php echo $msg; ?></div><?php endif;
            if ($err): ?><div class="toast error" style="display:block"><?php echo $err; ?></div><?php endif;
            ?>

            <!-- 数据源列表（按行业分组） -->
            <?php foreach ($grouped as $industry => $srcs): ?>
            <div class="industry-group">
                <div class="industry-header">
                    <span>📂 <?php echo xss_clean($industry); ?></span>
                    <span class="count">共 <?php echo count($srcs); ?> 个数据源</span>
                </div>
                <table class="source-table">
                    <thead>
                        <tr>
                            <th class="checkbox-col"><input type="checkbox" onchange="toggleGroup(this)"></th>
                            <th>名称</th>
                            <th class="hide-mobile">URL</th>
                            <th>状态</th>
                            <th class="hide-mobile">可用性</th>
                            <th class="hide-mobile">备注</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($srcs as $src): ?>
                        <tr id="row-<?php echo $src['id']; ?>">
                            <td><input type="checkbox" class="item-checkbox" value="<?php echo $src['id']; ?>"></td>
                            <td><strong><?php echo xss_clean($src['name']); ?></strong></td>
                            <td class="url-cell hide-mobile"><a href="<?php echo xss_clean($src['url']); ?>" target="_blank" title="<?php echo xss_clean($src['url']); ?>"><?php echo xss_clean($src['url']); ?></a></td>
                            <td>
                                <span class="badge badge-<?php echo ($src['status'] ?? 'disable') === 'enable' ? 'enable' : 'disable'; ?>">
                                    <?php echo ($src['status'] ?? 'disable') === 'enable' ? '已启用' : '已禁用'; ?>
                                </span>
                            </td>
                            <td class="hide-mobile">
                                <span class="badge <?php echo !empty($src['is_alive']) ? 'badge-alive' : 'badge-dead'; ?>">
                                    <?php echo !empty($src['is_alive']) ? '可访问' : '未知/不可达'; ?>
                                </span>
                            </td>
                            <td class="remark-cell hide-mobile" title="<?php echo xss_clean($src['remark'] ?? ''); ?>"><?php echo xss_clean($src['remark'] ?? '-'); ?></td>
                            <td>
                                <div class="actions">
                                    <button class="btn btn-info btn-sm" onclick="editSource('<?php echo $src['id']; ?>')">编辑</button>
                                    <a href="?action=test&id=<?php echo $src['id']; ?>" class="btn btn-sm btn-warning">测速</a>
                                    <a href="?action=delete&id=<?php echo $src['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('确认删除 <?php echo xss_clean($src['name']); ?>？')">删除</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>

            <?php if (empty($grouped)): ?>
            <div style="text-align:center;padding:60px 20px;color:#888">
                <div style="font-size:48px;margin-bottom:16px">📭</div>
                <p>暂无数据源，请点击右上角「新增数据源」添加</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- 新增/编辑 Modal -->
        <div class="modal-overlay" id="sourceModal">
            <div class="modal">
                <h3 id="modalTitle">新增数据源</h3>
                <form method="post" id="sourceForm">
                    <input type="hidden" name="edit_id" id="edit_id" value="">
                    <div class="form-group">
                        <label>数据源名称 *</label>
                        <input type="text" name="name" id="field_name" required>
                    </div>
                    <div class="form-group">
                        <label>URL地址 *</label>
                        <input type="url" name="url" id="field_url" required placeholder="https://example.com/rss">
                    </div>
                    <div class="form-group">
                        <label>行业分类 *</label>
                        <select name="industry" id="field_industry" required>
                            <?php foreach ($config['allow_industries'] as $ind): ?>
                            <option value="<?php echo $ind; ?>"><?php echo $ind; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>状态</label>
                        <select name="status" id="field_status">
                            <option value="enable">启用</option>
                            <option value="disable">禁用</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>备注</label>
                        <textarea name="remark" id="field_remark" placeholder="备注信息（可选）"></textarea>
                    </div>
                    <div class="btn-row">
                        <button type="button" class="btn-cancel" onclick="hideModal()">取消</button>
                        <button type="submit" class="btn btn-primary">保存</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        // 模态框
        function showModal(type, data) {
            const modal = document.getElementById('sourceModal');
            const form = document.getElementById('sourceForm');

            if (type === 'add') {
                document.getElementById('modalTitle').textContent = '➕ 新增数据源';
                document.getElementById('edit_id').value = '';
                form.action = '?action=add';
                document.getElementById('field_name').value = '';
                document.getElementById('field_url').value = '';
                document.getElementById('field_industry').value = '<?php echo $config['allow_industries'][0] ?? "政治"; ?>';
                document.getElementById('field_status').value = 'enable';
                document.getElementById('field_remark').value = '';
            }
            modal.classList.add('active');
        }

        function hideModal() {
            document.getElementById('sourceModal').classList.remove('active');
        }

        // 编辑数据源
        function editSource(id) {
            const row = document.getElementById('row-' + id);
            if (!row) return;

            const cells = row.querySelectorAll('td');
            const name = cells[1].textContent.trim();
            const url = cells[2]?.textContent?.trim() || '';
            const status = cells[3]?.querySelector('.badge')?.textContent?.includes('启用') ? 'enable' : 'disable';
            const remark = cells[5]?.textContent?.trim() || '';

            const modal = document.getElementById('sourceModal');
            document.getElementById('modalTitle').textContent = '✏️ 编辑数据源';
            document.getElementById('edit_id').value = id;
            document.getElementById('sourceForm').action = '?action=edit';
            document.getElementById('field_name').value = name;
            document.getElementById('field_url').value = url;
            document.getElementById('field_status').value = status;
            document.getElementById('field_remark').value = remark === '-' ? '' : remark;

            modal.classList.add('active');
        }

        // 分组全选
        function toggleGroup(chk) {
            const tbody = chk.closest('table').querySelector('tbody');
            tbody.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = chk.checked);
        }

        // 批量操作
        function batchAction(action) {
            const checked = document.querySelectorAll('.item-checkbox:checked');
            if (checked.length === 0) {
                alert('请先选择要操作的数据源');
                return;
            }

            if (action === 'batch_delete' && !confirm('确认删除选中的 ' + checked.length + ' 个数据源？')) {
                return;
            }

            const ids = Array.from(checked).map(cb => cb.value).join(',');
            location.href = '?action=' + action + '&ids=' + ids;
        }

        // 导入区域切换
        function toggleImport() {
            const area = document.getElementById('importArea');
            area.style.display = area.style.display === 'block' ? 'none' : 'block';
        }

        // 自动隐藏消息
        setTimeout(() => {
            document.querySelectorAll('.toast').forEach(el => el.style.display = 'none');
        }, 4000);

        // 点击模态框外部关闭
        document.getElementById('sourceModal').addEventListener('click', function(e) {
            if (e.target === this) hideModal();
        });
        </script>
    </body>
    </html>
    <?php
}

/**
 * 新增数据源
 */
function handle_add() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: source.php');
        exit;
    }

    $sources = json_read(__DIR__ . '/sources.json');

    $name = post('name');
    $url = post('url');
    $industry = post('industry');
    $status = post('status', 'enable');
    $remark = post('remark');

    // 校验
    if (empty($name) || empty($url)) {
        header('Location: source.php?err=名称和URL不能为空');
        exit;
    }

    // URL格式校验
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        header('Location: source.php?err=URL格式不正确');
        exit;
    }

    // 重复URL检测
    foreach ($sources as $src) {
        if ($src['url'] === $url) {
            header('Location: source.php?err=该URL已存在，请勿重复添加');
            exit;
        }
    }

    $new_src = [
        'id' => gen_id('src'),
        'name' => $name,
        'url' => $url,
        'industry' => $industry,
        'status' => $status,
        'remark' => $remark,
        'create_time' => date('Y-m-d H:i:s'),
        'last_check' => '',
        'is_alive' => false,
    ];

    $sources[] = $new_src;
    json_write(__DIR__ . '/sources.json', $sources);
    log_message("新增数据源: $name ($url)");

    header('Location: source.php?msg=数据源添加成功');
}

/**
 * 编辑数据源
 */
function handle_edit() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: source.php');
        exit;
    }

    $sources = json_read(__DIR__ . '/sources.json');
    $edit_id = post('edit_id');
    $name = post('name');
    $url = post('url');
    $industry = post('industry');
    $status = post('status', 'enable');
    $remark = post('remark');

    if (empty($edit_id) || empty($name) || empty($url)) {
        header('Location: source.php?err=参数不完整');
        exit;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        header('Location: source.php?err=URL格式不正确');
        exit;
    }

    $found = false;
    foreach ($sources as &$src) {
        if ($src['id'] === $edit_id) {
            // 检查URL重复（排除自己）
            foreach ($sources as $other) {
                if ($other['id'] !== $edit_id && $other['url'] === $url) {
                    header('Location: source.php?err=该URL已被其他数据源使用');
                    exit;
                }
            }
            $src['name'] = $name;
            $src['url'] = $url;
            $src['industry'] = $industry;
            $src['status'] = $status;
            $src['remark'] = $remark;
            $found = true;
            log_message("编辑数据源: $name ($url)");
            break;
        }
    }

    if (!$found) {
        header('Location: source.php?err=数据源不存在');
        exit;
    }

    json_write(__DIR__ . '/sources.json', $sources);
    header('Location: source.php?msg=数据源更新成功');
}

/**
 * 删除数据源
 */
function handle_delete() {
    $id = get('id');
    if (empty($id)) {
        header('Location: source.php?err=参数错误');
        exit;
    }

    $sources = json_read(__DIR__ . '/sources.json');
    $sources = array_values(array_filter($sources, fn($s) => $s['id'] !== $id));
    json_write(__DIR__ . '/sources.json', $sources);
    log_message("删除数据源: $id");

    header('Location: source.php?msg=数据源已删除');
}

/**
 * 批量删除
 */
function handle_batch_delete() {
    $ids = explode(',', get('ids', ''));
    if (empty($ids)) {
        header('Location: source.php?err=未选择数据源');
        exit;
    }

    $sources = json_read(__DIR__ . '/sources.json');
    $sources = array_values(array_filter($sources, fn($s) => !in_array($s['id'], $ids)));
    json_write(__DIR__ . '/sources.json', $sources);
    log_message("批量删除: " . count($ids) . " 个数据源");

    header('Location: source.php?msg=已删除 ' . count($ids) . ' 个数据源');
}

/**
 * 批量启用/禁用
 */
function handle_batch_status($status) {
    $ids = explode(',', get('ids', ''));
    if (empty($ids)) {
        header('Location: source.php?err=未选择数据源');
        exit;
    }

    $sources = json_read(__DIR__ . '/sources.json');
    foreach ($sources as &$src) {
        if (in_array($src['id'], $ids)) {
            $src['status'] = $status;
        }
    }
    json_write(__DIR__ . '/sources.json', $sources);

    $label = $status === 'enable' ? '启用' : '禁用';
    log_message("批量{$label}: " . count($ids) . " 个数据源");
    header('Location: source.php?msg=已' . $label . ' ' . count($ids) . ' 个数据源');
}

/**
 * 测试单个URL
 */
function handle_test() {
    $id = get('id');
    $sources = json_read(__DIR__ . '/sources.json');

    $src = null;
    foreach ($sources as &$s) {
        if ($s['id'] === $id) {
            $src = &$s;
            break;
        }
    }

    if (!$src) {
        header('Location: source.php?err=数据源不存在');
        exit;
    }

    $result = test_url($src['url']);

    // 更新可用性
    $src['last_check'] = date('Y-m-d H:i:s');
    $src['is_alive'] = $result['reachable'];
    json_write(__DIR__ . '/sources.json', $sources);

    log_message("测试数据源 [{$src['name']}]: HTTP {$result['http_code']}, 响应时间 {$result['total_time']}s");

    $status = $result['reachable'] ? "✅ 可访问" : "❌ 不可访问";
    $msg = "{$src['name']}: {$status} (HTTP {$result['http_code']}, {$result['total_time']}s)";
    header('Location: source.php?msg=' . urlencode($msg));
}

/**
 * 批量测试
 */
function handle_test_batch() {
    $sources = json_read(__DIR__ . '/sources.json');
    $success = 0;
    $fail = 0;

    foreach ($sources as &$src) {
        $result = test_url($src['url']);
        $src['last_check'] = date('Y-m-d H:i:s');
        $src['is_alive'] = $result['reachable'];
        if ($result['reachable']) $success++; else $fail++;
        usleep(200000); // 200ms间隔，防止被封
    }

    json_write(__DIR__ . '/sources.json', $sources);
    log_message("批量测速完成: $success 可访问, $fail 不可达");

    header('Location: source.php?msg=批量测速完成：' . $success . ' 个可访问，' . $fail . ' 个不可达');
}

/**
 * 检查所有数据源可用性
 */
function handle_check_alive() {
    handle_test_batch(); // 复用批量测速逻辑
}

/**
 * 导出数据源为JSON
 */
function handle_export() {
    $sources = json_read(__DIR__ . '/sources.json');
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="sources_export_' . date('Ymd_His') . '.json"');
    echo json_encode($sources, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * 导入JSON
 */
function handle_import() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: source.php');
        exit;
    }

    $import_data = post('import_data');
    if (empty($import_data)) {
        header('Location: source.php?err=导入数据为空');
        exit;
    }

    $data = json_decode($import_data, true);
    if (!is_array($data)) {
        header('Location: source.php?err=JSON格式无效，请检查数据');
        exit;
    }

    // 数据校验
    $valid = [];
    $errors = [];
    foreach ($data as $item) {
        if (empty($item['name']) || empty($item['url'])) {
            $errors[] = '存在缺少名称或URL的条目';
            continue;
        }
        if (!filter_var($item['url'], FILTER_VALIDATE_URL)) {
            $errors[] = "URL格式无效: {$item['url']}";
            continue;
        }
        $valid[] = [
            'id' => $item['id'] ?? gen_id('src'),
            'name' => $item['name'],
            'url' => $item['url'],
            'industry' => $item['industry'] ?? '社会',
            'status' => $item['status'] ?? 'enable',
            'remark' => $item['remark'] ?? '',
            'create_time' => $item['create_time'] ?? date('Y-m-d H:i:s'),
            'last_check' => '',
            'is_alive' => false,
        ];
    }

    if (empty($valid)) {
        header('Location: source.php?err=没有有效数据可导入');
        exit;
    }

    json_write(__DIR__ . '/sources.json', $valid);
    log_message("导入数据源: " . count($valid) . " 条");

    $msg = "成功导入 " . count($valid) . " 个数据源";
    if (!empty($errors)) {
        $msg .= "，跳过 " . count($errors) . " 条无效数据";
    }
    header('Location: source.php?msg=' . urlencode($msg));
}

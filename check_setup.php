<?php
/**
 * 开源情报系统 - 部署环境检测
 *
 * 访问此文件检查服务器环境是否正常。
 * 如果页面任何一项显示 ❌，请根据提示修复。
 *
 * 本系统仅用于学习和研究目的
 */
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>环境检测 - 开源情报系统</title>
    <style>
        body { font-family: -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif; background: #f5f6fa; padding: 40px; max-width: 800px; margin: 0 auto; }
        h1 { font-size: 22px; color: #1a1a2e; margin-bottom: 8px; }
        .sub { color: #888; font-size: 14px; margin-bottom: 24px; }
        .card { background: white; border-radius: 12px; padding: 20px 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 16px; }
        .card h3 { font-size: 16px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .item:last-child { border-bottom: none; }
        .ok { color: #27ae60; font-weight: 600; }
        .fail { color: #e74c3c; font-weight: 600; }
        .warn { color: #f39c12; font-weight: 600; }
        .tip { background: #fff8e1; border-left: 4px solid #f39c12; padding: 12px 16px; border-radius: 6px; font-size: 13px; margin-top: 8px; color: #666; }
        .tip code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
        .pass-banner { text-align: center; padding: 30px; background: #e8f8ef; border-radius: 12px; margin-bottom: 16px; }
        .pass-banner .big { font-size: 48px; }
        .pass-banner .text { font-size: 18px; color: #27ae60; font-weight: 600; margin-top: 8px; }
    </style>
</head>
<body>
    <h1>🔍 开源情报系统 - 部署环境检测</h1>
    <p class="sub">检测结果：<?php echo date('Y-m-d H:i:s'); ?></p>

    <?php
    $dir = __DIR__;
    $all_ok = true;

    // =============================================
    // 1. PHP 版本
    // =============================================
    ?>
    <div class="card">
        <h3>1️⃣ PHP 运行环境</h3>
        <div class="item">
            <span>PHP 版本</span>
            <span class="ok"><?php echo PHP_VERSION; ?></span>
        </div>
        <div class="item">
            <span>PHP 运行用户</span>
            <span><?php echo function_exists('exec') ? trim(shell_exec('whoami') ?: '未知') : '未知（exec 不可用）'; ?></span>
        </div>
        <div class="item">
            <span>服务器软件</span>
            <span><?php echo $_SERVER['SERVER_SOFTWARE'] ?? '未知'; ?></span>
        </div>
        <div class="item">
            <span>PHP 运行模式</span>
            <span><?php echo php_sapi_name(); ?></span>
        </div>
    </div>

    <?php
    // =============================================
    // 2. 文件权限检测
    // =============================================
    $files_to_check = [
        'data.json'     => '新闻数据文件（必须可写）',
        'sources.json'  => '数据源配置文件（必须可写）',
        'config.json'   => '系统配置（可读即可）',
        'logs/'         => '日志目录（必须可写）',
        'archive/'      => '归档目录（必须可写）',
        '.last_crawl'   => '爬虫时间标记（必须可写）',
    ];
    ?>
    <div class="card">
        <h3>2️⃣ 文件/目录写入权限</h3>
        <p style="font-size:13px;color:#888;margin-bottom:12px;">
            检测目录：<code><?php echo $dir; ?></code>
        </p>
        <?php foreach ($files_to_check as $file => $desc):
            $path = $dir . '/' . $file;
            $exists = file_exists($path);
            $writable = is_writable($path);

            // 如果是目录，检查里面能否创建文件
            if ($exists && is_dir($path)) {
                $test_file = $path . '/.write_test';
                $can_create = @file_put_contents($test_file, 'test') !== false;
                if ($can_create) @unlink($test_file);
                $writable = $can_create;
            }

            if (!$exists && substr($file, -1) === '/') {
                // 目录不存在，检查能否创建
                $writable = is_writable(dirname($path));
            }

            $is_ok = $writable;
            if (!$is_ok) $all_ok = false;
        ?>
        <div class="item">
            <span>
                <?php echo $exists ? '📄' : '❌'; ?>
                <code><?php echo $file; ?></code>
                <span style="color:#999;font-size:12px;">— <?php echo $desc; ?></span>
            </span>
            <span class="<?php echo $is_ok ? 'ok' : 'fail'; ?>">
                <?php echo $is_ok ? '✅ 可写' : '❌ 不可写'; ?>
            </span>
        </div>
        <?php endforeach; ?>
        <?php if (!$all_ok): ?>
        <div class="tip">
            <strong>🛠 修复方法：</strong>在服务器上执行以下命令（需要 SSH 或联系管理员）：
            <br><br>
            <code>cd <?php echo $dir; ?></code><br>
            <code>chmod 666 data.json sources.json .last_crawl</code><br>
            <code>chmod 777 logs/ archive/</code><br>
            <br>
            如果无法执行命令，尝试通过 FTP 将上述文件权限设置为 <strong>666</strong>，目录设置为 <strong>777</strong>。
        </div>
        <?php endif; ?>
    </div>

    <?php
    // =============================================
    // 3. PHP 函数可用性
    // =============================================
    $funcs = [
        'exec'        => ['后台启动爬虫', true],
        'curl_exec'   => ['抓取远程RSS/HTML', true],
        'file_put_contents' => ['写入文件', true],
        'json_encode' => ['JSON编码', true],
        'json_decode' => ['JSON解码', true],
        'session_start' => ['登录会话', true],
        'simplexml_load_string' => ['解析RSS XML', true],
        'mb_substr'   => ['中文截断', true],
        'filter_var'  => ['URL校验', true],
    ];
    ?>
    <div class="card">
        <h3>3️⃣ 关键 PHP 函数</h3>
        <?php foreach ($funcs as $func => [$label, $required]):
            $available = function_exists($func);
            if (!$available && $required) $all_ok = false;
            $disabled = in_array($func, explode(',', ini_get('disable_functions') ?: ''));
        ?>
        <div class="item">
            <span>
                <code><?php echo $func; ?>()</code>
                <span style="color:#999;font-size:12px;">— <?php echo $label; ?></span>
            </span>
            <span class="<?php echo $available && !$disabled ? 'ok' : ($required ? 'fail' : 'warn'); ?>">
                <?php
                if ($available && !$disabled) echo '✅ 可用';
                elseif ($disabled) echo '❌ 已禁用(disable_functions)';
                else echo $required ? '❌ 不可用' : '⚠️ 不可用（非必须）';
                ?>
            </span>
        </div>
        <?php endforeach; ?>
        <?php if (in_array('exec', explode(',', ini_get('disable_functions') ?: ''))): ?>
        <div class="tip">
            <strong>⚠️ `exec()` 被禁用：</strong>cron.php 会自动切换到进程内执行模式，但如果爬虫耗时超过服务器超时限制可能失败。
            建议联系管理员将 <code>exec</code> 移出 <code>disable_functions</code>。
        </div>
        <?php endif; ?>
    </div>

    <?php
    // =============================================
    // 4. PHP 配置关键参数
    // =============================================
    $settings = [
        'max_execution_time' => '脚本最大执行时间',
        'memory_limit'       => 'PHP 内存限制',
        'upload_max_filesize'=> '上传最大文件',
        'post_max_size'      => 'POST 最大数据',
    ];
    ?>
    <div class="card">
        <h3>4️⃣ PHP 配置参数</h3>
        <?php foreach ($settings as $key => $label):
            $val = ini_get($key);
            $is_ok = ($key === 'max_execution_time' && $val == 0) || ($key !== 'max_execution_time');
        ?>
        <div class="item">
            <span><code><?php echo $key; ?></code> — <?php echo $label; ?></span>
            <span><?php echo $val ?: ($key === 'max_execution_time' ? '0（不限）' : '未限制'); ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (ini_get('max_execution_time') > 0 && ini_get('max_execution_time') < 300): ?>
        <div class="tip">
            ⚠️ <code>max_execution_time</code> 仅 <?php echo ini_get('max_execution_time'); ?> 秒，
            爬虫全量运行可能需要10-20分钟。如果使用 cron.php 进程内执行模式可能会超时。
            建议设置 <code>set_time_limit(0)</code> 或在 cron-job.org 里调高间隔。
        </div>
        <?php endif; ?>
    </div>

    <?php
    // =============================================
    // 5. 最终结论
    // =============================================
    ?>
    <?php if ($all_ok): ?>
    <div class="pass-banner">
        <div class="big">✅</div>
        <div class="text">环境一切正常！爬虫应该可以正常工作</div>
        <p style="color:#666;margin-top:8px;">如果数据仍然不更新，请检查 cron-job.org 的 URL 配置是否正确</p>
    </div>
    <?php else: ?>
    <div class="card" style="border-left:4px solid #e74c3c;">
        <h3>❌ 存在需要修复的问题</h3>
        <p style="color:#666;font-size:14px;">
            请根据上面的红色 ❌ 项目逐项修复。最常见的原因是文件权限不足，
            需要服务器管理员执行 <code>chmod</code> 命令。
        </p>
        <p style="color:#888;font-size:13px;margin-top:8px;">
            修复后重新访问此页面确认所有项目变 ✅
        </p>
    </div>
    <?php endif; ?>

    <div style="text-align:center;color:#aaa;font-size:12px;margin-top:24px;">
        开源情报系统 - 环境检测工具
    </div>
</body>
</html>

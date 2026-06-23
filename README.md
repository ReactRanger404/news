# 🔍 开源情报系统

> 基于 PHP + JSON 的轻量级开源情报聚合系统
> 本系统仅用于学习和研究目的，请遵守相关法律法规

---

## 环境要求

- PHP 7.4 或更高版本（推荐 PHP 8.0+）
- PHP 扩展：curl、json、simplexml、mbstring
- 无需数据库

---

## 安装与部署

### 1. 安装 PHP

**Windows：**
1. 下载 PHP：https://windows.php.net/download/
2. 解压到 `C:\php\`
3. 将 `C:\php` 添加到系统 PATH 环境变量
4. 打开 `C:\php\php.ini`，确保以下扩展已启用：
   ```
   extension=curl
   extension=json
   extension=simplexml
   extension=mbstring
   ```

验证安装：
```bash
php --version
```

### 2. 启动服务

进入项目目录，使用 PHP 内置服务器：

```bash
cd C:\Users\z2256\Desktop\news
php -S localhost:8080
```

### 3. 访问系统

- **新闻展示页**：http://localhost:8080/show.php
- **管理后台**：http://localhost:8080/source.php

---

## 配置说明

### 管理员登录

默认账号密码：

| 字段 | 值 |
|------|-----|
| 账号 | `admin` |
| 密码 | `admin` |

> 首次登录后请及时修改密码！

### 修改密码

编辑 `config.json`：

```json
{
    "admin_user": "admin",
    "admin_pwd": "21232f297a57a5a743894a0e4a801fc3",
    ...
}
```

`admin_pwd` 为 **MD5 加密**后的密码。可以使用在线 MD5 工具生成。

### config.json 参数说明

| 参数 | 说明 | 默认值 |
|------|------|--------|
| `admin_user` | 管理员账号 | admin |
| `admin_pwd` | 管理员密码（MD5） | admin的MD5 |
| `crawl_interval` | 爬取间隔（分钟） | 15 |
| `max_news` | 最大存储新闻数 | 2000 |
| `page_size` | 每页展示条数 | 80 |
| `request_timeout` | HTTP请求超时（秒） | 10 |
| `allow_industries` | 允许的行业分类列表 | [...] |

---

## 使用说明

### 管理后台 (source.php)

1. **数据源管理**
   - 按行业分组展示所有数据源
   - 支持新增、编辑、删除（单条/批量）
   - 支持批量启用/禁用
   - 支持 URL 连通性测试（单条/批量）

2. **导入导出**
   - 导出所有数据源为 JSON 文件
   - 导入 JSON 格式的数据源（自动校验）

3. **可用性检查**
   - 批量测试所有数据源的连通性
   - 自动更新每个数据源的状态

### 爬取任务 (crawler.php)

两种运行方式：

**Web方式：**
浏览器访问：http://localhost:8080/crawler.php（需先登录管理后台）

**命令行方式：**
```bash
cd C:\Users\z2256\Desktop\news
php crawler.php
```

**定时任务设置：**

Windows 计划任务（每15分钟执行一次）：
```
1. 打开「任务计划程序」
2. 创建基本任务
3. 触发器：每15分钟
4. 操作：启动程序
   程序/脚本：php
   参数：C:\Users\z2256\Desktop\news\crawler.php
```

### 新闻展示 (show.php)

- 默认每页 80 条，倒序排列
- 支持行业筛选（政治/经济/军事/金融/股票/科技/医疗/娱乐/体育/社会）
- 支持标题关键词搜索
- 支持关键词 + 行业组合筛选
- 点击「加载更多」或滚动到底部自动加载
- 响应式设计，适配手机和 PC

---

## 目录结构

```
/news/
├── config.json       # 系统配置
├── sources.json      # 数据源配置
├── data.json         # 新闻数据
├── common.php        # 公共函数库
├── source.php        # 数据源管理
├── crawler.php       # 爬取程序
├── show.php          # 新闻展示
├── README.md         # 本文件
├── logs/
│   └── crawl.log     # 爬取日志
```

---

## 安全说明

- 所有 JSON 读写均带文件锁，防止并发写入损坏
- 所有输入均经过 XSS 过滤
- URL 格式校验和重复检测
- 错误捕获，不抛出 PHP 原生报错
- 随机 User-Agent，防止被反爬识别

---

## 免责声明

本系统仅用于学习和研究目的。用户在使用本系统时应遵守相关法律法规，不得用于任何非法用途。开发者不对因使用本系统而产生的任何法律问题承担责任。

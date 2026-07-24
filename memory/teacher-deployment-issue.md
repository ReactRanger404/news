---
name: teacher-deployment-issue
description: 老师服务器部署后爬虫不更新数据的根因分析和修复
metadata:
  type: project
---

# 老师服务器部署问题

## 问题现象
- Railway 部署 ✅ 爬虫正常定时执行
- 老师服务器 `a.kuwii.com/course/j/` ❌ cron-job.org 显示成功但数据不更新（停在7月13日）

## 根因
`function_exists('exec')` 在 PHP `disable_functions` 禁用 `exec` 时仍返回 `true`。
导致 `common.php` 和 `cron.php` 中的策略A（exec后台启动爬虫）误判为成功，
实际爬虫未运行，`data.json` 未更新。

## 修复
1. **common.php** `check_auto_crawl()` - 增加 `ini_get('disable_functions')` 检测 + `exec()` 返回值检查
2. **cron.php** 策略A - 同上修复，不可用时正确 fallback 到策略B（进程内执行）
3. **source.php / crawler.php** - `copyright_notice()` 移到登录检查之后，避免 `output_buffering=Off` 时 header 重定向失败导致白屏

## 待办
- [ ] 将最新代码（commit `c5129c9`）部署到老师服务器 `a.kuwii.com/course/j/`
- [ ] 部署后观察 cron-job.org 下次触发时 `data.json` 是否更新
- [ ] Railway 部署保持不变（已正常工作）

## 相关记忆
- [[data-revert-issue]] 之前回退数据文件导致7月22日数据丢失

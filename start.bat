@echo off
title 开源情报系统
cd /d "%~dp0"

echo ============================================
echo   开源情报系统 - 一键启动
echo ============================================
echo.
echo 启动 Web 服务器 (http://localhost:8080)
echo 启动后台爬取守护 (每15分钟自动更新)
echo.
echo 关闭本窗口即停止所有服务
echo ============================================
echo.

:: 启动 PHP 内置 Web 服务器（新窗口）
start "情报系统-Web" php -S localhost:8080

:: 启动后台爬取守护（新窗口）
start "情报系统-爬虫" php background_crawler.php

echo 服务已启动！
echo 新闻页面: http://localhost:8080
echo 管理后台: http://localhost:8080/source.php
echo.
echo 按任意键关闭所有服务...
pause >nul

:: 关闭所有相关进程
echo 正在关闭服务...
taskkill /f /fi "WINDOWTITLE eq 情报系统-Web" >nul 2>&1
taskkill /f /fi "WINDOWTITLE eq 情报系统-爬虫" >nul 2>&1
echo 已停止所有服务。
timeout /t 2 >nul

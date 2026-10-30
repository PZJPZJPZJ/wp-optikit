# WP OptiKit

WordPress 性能优化插件，提供图片优化、媒体清理和 Elementor 表单增强功能。

[![PHP 版本](https://img.shields.io/badge/PHP-8.1+-%23777BB4.svg)](https://php.net)
[![WordPress 版本](https://img.shields.io/badge/WordPress-6.7+-%2321759B.svg)](https://wordpress.org)
[![许可证](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](LICENSE)

## 图片优化

- **自动转换 WebP**：上传时转换 JPG、PNG 图片，也可按需开启 GIF 转换。
- **批量转换**：扫描媒体库，将选中的图片加入后台队列，支持查看进度、取消任务和清理记录。
- **质量与引擎设置**：支持调整输出质量，选择 Imagick 或 GD 转换引擎。
- **Elementor 联动**：可在批量图片任务结束后自动清理 Elementor 缓存。

## 媒体清理

- **孤立文件扫描**：查找上传目录中未被附件标准元数据记录的图片，支持删除选中项。
- **缺失文件清理**：检查附件文件缺失情况，清理对应的附件记录或缺失元数据。

## Elementor 表单

- **提交拦截**：拦截 referrer 字段缺失或为空的表单提交。
- **国家/区号字段**：提供国家名称与拨号区号下拉选择，支持设置默认国家和 Cloudflare 自动识别，识别失败时保留默认值。

表单功能需要 Elementor Pro，两项功能均默认关闭，可独立启用。

## 规划功能

页面缓存、CSS 与 JavaScript 优化、数据库维护尚未开放。

# WP OptiKit 🚀

**All-in-One WordPress Speed Optimization Toolkit** — supercharge your WordPress site with automatic image optimization, and more performance modules on the way.

[![PHP](https://img.shields.io/badge/PHP-8.1+-%23777BB4.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.7+-%2321759B.svg)](https://wordpress.org)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](LICENSE)

---

## ✨ Features

### 🖼️ Image Optimization (Active)

| Feature | Description |
|---|---|
| **Auto WebP Conversion** | Newly uploaded JPG, PNG, and GIF images are automatically converted to WebP — no manual steps needed. |
| **Batch Convert Existing** | Scan your entire Media Library and queue all unconverted images for bulk WebP conversion in one click. |
| **Adjustable Quality** | Fine-tune output quality from 1–100 to balance visual fidelity vs. file size. |
| **Replace Source Files** | Successful conversions replace the original source-format file with the generated WebP file. |
| **Background Queue** | All batch jobs run in a background queue with real-time progress tracking — no browser blocking. |
| **Elementor Integration** | Automatically clears the Elementor CSS cache after batch image jobs complete, so your builder-built site stays fast. |

### Elementor Form

后台 **OptiKit → 表单 → Elementor Form** 按“安全”和“功能”两张卡片组织设置，共用保存按钮。这些功能需要 Elementor Pro；未启用 Elementor Pro 时仍可保存设置，但功能不运行。

- **安全：拦截 referrer 为空的提交**。默认关闭。启用后检查全站 Elementor Pro 表单提交数据中的 `referrer` 字段；字段缺失、空字符串、仅含空白或非字符串时拒绝提交，显示“提交已被拒绝。”。只检查提交字段，不校验 URL、来源域名或 HTTP `Referer` 请求头。
- **功能：国家/区号字段（Country Code）**。默认关闭。启用并保存后，在 Elementor Form 小部件中添加“国家/区号”字段，为每个字段设置默认国家（初始为美国）和 Cloudflare 自动识别（初始开启）。字段独立显示国家/地区及拨号区号，沿用 Elementor 原生下拉样式、标签、尺寸与必填设置。

国家识别由浏览器请求本站 `/cdn-cgi/trace` 完成，适用于启用 Cloudflare 代理的站点；识别失败、超时或结果不支持时保留默认国家。整页缓存中始终输出默认国家，再按当前访客识别；异步识别不会覆盖访客已经手动选择的值。编辑器预览和禁用 JavaScript 时使用默认国家。

字段类型标识为 `wpok_country_code`；浏览器提交国家代码，服务端在表单动作执行前校验和格式化。处理后的 `raw_value` 为国家代码（如 `US`），`value` 为国家名称及区号（如英文环境的 `United States (+1)`），用于邮件、Webhook 和提交记录。国家名称随站点语言翻译；无独立拨号区号的地区显示“不适用”。这不是电话号码输入或电话号码格式校验组件。

两项设置保存在 `wpok_forms_settings`，互相独立；已有站点升级无需重新激活，原有 referrer 设置保留，新字段开关默认为关闭。已有表单使用国家/区号字段期间，请保持该功能开启。

### 🗂️ Planned Modules

| Module | Status |
|---|---|
| **Page Cache** | Coming in a future release |
| **CSS & JS Optimization** | Scafolded for development |
| **Database Maintenance** | Planned for a later phase |

---

## 🚦 How It Works

1. **Install & activate** — WP OptiKit immediately begins converting newly uploaded images to WebP.
2. **Configure** — Set your preferred output quality and source formats from the dedicated admin panel.
3. **Scan & batch** — Run the built-in scanner to find unconverted images in your existing Media Library.
4. **Queue & relax** — Large batch jobs process in the background. Monitor progress from the admin dashboard and let the queue handle the rest.

---

## 📦 Installation

### From WordPress Admin
1. Download the latest release from [GitHub Releases](https://github.com/AzzDev/wp-optikit/releases).
2. Go to **Plugins → Add New → Upload Plugin**.
3. Choose the downloaded `.zip` file and click **Install Now**.
4. Activate the plugin.

### Manual
1. Extract the plugin package.
2. Upload the `wp-optikit` folder to `/wp-content/plugins/`.
3. Activate the plugin from the **Plugins** menu in WordPress.

### Requirements
- PHP 8.1 or later
- WordPress 6.7 or later

---

## 后台界面

所有标签页按“分组标题与描述 → 卡片”组织，沿用统一的控件和卡片样式。屏幕宽度大于 1100px 显示三列，721–1100px 显示两列，720px 及以下显示一列；不足一行时保留空位，同一行卡片等高、不同行高度独立自适应；任务表格在卡片内部横向滚动。

首页展示系统、PHP 扩展和 WordPress 环境信息。图片页依次提供设置、工具和任务记录：转换设置与 Elementor 联动共用保存按钮，三个扫描或转换工具独立成卡片。表单页按 Elementor Form 分类保留安全与功能卡片。Cache、Assets 和 Database 仍为尚未开放的规划模块。卡片小标题旁的问号用于查看独立介绍，支持悬停、键盘聚焦和点击；设置项说明和依赖提示仍直接显示。详细维护规则见 AGENTS.md。

## 🎛️ Configuration

Navigate to **Settings → WP OptiKit** to access the admin dashboard.

### Image Settings
| Setting | Default | Description |
|---|---|---|
| **Enable Image Module** | On | Toggle automatic WebP conversion on upload |
| **Output Format** | WebP | Target format for all conversions |
| **Source Formats** | JPG, PNG, GIF | Which image types to convert |
| **Quality** | 80 | WebP compression quality (1–100) |
| **Source Files** | Replace on success | Original source-format files are removed only after WebP conversion and metadata updates complete |

---

## 🔄 Auto-Updates

WP OptiKit integrates with GitHub Releases for seamless plugin updates. When a new version is published, eligible sites will see the update notification on their Plugins screen — no manual re-downloading required.

---

## 🤝 Contributing

Contributions, bug reports, and feature requests are welcome! Please open an [issue](https://github.com/AzzDev/wp-optikit/issues) or submit a pull request.

---

## 📄 License

GPL-2.0-or-later. See [LICENSE](LICENSE) for details.

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

### 表单校验

后台 **OptiKit → 表单** 提供“拦截 referrer 为空的提交”开关，默认关闭。启用并保存后，校验全站 Elementor Pro 表单提交数据中的 `referrer` 字段；字段缺失、空字符串、仅含空白或非字符串时拒绝提交，显示表单整体错误“提交已被拒绝。”。

该功能检查表单提交字段，不校验 URL、来源域名或 HTTP `Referer` 请求头。Elementor Pro 未启用时不执行校验，设置页仍可访问；该开关不作用于其他表单插件。

新增设置保存在 `wpok_forms_settings`，已有站点更新插件后无需重新激活，首次使用仍默认关闭。后续表单功能可在同一标签页增加独立设置和处理逻辑。

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

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
| **Re-compress Oversized** | Find images above your configured size threshold and re-compress them to reduce file sizes. |
| **Adjustable Quality** | Fine-tune output quality from 1–100 to balance visual fidelity vs. file size. |
| **Replace Source Files** | Successful conversions replace the original source-format file with the generated WebP file. |
| **Background Queue** | All batch jobs run in a background queue with real-time progress tracking — no browser blocking. |
| **Elementor Integration** | Automatically clears the Elementor CSS cache after batch image jobs complete, so your builder-built site stays fast. |

### 🗂️ Planned Modules

| Module | Status |
|---|---|
| **Page Cache** | Coming in a future release |
| **CSS & JS Optimization** | Scafolded for development |
| **Database Maintenance** | Planned for a later phase |

---

## 🚦 How It Works

1. **Install & activate** — WP OptiKit immediately begins converting newly uploaded images to WebP.
2. **Configure** — Set your preferred output quality, max file size threshold, and source formats from the dedicated admin panel.
3. **Scan & batch** — Run the built-in scanner to find unconverted or oversized images in your existing Media Library.
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
| **Max File Size** | 512 KB | Threshold for "oversized" image detection |
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

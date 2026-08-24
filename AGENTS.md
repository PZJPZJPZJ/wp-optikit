# WP OptiKit  AI 开发指南

> 本文件供参与 WP OptiKit 开发的 AI 与贡献者使用。它分为“协作偏好”和“当前项目事实”两部分：偏好用于约束工作方式，不代表必须重构成某种架构；项目结构必须以仓库中的真实代码为准，不得照搬其他项目。

## 项目定位与运行要求

WP OptiKit 是 WordPress 性能优化插件。当前主要能力是上传时自动转换 WebP、批量转换媒体库图片、后台任务队列、Elementor 缓存清理，以及孤立媒体和缺失媒体记录扫描与清理。

- WordPress：6.7+
- PHP：8.1+
- Text domain：`wp-optikit`
- 插件入口：`wp-optikit.php`
- 当前版本号的唯一运行时来源：插件主文件头部 `Version`

README 中标记为 Planned、Coming Soon 或 Scafolded 的 Cache、Assets、Database 功能不是已实现能力。禁止把规划项描述为可用功能，也不要为了规划项提前创建空目录、空类或转发层。

##  AI 的工作偏好

- 先检查现有代码、调用链、工作区状态和相关文档，再设计或修改；不要凭文件名猜测实现。
- 优先完成用户要求的最小闭环，避免无关重构、依赖升级、目录搬迁和命名清洗。
- 工作区可能存在用户尚未提交的修改。必须保留无关改动；遇到重叠修改时先理解差异，禁止覆盖或回滚用户内容。
- 诊断、审查和解释请求默认只读；只有用户明确要求修改、删除或新增时才写入代码。
- 不为“看起来更完整”引入抽象。至少有真实复用、清晰边界或当前需求时，才增加 Contract、Service 或新层级。
- 优先使用现有 Container、ModuleInterface、JobHandlerInterface、AdminPageRegistry 和队列设施，不另建平行框架。
- 源代码标识符和新增代码注释优先使用英文；项目说明文档使用中文；用户可见文本必须通过 WordPress i18n 函数输出。
- PHP 代码保持当前项目风格：`WPOptiKit` namespace、PascalCase 类名、camelCase 方法名、构造函数属性提升、严格的参数和返回类型。不要套用其他 WordPress 项目的全局类前缀或 snake_case 方法风格。
- 前端继续维护原生 JavaScript 和 CSS，不为普通后台功能引入 webpack、Vite 或新的前端框架。
- 错误必须返回稳定、可诊断的信息。不要静默吞掉会造成文件、数据库或任务状态不一致的异常。
- 删除公开入口、任务类型、持久化字段或文件时，应同时检查 PHP、JavaScript、模板、REST、队列、README、翻译和版本影响。

## 当前目录与职责

```text
wp-optikit.php                     # 最小插件入口、版本、常量、激活与启动
includes/
├── core/
│   ├── admin/                     # 设置页、资源加载、标签页注册
│   ├── contracts/                 # Module 与 Job Handler 契约
│   ├── queue/                     # Job 注册、存储、领取、重试、取消与执行
│   ├── rest/                      # 通用 Job REST Controller
│   ├── storage/                   # Options 与激活时 Schema
│   ├── support/                   # 小型通用支持类
│   ├── updater/                   # GitHub Release 更新器
│   ├── autoloader.php             # WPOptiKit namespace 自动加载
│   ├── bootstrap.php              # 兼容性检查与启动入口
│   ├── compatibility.php          # PHP/WordPress 最低版本
│   ├── container.php              # 服务容器
│   └── plugin.php                 # 服务装配、模块注册与启动
└── modules/
    ├── image/                     # WebP 设置、扫描、转换、任务与 Elementor 缓存桥接
    ├── media-orphan/              # 孤立文件、缺失文件扫描及清理 REST
    └── support/                   # 规划模块的后台占位实现
assets/
├── css/admin.css                  # 后台样式
└── js/                            # 后台核心与图片模块原生 JavaScript
templates/                         # 后台布局和标签页；只负责展示准备好的数据
languages/                         # zh_CN PO/MO 与翻译编译脚本
.github/workflows/release.yml      # 推送 v* Tag 时创建 GitHub Release
```

不存在正式自动化测试目录。新增目录必须有正在使用的实现，不创建空架构。

## 当前装配与运行链路

启动链路：

```text
wp-optikit.php
  → Bootstrap::boot()
  → Plugin 构造并注册服务、模块
  → QueueRunner / JobsController 启动
  → MediaOrphan REST Controllers 启动
  → 各 Module 注册 Hook、REST 和后台标签页
```

图片上传转换链路：

```text
wp_handle_upload
  → ImageProcessor::handleUpload()
  → JPG/PNG/GIF 按设置转换为 WebP
  → wp_generate_attachment_metadata
  → ImageProcessor::handleAttachmentMetadata()
  → 转换成功并完成附件元数据后清理旧上传源文件
```

批量转换链路：

```text
POST /wp-optikit/v1/images/scans/non-webp
  → ImageScanner 返回候选附件
POST /wp-optikit/v1/jobs (job_type=image_convert)
  → JobRepository 创建 Job 与 Items
  → QueueRunner 通过 wpok_process_queue 逐项执行
  → ImageJobHandler
  → ImageProcessor::convertAttachmentToWebp()
  → ImageJobFinalizer 按设置清理 Elementor 缓存
```

当前 ImageJobHandler 只支持 `image_convert`。不得重新假设已经删除的重新压缩或缺失缩略图任务仍然存在。

媒体清理链路：

- `/wp-optikit/v1/media-orphan/scan` 与 `/delete`：扫描 uploads 年份目录中未被附件标准元数据记录的图片并永久删除选中项。
- `/wp-optikit/v1/media-missing/scan` 与 `/delete`：扫描附件主文件、尺寸文件和 `original_image` 的缺失情况，清理附件或缺失元数据。
- 所有清理端点要求 `manage_options`；单项操作还会检查对应的编辑或删除权限。

媒体删除属于高风险操作。修改时必须验证真实路径位于 uploads 内、删除前重新确认引用、避免只依据客户端提交路径，并考虑文章内容、页面构建器、自定义字段、附件备份尺寸和第三方插件可能存在的引用。

## 数据所有权

激活时由 `SchemaManager` 创建：

- `{$wpdb->prefix}wpok_jobs`：队列任务状态与 Payload。
- `{$wpdb->prefix}wpok_job_items`：任务单项、附件 ID、执行状态、尝试次数与结果。

当前 Options：

- `wpok_core_settings`
- `wpok_image_settings`
- `wpok_cache_settings`（规划状态）
- `wpok_assets_settings`（规划状态）
- `wpok_database_settings`（规划状态）

队列表只能由 Queue/Storage 相关类直接操作。模板和 JavaScript 不包含 SQL；模块通过 Job Handler 与 Repository 公开能力接入队列。

Schema 修改必须幂等，并考虑已安装站点升级，而不只是新激活。当前项目没有独立 migration 框架，不要假设仅修改激活 SQL 就能更新已有站点。

## 图片与文件安全约束

- 生成文件、更新附件记录、更新元数据和删除旧文件必须按可恢复顺序执行；任何失败都不能让数据库指向已删除文件。
- 删除前保护当前主文件、新生成文件、WordPress 尺寸文件及 `original_image`。
- 从附件元数据得到的绝对路径、相对路径和包含 `..` 的路径都必须做 uploads 边界验证。
- 批量替换图片扩展名时，必须考虑文章正文、区块、Elementor、CSS、自定义字段、CDN 和缓存中保存的旧 URL；清缓存不能替代 URL 迁移。
- 转换失败时保留原文件和原元数据，并把任务标记为失败或可重试。
- 不使用 `@unlink`、`@rename` 等错误抑制作为唯一错误处理；需要检查结果并提供可见错误。

## REST、权限与输入

- 后台 REST 路由统一位于 `wp-optikit/v1` namespace。
- 管理操作至少要求 `manage_options`，涉及附件时继续检查对象级 capability。
- 客户端提交的附件 ID、文件路径、任务类型和数组结构都不可信，必须在服务端重新校验。
- Job 类型由已注册的 JobHandler 白名单决定，不能由请求动态调用任意方法。
- 对文件删除、清空记录等不可逆操作保留明确确认，并在服务端再次验证当前状态。

## 翻译与前端资源

- 所有新增用户可见字符串使用 `__()`、`esc_html__()`、`esc_html_e()` 等函数和 `wp-optikit` text domain。
- 修改可见字符串后同步维护 `languages/wp-optikit-zh_CN.po`，并在 `languages/` 目录执行 `npm run build` 生成 `.mo`。
- `languages/update-translations.js` 只会规范化现有 PO 并编译 MO，不会自动从 PHP/JS 提取或删除词条；词条增删需要人工确认。
- 管理页面传给 JavaScript 的文本集中在 `AdminController` 本地化对象中，避免在 JavaScript 内新增不可翻译的正式 UI 文案。

## Git 与发布

- 不回滚、不覆盖用户已有修改，不使用 `git reset --hard` 或 `git checkout --` 清理工作区。
- 提交只包含当前任务相关文件；不要顺手格式化整个仓库。
- `.github/workflows/release.yml` 会在推送 `v*` Tag 时创建 GitHub Release。
- Release Tag、插件主文件版本和翻译元数据版本必须一致。
- GitHubUpdater 依赖插件头的 `Update URI` 解析仓库；如果该字段不存在，不得声称自动更新链路已经实际生效。

## 版本号规则

版本号固定为 `重大更新版本号.正式版版本号.测试版本号`，即 `X.Y.Z`。

- `X`：重大更新版本号。仅用于重大架构、核心数据模型、兼容性或公开产品边界升级。递增后 `Y`、`Z` 归零。
- `Y`：正式版版本号。功能完成并通过回归和发布验收后递增。递增后 `Z` 归零。
- `Z`：测试版本号。每完成一个能够安装、运行并交付测试的构建时递增。

权限规则：

-  AI 可以在确认当前构建可运行并完成必要检查后，自行递增 `Z`。
-  AI 不得自行递增或修改 `X`、`Y`。任何重大版本或正式版位数变更，都必须先获得用户明确同意。
- 用户没有授权 `X` 或 `Y` 变更时，即使存在 Breaking Change，也只能说明建议版本与原因，不能代替用户决定。
- 单纯文档修改、未完成中间状态、不可运行结果不递增任何版本位。
- 不得为了凑版本跳号、回退版本或把 `X`/`Y` 的变化伪装成 `Z` 变化。

同步规则：

- 版本变化必须更新 `wp-optikit.php` 的插件头 `Version`。
- 同步更新 `languages/wp-optikit-zh_CN.po` 的 `Project-Id-Version`，然后重新编译 MO。
- 创建 Release 时 Tag 使用同一版本，例如插件 `2.2.1` 对应 `v2.2.1`。
- 交付说明必须写明实际版本和本次递增的是哪一位。

## 最低验证要求

完成代码修改后，至少执行与变更相关的检查：

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
node --check assets/js/core.js
node --check assets/js/image-module.js
git diff --check
```

如果修改翻译：

```powershell
Set-Location languages
npm run build
```

还应使用 `rg` 搜索被删除或改名能力的残留引用，并检查 PHP、JavaScript、模板、README、PO/MO 与任务类型是否一致。涉及数据库、WordPress Hook、REST、图像处理或真实文件删除时，仅通过语法检查不等于功能验证；应补充相应 WordPress 环境中的集成或手工回归结果。无法执行的检查必须在交付中明确说明。

## 禁止的反模式

- 禁止照搬其他项目的目录、类前缀、模块名称或未来规划。
- 禁止创建 `helpers.php`、`utils.php` 或 `functions.php` 作为无边界代码集合。
- 禁止 Controller、模板或 JavaScript 直接执行 SQL。
- 禁止一个类同时承担 Hook 注册、页面渲染、数据库访问、图片转换和文件删除等多类职责。
- 禁止只更新后台界面而保留可被 REST 调用的已删除功能，或只删除 PHP 而留下 JavaScript、翻译和文档入口。
- 禁止把缓存清理当成数据库 URL 迁移。
- 禁止仅凭“不在附件元数据中”认定文件一定未被使用。
- 禁止静默忽略数据库更新、文件写入、重命名和删除失败。
- 禁止把规划功能、未验证代码或语法通过描述为“已完成并可安全上线”。

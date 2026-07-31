# DarkMode

DarkMode 为 Typecho 1.3 后台提供简单、响应式的夜间模式。它默认跟随设备外观，也允许在每个浏览器中单独选择浅色或深色。

## 功能

- 跟随设备、浅色、深色三种模式
- 浏览器本地记忆手动选择
- 覆盖登录、注册、内容管理、编辑器、设置及常见插件后台页面
- 适配 Typecho 原生移动端布局
- 不修改 Typecho 核心、数据库或博客前台样式
- 支持系统主题实时变化和 `prefers-reduced-motion`

## 兼容性

- Typecho 1.3.0 及以上
- PHP 8.2 及以上
- 支持 `prefers-color-scheme` 的现代浏览器

## 安装

1. 下载本项目并将目录重命名为 `DarkMode`。
2. 将目录放入 Typecho 的 `usr/plugins/`。
3. 在 Typecho 后台的“控制台 > 插件”中启用 DarkMode。

启用后，后台导航会显示“外观”选择控件。登录和注册页面的控件位于页面右上角。

## 外观模式

- **跟随设备**：根据设备的浅色或深色设置自动切换，并实时响应系统变化。
- **浅色**：始终使用 Typecho 原生浅色后台。
- **深色**：始终使用 DarkMode 夜间配色。

选择保存在当前浏览器的 `localStorage` 中，键名为 `typecho-admin-theme`。插件不会将偏好写入数据库，也不会在不同设备之间同步。

## 卸载

在插件管理页面停用并删除 `usr/plugins/DarkMode` 即可。停用后插件不再加载任何资源，Typecho 后台恢复原生样式。

如需清除浏览器中保留的偏好，可删除站点本地存储中的 `typecho-admin-theme`。

## 开发与测试

本项目根目录就是插件源码。测试时应将整个目录复制到测试站的 `usr/plugins/DarkMode`，不要直接修改测试副本。

## License

DarkMode is licensed under the GNU General Public License v2.0 or later.

<?php

namespace TypechoPlugin\DarkMode;

use Typecho\Common;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Hidden;
use Typecho\Widget\Helper\Form\Element\Submit;
use Utils\Helper;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 为 Typecho 后台提供跟随设备并可手动切换的夜间模式。
 *
 * @package DarkMode
 * @author mikusa
 * @link https://www.himiku.com/
 * @version 1.1.1
 * @since 1.3.0
 */
class Plugin implements PluginInterface
{
    private const VERSION = '1.1.1';
    private const UPDATE_ACTION = 'dark-mode-update';

    public static function activate()
    {
        Helper::addAction(self::UPDATE_ACTION, Action::class);
        \Typecho\Plugin::factory('admin/header.php')->header = __CLASS__ . '::renderHeader';
        \Typecho\Plugin::factory('admin/footer.php')->end = __CLASS__ . '::renderFooter';

        return _t('DarkMode 已启用，后台外观默认跟随设备设置。');
    }

    public static function deactivate()
    {
        Helper::removeAction(self::UPDATE_ACTION);
    }

    public static function config(Form $form)
    {
        $form->addInput(new Hidden('updateSource', null, 'github'));

        $update = new Submit(null, null, _t('检查并更新插件'));
        $update->input->setAttribute('class', 'btn');
        $update->input->setAttribute(
            'formaction',
            Helper::security()->getIndex('/action/' . self::UPDATE_ACTION)
        );
        $update->input->setAttribute('formmethod', Form::POST_METHOD);
        $update->input->setAttribute('formtarget', '_blank');
        $update->description(_t('从 DarkMode 的 GitHub 仓库校验并下载最新文件。更新前建议备份插件目录。'));
        $form->addItem($update);
    }

    public static function personalConfig(Form $form)
    {
    }

    public static function renderHeader(string $header): string
    {
        $cssUrl = self::assetUrl('assets/dark-mode.css');

        return $header . "\n" . <<<'HTML'
<script>
(function () {
    var key = 'typecho-admin-theme';
    var allowed = ['system', 'light', 'dark'];
    var mode = 'system';

    try {
        var stored = window.localStorage.getItem(key);
        if (allowed.indexOf(stored) !== -1) {
            mode = stored;
        }
    } catch (error) {
        mode = 'system';
    }

    var systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    var theme = mode === 'dark' || (mode === 'system' && systemDark) ? 'dark' : 'light';

    document.documentElement.setAttribute('data-typecho-theme-mode', mode);
    document.documentElement.setAttribute('data-typecho-theme', theme);
    document.documentElement.style.colorScheme = theme;
})();
</script>
HTML
            . '<link rel="stylesheet" href="' . $cssUrl . '">';
    }

    public static function renderFooter(): void
    {
        echo '<script src="' . self::assetUrl('assets/dark-mode.js') . '"></script>';
    }

    private static function assetUrl(string $path): string
    {
        $url = Common::url('DarkMode/' . ltrim($path, '/'), Options::alloc()->pluginUrl);

        return htmlspecialchars($url . '?v=' . self::VERSION, ENT_QUOTES, 'UTF-8');
    }
}

<?php

namespace TypechoPlugin\DarkMode;

use RuntimeException;
use Throwable;
use Typecho\Widget;
use Widget\ActionInterface;
use Widget\Security;
use Widget\User;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Action extends Widget implements ActionInterface
{
    private const MANIFEST_URL =
        'https://raw.githubusercontent.com/mikusaa/Typecho-Plugin-DarkMode/main/shasum.txt';
    private const FILE_BASE_URL =
        'https://raw.githubusercontent.com/mikusaa/Typecho-Plugin-DarkMode/main/';
    private const MAX_MANIFEST_BYTES = 32768;
    private const MAX_FILE_BYTES = 2097152;
    private const RELEASE_FILES = [
        'Action.php',
        'LICENSE',
        'Plugin.php',
        'README.md',
        'assets/dark-mode.css',
        'assets/dark-mode.js',
    ];

    public function action(): void
    {
        header('Content-Type: text/plain; charset=UTF-8');

        if (!$this->request->isPost()) {
            $this->fail('更新请求必须使用 POST。', 405);
        }

        $user = User::alloc();
        if (!$user->hasLogin() || !$user->pass('administrator', true)) {
            $this->fail('仅管理员可以更新插件。', 403);
        }

        Security::alloc()->protect();

        try {
            $this->updatePlugin();
        } catch (Throwable $error) {
            $this->fail('更新失败：' . $error->getMessage(), 500);
        }

        exit;
    }

    private function updatePlugin(): void
    {
        echo "DarkMode 插件更新\n";
        echo "正在获取远端文件清单...\n\n";

        $manifest = $this->download(self::MANIFEST_URL, self::MAX_MANIFEST_BYTES);
        $entries = $this->parseManifest($manifest);
        $staged = [];

        try {
            foreach ($entries as $path => $expectedHash) {
                $target = __DIR__ . '/' . $path;

                if (is_link($target)) {
                    throw new RuntimeException('拒绝更新符号链接：' . $path);
                }

                $localHash = is_file($target) ? @hash_file('sha256', $target) : false;
                if (is_string($localHash) && hash_equals($expectedHash, $localHash)) {
                    echo '无需更新  ' . $path . "\n";
                    continue;
                }

                $payload = $this->download($this->fileUrl($path), self::MAX_FILE_BYTES);
                $actualHash = hash('sha256', $payload);
                if (!hash_equals($expectedHash, $actualHash)) {
                    throw new RuntimeException('下载文件校验失败：' . $path);
                }

                $staged[$path] = $this->stageFile($target, $payload);
                echo '准备更新  ' . $path . "\n";
            }

            if (empty($staged)) {
                echo "\n当前已是最新版本。\n";
                return;
            }

            $this->replaceFiles($staged);
        } finally {
            foreach ($staged as $item) {
                if (isset($item['temporary']) && is_file($item['temporary'])) {
                    @unlink($item['temporary']);
                }
            }
        }

        echo "\n更新完成，共替换 " . count($staged) . " 个文件。\n";
        echo "请返回后台停用并重新启用 DarkMode，以完成插件升级。\n";
    }

    /**
     * @return array<string, string>
     */
    private function parseManifest(string $manifest): array
    {
        $entries = [];
        $allowed = array_fill_keys(self::RELEASE_FILES, true);
        $lines = preg_split('/\r\n|\r|\n/', trim($manifest));

        if ($lines === false || $lines === ['']) {
            throw new RuntimeException('远端文件清单为空。');
        }

        foreach ($lines as $line) {
            if (!preg_match('/\A([a-f0-9]{64})  \.\/([A-Za-z0-9][A-Za-z0-9._\/-]*)\z/', $line, $matches)) {
                throw new RuntimeException('远端文件清单格式无效。');
            }

            $path = $matches[2];
            if (!isset($allowed[$path]) || isset($entries[$path])) {
                throw new RuntimeException('远端文件清单包含无效路径：' . $path);
            }

            $entries[$path] = $matches[1];
        }

        $missing = array_diff(self::RELEASE_FILES, array_keys($entries));
        if (!empty($missing)) {
            throw new RuntimeException('远端文件清单不完整：' . implode(', ', $missing));
        }

        return $entries;
    }

    /**
     * @return array{target: string, temporary: string, mode: int}
     */
    private function stageFile(string $target, string $payload): array
    {
        $directory = dirname($target);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('插件目录不可写：' . basename($directory));
        }

        $temporary = @tempnam($directory, '.dark-mode-update-');
        if ($temporary === false) {
            throw new RuntimeException('无法创建更新临时文件。');
        }

        $written = @file_put_contents($temporary, $payload, LOCK_EX);
        if ($written === false || $written !== strlen($payload)) {
            @unlink($temporary);
            throw new RuntimeException('无法写入更新临时文件。');
        }

        $permissions = is_file($target) ? fileperms($target) : false;
        $mode = is_int($permissions) ? ($permissions & 0777) : 0644;
        @chmod($temporary, $mode);

        return [
            'target' => $target,
            'temporary' => $temporary,
            'mode' => $mode,
        ];
    }

    /**
     * @param array<string, array{target: string, temporary: string, mode: int}> $staged
     */
    private function replaceFiles(array &$staged): void
    {
        $backups = [];
        $applied = [];

        try {
            foreach ($staged as $path => &$item) {
                $target = $item['target'];
                $backup = null;

                if (is_file($target)) {
                    $backup = @tempnam(dirname($target), '.dark-mode-backup-');
                    if ($backup === false) {
                        throw new RuntimeException('无法备份文件：' . $path);
                    }
                    if (!@copy($target, $backup)) {
                        @unlink($backup);
                        throw new RuntimeException('无法备份文件：' . $path);
                    }
                    @chmod($backup, $item['mode']);
                }

                $backups[$path] = $backup;
                if (!@rename($item['temporary'], $target)) {
                    throw new RuntimeException('无法替换文件：' . $path);
                }

                $item['temporary'] = '';
                $applied[] = $path;
                echo '已更新    ' . $path . "\n";
            }
            unset($item);
        } catch (Throwable $error) {
            foreach (array_reverse($applied) as $path) {
                $backup = $backups[$path] ?? null;
                $target = $staged[$path]['target'];

                if ($backup !== null && is_file($backup)) {
                    @copy($backup, $target);
                    @chmod($target, $staged[$path]['mode']);
                } else {
                    @unlink($target);
                }
            }

            throw new RuntimeException('文件替换已回滚。' . $error->getMessage(), 0, $error);
        } finally {
            foreach ($backups as $backup) {
                if ($backup !== null && is_file($backup)) {
                    @unlink($backup);
                }
            }
        }
    }

    private function download(string $url, int $maximumBytes): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('服务器缺少 PHP cURL 扩展。');
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('无法初始化下载请求。');
        }

        curl_setopt_array($handle, [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FAILONERROR => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Typecho-DarkMode-Updater/1.1',
        ]);

        $payload = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if (!is_string($payload)) {
            throw new RuntimeException('下载失败：' . ($error !== '' ? $error : '未知错误'));
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('下载失败，HTTP 状态码：' . $status);
        }
        if (strlen($payload) > $maximumBytes) {
            throw new RuntimeException('远端文件超过允许大小。');
        }

        return $payload;
    }

    private function fileUrl(string $path): string
    {
        $segments = array_map('rawurlencode', explode('/', $path));

        return self::FILE_BASE_URL . implode('/', $segments);
    }

    private function fail(string $message, int $status): void
    {
        $this->response->setStatus($status);
        echo $message . "\n";
        exit;
    }
}

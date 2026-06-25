<?php

namespace WPOptiKit\Core\Updater;

use WPOptiKit\Core\Compatibility;

final class GithubUpdater
{
    public function __construct(private readonly string $pluginFile)
    {
    }

    public function boot(): void
    {
        if (!is_admin()) {
            return;
        }

        add_filter('update_plugins_github.com', array($this, 'checkUpdate'), 10, 4);
        add_filter('update_plugins_api.github.com', array($this, 'checkUpdate'), 10, 4);
        add_filter('plugins_api', array($this, 'pluginInfo'), 10, 3);
        add_filter('upgrader_source_selection', array($this, 'normalizeGithubZipFolder'), 10, 4);
    }

    public function checkUpdate($update, array $pluginData, string $pluginFile, array $locales): mixed
    {
        if (plugin_basename($this->pluginFile) !== $pluginFile) {
            return $update;
        }

        $release = $this->fetchReleaseData();

        if ($release === null) {
            return $update;
        }

        return array(
            'slug'         => dirname(plugin_basename($this->pluginFile)),
            'version'      => $release['version'],
            'url'          => $release['url'],
            'package'      => $release['download_url'],
            'requires_php' => Compatibility::MIN_PHP,
            'requires'     => Compatibility::MIN_WP,
            'autoupdate'   => true,
        );
    }

    public function pluginInfo($result, string $action, $args): mixed
    {
        if ($action !== 'plugin_information' || !is_object($args) || empty($args->slug) || dirname(plugin_basename($this->pluginFile)) !== $args->slug) {
            return $result;
        }

        $release = $this->fetchReleaseData();

        if ($release === null) {
            return $result;
        }

        return (object) array(
            'name'          => 'WP OptiKit',
            'slug'          => dirname(plugin_basename($this->pluginFile)),
            'version'       => $release['version'],
            'author'        => 'AzzDev',
            'homepage'      => $release['url'],
            'requires_php'  => Compatibility::MIN_PHP,
            'requires'      => Compatibility::MIN_WP,
            'download_link' => $release['download_url'],
            'sections'      => array(
                'description' => '<p>Managed via GitHub Releases.</p>',
                'changelog'   => '<div>' . $release['changelog'] . '</div>',
            ),
        );
    }

    public function normalizeGithubZipFolder($source, $remoteSource, $upgrader, $hookExtra = null): mixed
    {
        $pluginBase = plugin_basename($this->pluginFile);
        $slug       = dirname($pluginBase);
        $isMatch    = false;

        if (isset($hookExtra['plugin']) && $hookExtra['plugin'] === $pluginBase) {
            $isMatch = true;
        } elseif (isset($_GET['plugin']) && strpos((string) $_GET['plugin'], $slug) !== false) {
            $isMatch = true;
        }

        if (!$isMatch) {
            return $source;
        }

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        WP_Filesystem();
        global $wp_filesystem;

        if (!$wp_filesystem) {
            return $source;
        }

        $corrected = trailingslashit($remoteSource) . $slug . '/';

        if ($source !== $corrected) {
            $wp_filesystem->move($source, $corrected, true);
            return $corrected;
        }

        return $source;
    }

    private function fetchReleaseData(): ?array
    {
        $repo = $this->repositoryFromHeader();

        if ($repo === '') {
            return null;
        }

        $cacheKey   = 'wpok_github_release_' . md5($repo);
        $backoffKey = $cacheKey . '_backoff';
        $cached     = get_transient($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        if (get_transient($backoffKey)) {
            return null;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . $repo . '/releases/latest',
            array(
                'timeout'    => 15,
                'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
            )
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            set_transient($backoffKey, 1, HOUR_IN_SECONDS);
            return null;
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if (!is_array($data) || empty($data['tag_name'])) {
            set_transient($backoffKey, 1, HOUR_IN_SECONDS);
            return null;
        }

        $release = array(
            'version'      => ltrim((string) $data['tag_name'], 'v'),
            'download_url' => (string) ($data['zipball_url'] ?? ''),
            'url'          => (string) ($data['html_url'] ?? ''),
            'changelog'    => !empty($data['body']) ? nl2br(esc_html((string) $data['body'])) : 'No changelog provided.',
        );

        set_transient($cacheKey, $release, HOUR_IN_SECONDS);

        return $release;
    }

    private function repositoryFromHeader(): string
    {
        $headers = get_file_data(
            $this->pluginFile,
            array(
                'UpdateURI' => 'Update URI',
            )
        );

        $updateUri = trim((string) ($headers['UpdateURI'] ?? ''));

        if ($updateUri === '') {
            return '';
        }

        $patterns = array(
            'https://api.github.com/repos/',
            'https://github.com/',
            'http://api.github.com/repos/',
            'http://github.com/',
        );

        $repo = trim(str_replace($patterns, '', $updateUri), '/');

        return substr_count($repo, '/') >= 1 ? $repo : '';
    }
}

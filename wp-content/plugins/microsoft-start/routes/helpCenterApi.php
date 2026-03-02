<?php
// © Microsoft Corporation. All rights reserved.
namespace microsoft_start\routes;

require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');

use microsoft_start\infrastructure\ApiController;
use microsoft_start\services\MSNClient;
use microsoft_start\infrastructure\Util;

function get_actived_plugins()
{
    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $origin_plugins = array_filter(get_plugins(), function ($key) {
        return $key !== 'microsoft-start/index.php' && is_plugin_active($key);
    }, ARRAY_FILTER_USE_KEY);

    $plugins = array_reduce(array_keys($origin_plugins), function ($plugins, $key) use ($origin_plugins) {
        $path   = explode('/', $key);
        $folder = current($path);
        $folder = preg_replace('/[\-0-9\.]+$/', '', $folder);
        $plugins[$folder] = $origin_plugins[$key]['Name'];
        return $plugins;
    });

    return $plugins;
}
class helpCenterApi extends ApiController
{
    function register_routes()
    {
        register_rest_route('microsoft/v1', '/health-check', [
            'methods' => 'GET',
            'permission_callback' => function () {
                return current_user_can('activate_plugins');
            },
            'callback' => function () {
                $latest_version = Util::get_latest_plugin_version();
                $editor_mode = 'block';
                if (is_plugin_active('classic-editor/classic-editor.php')) {
                    $editor_mode = get_option('classic-editor-replace') == 'block' ? 'block' : 'classic';
                }

                $plugin_list = get_actived_plugins();

                $request_body = array(
                    'wpVersion' => MSPH_WP_VERSION,
                    'editorMode' => $editor_mode,
                    'pluginVersion' => MSPH_PLUGIN_VERSION,
                    'thirdPluginList' => array_keys($plugin_list)
                );

                $res_data = MSNClient::get_health_check_data($request_body);

                $third_plugin_list = $res_data['thirdPluginList'];
                $third_plugin_name_list = (object)array();
                foreach ($third_plugin_list as $key => $value) {
                    $third_plugin_name_list -> {$plugin_list[$key]} = $value;
                }
                $res_data['thirdPluginList'] = $third_plugin_name_list;

                return array(
                    'status' => $res_data,
                    'version' => array(
                        'latestPluginVersion' => $latest_version,
                        'currentPluginVersion' => MSPH_PLUGIN_VERSION,
                        'currentWpVersion' => MSPH_WP_VERSION
                    )
                );
            }
        ]);

        register_rest_route('microsoft/v1', '/help-list', [
            'methods' => 'GET',
            'permission_callback' => function () {
                return current_user_can('activate_plugins');
            },
            'callback' => function () {
                $res_data = MSNClient::get_help_faq_data();
                $version = $res_data['version'];
                $content = json_decode($res_data['content']);
                return [
                    'version' => $version,
                    'content' => $content
                ];
            }
        ]);
    }
}

<?php
// © Microsoft Corporation. All rights reserved.

namespace microsoft_start\routes;

use microsoft_start\services\TokenService;

use microsoft_start\infrastructure\ApiController;
use microsoft_start\services\MSNClient;
use microsoft_start\services\Options;
use microsoft_start\infrastructure\Util;

use function PHPSTORM_META\type;

class notificationApi extends ApiController
{
    function register_routes()
    {
        register_rest_route('microsoft/v1', '/notification', [
            'methods' => 'GET',
            'permission_callback' => function () {
                return current_user_can('activate_plugins');
            },
            'callback' => function ($data) {
                $type = $data['type'];
                $response = MSNClient::get_notification($type);
                return $response;
            }
        ]);

        register_rest_route('microsoft/v1', '/dismiss-wp-notification', [
            'methods' => 'POST',
            'permission_callback' => function () {
                return current_user_can('activate_plugins');
            },
            'callback' => function ($data) {
                $parameters = $data->get_json_params();
                if ($parameters['type'] === 'publish_article') {
                    Options::set_dismissed_publish_to_MSPH_notification(true);
                } else if ($parameters['type'] === 'version_update') {
                    Options::set_dismissed_notification_version(Util::get_latest_plugin_version());
                } else if ($parameters['type'] === 'batch_submit_completed') {
                    Options::set_batch_submit_completed_notification(false);
                }
            }
        ]);

        register_rest_route('microsoft/v1', '/dismiss-msph-notification', [
            'methods' => 'POST',
            'permission_callback' => function () {
                return current_user_can('activate_plugins');
            },
            'callback' => function ($data) {
                $parameters = $data->get_json_params();
                return MSNClient::dismissMSPHNotification($parameters);
            }
        ]);
        
    }
}

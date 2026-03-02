<?php
// © Microsoft Corporation. All rights reserved.

namespace microsoft_start\routes;

use microsoft_start\infrastructure\ApiController;
use microsoft_start\services\LoggerTelemetryType;
use microsoft_start\services\LogService;

class logApi extends ApiController
{
    function register_routes()
    {
        register_rest_route('microsoft/v1', '/log', [
            'methods' => 'POST',
            'permission_callback' => function () {
                return current_user_can('activate_plugins');
            },
            'callback' => function ($data) {
                $parameters = $data->get_json_params();
                $key = $parameters['key'];
                $featureSet = $parameters['featureSet'];
                $payload = $parameters['payload'];
                LogService::add_log(LoggerTelemetryType::Log, $featureSet, $key, $payload);
                return null;
            }
        ]);
    }
}

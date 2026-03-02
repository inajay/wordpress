<?php
// © Microsoft Corporation. All rights reserved.

namespace microsoft_start\services;

use microsoft_start\services\MSNClient;
use microsoft_start\services\TokenService;

class MSPostSyncService
{
    public static function sync_post($msPost, $origin)
    {
        if ($msPost && TokenService::get_token()) {
            $jsonObject = array(
                'link'     => $msPost['link'],
                'msnId'    => $msPost['id']
            );
            try {
                $response = MSNClient::update_post($msPost);
                if ($response) {
                    if ($response['response']['code'] == 200) {
                        LogService::add_log(LoggerTelemetryType::Counter, LoggerFeatureSet::ContentOnboarding, "Publish", array('origin' => $origin));
                        return array('code' => 0);
                    } else {
                        LogService::add_log(LoggerTelemetryType::ErrorCounter, LoggerFeatureSet::ContentOnboarding, "Publish", array(
                            'status' => 'error',
                            'error_code' => $response['response']['code'],
                            'origin' => $origin
                        ));
                        LogService::add_log(LoggerTelemetryType::Log, LoggerFeatureSet::ContentOnboarding, "Publish-Error", array_merge($jsonObject, array(
                            'error_message' => $response['body']
                        )));
                    }
                }
            } catch (\Exception $e) {
                // only add log here and don't block user's publishing experience
                LogService::add_log(LoggerTelemetryType::ErrorCounter, LoggerFeatureSet::ContentOnboarding, "Publish", array('status' => 'exception', 'origin' => $origin));
                LogService::add_log(LoggerTelemetryType::Log, LoggerFeatureSet::ContentOnboarding, "Publish-Excp", array_merge($jsonObject, array(
                    'exception' => $e
                )));
            }        
        }
        return array('code' => -1);
    }
}
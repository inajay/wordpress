<?php
// © Microsoft Corporation. All rights reserved.

namespace microsoft_start\services;

use microsoft_start\infrastructure\Registration;
use microsoft_start\services\MSNClient;

class LoggerTelemetryType {
    const Log = 0;
    const Counter = 1;
    const ErrorCounter = 2;
}

class LoggerFeatureSet {
    const PartnerOnboarding = "Partner Onboarding";
    const ContentOnboarding = "Content Onboarding";
    const HelpCenter = "Help Center";
}

class LogService extends Registration
{
    private static $logBaseArray = array(
        'wpVersion' => MSPH_WP_VERSION,
        "pluginVersion" => MSPH_PLUGIN_VERSION
    );

    private static $maxActionCount = array(
        LoggerTelemetryType::Log => 1000,
        LoggerTelemetryType::Counter => 500,
        LoggerTelemetryType::ErrorCounter => 500,
    );

    public function register_dependencies()
    {
        add_action( 'msphLogTask', [ $this, 'batch_send_logs' ] );
    }

    public static function add_log(/*LoggerTelemetryType*/ int $type, /*LoggerFeatureSet*/ string $featureSet, string $feature, $logFieldArray = array(), $forceSyncSendLog = false) {
        $logItem = static::compose_log($type, $featureSet, $feature, json_encode(array_merge(static::$logBaseArray, $logFieldArray)));
        $cachedLogs = Options::get_cached_user_action_logs($type);
        if ($cachedLogs == false) {
            $cachedLogs = array();
        }
        if (count($cachedLogs) >= static::$maxActionCount[$type]) {
            array_shift($cachedLogs);
        }
        $cachedLogs = array_merge($cachedLogs, array($logItem));
        Options::set_cached_user_action_logs($type, $cachedLogs);

        if ($forceSyncSendLog) {
            static::batch_send_logs($type);
        } else {
            static::schedule_logs_sending($type);
        }
    }

    public static function batch_send_logs(/*LoggerTelemetryType*/ int $type) {
        try {
            $cachedLogs = Options::get_cached_user_action_logs($type);
            if (count($cachedLogs) == 0) {
                return;
            }
            $response = MSNClient::send_logs($cachedLogs);

            if ($response && $response['response']['code'] == 200) {
                Options::set_cached_user_action_logs($type, array());
            }
        } catch (\Exception $e) {
        }
    }

    private static function compose_log(/*LoggerTelemetryType*/ int $type, /*LoggerFeatureSet*/ string $featureSet, $feature, $logMessage) {
        $logModel = array(
            'TimeStamp' => date('c', time()), // Use PascalCase since backend accepts it
            'tenant' => 'WordPress',
            'instance' => 'WordPress',
            'featureSet' => $featureSet,
            'feature' => $feature,
            'accountId' => Options::get_CID(),
            'userInfo' => Options::get_app_id(),
            'customD1' => date('c', time())
        );

        if ($type == LoggerTelemetryType::Log) {
            $logModel['logMessage'] = $logMessage;
        }

        if ($type == LoggerTelemetryType::Counter || $type == LoggerTelemetryType::ErrorCounter) {
            $logModel['count'] = 1;
            $logModel['customD2'] = $logMessage;
        }

        return [$type, $logModel];
    }
    
    private static function schedule_logs_sending(/*LoggerTelemetryType*/ int $type) {
		if ( ! wp_next_scheduled( 'msphLogTask', [$type] ) ) {
			wp_schedule_single_event( time() + 15 * MINUTE_IN_SECONDS, 'msphLogTask', [$type] );
		}
    }
}
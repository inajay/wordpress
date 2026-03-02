<?php
// © Microsoft Corporation. All rights reserved.

namespace microsoft_start\services;

use microsoft_start\infrastructure\Util;

class MSNClient
{
    static function update_post($body)
    {
        return static::BaseRequest("thirdparty/contents/article/upsert?documentId={$body['id']}&status=3", ['method' => 'POST', 'body' => $body]);
    }

    static function filter_empty_notification($item)
    {
        $signal_arr = ['Signal_423', 'Signal_424', 'Signal_425', 'Signal_426', 'Signal_427', 'Signal_454', 'Signal_508', 'Signal_482', 'Signal_428', 'Signal_429', 'Signal_430', 'Signal_431', 'Signal_432', 'Signal_483', 'Signal_433', 'Signal_455', 'Signal_456', 'Signal_474', 'Signal_475', 'Signal_489', 'Signal_582', 'Signal_583', 'Signal_653', 'Signal_654'];
        return isset($item['notificationTypeId']) ? array_search($item['notificationTypeId'], $signal_arr) : false;
    }

    static function send_logs($body)
    {
        $token = TokenService::get_token();

        $response = wp_remote_post(
            MSPH_SERVICE_URL . "telemetry?scn=3rdPartyAuth" . MSPH_OCID_APIKEY_QSP,
            [
                'headers' => [
                    "accept" => "*/*",
                    'Authorization' => "Bearer $token",
                    "accept-language" => "en-US,en;q=0.9",
                    "content-type" => "application/json",
                ],
                'method'      => 'POST',
                'body'        => json_encode($body)
            ]
        );
        if (is_wp_error($response)) {
            return null;
        }
        return $response;
    }

    static function upsert_feed($feedConfig)
    {
        $config = array('auth' => true, 'body' => $feedConfig, 'method' => 'POST', 'timeout' => 10);
        return static::BaseRequest("thirdparty/feed/upsert", $config);
    }

    static function suspend_feed()
    {
        $config = array('auth' => true, 'body' => (object)array(), 'method' => 'PUT', 'timeout' => 10);
        return static::BaseRequest("thirdparty/feed/suspend", $config);
    }

    static function account_settings($source)
    {
        if (Options::get_auth_token() == null) {
            return null;
        }
        if ($source == "set_client" || $source == "set_token") {
            // if the last request is within 60 seconds, return the cached settings
            // but if the request is come from dashboard page, we don't want to return cached settings
            $lastSettings = json_decode(Options::get_profile());
            if (isset($lastSettings->timeStamp) && (time() - $lastSettings->timeStamp < 60)) {
                return $lastSettings;
            }
        }
        LogService::add_log(LoggerTelemetryType::Log, LoggerFeatureSet::PartnerOnboarding, "request account/settings", array(
            'source' => $source
        ));
        $response = static::BaseRequest("account/settings?applicationVersion=" . MSPH_PLUGIN_VERSION);
        if (!$response || $response['response']['code'] != 200) {
            return null;
        }

        $settings = json_decode($response['body']);
        $settings->timeStamp = time();
        if ($settings) {
            Options::set_profile(json_encode($settings));
            $lifeCycleStatus = $settings->lifeCycleStatus;
            Options::set_CID($settings->accountId);
            if ($lifeCycleStatus == 0) {
                Options::set_status('active');
            } else if ($lifeCycleStatus == 3) {
                Options::set_status('pending');
            }
        }
        return $settings;
    }

    static function account_profile()
    {

        $response = static::BaseRequest("account/profile");
        if (!$response || $response['response']['code'] != 200) {
            return null;
        }

        $profile = json_decode($response['body']);
        Options::set_profile_cache(array(
            'profile' => $profile,
            'expired' => time() + HOUR_IN_SECONDS
        ));

        return $profile;
    }
    
    static function get_help_faq_data()
    {
        $config = array('auth' => false, 'method' => 'GET');
        $response = static::BaseRequest("thirdparty/helpcenter/faq?version=".MSPH_PLUGIN_VERSION."&locale=".MSPH_WP_LANG, $config);
        if (!$response || $response['response']['code'] != 200) {
            return null;
        }
        $body = json_decode($response['body'], true);
        return $body;
    }
    

    static function get_health_check_data($body)
    {
        $config = array('auth' => false, 'body' => $body, 'method' => 'POST');
        $response = static::BaseRequest("thirdparty/helpcenter/healthcheck", $config);
        if (!$response || $response['response']['code'] != 200) {
            return null;
        }
        $body = json_decode($response['body'], true);
        return $body;
    }
    
    static function get_msn_publish_status($post_ids)
    {
        $results = array();
        $limitation = 10;       // due to limitation from Http.sys registry settings for Windows.
        $msn_id_mapping = array_map(function ($post_id) {
            return array($post_id => get_post_meta($post_id, 'msn_id', true));
        }, $post_ids);
        $filtered_msn_id_mapping = array_filter($msn_id_mapping, function($item) {
            return !!array_values($item)[0];
        });
        $msn_ids = array_values(array_map(function($item) {
            return array_values($item)[0];
        }, $filtered_msn_id_mapping));
        while (!empty($msn_ids)) {
            $selectedIdListArray = array_slice($msn_ids, 0, $limitation);
            $selectedIdList = implode(",", $selectedIdListArray);
            $encodedSelectedIdList = urlencode($selectedIdList);
            $url = "thirdparty/contents/article?ids={$encodedSelectedIdList}&res=publish";
            $response = static::BaseRequest($url, ['method' => 'GET']);
            if ($response && $response['response']['code'] >= 200 && $response['response']['code'] < 300) {
                array_push($results, json_decode($response['body']));
            }
            array_splice($msn_ids, 0, $limitation);
        }
        $totalResult = call_user_func_array('array_merge', $results);
        $result_mapping = array();
        foreach($totalResult as $result) {
            $result_mapping[$result->id] = $result;
        };
        
        $final_result = array_map(function($item) use ($result_mapping) {
            $post_id = key($item);
            $msn_id = $item[$post_id];
            $is_msn_id_exists = !!$msn_id && array_key_exists($msn_id, $result_mapping);
            return array($post_id => array_merge($is_msn_id_exists ? (array)$result_mapping[$msn_id] : [], ['post_id' => $post_id]) );
        }, $msn_id_mapping);

        return $final_result;
    }

    static function get_msn_post_detail($msn_id)
    {
        $response = static::BaseRequest("thirdparty/contents/article/loadSnippet?documentId={$msn_id}&type=1");
        if (!$response || $response['response']['code'] != 200) {
            return array();
        }

        $body = json_decode($response['body'], true);
        return $body;
    }

    static function submit_appeal($msn_id, $body)
    {
        $response = static::BaseRequest("thirdparty/contents/article/appeal?documentId={$msn_id}&type=1", ['method' => 'POST', 'body' => $body, 'timeout' => 10]);
        return ($response && $response['response']['code'] == 200);
    }

    static function get_wp_notification()
    {
        // 1. MSN Partner Hub new version is available.
        // 2. You have articles which have not been published to MSN.
        $wpNotifications = array();
        $currentVersion = MSPH_PLUGIN_VERSION;
        $latestVersion = Util::get_latest_plugin_version();
        $profileFromDb = json_decode(Options::get_profile());
        if (Util::if_version_higher($latestVersion, $currentVersion)) {
            // Only show notification when there is new version and the new version's notification have not been dismissed.
            $dismissedNotificationVersion = Options::get_dismissed_notification_version();

            if (Util::if_version_higher($latestVersion, $dismissedNotificationVersion)) {
                array_push($wpNotifications, array(
                    'id' => 'wordpress_1',
                    'canDismiss' => true,
                    'severity' => "information",
                    'wpNotificationType' => 'version_update',
                    'propertyBag' => array(
                        'title' => /* translators: Notification of update plugin */ __('A new version of MSN Partner Hub is available.', 'microsoft-start'),
                        "action" => "[{\"Text\":\"" ./* translators: Update button in banner */ __("Update Now", 'microsoft-start') . "\",\"Href\":\"./plugins.php?s=microsoft-start\",\"IsExternalLink\":false}]"
                    )
                ));
            }
        }
        $accountSettings = json_decode(Options::get_profile());
        $suspended = $accountSettings->lifeCycleStatus === 1 || $accountSettings->partnerStatus === 4;
        if (!$suspended && Options::get_status() === 'active' && Options::get_publishOption() === 'editor' && !Options::get_dismissed_publish_to_MSPH_notification() && ($profileFromDb->partnerMetadata->paymentStatus->stripeAccountStatus ?? null) === 1) {
            // Only show notification when there is new article have not been publish to MSPH and the publish artile notification have not been dismissed.
            $posts = get_posts([
                'post_type' => 'post',
                'post_status' => ['publish'],
                'meta_query' => [
                    [
                        'key' => 'msn_id',
                        'compare' => 'NOT EXISTS'
                    ]
                ]
            ]);
            if (count($posts)) {
                array_push($wpNotifications, array(
                    'id' => 'wordpress_2',
                    'canDismiss' => true,
                    'severity' => "information",
                    'wpNotificationType' => 'publish_article',
                    'propertyBag' => array(
                        'title' => /* translators: Notification of publish post */ __("Some of your posts haven't been submitted to MSN yet.", 'microsoft-start'),
                        "action" => "[{\"Text\":\"" ./* translators: Batch submit button */ __("Submit all", "microsoft-start") . "\",\"Href\":\"./admin.php?page=microsoft-start#/batch-submit\",\"IsExternalLink\":false},{\"Text\":\"" ./* translators: Submit button in banner */ __("Edit and submit one by one", "microsoft-start") . "\",\"Href\":\"./edit.php\",\"IsExternalLink\":false}]"
                    )
                ));
            }
        }
        if (Options::get_status() === 'active' && Options::get_publishOption() === 'editor' && Options::get_batch_submit_completed_notification() && ($profileFromDb->partnerMetadata->paymentStatus->stripeAccountStatus ?? null) === 1) {
            array_push($wpNotifications, array(
                'id' => 'wordpress_3',
                'canDismiss' => true,
                'severity' => "information",
                'wpNotificationType' => 'batch_submit_completed',
                'propertyBag' => array(
                    'title' => /* translators: Notification of batch submit completed */ __("Batch submission completed!", 'microsoft-start'),
                    "action" => "[{\"Text\":\"" ./* translators: View details button in banner */ __("View details", "microsoft-start") . "\",\"Href\":\"./admin.php?page=microsoft-start#/batch-submit\",\"IsExternalLink\":false}]"
                )
            ));
        }
        return $wpNotifications;
    }

    static function get_notification($type)
    {
        $cid = Options::get_CID();
        $wpNotifications = static::get_wp_notification();
        $body = array('information' => array());
        if ($cid) {
            $profile = json_decode(Options::get_profile(), true);
            $partnerId = $profile['partnerId'];
            $response = static::BaseRequest(
                "notification/items?brandId="
                    . Options::get_CID() .
                    "&partnerId="
                    . $partnerId .
                    "&scenario=" . ($type === 'sidebar' ? "1" : "0")
            );
            if (!$response || $response['response']['code'] != 200) {
                Options::set_red_dot(0); // set red dot to 0 if request failed to avoid duplicated bad request
                return array(
                    'information' => $wpNotifications
                );
            }
            $body = json_decode($response['body'], true);
            $body['information'] = isset($body['information']) ? array_filter($body['information'], 'static::filter_empty_notification') : [];
            $body['critical'] = isset($body['critical']) ? array_filter($body['critical'], 'static::filter_empty_notification') : [];
            $body['warning'] = isset($body['warning']) ? array_filter($body['warning'], 'static::filter_empty_notification') : [];
        }

        $information = array_merge($body['information'], $wpNotifications);
        $body['information'] = $information;
        $type === 'sidebar' && Options::set_red_dot(array_key_exists('critical', $body) && is_array($body['critical']) ? count($body['critical']) : 0);
        return $body;
    }

    static function dismissMSPHNotification($body)
    {
        $response = static::BaseRequest(
            "notification/items",
            ['method' => 'POST', 'body' => $body]
        );
        return $response;
    }


    static function get_status_dashboard()
    {
        $profile = json_decode(Options::get_profile(), true);
        $partnerId = $profile['partnerId'];
        $cid = Options::get_CID();
        if (!$profile || !$partnerId || !$cid) {
            return null;
        }
        $response = static::BaseRequest(
            "account/statusdashboard?brandId=" . $cid . "&partnerId="
                . $partnerId,
            ['method' => 'GET']
        );
        if (!$response["body"]) {
            LogService::add_log(LoggerTelemetryType::Log, LoggerFeatureSet::PartnerOnboarding, "StatusDashboardError", array(
                'partnerId' => $partnerId,
                'accountId' => $cid,
                'response' => json_encode($response)
            ));
        }
        return $response;
    }


    /**
     * @param string $url request path.
     * @param array $config request config.
     * [
     *  method => GET | POST | PUT,
     *  body => array,
     *  auth => boolean, 
     *  header => array,
     *  timeout => number of seconds until timeout
     * ].
     */
    private static function BaseRequest($url, $config = [])
    {
        $url_arr = explode('?', $url);
        $path = $url_arr[0];
        $query_string = isset($url_arr[1]) ? $url_arr[1] : '';

        $method = isset($config['method']) ? $config['method'] : 'GET';
        $body = isset($config['body']) ? $config['body'] : null;
        $auth = isset($config['auth']) ? $config['auth'] : true;
        $timeout = isset($config['timeout']) ? $config['timeout'] : 5;
        $header = array_merge(array(
            "accept" => "*/*",
            "accept-language" => "en-US,en;q=0.9",
            "content-type" => "application/json"
        ),  isset($config['header']) ? $config['header'] : []);

        $query = $query_string ? explode('&', $query_string) : array();
        array_push($query, 'wrapodata=false');
        if ($auth) {
            array_push($query, 'scn=3rdPartyAuth');
            $header['Authorization'] = 'Bearer ' . TokenService::get_token();
        }


        $query_string = join('&', $query);
        $request_url = MSPH_SERVICE_URL . $path . '?' . $query_string . MSPH_OCID_APIKEY_QSP;
        if ($method === 'GET') {
            $response = wp_remote_get($request_url, array('headers' => $header, 'timeout' => $timeout));
        } else if ($method === 'POST') {
            $response = wp_remote_post($request_url, array('headers' => $header, 'body' => json_encode($body), 'timeout' => $timeout));
        } else if ($method === 'PUT') {
            $response = wp_remote_request($request_url, array('method' => 'PUT', 'headers' => $header, 'body' => json_encode($body), 'timeout' => $timeout));
        }
        if (is_wp_error($response)) {
            return null;
        }

        if ($auth && $response && $response['response']['code'] == 401 && strpos($response['body'], 'Invalid access token.')) {
            TokenService::delete_token();
        }
        return $response ? $response : null;
    }
}

<?php
// © Microsoft Corporation. All rights reserved.

namespace microsoft_start\routes;

use microsoft_start\infrastructure\ApiController;
use microsoft_start\infrastructure\Util;
use microsoft_start\services\Options;
use microsoft_start\services\MSNClient;

use WP_REST_Response;

class settingsApi extends ApiController
{
    function setValueInDatabse($parameters) {
        Options::set_enable($parameters['option']);
        Options::set_publishOption($parameters['publishOption']);
    }
    function getPayload($feedConfig) {
        $res = (object)[
            "name" => $feedConfig['feedName'],
            "markets" => [$feedConfig['countryRegion']],
            "format"=> 1,
            "type" => 1,
            "link" => trailingslashit(site_url()) . $feedConfig['feedURL'],
            "isLocalNews" => Util::trans_stringboolean_value_to_boolean($feedConfig['isLocalNews']),
            "isAIACIncluded" => Util::trans_stringboolean_value_to_boolean($feedConfig['isAIACIncluded']),
            "locations" => json_decode($feedConfig['locations'])
        ];
        return $res;
    }
    function isRequestSuccess($response) {
        $body = json_decode($response['body'],true);
        // status code of 400 cover cases of bad user
        if ($response['response']['code'] == 400 || (array_key_exists('feedIngestionResult',$body) && $body['feedIngestionResult'] != 0) || (array_key_exists('success',$body) && !$body['success'])) {
            return false;
        }
        return true;
    }
    function register_routes()
    {
        register_rest_route('microsoft/v1', '/publish-settings', [
            'methods' => 'POST',
            'permission_callback' => function () {
                return current_user_can('activate_plugins');
            },
            'callback' => function ($data) {
                $parameters = $data->get_json_params();
                $hasFeed = Options::get_hasFeed();
                if($parameters['option']){
                    if($parameters['publishOption'] === "feed"){
                        $response = MSNClient::upsert_feed($this->getPayload($parameters['feedConfig']));
                        if (!$response || !$this->isRequestSuccess($response)) return new WP_REST_Response(['status' => 'error', 'message' => 'upsert_feed error', 'body' => json_decode($response['body'])], 500);
                        Options::set_hasFeed(true);
                        Options::set_feedConfig($parameters['feedConfig']);
                    }else{
                        if ($hasFeed) {
                            //suspend current feed
                            $response = MSNClient::suspend_feed();
                            if (!$response || !$this->isRequestSuccess($response)) return new WP_REST_Response(['status' => 'error', 'message' => 'suspend_feed error', 'body' => json_decode($response['body'])], 500);
                        }
                        if (array_key_exists("editorConfig",$parameters)){
                            Options::set_category($parameters['editorConfig']['category']);
                        } else {
                            return new WP_REST_Response(['status' => 'error', 'message' => 'set editorConfig error'], 500);
                        }
                    }
                    $this->setValueInDatabse($parameters);
                } else{
                    if ($hasFeed) {
                        //suspend current feed
                        $response = MSNClient::suspend_feed();
                        if (!$response || !$this->isRequestSuccess($response)) return new WP_REST_Response(['status' => 'error', 'message' => 'suspend_feed error', 'body' => json_decode($response['body'])], 500);
                    }
                    Options::set_enable($parameters['option']);
                }

                return new WP_REST_Response(['status' => 'ok'], 200);
            }
        ]);

        register_rest_route('microsoft/v1', '/publish-settings', [
            'methods' => 'GET',
            'permission_callback' => function () {
                return current_user_can('activate_plugins');
            },
            'callback' => function () {
                $profile = MSNClient::account_profile();
                return [
                    "option" => Options::get_enable(),
                    "publishOption" => Options::get_publishOption(),
                    "feedConfig" =>  Options::get_feedConfig($profile),
                    "feedBaseUrl" =>  trailingslashit(site_url()),
                    "editorConfig" => [
                        "category" =>  Options::get_category(),
                        "editorLocations" => json_encode($profile->locations ?? []),
                    ]
                ];
            }
        ]);

        register_rest_route('microsoft/v1', '/get-market', [
            'methods' => 'GET',
            'permission_callback' => function () {
                return current_user_can('activate_plugins');
            },
            'callback' => function () {
                $profile = MSNClient::account_profile();
                $market = $profile->market ?? "en-us";
                return [
                    "market" => $market
                ];
            }
        ]);

        register_rest_route('microsoft/v1', '/get-profile', [
            'methods' => 'GET',
            'permission_callback' => function () {
                return current_user_can('activate_plugins');
            },
            'callback' => function ($data) {
                $profile = MSNClient::account_profile();
                return $profile;
            }
        ]);
    }
}

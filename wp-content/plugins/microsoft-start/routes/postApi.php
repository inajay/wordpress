<?php
// © Microsoft Corporation. All rights reserved.

namespace microsoft_start\routes;

use microsoft_start\services\MSPostConvertService;
use microsoft_start\infrastructure\ApiController;
use microsoft_start\Posts;
use microsoft_start\services\LoggerFeatureSet;
use microsoft_start\services\LoggerTelemetryType;
use microsoft_start\services\LogService;
use microsoft_start\services\MSNClient;
use microsoft_start\services\MSPostMetaService;
use microsoft_start\services\Options;

class postApi extends ApiController
{
    static function clear_batch_submit_cache()
    {
        Options::set_batch_submit_total([]);
        Options::set_batch_submit_total_count(0);
        Options::set_batch_submit_result([]);
        Options::set_batch_submit_complete_time(0);
        Options::set_batch_submit_completed_notification(false);
        Options::set_batch_submit_stop(false);
        LogService::add_log(LoggerTelemetryType::Log, LoggerFeatureSet::ContentOnboarding, "Batch submission options cleared");
    }

    function getIdsFromResultArr(array $result_arr)
    {
        return array_map(function ($result_item) {
            return $result_item->id;
        }, $result_arr);
    }

    function fillIdsWithResultArr(array $result_arr, array $total_ids, bool $is_stop_in_loop)
    {
        $result_ids = $this->getIdsFromResultArr($result_arr);
        $rest_ids = array_diff($total_ids, $result_ids);
        LogService::add_log(LoggerTelemetryType::Log, LoggerFeatureSet::ContentOnboarding, "Batch submission stop rest ids in " . ($is_stop_in_loop ? "loop" : "call progress"), array(
            'rest_ids' => array_values($rest_ids), // array_values to get the ids in array format
            'total_ids' => $total_ids
        ));
        $map_result = array_map(function ($ID) {
            return (object) array(
                'id' => $ID,
                'success' => false
            );
        }, $rest_ids);
        return array_merge($result_arr, $map_result);
    }

    function register_routes()
    {
        register_rest_route("microsoft/v1", "/msn-retrieval", [
            "methods" => "POST",
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => function ($data) {
                $parameters = $data->get_json_params();
                $post_ids = $parameters['postIds'];
                $publishStatus = array_map(function ($item) {
                    $value = $item[key($item)];
                    return [
                        'msn_id' => $value['id'] ?? null,
                        'msn_status' => $value['status'] ?? null,
                        'postId' => key($item),
                        'expirationDateTime' => $value['expirationDateTime'] ?? null,
                        'appealSignal' => $value['appealSignal'] ?? null
                    ];
                }, MSNClient::get_msn_publish_status($post_ids));
                return $publishStatus;
            }
        ]);
        
        register_rest_route("microsoft/v1", "/post-detail/(?P<postId>[0-9_]+)", [
            "methods" => "GET",
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => function ($data) {
                $post_id = $data['postId'];
                $post = get_post($post_id);
                $msn_id = get_post_meta($post_id, "msn_id", true);
                return array_merge(
                    ["id" => $post->ID],
                    MSPostConvertService::get_post_detail($post),
                    $msn_id ? MSPostMetaService::get_all_ms_post_meta($post_id) :MSPostMetaService::get_default_ms_post_meta()
                );
            }
        ]);

        register_rest_route("microsoft/v1", "/msn-id/(?P<postId>[0-9_]+)", [
            "methods" => "GET",
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => function ($data) {
                $post_id = $data['postId'];
                return get_post_meta($post_id, 'msn_id', true);
            }
        ]);

        register_rest_route("microsoft/v1", "/unpublished-to-msph-posts", [
            "methods" => "GET",
            'permission_callback' => function () {
                return current_user_can('edit_posts') || current_user_can('activate_plugins');
            },
            'callback' => function ($data) {
                $page = $data['page'];
                return array(
                    "total_ids" => MSPostMetaService::unpublished_post_ids(),
                    "post_list" => MSPostMetaService::unpublished_posts($page)
                );
            }
        ]);

        register_rest_route("microsoft/v1", "/batch-submit-posts", [
            "methods" => "POST",
            'permission_callback' => function () {
                return current_user_can('edit_posts') || current_user_can('activate_plugins');
            },
            'callback' => function ($data) {
                self::clear_batch_submit_cache();
                $ids = $data->get_json_params();
                LogService::add_log(LoggerTelemetryType::Log, LoggerFeatureSet::ContentOnboarding, 'Batch Submission Total Ids', ['ids' => $ids]);
                $gc_max_life_time = ini_get('session.gc_maxlifetime');
                $max_life_time = $gc_max_life_time ? $gc_max_life_time - 30 : MINUTE_IN_SECONDS * 5;
                LogService::add_log(LoggerTelemetryType::Log, LoggerFeatureSet::ContentOnboarding, "GC Maxlifetime", array(
                    'gc_lifeTime' => $gc_max_life_time,
                ));
                $posts = get_posts(array(
                    "numberposts" => -1,
                    "include" => $ids
                ));
                Options::set_batch_submit_total(array_map(function($item) {
                    return $item->ID;
                }, $posts));
                $start_time = microtime(true);
                for ($i = 0; $i < count($posts); $i++) {
                    $post = $posts[$i];
                    $now = microtime(true);
                    if ((($now - $start_time) < $max_life_time) && !Options::get_batch_submit_stop()) {
                        MSPostMetaService::set_default_ms_post_meta($post->ID);
                        $response = Posts::sync_new_post_to_MSPH($post, 'batch-submit');
                        LogService::add_log(LoggerTelemetryType::Log, LoggerFeatureSet::ContentOnboarding, "After sync", array(
                            'ID' => $post->ID,
                            'timeSpent' => round($now - $start_time, 3),
                            'code' => $response['code'],
                        ));
                        $result_arr = Options::get_batch_submit_result();
                        array_push($result_arr, array(
                            'id' => $post->ID,
                            'success' => $response['code'] === 0
                        ));
                        Options::set_batch_submit_result($result_arr);
                    } else {
                        $result_arr = Options::get_batch_submit_result();
                        $result_arr = $this->fillIdsWithResultArr($result_arr, $ids, true);
                        Options::set_batch_submit_result($result_arr);
                        LogService::add_log(LoggerTelemetryType::Log, LoggerFeatureSet::ContentOnboarding, "Batch submission options in loop", array(
                            'BatchSubmitResult' => Options::get_batch_submit_result(),
                            'BatchSubmitTotal' => Options::get_batch_submit_total(),
                            'BatchSubmitStop' => Options::get_batch_submit_stop(),
                            'BatchSubmitCompleteTime' => Options::get_batch_submit_complete_time(),
                            'BatchSubmitTotalCount' => Options::get_batch_submit_total_count()
                        ));
                        LogService::add_log(LoggerTelemetryType::Log, LoggerFeatureSet::ContentOnboarding, "Batch submission break");
                        break;
                    }
                }
                Options::set_batch_submit_complete_time(time());
                Options::set_batch_submit_completed_notification(true);
            }
        ]);

        register_rest_route("microsoft/v1", "/stop-batch-submission", [
            "methods" => "POST",
            'permission_callback' => function () {
                return current_user_can('edit_posts') || current_user_can('activate_plugins');
            },
            'callback' => function () {
                $total_id = Options::get_batch_submit_total();
                $total_count = count($total_id) ?? (int)Options::get_batch_submit_total_count();
                $result_arr = Options::get_batch_submit_result();
                LogService::add_log(LoggerTelemetryType::Log, LoggerFeatureSet::ContentOnboarding, "Batch-submission-stop", array(
                    'total_count' => $total_count,
                    'result_ids' => $this->getIdsFromResultArr($result_arr)
                ));
                Options::set_batch_submit_stop(true);
                return null;
            }
        ]);


        register_rest_route("microsoft/v1", "/batch-submit-progress", [
            "methods" => "GET",
            'permission_callback' => function () {
                return current_user_can('edit_posts') || current_user_can('activate_plugins');
            },
            'callback' => function () {
                $total_id = Options::get_batch_submit_total();
                $total_count = count($total_id) ?? (int)Options::get_batch_submit_total_count();
                if ($total_count === 0) {
                    return [
                        'total' => $total_count,
                        'result' => [],
                        'complete_time' => 0
                    ];
                } else {
                    $result_arr = Options::get_batch_submit_result();
                    $stopped = Options::get_batch_submit_stop();
                    if ($stopped && (count($result_arr) < $total_count)) {
                        $result_arr = $this->fillIdsWithResultArr($result_arr, $total_id, false);
                        Options::set_batch_submit_result($result_arr);
                        Options::set_batch_submit_complete_time(time());
                        if (!count($total_id)) {
                            $total_count = count($result_arr);
                        }
                        LogService::add_log(LoggerTelemetryType::Log, LoggerFeatureSet::ContentOnboarding, "Batch submission options in progress", array(
                            'BatchSubmitResult' => Options::get_batch_submit_result(),
                            'BatchSubmitTotal' => Options::get_batch_submit_total(),
                            'BatchSubmitStop' => Options::get_batch_submit_stop(),
                            'BatchSubmitCompleteTime' => Options::get_batch_submit_complete_time(),
                            'BatchSubmitTotalCount' => Options::get_batch_submit_total_count()
                        ));
                    }
                    $complete_time = (int)Options::get_batch_submit_complete_time();
                    if (is_array($result_arr) && count($result_arr) >= $total_count) {
                        Options::set_batch_submit_completed_notification(false);
                    }
                    return [
                        'total' => $total_count,
                        'result' => $result_arr,
                        'complete_time' => $complete_time
                    ];
                }
            }
        ]);

        register_rest_route("microsoft/v1", "/clear-batch-submit-progress", [
            "methods" => "POST",
            'permission_callback' => function () {
                return current_user_can('edit_posts') || current_user_can('activate_plugins');
            },
            'callback' => function () {
                self::clear_batch_submit_cache();
            }
        ]);

        register_rest_route("microsoft/v1", "/get-post-by-ids", [
            "methods" => "POST",
            'permission_callback' => function () {
                return current_user_can('edit_posts') || current_user_can('activate_plugins');
            },
            'callback' => function ($data) {
                $ids = $data->get_json_params();
                $posts = get_posts(array(
                    "numberposts" => -1,
                    "include" => $ids
                ));
                return $posts;
            }
        ]);

        register_rest_route("microsoft/v1", "/msn-post-detail/(?P<postId>[0-9_]+)", [
            "methods" => "GET",
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => function ($data) {
                $post_id = $data['postId'];
                $msnId = get_post_meta($post_id, 'msn_id', true);
                return array_merge(
                    [
                        "postId" => $post_id,
                        "msnId" => $msnId
                    ],
                    $msnId ? MSNClient::get_msn_post_detail($msnId) : []
                );
            }
        ]);

        register_rest_route("microsoft/v1", "/submit-appeal/(?P<postId>[0-9_]+)", [
            "methods" => "POST",
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => function ($data) {
                $post_id = $data['postId'];
                $msnId = get_post_meta($post_id, 'msn_id', true);
                return array(
                    "success" => MSNClient::submit_appeal($msnId, $data->get_json_params())
                );
            }
        ]);
    }
}

<?php
// © Microsoft Corporation. All rights reserved.

namespace microsoft_start\services;

class MSPostMetaService
{
    private static $ms_post_meta_keys = [
        "MSN_Publish_Option",
        "MSN_Categories",
        "MSN_Is_Local_News",
        "MSN_Is_AIAC_Included",
        "MSN_Location",
        "MSN_Has_Custom_Author",
        "MSN_Custom_Author",
        "MSN_Has_Custom_Canonical_Url",
        "MSN_Custom_Canonical_Url",
        "MSN_Add_Feature_Img_On_Top_Of_Post"
    ];

    private static $ms_post_meta_keys_for_default_value = [
        "MSN_Publish_Option",
        "MSN_Categories",
        "MSN_Is_Local_News",
        "MSN_Is_AIAC_Included",
        "MSN_Location"
    ];

    public static function set_default_ms_post_meta($postID)
    {
        static::update_meta_data_by_id($postID, static::get_default_ms_post_meta(true), static::$ms_post_meta_keys_for_default_value);
    }

    public static function update_meta_data_from_request($postID)
    {
        // update post meta according to the payload in POST request
        $post_request_data = json_decode(file_get_contents('php://input'), true);
        $meta_from_request = $post_request_data['meta'] ?? null;
        if (is_array($meta_from_request)) {
            static::update_meta_data_by_id($postID, $meta_from_request, static::$ms_post_meta_keys);
        }
    }

    public static function get_post_by_msn_id($msn_id)
    {
        global $wpdb;
        $meta_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->postmeta WHERE meta_key='msn_id' AND meta_value='$msn_id'"));
        $filtered_meta_rows = array_values(array_filter($meta_rows, function($row) {
            return strpos($row->meta_value, $row->post_id);
        }));
        $post_id = count($filtered_meta_rows) > 0 ? $filtered_meta_rows[0]->post_id : null;
        return $post_id ? get_post($post_id) : null;
    }

    public static function get_all_ms_post_meta($postID)
    {
        // Get the meta values
        $all_meta = get_post_meta($postID);
        // Filter out by MS related keys
        $filtered_meta = array_intersect_key($all_meta, array_flip(static::$ms_post_meta_keys));
        // Convert them to singles and return them
        return array_map(function ($n) {
            return $n[0];
        }, $filtered_meta);
    }

    public static function get_default_ms_post_meta()
    {
        $profile_cache = Options::get_profile_cache();
        if (time() > $profile_cache->expired) {
            $profile = MSNClient::account_profile();
        } else {
            $profile = $profile_cache->profile;
        }
        if (isset($profile->isAIACIncluded) && $profile->isAIACIncluded === true) {
            $MSN_Is_AIAC_Included = 'Yes';
        } else if (isset($profile->isAIACIncluded) && $profile->isAIACIncluded === false) {
            $MSN_Is_AIAC_Included = 'No';
        } else {
            $MSN_Is_AIAC_Included = "Empty";
        }
        $res = [
            "MSN_Publish_Option" => Options::get_enable() ? "1" : "0",
            "MSN_Categories" => Options::get_category(),
            "MSN_Is_Local_News" => $profile->isLocalNews ? '1' : '0',
            "MSN_Is_AIAC_Included" => $MSN_Is_AIAC_Included,
            // using wp_slash here to prevent wrong format of locaiton being passed to data-memorizedLocation field of the location selector of newsPanelBody
            "MSN_Location" => json_encode(($profile->locations) ?? [])
        ];

        return $res;
    }

    private static function update_meta_data_by_id($postID, $meta, $keys)
    {
        foreach ($keys as $key) {
            update_post_meta($postID, $key, wp_slash($meta[$key]));
        }
    }

    public static function unpublished_post_ids()
    {
        $posts = get_posts([
            'post_type' => 'post',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'post_status' => ['publish'],
            'has_password' => FALSE,
            'meta_query' => [
                [
                    'key' => 'msn_id',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);
        return $posts;
    }

    public static function unpublished_posts($page)
    {
        $per_page_count = 20;
        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => ['publish'],
            'posts_per_page' => $per_page_count,
            'offset' => ($page - 1) * $per_page_count,
            'orderby' => array(
                'post_date' => 'DESC',
                'ID' => 'DESC'
            ),
            'has_password' => FALSE,
            'meta_query' => [
                [
                    'key' => 'msn_id',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);
        return array_map(function ($post) {
            $post->post_feature_image = get_the_post_thumbnail_url($post, 'full');
            return $post;
        }, $posts);
    }
}

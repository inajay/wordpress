<?php
// © Microsoft Corporation. All rights reserved.

namespace microsoft_start\services;

use WP_Post;

class MSPostConvertService
{
    public static function compose_ms_post(WP_Post $post)
    {
        // query the feed to see if the post should be shared
        $contentHtml = static::query_post_content($post->ID);
        
        if (!$contentHtml) {
            return null;
        }

        $add_feature_to_top = get_post_meta($post->ID, 'MSN_Add_Feature_Img_On_Top_Of_Post', true);
        $feature_img = static::get_featured_img($post);
        if ($add_feature_to_top && $feature_img) {
            $contentHtml = '<figure class="msph-featured-image"><img src="'.$feature_img.'" /></figure>'.$contentHtml;
        }
        $msn_id = get_post_meta($post->ID, 'msn_id', true);
        // initialize the msn_id meta before firing the request if not exist
        if (!$msn_id || (strpos($msn_id, '_') && !strpos($msn_id, '_'.(string)($post->ID)))) {
            // use MSN_Draft_UniqID if exists; otherwise, create a new uniqid as fallback
            $msn_id = Options::get_msn_article_id_prefix().(string)($post->ID);
        }
        
        $publishedDateTime = new \DateTime;
        $publishedDateTime->setTimestamp(get_post_time('U', true, $post->ID));
        $MSPost = [
            "id" => $msn_id,
            "link" => $post->guid,
            "type" => 1,
            "title" => $post->post_title,
            "body" => $contentHtml,
            "abstract" => get_the_excerpt($post->ID),
            "coverImage" => $feature_img,
            "categories" => [get_post_meta($post->ID, 'MSN_Categories', true)],
            "tags" => static::get_tags($post),
            "isLocalNews" => get_post_meta($post->ID, 'MSN_Is_Local_News', true) == "1" ? true : false,
            "isAIACIncluded" => get_post_meta($post->ID, 'MSN_Is_AIAC_Included', true) == "Yes" ? true : (get_post_meta($post->ID, 'MSN_Is_AIAC_Included', true) == "No" ? false : null),
            "locations" => json_decode(get_post_meta($post->ID, 'MSN_Location', true)),
            "author" => get_post_meta($post->ID, 'MSN_Has_Custom_Author', true) == "1" ? get_post_meta($post->ID, 'MSN_Custom_Author', true) : "",
            "seo" => [
                "canonicalUrl" => get_post_meta($post->ID, 'MSN_Has_Custom_Canonical_Url', true) == "1" ? get_post_meta($post->ID, 'MSN_Custom_Canonical_Url', true) : get_permalink($post->ID)
            ],
            "baseAddress" => site_url(),
            "additionalInfo" => [
                "wpPostId" => $post->ID,
                "wpVersion" => MSPH_WP_VERSION,
                "pluginVersion" => MSPH_PLUGIN_VERSION
            ],
            "displayPublishedDateTime" => $publishedDateTime->format('Y-m-d\\TH:i:sP')
        ];
        
        return $MSPost;
    }

    // used in publishing historical articles
    public static function get_post_detail(WP_Post $post)
    {
        return [
            "title" => $post->post_title,
            "abstract" => get_the_excerpt($post->ID),
            "coverImage" => static::get_featured_img($post),
            "tags" => static::get_tags($post),
            "additionalInfo" => [
                "wpPostId" => $post->ID
            ]
        ];
    }

    public static function get_featured_img(WP_Post $post) {
        // feature image and tags might be set after the hook triggered, and this is to get them from POST request if exist
        // force to remove the featured image if the user set it to be empty in the request
        if (static::get_field_from_post_request("featured_media") == "0") {
            return null;
        }

        $featureImage = static::get_featured_img_url_from_post_request() ?: get_the_post_thumbnail_url($post, 'full') ?: null;

        if($featureImage) {
            if (substr($featureImage, 0, 4) != "http"){
                $featureImage = site_url() . $featureImage;
            }
        }
        return $featureImage;
    }
    public static function get_tags(WP_Post $post) {
        return static::get_tags_from_post_request() ?: array_map(function ($post_tag) {
            return $post_tag->name;
        }, wp_get_post_tags($post->ID));
    }
    private static function query_post_content($postID)
    {
        global $wp_query;
        $wp_query->query(array(
            'feed' => 'feed',
            'post_status' => 'publish',
            'suppress_filters' => true,
            'post__in' => array($postID)
        ));
        
        do_action_ref_array( 'wp', array( &$wp ) );

        $postContent = null;
        while (have_posts()) {
            the_post();
            if (get_the_ID() == $postID) {
                // remove thumbnails added by newspack
                remove_filter( 'the_content_feed', 'newspack_thumbnails_in_rss' );

                // add high-priority filter to override rss_use_excerpt option to ensure full content is returned
                add_filter( 'option_rss_use_excerpt', function(){
                    return "0";
                }, 1);

                $postContent = get_the_content_feed();
            }
        }
        
        return $postContent;
    }

    private static function get_featured_img_url_from_post_request()
    {
        $featured_media_id = static::get_field_from_post_request("featured_media");
        if (!$featured_media_id) {
            return null;
        }

        return wp_get_attachment_image_url($featured_media_id, "full");
    }

    private static function get_tags_from_post_request()
    {
        $tags_id_array = static::get_field_from_post_request("tags");
        if (!is_array($tags_id_array)) {
            return null;
        }

        return array_map(function ($tag_id) {
            $tag_object = get_tag($tag_id);
            return $tag_object !== null ? $tag_object->name : null;
        }, $tags_id_array);
    }

    private static function get_field_from_post_request($fieldName)
    {
        $post_request_data = json_decode(file_get_contents('php://input'), true);
        return $post_request_data[$fieldName] ?? null;
    }
}
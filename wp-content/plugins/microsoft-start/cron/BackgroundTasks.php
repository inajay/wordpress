<?php
// © Microsoft Corporation. All rights reserved.

namespace microsoft_start\cron;

use microsoft_start\infrastructure\Registration;
use microsoft_start\services\Options;

class BackgroundTasks extends Registration
{
    function register_dependencies()
    {
        // 1.6.2: Temporarily disble adding cron job as it has some side effect; will re-enable after PM confirms
        return;

        add_action('wp', function () {
            if (Options::get_share_past_posts_start_date()) {
                if (!wp_next_scheduled('msnPublishTask')) {
                    wp_schedule_event(time(), 'hourly', 'msnPublishTask');
                }
            }
        });

        register_deactivation_hook(__FILE__, function () {
            $timestamp = wp_next_scheduled('msnPublishTask');
            wp_unschedule_event($timestamp, 'msnPublishTask');
        });

        add_action('msnPublishTask', [$this, 'publish_posts']);
    }

    function publish_posts()
    {
        // 1.6.2: clear the added cron job in previous version
        wp_clear_scheduled_hook('msnPublishTask');
        return;

        if (!Options::get_share_past_posts_start_date()) {
            return;
        }

        $now = gmdate('Y-m-d H:i:00');
        $after =  date_parse(Options::get_share_past_posts_start_date());

        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => ['publish', 'future'],
            'meta_query' => [
                [
                    'key' => 'msn_id',
                    'compare' => 'NOT EXISTS'
                ]
            ],
            'date_query' => array(
                array(
                    'after'     => $after,
                    'before'    => $now,
                    'inclusive' => true,
                ),
            ),
        ]);

        foreach ($posts as $post) {
            switch ($post->post_status) {
                case 'future':
                    wp_publish_post($post->ID);
                    break;
                case 'publish':
                    do_action( 'wp_after_insert_post', $post->ID, $post, true, null );
                    break;
            }
        }
    }
}

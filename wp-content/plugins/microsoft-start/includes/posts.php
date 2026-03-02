<?php
// © Microsoft Corporation. All rights reserved.

namespace microsoft_start;

use microsoft_start\services\MSPostConvertService;
use microsoft_start\services\MSPostMetaService;
use microsoft_start\services\MSPostSyncService;
use microsoft_start\services\TokenService;
use microsoft_start\routes\postApi;
use microsoft_start\services\Options;
use WP_Post;

class Posts
{

    function __construct()
    {
        if (!TokenService::get_token()) {
            return;
        }
        $accountSettings = json_decode(Options::get_profile());
        if ($accountSettings -> partnerStatus === 3) {
            return;
        }
        postApi::register();

        add_filter('manage_post_posts_columns', array($this, 'manage_posts_columns'));
        add_action('manage_post_posts_custom_column', array($this, 'manage_msn_post_status'), 10, 2);
        add_action('transition_post_status',  array($this, 'ms_post_status_transition_handler'), 10, 3);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_script_in_post_list_page'));
    }

    function manage_posts_columns($defaults)
    {
        // add the staus column after date
        $index_of_date = array_search("date", array_keys($defaults));
        $index_of_insertion = $index_of_date !== false ? $index_of_date + 1 : count($defaults);
        $defaults = array_merge(array_slice($defaults, 0, $index_of_insertion), ['msn-status' => /* translators: table header for a column of publishing status of articles*/ __("MSN publish status", "microsoft-start")], array_slice($defaults, $index_of_insertion));
        return $defaults;
    }

    function manage_msn_post_status($column, $post_id)
    {
        switch ($column) {
            case 'msn-status':
                
                $post = get_post($post_id);
                $msn_id = get_post_meta($post_id, "msn_id", true);
?>
                <div  data-msn-id=<?= $msn_id?:"''" ?> data-post-id="<?= $post_id ?>" data-post-status="<?= $post->post_password ? 'password' : $post->post_status  ?>"  data-post-is-empty="<?= !$post->post_content ?>"></div>
<?php   
        }
    }

    function ms_post_status_transition_handler($new_status, $old_status, WP_Post $post)
    {
        if ( ! empty( $_REQUEST['meta-box-loader'] ) ) {
            // Avoid trigger this callback two times. Detail: https://github.com/WordPress/gutenberg/issues/15094
            return;
        }
        // not take any action if the brand is not active or user is in feed mode
        if (Options::get_status() != 'active' || Options::get_publishOption() === "feed") {
            return;
        }

        // not take any action if the partner hasn't added payment account
        $profileFromDb = json_decode(Options::get_profile());
        if (($profileFromDb->partnerMetadata->paymentStatus->stripeAccountStatus ?? null) === 0) {
            return;
        }

        if ($new_status == "auto-draft") {
            // set initial values for a new post
            MSPostMetaService::set_default_ms_post_meta($post->ID);
            return;
        }

        if ($new_status == "publish" && !$post->post_password) {
            // update post meta according to the payload in POST request
            MSPostMetaService::update_meta_data_from_request($post->ID);

            if (get_post_meta($post->ID, 'MSN_Publish_Option', true)) {
                self::sync_new_post_to_MSPH($post, 'single-submit');
            }
        }
    }

    static function sync_new_post_to_MSPH($post, $origin) {
        $msPost = MSPostConvertService::compose_ms_post($post);
        $response = MSPostSyncService::sync_post($msPost, $origin);
        if ($response['code'] === 0) {
            update_post_meta($post->ID, 'msn_id', $msPost['id']);
        }
        return $response;
    }

    function enqueue_script_in_post_list_page()
    {
        $current_screen = get_current_screen();
        if ($current_screen->id !== "edit-post") {
            return;
        }

        wp_enqueue_style(
            "post-status",
            plugins_url("/assets/js/status.css", dirname(__FILE__)),
            ['wp-components'],
            MSPH_PLUGIN_VERSION
        );

        wp_enqueue_script(
            "post-status",
            plugins_url("/assets/js/status.js", dirname(__FILE__)),
            array('wp-plugins', 'wp-edit-post', 'wp-api', 'wp-i18n', 'wp-components', 'wp-element'),
            MSPH_PLUGIN_VERSION,
            true
        );

        wp_set_script_translations('post-status', 'microsoft-start', plugin_dir_path(__DIR__) . 'languages/');

        wp_register_style('warnings', false);
        wp_enqueue_style('warnings');
        
        $accountSettings = json_decode(Options::get_profile());
        wp_localize_script(
            'post-status',
            'msn_dashboard_render_status',
            [
                'enabled' => Options::get_status() == 'active' || Options::get_status() == 'pending',
                'feed_mode' => Options::get_publishOption() === "feed",
                'profile' => $accountSettings,
                'language' => MSPH_WP_LANG
            ]
        );
        wp_localize_script( 'post-status', 'msn_sidebar_settings',
                [
                'enabled' => Options::get_status() == 'active' || Options::get_status() == 'pending' ? 'true' : 'false',
                'brand_active' => Options::get_status() == 'active' ? 'true' : 'false',
                'feed_mode' => Options::get_publishOption() === "feed",
                'is_admin' => current_user_can( 'manage_options' )
                ]
            );
    }
}

new Posts();

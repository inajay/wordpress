<?php
// © Microsoft Corporation. All rights reserved.

namespace microsoft_start;

use microsoft_start\services\Options;

class PostEditor
{

    function __construct()
    {
        add_action('init', array($this, 'initialise_sidebar_metadata'));
        add_action("enqueue_block_editor_assets", array($this, "enqueue_sidebar"));
    }

    function enqueue_sidebar()
    {
        $screen = get_current_screen();
        if($screen->post_type === "post") {
            wp_enqueue_script(
                'msn-sidebar',
                plugins_url("/assets/js/editorSidebar.js", dirname(__FILE__)),
                array('wp-plugins', 'wp-edit-post', 'wp-api', 'wp-i18n', 'wp-components', 'wp-element'),
                MSPH_PLUGIN_VERSION
            );
            wp_localize_script( 'msn-sidebar', 'msn_sidebar_settings',
                [
                'enabled' => Options::get_status() == 'active' || Options::get_status() == 'pending' ? 'true' : 'false',
                'brand_active' => Options::get_status() == 'active' ? 'true' : 'false',
                'feed_mode' => Options::get_publishOption() === "feed",
                'is_admin' => current_user_can( 'manage_options' )
                ]
            );

            wp_localize_script( 'msn-sidebar', 'msn_dashboard_render_status',
                [
                    'language' => MSPH_WP_LANG,
                    'profile' => json_decode(Options::get_profile())
                ]
            );

            wp_set_script_translations('msn-sidebar', 'microsoft-start', plugin_dir_path(__DIR__) . 'languages/');

            wp_enqueue_style(
                uniqid('msn-sidebar-styles'),
                plugins_url("../assets/js/editorSidebar.css", __FILE__),
                [],
                MSPH_PLUGIN_VERSION
            );
        }
    }

    function initialise_sidebar_metadata()
    {
        /**
         * metadata to store the value of the selected category in the gutenburg sidebar
         */
        register_meta('post', 'MSN_Categories', array(
            'show_in_rest' => true,
            'type' => 'string',
            'single' => true,
            'default' => 'Uncategorized'
        ));

        /**
         * metadata to store the value of the selected publishing option in the gutenburg sidebar
         */
        register_meta('post', 'MSN_Publish_Option', array(
            'show_in_rest' => true,
            'type' => 'boolean',
            'single' => true,
            'auth_callback' => true
        ));
        
        /**
         * metadata to store the value of location related fields in the gutenburg sidebar
         */
        register_meta('post', 'MSN_Is_Local_News', array(
            'show_in_rest' => true,
            'type' => 'boolean',
            'single' => true,
            'auth_callback' => true,
            'default' => false
        ));

        register_meta('post', 'MSN_Is_AIAC_Included', array(
            'show_in_rest' => true,
            'type' => 'string',
            'single' => true,
            'default' => 'Empty'
        ));

        register_meta('post', 'MSN_Location', array(
            'show_in_rest' => true,
            'type' => 'string',
            'single' => true,
            'auth_callback' => true,
            'default' => "[]"
        ));

        register_meta('post', 'MSN_Add_Feature_Img_On_Top_Of_Post', array(
            'show_in_rest' => true,
            'type' => 'boolean',
            'single' => true,
            'auth_callback' => true,
            'default' => false
        ));

        /**
         * metadata to store the value of author related fields in the gutenburg sidebar
         */
        register_meta('post', 'MSN_Has_Custom_Author', array(
            'show_in_rest' => true,
            'type' => 'boolean',
            'single' => true,
            'auth_callback' => true,
            'default' => false
        ));

        register_meta('post', 'MSN_Custom_Author', array(
            'show_in_rest' => true,
            'type' => 'string',
            'single' => true,
            'auth_callback' => true,
            'default' => ''
        ));

        /**
         * metadata to store the value of canonical url related fields in the gutenburg sidebar
         */
        register_meta('post', 'MSN_Has_Custom_Canonical_Url', array(
            'show_in_rest' => true,
            'type' => 'boolean',
            'single' => true,
            'auth_callback' => true,
            'default' => false
        ));

        register_meta('post', 'MSN_Custom_Canonical_Url', array(
            'show_in_rest' => true,
            'type' => 'string',
            'single' => true,
            'auth_callback' => true,
            'default' => ''
        ));
    }
}
new PostEditor();
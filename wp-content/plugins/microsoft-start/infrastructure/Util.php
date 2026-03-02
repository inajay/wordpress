<?php
// © Microsoft Corporation. All rights reserved.

namespace microsoft_start\infrastructure;
Class Util {
    static public function add_redirect_page($id, $title, $url) {

        $page = Util::add_submenu_page(
            $id,
            $title,
            function() { }
        );

        add_action( "load-$page",
            function() use($url) {
                wp_redirect($url);
                exit;
            }
        );
    }

    static public function add_submenu_page(string $id, ?string $title, callable $render, callable $enqueueScripts = null) {
        $page = \add_submenu_page(
            $title ? 'microsoft' : null,
            $title,
            $title,
            'manage_options',
            $id,
            $render
        );

        if($enqueueScripts) {
            add_action('admin_enqueue_scripts', function ($hook) use ($page, $enqueueScripts){
                if($hook !== $page) {
                return;
                }
                $enqueueScripts($page);
            });
        }
        return $page;
    }
    static function if_version_higher($v1, $v2) {
        $v1_arr = array_map(
            function($str) { 
                return (int)$str; 
            }, explode('.', $v1)
        );
        $v2_arr = array_map(
            function($str) { 
                return (int)$str; 
            }, explode('.', $v2)
        );
        $result = false;
        foreach($v1_arr as $i => $num) {
            if ($num > (isset($v2_arr[$i]) ? $v2_arr[$i] : 0)) {
                $result = true;
                break;
            }
            if ($num < (isset($v2_arr[$i]) ? $v2_arr[$i] : 0)) {
                $result = false;
                break;
            }
        }
        return $result;
    }

    static function get_latest_plugin_version() {
        $latest_version = plugins_api('plugin_information', array(
            'slug' => 'microsoft-start',
            'fields' => array(
                'version' => true,
            )
        ))->version;
        return $latest_version;
    }

    static function trans_stringboolean_value_to_boolean($value) {
        if ($value === "Yes") {
            return true;
        } else if ($value === "No") {
            return false;
        } else {
            return null;
        }
    }

    static function trans_boolean_value_to_stringboolean($value) {
        if ($value === true) {
            return "Yes";
        } else if ($value === false) {
            return "No";
        } else {
            return "Empty";
        }
    }
}
<?php
// © Microsoft Corporation. All rights reserved.
namespace microsoft_start\cron;
use microsoft_start\services\Options;
use microsoft_start\infrastructure\Util;

function if_version_higher($v1, $v2) {
    return Util::if_version_higher($v1, $v2);
}

class Task {
    protected $cb_map;
    function __construct() {
        $this->cb_map = array(
            array(
                'version' => '2.0.0',
                'callback' => function () {
                    global $wpdb;
                    $wpdb->delete('wp_postmeta', array('meta_key' => 'MSN_Draft_UniqID'));
                }
            ),

            array(
                'version' => '2.4.1',
                'callback' => function () {
                    // parse the location object to locations array
                    global $wpdb;
                    $wpdb->query($wpdb->prepare("UPDATE wp_postmeta SET meta_value = CONCAT('[', meta_value, ']') WHERE meta_key = 'MSN_Location' AND meta_value LIKE '{%}'"));
                }
            )
        );
    }
    
    public function diff_version_change() {
        if (
            !file_exists(dirname(__FILE__).'/../services/Options.php') ||
            !method_exists('microsoft_start\services\Options', 'get')) {
            return;
        }
        $last_version = Options::get_updated_version();
        $new_version = MSPH_PLUGIN_VERSION;
        if ($last_version === $new_version) {
            return;
        }
        Options::set_updated_version($new_version);
        
        $start_map_index = NULL;
        for($i = 0; $i < count($this -> cb_map); $i++) {
            if (is_null($start_map_index)) {
                if (Util::if_version_higher($this -> cb_map[$i]['version'], $last_version)) {
                    $start_map_index = $i;
                    $this -> cb_map[$i]['callback']();
                };
            } else {
                if (Util::if_version_higher($new_version, $this -> cb_map[$i]['version'])) {
                    break;
                } else {
                    $this -> cb_map[$i]['callback']();
                };
            }
        }
    }
}

$task = new Task();

add_action('plugins_loaded', function() use ($task) {
    $task -> diff_version_change();
});
?>
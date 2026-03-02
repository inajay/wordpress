<?php
// © Microsoft Corporation. All rights reserved.

if (!function_exists('get_plugin_data')) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

define('MSPH_PLUGIN_VERSION', get_plugin_data(WP_PLUGIN_DIR . '/microsoft-start/index.php')['Version']);
define('MSPH_WP_VERSION', get_bloginfo('version'));

define('MSPH_WP_LANG', (function () {
    $json_string = file_get_contents(WP_PLUGIN_DIR . '/microsoft-start/languages/languageMap.json'); 
    $lang_map = json_decode($json_string, true);
    $wp_lang = get_bloginfo('language');
    $msph_lang = isset($lang_map[$wp_lang])
        ? $lang_map[$wp_lang]
        : 'en-us';
    return $msph_lang;
})());
define('MSPH_SERVICE_URL', 'https://api.msn.com/msn/v0/pages/ugc/');
define('MSPH_OCID_APIKEY_QSP', '&ocid=msphwp&apikey=gHQ2BwVMjFj69uWfDdkunIHHUbDtKBzEBAkG1xOQrp');
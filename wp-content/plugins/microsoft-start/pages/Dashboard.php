<?php
// © Microsoft Corporation. All rights reserved.

namespace microsoft_start\pages;

use microsoft_start\infrastructure\Page;

use microsoft_start\routes\settingsApi;
use microsoft_start\routes\helpCenterApi;
use microsoft_start\routes\logApi;
use microsoft_start\routes\notificationApi;
use microsoft_start\services\MSNClient;
use microsoft_start\services\Options;

class Dashboard extends Page
{
    function register_dependencies()
    {
        settingsApi::register();
        helpCenterApi::register();
        notificationApi::register();
        logApi::register();
    }

    function admin_menu()
    {
        $this->add_submenu_page(
            'microsoft-start',
            /* translators: submenu item in the Menu bar under Microsoft Start*/__('Partner Hub', "microsoft-start"),
            function ($page) {
                wp_enqueue_script(
                    $page,
                    plugins_url("/assets/js/dashboard.js", dirname(__FILE__)),
                    array('wp-plugins', 'wp-edit-post', 'wp-api', 'wp-i18n', 'wp-components', 'wp-element'),
                    MSPH_PLUGIN_VERSION,
                    true
                );

                wp_set_script_translations($page, 'microsoft-start', plugin_dir_path(__DIR__) . 'languages/');

                wp_enqueue_style(
                    $page,
                    plugins_url("/assets/js/dashboard.css", dirname(__FILE__)),
                    ['wp-components'],
                    MSPH_PLUGIN_VERSION
                );

                $accountSettings = MSNClient::account_settings("dashboardPage");
                wp_localize_script(
                    $page,
                    'msn_dashboard_render_status',
                    [
                        'enabled' => Options::get_status() == 'active' || Options::get_status() == 'pending',
                        'default' => Options::get_status() == 'default',
                        'pending' => Options::get_status() == 'pending',
                        'active' => Options::get_status() == 'active',
                        'disconnected' => Options::get_status() == 'disconnected',
                        'profile' => $accountSettings,
                        'encodedConnectCallbackUrl' => urlencode(admin_url("admin.php?page=msn-callback")),
                        'language' => MSPH_WP_LANG
                    ]
                );
            }
        );

    }

    function render()
    {
?>
        <div id="msn-dashboard"></div>
<?php
    }
}

<?php
// © Microsoft Corporation. All rights reserved.

namespace microsoft_start\infrastructure;

use microsoft_start\services\MSNClient;
use microsoft_start\services\Options;

abstract class Page extends Registration
{
    function __construct()
    {
        add_action('admin_menu', function () {
            
            if (empty($GLOBALS['admin_page_hooks']['microsoft'])) {
                $current_timestamp = time();
                $red_dot = Options::get_red_dot();
                $red_dot_count = $red_dot['count'];
                if ($red_dot['timestamp'] + HOUR_IN_SECONDS * 6 < $current_timestamp) {
                    MSNClient::get_notification('sidebar');
                    $red_dot_count = Options::get_red_dot()['count'];
                }
                //https://wordpress.stackexchange.com/questions/311412/how-can-i-make-my-admin-icon-svg-color-correctly
                $image = base64_encode("<svg width='20' height='20' viewBox='0 0 20 20' fill='none' xmlns='http://www.w3.org/2000/svg'>
                    <path d='M5.58781 11.7422C5.89713 11.9007 6.18119 12.0559 6.44205 12.2075C1.30277 14.6196 2.06875 19.0356 4.71554 19.1693C7.51463 19.3115 9.33429 15.4373 9.33429 15.4373C9.34165 15.4224 11.8013 10.4391 10.3588 6.0205C9.36485 2.97606 7.37353 1.02958 5.47251 0.866559C4.04241 0.743946 3.06377 0.927866 1.9865 1.96033C1.22595 2.68904 0.77379 3.97996 0.840468 5.48337C0.907146 6.98677 1.39681 9.59439 5.58781 11.7422ZM9.33429 15.4373L9.34033 15.4073C9.33731 15.4265 9.33448 15.4366 9.33429 15.4373Z' fill=\"white\"/>
                    <path d='M15.5798 5.51735C11.575 6.33758 10.8107 15.4994 10.8107 15.4994C10.8067 15.5176 10.0103 19.2056 12.2734 19.173C13.9334 19.1486 14.9507 16.8579 13.6399 13.4017C13.9315 13.282 14.2473 13.1703 14.5899 13.0686C16.3843 12.536 19.168 11.0905 19.168 8.45361C19.168 6.5214 18.0587 5.00975 15.5798 5.51735Z' fill=\"white\"/>
                    </svg>");

                add_menu_page(
                    __('General', "microsoft-start"),
                    /* translators: Name of plugin, appears in menu bar and edit bar, or in connection page when user tries to connect*/
                    __(
                        'MSN Partner Hub',
                        "microsoft-start"
                    ) . ($red_dot_count ? '<span class="awaiting-mod">'. $red_dot_count .'</span>' : ''),
                    'manage_options',
                    'microsoft',
                    '',
                    'data:image/svg+xml;base64,' . $image,
                    0
                );
            }
            remove_submenu_page('microsoft', 'microsoft');

            $this->admin_menu();
        });
    }

    abstract protected function admin_menu();
    abstract protected function render();

    function add_submenu_page(string $id, string $title, callable $enqueueScripts)
    {
        Util::add_submenu_page($id, $title, [$this, 'render'], $enqueueScripts);
    }
}

<?php
// © Microsoft Corporation. All rights reserved.

/**
 * Plugin Name:       MSN Partner Hub
 * Plugin URI:        https://www.msn.com/
 * Description:       MSN Partner Hub WordPress plugin to help WordPress content creators to share content to MSN News feed.
 * Version:           2.8.7
 * Requires at least: 5.4
 * Requires PHP:      7.3
 * Author:            Microsoft
 * Author URI:        https://www.microsoft.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       microsoft-start
 */

namespace microsoft_start;

require __DIR__ . '/variable.php';
require __DIR__ . '/vendor/autoload.php';

//Modules
require_once('includes/posts.php');
require_once('includes/postEditor.php');
require_once('cron/Task.php');

pages\Dashboard::register();
pages\Callback::register();
cron\BackgroundTasks::register();
services\LogService::register();
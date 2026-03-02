<?php
// © Microsoft Corporation. All rights reserved.
namespace microsoft_start\services;
use microsoft_start\infrastructure\Util;
class Options
{
    private static function get($key, $default=null)
    {
        if ($default !== null) {
            add_option('msn_' . $key, $default);
        }
        $value = get_option('msn_' . $key);
        return $value;
    }
    private static function set($key, $value)
    {
        return update_option('msn_' . $key, $value);
    }
    static function __callStatic($name, $arguments)
    {
        return '';
    }

    public static function get_updated_version()
    {
        $result = self::get('updated_version');
        return $result ?: '0.0.0';
    }
    public static function set_updated_version($val)
    {
        return self::set('updated_version', $val);
    }

    public static function get_share_past_posts_start_date()
    {
        $result = self::get('SharePastPostsStartDate');
        return $result ?: null;
    }

    public static function set_share_past_posts_start_date($val)
    {
        return self::set('SharePastPostsStartDate', $val);
    }

    public static function get_app_id()
    {
        return self::get('AppId');
    }
    public static function set_app_id($val)
    {
        return self::set('AppId', $val);
    }

    public static function get_app_secret()
    {
        return self::get('AppSecret');
    }
    public static function set_app_secret($val)
    {
        return self::set('AppSecret', $val);
    }

    public static function get_auth_token()
    {
        return self::get('AuthToken');
    }
    public static function set_auth_token($val)
    {
        return self::set('AuthToken', $val);
    }

    public static function get_CID()
    {
        return self::get('CID');
    }
    public static function set_CID($val)
    {
        return self::set('CID', $val);
    }

    public static function get_status()
    {
        $result = self::get('Status');
        return $result ?: 'default';
    }
    public static function set_status($val)
    {
        return self::set('Status', $val);
    }

    public static function get_enable()
    {
        return self::get('Enabled', true);
    }
    public static function set_enable($val)
    {
        return self::set('Enabled', $val);
    }

    public static function get_category()
    {
        return self::get('Category', 'Uncategorized');
    }
    public static function set_category($val)
    {
        return self::set('Category', $val);
    }
    public static function get_msn_article_id_prefix()
    {
        $result = self::get('MsnArticleIdPrefix');
        return $result ?: 'default_';
    }
    public static function set_msn_article_id_prefix($val)
    {
        return self::set('MsnArticleIdPrefix', $val);
    }

    public static function get_cached_user_action_logs(/*LoggerTelemetryType*/ int $type)
    {
        return self::get('UserActionLogs_' . $type);
    }
    public static function set_cached_user_action_logs(/*LoggerTelemetryType*/ int $type, $val)
    {
        return self::set('UserActionLogs_' . $type, $val);
    }
    public static function get_feedConfig($profile)
    {
        $result = self::get('FeedConfig');
        if (!$result) {
            $result = [
                "feedName" => "",
                "feedURL" => get_option( 'permalink_structure' ) ? "feed/" : "?feed=rss2",
                "countryRegion" => $profile->market ?? 'en-us',
                "isLocalNews" => Util::trans_boolean_value_to_stringboolean($profile->isLocalNews),
                "isAIACIncluded" => Util::trans_boolean_value_to_stringboolean($profile->isAIACIncluded),
                "locations" => json_encode($profile->locations ?? [])
            ];
        }
        return $result;
    }
    public static function set_feedConfig($val)
    {
        return self::set('FeedConfig', $val);
    }
    public static function get_publishOption()
    {
        $result = self::get('PublishOption', "editor");
        return $result;
    }
    public static function set_publishOption($val)
    {
        return self::set('PublishOption', $val);
    }
    public static function get_hasFeed()
    {
        $result = self::get('HasFeed');
        return $result ?: false;
    }
    public static function set_hasFeed($val)
    {
        return self::set('HasFeed', $val);
    }
    public static function get_profile()
    {
        return self::get('Profile');
    }
    public static function set_profile($val)
    {
        return self::set('Profile', $val);
    }
    public static function get_dismissed_notification_version() {
        return self::get('DismissedNotificationVersion', '0.0.0');
    }
    public static function set_dismissed_notification_version($val) {
        return self::set('DismissedNotificationVersion', $val);
    }
    
    public static function get_dismissed_publish_to_MSPH_notification() {
        return self::get('DismissedPublishToMSPHNotification', false);
    }
    public static function set_dismissed_publish_to_MSPH_notification($val) {
        return self::set('DismissedPublishToMSPHNotification', $val);
    }

    public static function get_batch_submit_completed_notification() {
        return self::get('BatchSubmitCompletedNotification', false);
    }
    public static function set_batch_submit_completed_notification($val) {
        return self::set('BatchSubmitCompletedNotification', $val);
    }
    /**
     * @return array('count' => number, 'timestamp' => number) 
     */
    public static function get_red_dot() {
        $red_dot = self::get('RedDot', json_encode(array(
            'count' => 0,
            'timestamp' =>0
        )));
        return (array)json_decode($red_dot);
    }
    public static function set_red_dot($count) {
        return self::set('RedDot', json_encode(array(
            'count' => $count,
            'timestamp' => time()
        )));
    }
    /**
     * @deprecated
     */
    public static function get_batch_submit_total_count() {
        return self::get('BatchSubmitTotalCount', 0);
    }

    /**
     * @deprecated
     */
    public static function set_batch_submit_total_count($val) {
        return self::set('BatchSubmitTotalCount', $val);
    }

    public static function get_batch_submit_total() {
        $result_json = self::get('BatchSubmitTotal');
        return $result_json ? json_decode($result_json) : [];
    }
    public static function set_batch_submit_total($val) {
        return self::set('BatchSubmitTotal', json_encode($val));
    }

    public static function get_batch_submit_complete_time() {
        return self::get('BatchSubmitCompleteTime', 0);
    }
    public static function set_batch_submit_complete_time($val) {
        return self::set('BatchSubmitCompleteTime', $val);
    }

    public static function get_batch_submit_result() {
        
        $result_json = self::get('BatchSubmitResult');
        return $result_json ? json_decode($result_json) : [];
    }
    public static function set_batch_submit_result($val) {
        return self::set('BatchSubmitResult', json_encode($val));
    }

    public static function get_batch_submit_stop() {
        global $wpdb;
        $option_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_options WHERE option_name='msn_BatchSubmitStop'"));
        return $option_row->option_value ?? self::get('BatchSubmitStop', false);
    }
    public static function set_batch_submit_stop($val) {
        $res = self::set('BatchSubmitStop', $val);
        wp_cache_delete('alloptions', 'options');
        return $res;
    }

    public static function get_profile_cache() {
        return json_decode(self::get('ProfileCache', json_encode(array('expired' => 0))));
    }
    public static function set_profile_cache($val) {
        return self::set('ProfileCache', json_encode($val));
    }
}

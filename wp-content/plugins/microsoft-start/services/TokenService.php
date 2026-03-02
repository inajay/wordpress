<?php
// © Microsoft Corporation. All rights reserved.

namespace microsoft_start\services;

use microsoft_start\services\MSNClient;

class TokenService
{
    public static function set_client($appId, $appSecret) {
        Options::set_app_id($appId);
        Options::set_app_secret($appSecret);
        Options::set_msn_article_id_prefix(static::generate_msn_article_id_prefix($appId));
        Options::set_status('pending');
        Options::set_auth_token(null);
        Options::set_CID(null);
        $settings = MSNClient::account_settings("set_client");
        LogService::add_log(LoggerTelemetryType::Log, LoggerFeatureSet::PartnerOnboarding, "Connect", array(
            'appId' => $appId
        ));
    }

    public static function clear_client() {
        Options::set_app_id(null);
        Options::set_app_secret(null);
        Options::set_auth_token(null);
        Options::set_CID(null);
        Options::set_status("disconnected");
    }

    public static function get_token() {
        $token = Options::get_auth_token();
        if($token) {
            $jwtParts = explode('.', $token);
            $payload = json_decode(base64_decode($jwtParts[1]));

            // refresh if token will be expired in 5 mins
            if(time() + 5 * MINUTE_IN_SECONDS > $payload->exp)
            {
                $token = null;
            }
        }

        if(!$token) {
            $appId = Options::get_app_id();
            $appSecret = Options::get_app_secret();
            if($appId && $appSecret) {
                $msnAccountUrl = MSPH_SERVICE_URL;

                $response = wp_remote_post(
                    "{$msnAccountUrl}thirdparty/accesstoken?appId=$appId" . MSPH_OCID_APIKEY_QSP,
                    [
                        'headers' => [
                            'X-MsphConnectionSecret' => $appSecret
                        ],
                        'method'      => 'GET',
                        'data_format' => 'body'
                    ]
                );
                if (is_wp_error($response)) {
                    return null;
                }
                if ($response['response']['code'] == 200) {
                    $payload = json_decode($response['body']);
                    $token = $payload->token;
                    TokenService::set_token($token);
                } else if ($response['response']['code'] == 400) {
                    $payload = json_decode($response['body']);
                    if ($payload) {
                        $errorCode = $payload->errorCode ?? null;
                        // clear client if there's valid error code
                        if (is_numeric($errorCode)) {
                            TokenService::clear_client();
                        }
                    }
                }
            }
        }

        return $token;
    }

    public static function set_token($token) {
        Options::set_auth_token($token);
        // fetch latest account setting to see if the account passed review / account has added payment
        $profileFromDb = json_decode(Options::get_profile());
        if (Options::get_status() == 'pending' || ($profileFromDb->partnerMetadata->paymentStatus->stripeAccountStatus ?? null) === 0) {
            $accountSettings = MSNClient::account_settings("set_token");
        }
    }

    public static function delete_token() {
        $token = TokenService::get_token();

        $appId = Options::get_app_id();
        $msnAccountUrl = MSPH_SERVICE_URL;

        // Force sync send log before revoke the credential
        LogService::add_log(LoggerTelemetryType::Log, LoggerFeatureSet::PartnerOnboarding, "Disconnect", array(
            'appId' => $appId
        ), true);

        wp_remote_request(
            "{$msnAccountUrl}account/applications?appId={$appId}&scn=3rdPartyAuth" . MSPH_OCID_APIKEY_QSP,
            [
                'headers' => [
                    'Authorization' => "Bearer $token"
                ],
                'method'      => 'DELETE',
                'data_format' => 'body'
            ]
        );

        TokenService::clear_client();
    }

    // generate the article id prefix when connecting, which is based on site url and app id
    private static function generate_msn_article_id_prefix($appId) {
        $md5 = md5(site_url().$appId);
        return substr($md5, 0, 12).'_';
    }
}
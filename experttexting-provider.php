<?php
/**
 * SMS Provider: ExpertTexting (Debug Enabled)
 */

if (!defined('ABSPATH')) exit;

function erpgulf_send_sms_experttexting($phone, $otp, $settings) {
    $url    = !empty($settings['url']) ? trim($settings['url']) : 'https://www.experttexting.com/ExptRestApi/sms/json/Message/Send';
    $user   = trim(stripslashes($settings['username']));
    $key    = trim(stripslashes($settings['api_key']));
    $secret = trim(stripslashes($settings['api_secret']));

    $body = [
        'username'   => $user,
        'api_key'    => $key,
        'api_secret' => $secret,
        'from'       => !empty($settings['from']) ? trim($settings['from']) : 'DEFAULT',
        'to'         => $phone,
        'text'       => "Your verification code is: $otp",
        'type'       => 'unicode'
    ];

    $response = wp_remote_post($url, [
        'timeout'   => 30,
        'sslverify' => false,
        'headers'   => ['Content-Type' => 'application/x-www-form-urlencoded'],
        'body'      => $body,
    ]);

    $code = wp_remote_retrieve_response_code($response);
    $raw  = wp_remote_retrieve_body($response);

    // DEBUG OUTPUT - Remove this once it works
    $debug = "--- DEBUG DATA ---\nURL: $url\nUser: $user\nKey: $key\nSecret: $secret\nHTTP Code: $code\nGateway: $raw\n------------------";

    if ($code === 200) {
        $res = json_decode($raw, true);
        if (isset($res['Status']) && (int)$res['Status'] === 0) {
            return ['success' => true];
        }
    }

    return ['success' => false, 'message' => nl2br(esc_html($debug))];
}
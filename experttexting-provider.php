<?php
/**
 * SMS Provider: ExpertTexting
 *
 * Called by erpgulf-otp-login.php
 * Returns: [ 'success' => bool, 'message' => string (on failure) ]
 *
 * DEVELOPER HOOK
 * ─────────────────────────────────────────────────────────────────
 * Filter: erpgulf_otp_message_text ( $message, $otp, $phone, $user_id )
 *   Override the final SMS body text before it is transmitted.
 *   Called once per send attempt, after template resolution.
 *
 *   Example:
 *     add_filter( 'erpgulf_otp_message_text', function( $msg, $otp, $phone, $uid ) {
 *         return "[$otp] is your login code for MyStore.";
 *     }, 10, 4 );
 * ─────────────────────────────────────────────────────────────────
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Normalise a phone number to pure international digits.
 *
 * Digits length after stripping non-digits and "00" prefix:
 *   ≥ 11  → already has a country code, return as-is
 *   10, not starting with 0 → treat as having a CC, return as-is
 *   10, starting with 0 → trunk prefix, strip 0, prepend default CC
 *   ≤ 9   → local number, prepend default CC
 */
function erpgulf_normalise_phone( string $phone, string $default_cc = '966' ): string {

    $d = preg_replace( '/[^0-9]/', '', $phone );
    if ( empty( $d ) ) return $d;

    // Strip leading "00" international dialling prefix
    if ( strpos( $d, '00' ) === 0 ) {
        $d = substr( $d, 2 );
    }

    $len = strlen( $d );

    if ( $len >= 11 ) return $d;                               // already has CC

    if ( $len === 10 && strpos( $d, '0' ) !== 0 ) return $d;  // 10-digit with CC

    // Strip trunk prefix "0" if present
    if ( strpos( $d, '0' ) === 0 ) {
        $d = substr( $d, 1 );
    }

    // Prepend default country code
    if ( ! empty( $default_cc ) ) {
        $d = $default_cc . $d;
    }

    return $d;
}

function erpgulf_send_sms_experttexting( string $phone, int $otp, array $settings ): array {

    $url    = ! empty( $settings['url'] )          ? trim( $settings['url'] )                        : 'https://www.experttexting.com/ExptRestApi/sms/json/Message/Send';
    $user   = ! empty( $settings['username'] )     ? trim( stripslashes( $settings['username'] ) )   : '';
    $key    = ! empty( $settings['api_key'] )      ? trim( stripslashes( $settings['api_key'] ) )    : '';
    $secret = ! empty( $settings['api_secret'] )   ? trim( stripslashes( $settings['api_secret'] ) ) : '';
    $from   = ! empty( $settings['from'] )         ? trim( $settings['from'] )                       : 'DEFAULT';
    $cc     = ! empty( $settings['country_code'] ) ? trim( $settings['country_code'] )               : '966';
    $uid    = ! empty( $settings['user_id'] )      ? intval( $settings['user_id'] )                  : 0;

    if ( empty( $user ) || empty( $key ) || empty( $secret ) ) {
        return [ 'success' => false, 'message' => 'ExpertTexting credentials are missing.' ];
    }

    $clean_phone = erpgulf_normalise_phone( $phone, $cc );

    if ( strlen( $clean_phone ) < 7 ) {
        return [
            'success' => false,
            'message' => "Phone too short after normalisation (got: {$clean_phone}). Original: {$phone}",
        ];
    }

    // ── Build bilingual message body ──────────────────────────────
    $msg_en   = ! empty( $settings['message_en'] ) ? trim( $settings['message_en'] ) : "Your OTP is: {$otp}. Valid 5 mins.";
    $msg_ar   = ! empty( $settings['message_ar'] ) ? trim( $settings['message_ar'] ) : "رمز التحقق: {$otp}. صالح 5 دقائق.";
    $sms_body = $msg_en . "\n" . $msg_ar;

    /**
     * Filter: erpgulf_otp_message_text
     * Override the SMS body text immediately before transmission.
     *
     * @param string $sms_body   The combined EN + AR message.
     * @param int    $otp        The 6-digit OTP.
     * @param string $clean_phone The normalised international phone number.
     * @param int    $uid        WordPress user ID (0 if unavailable).
     */
    $sms_body = (string) apply_filters(
        'erpgulf_otp_message_text',
        $sms_body,
        $otp,
        $clean_phone,
        $uid
    );

    $body = [
        'username'   => $user,
        'api_key'    => $key,
        'api_secret' => $secret,
        'from'       => $from,
        'to'         => $clean_phone,
        'text'       => $sms_body,
        'type'       => 'unicode',   // required for Arabic characters
    ];

    $response = wp_remote_post( $url, [
        'timeout'   => 30,
        'sslverify' => true,
        'headers'   => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
        'body'      => $body,
    ] );

    if ( is_wp_error( $response ) ) {
        return [ 'success' => false, 'message' => 'HTTP error: ' . $response->get_error_message() ];
    }

    $http_code = wp_remote_retrieve_response_code( $response );
    $raw       = wp_remote_retrieve_body( $response );

    if ( $http_code !== 200 ) {
        return [
            'success' => false,
            'message' => "Gateway returned HTTP {$http_code}. Body: " . esc_html( $raw ),
        ];
    }

    $res = json_decode( $raw, true );

    if ( isset( $res['Status'] ) && (int) $res['Status'] === 0 ) {
        return [ 'success' => true ];
    }

    $gateway_msg = $res['ErrorMessage'] ?? ( $res['Message'] ?? $raw );
    return [
        'success' => false,
        'message' => 'Gateway error: ' . esc_html( $gateway_msg )
                   . ' — number sent: ' . esc_html( $clean_phone ),
    ];
}
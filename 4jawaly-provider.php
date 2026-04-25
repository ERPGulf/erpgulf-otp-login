<?php
/**
 * SMS Provider: 4jawaly (جوالي)
 * Major Saudi Arabia SMS gateway — https://4jawaly.com
 *
 * Called by erpgulf-otp-login.php
 * Returns: [ 'success' => bool, 'message' => string (on failure) ]
 *
 * ── HOW TO ADD THIS PROVIDER ────────────────────────────────────
 * 1. Drop this file into your plugin folder alongside the others.
 * 2. Add to erpgulf-otp-login.php near the top:
 *      require_once plugin_dir_path(__FILE__) . '4jawaly-provider.php';
 * 3. In erpgulf_handle_send_otp() replace the SMS call with:
 *      $r = erpgulf_send_sms_4jawaly($phone, $otp, [
 *          'app_key'    => get_option('erpgulf_4j_app_key'),
 *          'app_secret' => get_option('erpgulf_4j_app_secret'),
 *          'sender'     => get_option('erpgulf_4j_sender'),
 *          'number_iso' => get_option('erpgulf_4j_number_iso', 'SA'),
 *          'message_en' => $msg_sms_en,
 *          'message_ar' => $msg_sms_ar,
 *          'user_id'    => $user->ID,
 *      ]);
 * 4. Add the settings fields (see bottom of this file).
 * ─────────────────────────────────────────────────────────────────
 *
 * ── 4JAWALY API REFERENCE ────────────────────────────────────────
 * Endpoint:  POST https://api-sms.4jawaly.com/api/v1/account/area/sms/send
 * Auth:      Basic — base64(app_key:app_secret)
 * Payload:
 *   {
 *     "messages": [{
 *       "text":       "Your message",
 *       "numbers":    ["966501234567"],
 *       "number_iso": "SA",
 *       "sender":     "YourSender"
 *     }]
 *   }
 * Success:   HTTP 200 with is_success = 1 in response
 * ─────────────────────────────────────────────────────────────────
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Send an OTP SMS via 4jawaly.
 *
 * @param string $phone     Normalised international digits (e.g. 966501234567)
 * @param int    $otp       The 6-digit OTP
 * @param array  $settings {
 *     @type string app_key     4jawaly application key
 *     @type string app_secret  4jawaly application secret
 *     @type string sender      Approved sender name (e.g. "MRKBATX")
 *     @type string number_iso  ISO country code — default "SA" (Saudi Arabia)
 *                              Common values: SA, QA, AE, KW, BH, OM, EG, JO
 *     @type string message_en  Resolved English message from template
 *     @type string message_ar  Resolved Arabic message from template
 *     @type int    user_id     WordPress user ID (passed to the SMS text filter)
 * }
 */
function erpgulf_send_sms_4jawaly( string $phone, int $otp, array $settings ): array {

    // ── Credentials ───────────────────────────────────────────────
    $app_key    = ! empty( $settings['app_key'] )    ? trim( $settings['app_key'] )    : '';
    $app_secret = ! empty( $settings['app_secret'] ) ? trim( $settings['app_secret'] ) : '';
    $sender     = ! empty( $settings['sender'] )     ? trim( $settings['sender'] )     : '';
    $number_iso = ! empty( $settings['number_iso'] ) ? strtoupper( trim( $settings['number_iso'] ) ) : 'SA';
    $user_id    = ! empty( $settings['user_id'] )    ? intval( $settings['user_id'] )  : 0;

    if ( empty( $app_key ) || empty( $app_secret ) ) {
        return [ 'success' => false, 'message' => '4jawaly: app_key and app_secret are required.' ];
    }
    if ( empty( $sender ) ) {
        return [ 'success' => false, 'message' => '4jawaly: sender name is required.' ];
    }
    if ( strlen( $phone ) < 7 ) {
        return [ 'success' => false, 'message' => "4jawaly: phone number too short ({$phone})." ];
    }

    // ── Build message text ────────────────────────────────────────
    // Start with the bilingual template (EN + AR on two lines)
    $msg_en   = ! empty( $settings['message_en'] ) ? trim( $settings['message_en'] ) : "Your verification code is: {$otp}. Valid for 5 minutes.";
    $msg_ar   = ! empty( $settings['message_ar'] ) ? trim( $settings['message_ar'] ) : "رمز التحقق: {$otp}. صالح لمدة 5 دقائق.";
    $sms_text = $msg_en . "\n" . $msg_ar;

    /**
     * Filter: erpgulf_otp_message_text
     * Allows overriding the SMS body text for this provider exactly
     * as it works with ExpertTexting — consistent across all providers.
     *
     * @param string $sms_text  Bilingual EN + AR message
     * @param int    $otp       The 6-digit OTP
     * @param string $phone     Normalised international phone number
     * @param int    $user_id   WordPress user ID
     */
    $sms_text = (string) apply_filters(
        'erpgulf_otp_message_text',
        $sms_text,
        $otp,
        $phone,
        $user_id
    );

    // ── Build Basic Auth token ────────────────────────────────────
    // Same method as Python: base64( app_key:app_secret )
    $token   = $app_key . ':' . $app_secret;
    $encoded = base64_encode( $token );

    // ── Build payload ─────────────────────────────────────────────
    $payload = [
        'messages' => [
            [
                'text'       => $sms_text,
                'numbers'    => [ $phone ],   // array of numbers
                'number_iso' => $number_iso,  // e.g. "SA"
                'sender'     => $sender,      // approved sender name
            ],
        ],
    ];

    // ── Send the request ──────────────────────────────────────────
    $response = wp_remote_post(
        'https://api-sms.4jawaly.com/api/v1/account/area/sms/send',
        [
            'timeout'   => 30,
            'sslverify' => true,
            'headers'   => [
                'Authorization' => 'Basic ' . $encoded,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body' => wp_json_encode( $payload ),
        ]
    );

    // ── Handle network errors ─────────────────────────────────────
    if ( is_wp_error( $response ) ) {
        return [
            'success' => false,
            'message' => '4jawaly HTTP error: ' . $response->get_error_message(),
        ];
    }

    $http_code = wp_remote_retrieve_response_code( $response );
    $raw       = wp_remote_retrieve_body( $response );
    $res       = json_decode( $raw, true );

    // ── Parse response ────────────────────────────────────────────
    // 4jawaly returns HTTP 200 with is_success = 1 on success
    if ( $http_code === 200 ) {
        // Check top-level success flag
        if ( isset( $res['is_success'] ) && (int) $res['is_success'] === 1 ) {
            return [ 'success' => true ];
        }

        // Check message-level status (some versions nest it)
        if ( ! empty( $res['messages'][0]['is_success'] ) &&
             (int) $res['messages'][0]['is_success'] === 1 ) {
            return [ 'success' => true ];
        }

        // Sent but marked as error — extract reason
        $reason = $res['message']
            ?? $res['messages'][0]['message']
            ?? $res['err_text']
            ?? $raw;

        return [
            'success' => false,
            'message' => '4jawaly gateway error: ' . esc_html( $reason ),
        ];
    }

    // Non-200 HTTP code
    $reason = $res['message'] ?? $raw;
    return [
        'success' => false,
        'message' => "4jawaly returned HTTP {$http_code}: " . esc_html( $reason ),
    ];
}

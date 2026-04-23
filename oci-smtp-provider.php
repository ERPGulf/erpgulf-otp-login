<?php
/**
 * Email Provider: OCI SMTP — Oracle Cloud Infrastructure (Jeddah, me-jeddah-1)
 *
 * Called by erpgulf-otp-login.php
 * Returns: [ 'success' => bool, 'message' => string (on failure) ]
 *
 * ── OCI "Authorization failed: Envelope From not authorized" ─────────────────
 *   1. OCI Console → Email Delivery → Approved Senders → add the exact address
 *      saved in the plugin "Approved Sender" field.
 *   2. Publish SPF & DKIM DNS records for the sender domain.
 *   3. Use port 587 with STARTTLS (not 465).
 * ─────────────────────────────────────────────────────────────────────────────
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function erpgulf_send_email_oci( string $email, int $otp, array $settings ): array {

    $host = ! empty( $settings['host'] ) ? trim( $settings['host'] ) : 'smtp.email.me-jeddah-1.oci.oraclecloud.com';
    $port = ! empty( $settings['port'] ) ? intval( $settings['port'] ) : 587;
    $user = ! empty( $settings['user'] ) ? trim( $settings['user'] ) : '';
    $pass = ! empty( $settings['pass'] ) ? trim( $settings['pass'] ) : '';
    $from = ! empty( $settings['from'] ) ? trim( $settings['from'] ) : '';

    if ( empty( $user ) || empty( $pass ) ) {
        return [ 'success' => false, 'message' => 'OCI SMTP credentials (user / pass) are missing.' ];
    }
    if ( empty( $from ) || ! is_email( $from ) ) {
        return [
            'success' => false,
            'message' => 'OCI approved sender email is missing or invalid. '
                       . 'Set it in ERPGulf OTP → Email Settings → Approved Sender.',
        ];
    }

    // ── Message content from templates ───────────────────────────
    $subject = ! empty( $settings['subject'] )
        ? trim( $settings['subject'] )
        : "Your verification code: {$otp}";

    $msg_en = ! empty( $settings['message_en'] )
        ? trim( $settings['message_en'] )
        : "Your verification code is {$otp}. Valid for 5 minutes.";

    $msg_ar = ! empty( $settings['message_ar'] )
        ? trim( $settings['message_ar'] )
        : "رمز التحقق الخاص بك هو {$otp}. صالح لمدة 5 دقائق.";

    // ── SMTP setup ────────────────────────────────────────────────
    $smtp_error = '';

    $set_smtp = function ( $phpmailer ) use ( $host, $port, $user, $pass, $from ) {
        $phpmailer->isSMTP();
        $phpmailer->Host       = $host;
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Port       = $port;
        $phpmailer->Username   = $user;
        $phpmailer->Password   = $pass;
        $phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $phpmailer->From       = $from;
        $phpmailer->Sender     = $from;   // MAIL FROM envelope — must match OCI approved sender
        $phpmailer->FromName   = 'ERPGulf Security';
        $phpmailer->Timeout    = 15;
        $phpmailer->SMTPDebug  = 0;
    };

    $on_fail = function ( $wp_error ) use ( &$smtp_error ) {
        $smtp_error = $wp_error->get_error_message();
    };

    add_action( 'phpmailer_init', $set_smtp );
    add_action( 'wp_mail_failed',  $on_fail );

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ERPGulf Security <' . $from . '>',
    ];

    $sent = wp_mail( $email, $subject, erpgulf_build_email_body( $otp, $msg_en, $msg_ar ), $headers );

    remove_action( 'phpmailer_init', $set_smtp );
    remove_action( 'wp_mail_failed',  $on_fail );

    if ( $sent ) return [ 'success' => true ];

    // Make the OCI 535 error human-readable
    $friendly = $smtp_error;
    if ( strpos( $smtp_error, 'not authorized' ) !== false || strpos( $smtp_error, '535' ) !== false ) {
        $friendly = "OCI rejected the sender address ({$from}). "
                  . "Go to OCI Console → Email Delivery → Approved Senders and add this exact address. "
                  . "Also confirm SPF/DKIM DNS records are published for the domain. "
                  . "Original error: {$smtp_error}";
    }

    return [
        'success' => false,
        'message' => $friendly ?: 'wp_mail() returned false — check SMTP credentials and firewall.',
    ];
}

/**
 * Builds a bilingual (English + Arabic) HTML email.
 *
 * @param int    $otp     The 6-digit OTP.
 * @param string $msg_en  Resolved English body text.
 * @param string $msg_ar  Resolved Arabic body text.
 */
function erpgulf_build_email_body( int $otp, string $msg_en, string $msg_ar ): string {
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
    <body style="margin:0;padding:0;background:#f4f4f4;font-family:Tahoma,Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 0;">
      <tr><td align="center">
        <table width="520" cellpadding="0" cellspacing="0"
               style="background:#fff;border-radius:8px;border:1px solid #e0e0e0;overflow:hidden;">

          <!-- Header -->
          <tr>
            <td style="background:#007cba;padding:24px 30px;">
              <p style="margin:0;font-size:20px;font-weight:bold;color:#fff;">ERPGulf Security</p>
            </td>
          </tr>

          <!-- OTP block -->
          <tr>
            <td style="padding:30px 30px 0;">
              <p style="margin:0 0 6px;font-size:13px;color:#888;text-transform:uppercase;letter-spacing:1px;">
                Verification Code
              </p>
              <div style="font-size:40px;font-weight:bold;letter-spacing:10px;
                          text-align:center;padding:18px;background:#f4f4f4;
                          border-radius:6px;color:#007cba;margin-bottom:24px;">'
                  . esc_html( $otp ) .
              '</div>
            </td>
          </tr>

          <!-- English message -->
          <tr>
            <td style="padding:0 30px 20px;">
              <p style="margin:0;font-size:15px;color:#333;line-height:1.6;">'
                  . nl2br( esc_html( $msg_en ) ) .
              '</p>
            </td>
          </tr>

          <!-- Divider -->
          <tr>
            <td style="padding:0 30px;">
              <hr style="border:none;border-top:1px solid #eee;margin:0;">
            </td>
          </tr>

          <!-- Arabic message (RTL) -->
          <tr>
            <td style="padding:20px 30px 30px;" dir="rtl">
              <p style="margin:0;font-size:15px;color:#333;line-height:1.8;text-align:right;">'
                  . nl2br( esc_html( $msg_ar ) ) .
              '</p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background:#f9f9f9;padding:14px 30px;border-top:1px solid #eee;">
              <p style="margin:0;font-size:12px;color:#aaa;text-align:center;">
                ERPGulf · Secure Login System &nbsp;|&nbsp;
                <span dir="rtl">نظام تسجيل الدخول الآمن</span>
              </p>
            </td>
          </tr>

        </table>
      </td></tr>
    </table>
    </body>
    </html>';
}
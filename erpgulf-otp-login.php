<?php
/**
 * Plugin Name: ERPGulf Simple OTP
 * Version:     3.6.0
 * Author:      Farook (ERPGulf)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once plugin_dir_path(__FILE__) . 'experttexting-provider.php';

/**
 * 1. ADMIN MENU & SETTINGS
 */
add_action('admin_menu', function() {
    add_menu_page('ERPGulf OTP', 'ERPGulf OTP', 'manage_options', 'erpgulf-otp', 'erpgulf_settings_render', 'dashicons-shield-lock');
});

function erpgulf_settings_render() {
    // SAVE LOGIC - Fixed variable mapping
    if (isset($_POST['save_erpgulf_settings'])) {
        update_option('erpgulf_et_url',        trim(sanitize_text_field($_POST['et_url'])));
        update_option('erpgulf_et_username',   trim(sanitize_text_field($_POST['et_username'])));
        update_option('erpgulf_et_api_key',    trim(sanitize_text_field($_POST['et_api_key'])));
        update_option('erpgulf_et_api_secret', trim(sanitize_text_field($_POST['et_api_secret'])));
        update_option('erpgulf_et_from',       trim(sanitize_text_field($_POST['et_from'])));
        echo '<div class="updated"><p>Settings Saved. Please check that Key and Secret are different!</p></div>';
    }

    $url    = get_option('erpgulf_et_url', 'https://www.experttexting.com/ExptRestApi/sms/json/Message/Send');
    $user   = get_option('erpgulf_et_username');
    $key    = get_option('erpgulf_et_api_key');
    $secret = get_option('erpgulf_et_api_secret');
    $from   = get_option('erpgulf_et_from', 'DEFAULT');

    ?>
    <div class="wrap">
        <h1>ExpertTexting Settings</h1>
        <form method="post">
            <table class="form-table">
                <tr><th>API URL</th><td><input type="text" name="et_url" value="<?php echo esc_attr($url); ?>" class="regular-text"></td></tr>
                <tr><th>Username</th><td><input type="text" name="et_username" value="<?php echo esc_attr($user); ?>" class="regular-text"></td></tr>
                <tr><th>API Key</th><td><input type="text" name="et_api_key" value="<?php echo esc_attr($key); ?>" class="regular-text"></td></tr>
                <tr><th>API Secret</th><td><input type="password" name="et_api_secret" value="<?php echo esc_attr($secret); ?>" class="regular-text"></td></tr>
                <tr><th>From</th><td><input type="text" name="et_from" value="<?php echo esc_attr($from); ?>" class="regular-text"></td></tr>
            </table>
            <input type="submit" name="save_erpgulf_settings" class="button button-primary" value="Save Settings">
        </form>
    </div>
    <?php
}

/**
 * 2. TEST PAGE ROUTING (/otp-test)
 */
add_action('template_redirect', function() {
    if (trim($_SERVER['REQUEST_URI'], '/') == 'otp-test') {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>OTP System Test</title>
            <?php wp_head(); ?>
            <style>
                body { background: #f4f4f4; display: flex; align-items: center; justify-content: center; height: 100vh; font-family: sans-serif; }
                .otp-box { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 320px; }
                input { width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
                button { width: 100%; padding: 10px; background: #007cba; color: #fff; border: none; cursor: pointer; font-weight: bold; }
                #otp-msg { font-size: 13px; margin-top: 15px; text-align: center; font-weight: bold; line-height: 1.4; }
            </style>
        </head>
        <body>
            <div class="otp-box">
                <h3 style="margin-top:0;">ERPGulf OTP Test</h3>
                <div id="otp-step-1">
                    <input type="text" id="phone" placeholder="974XXXXXXXX" />
                    <button id="send">Send OTP</button>
                </div>
                <div id="otp-step-2" style="display:none;">
                    <input type="text" id="code" placeholder="Enter OTP" />
                    <button id="verify">Verify & Login</button>
                </div>
                <p id="otp-msg"></p>
            </div>
            <?php wp_footer(); ?>
            <script>
            jQuery(document).ready(function($) {
                const ajax = "<?php echo admin_url('admin-ajax.php'); ?>";
                let uid = null;
                $('#send').on('click', function() {
                    $('#otp-msg').text('Sending...').css('color', '#666');
                    $.post(ajax, { action: 'erpgulf_send_otp', phone: $('#phone').val() }, function(res) {
                        if(res.success) { uid = res.data.user_id; $('#otp-step-1').hide(); $('#otp-step-2').show(); $('#otp-msg').text('SMS Sent!').css('color', 'green'); }
                        else { $('#otp-msg').html(res.data).css('color', 'red'); }
                    });
                });
                $('#verify').on('click', function() {
                    $.post(ajax, { action: 'erpgulf_verify_otp', user_id: uid, otp: $('#code').val() }, function(res) {
                        if(res.success) { window.location.href = "<?php echo wc_get_page_permalink('myaccount'); ?>"; }
                        else { $('#otp-msg').text(res.data).css('color', 'red'); }
                    });
                });
            });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
});

/**
 * 3. AJAX HANDLERS
 */
add_action('wp_ajax_nopriv_erpgulf_send_otp', 'erpgulf_handle_send_otp');
add_action('wp_ajax_erpgulf_send_otp', 'erpgulf_handle_send_otp');
function erpgulf_handle_send_otp() {
    global $wpdb;
    $phone = sanitize_text_field($_POST['phone']);
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (strpos($clean, '00') === 0) $clean = substr($clean, 2);

    $user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}usermeta WHERE meta_key = 'customer_addresses_0_phone' AND meta_value LIKE %s LIMIT 1", '%' . $wpdb->esc_like($clean) . '%'));
    if (!$user_id) wp_send_json_error("Number not found.");

    $otp = rand(100000, 999999);
    update_user_meta($user_id, 'erpgulf_current_otp', $otp);
    update_user_meta($user_id, 'erpgulf_otp_expiry', time() + 300);

    $set = [
        'url' => get_option('erpgulf_et_url'), 
        'username' => get_option('erpgulf_et_username'),
        'api_key' => get_option('erpgulf_et_api_key'), 
        'api_secret' => get_option('erpgulf_et_api_secret'), 
        'from' => get_option('erpgulf_et_from')
    ];

    $sms = erpgulf_send_sms_experttexting($clean, $otp, $set);
    if ($sms['success']) wp_send_json_success(['user_id' => $user_id]);
    else wp_send_json_error($sms['message']);
}

add_action('wp_ajax_nopriv_erpgulf_verify_otp', 'erpgulf_handle_verify_otp');
add_action('wp_ajax_erpgulf_verify_otp', 'erpgulf_handle_verify_otp');
function erpgulf_handle_verify_otp() {
    $uid = intval($_POST['user_id']); $otp = sanitize_text_field($_POST['otp']);
    if (!$uid) wp_send_json_error('Session lost.');
    $stored = get_user_meta($uid, 'erpgulf_current_otp', true);
    if ($otp == $stored) { delete_user_meta($uid, 'erpgulf_current_otp'); wp_set_auth_cookie($uid, true); wp_send_json_success(); }
    else wp_send_json_error('Invalid Code.');
}
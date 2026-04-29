<?php
/**
 * Plugin Name: ERPGulf Simple OTP
 * Version:     4.6.0
 * Author:      Farook K — https://medium.com/nothing-big
 * Description: Dual-channel OTP (SMS + Email) for WooCommerce.
 *              Username → password login. Email/Phone → OTP login.
 *
 * ═══════════════════════════════════════════════════════════════════
 * USAGE
 * ═══════════════════════════════════════════════════════════════════
 *
 * SHORTCODE  (for the real site page the graphic team designs)
 *   [erpgulf_otp_form]
 *
 * TEST PAGE  (private, for your own testing only)
 *   yoursite.com/otp-test
 *
 * ═══════════════════════════════════════════════════════════════════
 * ADDING A NEW SMS OR EMAIL PROVIDER
 * ═══════════════════════════════════════════════════════════════════
 *
 * 1. Create your provider file  e.g. twilio-provider.php
 *    Function must follow the contract:
 *      erpgulf_send_sms_{name}( string $phone, int $otp, array $settings ): array
 *      returns [ 'success' => true ] or [ 'success' => false, 'message' => '...' ]
 *
 * 2. Add require_once below alongside the existing providers.
 *
 * 3. Add one entry to erpgulf_sms_providers() or erpgulf_email_providers().
 *
 * 4. Go to Admin → ERPGulf OTP → Active Providers → select → Save.
 *
 * No other code changes needed. Ever.
 *
 * ═══════════════════════════════════════════════════════════════════
 * PUBLIC JAVASCRIPT API  —  window.ERPGulfOTP
 * ═══════════════════════════════════════════════════════════════════
 *
 * Available on every front-end page automatically.
 *
 * METHODS
 *   ERPGulfOTP.sendOTP(identifier, onSuccess, onError)
 *   ERPGulfOTP.verifyOTP(userId, code, onSuccess, onError)
 *   ERPGulfOTP.verifyPassword(userId, password, onSuccess, onError)
 *
 * DOM EVENTS  (dispatched on document)
 *   erpgulf:otp:sent       detail: { user_id, method, identifier }
 *   erpgulf:otp:verified   detail: { user_id, method, redirect }
 *   erpgulf:otp:failed     detail: { user_id, submitted_otp }
 *   erpgulf:login:error    detail: { message }
 *
 * ═══════════════════════════════════════════════════════════════════
 * PHP DEVELOPER HOOKS
 * ═══════════════════════════════════════════════════════════════════
 *
 * FILTERS — LOGIN
 *   erpgulf_otp_message_text    ( $message, $otp, $phone, $user_id )
 *   erpgulf_otp_form_html       ( $html )
 *   erpgulf_otp_form_styles     ( $css )
 *   erpgulf_test_page_html      ( $html )
 *   erpgulf_test_page_styles    ( $css )
 *   erpgulf_error_messages      ( $message, $error_type )
 *   erpgulf_redirect_after_login( $url, $user_id )
 *   erpgulf_clean_phone         ( $cleaned_phone, $original_phone )
 *
 * FILTERS — REGISTRATION
 *   erpgulf_register_fields         ( $fields )
 *     Override or extend the registration fields array.
 *     Each field: [ 'label'=>'', 'type'=>'text', 'required'=>true, 'meta_key'=>'' ]
 *
 *   erpgulf_register_form_html      ( $html )
 *     Override the entire registration form HTML.
 *
 *   erpgulf_redirect_after_register ( $url, $user_id )
 *     Where to redirect after successful registration + OTP verification.
 *
 *   erpgulf_validate_registration   ( $errors, $post_data )
 *     Add custom validation. Return $errors array with string messages.
 *     Empty array = valid. Example:
 *       add_filter('erpgulf_validate_registration', function($errors, $data) {
 *           if (strlen($data['password']) < 8)
 *               $errors[] = 'Password must be at least 8 characters.';
 *           return $errors;
 *       }, 10, 2);
 *
 * ACTIONS — REGISTRATION
 *   erpgulf_before_register         ( $post_data )
 *     Fires just before the user account is created.
 *
 *   erpgulf_after_register          ( $user_id, $post_data )
 *     Fires after user created, before OTP is sent.
 *     Use this to save extra meta, assign roles, etc.
 *
 *   erpgulf_register_otp_sent       ( $user_id, $otp, $phone )
 *     Fires after OTP is sent to the new user.
 *
 * ACTIONS — LOGIN
 *   erpgulf_before_save_settings( $post_data )
 *   erpgulf_after_send_otp      ( $user_id, $otp, $phone )
 *   erpgulf_user_logged_in      ( $user_id, $method )
 *   erpgulf_verify_otp_failed   ( $user_id, $submitted_otp, $stored_otp )
 * ═══════════════════════════════════════════════════════════════════
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────────
// LOAD PROVIDERS
// To add a new provider: drop the file here and add require_once.
// ─────────────────────────────────────────────────────────────────

require_once plugin_dir_path(__FILE__) . 'experttexting-provider.php';
require_once plugin_dir_path(__FILE__) . '4jawaly-provider.php';
require_once plugin_dir_path(__FILE__) . 'oci-smtp-provider.php';

// ─────────────────────────────────────────────────────────────────
// PROVIDER REGISTRY
// Add one line here when you add a new provider.
// The admin dropdown builds itself from these arrays.
// ─────────────────────────────────────────────────────────────────

function erpgulf_sms_providers(): array {
    return [
        'experttexting' => 'ExpertTexting',       // ← default
        '4jawaly'       => '4jawaly (جوالي)',
        // 'twilio'     => 'Twilio',              // example — uncomment when ready
    ];
}

function erpgulf_email_providers(): array {
    return [
        'oci'           => 'OCI SMTP (Jeddah)',   // ← default
        // 'sendgrid'   => 'SendGrid',            // example — uncomment when ready
    ];
}

// ─────────────────────────────────────────────────────────────────
// HELPER — filterable error messages
// ─────────────────────────────────────────────────────────────────

function erpgulf_err( string $default, string $error_type ): string {
    return (string) apply_filters( 'erpgulf_error_messages', $default, $error_type );
}

// ─────────────────────────────────────────────────────────────────
// HELPER — default redirect URL
// ─────────────────────────────────────────────────────────────────

function erpgulf_redirect_url( int $user_id = 0 ): string {
    $default = function_exists('wc_get_page_permalink')
        ? wc_get_page_permalink('myaccount')
        : home_url('/my-account/');
    return (string) apply_filters( 'erpgulf_redirect_after_login', $default, $user_id );
}

// ─────────────────────────────────────────────────────────────────
// PUBLIC JAVASCRIPT API — window.ERPGulfOTP
// ─────────────────────────────────────────────────────────────────

add_action('wp_enqueue_scripts', function () {

    wp_register_script( 'erpgulf-otp-api', false, ['jquery'], null, true );
    wp_enqueue_script( 'erpgulf-otp-api' );

    wp_localize_script( 'erpgulf-otp-api', 'ERPGulfOTPConfig', [
        'ajaxUrl'  => admin_url('admin-ajax.php'),
        'redirect' => erpgulf_redirect_url(0),
        'version'  => '4.6.0',
    ]);

    $api_js = <<<'JS'
(function (window, $, cfg) {

    function dispatch(name, detail) {
        document.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: detail || {} }));
    }

    function ajax(data, onSuccess, onError) {
        $.post(cfg.ajaxUrl, data, function (res) {
            if (res.success) {
                onSuccess && onSuccess(res.data || {});
            } else {
                var msg = (typeof res.data === 'string') ? res.data : 'An error occurred.';
                onError && onError(msg);
                dispatch('erpgulf:login:error', { message: msg });
            }
        }).fail(function () {
            var msg = 'Network error. Please try again.';
            onError && onError(msg);
            dispatch('erpgulf:login:error', { message: msg });
        });
    }

    window.ERPGulfOTP = {

        version: cfg.version,

        sendOTP: function (identifier, onSuccess, onError) {
            ajax({ action: 'erpgulf_send_otp', identifier: identifier },
                function (data) {
                    dispatch('erpgulf:otp:sent', {
                        user_id: data.user_id, method: data.method, identifier: identifier
                    });
                    onSuccess && onSuccess(data);
                },
                onError
            );
        },

        verifyOTP: function (userId, code, onSuccess, onError) {
            ajax({ action: 'erpgulf_verify_otp', user_id: userId, method: 'otp', otp: code },
                function (data) {
                    dispatch('erpgulf:otp:verified', {
                        user_id: userId, method: 'otp', redirect: data.redirect || cfg.redirect
                    });
                    onSuccess && onSuccess({ redirect: data.redirect || cfg.redirect });
                },
                function (msg) {
                    dispatch('erpgulf:otp:failed', { user_id: userId, submitted_otp: code });
                    onError && onError(msg);
                }
            );
        },

        verifyPassword: function (userId, password, onSuccess, onError) {
            ajax({ action: 'erpgulf_verify_otp', user_id: userId, method: 'password', password: password },
                function (data) {
                    dispatch('erpgulf:otp:verified', {
                        user_id: userId, method: 'password', redirect: data.redirect || cfg.redirect
                    });
                    onSuccess && onSuccess({ redirect: data.redirect || cfg.redirect });
                },
                onError
            );
        },

        /**
         * Register a new user account and send an OTP to verify.
         *
         * @param {object}   data        { first_name, last_name, email, mobile, password, address }
         * @param {function} onSuccess   Called with { user_id, method: 'register' }
         * @param {function} onError     Called with error message string
         *
         * @fires erpgulf:otp:sent  { user_id, method: 'register', identifier: email }
         */
        register: function (data, onSuccess, onError) {
            var payload = { action: 'erpgulf_register_user' };
            Object.assign(payload, data);
            ajax(payload,
                function (res) {
                    dispatch('erpgulf:otp:sent', {
                        user_id:    res.user_id,
                        method:     'register',
                        identifier: data.email
                    });
                    onSuccess && onSuccess(res);
                },
                onError
            );
        },

        defaultRedirect: cfg.redirect,
    };

})(window, jQuery, ERPGulfOTPConfig);
JS;

    wp_add_inline_script( 'erpgulf-otp-api', $api_js );
});

// ─────────────────────────────────────────────────────────────────
// HELPER — registration fields definition
// ─────────────────────────────────────────────────────────────────

function erpgulf_register_fields(): array {
    $default = [
        'first_name' => [
            'label'    => 'First Name',
            'type'     => 'text',
            'required' => true,
            'meta_key' => 'first_name',
            'autocomplete' => 'given-name',
        ],
        'last_name' => [
            'label'    => 'Last Name',
            'type'     => 'text',
            'required' => true,
            'meta_key' => 'last_name',
            'autocomplete' => 'family-name',
        ],
        'email' => [
            'label'    => 'Email Address',
            'type'     => 'email',
            'required' => true,
            'meta_key' => '',
            'autocomplete' => 'email',
        ],
        'mobile' => [
            'label'    => 'Mobile Number',
            'type'     => 'tel',
            'required' => true,
            'meta_key' => 'customer_addresses_0_phone',
            'autocomplete' => 'tel',
        ],
        'password' => [
            'label'    => 'Password',
            'type'     => 'password',
            'required' => true,
            'meta_key' => '',
            'autocomplete' => 'new-password',
        ],
        'address' => [
            'label'    => 'Address Line 1',
            'type'     => 'text',
            'required' => true,
            'meta_key' => 'billing_address_1',
            'autocomplete' => 'address-line1',
        ],
        'address_2' => [
            'label'    => 'Address Line 2',
            'type'     => 'text',
            'required' => false,
            'meta_key' => 'billing_address_2',
            'autocomplete' => 'address-line2',
        ],
        'city' => [
            'label'    => 'City',
            'type'     => 'text',
            'required' => true,
            'meta_key' => 'billing_city',
            'autocomplete' => 'address-level2',
        ],
        'postcode' => [
            'label'    => 'Postcode / ZIP',
            'type'     => 'text',
            'required' => false,
            'meta_key' => 'billing_postcode',
            'autocomplete' => 'postal-code',
        ],
        'state' => [
            'label'    => 'State / County',
            'type'     => 'text',
            'required' => false,
            'meta_key' => 'billing_state',
            'autocomplete' => 'address-level1',
        ],
    ];
    return (array) apply_filters( 'erpgulf_register_fields', $default );
}

// ─────────────────────────────────────────────────────────────────
// HELPER — registration form HTML
// ─────────────────────────────────────────────────────────────────

function erpgulf_register_form_html(): string {

    $fields = erpgulf_register_fields();
    $inputs = '';
    foreach ( $fields as $key => $field ) {
        $required = $field['required'] ? 'required' : '';
        $label    = esc_html( $field['label'] );
        $type     = esc_attr( $field['type'] );
        $auto     = esc_attr( $field['autocomplete'] ?? 'off' );
        $star     = $field['required'] ? ' *' : '';

        if ( $type === 'select' ) {
            // Generic select — skip rendering (handled elsewhere)
            continue;
        } else {
            $inputs .= "\n<input type=\"{$type}\" id=\"reg-{$key}\" placeholder=\"{$label}{$star}\" autocomplete=\"{$auto}\" {$required} />";
        }
    }

    $html = '
    <div id="reg-step-form">
        ' . $inputs . '
        <button id="btn-register">Create Account &amp; Send OTP</button>
    </div>

    <div id="reg-step-otp" style="display:none;">
        <p class="otp-hint">A 6-digit code has been sent to your email &amp; mobile to verify your account.</p>
        <input type="text"
               id="reg-otp-code"
               placeholder="Enter 6-digit Code"
               maxlength="6"
               autocomplete="one-time-code" />
        <button id="btn-reg-verify-otp">Verify &amp; Complete Registration</button>
    </div>

    <p id="reg-msg"></p>';

    return (string) apply_filters( 'erpgulf_register_form_html', $html );
}

// ─────────────────────────────────────────────────────────────────
// HELPER — shared form HTML (tabbed login + register)
// ─────────────────────────────────────────────────────────────────

function erpgulf_otp_form_html(): string {
    $html = '
    <div class="otp-box">

        <div class="otp-tabs">
            <button class="otp-tab active" data-tab="login">Login</button>
            <button class="otp-tab" data-tab="register">Register</button>
        </div>

        {{!-- LOGIN TAB --}}
        <div class="otp-tab-content" id="tab-login">
            <div id="otp-step-1">
                <input type="text"
                       id="identifier"
                       placeholder="Email, Mobile, or Username"
                       autocomplete="username" />
                <button id="btn-send">Continue</button>
            </div>

            <div id="otp-step-otp" style="display:none;">
                <p class="otp-hint">A 6-digit code has been sent to your registered email &amp; phone.</p>
                <input type="text"
                       id="otp-code"
                       placeholder="Enter 6-digit Code"
                       maxlength="6"
                       autocomplete="one-time-code" />
                <button id="btn-verify-otp">Verify &amp; Login</button>
            </div>

            <div id="otp-step-password" style="display:none;">
                <p class="otp-hint">Enter your password to continue.</p>
                <input type="password"
                       id="password"
                       placeholder="Password"
                       autocomplete="current-password" />
                <button id="btn-verify-password">Login</button>
            </div>

            <p id="otp-msg"></p>
        </div>

        {{!-- REGISTER TAB --}}
        <div class="otp-tab-content" id="tab-register" style="display:none;">
            ' . erpgulf_register_form_html() . '
        </div>
    </div>';

    return (string) apply_filters( 'erpgulf_otp_form_html', $html );
}

// ─────────────────────────────────────────────────────────────────
// HELPER — shared JS
// ─────────────────────────────────────────────────────────────────

function erpgulf_otp_form_js( string $redirect ): string {
    $redir = esc_url( $redirect );
    return "
    <script>
    jQuery(document).ready(function (\$) {

        function msg(text, color) {
            \$('#otp-msg').html(text).css('color', color || '#333');
        }

        \$('#btn-send').on('click', function () {
            var id = \$.trim(\$('#identifier').val());
            if (!id) { msg('Please enter your email, mobile, or username.', 'red'); return; }
            msg('Looking up your account\u2026', '#666');
            \$(this).prop('disabled', true);
            ERPGulfOTP.sendOTP(id, function (data) {
                \$('#btn-send').prop('disabled', false);
                \$('#otp-step-1').hide();
                if (data.method === 'password') {
                    \$('#otp-step-password').show(); \$('#password').focus(); msg('', '');
                } else {
                    \$('#otp-step-otp').show(); \$('#otp-code').focus();
                    msg('Code sent! Check your email and/or phone.', 'green');
                }
            }, function (err) {
                \$('#btn-send').prop('disabled', false);
                msg(err, 'red');
            });
        });

        \$('#btn-verify-otp').on('click', function () {
            var code = \$.trim(\$('#otp-code').val());
            if (!code) { msg('Please enter the verification code.', 'red'); return; }
            msg('Verifying\u2026', '#666'); \$(this).prop('disabled', true);
            ERPGulfOTP.verifyOTP(window._erpgulf_uid, code, function (data) {
                \$('#btn-verify-otp').prop('disabled', false);
                msg('\u2705 Verified! Redirecting\u2026', 'green');
                setTimeout(function () { window.location.href = data.redirect || '{$redir}'; }, 800);
            }, function (err) {
                \$('#btn-verify-otp').prop('disabled', false);
                msg(err, 'red');
            });
        });

        \$('#btn-verify-password').on('click', function () {
            var pw = \$('#password').val();
            if (!pw) { msg('Please enter your password.', 'red'); return; }
            msg('Logging in\u2026', '#666'); \$(this).prop('disabled', true);
            ERPGulfOTP.verifyPassword(window._erpgulf_uid, pw, function (data) {
                \$('#btn-verify-password').prop('disabled', false);
                msg('\u2705 Login successful! Redirecting\u2026', 'green');
                setTimeout(function () { window.location.href = data.redirect || '{$redir}'; }, 800);
            }, function (err) {
                \$('#btn-verify-password').prop('disabled', false);
                msg(err, 'red');
            });
        });

        document.addEventListener('erpgulf:otp:sent', function (e) {
            window._erpgulf_uid = e.detail.user_id;
        });

        // ── Tab switching ─────────────────────────────────────────
        \$('.otp-tab').on('click', function() {
            var tab = \$(this).data('tab');
            \$('.otp-tab').removeClass('active');
            \$(this).addClass('active');
            \$('.otp-tab-content').hide();
            \$('#tab-' + tab).show();
        });

        // ── Registration ──────────────────────────────────────────
        function regMsg(text, color) {
            \$('#reg-msg').html(text).css('color', color || '#333');
        }

        \$('#btn-register').on('click', function() {
            var data = {
                first_name: \$.trim(\$('#reg-first_name').val()),
                last_name:  \$.trim(\$('#reg-last_name').val()),
                email:      \$.trim(\$('#reg-email').val()),
                mobile:     \$.trim(\$('#reg-mobile').val()),
                password:   \$('#reg-password').val(),
                address:    \$.trim(\$('#reg-address').val()),
                address_2:  \$.trim(\$('#reg-address_2').val()),
                city:       \$.trim(\$('#reg-city').val()),
                postcode:   \$.trim(\$('#reg-postcode').val()),
                state:      \$.trim(\$('#reg-state').val()),
            };

            // Basic client-side validation
            for (var k in data) {
                if (!data[k]) {
                    regMsg('Please fill in all required fields.', 'red');
                    return;
                }
            }

            regMsg('Creating your account\u2026', '#666');
            \$(this).prop('disabled', true);

            ERPGulfOTP.register(data, function(res) {
                \$('#btn-register').prop('disabled', false);
                \$('#reg-step-form').hide();
                \$('#reg-step-otp').show();
                \$('#reg-otp-code').focus();
                regMsg('Account created! Enter the code sent to your mobile and email.', 'green');
            }, function(err) {
                \$('#btn-register').prop('disabled', false);
                regMsg(err, 'red');
            });
        });

        \$('#btn-reg-verify-otp').on('click', function() {
            var code = \$.trim(\$('#reg-otp-code').val());
            if (!code) { regMsg('Please enter the verification code.', 'red'); return; }
            regMsg('Verifying\u2026', '#666');
            \$(this).prop('disabled', true);

            ERPGulfOTP.verifyOTP(window._erpgulf_uid, code, function(data) {
                \$('#btn-reg-verify-otp').prop('disabled', false);
                regMsg('\u2705 Verified! Redirecting\u2026', 'green');
                setTimeout(function() { window.location.href = data.redirect || '{$redir}'; }, 800);
            }, function(err) {
                \$('#btn-reg-verify-otp').prop('disabled', false);
                regMsg(err, 'red');
            });
        });

        \$('#reg-otp-code').on('keypress', function(e) { if(e.which===13) \$('#btn-reg-verify-otp').trigger('click'); });

        \$('#identifier').on('keypress', function (e) { if (e.which===13) \$('#btn-send').trigger('click'); });
        \$('#otp-code').on('keypress',   function (e) { if (e.which===13) \$('#btn-verify-otp').trigger('click'); });
        \$('#password').on('keypress',   function (e) { if (e.which===13) \$('#btn-verify-password').trigger('click'); });
    });
    </script>";
}

// ─────────────────────────────────────────────────────────────────
// HELPER — baseline CSS
// ─────────────────────────────────────────────────────────────────

function erpgulf_otp_base_css(): string {
    return '
        .otp-box { background:#fff; padding:30px; border-radius:8px; width:100%; max-width:380px; margin:0 auto; box-sizing:border-box; }
        .otp-tabs { display:flex; margin-bottom:20px; border-bottom:2px solid #eee; }
        .otp-tab { flex:1; padding:10px; background:none; border:none; cursor:pointer; font-size:15px; font-weight:bold; color:#999; font-family:sans-serif; border-bottom:2px solid transparent; margin-bottom:-2px; }
        .otp-tab.active { color:#007cba; border-bottom-color:#007cba; }
        .otp-tab:hover { color:#007cba; }
        .otp-box input[type="text"],
        .otp-box input[type="email"],
        .otp-box input[type="tel"],
        .otp-box input[type="password"],
        .otp-box select { width:100%; padding:12px; margin-bottom:10px; border:1px solid #ccc; border-radius:4px; font-size:14px; box-sizing:border-box; font-family:sans-serif; background:#fff; }
        .otp-box button.otp-tab { padding:10px; }
        .otp-box button:not(.otp-tab) { width:100%; padding:12px; background:#007cba; color:#fff; border:none; cursor:pointer; font-weight:bold; border-radius:4px; font-size:14px; font-family:sans-serif; margin-top:4px; }
        .otp-box button:not(.otp-tab):hover    { background:#005f8d; }
        .otp-box button:not(.otp-tab):disabled { background:#aaa; cursor:not-allowed; }
        .otp-hint { font-size:12px; color:#666; text-align:center; margin-bottom:12px; font-family:sans-serif; }
        #otp-msg, #reg-msg { font-size:13px; margin-top:12px; text-align:center; font-weight:bold; line-height:1.5; min-height:20px; font-family:sans-serif; }
    ';
}

// ─────────────────────────────────────────────────────────────────
// 1. SHORTCODE — [erpgulf_otp_form]
// ─────────────────────────────────────────────────────────────────

add_shortcode( 'erpgulf_otp_form', function () {
    wp_enqueue_script('jquery');
    $css = (string) apply_filters( 'erpgulf_otp_form_styles', erpgulf_otp_base_css() );
    wp_register_style( 'erpgulf-otp-form', false );
    wp_enqueue_style( 'erpgulf-otp-form' );
    wp_add_inline_style( 'erpgulf-otp-form', $css );
    $redirect = erpgulf_redirect_url( get_current_user_id() );
    return erpgulf_otp_form_html() . erpgulf_otp_form_js( $redirect );
});

// ─────────────────────────────────────────────────────────────────
// 2. ADMIN MENU & SETTINGS
// ─────────────────────────────────────────────────────────────────

add_action('admin_menu', function () {
    add_menu_page(
        'ERPGulf OTP', 'ERPGulf OTP', 'manage_options',
        'erpgulf-otp', 'erpgulf_settings_render', 'dashicons-shield-lock'
    );
});

function erpgulf_settings_render() {

    if ( isset($_POST['save_erpgulf_settings']) && check_admin_referer('erpgulf_save_settings') ) {

        do_action( 'erpgulf_before_save_settings', $_POST );

        // Active providers
        update_option('erpgulf_active_sms_provider',   sanitize_key($_POST['active_sms_provider']));
        update_option('erpgulf_active_email_provider',  sanitize_key($_POST['active_email_provider']));

        // ExpertTexting
        update_option('erpgulf_et_username',     trim(sanitize_text_field($_POST['et_username'])));
        update_option('erpgulf_et_api_key',      trim(sanitize_text_field($_POST['et_api_key'])));
        update_option('erpgulf_et_api_secret',   trim(sanitize_text_field($_POST['et_api_secret'])));
        update_option('erpgulf_et_country_code', trim(sanitize_text_field($_POST['et_country_code'])));

        // 4jawaly
        update_option('erpgulf_4j_app_key',    trim(sanitize_text_field($_POST['4j_app_key'])));
        update_option('erpgulf_4j_app_secret', trim(sanitize_text_field($_POST['4j_app_secret'])));
        update_option('erpgulf_4j_sender',     trim(sanitize_text_field($_POST['4j_sender'])));
        update_option('erpgulf_4j_number_iso', strtoupper(trim(sanitize_text_field($_POST['4j_number_iso']))));

        // OCI Email
        update_option('erpgulf_oci_host', trim(sanitize_text_field($_POST['oci_host'])));
        update_option('erpgulf_oci_port', trim(sanitize_text_field($_POST['oci_port'])));
        update_option('erpgulf_oci_user', trim(sanitize_textarea_field($_POST['oci_user'])));
        update_option('erpgulf_oci_pass', trim($_POST['oci_pass']));
        update_option('erpgulf_oci_from', trim(sanitize_email($_POST['oci_from'])));

        // Message templates
        update_option('erpgulf_msg_sms_en',    trim(sanitize_textarea_field($_POST['msg_sms_en'])));
        update_option('erpgulf_msg_sms_ar',    trim(sanitize_textarea_field($_POST['msg_sms_ar'])));
        update_option('erpgulf_msg_email_sub', trim(sanitize_text_field($_POST['msg_email_sub'])));
        update_option('erpgulf_msg_email_en',  trim(sanitize_textarea_field($_POST['msg_email_en'])));
        update_option('erpgulf_msg_email_ar',  trim(sanitize_textarea_field($_POST['msg_email_ar'])));

        echo '<div class="updated"><p>✅ Settings saved.</p></div>';
    }

    // Current selections — ExpertTexting is default SMS, OCI is default Email
    $active_sms   = get_option('erpgulf_active_sms_provider',   'experttexting');
    $active_email = get_option('erpgulf_active_email_provider',  'oci');

    $site          = get_bloginfo('name');
    $def_sms_en    = "The OTP for your {site} account login is {otp}. Valid for 5 minutes. Do not share this code.";
    $def_sms_ar    = "رمز التحقق (OTP) الخاص بتسجيل الدخول إلى حساب {site} هو {otp}. صالح لمدة 5 دقائق. لا تشارك هذا الرمز.";
    $def_email_sub = "Your {site} verification code: {otp}";
    $def_email_en  = "The OTP for your {site} account login is {otp}. Valid for 5 minutes. Do not share this code.";
    $def_email_ar  = "رمز التحقق (OTP) الخاص بتسجيل الدخول إلى حساب {site} هو {otp}. صالح لمدة 5 دقائق. لا تشارك هذا الرمز.";
    ?>

    <div class="wrap">
        <h1>ERPGulf OTP Configuration</h1>

        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:14px 18px;margin-bottom:24px;">
            <strong>📋 Shortcode:</strong>
            <code style="font-size:15px;margin-left:10px;">[erpgulf_otp_form]</code>
            &nbsp;&nbsp;
            <strong>🌐 JS API:</strong>
            <code style="font-size:15px;margin-left:10px;">window.ERPGulfOTP</code>
            &nbsp;&nbsp;
            <strong>🧪 Test:</strong>
            <code style="font-size:15px;margin-left:10px;"><?php echo esc_html(home_url('/otp-test')); ?></code>
        </div>

        <form method="post">
            <?php wp_nonce_field('erpgulf_save_settings'); ?>

            {{!-- ── Active Providers selector ── --}}
            <div style="background:#e8f4fd;padding:20px;border:2px solid #007cba;border-radius:4px;margin-bottom:24px;">
                <h3 style="margin-top:0;">⚙️ Active Providers</h3>
                <p style="color:#555;font-size:13px;margin-top:0;">
                    Select which provider handles SMS and Email. All providers below are saved — only the selected ones are used.
                </p>
                <table class="form-table" style="margin-top:0;">
                    <tr>
                        <th style="width:200px;">Active SMS Provider</th>
                        <td>
                            <select name="active_sms_provider" style="min-width:220px;font-size:14px;">
                                <?php foreach ( erpgulf_sms_providers() as $key => $label ): ?>
                                    <option value="<?php echo esc_attr($key); ?>"
                                        <?php selected( $active_sms, $key ); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                Currently active: <strong><?php echo esc_html( erpgulf_sms_providers()[$active_sms] ?? $active_sms ); ?></strong>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>Active Email Provider</th>
                        <td>
                            <select name="active_email_provider" style="min-width:220px;font-size:14px;">
                                <?php foreach ( erpgulf_email_providers() as $key => $label ): ?>
                                    <option value="<?php echo esc_attr($key); ?>"
                                        <?php selected( $active_email, $key ); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                Currently active: <strong><?php echo esc_html( erpgulf_email_providers()[$active_email] ?? $active_email ); ?></strong>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            {{!-- ── ExpertTexting credentials ── --}}
            <div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;margin-bottom:20px;">
                <h3 style="margin-top:0;">
                    📱 SMS — ExpertTexting
                    <?php if ( $active_sms === 'experttexting' ): ?>
                        <span style="font-size:12px;font-weight:normal;background:#007cba;color:#fff;padding:2px 8px;border-radius:10px;margin-left:8px;">Active</span>
                    <?php endif; ?>
                </h3>
                <table class="form-table" style="margin-top:0;">
                    <tr><th style="width:200px;">Username</th><td><input type="text" name="et_username" value="<?php echo esc_attr(get_option('erpgulf_et_username')); ?>" class="regular-text"></td></tr>
                    <tr><th>API Key</th><td><input type="text" name="et_api_key" value="<?php echo esc_attr(get_option('erpgulf_et_api_key')); ?>" class="regular-text"></td></tr>
                    <tr><th>API Secret</th><td><input type="password" name="et_api_secret" value="<?php echo esc_attr(get_option('erpgulf_et_api_secret')); ?>" class="regular-text"></td></tr>
                    <tr>
                        <th>Default Country Code</th>
                        <td>
                            <input type="text" name="et_country_code" value="<?php echo esc_attr(get_option('erpgulf_et_country_code', '966')); ?>" class="small-text" maxlength="5" placeholder="966">
                            <p class="description">Digits only. <strong>966</strong> Saudi Arabia &nbsp;·&nbsp; <strong>974</strong> Qatar &nbsp;·&nbsp; <strong>971</strong> UAE &nbsp;·&nbsp; <strong>91</strong> India &nbsp;·&nbsp; <strong>1</strong> USA</p>
                        </td>
                    </tr>
                </table>
            </div>

            {{!-- ── 4jawaly credentials ── --}}
            <div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;margin-bottom:20px;">
                <h3 style="margin-top:0;">
                    📱 SMS — 4jawaly (جوالي)
                    <?php if ( $active_sms === '4jawaly' ): ?>
                        <span style="font-size:12px;font-weight:normal;background:#007cba;color:#fff;padding:2px 8px;border-radius:10px;margin-left:8px;">Active</span>
                    <?php endif; ?>
                </h3>
                <p style="color:#666;font-size:13px;margin-top:0;">
                    Saudi Arabia SMS gateway — <a href="https://4jawaly.com" target="_blank">4jawaly.com</a>
                </p>
                <table class="form-table" style="margin-top:0;">
                    <tr>
                        <th style="width:200px;">App Key</th>
                        <td><input type="text" name="4j_app_key" value="<?php echo esc_attr(get_option('erpgulf_4j_app_key')); ?>" class="regular-text" placeholder="h3NF51eDNq..."></td>
                    </tr>
                    <tr>
                        <th>App Secret</th>
                        <td><input type="password" name="4j_app_secret" value="<?php echo esc_attr(get_option('erpgulf_4j_app_secret')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Sender Name</th>
                        <td>
                            <input type="text" name="4j_sender" value="<?php echo esc_attr(get_option('erpgulf_4j_sender')); ?>" class="regular-text" placeholder="MRKBATX">
                            <p class="description">Must be an approved sender name in your 4jawaly account.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Country ISO Code</th>
                        <td>
                            <input type="text" name="4j_number_iso" value="<?php echo esc_attr(get_option('erpgulf_4j_number_iso', 'SA')); ?>" class="small-text" maxlength="3" placeholder="SA">
                            <p class="description">
                                <strong>SA</strong> Saudi Arabia &nbsp;·&nbsp;
                                <strong>QA</strong> Qatar &nbsp;·&nbsp;
                                <strong>AE</strong> UAE &nbsp;·&nbsp;
                                <strong>KW</strong> Kuwait &nbsp;·&nbsp;
                                <strong>BH</strong> Bahrain &nbsp;·&nbsp;
                                <strong>OM</strong> Oman
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            {{!-- ── OCI Email credentials ── --}}
            <div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;margin-bottom:20px;">
                <h3 style="margin-top:0;">
                    📧 Email — OCI SMTP (Jeddah)
                    <?php if ( $active_email === 'oci' ): ?>
                        <span style="font-size:12px;font-weight:normal;background:#007cba;color:#fff;padding:2px 8px;border-radius:10px;margin-left:8px;">Active</span>
                    <?php endif; ?>
                </h3>
                <table class="form-table" style="margin-top:0;">
                    <tr><th style="width:200px;">SMTP Host</th><td><input type="text" name="oci_host" value="<?php echo esc_attr(get_option('erpgulf_oci_host', 'smtp.email.me-jeddah-1.oci.oraclecloud.com')); ?>" class="regular-text"></td></tr>
                    <tr><th>Port</th><td><input type="text" name="oci_port" value="<?php echo esc_attr(get_option('erpgulf_oci_port', '587')); ?>" class="small-text"> <span class="description">Use 587 (STARTTLS)</span></td></tr>
                    <tr><th>SMTP User (OCID)</th><td><textarea name="oci_user" rows="3" style="width:100%;max-width:500px;font-family:monospace;"><?php echo esc_textarea(get_option('erpgulf_oci_user')); ?></textarea></td></tr>
                    <tr><th>SMTP Password</th><td><input type="password" name="oci_pass" value="<?php echo esc_attr(get_option('erpgulf_oci_pass')); ?>" class="regular-text"></td></tr>
                    <tr>
                        <th>Approved Sender</th>
                        <td>
                            <input type="email" name="oci_from" value="<?php echo esc_attr(get_option('erpgulf_oci_from', 'support@erpgulf.com')); ?>" class="regular-text">
                            <p class="description">Must be registered in OCI Console → Email Delivery → Approved Senders.</p>
                        </td>
                    </tr>
                </table>
            </div>

            {{!-- ── Message Templates ── --}}
            <div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;margin-bottom:20px;">
                <h3 style="margin-top:0;">✏️ Message Templates</h3>
                <p style="background:#f0f6fc;border-left:4px solid #007cba;padding:10px 14px;margin-bottom:20px;">
                    <strong>Placeholders:</strong> &nbsp;
                    <code>{otp}</code> — verification code &nbsp;·&nbsp;
                    <code>{site}</code> — site name (<em><?php echo esc_html($site); ?></em>)
                </p>
                <h4 style="border-bottom:1px solid #eee;padding-bottom:8px;">SMS</h4>
                <table class="form-table" style="margin-top:0;">
                    <tr><th style="width:200px;">English 🇬🇧</th><td><textarea name="msg_sms_en" rows="3" class="large-text"><?php echo esc_textarea(get_option('erpgulf_msg_sms_en', $def_sms_en)); ?></textarea></td></tr>
                    <tr><th>Arabic 🇸🇦</th><td><textarea name="msg_sms_ar" rows="3" class="large-text" dir="rtl" style="font-family:Tahoma,Arial,sans-serif;"><?php echo esc_textarea(get_option('erpgulf_msg_sms_ar', $def_sms_ar)); ?></textarea></td></tr>
                </table>
                <h4 style="border-bottom:1px solid #eee;padding-bottom:8px;margin-top:24px;">Email</h4>
                <table class="form-table" style="margin-top:0;">
                    <tr><th style="width:200px;">Subject line</th><td><input type="text" name="msg_email_sub" value="<?php echo esc_attr(get_option('erpgulf_msg_email_sub', $def_email_sub)); ?>" class="large-text"></td></tr>
                    <tr><th>Body — English 🇬🇧</th><td><textarea name="msg_email_en" rows="3" class="large-text"><?php echo esc_textarea(get_option('erpgulf_msg_email_en', $def_email_en)); ?></textarea></td></tr>
                    <tr><th>Body — Arabic 🇸🇦</th><td><textarea name="msg_email_ar" rows="3" class="large-text" dir="rtl" style="font-family:Tahoma,Arial,sans-serif;"><?php echo esc_textarea(get_option('erpgulf_msg_email_ar', $def_email_ar)); ?></textarea></td></tr>
                </table>
            </div>

            <p class="submit"><input type="submit" name="save_erpgulf_settings" class="button button-primary" value="Save All Settings"></p>
        </form>
    </div>
    <?php
}

// ─────────────────────────────────────────────────────────────────
// 3. PHONE LOOKUP
// ─────────────────────────────────────────────────────────────────

function erpgulf_find_user_by_phone( string $raw_input, int $match_digits = 7 ): ?WP_User {
    global $wpdb;

    $input_digits = preg_replace( '/[^0-9]/', '', $raw_input );
    if ( strlen( $input_digits ) < 4 ) return null;
    if ( strlen( $input_digits ) < $match_digits ) $match_digits = strlen( $input_digits );

    $input_suffix = (string) apply_filters(
        'erpgulf_clean_phone',
        substr( $input_digits, -$match_digits ),
        $raw_input
    );

    $rows = $wpdb->get_results(
        "SELECT user_id, meta_value FROM {$wpdb->prefix}usermeta
         WHERE meta_key = 'customer_addresses_0_phone' AND meta_value != ''"
    );

    if ( empty( $rows ) ) return null;

    foreach ( $rows as $row ) {
        $stored_digits = preg_replace( '/[^0-9]/', '', $row->meta_value );
        if ( strlen( $stored_digits ) < 4 ) continue;
        $effective = min( $match_digits, strlen( $stored_digits ) );
        if ( $effective < 4 ) continue;
        if ( substr( $stored_digits, -$effective ) !== substr( $input_suffix, -$effective ) ) continue;
        $user = get_userdata( (int) $row->user_id );
        if ( $user ) return $user;
    }
    return null;
}

// ─────────────────────────────────────────────────────────────────
// 4. HELPER — resolve placeholders
// ─────────────────────────────────────────────────────────────────

function erpgulf_resolve_template( string $template, int $otp ): string {
    return str_replace( [ '{otp}', '{site}' ], [ $otp, get_bloginfo('name') ], $template );
}

// ─────────────────────────────────────────────────────────────────
// 5. TEST PAGE  →  /otp-test
// ─────────────────────────────────────────────────────────────────

add_action('template_redirect', function () {
    if ( trim($_SERVER['REQUEST_URI'], '/') !== 'otp-test' ) return;

    $redirect  = erpgulf_redirect_url(0);
    $styles    = (string) apply_filters( 'erpgulf_test_page_styles',
        erpgulf_otp_base_css() . '
        body { background:#f4f4f4; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
        .otp-box { box-shadow:0 4px 15px rgba(0,0,0,.1); }
    ' );
    $form_html = (string) apply_filters( 'erpgulf_test_page_html', erpgulf_otp_form_html() );
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>ERPGulf OTP — Test Page</title>
        <?php wp_head(); ?>
        <style><?php echo $styles; ?></style>
    </head>
    <body>
        <?php echo $form_html; ?>
        <?php wp_footer(); ?>
        <?php echo erpgulf_otp_form_js( $redirect ); ?>
    </body>
    </html>
    <?php
    exit;
});

// ─────────────────────────────────────────────────────────────────
// 6. AJAX — SEND OTP
// ─────────────────────────────────────────────────────────────────

add_action('wp_ajax_nopriv_erpgulf_send_otp', 'erpgulf_handle_send_otp');
add_action('wp_ajax_erpgulf_send_otp',        'erpgulf_handle_send_otp');

function erpgulf_handle_send_otp() {

    $identifier = sanitize_text_field( wp_unslash( $_POST['identifier'] ?? '' ) );
    if ( empty($identifier) ) wp_send_json_error( erpgulf_err( 'Please enter your email, mobile, or username.', 'number_not_found' ) );

    $user = null; $found_by = '';

    if ( filter_var($identifier, FILTER_VALIDATE_EMAIL) ) {
        $user = get_user_by('email', $identifier); $found_by = 'email';
    }
    if ( ! $user ) {
        $try = get_user_by('login', $identifier);
        if ( $try ) { $user = $try; $found_by = 'username'; }
    }
    if ( ! $user ) {
        $try = erpgulf_find_user_by_phone( $identifier );
        if ( $try ) { $user = $try; $found_by = 'phone'; }
    }

    if ( ! $user ) wp_send_json_error( erpgulf_err( "We couldn't find an account with that email, username, or phone number.", 'number_not_found' ) );

    if ( $found_by === 'username' ) {
        wp_send_json_success([ 'user_id' => $user->ID, 'method' => 'password' ]);
    }

    $otp = wp_rand(100000, 999999);
    update_user_meta($user->ID, 'erpgulf_current_otp',  $otp);
    update_user_meta($user->ID, 'erpgulf_otp_expiry', time() + 300);

    $def_sms_en    = "The OTP for your {site} account login is {otp}. Valid for 5 minutes. Do not share this code.";
    $def_sms_ar    = "رمز التحقق (OTP) الخاص بتسجيل الدخول إلى حساب {site} هو {otp}. صالح لمدة 5 دقائق. لا تشارك هذا الرمز.";
    $def_email_sub = "Your {site} verification code: {otp}";
    $def_email_en  = "The OTP for your {site} account login is {otp}. Valid for 5 minutes. Do not share this code.";
    $def_email_ar  = "رمز التحقق (OTP) الخاص بتسجيل الدخول إلى حساب {site} هو {otp}. صالح لمدة 5 دقائق. لا تشارك هذا الرمز.";

    $msg_sms_en    = erpgulf_resolve_template( get_option('erpgulf_msg_sms_en',    $def_sms_en),    $otp );
    $msg_sms_ar    = erpgulf_resolve_template( get_option('erpgulf_msg_sms_ar',    $def_sms_ar),    $otp );
    $msg_email_sub = erpgulf_resolve_template( get_option('erpgulf_msg_email_sub', $def_email_sub), $otp );
    $msg_email_en  = erpgulf_resolve_template( get_option('erpgulf_msg_email_en',  $def_email_en),  $otp );
    $msg_email_ar  = erpgulf_resolve_template( get_option('erpgulf_msg_email_ar',  $def_email_ar),  $otp );

    $errors = [];
    $phone  = get_user_meta($user->ID, 'customer_addresses_0_phone', true);

    // ── Dynamic SMS dispatch ──────────────────────────────────────
    // Reads the active provider from the admin dropdown.
    // No code change needed when switching providers.
    if ( $phone ) {
        $sms_provider = get_option('erpgulf_active_sms_provider', 'experttexting');
        $sms_function = 'erpgulf_send_sms_' . $sms_provider;

        if ( function_exists($sms_function) ) {
            $r = $sms_function($phone, $otp, [
                // ExpertTexting settings
                'username'     => get_option('erpgulf_et_username'),
                'api_key'      => get_option('erpgulf_et_api_key'),
                'api_secret'   => get_option('erpgulf_et_api_secret'),
                'country_code' => get_option('erpgulf_et_country_code', '966'),
                // 4jawaly settings
                'app_key'      => get_option('erpgulf_4j_app_key'),
                'app_secret'   => get_option('erpgulf_4j_app_secret'),
                'sender'       => get_option('erpgulf_4j_sender'),
                'number_iso'   => get_option('erpgulf_4j_number_iso', 'SA'),
                // shared
                'message_en'   => $msg_sms_en,
                'message_ar'   => $msg_sms_ar,
                'user_id'      => $user->ID,
            ]);
            if ( empty($r['success']) ) $errors[] = 'SMS: ' . ( $r['message'] ?? 'Unknown error' );
        } else {
            $errors[] = "SMS: Provider '{$sms_provider}' is not installed.";
        }
    }

    // ── Dynamic Email dispatch ────────────────────────────────────
    $email_provider = get_option('erpgulf_active_email_provider', 'oci');
    $email_function = 'erpgulf_send_email_' . $email_provider;

    if ( function_exists($email_function) ) {
        $r = $email_function($user->user_email, $otp, [
            // OCI settings
            'host'       => get_option('erpgulf_oci_host'),
            'port'       => get_option('erpgulf_oci_port'),
            'user'       => get_option('erpgulf_oci_user'),
            'pass'       => get_option('erpgulf_oci_pass'),
            'from'       => get_option('erpgulf_oci_from'),
            // shared
            'subject'    => $msg_email_sub,
            'message_en' => $msg_email_en,
            'message_ar' => $msg_email_ar,
        ]);
        if ( empty($r['success']) ) $errors[] = 'Email: ' . ( $r['message'] ?? 'Unknown error' );
    } else {
        $errors[] = "Email: Provider '{$email_provider}' is not installed.";
    }

    $all_failed = ( ! $phone && ! empty($errors) ) || ( $phone && count($errors) >= 2 );
    if ( $all_failed ) {
        delete_user_meta($user->ID, 'erpgulf_current_otp');
        delete_user_meta($user->ID, 'erpgulf_otp_expiry');
        wp_send_json_error( erpgulf_err( 'Could not send verification code. ' . implode(' | ', $errors), 'send_failed' ) );
    }

    do_action( 'erpgulf_after_send_otp', $user->ID, $otp, (string) $phone );

    wp_send_json_success([ 'user_id' => $user->ID, 'method' => 'otp' ]);
}

// ─────────────────────────────────────────────────────────────────
// 7. AJAX — VERIFY OTP OR PASSWORD
// ─────────────────────────────────────────────────────────────────

add_action('wp_ajax_nopriv_erpgulf_verify_otp', 'erpgulf_handle_verify_otp');
add_action('wp_ajax_erpgulf_verify_otp',        'erpgulf_handle_verify_otp');

function erpgulf_handle_verify_otp() {
    $uid    = intval( $_POST['user_id'] ?? 0 );
    $method = sanitize_text_field( $_POST['method'] ?? 'otp' );

    if ( ! $uid ) wp_send_json_error( erpgulf_err( 'Session lost. Please refresh and try again.', 'session_lost' ) );

    if ( $method === 'password' ) {
        $password = $_POST['password'] ?? '';
        if ( empty($password) ) wp_send_json_error( erpgulf_err( 'Please enter your password.', 'session_lost' ) );
        $user = get_userdata($uid);
        if ( ! $user ) wp_send_json_error( erpgulf_err( 'User not found.', 'session_lost' ) );
        if ( wp_check_password($password, $user->user_pass, $user->ID) ) {
            wp_set_auth_cookie($uid, true);
            do_action( 'erpgulf_user_logged_in', $uid, 'password' );
            wp_send_json_success([ 'redirect' => erpgulf_redirect_url($uid) ]);
        }
        sleep(1);
        wp_send_json_error( erpgulf_err( 'Incorrect password. Please try again.', 'wrong_password' ) );
    }

    $otp    = sanitize_text_field( $_POST['otp'] ?? '' );
    $stored = get_user_meta($uid, 'erpgulf_current_otp', true);
    $expiry = get_user_meta($uid, 'erpgulf_otp_expiry',  true);

    if ( empty($stored) ) wp_send_json_error( erpgulf_err( 'No active code found. Please request a new one.', 'session_lost' ) );

    if ( (string) $otp !== (string) $stored ) {
        do_action( 'erpgulf_verify_otp_failed', $uid, $otp, (string) $stored );
        wp_send_json_error( erpgulf_err( 'Invalid verification code.', 'invalid_code' ) );
    }

    if ( time() >= intval($expiry) ) {
        delete_user_meta($uid, 'erpgulf_current_otp');
        delete_user_meta($uid, 'erpgulf_otp_expiry');
        wp_send_json_error( erpgulf_err( 'This code has expired. Please request a new one.', 'expired_code' ) );
    }

    delete_user_meta($uid, 'erpgulf_current_otp');
    delete_user_meta($uid, 'erpgulf_otp_expiry');
    wp_set_auth_cookie($uid, true);
    do_action( 'erpgulf_user_logged_in', $uid, 'otp' );
    wp_send_json_success([ 'redirect' => erpgulf_redirect_url($uid) ]);
}

// ─────────────────────────────────────────────────────────────────
// 8. AJAX — REGISTER NEW USER
// ─────────────────────────────────────────────────────────────────

add_action('wp_ajax_nopriv_erpgulf_register_user', 'erpgulf_handle_register_user');
add_action('wp_ajax_erpgulf_register_user',        'erpgulf_handle_register_user');

function erpgulf_handle_register_user() {

    // ── Collect and sanitise fields ───────────────────────────────
    $post_data = [
        'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
        'last_name'  => sanitize_text_field( wp_unslash( $_POST['last_name']  ?? '' ) ),
        'email'      => sanitize_email(      wp_unslash( $_POST['email']      ?? '' ) ),
        'mobile'     => sanitize_text_field( wp_unslash( $_POST['mobile']     ?? '' ) ),
        'password'   =>                                   $_POST['password']   ?? '',
        'address'    => sanitize_text_field( wp_unslash( $_POST['address']    ?? '' ) ),
        'address_2'  => sanitize_text_field( wp_unslash( $_POST['address_2']  ?? '' ) ),
        'city'       => sanitize_text_field( wp_unslash( $_POST['city']       ?? '' ) ),
        'postcode'   => sanitize_text_field( wp_unslash( $_POST['postcode']   ?? '' ) ),
        'country'    => 'SA',   // hardcoded — Saudi Arabia
        'state'      => sanitize_text_field( wp_unslash( $_POST['state']      ?? '' ) ),
    ];

    // Allow extra fields via filter
    $fields = erpgulf_register_fields();
    foreach ( $fields as $key => $field ) {
        if ( ! isset( $post_data[ $key ] ) && isset( $_POST[ $key ] ) ) {
            $post_data[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
        }
    }

    // ── Built-in validation ───────────────────────────────────────
    $errors = [];

    foreach ( $fields as $key => $field ) {
        if ( ! empty( $field['required'] ) && empty( $post_data[ $key ] ) ) {
            $errors[] = esc_html( $field['label'] ) . ' is required.';
        }
    }

    if ( empty( $errors ) ) {
        if ( ! is_email( $post_data['email'] ) ) {
            $errors[] = erpgulf_err( 'Please enter a valid email address.', 'invalid_email' );
        }
        if ( email_exists( $post_data['email'] ) ) {
            $errors[] = erpgulf_err( 'An account with this email already exists.', 'email_exists' );
        }
        if ( strlen( $post_data['password'] ) < 6 ) {
            $errors[] = erpgulf_err( 'Password must be at least 6 characters.', 'weak_password' );
        }
        if ( ! empty( $post_data['mobile'] ) ) {
            $mobile_digits = preg_replace( '/[^0-9]/', '', $post_data['mobile'] );
            if ( strlen( $mobile_digits ) < 7 ) {
                $errors[] = erpgulf_err( 'Please enter a valid mobile number.', 'invalid_phone' );
            } else {
                // Check duplicate using last 7 digits — consistent with login phone matching
                $existing = erpgulf_find_user_by_phone( $post_data['mobile'], 7 );
                if ( $existing ) {
                    $errors[] = erpgulf_err( 'An account with this mobile number already exists.', 'phone_exists' );
                }
            }
        }
    }

    // ── Custom validation via filter ──────────────────────────────
    $errors = (array) apply_filters( 'erpgulf_validate_registration', $errors, $post_data );

    if ( ! empty( $errors ) ) {
        wp_send_json_error( implode( ' ', $errors ) );
    }

    do_action( 'erpgulf_before_register', $post_data );

    // ── Create the user ───────────────────────────────────────────
    $username = sanitize_user( strtolower( $post_data['first_name'] . '.' . $post_data['last_name'] ) );

    // Ensure unique username
    $base_username = $username;
    $suffix        = 1;
    while ( username_exists( $username ) ) {
        $username = $base_username . $suffix++;
    }

    $user_id = wp_create_user(
        $username,
        $post_data['password'],
        $post_data['email']
    );

    if ( is_wp_error( $user_id ) ) {
        wp_send_json_error( erpgulf_err( $user_id->get_error_message(), 'register_failed' ) );
    }

    // ── Save user meta from registered fields ─────────────────────
    foreach ( $fields as $key => $field ) {
        if ( empty( $field['meta_key'] ) || empty( $post_data[ $key ] ) ) continue;

        // Core WP fields — update directly
        if ( in_array( $field['meta_key'], [ 'first_name', 'last_name' ], true ) ) {
            wp_update_user( [ 'ID' => $user_id, $field['meta_key'] => $post_data[ $key ] ] );
        } else {
            update_user_meta( $user_id, $field['meta_key'], $post_data[ $key ] );
        }
    }

    // Also save WooCommerce billing fields
    if ( ! empty( $post_data['first_name'] ) ) update_user_meta( $user_id, 'billing_first_name', $post_data['first_name'] );
    if ( ! empty( $post_data['last_name'] ) )  update_user_meta( $user_id, 'billing_last_name',  $post_data['last_name'] );
    if ( ! empty( $post_data['email'] ) )       update_user_meta( $user_id, 'billing_email',      $post_data['email'] );
    if ( ! empty( $post_data['mobile'] ) )      update_user_meta( $user_id, 'billing_phone',      $post_data['mobile'] );
    if ( ! empty( $post_data['address'] ) )     update_user_meta( $user_id, 'billing_address_1',  $post_data['address'] );
    if ( ! empty( $post_data['address_2'] ) )   update_user_meta( $user_id, 'billing_address_2',  $post_data['address_2'] );
    if ( ! empty( $post_data['city'] ) )        update_user_meta( $user_id, 'billing_city',        $post_data['city'] );
    if ( ! empty( $post_data['postcode'] ) )    update_user_meta( $user_id, 'billing_postcode',    $post_data['postcode'] );
    if ( ! empty( $post_data['country'] ) )     update_user_meta( $user_id, 'billing_country',     $post_data['country'] );
    if ( ! empty( $post_data['state'] ) )       update_user_meta( $user_id, 'billing_state',       $post_data['state'] );

    do_action( 'erpgulf_after_register', $user_id, $post_data );

    // ── Send OTP to verify the account ───────────────────────────
    $otp = wp_rand(100000, 999999);
    update_user_meta( $user_id, 'erpgulf_current_otp',    $otp );
    update_user_meta( $user_id, 'erpgulf_otp_expiry',  time() + 300 );
    update_user_meta( $user_id, 'erpgulf_pending_verification', 1 );

    $def_sms_en    = "Your {site} account verification code is {otp}. Valid for 5 minutes.";
    $def_sms_ar    = "رمز التحقق لحسابك في {site} هو {otp}. صالح لمدة 5 دقائق.";
    $def_email_sub = "Verify your {site} account — code: {otp}";
    $def_email_en  = "Welcome! Your {site} account verification code is {otp}. Valid for 5 minutes.";
    $def_email_ar  = "مرحباً! رمز التحقق لحسابك في {site} هو {otp}. صالح لمدة 5 دقائق.";

    $msg_sms_en    = erpgulf_resolve_template( get_option('erpgulf_msg_sms_en',    $def_sms_en),    $otp );
    $msg_sms_ar    = erpgulf_resolve_template( get_option('erpgulf_msg_sms_ar',    $def_sms_ar),    $otp );
    $msg_email_sub = erpgulf_resolve_template( get_option('erpgulf_msg_email_sub', $def_email_sub), $otp );
    $msg_email_en  = erpgulf_resolve_template( get_option('erpgulf_msg_email_en',  $def_email_en),  $otp );
    $msg_email_ar  = erpgulf_resolve_template( get_option('erpgulf_msg_email_ar',  $def_email_ar),  $otp );

    $phone  = $post_data['mobile'];
    $errors = [];

    // Send SMS
    if ( $phone ) {
        $sms_provider = get_option('erpgulf_active_sms_provider', 'experttexting');
        $sms_function = 'erpgulf_send_sms_' . $sms_provider;
        if ( function_exists($sms_function) ) {
            $r = $sms_function( $phone, $otp, [
                'username'     => get_option('erpgulf_et_username'),
                'api_key'      => get_option('erpgulf_et_api_key'),
                'api_secret'   => get_option('erpgulf_et_api_secret'),
                'country_code' => get_option('erpgulf_et_country_code', '966'),
                'app_key'      => get_option('erpgulf_4j_app_key'),
                'app_secret'   => get_option('erpgulf_4j_app_secret'),
                'sender'       => get_option('erpgulf_4j_sender'),
                'number_iso'   => get_option('erpgulf_4j_number_iso', 'SA'),
                'message_en'   => $msg_sms_en,
                'message_ar'   => $msg_sms_ar,
                'user_id'      => $user_id,
            ]);
            if ( empty($r['success']) ) $errors[] = 'SMS: ' . ( $r['message'] ?? 'Unknown error' );
        }
    }

    // Send Email
    $email_provider = get_option('erpgulf_active_email_provider', 'oci');
    $email_function = 'erpgulf_send_email_' . $email_provider;
    if ( function_exists($email_function) ) {
        $r = $email_function( $post_data['email'], $otp, [
            'host'       => get_option('erpgulf_oci_host'),
            'port'       => get_option('erpgulf_oci_port'),
            'user'       => get_option('erpgulf_oci_user'),
            'pass'       => get_option('erpgulf_oci_pass'),
            'from'       => get_option('erpgulf_oci_from'),
            'subject'    => $msg_email_sub,
            'message_en' => $msg_email_en,
            'message_ar' => $msg_email_ar,
        ]);
        if ( empty($r['success']) ) $errors[] = 'Email: ' . ( $r['message'] ?? 'Unknown error' );
    }

    do_action( 'erpgulf_register_otp_sent', $user_id, $otp, $phone );

    // Return success — OTP sent, waiting for verification
    wp_send_json_success( [
        'user_id' => $user_id,
        'method'  => 'register',
        'warnings' => $errors,
    ] );
}
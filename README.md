# Simple OTP Plugin for WooCommerce by ERPGulf
**Version:** 4.5.0  
**Author:** Farook K — [https://medium.com/nothing-big](https://medium.com/nothing-big)  
**Requires:** WordPress 6.0+, WooCommerce 7.0+, PHP 8.0+

---

## What This Plugin Does

Replaces the standard WooCommerce login with a secure dual-channel OTP system.

- User enters their **email, phone, or username**
- If email or phone → a **6-digit OTP** is sent via **SMS + Email** simultaneously
- If username → a **password field** appears instead
- User verifies the code → logged in and redirected to My Account

---

## File Structure

```
wp-content/plugins/erpgulf-otp-login/
    ├── erpgulf-otp-login.php        Main plugin file
    ├── experttexting-provider.php   SMS delivery (ExpertTexting)
    ├── oci-smtp-provider.php        Email delivery (Oracle OCI SMTP)
    └── README.md                    This file
```

---

## Installation

1. Upload the `erpgulf-otp-login` folder to `wp-content/plugins/`
2. Go to **WordPress Admin → Plugins**
3. Find **ERPGulf Simple OTP** and click **Activate**
4. Go to **WordPress Admin → ERPGulf OTP** and fill in credentials
5. Click **Save All Settings**

---

## Configuration

Go to **WordPress Admin → ERPGulf OTP**

### SMS Settings (ExpertTexting)

| Field | Description |
|---|---|
| Username | Your ExpertTexting account username |
| API Key | Your ExpertTexting API key |
| API Secret | Your ExpertTexting API secret |
| Default Country Code | Applied when a stored phone has no country code. Default: `966` (Saudi Arabia) |

**Common country codes:**

| Code | Country |
|---|---|
| 966 | Saudi Arabia |
| 974 | Qatar |
| 971 | UAE |
| 965 | Kuwait |
| 91 | India |
| 1 | USA / Canada |

---

### Email Settings (OCI SMTP — Jeddah)

| Field | Value |
|---|---|
| SMTP Host | `smtp.email.me-jeddah-1.oci.oraclecloud.com` |
| Port | `587` (STARTTLS — do not use 465) |
| SMTP User | Your OCI SMTP username (long OCID string) |
| SMTP Password | Your OCI SMTP auth token |
| Approved Sender | The exact email registered in OCI Console |

> ⚠️ **OCI requirement:** The Approved Sender email must be registered in  
> **OCI Console → Email Delivery → Approved Senders**  
> SPF and DKIM DNS records must also be published for that domain.  
> Without this, OCI will reject the email with error 535.

---

### Message Templates

All messages support two placeholders:

| Placeholder | Replaced with |
|---|---|
| `{otp}` | The 6-digit verification code |
| `{site}` | Your WordPress site name |

**Default SMS (English):**
```
The OTP for your {site} account login is {otp}. Valid for 5 minutes. Do not share this code.
```

**Default SMS (Arabic):**
```
رمز التحقق (OTP) الخاص بتسجيل الدخول إلى حساب {site} هو {otp}. صالح لمدة 5 دقائق. لا تشارك هذا الرمز.
```

Both English and Arabic are sent in a single SMS message (unicode enabled).  
The email is a bilingual HTML email with both languages in one body.

---

## How to Use — Two Surfaces

### 1. Test Page (private, for developers only)

```
https://yoursite.com/otp-test
```

- Full standalone HTML page
- No theme header or footer
- Not in the database, not in the menu
- Not indexed by Google
- Use this to verify SMS, email, OTP, and login flow
- **Do not share this URL with customers**

---

### 2. Shortcode (for the real customer-facing page)

```
[erpgulf_otp_form]
```

Drop this into any WordPress page via the editor.  
The active theme provides the header, footer, fonts, and layout.  
The form sits inside the page like any other content block.

**To create the login page:**
1. WordPress Admin → Pages → Add New
2. Title: `Login` (or any name)
3. Content: `[erpgulf_otp_form]`
4. Set URL slug to `login`
5. Publish

> ⚠️ **Important:** The shortcode form uses the same JavaScript as the test page.  
> Do not rename the element IDs — the JS depends on them (see Design Team section below).

---

## Phone Number Matching

The plugin uses **last-7-digit matching** to find users by phone.  
This means it works regardless of how the number is stored or typed.

**All of these match the same user (+966 501 234 567):**

| User types | Matches? |
|---|---|
| `0501234567` | ✅ |
| `+966501234567` | ✅ |
| `00966501234567` | ✅ |
| `966501234567` | ✅ |
| `501 234 567` | ✅ |
| `501-234-567` | ✅ |
| `(050) 1234567` | ✅ |

Phone numbers without a country code are automatically assigned  
the **Default Country Code** set in the plugin settings (default: 966).

---

## Login Flow

```
User types identifier
        │
        ├── Email?     → look up by email  → send OTP via SMS + Email
        ├── Username?  → look up by login  → show password field
        └── Phone?     → last-7-digit match → send OTP via SMS + Email
                │
                ├── OTP correct + not expired → log in → redirect
                ├── OTP wrong                 → error (erpgulf_verify_otp_failed fires)
                └── OTP expired (5 min)       → error, request new code
```

---

## Developer Hooks Reference

All customisation goes in your **child theme's `functions.php`**.  
Never edit the plugin files directly.

---

### FILTERS

---

#### `erpgulf_otp_message_text`
Customize the SMS body text before it is transmitted.

```php
add_filter('erpgulf_otp_message_text', function($message, $otp, $phone, $user_id) {

    $user = get_userdata($user_id);
    $name = $user->first_name ?: 'Customer';
    return "Hello {$name}, your login code is {$otp}. Valid 5 mins.";

}, 10, 4);
```

| Parameter | Type | Description |
|---|---|---|
| `$message` | string | The built bilingual EN + AR message |
| `$otp` | int | The 6-digit OTP |
| `$phone` | string | Normalised international phone number |
| `$user_id` | int | WordPress user ID |

> Must return a string. If you return nothing, the SMS body will be empty.

---

#### `erpgulf_otp_form_html`
Replace the login form HTML. Applies to **both** the shortcode page and `/otp-test`.

```php
add_filter('erpgulf_otp_form_html', function($html) {

    $logo = '<img src="' . get_stylesheet_directory_uri() . '/logo.png"
                  style="display:block;margin:0 auto 20px;width:120px;">';

    return str_replace('<div class="otp-box">', '<div class="otp-box">' . $logo, $html);

});
```

> ⚠️ Keep these element IDs intact — the JavaScript depends on them:  
> `#otp-step-1` `#otp-step-otp` `#otp-step-password`  
> `#identifier` `#otp-code` `#password`  
> `#btn-send` `#btn-verify-otp` `#btn-verify-password` `#otp-msg`

---

#### `erpgulf_otp_form_styles`
Customize CSS on the **shortcode page only**.

```php
add_filter('erpgulf_otp_form_styles', function($css) {
    return $css . '
        .otp-box button { background: #00b894; }
        .otp-box { border-radius: 0; border-top: 4px solid #00b894; }
    ';
});
```

---

#### `erpgulf_test_page_styles`
Customize CSS on the **/otp-test page only**.

```php
add_filter('erpgulf_test_page_styles', function($css) {
    return $css . '
        body { background: #1a1a2e; }
        .otp-box button { background: #e63946; }
    ';
});
```

---

#### `erpgulf_error_messages`
Override any error message shown to the user.

```php
add_filter('erpgulf_error_messages', function($message, $error_type) {

    $arabic = [
        'number_not_found' => 'لم نجد حساباً بهذه البيانات.',
        'invalid_code'     => 'الرمز غير صحيح. حاول مجدداً.',
        'expired_code'     => 'انتهت صلاحية الرمز. اطلب رمزاً جديداً.',
        'session_lost'     => 'انتهت الجلسة. يرجى تحديث الصفحة.',
        'wrong_password'   => 'كلمة المرور غير صحيحة.',
        'send_failed'      => 'تعذّر إرسال الرمز. حاول لاحقاً.',
    ];

    return $arabic[$error_type] ?? $message;

}, 10, 2);
```

| `$error_type` value | When it fires |
|---|---|
| `number_not_found` | Email / phone / username not found |
| `invalid_code` | OTP does not match |
| `expired_code` | OTP older than 5 minutes |
| `session_lost` | Browser lost the user session |
| `wrong_password` | Password incorrect |
| `send_failed` | Both SMS and email failed to send |

---

#### `erpgulf_redirect_after_login`
Change where the user lands after a successful login.

```php
add_filter('erpgulf_redirect_after_login', function($url, $user_id) {

    if ( user_can($user_id, 'manage_options') ) {
        return admin_url();
    }
    return $url;

}, 10, 2);
```

---

#### `erpgulf_clean_phone`
Override how the phone number suffix is extracted for lookup matching.

```php
add_filter('erpgulf_clean_phone', function($cleaned, $original) {

    $digits = preg_replace('/[^0-9]/', '', $original);
    return substr($digits, -9); // use 9 digits instead of 7

}, 10, 2);
```

---

### ACTION HOOKS

---

#### `erpgulf_before_save_settings`
Fires before any option is written when admin clicks Save All Settings.

```php
add_action('erpgulf_before_save_settings', function($post_data) {
    $who  = wp_get_current_user()->user_login;
    $time = current_time('mysql');
    error_log("OTP settings changed by {$who} at {$time}");
});
```

---

#### `erpgulf_after_send_otp`
Fires after OTP has been dispatched via SMS and/or email.

```php
add_action('erpgulf_after_send_otp', function($user_id, $otp, $phone) {

    $key   = 'otp_requests_' . current_time('Y-m-d');
    $count = (int) get_user_meta($user_id, $key, true);
    update_user_meta($user_id, $key, $count + 1);

    if ( $count + 1 >= 5 ) {
        wp_mail(get_option('admin_email'), 'Suspicious OTP Activity',
            "User {$user_id} requested " . ($count + 1) . " OTPs today.");
    }

}, 10, 3);
```

---

#### `erpgulf_user_logged_in`
Fires after a successful login — for both OTP and password methods.

```php
add_action('erpgulf_user_logged_in', function($user_id, $method) {
    update_user_meta($user_id, 'last_login',        current_time('mysql'));
    update_user_meta($user_id, 'last_login_method', $method);
}, 10, 2);
```

| Parameter | Type | Description |
|---|---|---|
| `$user_id` | int | WordPress user ID |
| `$method` | string | `'otp'` or `'password'` |

---

#### `erpgulf_verify_otp_failed`
Fires when the submitted OTP does not match the stored one.

```php
add_action('erpgulf_verify_otp_failed', function($user_id, $submitted_otp, $stored_otp) {

    $fails = (int) get_user_meta($user_id, 'otp_fail_count', true) + 1;
    update_user_meta($user_id, 'otp_fail_count', $fails);

    if ( $fails >= 3 ) {
        delete_user_meta($user_id, 'erpgulf_current_otp');
        delete_user_meta($user_id, 'erpgulf_otp_expiry');
        update_user_meta($user_id, 'otp_fail_count', 0);
        wp_mail(get_option('admin_email'), 'OTP brute force alert',
            "User {$user_id} failed OTP 3 times.");
    }

}, 10, 3);
```

---

## Adding New Providers

The plugin is built so you can swap or add SMS and email providers  
without touching any existing files.

Each provider is a **single PHP file** with **one function** that:
- Accepts the phone/email, the OTP, and a settings array
- Sends the message
- Returns `[ 'success' => true ]` or `[ 'success' => false, 'message' => '...' ]`

---

### Adding a New SMS Provider

#### Step 1 — Create the provider file

Create a new file in the plugin folder.  
Example: `twilio-provider.php`

```php
<?php
/**
 * SMS Provider: Twilio
 *
 * Returns: [ 'success' => bool, 'message' => string (on failure) ]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function erpgulf_send_sms_twilio( string $phone, int $otp, array $settings ): array {

    $sid   = ! empty($settings['account_sid']) ? trim($settings['account_sid']) : '';
    $token = ! empty($settings['auth_token'])  ? trim($settings['auth_token'])  : '';
    $from  = ! empty($settings['from_number']) ? trim($settings['from_number']) : '';

    // ── Validate credentials ──────────────────────────────────────
    if ( empty($sid) || empty($token) || empty($from) ) {
        return [ 'success' => false, 'message' => 'Twilio credentials are missing.' ];
    }

    // ── Build message text ────────────────────────────────────────
    $message = ! empty($settings['message_en'])
        ? trim($settings['message_en'])
        : "Your verification code is: {$otp}. Valid for 5 minutes.";

    // ── Apply the shared SMS text filter ─────────────────────────
    // This allows the erpgulf_otp_message_text filter to work
    // with your provider exactly as it does with ExpertTexting.
    $message = (string) apply_filters(
        'erpgulf_otp_message_text',
        $message,
        $otp,
        $phone,
        $settings['user_id'] ?? 0
    );

    // ── Send via Twilio REST API ──────────────────────────────────
    $url  = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

    $response = wp_remote_post( $url, [
        'timeout'   => 30,
        'sslverify' => true,
        'headers'   => [
            'Authorization' => 'Basic ' . base64_encode("{$sid}:{$token}"),
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ],
        'body' => [
            'From' => $from,
            'To'   => '+' . ltrim($phone, '+'),
            'Body' => $message,
        ],
    ]);

    if ( is_wp_error($response) ) {
        return [ 'success' => false, 'message' => 'HTTP error: ' . $response->get_error_message() ];
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $raw       = wp_remote_retrieve_body($response);
    $res       = json_decode($raw, true);

    // Twilio returns 201 Created on success
    if ( $http_code === 201 && ! empty($res['sid']) ) {
        return [ 'success' => true ];
    }

    $error = $res['message'] ?? $raw;
    return [ 'success' => false, 'message' => 'Twilio error: ' . esc_html($error) ];
}
```

---

#### Step 2 — Load it in the main plugin file

Open `erpgulf-otp-login.php` and add one line near the top alongside the existing requires:

```php
require_once plugin_dir_path(__FILE__) . 'experttexting-provider.php';
require_once plugin_dir_path(__FILE__) . 'oci-smtp-provider.php';
require_once plugin_dir_path(__FILE__) . 'twilio-provider.php';   // ← add this
```

---

#### Step 3 — Call it in `erpgulf_handle_send_otp()`

In `erpgulf-otp-login.php` find the SMS sending block (look for "Send SMS") and replace the call:

```php
// Before — ExpertTexting
$r = erpgulf_send_sms_experttexting($phone, $otp, [
    'username'   => get_option('erpgulf_et_username'),
    'api_key'    => get_option('erpgulf_et_api_key'),
    'api_secret' => get_option('erpgulf_et_api_secret'),
    ...
]);

// After — Twilio
$r = erpgulf_send_sms_twilio($phone, $otp, [
    'account_sid' => get_option('erpgulf_twilio_sid'),
    'auth_token'  => get_option('erpgulf_twilio_token'),
    'from_number' => get_option('erpgulf_twilio_from'),
    'message_en'  => $msg_sms_en,
    'message_ar'  => $msg_sms_ar,
    'user_id'     => $user->ID,
]);
```

---

#### Step 4 — Add settings fields (optional but recommended)

In `erpgulf_settings_render()` add a new section for Twilio credentials,  
exactly like the existing ExpertTexting section:

```php
<div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;margin-bottom:20px;">
    <h3 style="margin-top:0;">📱 SMS Credentials (Twilio)</h3>
    <table class="form-table">
        <tr>
            <th>Account SID</th>
            <td><input type="text" name="twilio_sid"
                       value="<?php echo esc_attr(get_option('erpgulf_twilio_sid')); ?>"
                       class="regular-text"></td>
        </tr>
        <tr>
            <th>Auth Token</th>
            <td><input type="password" name="twilio_token"
                       value="<?php echo esc_attr(get_option('erpgulf_twilio_token')); ?>"
                       class="regular-text"></td>
        </tr>
        <tr>
            <th>From Number</th>
            <td><input type="text" name="twilio_from"
                       value="<?php echo esc_attr(get_option('erpgulf_twilio_from')); ?>"
                       class="regular-text"
                       placeholder="+12345678900"></td>
        </tr>
    </table>
</div>
```

And save the new fields in the save block:

```php
update_option('erpgulf_twilio_sid',   trim(sanitize_text_field($_POST['twilio_sid'])));
update_option('erpgulf_twilio_token', trim(sanitize_text_field($_POST['twilio_token'])));
update_option('erpgulf_twilio_from',  trim(sanitize_text_field($_POST['twilio_from'])));
```

---

#### SMS Provider Contract — what every provider file must follow

```
Function name:   erpgulf_send_sms_{yourprovider}()
Parameters:      ( string $phone, int $otp, array $settings )
Returns:         [ 'success' => true ]
              or [ 'success' => false, 'message' => 'reason' ]

The $settings array will contain at minimum:
    message_en   Resolved English message text
    message_ar   Resolved Arabic message text
    user_id      WordPress user ID
    country_code Default country code from settings

Apply the erpgulf_otp_message_text filter before sending
so that developer overrides still work with your provider.
```

---

### Adding a New Email Provider

#### Step 1 — Create the provider file

Example: `sendgrid-provider.php`

```php
<?php
/**
 * Email Provider: SendGrid
 *
 * Returns: [ 'success' => bool, 'message' => string (on failure) ]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function erpgulf_send_email_sendgrid( string $email, int $otp, array $settings ): array {

    $api_key  = ! empty($settings['api_key'])  ? trim($settings['api_key'])  : '';
    $from     = ! empty($settings['from'])     ? trim($settings['from'])     : '';
    $from_name = ! empty($settings['from_name']) ? trim($settings['from_name']) : 'ERPGulf Security';

    // ── Validate ──────────────────────────────────────────────────
    if ( empty($api_key) ) {
        return [ 'success' => false, 'message' => 'SendGrid API key is missing.' ];
    }
    if ( empty($from) || ! is_email($from) ) {
        return [ 'success' => false, 'message' => 'SendGrid sender email is missing or invalid.' ];
    }

    // ── Build subject and body from passed-in templates ───────────
    $subject = ! empty($settings['subject'])
        ? trim($settings['subject'])
        : "Your verification code: {$otp}";

    $msg_en = ! empty($settings['message_en'])
        ? trim($settings['message_en'])
        : "Your verification code is {$otp}. Valid for 5 minutes.";

    $msg_ar = ! empty($settings['message_ar'])
        ? trim($settings['message_ar'])
        : "رمز التحقق الخاص بك هو {$otp}. صالح لمدة 5 دقائق.";

    // ── Build HTML body ───────────────────────────────────────────
    // Reuse the shared email body builder from oci-smtp-provider.php
    $html_body = erpgulf_build_email_body($otp, $msg_en, $msg_ar);

    // ── SendGrid v3 API payload ───────────────────────────────────
    $payload = [
        'personalizations' => [[
            'to' => [[ 'email' => $email ]],
        ]],
        'from'    => [ 'email' => $from, 'name' => $from_name ],
        'subject' => $subject,
        'content' => [[
            'type'  => 'text/html',
            'value' => $html_body,
        ]],
    ];

    $response = wp_remote_post('https://api.sendgrid.com/v3/mail/send', [
        'timeout'   => 30,
        'sslverify' => true,
        'headers'   => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode($payload),
    ]);

    if ( is_wp_error($response) ) {
        return [ 'success' => false, 'message' => 'HTTP error: ' . $response->get_error_message() ];
    }

    $http_code = wp_remote_retrieve_response_code($response);

    // SendGrid returns 202 Accepted on success
    if ( $http_code === 202 ) {
        return [ 'success' => true ];
    }

    $raw   = wp_remote_retrieve_body($response);
    $res   = json_decode($raw, true);
    $error = $res['errors'][0]['message'] ?? $raw;

    return [ 'success' => false, 'message' => 'SendGrid error: ' . esc_html($error) ];
}
```

---

#### Step 2 — Load it in the main plugin file

```php
require_once plugin_dir_path(__FILE__) . 'oci-smtp-provider.php';
require_once plugin_dir_path(__FILE__) . 'sendgrid-provider.php';   // ← add this
```

---

#### Step 3 — Call it in `erpgulf_handle_send_otp()`

Find the email sending block and replace the call:

```php
// Before — OCI SMTP
$r = erpgulf_send_email_oci($user->user_email, $otp, [
    'host'    => get_option('erpgulf_oci_host'),
    ...
]);

// After — SendGrid
$r = erpgulf_send_email_sendgrid($user->user_email, $otp, [
    'api_key'    => get_option('erpgulf_sg_api_key'),
    'from'       => get_option('erpgulf_sg_from'),
    'from_name'  => get_option('erpgulf_sg_from_name'),
    'subject'    => $msg_email_sub,
    'message_en' => $msg_email_en,
    'message_ar' => $msg_email_ar,
]);
```

---

#### Step 4 — Add settings fields

In `erpgulf_settings_render()` add a SendGrid section:

```php
<div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;margin-bottom:20px;">
    <h3 style="margin-top:0;">📧 Email Credentials (SendGrid)</h3>
    <table class="form-table">
        <tr>
            <th>API Key</th>
            <td><input type="password" name="sg_api_key"
                       value="<?php echo esc_attr(get_option('erpgulf_sg_api_key')); ?>"
                       class="regular-text"></td>
        </tr>
        <tr>
            <th>From Email</th>
            <td><input type="email" name="sg_from"
                       value="<?php echo esc_attr(get_option('erpgulf_sg_from')); ?>"
                       class="regular-text"></td>
        </tr>
        <tr>
            <th>From Name</th>
            <td><input type="text" name="sg_from_name"
                       value="<?php echo esc_attr(get_option('erpgulf_sg_from_name', 'ERPGulf Security')); ?>"
                       class="regular-text"></td>
        </tr>
    </table>
</div>
```

Save the fields:

```php
update_option('erpgulf_sg_api_key',   trim(sanitize_text_field($_POST['sg_api_key'])));
update_option('erpgulf_sg_from',      trim(sanitize_email($_POST['sg_from'])));
update_option('erpgulf_sg_from_name', trim(sanitize_text_field($_POST['sg_from_name'])));
```

---

#### Email Provider Contract — what every provider file must follow

```
Function name:   erpgulf_send_email_{yourprovider}()
Parameters:      ( string $email, int $otp, array $settings )
Returns:         [ 'success' => true ]
              or [ 'success' => false, 'message' => 'reason' ]

The $settings array will contain at minimum:
    subject      Resolved email subject line
    message_en   Resolved English body text
    message_ar   Resolved Arabic body text

Use erpgulf_build_email_body($otp, $msg_en, $msg_ar) to
generate the shared bilingual HTML email template.
This keeps the email design consistent across all providers.
```

---

### Running Both SMS Providers Simultaneously

If you want to send via **two SMS providers at the same time**  
(e.g. ExpertTexting as primary, Twilio as backup):

```php
// In erpgulf_handle_send_otp() — replace the SMS block with:

$sms_settings_et = [
    'username'   => get_option('erpgulf_et_username'),
    'api_key'    => get_option('erpgulf_et_api_key'),
    'api_secret' => get_option('erpgulf_et_api_secret'),
    'message_en' => $msg_sms_en,
    'message_ar' => $msg_sms_ar,
    'user_id'    => $user->ID,
];

$r = erpgulf_send_sms_experttexting($phone, $otp, $sms_settings_et);

// If ExpertTexting fails, try Twilio as fallback
if ( empty($r['success']) ) {
    $r = erpgulf_send_sms_twilio($phone, $otp, [
        'account_sid' => get_option('erpgulf_twilio_sid'),
        'auth_token'  => get_option('erpgulf_twilio_token'),
        'from_number' => get_option('erpgulf_twilio_from'),
        'message_en'  => $msg_sms_en,
        'user_id'     => $user->ID,
    ]);
}

if ( empty($r['success']) ) {
    $errors[] = 'SMS: ' . ( $r['message'] ?? 'Unknown error' );
}
```

---

### Provider Summary

| Provider | File | Function | Type |
|---|---|---|---|
| ExpertTexting | `experttexting-provider.php` | `erpgulf_send_sms_experttexting()` | SMS (built-in) |
| OCI SMTP | `oci-smtp-provider.php` | `erpgulf_send_email_oci()` | Email (built-in) |
| Twilio | `twilio-provider.php` | `erpgulf_send_sms_twilio()` | SMS (example) |
| SendGrid | `sendgrid-provider.php` | `erpgulf_send_email_sendgrid()` | Email (example) |
| Any other | `{name}-provider.php` | `erpgulf_send_{type}_{name}()` | Your provider |

---

## Design Team Guide

### What you receive
- Plugin is already installed and configured
- Credentials (SMS + Email) are already entered in the settings
- Your job is to design the login page and style the form

### Step 1 — Create the login page

```
WordPress Admin → Pages → Add New
→ Title: Login
→ URL slug: login
→ Content area: [erpgulf_otp_form]
→ Page template: Full Width (no sidebar)
→ Publish
```

### Step 2 — Style the form

All styling goes in your **child theme's `functions.php`**.

```php
add_filter('erpgulf_otp_form_styles', function($css) {
    return $css . '
        .otp-box {
            border-radius: 0;
            border-top: 4px solid #your-brand-color;
        }
        .otp-box button {
            background: #your-brand-color;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .otp-box input[type="text"],
        .otp-box input[type="password"] {
            border-radius: 0;
            border: 2px solid #ddd;
        }
    ';
});
```

### Step 3 — Translate error messages (Arabic site)

```php
add_filter('erpgulf_error_messages', function($message, $error_type) {
    $arabic = [
        'number_not_found' => 'لم نجد حساباً بهذه البيانات.',
        'invalid_code'     => 'الرمز غير صحيح. حاول مجدداً.',
        'expired_code'     => 'انتهت صلاحية الرمز.',
        'session_lost'     => 'انتهت الجلسة. يرجى تحديث الصفحة.',
        'wrong_password'   => 'كلمة المرور غير صحيحة.',
        'send_failed'      => 'تعذّر إرسال الرمز.',
    ];
    return $arabic[$error_type] ?? $message;
}, 10, 2);
```

### Element IDs — style freely, never rename

```
.otp-box              Outer wrapper
.otp-hint             Small hint text paragraphs
#otp-msg              Error / success message

#otp-step-1           Step 1 panel (identifier input)
#otp-step-otp         Step 2a panel (OTP input)
#otp-step-password    Step 2b panel (password input)

#identifier           Email / phone / username field
#otp-code             6-digit code field
#password             Password field

#btn-send             Continue button
#btn-verify-otp       Verify & Login button
#btn-verify-password  Login button
```

---

## Testing Guide

### Setup — enable debug logging

Open `/var/www/woocommerce/wp-config.php` and set:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Watch the log (SSH):

```bash
tail -f /var/www/woocommerce/wp-content/debug.log | grep "TEST"
```

Add test hooks to `functions.php`:

```php
add_action('erpgulf_before_save_settings', function($post_data) {
    error_log('✅ HOOK TEST: settings saved by user ' . get_current_user_id());
});
add_action('erpgulf_after_send_otp', function($user_id, $otp, $phone) {
    error_log("✅ HOOK TEST: OTP sent — user {$user_id}, phone {$phone}");
}, 10, 3);
add_action('erpgulf_user_logged_in', function($user_id, $method) {
    error_log("✅ HOOK TEST: user {$user_id} logged in via {$method}");
}, 10, 2);
add_action('erpgulf_verify_otp_failed', function($user_id, $submitted, $stored) {
    error_log("✅ HOOK TEST: wrong OTP — user {$user_id} typed {$submitted}");
}, 10, 3);
add_filter('erpgulf_error_messages', function($message, $error_type) {
    error_log("✅ FILTER TEST: error type={$error_type}");
    return $message;
}, 10, 2);
add_filter('erpgulf_otp_message_text', function($message, $otp, $phone, $user_id) {
    error_log("✅ FILTER TEST: SMS sending to {$phone}");
    return $message;
}, 10, 4);
add_filter('erpgulf_redirect_after_login', function($url, $user_id) {
    error_log("✅ FILTER TEST: redirect to {$url} for user {$user_id}");
    return $url;
}, 10, 2);
```

---

### Test Checklist

#### Test 1 — Settings save
```
Admin → ERPGulf OTP → click Save All Settings
```
- Screen: green ✅ Settings saved notice
- Log: `✅ HOOK TEST: settings saved by user 1`

---

#### Test 2 — Test page loads
```
Visit: yoursite.com/otp-test
```
- Login box appears centred on grey background
- No theme header or footer

---

#### Test 3 — Login by email
```
/otp-test → type a registered email → Continue
```
- OTP step appears
- Email arrives (bilingual HTML)
- SMS arrives if phone on file
- Log: `✅ FILTER TEST: SMS sending to 9665xxxxxxx`
- Log: `✅ HOOK TEST: OTP sent — user X`

---

#### Test 4 — Login by phone (all formats)

```
0501234567          → ✅ finds user
+966501234567       → ✅ finds user
00966501234567      → ✅ finds user
501 234 567         → ✅ finds user
```

---

#### Test 5 — Login by username
```
/otp-test → type a WordPress username → Continue
```
- Password field appears (not OTP)
- Correct password → login
- Wrong password → error + log

---

#### Test 6 — Correct OTP
```
/otp-test → phone → Continue → correct code → Verify
```
- Redirects to My Account
- User is logged in
- Log: `✅ HOOK TEST: user X logged in via otp`

---

#### Test 7 — Wrong OTP
```
Type 000000 → Verify
```
- Error message appears
- Log: `✅ HOOK TEST: wrong OTP — user X typed 000000`

---

#### Test 8 — Unknown identifier
```
/otp-test → nobody@nowhere.com → Continue
```
- "We couldn't find an account…" on screen
- Log: `✅ FILTER TEST: error type=number_not_found`

---

#### Test 9 — Expired OTP
```
Request OTP → wait 5 min 10 sec → submit it
```
- "This code has expired." on screen

---

#### Test 10 — Error messages filter
```php
add_filter('erpgulf_error_messages', function($msg, $type) {
    if ($type === 'invalid_code') return '🔴 FILTER WORKING';
    return $msg;
}, 10, 2);
```
Type wrong code → screen shows `🔴 FILTER WORKING`

---

#### Test 11 — SMS text filter
```php
add_filter('erpgulf_otp_message_text', function($msg, $otp, $phone, $uid) {
    return "FILTER TEST — code: {$otp}";
}, 10, 4);
```
SMS received says `FILTER TEST — code: 123456`

---

#### Test 12 — Redirect filter
```php
add_filter('erpgulf_redirect_after_login', function($url, $uid) {
    return home_url('/');
}, 10, 2);
```
After login → goes to homepage

---

#### Test 13 — Style filter
```php
add_filter('erpgulf_test_page_styles', function($css) {
    return $css . ' .otp-box button { background: #e63946 !important; } body { background: #1a1a2e; }';
});
```
Visit `/otp-test` → red button, dark background

---

#### Test 14 — Shortcode on a real page
```
Admin → Pages → Add New → content: [erpgulf_otp_form] → Publish → visit
```
- Theme header and footer appear
- Form works identically to /otp-test

---

### Quick Results Reference

| Test | Expected | Hook / Filter confirmed |
|---|---|---|
| Settings save | Green notice + log | `erpgulf_before_save_settings` ✅ |
| Test page | Box, no theme | — |
| Email login | OTP arrives | `erpgulf_after_send_otp` ✅ |
| Phone (all formats) | OTP each time | `erpgulf_clean_phone` ✅ |
| Username login | Password field | — |
| Correct OTP | Login + redirect | `erpgulf_user_logged_in` ✅ `erpgulf_redirect_after_login` ✅ |
| Wrong OTP | Error + log | `erpgulf_verify_otp_failed` ✅ `erpgulf_error_messages` ✅ |
| Unknown identifier | Not found error | `erpgulf_error_messages` ✅ |
| Expired OTP | Expired error | — |
| Error filter | Custom text | `erpgulf_error_messages` ✅ |
| SMS filter | Custom SMS | `erpgulf_otp_message_text` ✅ |
| Redirect filter | Homepage | `erpgulf_redirect_after_login` ✅ |
| Style filter | Red button | `erpgulf_test_page_styles` ✅ |
| Shortcode | Themed page | — |

All 14 passing = plugin fully working ✅

---

### Cleanup after testing

Remove all test hooks from `functions.php`.

Turn off debug in `wp-config.php`:
```php
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
```

---

## Troubleshooting

### SMS not sending
```
Error: Unrecognized or invalid To Number
```
- Check the phone stored in the user profile has at least 7 digits
- Check Default Country Code is set correctly in settings

---

### Email not sending
```
Error: 535 Authorization failed: Envelope From not authorized
```
1. OCI Console → Email Delivery → Approved Senders → add the sender email
2. Publish SPF and DKIM DNS records for the sender domain
3. Wait 5 minutes → test again

---

### Nothing in debug.log
- Confirm `WP_DEBUG_LOG` is `true` in `wp-config.php`
- Check log file exists: `ls -la wp-content/debug.log`
- If missing: `touch wp-content/debug.log && chmod 666 wp-content/debug.log`

---

### Hooks not firing
- Confirm code is at the bottom of `functions.php`
- Check for PHP syntax errors: `php -l functions.php`
- Confirm plugin is **Active** in WordPress Admin → Plugins

---

### Shortcode shows nothing
- Confirm plugin is Active
- Confirm you typed `[erpgulf_otp_form]` exactly
- Check the page is Published not Draft

---

## Security Notes

- OTP expires after **5 minutes**
- OTP deleted from database immediately after successful use
- Password path has a **1-second delay** to slow brute-force attempts
- `/otp-test` has no link, no menu item, not indexed — keep it private
- All user input is sanitized before use
- Admin settings form protected with WordPress nonces

---

## Support

**Author:** Farook K — [https://medium.com/nothing-big](https://medium.com/nothing-big)  
**Test URL:** `yoursite.com/otp-test`  
**Settings:** WordPress Admin → ERPGulf OTP  
**Shortcode:** `[erpgulf_otp_form]`
**Contact us:** support@erpgulf.com  
**Supporting Articles:** https://app.erpgulf.com/en/articles
**Blogs:** https://app.erpgulf.com/blog
**Documentation and Hosting :** https://docs.claudion.com/



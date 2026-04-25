# Simple OTP Plugin for WooCommerce by ERPGulf
**Version:** 4.6.0  
**Author:** Farook K — [https://medium.com/nothing-big](https://medium.com/nothing-big)  
**Requires:** WordPress 6.0+, WooCommerce 7.0+, PHP 8.0+  
**Support:** support@erpgulf.com  
**Articles:** https://app.erpgulf.com/en/articles  
**Blog:** https://app.erpgulf.com/blog  
**Docs & Hosting:** https://docs.claudion.com/

---

## What You Can Do Without Knowing Our Code

This plugin is built so that **designers and developers never need to read the plugin source code** to fully customise it. Everything is exposed through PHP hooks, filters, and a public JavaScript API.

The plugin handles all the logic — OTP generation, SMS delivery, email delivery, phone lookup, authentication, and session management. You handle everything visual and behavioural through the interfaces below.

---

### PHP Filters — Change Things Before They Happen

A filter intercepts a value just before the plugin uses it. You receive the value, change it, and return it. The plugin uses whatever you return.

---

#### `erpgulf_otp_form_styles` — Change every colour, font, spacing, shadow

The plugin ships with a clean default style. This filter gives you the entire CSS string to override or extend.

```php
// child theme / functions.php

add_filter('erpgulf_otp_form_styles', function($css) {
    return $css . '

        /* Box */
        .otp-box {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            padding: 40px;
            border-top: 4px solid #1a1acc;
        }

        /* Heading */
        .otp-box h3 {
            font-family: "Inter", sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: #1a1a2e;
        }

        /* Inputs */
        .otp-box input[type="text"],
        .otp-box input[type="password"] {
            height: 52px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
        }
        .otp-box input:focus {
            border-color: #1a1acc;
            outline: none;
        }

        /* Button */
        .otp-box button {
            height: 52px;
            background: #1a1acc;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .otp-box button:hover { background: #1313aa; }

        /* Hint text */
        .otp-hint { font-size: 13px; color: #888; }

        /* Error / success message */
        #otp-msg { font-size: 13px; font-weight: 600; }
    ';
});
```

**What you can change:** background, border, shadow, radius, padding, width, all font sizes, all font families, all colours, input height, button height, hover states, focus states, spacing between elements — everything visual.

**What you cannot change here:** the HTML structure (use `erpgulf_otp_form_html` for that).

---

#### `erpgulf_otp_form_html` — Completely rebuild the form layout

Replace the entire HTML of the login form. This applies to both the `/otp-test` page and any page using the `[erpgulf_otp_form]` shortcode.

```php
add_filter('erpgulf_otp_form_html', function($html) {
    return '
    <div class="otp-box">

        <!-- Your logo -->
        <img src="' . get_stylesheet_directory_uri() . '/images/logo.svg"
             style="display:block; margin:0 auto 24px; width:120px;">

        <h3>مرحبًا بعودتك</h3>
        <p class="otp-hint" style="text-align:center; margin-bottom:24px;">
            سجّل الدخول إلى حسابك
        </p>

        <!-- Step 1 -->
        <div id="otp-step-1">
            <label style="font-size:13px; font-weight:600; display:block; margin-bottom:6px;">
                البريد الإلكتروني أو رقم الجوال
            </label>
            <input type="text" id="identifier"
                   placeholder="أدخل بريدك الإلكتروني أو جوالك"
                   dir="rtl" autocomplete="username" />
            <button id="btn-send">متابعة</button>
        </div>

        <!-- Step 2a: OTP -->
        <div id="otp-step-otp" style="display:none;">
            <p class="otp-hint">تم إرسال رمز التحقق إلى جوالك وبريدك.</p>
            <input type="text" id="otp-code"
                   placeholder="أدخل الرمز المكوّن من 6 أرقام"
                   maxlength="6" autocomplete="one-time-code" />
            <button id="btn-verify-otp">تحقق وسجّل الدخول</button>
        </div>

        <!-- Step 2b: Password -->
        <div id="otp-step-password" style="display:none;">
            <p class="otp-hint">أدخل كلمة المرور للمتابعة.</p>
            <input type="password" id="password"
                   placeholder="كلمة المرور"
                   autocomplete="current-password" />
            <button id="btn-verify-password">تسجيل الدخول</button>
        </div>

        <p id="otp-msg"></p>
    </div>';
});
```

> ⚠️ **These element IDs must stay exactly as shown** — the plugin JavaScript is wired to them. You can put them inside any structure you like, but never rename them:
>
> `#otp-step-1` `#otp-step-otp` `#otp-step-password`  
> `#identifier` `#otp-code` `#password`  
> `#btn-send` `#btn-verify-otp` `#btn-verify-password` `#otp-msg`

**What you can change:** layout, heading text, label text, placeholder text, button labels, wrappers, logo, icons, dividers, Arabic/RTL direction, any extra elements around the inputs.

---

#### `erpgulf_error_messages` — Translate every error to any language

Every error message the user sees passes through this filter before being displayed. You receive the message and the error type, and return whatever text you want.

```php
add_filter('erpgulf_error_messages', function($message, $error_type) {

    // Full Arabic translation
    $arabic = [
        'number_not_found' => 'لم نجد حساباً مرتبطاً بهذا البريد أو الجوال.',
        'invalid_code'     => 'الرمز الذي أدخلته غير صحيح. حاول مرة أخرى.',
        'expired_code'     => 'انتهت صلاحية الرمز. اضغط إرسال للحصول على رمز جديد.',
        'session_lost'     => 'انتهت جلستك. يرجى تحديث الصفحة والمحاولة مجدداً.',
        'wrong_password'   => 'كلمة المرور غير صحيحة. حاول مجدداً.',
        'send_failed'      => 'تعذّر إرسال رمز التحقق. يرجى المحاولة لاحقاً.',
    ];

    return $arabic[$error_type] ?? $message;

}, 10, 2);
```

| `$error_type` | When it shows |
|---|---|
| `number_not_found` | Email / phone / username not found in the system |
| `invalid_code` | User typed the wrong OTP code |
| `expired_code` | OTP is older than 5 minutes |
| `session_lost` | Browser lost track of the login session |
| `wrong_password` | Password was incorrect |
| `send_failed` | Both SMS and email failed to deliver |

**What you can change:** the text of every error, the language, the tone, emojis, HTML formatting inside the message.

---

#### `erpgulf_redirect_after_login` — Send users anywhere after login

By default, users go to the WooCommerce My Account page after logging in. Override this per user, per role, or per any condition you choose.

```php
add_filter('erpgulf_redirect_after_login', function($url, $user_id) {

    // Admins → WordPress dashboard
    if ( user_can($user_id, 'manage_options') ) {
        return admin_url();
    }

    // Wholesale customers → wholesale ordering page
    if ( user_can($user_id, 'wholesale_customer') ) {
        return home_url('/wholesale-orders/');
    }

    // Premium members → their subscription page
    $is_premium = get_user_meta($user_id, 'is_premium_member', true);
    if ( $is_premium ) {
        return home_url('/my-subscription/');
    }

    // Everyone else → default (My Account)
    return $url;

}, 10, 2);
```

**What you can change:** the destination URL for any user, based on any condition — role, meta value, membership, purchase history, anything WordPress gives you access to.

---

#### `erpgulf_otp_message_text` — Customise the SMS text

The plugin builds a bilingual (English + Arabic) SMS from the templates in the admin settings. This filter fires just before the SMS is transmitted — you can replace the text entirely.

```php
add_filter('erpgulf_otp_message_text', function($message, $otp, $phone, $user_id) {

    $user = get_userdata($user_id);
    $name = $user->first_name ?: 'عميلنا';
    $site = get_bloginfo('name');

    // Arabic only, personalised
    return "مرحباً {$name}، رمز تسجيل الدخول إلى {$site} هو {$otp}. صالح 5 دقائق. لا تشاركه.";

}, 10, 4);
```

| Parameter | Type | Description |
|---|---|---|
| `$message` | string | The full bilingual SMS text built from admin templates |
| `$otp` | int | The 6-digit OTP |
| `$phone` | string | The normalised international phone number |
| `$user_id` | int | WordPress user ID |

> Must return a string. Returning nothing makes the SMS body empty.

---

### PHP Actions — React After Things Happen

An action fires after something has happened. The plugin does not wait for your response — you react independently. Use these to log, notify, count, award, or restrict.

---

#### `erpgulf_before_save_settings` — React to settings changes

Fires the moment the admin clicks Save All Settings, before anything is written to the database. Use it to validate, log, or send yourself an alert.

```php
add_action('erpgulf_before_save_settings', function($post_data) {

    $who  = wp_get_current_user()->user_login;
    $time = current_time('mysql');
    $new_key = sanitize_text_field($post_data['et_api_key'] ?? '');

    // Email yourself every time settings change
    wp_mail(
        get_option('admin_email'),
        '⚙️ OTP Plugin Settings Changed',
        "User '{$who}' changed OTP settings at {$time}.\nNew API key starts with: " . substr($new_key, 0, 6) . '...'
    );

});
```

---

#### `erpgulf_after_send_otp` — Log, count, notify after OTP is sent

Fires after both SMS and email have been dispatched successfully. The OTP has already been sent — this is your chance to react.

```php
add_action('erpgulf_after_send_otp', function($user_id, $otp, $phone) {

    // Count daily OTP requests — detect abuse
    $today    = current_time('Y-m-d');
    $meta_key = 'otp_requests_' . $today;
    $count    = (int) get_user_meta($user_id, $meta_key, true) + 1;
    update_user_meta($user_id, $meta_key, $count);

    // Alert admin if more than 5 OTPs in one day from same user
    if ( $count >= 5 ) {
        wp_mail(
            get_option('admin_email'),
            '🚨 Suspicious OTP Activity',
            "User ID {$user_id} has requested {$count} OTPs today.\nPhone: {$phone}"
        );
    }

}, 10, 3);
```

---

#### `erpgulf_user_logged_in` — Award points, track logins

Fires immediately after a successful login, whether by OTP or by password. Use it to record the event, award loyalty points, update timestamps, or trigger any post-login workflow.

```php
add_action('erpgulf_user_logged_in', function($user_id, $method) {

    // Record login history
    $log   = get_user_meta($user_id, 'login_history', true) ?: [];
    $log[] = [
        'time'   => current_time('mysql'),
        'method' => $method,             // 'otp' or 'password'
        'ip'     => $_SERVER['REMOTE_ADDR'],
    ];
    update_user_meta($user_id, 'login_history', array_slice($log, -50));

    // Award 10 loyalty points — once per day
    $today      = current_time('Y-m-d');
    $last_award = get_user_meta($user_id, 'last_points_award', true);
    if ( $last_award !== $today ) {
        $points = (int) get_user_meta($user_id, 'loyalty_points', true);
        update_user_meta($user_id, 'loyalty_points', $points + 10);
        update_user_meta($user_id, 'last_points_award', $today);
    }

}, 10, 2);
```

| Parameter | Type | Description |
|---|---|---|
| `$user_id` | int | WordPress user ID |
| `$method` | string | `'otp'` or `'password'` |

---

#### `erpgulf_verify_otp_failed` — Lock accounts, alert admin

Fires every time a user submits the wrong OTP code. Use it to detect brute-force attempts and lock accounts or send security alerts.

```php
add_action('erpgulf_verify_otp_failed', function($user_id, $submitted_otp, $stored_otp) {

    // Count failures
    $fails = (int) get_user_meta($user_id, 'otp_fail_count', true) + 1;
    update_user_meta($user_id, 'otp_fail_count', $fails);

    // After 3 failures — wipe the OTP and alert admin
    if ( $fails >= 3 ) {

        // Wipe stored OTP so it cannot be guessed even if correct
        delete_user_meta($user_id, 'erpgulf_current_otp');
        delete_user_meta($user_id, 'erpgulf_otp_expiry');
        update_user_meta($user_id, 'otp_fail_count', 0);

        // Alert admin
        wp_mail(
            get_option('admin_email'),
            '🔒 OTP Brute Force Alert',
            "User ID {$user_id} failed OTP verification 3 times.\nLast attempt: {$submitted_otp}"
        );
    }

}, 10, 3);
```

| Parameter | Type | Description |
|---|---|---|
| `$user_id` | int | WordPress user ID |
| `$submitted_otp` | string | What the user typed |
| `$stored_otp` | string | What was stored (treat as sensitive) |

---

### JavaScript API — Wire Any Button on Any Page

The plugin publishes `window.ERPGulfOTP` on every front-end page automatically. Any developer can open the browser console and use it immediately.

```javascript
// Open browser console on any page and type:
ERPGulfOTP
// → { version: "4.6.0", sendOTP: fn, verifyOTP: fn, verifyPassword: fn, defaultRedirect: "..." }
```

---

#### `ERPGulfOTP.sendOTP()` — Wire any button to send OTP

```javascript
// Your button — any button on any page
document.getElementById('my-login-btn').addEventListener('click', function() {

    var identifier = document.getElementById('my-input').value;

    ERPGulfOTP.sendOTP(identifier,

        function(data) {
            // data.user_id  → WordPress user ID (keep this for the verify step)
            // data.method   → 'otp' (email/phone) or 'password' (username)

            window._userId = data.user_id;

            if (data.method === 'otp') {
                // Show your OTP input
                document.getElementById('my-code-step').style.display = 'block';
                showMessage('Code sent to your phone and email.', 'green');
            } else {
                // Show your password input
                document.getElementById('my-password-step').style.display = 'block';
                showMessage('Enter your password to continue.', 'blue');
            }
        },

        function(err) {
            showMessage(err, 'red');
        }
    );
});
```

---

#### `ERPGulfOTP.verifyOTP()` — Wire any input to verify the code

```javascript
document.getElementById('my-verify-btn').addEventListener('click', function() {

    var code = document.getElementById('my-code-input').value;

    ERPGulfOTP.verifyOTP(window._userId, code,

        function(data) {
            // data.redirect → where to send the user (from plugin settings)
            window.location.href = data.redirect;
        },

        function(err) {
            // err → 'Invalid verification code.' or 'This code has expired.' etc.
            showMessage(err, 'red');
        }
    );
});
```

---

#### `ERPGulfOTP.verifyPassword()` — Wire any input to verify password

Used when `sendOTP` returns `method: 'password'` (username was entered).

```javascript
document.getElementById('my-login-submit').addEventListener('click', function() {

    var password = document.getElementById('my-password-input').value;

    ERPGulfOTP.verifyPassword(window._userId, password,

        function(data) {
            window.location.href = data.redirect;
        },

        function(err) {
            showMessage(err, 'red');
        }
    );
});
```

---

#### DOM Events — React to login without touching our code

The plugin fires these events on `document` automatically. Any script on the page can listen — no integration code needed.

```javascript
// Fires after OTP is sent successfully
document.addEventListener('erpgulf:otp:sent', function(e) {
    console.log('OTP sent to user', e.detail.user_id);
    console.log('Method:', e.detail.method);       // 'otp' or 'password'
    console.log('Identifier:', e.detail.identifier); // what the user typed
});

// Fires after successful login (OTP or password)
document.addEventListener('erpgulf:otp:verified', function(e) {
    console.log('User logged in:', e.detail.user_id);
    console.log('Method:', e.detail.method);       // 'otp' or 'password'
    console.log('Redirect:', e.detail.redirect);   // where to send them

    // Simplest possible integration — just listen and redirect
    window.location.href = e.detail.redirect;
});

// Fires when the user types the wrong OTP code
document.addEventListener('erpgulf:otp:failed', function(e) {
    console.log('Wrong code from user', e.detail.user_id);
    console.log('They typed:', e.detail.submitted_otp);
    // Use this to show a warning, count attempts, or lock the UI
});

// Fires on any error (not found, network failure, expired, etc.)
document.addEventListener('erpgulf:login:error', function(e) {
    console.log('Error:', e.detail.message);
    // Use this to show error UI without calling verifyOTP yourself
});
```

**The simplest possible integration using only events:**

```javascript
// No API calls. No button wiring. Just listen.
// The plugin's own form handles everything — you just react.

document.addEventListener('erpgulf:otp:verified', function(e) {
    // Log to your analytics
    myAnalytics.track('login', { method: e.detail.method, userId: e.detail.user_id });
    // Then redirect
    window.location.href = e.detail.redirect;
});

document.addEventListener('erpgulf:otp:failed', function(e) {
    myAnalytics.track('login_failed', { userId: e.detail.user_id });
});
```

---

### Everything in one table

| What you want to do | How |
|---|---|
| Change button colour | `erpgulf_otp_form_styles` filter |
| Change font or spacing | `erpgulf_otp_form_styles` filter |
| Add logo above the form | `erpgulf_otp_form_html` filter |
| Make the form Arabic/RTL | `erpgulf_otp_form_html` filter |
| Translate error messages | `erpgulf_error_messages` filter |
| Send admins to dashboard after login | `erpgulf_redirect_after_login` filter |
| Change the SMS text | `erpgulf_otp_message_text` filter |
| Log every settings change | `erpgulf_before_save_settings` action |
| Count OTP requests per user | `erpgulf_after_send_otp` action |
| Award loyalty points on login | `erpgulf_user_logged_in` action |
| Lock account after 3 wrong codes | `erpgulf_verify_otp_failed` action |
| Wire your own button to send OTP | `ERPGulfOTP.sendOTP()` |
| Wire your own input to verify code | `ERPGulfOTP.verifyOTP()` |
| Wire your own input to verify password | `ERPGulfOTP.verifyPassword()` |
| React to login in another script | `erpgulf:otp:verified` DOM event |
| React to wrong code in another script | `erpgulf:otp:failed` DOM event |
| Integrate with an existing modal | Capture phase + `ERPGulfOTP` methods |

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

---

## How to Use — Two Surfaces

### 1. Test Page (private, for developers only)

```
https://yoursite.com/otp-test
```

- Full standalone HTML page — no theme header or footer
- Not in the database, not in the menu, not indexed by Google
- **Do not share this URL with customers**

### 2. Shortcode (for the real customer-facing page)

```
[erpgulf_otp_form]
```

Drop into any WordPress page. The active theme provides the header, footer, and layout.

---

## Phone Number Matching

The plugin uses **last-7-digit matching** — works regardless of how the number is stored or typed.

| User types | Matches? |
|---|---|
| `0501234567` | ✅ |
| `+966501234567` | ✅ |
| `00966501234567` | ✅ |
| `501 234 567` | ✅ |

---

## Login Flow

```
User types identifier
        │
        ├── Email?     → send OTP via SMS + Email
        ├── Username?  → show password field
        └── Phone?     → send OTP via SMS + Email
                │
                ├── OTP correct + not expired → log in → redirect
                ├── OTP wrong                 → erpgulf_verify_otp_failed fires
                └── OTP expired (5 min)       → request new code
```

---

## Adding New Providers

Each provider is a single PHP file with one function.

### SMS Provider Contract
```
Function:  erpgulf_send_sms_{name}( string $phone, int $otp, array $settings ): array
Returns:   [ 'success' => true ]
        or [ 'success' => false, 'message' => 'reason' ]
```

### Email Provider Contract
```
Function:  erpgulf_send_email_{name}( string $email, int $otp, array $settings ): array
Returns:   [ 'success' => true ]
        or [ 'success' => false, 'message' => 'reason' ]
```

### Provider Summary

| Provider | File | Type |
|---|---|---|
| ExpertTexting | `experttexting-provider.php` | SMS (built-in) |
| OCI SMTP | `oci-smtp-provider.php` | Email (built-in) |
| Twilio | `twilio-provider.php` | SMS (example) |
| SendGrid | `sendgrid-provider.php` | Email (example) |

See the full provider examples in the detailed hooks reference section above.

---

## Integrating With an Existing Login Modal

If your site has a login modal from another plugin, intercept its button using the JS API. Add to child theme `functions.php`:

```php
add_action('wp_footer', function() { ?>
<script>
(function($) {

    // Language-aware redirect
    var lang = 'ar';
    try { lang = localStorage.getItem('siteLanguage') || 'ar'; } catch(e) {}
    var redirect = lang === 'en'
        ? '<?php echo esc_url(home_url('/en/')); ?>'
        : '<?php echo esc_url(home_url('/')); ?>';

    // Rename the button
    var renamed = false;
    var obs = new MutationObserver(function() {
        if (renamed) return;
        var btn = document.querySelector('.awp-request-btn[data-method="whatsapp"]');
        if (btn) { btn.innerHTML = '<i class="ri-message-3-line"></i> Send SMS OTP'; renamed = true; obs.disconnect(); }
    });
    obs.observe(document.body, { childList: true, subtree: true });

    document.addEventListener('click', function(e) {

        var sendBtn = e.target.closest('.awp-request-btn[data-method="whatsapp"]');
        if (sendBtn) {
            e.stopImmediatePropagation(); e.preventDefault();
            var phone = $('#awp_phone').val().trim().replace(/[^0-9]/g, '');
            if (!phone) { $('#awp_login_message_phone').html('<p style="color:red;">Please enter your phone number.</p>'); return; }
            $('#awp_login_message_phone').html('<p style="color:#666;">Looking up your account...</p>');
            ERPGulfOTP.sendOTP(phone, function(data) {
                window._erpgulf_user_id = data.user_id;
                if (data.method === 'password') {
                    if (!$('#erpgulf_pw_group').length) {
                        $('#awp_otp_group_phone').before('<div id="erpgulf_pw_group" style="margin-bottom:12px;"><input type="password" id="erpgulf_password" placeholder="Enter your password" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;"></div>');
                    }
                    $('#awp_phone_group').hide(); $('#erpgulf_pw_group').show(); $('#erpgulf_password').focus();
                    $('#awp_verify_row_phone').show(); $('#awp_verify_otp_phone').html('Login');
                    $('#awp_login_message_phone').html('<p style="color:#007cba;">Username found. Enter your password.</p>');
                } else {
                    $('#awp_otp_group_phone, #awp_verify_row_phone, #awp_otp_sent_message_phone').show();
                    $('#awp_verify_otp_phone').html('Confirm Code');
                    $('#awp_login_message_phone').html('<p style="color:green;">✅ Code sent to your phone and email.</p>');
                }
            }, function(err) { $('#awp_login_message_phone').html('<p style="color:red;">' + err + '</p>'); });
            return;
        }

        var verifyBtn = e.target.closest('#awp_verify_otp_phone');
        if (verifyBtn) {
            e.stopImmediatePropagation(); e.preventDefault();
            if (!window._erpgulf_user_id) { $('#awp_login_message_phone').html('<p style="color:red;">Session lost. Refresh and try again.</p>'); return; }
            if ($('#erpgulf_pw_group').is(':visible')) {
                var pw = $('#erpgulf_password').val();
                if (!pw) { $('#awp_login_message_phone').html('<p style="color:red;">Please enter your password.</p>'); return; }
                $('#awp_login_message_phone').html('<p style="color:#666;">Logging in...</p>');
                ERPGulfOTP.verifyPassword(window._erpgulf_user_id, pw,
                    function() { window.location.href = redirect; },
                    function(err) { $('#awp_login_message_phone').html('<p style="color:red;">' + err + '</p>'); }
                );
            } else {
                var otp = $('#awp_otp_phone').val().trim();
                if (!otp) { $('#awp_login_message_phone').html('<p style="color:red;">Please enter the code.</p>'); return; }
                $('#awp_login_message_phone').html('<p style="color:#666;">Verifying...</p>');
                ERPGulfOTP.verifyOTP(window._erpgulf_user_id, otp,
                    function() { $('#awp_login_message_phone').html('<p style="color:green;">✅ Verified! Redirecting...</p>'); setTimeout(function() { window.location.href = redirect; }, 800); },
                    function(err) { $('#awp_login_message_phone').html('<p style="color:red;">' + err + '</p>'); }
                );
            }
            return;
        }

    }, true); // capture phase — fires before the original plugin

})(jQuery);
</script>
<?php });
```

---

## Testing Guide

### Enable debug logging

```php
// wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

```bash
tail -f /var/www/woocommerce/wp-content/debug.log | grep "TEST"
```

### JS API tests (browser console)

```javascript
ERPGulfOTP                              // confirm API loaded
ERPGulfOTP.sendOTP('97455124924', console.log, console.error)
ERPGulfOTP.verifyOTP(2, '123456', console.log, console.error)
document.addEventListener('erpgulf:otp:verified', function(e) { console.log(e.detail); })
```

### Test checklist

| Test | Expected |
|---|---|
| `ERPGulfOTP` in console | Object with 4 methods |
| sendOTP with phone | `method:'otp'` + SMS arrives |
| sendOTP with username | `method:'password'` no SMS |
| verifyOTP correct code | Logged in + redirect |
| verifyOTP wrong code | Error message |
| verifyPassword correct | Logged in + redirect |
| verifyPassword wrong | Error message |
| `erpgulf:otp:sent` event | Fires after sendOTP |
| `erpgulf:otp:verified` event | Fires after login |
| Settings save | Green notice in admin |

### Cleanup

```php
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
```

---

## Troubleshooting

| Problem | Fix |
|---|---|
| `ERPGulfOTP is not defined` | Plugin file not updated to v4.6.0. Re-upload `erpgulf-otp-login.php` |
| SMS: Unrecognized To Number | Check Default Country Code in settings |
| Email: 535 not authorized | Add sender to OCI Approved Senders. Publish SPF/DKIM |
| Nothing in debug.log | `touch wp-content/debug.log && chmod 666 wp-content/debug.log` |
| Hooks not firing | Run `php -l functions.php` to check for syntax errors |
| Shortcode shows nothing | Confirm plugin is Active. Check page is Published |

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
**Email:** support@erpgulf.com  
**Articles:** https://app.erpgulf.com/en/articles  
**Blog:** https://app.erpgulf.com/blog  
**Docs & Hosting:** https://docs.claudion.com/  
**Test URL:** `yoursite.com/otp-test`  
**Settings:** WordPress Admin → ERPGulf OTP  
**Shortcode:** `[erpgulf_otp_form]`  
**JS API:** `window.ERPGulfOTP`
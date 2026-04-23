# ERPGulf Simple OTP
**Version:** 4.5.0  
**Author:** Farook (ERPGulf)  
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

    // Add a logo above the form
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
        return admin_url(); // admins go to dashboard
    }
    return $url; // everyone else goes to My Account

}, 10, 2);
```

---

#### `erpgulf_clean_phone`
Override how the phone number suffix is extracted for lookup matching.

```php
add_filter('erpgulf_clean_phone', function($cleaned, $original) {

    // Use last 9 digits instead of 7 for stricter matching
    $digits = preg_replace('/[^0-9]/', '', $original);
    return substr($digits, -9);

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

| Parameter | Type | Description |
|---|---|---|
| `$post_data` | array | Copy of `$_POST` from the settings form |

---

#### `erpgulf_after_send_otp`
Fires after OTP has been dispatched via SMS and/or email.

```php
add_action('erpgulf_after_send_otp', function($user_id, $otp, $phone) {

    // Count daily OTP requests per user
    $key   = 'otp_requests_' . current_time('Y-m-d');
    $count = (int) get_user_meta($user_id, $key, true);
    update_user_meta($user_id, $key, $count + 1);

    // Alert if more than 5 in one day
    if ( $count + 1 >= 5 ) {
        wp_mail(
            get_option('admin_email'),
            'Suspicious OTP Activity',
            "User {$user_id} has requested " . ($count + 1) . " OTPs today."
        );
    }

}, 10, 3);
```

| Parameter | Type | Description |
|---|---|---|
| `$user_id` | int | WordPress user ID |
| `$otp` | int | The 6-digit OTP (treat as sensitive) |
| `$phone` | string | Raw phone from user meta (may be empty) |

---

#### `erpgulf_user_logged_in`
Fires after a successful login — for both OTP and password methods.

```php
add_action('erpgulf_user_logged_in', function($user_id, $method) {

    // Record login history
    update_user_meta($user_id, 'last_login',        current_time('mysql'));
    update_user_meta($user_id, 'last_login_method', $method);

    // Award daily loyalty points
    $today = current_time('Y-m-d');
    if ( get_user_meta($user_id, 'last_points_award', true) !== $today ) {
        $pts = (int) get_user_meta($user_id, 'loyalty_points', true);
        update_user_meta($user_id, 'loyalty_points',     $pts + 10);
        update_user_meta($user_id, 'last_points_award',  $today);
    }

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
        // Wipe the OTP so it cannot be guessed
        delete_user_meta($user_id, 'erpgulf_current_otp');
        delete_user_meta($user_id, 'erpgulf_otp_expiry');
        update_user_meta($user_id, 'otp_fail_count', 0);
        // Send security alert to admin
        wp_mail(get_option('admin_email'), 'OTP brute force alert', "User {$user_id} failed 3 times.");
    }

}, 10, 3);
```

| Parameter | Type | Description |
|---|---|---|
| `$user_id` | int | WordPress user ID |
| `$submitted_otp` | string | What the user typed |
| `$stored_otp` | string | What was stored (treat as sensitive) |

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
        /* Your custom CSS here */
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

### Step 3 — Translate error messages (if Arabic site)

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

Open a terminal and watch the log:

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
- Input field and Continue button visible

---

#### Test 3 — Login by email
```
/otp-test → type a registered email → Continue
```
- OTP step appears on screen
- Email arrives in inbox (bilingual HTML)
- SMS arrives on phone (if phone on file)
- Log: `✅ FILTER TEST: SMS sending to 9665xxxxxxx`
- Log: `✅ HOOK TEST: OTP sent — user X, phone 9665xxxxxxx`

---

#### Test 4 — Login by phone (test all formats)

Try each format — all should find the same user:

```
0501234567
+966501234567
00966501234567
966501234567
501 234 567
```

Each should trigger OTP send.

---

#### Test 5 — Login by username
```
/otp-test → type a WordPress username → Continue
```
- No OTP sent
- Password field appears (not OTP field)
- Type correct password → login succeeds
- Log: `✅ HOOK TEST: user X logged in via password`
- Type wrong password → "Incorrect password" error
- Log: `✅ FILTER TEST: error type=wrong_password`

---

#### Test 6 — Correct OTP code
```
/otp-test → phone → Continue → type real code → Verify
```
- "✅ Verified! Redirecting…" appears
- Browser redirects to My Account
- User is logged in
- Log: `✅ HOOK TEST: user X logged in via otp`
- Log: `✅ FILTER TEST: redirect to https://...`

---

#### Test 7 — Wrong OTP code
```
/otp-test → phone → Continue → type 000000 → Verify
```
- "Invalid verification code." appears on screen
- Real OTP still works after one wrong attempt
- Log: `✅ HOOK TEST: wrong OTP — user X typed 000000`
- Log: `✅ FILTER TEST: error type=invalid_code`

---

#### Test 8 — Unknown identifier
```
/otp-test → type nobody@nowhere.com → Continue
```
- "We couldn't find an account…" appears on screen
- Nothing sent to anyone
- Log: `✅ FILTER TEST: error type=number_not_found`

---

#### Test 9 — Expired OTP
```
/otp-test → phone → Continue
→ wait 5 minutes and 10 seconds
→ type the code that arrived → Verify
```
- "This code has expired. Please request a new one." appears

---

#### Test 10 — Error messages filter
Add to `functions.php`:
```php
add_filter('erpgulf_error_messages', function($message, $type) {
    if ($type === 'invalid_code') return '🔴 FILTER WORKING';
    return $message;
}, 10, 2);
```
Type wrong code → screen shows `🔴 FILTER WORKING`  
Remove filter → original message returns.

---

#### Test 11 — SMS text filter
Add to `functions.php`:
```php
add_filter('erpgulf_otp_message_text', function($message, $otp, $phone, $user_id) {
    return "FILTER TEST — code: {$otp}";
}, 10, 4);
```
Request OTP → SMS says `FILTER TEST — code: 123456`  
Remove filter → bilingual SMS returns.

---

#### Test 12 — Redirect filter
Add to `functions.php`:
```php
add_filter('erpgulf_redirect_after_login', function($url, $user_id) {
    return home_url('/');
}, 10, 2);
```
Login → browser goes to homepage instead of My Account.  
Remove filter → goes back to My Account.

---

#### Test 13 — Form styles filter
Add to `functions.php`:
```php
add_filter('erpgulf_test_page_styles', function($css) {
    return $css . '
        .otp-box button { background: #e63946 !important; }
        body { background: #1a1a2e; }
    ';
});
```
Visit `/otp-test` → red button, dark background.  
Remove filter → original style returns.

---

#### Test 14 — Shortcode on a real page
```
Admin → Pages → Add New
→ Title: Login Test
→ Content: [erpgulf_otp_form]
→ Publish → visit the page
```
- Theme header and footer appear
- Login form appears inside the page
- Everything works the same as /otp-test
- Form inherits theme fonts and colours

---

### Quick Results Reference

| Test | Expected result | Hook / Filter confirmed |
|---|---|---|
| Settings save | Green notice + log line | `erpgulf_before_save_settings` |
| Test page | Box appears, no theme | — |
| Email login | OTP sent + arrives | `erpgulf_after_send_otp` |
| Phone login (all formats) | OTP sent each time | `erpgulf_clean_phone` |
| Username login | Password field appears | — |
| Correct OTP | Login + redirect | `erpgulf_user_logged_in`, `erpgulf_redirect_after_login` |
| Wrong OTP | Error + log | `erpgulf_verify_otp_failed`, `erpgulf_error_messages` |
| Unknown identifier | Not found error | `erpgulf_error_messages` |
| Expired OTP | Expired error | — |
| Error filter | Custom text on screen | `erpgulf_error_messages` |
| SMS filter | Custom SMS on phone | `erpgulf_otp_message_text` |
| Redirect filter | Goes to homepage | `erpgulf_redirect_after_login` |
| Style filter | Red button, dark bg | `erpgulf_test_page_styles` |
| Shortcode | Form inside themed page | — |

All 14 passing = plugin fully working ✅

---

### Cleanup after testing

Remove all test hooks from `functions.php`.

Turn off debug logging in `wp-config.php`:
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
- Check the phone number stored in the user profile
- Plugin normalises automatically but the number must have at least 7 digits
- Check Default Country Code is set correctly in settings

---

### Email not sending
```
Error: 535 Authorization failed: Envelope From address not authorized
```
1. Go to OCI Console → Email Delivery → Approved Senders
2. Add the exact email in the plugin's Approved Sender field
3. Publish SPF and DKIM DNS records for the sender domain
4. Wait 5 minutes for OCI to verify
5. Test again

---

### Nothing appears in debug.log
- Check `WP_DEBUG_LOG` is `true` in `wp-config.php`
- Check the log file exists: `ls -la wp-content/debug.log`
- If missing: `touch wp-content/debug.log && chmod 666 wp-content/debug.log`

---

### Hooks not firing
- Confirm test code is at the **bottom** of `functions.php`
- Confirm the file was saved
- Check for PHP syntax errors: `php -l functions.php`
- Confirm the plugin is **Active** in WordPress Admin → Plugins

---

### Shortcode shows nothing
- Confirm the plugin is Active
- Confirm you typed `[erpgulf_otp_form]` exactly (no spaces inside brackets)
- Check the page is Published not Draft

---

## Security Notes

- OTP expires after **5 minutes**
- OTP is deleted from the database immediately after successful use
- Password verification has a **1-second delay** to slow brute-force attempts
- The `/otp-test` URL has no link, no menu item, and is not indexed — keep it private
- All user input is sanitized before use
- Admin settings form is protected with WordPress nonces

---

## Support

**Plugin author:** Farook — ERPGulf  
**Test URL:** `yoursite.com/otp-test`  
**Settings:** WordPress Admin → ERPGulf OTP  
**Shortcode:** `[erpgulf_otp_form]`
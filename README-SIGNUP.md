# ERPGulf OTP — Registration Integration Guide
**Version:** 4.6.0  
**Plugin:** ERPGulf Simple OTP

---

## Step 1 — Test First, Integrate Later

Before writing a single line of code, test the registration form live on your site.

**Open this URL in your browser:**
```
yoursite.com/otp-test
```

Click the **Register** tab. Fill in the form and submit. Check:

```
✅ OTP arrives on mobile
✅ OTP arrives on email
✅ Account created in WordPress Admin → Users
✅ All fields saved correctly (billing address etc.)
✅ Redirected to My Account after verification
```

This page is private — never linked from the frontend. It is purely for your own testing.

---

## WP-CLI Commands for Testing

Use these to inspect, verify, and clean up test accounts without touching the WordPress admin.

### Find a user by email
```bash
wp user get test@example.com --field=ID --allow-root
```

### See all meta for a registered user
```bash
wp user meta list USER_ID --allow-root
```

### Check billing fields were saved correctly
```bash
wp user meta get USER_ID billing_first_name --allow-root
wp user meta get USER_ID billing_phone --allow-root
wp user meta get USER_ID billing_address_1 --allow-root
wp user meta get USER_ID billing_country --allow-root
```

### Check OTP is stored (should be empty after successful verification)
```bash
wp user meta get USER_ID erpgulf_current_otp --allow-root
wp user meta get USER_ID erpgulf_otp_expiry --allow-root
```

### Delete a test user cleanly
```bash
wp user delete USER_ID --yes --allow-root
```

### List recently registered users
```bash
wp user list --orderby=registered --order=DESC --number=5 --allow-root \
  --fields=ID,user_email,user_registered,display_name
```

### Manually set an OTP for testing (skip SMS/email)
```bash
wp user meta update USER_ID erpgulf_current_otp 123456 --allow-root
wp user meta update USER_ID erpgulf_otp_expiry 9999999999 --allow-root
```
Use code `123456` on the verification screen — bypasses SMS and email delivery entirely. Useful when testing on a staging server without real SMS credits.

### Check if email already exists
```bash
wp user get test@example.com --allow-root 2>&1 | head -1
```

### Check if phone already exists (last 7 digits match)
```bash
wp user meta list --meta_key=customer_addresses_0_phone --allow-root | grep "1234567"
```

---

## Who this guide is for

| Role | What you need |
|---|---|
| **Designer** | How to style the form, match a Figma design |
| **Frontend Developer** | JS API, wire any button to registration |
| **PHP Developer** | Hooks, filters, validation, custom fields |

No one needs to read the plugin source code. Everything is exposed through hooks, filters, and the JS API.

---

## How Registration Works

```
1. User fills the form
   First Name, Last Name, Email, Mobile, Password,
   Address Line 1, Address Line 2, City, Postcode, State

2. Clicks "Create Account & Send OTP"

3. Plugin validates:
   - All required fields filled
   - Valid email format
   - Email not already registered
   - Mobile last 7 digits not already registered
   - Password at least 6 characters
   - Any custom validation you add via filter

4. Account created immediately

5. OTP sent to mobile (SMS) + email

6. User enters the 6-digit code

7. Account verified + user logged in + redirected
```

---

## For Designers

### The form uses `.otp-box` — same as login

The registration form sits inside the same `.otp-box` container as the login form. Any CSS targeting `.otp-box` applies to both.

### Default CSS class reference

```css
.otp-box              /* outer container — white card */
.otp-tabs             /* tab bar — Login / Register */
.otp-tab              /* individual tab button */
.otp-tab.active       /* currently selected tab */
.otp-tab-content      /* content area for each tab */
#tab-login            /* login tab content */
#tab-register         /* register tab content */
#reg-step-form        /* registration fields area */
#reg-step-otp         /* OTP verification step (hidden until after register) */
#reg-msg              /* status / error message area */

/* Individual field IDs */
#reg-first_name
#reg-last_name
#reg-email
#reg-mobile
#reg-password
#reg-address
#reg-address_2
#reg-city
#reg-postcode
#reg-state

/* Buttons */
#btn-register         /* "Create Account & Send OTP" button */
#btn-reg-verify-otp   /* "Verify & Complete Registration" button */
```

### Override styles — no plugin editing needed

```php
// In functions.php
add_filter( 'erpgulf_otp_form_styles', function( $css ) {

    $css .= '
        /* Change tab colours */
        .otp-tab.active { color: #e63946; border-bottom-color: #e63946; }

        /* Make register button a different colour */
        #btn-register { background: #2d6a4f; }
        #btn-register:hover { background: #1b4332; }

        /* Style the field inputs */
        #tab-register input, #tab-register select {
            border-radius: 8px;
            border-color: #ccc;
        }

        /* Change tab labels font */
        .otp-tab { font-size: 16px; letter-spacing: 0.5px; }
    ';

    return $css;
} );
```

---

## For Frontend Developers

### The JS API — `window.ERPGulfOTP`

Available on every front-end page automatically. No imports needed.

---

### `ERPGulfOTP.register(data, onSuccess, onError)`

Register a new user account and trigger OTP sending.

```javascript
ERPGulfOTP.register(
    {
        first_name: 'Ahmed',
        last_name:  'Al-Rashid',
        email:      'ahmed@example.com',
        mobile:     '0501234567',
        password:   'SecurePass123',
        address:    '123 King Fahd Road',
        address_2:  'Apt 4B',              // optional
        city:       'Riyadh',
        postcode:   '12345',               // optional
        state:      'Riyadh Region',       // optional
    },
    function( data ) {
        // Success — OTP has been sent
        // data.user_id = the new user's ID
        // data.method  = 'register'
        console.log('Account created, OTP sent. User ID:', data.user_id);

        // Store user_id for the verify step
        myApp.newUserId = data.user_id;
        myApp.showOTPInput();
    },
    function( error ) {
        // Validation failed or duplicate email/phone
        console.error('Registration failed:', error);
        myApp.showError(error);
    }
);
```

---

### `ERPGulfOTP.verifyOTP(userId, code, onSuccess, onError)`

Same method used for login — works for registration verification too.

```javascript
ERPGulfOTP.verifyOTP(
    myApp.newUserId,   // from the register callback
    '123456',          // the 6-digit code the user typed
    function( data ) {
        // Verified — user is now logged in
        window.location.href = data.redirect;
    },
    function( error ) {
        myApp.showError(error);
    }
);
```

---

### Complete custom registration flow — zero plugin HTML used

Build your own form in any design. The plugin handles everything behind the scenes.

```html
<!-- Your Figma design — any structure you want -->
<div id="my-signup">
    <input id="s-fname"    placeholder="الاسم الأول" />
    <input id="s-lname"    placeholder="اسم العائلة" />
    <input id="s-email"    placeholder="البريد الإلكتروني" type="email" />
    <input id="s-mobile"   placeholder="رقم الجوال" type="tel" />
    <input id="s-password" placeholder="كلمة المرور" type="password" />
    <input id="s-address"  placeholder="العنوان" />
    <input id="s-city"     placeholder="المدينة" />
    <button id="s-submit">إنشاء حساب</button>
    <p id="s-error" style="color:red;display:none;"></p>
</div>

<div id="my-otp-verify" style="display:none;">
    <input id="s-otp" placeholder="أدخل الرمز" maxlength="6" />
    <button id="s-verify">تحقق</button>
</div>
```

```javascript
var newUserId;

document.getElementById('s-submit').addEventListener('click', function() {
    this.disabled = true;

    ERPGulfOTP.register({
        first_name: document.getElementById('s-fname').value,
        last_name:  document.getElementById('s-lname').value,
        email:      document.getElementById('s-email').value,
        mobile:     document.getElementById('s-mobile').value,
        password:   document.getElementById('s-password').value,
        address:    document.getElementById('s-address').value,
        city:       document.getElementById('s-city').value,
    },
    function(data) {
        newUserId = data.user_id;
        document.getElementById('my-signup').style.display = 'none';
        document.getElementById('my-otp-verify').style.display = 'block';
    },
    function(error) {
        document.getElementById('s-error').textContent = error;
        document.getElementById('s-error').style.display = 'block';
        document.getElementById('s-submit').disabled = false;
    });
});

document.getElementById('s-verify').addEventListener('click', function() {
    var code = document.getElementById('s-otp').value;
    ERPGulfOTP.verifyOTP(newUserId, code,
        function(data) { window.location.href = data.redirect || '/'; },
        function(err)  { alert(err); }
    );
});
```

---

### DOM Events — listen without modifying any code

```javascript
// Fires when a new user registers and OTP is sent
document.addEventListener('erpgulf:otp:sent', function(e) {
    if (e.detail.method === 'register') {
        console.log('New user registered:', e.detail.user_id);
        analytics.track('signup_started');
    }
});

// Fires when OTP is verified (works for both login and registration)
document.addEventListener('erpgulf:otp:verified', function(e) {
    if (e.detail.method === 'register') {
        console.log('Registration complete. Redirecting to:', e.detail.redirect);
        analytics.track('signup_complete', { user_id: e.detail.user_id });
    }
});

// Fires when registration fails validation
document.addEventListener('erpgulf:login:error', function(e) {
    console.error('Error:', e.detail.message);
});
```

---

## For PHP Developers

All customisation goes in `functions.php`. Never edit the plugin.

---

### FILTER — `erpgulf_register_fields`

Override or extend the registration fields. Each field can be required or optional.

```php
/**
 * @param array $fields  Default fields array
 * @return array         Modified fields array
 */
add_filter( 'erpgulf_register_fields', function( $fields ) {

    // Add a company name field
    $fields['company'] = [
        'label'        => 'Company Name',
        'type'         => 'text',
        'required'     => false,
        'meta_key'     => 'billing_company',
        'autocomplete' => 'organization',
    ];

    // Make postcode required
    $fields['postcode']['required'] = true;

    // Remove state field
    unset( $fields['state'] );

    return $fields;
} );
```

**Field structure:**

| Key | Type | Description |
|---|---|---|
| `label` | string | Placeholder text shown in the input |
| `type` | string | HTML input type: `text`, `email`, `tel`, `password` |
| `required` | bool | If true — validated server-side and marked with `*` |
| `meta_key` | string | WordPress user meta key to save the value to |
| `autocomplete` | string | HTML autocomplete attribute |

---

### FILTER — `erpgulf_validate_registration`

Add custom server-side validation. Return an array of error strings. Empty array = valid.

```php
/**
 * @param array $errors     Existing errors (may already contain built-in errors)
 * @param array $post_data  All submitted field values
 * @return array            Updated errors array
 */
add_filter( 'erpgulf_validate_registration', function( $errors, $post_data ) {

    // Example 1 — enforce strong password
    if ( strlen( $post_data['password'] ) < 8 ) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ( ! preg_match( '/[0-9]/', $post_data['password'] ) ) {
        $errors[] = 'Password must contain at least one number.';
    }

    // Example 2 — only allow Saudi mobile numbers
    $digits = preg_replace( '/[^0-9]/', '', $post_data['mobile'] );
    if ( ! preg_match( '/^(05|9665)\d{8}$/', $digits ) ) {
        $errors[] = 'Please enter a valid Saudi mobile number starting with 05.';
    }

    // Example 3 — block certain email domains
    $domain = substr( strrchr( $post_data['email'], '@' ), 1 );
    if ( in_array( $domain, [ 'tempmail.com', 'throwaway.com' ] ) ) {
        $errors[] = 'This email domain is not allowed.';
    }

    return $errors;

}, 10, 2 );
```

---

### FILTER — `erpgulf_register_form_html`

Replace the entire registration form HTML.

```php
/**
 * @param string $html  Default form HTML
 * @return string       Your custom form HTML
 */
add_filter( 'erpgulf_register_form_html', function( $html ) {

    // Return completely custom HTML
    // IDs must match what your JS uses
    return '
        <div id="reg-step-form">
            <div class="form-row">
                <input id="reg-first_name" type="text" placeholder="الاسم الأول *" required />
                <input id="reg-last_name"  type="text" placeholder="اسم العائلة *" required />
            </div>
            <input id="reg-email"    type="email"    placeholder="البريد الإلكتروني *" required />
            <input id="reg-mobile"   type="tel"      placeholder="رقم الجوال *" required />
            <input id="reg-password" type="password" placeholder="كلمة المرور *" required />
            <input id="reg-address"  type="text"     placeholder="العنوان *" required />
            <input id="reg-city"     type="text"     placeholder="المدينة *" required />
            <input id="reg-postcode" type="text"     placeholder="الرمز البريدي" />
            <input id="reg-state"    type="text"     placeholder="المنطقة" />
            <button id="btn-register">إنشاء حساب وإرسال رمز التحقق</button>
        </div>
        <div id="reg-step-otp" style="display:none;">
            <input id="reg-otp-code" type="text" placeholder="أدخل رمز التحقق" maxlength="6" />
            <button id="btn-reg-verify-otp">تحقق وأكمل التسجيل</button>
        </div>
        <p id="reg-msg"></p>
    ';
} );
```

**Required IDs** — these must exist in your HTML for the built-in JS to work:

| ID | Description |
|---|---|
| `#reg-step-form` | Wrapper for the fields — hidden after register clicked |
| `#reg-step-otp` | OTP input wrapper — shown after account created |
| `#reg-first_name` | First name input |
| `#reg-last_name` | Last name input |
| `#reg-email` | Email input |
| `#reg-mobile` | Mobile input |
| `#reg-password` | Password input |
| `#reg-address` | Address line 1 input |
| `#reg-city` | City input |
| `#reg-otp-code` | OTP code input |
| `#btn-register` | Register button |
| `#btn-reg-verify-otp` | OTP verify button |
| `#reg-msg` | Status message paragraph |

---

### FILTER — `erpgulf_redirect_after_register`

Control where the user lands after successful registration and OTP verification.

```php
/**
 * @param string $url      Default redirect URL (my-account page)
 * @param int    $user_id  The newly registered user's ID
 * @return string          Your redirect URL
 */
add_filter( 'erpgulf_redirect_after_login', function( $url, $user_id ) {

    // Send new users to a welcome page
    // Check if this is a new registration (registered in last 60 seconds)
    $registered = get_userdata( $user_id )->user_registered;
    if ( strtotime( $registered ) > ( time() - 60 ) ) {
        return home_url( '/welcome/' );
    }

    return $url;

}, 10, 2 );
```

---

### ACTION — `erpgulf_before_register`

Fires just before the user account is created. Use to block registration or run pre-checks.

```php
/**
 * @param array $post_data  All submitted field values
 */
add_action( 'erpgulf_before_register', function( $post_data ) {
    // Log registration attempt
    error_log( 'Registration attempt: ' . $post_data['email'] );
} );
```

---

### ACTION — `erpgulf_after_register`

Fires after the user account is created but before OTP is sent. Use to save extra data, assign roles, send internal notifications.

```php
/**
 * @param int   $user_id   WordPress user ID
 * @param array $post_data All submitted field values
 */
add_action( 'erpgulf_after_register', function( $user_id, $post_data ) {

    // Assign customer role
    wp_update_user( [ 'ID' => $user_id, 'role' => 'customer' ] );

    // Save extra fields
    if ( ! empty( $post_data['company'] ) ) {
        update_user_meta( $user_id, 'billing_company', $post_data['company'] );
    }

    // Send internal Slack notification
    wp_remote_post( 'https://hooks.slack.com/your-webhook', [
        'body' => json_encode([
            'text' => "New registration: {$post_data['first_name']} {$post_data['last_name']} ({$post_data['email']})"
        ])
    ]);

    // Give welcome loyalty points
    do_action( 'woocommerce_points_earned', $user_id, 100, 'New registration bonus' );

}, 10, 2 );
```

---

### ACTION — `erpgulf_register_otp_sent`

Fires after OTP is dispatched to the new user.

```php
/**
 * @param int    $user_id  WordPress user ID
 * @param int    $otp      The 6-digit OTP (do not log in production)
 * @param string $phone    The mobile number OTP was sent to
 */
add_action( 'erpgulf_register_otp_sent', function( $user_id, $otp, $phone ) {
    // Log that OTP was sent
    update_user_meta( $user_id, '_registration_otp_sent_at', time() );
}, 10, 3 );
```

---

## Built-in Validation Reference

These checks run automatically — no code needed:

| Check | Error message |
|---|---|
| Required field empty | `{Field label} is required.` |
| Invalid email format | `Please enter a valid email address.` |
| Email already exists | `An account with this email already exists.` |
| Mobile too short | `Please enter a valid mobile number.` |
| Mobile last 7 digits match existing | `An account with this mobile number already exists.` |
| Password under 6 characters | `Password must be at least 6 characters.` |

All error messages can be overridden via the `erpgulf_error_messages` filter:

```php
add_filter( 'erpgulf_error_messages', function( $message, $error_type ) {
    $custom = [
        'email_exists' => 'هذا البريد الإلكتروني مسجل بالفعل.',
        'phone_exists' => 'رقم الجوال هذا مسجل بالفعل.',
        'weak_password' => 'كلمة المرور ضعيفة جداً.',
        'invalid_email' => 'يرجى إدخال بريد إلكتروني صحيح.',
        'invalid_phone' => 'يرجى إدخال رقم جوال صحيح.',
    ];
    return $custom[ $error_type ] ?? $message;
}, 10, 2 );
```

---

## Data Saved on Registration

Every registered user automatically gets these WordPress/WooCommerce fields populated:

| Field | Meta Key | Source |
|---|---|---|
| First Name | `first_name`, `billing_first_name` | Form input |
| Last Name | `last_name`, `billing_last_name` | Form input |
| Email | `billing_email` | Form input |
| Mobile | `customer_addresses_0_phone`, `billing_phone` | Form input |
| Address Line 1 | `billing_address_1` | Form input |
| Address Line 2 | `billing_address_2` | Form input |
| City | `billing_city` | Form input |
| Postcode | `billing_postcode` | Form input |
| State | `billing_state` | Form input |
| Country | `billing_country` | Hardcoded: `SA` |

---

## Common Recipes

### Show register tab by default instead of login

```php
add_filter( 'erpgulf_otp_form_html', function( $html ) {
    // Swap active class from login to register
    $html = str_replace(
        'class="otp-tab active" data-tab="login"',
        'class="otp-tab" data-tab="login"',
        $html
    );
    $html = str_replace(
        'class="otp-tab" data-tab="register"',
        'class="otp-tab active" data-tab="register"',
        $html
    );
    $html = str_replace(
        'id="tab-login"',
        'id="tab-login" style="display:none;"',
        $html
    );
    $html = str_replace(
        'id="tab-register" style="display:none;"',
        'id="tab-register"',
        $html
    );
    return $html;
} );
```

### Hide the tabs entirely — login only or register only

```php
// Hide register tab — login only page
add_filter( 'erpgulf_otp_form_styles', function( $css ) {
    return $css . ' .otp-tab[data-tab="register"] { display: none; } ';
} );

// Hide login tab — register only page
add_filter( 'erpgulf_otp_form_styles', function( $css ) {
    return $css . ' .otp-tab[data-tab="login"] { display: none; } ';
} );
```

### Add Arabic labels above fields instead of placeholders

```php
add_filter( 'erpgulf_register_form_html', function( $html ) {
    return '
        <div id="reg-step-form">
            <label>الاسم الأول *</label>
            <input id="reg-first_name" type="text" required />

            <label>اسم العائلة *</label>
            <input id="reg-last_name" type="text" required />

            <label>البريد الإلكتروني *</label>
            <input id="reg-email" type="email" required />

            <label>رقم الجوال *</label>
            <input id="reg-mobile" type="tel" required />

            <label>كلمة المرور *</label>
            <input id="reg-password" type="password" required />

            <label>العنوان *</label>
            <input id="reg-address" type="text" required />

            <label>المدينة *</label>
            <input id="reg-city" type="text" required />

            <label>الرمز البريدي</label>
            <input id="reg-postcode" type="text" />

            <label>المنطقة</label>
            <input id="reg-state" type="text" />

            <button id="btn-register">إنشاء حساب</button>
        </div>
        <div id="reg-step-otp" style="display:none;">
            <p>تم إرسال رمز التحقق إلى جوالك وبريدك الإلكتروني.</p>
            <input id="reg-otp-code" type="text" maxlength="6" placeholder="أدخل الرمز" />
            <button id="btn-reg-verify-otp">تحقق وأكمل التسجيل</button>
        </div>
        <p id="reg-msg"></p>
    ';
} );
```

---

## Support

**Author:** Farook K — https://medium.com/nothing-big  
**Email:** support@erpgulf.com
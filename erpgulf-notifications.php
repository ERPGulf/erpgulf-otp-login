<?php
/**
 * ERPGulf Notifications
 * Part of ERPGulf Simple OTP plugin
 *
 * Sends SMS + Email alerts for WooCommerce order events and custom triggers.
 * Uses the same providers already configured in ERPGulf OTP settings.
 *
 * ═══════════════════════════════════════════════════════════════════
 * DEVELOPER USAGE
 * ═══════════════════════════════════════════════════════════════════
 *
 * Send to a WordPress user (looks up their phone + email automatically):
 *   do_action( 'erpgulf_notify', $user_id, [
 *       'subject'    => 'Order Confirmed',
 *       'message_en' => 'Your order #123 is confirmed.',
 *       'message_ar' => 'تم تأكيد طلبك رقم #123.',
 *       'sms'        => true,   // optional, default true
 *       'email'      => true,   // optional, default true
 *   ]);
 *
 * Send directly to a phone/email (no user account needed):
 *   do_action( 'erpgulf_notify_direct', [
 *       'phone'      => '0501234567',
 *       'email'      => 'customer@example.com',
 *       'subject'    => 'Order Shipped',
 *       'message_en' => 'Your order is on the way.',
 *       'message_ar' => 'طلبك في الطريق.',
 *   ]);
 *
 * PLACEHOLDERS in message templates (admin settings):
 *   {site}         — site name
 *   {order_id}     — WooCommerce order ID
 *   {order_total}  — formatted order total
 *   {first_name}   — customer first name
 *   {status}       — order status label
 * ═══════════════════════════════════════════════════════════════════
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────────
// ADMIN MENU — Notifications submenu under ERPGulf OTP
// ─────────────────────────────────────────────────────────────────

add_action( 'admin_menu', function () {
    add_submenu_page(
        'erpgulf-otp',
        'Notifications',
        'Notifications',
        'manage_options',
        'erpgulf-notifications',
        'erpgulf_notifications_render'
    );
}, 20 );

// ─────────────────────────────────────────────────────────────────
// NOTIFICATION DEFINITIONS
// ─────────────────────────────────────────────────────────────────

function erpgulf_notification_definitions(): array {
    return [
        'order_processing' => [
            'label'      => 'Order Confirmed',
            'hook'       => 'woocommerce_order_status_processing',
            'icon'       => '✅',
            'desc'       => 'Fires when a new order is confirmed / payment received.',
            'default_en' => 'Hello {first_name}, your order #{order_id} has been confirmed. Total: {order_total}. Thank you for shopping at {site}!',
            'default_ar' => 'مرحباً {first_name}، تم تأكيد طلبك رقم #{order_id}. الإجمالي: {order_total}. شكراً لتسوقك في {site}!',
            'default_sub'=> 'Order #{order_id} Confirmed — {site}',
        ],
        'order_shipped' => [
            'label'      => 'Order Shipped',
            'hook'       => 'woocommerce_order_status_shipped',
            'icon'       => '🚚',
            'desc'       => 'Fires when order status changes to Shipped.',
            'default_en' => 'Hello {first_name}, your order #{order_id} has been shipped and is on its way!',
            'default_ar' => 'مرحباً {first_name}، تم شحن طلبك رقم #{order_id} وهو في الطريق إليك!',
            'default_sub'=> 'Order #{order_id} Shipped — {site}',
        ],
        'order_completed' => [
            'label'      => 'Order Delivered',
            'hook'       => 'woocommerce_order_status_completed',
            'icon'       => '🎉',
            'desc'       => 'Fires when order status changes to Completed.',
            'default_en' => 'Hello {first_name}, your order #{order_id} has been delivered. We hope you enjoy your purchase!',
            'default_ar' => 'مرحباً {first_name}، تم تسليم طلبك رقم #{order_id}. نتمنى أن تستمتع بمشترياتك!',
            'default_sub'=> 'Order #{order_id} Delivered — {site}',
        ],
        'order_cancelled' => [
            'label'      => 'Order Cancelled',
            'hook'       => 'woocommerce_order_status_cancelled',
            'icon'       => '❌',
            'desc'       => 'Fires when an order is cancelled.',
            'default_en' => 'Hello {first_name}, your order #{order_id} has been cancelled. Contact us if you have questions.',
            'default_ar' => 'مرحباً {first_name}، تم إلغاء طلبك رقم #{order_id}. تواصل معنا إذا كان لديك أي استفسار.',
            'default_sub'=> 'Order #{order_id} Cancelled — {site}',
        ],
        'order_on_hold' => [
            'label'      => 'Order On Hold',
            'hook'       => 'woocommerce_order_status_on-hold',
            'icon'       => '⏳',
            'desc'       => 'Fires when an order is placed on hold.',
            'default_en' => 'Hello {first_name}, your order #{order_id} is on hold. We will update you soon.',
            'default_ar' => 'مرحباً {first_name}، طلبك رقم #{order_id} قيد الانتظار. سنحدثك قريباً.',
            'default_sub'=> 'Order #{order_id} On Hold — {site}',
        ],
        'order_refunded' => [
            'label'      => 'Order Refunded',
            'hook'       => 'woocommerce_order_status_refunded',
            'icon'       => '↩️',
            'desc'       => 'Fires when an order is refunded.',
            'default_en' => 'Hello {first_name}, a refund for order #{order_id} has been processed.',
            'default_ar' => 'مرحباً {first_name}، تم معالجة استرداد الطلب رقم #{order_id}.',
            'default_sub'=> 'Refund Processed — Order #{order_id}',
        ],
    ];
}

// ─────────────────────────────────────────────────────────────────
// ADMIN PAGE RENDER
// ─────────────────────────────────────────────────────────────────

function erpgulf_notifications_render() {

    $definitions = erpgulf_notification_definitions();

    // ── Save ─────────────────────────────────────────────────────
    if ( isset( $_POST['save_erpgulf_notifications'] ) && check_admin_referer( 'erpgulf_save_notifications' ) ) {
        foreach ( $definitions as $key => $def ) {
            update_option( "erpgulf_notif_{$key}_enabled",  isset( $_POST["notif_{$key}_enabled"] )  ? 1 : 0 );
            update_option( "erpgulf_notif_{$key}_sms",      isset( $_POST["notif_{$key}_sms"] )      ? 1 : 0 );
            update_option( "erpgulf_notif_{$key}_email",    isset( $_POST["notif_{$key}_email"] )    ? 1 : 0 );
            update_option( "erpgulf_notif_{$key}_subject",  sanitize_text_field( trim( $_POST["notif_{$key}_subject"] ?? '' ) ) );
            update_option( "erpgulf_notif_{$key}_msg_en",   sanitize_textarea_field( trim( $_POST["notif_{$key}_msg_en"] ?? '' ) ) );
            update_option( "erpgulf_notif_{$key}_msg_ar",   sanitize_textarea_field( trim( $_POST["notif_{$key}_msg_ar"] ?? '' ) ) );
        }
        echo '<div class="updated"><p>✅ Notification settings saved.</p></div>';
    }
    ?>
    <div class="wrap" style="max-width:900px;">
        <h1>🔔 ERPGulf Notifications</h1>

        <div style="background:#e8f4fd;border:1px solid #007cba;border-radius:4px;padding:14px 18px;margin-bottom:24px;">
            <strong>Developer hook:</strong>
            <code style="margin-left:8px;">do_action( 'erpgulf_notify', $user_id, [ 'subject'=>'', 'message_en'=>'', 'message_ar'=>'' ] );</code><br>
            <strong style="margin-top:6px;display:inline-block;">Placeholders:</strong>
            <code>{site}</code> &nbsp;
            <code>{order_id}</code> &nbsp;
            <code>{order_total}</code> &nbsp;
            <code>{first_name}</code> &nbsp;
            <code>{status}</code>
        </div>

        <form method="post">
            <?php wp_nonce_field( 'erpgulf_save_notifications' ); ?>

            <?php foreach ( $definitions as $key => $def ):
                $enabled = get_option( "erpgulf_notif_{$key}_enabled", 1 );
                $sms     = get_option( "erpgulf_notif_{$key}_sms",     1 );
                $email   = get_option( "erpgulf_notif_{$key}_email",   1 );
                $subject = get_option( "erpgulf_notif_{$key}_subject", $def['default_sub'] );
                $msg_en  = get_option( "erpgulf_notif_{$key}_msg_en",  $def['default_en'] );
                $msg_ar  = get_option( "erpgulf_notif_{$key}_msg_ar",  $def['default_ar'] );
                $border  = $enabled ? '#007cba' : '#ddd';
                $bg      = $enabled ? '#f0f7ff' : '#fff';
            ?>
            <div style="background:<?php echo $bg; ?>;border:1px solid <?php echo $border; ?>;border-radius:6px;padding:20px;margin-bottom:16px;">

                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:<?php echo $enabled ? '16px' : '0'; ?>;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="font-size:22px;"><?php echo $def['icon']; ?></span>
                        <div>
                            <strong style="font-size:15px;"><?php echo esc_html( $def['label'] ); ?></strong>
                            <p style="margin:2px 0 0;font-size:12px;color:#888;"><?php echo esc_html( $def['desc'] ); ?></p>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:16px;">
                        <?php if ( $enabled ): ?>
                            <label style="font-size:12px;color:#555;">
                                <input type="checkbox" name="notif_<?php echo $key; ?>_sms"
                                       <?php checked( $sms, 1 ); ?>>
                                📱 SMS
                            </label>
                            <label style="font-size:12px;color:#555;">
                                <input type="checkbox" name="notif_<?php echo $key; ?>_email"
                                       <?php checked( $email, 1 ); ?>>
                                📧 Email
                            </label>
                        <?php endif; ?>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox"
                                   name="notif_<?php echo $key; ?>_enabled"
                                   <?php checked( $enabled, 1 ); ?>
                                   onchange="this.closest('div.wrap form div').style.background=this.checked?'#f0f7ff':'#fff';this.closest('div.wrap form div').style.borderColor=this.checked?'#007cba':'#ddd';">
                            <span style="font-size:13px;font-weight:600;color:<?php echo $enabled ? '#007cba' : '#888'; ?>;">
                                <?php echo $enabled ? 'ON' : 'OFF'; ?>
                            </span>
                        </label>
                    </div>
                </div>

                <?php if ( $enabled ): ?>
                <table class="form-table" style="margin:0;">
                    <tr>
                        <th style="width:160px;padding:6px 0;font-size:13px;">Email Subject</th>
                        <td style="padding:6px 0;">
                            <input type="text"
                                   name="notif_<?php echo $key; ?>_subject"
                                   value="<?php echo esc_attr( $subject ); ?>"
                                   class="large-text" style="font-size:13px;">
                        </td>
                    </tr>
                    <tr>
                        <th style="padding:6px 0;font-size:13px;">Message EN 🇬🇧</th>
                        <td style="padding:6px 0;">
                            <textarea name="notif_<?php echo $key; ?>_msg_en"
                                      rows="2" class="large-text"
                                      style="font-size:13px;"><?php echo esc_textarea( $msg_en ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding:6px 0;font-size:13px;">Message AR 🇸🇦</th>
                        <td style="padding:6px 0;">
                            <textarea name="notif_<?php echo $key; ?>_msg_ar"
                                      rows="2" class="large-text"
                                      dir="rtl"
                                      style="font-size:13px;font-family:Tahoma,Arial,sans-serif;"><?php echo esc_textarea( $msg_ar ); ?></textarea>
                        </td>
                    </tr>
                </table>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>

            <p class="submit">
                <input type="submit" name="save_erpgulf_notifications"
                       class="button button-primary" value="Save Notification Settings">
            </p>
        </form>
    </div>
    <?php
}

// ─────────────────────────────────────────────────────────────────
// CORE SEND ENGINE
// ─────────────────────────────────────────────────────────────────

function erpgulf_get_send_settings(): array {
    return [
        'username'     => get_option( 'erpgulf_et_username' ),
        'api_key'      => get_option( 'erpgulf_et_api_key' ),
        'api_secret'   => get_option( 'erpgulf_et_api_secret' ),
        'country_code' => get_option( 'erpgulf_et_country_code', '966' ),
        'app_key'      => get_option( 'erpgulf_4j_app_key' ),
        'app_secret'   => get_option( 'erpgulf_4j_app_secret' ),
        'sender'       => get_option( 'erpgulf_4j_sender' ),
        'number_iso'   => get_option( 'erpgulf_4j_number_iso', 'SA' ),
        'host'         => get_option( 'erpgulf_oci_host' ),
        'port'         => get_option( 'erpgulf_oci_port' ),
        'user'         => get_option( 'erpgulf_oci_user' ),
        'pass'         => get_option( 'erpgulf_oci_pass' ),
        'from'         => get_option( 'erpgulf_oci_from' ),
    ];
}

/**
 * Low-level send — call this directly or via the hooks below.
 *
 * @param string $phone      Phone number (or empty to skip SMS)
 * @param string $email_addr Email address (or empty to skip email)
 * @param array  $payload    [ subject, message_en, message_ar, sms, email ]
 */
function erpgulf_do_send( string $phone, string $email_addr, array $payload ): void {

    $send_sms   = $payload['sms']   ?? true;
    $send_email = $payload['email'] ?? true;
    $msg_en     = $payload['message_en'] ?? '';
    $msg_ar     = $payload['message_ar'] ?? '';
    $subject    = $payload['subject']    ?? get_bloginfo('name') . ' — Notification';
    $settings   = erpgulf_get_send_settings();

    // ── SMS ───────────────────────────────────────────────────────
    if ( $send_sms && ! empty( $phone ) ) {
        $sms_provider = get_option( 'erpgulf_active_sms_provider', 'experttexting' );
        $sms_fn       = 'erpgulf_send_sms_' . $sms_provider;
        if ( function_exists( $sms_fn ) ) {
            $sms_fn( $phone, 0, array_merge( $settings, [
                'message_en' => $msg_en,
                'message_ar' => $msg_ar,
                'is_notification' => true,  // tells provider: no OTP, use message directly
            ] ) );
        }
    }

    // ── Email ─────────────────────────────────────────────────────
    if ( $send_email && ! empty( $email_addr ) && is_email( $email_addr ) ) {
        $email_provider = get_option( 'erpgulf_active_email_provider', 'oci' );
        $email_fn       = 'erpgulf_send_email_' . $email_provider;
        if ( function_exists( $email_fn ) ) {
            $email_fn( $email_addr, 0, array_merge( $settings, [
                'subject'    => $subject,
                'message_en' => $msg_en,
                'message_ar' => $msg_ar,
                'is_notification' => true,
            ] ) );
        }
    }
}

// ─────────────────────────────────────────────────────────────────
// ACTION — erpgulf_notify  ( $user_id, $payload )
// ─────────────────────────────────────────────────────────────────

add_action( 'erpgulf_notify', function ( int $user_id, array $payload ) {

    $user  = get_userdata( $user_id );
    if ( ! $user ) return;

    $phone = (string) get_user_meta( $user_id, 'customer_addresses_0_phone', true );
    erpgulf_do_send( $phone, $user->user_email, $payload );

}, 10, 2 );

// ─────────────────────────────────────────────────────────────────
// ACTION — erpgulf_notify_direct  ( $payload )
// ─────────────────────────────────────────────────────────────────

add_action( 'erpgulf_notify_direct', function ( array $payload ) {

    $phone = $payload['phone'] ?? '';
    $email = $payload['email'] ?? '';
    erpgulf_do_send( (string) $phone, (string) $email, $payload );

}, 10, 1 );

// ─────────────────────────────────────────────────────────────────
// PLACEHOLDER RESOLVER
// ─────────────────────────────────────────────────────────────────

function erpgulf_resolve_notification( string $template, WC_Order $order ): string {
    return str_replace(
        [ '{site}', '{order_id}', '{order_total}', '{first_name}', '{status}' ],
        [
            get_bloginfo( 'name' ),
            $order->get_id(),
            html_entity_decode( strip_tags( $order->get_formatted_order_total() ) ),
            $order->get_billing_first_name(),
            wc_get_order_status_name( $order->get_status() ),
        ],
        $template
    );
}

// ─────────────────────────────────────────────────────────────────
// WOOCOMMERCE ORDER HOOKS — wire up all defined notifications
// ─────────────────────────────────────────────────────────────────

function erpgulf_wire_order_notification( string $key, array $def ): void {

    add_action( $def['hook'], function ( int $order_id ) use ( $key, $def ) {

        // Check if this notification is enabled
        if ( ! get_option( "erpgulf_notif_{$key}_enabled", 1 ) ) return;

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $send_sms   = (bool) get_option( "erpgulf_notif_{$key}_sms",   1 );
        $send_email = (bool) get_option( "erpgulf_notif_{$key}_email", 1 );
        $subject    = get_option( "erpgulf_notif_{$key}_subject", $def['default_sub'] );
        $msg_en     = get_option( "erpgulf_notif_{$key}_msg_en",  $def['default_en'] );
        $msg_ar     = get_option( "erpgulf_notif_{$key}_msg_ar",  $def['default_ar'] );

        // Resolve placeholders
        $subject = erpgulf_resolve_notification( $subject, $order );
        $msg_en  = erpgulf_resolve_notification( $msg_en,  $order );
        $msg_ar  = erpgulf_resolve_notification( $msg_ar,  $order );

        // Get customer contact from order (works even for guest checkouts)
        $phone      = $order->get_billing_phone();
        $email_addr = $order->get_billing_email();

        // Also try custom phone meta if billing_phone is empty
        if ( empty( $phone ) && $order->get_user_id() ) {
            $phone = (string) get_user_meta( $order->get_user_id(), 'customer_addresses_0_phone', true );
        }

        erpgulf_do_send( (string) $phone, (string) $email_addr, [
            'subject'    => $subject,
            'message_en' => $msg_en,
            'message_ar' => $msg_ar,
            'sms'        => $send_sms,
            'email'      => $send_email,
        ] );

    }, 10, 1 );
}

// Register all hooks
foreach ( erpgulf_notification_definitions() as $key => $def ) {
    erpgulf_wire_order_notification( $key, $def );
}
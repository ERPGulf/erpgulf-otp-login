# ERPGulf Simple OTP Login for WooCommerce

A lightweight, secure, and customizable SMS OTP (One-Time Password) verification plugin for WooCommerce. While designed with **Saudi Arabian and Gulf region** SMS providers in mind, its modular architecture allows for easy integration with any global SMS gateway.

---

## 🚀 Features

* **Gulf-Ready:** Built specifically to handle regional requirements (like ZATCA compliance and local mobile formatting).
* **Plug & Play Providers:** Comes pre-integrated with **ExpertTexting**, but can be adapted for local providers (like Unifonic, Mobily, or STC) with minor code adjustments in the provider file.
* **Dedicated Settings Page:** Manage your API URL, Username, Keys, and Secrets directly from the WordPress Dashboard.
* **AJAX Powered:** Smooth user experience with no page reloads during OTP request or verification.
* **Secure:** Implements OTP expiry and cleans mobile numbers to match database records automatically.

---

## 🛠 Installation & Setup

1.  Upload the `erpgulf-otp-login` folder to your `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Navigate to the **ERPGulf OTP** menu in your sidebar.
4.  Enter your SMS Provider credentials (API Key, Secret, and Sender ID).
5.  Test your integration by visiting `your-site.com/otp-test`.

---

## 🔧 Customization

### Adding New Providers
This plugin is designed to be extensible. To add a different SMS provider:
1.  Open `experttexting-provider.php`.
2.  Modify the `wp_remote_post` body parameters to match your provider's API documentation.
3.  The main logic in `erpgulf-otp-login.php` will handle the rest.

---

## ✉️ Professional Support & Customization

Need help integrating a specific local gateway? Or looking for a complete business ecosystem? **ERPGulf** specializes in:
* **WooCommerce** custom development and API integrations.
* **ERPNext** implementation and Frappe Framework apps.
* Bespoke Business Intelligence and AI solutions.

**Contact Us:**
* **Email:** [support@erpgulf.com](mailto:support@erpgulf.com)
* **Website:** [www.erpgulf.com](https://www.erpgulf.com)

---
*Created by the ERPGulf Team.*

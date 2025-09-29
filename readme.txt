=== FP Experiences ===

Contributors: francescopasseri
Tags: experiences, booking, wooocommerce, shortcodes, calendar
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

FP Experiences brings GetYourGuide-style booking flows to WooCommerce without touching existing physical products. It delivers isolated carts, shortcodes, Elementor widgets, marketing integrations, and staff tooling tailored for tour operators and experience providers.

== Description ==

* Isolated booking cart that never mixes with WooCommerce products and uses a dedicated checkout shortcode.
* Experience discovery widgets with availability calendars, ticket types, add-ons, and schema-ready markup.
* Reusable meeting points with CSV import, experience linking, shortcode, and Elementor widget.
* Optional Brevo transactional email delivery, Google Calendar sync, and marketing pixels (GA4, Google Ads, Meta, Clarity).
* Admin calendar with drag-and-drop rescheduling, manual booking creation with payment links, and operations roles for managers, operators, and guides.
* Consent-aware tracking, theming presets, CSS variable overrides, and Elementor controls for editors.

== Installation ==

1. Upload the `fp-experiences` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Visit **FP Experiences → Settings** to configure branding, emails, Brevo, calendar, tracking, and tools.
4. Add the shortcodes or Elementor widgets to the desired pages.

== Shortcodes ==

* `[fp_exp_list]` – Grid of experiences with filters. Attributes: `filters`, `per_page`, `order`.
* `[fp_exp_widget id="123"]` – Booking widget for a specific experience. Attributes: `sticky`, `show_calendar`, `primary`, `accent`, `radius`.
* `[fp_exp_calendar id="123" months="2"]` – Inline availability calendar for a single experience.
* `[fp_exp_checkout]` – Isolated checkout that finalises the FP Experiences cart only.
* `[fp_exp_meeting_points id="123"]` – Outputs the primary meeting point and optional alternatives for an experience, with map links built client-side.

== Elementor Widgets ==

Five Elementor widgets mirror the shortcodes: List, Widget, Calendar, Checkout, and Meeting Points. Each exposes theming controls (colors, radius, fonts) plus behavioural toggles (sticky mode, inline calendar, consent defaults).

== Settings & Tools ==

* **General** – Structure and webmaster emails, locale preferences, VAT class filters, meeting points toggle.
* **Branding** – Color palette, button radius, shadows, presets, contrast checker, optional Google Font.
* **Tracking** – Enable/disable GA4, Google Ads, Meta Pixel, Clarity, enhanced conversions, and consent defaults.
* **Brevo** – API key, list ID, attribute mappings, transactional template IDs, webhook diagnostics.
* **Calendar** – Google OAuth client credentials, redirect URI, connect/disconnect, target calendar.
* **Tools** – Brevo resync, event replay, REST API ping, meeting point CSV import, and cache/log clearance with rate-limited REST endpoints.

== Hooks ==

Filters:

* `fp_exp_cart_can_mix` – Allow WooCommerce cart mixing (default false).
* `fp_exp_vat_class` – Override VAT class used on experience line items.
* `fp_exp_email_recipients` – Modify staff email recipients array.
* `fp_exp_brevo_contact_payload`, `fp_exp_brevo_tx_payload` – Adjust Brevo payloads before sending.
* `fp_exp_calendar_event_payload` – Modify Google Calendar event payload before insert/update.
* `fp_exp_datalayer_purchase` – Filter purchase payload pushed to the dataLayer.

Actions:

* `fp_exp_reservation_created`
* `fp_exp_reservation_paid`
* `fp_exp_reservation_cancelled`
* `fp_exp_checkin_done`

== Frequently Asked Questions ==

= Does FP Experiences create WooCommerce products? =

No. Experiences are stored as a dedicated custom post type with isolated availability, pricing, and checkout logic.

= Can I keep my existing WooCommerce theme? =

Yes. The plugin scopes CSS and JS to its shortcodes/widgets, injects CSS variables only when needed, and exposes branding controls for overrides.

= How are transactional emails delivered? =

If Brevo credentials are provided, confirmations, reminders, and cancellations use Brevo templates. Otherwise WooCommerce’s mailer delivers the bundled templates with ICS attachments.

== Privacy ==

FP Experiences stores reservation details inside custom tables linked to WooCommerce orders. Marketing consent is recorded per order (`_fp_exp_consent_marketing`) and forwarded to Brevo only when enabled. UTM parameters are captured in the `fp_exp_utm` cookie, copied to reservation/order meta, and never displayed publicly. Site owners can export or erase booking data through WooCommerce personal data tools; deleting an order removes the associated reservation payload. API credentials (Brevo, Google Calendar) are kept in WordPress options and can be revoked at any time from the Settings screen.

== Changelog ==

= 0.1.0 =
* Initial public release with isolated booking cart, shortcodes, Elementor widgets, Brevo integration, Google Calendar sync, marketing tracking toggles, admin calendar/manual booking tools, and hardened REST utilities.

See `docs/CHANGELOG.md` for the full development log.

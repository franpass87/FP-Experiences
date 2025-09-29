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

* `[fp_exp_list]` – Mobile-first experiences showcase with accessible filter form (themes, languages, duration, price range, family-friendly toggle, date picker, and text search), sorting controls, pagination, price badges, optional map links, and dataLayer tracking (`view_item_list` + `select_item`). Attributes: `filters`, `per_page`, `page`, `search`, `order`, `orderby`, `view`, `show_map`, `cta`, `badge_lang`, `badge_duration`, `badge_family`, `show_price_from`, plus layout helpers (`columns_desktop`, `columns_tablet`, `columns_mobile`, `gap`). Price badges cache the lowest ticket price per experience via transients and respect dedicated experience pages when available. Examples:
  * `[fp_exp_list filters="theme,language,price,date,family" per_page="9" view="grid" orderby="price" order="ASC" show_price_from="1" show_map="1"]`
  * `[fp_exp_list filters="search,theme" per_page="12" view="list" cta="widget" gap="compact"]`
* `[fp_exp_widget id="123"]` – Booking widget for a specific experience. Attributes: `sticky`, `show_calendar`, `primary`, `accent`, `radius`.
* `[fp_exp_calendar id="123" months="2"]` – Inline availability calendar for a single experience.
* `[fp_exp_checkout]` – Isolated checkout that finalises the FP Experiences cart only.
* `[fp_exp_meeting_points id="123"]` – Outputs the primary meeting point and optional alternatives for an experience, with map links built client-side.
* `[fp_exp_page id="123" sections="hero,highlights,inclusions,meeting,extras,faq,reviews" sticky_widget="1"]` – Full experience detail page with hero gallery, highlights, inclusions/exclusions, meeting point block, FAQ accordion, reviews, and sticky availability widget. Supports the usual theming overrides (`preset`, `mode`, color variables, `radius`, `shadow`, `font`).

The `sections` attribute accepts a comma-separated list of sections to render (hero, highlights, inclusions, meeting, extras, faq, reviews). Meeting point data automatically reuses the Meeting Points module when enabled; otherwise the section is hidden. Set `sticky_widget="0"` to disable the mobile CTA bar.

== Elementor Widgets ==

Six Elementor widgets mirror the shortcodes: List, Widget, Calendar, Checkout, Meeting Points, and the new Experience Page layout. The List widget now bundles the full showcase controls (filters, search, ordering, map toggle, CTA behaviour) plus responsive style controls for columns, card spacing, and badge/price visibility. Each widget exposes theming overrides (colors, radius, fonts) alongside behavioural toggles (sticky mode, inline calendar, consent defaults). The Experience Page widget lets editors pick sections to display and toggle the sticky availability bar while reusing the `[fp_exp_page]` shortcode under the hood.

== Settings & Tools ==

* **General** – Structure and webmaster emails, locale preferences, VAT class filters, meeting points toggle.
* **Branding** – Color palette, button radius, shadows, presets, contrast checker, optional Google Font.
* **Showcase** – Default filters, ordering, and price badge toggle for the experiences listing/Elementor widget.
* **Tracking** – Enable/disable GA4, Google Ads, Meta Pixel, Clarity, enhanced conversions, and consent defaults.
* **Brevo** – API key, list ID, attribute mappings, transactional template IDs, webhook diagnostics.
* **Calendar** – Google OAuth client credentials, redirect URI, connect/disconnect, target calendar.
* **Tools** – Brevo resync, event replay, REST API ping, meeting point CSV import, and cache/log clearance with rate-limited REST endpoints.

== Admin UX ==

The Experience edit screen now groups meta fields into accessible tabs (“Dettagli”, “Biglietti & Prezzi”, “Calendario & Slot”, “Meeting Point”, “Extra”, “Policy/FAQ”, “SEO/Schema”) with a sticky navigation bar. Ticket types and add-ons use drag-and-drop repeaters with inline validation, tooltips, and non-blocking warnings when no ticket is configured. The tabs support deep linking, focus management, and keyboard navigation while keeping the original `_fp_*` meta keys untouched.

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

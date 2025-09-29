# Overview
- Custom post type `fp_meeting_point` is registered with dedicated meta boxes, Elementor widget, shortcode, and CSV importer hooks. Core behaviors live under `src/MeetingPoints`.
- Experience detail shortcode `[fp_exp_page]` renders the hero layout, sticky widget, and section toggles while reusing meeting point data and theming helpers.
- Tracking and consent plumbing piggyback on the existing shortcode asset loader and configuration emitted through `fpExpConfig`.

# Repo Layout
- `src/MeetingPoints/` – CPT registration, meta boxes, importer, REST controller, repository helper, and admin manager.
- `src/Shortcodes/` – Base renderer, experience + meeting point shortcodes, and shared asset bootstrap.
- `src/Elementor/` – Widgets for meeting points and the full experience page, registered through `WidgetsRegistrar`.
- `templates/front/` – Experience page layout plus meeting point partial reused by both features.
- `assets/css/front.css` & `assets/js/front.js` – Scoped styles and behavior for meeting points, sticky widget, accordions, and map links.
- `readme.txt` – Documents the new shortcodes, Elementor widgets, and the meeting points setting toggle.

# Admin/UI
- Meeting point CPT uses `public => false`, `show_ui => true`, and `supports => ['title']`; list table columns display address and coordinates while admin search extends across `_fp_mp_address`.
- Meeting point meta box captures address, lat/lng, notes (`wp_kses_post`), phone, email, and opening hours with capability + nonce checks before persisting `_fp_mp_*` keys.
- Experience edit screen side box allows choosing a primary meeting point and multiple alternatives, saving into `_fp_meeting_point_id` and `_fp_meeting_point_alt` after sanitisation and deduplication. A summary copy is stored back-compatibly in `_fp_meeting_point` when available.
- Meeting point CSV importer is exposed under the plugin menu when the module is enabled, handling capability checks, nonce, sanitisation, and transient-based notices.
- All meeting point admin UI (meta boxes, menu, importer) early-return when the “Enable meeting points” toggle is off.

# Front-end
- `[fp_exp_meeting_points id="..."]` pulls primary/alternate data through the repository and renders `templates/front/meeting-points.php`, which wraps each entry with the shared partial.
- The partial outputs headings, address blocks with “Apri in Maps” links, contacts, and notes; JS enriches the map anchor with a Google Maps query derived from coordinates/address and disables it when no query is available.
- Elementor widgets simply proxy to the shortcodes, mirroring attributes for experience ID, section selection, sticky widget toggle, and theming overrides.
- `[fp_exp_page]` composes hero gallery, badges (duration, languages, family-friendly), highlights, inclusion/exclusion columns, meeting block reuse, extras (what to bring, notes, cancellation), FAQ accordion, and reviews list. The sticky widget container embeds `[fp_exp_widget]` output and a responsive sticky CTA bar on mobile.
- Shortcode asset loader enqueues CSS/JS only when invoked, scoping variables via the generated `scope_class` and optional palette overrides.

# REST
- Meeting point REST controller registers `GET fp-exp/v1/meeting-points/<experience_id>` returning primary/alternatives prepared via the repository; payload normalises numeric IDs/coords and strips data when the module is disabled.
- Existing core REST routes remain untouched; no POST/PUT endpoints are exposed for meeting points.
- Spec called for a `?experience_id=` query string pattern, whereas the implementation uses a path parameter segment.

# Tracking
- Experience shortcode builds a GA4/ecommerce `view_item` payload and prints it into the DOM, while the widget JS pushes granular events (`view_item`, `select_item`, `add_to_cart`, `begin_checkout`) gated behind consent-aware channel flags.
- CTA buttons expose `data-fp-cta` attributes (`hero`, `sticky`) and scroll targets for JS smooth scrolling.
- `fpExpConfig` localised data includes tracking configuration derived from helper settings so script loaders know which channels to fire.

# A11y
- Meeting point markup uses semantic headings, lists, and `<details>/<summary>` for alternatives; map links gain `aria-disabled` when deactivated.
- Experience page layout provides section headings, nav buttons with `aria-label`, accessible accordion controls (`aria-expanded`, focusable keyboard support), and `<time>` semantics for review dates.
- Sticky CTA button remains a native `<button>` with focus styles inherited from CSS.

# Theming
- Both shortcodes feed through `Theme::resolve_palette`, allowing presets, explicit color tokens, radius/shadow, and font overrides; inline CSS scopes styles to the generated class.
- CSS leverages CSS variables (`--fp-exp-color-*`) and responsive `clamp()` spacing while keeping selectors namespaced under `.fp-exp-*`.

# Settings/Consent
- Settings page registers `fp_exp_enable_meeting_points` under the “General” tab with sanitisation to enforce `yes`/`no` values. Toggle drives CPT menu visibility, meta box rendering, shortcode/widget availability, and REST responses.
- Consent gating flows through `Helpers::tracking_config()` so GA4/Ads/Meta events only fire when corresponding consent flags are granted; no external map SDKs are enqueued.

# Known Issues
1. Meeting point REST route uses a path parameter (`/meeting-points/<id>`) rather than the documented `?experience_id=` query string, so API consumers expecting the spec may fail until docs or endpoints align.
2. Meeting point map link defaults to `href="#"` until JS runs, leaving no Maps link fallback for no-JS environments.
3. Data layer `view_item` payload is echoed inline regardless of consent; while downstream trackers honour consent before firing, privacy-sensitive projects may prefer gating the initial push as well.
4. There is no dedicated `src/Admin/MeetingPointsAdmin.php`; list-table tweaks live inside the CPT class. Behaviour matches the requirement, but future maintainers may look for the admin class documented in the spec.

# Suggested Fixes
- Mirror the documented REST shape by accepting `experience_id` via query argument (and optionally keep the path variant for backwards compatibility) or update docs to reflect the implemented route.
- Provide a non-JS fallback for the map link (e.g., prebuild the Google Maps URL in PHP) so meeting point details remain actionable without scripts.
- Gate the inline `dataLayer.push` behind the same consent checks used in JS helpers for consistent privacy handling.
- Consider adding a thin `MeetingPointsAdmin` wrapper (or adjusting docs) to reduce confusion about the admin integration point.

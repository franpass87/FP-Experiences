# Admin Guide – FP Experiences 0.3.0

## Add-on images
- Open an experience and switch to the **Extra/Add-ons** tab.
- Each add-on row includes a “Scegli immagine” button that opens the WordPress media modal.
- Select or upload an image (medium size is used on the front end). Remove the image with “Rimuovi”.
- Images are saved as attachment IDs in `_fp_addons` and lazy-loaded on the widget and listing cards.

## Recurring slots & time sets
- Go to **Calendario & Slot → Ricorrenze** inside an experience.
- Define the RRULE (frequency, interval, exclusions) and pick the **Time set** chips that map to the recurrence.
- Use **Anteprima** to review generated dates and **Rigenera slot** to backfill without touching historical reservations.
- Validation blocks saving if a recurrence has no time set assigned.

## Gift Your Experience
- Enable the feature under **Impostazioni → Gift** (validity days, reminder offsets/time, redemption page slug).
- Editors see a “Gift this experience” CTA on the experience template with a purchase form (quantity, add-ons, personalised message).
- Successful purchases create a WooCommerce order, the `fp_exp_gift_voucher` CPT entry, and email the recipient with redemption details.
- Recipients visit the redemption page rendered by `[fp_exp_gift_redeem]`, review prepaid add-ons/quantity, choose a slot, and complete a zero-cost order.
- Reminders fire 30/7/1 days before expiry (configurable) via the `fp_exp_gift_send_reminders` cron.
- Admins manage vouchers under **FP Experiences → Gift vouchers** with quick actions to cancel or extend +30 days (logging every change).

## Meeting point import (advanced)
- Toggle the CSV importer from **Impostazioni → Generali → Enable meeting point import (advanced)**.
- When disabled, the “Import Meeting Points” submenu and tools are hidden for safety.
- When enabled, authorised managers can upload CSVs from **Tools → Meeting points**; the importer validates nonce, columns, and duplicates before persisting entries.

## Recovery tools
- Open **FP Experiences → Dashboard → Tools** to execute maintenance and diagnostic utilities.
- Use **Resynchronise FP roles** if administrators or managers lose access to the Experiences menu. The action rebuilds custom roles, grants the expected capabilities, and updates the stored role signature.
- Each tool surfaces a success/error summary along with detailed bullet points returned by the REST endpoint; review them to confirm missing capabilities have been restored or to follow up on warnings.

## Release & migrations
- The migration runner executes on `init`/`admin_init`, backfilling `_fp_addons` entries with `image_id` keys and populating the `wp_fp_exp_gift_vouchers` summary table.
- Inspect run state in the `fp_exp_migrations` option; rerun by deleting the option if troubleshooting on staging.
- Voucher events are mirrored to the summary table whenever status/validity changes (including admin quick actions).

For additional troubleshooting tips and operational SOPs refer to the detailed audits in `/docs/QA` and `/docs/PLAYBOOK.md`.

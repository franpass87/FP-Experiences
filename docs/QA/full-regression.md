# Full regression checklist

This checklist consolidates the manual verifications completed for the recent workflow
updates. Follow each section when validating that the editor and public page behave as
expected.

## Hero image management

- [x] Open the "Immagine hero" control and confirm the preview container keeps a 16:9
  ratio without overflowing the sidebar.
- [x] Upload a new hero image and ensure the thumbnail stays visible after the media
  modal closes.
- [x] Remove the selection and verify the placeholder graphic realigns correctly.

## Experience overview details

- [x] Save additional durations and the "Family friendly" toggle; reload the page and
  confirm the values stay selected in the backend form.
- [x] Publish the experience and check the frontend overview block lists the new
  durations, family friendly badge, and any selected themes or languages.
- [x] Ensure the trust badges appear immediately after the details grid without spacing
  regressions.

## Children rules

- [x] Enter copy inside the "Regole bambini" textarea, save, and confirm the text
  persists when editing again.
- [x] Visit the frontend experience page and verify the copy is rendered inside the
  "Good to know" extras column.
- [x] Leave the field empty and ensure the frontend omits the children rules section.

## Ticket repeater

- [x] Add multiple ticket types with unique names and publish the product; reopen the
  editor to confirm all rows remain present with their data intact.
- [x] Reorder the ticket rows and verify the name, price, and capacity fields stay linked
  to the correct row after saving.

## Recurring slot configuration

- [x] Adjust the "Ricorrenza slot" frequency and confirm the weekly day checkboxes only
  appear when "Settimanale" is selected.
- [x] Generate weekly slots with multiple time sets and ensure each inherits the
  top-level weekday selection when no per-set days are defined.
- [x] Configure a "Date specifiche" recurrence and check that generation only creates
  slots on the listed days.
- [x] Attempt to generate slots with no time entries and observe the inline validation
  preventing the request.


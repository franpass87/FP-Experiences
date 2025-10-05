# FP Experiences Plugin

This repository contains the source code for the FP Experiences WordPress plugin. Development follows the phased playbook documented in [`docs/PLAYBOOK.md`](docs/PLAYBOOK.md).

## Meeting Points module

* Enable or disable the feature from **FP Experiences → Settings → General → Meeting points module**.
* Manage meeting point entries under **FP Experiences → Meeting Points** with address, notes, contact details, and optional coordinates.
* Bulk create locations by pasting a CSV into **FP Experiences → Import Meeting Points** (`title,address,lat,lng,notes,phone,email,opening_hours`).
* Associate meeting points with experiences from the dedicated meta box when editing an experience (primary + alternative).
* Render the output with the `[fp_exp_meeting_points id="123"]` shortcode or the Elementor “FP Meeting Points” widget; both display the primary location with optional collapsible alternatives and Google Maps links built client-side.

## Experience editor enhancements

* Curate the hero gallery from the **Dettagli → Galleria immagini** panel with drag-and-drop ordering, multi-select uploads, and one-click clearing.
* Pick available languages directly inside the **Dettagli** tab, create new terms on the fly, and preview the public badges (flag + label) before saving.
* Guide editors with reusable badge presets (family friendly, best seller, etc.) that ship with descriptions and can be assigned from the experience form.
* Cleaned up essentials/notes lists to use native bullets so copied checklists render consistently across themes.

## Branding & listing badges

* Customize section icon backgrounds and glyph colours from **Settings → Branding**; the values propagate to the front end via CSS variables and Font Awesome icons.
* Manage the global badge library from **Settings → Showcase → Experience badges**, editing default labels/descriptions or adding organisation-specific entries available to editors.
* Global iconography now comes from the enqueued Font Awesome bundle, ensuring consistent rendering without relying on per-template SVGs.

## Release process

Refer to [README-BUILD.md](README-BUILD.md) for the end-to-end packaging workflow. In short:

1. Run `bash build.sh --bump=patch` (or `--set-version=1.2.3`) to bump the version, install production dependencies, and produce a clean zip in `build/`.
2. Optionally push a tag like `v1.2.3` to trigger the automated GitHub Action that builds and uploads the zip artifact.

## Development checks

Run `tools/run-php-syntax-check.sh` to lint every PHP file in both the source and compiled build trees. The script exits on the first syntax error so issues can be addressed before packaging.

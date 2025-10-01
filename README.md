# FP Experiences Plugin

This repository contains the source code for the FP Experiences WordPress plugin. Development follows the phased playbook documented in [`docs/PLAYBOOK.md`](docs/PLAYBOOK.md).

## Meeting Points module

* Enable or disable the feature from **FP Experiences → Settings → General → Meeting points module**.
* Manage meeting point entries under **FP Experiences → Meeting Points** with address, notes, contact details, and optional coordinates.
* Bulk create locations by pasting a CSV into **FP Experiences → Import Meeting Points** (`title,address,lat,lng,notes,phone,email,opening_hours`).
* Associate meeting points with experiences from the dedicated meta box when editing an experience (primary + alternative).
* Render the output with the `[fp_exp_meeting_points id="123"]` shortcode or the Elementor “FP Meeting Points” widget; both display the primary location with optional collapsible alternatives and Google Maps links built client-side.

## Release process

Refer to [README-BUILD.md](README-BUILD.md) for the end-to-end packaging workflow. In short:

1. Run `bash build.sh --bump=patch` (or `--set-version=1.2.3`) to bump the version, install production dependencies, and produce a clean zip in `build/`.
2. Optionally push a tag like `v1.2.3` to trigger the automated GitHub Action that builds and uploads the zip artifact.

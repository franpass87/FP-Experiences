# FP Experiences Plugin

This repository contains the source code for the FP Experiences WordPress plugin. Development follows the phased playbook documented in [`docs/PLAYBOOK.md`](docs/PLAYBOOK.md).

## Meeting Points module

* Enable or disable the feature from **FP Experiences → Settings → General → Meeting points module**.
* Manage meeting point entries under **FP Experiences → Meeting Points** with address, notes, contact details, and optional coordinates.
* Bulk create locations by pasting a CSV into **FP Experiences → Import Meeting Points** (`title,address,lat,lng,notes,phone,email,opening_hours`).
* Associate meeting points with experiences from the dedicated meta box when editing an experience (primary + alternative).
* Render the output with the `[fp_exp_meeting_points id="123"]` shortcode or the Elementor “FP Meeting Points” widget; both display the primary location with optional collapsible alternatives and Google Maps links built client-side.

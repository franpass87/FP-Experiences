# Phase 1 – Meeting point import toggle

## QA checklist

- [x] Verified the “Meeting point import” toggle defaults to disabled on **Settings → General** and shows the advanced description.
- [x] Confirmed the “Import Meeting Points” submenu is hidden while the toggle is off.
- [x] Enabled the toggle and checked that the submenu appears and the importer page loads for managers.
- [x] Submitted the sample 3-row CSV and validated success/skipped notices render without PHP warnings.
- [x] Disabled the toggle again and confirmed direct access to the importer endpoint returns the “Meeting point import is disabled.” guard.

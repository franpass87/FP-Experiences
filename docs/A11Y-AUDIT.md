# FP Experiences — Accessibility Audit

## Findings
| ID | Area | Severity | Status | Notes |
| -- | ---- | -------- | ------ | ----- |
| A11Y-01 | RTB request feedback | Low | Fixed | Added `role="status"` to the RTB status container so screen readers announce submission state changes surfaced via `aria-live`. |
| A11Y-02 | Calendar & quantity controls | Info | Pass | Calendar days and quantity buttons are keyboard-focusable buttons with descriptive `aria-label` attributes for screen-reader context. |
| A11Y-03 | Checkout summary updates | Info | Pass | Order summary uses `aria-live="polite"` to announce price recalculations without disrupting assistive technology focus. |
| A11Y-04 | Widget focus management | Medium | Fixed | Sticky/modal widgets now expose open/close controls, trap focus while active, restore focus to the trigger, and respond to the Escape key. |
| A11Y-05 | Form error guidance | Medium | Fixed | Checkout and RTB forms surface a summarized error list with field anchors and set `aria-invalid` on each offending control. |
| A11Y-06 | Branding contrast notice | Medium | Fixed | The branding tab renders an accessibility notice that highlights sub-AA contrast ratios in real time and confirms when the palette passes. |

## Recommendations
- Monitor future additions for new interactive surfaces that might also require focus trapping or summarized error feedback.
- Continue validating custom palette presets when new colors are introduced to ensure AA ratios are preserved.

## Verification
- Sticky widget opened via the new launcher, trapped focus with Tab/Shift+Tab, and closed via Escape with focus returning to the trigger.
- Triggered checkout and RTB submissions with empty required fields to confirm the error summary receives focus and links move focus to the associated inputs.
- Adjusted branding colors to low contrast values to observe warning notices and verified success messaging when colors returned to compliant ratios.

Next phase: A6 — Tracking & consent mode.

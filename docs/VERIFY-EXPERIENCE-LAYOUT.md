# Verify Experience Page Layout

## Desktop layout
- Place `[fp_exp_page id="{ID}"]` on a page and view at ≥1024px.
- Confirm the layout shows two columns with content on the left and the booking widget in a sticky aside on the right.
- Scroll to ensure the aside sticks within the viewport and the main sections keep rounded cards with comfortable spacing.
- Toggle the shortcode to `[fp_exp_page ... sidebar="left"]` and verify the widget column moves to the left while the DOM order (main then aside) remains unchanged.
- Test `[fp_exp_page ... sidebar="none"]` and ensure the page becomes single-column, the widget is hidden, and sticky CTA buttons disappear.

## Full-width breakout
- Set shortcode attributes `container="full" max_width="1280" gutter="32"` (or adjust defaults in **Settings → Experience Page Layout**).
- Confirm the layout breaks out of narrow theme containers, aligning edge-to-edge with the configured gutter.
- Resize to ensure the layout recenters correctly when returning to boxed mode.

## Mobile behaviour
- View the page at ≤1023px.
- Check that sections stack vertically with consistent 16–20px spacing and the widget appears below the content.
- Scroll to confirm the sticky CTA bar still appears only when the widget exists.

## Settings defaults
- In **FP Experiences → Settings → Experience Page Layout**, adjust container, max-width, gutter, and sidebar defaults.
- Save and verify a shortcode without explicit layout attributes inherits the updated defaults.

## Elementor widget
- Drop the “FP Experience Page” widget in Elementor and confirm the new layout controls (Container, Maximum width, Side padding, Sidebar) appear.
- Change controls and observe the live preview updates after applying; publish and verify the rendered page matches the selected options.

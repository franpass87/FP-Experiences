# Verify Experience Page Layout

## Desktop layout
- Place `[fp_exp_page id="{ID}"]` on a page and view at ≥1024px.
- Confirm the layout shows two columns with content on the left and the booking widget in a sticky aside on the right.
- Scroll to ensure the aside sticks within the viewport and the main sections keep rounded cards with comfortable spacing.
- Check that the hero gallery renders as a 2:1 grid (large image plus supporting thumbnails) with rounded images and smooth shadows, matching the updated GetYourGuide-inspired style tokens.
- Keyboard-tab through hero CTAs, navigation chips, and widget actions to ensure the new focus rings are clearly visible and maintain sufficient contrast.
- Toggle the shortcode to `[fp_exp_page ... sidebar="left"]` and verify the widget column moves to the left while the DOM order (main then aside) remains unchanged.
- Test `[fp_exp_page ... sidebar="none"]` and ensure the page becomes single-column, the widget is hidden, and sticky CTA buttons disappear.
- Flip `sticky_widget="0"` while keeping a sidebar enabled and confirm the mobile CTA bar stays hidden even though the desktop widget remains in place.
- Increment ticket quantities, toggle add-ons, and pick a slot to confirm the widget summary displays itemised lines, adjustments, and an updated total after the loading state clears.

## Full-width breakout
- Set shortcode attributes `container="full" max_width="1280" gutter="32"` (or adjust defaults in **Settings → Experience Page Layout**).
- Confirm the layout breaks out of narrow theme containers, aligning edge-to-edge with the configured gutter.
- Change the gutter value (e.g. 24 → 40) and reload to verify the desktop padding updates without affecting the sticky widget column.
- Resize to ensure the layout recenters correctly when returning to boxed mode.

## Mobile behaviour
- View the page at ≤1023px.
- Check that sections stack vertically with consistent 16–20px spacing and the widget appears below the content.
- Scroll to confirm the sticky CTA bar still appears only when the widget exists.

## Settings defaults
- In **FP Experiences → Settings → Experience Page Layout**, adjust container, max-width, gutter, and sidebar defaults.
- Save and verify a shortcode without explicit layout attributes inherits the updated defaults.
- Flip the default sidebar to “none” and confirm the shortcode hides the widget and removes the sticky CTA without additional attributes.
- Toggle a Branding preset or custom colors and confirm the injected design tokens update button, badge, and card colors accordingly.

## Elementor widget
- Drop the “FP Experience Page” widget in Elementor and confirm the new layout controls (Container, Maximum width, Side padding, Sidebar) appear.
- Change controls and observe the live preview updates after applying; publish and verify the rendered page matches the selected options.

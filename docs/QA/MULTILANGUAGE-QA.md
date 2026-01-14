# FP Experiences - Multilanguage QA Checklist

Comprehensive multilanguage testing checklist for FP Experiences plugin.

## Preconditions

- [ ] Multilanguage plugin active (FP-Multilanguage, WPML, or Polylang)
- [ ] Multiple languages configured
- [ ] Test content in multiple languages
- [ ] Language switcher functional

---

## 1. Translation Coverage

### User-Facing Strings
- [ ] All frontend strings translatable
- [ ] All admin strings translatable
- [ ] All email template strings translatable
- [ ] All error messages translatable
- [ ] All success messages translatable

### String Extraction
- [ ] All strings use `__()`, `_e()`, `esc_html__()`, etc.
- [ ] Text domain consistent (`fp-experiences`)
- [ ] Context strings used where needed
- [ ] Plural forms handled correctly

### Translation Files
- [ ] .pot file generated
- [ ] Translation files for target languages
- [ ] Translation files loaded correctly
- [ ] Translation updates work

---

## 2. Options Per-Language vs Shared

### Settings Structure
- [ ] Settings can be per-language (if applicable)
- [ ] Settings can be shared (if applicable)
- [ ] Settings UI shows language options
- [ ] Settings inheritance clear

### Experience Content
- [ ] Experience content translatable
- [ ] Experience meta fields translatable
- [ ] Experience pages per language
- [ ] Experience URLs localized

### Admin Settings
- [ ] Admin settings translatable
- [ ] Settings per language (if applicable)
- [ ] Default language settings
- [ ] Language-specific defaults

---

## 3. Page Generation and Sync

### Experience Pages
- [ ] Experience pages created per language
- [ ] Page content synced across languages
- [ ] Page URLs localized correctly
- [ ] Page slugs translated

### Page Updates
- [ ] Updates sync to all languages
- [ ] Or updates language-specific
- [ ] Sync behavior configurable
- [ ] Sync errors handled

### URL Structure
- [ ] URLs use language prefix/suffix
- [ ] Default language URL structure
- [ ] Language switching works
- [ ] Canonical URLs correct

---

## 4. Compatibility

### FP-Multilanguage
- [ ] Compatible with FP-Multilanguage
- [ ] Language detection works
- [ ] Language switching works
- [ ] Content filtering works

### WPML
- [ ] Compatible with WPML
- [ ] String translation works
- [ ] Content translation works
- [ ] Language switcher works

### Polylang
- [ ] Compatible with Polylang
- [ ] Language assignment works
- [ ] Translation links work
- [ ] Language switcher works

---

## 5. URL Structure Consistency

### Localized URLs
- [ ] URLs include language code
- [ ] URLs format consistent
- [ ] Default language handling
- [ ] Language code position correct

### Permalink Structure
- [ ] Permalinks work with languages
- [ ] Rewrite rules correct
- [ ] 404 errors prevented
- [ ] Redirects work correctly

---

## 6. Fallback Logic

### Default Language
- [ ] Falls back to default language
- [ ] Fallback content displays
- [ ] Fallback behavior clear
- [ ] Fallback configurable

### Missing Translations
- [ ] Missing translations handled
- [ ] Partial translations supported
- [ ] Missing translation indicators
- [ ] Translation status visible

---

## 7. Date/Time Localization

### Date Formats
- [ ] Dates formatted per locale
- [ ] Date formats configurable
- [ ] Timezone handling correct
- [ ] Calendar displays correctly

### Time Formats
- [ ] Times formatted per locale
- [ ] 12/24 hour format
- [ ] Timezone display
- [ ] Time format configurable

---

## 8. Currency Formatting

### Currency Display
- [ ] Currency formatted per locale
- [ ] Currency symbol position
- [ ] Decimal separator correct
- [ ] Thousand separator correct

### Currency Conversion
- [ ] Multi-currency support (if applicable)
- [ ] Exchange rates (if applicable)
- [ ] Currency switching
- [ ] Price display correct

---

## 9. RTL Support

### Text Direction
- [ ] RTL languages supported
- [ ] Text direction switches
- [ ] Layout adapts to RTL
- [ ] Icons/images mirrored (if needed)

### CSS
- [ ] RTL styles included
- [ ] Layout works in RTL
- [ ] Forms work in RTL
- [ ] Navigation works in RTL

---

## 10. Email Templates

### Email Translation
- [ ] Email templates translatable
- [ ] Email language matches user
- [ ] Email content localized
- [ ] Email formatting correct

### Email Language Detection
- [ ] User language detected
- [ ] Site language fallback
- [ ] Language per email type
- [ ] Language switching in emails

---

## 11. REST API

### Language in API
- [ ] API responses localized
- [ ] Language parameter accepted
- [ ] Default language handling
- [ ] Language in error messages

### API Endpoints
- [ ] All endpoints support language
- [ ] Language filtering works
- [ ] Language in responses
- [ ] Language switching via API

---

## 12. Shortcodes

### Shortcode Output
- [ ] Shortcode output translated
- [ ] Shortcode attributes localized
- [ ] Shortcode content per language
- [ ] Shortcode language parameter

### Shortcode Behavior
- [ ] Shortcodes respect current language
- [ ] Shortcodes can force language
- [ ] Shortcode fallback works
- [ ] Shortcode errors translated

---

## Test Scenarios

### Scenario 1: Basic Translation
1. Switch to Language A
2. View experience page
3. Verify all text in Language A
4. Switch to Language B
5. Verify all text in Language B

### Scenario 2: Missing Translation
1. Set Language A as default
2. Add content only in Language A
3. Switch to Language B
4. Verify fallback to Language A
5. Verify fallback indicator shown

### Scenario 3: URL Localization
1. Access experience in Language A
2. Verify URL includes language code
3. Switch to Language B
4. Verify URL updates
5. Verify content updates

### Scenario 4: Email Translation
1. Create booking in Language A
2. Receive confirmation email
3. Verify email in Language A
4. Create booking in Language B
5. Verify email in Language B

---

## Expected Results

- [ ] All strings translated
- [ ] Content per language
- [ ] URLs localized
- [ ] Dates/times localized
- [ ] Currency formatted correctly
- [ ] RTL support works
- [ ] Emails translated
- [ ] Fallback works

---

## Failure Indicators

- [ ] Strings not translated
- [ ] Wrong language displayed
- [ ] URLs not localized
- [ ] Date/time format wrong
- [ ] Currency format wrong
- [ ] RTL layout broken
- [ ] Emails in wrong language
- [ ] No fallback

---

## Testing Notes

- Test with 2+ languages
- Test all language combinations
- Test fallback scenarios
- Test URL structures
- Test email delivery
- Verify RTL if applicable
- Check date/time formats
- Verify currency formatting

---

## Multilanguage Support Status

**Supported Plugins:**
- [ ] FP-Multilanguage
- [ ] WPML
- [ ] Polylang
- [ ] Other: _______________

**Translation Coverage:**
- [ ] Frontend: _____%
- [ ] Admin: _____%
- [ ] Emails: _____%
- [ ] Errors: _____%

**Notes:**
_________________________________________________________________
_________________________________________________________________








# FP Experiences - Multisite QA Checklist

**Note:** Verify if FP-Experiences supports multisite. This checklist applies only if multisite support exists.

## Preconditions

- [ ] WordPress multisite network configured
- [ ] Plugin network-activated or site-activated
- [ ] Test sites created
- [ ] Network admin access

---

## 1. Network Activation

### Network Activation Process
- [ ] Plugin can be network-activated
- [ ] No errors during network activation
- [ ] Database tables created correctly
- [ ] Settings initialized

### Network Activation Behavior
- [ ] Activation runs on all sites
- [ ] Or activation runs only on main site
- [ ] Activation logging works
- [ ] Error handling works

---

## 2. Per-Site Provisioning

### Site-Specific Setup
- [ ] Each site gets required database tables
- [ ] Each site gets default settings
- [ ] Site-specific data isolated
- [ ] No cross-site data leakage

### Site Creation
- [ ] New site gets plugin setup automatically
- [ ] Database tables created for new site
- [ ] Default settings applied
- [ ] Custom post types registered

---

## 3. Settings Inheritance

### Network vs Site Settings
- [ ] Network settings exist (if applicable)
- [ ] Site settings override network (if applicable)
- [ ] Settings inheritance rules clear
- [ ] Settings UI shows inheritance status

### Settings Management
- [ ] Network admin can set network defaults
- [ ] Site admin can override (if allowed)
- [ ] Settings locked at network level (if applicable)
- [ ] Settings export/import per site

---

## 4. Cron Events

### Per-Site Cron
- [ ] Cron events registered per site
- [ ] Cron events don't conflict across sites
- [ ] Cron events run independently
- [ ] Cron events use correct site context

### Network Cron
- [ ] Network-wide cron events (if applicable)
- [ ] Network cron doesn't interfere with site cron
- [ ] Cron event scheduling correct

---

## 5. Database Tables

### Table Creation
- [ ] Tables created per site (if applicable)
- [ ] Tables use correct site prefix
- [ ] Network tables (if applicable)
- [ ] Table structure consistent across sites

### Data Isolation
- [ ] Site data isolated from other sites
- [ ] No cross-site data queries
- [ ] Site switching works correctly
- [ ] Data queries use correct site context

---

## 6. Plugin Deactivation

### Per-Site Deactivation
- [ ] Plugin can be deactivated per site
- [ ] Deactivation doesn't affect other sites
- [ ] Site-specific cleanup runs
- [ ] Data preserved (if configured)

### Network Deactivation
- [ ] Network deactivation works
- [ ] All sites deactivated
- [ ] Network-wide cleanup runs
- [ ] Data handling correct

---

## 7. Cross-Site Contamination Prevention

### Data Isolation
- [ ] No shared data between sites
- [ ] Queries scoped to current site
- [ ] User data isolated
- [ ] Settings isolated

### Security
- [ ] Users can't access other sites' data
- [ ] Capability checks site-specific
- [ ] REST API scoped to site
- [ ] Admin UI shows only current site data

---

## 8. Network Admin Features

### Network Admin UI
- [ ] Network admin menu appears
- [ ] Network settings page works
- [ ] Network-wide statistics
- [ ] Network-wide tools

### Network Capabilities
- [ ] Network admin capabilities enforced
- [ ] Site admin capabilities enforced
- [ ] Capability checks work correctly

---

## 9. Site Switching

### Context Switching
- [ ] Plugin works after site switch
- [ ] Data loads for correct site
- [ ] Settings load for correct site
- [ ] No cached data from wrong site

### Multisite Functions
- [ ] `switch_to_blog()` works
- [ ] `restore_current_blog()` works
- [ ] Site context maintained
- [ ] No context leaks

---

## 10. Performance

### Per-Site Performance
- [ ] Plugin performance acceptable per site
- [ ] No performance degradation with many sites
- [ ] Database queries optimized
- [ ] Caching works per site

### Network Performance
- [ ] Network activation doesn't slow down
- [ ] Network admin pages load quickly
- [ ] Bulk operations efficient

---

## Test Scenarios

### Scenario 1: Network Activation
1. Network-activate plugin
2. Verify tables created
3. Verify settings initialized
4. Check multiple sites

### Scenario 2: Site Activation
1. Activate plugin on single site
2. Verify tables created for that site only
3. Verify other sites unaffected
4. Test site-specific features

### Scenario 3: Cross-Site Data
1. Create data on Site A
2. Switch to Site B
3. Verify Site A data not visible
4. Verify Site B has own data

### Scenario 4: Network Settings
1. Set network default settings
2. Verify sites inherit settings
3. Override on one site
4. Verify other sites unchanged

---

## Expected Results

- [ ] Plugin works correctly in multisite
- [ ] Data isolated per site
- [ ] Settings inheritance works
- [ ] No cross-site contamination
- [ ] Performance acceptable
- [ ] No errors in network admin
- [ ] Site switching works

---

## Failure Indicators

- [ ] Data visible across sites
- [ ] Settings affect all sites unexpectedly
- [ ] Errors during network activation
- [ ] Performance degradation
- [ ] Cross-site queries
- [ ] Context switching failures

---

## Testing Notes

- Test with 3+ sites minimum
- Test network and site activation
- Test site switching extensively
- Monitor database queries
- Check for data leakage
- Verify performance

---

## Multisite Support Status

**Current Status:** [ ] Supported [ ] Not Supported [ ] Partial Support

**Notes:**
_________________________________________________________________
_________________________________________________________________








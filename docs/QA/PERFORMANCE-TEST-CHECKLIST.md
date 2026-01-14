# FP Experiences - Performance Testing Checklist

Comprehensive performance testing checklist for FP Experiences plugin.

## 1. Database Performance

### Query Optimization
- [ ] No N+1 query problems
- [ ] Queries use indexes
- [ ] Unnecessary queries eliminated
- [ ] Query results cached where appropriate

### Query Count
- [ ] Page load query count < 50 (admin)
- [ ] Page load query count < 30 (frontend)
- [ ] REST API query count < 20 per request
- [ ] Query count measured with Query Monitor

**Measurement:**
```php
// Enable Query Monitor plugin
// Check query count on each page
```

### Query Performance
- [ ] Slow queries identified
- [ ] Queries optimized with EXPLAIN
- [ ] Indexes added where needed
- [ ] Query execution time < 100ms per query

---

## 2. Memory Usage

### Memory Footprint
- [ ] Plugin memory usage < 20MB
- [ ] No memory leaks detected
- [ ] Memory usage stable over time
- [ ] Memory cleanup on deactivation

### Memory Leaks
- [ ] No increasing memory usage
- [ ] Objects properly destroyed
- [ ] Event listeners removed
- [ ] Transients cleaned up

**Measurement:**
```php
// Check memory usage
echo memory_get_usage(true) / 1024 / 1024 . ' MB';
```

---

## 3. Asset Loading

### CSS Performance
- [ ] CSS files minified in production
- [ ] CSS files combined where possible
- [ ] Critical CSS inlined (if applicable)
- [ ] Non-critical CSS deferred

### JavaScript Performance
- [ ] JavaScript files minified in production
- [ ] JavaScript files combined where possible
- [ ] Scripts deferred/async where appropriate
- [ ] No render-blocking scripts

### Asset Size
- [ ] Total CSS size < 100KB
- [ ] Total JavaScript size < 200KB
- [ ] Images optimized
- [ ] SVG used where possible

**Measurement:**
- Use browser DevTools Network tab
- Check total transfer size
- Check load time

---

## 4. Page Load Times

### Admin Pages
- [ ] Settings page loads < 2s
- [ ] Experience edit page loads < 2s
- [ ] Calendar admin loads < 3s
- [ ] Orders page loads < 2s

### Frontend Pages
- [ ] Experience listing loads < 2s
- [ ] Single experience page loads < 2s
- [ ] Checkout page loads < 2s
- [ ] Calendar shortcode loads < 1s

### REST API
- [ ] Availability endpoint < 500ms
- [ ] Calendar endpoint < 500ms
- [ ] Checkout endpoint < 1s
- [ ] Settings endpoint < 200ms

**Measurement:**
- Use browser DevTools Performance tab
- Use Lighthouse
- Use GTmetrix or WebPageTest

---

## 5. Caching Effectiveness

### Transient Cache
- [ ] Transients used for expensive operations
- [ ] Cache keys unique and descriptive
- [ ] Cache TTL appropriate
- [ ] Cache invalidation works

### Object Cache
- [ ] Works with object cache plugins
- [ ] Cache hits measured
- [ ] Cache misses handled gracefully

### Page Cache
- [ ] REST API excluded from page cache
- [ ] Dynamic content not cached
- [ ] Cache invalidation on updates

**Test Cases:**
- First load (cache miss) → Measure time
- Second load (cache hit) → Measure time
- Cache invalidation → Verify updates

---

## 6. AJAX Performance

### Response Times
- [ ] AJAX requests complete < 1s
- [ ] Large responses paginated
- [ ] Error handling doesn't block

### Request Optimization
- [ ] Only necessary data sent
- [ ] Requests batched where possible
- [ ] Debouncing/throttling used

**Measurement:**
- Use browser DevTools Network tab
- Check AJAX request timing
- Check response size

---

## 7. Database Table Performance

### Table Structure
- [ ] Indexes on frequently queried columns
- [ ] Foreign keys indexed
- [ ] Table structure optimized
- [ ] No redundant columns

### Table Size
- [ ] Tables don't grow unbounded
- [ ] Old data cleaned up
- [ ] Table optimization scheduled

**Measurement:**
```sql
-- Check table size
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES
WHERE table_schema = DATABASE()
AND table_name LIKE 'wp_fp_exp_%';
```

---

## 8. Slot Availability Calculation

### Performance
- [ ] Availability calculation < 500ms
- [ ] Large date ranges handled efficiently
- [ ] Recurring slots generated efficiently
- [ ] Results cached appropriately

### Optimization
- [ ] Database queries optimized
- [ ] In-memory calculations minimized
- [ ] Batch processing for large sets

**Test Cases:**
- Calculate availability for 1 day → Measure time
- Calculate availability for 1 month → Measure time
- Calculate availability for 1 year → Measure time

---

## 9. Email Performance

### Email Sending
- [ ] Emails sent asynchronously (where possible)
- [ ] Email queue processed efficiently
- [ ] Bulk emails batched
- [ ] Email sending doesn't block requests

### Email Templates
- [ ] Templates cached
- [ ] Template rendering optimized
- [ ] Large attachments handled

---

## 10. Cron Job Performance

### Execution Time
- [ ] Cron jobs complete < 30s
- [ ] Long-running jobs split into batches
- [ ] Timeout handling implemented

### Resource Usage
- [ ] Cron jobs don't consume excessive memory
- [ ] Cron jobs don't block other processes
- [ ] Concurrent execution handled

**Test Cases:**
- Run cron job manually → Measure time
- Monitor resource usage
- Check for timeouts

---

## 11. Integration Performance

### External API Calls
- [ ] API calls have timeouts
- [ ] Failed requests don't block
- [ ] Responses cached where appropriate
- [ ] Rate limiting respected

### Google Calendar
- [ ] Sync operations efficient
- [ ] Batch operations used
- [ ] Errors don't block

### Brevo Email
- [ ] Email sending efficient
- [ ] Bulk sending optimized
- [ ] Rate limits respected

---

## 12. Frontend Performance

### JavaScript Execution
- [ ] JavaScript execution < 100ms
- [ ] Event handlers optimized
- [ ] DOM manipulation minimized
- [ ] Debouncing/throttling used

### Rendering
- [ ] No layout shifts
- [ ] Images lazy-loaded
- [ ] Content loaded progressively
- [ ] Animations 60fps

**Measurement:**
- Use Lighthouse
- Use Chrome DevTools Performance
- Check Core Web Vitals

---

## Performance Benchmarks

### Target Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Admin page load | < 2s | Lighthouse |
| Frontend page load | < 2s | Lighthouse |
| REST API response | < 500ms | Network tab |
| Database queries | < 50/page | Query Monitor |
| Memory usage | < 20MB | PHP memory functions |
| CSS size | < 100KB | Network tab |
| JS size | < 200KB | Network tab |

---

## Performance Testing Tools

### Recommended Tools
- **Lighthouse** - Performance auditing
- **Query Monitor** - Database query analysis
- **New Relic** - Application performance monitoring
- **GTmetrix** - Page speed testing
- **WebPageTest** - Detailed performance analysis

### WordPress-Specific
- **Query Monitor** plugin
- **Debug Bar** plugin
- **P3 Profiler** plugin

---

## Performance Optimization Checklist

**Before Release:**
- [ ] All queries optimized
- [ ] Assets minified
- [ ] Caching implemented
- [ ] Lazy loading enabled
- [ ] Database indexes added
- [ ] Memory leaks fixed
- [ ] Performance audit completed
- [ ] Lighthouse score > 90

**Performance Score Target:** > 90/100 (Lighthouse)

---

## Performance Monitoring

### Production Monitoring
- [ ] Slow queries logged
- [ ] Error rates monitored
- [ ] Response times tracked
- [ ] Resource usage monitored

### Alerts
- [ ] Alerts for slow pages
- [ ] Alerts for high error rates
- [ ] Alerts for memory issues
- [ ] Alerts for database issues

---

## Performance Regression Testing

### Baseline Metrics
- [ ] Baseline metrics established
- [ ] Metrics tracked over time
- [ ] Regressions identified quickly
- [ ] Performance budgets set

### Testing Process
1. Measure baseline performance
2. Make changes
3. Re-measure performance
4. Compare against baseline
5. Fix regressions before release

---

## Notes

- Performance testing should be done on staging environment
- Use production-like data volumes
- Test with realistic traffic patterns
- Monitor performance over time
- Document performance improvements








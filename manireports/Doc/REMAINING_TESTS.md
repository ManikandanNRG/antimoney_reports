# ManiReports - Remaining Test Scenarios

**Last Updated**: November 18, 2025
**Tests Completed**: 8 out of 19 (42%)
**Tests Remaining**: 11 (58%)

---

## ✅ COMPLETED TESTS (8/19)

### Section 1: Quick Tests
1. ✅ Test 1: Verify Upgrade Success
2. ✅ Test 2: Check All Tables Exist
3. ✅ Test 3: Access Plugin Settings
4. ✅ Test 4: Access Admin Dashboard
5. ✅ Test 5: Run a Prebuilt Report
6. ✅ Test 6: Test Export
7. ⏳ Test 7: Check Error Logs (PENDING)

### Section 2: Standard Tests
8. ✅ Test 8.1: Course Completion Report (with filters, navigation, user search)

---

## 🔄 REMAINING TESTS (11/19)

### HIGH PRIORITY - Core Functionality (5 tests)

#### 1. Test 8.2: Course Progress Report
**Estimated Time**: 5 minutes
**What to Test**:
- Navigate to: `/local/manireports/ui/report_view.php?report=course_progress`
- Select a specific course from filter
- Test username/email search filter
- Test company filter
- Check execution time (should be < 10 seconds)
- Test exports (CSV, XLSX, PDF)

**Why Important**: Verifies per-user progress tracking works correctly

---

#### 2. Test 8.3: SCORM Summary Report
**Estimated Time**: 5 minutes
**What to Test**:
- Navigate to: `/local/manireports/ui/report_view.php?report=scorm_summary`
- Check displayed data (attempts, completion, scores)
- Test username/email search filter
- Test company filter
- Test exports

**Why Important**: Verifies SCORM analytics aggregation works

---

#### 3. Test 8.4: User Engagement Report
**Estimated Time**: 5 minutes
**What to Test**:
- Navigate to: `/local/manireports/ui/report_view.php?report=user_engagement`
- Check time spent and active days data
- Test username/email search filter
- Test company filter
- Test date range filter
- Test exports

**Why Important**: Verifies time tracking data is being aggregated correctly

---

#### 4. Test 8.5: Quiz Attempts Report
**Estimated Time**: 5 minutes
**What to Test**:
- Navigate to: `/local/manireports/ui/report_view.php?report=quiz_attempts`
- Check quiz attempts and scores
- Test username/email search filter
- Test company filter
- Test date range filter
- Test exports

**Why Important**: Verifies quiz analytics work correctly

---

#### 5. Test 11: Report Scheduling
**Estimated Time**: 10 minutes
**What to Test**:
- Create a scheduled report (daily/weekly)
- Add email recipients
- Run manually: `sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\report_scheduler`
- Check if email is received with attachment
- Verify "Last Run" time is updated

**Why Important**: Verifies automated report delivery works

---

### MEDIUM PRIORITY - Advanced Features (4 tests)

#### 6. Test 9: Custom SQL Reports
**Estimated Time**: 10 minutes
**What to Test**:
- Create a custom SQL report
- Execute the report
- Test SQL security (try malicious SQL)
- Test exports

**Why Important**: Verifies custom report builder works and is secure

---

#### 7. Test 10: GUI Report Builder (NEW FEATURE)
**Estimated Time**: 15 minutes
**What to Test**:
- Access GUI builder interface
- Create simple report (single table)
- Create report with JOIN (multiple tables)
- Execute GUI reports
- Test exports

**Why Important**: Verifies new GUI builder feature works

---

#### 8. Test 12: Dashboard Builder (NEW FEATURE)
**Estimated Time**: 15 minutes
**What to Test**:
- Access dashboard builder
- Create custom dashboard with widgets
- Arrange widgets in grid
- View custom dashboard
- Test widget data loading

**Why Important**: Verifies new dashboard customization feature works

---

#### 9. Test 13: Drill-Down Functionality (NEW FEATURE)
**Estimated Time**: 10 minutes
**What to Test**:
- Click on chart data points
- Verify navigation to filtered report
- Test filter badges and removal
- Test "Clear All" button
- Test export from drill-down view
- Test browser back button

**Why Important**: Verifies new drill-down navigation feature works

---

### LOW PRIORITY - Background Tasks & Security (2 tests)

#### 10. Test 14: Time Tracking
**Estimated Time**: 10 minutes
**What to Test**:
- Check heartbeat AJAX requests in browser console
- Verify session records in database
- Run time aggregation task manually
- Check daily summary records

**Why Important**: Verifies time tracking system works

---

#### 11. Test 15-19: Security & Performance
**Estimated Time**: 20 minutes
**What to Test**:
- Test capability enforcement (student access)
- Test SQL injection prevention
- Test XSS prevention
- Run all scheduled tasks
- Check audit logging
- Measure performance (load times)

**Why Important**: Verifies security and performance requirements are met

---

## 📊 TESTING PRIORITY MATRIX

### Must Test Before Production (Critical)
1. ✅ Course Completion Report (DONE)
2. ⏳ Course Progress Report
3. ⏳ User Engagement Report
4. ⏳ Quiz Attempts Report
5. ⏳ SCORM Summary Report
6. ⏳ Report Scheduling
7. ⏳ Security Tests (SQL injection, XSS, capabilities)

### Should Test (Important)
8. ⏳ Custom SQL Reports
9. ⏳ Time Tracking
10. ⏳ Scheduled Tasks
11. ⏳ Audit Logging

### Nice to Test (Optional - New Features)
12. ⏳ GUI Report Builder
13. ⏳ Dashboard Builder
14. ⏳ Drill-Down Functionality

---

## 🎯 RECOMMENDED TESTING ORDER

### Phase 1: Core Reports (30 minutes)
Test all 5 prebuilt reports to ensure basic functionality works:
1. Course Progress Report (5 min)
2. SCORM Summary Report (5 min)
3. User Engagement Report (5 min)
4. Quiz Attempts Report (5 min)
5. Check Error Logs (10 min)

### Phase 2: Automation & Security (30 minutes)
Test scheduling and security:
6. Report Scheduling (10 min)
7. Custom SQL Reports (10 min)
8. Security Tests (10 min)

### Phase 3: Advanced Features (40 minutes) - OPTIONAL
Test new features if time permits:
9. GUI Report Builder (15 min)
10. Dashboard Builder (15 min)
11. Drill-Down Functionality (10 min)

### Phase 4: Background Systems (20 minutes) - OPTIONAL
Test background tasks:
12. Time Tracking (10 min)
13. Scheduled Tasks (10 min)

---

## 📝 QUICK TEST COMMANDS

### Check Error Logs
```bash
tail -50 /opt/moodledata/moodledata.log | grep -i error
tail -50 /var/log/apache2/error.log | grep manireports
```

### Run Scheduled Tasks
```bash
# Report Scheduler
sudo -u www-data php /var/www/html/admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\report_scheduler

# Time Aggregation
sudo -u www-data php /var/www/html/admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\time_aggregation

# Cache Builder
sudo -u www-data php /var/www/html/admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\cache_builder

# SCORM Summary
sudo -u www-data php /var/www/html/admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\scorm_summary

# Cleanup
sudo -u www-data php /var/www/html/admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\cleanup_old_data
```

### Clear Cache
```bash
sudo -u www-data php /var/www/html/admin/cli/purge_caches.php
```

---

## ✅ COMPLETION CHECKLIST

Before marking testing as complete, ensure:

- [ ] All 5 prebuilt reports work correctly
- [ ] All filters work (company, date, course, user search)
- [ ] All exports work (CSV, XLSX, PDF)
- [ ] Navigation buttons work (Back to Dashboard, Clear Filters)
- [ ] Report scheduling works and emails are delivered
- [ ] Custom SQL reports work and are secure
- [ ] Security tests pass (no unauthorized access)
- [ ] No errors in error logs
- [ ] Performance is acceptable (< 3s dashboard, < 10s reports)

---

## 🚀 NEXT STEPS

1. **Complete Phase 1** (Core Reports) - 30 minutes
   - This will verify all basic reporting functionality works

2. **Complete Phase 2** (Automation & Security) - 30 minutes
   - This will verify scheduling and security are working

3. **Optional: Complete Phase 3 & 4** (Advanced Features) - 60 minutes
   - Only if you want to test the new features (GUI builder, dashboard builder, drill-down)

**Total Minimum Testing Time**: 1 hour (Phases 1 & 2)
**Total Complete Testing Time**: 2 hours (All phases)

---

## 📞 SUPPORT

If you encounter any issues during testing:
1. Check the error logs first
2. Clear cache and retry
3. Report the issue with:
   - Test number
   - Error message
   - Steps to reproduce
   - Browser console output (if applicable)


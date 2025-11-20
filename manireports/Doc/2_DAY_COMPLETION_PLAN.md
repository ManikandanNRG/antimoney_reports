# 2-Day Completion Plan

**Deadline**: 2 days from now
**Required Features**: Time Tracking, Manager/Teacher Dashboards, GDPR Compliance
**Skip**: Caching (not needed)

---

## DAY 1: Core Missing Features (6-7 hours)

### Session 1: Manager Dashboard (1.5 hours)
**Task**: Complete manager dashboard with company filtering

**Files to modify**:
1. `classes/output/dashboard_renderer.php` - Add render_manager_dashboard()
2. `templates/dashboard_manager.mustache` - Complete template
3. Test with IOMAD company filtering

**Deliverables**:
- Manager sees only their company data
- KPI widgets for company metrics
- Company-specific charts

---

### Session 2: Teacher Dashboard (1.5 hours)
**Task**: Complete teacher dashboard with course filtering

**Files to modify**:
1. `classes/output/dashboard_renderer.php` - Add render_teacher_dashboard()
2. `templates/dashboard_teacher.mustache` - Complete template
3. Show only teacher's courses and students

**Deliverables**:
- Teacher sees only their courses
- Student progress widgets
- Course completion charts

---

### Session 3: Time Tracking Integration (2 hours)
**Task**: Integrate heartbeat JavaScript into course pages

**Files to modify**:
1. `lib.php` - Add hook to inject heartbeat.js into course pages
2. `amd/src/heartbeat.js` - Already exists, verify code
3. `ui/ajax/heartbeat.php` - Already exists, test endpoint
4. `classes/tasks/time_aggregation.php` - Already exists, test task

**Steps**:
1. Add JavaScript injection in lib.php
2. Test heartbeat AJAX calls
3. Test session recording
4. Test aggregation task
5. Verify time data appears in reports

**Deliverables**:
- Heartbeat sends every 20-30 seconds
- Sessions recorded in database
- Aggregation task runs successfully
- Time data visible in User Engagement report

---

### Session 4: SCORM Optimization (1 hour)
**Task**: Add incremental processing to SCORM task

**Files to modify**:
1. `classes/tasks/scorm_summary.php` - Add timestamp tracking

**Deliverables**:
- Only process new SCORM data
- Faster execution on large datasets

---

## DAY 2: Compliance & Polish (5-6 hours)

### Session 5: Privacy API (GDPR) (2 hours)
**Task**: Implement GDPR data export and deletion

**Files to create/modify**:
1. `classes/privacy/provider.php` - Create privacy provider
2. Implement get_metadata()
3. Implement export_user_data()
4. Implement delete_data_for_user()

**Data to handle**:
- Time tracking sessions
- Audit logs
- Custom reports created by user
- Report run history

**Deliverables**:
- User data can be exported
- User data can be deleted
- Privacy API compliant

---

### Session 6: Data Retention & Cleanup (1 hour)
**Task**: Complete cleanup task for old data

**Files to modify**:
1. `classes/task/cleanup_old_data.php` - Already exists, complete implementation
2. `settings.php` - Add retention period settings

**Deliverables**:
- Old audit logs cleaned up
- Old report runs cleaned up
- Old session data cleaned up
- Configurable retention periods

---

### Session 7: Security Hardening (1 hour)
**Task**: Final security review and fixes

**Checklist**:
1. Verify all inputs use PARAM_* validation
2. Verify all outputs are escaped
3. Verify capability checks on all pages
4. Test SQL injection prevention
5. Test XSS prevention
6. Test CSRF protection

**Deliverables**:
- All security checks pass
- No vulnerabilities found

---

### Session 8: Documentation (1 hour)
**Task**: Create essential documentation

**Files to create**:
1. `USER_GUIDE.md` - How to use dashboards and reports
2. `ADMIN_GUIDE.md` - How to configure and manage
3. Update `README.md` - Installation and features
4. Update `INSTALL.md` - Deployment instructions

**Deliverables**:
- Clear user documentation
- Clear admin documentation
- Installation guide complete

---

### Session 9: Final Testing (1 hour)
**Task**: Run through all features and fix issues

**Test Checklist**:
- [ ] Admin dashboard loads
- [ ] Manager dashboard loads (with company filtering)
- [ ] Teacher dashboard loads (with course filtering)
- [ ] Student dashboard loads
- [ ] All 5 reports work
- [ ] Time tracking records sessions
- [ ] Custom SQL reports work
- [ ] Scheduling works
- [ ] Exports work (CSV, XLSX, PDF)
- [ ] Audit logs work
- [ ] Privacy export works
- [ ] Privacy deletion works
- [ ] All scheduled tasks run
- [ ] No errors in logs

---

## IMPLEMENTATION ORDER

### TODAY (Day 1):
1. ✅ AMD build (DONE)
2. ⏳ Manager Dashboard (1.5h)
3. ⏳ Teacher Dashboard (1.5h)
4. ⏳ Time Tracking (2h)
5. ⏳ SCORM Optimization (1h)

**Total**: 6 hours

### TOMORROW (Day 2):
6. ⏳ Privacy API (2h)
7. ⏳ Data Cleanup (1h)
8. ⏳ Security Review (1h)
9. ⏳ Documentation (1h)
10. ⏳ Final Testing (1h)

**Total**: 6 hours

---

## ADDITIONAL FEATURES

You mentioned additional features to add later. We'll handle those AFTER completing this 2-day plan.

**Process for new features**:
1. You describe the feature
2. I estimate time required
3. We agree on implementation
4. I implement and test

---

## DEPLOYMENT STRATEGY

After each session:
1. Upload files to server
2. Purge caches
3. Test functionality
4. Fix any issues before moving on

**Commands**:
```bash
# Upload files (use your method: Git, SCP, etc.)

# Purge caches
sudo -u www-data php /var/www/html/admin/cli/purge_caches.php

# Test scheduled tasks
sudo -u www-data php /var/www/html/admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\time_aggregation

# Check logs
tail -50 /opt/moodledata/moodledata.log | grep manireports
```

---

## READY TO START?

I'll begin with **Session 1: Manager Dashboard** right now.

This will take about 1.5 hours to:
1. Complete the dashboard renderer for managers
2. Update the Mustache template
3. Add company-specific KPIs and charts
4. Test with IOMAD filtering

**Should I proceed?**


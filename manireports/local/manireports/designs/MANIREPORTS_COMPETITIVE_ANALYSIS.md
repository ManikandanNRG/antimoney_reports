# ManiReports: Competitive Analysis & Feature Roadmap

## Executive Summary

ManiReports is positioned as a **self-hosted, IOMAD-native analytics platform** that combines the best features of Edwiser Reports, LearnerScript, and IntelliBoard while maintaining local data storage and superior multi-tenant support.

---

## Competitive Landscape Analysis

### 1. EDWISER REPORTS
**Strengths:**
- Simple, lightweight implementation
- Easy installation and setup
- Low database overhead
- Basic course completion reports

**Weaknesses:**
- ❌ No IOMAD/multi-tenant support
- ❌ No custom SQL report builder
- ❌ Limited engagement analytics
- ❌ No custom dashboards
- ❌ Basic time tracking
- ❌ No predictive analytics

**Best For:** Small single-tenant Moodle installations

---

### 2. LEARNERSCRIPT
**Strengths:**
- ✅ IOMAD multi-tenant support
- ✅ Custom SQL report builder
- ✅ Advanced SCORM analytics
- ✅ Custom dashboards
- ✅ Scheduled email reports
- ✅ Good engagement metrics

**Weaknesses:**
- ⚠️ Medium database load
- ⚠️ Requires cron jobs
- ⚠️ Limited real-time dashboards
- ⚠️ No predictive analytics
- ⚠️ Moderate time tracking

**Best For:** IOMAD environments needing custom reports

---

### 3. INTELLIBOARD
**Strengths:**
- ✅ Real-time dashboards
- ✅ Advanced time tracking
- ✅ Predictive analytics (at-risk detection)
- ✅ Excellent performance on large sites
- ✅ Scheduled reports
- ✅ Department-level reporting

**Weaknesses:**
- ❌ Cloud-based (data leaves your server)
- ❌ High cost
- ❌ Limited IOMAD support
- ❌ No custom SQL reports
- ❌ Vendor lock-in

**Best For:** Large enterprises with budget

---

## ManiReports: Competitive Positioning

### Core Advantages Over Competitors

| Feature | Edwiser | LearnerScript | IntelliBoard | **ManiReports** |
|---------|---------|---------------|--------------|-----------------|
| **IOMAD Support** | ❌ | ✅ | ⚠️ | ✅✅ |
| **Custom SQL Reports** | ❌ | ✅ | ❌ | ✅✅ |
| **Custom Dashboards** | ❌ | ✅ | ⚠️ | ✅✅ |
| **Time Tracking** | ⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Real-Time Analytics** | ❌ | ⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ |
| **Predictive Analytics** | ❌ | ❌ | ✅ | ✅ |
| **Data Storage** | Local | Local | Cloud | **Local** |
| **Cost** | Low | Medium | High | **Low** |
| **Self-Hosted** | ✅ | ✅ | ❌ | ✅ |
| **No Vendor Lock-in** | ✅ | ✅ | ❌ | ✅ |

---

## ManiReports: Current Implementation

### Role-Based Dashboards (ACTUAL)

#### 1. ADMIN DASHBOARD
**Current Features:**
- System-wide KPI widgets
- Company management (IOMAD)
- Course usage analytics
- Inactive users tracking
- System health metrics
- User growth trends
- Course completion overview
- Audit logs

**Data Displayed:**
```
┌─────────────────────────────────────────────────────┐
│ ADMIN DASHBOARD                                     │
├─────────────────────────────────────────────────────┤
│                                                     │
│ KPI WIDGETS:                                        │
│ ├─ Total Users (with growth %)                     │
│ ├─ Total Courses (with status)                     │
│ ├─ Course Completions (with trend)                 │
│ ├─ Active Users (real-time)                        │
│ ├─ System Health (uptime, cron status)             │
│ └─ Database Performance (query time)               │
│                                                     │
│ ANALYTICS:                                          │
│ ├─ Company Overview (IOMAD)                        │
│ ├─ Course Usage Heatmap                            │
│ ├─ User Activity Timeline                          │
│ ├─ Inactive Users List (>7 days)                   │
│ ├─ Course Completion Trends                        │
│ └─ Audit Log (recent activities)                   │
│                                                     │
│ ACTIONS:                                            │
│ ├─ View Detailed Reports                           │
│ ├─ Generate Custom Reports                         │
│ ├─ Schedule Report Delivery                        │
│ └─ Configure System Settings                       │
│                                                     │
└─────────────────────────────────────────────────────┘
```

#### 2. MANAGER DASHBOARD (IOMAD)
**Current Features:**
- Company-specific metrics
- Department performance
- Employee engagement tracking
- Company courses overview
- Team member list with status
- Compliance tracking
- Company-level reports

**Data Displayed:**
```
┌─────────────────────────────────────────────────────┐
│ MANAGER DASHBOARD (Company: Acme Corp)              │
├─────────────────────────────────────────────────────┤
│                                                     │
│ COMPANY METRICS:                                    │
│ ├─ Total Employees (with growth)                   │
│ ├─ Active Courses (company-specific)               │
│ ├─ Avg Completion Rate (company)                   │
│ ├─ Avg Engagement Score (company)                  │
│ └─ At-Risk Employees (count)                       │
│                                                     │
│ DEPARTMENT PERFORMANCE:                             │
│ ├─ Department-wise completion rates                │
│ ├─ Department engagement scores                    │
│ ├─ Department time spent averages                  │
│ └─ Department-wise at-risk count                   │
│                                                     │
│ EMPLOYEE TRACKING:                                  │
│ ├─ Company users list (with status)                │
│ ├─ Individual progress tracking                    │
│ ├─ Compliance course status                        │
│ ├─ Time spent per employee                         │
│ └─ At-risk employee alerts                         │
│                                                     │
│ COMPANY COURSES:                                    │
│ ├─ Assigned courses list                           │
│ ├─ Enrollment status                               │
│ ├─ Completion tracking                             │
│ └─ Course-wise performance                         │
│                                                     │
└─────────────────────────────────────────────────────┘
```

#### 3. TEACHER DASHBOARD
**Current Features:**
- Course-specific analytics
- Student progress tracking
- Individual student performance
- Assignment/quiz analytics
- Recent activity feed
- At-risk student alerts
- Course completion overview

**Data Displayed:**
```
┌─────────────────────────────────────────────────────┐
│ TEACHER DASHBOARD (Course: Advanced Analytics)      │
├─────────────────────────────────────────────────────┤
│                                                     │
│ COURSE METRICS:                                     │
│ ├─ Total Students (enrolled)                       │
│ ├─ Avg Completion Rate (course)                    │
│ ├─ Avg Grade (course)                              │
│ ├─ Avg Time Spent (per student)                    │
│ └─ At-Risk Students (count)                        │
│                                                     │
│ STUDENT PROGRESS:                                   │
│ ├─ Completed: X students (%)                       │
│ ├─ In Progress: X students (%)                     │
│ ├─ Not Started: X students (%)                     │
│ └─ At-Risk: X students (%)                         │
│                                                     │
│ INDIVIDUAL TRACKING:                                │
│ ├─ Student list with progress bars                 │
│ ├─ Individual grades/scores                        │
│ ├─ Time spent per student                          │
│ ├─ Last activity timestamp                         │
│ └─ Engagement level (color-coded)                  │
│                                                     │
│ ACTIVITY & ALERTS:                                  │
│ ├─ Recent submissions                              │
│ ├─ Quiz attempts                                   │
│ ├─ Discussion posts                                │
│ ├─ At-risk student alerts                          │
│ └─ Pending assignments                             │
│                                                     │
│ ANALYTICS:                                          │
│ ├─ Performance distribution chart                  │
│ ├─ Engagement trend line                           │
│ ├─ Time spent histogram                            │
│ └─ Completion rate pie chart                       │
│                                                     │
└─────────────────────────────────────────────────────┘
```

#### 4. STUDENT DASHBOARD
**Current Features:**
- Personal course progress
- Grade tracking
- Time spent analytics
- Upcoming deadlines
- Achievement badges
- Course recommendations
- Performance trends

**Data Displayed:**
```
┌─────────────────────────────────────────────────────┐
│ STUDENT DASHBOARD (Welcome, John Smith!)            │
├─────────────────────────────────────────────────────┤
│                                                     │
│ PERSONAL METRICS:                                   │
│ ├─ Courses Enrolled (count)                        │
│ ├─ Courses Completed (count)                       │
│ ├─ Avg Grade (across courses)                      │
│ ├─ Total Time Spent (hours)                        │
│ └─ Current Engagement Level (%)                    │
│                                                     │
│ MY COURSES:                                         │
│ ├─ Course name                                     │
│ ├─ Progress bar (%)                                │
│ ├─ Current grade                                   │
│ ├─ Time spent (hours)                              │
│ ├─ Last activity (timestamp)                       │
│ └─ Status (Completed/In Progress/Not Started)      │
│                                                     │
│ PERFORMANCE:                                        │
│ ├─ Grade trends (line chart)                       │
│ ├─ Time spent trends (area chart)                  │
│ ├─ Engagement trends (line chart)                  │
│ └─ Performance distribution (pie chart)            │
│                                                     │
│ UPCOMING:                                           │
│ ├─ Assignment deadlines                            │
│ ├─ Quiz dates                                      │
│ ├─ Project submissions                             │
│ └─ Course end dates                                │
│                                                     │
│ ACHIEVEMENTS:                                       │
│ ├─ Badges earned                                   │
│ ├─ Milestones reached                              │
│ ├─ Certificates earned                             │
│ └─ Streaks (consecutive days)                      │
│                                                     │
└─────────────────────────────────────────────────────┘
```

---

## ManiReports: Advanced Features (Implemented)

### 1. CUSTOM SQL REPORT BUILDER
- Admin-only SQL query builder
- Whitelisted table access
- Parameter binding for security
- Query validation
- Result pagination
- Export to CSV/XLSX/PDF

### 2. SCHEDULED REPORTS
- Daily/Weekly/Monthly scheduling
- Email delivery to recipients
- Report templates
- Recipient management
- Execution history
- Failed job tracking

### 3. TIME TRACKING
- JavaScript heartbeat (20-30 second intervals)
- Log-based fallback tracking
- Session aggregation
- Daily summaries
- Engagement scoring
- At-risk detection

### 4. PREDICTIVE ANALYTICS
- At-risk learner detection
- Rule-based scoring
- Engagement thresholds
- Inactivity alerts
- Performance predictions
- Intervention recommendations

### 5. EXPORT FORMATS
- CSV (comma-separated)
- XLSX (Excel workbooks)
- PDF (formatted reports)
- Scheduled delivery
- Batch exports

### 6. AUDIT LOGGING
- All user actions logged
- Data access tracking
- Report generation history
- Configuration changes
- Security events
- Compliance reporting

---

## ManiReports: Unique Competitive Advantages

### 1. **IOMAD-First Design**
- ✅ Built specifically for IOMAD multi-tenant environments
- ✅ Company-level isolation and filtering
- ✅ Department-specific reporting
- ✅ Manager dashboards with company data only
- ✅ Automatic company filtering on all queries

**vs Competitors:**
- Edwiser: No IOMAD support
- LearnerScript: IOMAD support but not primary focus
- IntelliBoard: Limited IOMAD support

### 2. **Custom SQL Report Builder**
- ✅ Admin-only SQL query interface
- ✅ Whitelisted table access (security)
- ✅ Parameter binding (SQL injection prevention)
- ✅ Query validation and optimization
- ✅ Result pagination and export

**vs Competitors:**
- Edwiser: No custom reports
- LearnerScript: Has custom reports but less flexible
- IntelliBoard: No custom SQL reports

### 3. **Self-Hosted with No Vendor Lock-in**
- ✅ All data stays on your server
- ✅ No cloud dependencies
- ✅ No subscription fees
- ✅ Full control over data
- ✅ GDPR compliant (data stays local)

**vs Competitors:**
- Edwiser: Self-hosted ✅
- LearnerScript: Self-hosted ✅
- IntelliBoard: Cloud-based ❌ (data leaves server)

### 4. **Advanced Time Tracking**
- ✅ JavaScript heartbeat (real-time)
- ✅ Log-based fallback (no JS required)
- ✅ Session aggregation
- ✅ Daily summaries
- ✅ Engagement scoring
- ✅ At-risk detection

**vs Competitors:**
- Edwiser: Basic time tracking
- LearnerScript: Good time tracking
- IntelliBoard: Advanced time tracking (but cloud-based)

### 5. **Predictive Analytics**
- ✅ At-risk learner detection
- ✅ Rule-based scoring system
- ✅ Engagement thresholds
- ✅ Inactivity alerts
- ✅ Performance predictions
- ✅ Intervention recommendations

**vs Competitors:**
- Edwiser: No predictive analytics
- LearnerScript: No predictive analytics
- IntelliBoard: Has predictive analytics (but cloud-based)

---

## ManiReports: Feature Roadmap

### Phase 1: MVP (COMPLETE)
- ✅ Role-based dashboards (Admin, Manager, Teacher, Student)
- ✅ IOMAD multi-tenant support
- ✅ Prebuilt reports (Course Completion, Progress, SCORM, Engagement)
- ✅ Time tracking (heartbeat + logs)
- ✅ Scheduled reports
- ✅ Custom SQL report builder
- ✅ Export formats (CSV, XLSX, PDF)
- ✅ Audit logging

### Phase 2: Advanced Features (IN PROGRESS)
- ✅ Dashboard builder (widgets)
- ✅ Caching and pre-aggregation
- ✅ Report run logs
- ✅ At-risk learner detection
- ⏳ Drill-down dashboards
- ⏳ Advanced filtering
- ⏳ Mobile-responsive dashboards

### Phase 3: Enterprise Features (PLANNED)
- ⏳ xAPI integration
- ⏳ LTI integration
- ⏳ API endpoints (JSON)
- ⏳ Custom branding
- ⏳ Role-based report access
- ⏳ Advanced predictive models
- ⏳ Machine learning insights

---

## Market Positioning

### Target Market
- **Primary**: IOMAD installations (multi-tenant Moodle)
- **Secondary**: Large single-tenant Moodle sites
- **Tertiary**: Organizations needing self-hosted analytics

### Pricing Strategy
- **Free/Open Source** (self-hosted)
- **Optional Premium Support** (consulting, customization)
- **No subscription fees**
- **No cloud costs**

### Competitive Advantages Summary
1. **IOMAD-native** (best multi-tenant support)
2. **Self-hosted** (no vendor lock-in, GDPR compliant)
3. **Custom SQL reports** (flexibility)
4. **Advanced time tracking** (engagement measurement)
5. **Predictive analytics** (at-risk detection)
6. **Low cost** (free + optional support)
7. **No external dependencies** (local data storage)

---

## Conclusion

ManiReports combines the best features of all three competitors while maintaining:
- **Self-hosted architecture** (like Edwiser & LearnerScript)
- **IOMAD support** (like LearnerScript, better than IntelliBoard)
- **Custom reports** (like LearnerScript, better than Edwiser)
- **Real-time analytics** (like IntelliBoard, but self-hosted)
- **Predictive analytics** (like IntelliBoard, but self-hosted)
- **Low cost** (better than all competitors)
- **No vendor lock-in** (better than IntelliBoard)

**ManiReports is positioned as the best choice for organizations that need:**
- IOMAD multi-tenant support
- Self-hosted analytics
- Custom reporting capabilities
- Advanced engagement tracking
- Predictive insights
- Cost-effective solution

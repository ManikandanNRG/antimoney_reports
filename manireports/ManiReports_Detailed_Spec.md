
# ManiReports – Full Detailed Specification (AI‑Ready)

This document is a **complete, engineering‑grade specification** for building the `local_manireports` plugin for Moodle/IOMAD.  
It is designed to be immediately usable by AI coding agents (Cursor, Windsurf, Kiro, Bolt, etc.) to **generate full plugin code**.

---

# 1. Project Overview

## 1.1 Purpose
ManiReports is a **self-hosted advanced analytics plugin** for Moodle/IOMAD, combining the best features of:
- **IntelliBoard** (modern dashboards, engagement analytics)
- **LearnerScript** (custom reports, multi-tenant IOMAD support)
- **Edwiser Reports** (simple installation & native Moodle UX)

The plugin provides:
- Real-time dashboards  
- Role-based analytics (Admin, Manager, Teacher, Student)  
- SCORM, Quiz, Activity, Course, User analytics  
- Multi-company reporting (IOMAD)  
- Custom SQL + GUI report builder  
- Predictive analytics (basic rules)  
- Scheduled email reports  
- Engagement & time tracking  

The plugin **runs entirely inside Moodle**.  
**No external cloud is used.**

---

# 2. Target Platforms

### 2.1 Moodle Versions
- Moodle 4.0 – 4.4 LTS  
- IOMAD 4.0 – 4.4  

### 2.2 PHP Compatibility
- PHP 7.4 – 8.2

### 2.3 Database
- MariaDB / MySQL  
- PostgreSQL (optional support)

---

# 3. Plugin Architecture

### 3.1 Location
```
local/manireports/
```

### 3.2 Directory Structure
```
local/manireports/
│
├── classes/
│   ├── api/
│   │   ├── reportbuilder.php
│   │   ├── analytics.php
│   │   ├── timeengine.php
│   │   ├── scheduler.php
│   │   └── iomadfilters.php
│   │
│   ├── charts/
│   │   ├── basechart.php
│   │   ├── linechart.php
│   │   ├── barchart.php
│   │   ├── piechart.php
│   │   └── timespentchart.php
│   │
│   ├── output/
│   │   ├── dashboard_renderer.php
│   │   ├── tables/
│   │   └── widgets/
│   │
│   ├── tasks/
│       ├── cron_cache_builder.php
│       ├── cron_time_tracking.php
│       ├── cron_report_scheduler.php
│       └── cron_scorm_summary.php
│
├── db/
│   ├── install.xml
│   ├── access.php
│   ├── events.php
│   ├── tasks.php
│   └── upgrades.php
│
├── amd/
│   ├── src/
│   │   ├── dashboard.js
│   │   ├── filters.js
│   │   ├── charts.js
│   │   ├── timeheartbeat.js
│   │   └── ajax.js
│   └── build/ (auto-generated)
│
├── templates/
│   ├── dashboard.mustache
│   ├── widgets/
│   ├── charts/
│   └── tables/
│
├── ui/
│   ├── admin_dashboard.php
│   ├── manager_dashboard.php
│   ├── teacher_dashboard.php
│   ├── student_dashboard.php
│   └── report_builder.php
│
├── reports/
│   ├── course_reports.php
│   ├── user_reports.php
│   ├── scorm_reports.php
│   ├── enrollment_reports.php
│   └── engagement_reports.php
│
├── version.php
├── settings.php
└── index.php
```

---

# 4. Database Schema

## 4.1 Time Tracking Table
```
manireports_usertime
--------------------------------
id
userid
courseid
duration_seconds
date (Y-m-d)
lastupdated
```

## 4.2 Custom Reports Table
```
manireports_customreports
--------------------------------
id
name
description
type (sql/gui)
sqlquery
configjson
createdby
timecreated
timemodified
```

## 4.3 Scheduled Reports Table
```
manireports_schedules
--------------------------------
id
reportid
userid
frequency (daily/weekly/monthly)
format (csv/pdf/xlsx)
next_run
active
```

## 4.4 Cached Summary Table
```
manireports_cache_summary
--------------------------------
id
reporttype
referenceid
summaryjson
lastgenerated
```

---

# 5. Major Modules

# 5.1 Dashboard System

## Features:
- AJAX loading of charts
- Responsive layout
- Drag & drop widgets
- Global filters:
  - Company (IOMAD)
  - Course
  - User
  - Date range

## Widgets:
- Total users
- Active users today
- Total enrolments
- Course completion %
- SCORM completion graph
- Time spent overview
- Most accessed courses
- Engagement score
- At-risk learners

---

# 5.2 Role-Based Dashboards

## Admin Dashboard
- All-site statistics
- All companies (IOMAD)
- Course usage heatmaps
- Inactive users
- Course popularity

## Company Manager Dashboard (IOMAD)
- Only their company users
- Department reports
- Completion, progress, enrolment
- SCORM performance

## Teacher Dashboard
- Student progress
- Activity completion
- Quiz analytics
- Time spent per user

## Student Dashboard
- Personal progress
- Time tracking
- Course completion
- Activity deadlines

---

# 5.3 Report Types

### 1. Course Reports
- Enrolment trend
- Completion trend
- Attempts & progress
- Activity usage stats

### 2. User Reports
- Engagement score
- Time spent
- Progress tracking
- At-risk detection

### 3. SCORM Reports
- Attempts
- Suspended data
- Raw tracking entries
- Time spent
- Pass/fail summary

### 4. Quiz Reports
- Attempts summary
- Marks distribution
- Time-per-question

### 5. Enrollment Reports
- By course
- By company
- By department

---

# 5.4 Custom Report Builder

### Two modes:
- SQL mode (full control)
- GUI mode (drag-drop)

### GUI components:
- Select tables
- Select columns
- Add filters
- Choose chart type
- Save template

### Output formats:
- Table
- Line chart
- Bar chart
- Pie chart
- KPI card

---

# 5.5 Time Tracking Engine

### Modes:
1. Heartbeat JS (every 20–30 sec)
2. Log-based fallback

### Data points:
- Time spent per day
- Time per course
- Active session map

---

# 5.6 Predictive Analytics (Logic-Based)

Rules:
```
IF time_spent < threshold AND due_date_near THEN at_risk
IF no login for X days THEN inactive
IF low activity + low completion THEN risk_score++
```

Outputs:
- At-risk learners dashboard
- Risk scoring (0–100)
- Email notifications (optional)

---

# 5.7 Report Scheduling

Users can:
- Select report
- Select frequency
- Select recipients
- Select format (PDF/CSV/Excel)

Cron tasks:
- Check schedules
- Generate data
- Send email with attachments

---

# 5.8 UI/UX Guidelines

- Bootstrap 4/5
- AJAX everywhere
- No full-page reloads
- Mustache templates for frontend
- Loading spinners & skeleton loaders

Charts:
- Chart.js or ApexCharts

---

# 6. Cron Tasks

### 1. Time Tracking Cron
Updates user engagement summary.

### 2. Cache Builder Cron
Pre-aggregates heavy reports.

### 3. Report Scheduler Cron
Emails scheduled reports.

### 4. SCORM Summary Cron
Builds fast SCORM summaries.

---

# 7. Permissions/Capabilities

```
local/manireports:viewadmindashboard
local/manireports:viewmanagerdashboard
local/manireports:viewteacherdashboard
local/manireports:viewstudentdashboard
local/manireports:managereports
local/manireports:schedule
local/manireports:customreports
```

Mapped to:
- Admin
- Manager
- Editing teacher
- Student

---

# 8. Settings Page (settings.php)

### Options:
- Enable/Disable time tracking
- Heartbeat interval
- Cache duration
- SCORM summary enable/disable
- Max SQL execution time
- Allowed roles for report creation

---

# 9. Future Extensions

### Planned enhancements:
- xAPI event tracking
- Mobile app dashboards (via WS)
- Integration with PowerBI/Tableau via API
- ML-based predictive analytics
- Export full dashboards as image/PDF

---

# 10. Deliverables for AI Agent

### AI should generate:
- Full plugin folder structure
- All PHP classes
- All JS files (AMD modules)
- Templates
- Cron tasks
- Install XML DB
- Renderer & output classes
- Full dashboards
- Sample reports
- Settings page
- Permissions

---

# END OF SPEC

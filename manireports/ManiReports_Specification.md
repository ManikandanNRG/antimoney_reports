
# ManiReports Plugin Specification

## 1. Overview
ManiReports is a self-hosted local Moodle/IOMAD reporting plugin combining the best features of Edwiser Reports, LearnerScript, and IntelliBoard into a modern, powerful analytics engine with no cloud dependency.

---

## 2. Core Purpose
Provide:
- Advanced dashboards
- Course & SCORM analytics
- User engagement tracking
- Multi-tenant IOMAD reports
- Custom report builder
- Real-time insights
- Scheduler for email reports

---

## 3. Key Features

### 3.1 Unified Dashboard System
- Chart-based dashboards
- KPIs and widgets
- Filters (company, course, user, date)
- Widgets: Enrolments, Completion, Active Users, SCORM stats, At-risk learners

### 3.2 Role-Based Dashboards
- Admin Dashboard
- Company Manager Dashboard
- Teacher Dashboard
- Student Dashboard

### 3.3 Advanced Report Types
- Course Analytics
- Activity/SCORM Analytics
- User Analytics
- Company/Department Analytics

### 3.4 Custom Report Builder
- SQL & GUI modes
- Filters
- Export to CSV, Excel, PDF
- JSON config per report

### 3.5 Time Tracking Engine
- Log-based & JS heartbeat
- Time spent
- Heatmaps
- Engagement scoring

### 3.6 SCORM Analytics
- Attempts
- Time spent
- Attempt history
- Completion & interaction tracking

### 3.7 Predictive Analytics
- At-risk logic
- Completion prediction
- Engagement alerts

### 3.8 Report Scheduling
- Email reports
- Daily/Weekly/Monthly

### 3.9 UI/UX
- Bootstrap
- AJAX filters
- Fast charts
- Drag & drop panels

---

## 4. Architecture

### Folder Structure
```
local/manireports/
    classes/
    db/
    lang/
    templates/
    amd/
        src/
        build/
    reports/
    ui/
    version.php
    settings.php
    index.php
```

### Database
- manireports_customreports
- manireports_schedules
- manireports_usertime
- manireports_cache_summary

---

## 5. Future-Proof
- xAPI integration
- JSON API
- Export dashboards
- PowerBI/Tableau-ready

---

## 6. Positioning
- As powerful as LearnerScript
- As modern as IntelliBoard
- As simple as Edwiser
- Fully self-hosted & IOMAD-ready

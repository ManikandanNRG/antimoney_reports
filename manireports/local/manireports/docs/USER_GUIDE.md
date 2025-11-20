# ManiReports User Guide

## Table of Contents

1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Dashboards](#dashboards)
4. [Reports](#reports)
5. [Scheduled Reports](#scheduled-reports)
6. [Exporting Data](#exporting-data)
7. [FAQ](#faq)

## Introduction

ManiReports is a comprehensive analytics and reporting plugin for Moodle that provides role-based dashboards, custom reports, and automated report delivery.

### Key Features

- **Role-Based Dashboards**: Different views for administrators, managers, teachers, and students
- **Custom Reports**: Create reports using SQL or visual builder
- **Scheduled Reports**: Automate report generation and email delivery
- **Time Tracking**: Monitor user engagement and time spent
- **Export Options**: CSV, Excel, and PDF formats
- **Multi-Tenant Support**: IOMAD company isolation

## Getting Started

### Accessing ManiReports

1. Log in to your Moodle site
2. Navigate to **Site Administration → Plugins → Local plugins → ManiReports**
3. Click on **Dashboard** to view your personalized dashboard

### Dashboard Overview

Your dashboard displays different information based on your role:

- **Students**: Personal progress, time spent, course completion
- **Teachers**: Student progress, activity completion, quiz analytics
- **Managers**: Company-specific statistics, department reports
- **Administrators**: Site-wide statistics, all companies

## Dashboards

### Admin Dashboard

**Access**: Site Administration → Plugins → ManiReports → Dashboard

**Features**:
- Site-wide enrollment and completion statistics
- Course usage heatmaps
- Inactive user tracking
- Company comparisons (IOMAD)
- System health monitoring

**Widgets**:
- Total Users
- Active Courses
- Completion Rate
- Average Time Spent
- Recent Activity

### Manager Dashboard

**Access**: Site Administration → Plugins → ManiReports → Dashboard

**Features**:
- Company-specific data (IOMAD)
- Department reports
- Team progress tracking
- Completion trends

**Filters**:
- Date range (7/30/90 days)
- Department
- Course category

### Teacher Dashboard

**Access**: Site Administration → Plugins → ManiReports → Dashboard

**Features**:
- Student progress in your courses
- Activity completion rates
- Quiz performance
- Time spent per student
- At-risk learner alerts

**Actions**:
- View detailed student reports
- Export class data
- Contact at-risk learners

### Student Dashboard

**Access**: Site Administration → Plugins → ManiReports → Dashboard

**Features**:
- Personal progress overview
- Time tracking statistics
- Course completion status
- Upcoming deadlines
- Achievement badges

## Reports

### Prebuilt Reports

#### Course Completion Report

Shows completion status across courses.

**Columns**:
- Course Name
- Enrolled Users
- Completed Users
- Completion %
- Trend

**Filters**:
- Date range
- Company (IOMAD)
- Course category

#### Course Progress Report

Displays per-user completion percentages.

**Columns**:
- User Name
- Course Name
- Progress %
- Last Activity
- Estimated Completion

#### User Engagement Report

Tracks user activity and engagement.

**Columns**:
- User Name
- Active Days (7/30)
- Time Spent
- Login Count
- Engagement Score

#### SCORM Summary Report

Aggregates SCORM activity data.

**Columns**:
- SCORM Activity
- Attempts
- Completion Rate
- Average Score
- Average Time

#### Quiz Attempts Report

Summarizes quiz performance.

**Columns**:
- Quiz Name
- Attempts
- Average Score
- Pass Rate
- Completion Time

### Custom Reports

#### Creating a Custom Report

1. Go to **Custom Reports**
2. Click **Create Custom Report**
3. Enter report name and description
4. Choose report type:
   - **SQL Report**: Write custom SQL query
   - **GUI Report**: Use visual builder

#### SQL Reports

**Example**:
```sql
SELECT u.firstname, u.lastname, c.fullname, cc.timecompleted
FROM {user} u
JOIN {course_completions} cc ON cc.userid = u.id
JOIN {course} c ON c.id = cc.course
WHERE cc.timecompleted > :startdate
ORDER BY cc.timecompleted DESC
```

**Tips**:
- Use `{tablename}` notation for tables
- Use `:paramname` for parameters
- Only SELECT queries allowed
- Whitelist tables only

#### GUI Reports

1. Select tables to query
2. Choose columns to display
3. Add filters
4. Set grouping and sorting
5. Preview and save

### Running Reports

1. Navigate to **Reports**
2. Select a report
3. Set filter values
4. Click **Run Report**
5. View results in table format

## Scheduled Reports

### Creating a Schedule

1. Go to **Scheduled Reports**
2. Click **Create Schedule**
3. Configure:
   - Report to run
   - Frequency (daily/weekly/monthly)
   - Time to run
   - Recipients (email addresses)
   - Export format (CSV/XLSX/PDF)
4. Save schedule

### Managing Schedules

**View Schedules**:
- Active schedules list
- Next run time
- Last run status

**Actions**:
- Edit schedule
- Disable/Enable
- Delete schedule
- Run now (manual trigger)

### Schedule History

View execution history:
- Run date/time
- Status (success/failed)
- Duration
- File size
- Download link

## Exporting Data

### Export Formats

**CSV**:
- Plain text format
- Opens in Excel/Sheets
- Best for data analysis

**XLSX**:
- Excel format
- Formatted headers
- Auto-sized columns

**PDF**:
- Printable format
- Includes charts
- Professional appearance

### Exporting a Report

1. Run the report
2. Click **Export** button
3. Select format
4. Download file

## FAQ

### How do I access my dashboard?

Navigate to Site Administration → Plugins → ManiReports → Dashboard

### Can I create custom reports?

Yes, if you have the `local/manireports:customreports` capability.

### How often is data updated?

- Dashboards: Cached for 1 hour
- Reports: Real-time (unless cached)
- Time tracking: Aggregated hourly

### What tables can I query in custom reports?

Only whitelisted tables including:
- user, course, course_completions
- grade_grades, quiz_attempts
- scorm, assign, forum
- See full list in report builder

### How do I schedule a report?

Go to Scheduled Reports → Create Schedule, configure frequency and recipients.

### Can I export reports automatically?

Yes, use scheduled reports with email delivery.

### How do I track time spent?

Time tracking is automatic via JavaScript heartbeat (if enabled).

### What is an at-risk learner?

A learner flagged based on:
- Low time spent
- No recent login
- Low completion rate

### How do I filter by company (IOMAD)?

Company filters are automatic based on your role. Managers see only their company data.

### Can I customize dashboards?

Yes, use the Dashboard Builder (if enabled) to create custom layouts.

## Support

For additional help:
- Check the Administrator Guide
- Review the Troubleshooting Guide
- Contact your system administrator

---

**Version**: 1.0  
**Last Updated**: 2024

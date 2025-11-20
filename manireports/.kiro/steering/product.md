# Product Overview

ManiReports is a self-hosted advanced analytics and reporting plugin for Moodle/IOMAD that combines enterprise-grade analytics capabilities with modern UI/UX and simple deployment.

## Core Purpose

Provide comprehensive analytics without external cloud dependencies, targeting Moodle 4.0–4.4 LTS and IOMAD 4.0–4.4 installations.

## Key Features

- **Role-Based Dashboards**: Admin, Company Manager, Teacher, and Student views with appropriate data access
- **Multi-Tenant Support**: Full IOMAD company isolation and filtering
- **Prebuilt Reports**: Course completion, user progress, SCORM analytics, engagement metrics, quiz attempts
- **Custom Report Builder**: SQL and GUI modes for creating custom reports
- **Time Tracking**: JavaScript heartbeat and log-based fallback for accurate engagement measurement
- **Scheduled Reports**: Automated report generation and email delivery (daily/weekly/monthly)
- **Export Formats**: CSV, XLSX, and PDF export capabilities
- **Predictive Analytics**: Rule-based at-risk learner detection
- **SCORM Deep Analytics**: Detailed tracking of SCORM activities and interactions

## Target Users

- **Administrators**: Site-wide analytics and system management
- **Company Managers**: Company-specific reporting in IOMAD environments
- **Teachers**: Student progress and course analytics
- **Students**: Personal progress tracking and time spent metrics

## Design Philosophy

- Self-hosted with no external cloud services
- Native Moodle integration following platform standards
- Performance-optimized with pre-aggregation and caching
- Security-first with role-based access control and SQL validation
- Privacy-compliant with GDPR support and configurable data retention

## Feature Boundaries (Strict Rules)

ManiReports must implement only the approved features:

### MVP Features
- Role-based dashboards (Admin, Manager, Teacher, Student)
- Course completion dashboard
- IOMAD multi-tenant filtering
- Prebuilt reports (Course Completion, Course Progress, SCORM Summary, User Engagement)
- Scheduled email reports
- Custom SQL report builder (admin only)
- Chart.js + Mustache + AJAX UI

### Advanced Features (Phase 2)
- Dashboard builder (widgets)
- Time tracking (heartbeat + logs)
- SCORM deep analytics
- Caching and pre-aggregation
- Report run logs & audit logs
- Export formats (CSV/XLSX/PDF)
- RBAC capabilities

### Optional Features (Phase 3 – only if explicitly approved)
- GUI-based report builder
- At-risk learner detection
- Drill-down dashboards
- JSON API endpoints
- xAPI integration

**The AI must not add any new features unless the user explicitly approves.**

## Platform Restrictions

- No external cloud or SaaS systems
- All analytics must run locally inside Moodle
- All storage must use Moodle DB & File API
- No non-Moodle frameworks (Laravel, Symfony, React, Vue)

## Agent Behavior Rules

- Do not rename directories, classes, tables, or architecture
- Do not modify system design unless the user approves
- Ask for clarification when requirements are unclear
- Never hallucinate new features or components
- Follow the documented scope strictly

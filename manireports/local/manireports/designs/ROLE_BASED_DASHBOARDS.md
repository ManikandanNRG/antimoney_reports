# Role-Based Dashboard Architecture

## Overview

ManiReports provides role-specific dashboards tailored to each user type's needs. Each role sees only relevant data and metrics based on their permissions and responsibilities.

---

## 1. ADMIN DASHBOARD

### Purpose
System-wide analytics, configuration management, and platform health monitoring.

### Key Metrics & Data
- **System Overview**: Total users, courses, enrollments, completions
- **Platform Health**: Server uptime, database performance, cron job status
- **User Management**: Active users, new registrations, user growth trends
- **Course Analytics**: Total courses, active courses, course completion rates
- **Engagement Metrics**: Overall engagement score, time spent, activity trends
- **Financial**: Revenue (if applicable), subscription status
- **Audit & Compliance**: Audit logs, security events, data access logs
- **System Configuration**: Settings, plugin status, scheduled tasks

### Pages
1. **Dashboard** - System overview with KPIs and charts
2. **Reports** - Pre-built reports (Course Completion, User Engagement, SCORM Summary)
3. **Custom Reports** - SQL-based custom report builder
4. **Schedules** - Manage automated report delivery
5. **Audit Log** - View all system activities
6. **Settings** - Configure plugin options

### ASCII Diagram
```
┌─────────────────────────────────────────────────────────────┐
│                    ADMIN DASHBOARD                          │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Total Users  │  │ Total Courses│  │ Completions │      │
│  │   12,450     │  │     248      │  │    8,956     │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  System Health & Performance                        │   │
│  │  ├─ Server Uptime: 99.98%                          │   │
│  │  ├─ Database: Healthy                              │   │
│  │  ├─ Cron Jobs: All Running                         │   │
│  │  └─ Cache: Optimized                               │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌──────────────────────┐  ┌──────────────────────┐        │
│  │ User Growth Trend    │  │ Course Completion    │        │
│  │ [Line Chart]         │  │ [Bar Chart]          │        │
│  └──────────────────────┘  └──────────────────────┘        │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Recent Audit Events                                 │   │
│  │ ├─ User Login: admin@example.com (2 min ago)      │   │
│  │ ├─ Report Generated: Course Completion (1 hr ago) │   │
│  │ ├─ Settings Changed: Email Config (3 hrs ago)     │   │
│  │ └─ Database Backup: Completed (1 day ago)         │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. MANAGER DASHBOARD (IOMAD Company Manager)

### Purpose
Company/department-level analytics and team performance monitoring.

### Key Metrics & Data
- **Company Overview**: Company name, total employees, active courses
- **Team Performance**: Department-wise engagement, completion rates
- **Employee Analytics**: Active employees, training progress, at-risk employees
- **Course Management**: Courses assigned, enrollment status, completion tracking
- **Engagement Metrics**: Team engagement score, time spent, activity trends
- **Reports**: Department-specific reports, team performance reports
- **Compliance**: Training completion for compliance courses

### Pages
1. **Dashboard** - Company overview with team metrics
2. **Team Reports** - Department and employee performance
3. **Course Management** - Manage company courses
4. **Employee Progress** - Track individual employee progress
5. **Compliance Reports** - Training compliance tracking
6. **Schedules** - Manage team report delivery

### ASCII Diagram
```
┌─────────────────────────────────────────────────────────────┐
│                  MANAGER DASHBOARD                          │
│                  (Company: Acme Corp)                       │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Team Members │  │ Active Courses│ │ Avg Completion│     │
│  │     245      │  │      18       │  │    72.5%     │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Department Performance                              │   │
│  │ ├─ Engineering: 85% completion, 4.2h avg time     │   │
│  │ ├─ Sales: 68% completion, 3.1h avg time           │   │
│  │ ├─ HR: 92% completion, 5.1h avg time              │   │
│  │ └─ Operations: 76% completion, 3.8h avg time      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌──────────────────────┐  ┌──────────────────────┐        │
│  │ Team Engagement      │  │ Course Completion    │        │
│  │ [Line Chart]         │  │ [Pie Chart]          │        │
│  └──────────────────────┘  └──────────────────────┘        │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ At-Risk Employees (Need Attention)                  │   │
│  │ ├─ John Smith: No activity for 7 days              │   │
│  │ ├─ Sarah Johnson: 15% course completion            │   │
│  │ └─ Mike Chen: Missed deadline for compliance       │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. TEACHER DASHBOARD

### Purpose
Course-level analytics and student progress monitoring.

### Key Metrics & Data
- **Course Overview**: Course name, total students, enrollment status
- **Student Progress**: Individual student progress, completion status
- **Engagement**: Student engagement scores, time spent, activity frequency
- **Performance**: Quiz scores, assignment grades, overall performance
- **At-Risk Students**: Students falling behind, low engagement
- **Course Analytics**: Course completion rate, average time spent
- **Assignments**: Pending submissions, grading status
- **Communication**: Student messages, announcements

### Pages
1. **Dashboard** - Course overview with student metrics
2. **Student Progress** - Individual student tracking
3. **Course Reports** - Course-specific analytics
4. **Assignments** - Manage and grade assignments
5. **At-Risk Students** - Identify struggling students
6. **Communication** - Messages and announcements

### ASCII Diagram
```
┌─────────────────────────────────────────────────────────────┐
│                   TEACHER DASHBOARD                         │
│              Course: Advanced Analytics 101                 │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Total Students│ │ Avg Completion│ │ Avg Grade    │      │
│  │     245      │  │    68.5%      │  │    78.3%     │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Student Progress Overview                           │   │
│  │ ├─ Completed: 168 students (68.6%)                 │   │
│  │ ├─ In Progress: 62 students (25.3%)                │   │
│  │ ├─ Not Started: 15 students (6.1%)                 │   │
│  │ └─ At-Risk: 12 students (4.9%)                     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌──────────────────────┐  ┌──────────────────────┐        │
│  │ Student Engagement   │  │ Performance Dist.    │        │
│  │ [Line Chart]         │  │ [Bar Chart]          │        │
│  └──────────────────────┘  └──────────────────────┘        │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Recent Student Activity                             │   │
│  │ ├─ Emma Davis: Submitted Assignment 3 (2 hrs ago) │   │
│  │ ├─ John Smith: Completed Quiz 5 (4 hrs ago)       │   │
│  │ ├─ Lisa Brown: Posted in Discussion (1 day ago)   │   │
│  │ └─ Tom Garcia: Viewed Lecture 8 (2 days ago)      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Pending Submissions (5)                             │   │
│  │ ├─ Assignment 4: 8 pending submissions             │   │
│  │ ├─ Quiz 6: 3 pending submissions                   │   │
│  │ └─ Project: 2 pending submissions                  │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 4. STUDENT DASHBOARD

### Purpose
Personal learning progress and performance tracking.

### Key Metrics & Data
- **My Courses**: Enrolled courses, progress, completion status
- **Course Progress**: Current course progress, time spent, completion percentage
- **Grades**: Quiz scores, assignment grades, overall course grade
- **Time Spent**: Total time spent, time per course, engagement level
- **Achievements**: Badges earned, milestones reached, certificates
- **Upcoming**: Upcoming deadlines, upcoming assignments
- **Performance**: Performance trends, grade trends
- **Recommendations**: Suggested courses, learning paths

### Pages
1. **Dashboard** - Personal overview with progress
2. **My Courses** - List of enrolled courses
3. **Course Progress** - Detailed progress in current course
4. **Grades** - View all grades and scores
5. **Time Tracking** - View time spent on courses
6. **Achievements** - View badges and certificates

### ASCII Diagram
```
┌─────────────────────────────────────────────────────────────┐
│                   STUDENT DASHBOARD                         │
│                  Welcome, John Smith!                       │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Courses      │  │ Avg Grade    │  │ Time Spent   │      │
│  │ Enrolled: 4  │  │    82.5%     │  │  45.2 hours  │      │
│  │ Completed: 1 │  │              │  │              │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ My Courses                                          │   │
│  │ ┌─────────────────────────────────────────────────┐ │   │
│  │ │ Advanced Analytics 101                          │ │   │
│  │ │ Progress: ████████░░ 85% | Grade: A (92%)     │ │   │
│  │ │ Time Spent: 12.5 hours | Due: 5 days         │ │   │
│  │ └─────────────────────────────────────────────────┘ │   │
│  │ ┌─────────────────────────────────────────────────┐ │   │
│  │ │ Data Science 101                                │ │   │
│  │ │ Progress: ██████░░░░ 65% | Grade: B+ (87%)    │ │   │
│  │ │ Time Spent: 8.3 hours | Due: 12 days         │ │   │
│  │ └─────────────────────────────────────────────────┘ │   │
│  │ ┌─────────────────────────────────────────────────┐ │   │
│  │ │ Python Basics                                   │ │   │
│  │ │ Progress: ████░░░░░░ 45% | Grade: B (78%)     │ │   │
│  │ │ Time Spent: 6.1 hours | Due: 20 days         │ │   │
│  │ └─────────────────────────────────────────────────┘ │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌──────────────────────┐  ┌──────────────────────┐        │
│  │ Grade Trends         │  │ Time Spent Trends    │        │
│  │ [Line Chart]         │  │ [Area Chart]         │        │
│  └──────────────────────┘  └──────────────────────┘        │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Upcoming Deadlines                                  │   │
│  │ ├─ Assignment 5 (Advanced Analytics): Due in 2 days│   │
│  │ ├─ Quiz 4 (Data Science): Due in 5 days            │   │
│  │ └─ Project (Python): Due in 10 days                │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Achievements                                        │   │
│  │ ├─ 🏆 Course Completed: Advanced Analytics         │   │
│  │ ├─ ⭐ Perfect Score: Quiz 3                         │   │
│  │ └─ 🎖️ Consistent Learner: 7 days streak           │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Data Access & Filtering

### Admin
- ✅ Access to all data across the system
- ✅ No filtering applied
- ✅ Can view any user's data
- ✅ Can view all courses and reports

### Manager (IOMAD)
- ✅ Access to company/department data only
- ✅ Automatic company filtering applied
- ✅ Can view employees in their company
- ✅ Can view courses assigned to company
- ❌ Cannot access other companies' data

### Teacher
- ✅ Access to their own courses only
- ✅ Automatic course filtering applied
- ✅ Can view students enrolled in their courses
- ✅ Can view course-specific data
- ❌ Cannot access other teachers' courses

### Student
- ✅ Access to their own data only
- ✅ Automatic user filtering applied
- ✅ Can view only their enrolled courses
- ✅ Can view only their grades and progress
- ❌ Cannot access other students' data

---

## Design Recommendations

### Admin Dashboard
- **Best Design**: V1 (Modern Professional) or V4 (Dark Professional)
- **Reason**: Professional appearance, comprehensive data display, system-wide overview

### Manager Dashboard
- **Best Design**: V3 (Data-Rich & Compact) or V5 (Modern Compact)
- **Reason**: Information-dense, table-based data, team performance metrics

### Teacher Dashboard
- **Best Design**: V2 (Colorful & Engaging) or V5 (Modern Compact)
- **Reason**: Student-focused, engaging visuals, clear progress indicators

### Student Dashboard
- **Best Design**: V2 (Colorful & Engaging)
- **Reason**: Motivating colors, personal progress focus, achievement recognition

---

## Implementation Checklist

- [ ] Create role-specific dashboard templates
- [ ] Implement capability checks for each role
- [ ] Add IOMAD company filtering for managers
- [ ] Add course filtering for teachers
- [ ] Add user filtering for students
- [ ] Create role-specific Mustache templates
- [ ] Implement role-based data access in API
- [ ] Test data visibility for each role
- [ ] Verify IOMAD isolation works correctly
- [ ] Document role permissions in access.php

---

## Security Considerations

1. **Capability Checks**: Every page must verify user has required capability
2. **Data Filtering**: IOMAD filters must be applied automatically
3. **SQL Safety**: All queries must use parameter binding
4. **Output Escaping**: All data must be properly escaped
5. **Audit Logging**: Log all data access for compliance
6. **Session Management**: Verify user session is valid
7. **CSRF Protection**: Use sesskey on all forms

---

## Performance Optimization

1. **Caching**: Cache role-specific data for 1 hour
2. **Pagination**: Paginate large result sets (100 items per page)
3. **Indexing**: Index frequently filtered columns
4. **Query Optimization**: Use JOINs instead of subqueries
5. **Lazy Loading**: Load charts and tables on demand
6. **Pre-aggregation**: Pre-compute heavy metrics via scheduled tasks

---

## Future Enhancements

- [ ] Customizable dashboard widgets
- [ ] Role-specific report templates
- [ ] Advanced filtering and search
- [ ] Export to PDF/Excel
- [ ] Email report delivery
- [ ] Mobile-responsive dashboards
- [ ] Dark mode toggle
- [ ] Accessibility improvements

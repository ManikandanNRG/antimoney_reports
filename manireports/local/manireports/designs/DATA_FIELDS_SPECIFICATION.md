# ManiReports Data Fields Specification

## Overview
This document specifies exactly which data fields will be displayed on each dashboard and report page to ensure ManiReports is competitive with Edwiser, LearnerScript, and IntelliBoard.

---

## 1. ADMIN DASHBOARD

### KPI Cards (Top Section)
- **New Registrations** - Count of new users this period (with % change)
- **Course Enrollments** - Total active enrollments (with % change)
- **Course Completions** - Total completions this period (with % change)
- **Active Users** - Currently active users (with % change)

### Site Overview Status Widget
- **Total Active Users** - Count of users with activity in last 30 days
- **Total Course Enrollments** - Total active enrollments across site
- **Total Course Completions** - Total completions across site
- **Last Updated** - Timestamp of last data refresh

### Course Completion Trend Chart (Line Chart - 6 months)
- **X-Axis:** Month (Aug, Sep, Oct, Nov, Dec, Jan)
- **Y-Axis:** Count
- **Lines:**
  - Completions (gold)
  - Enrollments (green)
  - Shows correlation between enrollment and completion

### Engagement by Course Chart (Bar Chart)
- **X-Axis:** Course names (top 10 courses)
- **Y-Axis:** Engagement score (0-100%)
- **Data:** Average engagement per course
- **Color-coded:** Green (high), Yellow (medium), Red (low)

### Top Courses by Completion Table
| Column | Data |
|--------|------|
| Course Name | Full course name |
| Enrollments | Total enrolled users |
| Completions | Users who completed |
| Completion % | (Completions / Enrollments) * 100 |
| Avg Time Spent | Average hours per user |
| Last Activity | Date of last user activity |

### User Activity Heatmap (Calendar View)
- **X-Axis:** Days of week (Mon-Sun)
- **Y-Axis:** Hours of day (00:00-23:00)
- **Color intensity:** Activity level (light = low, dark = high)
- **Shows:** When users are most active

### System Health Metrics
- **Database Size** - Current database size in MB
- **Cache Hit Rate** - % of queries served from cache
- **Avg Query Time** - Average database query time in ms
- **Scheduled Tasks Status** - Last run time of each task
- **Error Rate** - % of failed operations

---

## 2. MANAGER DASHBOARD (Company-Specific)

### KPI Cards (Top Section)
- **Company Users** - Total users in this company
- **Course Completions** - Completions in this company (with % change)
- **Avg Engagement** - Average engagement score (0-100%)
- **At-Risk Learners** - Count of flagged at-risk users

### Company Overview Widget
- **Total Users** - Count of company users
- **Active Users** - Users active in last 30 days
- **Total Enrollments** - Active enrollments in company
- **Total Completions** - Completions in company

### Company Course Completion Trend (Line Chart)
- **X-Axis:** Month (6 months)
- **Y-Axis:** Count
- **Lines:**
  - Company completions (gold)
  - Company enrollments (green)

### Department Performance Chart (Bar Chart)
- **X-Axis:** Department names
- **Y-Axis:** Completion rate (%)
- **Shows:** Which departments are performing best

### Top Performers Table
| Column | Data |
|--------|------|
| User Name | Full name |
| Courses Completed | Count of completed courses |
| Avg Score | Average quiz/assignment score |
| Time Spent | Total hours in platform |
| Last Activity | Date of last login |
| Engagement Score | 0-100% |

### At-Risk Learners Alert Widget
- **Count** - Number of at-risk learners
- **Threshold** - Risk score threshold (e.g., < 50)
- **Criteria:**
  - No login in last 14 days
  - Time spent < 2 hours per week
  - Completion rate < 30%
- **Action:** Link to at-risk learners page

### Company Compliance Progress
| Item | Progress | Status |
|------|----------|--------|
| Course Completion Rate | 75% | On Track |
| User Engagement | 68% | Warning |
| Training Deadline | 45 days | On Track |

---

## 3. TEACHER DASHBOARD

### KPI Cards (Top Section)
- **My Students** - Total students in my courses
- **Completions** - Students who completed my courses
- **Avg Engagement** - Average engagement in my courses
- **At-Risk in My Courses** - At-risk students in my courses

### My Courses Overview
- **Total Courses** - Number of courses I teach
- **Active Students** - Students currently enrolled
- **Avg Completion Rate** - Average completion across my courses

### Student Progress Table
| Column | Data |
|--------|------|
| Student Name | Full name |
| Courses Enrolled | Count of my courses student is in |
| Completion % | % of course completed |
| Time Spent | Hours spent in course |
| Last Activity | Date of last activity |
| Engagement Score | 0-100% |
| Status | On Track / At Risk / Completed |

### Course Activity Timeline (Line Chart)
- **X-Axis:** Date (last 30 days)
- **Y-Axis:** Activity count
- **Lines:**
  - Logins (blue)
  - Submissions (green)
  - Quiz attempts (orange)

### Quiz Performance Chart (Bar Chart)
- **X-Axis:** Quiz names
- **Y-Axis:** Average score (%)
- **Shows:** Which quizzes students struggle with

### Time Spent Distribution (Pie Chart)
- **Segments:** By course
- **Shows:** How students distribute time across courses

### Student Engagement Scores Table
| Column | Data |
|--------|------|
| Student Name | Full name |
| Engagement Score | 0-100% |
| Time Spent | Total hours |
| Logins | Count of logins |
| Submissions | Count of submissions |
| Quiz Attempts | Count of quiz attempts |
| Last Active | Date/time |

---

## 4. STUDENT DASHBOARD

### KPI Cards (Top Section)
- **My Courses** - Total courses enrolled in
- **Completed** - Courses completed
- **In Progress** - Courses currently taking
- **Time Spent** - Total hours in platform

### My Learning Progress
- **Overall Completion** - % of all courses completed
- **Overall Engagement** - Average engagement score
- **Certificates Earned** - Count of certificates

### My Course Progress (Progress Bars)
| Course Name | Progress | Status | Time Spent |
|-------------|----------|--------|-----------|
| Course 1 | 85% | On Track | 12.5h |
| Course 2 | 45% | In Progress | 8.2h |
| Course 3 | 100% | Completed | 15.3h |

### Learning Timeline (Line Chart - Last 30 days)
- **X-Axis:** Date
- **Y-Axis:** Activity count
- **Lines:**
  - Logins (blue)
  - Activities completed (green)
  - Time spent (orange)

### Time Spent by Course (Pie Chart)
- **Segments:** By course
- **Shows:** How much time in each course

### My Achievements/Certificates
- **Certificates Earned** - Count and list
- **Badges Earned** - Count and list
- **Milestones Reached** - List of achievements

### Upcoming Deadlines Alert Widget
| Assignment | Course | Due Date | Days Left |
|-----------|--------|----------|-----------|
| Quiz 1 | Course A | 2025-01-15 | 5 days |
| Project | Course B | 2025-01-20 | 10 days |

---

## 5. COURSE COMPLETION REPORT

### Report Filters (Top)
- Date Range (From / To)
- Course (Single or Multiple)
- Department (if applicable)
- Completion Status (All / Completed / Not Completed)

### Summary Statistics
- **Total Enrolled** - Count
- **Total Completed** - Count
- **Completion Rate** - %
- **Avg Time to Complete** - Hours

### Course Completion Trend Chart (Line Chart)
- **X-Axis:** Date
- **Y-Axis:** Cumulative completions
- **Shows:** Completion trend over time

### Course Completion Table
| Column | Data |
|--------|------|
| Course Name | Full course name |
| Shortname | Course code |
| Enrolled | Total enrolled users |
| Completed | Users who completed |
| Completion % | (Completed / Enrolled) * 100 |
| Avg Time | Average hours to complete |
| Earliest Completion | Date of first completion |
| Latest Completion | Date of most recent completion |

### Export Options
- CSV, XLSX, PDF
- Scheduled delivery (daily/weekly/monthly)

---

## 6. COURSE PROGRESS REPORT

### Report Filters (Top)
- Date Range (From / To)
- Course (Single or Multiple)
- User/Student (Optional)
- Progress Range (0-25%, 25-50%, 50-75%, 75-100%)

### Summary Statistics
- **Total Enrolled** - Count
- **Avg Progress** - %
- **On Track** - Count (> 50% progress)
- **At Risk** - Count (< 50% progress)

### Progress Distribution Chart (Bar Chart)
- **X-Axis:** Progress ranges (0-25%, 25-50%, 50-75%, 75-100%)
- **Y-Axis:** Number of students
- **Shows:** How many students in each progress bracket

### Student Progress Table
| Column | Data |
|--------|------|
| Student Name | Full name |
| Course Name | Course enrolled in |
| Progress % | % of course completed |
| Activities Completed | Count of completed activities |
| Total Activities | Total activities in course |
| Time Spent | Hours spent |
| Last Activity | Date/time of last activity |
| Status | On Track / At Risk / Completed |

### Progress Timeline Chart (Line Chart)
- **X-Axis:** Date
- **Y-Axis:** Average progress %
- **Shows:** How progress changes over time

---

## 7. USER ENGAGEMENT REPORT

### Report Filters (Top)
- Date Range (From / To)
- Course (Optional)
- Department (Optional)
- Engagement Level (All / High / Medium / Low)

### Summary Statistics
- **Total Users** - Count
- **Avg Engagement Score** - 0-100%
- **Active Users** - Users with activity in period
- **Inactive Users** - No activity in period

### Engagement Score Distribution (Bar Chart)
- **X-Axis:** Engagement ranges (0-20%, 20-40%, 40-60%, 60-80%, 80-100%)
- **Y-Axis:** Number of users
- **Shows:** Distribution of engagement levels

### User Engagement Table
| Column | Data |
|--------|------|
| User Name | Full name |
| Engagement Score | 0-100% |
| Logins | Count in period |
| Time Spent | Total hours |
| Activities Completed | Count |
| Quiz Attempts | Count |
| Submissions | Count |
| Last Active | Date/time |
| Status | Active / Inactive |

### Engagement Trend Chart (Line Chart)
- **X-Axis:** Date
- **Y-Axis:** Average engagement score
- **Shows:** Engagement trend over time

### Activity Heatmap (Calendar View)
- **X-Axis:** Days of week
- **Y-Axis:** Hours of day
- **Color intensity:** Activity level

---

## 8. QUIZ ATTEMPTS REPORT

### Report Filters (Top)
- Date Range (From / To)
- Course (Optional)
- Quiz (Optional)
- User (Optional)

### Summary Statistics
- **Total Attempts** - Count
- **Avg Score** - %
- **Pass Rate** - % of attempts passed
- **Avg Time** - Average time per attempt

### Quiz Performance Chart (Bar Chart)
- **X-Axis:** Quiz names
- **Y-Axis:** Average score (%)
- **Shows:** Which quizzes are hardest

### Quiz Attempts Table
| Column | Data |
|--------|------|
| Quiz Name | Quiz title |
| User Name | Student name |
| Attempt # | Attempt number |
| Score | Points earned |
| Max Score | Total possible points |
| Score % | (Score / Max) * 100 |
| Time Taken | Minutes spent |
| Passed | Yes / No |
| Attempt Date | Date/time |

### Score Distribution Chart (Histogram)
- **X-Axis:** Score ranges (0-20%, 20-40%, etc.)
- **Y-Axis:** Number of attempts
- **Shows:** Distribution of scores

### Attempt Trend Chart (Line Chart)
- **X-Axis:** Date
- **Y-Axis:** Average score
- **Shows:** Score trend over time

---

## 9. SCORM SUMMARY REPORT

### Report Filters (Top)
- Date Range (From / To)
- SCORM Activity (Optional)
- User (Optional)
- Completion Status (All / Completed / Not Completed)

### Summary Statistics
- **Total Attempts** - Count
- **Completed** - Count
- **Completion Rate** - %
- **Avg Score** - %
- **Avg Time** - Hours

### SCORM Completion Chart (Pie Chart)
- **Segments:**
  - Completed (green)
  - In Progress (yellow)
  - Not Started (red)

### SCORM Summary Table
| Column | Data |
|--------|------|
| SCORM Name | Activity title |
| User Name | Student name |
| Status | Completed / In Progress / Not Started |
| Score | Points earned |
| Max Score | Total possible |
| Score % | (Score / Max) * 100 |
| Time Spent | Hours |
| Attempts | Count |
| Completion Date | Date completed |
| Last Activity | Date/time |

### Attempt Timeline Chart (Line Chart)
- **X-Axis:** Date
- **Y-Axis:** Cumulative completions
- **Shows:** Completion trend

### Performance by SCORM Chart (Bar Chart)
- **X-Axis:** SCORM activity names
- **Y-Axis:** Average score (%)
- **Shows:** Which activities are hardest

---

## Competitive Analysis: Data Fields Comparison

### Edwiser ReportS (Edwiser)

**Dashboard Metrics:**
- New Registrations (with % change)
- Course Enrollments (with % change)
- Course Completions (with % change)
- Active Users (with % change)
- Total Active Users
- Total Course Enrollments
- Total Course Completions
- Site Overview Status
- Course Completion Trend (6-month line chart)
- Engagement by Course (bar chart)
- Top Courses by Completion (table)
- User Activity Heatmap (calendar)
- Division-Based Reports (if multi-tenant)

**Report Fields:**
- Course name, enrollment count, completion count, completion %
- Student name, course, progress %, time spent, last activity
- Quiz name, attempts, average score, pass rate
- User engagement score, logins, submissions, quiz attempts

**Strengths:**
✅ Clean dashboard design
✅ Good engagement metrics
✅ Division-based filtering
✅ Multiple chart types

**Limitations:**
❌ Limited time tracking
❌ No at-risk detection
❌ Limited export options
❌ No custom report builder

---

### IntelliBoard

**Dashboard Metrics:**
- Course Completion Rate (%)
- Student Engagement Score (0-100)
- Course Progress (%)
- Time Spent on Course (hours)
- Quiz Performance (average score)
- Assignment Completion Rate (%)
- Forum Activity (posts, discussions)
- Video Engagement (watch time, completion)
- xAPI Integration (video analytics)
- Learner Interactions (clicks, views)
- Course Activity Status (active/inactive)
- Predictive Analytics (at-risk detection)
- Drill-down Capabilities (click to filter)

**Report Fields:**
- Student name, ID, email, enrollment date
- Course name, category, completion date
- Activity type, timestamp, duration
- Quiz attempts, scores, time taken
- Assignment submissions, grades
- Forum posts, discussions, replies
- Video watch time, completion %, engagement
- xAPI statements (video interactions)
- Risk score, contributing factors
- Engagement trend (7/30/90 days)

**Strengths:**
✅ Advanced engagement scoring
✅ xAPI integration (video analytics)
✅ Predictive analytics (at-risk)
✅ Drill-down functionality
✅ Learner interaction tracking
✅ Multiple activity types
✅ Trend analysis

**Limitations:**
❌ Cloud-based (no self-hosted option)
❌ Higher cost
❌ Limited IOMAD support
❌ Vendor lock-in

---

### LearnerScript

**Dashboard Metrics:**
- Course Completion Status (completed/in-progress/not-started)
- Student Progress (%)
- Time Spent (hours)
- Engagement Level (high/medium/low)
- Quiz Performance (average score, pass rate)
- Assignment Status (submitted/pending/graded)
- Attendance/Login Frequency
- Course Activity (last activity date)
- Learner Milestones (achievements, badges)
- Compliance Status (training requirements)
- Department Performance (completion rate by dept)
- Cohort Analysis (group comparisons)

**Report Fields:**
- Student name, ID, department, enrollment date
- Course name, category, start date, end date
- Completion status, completion date, completion %
- Time spent, logins, last activity
- Quiz attempts, scores, average score
- Assignment submissions, grades, feedback
- Engagement score, activity level
- Compliance status, deadline, days remaining
- Department name, completion rate, average score
- Cohort name, size, average completion

**Strengths:**
✅ Compliance tracking
✅ Department/cohort analysis
✅ Milestone/badge tracking
✅ Assignment tracking
✅ Attendance tracking
✅ Good UI/UX

**Limitations:**
❌ Limited xAPI support
❌ No custom report builder
❌ Limited export options
❌ No time tracking (heartbeat)
❌ Limited IOMAD support

---

## ManiReports: Best of All Three + More

### What We Include from Each:

**From Edwiser:**
✅ Dashboard KPI cards with % change
✅ Course completion metrics
✅ Engagement by course
✅ User activity heatmap
✅ Division-based filtering

**From IntelliBoard:**
✅ Advanced engagement scoring (0-100)
✅ xAPI integration (video analytics)
✅ Predictive analytics (at-risk detection)
✅ Drill-down functionality
✅ Learner interaction tracking
✅ Trend analysis (7/30/90 days)
✅ Video engagement metrics

**From LearnerScript:**
✅ Compliance tracking
✅ Department/cohort analysis
✅ Milestone/badge tracking
✅ Assignment tracking
✅ Attendance/login frequency
✅ Cohort comparisons

### What We Add (Unique to ManiReports):

✅ **Self-Hosted** - No cloud dependency, full control
✅ **IOMAD Multi-Tenant** - Company isolation, per-company control
✅ **Time Tracking** - JavaScript heartbeat for accurate engagement
✅ **Custom Report Builder** - SQL & GUI modes
✅ **Cloud Offload** - Optional email/certificate generation
✅ **API Access** - PowerBI, Tableau, external BI tools
✅ **Lower Cost** - Self-hosted, no subscription
✅ **Better Performance** - Pre-aggregation, caching
✅ **Dashboard Builder** - User-customizable dashboards
✅ **Scheduled Reports** - Automated delivery
✅ **Multiple Export Formats** - CSV, XLSX, PDF
✅ **GDPR Compliance** - Data export/deletion
✅ **Audit Logging** - Complete audit trail

---

## Enhanced Data Fields (Combining All Three)

### Admin Dashboard - Enhanced Fields

**KPI Cards:**
- New Registrations (with % change vs last period)
- Course Enrollments (with % change)
- Course Completions (with % change)
- Active Users (with % change)
- At-Risk Learners (with % change)
- Compliance Rate (%)
- Average Engagement Score (0-100)

**Site Overview:**
- Total Active Users (last 30 days)
- Total Course Enrollments
- Total Course Completions
- Total Compliance Rate (%)
- Average Time Spent (hours)
- Average Engagement Score
- Last Updated (timestamp)

**Charts:**
- Course Completion Trend (6 months)
- Engagement by Course (bar chart)
- Department Performance (bar chart)
- User Activity Heatmap (calendar)
- At-Risk Learners Trend (line chart)
- Compliance Status (pie chart)

**Tables:**
- Top Courses (name, enrolled, completed, completion %, avg time, engagement score)
- At-Risk Learners (name, risk score, contributing factors, last activity)
- Department Performance (dept name, completion rate, avg engagement, at-risk count)
- Compliance Status (requirement, completion rate, deadline, status)

---

### Manager Dashboard - Enhanced Fields

**KPI Cards:**
- Company Users (with % change)
- Course Completions (with % change)
- Avg Engagement (0-100, with % change)
- At-Risk Learners (with % change)
- Compliance Rate (%)
- Avg Time Spent (hours)

**Company Overview:**
- Total Users
- Active Users (last 30 days)
- Total Enrollments
- Total Completions
- Completion Rate (%)
- Average Engagement Score
- Average Time Spent

**Charts:**
- Company Completion Trend (6 months)
- Department Performance (bar chart)
- Engagement Distribution (histogram)
- At-Risk Learners (pie chart)
- Compliance Status (progress bars)
- Activity Heatmap (calendar)

**Tables:**
- Top Performers (name, courses completed, avg score, time spent, engagement score)
- At-Risk Learners (name, risk score, factors, last activity, action)
- Department Performance (dept, completion rate, avg engagement, at-risk count)
- Compliance Status (requirement, completion rate, deadline, status)

---

### Teacher Dashboard - Enhanced Fields

**KPI Cards:**
- My Students (with % change)
- Completions (with % change)
- Avg Engagement (0-100)
- At-Risk in My Courses
- Avg Time Spent (hours)
- Assignment Submission Rate (%)

**My Courses Overview:**
- Total Courses
- Active Students
- Avg Completion Rate (%)
- Avg Engagement Score
- Avg Time Spent
- Assignment Submission Rate (%)

**Charts:**
- Student Progress (progress bars)
- Course Activity Timeline (line chart)
- Quiz Performance (bar chart)
- Time Spent Distribution (pie chart)
- Assignment Status (pie chart)
- Engagement Trend (line chart)

**Tables:**
- Student Progress (name, courses, completion %, time spent, engagement score, status)
- Quiz Performance (quiz name, avg score, pass rate, attempts)
- Assignment Status (assignment name, submitted, graded, avg grade)
- At-Risk Students (name, risk score, factors, last activity, action)

---

### Student Dashboard - Enhanced Fields

**KPI Cards:**
- My Courses (with count)
- Completed (with count)
- In Progress (with count)
- Time Spent (hours)
- Avg Engagement Score (0-100)
- Compliance Status (%)

**My Learning Progress:**
- Overall Completion (%)
- Overall Engagement Score (0-100)
- Certificates Earned (count)
- Badges Earned (count)
- Milestones Reached (count)
- Time Spent (total hours)

**Charts:**
- My Course Progress (progress bars)
- Learning Timeline (line chart - 30 days)
- Time Spent by Course (pie chart)
- Quiz Performance (bar chart)
- Engagement Trend (line chart)
- Activity Heatmap (calendar)

**Tables:**
- My Courses (name, progress %, status, time spent, last activity)
- My Achievements (certificate/badge name, earned date, description)
- Upcoming Deadlines (assignment, course, due date, days left)
- My Quiz Scores (quiz name, score, avg score, attempts)

---

## Report Pages - Enhanced Fields

### Course Completion Report
**Filters:** Date range, course, department, completion status, company (IOMAD)

**Summary:**
- Total Enrolled, Completed, Completion Rate (%), Avg Time to Complete

**Charts:**
- Completion Trend (line chart)
- Completion by Department (bar chart)
- Completion Distribution (histogram)

**Table Columns:**
- Course Name, Shortname, Enrolled, Completed, Completion %, Avg Time, Earliest Completion, Latest Completion, Engagement Score, At-Risk Count

---

### Course Progress Report
**Filters:** Date range, course, user, progress range, department, company (IOMAD)

**Summary:**
- Total Enrolled, Avg Progress (%), On Track, At Risk

**Charts:**
- Progress Distribution (bar chart)
- Progress Timeline (line chart)
- Progress by Department (bar chart)

**Table Columns:**
- Student Name, Course Name, Progress %, Activities Completed, Total Activities, Time Spent, Last Activity, Status, Engagement Score, At-Risk Flag

---

### User Engagement Report
**Filters:** Date range, course, department, engagement level, company (IOMAD)

**Summary:**
- Total Users, Avg Engagement Score (0-100), Active Users, Inactive Users

**Charts:**
- Engagement Distribution (histogram)
- Engagement Trend (line chart)
- Activity Heatmap (calendar)
- Engagement by Department (bar chart)

**Table Columns:**
- User Name, Engagement Score, Logins, Time Spent, Activities Completed, Quiz Attempts, Submissions, Last Active, Status, Trend (7/30/90 days)

---

### Quiz Attempts Report
**Filters:** Date range, course, quiz, user, pass/fail, company (IOMAD)

**Summary:**
- Total Attempts, Avg Score (%), Pass Rate (%), Avg Time

**Charts:**
- Quiz Performance (bar chart)
- Score Distribution (histogram)
- Attempt Trend (line chart)
- Difficulty Analysis (bar chart)

**Table Columns:**
- Quiz Name, User Name, Attempt #, Score, Max Score, Score %, Time Taken, Passed, Attempt Date, Engagement Score

---

### SCORM Summary Report
**Filters:** Date range, SCORM activity, user, completion status, company (IOMAD)

**Summary:**
- Total Attempts, Completed, Completion Rate (%), Avg Score (%), Avg Time

**Charts:**
- Completion Status (pie chart)
- Performance by SCORM (bar chart)
- Attempt Timeline (line chart)
- Score Distribution (histogram)

**Table Columns:**
- SCORM Name, User Name, Status, Score, Max Score, Score %, Time Spent, Attempts, Completion Date, Last Activity, Engagement Score

---

## Additional Reports (From Competitors)

### Assignment Report (from LearnerScript)
**Filters:** Date range, course, assignment, user, status, company (IOMAD)

**Table Columns:**
- Assignment Name, Course, User Name, Status (submitted/pending/graded), Submission Date, Grade, Feedback, Time Spent, Engagement Score

---

### Compliance Report (from LearnerScript)
**Filters:** Date range, department, compliance requirement, company (IOMAD)

**Table Columns:**
- Requirement Name, Department, Total Users, Completed, Completion Rate (%), Deadline, Days Remaining, Status, At-Risk Count

---

### Department/Cohort Report (from LearnerScript)
**Filters:** Date range, department/cohort, company (IOMAD)

**Table Columns:**
- Department/Cohort Name, Total Users, Avg Completion Rate (%), Avg Engagement Score, Avg Time Spent, At-Risk Count, Compliance Rate (%)

---

### Video Engagement Report (from IntelliBoard)
**Filters:** Date range, video/SCORM, user, company (IOMAD)

**Table Columns:**
- Video Name, User Name, Watch Time (hours), Completion %, Engagement Score, Interactions, Last Watched, Status

---

## Summary: ManiReports Competitive Advantage

| Feature | Edwiser | IntelliBoard | LearnerScript | ManiReports |
|---------|---------|--------------|---------------|------------|
| Dashboard KPIs | ✅ | ✅ | ✅ | ✅✅ |
| Engagement Scoring | ✅ | ✅✅ | ✅ | ✅✅ |
| At-Risk Detection | ❌ | ✅✅ | ❌ | ✅✅ |
| xAPI Integration | ❌ | ✅✅ | ❌ | ✅✅ |
| Time Tracking | ❌ | ❌ | ❌ | ✅✅ |
| Custom Reports | ❌ | ❌ | ❌ | ✅✅ |
| IOMAD Support | ✅ | ❌ | ❌ | ✅✅ |
| Self-Hosted | ✅ | ❌ | ✅ | ✅✅ |
| API Access | ❌ | ❌ | ❌ | ✅✅ |
| Dashboard Builder | ❌ | ❌ | ❌ | ✅✅ |
| Compliance Tracking | ❌ | ❌ | ✅ | ✅✅ |
| Department Analysis | ❌ | ❌ | ✅ | ✅✅ |
| Assignment Tracking | ❌ | ❌ | ✅ | ✅✅ |
| Video Analytics | ❌ | ✅✅ | ❌ | ✅✅ |
| Drill-Down | ❌ | ✅ | ❌ | ✅✅ |
| Export Formats | ✅ | ✅ | ✅ | ✅✅ |
| Scheduled Reports | ✅ | ✅ | ✅ | ✅✅ |
| Cost | Medium | High | Medium | Low |

**ManiReports = Best of all three + unique features + lower cost + self-hosted!**

---

## Data Refresh Frequency

| Data Type | Refresh Frequency |
|-----------|-------------------|
| KPI Cards | Real-time (AJAX) |
| Dashboards | Every 5 minutes (cached) |
| Reports | On-demand (cached 1 hour) |
| Time Tracking | Every 30 seconds (heartbeat) |
| Aggregated Data | Hourly (scheduled task) |
| SCORM Data | Hourly (scheduled task) |

---

## Performance Targets

- Dashboard load time: < 2 seconds
- Report load time: < 3 seconds
- Chart rendering: < 1 second
- Filter response: < 500ms
- Export generation: < 5 seconds

---

## Security & Privacy

- All data filtered by user role
- IOMAD company isolation enforced
- GDPR compliance (data export/deletion)
- Audit logging of all access
- SQL injection prevention
- XSS protection
- CSRF protection

---

## Next Steps

1. **Review** this specification with stakeholders
2. **Approve** the data fields for each page
3. **Request changes** if needed
4. **Finalize** before implementation
5. **Implement** based on approved specification

This ensures we build exactly what users need to compete with Edwiser, LearnerScript, and IntelliBoard!

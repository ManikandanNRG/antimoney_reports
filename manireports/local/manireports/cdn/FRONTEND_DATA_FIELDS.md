# Frontend Data Fields & UI Recommendations

This document outlines the data fields available for each page in **ManiReports** and provides recommendations to achieve a "Market Best" UI/UX.

## 1. Dashboards

### A. Admin Dashboard
**Goal:** High-level system overview and health check.

| Section | Current Data Fields | **"Market Best" Additions (Recommended)** |
| :--- | :--- | :--- |
| **KPI Cards** | Total Users, Total Courses, Total Enrollments, Active Users, Inactive Users. | **Revenue** (if paid), **Storage Used**, **Cloud Offload Savings** (Phase 4). |
| **Charts** | User Access vs. Completions (Line), Top Courses (Bar). | **Geographic Map** (User login locations), **Device Usage** (Mobile vs Desktop). |
| **Tables** | Top Accessed Courses (Name, Users, Accesses). | **Recent Errors/Failed Jobs**, **New Registrations** (Last 24h). |
| **Lists** | Inactive Users (Name, Days Inactive). | **At-Risk Courses** (Low engagement), **System Alerts**. |

### B. Instructor/Manager Dashboard
**Goal:** Course effectiveness and learner progress tracking.

| Section | Current Data Fields | **"Market Best" Additions (Recommended)** |
| :--- | :--- | :--- |
| **KPI Cards** | My Courses, Total Students, Assignments to Grade. | **Average Course Grade**, **Completion Rate**, **Dropout Risk**. |
| **Charts** | Course Progress Distribution, Quiz Average Scores. | **Engagement Heatmap** (When do students study?), **Grade Distribution** (Bell curve). |
| **Tables** | Course Usage (Enrolled, Completed, % Complete). | **"Needs Attention" List** (Students who haven't logged in > 7 days). |

### C. Student Dashboard
**Goal:** Personal progress and motivation.

| Section | Current Data Fields | **"Market Best" Additions (Recommended)** |
| :--- | :--- | :--- |
| **KPI Cards** | Enrolled Courses, Completed Courses, Certificates. | **Current Streak** (Days logged in), **Points/Badges**, **Next Deadline**. |
| **Charts** | My Progress vs Class Average. | **Time Spent per Course**, **Skill Radar Chart**. |
| **Lists** | My Courses (Progress Bar). | **"Resume Learning"** (Deep link to last activity), **Upcoming Events**. |

---

## 2. Reports (Detailed Views)

### A. Course Completion Report
**File:** `classes/reports/course_completion.php`

| Column | Field Name | Description | UI Recommendation |
| :--- | :--- | :--- | :--- |
| 1 | `coursename` | Full name of the course | Link to course page. |
| 2 | `shortname` | Course short code | Badge style. |
| 3 | `enrolled` | Count of enrolled users | Clickable (drills down to user list). |
| 4 | `completed` | Count of completed users | Clickable (drills down to completers). |
| 5 | `completion_percentage` | % of enrolled who completed | **Circular Progress Bar** or Color-coded text (Green > 80%). |

### B. Course Progress Report
**File:** `classes/reports/course_progress.php`

| Column | Field Name | Description | UI Recommendation |
| :--- | :--- | :--- | :--- |
| 1 | `firstname` / `lastname` | User's name | User Avatar + Name combo. |
| 2 | `email` | User's email | Copy-to-clipboard icon on hover. |
| 3 | `coursename` | Course name | - |
| 4 | `total_activities` | Total activities in course | - |
| 5 | `completed_activities` | Activities completed by user | - |
| 6 | `progress_percentage` | (Completed / Total) * 100 | **Linear Progress Bar** (Visual). |
| 7 | `timecompleted` | Date of completion | Relative time (e.g., "2 days ago"). |

### C. Quiz Attempts Report
**File:** `classes/reports/quiz_attempts.php`

| Column | Field Name | Description | UI Recommendation |
| :--- | :--- | :--- | :--- |
| 1 | `firstname` / `lastname` | User's name | User Avatar + Name. |
| 2 | `coursename` | Course name | - |
| 3 | `quizname` | Quiz name | Icon indicating quiz type. |
| 4 | `total_attempts` | Number of attempts | - |
| 5 | `finished_attempts` | Completed attempts | - |
| 6 | `avg_score` | Average grade | Color-coded (Red < 50%, Green > 80%). |
| 7 | `best_score` | Highest grade | Highlight with a "Trophy" icon if 100%. |
| 8 | `last_attempt` | Date of last attempt | - |

### D. SCORM Summary Report
**File:** `classes/reports/scorm_summary.php`

| Column | Field Name | Description | UI Recommendation |
| :--- | :--- | :--- | :--- |
| 1 | `firstname` / `lastname` | User's name | User Avatar + Name. |
| 2 | `scormname` | SCORM package name | - |
| 3 | `attempts` | Number of attempts | - |
| 4 | `completed` | Yes/No status | **Status Badge** (Green "Completed", Grey "In Progress"). |
| 5 | `totaltime` | Time spent (HH:MM:SS) | - |
| 6 | `score` | Score achieved | - |
| 7 | `lastaccess` | Last access date | - |

### E. User Engagement Report
**File:** `classes/reports/user_engagement.php`

| Column | Field Name | Description | UI Recommendation |
| :--- | :--- | :--- | :--- |
| 1 | `firstname` / `lastname` | User's name | User Avatar + Name. |
| 2 | `coursename` | Course name | - |
| 3 | `time_7days` | Time spent in last 7 days | **Sparkline Chart** (Trend over 7 days). |
| 4 | `time_30days` | Time spent in last 30 days | - |
| 5 | `active_days_7` | Days active in last 7 | - |
| 6 | `active_days_30` | Days active in last 30 | - |
| 7 | `lastaccess` | Last login time | "Online Now" indicator if < 5 mins. |

---

## 3. Global UI Recommendations (The "Market Best" Polish)

To make the frontend truly stand out, apply these global styles to all pages:

1.  **Filters Bar**:
    *   **Current**: Standard HTML form inputs.
    *   **Recommendation**: A floating, glassmorphic filter bar. Use "Pill" shaped selectors for quick filters (e.g., "Last 7 Days", "Last Month"). Date pickers should be modern (e.g., Flatpickr).

2.  **Data Tables**:
    *   **Current**: Standard Bootstrap table.
    *   **Recommendation**:
        *   **Sticky Headers**: Keep headers visible while scrolling.
        *   **Hover Actions**: Show "Email User" or "View Profile" buttons only when hovering over a row.
        *   **Skeleton Loading**: Show a shimmering skeleton state while data loads instead of a spinner.

3.  **Charts**:
    *   **Current**: Chart.js (Good).
    *   **Recommendation**:
        *   **Gradients**: Use gradient fills for line/bar charts (already in V6).
        *   **Interactivity**: Clicking a bar in a chart should filter the table below (Drill-down).

4.  **Export Actions**:
    *   **Current**: Simple text links.
    *   **Recommendation**: A "Download" dropdown button with icons for PDF, CSV, Excel.

# Reports & Role-Based Dashboards Implementation Plan

## Current Status

✅ **Dashboard Template Created:**
- V4 Dark Professional (enhanced)
- V5 Modern Compact (enhanced)
- Both with realistic ManiReports data

❓ **Still Need:**
- 5 Report Pages (Course Completion, Course Progress, User Engagement, Quiz Attempts, SCORM Summary)
- 4 Role-Based Dashboards (Admin, Manager, Teacher, Student)

---

## Architecture Overview

```
ManiReports UI Structure:
├── dashboard.php (Main entry point - role detection)
│   ├── Admin Dashboard (dashboard_admin.mustache)
│   ├── Manager Dashboard (dashboard_manager.mustache)
│   ├── Teacher Dashboard (dashboard_teacher.mustache)
│   └── Student Dashboard (dashboard_student.mustache)
│
├── reports.php (Reports listing page)
│   ├── Course Completion Report (report_view.php?report=course_completion)
│   ├── Course Progress Report (report_view.php?report=course_progress)
│   ├── User Engagement Report (report_view.php?report=user_engagement)
│   ├── Quiz Attempts Report (report_view.php?report=quiz_attempts)
│   └── SCORM Summary Report (report_view.php?report=scorm_summary)
│
└── Custom Reports (custom_reports.php)
    ├── Custom Report Builder (report_builder_gui.php)
    └── Custom Report Editor (custom_report_edit.php)
```

---

## Strategy: Reusable Template System

Instead of creating 9 separate pages, we'll use a **template-based approach**:

### 1. **Base Report Template** (report_view.php)
- Single entry point for all reports
- Accepts `?report=report_name` parameter
- Dynamically loads report class
- Renders with appropriate template

### 2. **Report Templates** (templates/)
- `report_table.mustache` - Data table view
- `report_chart.mustache` - Chart visualization
- `report_combined.mustache` - Table + Chart
- `report_filters.mustache` - Filter controls

### 3. **Dashboard Templates** (templates/)
- `dashboard_admin.mustache` - Site-wide metrics
- `dashboard_manager.mustache` - Company metrics
- `dashboard_teacher.mustache` - Course metrics
- `dashboard_student.mustache` - Personal metrics

---

## Implementation Plan

### Phase 1: Report Pages (Unified Approach)

#### Step 1: Create Generic Report View Page
**File:** `ui/report_view.php`

```php
<?php
require_once(__DIR__ . '/../../../config.php');
require_login();

$report_type = required_param('report', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$filters = optional_param_array('filters', [], PARAM_RAW);

// Load report class dynamically
$report_class = "\\local_manireports\\reports\\" . $report_type;
$report = new $report_class();

// Execute report
$data = $report->execute($filters, $page);

// Render
$renderer = $PAGE->get_renderer('local_manireports');
echo $renderer->render_report($report, $data);
?>
```

#### Step 2: Create Report Templates
Each report uses the same template structure:

**Template:** `templates/report_combined.mustache`
```mustache
<div class="report-container">
    <div class="report-header">
        <h2>{{report_name}}</h2>
        <div class="report-controls">
            {{>filters}}
            <button class="btn-export">Export</button>
        </div>
    </div>
    
    <div class="report-content">
        <div class="report-chart">
            <canvas id="reportChart"></canvas>
        </div>
        <div class="report-table">
            {{>report_table}}
        </div>
    </div>
</div>
```

#### Step 3: Report Pages Map

| Report | URL | Class | Template | Data Source |
|--------|-----|-------|----------|-------------|
| Course Completion | `report_view.php?report=course_completion` | `course_completion.php` | `report_combined.mustache` | `{course_completions}` |
| Course Progress | `report_view.php?report=course_progress` | `course_progress.php` | `report_combined.mustache` | `{course_modules_completion}` |
| User Engagement | `report_view.php?report=user_engagement` | `user_engagement.php` | `report_combined.mustache` | `{manireports_usertime_daily}` |
| Quiz Attempts | `report_view.php?report=quiz_attempts` | `quiz_attempts.php` | `report_combined.mustache` | `{quiz_attempts}` |
| SCORM Summary | `report_view.php?report=scorm_summary` | `scorm_summary.php` | `report_combined.mustache` | `{manireports_scorm_summary}` |

---

### Phase 2: Role-Based Dashboards

#### Dashboard Routing Logic

**File:** `ui/dashboard.php` (already exists)

```php
<?php
// Determine user role
$role = get_user_role($USER->id);

// Load appropriate dashboard template
switch($role) {
    case 'admin':
        $template = 'dashboard_admin.mustache';
        $data = get_admin_dashboard_data();
        break;
    case 'manager':
        $template = 'dashboard_manager.mustache';
        $data = get_manager_dashboard_data();
        break;
    case 'teacher':
        $template = 'dashboard_teacher.mustache';
        $data = get_teacher_dashboard_data();
        break;
    case 'student':
        $template = 'dashboard_student.mustache';
        $data = get_student_dashboard_data();
        break;
}

// Render
echo $renderer->render_from_template($template, $data);
?>
```

#### Dashboard Specifications

##### 1. **Admin Dashboard** (dashboard_admin.mustache)
**Purpose:** Site-wide analytics and management

**Widgets:**
- KPI Cards: Total users, courses, completions, at-risk
- Course Completion Trend (line chart - 6 months)
- Engagement by Course (bar chart)
- Top Courses by Completion (table)
- User Activity Heatmap (calendar)
- System Health (performance metrics)

**Data Sources:**
- All courses, all users
- No company filtering (site admin)
- Aggregated metrics

**Design:** V4 Dark Professional (professional/enterprise)

---

##### 2. **Manager Dashboard** (dashboard_manager.mustache)
**Purpose:** Company-specific analytics

**Widgets:**
- KPI Cards: Company users, completions, engagement, at-risk
- Company Course Completion (line chart)
- Department Performance (bar chart)
- Top Performers (table)
- At-Risk Learners (alert widget)
- Company Compliance (progress bars)

**Data Sources:**
- Only company's courses and users
- IOMAD company filtering applied
- Company-specific metrics

**Design:** V5 Modern Compact (modern/accessible)

---

##### 3. **Teacher Dashboard** (dashboard_teacher.mustache)
**Purpose:** Course and student progress tracking

**Widgets:**
- KPI Cards: My students, completions, avg engagement, at-risk
- Student Progress (table with progress bars)
- Course Activity Timeline (line chart)
- Quiz Performance (bar chart)
- Time Spent Distribution (pie chart)
- Student Engagement Scores (table)

**Data Sources:**
- Only teacher's enrolled courses
- Only students in those courses
- Per-student metrics

**Design:** V5 Modern Compact (clean/focused)

---

##### 4. **Student Dashboard** (dashboard_student.mustache)
**Purpose:** Personal progress and learning analytics

**Widgets:**
- KPI Cards: My courses, completed, in progress, time spent
- My Course Progress (progress bars)
- Learning Timeline (line chart - activity over time)
- Time Spent by Course (pie chart)
- My Achievements (badges/certificates)
- Upcoming Deadlines (alert widget)

**Data Sources:**
- Only student's enrolled courses
- Only student's own data
- Personal metrics only

**Design:** V5 Modern Compact (personal/motivational)

---

## Implementation Workflow

### Week 1: Report Pages
```
Day 1-2: Create generic report_view.php
Day 3-4: Create report templates (combined, table, chart)
Day 5: Create reports.php listing page
```

### Week 2: Role-Based Dashboards
```
Day 1-2: Create dashboard role detection logic
Day 3-4: Create admin dashboard template + data functions
Day 5: Create manager dashboard template + data functions
```

### Week 3: Teacher & Student Dashboards
```
Day 1-2: Create teacher dashboard template + data functions
Day 3-4: Create student dashboard template + data functions
Day 5: Testing and refinement
```

---

## File Structure After Implementation

```
local/manireports/
├── ui/
│   ├── dashboard.php (role detection + routing)
│   ├── report_view.php (generic report viewer)
│   ├── reports.php (reports listing)
│   └── ajax/
│       ├── dashboard_data.php (admin dashboard data)
│       ├── manager_dashboard_data.php (manager data)
│       ├── teacher_dashboard_data.php (teacher data)
│       └── student_dashboard_data.php (student data)
│
├── templates/
│   ├── dashboard_admin.mustache
│   ├── dashboard_manager.mustache
│   ├── dashboard_teacher.mustache
│   ├── dashboard_student.mustache
│   ├── report_combined.mustache
│   ├── report_table.mustache
│   ├── report_chart.mustache
│   └── filters.mustache
│
├── classes/
│   ├── output/
│   │   ├── dashboard_renderer.php
│   │   └── report_renderer.php
│   │
│   └── api/
│       ├── dashboard_data_provider.php (new)
│       └── report_data_provider.php (new)
```

---

## Data Flow Example: Course Completion Report

```
User clicks "Course Completion" in reports menu
    ↓
report_view.php?report=course_completion
    ↓
Load course_completion.php report class
    ↓
Execute report with filters
    ↓
Get data from database (course_completions table)
    ↓
Apply IOMAD filtering (company isolation)
    ↓
Render with report_combined.mustache template
    ↓
Display table + chart
    ↓
User can export, filter, paginate
```

---

## Data Flow Example: Manager Dashboard

```
Manager logs in
    ↓
dashboard.php detects role = "manager"
    ↓
Load dashboard_manager.mustache template
    ↓
AJAX calls manager_dashboard_data.php
    ↓
Get company-specific metrics
    ↓
Apply IOMAD company filter
    ↓
Return JSON data
    ↓
JavaScript renders widgets
    ↓
Display company analytics
```

---

## Key Benefits of This Approach

✅ **DRY (Don't Repeat Yourself)**
- Single report_view.php handles all reports
- Reusable templates
- Shared styling and functionality

✅ **Scalable**
- Easy to add new reports (just create report class)
- Easy to add new dashboards (just create template)
- No code duplication

✅ **Maintainable**
- Changes to report styling affect all reports
- Dashboard logic centralized
- Easy to debug and test

✅ **Consistent UX**
- All reports look and feel the same
- All dashboards follow same patterns
- Familiar navigation for users

✅ **Performance**
- Shared AJAX endpoints
- Cached data
- Optimized queries

---

## Next Steps

1. **Confirm this approach** - Do you want to use this template-based system?
2. **Choose dashboard design** - V4 (dark) or V5 (light)?
3. **Start implementation** - Begin with report_view.php
4. **Create templates** - Build report and dashboard templates
5. **Test on EC2** - Deploy and verify with real data

---

## Questions to Consider

1. Should all reports use the same design (V4 or V5)?
2. Should dashboards be customizable by users?
3. Do you want drill-down functionality (click chart → filtered report)?
4. Should reports be exportable in all formats (CSV, XLSX, PDF)?
5. Do you want scheduled report delivery?

Let me know your thoughts and we can proceed with implementation!

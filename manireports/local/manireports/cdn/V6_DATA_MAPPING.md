# V6 Ultimate Dashboard - Data Field Mapping

This document maps the data fields from your `DATA_FIELDS_SPECIFICATION.md` to the **Dashboard V6 Ultimate** design (Glassmorphism + Bento Grid).

## 1. Admin Dashboard Mapping

### A. KPI Cards (The Top Row)
The V6 design has 4 Bento Cards in the top row. We will map your spec metrics here:

| V6 Component | Spec Field | Visual Style |
| :--- | :--- | :--- |
| **Card 1** | **New Registrations** (vs New Users) | Sparkline Chart (Green) |
| **Card 2** | **Active Users** (Last 30 days) | Sparkline Chart (Blue) |
| **Card 3** | **Course Completions** | Progress Bar / Circular |
| **Card 4** | **System Health** (Error Rate/Cron) | Status Indicator (Pulse) |

*   **Gap Identified:** The V6 template currently shows "Inactive Users" in Card 4. We should swap this for "System Health" or "Revenue" depending on importance, or make it a carousel.

### B. Main Chart Section (The Big Card)
The V6 design has a large `card-span-3` chart area.

| V6 Component | Spec Field | Visual Style |
| :--- | :--- | :--- |
| **Main Chart** | **Course Completion Trend** (6 Months) | Gradient Area Chart |
| **Overlay** | **Enrollments** (Comparison line) | Dashed Line (Secondary) |
| **Dropdown** | Filter by Category/Time | Glassmorphic Select |

### C. Side Column (The Right Vertical Stack)
The V6 design has a vertical stack on the right (`card-span-1`).

| V6 Component | Spec Field | Visual Style |
| :--- | :--- | :--- |
| **Widget 1** | **User Activity Heatmap** | Calendar Grid (GitHub style) |
| **Widget 2** | **Engagement by Course** | Horizontal Bar Chart (Top 5) |
| **Widget 3** | **System Health Metrics** | Compact List (DB Size, Cache) |

*   **Gap Identified:** We need to add the **Heatmap** component to the V6 CSS/JS.

### D. Bottom Section (Full Width)
The V6 design has a `card-span-4` table area.

| V6 Component | Spec Field | Visual Style |
| :--- | :--- | :--- |
| **Table** | **Top Courses by Completion** | Modern Data Table |
| **Columns** | Name, Enrollments, Completions, % | Sortable Headers |
| **Action** | "View All Courses" | Floating Action Button |

---

## 2. Missing UI Components in V6
To fully support your spec, I need to add these specific UI components to the `dashboard_v6_ultimate.php` template:

1.  **Activity Heatmap**: A GitHub-style contribution graph for "User Activity".
2.  **System Health Widget**: A compact list with status dots (Green/Red) for Cron, DB, Cache.
3.  **Engagement Score Meter**: A gauge or circular chart for "Average Engagement".
4.  **Compliance Progress Bar**: A stacked progress bar for "Compliance Status".

## 3. Role-Based Adaptations
The V6 template is currently "Admin-focused". To support Manager/Teacher/Student views as per your spec:

*   **Manager**: Replace "System Health" with "Company Overview".
*   **Teacher**: Replace "New Registrations" with "My Students".
*   **Student**: Replace "Total Users" with "My Progress" and add "Upcoming Deadlines" widget.

## 4. Next Steps
I recommend we proceed by:
1.  **Updating V6 Template**: Add the missing "Heatmap" and "System Health" styles.
2.  **Creating Role Variants**: Create `dashboard_v6_manager.php`, `dashboard_v6_student.php`, etc., using the same V6 base but with mapped fields.

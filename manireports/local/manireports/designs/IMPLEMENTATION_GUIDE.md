# Dashboard Design Implementation Guide

## Quick Start

### View the Designs

Access each design directly in your browser:

```
http://your-moodle/local/manireports/designs/dashboard_v1_modern.php
http://your-moodle/local/manireports/designs/dashboard_v2_colorful.php
http://your-moodle/local/manireports/designs/dashboard_v3_datarich.php
```

### Files Created

```
local/manireports/designs/
├── DESIGN_REFERENCE.md          # Design inspiration sources
├── DESIGN_SHOWCASE.md           # Detailed design descriptions
├── DESIGN_COMPARISON.md         # Side-by-side comparison
├── IMPLEMENTATION_GUIDE.md      # This file
├── dashboard_v1_modern.php      # Modern Professional design
├── dashboard_v2_colorful.php    # Colorful & Engaging design
└── dashboard_v3_datarich.php    # Data-Rich & Compact design
```

---

## Design Selection Flowchart

```
START
  ↓
Who are your primary users?
  ├─ Administrators/Executives → V1 (Modern Professional)
  ├─ Teachers/Students → V2 (Colorful & Engaging)
  └─ Managers/Analysts → V3 (Data-Rich & Compact)
  ↓
Do you need detailed data tables?
  ├─ Yes → V3 (Data-Rich & Compact)
  └─ No → V1 or V2
  ↓
Do you want vibrant colors?
  ├─ Yes → V2 (Colorful & Engaging)
  └─ No → V1 (Modern Professional)
  ↓
SELECTED DESIGN
```

---

## Step-by-Step Implementation

### Step 1: Choose Your Design

Review the three designs and select the one that best fits your needs:

- **V1 Modern**: Professional, clean, minimalist
- **V2 Colorful**: Modern, vibrant, engaging
- **V3 Data-Rich**: Comprehensive, information-dense, action-oriented

### Step 2: Create Mustache Templates

Convert the HTML from your chosen design into Mustache templates.

**Example for V1 (Modern Professional)**:

```mustache
<div class="dashboard-v1">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h2 style="margin: 0; font-size: 24px; color: #212529;">{{title}}</h2>
        <div class="time-selector">
            {{#timeperiods}}
            <button class="time-btn {{#active}}active{{/active}}">{{label}}</button>
            {{/timeperiods}}
        </div>
    </div>
    
    <!-- KPI Cards -->
    <div class="kpi-section">
        {{#kpis}}
        <div class="kpi-card {{class}}">
            <div class="kpi-label">
                <div class="kpi-icon">{{icon}}</div>
                {{label}}
            </div>
            <div class="kpi-value">{{value}}</div>
            <div class="kpi-trend {{trend_class}}">
                <span class="trend-arrow">{{trend_arrow}}</span>
                <span>{{trend_text}}</span>
            </div>
        </div>
        {{/kpis}}
    </div>
    
    <!-- Charts Section -->
    <div class="charts-section">
        <div class="chart-card">
            <div class="chart-title">{{chart1_title}}</div>
            <div class="chart-placeholder">
                <canvas id="chart1"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-title">{{chart2_title}}</div>
            <div class="chart-placeholder">
                <canvas id="chart2"></canvas>
            </div>
        </div>
    </div>
</div>
```

### Step 3: Create PHP Renderer Class

Create a renderer class to prepare data for the template:

```php
<?php
namespace local_manireports\output;

use renderable;
use renderer_base;
use templatable;

class dashboard_renderer_v1 implements renderable, templatable {
    
    private $dashboard_data;
    
    public function __construct($data) {
        $this->dashboard_data = $data;
    }
    
    public function export_for_template(renderer_base $output) {
        return [
            'title' => 'Dashboard Overview',
            'kpis' => $this->prepare_kpis(),
            'charts' => $this->prepare_charts(),
            'timeperiods' => $this->prepare_timeperiods(),
        ];
    }
    
    private function prepare_kpis() {
        return [
            [
                'label' => 'Total Enrolled Students',
                'icon' => '👥',
                'value' => $this->dashboard_data['total_students'],
                'trend_class' => 'up',
                'trend_arrow' => '↑',
                'trend_text' => '12.5% from last month',
                'class' => 'success',
            ],
            // ... more KPIs
        ];
    }
    
    private function prepare_charts() {
        return [
            [
                'title' => 'Course Completion Trends',
                'type' => 'bar',
                'data' => $this->dashboard_data['completion_trends'],
            ],
            // ... more charts
        ];
    }
    
    private function prepare_timeperiods() {
        return [
            ['label' => '1D', 'active' => false],
            ['label' => '7D', 'active' => false],
            ['label' => '1M', 'active' => true],
            ['label' => '3M', 'active' => false],
            ['label' => 'All', 'active' => false],
        ];
    }
}
```

### Step 4: Create UI Entry Point

Create a PHP file that uses the renderer:

```php
<?php
require_once(__DIR__ . '/../../config.php');

$context = context_system::instance();
require_login();
require_capability('local/manireports:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/manireports/ui/dashboard.php'));
$PAGE->set_title('Dashboard');

// Get dashboard data from API
$api = new \local_manireports\api\report_builder();
$data = $api->get_dashboard_data();

// Create renderer
$renderer = new \local_manireports\output\dashboard_renderer_v1($data);

// Render
echo $OUTPUT->header();
echo $OUTPUT->render($renderer);
echo $OUTPUT->footer();
```

### Step 5: Add Chart.js Integration

Create an AMD module to render charts:

```javascript
// amd/src/dashboard_charts.js
define(['jquery', 'core/chartjs'], function($, Chart) {
    return {
        init: function(data) {
            this.renderCompletionChart(data.completion_trends);
            this.renderDistributionChart(data.distribution);
        },
        
        renderCompletionChart: function(data) {
            var ctx = document.getElementById('completionChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Completed',
                            data: data.completed,
                            backgroundColor: '#007bff',
                        },
                        {
                            label: 'In Progress',
                            data: data.in_progress,
                            backgroundColor: '#ffc107',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                }
            });
        },
        
        renderDistributionChart: function(data) {
            var ctx = document.getElementById('distributionChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: [
                            '#007bff',
                            '#28a745',
                            '#ffc107',
                            '#dc3545',
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                }
            });
        }
    };
});
```

### Step 6: Connect to API

Update your API class to provide dashboard data:

```php
<?php
namespace local_manireports\api;

class report_builder {
    
    public function get_dashboard_data() {
        global $DB, $USER;
        
        $context = \context_system::instance();
        
        // Apply IOMAD filtering
        $filter = new iomad_filter();
        $user_filter = $filter->get_user_filter($USER->id);
        
        // Get KPI data
        $total_students = $DB->count_records('user', 
            array_merge(['deleted' => 0], $user_filter));
        
        $completed_courses = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {course_completions} 
             WHERE userid IN (SELECT id FROM {user} WHERE " . 
            implode(' AND ', array_map(fn($k, $v) => "$k = $v", 
                array_keys($user_filter), $user_filter)) . ")"
        );
        
        // Get chart data
        $completion_trends = $this->get_completion_trends();
        $distribution = $this->get_course_distribution();
        
        return [
            'total_students' => $total_students,
            'completed_courses' => $completed_courses,
            'at_risk_students' => $this->get_at_risk_count(),
            'avg_time_spent' => $this->get_avg_time_spent(),
            'completion_trends' => $completion_trends,
            'distribution' => $distribution,
        ];
    }
    
    private function get_completion_trends() {
        // Implementation
    }
    
    private function get_course_distribution() {
        // Implementation
    }
    
    private function get_at_risk_count() {
        // Implementation
    }
    
    private function get_avg_time_spent() {
        // Implementation
    }
}
```

### Step 7: Add CSS Styling

Include the CSS from your chosen design in your stylesheet:

```css
/* styles.css */
@import url('designs/dashboard_v1_modern.css');
/* or */
@import url('designs/dashboard_v2_colorful.css');
/* or */
@import url('designs/dashboard_v3_datarich.css');
```

### Step 8: Test and Deploy

1. **Local Testing**:
   ```bash
   # Clear caches
   php admin/cli/purge_caches.php
   
   # Test the dashboard
   # Visit: http://localhost/local/manireports/ui/dashboard.php
   ```

2. **Remote Testing** (EC2):
   ```bash
   # SSH to server
   ssh user@your-ec2-instance.com
   
   # Deploy files
   cd /var/www/html/moodle/local/manireports
   git pull origin main
   
   # Clear caches
   sudo -u www-data php admin/cli/purge_caches.php
   
   # Test
   # Visit: http://your-domain/local/manireports/ui/dashboard.php
   ```

3. **Verify**:
   - Check browser console for errors
   - Verify charts render correctly
   - Test on mobile devices
   - Check Moodle error log

---

## Customization Examples

### Change Color Scheme

**V1 - Modern Professional**:
```css
.kpi-card { border-left-color: #667eea; } /* Change primary color */
.kpi-card.success { border-left-color: #43e97b; } /* Change success color */
```

**V2 - Colorful & Engaging**:
```css
.kpi-card-v2.orange {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); /* New gradient */
}
```

**V3 - Data-Rich & Compact**:
```css
.badge-success {
    background: #d4edda;
    color: #155724;
}
```

### Add New KPI Card

```php
// In renderer class
private function prepare_kpis() {
    return [
        // ... existing KPIs
        [
            'label' => 'New Metric',
            'icon' => '📊',
            'value' => $this->dashboard_data['new_metric'],
            'trend_class' => 'up',
            'trend_arrow' => '↑',
            'trend_text' => '5.2% from last month',
            'class' => 'info',
        ],
    ];
}
```

### Add New Chart

```javascript
// In dashboard_charts.js
renderNewChart: function(data) {
    var ctx = document.getElementById('newChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'New Data',
                data: data.values,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
        }
    });
}
```

---

## Troubleshooting

### Charts Not Rendering

**Problem**: Canvas elements show but no charts appear

**Solution**:
1. Check browser console for errors
2. Verify Chart.js is loaded: `console.log(Chart)`
3. Ensure data is being passed correctly
4. Check canvas element IDs match JavaScript

### Styling Issues

**Problem**: CSS not applying correctly

**Solution**:
1. Clear browser cache (Ctrl+Shift+Delete)
2. Clear Moodle cache: `php admin/cli/purge_caches.php`
3. Check CSS file is included in template
4. Verify CSS selectors match HTML elements

### Data Not Displaying

**Problem**: KPI values show as empty or 0

**Solution**:
1. Check API is returning data
2. Verify database queries are correct
3. Check IOMAD filtering is applied
4. Review error log: `/var/www/html/moodledata/error.log`

### Mobile Layout Issues

**Problem**: Design doesn't look good on mobile

**Solution**:
1. Test with browser DevTools (F12)
2. Check media queries are correct
3. Verify grid layouts are responsive
4. Test on actual mobile device

---

## Performance Optimization

### Reduce Load Time

1. **Cache Dashboard Data**:
   ```php
   $cache = cache::make('local_manireports', 'dashboard');
   $data = $cache->get('dashboard_data');
   if (!$data) {
       $data = $this->get_dashboard_data();
       $cache->set('dashboard_data', $data, 3600); // 1 hour
   }
   ```

2. **Lazy Load Charts**:
   ```javascript
   // Only render charts when visible
   var observer = new IntersectionObserver(function(entries) {
       entries.forEach(function(entry) {
           if (entry.isIntersecting) {
               renderChart(entry.target);
               observer.unobserve(entry.target);
           }
       });
   });
   document.querySelectorAll('canvas').forEach(el => observer.observe(el));
   ```

3. **Optimize Database Queries**:
   - Add indexes to frequently queried columns
   - Use JOINs instead of subqueries
   - Limit result sets with pagination

### Monitor Performance

```bash
# Check query performance
php admin/cli/performance_monitor.php

# Monitor server resources
top
free -h
df -h
```

---

## Next Steps

1. **Choose Your Design**: Select V1, V2, or V3
2. **Create Templates**: Convert HTML to Mustache
3. **Implement Renderer**: Create PHP renderer class
4. **Connect Data**: Implement API calls
5. **Add Charts**: Integrate Chart.js
6. **Test**: Verify on all devices
7. **Deploy**: Push to production
8. **Gather Feedback**: Collect user feedback
9. **Iterate**: Make improvements based on feedback

---

## Support & Resources

- **Design Files**: `local/manireports/designs/`
- **Documentation**: `local/manireports/docs/`
- **API Reference**: `local/manireports/classes/api/`
- **Moodle Docs**: https://docs.moodle.org/
- **Chart.js Docs**: https://www.chartjs.org/

---

## Conclusion

You now have three beautiful, production-ready dashboard designs. Choose the one that best fits your needs, follow the implementation steps, and deploy with confidence!

Happy coding! 🚀

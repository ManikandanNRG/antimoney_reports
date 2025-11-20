# Chart.js Integration Guide

## Overview

All three dashboard designs now include working Chart.js visualizations. The charts are rendered using an AMD module that loads Chart.js from CDN and creates responsive, interactive charts.

## Charts Included

### Dashboard V1 - Modern Professional

1. **Course Completion Trends** (Bar Chart)
   - Shows completed vs in-progress courses over 6 weeks
   - Dual-axis bar chart with blue and orange colors
   - Canvas ID: `completionChart`

2. **Course Distribution** (Doughnut Chart)
   - Shows distribution across 5 course categories
   - Colorful segments with legend
   - Canvas ID: `distributionChart`

3. **Engagement by Department** (Line Chart)
   - Shows engagement trends over 6 months
   - Dual-line chart with fill areas
   - Canvas ID: `engagementTrendChart`

4. **Student Performance** (Horizontal Bar Chart)
   - Shows performance distribution across 5 levels
   - Color-coded by performance level
   - Canvas ID: `performanceChart`

### Dashboard V2 - Colorful & Engaging

1. **Engagement Trends** (Line Chart)
   - Active users and engagement hours over 6 months
   - Dual-line chart with gradient fills
   - Canvas ID: `engagementTrendChart`

2. **Course Status** (Pie Chart)
   - Shows course status distribution
   - Active, Completed, Archived, Draft
   - Canvas ID: `courseStatusChart`

### Dashboard V3 - Data-Rich & Compact

1. **Engagement Trend** (Line Chart)
   - Similar to V2 engagement trends
   - Canvas ID: `engagementTrendChart`

2. **Performance Distribution** (Horizontal Bar Chart)
   - Student performance levels
   - Canvas ID: `performanceChart`

## Technical Implementation

### AMD Module: `dashboard_charts.js`

Located at: `local/manireports/amd/src/dashboard_charts.js`

**Features:**
- Automatic Chart.js loading from CDN
- Promise-based chart rendering
- Responsive chart configurations
- Multiple chart types (bar, line, pie, doughnut)

**Main Functions:**
```javascript
// Initialize all charts
dashboard_charts.init();

// Render individual charts
dashboard_charts.renderCompletionTrendChart();
dashboard_charts.renderDistributionChart();
dashboard_charts.renderEngagementTrendChart();
dashboard_charts.renderCourseStatusChart();
dashboard_charts.renderPerformanceChart();
```

### Chart.js Configuration

Each chart includes:
- **Responsive**: Adapts to container size
- **Maintain Aspect Ratio**: Keeps consistent proportions
- **Legend**: Positioned for optimal layout
- **Grid**: Subtle gridlines for readability
- **Animations**: Smooth transitions on load

### Data Format

Charts use sample data. To connect real data:

```javascript
// In dashboard_charts.js, replace sample data with API calls
var renderCompletionTrendChart = function() {
    // Fetch real data from API
    $.ajax({
        url: '/local/manireports/ui/ajax/get_completion_data.php',
        success: function(data) {
            // Use data.labels and data.datasets
            new Chart(ctx, {
                type: 'bar',
                data: data,
                options: { /* ... */ }
            });
        }
    });
};
```

## Chart Types Used

### 1. Bar Chart
- **Use Case**: Comparing values across categories
- **Example**: Course completion trends
- **Configuration**: `type: 'bar'`

### 2. Line Chart
- **Use Case**: Showing trends over time
- **Example**: Engagement trends
- **Configuration**: `type: 'line'`

### 3. Pie Chart
- **Use Case**: Showing proportions of a whole
- **Example**: Course status distribution
- **Configuration**: `type: 'pie'`

### 4. Doughnut Chart
- **Use Case**: Similar to pie, with center space
- **Example**: Course distribution
- **Configuration**: `type: 'doughnut'`

### 5. Horizontal Bar Chart
- **Use Case**: Comparing values with long labels
- **Example**: Performance distribution
- **Configuration**: `type: 'bar'` with `indexAxis: 'y'`

## Color Scheme

### Professional Colors (V1)
- Primary Blue: `#007bff`
- Success Green: `#28a745`
- Warning Orange: `#ffc107`
- Danger Red: `#dc3545`
- Purple: `#6f42c1`

### Vibrant Colors (V2)
- Bright Blue: `#4facfe`
- Bright Green: `#43e97b`
- Bright Orange: `#fa709a`
- Bright Yellow: `#fee140`

## Customization

### Change Chart Data

Edit the `data` property in each chart function:

```javascript
data: {
    labels: ['Label1', 'Label2', 'Label3'],
    datasets: [{
        label: 'Dataset Name',
        data: [10, 20, 30],
        backgroundColor: '#007bff'
    }]
}
```

### Change Chart Colors

Modify `backgroundColor` and `borderColor`:

```javascript
backgroundColor: [
    '#007bff',  // Blue
    '#28a745',  // Green
    '#ffc107'   // Orange
]
```

### Change Chart Options

Adjust responsive behavior, legend position, scales:

```javascript
options: {
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
        legend: {
            position: 'top'  // or 'bottom', 'left', 'right'
        }
    }
}
```

## Performance Considerations

### Chart.js Loading
- Loaded from CDN: `https://cdn.jsdelivr.net/npm/chart.js@4.4.0`
- Loaded only once, cached by browser
- Fallback error handling if CDN unavailable

### Rendering
- Charts render asynchronously
- No blocking of page load
- Responsive to window resize

### Data Updates
- Use `chart.data = newData; chart.update();` to refresh
- Smooth animations on data changes
- No page reload required

## Testing

### Local Testing
```bash
# Clear caches
php admin/cli/purge_caches.php

# Visit dashboard
# https://dev.aktrea.net/local/manireports/designs/dashboard_v1_modern.php
```

### Browser Console
```javascript
// Check if Chart.js loaded
console.log(typeof Chart);  // Should be 'function'

// Check if charts rendered
console.log(document.getElementById('completionChart'));
```

### Troubleshooting

**Charts not rendering:**
1. Check browser console for errors
2. Verify Chart.js loaded: `console.log(Chart)`
3. Check canvas element IDs match
4. Verify data is valid

**Charts look wrong:**
1. Check responsive settings
2. Verify color values are valid hex codes
3. Check data array lengths match labels

## Integration with Real Data

### Step 1: Create AJAX Endpoint

Create `local/manireports/ui/ajax/get_chart_data.php`:

```php
<?php
require_once(__DIR__ . '/../../../config.php');

$chart_type = required_param('type', PARAM_ALPHA);

// Get data from API
$api = new \local_manireports\api\report_builder();
$data = $api->get_chart_data($chart_type);

// Return JSON
header('Content-Type: application/json');
echo json_encode($data);
```

### Step 2: Update Chart Function

```javascript
var renderCompletionTrendChart = function() {
    var canvas = document.getElementById('completionChart');
    if (!canvas) return;

    loadChartJs().then(function() {
        // Fetch real data
        $.ajax({
            url: '/local/manireports/ui/ajax/get_chart_data.php',
            data: { type: 'completion' },
            dataType: 'json',
            success: function(data) {
                var ctx = canvas.getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: data,
                    options: { /* ... */ }
                });
            }
        });
    });
};
```

### Step 3: Implement API Method

In `local/manireports/classes/api/report_builder.php`:

```php
public function get_chart_data($type) {
    global $DB;
    
    switch ($type) {
        case 'completion':
            return $this->get_completion_chart_data();
        case 'engagement':
            return $this->get_engagement_chart_data();
        // ... more cases
    }
}

private function get_completion_chart_data() {
    // Query database
    // Format data for Chart.js
    return [
        'labels' => ['Week 1', 'Week 2', ...],
        'datasets' => [
            [
                'label' => 'Completed',
                'data' => [45, 52, ...],
                'backgroundColor' => '#007bff'
            ]
        ]
    ];
}
```

## Next Steps

1. **Test Charts**: Verify all charts render correctly
2. **Connect Real Data**: Replace sample data with API calls
3. **Customize Colors**: Adjust to match your branding
4. **Add Interactivity**: Implement drill-down and filtering
5. **Optimize Performance**: Cache chart data, lazy-load charts

## Resources

- **Chart.js Docs**: https://www.chartjs.org/docs/latest/
- **Moodle AMD**: https://docs.moodle.org/dev/AMD_modules
- **Chart.js CDN**: https://cdn.jsdelivr.net/npm/chart.js@4.4.0

## Support

For issues or questions:
1. Check browser console for errors
2. Review Chart.js documentation
3. Check Moodle error log: `/var/www/html/moodledata/error.log`
4. Test with sample data first before connecting real data

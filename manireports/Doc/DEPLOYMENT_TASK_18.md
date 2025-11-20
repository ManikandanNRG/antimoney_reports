# Deployment Guide: Task 18 - Custom Dashboard Builder

## Overview
Task 18 implements the custom dashboard builder feature, allowing users to create personalized dashboards with drag-and-drop widgets.

## Components Completed

### 18.1 Dashboard Management API ✅
- `classes/api/dashboard_manager.php` - CRUD operations for dashboards

### 18.2 Widget Configuration System ✅
- `classes/api/widget_manager.php` - Widget management and configuration

### 18.3 Dashboard Builder UI ✅
- `ui/dashboard_builder.php` - Dashboard builder interface

### 18.4 Dashboard Builder JavaScript ✅
- `amd/src/dashboard_builder.js` - Client-side dashboard builder logic

## Deployment Steps

### 1. Upload Files to Server

```bash
# SSH into your EC2 instance
ssh user@your-ec2-instance.com

# Navigate to Moodle directory
cd /var/www/html/moodle/local/manireports

# Upload the following files (via Git/SCP/SFTP):
# - classes/api/dashboard_manager.php
# - classes/api/widget_manager.php
# - ui/dashboard_builder.php
# - amd/src/dashboard_builder.js
# - lang/en/local_manireports.php (updated)
```

### 2. Set Proper Permissions

```bash
sudo chown -R www-data:www-data /var/www/html/moodle/local/manireports/
sudo chmod -R 755 /var/www/html/moodle/local/manireports/
```

### 3. Build AMD JavaScript Module

```bash
# From Moodle root directory
cd /var/www/html/moodle

# Build the AMD module
sudo -u www-data npx grunt amd --root=local/manireports

# If grunt is not installed, install it first:
# sudo npm install -g grunt-cli
```

### 4. Clear Moodle Caches

```bash
# Clear all caches
sudo -u www-data php admin/cli/purge_caches.php
```

### 5. Verify Database Tables

The dashboard tables should already exist from previous deployments:

```bash
mysql -u moodle_user -p moodle_db -e "
SELECT TABLE_NAME 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'moodle_db' 
AND TABLE_NAME LIKE 'mdl_manireports_dashboard%';
"
```

Expected tables:
- `mdl_manireports_dashboards`
- `mdl_manireports_dashboard_widgets`

## Testing Instructions

### 1. Access Dashboard Builder

```
URL: https://your-moodle-site.com/local/manireports/ui/dashboard_builder.php
```

### 2. Test Creating a New Dashboard

1. Navigate to the dashboard builder URL
2. Enter a dashboard name (e.g., "My Custom Dashboard")
3. Select dashboard scope (personal/global/company)
4. Click on widget types in the palette to add widgets
5. Configure each widget:
   - Set title
   - Choose data source
   - Set width and height
6. Drag widgets to reorder them
7. Resize widgets using the resize handle
8. Click "Save Dashboard"

### 3. Test Editing an Existing Dashboard

1. Navigate to dashboard list
2. Click "Edit" on an existing dashboard
3. Modify widget configuration
4. Add/remove widgets
5. Save changes

### 4. Test Widget Operations

- **Add Widget**: Click widget type in palette → Configure → Save
- **Edit Widget**: Click edit icon on widget → Modify → Save
- **Remove Widget**: Click remove icon → Confirm
- **Reorder Widgets**: Drag widget by header
- **Resize Widget**: Drag resize handle in bottom-right corner

### 5. Verify JavaScript Functionality

Open browser console (F12) and check for:
- No JavaScript errors
- AJAX requests completing successfully
- Proper event handling (drag, resize, save)

### 6. Test Unsaved Changes Warning

1. Make changes to dashboard
2. Try to navigate away or close tab
3. Verify warning message appears

## Troubleshooting

### JavaScript Not Loading

```bash
# Rebuild AMD modules
cd /var/www/html/moodle
sudo -u www-data npx grunt amd --root=local/manireports

# Clear caches
sudo -u www-data php admin/cli/purge_caches.php

# Check browser console for errors
```

### Drag-and-Drop Not Working

- Verify jQuery UI is loaded (Moodle includes it by default)
- Check browser console for JavaScript errors
- Ensure proper permissions on the dashboard

### Save Operation Failing

```bash
# Check error logs
tail -f /var/www/html/moodledata/error.log

# Verify database tables exist
mysql -u moodle_user -p moodle_db -e "SHOW TABLES LIKE 'mdl_manireports_dashboard%';"

# Check file permissions
ls -la /var/www/html/moodle/local/manireports/classes/api/
```

### Widget Configuration Modal Not Appearing

- Check if Bootstrap modal JavaScript is loaded
- Verify no JavaScript errors in console
- Clear browser cache

## Performance Considerations

- Dashboard builder uses jQuery sortable (lightweight)
- Widget configurations stored as JSON (efficient)
- AJAX calls for save operations (no page reload)
- Minimal DOM manipulation for better performance

## Security Notes

- All operations require proper capabilities
- SESSKEY validation on all AJAX requests
- Input validation on widget configurations
- Company isolation applied to dashboard scope

## Browser Compatibility

Tested on:
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Next Steps

After successful deployment:

1. Test with different user roles (admin, manager, teacher)
2. Verify IOMAD company isolation works correctly
3. Test dashboard rendering with actual data
4. Create sample dashboards for different roles
5. Document dashboard builder for end users

## Rollback Plan

If issues occur:

```bash
# Restore previous version of files
# Clear caches
sudo -u www-data php admin/cli/purge_caches.php

# Check error logs
tail -f /var/www/html/moodledata/error.log
```

## Success Criteria

- ✅ Dashboard builder UI loads without errors
- ✅ Widgets can be added, edited, and removed
- ✅ Drag-and-drop reordering works
- ✅ Widget resizing works
- ✅ Dashboard saves successfully
- ✅ Unsaved changes warning appears
- ✅ No JavaScript errors in console
- ✅ Proper capability checks enforced

## Related Tasks

- Task 13: Role-based dashboards (foundation)
- Task 15: Chart rendering system (widget data)
- Task 16: AJAX filter system (widget filtering)

## Documentation

User documentation should cover:
- How to create a new dashboard
- How to add and configure widgets
- How to arrange and resize widgets
- How to save and share dashboards
- Dashboard scope options (personal/global/company)

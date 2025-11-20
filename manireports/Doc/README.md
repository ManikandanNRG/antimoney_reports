# ManiReports - Advanced Analytics and Reporting for Moodle/IOMAD

ManiReports is a self-hosted advanced analytics and reporting plugin for Moodle/IOMAD that combines enterprise-grade analytics capabilities with modern UI/UX and simple deployment.

## Features

- **Role-Based Dashboards**: Admin, Company Manager, Teacher, and Student views
- **Multi-Tenant Support**: Full IOMAD company isolation and filtering
- **Prebuilt Reports**: Course completion, user progress, SCORM analytics, engagement metrics, quiz attempts
- **Custom Report Builder**: SQL and GUI modes for creating custom reports
- **Time Tracking**: JavaScript heartbeat and log-based fallback
- **Scheduled Reports**: Automated report generation and email delivery
- **Export Formats**: CSV, XLSX, and PDF export capabilities
- **Predictive Analytics**: Rule-based at-risk learner detection
- **SCORM Deep Analytics**: Detailed tracking of SCORM activities

## Requirements

- **Moodle**: 4.0 – 4.4 LTS
- **IOMAD**: 4.0 – 4.4 (optional)
- **PHP**: 7.4 – 8.2
- **Database**: MariaDB/MySQL (primary), PostgreSQL (supported)

## Installation

1. Copy the plugin to your Moodle installation:
   ```bash
   cd /var/www/html/moodle/local/
   # Upload manireports folder via Git/SCP/SFTP
   ```

2. Set proper permissions:
   ```bash
   sudo chown -R www-data:www-data manireports/
   sudo chmod -R 755 manireports/
   ```

3. Clear Moodle caches:
   ```bash
   sudo -u www-data php admin/cli/purge_caches.php
   ```

4. Run database upgrade:
   ```bash
   sudo -u www-data php admin/cli/upgrade.php --non-interactive
   ```

5. Configure the plugin at: Site administration > Plugins > Local plugins > ManiReports

## Configuration

After installation, configure the following settings:

- **Time Tracking**: Enable/disable time tracking and set heartbeat interval
- **Cache Settings**: Configure TTL for different report types
- **Report Execution**: Set query timeout and concurrent execution limits
- **Data Retention**: Configure retention periods for audit logs and report runs
- **At-Risk Detection**: Set thresholds for identifying at-risk learners

## Usage

Access ManiReports from the navigation menu. The dashboard displayed depends on your role:

- **Administrators**: Site-wide analytics and system management
- **Company Managers**: Company-specific reporting (IOMAD)
- **Teachers**: Student progress and course analytics
- **Students**: Personal progress tracking

## Scheduled Tasks

The plugin includes several scheduled tasks that run automatically:

- `cache_builder`: Pre-aggregates heavy metrics (every 6 hours)
- `report_scheduler`: Executes scheduled reports (every 15 minutes)
- `time_aggregation`: Aggregates time tracking data (hourly)
- `scorm_summary`: Aggregates SCORM data (hourly)
- `cleanup_old_data`: Cleans up old data based on retention settings (daily)

## Support

For issues, questions, or feature requests, please contact the development team.

## License

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

## Copyright

Copyright © 2024 ManiReports

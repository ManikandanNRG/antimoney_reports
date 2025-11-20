# Deployment Environment

## Server Configuration

**Environment**: AWS EC2 (Remote Server)
**Deployment Type**: Live production/staging server (NOT local development)

## Critical Deployment Rules

### Remote Server Considerations

- **No localhost assumptions**: All URLs, paths, and configurations must work on remote servers
- **File permissions**: Ensure proper permissions for Moodle data directory and plugin files
- **Database access**: Use remote database connection settings
- **Cron setup**: Configure server cron (not Moodle's built-in cron UI)
- **Cache clearing**: Use CLI commands via SSH, not browser-based cache clearing

### AWS EC2 Specific Requirements

- **SSH Access**: All deployment and testing must be done via SSH
- **File Transfer**: Use SCP, SFTP, or Git for file deployment
- **Web Server**: Apache/Nginx configuration must be verified
- **PHP Configuration**: Verify PHP version and extensions on server
- **Database**: Ensure MariaDB/MySQL is accessible and configured

## Deployment Workflow

### Initial Deployment
```bash
# SSH into EC2 instance
ssh user@your-ec2-instance.com

# Navigate to Moodle directory
cd /var/www/html/moodle  # or your Moodle path

# Deploy plugin files
cd local/
# Upload manireports folder via Git/SCP/SFTP

# Set proper permissions
sudo chown -R www-data:www-data manireports/
sudo chmod -R 755 manireports/

# Clear Moodle caches
sudo -u www-data php admin/cli/purge_caches.php

# Run database upgrade
sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

### Testing on Remote Server
```bash
# Test scheduled tasks
sudo -u www-data php admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\cache_builder

# Check logs
tail -f /var/www/html/moodledata/error.log

# Verify database tables
mysql -u moodle_user -p moodle_db -e "SHOW TABLES LIKE 'mdl_manireports%';"
```

### Cron Configuration
```bash
# Edit crontab for www-data user
sudo crontab -u www-data -e

# Add Moodle cron (if not already present)
*/5 * * * * /usr/bin/php /var/www/html/moodle/admin/cli/cron.php >/dev/null 2>&1
```

## Development Workflow for Remote Server

### Code Changes
1. Make changes locally in IDE (Kiro AI)
2. Test syntax and logic locally if possible
3. Deploy to EC2 via Git/SCP
4. Clear caches on server
5. Test functionality via browser
6. Check error logs for issues

### Debugging on Remote Server
```bash
# Enable debugging in Moodle config.php
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

# Monitor error logs in real-time
tail -f /var/www/html/moodledata/error.log

# Check PHP error logs
tail -f /var/log/apache2/error.log  # or nginx error log
```

## File Paths and URLs

### Absolute Paths (Server-Specific)
- Moodle root: `/var/www/html/moodle` (verify actual path)
- Moodle data: `/var/www/html/moodledata` (verify actual path)
- Plugin path: `/var/www/html/moodle/local/manireports`

### Web URLs
- Use `$CFG->wwwroot` for all URL generation
- Never hardcode domain names or IPs
- Use `moodle_url` class for all links

## Security Considerations for Production

- **Never commit sensitive data**: No passwords, API keys, or tokens in code
- **Use environment variables**: For sensitive configuration
- **Restrict file permissions**: 755 for directories, 644 for files
- **Disable debug mode**: In production after testing
- **Use HTTPS**: Ensure SSL/TLS is configured
- **Firewall rules**: Restrict database access to localhost or specific IPs

## Performance on Production Server

- **Enable opcode caching**: Verify PHP OPcache is enabled
- **Database optimization**: Ensure proper indexing and query optimization
- **CDN for assets**: Consider CloudFront for static assets (optional)
- **Monitor resources**: Watch CPU, memory, and disk usage
- **Scheduled tasks**: Run during off-peak hours

## Backup Strategy

### Before Major Changes
```bash
# Backup database
mysqldump -u moodle_user -p moodle_db > backup_$(date +%Y%m%d_%H%M%S).sql

# Backup plugin files
tar -czf manireports_backup_$(date +%Y%m%d_%H%M%S).tar.gz /var/www/html/moodle/local/manireports/

# Backup Moodle data directory (if needed)
tar -czf moodledata_backup_$(date +%Y%m%d_%H%M%S).tar.gz /var/www/html/moodledata/
```

## Rollback Plan

If deployment fails:
1. Restore database from backup
2. Restore plugin files from backup
3. Clear all caches
4. Verify Moodle functionality

## AI Agent Instructions for Remote Deployment

When generating code for this project:

- **Assume remote server environment**: No `localhost` references
- **Use Moodle APIs exclusively**: No direct file system operations outside Moodle
- **Provide deployment commands**: Include SSH and CLI commands for testing
- **Consider permissions**: All file operations must respect server permissions
- **Use relative paths**: Within Moodle, use relative paths or Moodle path functions
- **Test commands**: Provide CLI commands for testing features remotely
- **Log everything**: Ensure proper logging for remote debugging

## Common Issues and Solutions

### Permission Denied Errors
```bash
sudo chown -R www-data:www-data /var/www/html/moodle/local/manireports/
sudo chmod -R 755 /var/www/html/moodle/local/manireports/
```

### Cache Issues
```bash
sudo -u www-data php admin/cli/purge_caches.php
```

### Database Connection Issues
- Verify database credentials in `config.php`
- Check database server is running
- Verify firewall rules allow database connections

### Scheduled Tasks Not Running
- Verify cron is configured correctly
- Check cron logs: `grep CRON /var/log/syslog`
- Run tasks manually to test: `sudo -u www-data php admin/cli/scheduled_task.php --execute=...`

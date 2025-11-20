# ManiReports Developer Documentation

## Table of Contents

1. [Architecture](#architecture)
2. [API Reference](#api-reference)
3. [Database Schema](#database-schema)
4. [Extending ManiReports](#extending-manireports)
5. [Testing](#testing)
6. [Contributing](#contributing)

## Architecture

### Directory Structure

```
local/manireports/
├── classes/
│   ├── api/              # Core business logic
│   ├── charts/           # Chart generation
│   ├── output/           # Rendering layer
│   ├── reports/          # Report implementations
│   ├── tasks/            # Scheduled tasks
│   ├── privacy/          # GDPR compliance
│   └── external/         # Web service API
├── db/                   # Database definitions
├── amd/                  # JavaScript (AMD modules)
├── templates/            # Mustache templates
├── ui/                   # User interface entry points
├── lang/                 # Language strings
├── tests/                # PHPUnit tests
├── cli/                  # CLI scripts
└── docs/                 # Documentation
```

### Design Patterns

**Factory Pattern**: Chart creation (`chart_factory.php`)  
**Repository Pattern**: Data access through API classes  
**MVC Pattern**: Separation of concerns (Model-View-Controller)  
**Dependency Injection**: Pass dependencies to constructors  

### Data Flow

```
User Request → UI Page → API Class → Database → Renderer → Template → Response
```

## API Reference

### Report Builder API

**Class**: `\local_manireports\api\report_builder`

**Execute Report**:
```php
$builder = new \local_manireports\api\report_builder();
$result = $builder->execute_report($reportid, $params, $userid, $page, $perpage);

// Returns:
// [
//     'data' => [...],
//     'columns' => [...],
//     'total' => 100,
//     'cached' => false,
//     'executiontime' => 0.5
// ]
```

**Save Report**:
```php
$report = new stdClass();
$report->name = 'My Report';
$report->type = 'sql';
$report->sqlquery = 'SELECT ...';

$reportid = $builder->save_report($report, $userid);
```

### Analytics Engine API

**Class**: `\local_manireports\api\analytics_engine`

**Calculate Engagement Score**:
```php
$engine = new \local_manireports\api\analytics_engine();
$score = $engine->calculate_engagement_score($userid, $courseid);

// Returns:
// [
//     'score' => 75.5,
//     'components' => [
//         'time' => 80,
//         'login' => 70,
//         'completion' => 76
//     ]
// ]
```

**Detect At-Risk Learners**:
```php
$atrisk = $engine->detect_at_risk_learners($courseid);

// Returns array of user objects with risk scores
```

### Time Engine API

**Class**: `\local_manireports\api\time_engine`

**Record Heartbeat**:
```php
$engine = new \local_manireports\api\time_engine();
$engine->record_heartbeat($userid, $courseid);
```

**Get User Time**:
```php
$time = $engine->get_user_time($userid, $courseid, $startdate, $enddate);

// Returns total seconds
```

### Cache Manager API

**Class**: `\local_manireports\api\cache_manager`

**Get Cached Data**:
```php
$manager = new \local_manireports\api\cache_manager();
$data = $manager->get_cached_data($cachekey);
```

**Set Cached Data**:
```php
$manager->set_cached_data($cachekey, $data, $type, $objectid, $ttl);
```

### Export Engine API

**Class**: `\local_manireports\api\export_engine`

**Export Report**:
```php
$engine = new \local_manireports\api\export_engine();
$file = $engine->export($data, $columns, $format, $filename);

// Formats: 'csv', 'xlsx', 'pdf'
// Returns: stored_file object
```

### Security Validator API

**Class**: `\local_manireports\api\security_validator`

**Validate Input**:
```php
use \local_manireports\api\security_validator;

$userid = security_validator::validate_input('userid', PARAM_INT, 0, true);
```

**Check Rate Limit**:
```php
security_validator::check_rate_limit($identifier, 60, 60);
```

### Error Handler API

**Class**: `\local_manireports\api\error_handler`

**Execute with Retry**:
```php
use \local_manireports\api\error_handler;

$result = error_handler::execute_with_retry(function() {
    return perform_operation();
}, 3, 'Operation context');
```

**Log Error**:
```php
try {
    // Operation
} catch (\Exception $e) {
    error_handler::log_error($e, 'Context', ['data' => 'value']);
}
```

## Database Schema

### Core Tables

**manireports_customreports**:
```sql
CREATE TABLE mdl_manireports_customreports (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),
    description TEXT,
    type VARCHAR(20),  -- 'sql' or 'gui'
    sqlquery TEXT,
    configjson TEXT,
    createdby BIGINT,
    timecreated BIGINT,
    timemodified BIGINT
);
```

**manireports_schedules**:
```sql
CREATE TABLE mdl_manireports_schedules (
    id BIGINT PRIMARY KEY,
    reportid BIGINT,
    frequency VARCHAR(20),  -- 'daily', 'weekly', 'monthly'
    nextrun BIGINT,
    format VARCHAR(10),  -- 'csv', 'xlsx', 'pdf'
    enabled TINYINT,
    timecreated BIGINT
);
```

**manireports_usertime_daily**:
```sql
CREATE TABLE mdl_manireports_usertime_daily (
    id BIGINT PRIMARY KEY,
    userid BIGINT,
    courseid BIGINT,
    date INT,
    totalseconds INT,
    UNIQUE KEY userid_courseid_date (userid, courseid, date)
);
```

**manireports_audit_logs**:
```sql
CREATE TABLE mdl_manireports_audit_logs (
    id BIGINT PRIMARY KEY,
    userid BIGINT,
    action VARCHAR(50),
    objecttype VARCHAR(50),
    objectid BIGINT,
    details TEXT,
    timecreated BIGINT
);
```

### Indexes

**Performance Indexes**:
- `userid_courseid_idx` on usertime tables
- `timecreated_idx` on audit logs
- `reportid_idx` on schedules
- `cachekey_idx` on cache summary

## Extending ManiReports

### Creating a Custom Report

**1. Extend Base Report**:
```php
namespace local_manireports\reports;

class my_custom_report extends base_report {
    
    public function get_name() {
        return get_string('mycustomreport', 'local_manireports');
    }
    
    public function get_sql() {
        return "SELECT u.id, u.firstname, u.lastname, c.fullname
                FROM {user} u
                JOIN {user_enrolments} ue ON ue.userid = u.id
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {course} c ON c.id = e.courseid
                WHERE c.id = :courseid";
    }
    
    public function get_columns() {
        return [
            'id' => get_string('userid', 'local_manireports'),
            'firstname' => get_string('firstname'),
            'lastname' => get_string('lastname'),
            'fullname' => get_string('course'),
        ];
    }
    
    public function get_filters() {
        return [
            'courseid' => [
                'type' => 'course',
                'required' => true,
            ],
        ];
    }
}
```

**2. Register Report**:
Add to report factory or configuration.

### Creating a Custom Chart

**1. Extend Base Chart**:
```php
namespace local_manireports\charts;

class my_custom_chart extends base_chart {
    
    public function get_chart_data() {
        // Return data in Chart.js format
        return [
            'labels' => ['Jan', 'Feb', 'Mar'],
            'datasets' => [[
                'label' => 'My Data',
                'data' => [10, 20, 30],
            ]],
        ];
    }
    
    public function get_chart_config() {
        return [
            'type' => 'line',
            'options' => [
                'responsive' => true,
            ],
        ];
    }
}
```

### Creating a Scheduled Task

**1. Create Task Class**:
```php
namespace local_manireports\task;

class my_custom_task extends \core\task\scheduled_task {
    
    public function get_name() {
        return get_string('mycustomtask', 'local_manireports');
    }
    
    public function execute() {
        // Task logic here
        mtrace('Executing my custom task...');
        
        // Use error handling
        $handler = new \local_manireports\api\error_handler();
        try {
            $handler->execute_with_retry(function() {
                $this->process_data();
            }, 3, 'My custom task');
        } catch (\Exception $e) {
            $handler->handle_task_failure(get_class($this), $e);
            throw $e;
        }
    }
    
    private function process_data() {
        // Implementation
    }
}
```

**2. Register in db/tasks.php**:
```php
$tasks = [
    [
        'classname' => 'local_manireports\task\my_custom_task',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '2',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
```

### Adding a Web Service

**1. Define External Function**:
```php
namespace local_manireports\external;

class my_api extends \external_api {
    
    public static function get_data_parameters() {
        return new \external_function_parameters([
            'userid' => new \external_value(PARAM_INT, 'User ID'),
        ]);
    }
    
    public static function get_data($userid) {
        // Validate parameters
        $params = self::validate_parameters(
            self::get_data_parameters(),
            ['userid' => $userid]
        );
        
        // Check capability
        $context = \context_system::instance();
        require_capability('local/manireports:viewadmindashboard', $context);
        
        // Return data
        return [
            'userid' => $params['userid'],
            'data' => [],
        ];
    }
    
    public static function get_data_returns() {
        return new \external_single_structure([
            'userid' => new \external_value(PARAM_INT, 'User ID'),
            'data' => new \external_multiple_structure(
                new \external_value(PARAM_RAW, 'Data')
            ),
        ]);
    }
}
```

**2. Register in db/services.php**:
```php
$functions = [
    'local_manireports_get_data' => [
        'classname' => 'local_manireports\external\my_api',
        'methodname' => 'get_data',
        'description' => 'Get data',
        'type' => 'read',
        'ajax' => true,
    ],
];
```

## Testing

### PHPUnit Tests

**Create Test Class**:
```php
namespace local_manireports;

class my_test extends \advanced_testcase {
    
    public function setUp(): void {
        $this->resetAfterTest(true);
    }
    
    public function test_my_function() {
        // Create test data
        $user = $this->getDataGenerator()->create_user();
        
        // Test function
        $result = my_function($user->id);
        
        // Assert
        $this->assertNotEmpty($result);
    }
}
```

**Run Tests**:
```bash
vendor/bin/phpunit --testsuite local_manireports_testsuite
```

### JavaScript Tests

**Create Test**:
```javascript
define(['local_manireports/mymodule'], function(MyModule) {
    describe('MyModule', function() {
        it('should do something', function() {
            var result = MyModule.doSomething();
            expect(result).toBe(true);
        });
    });
});
```

## Contributing

### Code Standards

- Follow Moodle coding guidelines
- PSR-1 and PSR-2 with Moodle extensions
- PHPDoc comments on all classes and methods
- 4 spaces indentation (no tabs)

### Pull Request Process

1. Fork the repository
2. Create feature branch
3. Write tests
4. Follow coding standards
5. Submit pull request
6. Address review comments

### Code Review Checklist

- [ ] Follows Moodle coding standards
- [ ] PHPDoc comments complete
- [ ] Tests written and passing
- [ ] Security best practices followed
- [ ] Performance considered
- [ ] Documentation updated

---

**Version**: 1.0  
**Last Updated**: 2024

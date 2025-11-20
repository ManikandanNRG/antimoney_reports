# AI Workflow and Project Completion Guide

## Project Completion Strategy

### Phase-Based Implementation

Follow the implementation phases strictly to avoid scope creep:

**Phase 1: MVP (Minimum Viable Product)**
- Focus: Core functionality only
- Timeline: Complete this first before moving to Phase 2
- Deliverables: Basic dashboards, prebuilt reports, IOMAD filtering, scheduled reports

**Phase 2: Advanced Features**
- Start only after Phase 1 is fully tested and working
- Add: Time tracking, caching, export formats, audit logs

**Phase 3: Optional Features**
- Implement only if explicitly requested by user
- Requires user approval before starting

## AI Agent Workflow Rules

### Before Writing Any Code

1. **Verify the feature is in scope**: Check product.md Feature Boundaries
2. **Confirm the phase**: Ensure prerequisites from earlier phases are complete
3. **Check existing structure**: Review structure.md to understand where code goes
4. **Review technical constraints**: Verify tech.md requirements are met

### When Writing Code

1. **Start with the smallest unit**: Write one class/function at a time
2. **Follow the contract**: Extend base classes, implement required methods
3. **Apply IOMAD filters**: Every report must include company isolation
4. **Add capability checks**: Every UI page and AJAX endpoint needs permission checks
5. **Use Moodle APIs only**: No custom database connections or file operations
6. **Write PHPDoc comments**: Document all classes and methods
7. **Handle errors gracefully**: Use try-catch and log errors properly

### After Writing Code

1. **Provide deployment commands**: Include SSH commands for testing on EC2
2. **Suggest testing steps**: Provide specific URLs and actions to test
3. **Check for security issues**: Verify input validation and output escaping
4. **Verify performance**: Ensure queries are optimized and indexed

## Kiro AI Specific Instructions

### Using Kiro AI Effectively

**Leverage Kiro's Context**
- Use `#File` to reference specific files
- Use `#Folder` to reference entire directories
- Use `#Codebase` for project-wide searches
- Use `#Problems` to see current errors

**Iterative Development**
- Ask Kiro to implement one feature at a time
- Test each feature before moving to the next
- Use Kiro to fix errors immediately when they appear

**Code Generation Best Practices**
- Request complete files, not snippets
- Ask for deployment commands with each feature
- Request testing instructions for remote server
- Have Kiro generate database migration scripts when needed

### Example Kiro Prompts

**Good Prompts (Specific and Scoped)**
```
"Implement the course_completion.php report class following the base_report 
contract. Include IOMAD filtering and pagination. Provide deployment commands 
for testing on EC2."

"Create the dashboard.php UI file with capability checks for the admin 
dashboard. Include the Mustache template and AMD JavaScript module."

"Write the time_aggregation scheduled task that aggregates session data 
into daily summaries. Include the db/tasks.php registration."
```

**Bad Prompts (Too Vague or Out of Scope)**
```
"Build the entire reporting system"  # Too broad
"Add AI-powered predictions"  # Not in approved scope
"Create a React dashboard"  # Violates tech stack rules
"Make it look modern"  # Too vague
```

## Preventing AI Deviation

### Strict Boundaries

**Always Reference Steering Docs**
- Before each major task, remind AI to review steering docs
- Reference specific sections: "Follow structure.md Report Builder Contract"
- Cite rules: "Per tech.md Coding Enforcement Rules, use Moodle DB API only"

**Reject Out-of-Scope Suggestions**
- If AI suggests features not in product.md, reject immediately
- If AI proposes architectural changes, verify against structure.md
- If AI recommends different libraries, check tech.md allowed technologies

**Incremental Validation**
- Test each component immediately after generation
- Deploy to EC2 and verify functionality
- Check error logs before proceeding
- Fix issues before adding new features

## Task Breakdown for Quick Completion

### Week 1: Foundation
- [ ] Create plugin structure (version.php, settings.php, lib.php)
- [ ] Implement database schema (db/install.xml)
- [ ] Define capabilities (db/access.php)
- [ ] Create base classes (base_report.php, base_chart.php)
- [ ] Implement IOMAD filter (iomad_filter.php)
- [ ] Deploy and test on EC2

### Week 2: Core Reports
- [ ] Implement report_builder API
- [ ] Create course_completion report
- [ ] Create course_progress report
- [ ] Create user_engagement report
- [ ] Create SCORM summary report
- [ ] Test all reports with IOMAD filtering

### Week 3: Dashboards
- [ ] Create dashboard renderer
- [ ] Implement admin dashboard
- [ ] Implement manager dashboard
- [ ] Implement teacher dashboard
- [ ] Implement student dashboard
- [ ] Add Chart.js integration

### Week 4: Scheduling & Export
- [ ] Implement scheduler API
- [ ] Create report_scheduler task
- [ ] Implement export_engine (CSV/XLSX/PDF)
- [ ] Create schedule management UI
- [ ] Test email delivery
- [ ] Final testing and bug fixes

## Quality Checklist

Before marking any feature as complete:

- [ ] Code follows Moodle coding standards
- [ ] All inputs are validated with PARAM_* types
- [ ] All outputs are escaped properly
- [ ] Capability checks are in place
- [ ] IOMAD filtering is applied (if applicable)
- [ ] Database queries use parameter binding
- [ ] PHPDoc comments are complete
- [ ] Error handling is implemented
- [ ] Deployed and tested on EC2
- [ ] No errors in Moodle error log
- [ ] Performance is acceptable (< 3 seconds for dashboards)

## Communication with AI

### Effective Commands

**Request Implementation**
```
"Implement [specific feature] following [specific steering doc section]. 
Include deployment commands for EC2 testing."
```

**Request Review**
```
"Review the [file name] against [steering doc] requirements. 
Identify any violations or missing elements."
```

**Request Fix**
```
"Fix the error in [file name] at line [number]. 
The error is: [error message]. Follow [steering doc] guidelines."
```

**Request Testing**
```
"Provide step-by-step testing instructions for [feature] on EC2 server. 
Include SSH commands and expected results."
```

## Progress Tracking

### Use Task Lists
- Reference tasks.md for the complete implementation plan
- Check off tasks as they are completed and tested
- Update tasks.md if requirements change (with user approval)

### Document Decisions
- Keep notes on why certain approaches were chosen
- Document any deviations from the spec (with justification)
- Track issues and their resolutions

### Regular Checkpoints
- After each major component, review against requirements.md
- Verify alignment with design.md architecture
- Ensure all steering rules are being followed

## Emergency Procedures

### If AI Goes Off Track

1. **Stop immediately**: Don't let AI continue generating code
2. **Review steering docs**: Identify which rule was violated
3. **Revert changes**: Use Git or backups to restore previous state
4. **Restart with specific prompt**: Reference the exact steering rule to follow
5. **Test incrementally**: Verify each small change before proceeding

### If Deployment Fails

1. **Check error logs**: Review Moodle and server error logs
2. **Verify permissions**: Ensure file permissions are correct
3. **Clear caches**: Purge all Moodle caches
4. **Test database**: Verify tables were created correctly
5. **Rollback if needed**: Restore from backup and try again

## Success Criteria

The project is complete when:

- [ ] All MVP features are implemented and tested
- [ ] All Phase 2 features are implemented and tested (if in scope)
- [ ] All reports work with IOMAD filtering
- [ ] Scheduled reports are delivered successfully
- [ ] Export formats work correctly
- [ ] No errors in production logs
- [ ] Performance meets requirements (< 3 seconds for dashboards)
- [ ] Security audit passes (input validation, capability checks, SQL safety)
- [ ] User acceptance testing is complete
- [ ] Documentation is complete

## Final Notes

**Speed vs Quality**: Prioritize working features over perfect code. You can refactor later.

**Test Early, Test Often**: Deploy to EC2 and test after every major change.

**Follow the Plan**: Stick to tasks.md and don't skip ahead.

**Ask Questions**: If requirements are unclear, ask before implementing.

**Document Everything**: Keep notes on decisions, issues, and solutions.

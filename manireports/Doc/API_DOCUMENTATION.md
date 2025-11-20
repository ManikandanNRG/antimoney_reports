# ManiReports External API Documentation

## Overview

ManiReports provides RESTful JSON API endpoints for external integration with BI tools, mobile applications, and third-party systems. All API endpoints require authentication via Moodle web service tokens and enforce the same capability checks as the UI.

## Authentication

All API calls must include a valid Moodle web service token. Tokens can be generated in:
**Site Administration → Server → Web services → Manage tokens**

### Authentication Methods

1. **URL Parameter**: `?wstoken=YOUR_TOKEN`
2. **HTTP Header**: `Authorization: Bearer YOUR_TOKEN`

## Base URL

```
https://your-moodle-site.com/webservice/rest/server.php
```

## Common Parameters

All endpoints accept these standard parameters:

- `wstoken` (required): Web service authentication token
- `wsfunction` (required): The API function name
- `moodlewsrestformat` (optional): Response format (default: json)

## Available Endpoints

### 1. Get Dashboard Data

Retrieve dashboard data for a specific dashboard type.

**Function**: `local_manireports_get_dashboard_data`

**Capabilities Required**:
- Admin dashboard: `local/manireports:viewadmindashboard`
- Manager dashboard: `local/manireports:viewmanagerdashboard`
- Teacher dashboard: `local/manireports:viewteacherdashboard`
- Student dashboard: `local/manireports:viewstudentdashboard`

**Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| dashboardtype | string | Yes | Dashboard type: admin, manager, teacher, or student |
| filters[companyid] | int | No | Company ID for IOMAD filtering |
| filters[courseid] | int | No | Course ID filter |
| filters[startdate] | int | No | Start date timestamp |
| filters[enddate] | int | No | End date timestamp |
| page | int | No | Page number (default: 0) |
| pagesize | int | No | Items per page (default: 25, max: 100) |

**Example Request**:

```bash
curl "https://your-moodle-site.com/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_manireports_get_dashboard_data" \
  -d "moodlewsrestformat=json" \
  -d "dashboardtype=admin" \
  -d "page=0" \
  -d "pagesize=25"
```

**Example Response**:

```json
{
  "success": true,
  "data": {
    "widgets": [
      {
        "type": "kpi",
        "title": "Total Users",
        "data": "{\"value\": 1250, \"trend\": \"+5%\"}"
      },
      {
        "type": "line",
        "title": "Course Completions",
        "data": "{\"labels\": [...], \"datasets\": [...]}"
      }
    ],
    "total": 2
  },
  "pagination": {
    "page": 0,
    "pagesize": 25,
    "total": 2,
    "totalpages": 1
  }
}
```

**Error Response**:

```json
{
  "success": false,
  "error": "Invalid dashboard type specified",
  "errorcode": 400
}
```

---

### 2. Get Report Data

Execute a report and retrieve the data.

**Function**: `local_manireports_get_report_data`

**Capabilities Required**: `local/manireports:managereports`

**Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| reportid | int | Yes | Report ID to execute |
| parameters[companyid] | int | No | Company ID filter |
| parameters[courseid] | int | No | Course ID filter |
| parameters[userid] | int | No | User ID filter |
| parameters[startdate] | int | No | Start date timestamp |
| parameters[enddate] | int | No | End date timestamp |
| page | int | No | Page number (default: 0) |
| pagesize | int | No | Items per page (default: 25, max: 100) |

**Example Request**:

```bash
curl "https://your-moodle-site.com/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_manireports_get_report_data" \
  -d "moodlewsrestformat=json" \
  -d "reportid=5" \
  -d "parameters[startdate]=1609459200" \
  -d "parameters[enddate]=1640995199" \
  -d "page=0" \
  -d "pagesize=50"
```

**Example Response**:

```json
{
  "success": true,
  "report": {
    "id": 5,
    "name": "Course Completion Report",
    "description": "Shows course completion statistics"
  },
  "columns": ["coursename", "enrolled", "completed", "percentage"],
  "data": [
    "{\"coursename\": \"Introduction to PHP\", \"enrolled\": 50, \"completed\": 35, \"percentage\": 70}",
    "{\"coursename\": \"Advanced JavaScript\", \"enrolled\": 30, \"completed\": 25, \"percentage\": 83}"
  ],
  "pagination": {
    "page": 0,
    "pagesize": 50,
    "total": 2,
    "totalpages": 1
  }
}
```

**Error Response**:

```json
{
  "success": false,
  "error": "Report not found or you do not have permission to access it",
  "errorcode": 404
}
```

---

### 3. Get Report Metadata

Retrieve metadata about one or all reports.

**Function**: `local_manireports_get_report_metadata`

**Capabilities Required**: `local/manireports:managereports`

**Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| reportid | int | No | Report ID (omit to get all reports) |

**Example Request (Single Report)**:

```bash
curl "https://your-moodle-site.com/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_manireports_get_report_metadata" \
  -d "moodlewsrestformat=json" \
  -d "reportid=5"
```

**Example Response (Single Report)**:

```json
{
  "success": true,
  "report": {
    "id": 5,
    "name": "Course Completion Report",
    "description": "Shows course completion statistics",
    "type": "sql",
    "timecreated": 1609459200,
    "timemodified": 1640995199,
    "parameters": ["startdate", "enddate", "companyid"]
  }
}
```

**Example Request (All Reports)**:

```bash
curl "https://your-moodle-site.com/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_manireports_get_report_metadata" \
  -d "moodlewsrestformat=json"
```

**Example Response (All Reports)**:

```json
{
  "success": true,
  "reports": [
    {
      "id": 5,
      "name": "Course Completion Report",
      "description": "Shows course completion statistics",
      "type": "sql",
      "timecreated": 1609459200,
      "timemodified": 1640995199
    },
    {
      "id": 6,
      "name": "User Engagement Report",
      "description": "Shows user engagement metrics",
      "type": "gui",
      "timecreated": 1609459300,
      "timemodified": 1640995299
    }
  ],
  "total": 2
}
```

---

### 4. Get Available Reports

Get a list of all reports available to the current user.

**Function**: `local_manireports_get_available_reports`

**Capabilities Required**: None (returns reports based on user's capabilities)

**Parameters**: None

**Example Request**:

```bash
curl "https://your-moodle-site.com/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_manireports_get_available_reports" \
  -d "moodlewsrestformat=json"
```

**Example Response**:

```json
{
  "success": true,
  "reports": [
    {
      "id": 0,
      "name": "Course Completion Report",
      "type": "prebuilt",
      "key": "course_completion"
    },
    {
      "id": 0,
      "name": "Course Progress Report",
      "type": "prebuilt",
      "key": "course_progress"
    },
    {
      "id": 5,
      "name": "Custom Sales Report",
      "type": "custom",
      "key": "custom_5"
    }
  ],
  "total": 3
}
```

---

## Error Handling

All API endpoints return a consistent error structure:

```json
{
  "success": false,
  "error": "Human-readable error message",
  "errorcode": 400
}
```

### Common Error Codes

| Code | Description |
|------|-------------|
| 400 | Bad Request - Invalid parameters |
| 401 | Unauthorized - Invalid or missing token |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found - Resource not found |
| 500 | Internal Server Error |

### Common Error Messages

- `Invalid dashboard type specified`
- `Report not found or you do not have permission to access it`
- `Invalid parameters provided to API`
- `You are not authorized to access this API endpoint`
- `Invalid page size. Maximum allowed is 100`
- `Report execution failed: [details]`

---

## Pagination

All endpoints that return lists support pagination:

**Request Parameters**:
- `page`: Zero-based page number (default: 0)
- `pagesize`: Number of items per page (default: 25, max: 100)

**Response Structure**:
```json
{
  "pagination": {
    "page": 0,
    "pagesize": 25,
    "total": 150,
    "totalpages": 6
  }
}
```

---

## Rate Limiting

API calls are subject to Moodle's standard rate limiting. If you exceed the limit, you'll receive:

```json
{
  "success": false,
  "error": "API rate limit exceeded. Please try again later",
  "errorcode": 429
}
```

---

## Security Considerations

1. **Token Security**: Keep your web service tokens secure. Never commit them to version control.
2. **HTTPS**: Always use HTTPS in production to protect tokens in transit.
3. **Capability Checks**: All endpoints enforce Moodle capability checks.
4. **IOMAD Filtering**: Company isolation is automatically applied in IOMAD environments.
5. **SQL Injection**: Custom reports are validated against SQL injection attacks.

---

## Testing API Endpoints

### Using cURL

```bash
# Test get_available_reports
curl "https://your-moodle-site.com/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_manireports_get_available_reports" \
  -d "moodlewsrestformat=json"
```

### Using Postman

1. Create a new POST request
2. URL: `https://your-moodle-site.com/webservice/rest/server.php`
3. Body type: `x-www-form-urlencoded`
4. Add parameters:
   - `wstoken`: YOUR_TOKEN
   - `wsfunction`: local_manireports_get_dashboard_data
   - `moodlewsrestformat`: json
   - `dashboardtype`: admin

### Using Python

```python
import requests

url = "https://your-moodle-site.com/webservice/rest/server.php"
params = {
    "wstoken": "YOUR_TOKEN",
    "wsfunction": "local_manireports_get_available_reports",
    "moodlewsrestformat": "json"
}

response = requests.post(url, data=params)
data = response.json()
print(data)
```

---

## Setup Instructions

### 1. Enable Web Services

1. Go to **Site Administration → Advanced features**
2. Enable "Enable web services"
3. Save changes

### 2. Enable REST Protocol

1. Go to **Site Administration → Server → Web services → Manage protocols**
2. Enable "REST protocol"

### 3. Create a Web Service

1. Go to **Site Administration → Server → Web services → External services**
2. Click "Add"
3. Name: "ManiReports API"
4. Short name: "manireports_api"
5. Enabled: Yes
6. Add the following functions:
   - `local_manireports_get_dashboard_data`
   - `local_manireports_get_report_data`
   - `local_manireports_get_report_metadata`
   - `local_manireports_get_available_reports`

### 4. Create a User for API Access

1. Create a new user or use an existing one
2. Assign appropriate roles and capabilities
3. Go to **Site Administration → Server → Web services → Manage tokens**
4. Create a token for the user and the "ManiReports API" service

### 5. Test the API

Use the token to make test API calls as shown in the examples above.

---

## Support

For issues or questions about the API:
1. Check the Moodle error logs
2. Verify token permissions and capabilities
3. Ensure web services are properly configured
4. Review the API documentation

---

## Changelog

### Version 1.0 (2024-11-19)
- Initial API release
- Added dashboard data endpoint
- Added report execution endpoint
- Added report metadata endpoint
- Added available reports endpoint
- Implemented pagination support
- Implemented error handling

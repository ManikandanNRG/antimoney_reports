# Heartbeat Refactor TODO: External API

## Overview
Currently, the heartbeat mechanism uses a direct AJAX POST to `local/manireports/ui/ajax/heartbeat.php`. This approach is fragile in certain contexts (like SCORM players in popups/iframes) because it relies on `M.cfg.sesskey` being present and valid in the client-side JavaScript, which is not always the case in minimal layouts.

To fix the "400 Bad Request" errors, we have temporarily **excluded** the heartbeat from these contexts. However, this means time spent in SCORM packages is not tracked in the User Engagement report.

## The "Best Solution"
To enable robust tracking everywhere (including SCORM players), we should refactor the heartbeat to use **Moodle's External API (Web Services)**.

### Implementation Steps

1.  **Create External Class**:
    - Create `local/manireports/classes/external/heartbeat.php`.
    - Implement a `submit_heartbeat` external function.
    - This function should accept `courseid` and `timestamp`, validate context, and call `time_engine::record_heartbeat()`.

2.  **Register Web Service**:
    - In `local/manireports/db/services.php`, register `local_manireports_submit_heartbeat`.
    - Set `'ajax' => true` to allow calling via `core/ajax`.

3.  **Update JavaScript**:
    - In `local/manireports/amd/src/heartbeat.js`, replace the `$.ajax` call with `Ajax.call` from the `core/ajax` module.
    - Example:
      ```javascript
      var Ajax = require('core/ajax');
      Ajax.call([{
          methodname: 'local_manireports_submit_heartbeat',
          args: { courseid: this.courseid, timestamp: now }
      }]);
      ```
    - `core/ajax` automatically handles the session key and URL, making it work reliably in all Moodle contexts.

4.  **Remove Old Endpoint**:
    - Delete `local/manireports/ui/ajax/heartbeat.php` once the refactor is complete and verified.

## Benefits
-   **Reliability**: Works in iframes, popups, and mobile app contexts.
-   **Security**: Standardized authentication and capability checks.
-   **Completeness**: Allows tracking time in SCORM packages for better analytics.

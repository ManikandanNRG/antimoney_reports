# Cloud Offload Process Flow (ASCII) - FINAL (IOMAD VERIFIED)

This document visualizes how the **Selective Cloud Offload** system intercepts standard IOMAD email processes.

## 1. License Allocation Email Flow

### A. Standard IOMAD Flow (Current)
```text
[ Admin ]
    |
    | (1) Upload CSV / Assign License
    v
[ IOMAD Core ]
    |
    | (2) Insert into {companylicense_users}
    |
    | (3) Run allocation logic (Internal)
    |
    +---> [ EVENT: \block_iomad_company_admin\event\user_license_assigned ]
            |
            | (4) Triggered
            v
    [ IOMAD Email System ]
            |
            | (5) Inserts into {email} table (Queue)
            | (6) Cron sends email later
            v
    [ Admin / User ]
```

### B. Cloud Offload Flow (Proposed)
```text
[ Admin ]
    |
    | (1) Upload CSV / Assign License
    v
[ IOMAD Core ]
    |
    | (2) Insert into {companylicense_users}
    |
    +---> [ EVENT: \block_iomad_company_admin\event\user_license_assigned ]
            |
            | (3) INTERCEPTED by ManiReports Observer
            v
    [ ManiReports Observer ]
            |
            +--- Is Cloud Offload ON? ---+
            |                            |
        [ YES ]                       [ NO ]
            |                            |
            | (4a) Create Cloud Job      | (4b) Do Nothing
            | (4c) DELETE from {email}   |
            v                            v
    [ CloudJobManager ]           [ IOMAD Email System ]
            |                            |
            | (5) Push to AWS            | (5) Cron sends email
            v                            v
       [ AWS Cloud ]              [ End ]
```

---

## 2. New User / Temp Password Flow (IOMAD SPECIFIC)

### A. Standard IOMAD Flow (Current)
```text
[ Admin ]
    |
    | (1) Upload User CSV
    v
[ IOMAD (iomad/lib/user.php) ]
    |
    | (2) generate_temporary_password()
    |     -> Stores Hash in {user} (For Login)
    |     -> Stores Encrypted Pass in {user_preferences} ('iomad_temporary')
    |
    +---> [ EmailTemplate::send('user_create') ]
            |
            | (3) Inserts into {email} table (Queue)
            v
    [ Moodle Event: user_created ]
```

### B. Cloud Offload Flow (Proposed)
```text
[ Admin ]
    |
    | (1) Upload User CSV
    v
[ IOMAD ]
    |
    | (2) Generate Pass & Queue Email (Standard IOMAD)
    |
    +---> [ EVENT: user_created ]
            |
            | (3) INTERCEPTED by ManiReports Observer
            v
    [ ManiReports Observer ]
            |
            +--- Is Cloud Offload ON? ---+
            |                            |
        [ YES ]                       [ NO ]
            |                            |
            | (4a) Fetch Pass from       | (4b) Do Nothing
            |      {user_preferences}    |
            |      ('iomad_temporary')   |
            |                            |
            | (4b) Create Cloud Job      |
            |                            |
            | (4c) DELETE from {email}   |
            |      (Suppress Local)      |
            v                            v
    [ CloudJobManager ]           [ IOMAD Email System ]
            |                            |
            | (5) Send to AWS            | (5) Cron sends email
            v                            v
       [ AWS Cloud ]              [ Admin / User ]
```

## Critical Verification Notes (Why this differs from Standard Moodle)
1.  **Password Storage**: Unlike Standard Moodle, **IOMAD explicitly stores the temp password** in `mdl_user_preferences` (key: `iomad_temporary`). We verified this in `iomad/lib/user.php` line 571.
2.  **Email Queue**: IOMAD uses its own `{email}` table for queuing these messages, bypassing the standard Moodle `message_send` for this specific flow.
3.  **Suppression**: Because it uses a DB table `{email}`, we can reliably "suppress" the email by simply **deleting the record** from that table matching the user and template.

# Cloud Offload Process Flow (ASCII) - FINAL

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
            | (Note: Might be \tool_iomad\event\license_assigned in some versions)
            |
            | (4) Triggered
            v
    [ Moodle Mailer ]
            |
            | (5) Sends Email (Slow / Server Load)
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
    | (3) Run allocation logic
    |
    +---> [ EVENT: \block_iomad_company_admin\event\user_license_assigned ]
            |
            | (4) INTERCEPTED by ManiReports Observer
            v
    [ ManiReports Observer ]
            |
            +--- Is Cloud Offload ON? ---+
            |                            |
        [ YES ]                       [ NO ]
            |                            |
            | (5a) Queue Cloud Job       | (5b) Allow default
            |     (email payload)        |      Moodle Mail
            v                            v
    [ CloudJobManager ]           [ Moodle Mailer ]
            |                            |
            | (6) Push to AWS            | (6) Email sent normally
            v                            v
       [ AWS Cloud ]              [ End ]
            |
            | (7) SES Sends Email
            v
       [ User ]
```

---

## 2. New User / Temp Password Flow

### A. Standard IOMAD Flow (Current)
```text
[ Admin ]
    |
    | (1) Upload User CSV
    v
[ Moodle ]
    |
    | (2) generate_temporary_password()
    |     -> Stores Hash in DB (Required for Login)
    |     -> Stores Plaintext in User Pref (Briefly)
    |
    +---> [ Trigger EVENT: user_created ]
            |
            | (3) INTERCEPTED by ManiReports Observer
            v
    [ ManiReports Observer ]
            |
            +--- Is Cloud Offload ON? ---+
            |                            |
        [ YES ]                       [ NO ]
            |                            |
            | (4a) Create Job            | (4b) Do Nothing
            | (Fetch TempPass from       |
            |  mdl_user_preferences)     |
            v                            v
    [ CloudJobManager ]           [ Moodle Mailer ]
            |                            |
            | (5) Send to AWS            | (5) Send Email
            v                            v
       [ AWS Cloud ]              [ Admin / User ]
            |
            | (6) Sends Email
            v
      [ Admin / User ]
```

## Implementation Notes
1.  **Event Name**: We will listen for `\block_iomad_company_admin\event\user_license_assigned` as seen in `company.php`. We will also add a fallback listener for `\tool_iomad\event\license_assigned` just in case.
2.  **Password Retrieval**: We use `get_user_preferences('iomad_temporary')` to retrieve the password for the cloud job.

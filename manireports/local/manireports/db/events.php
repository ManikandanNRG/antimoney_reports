<?php
defined('MOODLE_INTERNAL') || die();

$observers = array(
    // Listen for new user creation (e.g., via CSV upload)
    array(
        'eventname'   => '\core\event\user_created',
        'callback'    => '\local_manireports\api\EmailOffloadHandler::handle_user_created',
    ),
    // Listen for IOMAD license allocation
    array(
        'eventname'   => '\block_iomad_company_admin\event\user_license_assigned',
        'callback'    => '\local_manireports\api\EmailOffloadHandler::handle_license_allocated',
    ),
    // Listen for user deletion - cleanup reminder instances
    array(
        'eventname'   => '\core\event\user_deleted',
        'callback'    => '\local_manireports\observer\user_events::user_deleted',
    ),
    // Listen for user unenrollment - cleanup reminder instances
    array(
        'eventname'   => '\core\event\user_enrolment_deleted',
        'callback'    => '\local_manireports\observer\user_events::user_unenrolled',
    ),
);

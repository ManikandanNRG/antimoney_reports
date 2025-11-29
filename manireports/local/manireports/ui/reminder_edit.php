<?php
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/../classes/form/reminder_rule_form.php');

admin_externalpage_setup('manireports_reminders');

$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/manireports/ui/reminder_edit.php', ['id' => $id]));
$PAGE->set_title($id ? 'Edit Reminder Rule' : 'Create Reminder Rule');
$PAGE->set_heading($id ? 'Edit Reminder Rule' : 'Create Reminder Rule');
$PAGE->set_pagelayout('embedded');

$form = new \local_manireports\form\reminder_rule_form(null, ['id' => $id]);

if ($id) {
    $rule = $DB->get_record('manireports_rem_rule', ['id' => $id], '*', MUST_EXIST);
    // Unpack trigger value
    $trigger_value = json_decode($rule->trigger_value, true);
    $rule->trigger_days = isset($trigger_value['days']) ? $trigger_value['days'] : 0;
    $form->set_data($rule);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/manireports/ui/reminders.php'));
} else if ($data = $form->get_data()) {
    $manager = new \local_manireports\api\ReminderManager();
    
    // Pack trigger value
    $data->trigger_value = json_encode(['days' => $data->trigger_days]);
    unset($data->trigger_days);

    if ($id) {
        $manager->update_rule($id, $data);
        $msg = 'Reminder rule updated successfully';
    } else {
        $manager->create_rule($data);
        $msg = 'Reminder rule created successfully';
    }

    redirect(new moodle_url('/local/manireports/ui/reminders.php'), $msg, null, \core\output\notification::NOTIFY_SUCCESS);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $id ? 'Edit' : 'Create'; ?> Reminder Rule - ManiReports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #f8fafc;
            min-height: 100vh;
            padding: 24px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Header */
        .page-header {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 24px;
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-header p {
            color: #94a3b8;
            font-size: 16px;
        }

        /* Action Bar */
        .action-bar {
            margin-bottom: 24px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-secondary {
            background: rgba(148, 163, 184, 0.1);
            color: #f8fafc;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(148, 163, 184, 0.2);
        }

        /* Form Container */
        .form-container {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        /* Moodle Form Overrides */
        .mform {
            margin: 0;
        }

        .mform fieldset {
            border: none;
            padding: 0;
            margin: 0;
        }

        .mform .fitem {
            margin-bottom: 24px;
        }

        .mform .fitem .fitemtitle {
            width: 100%;
            padding: 0;
            text-align: left;
        }

        .mform .fitem .fitemtitle label {
            display: block;
            margin-bottom: 8px;
            color: #cbd5e1;
            font-weight: 600;
            font-size: 14px;
        }

        .mform .fitem .felement {
            margin-left: 0;
        }

        .mform input[type="text"],
        .mform textarea,
        .mform select {
            width: 100%;
            padding: 12px 16px;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 12px;
            color: #f8fafc;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .mform input[type="text"]:focus,
        .mform textarea:focus,
        .mform select:focus {
            outline: none;
            border-color: rgba(99, 102, 241, 0.5);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .mform textarea {
            min-height: 120px;
            resize: vertical;
        }

        .mform .fdescription {
            color: #94a3b8;
            font-size: 13px;
            margin-top: 6px;
        }

        .mform .fgroup .felement {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .mform input[type="submit"],
        .mform input[type="button"] {
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .mform input[type="submit"] {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .mform input[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .mform input[type="button"] {
            background: rgba(148, 163, 184, 0.1);
            color: #f8fafc;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .mform input[type="button"]:hover {
            background: rgba(148, 163, 184, 0.2);
        }

        .mform .fitem_actionbuttons {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid rgba(148, 163, 184, 0.1);
        }

        .mform .fitem_actionbuttons .felement {
            display: flex;
            gap: 12px;
        }

        /* Help Icon Styling - Universal Fix */
        .mform .iconhelp,
        .mform .btn-link,
        .mform .btn-link i,
        .mform .btn-link .icon,
        .mform a[data-toggle="popover"],
        .mform a[data-toggle="popover"] i,
        .mform .fitemtitle i,
        .mform .fitemtitle .icon,
        .mform label i,
        .mform label .icon,
        .mform legend i,
        .mform legend .icon {
            color: #6366f1 !important;
            font-size: 16px !important;
            opacity: 1 !important;
            visibility: visible !important;
            display: inline-block !important;
            text-decoration: none !important;
        }

        .mform .btn-link:hover,
        .mform .btn-link:focus,
        .mform a[data-toggle="popover"]:hover,
        .mform .iconhelp:hover {
            color: #8b5cf6 !important;
            cursor: pointer !important;
        }

        /* Ensure the container of the icon doesn't hide it */
        .mform .form-label-addon {
            opacity: 1 !important;
            visibility: visible !important;
            color: #6366f1 !important;
            display: inline-block !important;
        }

        /* Popover Styling */
        .popover {
            background: #1e293b !important;
            border: 1px solid rgba(148, 163, 184, 0.2) !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4) !important;
            color: #f8fafc !important;
            z-index: 10000 !important; /* Ensure on top */
        }

        .popover-header {
            background: rgba(30, 41, 59, 0.9) !important;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1) !important;
            color: #f8fafc !important;
            font-weight: 700 !important;
        }

        .popover-body {
            color: #cbd5e1 !important;
        }

        .bs-popover-right .arrow::after,
        .bs-popover-auto[x-placement^="right"] .arrow::after {
            border-right-color: #1e293b;
        }

        .bs-popover-left .arrow::after,
        .bs-popover-auto[x-placement^="left"] .arrow::after {
            border-left-color: #1e293b;
        }

        .bs-popover-top .arrow::after,
        .bs-popover-auto[x-placement^="top"] .arrow::after {
            border-top-color: #1e293b;
        }

        .bs-popover-bottom .arrow::after,
        .bs-popover-auto[x-placement^="bottom"] .arrow::after {
            border-bottom-color: #1e293b;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fa-solid fa-<?php echo $id ? 'pen' : 'plus'; ?>"></i> <?php echo $id ? 'Edit' : 'Create'; ?> Reminder Rule</h1>
            <p>Configure automated reminder emails for course enrollments and completions</p>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <div class="d-flex gap-2">
                <a href="<?php echo $CFG->wwwroot; ?>/local/manireports/designs/dashboard_v6_ultimate.php" class="btn btn-secondary text-white">
                    <i class="fa fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
                <a href="reminders.php" class="btn btn-secondary text-white">
                    <i class="fa fa-list mr-2"></i> Back to Rules
                </a>
            </div>
        </div>

        <!-- Form -->
        <div class="form-container">
            <?php $form->display(); ?>
        </div>
    </div>
</body>
</html>

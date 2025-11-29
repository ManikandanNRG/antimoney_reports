<?php
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/../lib.php');

admin_externalpage_setup('manireports_reminders');

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/manireports/ui/reminders.php'));
$PAGE->set_title(get_string('reminders', 'local_manireports'));
$PAGE->set_heading(get_string('reminders', 'local_manireports'));
$PAGE->set_pagelayout('embedded');

// Handle Actions
if ($action === 'delete' && $id && confirm_sesskey()) {
    $DB->set_field('manireports_rem_rule', 'enabled', 0, ['id' => $id]);
    redirect($PAGE->url, get_string('ruledeleted', 'local_manireports'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'run' && $id && confirm_sesskey()) {
    // Manually trigger instance creation for this rule
    $manager = new \local_manireports\api\ReminderManager();
    $count = $manager->create_instances($id);
    redirect($PAGE->url, get_string('instancescreated', 'local_manireports', $count), null, \core\output\notification::NOTIFY_SUCCESS);
}

// List Rules
$rules = $DB->get_records('manireports_rem_rule', ['enabled' => 1]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo get_string('reminders', 'local_manireports'); ?> - ManiReports</title>
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
            max-width: 1400px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: rgba(148, 163, 184, 0.1);
            color: #f8fafc;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(148, 163, 184, 0.2);
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        .btn-info {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .btn-info:hover {
            background: rgba(59, 130, 246, 0.2);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }

        /* Bento Card */
        .bento-card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .bento-card:hover {
            border-color: rgba(99, 102, 241, 0.3);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .card-title {
            font-size: 20px;
            font-weight: 700;
            color: #f8fafc;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-header {
            text-align: left;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
        }

        .table-row {
            transition: all 0.2s ease;
        }

        .table-row:hover {
            background: rgba(99, 102, 241, 0.05);
        }

        .table-cell {
            padding: 16px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.05);
            color: #e2e8f0;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-inactive {
            background: rgba(148, 163, 184, 0.1);
            color: #94a3b8;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #cbd5e1;
        }

        .empty-state p {
            font-size: 14px;
        }

        .actions-cell {
            display: flex;
            gap: 8px;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fa-solid fa-bell"></i> Reminder Rules</h1>
            <p>Configure automated reminder emails for course enrollments and completions</p>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <div></div>
            <a href="<?php echo new moodle_url('/local/manireports/ui/reminder_edit.php'); ?>" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i>
                <?php echo get_string('addnewrule', 'local_manireports'); ?>
            </a>
        </div>

        <!-- Rules List -->
        <div class="bento-card">
            <div class="card-header">
                <div class="card-title">Active Reminder Rules</div>
            </div>

            <?php if ($rules): ?>
                <table>
                    <thead>
                        <tr>
                            <th class="table-header">Name</th>
                            <th class="table-header">Trigger</th>
                            <th class="table-header">Delay</th>
                            <th class="table-header">Count</th>
                            <th class="table-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rules as $rule): ?>
                            <?php
                                $editurl = new moodle_url('/local/manireports/ui/reminder_edit.php', ['id' => $rule->id]);
                                $deleteurl = new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $rule->id, 'sesskey' => sesskey()]);
                                $runurl = new moodle_url($PAGE->url, ['action' => 'run', 'id' => $rule->id, 'sesskey' => sesskey()]);
                            ?>
                            <tr class="table-row">
                                <td class="table-cell" style="font-weight: 600;"><?php echo format_string($rule->name); ?></td>
                                <td class="table-cell"><?php echo $rule->trigger_type; ?></td>
                                <td class="table-cell"><?php echo format_time($rule->emaildelay); ?></td>
                                <td class="table-cell"><?php echo $rule->remindercount; ?></td>
                                <td class="table-cell">
                                    <div class="actions-cell">
                                        <a href="<?php echo $editurl; ?>" class="btn btn-sm btn-secondary">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </a>
                                        <a href="<?php echo $runurl; ?>" class="btn btn-sm btn-info">
                                            <i class="fa-solid fa-play"></i> Run Now
                                        </a>
                                        <a href="<?php echo $deleteurl; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this rule?')">
                                            <i class="fa-solid fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-bell-slash"></i>
                    <h3><?php echo get_string('norulesfound', 'local_manireports'); ?></h3>
                    <p>Create your first reminder rule to start sending automated emails</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

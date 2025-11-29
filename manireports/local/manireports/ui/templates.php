<?php
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/form/template_form.php');

admin_externalpage_setup('manireports_reminders'); // Reuse same permission check

$action = optional_param('action', 'list', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/manireports/ui/templates.php'));
$PAGE->set_title(get_string('templates', 'local_manireports'));
$PAGE->set_heading(get_string('templates', 'local_manireports'));
$PAGE->set_pagelayout('embedded');

$form = new \local_manireports\form\template_form(new moodle_url($PAGE->url, ['action' => 'edit', 'id' => $id]));

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/manireports/ui/templates.php'));
} else if ($data = $form->get_data()) {
    $data->timemodified = time();
    $data->body_text = html_to_text($data->body_html['text']);
    $data->body_html = $data->body_html['text']; // Extract text from editor array

    if ($data->id) {
        $DB->update_record('manireports_rem_tmpl', $data);
        $msg = get_string('templateupdated', 'local_manireports');
    } else {
        $DB->insert_record('manireports_rem_tmpl', $data);
        $msg = get_string('templatecreated', 'local_manireports');
    }
    redirect(new moodle_url('/local/manireports/ui/templates.php'), $msg, null, \core\output\notification::NOTIFY_SUCCESS);
}

$templates = $DB->get_records('manireports_rem_tmpl', null, 'name ASC');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo get_string('templates', 'local_manireports'); ?> - ManiReports</title>
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-secondary {
            background: rgba(148, 163, 184, 0.1);
            color: #f8fafc;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(148, 163, 184, 0.2);
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
            border-color: rgba(16, 185, 129, 0.3);
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
            background: rgba(16, 185, 129, 0.05);
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
            color: #10b981 !important;
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
            color: #059669 !important;
            cursor: pointer !important;
        }

        /* Ensure the container of the icon doesn't hide it */
        .mform .form-label-addon {
            opacity: 1 !important;
            visibility: visible !important;
            color: #10b981 !important;
            display: inline-block !important;
        }

        /* Popover Styling */
        .popover {
            background: #1e293b !important;
            border: 1px solid rgba(148, 163, 184, 0.2) !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4) !important;
            color: #f8fafc !important;
            z-index: 10000 !important;
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

        /* Form Styles */
        .form-container {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            margin-bottom: 24px;
        }

        .mform {
            margin: 0;
        }

        .fitem {
            margin-bottom: 24px;
        }

        .fitem label {
            display: block;
            margin-bottom: 8px;
            color: #cbd5e1;
            font-weight: 600;
            font-size: 14px;
        }

        .fitem input[type="text"],
        .fitem textarea,
        .fitem select {
            width: 100%;
            padding: 12px 16px;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 12px;
            color: #f8fafc;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .fitem input[type="text"]:focus,
        .fitem textarea:focus,
        .fitem select:focus {
            outline: none;
            border-color: rgba(16, 185, 129, 0.5);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .fitem textarea {
            min-height: 120px;
            resize: vertical;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fa-solid fa-file-code"></i> Email Templates</h1>
            <p>Create and manage email templates for reminder notifications</p>
        </div>

        <?php if ($action === 'edit' || $action === 'add'): ?>
            <!-- Form View -->
            <div class="action-bar">
                <a href="<?php echo new moodle_url('/local/manireports/ui/templates.php'); ?>" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Back to List
                </a>
            </div>

            <div class="form-container">
                <h2 style="margin-bottom: 24px; color: #f8fafc;">
                    <?php echo $id ? get_string('edittemplate', 'local_manireports') : get_string('addtemplate', 'local_manireports'); ?>
                </h2>
                <?php
                    if ($id && $action === 'edit') {
                        $template = $DB->get_record('manireports_rem_tmpl', ['id' => $id], '*', MUST_EXIST);
                        $template->body_html = ['text' => $template->body_html, 'format' => FORMAT_HTML];
                        $form->set_data($template);
                    }
                    $form->display();
                ?>
            </div>

        <?php else: ?>
            <!-- List View -->
            <div class="action-bar">
                <div></div>
                <a href="<?php echo new moodle_url($PAGE->url, ['action' => 'add']); ?>" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i>
                    <?php echo get_string('addtemplate', 'local_manireports'); ?>
                </a>
            </div>

            <div class="bento-card">
                <div class="card-header">
                    <div class="card-title">Email Templates</div>
                </div>

                <?php if ($templates): ?>
                    <table>
                        <thead>
                            <tr>
                                <th class="table-header">Name</th>
                                <th class="table-header">Subject</th>
                                <th class="table-header">Status</th>
                                <th class="table-header">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $tmpl): ?>
                                <?php
                                    $editurl = new moodle_url($PAGE->url, ['action' => 'edit', 'id' => $tmpl->id]);
                                ?>
                                <tr class="table-row">
                                    <td class="table-cell" style="font-weight: 600;"><?php echo format_string($tmpl->name); ?></td>
                                    <td class="table-cell"><?php echo format_string($tmpl->subject); ?></td>
                                    <td class="table-cell">
                                        <span class="status-badge <?php echo $tmpl->enabled ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $tmpl->enabled ? 'Enabled' : 'Disabled'; ?>
                                        </span>
                                    </td>
                                    <td class="table-cell">
                                        <div class="actions-cell">
                                            <a href="<?php echo $editurl; ?>" class="btn btn-sm btn-secondary">
                                                <i class="fa-solid fa-pen"></i> Edit
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-file-circle-xmark"></i>
                        <h3><?php echo get_string('notemplates', 'local_manireports'); ?></h3>
                        <p>Create your first email template to use in reminder rules</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

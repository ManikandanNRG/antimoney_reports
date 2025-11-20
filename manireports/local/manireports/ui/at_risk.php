<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * At-Risk Learners Dashboard
 *
 * Displays learners identified as at-risk based on engagement metrics.
 * Allows managers to acknowledge alerts and add intervention notes.
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();

$courseid = optional_param('courseid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);

$context = context_system::instance();

// Check capability.
require_capability('local/manireports:viewmanagerdashboard', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/manireports/ui/at_risk.php', ['courseid' => $courseid]);
$PAGE->set_title(get_string('atriskdashboard', 'local_manireports'));
$PAGE->set_heading(get_string('atriskdashboard', 'local_manireports'));
$PAGE->set_pagelayout('admin');

// Handle actions.
if ($action === 'acknowledge' && $userid && confirm_sesskey()) {
    $note = optional_param('note', '', PARAM_TEXT);
    
    // Record acknowledgment.
    $record = new stdClass();
    $record->userid = $userid;
    $record->courseid = $courseid;
    $record->acknowledgedby = $USER->id;
    $record->note = $note;
    $record->timeacknowledged = time();
    
    $DB->insert_record('manireports_atrisk_ack', $record);
    
    // Log the action.
    $logger = new \local_manireports\api\audit_logger();
    $logger->log_action('acknowledge_atrisk', 'user', $userid, [
        'courseid' => $courseid,
        'note' => $note,
    ]);
    
    redirect($PAGE->url, get_string('atrisk:acknowledged', 'local_manireports'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

// Get at-risk learners.
$analytics = new \local_manireports\api\analytics_engine();
$iomadfilter = new \local_manireports\api\iomad_filter();

// Apply IOMAD filtering if needed.
$companyid = 0;
if ($iomadfilter->is_iomad_installed()) {
    $companies = $iomadfilter->get_user_companies($USER->id);
    if (!empty($companies)) {
        $companyid = reset($companies);
    }
}

// Get at-risk learners.
if ($courseid > 0) {
    $atrisklearners = $analytics->detect_at_risk_learners($courseid);
} else {
    // Get all courses for the user/company.
    if ($companyid > 0) {
        $sql = "SELECT DISTINCT c.id, c.fullname
                  FROM {course} c
                  JOIN {company_course} cc ON cc.courseid = c.id
                 WHERE cc.companyid = :companyid
                   AND c.id > 1
              ORDER BY c.fullname";
        $courses = $DB->get_records_sql($sql, ['companyid' => $companyid]);
    } else {
        $courses = $DB->get_records('course', ['id' => ['>', 1]], 'fullname', 'id, fullname');
    }
    
    $atrisklearners = [];
    foreach ($courses as $course) {
        $courseatrisk = $analytics->detect_at_risk_learners($course->id);
        foreach ($courseatrisk as $learner) {
            $learner->coursename = $course->fullname;
            $atrisklearners[] = $learner;
        }
    }
}

// Get acknowledgments.
$acknowledgments = [];
if (!empty($atrisklearners)) {
    list($insql, $params) = $DB->get_in_or_equal(array_column($atrisklearners, 'userid'));
    $sql = "SELECT *
              FROM {manireports_atrisk_ack}
             WHERE userid $insql";
    if ($courseid > 0) {
        $sql .= " AND courseid = :courseid";
        $params['courseid'] = $courseid;
    }
    $sql .= " ORDER BY timeacknowledged DESC";
    
    $acks = $DB->get_records_sql($sql, $params);
    foreach ($acks as $ack) {
        $key = $ack->userid . '_' . $ack->courseid;
        if (!isset($acknowledgments[$key])) {
            $acknowledgments[$key] = $ack;
        }
    }
}

// Display filters.
echo html_writer::start_div('at-risk-filters mb-3');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out(false), 'class' => 'form-inline']);

// Course filter.
echo html_writer::label(get_string('course'), 'courseid', false, ['class' => 'mr-2']);
echo html_writer::start_tag('select', ['name' => 'courseid', 'id' => 'courseid', 'class' => 'custom-select mr-2']);
echo html_writer::tag('option', get_string('allcourses', 'local_manireports'), ['value' => 0]);

if ($companyid > 0) {
    $sql = "SELECT DISTINCT c.id, c.fullname
              FROM {course} c
              JOIN {company_course} cc ON cc.courseid = c.id
             WHERE cc.companyid = :companyid
               AND c.id > 1
          ORDER BY c.fullname";
    $courses = $DB->get_records_sql($sql, ['companyid' => $companyid]);
} else {
    $courses = $DB->get_records('course', ['id' => ['>', 1]], 'fullname', 'id, fullname');
}

foreach ($courses as $course) {
    $selected = ($course->id == $courseid) ? ['selected' => 'selected'] : [];
    echo html_writer::tag('option', format_string($course->fullname), array_merge(['value' => $course->id], $selected));
}

echo html_writer::end_tag('select');
echo html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

// Display summary.
$totalcount = count($atrisklearners);
$acknowledgedcount = count($acknowledgments);
$pendingcount = $totalcount - $acknowledgedcount;

echo html_writer::start_div('at-risk-summary mb-4');
echo html_writer::tag('h4', get_string('atrisk:summary', 'local_manireports'));
echo html_writer::start_div('row');

echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card bg-danger text-white');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', $totalcount, ['class' => 'card-title']);
echo html_writer::tag('p', get_string('atrisk:totalcount', 'local_manireports'), ['class' => 'card-text']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card bg-warning text-white');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', $pendingcount, ['class' => 'card-title']);
echo html_writer::tag('p', get_string('atrisk:pendingcount', 'local_manireports'), ['class' => 'card-text']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card bg-success text-white');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', $acknowledgedcount, ['class' => 'card-title']);
echo html_writer::tag('p', get_string('atrisk:acknowledgedcount', 'local_manireports'), ['class' => 'card-text']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();

// Display at-risk learners table.
if (empty($atrisklearners)) {
    echo $OUTPUT->notification(get_string('atrisk:nolearners', 'local_manireports'), 'info');
} else {
    echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('student'));
    if ($courseid == 0) {
        echo html_writer::tag('th', get_string('course'));
    }
    echo html_writer::tag('th', get_string('atrisk:riskscore', 'local_manireports'));
    echo html_writer::tag('th', get_string('atrisk:factors', 'local_manireports'));
    echo html_writer::tag('th', get_string('atrisk:lastactivity', 'local_manireports'));
    echo html_writer::tag('th', get_string('atrisk:status', 'local_manireports'));
    echo html_writer::tag('th', get_string('actions'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    foreach ($atrisklearners as $learner) {
        $user = $DB->get_record('user', ['id' => $learner->userid]);
        if (!$user) {
            continue;
        }
        
        $key = $learner->userid . '_' . $learner->courseid;
        $acknowledged = isset($acknowledgments[$key]);
        
        echo html_writer::start_tag('tr', ['class' => $acknowledged ? 'table-success' : '']);
        
        // Student name.
        echo html_writer::start_tag('td');
        echo html_writer::link(
            new moodle_url('/user/profile.php', ['id' => $user->id]),
            fullname($user)
        );
        echo html_writer::end_tag('td');
        
        // Course name (if showing all courses).
        if ($courseid == 0) {
            echo html_writer::tag('td', format_string($learner->coursename ?? ''));
        }
        
        // Risk score.
        $scoreclass = '';
        if ($learner->risk_score >= 75) {
            $scoreclass = 'badge-danger';
        } else if ($learner->risk_score >= 50) {
            $scoreclass = 'badge-warning';
        } else {
            $scoreclass = 'badge-info';
        }
        echo html_writer::start_tag('td');
        echo html_writer::tag('span', round($learner->risk_score), ['class' => 'badge ' . $scoreclass]);
        echo html_writer::end_tag('td');
        
        // Contributing factors.
        echo html_writer::start_tag('td');
        $factors = [];
        if (!empty($learner->factors)) {
            foreach ($learner->factors as $factor) {
                $factors[] = get_string('atrisk:factor_' . $factor, 'local_manireports');
            }
        }
        echo html_writer::tag('small', implode(', ', $factors));
        echo html_writer::end_tag('td');
        
        // Last activity.
        echo html_writer::tag('td', $learner->last_access ? userdate($learner->last_access) : get_string('never'));
        
        // Status.
        echo html_writer::start_tag('td');
        if ($acknowledged) {
            $ack = $acknowledgments[$key];
            $acknowledger = $DB->get_record('user', ['id' => $ack->acknowledgedby]);
            echo html_writer::tag('span', get_string('acknowledged', 'local_manireports'), ['class' => 'badge badge-success']);
            echo html_writer::tag('br');
            echo html_writer::tag('small', get_string('by') . ' ' . fullname($acknowledger));
            echo html_writer::tag('br');
            echo html_writer::tag('small', userdate($ack->timeacknowledged));
        } else {
            echo html_writer::tag('span', get_string('pending', 'local_manireports'), ['class' => 'badge badge-warning']);
        }
        echo html_writer::end_tag('td');
        
        // Actions.
        echo html_writer::start_tag('td');
        if (!$acknowledged) {
            $acknowledgeurl = new moodle_url($PAGE->url, [
                'action' => 'acknowledge',
                'userid' => $learner->userid,
                'courseid' => $learner->courseid,
                'sesskey' => sesskey(),
            ]);
            echo html_writer::link(
                '#',
                get_string('acknowledge', 'local_manireports'),
                [
                    'class' => 'btn btn-sm btn-primary acknowledge-btn',
                    'data-userid' => $learner->userid,
                    'data-courseid' => $learner->courseid,
                    'data-username' => fullname($user),
                ]
            );
        } else {
            $ack = $acknowledgments[$key];
            if (!empty($ack->note)) {
                echo html_writer::link(
                    '#',
                    get_string('viewnote', 'local_manireports'),
                    [
                        'class' => 'btn btn-sm btn-info view-note-btn',
                        'data-note' => s($ack->note),
                    ]
                );
            }
        }
        echo html_writer::end_tag('td');
        
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

// Add acknowledge modal.
echo html_writer::start_div('modal fade', ['id' => 'acknowledgeModal', 'tabindex' => '-1']);
echo html_writer::start_div('modal-dialog');
echo html_writer::start_div('modal-content');

echo html_writer::start_div('modal-header');
echo html_writer::tag('h5', get_string('atrisk:acknowledgetitle', 'local_manireports'), ['class' => 'modal-title']);
echo html_writer::tag('button', '&times;', ['type' => 'button', 'class' => 'close', 'data-dismiss' => 'modal']);
echo html_writer::end_div();

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'id' => 'acknowledgeForm']);
echo html_writer::start_div('modal-body');
echo html_writer::tag('p', '', ['id' => 'acknowledgeText']);
echo html_writer::start_div('form-group');
echo html_writer::tag('label', get_string('atrisk:note', 'local_manireports'), ['for' => 'note']);
echo html_writer::tag('textarea', '', [
    'name' => 'note',
    'id' => 'note',
    'class' => 'form-control',
    'rows' => 4,
    'placeholder' => get_string('atrisk:noteplaceholder', 'local_manireports'),
]);
echo html_writer::end_div();
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'acknowledge']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'id' => 'modal_userid']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid, 'id' => 'modal_courseid']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::end_div();

echo html_writer::start_div('modal-footer');
echo html_writer::tag('button', get_string('cancel'), ['type' => 'button', 'class' => 'btn btn-secondary', 'data-dismiss' => 'modal']);
echo html_writer::tag('button', get_string('acknowledge', 'local_manireports'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_div();
echo html_writer::end_tag('form');

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Add view note modal.
echo html_writer::start_div('modal fade', ['id' => 'viewNoteModal', 'tabindex' => '-1']);
echo html_writer::start_div('modal-dialog');
echo html_writer::start_div('modal-content');

echo html_writer::start_div('modal-header');
echo html_writer::tag('h5', get_string('atrisk:interventionnote', 'local_manireports'), ['class' => 'modal-title']);
echo html_writer::tag('button', '&times;', ['type' => 'button', 'class' => 'close', 'data-dismiss' => 'modal']);
echo html_writer::end_div();

echo html_writer::start_div('modal-body');
echo html_writer::tag('p', '', ['id' => 'noteContent']);
echo html_writer::end_div();

echo html_writer::start_div('modal-footer');
echo html_writer::tag('button', get_string('close'), ['type' => 'button', 'class' => 'btn btn-secondary', 'data-dismiss' => 'modal']);
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Add JavaScript.
echo html_writer::start_tag('script');
?>
document.addEventListener('DOMContentLoaded', function() {
    // Handle acknowledge button clicks
    document.querySelectorAll('.acknowledge-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var userid = this.getAttribute('data-userid');
            var courseid = this.getAttribute('data-courseid');
            var username = this.getAttribute('data-username');
            
            document.getElementById('modal_userid').value = userid;
            document.getElementById('modal_courseid').value = courseid;
            document.getElementById('acknowledgeText').textContent = 
                '<?php echo get_string('atrisk:acknowledgeconfirm', 'local_manireports'); ?>'.replace('{$a}', username);
            
            $('#acknowledgeModal').modal('show');
        });
    });
    
    // Handle view note button clicks
    document.querySelectorAll('.view-note-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var note = this.getAttribute('data-note');
            document.getElementById('noteContent').textContent = note;
            $('#viewNoteModal').modal('show');
        });
    });
});
<?php
echo html_writer::end_tag('script');

echo $OUTPUT->footer();

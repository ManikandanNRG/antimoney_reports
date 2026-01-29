<?php
/**
 * AJAX handler for Company Tab operations.
 * 
 * @package    local_manireports
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/output/dashboard_data_loader.php');

require_login();
require_sesskey();

$action = required_param('action', PARAM_ALPHANUMEXT);
$page = optional_param('page', 1, PARAM_INT);
$limit = optional_param('limit', 6, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$license_filter = optional_param('license_filter', 'all', PARAM_ALPHA);
$companyid = optional_param('companyid', 0, PARAM_INT);

// Admin only
if (!is_siteadmin()) {
    echo json_encode(['error' => 'Unauthorized']);
    die();
}

$loader = new \local_manireports\output\dashboard_data_loader($USER->id);

switch ($action) {
    case 'get_company_cards':
        try {
            $result = $loader->get_company_cards($page, $limit, $search, $license_filter);
            
            // Build HTML for cards
            $html = '';
            if (!empty($result['cards'])) {
                foreach ($result['cards'] as $card) {
                    $html .= render_company_card($card);
                }
            } else {
                $html = '<div style="grid-column: 1 / -1; text-align: center; padding: 60px; color: var(--text-secondary);">
                            <i class="fa-solid fa-building" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                            <p>No companies found matching your criteria.</p>
                         </div>';
            }
            
            echo json_encode([
                'html' => $html,
                'pagination' => $result['pagination']
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'html' => '<div style="grid-column: 1 / -1; text-align: center; padding: 60px; color: var(--accent-danger);"><i class="fa-solid fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 12px;"></i><p>Error: ' . htmlspecialchars($e->getMessage()) . '</p></div>',
                'pagination' => ['total' => 0, 'pages' => 0, 'current' => 1, 'limit' => $limit],
                'error' => $e->getMessage()
            ]);
        }
        break;
        
    case 'get_company_profile':
        if ($companyid <= 0) {
            echo json_encode(['error' => 'Invalid company ID']);
            die();
        }
        
        $profile = $loader->get_company_profile($companyid);
        echo json_encode($profile);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}

/**
 * Render a single company card HTML.
 * 
 * @param array $card Company card data
 * @return string HTML
 */
function render_company_card($card) {
    global $CFG;
    
    $manager_name = $card['manager'] ? htmlspecialchars($card['manager']['name']) : '';
    $manager_email = $card['manager'] ? htmlspecialchars($card['manager']['email']) : '';
    $coach_name = isset($card['coach']) && $card['coach'] ? htmlspecialchars($card['coach']['name']) : '';
    $coach_email = isset($card['coach']) && $card['coach'] ? htmlspecialchars($card['coach']['email']) : '';
    $domain = htmlspecialchars($card['domain']) ?: 'No domain';
    
    // License status styling
    $license_status = $card['license']['status'];
    $license_color = '#10b981'; // active
    $license_icon = '⏰';
    if ($license_status === 'expiring') {
        $license_color = '#f59e0b';
        $license_icon = '⚠️';
    } else if ($license_status === 'expired') {
        $license_color = '#ef4444';
        $license_icon = '❌';
    } else if ($license_status === 'none') {
        $license_color = '#64748b';
        $license_icon = '📜';
    }
    
    $license_used = $card['license']['used'];
    $license_total = $card['license']['total'];
    $license_percent = $card['license']['percent'];
    $license_expires = $card['license']['expires'] ?? 'No expiry';
    
    $reminder_count = $card['reminders']['count'];
    
    // Logo HTML - use actual logo if available, otherwise show initials
    $logo_url = $card['logo_url'] ?? null;
    if ($logo_url) {
        $logo_html = '<img src="' . htmlspecialchars($logo_url) . '" alt="' . htmlspecialchars($card['name']) . '" style="width: 48px; height: 48px; border-radius: 12px; object-fit: contain; background: #fff;">';
    } else {
        // Default initials avatar
        $logo_html = '<div style="width: 48px; height: 48px; background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary)); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 18px;">' . strtoupper(substr($card['name'], 0, 2)) . '</div>';
    }
    
    
    // SVG Circular Completion Graph
    $completion_rate = $card['stats']['completion_rate'];
    $circumference = 2 * 3.14159 * 22; // radius = 22
    $stroke_dashoffset = $circumference * (1 - $completion_rate / 100);
    
    $svg_circle = '<svg width="64" height="64" style="transform: rotate(-90deg);">' .
        '<circle cx="32" cy="32" r="22" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="4"/>' .
        '<circle cx="32" cy="32" r="22" fill="none" stroke="#6366f1" stroke-width="4" stroke-dasharray="' . $circumference . '" stroke-dashoffset="' . $stroke_dashoffset . '" stroke-linecap="round"/>' .
        '<text x="32" y="37" text-anchor="middle" fill="#fff" font-size="16" font-weight="600" transform="rotate(90 32 32)">' . $completion_rate . '%</text>' .
        '</svg>';
    
    
    
    $html = '
    <div class="company-card" data-companyid="' . $card['id'] . '" style="
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        transition: all 0.3s ease;
        cursor: pointer;
        height: 100%;
        min-height: 420px;
    " onmouseover="this.style.transform=\'translateY(-4px)\'; this.style.boxShadow=\'0 10px 30px rgba(0,0,0,0.3)\';" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'none\';" onclick="openCompanyProfile(' . $card['id'] . ')">
        
        <!-- Header: Logo + Name + Domain (40px) -->
        <div style="display: flex; align-items: center; gap: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--glass-border);">
            ' . $logo_html . '
            <div style="flex: 1; min-width: 0;">
                <div style="font-weight: 600; font-size: 15px; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . htmlspecialchars($card['name']) . '</div>
                <div style="font-size: 12px; color: var(--accent-primary); display: flex; align-items: center; gap: 4px;">
                    <i class="fa-solid fa-globe"></i> ' . $domain . '
                </div>
            </div>
        </div>
        
        <!-- TEAM Section (Fixed 110px height) -->
        <div style="display: flex; flex-direction: column; gap: 8px; min-height: 110px;">
            <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-bottom: 4px;">
                <i class="fa-solid fa-users"></i> Team
            </div>
            
            <!-- Manager Row (Fixed 46px) -->
            <div style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 8px; min-height: 46px;">';
    
    if ($manager_name) {
        $html .= '
                <i class="fa-solid fa-user-tie" style="color: #6366f1; font-size: 12px;"></i>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-size: 12px; font-weight: 500; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . $manager_name . '</div>
                    <div style="font-size: 10px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . $manager_email . '</div>
                </div>
                <span style="font-size: 9px; padding: 2px 6px; background: rgba(99, 102, 241, 0.2); color: #6366f1; border-radius: 4px; text-transform: uppercase; font-weight: 600;">Manager</span>';
    } else {
        $html .= '
                <i class="fa-solid fa-user-tie" style="color: rgba(99, 102, 241, 0.3); font-size: 12px;"></i>
                <div style="flex: 1; font-size: 12px; color: rgba(255,255,255,0.2);">No Manager Assigned</div>';
    }
    
    $html .= '
            </div>
            
            <!-- Coach Row (Fixed 46px) -->
            <div style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2); border-radius: 8px; min-height: 46px;">';
    
    if ($coach_name) {
        $html .= '
                <i class="fa-solid fa-chalkboard-teacher" style="color: #22c55e; font-size: 12px;"></i>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-size: 12px; font-weight: 500; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . $coach_name . '</div>
                    <div style="font-size: 10px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . $coach_email . '</div>
                </div>
                <span style="font-size: 9px; padding: 2px 6px; background: rgba(34, 197, 94, 0.2); color: #22c55e; border-radius: 4px; text-transform: uppercase; font-weight: 600;">Coach</span>';
    } else {
        $html .= '
                <i class="fa-solid fa-chalkboard-teacher" style="color: rgba(34, 197, 94, 0.3); font-size: 12px;"></i>
                <div style="flex: 1; font-size: 12px; color: rgba(255,255,255,0.2);">No Coach Assigned</div>';
    }
    
    $html .= '
            </div>
        </div>
        
        <!-- METRICS Section (3 columns with SVG) -->
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">
                <i class="fa-solid fa-chart-bar"></i> Metrics
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; padding: 12px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                <!-- Users Column -->
                <div style="text-align: center;">
                    <div style="font-size: 24px; font-weight: 700; color: var(--text-primary);">' . number_format($card['stats']['users']) . '</div>
                    <div style="font-size: 10px; color: var(--text-secondary); text-transform: uppercase; margin-top: 4px;">Users</div>
                </div>
                <!-- Courses Column -->
                <div style="text-align: center; border-left: 1px solid var(--glass-border); border-right: 1px solid var(--glass-border);">
                    <div style="font-size: 24px; font-weight: 700; color: var(--text-primary);">' . $card['stats']['courses'] . '</div>
                    <div style="font-size: 10px; color: var(--text-secondary); text-transform: uppercase; margin-top: 4px;">Courses</div>
                </div>
                <!-- Completion Column with SVG Circle -->
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center;">
                    ' . $svg_circle . '
                </div>
            </div>
        </div>
        
        <!-- LICENSE Section -->
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">
                <i class="fa-solid fa-id-card"></i> License
            </div>
            <div style="padding: 10px 12px; background: rgba(0,0,0,0.15); border-radius: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span style="font-size: 12px; color: var(--text-secondary);">Active</span>
                    <span style="font-size: 12px; font-weight: 600; color: ' . $license_color . ';">' . $license_used . '/' . $license_total . '</span>
                </div>
                <div style="height: 5px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden; margin-bottom: 8px;">
                    <div style="height: 100%; width: ' . $license_percent . '%; background: ' . $license_color . '; border-radius: 3px;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 11px;">
                    <span style="color: var(--text-secondary);">' . $license_icon . ' ' . $license_expires . '</span>
                    <span style="color: var(--accent-warning);"><i class="fa-solid fa-bell"></i> ' . $reminder_count . ' Reminders</span>
                </div>
            </div>
        </div>
        
        <!-- Action Button -->
        <button onclick="event.stopPropagation(); openCompanyProfile(' . $card['id'] . ');" style="
            margin-top: auto;
            padding: 10px 16px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        " onmouseover="this.style.opacity=\'0.9\'" onmouseout="this.style.opacity=\'1\'">
            View Profile →
        </button>
    </div>';
    
    return $html;
}

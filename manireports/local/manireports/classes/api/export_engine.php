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
 * Export engine for generating report exports in various formats.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Export engine class.
 */
class export_engine {

    /**
     * @var string File area for exports
     */
    const FILEAREA = 'manireports_exports';

    /**
     * Export report data to specified format.
     *
     * @param array $data Report data (array of objects)
     * @param array $columns Column definitions [key => label]
     * @param string $format Export format (csv, xlsx, pdf)
     * @param string $filename Base filename (without extension)
     * @return stored_file Stored file object
     * @throws moodle_exception If format is unsupported
     */
    public function export($data, $columns, $format, $filename) {
        global $USER;

        // Validate format.
        $supported_formats = array('csv', 'xlsx', 'pdf');
        if (!in_array($format, $supported_formats)) {
            throw new \moodle_exception('error:unsupportedformat', 'local_manireports', '', $format);
        }

        // Route to appropriate export method.
        switch ($format) {
            case 'csv':
                $content = $this->export_csv($data, $columns);
                $mimetype = 'text/csv';
                break;
            case 'xlsx':
                $content = $this->export_xlsx($data, $columns);
                $mimetype = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
            case 'pdf':
                $content = $this->export_pdf($data, $columns, $filename);
                $mimetype = 'application/pdf';
                break;
        }

        // Store file using Moodle File API.
        $stored_file = $this->store_export_file($content, $filename . '.' . $format, $mimetype);

        return $stored_file;
    }

    /**
     * Store export file using Moodle File API.
     *
     * @param string $content File content
     * @param string $filename Filename with extension
     * @param string $mimetype MIME type
     * @return stored_file Stored file object
     */
    private function store_export_file($content, $filename, $mimetype) {
        global $USER;

        $fs = get_file_storage();
        $context = \context_system::instance();

        // Prepare file record.
        $filerecord = array(
            'contextid' => $context->id,
            'component' => 'local_manireports',
            'filearea' => self::FILEAREA,
            'itemid' => $USER->id,
            'filepath' => '/',
            'filename' => $filename,
            'timecreated' => time(),
            'timemodified' => time(),
            'mimetype' => $mimetype,
            'userid' => $USER->id
        );

        // Delete existing file with same name.
        $existing_file = $fs->get_file(
            $filerecord['contextid'],
            $filerecord['component'],
            $filerecord['filearea'],
            $filerecord['itemid'],
            $filerecord['filepath'],
            $filerecord['filename']
        );

        if ($existing_file) {
            $existing_file->delete();
        }

        // Create new file.
        $stored_file = $fs->create_file_from_string($filerecord, $content);

        return $stored_file;
    }

    /**
     * Get download URL for a stored export file.
     *
     * @param stored_file $file Stored file object
     * @return moodle_url Download URL
     */
    public function get_download_url($file) {
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            true
        );
    }

    /**
     * Clean up old export files for a user.
     *
     * @param int $userid User ID
     * @param int $older_than Delete files older than this many seconds (default: 24 hours)
     */
    public function cleanup_old_exports($userid, $older_than = 86400) {
        $fs = get_file_storage();
        $context = \context_system::instance();

        $files = $fs->get_area_files(
            $context->id,
            'local_manireports',
            self::FILEAREA,
            $userid,
            'timecreated',
            false
        );

        $cutoff = time() - $older_than;

        foreach ($files as $file) {
            if ($file->get_timecreated() < $cutoff) {
                $file->delete();
            }
        }
    }

    /**
     * Export data to CSV format.
     *
     * Generates UTF-8 CSV with BOM, comma delimiter, double-quote enclosure.
     *
     * @param array $data Report data
     * @param array $columns Column definitions
     * @return string CSV content
     */
    private function export_csv($data, $columns) {
        // Ensure columns is an array (may be stdClass from cache).
        $columns = (array)$columns;
        
        // Use output buffering to capture CSV content.
        ob_start();
        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM for Excel compatibility.
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write header row.
        $headers = array_values($columns);
        fputcsv($output, $headers);

        // Write data rows.
        foreach ($data as $row) {
            $csv_row = array();
            foreach (array_keys($columns) as $key) {
                $value = isset($row->$key) ? $row->$key : '';
                // Clean value for CSV.
                $value = $this->clean_csv_value($value);
                $csv_row[] = $value;
            }
            fputcsv($output, $csv_row);
        }

        fclose($output);
        $content = ob_get_clean();

        return $content;
    }

    /**
     * Clean value for CSV export.
     *
     * @param mixed $value Value to clean
     * @return string Cleaned value
     */
    private function clean_csv_value($value) {
        // Convert to string.
        $value = (string)$value;

        // Strip HTML tags.
        $value = strip_tags($value);

        // Decode HTML entities.
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');

        // Remove extra whitespace.
        $value = trim($value);

        return $value;
    }

    /**
     * Export data to XLSX format using PHPSpreadsheet.
     *
     * @param array $data Report data
     * @param array $columns Column definitions
     * @return string XLSX content
     */
    private function export_xlsx($data, $columns) {
        global $CFG;

        // Ensure columns is an array (may be stdClass from cache).
        $columns = (array)$columns;

        // Check if PHPSpreadsheet is available (Moodle 3.8+).
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            // Fallback to CSV if PHPSpreadsheet not available.
            return $this->export_csv($data, $columns);
        }

        // Create new spreadsheet.
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set header row with formatting.
        $col = 1;
        foreach ($columns as $label) {
            $cell = $sheet->getCellByColumnAndRow($col, 1);
            $cell->setValue($label);
            
            // Format header.
            $cell->getStyle()->getFont()->setBold(true);
            $cell->getStyle()->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('CCCCCC');
            
            $col++;
        }

        // Write data rows.
        $row = 2;
        foreach ($data as $datarow) {
            $col = 1;
            foreach (array_keys($columns) as $key) {
                $value = isset($datarow->$key) ? $datarow->$key : '';
                $value = $this->clean_csv_value($value);
                
                $cell = $sheet->getCellByColumnAndRow($col, $row);
                $cell->setValue($value);
                
                $col++;
            }
            $row++;
        }

        // Auto-size columns.
        foreach (range(1, count($columns)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        // Generate XLSX content.
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return $content;
    }

    /**
     * Export data to PDF format using Moodle's PDF library.
     *
     * @param array $data Report data
     * @param array $columns Column definitions
     * @param string $title Report title
     * @return string PDF content
     */
    private function export_pdf($data, $columns, $title) {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');

        // Ensure columns is an array (may be stdClass from cache).
        $columns = (array)$columns;

        // Limit data for PDF to prevent memory issues.
        if (count($data) > 500) {
            $data = array_slice($data, 0, 500);
        }

        // Create PDF instance.
        $pdf = new \pdf();
        $pdf->SetTitle($title);
        $pdf->SetAuthor('ManiReports');
        $pdf->SetCreator('Moodle ManiReports Plugin');
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);

        // Add page.
        $pdf->AddPage('L'); // Landscape for better table fit.

        // Add title.
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, $title, 0, 1, 'C');
        $pdf->Ln(5);

        // Add timestamp.
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Generated: ' . userdate(time(), get_string('strftimedatetime', 'langconfig')), 0, 1, 'C');
        $pdf->Ln(5);

        // Build HTML table.
        $html = '<table border="1" cellpadding="4" cellspacing="0" style="width:100%;">';
        
        // Header row.
        $html .= '<thead><tr style="background-color:#CCCCCC;font-weight:bold;">';
        foreach ($columns as $label) {
            $html .= '<th>' . htmlspecialchars($label) . '</th>';
        }
        $html .= '</tr></thead>';

        // Data rows.
        $html .= '<tbody>';
        $row_count = 0;
        foreach ($data as $datarow) {
            $row_count++;
            $bg_color = ($row_count % 2 == 0) ? '#F5F5F5' : '#FFFFFF';
            $html .= '<tr style="background-color:' . $bg_color . ';">';
            
            foreach (array_keys($columns) as $key) {
                $value = isset($datarow->$key) ? $datarow->$key : '';
                $value = $this->clean_csv_value($value);
                $html .= '<td>' . htmlspecialchars($value) . '</td>';
            }
            
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        // Write HTML table to PDF.
        $pdf->SetFont('helvetica', '', 8);
        $pdf->writeHTML($html, true, false, true, false, '');

        // Get PDF content.
        $content = $pdf->Output('', 'S');

        return $content;
    }
}

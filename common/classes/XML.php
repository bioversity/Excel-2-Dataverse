<?php
/**
 * Bioversity AGROVOC Indexing
 *
 * PHP Version 7.2.11
 *
 * @copyright 2018 Bioversity International (http://www.bioversityinternational.org/)
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 * @link https://github.com/gubi/bioversity_agrovoc-indexing
*/

/**
 * A script for manage XML file and prepare data for Dataverse
 *
 * @package Bioversity AGROVOC Indexing
 * @author Alessandro Gubitosi <a.gubitosi@cgiar.org>
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 * @link https://github.com/gubi/bioversity_agrovoc-indexing
*/

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

class XML {
    public static $filename = "";
    public static $worksheet;
    public static $spreadsheet;

    /**
     * Initialize the XML file
     */
    public static function init() {
        self::$spreadsheet = new Spreadsheet();
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        self::$spreadsheet = $reader->load(self::$filename);
        self::$worksheet = self::$spreadsheet->getActiveSheet();
    }

    /**
     * Get the Excel column name
     * @example "A1"
     *
     * @param  integer                          $col                            The target column
     * @return string                                                           The column name
     */
    public static function get_excel_column($col) {
        return self::$worksheet->getCellByColumnAndRow($col, 1)->getCoordinate();
    }

    /**
     * Get the Excel column title
     *
     * @param  integer                          $col                            The target column
     * @return string                                                           The column name
     */
    public static function get_column_title($col) {
        return self::$worksheet->getCellByColumnAndRow($col, 1)->getValue();
    }

    /**
     * Get a single cell value
     *
     * @param  integer                          $row                            The target row
     * @param  integer                          $col                            The target column
     * @return string                                                           The cell value
     */
    public static function get_cell_value($row, $col) {
        return self::$worksheet->getCellByColumnAndRow($col, $row)->getValue();
    }

    /**
     * Determine if a given row is visible
     *
     * @param  integer                          $row                            The target row
     * @return boolean
     */
    public static function is_visible_row($row) {
        if(is_integer($row)) {
            return self::$spreadsheet->getActiveSheet()->getRowDimension($row)->getVisible();
        } else {
            return self::$spreadsheet->getActiveSheet()->getRowDimension($row->getRowIndex())->getVisible();
        }
    }
}

?>

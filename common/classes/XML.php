<?php


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

class XML {
    public static $data;
    public static $filename = "";
    public static $worksheet;
    public static $spreadsheet;
    public static $highestRow;
    public static $highestColumn;
    public static $highestColumnIndex;

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
     * Get the excel column name
     * @example "A1"
     *
     * @param  integer                          $col                            The target column
     * @return string                                                           The column name
     */
    public static function get_excel_column($col) {
        return self::$worksheet->getCellByColumnAndRow($col, 1)->getCoordinate();
    }

    /**
     * Get the excel column title
     * @param  integer                          $col                            The target column
     * @return string                                                           The column name
     */
    public static function get_column_title($col) {
        return self::$worksheet->getCellByColumnAndRow($col, 1)->getValue();
    }

    public static function get_cell_value($row, $col) {
        return self::$worksheet->getCellByColumnAndRow($col, $row)->getValue();
    }

    public static function is_visible_row($row) {
        if(is_integer($row)) {
            return self::$spreadsheet->getActiveSheet()->getRowDimension($row)->getVisible();
        } else {
            return self::$spreadsheet->getActiveSheet()->getRowDimension($row->getRowIndex())->getVisible();
        }
    }
}

?>

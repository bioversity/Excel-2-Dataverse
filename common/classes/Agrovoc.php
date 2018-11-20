<?php
/**
 * Bioversity AGROVOC Indexing
 *
 * PHP Version 7.2.11
 *
 * @copyright
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

require_once("vendor/autoload.php");
require_once("XML.php");
require_once("Obj.php");

use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class Agrovoc {
    public static $data;
    public static $filename = "";
    public static $spreadsheet;
    public static $highestRow;
    public static $highestColumn;
    public static $highestColumnIndex;
    public static $persistentId;
    /**
     * Filtering status
     * Can be "single" (default), "multiple" or "range"
     * @var string
     */
    public static $filter = "single";
    public static $available_rows;
    public static $unavailable_rows;
    private static $export_extensions = ["txt", "json"];

    /**
     * Open an URL using cURL
     *
     * @param  string                           $url                            The given URL
     * @return object                                                           A JSON decoded output
    */
    private static function url_open($url) {
        trigger_error("[INFO] Getting data from {$url}", E_USER_NOTICE);
        $logger = new Logger("agrovoc-indexing");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Dataverse-key:" . HARVARD_KEY]);

        $output = json_decode(curl_exec($ch));
        $output->headers = @curl_getinfo($ch);

        if(curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
            $logger->pushHandler(new StreamHandler(getcwd() . "/curl.log", Logger::ERROR));
            $logger->error(json_encode($output));

            return json_encode($output);
        }
        $output_array = json_decode(json_encode($output), 1);
        $output = Obj::move_to_bottom($output_array, "data");
        return json_decode(json_encode($output));

        curl_close($ch);
    }

    /**
     * Set a selected row filter
     *
     * @param string                            $filter                         The filter to select. Can be "single" (default), "multiple" or "range"
     */
    private function set_filter($filter) {
        self::$filter = $filter;
    }

    /**
     * Se globally the row availability and unavailability
     *
     * @param int|string                        $available                      Available row(s)
     * @param int|string                        $unavailable                    Unavailable row(s)
     */
    private function set_row_availability($available, $unavailable) {
        self::$available_rows = $available;
        self::$unavailable_rows = $unavailable;
    }

    /**
    * Recognise keywords for the "label" section
    *
    * @return array                                                             An array with "keywords" data
    */
    private static function recognise_label_keywords() {
        for($col = 1; $col <= self::$highestColumnIndex; $col++) {
            $column_name = XML::get_excel_column($col);
            $title = XML::get_column_title($col);
            $value = XML::get_cell_value(1, $col);

            if(strpos($title, "__") !== false) {
                $keywords = explode("__", $title);
                if(trim($keywords[1]) !== "" || $column_name == "A1") {
                    $array["_keywords"][$column_name] = $keywords[1];
                }
            } else {
                if(trim($value) !== "" || $column_name == "A1") {
                    $array[$column_name] = ucfirst($value);
                }
            }
        }
        return Obj::array_to_object($array);
    }

    /**
     * Recognise keywords for the "contents" section
     *
     * @param  integer                          $row                            The row number
     * @return array                                                            An array with "keywords" data
     */
    private static function recognise_content_keywords($row) {
        for($col = 1; $col <= self::$highestColumnIndex; $col++) {
            $title = XML::get_column_title($col);
            $value = XML::get_cell_value($row, $col);

            if(strpos($title, "__") !== false) {
                $keywords = explode("__", $title);
                if(trim($value) !== "") {
                    $array["_keywords"][$keywords[1]] = $value;
                }
            } else {
                if(trim($value) !== "") {
                    $array[$title] = $value;
                }
            }
        }
        return Obj::array_to_object($array);
    }

    /**
    * Create a new field value for Dataverse
    *
    * @param  object                            $field                          The field data
    * @param  string                            $type                           The new field value key name
    * @param  mixed                             $value                          The new field value data
    * @return object                                                            The object with new field data
    */
    private static function create_values($field, $type, $value) {
        $old_values = [];
        // $field_data = [];
        foreach($field as $k => $field_data) {
            $old_values[] = isset($field_data->{$type}) ? trim($field_data->{$type}->value) : null;

            if(isset($field_data->{$type})) {
                if(isset($_GET["debug"])) {
                    $field_data->{$type}->old_values = $old_values;
                }
                $field_data->{$type}->value = trim($value);
            } else {
                $field_data->{$type} = new stdClass();
                $field_data->{$type}->typeName = $type;
                $field_data->{$type}->multiple = false;
                $field_data->{$type}->typeClass = "primitive";
                if(isset($_GET["debug"])) {
                    $field_data->{$type}->old_values = null;
                }
                $field_data->{$type}->value = trim($value);
            }
        }
        return $field_data;
    }

    /**
     * Generate the new keywords tree
     *
     * @param  object                           $row_data                       The object that contains the dataset
     * @param  object                           $row_name                       The row name (typically "row <nr>")
     * @return object                                                           The object with new keywords data
     */
    private function generate_new_keywords_tree($row_data, $row_name) {
        $a = [];
        if(isset($row_data->dataset)) {
            trigger_error("[INFO] Parsing {$row_name}", E_USER_NOTICE);
            foreach($row_data->dataset->results->data->latestVersion->metadataBlocks->citation->fields as $k => $v) {
                if($v->typeName == "keyword") {
                    $v->value[count($v->value)-1] = self::create_values($v->value, "keywordValue", isset($row_data->_keywords->value) ? $row_data->_keywords->value : null);
                    $v->value[count($v->value)-1] = self::create_values($v->value, "keywordVocabulary", isset($row_data->Vocabulary) ? $row_data->Vocabulary : null);
                    $v->value[count($v->value)-1] = self::create_values($v->value, "keywordVocabularyURI", isset($row_data->_keywords->uri) ? $row_data->_keywords->uri : null);

                    $v->value = [$v->value[count($v->value)-1]];
                    $a[$k] = $v;
                } else {
                    $a[$k] = $v;
                }
            }
        }
        return $a;
    }

    /**
     * Check the file extension availability before save
     * @uses self::$export_extensions
     *
     * @param  string                           $extension                      The file extension
     * @return bool                                                             True if the extension is allowed
     */
    private static function check_extension_before_save($extension) {
        if(in_array($extension, self::$export_extensions)) {
            return true;
        }
    }

    /**
     * Save data to files
     *
     * @param  object                           $data                           The object to save
     * @param  string                           $name                           The file name. Default is "output"
     * @param  boolean                          $force                          Force overwriting? Default false
     * @param  string                           $extensions                     The desired extension
    */
    public static function save($data, $name = FILENAME, $force = false, $extensions = "json") {
        if(!$force) {
            if(self::check_extension_before_save($extensions)) {
                // Text file does not exists
                if(!file_exists(getcwd() . "/export/" . $name . ".txt")) {
                    if(is_array($extensions) && in_array("txt", $extensions) || $extensions == "txt") {
                        // Save the output as plain text object
                        file_put_contents(getcwd() . "/export/" . $name . ".txt", print_r($data, true));
                    }
                }
                // JSON file does not exists
                if(!file_exists(getcwd() . "/export/" . $name . ".json")) {
                    if(is_array($extensions) && in_array("json", $extensions) || $extensions == "json") {
                        // Save the output as json
                        if(is_array($data) || is_object($data)) {
                            $data = json_encode($data);
                        }
                        file_put_contents(getcwd() . "/export/" . $name . ".json", $data);
                    }
                }
            }
        } else {
            if(self::check_extension_before_save($extensions)) {
                $extensions = array_intersect($extensions, self::$export_extensions);
            }
            if(is_array($extensions) && in_array("txt", $extensions) || $extensions == "txt") {
                // Save the output as plain text object
                file_put_contents(getcwd() . "/export/" . $name . ".txt", print_r($data, true));
            }
            if(is_array($extensions) && in_array("json", $extensions) || $extensions == "json") {
                // Save the output as json
                if(is_array($data) || is_object($data)) {
                    $data = json_encode($data);
                }
                file_put_contents(getcwd() . "/export/" . $name . ".json", $data);
            }
        }
    }


    /* ---------------------------------------------------------------------- */

    /**
     * Get the persistentId
     *
     * @return string                                                           The persistentId
     */
    public static function get_persistendId() {
        return self::$persistentId;
    }

    /**
     * Build the Dataset API URL
     *
     * @param  string                           $server                         The host server
     * @param  string                           $persistentId                   The persistentId
     * @param  string                           $key                            The server token key
     * @return string                                                           The Dataset API URL
     */
    public static function build_dataset_api_url($server, $persistentId, $key = "") {
        return $server . "/api/datasets/:persistentId?" . (isset($key) ? "key=" . $key . "&" : "") . "persistentId=" . $persistentId;
    }

    /**
     * Analyse the GET requests
     *
     * @return array                                                            An array with available and not-available rows
     */
    private static function analyse_row_request() {
        $filter = [];
        switch(self::$filter) {
            case "single":
                $rows = [(int)$_GET["row"]];
                break;
            case "multiple":
                $rows = explode(",", $_GET["row"]);
                break;
            case "range":
                $rows = explode("-", $_GET["row"]);
                break;
        }
        if(is_array($rows)) {
            foreach($rows as $kr => $row) {
                if((int)$row == 1 || (int)$row > self::$highestRow) {
                    if(is_array($row)) {
                        $key = array_search($row, $rows);
                        if(false !== $key) {
                            unset($rows[$key]);
                        }
                        if((int)$row <= 1 && (int)$row <= self::$highestRow) {
                            $filter["available"] = $rows;
                        }

                        if((int)$row == $rows[$kr+1]) {
                            $rows[$kr] = 2;
                            unset($rows[$kr]);
                        }
                        sort($row);
                        $filter["unavailable"][] = $row;

                        self::set_row_availability($filter["available"], $filter["unavailable"]);
                    } else {
                        if((int)$row <= 1) {
                            unset($rows[$kr]);
                            $filter["unavailable"][] = (int)$row;
                            $filter["available"][] = $row+1;
                            self::set_row_availability($filter["available"], $filter["unavailable"]);
                        }

                        if((int)$row > self::$highestRow) {
                            if((int)$row <= 1 && (int)$row <= self::$highestRow) {
                                unset($rows[$kr]);
                            }
                            $rows[$kr] = self::$highestRow;
                            $filter["unavailable"][] = (int)$row;
                            $filter["available"] = $rows;
                            self::set_row_availability($filter["available"], $filter["unavailable"]);
                        }
                    }
                } else {
                    if(!isset($filter["available"])) {
                        if((int)$row > 1 && (int)$row <= self::$highestRow) {
                            $filter["available"] = $rows;
                        }
                        self::set_row_availability(@$filter["available"], @$filter["unavailable"]);
                    }
                }
            }
            sort($rows);
        }
        if(isset($filter["available"]) && count($filter["available"]) < 2) {
            self::set_filter("single");
        }

        return $filter;
    }

    /**
     * Create the stats section
     */
    private static function build_stats() {
        if(isset($_GET["row"])) {
            /**
             * Check for multiple filtering
             */
            if(strpos($_GET["row"], ",") !== false) {
                self::set_filter("multiple");
            }
            /**
             * Check for range filtering
             */
            if(strpos($_GET["row"], "-") !== false) {
                self::set_filter("range");
            }
        }

        /**
        * Last row number
        * Note: the first row is used for column title
        * @var integer
        */
        self::$highestRow = (int)XML::$worksheet->getHighestRow();
        /**
        * Last Excel column
        * @example "F"
        * @var string
        */
        self::$highestColumn = XML::$worksheet->getHighestDataColumn();
        /**
        * Last column index count
        * @var integer
        */
        self::$highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(self::$highestColumn);

        // Status
        self::$data = new stdClass();
        self::$data->status = new stdClass();
        self::$data->status->code = http_response_code();
        self::$data->status->date = date("Y-m-d H:i:s", $_SERVER["REQUEST_TIME"]);

        // Stats
        self::$data->{XML::$filename} = new stdClass();
        self::$data->{XML::$filename}->stats = new stdClass();
        self::$data->{XML::$filename}->stats = new stdClass();
        self::$data->{XML::$filename}->stats->columns = new stdClass();
        self::$data->{XML::$filename}->stats->columns->count = self::$highestColumnIndex;
        self::$data->{XML::$filename}->stats->columns->highest = self::$highestColumn;
        // Labels
        self::$data->{XML::$filename}->stats->columns->labels = self::recognise_label_keywords();

        // Row statistics
        self::$data->{XML::$filename}->stats->rows = new stdClass();
        self::$data->{XML::$filename}->stats->rows->total = self::$highestRow;

        if(isset($_GET["row"])) {
            self::$data->{XML::$filename}->stats->rows->filter = new stdClass();

            // Analyse the row request
            self::analyse_row_request();

            // Set stats
            $_get = explode(",", $_GET["row"]);
            sort($_get);
            self::$data->{XML::$filename}->stats->rows->filter->requested = self::$filter . ": " . Obj::list(explode(", ", $_GET["row"]));
            self::$data->{XML::$filename}->stats->rows->filter->unavailable = Obj::list(self::$unavailable_rows);
            self::$data->{XML::$filename}->stats->rows->filter->available = Obj::list(self::$available_rows);
        }
    }

    /**
     * Extract data from the Internet
     *
     * @param  integer                          $index                          The desired row number
     * @param  boolean                          $is_visible                     The row is visible?
     * @param  boolean                          $requested                      Is requested a single row?
     * @param  boolean                          $strip_prefix                   Strip the prefixed JSON tree?
     * @return object                                                           The extracted data
     */
    private static function extract_data($index, $is_visible, $requested = false, $strip_prefix = false) {
        $visible = ($is_visible) ? "visible" : "not visible";

        /**
         * Because counters start from 0 but not in the Excel file (which has also a row for labels),
         * there's difference between row number displayed and really parsed from the file
         * So the first row of data is the number 2
         */
        // The Excel row number
        $index = (!$requested) ? $index + 1 : $index;
        // The displayed row number
        $i = (!$requested) ? $index : $index;

        if($i > 1 && $i <= self::$highestRow) {
            $row_name = "row " . $i;
            $dataset_api_url = "";
            self::$data->{XML::$filename}->rows->{$visible}->contents[$row_name] = self::recognise_content_keywords($index);

            for($col = 1; $col <= self::$highestColumnIndex; $col++) {
                $title = XML::get_column_title($col);
                $value = XML::get_cell_value($index, $col);

                if($title == "id") {
                    // Match the schema (HDL or DOI)
                    $schema = (explode(".", parse_url($value)["host"])[0] == "hdl") ? "hdl" : "doi";
                    $id = substr(parse_url($value)["path"], 1);
                    self::$persistentId = (($schema == "hdl") ? "hdl" : "doi") . ":" . $id;
                    $dataset_api_url = self::build_dataset_api_url("https://dataverse.harvard.edu/", self::$persistentId);

                    self::$data->{XML::$filename}->rows->{$visible}->contents[$row_name]->dataset = new stdClass();
                    self::$data->{XML::$filename}->rows->{$visible}->contents[$row_name]->dataset->source = new stdClass();
                    self::$data->{XML::$filename}->rows->{$visible}->contents[$row_name]->dataset->source->doi = new stdClass();
                    self::$data->{XML::$filename}->rows->{$visible}->contents[$row_name]->dataset->target = new stdClass();
                    self::$data->{XML::$filename}->rows->{$visible}->contents[$row_name]->dataset->source->doi->uri = parse_url($value);
                    self::$data->{XML::$filename}->rows->{$visible}->contents[$row_name]->dataset->source->doi->uri["value"] = $value;
                    self::$data->{XML::$filename}->rows->{$visible}->contents[$row_name]->dataset->source->doi->value = $id;
                    self::$data->{XML::$filename}->rows->{$visible}->contents[$row_name]->dataset->target->persistentId = self::$persistentId;
                    self::$data->{XML::$filename}->rows->{$visible}->contents[$row_name]->dataset->target->dataset_api_url = $dataset_api_url;
                }
            }
            trigger_error("[INFO] Detected API URL: {$dataset_api_url}", E_USER_NOTICE);

            // Download data only for visible rows
            if($is_visible) {
                // Download datasets
                $dataset = self::url_open($dataset_api_url);
                self::$data->{XML::$filename}->rows->{$visible}->contents[$row_name]->dataset->results = $dataset;
                /**
                 * Assign new values
                 */
                $fields = self::generate_new_keywords_tree(self::$data->{XML::$filename}->rows->{$visible}->contents[$row_name], $row_name);
                self::$data->{XML::$filename}->rows->{$visible}->contents[$row_name]->dataset->results->data->latestVersion->metadataBlocks->citation->fields = $fields;
            }
            $rows = self::$data->{XML::$filename}->rows->{$visible}->contents[$row_name]->dataset->results->data->latestVersion->metadataBlocks->citation->fields;
            $metadataBlocks = (object)["datasetVersion" => self::$data->{XML::$filename}->rows->{$visible}->contents[$row_name]->dataset->results->data->latestVersion];
        }
        // Callback
        if(!$requested && $i <= (self::$highestRow - 1) || $requested && $i > 1 && $i <= self::$highestRow) {
            if($strip_prefix) {
                return $metadataBlocks;
            } else {
                return $rows;
            }
        }
    }

    /**
     * Parse the opened xml file by PHP Spreadsheet and do the job
     *
     * @param  array                            $filename                       The file to parse
     * @param  integer|string                   $parse_row                      The row to parse. Can be single, separated by "," or with "-" for a range
     * @param  boolean                          $output                         Display the output on screen?
     * @param  boolean                          $strip_prefix                   Strip the prefixed JSON tree?
     * @return object                                                           The result object
    */
    public static function parse_xml($filename, $parse_row, $output = false, $strip_prefix = false) {
        trigger_error("[INFO] Starting...", E_USER_NOTICE);
        XML::$filename = $filename;

        /**
         * Init the XML file
         */
        XML::init();

        /**
         * Build statistics
         */
        self::build_stats();

        if(!is_null($parse_row)) {
            $extracted = [];
            // Check for multiple filtering
            if(self::$filter == "multiple") {
                $rows = explode(",", $parse_row);
            }
            // Check for range filtering
            if(self::$filter == "range") {
                for($i = self::$available_rows[0]; $i <= self::$available_rows[1]; $i++) {
                    $rows[] = $i;
                }
            }

            $f = 0;
            foreach(XML::$spreadsheet->getActiveSheet()->getRowIterator() as $row) {
                $f++;
                $key = ($strip_prefix) ? "row " . $row->getRowIndex() : "";
                switch(self::$filter) {
                    case "single":
                        if($row->getRowIndex() == (int)$parse_row) {
                            $extracted[$key] = self::extract_data((int)$parse_row, XML::is_visible_row((int)$parse_row), true, $strip_prefix);

                            // Save data
                            if(!isset($_GET["debug"])) {
                                if(isset($_GET["only_fields"])) {
                                    self::save((object)$extracted[$key], $key);
                                }
                            }
                        }
                        break;
                    case "multiple":
                        if(in_array($row->getRowIndex(), $rows)) {
                            $extracted[$key] = self::extract_data((int)$row->getRowIndex(), XML::is_visible_row((int)$row->getRowIndex()), true, $strip_prefix);

                            // Save data
                            if(!isset($_GET["debug"])) {
                                if(isset($_GET["only_fields"])) {
                                    self::save((object)$extracted[$key], $key);
                                }
                            }
                        }
                        break;
                    case "range":
                        if(in_array($row->getRowIndex(), $rows)) {
                            $extracted[$key] = self::extract_data((int)$row->getRowIndex(), XML::is_visible_row((int)$row->getRowIndex()), true, $strip_prefix);

                            // Save data
                            if(!isset($_GET["debug"])) {
                                if(isset($_GET["only_fields"])) {
                                    self::save((object)$extracted[$key], $key);
                                }
                            }
                        }
                        break;
                }
            }
            if(!isset($extracted) && !$extracted) {
                trigger_error("--------------------------------------------------", E_USER_NOTICE);

                // Output on screen
                if($output) {
                    self::save(self::$data);
                    Obj::output(self::$data, true);
                }
            } else {
                // Output on screen
                if($output) {
                    Obj::output(self::$data, true);
                    Obj::output((object)$extracted, true);
                }
            }
        } else {
            foreach(XML::$spreadsheet->getActiveSheet()->getRowIterator() as $row) {
                $extracted["row " . $row->getRowIndex()] = self::extract_data($row->getRowIndex(), XML::is_visible_row($row), false);
                // Save data
                if(!isset($_GET["debug"])) {
                    self::save((object)$extracted[$key], $key);
                }
            }

            if(isset($extracted) && $extracted) {
                trigger_error("--------------------------------------------------", E_USER_NOTICE);

                // Output on screen
                if($output) {
                    self::save(self::$data);
                    Obj::output(self::$data, true);
                }
            } else {
                // Output on screen
                if($output) {
                    Obj::output(self::$data, true);
                    Obj::output((object)$extracted, true);
                }
            }
        }

        if(!isset($_GET["only_fields"])) {
            return self::$data;
        } else {
            return (object)$extracted;
        }
    }
}

?>

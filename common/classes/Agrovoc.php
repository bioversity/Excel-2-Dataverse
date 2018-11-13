<?php
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

    /**
    * Open an URL using cURL
    * @param  string                           $url                             The given URL
    * @return object                                                            A JSON decoded output
    */
    private static function url_open($url) {
        $logger = new Logger("agrovoc-indexing");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE,true);

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
    * Recognise keywords for the "label" section
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
     * @param  integer                          $row                          The row number
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
    * @param  object                           $field                           The field data
    * @param  string                           $type                            The new field value key name
    * @param  mixed                            $value                           The new field value data
    * @return object                                                            The object with new field data
    */
    private static function create_values($field, $type, $value) {
        $old_values = [];
        // $field_data = [];
        foreach($field as $k => $field_data) {
            $old_values[] = isset($field_data->{$type}) ? trim($field_data->{$type}->value) : null;

            if(isset($field_data->{$type})) {
                $field_data->{$type}->old_values = $old_values;
                $field_data->{$type}->value = trim($value);
            } else {
                $field_data->{$type} = new stdClass();
                $field_data->{$type}->typeName = $type;
                $field_data->{$type}->multiple = false;
                $field_data->{$type}->typeClass = "primitive";
                $field_data->{$type}->old_values = null;
                $field_data->{$type}->value = trim($value);
            }
        }
        // $field_data->{$type} = "ok";
        // print_r($field_data);
        return $field_data;
    }

    private function generate_new_keywords_tree($row_data, $row_name) {
        $a = [];
        if(isset($row_data->dataset)) {
            foreach($row_data->dataset->results->data->latestVersion->metadataBlocks->citation->fields as $k => $v) {
                if($v->typeName == "keyword") {
                    $v->value[count($v->value)-1] = self::create_values($v->value, "keywordValue", isset($row_data->_keywords->value) ? $row_data->_keywords->value : null);
                    $v->value[count($v->value)-1] = self::create_values($v->value, "keywordVocabulary", isset($row_data->Vocabulary) ? $row_data->Vocabulary : null);
                    $v->value[count($v->value)-1] = self::create_values($v->value, "keywordVocabularyURI", isset($row_data->_keywords->uri) ? $row_data->_keywords->uri : null);

                    $a[$k] = $v->value[count($v->value)-1];
                } else {
                    $a[$k] = $v;
                }
            }
        }
        return $a;
    }

    /**
    * Save data to files
    * @param  object                           $data                            The object to save
    * @param  string                           $name                            The file name
    * @param  boolean                          $force                           Force overwriting? Default false
    */
    public static function save($data, $name = "output", $force = false) {
        if($force) {
            // Save the output as plain text object
            file_put_contents(getcwd() . "/" . $name . ".txt", print_r($data, true));
            // Save the output as json
            file_put_contents(getcwd() . "/" . $name . ".json", json_encode($data, JSON_PRETTY_PRINT));
        } else {
            // Text file does not exists
            if(!file_exists(getcwd() . "/" . $name . ".txt")) {
                // Save the output as plain text object
                file_put_contents(getcwd() . "/" . $name . ".txt", print_r($data, true));
            }
            // JSON file does not exists
            if(!file_exists(getcwd() . "/" . $name . ".json")) {
                // Save the output as json
                file_put_contents(getcwd() . "/" . $name . ".json", json_encode($data, JSON_PRETTY_PRINT));
            }
        }
    }


    public static function build_label_section() {

    }

    public static function build_stats() {
        /**
        * Last row number
        * @var integer
        */
        self::$highestRow = (int)XML::$worksheet->getHighestRow();
        /**
        * Last excel column
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
        self::$data->{XML::$filename}->stats->columns->_labels = self::recognise_label_keywords();

        self::$data->{XML::$filename}->stats->rows = new stdClass();
        self::$data->{XML::$filename}->stats->rows->count = self::$highestRow;
    }

    /* ---------------------------------------------------------------------- */

    private static function extract_data($index, $is_visible, $requested) {
        $visible = ($is_visible) ? "visible" : "not visible";
        $index = ($requested) ? $index + 1 : $index;
        $i = (!$requested) ? $index - 1: $index - 1;

        self::$data->{XML::$filename}->contents["row " . $i] = self::recognise_content_keywords($index);

        for($col = 1; $col <= self::$highestColumnIndex; $col++) {
            $title = XML::get_column_title($col);
            $value = XML::get_cell_value($index, $col);

            if($title == "id") {
                // Match the schema (HDL or DOI)
                $schema = (explode(".", parse_url($value)["host"])[0] == "hdl") ? "hdl" : "doi";
                $id = substr(parse_url($value)["path"], 1);
                $dataset_api_url = "https://dataverse.harvard.edu/api/datasets/:persistentId?persistentId=" . (($schema == "hdl") ? "hdl" : "doi") . ":" . $id;

                self::$data->{XML::$filename}->contents["row " . $i]->dataset = new stdClass();
                self::$data->{XML::$filename}->contents["row " . $i]->dataset->source = new stdClass();
                self::$data->{XML::$filename}->contents["row " . $i]->dataset->source->doi = new stdClass();
                self::$data->{XML::$filename}->contents["row " . $i]->dataset->target = new stdClass();

                self::$data->{XML::$filename}->contents["row " . $i]->dataset->source->doi->uri = parse_url($value);
                self::$data->{XML::$filename}->contents["row " . $i]->dataset->source->doi->uri["value"] = $value;
                self::$data->{XML::$filename}->contents["row " . $i]->dataset->source->doi->value = $id;
                self::$data->{XML::$filename}->contents["row " . $i]->dataset->target->dataset_api_url = $dataset_api_url;

                // Download data only for visible rows
                if($is_visible) {
                    // Download datasets only for first 3 rows
                    $dataset = self::url_open($dataset_api_url);
                    self::$data->{XML::$filename}->contents["row " . $i]->dataset->results = $dataset;

                    /**
                    * Assign new values
                    */
                   foreach(self::$data->{XML::$filename}->contents as $row_name => $row_data) {
                       self::$data->{XML::$filename}->contents["row " . $i]->dataset->results->data->latestVersion->metadataBlocks->citation->fields = self::generate_new_keywords_tree($row_data, $row_name);
                   }
                }
            }
        }
    }

    /**
    * Parse the opened xml file by PHP Spreadsheet and do the job
    * @param  array                            $filename                        The file to parse
    * @param  array                            $parse_row                        The file to parse
    * @return object                                                            The result object
    */
    public static function parse_xml($filename, $parse_row) {
        XML::$filename = $filename;

        /**
         * Init the XML file
         */
        XML::init();

        /**
         * Build statistics
         */
        self::build_stats();

        self::build_label_section();

        if(!is_null($parse_row)) {
            foreach(XML::$spreadsheet->getActiveSheet()->getRowIterator() as $row) {
                if($row->getRowIndex() == (int)$parse_row) {
                    self::extract_data((int)$parse_row, XML::is_visible_row((int)$parse_row), true);
                }
            }
        } else {
            foreach(XML::$spreadsheet->getActiveSheet()->getRowIterator() as $row) {
                self::extract_data($row->getRowIndex(), XML::is_visible_row($row), false);
            }

            // Save data for future purposes
            // self::save(self::$data);
        }

        return self::$data;
    }
}

?>

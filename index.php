<?php
ini_set("memory_limit", "1G");
header("Content-type: text/plain");

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


define("FILENAME", "resultAgrovoc_filled_20181108.xlsx");

$spreadsheet = new Spreadsheet();
$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load(FILENAME);
$worksheet = $spreadsheet->getActiveSheet();
$json = [];
$json[FILENAME] = [];

/**
 * Move an array item to the top of order
 * @param  array                            $array                              The array to sort
 * @param  string                           $key                                The item to move to the top
 * @return array                                                                The sorted array
 */
function move_to_top(&$array, $key) {
    return array_splice($array, array_search($key, array_keys($array)), 1) + $array;
}

/**
 * Move an array item to the bottom of order
 * @param  array                            $array                              The array to sort
 * @param  string                           $key                                The item to move to the bottom
 * @return array                                                                The sorted array
 */
function move_to_bottom(&$array, $key) {
    return $array + array_splice($array, array_search($key, array_keys($array)), 1);
}

/**
 * Open an URL using cURL
 * @param  string                           $url                                The given URL
 * @return object                                                               A JSON decoded output
 */
function url_open($url) {
    $logger = new Logger("agrovoc-indexing");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch,CURLOPT_VERBOSE,true);

    $output = json_decode(curl_exec($ch));
    $output->headers = curl_getinfo($ch);

    if(curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
        $logger->pushHandler(new StreamHandler(getcwd() . "/curl.log", Logger::ERROR));
        $logger->error(json_encode($output));

        return json_encode($output);
    }
    $output_array = json_decode(json_encode($output), 1);
    $output = move_to_bottom($output_array, "data");
    return json_decode(json_encode($output));

    curl_close($ch);
}

function recognise_keywords(&$arr, $index, $title, $value, $section, $column_name = null) {
    if($section == "_labels") { // -------------------------------------------> "_labels" section
        if(strpos($title, "__") !== false) {
            $keywords = explode("__", $title);
            if(trim($keywords[1]) !== "" || $column_name == "A1") {
                $arr[$section]["row " . $index]["_keywords"][$column_name] = $keywords[1];
            }
        } else {
            if(trim($value) !== "" || $column_name == "A1") {
                $arr[$section]["row " . $index][$column_name] = ucfirst($value);
            }
        }
    } elseif($section == "contents") { // ------------------------------------> "contents" section
        if(strpos($title, "__") !== false) {
            $keywords = explode("__", $title);
            if(trim($value) !== "") {
                $arr[$section]["row " . $index]["_keywords"][$keywords[1]] = $value;
            }
        } else {
            if(trim($value) !== "") {
                $arr[$section]["row " . $index][$title] = $value;
            }
        }
    }
}

function create_values($field, $type, $value) {
    $old_values = [];
    // $field_data = [];
    foreach($field as $k => $field_data) {
        $old_values[] = trim($field_data->{$type}->value);

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

function save($data, $name = "output", $force = false) {
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
/**
 * Parse the opened xml file by PHP Spreadsheet and do the job
 * @param  array                            $json                               The array to manipulate
 * @param  object                           $row                                The PHP Spreadsheet row object
 * @param  object                           $worksheet                          The PHP Spreadsheet worksheet object
 * @param  bool                             $visible                            This row is visible?
 * @return object                                                               The result object
 */
function parse_xml($json, $row, $worksheet, $visible) {
    $visible_label = ($visible) ? "visible" : "not visible";
    $highestRow = $worksheet->getHighestRow(); // e.g. 10
    $highestColumn = $worksheet->getHighestDataColumn(); // e.g 'F'
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn); // e.g. 5


    $json["status"]["code"] = http_response_code();
    $json["status"]["date"] = date("Y-m-d H:i:s", $_SERVER["REQUEST_TIME"]);
    // Stats
    $json["stats"]["columns"]["count"] = $highestColumnIndex;
    $json["stats"]["columns"]["highest"] = $highestColumn;
    $json["stats"]["rows"]["count"] = (int)$highestRow;

    for($col = 1; $col <= $highestColumnIndex; $col++) {
        // The first row is used for labels
        $title = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
        $value = $worksheet->getCellByColumnAndRow($col, $row->getRowIndex())->getValue();

        if($row->getRowIndex() == 1) { // ------------------------------------> "_labels" section
            $column_name = $worksheet->getCellByColumnAndRow($col, 1)->getCoordinate();
            // Split keywords in sub-labels
            recognise_keywords($json["rows"][$visible_label], $row->getRowIndex(), $title, $value, "_labels", $column_name);
        } elseif($row->getRowIndex() > 1) { // -------------------------------> "contents" section
            // Split keywords in sub-labels
            recognise_keywords($json["rows"][$visible_label], $row->getRowIndex(), $title, $value, "contents");

            if($title == "id") {
                // $json["rows"][$visible_label]["contents"]["row " . $row->getRowIndex()]["visible"] = $visible;

                // Match the schema (HDL or DOI)
                $schema = (explode(".", parse_url($value)["host"])[0] == "hdl") ? "hdl" : "doi";
                $id = substr(parse_url($value)["path"], 1);
                $dataset_api_url = "https://dataverse.harvard.edu/api/datasets/:persistentId?persistentId=" . (($schema == "hdl") ? "hdl" : "doi") . ":" . $id;

                $json["rows"][$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["source"]["doi"]["uri"] = parse_url($value);
                $json["rows"][$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["source"]["doi"]["uri"]["value"] = $value;
                $json["rows"][$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["source"]["doi"]["value"] = $id;
                $json["rows"][$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["target"]["dataset_api_url"] = $dataset_api_url;

                if($visible) {
                    // Download datasets only for first 3 rows
                    // if(($row->getRowIndex() - 1) <= 3) {
                        $dataset = url_open($dataset_api_url);
                        $json["rows"][$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["results"] = $dataset;
                    // } else {
                    //     $json["rows"][$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["results"] = null;
                    // }
                }
            }
            $json["rows"][$visible_label]["contents"]["row " . $row->getRowIndex()] = move_to_bottom($json["rows"][$visible_label]["contents"]["row " . $row->getRowIndex()], "dataset");
        }
    }
    $json["rows"][$visible_label] = move_to_top($json["rows"][$visible_label], "_labels");

    return $json;
}
// exit();

// Check whether the output file exists (speed up and separate jobs)
if(!file_exists(getcwd() . "/output.json")) {
    /**
    * Iterate rows
    */
    foreach($spreadsheet->getActiveSheet()->getRowIterator() as $row) {
        // Separe visible/not visible rows
        if($spreadsheet->getActiveSheet()->getRowDimension($row->getRowIndex())->getVisible()) {
            $json[FILENAME] = parse_xml($json[FILENAME], $row, $worksheet, true);
        } else {
            $json[FILENAME] = parse_xml($json[FILENAME], $row, $worksheet, false);
        }
    }
    save($json);
} else {
    // $changes = [];
    $json = json_decode(file_get_contents(getcwd() . "/output.json"));
    foreach($json->{FILENAME}->rows->visible->contents as $row_name => $row_data) {
        if(!isset($row_data->dataset->results->data)) {
            print $row_name . " with new keyword \"" . $row_data->_keywords->value . "\" does not exists in Dataverse\n";
            print "Check the dataset url schema!";
            exit();
        } else {
            // Empty keywords in the excel file
            if(!isset($row_data->_keywords) || !isset($row_data->_keywords->value)) {
                foreach($row_data->dataset->results->data->latestVersion->metadataBlocks->citation->fields as $k => $v) {
                    if($v->typeName == "keyword") {
                        $v->value[count($v->value)-1] = create_values($v->value, "keywordValue", $row_data->_keywords->value);
                        $v->value[count($v->value)-1] = create_values($v->value, "keywordVocabulary", isset($row_data->Vocabulary) ? $row_data->Vocabulary : null);
                        $v->value[count($v->value)-1] = create_values($v->value, "keywordVocabularyURI", isset($row_data->_keywords->uri) ? $row_data->_keywords->uri : null);

                        $changes[$row_name] = $v->value[count($v->value)-1];
                        $row_data->dataset->results->data->latestVersion->metadataBlocks->citation->fields[$k]->value = $v->value[count($v->value)-1];
                    }
                }
            } else {
                foreach($row_data->dataset->results->data->latestVersion->metadataBlocks->citation->fields as $k => $v) {
                    if($v->typeName == "keyword") {
                        $v->value[count($v->value)-1] = create_values($v->value, "keywordValue", $row_data->_keywords->value);
                        $v->value[count($v->value)-1] = create_values($v->value, "keywordVocabulary", isset($row_data->Vocabulary) ? $row_data->Vocabulary : null);
                        $v->value[count($v->value)-1] = create_values($v->value, "keywordVocabularyURI", isset($row_data->_keywords->uri) ? $row_data->_keywords->uri : null);

                        $changes[$row_name] = $v->value[count($v->value)-1];
                        $row_data->dataset->results->data->latestVersion->metadataBlocks->citation->fields[$k]->value = $v->value[count($v->value)-1];
                    }
                }
            }
        }
    }
}

// Display the output as plain text
// print_r($changes);
// print_r($json);

// Display the output as json
header("Content-type: application/json");
// print_r(json_encode($changes, JSON_PRETTY_PRINT));
print_r(json_encode($json, JSON_PRETTY_PRINT));

save($changes, "changes");
save($json, "output");
?>

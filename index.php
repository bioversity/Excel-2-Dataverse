<?php
ini_set("memory_limit", "1G");
header("Content-type: text/plain");

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


define("FILENAME", "resultAgrovoc_filled_20181102.xlsx");

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

                $doi = substr(parse_url($value)["path"], 1);
                $dataset_api_url = "https://dataverse.harvard.edu/api/datasets/:persistentId?persistentId=doi:" . $doi;

                $json["rows"][$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["source"]["doi"]["uri"] = parse_url($value);
                $json["rows"][$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["source"]["doi"]["uri"]["value"] = $value;
                $json["rows"][$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["source"]["doi"]["value"] = $doi;
                $json["rows"][$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["target"]["dataset_api_url"] = $dataset_api_url;

                if($visible) {
                    // Download datasets only for first 3 rows
                    // if(($row->getRowIndex() - 1) <= 3) {
                        $json["rows"][$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["results"] = url_open($dataset_api_url);
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

// Display the output as plain text
// print_r($json);

// Save the output as plain text object
file_put_contents(getcwd() . "/output.txt", print_r($json, true));

header("Content-type: application/json");
// Display the output as json
// print_r(json_encode($json, JSON_PRETTY_PRINT));
// Save the output as json
file_put_contents(getcwd() . "/output.json", json_encode($json, JSON_PRETTY_PRINT));
?>

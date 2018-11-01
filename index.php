<?php
header("Content-type: text/plain");

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


define("FILENAME", "resultAgrovoc_filled_20181029.xlsx");

$spreadsheet = new Spreadsheet();
$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load(FILENAME);
$worksheet = $spreadsheet->getActiveSheet();
$json = [];
$json[FILENAME] = [];

/**
 * Open an URL using cURL
 * @param  string                           $url                                The given URL
 * @return object                                                               A JSON decoded output
 */
function url_open($url) {
    $logger = new Logger("agrovoc-indexing");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);

    if(curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
        $logger->pushHandler(new StreamHandler(getcwd() . "/curl.log", Logger::ERROR));
        $logger->error($output);

        return json_decode($output);
    }
    return json_decode($output)->data;

    curl_close($ch);
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
    $highestColumn = $worksheet->getHighestColumn(); // e.g 'F'
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn); // e.g. 5

    for($col = 1; $col <= $highestColumnIndex; $col++) {
        // The first row is used for labels
        $title = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
        $value = $worksheet->getCellByColumnAndRow($col, $row->getRowIndex())->getValue();

        if($row->getRowIndex() == 1) { // ---------------------------------> "_labels" section
            $column_name = $worksheet->getCellByColumnAndRow($col, 1)->getCoordinate();
            // Split keywords in sub-labels
            if(strpos($title, "__") !== false) {
                $keywords = explode("__", $title);
                $json[$visible_label]["_labels"]["row " . $row->getRowIndex()]["_keywords"][$column_name] = $keywords[1];
            } else {
                $json[$visible_label]["_labels"]["row " . $row->getRowIndex()][$column_name] = $value;
            }
        } elseif($row->getRowIndex() > 1) { // ----------------------------> "contents" section
            // Split keywords in sub-labels
            if(strpos($title, "__") !== false) {
                $keywords = explode("__", $title);
                $json[$visible_label]["contents"]["row " . $row->getRowIndex()]["_keywords"][$keywords[1]] = $value;
            } else {
                $json[$visible_label]["contents"]["row " . $row->getRowIndex()][$title] = $value;
            }

            if($title == "id") {
                $doi = substr(parse_url($value)["path"], 1);
                $dataset_api_url = "https://dataverse.harvard.edu/api/datasets/:persistentId?persistentId=doi:" . $doi;
                $json[$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"] = parse_url($value);
                $json[$visible_label]["contents"]["row " . $row->getRowIndex()]["visible"] = $visible;
                if($visible) {
                    $json[$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["doi"] = $doi;
                    $json[$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["dataset_api_url"] = $dataset_api_url;

                    // Download datasets only for row 24, 57 and 58
                    if($row->getRowIndex() == 24 || $row->getRowIndex() == 57 || $row->getRowIndex() == 58) {
                        $json[$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["data"] = url_open($dataset_api_url);
                    } else {
                        $json[$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["data"] = null;
                    }
                }
            }
        }
    }

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
print_r(json_encode($json, JSON_PRETTY_PRINT));
// Save the output as json
file_put_contents(getcwd() . "/output.json", json_encode($json, JSON_PRETTY_PRINT));
?>

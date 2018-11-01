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
    // $logger->pushHandler(new StreamHandler(getcwd() . "/curl.log", Logger::INFO));
    // $logger->info($output->data);
    return json_decode($output)->data;

    curl_close($ch);
}

function parse_xml($json, $row, $worksheet, $visible) {
    $visible_label = ($visible) ? "visible" : "not visible";
    $highestRow = $worksheet->getHighestRow(); // e.g. 10
    $highestColumn = $worksheet->getHighestColumn(); // e.g 'F'
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn); // e.g. 5

    for($col = 1; $col <= $highestColumnIndex; $col++) {
        // $lastCellAddress = $worksheet->getCellByColumnAndRow($$col, 1)->getCoordinate();
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
            // $visible_rows = [];
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
                    // Extract datasets only for row 24
                    if($row->getRowIndex() == 24 || $row->getRowIndex() == 57 || $row->getRowIndex() == 58) {
                        $json[$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["doi"] = $doi;
                        $json[$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["dataset_api_url"] = $dataset_api_url;
                        // $json[$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["data"] = json_decode(url_open($dataset_api_url), 1)["data"];

                        // LOG
                        // $logger->warning(escapeshellcmd(url_open($dataset_api_url)));
                        // print $dataset_api_url ."\n";
                        $json[$visible_label]["contents"]["row " . $row->getRowIndex()]["dataset"]["data"] = url_open($dataset_api_url);
                    }
                }
            }
        }
    }

    // print_r($json);
    return $json;
}

foreach($spreadsheet->getActiveSheet()->getRowIterator() as $row) {
    // print $spreadsheet->getActiveSheet()->getRowDimension($row->getRowIndex())->getVisible();
    if($spreadsheet->getActiveSheet()->getRowDimension($row->getRowIndex())->getVisible()) {
        // print $row->getRowIndex() . "\n";
        // exit();
        $json[FILENAME] = parse_xml($json[FILENAME], $row, $worksheet, true);
    } else {
        $json[FILENAME] = parse_xml($json[FILENAME], $row, $worksheet, false);
    }
}
print_r($json);
file_put_contents(getcwd() . "/output.txt", print_r($json, true));
file_put_contents(getcwd() . "/output.json", json_encode($json, JSON_PRETTY_PRINT));
// header("Content-type: application/json");
// print_r(json_encode($json));
?>

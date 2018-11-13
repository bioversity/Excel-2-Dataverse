<?php
ini_set("memory_limit", "1G");
header("Content-type: text/plain");

require_once("vendor/autoload.php");
require_once("common/classes/Obj.php");
require_once("common/classes/Agrovoc.php");

use Monolog\Logger;
use Monolog\Handler\StreamHandler;


define("FILENAME", "resultAgrovoc_filled_20181108.xlsx");


$parse_row = (isset($_GET["row"])) ? $_GET["row"] : null;

// Check whether the output file exists (speed up and separate jobs)
if(!file_exists(getcwd() . "/output.json")) {
    /**
     * Parse the excel file
     */
    $data = Agrovoc::parse_xml(FILENAME, $parse_row);

    print_r($data);
} else {
    // $json = json_decode(file_get_contents(getcwd() . "/output.json"));
    // foreach($json->{FILENAME}->rows->visible->contents as $row_name => $row_data) {
    //     if(!isset($row_data->dataset->results->data)) {
    //         print $row_name . " with new keyword \"" . $row_data->_keywords->value . "\" does not exists in Dataverse\n";
    //         print "Check the dataset url schema!";
    //         exit();
    //     } else {
    //         /**
    //          * Assign new values
    //          */
    //
    //         // Empty keywords in the excel file
    //         if(!isset($row_data->_keywords) || !isset($row_data->_keywords->value)) {
    //             $changes[$row_name] = Agrovoc::generate_new_keywords_tree($row_data, $row_name);
    //             $row_data->dataset->results->data->latestVersion->metadataBlocks->citation->fields[$k]->value = Agrovoc::generate_new_keywords_tree($row_data, $row_name);
    //         } else {
    //             $changes[$row_name] = Agrovoc::generate_new_keywords_tree($row_data, $row_name);
    //             $row_data->dataset->results->data->latestVersion->metadataBlocks->citation->fields[$k]->value = Agrovoc::generate_new_keywords_tree($row_data, $row_name);
    //         }
    //     }
    // }
}

// Display the output as plain text
// print_r($changes);
// print_r($json);

// Display the output as json
header("Content-type: application/json");
// print_r(json_encode($changes, JSON_PRETTY_PRINT));
print_r(json_encode($data, JSON_PRETTY_PRINT));

// Agrovoc::save($changes, "changes");
// Agrovoc::save($json, "output");
?>

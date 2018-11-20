<?php
/**
 * Excel to Dataverse
 * This script parse an xml file row by row, extract keywords and publish the correct metadata to a given Dataverse
 *
 * Available parameters:
 * @var row             Filter one or more rows (separate with "," or with "-" to select a range)
 * @var debug           Set debug mode. NOTE: debug mode does not save local files and append "old_values" into the output tree
 * @var only_fields     Display only fields in `dataset > results > data > latestVersion > metadataBlocks`
 * @var
 */

ini_set("memory_limit", "1G");
if(isset($_GET["debug"])) {
    header("Content-type: text/plain");
} else {
    header("Content-type: application/json");
}


/* -----------------------------------------------------------------------------
                                    REQUIRES
----------------------------------------------------------------------------- */

require_once("vendor/autoload.php");
require_once("common/classes/Obj.php");
require_once("common/classes/Agrovoc.php");
require_once("dataverse-php-library/Request_handler.php");
/**
 * Require all classes in "dataverse-php-library/classes/" dir
 */
foreach (glob(getcwd() . "/dataverse-php-library/classes/*") as $filename) {
    require_once($filename);
}
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


/* -----------------------------------------------------------------------------
                                    DEFINES
----------------------------------------------------------------------------- */

$parse_row = (isset($_GET["row"])) ? $_GET["row"] : null;
$strip_prefix = (isset($_GET["strip_prefix"])) ? true : false;
$check_file = ((!is_null($parse_row)) ? "row_" . $parse_row : "output") . ((isset($_GET["only_fields"])) ? "-only_fields" : "") . ((isset($_GET["strip_prefix"])) ? "-strip_prefix" : "");
define("SOURCE", "resultAgrovoc_filled_20181108.xlsx");
define("FILENAME", $check_file);
define("STRIP_PREFIX", $strip_prefix);
define("APITEST_KEY", "<YOUR-KEY>");
define("HARVARD_KEY", "<YOUR-KEY>");
define("TARGET_SERVER", "https://apitest.dataverse.org");


/* -----------------------------------------------------------------------------
                                    MAIN SCRIPT
----------------------------------------------------------------------------- */

cURL::set_APITEST_KEY(APITEST_KEY);
cURL::set_server(TARGET_SERVER);

/**
* Parse the excel file
* @uses Agrovoc::parse_xml()
*/
$parsed = Agrovoc::parse_xml(SOURCE, $parse_row, false, $strip_prefix);
// Cyclate parsed files
foreach($parsed as $k => $v) {
    /**
     * Add to "agrovoc" Dataverse
     * @see  https://apitest.dataverse.org/api/dataverses/agrovoc?key=3e8b9d00-e7d4-4100-baf6-6210223591f1
     * @uses Datasets::add_dataset_to_dataverse()
     * @uses Agrovoc::save()
     */
    $output = Datasets::add_dataset_to_dataverse("agrovoc", getcwd() . "/export/{$k}.json");
    Agrovoc::save($output, $k . "_output", false, "json");

    /**
     * Log execution to the file `export/export_results.txt`
     * @uses Agrovoc::build_dataset_api_url()
     */
    $parsed_output = json_decode($output);
    file_put_contents(getcwd() . "/export/export_results.txt", str_repeat("-", 50) . "\n", FILE_APPEND | LOCK_EX);

    $txt .= "Date: " . date("Y-m-d H:i:s") . "\n";
    $txt .= "Analysed: " . $k . "\n";
    $txt .= "Result: " . $output . "\n";
    $txt .= "Published_dataset: " . Agrovoc::build_dataset_api_url(TARGET_SERVER, $parsed_output->data->persistentId, APITEST_KEY) . "\n";
    $txt .= str_repeat("-", 50) . "\n";
    file_put_contents(getcwd() . "/export/export_results.txt", $txt, FILE_APPEND | LOCK_EX);
}
?>

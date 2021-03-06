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

class Obj {
    /**
     * Sort and list all array items in human readable format
     * @example [1, 3, 2] ==> 1, 2 and 3
     *
     * @param  int|array                        $array                          The array to display
     * @return int|string                                                       An integer or a string describing the array elements or
     */
    public static function list($array) {
        if(is_array($array)) {
            sort($array);
            $res = preg_replace('/\,\s+(\w+)$/', " and $1", implode(", ", $array));
            return (is_numeric($res)) ? (int)$res : $res;
        }
    }

    /**
     * Convert an array to an object
     *
     * @param  array                            $array                          The array to convert
     * @return object                                                           The converted object
     */
    public static function array_to_object($array) {
        return json_decode(json_encode($array));
    }

    /**
     * Convert an object to an array
     *
     * @param  object                           $object                         The object to convert
     * @return object                                                           The converted array
     */
    public static function object_to_array($object) {
        return json_decode(json_encode($array), 1);
    }

    /**
     * Move an array item to the top of order
     *
     * @param  array                            $array                          The array to sort
     * @param  string                           $key                            The item to move to the top
     * @return array                                                            The sorted array
     */
    public static function move_to_top($array, $key) {
        return array_splice($array, array_search($key, array_keys($array)), 1) + $array;
    }

    /**
     * Move an array item to the bottom of order
     *
     * @param  array                            $array                          The array to sort
     * @param  string                           $key                            The item to move to the bottom
     * @return array                                                            The sorted array
     */
    public static function move_to_bottom($array, $key) {
        if(is_object($array)) {
            $array = object_to_array($array);
        }
        return $array + array_splice($array, array_search($key, array_keys($array)), 1);
    }

    /**
     * Display data on screen
     *
     * @param  string|object|array              $data                           What to display
     * @param  boolean                          $json                           Display as JSON?
     */
    function output($data, $json = false) {
        if($json) {
            // Display the output as json
            header("Content-type: application/json");
            // print_r(json_encode($changes, JSON_PRETTY_PRINT));
            if(is_array($data)) {
                print_r(json_encode($data, JSON_PRETTY_PRINT));
            } else {
                print_r($data);
            }
        } else {
            header("Content-type: text/plain");
            print_r($data);
        }
    }
}

?>

<?php
/**
 * Bioversity AGROVOC Indexing
 *
 * PHP Version 7.2.11
 *
 * @copyright 2018 Bioversity International (http://www.bioversityinternational.org/)
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
    public static function list($array) {
        if(is_array($array)) {
            sort($array);
        }
        $res = preg_replace('/\,\s+(\w+)$/', " and $1", implode(", ", $array));
        return (is_numeric($res)) ? (int)$res : $res;
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
}

?>

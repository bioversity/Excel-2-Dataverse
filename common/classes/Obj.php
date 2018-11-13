<?php










class Obj {
    /**
     * Convert an array to an object
     * @param  array                            $array                          The array to convert
     * @return object                                                           The converted object
     */
    public static function array_to_object($array) {
        return json_decode(json_encode($array));
    }

    /**
     * Convert an object to an array
     * @param  object                           $object                         The object to convert
     * @return object                                                           The converted array
     */
    public static function object_to_array($object) {
        return json_decode(json_encode($array), 1);
    }

    /**
     * Move an array item to the top of order
     * @param  array                            $array                          The array to sort
     * @param  string                           $key                            The item to move to the top
     * @return array                                                            The sorted array
     */
    public static function move_to_top($array, $key) {
        return array_splice($array, array_search($key, array_keys($array)), 1) + $array;
    }

    /**
     * Move an array item to the bottom of order
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

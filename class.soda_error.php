<?php

class soda_error {

    static $validation_errors = array();


    static function invalid() {
        return (count(self::$validation_errors));
    } // function invalid


    static function valid() {
        return !self::invalid();        
    } // function valid


    static function add_error($field, $message) {
        $model = get_called_class();
        return self::$validation_errors[$model][$field][] = $message;
    } // function add_error


    static function in_error($field) {
        $model = get_called_class();
        if (!isset(self::$validation_errors[$model])) return false;
        if (!isset(self::$validation_errors[$model][$field])) return false;
        return true;
    } // function in_error
    
    static function get_first_error($field) {
		$model = get_called_class();
		return (self::in_error($field)) ? array_shift(self::$validation_errors[$model][$field]) : false;
	}


    static function foreach_error($function) {
        foreach(self::$validation_errors as $model => $fields) {
            foreach($fields as $field => $messages) {
                foreach($messages as $message) {
                    $function($model, $field, $message);
                }
            }
        }
    } // function foreach_error

} // class soda_error 
?>

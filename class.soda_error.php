<?php

/////////////////////////////////////////////////////////////////////////////
//                                                                         //
// NOTICE OF COPYRIGHT                                                     //
//                                                                         //
// Moodle - Soda Module Constructor and MVC Framework                      //
//                                                                         //
// Copyright (C) 2011 Solin - www.solin.eu                                 //
//                                                                         //
//                                                                         //
// Programming and development:                                            //
//     Onno Schuit (o.schuit[atoraround]solin.nl)                          //
//                                                                         //
// For bugs, suggestions, etc. contact:                                    //
//     Onno Schuit (o.schuit[atoraround]solin.nl)                          //
//                                                                         //
// This program is free software; you can redistribute it and/or modify    //
// it under the terms of the GNU General Public License as published by    //
// the Free Software Foundation; either version 3 of the License, or       //
// (at your option) any later version.                                     //
//                                                                         //
// This program is distributed in the hope that it will be useful,         //
// but WITHOUT ANY WARRANTY; without even the implied warranty of          //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           //
// GNU General Public License for more details:                            //
//                                                                         //
//          http://www.gnu.org/copyleft/gpl.html                           //
//                                                                         //
/////////////////////////////////////////////////////////////////////////////

/**
 * Class soda_error stores error messages meant to be displayed for end users.
 * A typical use case is validation. If a model is not valid, the error messages
 * is added to soda_error::$validation errors. You can display the invalid form and
 * use the error messages to show the user which fields are "in error".
 * @package Soda
 */
class soda_error {

    static $validation_errors = array();


    /**
     * Returns true if soda_error::$validation_errors has at least one entry.
     *
     * @return  boolean Returns true if soda_error::$validation_errors has at least one entry, otherwise false.
     */
    static function invalid() {
        return (count(self::$validation_errors));
    } // function invalid


    /**
     * Returns true if soda_error::$validation_errors has no entries
     *
     * @return  boolean Returns true if soda_error::$validation_errors has no entries, otherwise false.
     */
    static function valid() {
        return !self::invalid();        
    } // function valid


    /**
     * Adds an error message to soda_error::$validation_errors for the given object and field.
     *
     * @param   object  $object     Soda model that contains an error
     * @param   string  $field      Field that is in error
     * @param   string  $message    Error message for user
     * @return  void
     */
    static function add_error($object, $field, $message) {
        $model_name = get_class($object);
        if ( (isset(self::$validation_errors[$model_name][spl_object_hash($object)][$field])) && ( in_array($message, self::$validation_errors[$model_name][spl_object_hash($object)][$field]) ) ) return;
        self::$validation_errors[$model_name][spl_object_hash($object)][$field][] = $message;
        //return print_object(self::$validation_errors);
    } // function add_error


    /**
     * Determines whether a given object is invalid for a given field.
     * If you omit the field, this function will return true if there is at least one error for the object.
     *
     * @param   object  $object     Soda model
     * @param   string  $field      Field
     * @return  boolean             Returns true if the object is in error, otherwise false
     */
    static function in_error($object, $field = false) {
        $model_name = get_class($object);
        if (!isset(self::$validation_errors[$model_name])) return false;
        if (!isset(self::$validation_errors[$model_name][spl_object_hash($object)])) return false;
        //print_object(self::$validation_errors[$model_name][spl_object_hash($object)]);
        if (!$field) return true;
        if (!isset(self::$validation_errors[$model_name][spl_object_hash($object)][$field])) return false;
        return true;
    } // function in_error


    static function class_in_error($model_name) {
        return isset(self::$validation_errors[$model_name]);
    } // function class_in_error

    
    /**
     * Returns the first error of the given object and field.
     * If there are no errors, the function returns false.
     *
     * @param   object  $object     Soda model
     * @param   string  $field      Name of the field
     * @return  boolean             Returns the first error if there is one, otherwise false
     */
    static function get_first_error($object, $field) {
        $model_name = get_class($object);
        if (!isset(self::$validation_errors[$model_name][spl_object_hash($object)][$field])) return false;
        $keys = array_keys(self::$validation_errors[$model_name][spl_object_hash($object)][$field]);
        return (self::in_error($object, $field)) ? self::$validation_errors[$model_name][spl_object_hash($object)][$field][$keys[0]] : false;
	} // function get_first_error


    /**
     * Calls an anonymous function for each error.
     * The anonymous function should have the following call signature:
     *
     *   $model_name, $instance, $field,  $message
     *
     * @param   function  $function The function to call for each error.     Soda model
     * @return  void
     */
    static function foreach_error($function) {
        foreach(self::$validation_errors as $model_name => $instances) {
            foreach($instances as $instance => $fields) {
                foreach($fields as $field => $messages) {
                    foreach($messages as $message) {
                        $function($model_name, $instance, $field, $message);
                    }
                }
            }
        }
    } // function foreach_error

} // class soda_error 
?>

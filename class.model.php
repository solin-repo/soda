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
// the Free Software Foundation; either version 2 of the License, or       //
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

// Help with Moodle 2.x migration:  http://docs.moodle.org/dev/DB_layer_2.0_migration_docs  
   
/**
 * A model object usually corresponds to exactly one database record. All columns 
 * are made available as object properties pointing to their corresponding database values (if any).
 * In addition, a number of methods are available which allow you to perform standard database actions.
 * For instance, you can invoke the 'save' method on a Soda model object.
 * A model class is also the place where you specify "business logic", including validation rules.
 * @package Soda
 */
class model {

    var $validation_rules = array();
    static $plural = false;
    static $has_many = false;
    static $finder_signatures = array(
        'find_all',
        'find',
        'load_all',
        'load',
        'delete_all'
    );


    /**
     * Instantiates model class with an array of property-value pairs. 
     * Calls the define_validation_rules method to add validation rules. 
     *
     * @param array  $properties  Properties and their values to populate the model with
     * @return object
     */
    function __construct($properties) {
        $this->attach_properties($properties);
        $this->define_validation_rules();
    } // function __construct


    /**
     * Loads the first object of the table specified in model::$table_name.
     * For example, if your model looks like this:
     * <code>
     * class user extends model {
     *   static $table_name = 'user';
     * }
     * </code>
     *
     * then calling $first_user = user::load() will simply return the first user in the database as a Soda model object.
     *
     *
     * This method uses an optional array of class names to load associated objects.
     *
     * Example: $author = author::load(false, $include = array('book'));
     *
     *
     * You can also specify nested relations:
     *
     * $publisher = publisher::load(false, $include = array('author' => 'book'));     
     *
     * This will load all authors and their books as an object hierarchy:
     *
     * <code>
     * $first_author = array_shift($publisher->authors);
     * $books_for_first_author = $first_author->books;
     * </code>
     *
     * Please note that there's also a 'plural' version of this function: model::load_all.
     *
     * @param string $where_clause      SQL where clause: use without the WHERE keyword (optional)
     * @param array  $include           class names of associated models to include (optional)
     * @param array  $params            Array of sql parameters (optional)
     * @return object                   Returns object or null if no object was found or false upon error
     */
    public static function load($where_clause = false, $include = false, $params = null) {
        if (!$objects = static::load_all($where_clause, $include, $params, $limitfrom = -1, $limitnum = 1)) return false;
        return array_shift($objects);
    } // function load


    /**
     * Loads specified associations and attaches them to parent objects.
     *
     * Example: seat::load_associations($seats, $bookings)
     *
     * This will endow each 'seat' object with the property bookings.
     * If a particular seat has no bookings, the property will be an empty array. In turn, each booking object
     * will be endowed with a property 'seat', containing a reference to the parent object.
     *
     * The associations parameter can also be a nested array: 
     *
     * array('author' => array('book' => 'chapter'))
     *
     * The association is recognized if your associated child objects contain a foreign key
     * which is named [model_name]_id. The model class for the child objects may also contain 
     * a plural version of its name:
     *
     * <code>
     * class mouse extends model {
     *     static $plural = 'mice';
     *     // ...
     * }
     * </code>
     *
     * If you don't specify your own plural version, Soda will simply use an 's' postfix to pluralize.
     *
     * Finally, if the $associations parameter contains a key which points to an object, the properties
     * of this object will be used to impose constraints on the retrieved associated objects. Technically,
     * the object's properties will be used to create an additional sql clause. Example:
     *
     * <code>
     * $authors = author::load_all_by_publisher( 'Penguin', $include = array('book' => (object) array('year' => 1972)) );
     * </code>
     *
     *
     * @param array  $objects       Parent objects to attach the associated models to
     * @param array  $associations  Class names of the associated models to be loaded
     * @return array
     */
    public static function load_associations($objects, $associations) {
        global $CFG, $soda_module_name;
        $model_name = get_called_class();
        foreach($associations as $parent_model => $child_model) {
            // check for is_object is just a dirty trick to use this function in multiple ways
            if (is_object($child_model)) {
                include_once("{$CFG->dirroot}/mod/{$soda_module_name}/models/{$parent_model}.php");
                $children = $parent_model::load_all("{$model_name}_id IN (" . join(',', static::collect('id', $objects)) .  ")
                    AND " . static::build_where_clause((array) $child_model)
                );
                $objects = static::attach_associations($objects, $children);
                continue;
            }

            if (is_string($parent_model)) {
                include_once("{$CFG->dirroot}/mod/{$soda_module_name}/models/{$parent_model}.php");
                $parents = $parent_model::load_all("{$model_name}_id IN (" . join(',', static::collect('id', $objects)) .  ")");
                if (!is_array($child_model)) $child_model = array($child_model);
                $parents = $parent_model::load_associations($parents, $child_model);
                static::attach_associations($objects, $parents);
                continue;
            }
            include_once("{$CFG->dirroot}/mod/{$soda_module_name}/models/{$child_model}.php");
            $children = $child_model::load_all("{$model_name}_id IN (" . join(',', static::collect('id', $objects)) .  ")");
            $objects = static::attach_associations($objects, $children);
        }
        return $objects;
    } // function load_associations



    public static function load_association_through($objects, $association, $through) {
        global $CFG, $soda_module_name;
        $model_name = get_called_class();
        include_once("{$CFG->dirroot}/mod/{$soda_module_name}/models/{$association}.php");
        include_once("{$CFG->dirroot}/mod/{$soda_module_name}/models/{$through}.php");

        $through_objects = $through::load_all("{$model_name}_id IN (" . join(',', static::collect('id', $objects)) .  ")" );
        $association_objects = $association::load_all("id IN (" . join(',', static::collect("{$association}_id", $through_objects)) .  ")" );

        $association_name = $association::plural();
        $model_id_name = $model_name . '_id';
        $association_id_name = $association . '_id';

        foreach($objects as $object) {
            foreach($through_objects as $through_object) {
                if ($object->id != $through_object->$model_id_name) continue;
                $selected[] = $association::find_by_id($through_object->$association_id_name, $association_objects);
            }
            $object->$association_name = $selected;
        }
    } // function load_association_through


    /**
     * Attaches associated child objects to parent objects.
     *
     * Example: seat::load_associations($seats, $bookings)
     *
     * This will endow each 'seat' object with the property bookings.
     * If a particular seat has no bookings, the property will be an empty array.
     * Each booking, in turn, will be endowed with a property 'seat', pointing to the parent object.
     *
     * The associations parameter can also be a nested array: 
     * 
     * <code>
     * array('author' => array('book' => 'chapter'))
     * </code>
     *
     * This 'include' array will result in an even deeper object hierarchy.
     *
     * If don't specify the optional association name, the pluralized name of the child model will be used as
     * the property name.
     * Alternatively, if you do specify a name, and the name matches the class name of the child model,
     * (i.e. is singular), then exactly one associated object will be attached (many-to-one relation will be assumed).
     *
     * Example: 
     * <code>
     * book::attach_associations($books, $authors, 'author');
     * </code>
     *
     * This will create a property 'author' for each book, containing the associated author object
     *
     * @param array  $objects           Parent objects to attach the associated models to
     * @param array  $children          Associated objects
     * @param string $association_name  Name of the parent's property for the associated objects (optional)
     * @return array
     */
    public static function attach_associations($objects, $children, $association_name = false) {
        if ( (!is_array($objects)) || (!count($objects)) ) return false;
        if ( (!is_array($children)) || (!count($children)) ) return false;
        if (is_array(static::get_first($objects)) ) $objects = static::flatten($objects);
        $model_name = get_called_class();
        $finder = "find_all_by_{$model_name}_id";
        $child_model = get_class(static::get_first($children));
        if (!$association_name) $association_name = $child_model::plural();
        if ($association_name == $child_model) $finder = "find_by_{$model_name}_id";
        foreach($objects as $object) {
            $object->$association_name = $child_model::$finder($object->id, $children);
        }               
        $parent_id = $model_name . '_id';
        foreach($children as $child) {
            $child->$model_name = $model_name::find_by_id($child->$parent_id, $objects);
        }
        return $objects;
    } // function attach_associations 




    public static function get_first($collection) {
        $keys = array_keys($collection);
        return $collection[$keys[0]];
    } // function get_first


    /**
     * Loads a collection of parent objects by association with a collection of child objects.
     * For each child object, this function will attempt to look for the associated
     * parent object. The child objects are inserted into an appropriately named property
     * of the parent object.
     * Each child object will also be endowed with a property named after the class of the parent object.
     * This property points to the parent object.
     *
     * Example: 
     * <code>
     * $authors = author::associative_load($books);
     * </code>
     *
     * This will lookup all authors with the id $book->author_id and load them 
     * as a collection of author objects.
     *
     * Each author object will be endowed with the collection of associated book objects:
     * $author->books
     *
     * And each book object will be endowed with an author property, pointing to the author object:
     * $book->author
     *
     * @param array $associated_collection  collection which contains objects with parent_ids 
     * @param array $association_name       name for the property where the child objects will reside (optional)
     * @return array
     */
    public static function associative_load($associated_collection, $association_name = false) {
        if ( (!is_array($associated_collection)) || (!count($associated_collection)) ) return false;
        $model_name = get_called_class();
        $child_model = get_class(static::get_first($associated_collection));
        $objects = $model_name::load_all( "id IN (" . join(',', array_unique($child_model::collect($model_name . '_id', $associated_collection)) ) .")" );
        return $model_name::attach_associations($objects, $associated_collection, $association_name);
    } // function associative_load


    /**
     * Loads all objects of the table specified in model::$table_name
     * For example, if your model looks like this:
     * <code>
     * class user extends model {
     *   static $table_name = 'user';
     * }
     * </code>
     *
     * then calling $users = user::load_all() will return all users in the database as an array of Soda model objects.
     *
     *
     * This method uses an optional array of class names to load associated objects.
     * 
     * Example: $authors = author::load_all(false, $include = array('book'));
     * 
     * You can also specify nested relations:
     * 
     * $publishers = publishers::load_all(false, $include = array('author' => 'book'));
     * 
     * This will load all authors and their books as an object hierarchy residing in each publisher object.
     *
     * Please note that there's also a 'singular' version of this function: model::load.
     * 
     *
     * @param string $where_clause      SQL where clause: use without the WHERE keyword (optional)
     * @param array  $include           Class names of associated models to include (optional)
     * @param array  $params            Array of sql parameters (optional)
     * @param int    $limitfrom         Return a subset of records, starting at this point (optional, required if $limitnum is set).
     * @param int    $limitnum          Return a subset comprising this many records (optional, required if $limitfrom is set).
     * @return array
     */
    public static function load_all($where_clause = false, $include = false, $params = null, $limitfrom = null, $limitnum = null) {
        global $CFG, $DB;
        $where = ($where_clause) ? "WHERE $where_clause " : "";
        if (! $recordset = $DB->get_recordset_sql("SELECT *
                                                   FROM {$CFG->prefix}" . static::$table_name . " 
                                                   $where",
                                                   $params,
                                                   $limitfrom, $limitnum) ) return;
        $objects = static::convert_to_objects($recordset);
        $recordset->close();
        if ($include) $objects = static::load_associations($objects, $include);
        return $objects;       
    } // function load_all


    public static function convert_to_objects($recordset, $class_name = false) {
        if (! $class_name) $class_name = get_called_class();
        $objects = array();
        foreach ($recordset as $record) {
            $objects[] = new $class_name($record);
        }
        return $objects;
    } // function convert_to_objects


    /**
     * Calls save method on each object in array $objects.
     * The default implementation for model::save is to perform a validation first. 
     * If this fails, then no actual save takes place for that object. All other valid objects
     * are still saved.
     *
     * @param array     $objects      Objects to save
     * @param array     $constants    Constants to attach to each object as a property before saving (optional)
     * @return boolean                Returns true if all objects were valid, otherwise false 
     */
    public static function save_all($objects, $constants = false) {
        if ( !isset($objects) || !is_array($objects) || !count($objects) ) return true;
        $valid = true;
        foreach($objects as $object) {
            $object->attach_properties($constants);
            $valid = $valid && $object->save();
        }
        return $valid;
    } // function save_all


    function attach_properties($properties = false) {
        if ($properties && (is_array($properties) || (is_object($properties))) ) {
            foreach($properties as $key => $value) {
                $this->$key = $value;
            }
        }               
    } // function attach_properties


    /**
     * Creates objects from a collection of properties.
     * This function is typically used to convert form post data into a collection of objects.
     *
     * @param array     $properties_collection      Array of property arrays (key value pairs)
     * @param array     $constants                  Constants to attach to each object as a property before saving (optional)
     * @return array                                Returns objects
     */
    public static function instantiate_all($properties_collection, $constants = false) {
        $class = get_called_class();
        $objects = array();
        foreach($properties_collection as $properties) {
            $object = new $class($properties);
            $object->attach_properties($constants);
            $objects[] = $object;
        }
        return $objects;
    } // function 


    /**
     * Returns an item from a collection which matches whatever criterion is specified in the anonymous function $compare.
     * Please note that there is a 'plural' version as well: model::find_all
     *
     * @param  function     $compare      Function which returns the item that matches the criterion specified in the function body
     * @param  array        $collection   Array of items to search through
     * @return mixed                      Returns item of mixed type or false
     */
    public static function find($compare, $collection = false) {
        if (!is_array($collection)) return false;
        foreach($collection as $item)  {
            if ($compare($item)) return $item;
        }
        return false;
    } // function find


    /**
     * Delegates calls to non-existing method to a finder or loader.
     * If the method is not found in static::$finder_signatures, an exception is thrown.
     *
     * @param  string       $method Name of the method to call dynamically
     * @param  array        $args   Array of arguments with the method
     * @return mixed                Returns mixed type
     */
    public static function __callStatic($method, $args) {
        foreach(static::$finder_signatures as $signature) {
            if (strpos($method, $signature) !== false) {
                $caller = "call_{$signature}";
                return static::$caller($method, $args);
            }
        }
        throw new Exception("Unknown method [$method]");
    } // function __callStatic


    /**
     * Calls delete_all with a sql WHERE clause which is constructed from the name of the calling function $method
     *
     * @param  string       $method Name of the method to call dynamically
     * @param  array        $args   Array of values for the columns mentioned in the where clause
     * @return mixed                Returns true upon succes, or false if an error occured.
     */
    public static function call_delete_all($method, $args) {
        $property_names = static::extract_properties( substr($method, strlen('delete_all_by_')) );
        $params = static::create_params_array($property_names, $args);
        return static::delete_all(static::build_params_clause($property_names), $params);               
    } // function call_delete


    /**
     * Deletes all objects of this class from the database.
     * Please note: there is a 'singular' version for this method model#delete
     *
     * @param  string       $where_clause SQL where clause (optional)
     * @param  array        $params       Array of sql parameters (optional)
     * @return mixed                      Returns true upon success or false if an error occured.
     */
    public static function delete_all($where_clause = false, $params = null) {
        global $CFG;
        $where = ($where_clause) ? "WHERE $where_clause " : "";
        if (! $recordset = $DB->get_recordset_sql("DELETE
                                                   FROM {$CFG->prefix}" . static::$table_name . " 
                                                   $where",
                                                   $params)) return false;
        $recordset->close();
        return true;
    } // function delete_all


    /**
     * Turns a string of properties into an array
     * This method is typically used to retrieve the column names used in the method call to dynamically created
     * loaders and finders. Example: company_id_and_year is turned into array('company_id', 'year').
     *
     * @param  string       $properties   String of SQL column names separated by '_and_'
     * @return array                      Returns an array of column names
     */
    public static function extract_properties($properties) {
        if (strpos($properties, '_and_') === false) return array($properties);
        return explode('_and_', $properties); 
    } // function extract_properties


    /**
     * Builds an associative array by using an array of property names as the keys and an array of arguments
     * as the values.
     * This method is typically used to deconstruct a dynamic finder or loader call into a SQL where
     * clause which is used in default load or find call.
     *
     * @param  array       $property_names   Array of column names
     * @param  array       $args             Array of values for the columns
     * @return array                         Returns an associative array of column names pointing to argument values
     */
    public static function map_properties_to_values($property_names, $args) {
        $properties = array();
        for($i = 0; $i < count($property_names); $i++) {
            $properties[$property_names[$i]] = $args[$i];
        }               
        return $properties;
    } // function map_properties_to_values 


    /**
     * Wrapper for finder method: Calls finder with 'find' as finder_name and 'load' as loader_name.
     *
     * @param  string       $method Name of the method to call dynamically
     * @param  array        $args   Array of arguments: the last argument is an optional array of objects to search through, 
     *                              the other elements are values which the finder should compare as search criteria
     * @return object               Returns object, or false if an error occured.
     */
    public static function call_find($method, $args) {
        return static::finder($method, $args, 'find', 'load');
    } // function call_find


    /**
     * Wrapper for finder method: Calls finder with 'find_all' as finder_name and 'load_all' as loader_name.
     *
     * @param  string       $method Name of the method to call dynamically
     * @param  array        $args   Array of arguments: the last argument is an optional array of objects to search through, 
     *                              the other elements are values which the finder should compare as search criteria
     * @return array                Returns an array objects, or false if an error occured.
     */
    public static function call_find_all($method, $args) {
        return static::finder($method, $args, 'find_all', 'load_all');
    } // function call_find_all


    /**
     * Deconstructs a dynamic method call into a call to a finder
     * The arguments array may optionally contain, as the last element, an array of objects to search through.
     * If you do not provide this array searchable objects, the find and find_all will try to retrieve the
     * searchable objects from the database by calling the method in $loader_name
     *
     * @param  string       $method         Name of the method called dynamically
     * @param  array        $args           Array of arguments: the last argument is an optional array of objects to search through, 
     *                                      the other elements are values which the finder should compare as search criteria
     * @param  string       $finder_name    Finder method to call for finding one or more objects
     * @param  string       $loader_name    Loader method to use if no array of searchable objects was provided
     * @return mixed                        Returns an array objects, an object, or false if an error occured.
     */
    public static function finder($method, $args, $finder_name, $loader_name) {
        $property_names = static::extract_properties( substr($method, strlen($loader_name . '_by_')) );
        if (!isset($args[count($property_names)])) {
            $params = static::create_params_array($property_names, $args);
            return static::$loader_name(static::build_params_clause($property_names), false, $params);
        }
        $properties = static::map_properties_to_values($property_names, $args);
        return static::$finder_name(function($item) use($properties) { 
            foreach($properties as $property_name => $value) {
                if ( (!isset($item->$property_name)) || $item->$property_name != $value) return false;
            }
            return true;
        }, $args[count($property_names)]);
    } // function finder


    /**
     * Constructs an SQL WHERE clause out of an associative array of column names pointing to corresponding values
     *
     * @param  array        $properties         Associative array of column names pointing to values.
     * @return string                       Returns a string containing a WHERE clause
     */
    public static function build_where_clause($properties) {
        $where_parts = array();
        foreach($properties as $column => $value) {
            $where_parts[] = "$column = '$value'";
        }
        return join(' AND ', $where_parts);
    } // function build_where_clause


    /**
     * Constructs a partial SQL WHERE clause out of an array of column names
     *
     * @param  array        $params         Array of column names
     * @return string                       Returns a string containing a partial WHERE clause
     */
    public static function build_params_clause($params) {
        $where_parts = array();
        foreach($params as $column) {
            $where_parts[] = "$column = :$column";
        }
        return join(' AND ', $where_parts);
    } // function build_params_clause


    /**
     * Wrapper for loader: calls method loader with 'load' as the $loader_name 
     *
     * @param  string       $method Name of the method to call dynamically
     * @param  array        $args   Array of values for the columns
     * @return mixed                Returns an object, an array of objects or false
     */
    public static function call_load($method, $args) {
        return static::loader($method, $args, 'load');
    } // function call_load_all


    /**
     * Wrapper for loader: calls method loader with 'load_all' as the $loader_name 
     *
     * @param  string       $method Name of the method to call dynamically
     * @param  array        $args   Array of values for the columns used in the dynamically constructed WHERE clause
     * @return mixed                Returns an object, an array of objects or false
     */
    public static function call_load_all($method, $args) {
        return static::loader($method, $args, 'load_all');
    } // function call_load_all


    /**
     * Deconstructs a dynamic method call into a call to a loader
     *
     * @param  string       $method         Name of the method called dynamically
     * @param  array        $args           Array of values for the columns used in the dynamically constructed WHERE clause
     * @param  string       $loader_name    Loader method to use
     * @return mixed                        Returns an array objects, an object, or false if an error occured.
     */
    public static function loader($method, $args, $loader_name) {
        $property_names = static::extract_properties( substr($method, strlen($loader_name . '_by_')) );
        $include = (isset($args[count($property_names)])) ? $args[count($property_names)] : false;
        $params = static::create_params_array($property_names, $args);
        return static::$loader_name(static::build_params_clause($property_names), $include, $params);               
    } // function loader


    public static function create_params_array($property_names, $args) {
        return static::map_properties_to_values($property_names, array_slice($args, 0, count($property_names)) );
    } // function create_params_array


    /**
     * Returns an array of items which match whatever criterion is specified in the anonymous function $compare.
     * Please note this is just a wrapper for array_filter - mainly here for consistency with similarly named methods 
     * 'find_all_by_' and model::find.
     *
     * @param  function     $compare      Function which returns the item that matches the criterion specified in the function body
     * @param  array        $collection   Array of items to search through
     * @return mixed                      Returns item of mixed type or false
     */
    public static function find_all($compare, $collection) {
        return array_filter($collection, $compare);
    } // function find_all


    /**
     * Returns all values for the property $property from $collection
     *
     * @param  string     $property     Name of the property we are looking for
     * @param  array      $collection   Array of items to search through
     * @return array                    Returns an array of property values or an empty array
     */
    public static function collect($property, $collection) {
        return array_map(function($item) use ($property) { 
            if (isset($item->$property)) return $item->$property; 
        }, $collection);
    } // function collect


    /**
     * Reduces a nested array to a flat array.
     * This method is typically used to merge multiple collections of objects into one array.
     *
     * @param  array      $array   Array of arrays (typically) and objects
     * @return array               Returns an array of objects or an empty array
     */
    public static function flatten($array) {
        return array_reduce($array, function($result, $input) {
            return array_merge($result, $input);
        }, array());
    } // function flatten


    /**
     * Returns the plural version of the model name
     *
     * @return string   Returns the plural version of the model name
     */
    public static function plural() {
        return (static::$plural) ? static::$plural : get_called_class() . 's';
    } // function plural


    /**
     * This method is called from the model's constructor.
     * Overwrite this method if you want to specify validation rules for your model.
     *
     * @return void
     */
    function define_validation_rules() {
    } // function define_validation_rules


    /**
     * Adds a rule to the validation rules for this model.
     * This method is typically called from within model#define_validation_rules.
     *
     * Example:
     * 
     * $this->add_rule('label', 'Please fill in a label.', function($label) { return ( trim($label) != '' ); });
     *
     * If this rule fails, an error will be added to soda_error::$validation_errors, for the given class of the model,
     * the specific instance of the model (i.e. this object) and the field.
     *
     * @param  string   Name of the property on which this rule operates
     * @param  string   Message to display when the validation fails
     * @param  function Validation code to be executed whenever the model is validated
     * @return void
     */
    function add_rule($field, $message, $rule_code) {
        $this->validation_rules[] = array('field' => $field, 'message' => $message, 'code' => $rule_code);
    } // function add_rule


    /**
     * Returns the public properties and their corresponding values for this object.
     *
     * @return array Returns an associative array of all properties of this model pointing to the corresponding values.
     */
    function get_properties() {
        $ref = new ReflectionObject($this);
        $properties = $ref->getProperties(ReflectionProperty::IS_PUBLIC);
        $result = array();
        foreach ($properties as $property) {
            false && $property = new ReflectionProperty();
            $result[$property->getName()] = $property->getValue($this);
        }               
        return $result;
    } // function get_properties


    /**
     * Validates the object.
     * Calls all validation rules for this model. 
     *
     * @return boolean Returns false if any of the validation rules failed, otherwise true
     */
    function validate() {    
        // Turn properities into local variables. This is currently the only way to bring them into the scope 
        // of the validation code, because php 5.3 does not support closures over '$this'.
        foreach($this->get_properties() as $property => $value) {
            ${$property} = $value;
        }
        $valid = true;
        if (!isset($this->validation_rules) || !count($this->validation_rules) ) return true;
        foreach($this->validation_rules as $rule) {
            $code = $rule['code'];
            if ( !$code(${$rule['field']}) ) {
                soda_error::add_error($this, $rule['field'], $rule['message']);
                $valid = false;
            }
        }
        return $valid;
    } // function validate


    /**
     * Sets the deleted column to 1 and saves the object to the database.
     * Please note: there is a 'plural' version for this method model::delete_all, but this method actually deletes
     * the objects from the database.
     *
     * @return boolean                      Returns true upon success or false
     */
    function delete() {
        $this->deleted = 1;
        if (! $this->id ) error("No record id found");
        return $this->save_without_validation($record_id);
    } // function delete


    /**
     * Validates and saves the object.
     * If the validation failes, the object is not saved. Instead, false is returned.
     *
     * @param  integer  Id of the object to save (optional)
     * @return boolean  Returns true upon success, otherwise false
     */
    function save($record_id = false) {
        if (!$this->validate()) return false;
        return $this->save_without_validation($record_id);
    } // function save


    /**
     * Saves the object.
     * If you provide a record id, or a valid id property is present, the object will be updated in the database. 
     * Otherwise an insert will take place.
     *
     * @param  integer  Id of the object to save (optional)
     * @return integer  Returns the record id of the object upon success, otherwise false
     */
    function save_without_validation($record_id = false) {
        global $DB;
        $class = get_class($this);
        $this->timemodified = time();
        if ($record_id || (property_exists($this, 'id') && $this->id && $this->id != '') ) {
            if ($record_id) $this->id = $record_id;
            if (!$DB->update_record($class::$table_name, $this)) return false;
            return $this->id;
        }
        $this->timecreated = time();
        return $this->id = $DB->insert_record($class::$table_name, $this);               
    } // function save_without_validation


    /**
     * Adds an error to soda_error::$validation_errors, for the given class of the model,
     * the specific instance of the model (i.e. this object) and the property $field.
     *
     * @param  string   Name of the property which is in error
     * @param  string   Error message meant for the user
     * @return void
     */
    function add_error($field, $message) {
        return soda_error::add_error($this, $field, $message);
    } // function add_error


    /**
     * Finds out if this object is "in error" (e.g. is not valid)
     * If you provide a property name $field, only the status of this property is checked, 
     * otherwise the entire object.
     * Please note that an error may also have been added for other reasons than an invalid state.
     *
     * @param  string   Name of the property to check (optional)
     * @return boolean  Returns true if in error, otherwise false.
     */
    function in_error($field = false) {
        return soda_error::in_error($this, $field);
    } // function in_error


    /**
     * Returns the first error message for this object.
     * If you provide a property name $field, only the error stack for this property is checked,
     * otherwise the stack for the entire object.
     *
     * @param  string  Name of the property to check (optional)
     * @return string  Returns the error message or false
     */
    function get_first_error($field = false) {
        return soda_error::get_first_error($this, $field);
    } // function get_first_error

} // class model 

?>

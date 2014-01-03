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
    // directives for the loader method
    static $directives = array(
        'scope',
        'through'
    );
    static $table_relations = false;
    static $connection_object = false;
    static $plugin_type = 'mod';


    /**
     * Instantiates model class with an array of property-value pairs. 
     * Calls the define_validation_rules method to add validation rules. 
     *
     * @param array  $properties  Properties and their values to populate the model with (optional)
     * @return object
     */
    function __construct($properties = false) {
        $this->attach_properties($properties);
    } // function __construct


    /**
     * Returns table name associated with the model.
     *
     * @return string   Returns the table name
     */
    public static function table_name() {
        if ( isset(static::$table_name) ) return static::$table_name;

        //provision for class names with a namespace in front of it
        $array = explode('\\', get_called_class());
        return end($array) . 's';
    } // function table_name


    /**
     * Loads the first object of the table as specified in model::table_name().
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
        if (static::no_more_associations_to_load($associations)) return $objects;
        $model_name = get_called_class();
        foreach($associations as $parent_model => $child_model) {
            if ( (is_string($parent_model)) && (strstr($parent_model, ':')) ) continue;
            if ($directive = static::get_directive($child_model)) {
                $method = "call_{$directive}";
                static::$method( $objects, $parent_model, $child_model );
                continue;
            }
            if (is_string($parent_model)) {
                include_once("{$CFG->dirroot}/".static::get_plugin_type()."/{$soda_module_name}/models/{$parent_model}.php");
                // This should actually be done through the model class settings of the association class,
                // not as a parameter in the 'load' function call
                $foreign_key = static::construct_foreign_key($parent_model);
                $parents = $parent_model::load_all("{$foreign_key} IN (" . join(',', static::collect('id', $objects)) .  ")");
                if (!is_array($child_model)) $child_model = array($child_model);
                $parents = $parent_model::load_associations($parents, $child_model);
                static::attach_associations($objects, $parents, false, $foreign_key);
                continue;
            }
            include_once("{$CFG->dirroot}/".static::get_plugin_type()."/{$soda_module_name}/models/{$child_model}.php");
            $foreign_key = static::construct_foreign_key($child_model);
            $children = $child_model::load_all("{$foreign_key} IN (" . join(',', static::collect('id', $objects)) .  ")");
            static::attach_associations($objects, $children, false, $foreign_key);
        }
        return $objects;
    } // function load_associations


    /**
     * See if the associations array contains anything besides 'directives'
     *
     * @param array    $associations     Array of 'directives' or association names
     * @return boolean                   Returns false if any of the keys is not a 'directive', otherwise true
     */
    public static function no_more_associations_to_load($associations) {
        foreach($associations as $key => $value) {
            if ( !strstr($key, ':') )  return false;
        }
        return true;
    } // function no_more_associations_to_load


    /**
     * Creates and executes an sql 'where clause' to restrict the set of associated items.
     *
     * @param array  $objects           Array of 'parent' objects
     * @param string $association_model Name of the associated model
     * @param array  $child_model       Array of 'directives', including the actual scope
     * @return array                    Returns array of loaded and attached associated objects
     */
    public static function call_scope($objects, $association_model, $child_model) {
        global $CFG, $soda_module_name;

        //$restrictions = static::get_first($child_model[':scope']);
        $restrictions = $child_model[':scope'];
        include_once("{$CFG->dirroot}/".static::get_plugin_type()."/{$soda_module_name}/models/{$association_model}.php");
        $order = (isset($child_model[':order'])) ? " ORDER BY {$child_model[':order']} " : "";
        $association_name = (isset($child_model[':association_name'])) ? $child_model[':association_name'] : false;
        $foreign_key = static::construct_foreign_key($association_model);
        $children = $association_model::load_all("{$foreign_key} IN (" . join(',', static::collect('id', $objects)) .  ")
            AND " . static::build_where_clause($restrictions) . $order
        );
        return $objects = static::attach_associations($objects, $children, $association_name, $foreign_key);
    } // function call_scope


    /**
     * Looks up the static property table_relations to see if there is a relationship
     * with $association_model which includes a foreign key specification.
     *
     * @param string $association_model Name of the associated model
     * @return string                   Returns the foreign key or false
     */
    public static function find_foreign_key_for($association_model) {
        if (! $relations = static::$table_relations) return false;
        $keys = array_keys($relations);
        if (! is_array($relations[$keys[0]]) ) $relations = array($relations);
        foreach($relations as $relation) {
            if (!array_key_exists(':foreign_key', $relation)) continue;
            if (array_key_exists($association_model, array_flip($relation))) return $relation[':foreign_key'];
        }               
        return false;
    } // function find_foreign_key_for


    /**
     * Looks up the foreign key of the currenct model used in $association_model or 
     * returns the name of the current model affixed with '_id'.
     *
     * @param string $association_model Name of the associated model
     * @return string                   Returns the foreign key
     */
    public static function construct_foreign_key($association_model) {
        //exit(print_object($association_model));
        $model_name = get_called_class();
        if ($foreign_key = static::find_foreign_key_for($association_model)) return $foreign_key;
        // search both ends of the relationship
        if ($foreign_key = $association_model::find_foreign_key_for($model_name)) return $foreign_key;
        return "{$model_name}_id";
    } // function construct_foreign_key


    /**
     * Loads objects of $child_model if they appear in $association_model together with
     * an object out of $objects.
     * Please note: this is primarily a wrapper function for load_association_through.
     * TODO: does not seem to be used anywhere. Delete?
     *
     * @param array  $objects           Array of objects for which to find the associated models
     * @param string $association_model Name of the association model
     * @param array  $child_model       Array containing 'directives' and the name of the child model
     * @return void
     */
    public static function call_through($objects, $association_model, $child_model) {
        global $CFG, $soda_module_name;
        $through_model = static::get_first($child_model);
        $model_name = get_called_class();
        $order = (isset($child_model[':order'])) ? $child_model[':order'] : false;
        $model_name::load_association_through( $objects,  $association_model, $through_model, $order);
    } // function call_through


    /**
     * Extracts 'directive' out of $child_model array.
     *
     * @param array   $child_model  Array containing 'directives' or the name of the child model
     * @return string               Returns directive or false if none was found
     */   
    public static function get_directive($child_model) {
        if (!is_array($child_model)) return false;        
        foreach($child_model as $key => $value) {
            if (!strstr($key, ':')) continue;
            if (in_array(substr($key, 1), static::$directives) ) return substr($key, 1);
        }
        return false;
    } // function get_directive


    /**
     * Loads associated models if they appear in an association table together with
     * an object out of $objects.
     *
     * @param array  $objects     Array of objects for which to find the associated models
     * @param string $association Name of the association model
     * @param string $through     Name of the combining model (i.e. the association table)
     * @param string $order       Property by which to sort the associated object
     * @return void
     */
    public static function load_association_through($objects, $association, $through, $order = false) {
        global $CFG, $soda_module_name;
        $model_name = get_called_class();
        include_once("{$CFG->dirroot}/".static::get_plugin_type()."/{$soda_module_name}/models/{$association}.php");
        include_once("{$CFG->dirroot}/".static::get_plugin_type()."/{$soda_module_name}/models/{$through}.php");

        $association_objects = array();
        if ($through_objects = $through::load_all("{$model_name}_id IN (" . join(',', static::collect('id', $objects)) .  ")" )) {
            $association_objects = $association::load_all("id IN (" . join(',', static::collect("{$association}_id", $through_objects)) .  ")" );
        }
        //exit(print_object($through_objects));

        $association_name = $association::plural();
        $model_id_name = $model_name . '_id';
        $association_id_name = $association . '_id';

        foreach($objects as $object) {
            $selected = array();
            foreach($through_objects as $through_object) {
                if ($object->id != $through_object->$model_id_name) continue;
                $selected[] = $association::find_by_id($through_object->$association_id_name, $association_objects);
            }
            $object->$association_name = $selected;
            static::sort_by($object->$association_name, $order);
        }
    } // function load_association_through


    /**
     * Sorts an array of objects by a property or method.
     *
     * @param array  $objects            Array of objects to sort
     * @param string $property_or_method Name of the property or method to sort by
     * @param string $order              Sorting order (defaults to 'ascending', e.g. a, .. , z or 0, .. n)
     * @return array                     Returns sorted array
     */
    public static function sort_by(&$objects, $property_or_method, $order = "ASC") {
        if (! $property_or_method) return;
        $keys = array_keys($objects);
        if (! count($keys) ) return $objects;
        $obj = $objects[$keys[0]];

        if (property_exists($obj, $property_or_method)) return static::sort_by_property($objects, $property_or_method, $order);
        if (method_exists($obj, $property_or_method)) return static::sort_by_method($objects, $property_or_method, $order);
    } // function sort_by


    /**
     * Sorts an array of objects by a property
     *
     * @param array  $objects            Array of objects to sort
     * @param string $property           Name of the property to sort by
     * @param string $order              Sorting order (defaults to 'ascending', e.g. a, .. , z or 0, .. n)
     * @return array                     Returns sorted array
     */
    public static function sort_by_property(&$objects, $property, $order) {
        usort($objects, function($a, $b) use ($property, $order) {
            if ($a->$property == $b->$property ) return 0;
            if ($order == 'ASC') {
                return ($a->$property < $b->$property) ? -1 : 1;
            } else {
                return ($a->$property > $b->$property) ? -1 : 1;
            }
        });
        return $objects;        
    } // function sort_by_property


    /**
     * Sorts an array of objects by a method, i.e. by comparing the output of the called method.
     *
     * @param array  $objects            Array of objects to sort
     * @param string $method             Name of the method to sort by
     * @param string $order              Sorting order (defaults to 'ascending', e.g. a, .. , z or 0, .. n)
     * @return array                     Returns sorted array
     */
    public static function sort_by_method(&$objects, $method, $order) {
        usort($objects, function($a, $b) use ($method, $order) {
            if ($a->$method() == $b->$method() ) return 0;
            if ($order == 'ASC') {
                return ($a->$method() < $b->$method()) ? -1 : 1;
            } else {
                return ($a->$method() > $b->$method()) ? -1 : 1;
            }
        });               
        return $objects;
    } // function sort_by_method



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
    public static function attach_associations($objects, $children, $association_name = false, $foreign_key = false) {
        if ( (!is_array($objects)) || (!count($objects)) ) return false;
        if ( (!is_array($children)) || (!count($children)) ) return false;
        if (is_array(static::get_first($objects)) ) $objects = static::flatten($objects);
        $model_name = get_called_class();
        $foreign_key = ($foreign_key) ? $foreign_key : $model_name . '_id';
        $finder = "find_all_by_{$foreign_key}";
        $child_model = get_class(static::get_first($children));
        if (!$association_name) $association_name = $child_model::plural();
        if ($association_name == $child_model) $finder = "find_by_{$foreign_key}";
        foreach($objects as $object) {
            $object->$association_name = $child_model::$finder($object->id, $children);
        }               
        foreach($children as $child) {
            $child->$model_name = $model_name::find_by_id($child->$foreign_key, $objects);
        }
        return $objects;
    } // function attach_associations 



    /**
     * Returns first element of an array
     *
     * @param array  $collection         Array of items
     * @return mixed                     Returns first item of array
     */
    public static function get_first($collection) {
        $keys = array_keys($collection);
        return $collection[$keys[0]];
    } // function get_first


    /**
     * Loads all objects of the table as specified in model::table_name()
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
     * @param string $fields            An optional SQL fragment specifying the columns. Defaults to *.
     * @return array
     */
    public static function load_all($where_clause = false, $include = false, $params = null, $limitfrom = null, $limitnum = null, $fields = '*') {
        $connection = static::get_connection_object();
        $prefix = $connection->get_prefix();
        $where = ($where_clause) ? "WHERE $where_clause " : "";
        $sql = "SELECT $fields FROM {$prefix}" . static::table_name() . " $where";
        $objects = static::base_load($sql, $params, $limitfrom, $limitnum);
        if ($include) $objects = static::load_associations($objects, $include);
        return $objects;
    } // function load_all


    /**
     * Executes an sql query and returns the results by applying a supplied anonymous function.
     *
     * Example usage: see base_load
     *
     * @param string    $sql                    Complete SQL query - also takes Moodle DB API type parameters
     * @param function  $recordset_processor    Anonymous function which should contain an iterator, process the recordset and return the result
     * @param array     $params                 Array of sql parameters (optional)
     * @param int       $limitfrom              Return a subset of records, starting at this point (optional, required if $limitnum is set).
     * @param int       $limitnum               Return a subset comprising this many records (optional, required if $limitfrom is set).
     * @return array                            Returns an array of results, as determined by the $recordset_processor
     */
    public static function raw_load($sql, $recordset_processor, $params = null, $limitfrom = null, $limitnum = null) {
        $connection = static::get_connection_object();
        if (! $recordset = $connection->get_recordset_sql($sql, $params, $limitfrom, $limitnum) ) return;
        $results = $recordset_processor($recordset);
        $recordset->close();
        return $results;       
    } // function raw_load


    /**
     * Executes an sql query and returns the results as an array of objects.
     *
     * Example usage: see load_all
     *
     * @param string    $sql                    Complete SQL query - also takes Moodle DB API type parameters
     * @param array     $params                 Array of sql parameters (optional)
     * @param int       $limitfrom              Return a subset of records, starting at this point (optional, required if $limitnum is set).
     * @param int       $limitnum               Return a subset comprising this many records (optional, required if $limitfrom is set).
     * @return array                            Returns an array of objects
     */
    public static function base_load($sql, $params = null, $limitfrom = null, $limitnum = null) {
        $class = get_called_class();
        return static::raw_load($sql, function($recordset) use ($class) {return $class::convert_to_objects($recordset);}, $params, $limitfrom, $limitnum);
    } // function base_load


    /**
     * Returns a connection object, usually $DB (global var)
     *
     * @return object   Returns instance of class moodle_database
     */
    public static function get_connection_object() {
        global $DB;
        if (static::$connection_object) return static::$connection_object;
        return $DB;
    } // function get_connection_object


    /**
     * Converts a record set to an array of objects of the called class.
     *
     * @param recordset $recordset    Record set
     * @param string    $class_name   Name of the class to convert to (optional, defaults to called class)
     * @return array                  Returns array of objects
     */
    public static function convert_to_objects($recordset, $class_name = false) {
        if (! $class_name) $class_name = get_called_class();
        $objects = array();
        foreach ($recordset as $record) {
            $objects[] = new $class_name($record);
        }
        return $objects;
    } // function convert_to_objects


    /**
     * Calls save method on each object in array $objects (insert operation if no id is present, update otherwise)
     * The default implementation for model::save is to perform a validation first. 
     * If this fails, then no actual save takes place for that object. All other valid objects
     * are still saved.
     *
     * @param array     $objects            Objects to save
     * @param array     $constants          Constants to attach to each object as a property before saving (optional)
     * @param boolean   $skip_validation    Does not validate if set to true, which will cause this function to always return true (optional, defaults to false)  
     * @return boolean                      Returns true if all objects were valid, otherwise false 
     */
    public static function save_all($objects, $constants = false, $skip_validation = false) {
        if ( !isset($objects) || !is_array($objects) || !count($objects) ) return true;
        $valid = true;
        foreach($objects as $object) {
            $object->attach_properties($constants);
            if ($skip_validation) {
                $object->save_without_validation();
                continue;
            }
            $valid = $valid && $object->save();
        }
        return $valid;
    } // function save_all


    /**
     * Wrapper for save_all. Calls save all with parameter $skip_validation set to true (won't validate).
     * Please note that this function will always return true.
     *
     * @param array     $objects            Objects to save
     * @param array     $constants          Constants to attach to each object as a property before saving (optional)
     * @return boolean                      Returns true
     */
    public static function save_all_without_validation($objects, $constants = false) {
        return static::save_all($objects, $constants, $skip_validation = true);
    } // function save_all_without_validation


    /**
     * Adds key - value pairs to objects as properties and their values.
     *
     * @param recordset $properties   Array of properties and corresponding values
     * @return void
     */
    function attach_properties($properties = false) {
        if ($properties && (is_array($properties) || (is_object($properties))) ) {
            foreach($properties as $key => $value) {
                $this->$key = $value;
            }
        }               
    } // function attach_properties


    /**
     * Wrapper for model#attach_properties.
     * Adds key - value pairs to objects as properties and their values.
     * Does NOT save the model.
     *
     * @param recordset $properties   Array of properties and corresponding values
     * @return void
     */
    function update($properties = false) {
        $this->attach_properties($properties);
    } // function update


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
     * Please note: there is a 'singular' version for this method model#delete, but it
     * has a different behavior.
     *
     * @param  string       $where_clause SQL where clause (optional) - will delete all if false!
     * @param  array        $params       Array of sql parameters (optional)
     * @return mixed                      Returns true upon success or false if an error occured.
     */
    public static function delete_all($where_clause = false, $params = null) {
        $connection = static::get_connection_object();
        $where = ($where_clause) ? "$where_clause" : "1=1";
        return $connection->delete_records_select(static::table_name(), $where, $params);
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
     * Constructs an SQL WHERE clause out of an array of column names and an array of corresponding values
     *
     * @param  array        $properties     Array of column names. If the $args parameter is not provided, 
     *                                      $properties is assumed to be an associative array: a column 
     *                                      name pointing to a value.
     * @param  array        $args           Array of values for the columns. If not provided, the parameter
     *                                      $properties must contain the values (optional) 
     * @return string                       Returns a string containing a WHERE clause
     */
    public static function build_where_clause($properties, $args = false) {
        $where_parts = array();
        $columns = (! $args) ? $properties : static::map_properties_to_values($properties, $args);
        if (! is_array($columns) ) return '';
        foreach($columns as $property => $value) {
            $where_parts[] = "$property = '$value'";
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
     * @return array                      Returns array of items of mixed type, or false 
     */
    public static function find_all($compare, $collection) {
        // if array_filter does not yield any results, php returns false
        return array_filter($collection, $compare);
    } // function find_all


    /**
     * Returns all values for the property $property from $collection (will return an empty
     * element if the property does not exist at all for a given item)
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
     * Returns an associative array with properties extracted from an object (or array)
     *
     * @param  object   $object      Object to extract 
     * @param  array    $columns     Array of strings identifying the columns to be extracted
     * @return array                 Associative array with properties
     */
    public static function extract($object, $columns) {
        if (! ($columns && (is_array($columns) || (is_object($columns))) )) {
            throw new Exception("Argument $columns should be an object or an array");
        }
        $properties = array();
        foreach($columns as $column) {
            $properties[$column] = $object->$column;
        }
        return $properties;
    } // function extract


    /**
     * Adds single quotes to all words in a comma separated string.
     *
     * @param  string     $string     Comma separated string
     * @return array                  Returns a comma separated string where each word is surrounded by quotes
     */
    public static function quotify($string) {
        $string = explode( ',', $string);
        $string = array_map(function($tag) { return trim($tag); }, $string);
        return "'" . join( "', '", $string ) . "'";
    } // function quotify


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
        
        $this->define_validation_rules();
        
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
        return $this->save_without_validation();
    } // function delete


    /**
     * Validates and saves the object.
     * If the validation failes, the object is not saved. Instead, false is returned.
     *
     * @param  integer  Id of the object to save (optional)
     * @return integer  Returns the record id of the object upon success, otherwise false
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
        $connection = static::get_connection_object();
        $class = get_class($this);
        if ($record_id || (property_exists($this, 'id') && $this->id && $this->id != '') ) {
            if ($record_id) $this->id = $record_id;
            if (!$connection->update_record($class::table_name(), $this)) return false;
            return $this->id;
        }
        return $this->id = $this->create();
    } // function save_without_validation


    /**
     * Inserts a new record in the database. Usually only indirectly called through save_without_validation.
     * Can be overwritten in your own model if you want to perform some additional operation, such as always
     * creating an additional record in another table.
     *
     * @return integer  Returns the record id of the object upon success, otherwise false
     */
    function create() {
        $connection = static::get_connection_object();
        $class = get_class($this);
        return $this->id = $connection->insert_record($class::table_name(), $this);               
    } // function create


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


    /**
     * Retrieve plugin type as a string
     *
     * Currently, only two types of plugins are supported: mod and report
     */
    static function get_plugin_type() {
        global $CFG;
        $reflection = new ReflectionClass( get_called_class() );
        // get path relative to webroot
        $location = substr($reflection->getFileName(), strlen($CFG->dirroot));
        foreach(soda::$supported_plugins as $plugin) {
            if (strpos($location, $plugin) !== false) return $plugin;
        }
        return static::$plugin_type;
    } // function get_plugin_type

} // class model 

?>

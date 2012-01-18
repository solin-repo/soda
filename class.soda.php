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

include_once("{$CFG->dirroot}/local/soda/class.controller.php");
include_once("{$CFG->dirroot}/local/soda/class.soda_error.php");
include_once("{$CFG->dirroot}/local/soda/class.model.php");
include_once("{$CFG->dirroot}/local/soda/class.helper.php");

/**
 * The Soda base class. If you instantiate this class, all default lib functions for 
 * Moodle module will be made available automatically. These are accessible as both
 * static functions on your Soda derived module class and as global functions prefixed
 * with your module's name.
 *
 * To build a Moodle module with Soda, you need at least three files. Here, we'll provide
 * an example derived from a module called 'compass':
 *
 *   - mod/compass/index.php
 *   - mod/compass/lib.php
 *   - mod/compass/class.compass.php
 *
 * The first file, index.php should look like this:
 * <code>
 *   # mod/compass/index.php
 *   require_once("../../config.php");
 *   include_once('lib.php');
 *   $compass_instance->display();
 * </code>
 * 
 * Apart from these lines of code, your index.php file can be entirely empty.
 *
 * The $compass_instance variable is assigned in lib.php:
 * <code>
 *   # mod/compass/lib.php
 *   require_once("class.compass.php");
 *   $compass_instance = new compass();
 * </code>
 *
 * Again, apart from these lines of code, your lib.php file can be entirely empty.
 *
 *
 * class.compass.php, in turn, looks like this:
 * <code>
 *   # mod/compass/class.compass.php
 *   include_once("{$CFG->dirroot}/local/soda/class.soda.php"); 
 *   class compass extends soda {}
 * </code>
 *
 * Please use the soda extending class (i.e. class compass in our example) to overwrite any non-default functions
 * you would normally place in lib.php.
 *
 *
 * Once these classes are in place, you can call display() on your module's Soda extension in your module's index.php file:
 *
 *   $compass_instance->display();
 *
 * @package Soda
 */
class soda {

    var $redirect = false;

    /**
     * Instantiates soda class and creates all standard Moodle module library functions.
     *
     * @return void
     */
    function __construct() {
        $this->create_mod_library_functions( get_called_class() );
    } // function __construct


    /**
     * Entry point of any Soda based Moodle module.
     * Call this method from your module's index.php file.
     *
     * @return void
     */
    function display() {            
        $mod_name = get_called_class();
        global ${$mod_name}, $CFG, $cm, $course, $soda_module_name;
        $soda_module_name = $mod_name;
        ${$mod_name} = static::get_module_instance();
        static::set_variables($mod_name);

        // TODO: call default module->index() to show 'all instances of module'
        $action = optional_param('action', 'index', PARAM_RAW);
        $this->add_layout_and_dispatch($action);
    } // function display


    /**
     * Dispatches control to the Soda based Module.
     * The dispatcher instantiates the appropriate controller, model and helper classes.
     * If there is no 'controller' parameter, Soda will use module name to find a controller.
     * In case there is no [module_name]_controller either, the Soda controller (this class) will
     * be instantiated.
     * Then, the method from the $action parameter is invoked (the action function is called).
     * Finally, the after_action method is called on the controller.
     *
     * @param   string  $action Method to call on the target controller
     * @return  void
     */
    function dispatch($action) {
        $mod_name = get_called_class();
        global $CFG, ${$mod_name}, $PAGE, $soda_module_name, $cm;

        $controller = optional_param('controller', $mod_name, PARAM_RAW);

        $general_helper = $this->get_helper($mod_name);
        $specific_helper  = $this->get_helper($mod_name, $controller);

        if (! controller::load_file($controller, $mod_name)) {
            $instance = new controller($mod_name, ${$mod_name}->id, $action);
            $instance->set_helpers(array($general_helper, $specific_helper));
            return $instance->$action();
        }
        $record_id = optional_param("{$controller}_id", false, PARAM_INT);
        if (file_exists("{$CFG->dirroot}/mod/$mod_name/models/{$controller}.php")) {
            include_once("{$CFG->dirroot}/mod/$mod_name/models/{$controller}.php");
        }
        $class = $controller . "_controller";
        $instance = new $class($mod_name, ${$mod_name}->id, $action);
        $instance->set_helpers(array($general_helper, $specific_helper));
        $instance->$action($record_id);               
        $this->redirect = $instance->redirect;
        $instance->after_action();
    } // function dispatch


    /**
     * Instantiates the helper class for a controller and returns the object.
     * If you don't specify a controller, the current controller will be used.
     *
     * @param   string  $mod_name   Module name
     * @param   string  $controller Name of the controller (optional)
     * @return  object              Returns the helper object or false
     */
    function get_helper($mod_name, $controller = false) {
        global $CFG;
        $helper = false;
        $path = ($controller) ? "{$controller}/" : "";
        $helper_class_name = ($controller) ? "{$controller}_helper" : "{$mod_name}_helper"  ;
        if (file_exists("{$CFG->dirroot}/mod/$mod_name/helpers/{$path}class.{$helper_class_name}.php")) {
            include_once("{$CFG->dirroot}/mod/$mod_name/helpers/{$path}class.{$helper_class_name}.php");
            //if (!$controller) exit("{$CFG->dirroot}/mod/$mod_name/helpers/{$path}class.{$helper_class_name}.php");
            $helper = new $helper_class_name();
        }               
        return $helper;
    } // function get_helper


    /**
     * Outputs the result of the action called on the controller and conditionally renders the layout as well.
     * By default, whenever you call a Soda controller's redirect method, no layout will be rendered.
     *
     * @param   string  $action  Method to invoke on the controller
     * @return  void
     */
    function add_layout_and_dispatch($action) {
        $mod_name = get_called_class();
        global $CFG, $cm, $PAGE, $soda_module_name, ${$mod_name}, $course;

        // Included to fix problem: "Coding problem: $PAGE->context was not set. You may have forgotten to call 
        //                           require_login() or $PAGE->set_context(). The page may not display correctly 
        //                           as a result"  
        require_course_login($course);


        $PAGE->set_url("/mod/$soda_module_name/index.php", array('id' => $cm->id, 'action' => $action, 'controller' => optional_param('controller', $mod_name, PARAM_RAW) ));
        $PAGE->set_pagelayout('admin');
        $header = $this->get_header(get_called_class());

        ob_start(); // Start output buffering
        $this->dispatch($action);
        $content = ob_get_contents(); // Store buffer in variable
        ob_end_clean(); // End buffering and clean up

        if ($this->no_layout_required() ) {
            echo $content;
            return;
        }

        echo $header;
        echo $content;
        $this->print_footer(get_called_class());
    } // function add_layout_and_dispatch


    /**
     * Determines whether the layout should be displayed or not.
     * By default, whenever you call a Soda controller's redirect method, no layout is required.
     *
     * @return  boolean     Returns true if no layout is required, otherwise false
     */
    function no_layout_required() {
        if ($this->redirect) return true;
        if (optional_param('no_layout', false, PARAM_RAW)) return true;
        return false;
    } // function no_layout_required


    /**
     * Returns Moodle layout header
     *
     * @param   string  $mod_name   Name of the module
     * @return  string              Returns Moodle layout header
     */
    function get_header($mod_name) {
        global $cm, $course, $CFG, $OUTPUT;
        ob_start(); // Start output buffering
        $str_mod_name_singular = get_string('modulename', $mod_name);
        /*
        $navigation = build_navigation( get_string('modulename', $mod_name) );
        print_header_simple(format_string($mod_name), "", $navigation, "", "", true,
                            update_module_button($cm->id, $course->id, $str_mod_name_singular), navmenu($course, $cm));               
         */
        echo $OUTPUT->header();
        echo "<script src='{$CFG->wwwroot}/local/soda/lib.js' type='text/javascript'></script>";
        $header = ob_get_contents(); // Store buffer in variable
        ob_end_clean(); // End buffering and clean up
        return $header;
    } // function get_header


    /**
     * Returns Moodle layout footer
     *
     * @param   string  $mod_name   Name of the module
     * @return  string              Returns Moodle layout footer
     */
    function print_footer($mod_name) {
        global $course, $OUTPUT;
        //print_footer($course);
        echo $OUTPUT->footer();
    } // function print_footer


    /**
     * Creates globally defined wrapper functions for all static functions in this class.
     * Uses the 'mod_name' to prefix the functions to create unique function names.
     * This eliminates the need to specify the same functions for each new Moodle module in lib.php.
     *                                                                                   
     * Example: if your mod is called 'planner', planner_get_instance will be created
     * as a wrapper for planner::get_instance() - which will typically be inherited. I.e. there is a soda::get_instance().
     *
     * @param   string  $mod_name   Name of the module
     * @return  void
     */
    function create_mod_library_functions($mod_name) {
        $reflection = new ReflectionClass( get_called_class() );
        foreach($reflection->getMethods(ReflectionMethod::IS_STATIC) as $method) {
            $this->create_wrapper_function($method, $mod_name);
        }
        // special case deviating from naming convention
        if (! function_exists($mod_name . '_get_' . $mod_name) ) {
            eval('function ' . $mod_name . '_get_' . $mod_name . '($mod_id) { return ' . $mod_name . '::get_mod_by_id($mod_id); }');
        }
    } // function create_mod_library_functions


    /**
     * Creates a globally defined wrapper function.
     *
     * @param   string  $method     Reference to the method
     * @param   string  $mod_name   Name of the module
     * @return  void
     */
    function create_wrapper_function($method, $mod_name) {
        $base_method_name = $method->name;
        $param_names = array();
        $params = $method->getParameters();
        foreach ($params as $param) {
            $param_names[] = '$' . $param->getName();
        }
        $arguments = (count($param_names)) ? join(', ', $param_names) : '';
        $function_name = $mod_name . '_' . $base_method_name;
        if (! function_exists($function_name)) {
            $str = "function $function_name($arguments) { return $mod_name::$base_method_name($arguments); }";
            // Better close your eyes now...
            eval($str);
        }               
    } // function create_wrapper_function


    /*
    function initialize() {
                
    } // function initialize
    */


    /***************************************************************************************************************
     * Following methods are derived from Moodle's standard lib.php functions.
     * Please overwrite them in your own module's class if you need deviant behavior.
     ***************************************************************************************************************/

    static function add_instance($mod_instance) {
        global $DB;

        $mod_instance->timecreated = time();

        # You may have to add extra stuff in here #

        return $DB->insert_record(get_called_class(), $mod_instance);
    } // function add_instance


    static function update_instance($mod_instance) {
        global $DB;

        $mod_instance->timemodified = time();
        $mod_instance->id = $mod_instance->instance;

        # You may have to add extra stuff in here #

        return $DB->update_record(get_called_class(), $mod_instance);
    } // function update_instance


    static function delete_instance($id) {
        global $DB;

        if (! $mod_instance = $DB->get_record(get_called_class(), array('id' => $id))) {
            return false;
        }

        # Delete any dependent records here #

        $DB->delete_records(get_called_class(), array('id' => $mod_instance->id));

        return true;
    } // function delete_instance


    static function user_outline($course, $user, $mod, $mod_instance) { 
        $return = new stdClass;
        $return->time = 0;
        $return->info = '';
        return $return;
    } // function user_outline


    static function user_complete($course, $user, $mod, $planner) { return true; }
    static function print_recent_activity($course, $isteacher, $timestart) { return false; }
    static function cron() { return true; }
    static function grades($mod_id) { return NULL; }
    static function get_participants($mod_id) { return false; }
    static function scale_used($mod_id, $scale_id) { return false; }

    /**
     * Checks if scale is being used by any instance of newmodule.
     * This function was added in 1.9
     *
     * This is used to find out if scale used anywhere
     * @param $scaleid int
     * @return boolean True if the scale is used by any newmodule
     */
    static function scale_used_anywhere($scaleid) {
        global $DB;

        if ($scaleid and $DB->record_exists(get_called_class(), 'grade', -$scaleid)) {
            return true;
        } else {
            return false;
        }
    }

    static function get_navigation() {
        global $course;
        if ($course->category) {
            return "<a href=\"../../course/view.php?id=$course->id\">$course->shortname</a> ->";
        }
        return '';
    } // function get_navigation


    static function get_module_instance() { 
        global $course, $cm, $id, $DB;
        
        $id = optional_param('id', 0, PARAM_INT); // Course Module ID, or
        $a  = optional_param('a', 0, PARAM_INT);  // planner ID

        if ($id) {
            $cm         = get_coursemodule_from_id(get_called_class(), $id, 0, false, MUST_EXIST);
            $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
            $mod_instance  = $DB->get_record(get_called_class(), array('id' => $cm->instance), '*', MUST_EXIST);
        } elseif ($n) {
            $mod_instance  = $DB->get_record(get_called_class(), array('id' => $n), '*', MUST_EXIST);
            $course     = $DB->get_record('course', array('id' => $mod_instance->course), '*', MUST_EXIST);
            $cm         = get_coursemodule_from_instance(get_called_class(), $mod_instance->id, $course->id, false, MUST_EXIST);
        } else {
            error('You must specify a course_module ID or an instance ID');
        }
        return $mod_instance;
    } // function get_module_instance


    static function get_mod_by_id($mod_id) {
        global $DB;
        return $DB->get_record(get_called_class(), array("id" => $mod_id) );
    }


    static function set_variables($mod_name) {
        global $cm, $id, $course, $context, $DB;
        if (! $cm = get_coursemodule_from_id($mod_name, $id)) {
            error("Course Module ID was incorrect");
        }

        if (! $course = $DB->get_record("course", array("id" => $cm->course) )) {
            error("Course is misconfigured");
        } 
        
        if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
            print_error('badcontext');
        }  
    } // function set_variables

} // class soda 
?>

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
include_once("{$CFG->dirroot}/local/soda/class.user.php");

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
 *
 * TODO: remove global $course and store the information in a static variable
 *
 * @package Soda
 */
class soda {

    var $no_layout = false;
    var $overriding_no_layout = false;
    var $mod_name = false;
    var $plugin_type = 'mod';
    static $supported_plugins = array('mod', 'report', 'local');

    /**
     * Instantiates soda class and creates all standard Moodle module library functions.
     *
     * @return void
     */
    function __construct() {
        $this->create_mod_library_functions( get_called_class() );
        $this->mod_name = get_called_class();
        $this->plugin_type = $this->determine_plugin_type();
    } // function __construct


    /**
     * Retrieve plugin type as a string
     *
     * Currently, only two types of plugins are supported: mod and report
     */
    function determine_plugin_type() {
        global $CFG;
        $reflection = new ReflectionClass( get_called_class() );
        // get path relative to webroot
        $location = substr($reflection->getFileName(), strlen($CFG->dirroot));
        foreach(self::$supported_plugins as $plugin) {
            if (strpos($location, $plugin) !== false) return $plugin;
        }
        return $this->plugin_type;
    } // function determine_plugin_type

    /**
     * Entry point of any Soda based Moodle module.
     * Call this method from your module's index.php file.
     *
     * @return void
     */
    function display($no_layout = false, $activity_id = false, $overriding_controller = false, $overriding_action = false) {            
        $this->overriding_no_layout = $no_layout;
        $mod_name = get_called_class();
        global ${$mod_name}, $CFG, $cm, $course, $soda_module_name, $DB, $id;
        $id = optional_param('id', 0, PARAM_INT); // Course Module ID in case of mods, or course_id in case of reports (sorry, default Moodle stuff...)
        $soda_module_name = $mod_name;
        switch($this->plugin_type) {
            case 'mod':
                ${$mod_name} = static::get_module_instance($activity_id);
                static::set_variables($mod_name);
                break;
            case 'local':
            case 'report':
                $course = $DB->get_record( 'course', array('id' => $id) );
                break;
        }
        // TODO: call default module->index() to show 'all instances of module'
        $action = optional_param('action', 'index', PARAM_RAW);
        $action = ($overriding_action) ? $overriding_action : $action;
        $this->add_layout_and_dispatch($action, $overriding_controller);
    } // function display


    /**
     * Dispatches control to the Soda based Module.
     * The dispatcher instantiates the appropriate controller, model and helper classes.
     * If there is no 'controller' parameter, Soda will use module name to find a controller.
     * In case there is no [module_name]_controller either, the Soda controller (this class) will
     * be instantiated.
     * Then, the method from the $action parameter is invoked (the action function is called).
     * Finally, the after_action method is called on the controller.
     * NOTE: this method needs refactoring
     *
     * @param   string  $action Method to call on the target controller
     * @return  controller
     */
    function dispatch($action, $overriding_controller) {
        $mod_name = get_called_class();
        global $CFG, ${$mod_name}, $PAGE, $soda_module_name, $cm;

        $controller = optional_param('controller', $mod_name, PARAM_RAW);
        $controller = ($overriding_controller) ? $overriding_controller : $controller;
        $helpers = array($this->get_helper($mod_name), $this->get_helper($mod_name, $controller));

        if (! controller::load_file($controller, $mod_name, $this->plugin_type)) {
            // no specific controller - let's fallback to default
            $instance = new controller($mod_name, ${$mod_name}->id, $action, $this->plugin_type);
            $instance->overriding_no_layout = $this->overriding_no_layout;
            return $this->perform_action($instance, $action, $helpers);
        }
        $record_id = optional_param("{$controller}_id", false, PARAM_INT);
        if (file_exists("{$CFG->dirroot}/{$this->plugin_type}/$mod_name/models/{$controller}.php")) {
            include_once("{$CFG->dirroot}/{$this->plugin_type}/$mod_name/models/{$controller}.php");
        }
        $class = $controller . "_controller";
        $mod_instance_id = (is_object(${$mod_name}) && property_exists(${$mod_name}, 'id')) ? ${$mod_name}->id : 0; // bit of a hack to make reports work
        $instance = new $class($mod_name, $mod_instance_id, $action, $this->plugin_type);
        $instance->overriding_no_layout = $this->overriding_no_layout;
        return $this->perform_action($instance, $action, $helpers, $record_id);
    } // function dispatch


    /**
     * Initializes the controller by setting helpers, and performs the requested action.
     * If no action was specified, the controller instance is returned immediately.
     * Otherwise, the after_action code hook is handled.
     *
     * @param   object  $instance   Controller instance
     * @param   string  $action     Name of the action or false
     * @param   array   $helpers    Array of helper objects
     * @param   integer $record_id  Id of record which the action is 'about' (optional)
     * @return  object              Returns the controller instance object
     */
    function perform_action($instance, $action, $helpers, $record_id = false) {
        $instance->set_helpers($helpers);
        if (! $action ) return $instance;
        $instance->$action($record_id);               
        $this->no_layout = $instance->no_layout;
        $instance->after_action();
        return $instance;               
    } // function perform_action


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
        if (file_exists("{$CFG->dirroot}/{$this->plugin_type}/$mod_name/helpers/{$path}class.{$helper_class_name}.php")) {
            include_once("{$CFG->dirroot}/{$this->plugin_type}/$mod_name/helpers/{$path}class.{$helper_class_name}.php");
            //if (!$controller) exit("{$CFG->dirroot}/{$this->plugin_type}/$mod_name/helpers/{$path}class.{$helper_class_name}.php");
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
    function add_layout_and_dispatch($action, $overriding_controller) {
        $mod_name = get_called_class();
        global $CFG, $cm, $PAGE, $soda_module_name, ${$mod_name}, $course;

        $this->set_page_variables($cm, $course);

        ob_start(); // Start output buffering
        $controller = $this->dispatch($action, $overriding_controller);
        $content = ob_get_contents(); // Store buffer in variable
        ob_end_clean(); // End buffering and clean up

        if ($this->no_layout_required() ) {
            echo $content;
            return;
        }
        // retrieve the stored moodle header from the controller
        $header = $controller->get_moodle_header();
        echo $header;
        echo $content;
        $this->print_footer(get_called_class());
    } // function add_layout_and_dispatch


    function set_page_variables($cm, $course) {
        // Included to fix problem: "Coding problem: $PAGE->context was not set. You may have forgotten to call 
        //                           require_login() or $PAGE->set_context(). The page may not display correctly 
        //                           as a result"  
        if ($this->overriding_no_layout) return;
        /*
        if ($this->plugin_type == 'mod') return require_course_login($course, true, $cm);
        require_login($course); 
         */
    } // function set_page_variables


    /**
     * Determines whether the layout should be displayed or not.
     * By default, whenever you call a Soda controller's redirect method, no layout is required.
     *
     * @return  boolean     Returns true if no layout is required, otherwise false
     */
    function no_layout_required() {
        if (($this->no_layout) || ($this->overriding_no_layout)) return true;
        if (optional_param('no_layout', false, PARAM_RAW)) return true;
        return false;
    } // function no_layout_required


    /**
     * Returns Moodle layout header
     * WARNING: NOT USED ANYMORE - TO BE REMOVED IN SOME FUTURE VERSION
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


    /**
     * List of features supported in your module
     * @param string $feature FEATURE_xx constant for requested feature
     * @return mixed True if module supports feature, false if not, null if doesn't know
     */
    static function supports($feature) {
        switch($feature) {
            /*
            case FEATURE_MOD_ARCHETYPE:           return MOD_ARCHETYPE_RESOURCE;
            case FEATURE_GROUPS:                  return false;
            case FEATURE_GROUPINGS:               return false;
            case FEATURE_GROUPMEMBERSONLY:        return true;
            case FEATURE_MOD_INTRO:               return true;
            case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
            case FEATURE_GRADE_HAS_GRADE:         return false;
            case FEATURE_GRADE_OUTCOMES:          return false;
            case FEATURE_BACKUP_MOODLE2:          return true;
            case FEATURE_SHOW_DESCRIPTION:        return true;
            */
            default: return null;
        }
    } // function supports


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

        try
        {
            if ($scaleid and $DB->record_exists(get_called_class(), array('grade'=> -$scaleid))) {
                return true;
            } else {
                return false;
            }
        }
        catch (dml_exception $exception)
        {
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


    static function get_module_instance($activity_id = false) { 
        global $course, $cm, $id, $DB;
        
        if ($activity_id) {
            $mod_instance  = $DB->get_record(get_called_class(), array('id' => $activity_id), '*', MUST_EXIST);
            $course     = $DB->get_record('course', array('id' => $mod_instance->course), '*', MUST_EXIST);
            $cm         = get_coursemodule_from_instance(get_called_class(), $mod_instance->id, $course->id, false, MUST_EXIST);
        } elseif ($id) {
            $cm         = get_coursemodule_from_id(get_called_class(), $id, 0, false, MUST_EXIST);
            $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
            $mod_instance  = $DB->get_record(get_called_class(), array('id' => $cm->instance), '*', MUST_EXIST);
        } else {
            error('You must specify a course_module ID or an instance ID');
        }
        $id = $cm->id;
        /*
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
        global $PAGE;
        $PAGE->set_context($context);
         */
        return $mod_instance;
    } // function get_module_instance


    static function get_mod_by_id($mod_id) {
        global $DB;
        return $DB->get_record(get_called_class(), array("id" => $mod_id) );
    }


    static function set_variables($mod_name) {
        global $cm, $id, $course, $context, $DB, $PAGE;
        /* Redundant!
        if (! $cm = get_coursemodule_from_id($mod_name, $id)) {
            error("Course Module ID was incorrect");
        }

        if (! $course = $DB->get_record("course", array("id" => $cm->course) )) {
            error("Course is misconfigured");
        } 
         */
        
        if (isset($context)) return;
        if (!$context = context_module::instance($cm->id)) {
            print_error('badcontext');
        }  
        $PAGE->set_context($context);
    } // function set_variables



    static function arguments( $args ) {
        array_shift( $args );
        $endofoptions = false;

        $ret = array
        (
        'commands' => array(),
        'options' => array(),
        'flags'    => array(),
        'arguments' => array(),
        );

        while ( $arg = array_shift($args) )
        {

        // if we have reached end of options,
        //we cast all remaining argvs as arguments
        if ($endofoptions)
        {
          $ret['arguments'][] = $arg;
          continue;
        }

        // Is it a command? (prefixed with --)
        if ( substr( $arg, 0, 2 ) === '--' )
        {

          // is it the end of options flag?
          if (!isset ($arg[3]))
          {
            $endofoptions = true;; // end of options;
            continue;
          }

          $value = "";
          $com   = substr( $arg, 2 );

          // is it the syntax '--option=argument'?
          if (strpos($com,'='))
            list($com,$value) = split("=",$com,2);

          // is the option not followed by another option but by arguments
          elseif (strpos($args[0],'-') !== 0)
          {
            while (strpos($args[0],'-') !== 0)
              $value .= array_shift($args).' ';
            $value = rtrim($value,' ');
          }

          $ret['options'][$com] = !empty($value) ? $value : true;
          continue;

        }

        // Is it a flag or a serial of flags? (prefixed with -)
        if ( substr( $arg, 0, 1 ) === '-' )
        {
          for ($i = 1; isset($arg[$i]) ; $i++)
            $ret['flags'][] = $arg[$i];
          continue;
        }

        // finally, it is not option, nor flag, nor argument
        $ret['commands'][] = $arg;
        continue;
        }

        if (!count($ret['options']) && !count($ret['flags']))
        {
        $ret['arguments'] = array_merge($ret['commands'], $ret['arguments']);
        $ret['commands'] = array();
        }
        return $ret;
    } // function arguments 


    public static function find_cm_by_course_and_section($course_id, $section_id) {
        global $DB;
        if (! $cm = $DB->get_record_sql(
            "SELECT cm.* FROM {course_modules} AS cm, {modules} AS m 
             WHERE m.name = :module AND m.id = cm.module AND course = :course_id AND section = :section_id",
            array( 'course_id' => $course_id, 'module' => get_called_class(), 'section_id' => $section_id)
        )) return false;
        return $cm;               
    } // function find_cm_by_course_and_sectioncourse_module_by_course_and_section

} // class soda 
?>

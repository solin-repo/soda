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
 * A controller performs actions based on form submits and querystring parameters.
 * Typically, a controller will perform one action and then either redirect
 * the browser to perform a new action or display a view.
 * You don't have to instantiate a controller yourself. Soda constructs
 * the appropriate controller based on the controller querystring parameter (or
 * form post variable).
 * If there is no 'controller' parameter, Soda will use module name to find a controller.
 * In case there is no [module_name]_controller either, the Soda controller (this class) will
 * be instantiated.
 * @package Soda
 */
class controller {

    var $mod_name;
    var $model_name;
    var $action;
    var $view;
    var $helpers = array();
    var $no_layout = false;
    var $overriding_no_layout = false;
    var $user;
    var $course_id;
    var $instance_id; // Id of the module record itself (as opposed to the course_module.id)
    var $auto_replace_vars = false; // replaces template variables like {$username}, {$city}, {$user.firstname}
    var $plugin_type;
    protected $_moodle_header = '';
    protected $_page_title;
    protected $_nav_title;
    protected $_nav_link;


    /**
     * Instantiates controller class.
     * Sets a number of important instance variables:
     * 
     *      - mod_name: name of the current Moodle module
     *      - model_name: model name as derived from the controller class name
     *      - base_url: url pointing to the index file of the current module
     *      - action: currently invoked action method
     *      - [module_name]_id (e.g. for module 'compass': $this->compass_id)
     *      - user (if there is a $USER available)
     *
     * @param string  $mod_name         Name of the Moodle module where the controller resides
     * @param integer $mod_instance_id  Id of the module's instance
     * @param string  $action           Name of the method to be invoked later on
     * @return void
     */
    function __construct($mod_name, $mod_instance_id, $action = false, $plugin_type = 'mod') {
        global $USER, $course, $compass, $DB;
        $this->plugin_type = $plugin_type;
        $this->course = $course;
        $this->mod_name = $mod_name;
        $this->model_name = static::model_name(get_called_class()); 
        $this->base_url = $this->create_base_url();
        $instance_id_name = "{$this->mod_name}_id";
        $this->action = ($action) ? $action : optional_param('action', 'index', PARAM_RAW);
        $this->{$instance_id_name} = $this->instance_id = $mod_instance_id;
        if (isset($USER) && ($USER->id != 0)) $this->user = $USER;
        if (isset($this->course) && is_object($this->course)) $this->course_id = $this->course->id;

    } // function __construct


    public static function model_name($controller_name) {
        return substr($controller_name, 0, strrpos($controller_name, "_"));
    } // function model_name


    /**
     * Loads controller class file
     * Used by class soda to include the controller class.
     *
     * @param string  $controller_name  Name of the controller 
     * @param string  $mod_name         Name of the Moodle module where the controller resides
     * @return void
     */
    static function load_file($controller_name, $mod_name, $plugin_type) {
        global $CFG;
        if (!file_exists("{$CFG->dirroot}/{$plugin_type}/$mod_name/controllers/{$controller_name}.php")) return false;
        return include_once("{$CFG->dirroot}/{$plugin_type}/$mod_name/controllers/$controller_name.php");
    } // function load_file


    /**
     * Loads controller class file.
     * This function is a wrapper for controller::load_file which provides the mod_name to load_file.
     * Please note that you shouldn't normally need this method, since it can only be used to load
     * another controller from within the current controller. Use case: you need some metadata from another
     * controller that is relevant in the current controller.
     *
     * @param string  $controller_name  Name of the controller 
     * @return void
     */
    function load($controller_name) {
        return static::load_file($controller_name, $this->mod_name, $this->plugin_type);
    } // function load


    /**
     * Called after the action method is invoked.
     * Overwrite this method in your own controller to have code executed each time an action was invoked.
     * Example usage:
     *    
     * <code>
     * function after_action() {
     *     if (!$this->redirect) {
     *         $this->save_state();
     *     }
     * } // function after_action
     * </code>
     *
     * - this example saves the last called action conditionally (i.e. the 'redirect' property gets set inside the action method)
     *
     * @return void
     */
    function after_action() {
    } // function after_action


    /**
     * Endows the controller with a collection of helper methods which
     * are made available as native controller methods.
     *
     * @param  array  $helpers  Array of helper methods
     * @return void
     */
    function set_helpers($helpers = false) {        
        if (!$helpers) return;
        $helper_set = false;
        foreach($helpers as $helper) {
            if (!$helper) continue;
            $helper->controller = $this;
            $this->helpers[] = $helper;
            $helper_set = true;
        }
        if (! $helper_set ) {
            $helper = new helper();
            $helper->controller = $this;
            $this->helpers[] = $helper;
        }
    } // function set_helpers


    /**
     * Reroutes method calls to non-existing methods to helper methods.
     * Throws an exception if the called method does not exist as a helper method either.
     * Please note that this behavior is symmetrical: the Soda helper class reroutes calls to unknown
     * methods to the controller class.
     *
     * @param  string       $method Name of the method to call dynamically
     * @param  array        $args   Array of arguments with the method
     * @return mixed        Returns whatever the called method returns
     */
    function __call($method, $args) {
        foreach($this->helpers as $helper) {
            if (!method_exists($helper, $method)) continue;
            return call_user_func_array(
                array($helper, $method),
                $args
            );
        }
        throw new Exception("Unknown method [$method]");
    } // function __call

    
    /**
     * Default action. Please overwrite this method in your own controller.
     * If you don't specify an action, Soda will invoke the index method on your controller.
     *
     * @return void
     */
    function index() {
        $this->_prepare_moodle_header($this->mod_name);
        echo "<h1>Soda controller#index</h1>
              <p>Please create a controller and a view of your own to get started.</p>
              <p>Visit <a target='_blank' href='http://tech.solin.eu/doku.php?id=moodle:using_soda_to_create_new_moodle_modules'>tech.solin.eu</a>
              to learn more about creating a Moodle module with Soda.</p>
              <p>Soda's Application Programming Interface (API) can be found <a target='_blank' href='http://soda-api.solin.eu/'>here</a>.</p>";
    } // function index


    /**
     * Wrapper function for Moodle's has_capability function.
     * Calls has_capability with the current context and the currently logged in user, for the module where the controller resides.
     *
     * @param  string       $capability_short Name of the capability to check, defaults to 'edit' (optional)
     * @param  string       $model_name       Name of the model for which the capability must be checked (defaults to 'current')
     * @param  array        $user             User to check capability for, defaults to currently logged in user (optional)
     * @return boolean                        Returns true if user has capability, otherwise false
     */
    function check_capability($capability_short = 'edit', $model_name = false, $user = false) {
        global $context, $USER;
        if (!$user) $user = $USER;
        $model_name = ($model_name !== false ) ? $model_name : $this->model_name;
        return has_capability("{$this->plugin_type}/{$this->mod_name}:$capability_short{$model_name}", $context, $user->id); 
    } // function check_capability


    /**
     * Wrapper function for check_capability, but sets model_name to empty string.
     * Calls has_capability with the current context and the currently logged in user, for the module where the controller resides.
     *
     * @param  string       $capibility_short Name of the capability to check, defaults to 'edit' (optional)
     * @return boolean                        Returns true if user has capability, otherwise false
     */
    function has_capability($capability_short = 'edit') {
        return $this->check_capability($capability_short, '');
    } // function has_capability


    /**
     * Wrapper function for Moodle's require_login
     * require_login is called with $autologinguest = false by default
     *
     * @param  boolean  $autologinguest If true, the user will be automatically logged in as guest,
     *                                  if $CFG->autologinguests is also true. Defaults to false.
     * @return void
     */
    function require_login($autologinguest = false) {
        global $cm;
        require_login($this->course, $autologinguest, $cm);
    } // function require_login


    /**
     * Adds messages to session object for later reuse.
     * This function is typically called to add user feedback messages which are displayed after a redirect.
     *
     * @param  array  $messages Collection of messages to be added to the session object
     * @return void
     */
	function create_messages($messages) {
		foreach($messages as $key => $message) {
			$_SESSION['messages'][$key] = $message;
		}
	} // function create_messages


    function set_notification($notification) {
        $_SESSION['messages']['notification'] = $notification;
    } // function set_notification


    function set_error($error) {
        $_SESSION['messages']['error'] = $error;
    } // function set_error

	
    /**
     * Prints a message from the session object.
     * Typically used to provide the user with feedback about what happened before a redirect, e.g. to
     * display the message "Organization addresss saved", after saving an object of class address.
     *
     * @param  string  $key     Key of the message to print
     * @parem  boolean $delete  If delete is true (the default value), the message will be deleted from the session object (optional)
     * @return void
     */
	function print_message($key, $delete = true) {
        if ($message = $this->get_message($key, $delete)) {
            echo $message;
        }
	} // function print_message
    

    /**
     * Get a message from the session object.
     * Typically used to provide the user with feedback about what happened before a redirect, e.g. to
     * display the message "Organization addresss saved", after saving an object of class address.
     *
     * @param  string  $key     Key of the message to retrieve
     * @parem  boolean $delete  If delete is true (the default value), the message will be deleted from the session object (optional)
     * @return void
     */
    function get_message($key, $delete = true) {
		if (!isset($_SESSION['messages'][$key])) return false;
		$message = $_SESSION['messages'][$key];
		if ($delete) unset($_SESSION['messages'][$key]);
        return $message;
    } // function get_message


    /**
     * Redirect to another entry point in the module.
     * If you don't specify a controller, the current controller will be used. If you call redirect_to, 
     * no view will be presented for the current action.
     *
     * redirect_to is usualy called after executing some database action. Example:
     *
     * <code>
     *   $organization->delete();
     *   $this->redirect_to('index');
     * </code>
     *
     * @param string $action        The action to redirect to 
     * @param array  $parameters    Optional: Key - value pairs specifying the parameter's content
     * @param array  $messages      Optional: 'Ruby on Rails Flash'-type messages, identified with a key
     *                                        The messages will be displayed in the view associated with $action
     *                                        (See controller#create_messages).
     * @return void
     */
    function redirect_to($action, $parameters = false, $messages = false) {
        if ($messages) $this->create_messages($messages);
        if ($parameters) $parameters = $this->remove_collections_from_parameters($parameters);
        $query_string = ($parameters) ? http_build_query($parameters) . '&' : '';
        $this->redirect_to_url( $this->get_url("{$query_string}action=$action" , '') );
    } // function redirect_to


    /**
     * Wrapper for Moodle's redirect. Sets redirect property to true.
     * Typically, setting redirect to true means that the view for the current action is not displayed.
     *
     * @param  string  $url    Url to redirect to
     * @return void
     */
    function redirect_to_url($url) {
        $this->no_layout = true;
        redirect($url);
    } // function redirect_to_url


    /**
     * Returns a given array with all values of type array and object removed.
     * All objects and arrays are stripped from the array.
     *
     * @param  array  $parameters Array to be cleaned (optional)
     * @return $array             Returns cleaned array
     */
    function remove_collections_from_parameters($parameters = array()) {
        if (! count($parameters)) return $parameters;
        $filtered_array = array();
        foreach($parameters as $key => $value) {
            if (! ((is_array($value)) || (is_object($value))) ) $filtered_array[$key] = $value;
        }
        return $filtered_array;
    } // function remove_collections_from_parameters 


    /**
     * Creates Moodle layout header and stores it for 
	 * later use (see class.soda.php->add_layout_and_dispatch() )
     *
     * @param   string  $mod_name   Name of the module
     * @return  string              Returns Moodle layout header
     */
	protected function _prepare_moodle_header($mod_name) {
        global $cm, $course, $CFG, $OUTPUT, $context, $PAGE;

        if ($this->overriding_no_layout) return;
        $this->set_page_variables($mod_name, $course);


        ob_start(); // Start output buffering
        $prefix = ($this->plugin_type != 'mod') ? "{$this->plugin_type}_" : '';
        $str_mod_name_singular = get_string('modulename', $prefix.$mod_name);
        /*
        $navigation = build_navigation( get_string('modulename', $mod_name) );
        print_header_simple(format_string($mod_name), "", $navigation, "", "", true,
                            update_module_button($cm->id, $course->id, $str_mod_name_singular), navmenu($course, $cm));               
         */
        echo $OUTPUT->header();
        echo "<script src='//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js' type='text/javascript'></script>
              <script src='{$CFG->wwwroot}/local/soda/lib.js' type='text/javascript'></script>";
        $header = ob_get_contents(); // Store buffer in variable
        ob_end_clean(); // End buffering and clean up
        $this->_moodle_header = $header;
    } // function _prepare_moodle_header 


    /**
     * Set Moodle $PAGE variables. Override in your own controller if necessary.
     *
     * @param   string  $mod_name   Name of the module
     * @return  void
     */
    public function set_page_variables($mod_name, $course = false) {
        global $PAGE, $cm;        

        if ($this->plugin_type == 'report') $PAGE->set_pagelayout('admin');

        if ($cm) $PAGE->set_cm($cm, $course); // sets up global $COURSE

        // This call CHANGES THE NAVBAR
        $PAGE->set_course($course); // sets up global $COURSE
        //$PAGE->set_pagelayout('incourse');

        $query_array = array('action' => $this->action, 'controller' => optional_param('controller', $mod_name, PARAM_RAW));
        if (is_object($cm)) $query_array['id'] = $cm->id; // reports don't have $cm objects
        if (isset($_REQUEST)) {
            $query_array = $_REQUEST;
        }
        $query_array = self::remove_block_parameters(self::flatten_array($query_array));

        $PAGE->set_url("/{$this->plugin_type}/$mod_name/index.php", $query_array);
        if ($course) {
            $PAGE->set_heading(format_string($course->fullname));
        }
        
        $prefix = ($this->plugin_type != 'mod') ? "{$this->plugin_type}_" : '';
        $PAGE->set_title(format_string(get_string('modulename', $prefix.$mod_name)));
        if (isset($this->_page_title)) {
            $PAGE->set_title($this->_page_title);
        }

        if (isset($this->_nav_title)) {
            $PAGE->navbar->ignore_active();
            $PAGE->navbar->add($this->_nav_title, $this->_nav_link);
        }

        $PAGE->add_body_class("{$this->plugin_type}_{$this->mod_name}_{$this->model_name}");

    } // function set_page_variables


    public static function remove_block_parameters($query_array) {
        if (! is_array($query_array)) return $query_array;
        foreach($query_array as $key => $value) {
            if (substr($key, 0, 4) == 'bui_') unset($query_array[$key]);
        }
        return $query_array;
    } // function remove_block_parameters


    /**
     * Remove all elements from an array which are arrays themselves.
     *
     * @param    array   $collection
     * @return   array   flattened collection
     */
    public static function flatten_array($collection) {
        if (!is_array($collection)) return $collection;
        foreach($collection as $key => $value) {
            if (is_array($value)) unset($collection[$key]);
        }        
        return $collection;
    } // function flatten_array


	public function get_moodle_header() {
		return $this->_moodle_header;
	}
		
    /**
     * Converts variables array to an array with indexes 'in' and 'out', useable by str_replace
     * e.g. input: array('hello' => 'world, 'user' => array('name' => 'Pete', 'age' => '28'))
     * output: array(
     *   'in' => array('{$hello}', '{$user.name}', '{$user.age}'),
     *   'out' => array('world', 'Pete', '28')
     * );
     * 
     * @param mixed $data
     * @param string $prefix
     * @return
     */
    static function create_replace_array($data, $prefix='') {
        $in = array();
        $out = array();
        
        if (!$prefix) {
            $prefix = '{$';
        }
        
        if (is_array($data)) {
            foreach ($data as $varname=>$value) {
                if (!is_array($value) && (!is_object($value))) {
                    $in[] = $prefix . $varname . '}';
                    $out[] = $value;
                } elseif (!is_object($value)) {
                    $recursive_data = self::create_replace_array($value, $prefix . $varname . '.');
                    $in = array_merge($in, $recursive_data['in']);
                    $out = array_merge($out, $recursive_data['out']);
                }
            }
        }
        
        return array('in' => $in, 'out' => $out);
    }
        
    /**
     * Includes a html file containing the view for the current action.
     * If you specify the view parameter, that view will used instead of the default view.
     * You can also provide an associative array as the first argument. The keys in this array will made available
     * in the view as local variables holding the corresponding values from the array.
     * Please note that all instance variables of the controller will also be available in the view.
     * If the view cannot be found, the views directory for the current controller's parent will be searched.
     *
     * @param  array  $data_array Associative array with variable names pointing to corresponding values (optional)
     * @param  string $view       View to include, defaults to view with same name as current action (optional)
     * @param  string $template   Whether to use the module's template, defaults to true (optional)
     * @return void
     */
    function get_view($data_array = array(), $view = false, $template = true) {
        global $CFG, $id;
        
        // hack to make Moodle populate the $OUTPUT variable correctly (instead of a bootstrap_renderer)
        if (! ($this->no_layout || $this->overriding_no_layout) ) $this->_prepare_moodle_header($this->mod_name);

        foreach($data_array as $variable_name => $value) {
            $$variable_name = $value;
        }
        //include correct view
        $trace = debug_backtrace();
        $this->view = ($view) ? $view : $trace[1]['function'];
        $view_path = "{$CFG->dirroot}/{$this->plugin_type}/{$this->mod_name}/views/{$this->model_name}/{$this->view}.html";
        if (! file_exists($view_path)) {
            $parent_views = static::model_name(get_parent_class($this));
            $view_path = "{$CFG->dirroot}/{$this->plugin_type}/{$this->mod_name}/views/{$parent_views}/{$this->view}.html";
        }
        if (! $template) {
            include_once($view_path);
            return;
        }
        $template_path = "{$CFG->dirroot}/{$this->plugin_type}/$this->mod_name/views/template.html";
        if (file_exists($template_path)) {
            include_once($template_path);
            return;
        }
        
        ob_start();
        include_once($view_path);
        $contents = ob_get_clean();
        
        if ($this->auto_replace_vars) {
            // replace vars like {$user.username}
            $replace_array = self::create_replace_array($data_array);
            $contents = str_replace($replace_array['in'], $replace_array['out'], $contents);
            // remove {$var1}, {$var2}, .. if not supplied in $data_array
            $contents = preg_replace('/\{\$[a-z_0-9\.]+\}/i', '', $contents); 
        }
        
        echo $contents;
        
    } // function get_view


    /**
     * Creates a complete url and attaches the querystring parameters to it.
     * This method is usually called from a view. Example:
     *
     * <code>
     * <a href='<?= $this->get_url("organization_id={$listed_organization->id}") ?>
     *   <?= get_string('edit', 'compass') ?>
     * </a>
     * </code>
     *
     * @param  string $parameter_string  Querystring parameters
     * @return string                    Returns a complete url
     */
    function get_url($parameter_string = '') {
        return $this->base_url . $this->create_querystring($parameter_string);
    } // function get_url


    /**
     * Returns an html string containing a hyperlink. Uses the current model to derive the controller name, 
     * unless a controller is specified in the parameter_string argument.
     *
     * Example:
     *
     * <?= $this->link_to('drives_match', get_string('drives_match', 'yourmod'), "job_id={$job->id}") ?>
     *
     * Returns:
     *
     * <a href="/mod/yourmod/index.php?id=64&action=drives_match&job_id=152&controller=job">Hyperdrive Signature Matches</a>
     *
     * @param  string $action            Name of the action to link to
     * @param  string $label             Text to display in the link
     * @param  string $parameter_string  Querystring parameters
     * @return string                    Returns a complete hyperlink
     */
    function link_to($action, $label, $parameter_string = '') {
        $parameter_string = ($parameter_string == '') ? "action=$action" : "action=$action&$parameter_string";               
        return "<a href='{$this->get_url($parameter_string)}'>$label</a>";
    } // function link_to



    /**
     * Creates a querystring.
     * If you omit the parameters string, the querystring will consist of just the id for the module.
     *
     * @param  string $parameter_string  Querystring parameters (optional)
     * @return string                    Returns a querystring, including the '?' prefix
     */
    function create_querystring($parameter_string = '') {
        global $id;
        $postfix =  (strpos($parameter_string, 'controller') === false) ? "&controller={$this->model_name}" : "";
        $parameter_string = ($parameter_string == '') ? '' : "&$parameter_string";               
        return "?id=$id{$parameter_string}{$postfix}";
    } // function create_querystring


    /**
     * Creates a url pointing to the index file of the current module.
     *
     * @return string                    Returns a url
     */
    function create_base_url() {
        global $CFG;
        return "{$CFG->wwwroot}/{$this->plugin_type}/{$this->mod_name}/index.php";
    } // function create_base_url 


    function start_url() {
        global $id;
        return $this->create_base_url() . "?id=$id";
    } // function start_url


    /**
     * Creates a series of hidden input fields for inclusion in a form.
     * The id parameter is the Moodle module's instance id.
     *
     *
     * @param  array  $parameters  Associative array to be transformed in hidden input fields
     * @return string              Returns a string of html hidden input fields
     */
    function create_hidden_fields($parameters, $prefix = '', $no_id = false) {
        global $id;
        $id_prefix = str_replace(']', '_', str_replace('[', '_', $prefix) );
        $inputs = array();
        $parameters = $this->remove_collections_from_parameters($parameters);
        foreach($parameters as $key => $value) {
            $html_id = ($no_id) ?  "" : "id='{$id_prefix}$key'";
            $key = ($prefix == '') ? $key : "[$key]";
            $inputs[] = "<input type='hidden' value='$value' name='{$prefix}$key' $html_id />";
        }
        return join("\n", $inputs);
    } // function create_hidden_fields


    /**
     * Creates a call to a javascript function post_to_url.
     * This method is typically called to create the javascript code for an 'onclick' action of a hyperlink.
     *
     * Example:
     * <code>
     * <a onclick="<?= $this->post_to_url_js(
     *                   array('organization_id' => $organization->id)
     *                                      ) ?>" 
     *    href="#">Delete</a>
     * </code>
     *
     * Clicking the link will result in a dynamically constructed form being submitted (through javascript, obviously).
     *
     * @param  array  $parameters  Associative array to be transformed in hidden input fields
     * @param  string $action      Controller method to be called - defaults to 'delete' (optional)
     * @return string              Returns a string containing the javascript call
     */
    function post_to_url_js($parameters, $action = 'delete') {
        global $id;
        $quoted_parameters = array();
        foreach($parameters as $key => $value) {
            $quoted_parameters[] = "'$key': '$value'";
        }
        $parameters_string = (count($quoted_parameters)) ? join(',', $quoted_parameters) . ',' : '';
        return "post_to_url('$this->base_url',
                { $parameters_string 'controller': '$this->model_name', 'id': '$id', 'action': '$action' })"; 
    } // function post_to_url


    /**
     * Returns the base url for this controller.
     * Upon instantiating the controller, the constructor method calls create_base_url on the controller and assigns
     * the result to $this->base_url.
     * The base url is the url pointing to the index file of the current module.
     *
     * @return string Returns the base url for this controller.
     */
    function base_url() {
        return $this->base_url;        
    } // function base_url
    

    public function set_page_info($title, $nav_title, $nav_link) {
        $this->_page_title = $title;
        $this->_nav_title = $nav_title;
        $this->_nav_link = $nav_link;
    }


    static function get_limitfrom($page_number, $page_size) {
        return ($page_size * ($page_number - 1));
    } // function get_limitfrom


    static function max_page_size($page_size, $total_records) {
        return ceil($total_records / $page_size);
    } // function get_max_page_size


     /**
     * Returns true if the currently logged in user is admin.
     *
     * @return boolean Returns true if admin, otherwise false
     */   
    static function is_admin() {
        return has_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));        
    } // function is_admin
} // class controller

?>

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
    var $redirect = false;
    var $user;
    protected $_moodle_header = '';


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
    function __construct($mod_name, $mod_instance_id, $action = false) {
        global $USER, $course, $compass;
        $this->course = $course;
        $this->mod_name = $mod_name;
        $this->model_name = static::model_name(get_called_class()); 
        $this->base_url = $this->create_base_url();
        $instance_id_name = "{$this->mod_name}_id";
        $this->action = ($action) ? $action : optional_param('action', 'index', PARAM_RAW);
        $this->{$instance_id_name} = $mod_instance_id;

        if (isset($USER) && ($USER->id != 0)) $this->user = $USER;

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
    static function load_file($controller_name, $mod_name) {
        global $CFG;
        if (!file_exists("{$CFG->dirroot}/mod/$mod_name/controllers/{$controller_name}.php")) return false;
        return include_once("{$CFG->dirroot}/mod/$mod_name/controllers/$controller_name.php");
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
        return static::load_file($controller_name, $this->mod_name);
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
    function set_helpers($helpers) {
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
        echo "Please overwrite the Soda controller method 'index' with your own method.";
    } // function index


    /**
     * Wrapper function for Moodle's has_capability function.
     * Calls has_capability with the current context and the currently logged in user, for the module where the controller resides.
     *
     * @param  string       $capibility_short Name of the capability to check, defaults to 'edit' (optional)
     * @param  array        $user             User to check capability for, defaults to currently logged in user (optional)
     * @return boolean                        Returns true if user has capability, otherwise false
     */
    function check_capability($capability_short = 'edit', $model_name = false, $user = false) {
        global $context, $USER;
        if (!$user) $user = $USER;
        $model_name = ($model_name !== false ) ? $model_name : $this->model_name;
        return has_capability("mod/{$this->mod_name}:$capability_short{$model_name}", $context, $user->id); 
    } // function check_capability


    /**
     * Wrapper function for Moodle's require_login
     *
     * @return void
     */
    function require_login() {
        require_login();
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

	
    /**
     * Prints a message from the session object.
     * Typically used to provide the user with feedback about what happened before a redirect, e.g. to
     * display the message "Organization addresss saved", after saving an object of class address.
     *
     * @param  string  $key    Key of the message to print
     * @parem  boolean $delete If delete is true (the default value), the message will be deleted from the session object (optional)
     * @return void
     */
	function print_message($message, $delete = true) {
		if (!isset($_SESSION['messages'][$message])) return false;
		echo $_SESSION['messages'][$message];
		if ($delete) unset($_SESSION['messages'][$message]);
	} // function print_message


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
     * @param array  $parameters    Optional: Key - value pairs specifying the parameter its content
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
        $this->redirect = true;
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
        global $cm, $course, $CFG, $OUTPUT, $PAGE;

        $PAGE->set_url("/mod/$mod_name/index.php", array('id' => $cm->id, 'action' => $this->action, 'controller' => optional_param('controller', $mod_name, PARAM_RAW) ));
        $PAGE->set_pagelayout('admin');

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
        $this->_moodle_header = $header;
    } // function get_header

	public function get_moodle_header() {
		return $this->_moodle_header;
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
     * @return void
     */
    function get_view($data_array = array(), $view = false) {
        global $CFG, $id;
        
        // hack to make Moodle populate the $OUTPUT variable correctly (instead of a bootstrap_renderer)
        $this->_prepare_moodle_header($this->mod_name);

        foreach($data_array as $variable_name => $value) {
            $$variable_name = $value;
        }
        //include correct view
        $trace = debug_backtrace();
        $this->view = ($view) ? $view : $trace[1]['function'];
        $view_path = "{$CFG->dirroot}/mod/{$this->mod_name}/views/{$this->model_name}/{$this->view}.html";
        if (! file_exists($view_path)) {
            $parent_views = static::model_name(get_parent_class($this));
            $view_path = "{$CFG->dirroot}/mod/{$this->mod_name}/views/{$parent_views}/{$this->view}.html";
        }
        $template_path = "{$CFG->dirroot}/mod/$this->mod_name/views/template.html";
        if (file_exists($template_path)) {
            include_once($template_path);
            return;
        }
        include_once($view_path);
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
        return "{$CFG->wwwroot}/mod/{$this->mod_name}/index.php";
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

} // class controller

?>

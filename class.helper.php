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

/**
 * All methods from the Soda helper class can used in the context of a controller
 * and a view. In addition, you can specify your own helper class. Each of the methods
 * you specify there will also be made available in your own controller.
 * @package Soda
 */
class helper {


    /**
     * Reroutes method calls to non-existing methods to controller methods.
     * Throws an exception if the called method does not exist as a controller method either.
     * Please note that this behavior is symmetrical: the Soda controller class reroutes calls to unknown
     * methods to the helper class.
     *
     * @param  string       $method Name of the method to call dynamically
     * @param  array        $args   Array of arguments with the method
     * @return mixed        Returns whatever the called method returns
     */
    function __call($method, $args) {    

        if (method_exists($this->controller, $method)) {
            return call_user_func_array(
                array($this->controller, $method),
                $args
            );
        }
        foreach($this->controller->helpers as $helper) {
            if (!method_exists($helper, $method)) continue;
            return call_user_func_array(
                array($helper, $method),
                $args
            );

        }
        throw new Exception("Unknown method [$method]");
    } // function __call


    /**
     * Reroutes method calls to non-existing properties to controller properties.
     * Throws an exception if the called property does not exist as a controller property either.
     * Please note that this behavior does not display the same symmetry as __call. There is no corresponding
     * method in the controller class.
     *
     * @param  string       $method Name of the property to call dynamically
     * @param  array        $args   Array of arguments with the property
     * @return mixed        Returns whatever the called property returns
     */
    function __get($property) {
        if (property_exists($this->controller, $property)) {
            return $this->controller->$property;
        }
        $class = get_class($this->controller);
        if (property_exists($class, $property)) {
            return $class::$property;
        }
        throw new Exception("Unknown property [$property]");
    } // function __get


    /**
     * Prints opening form tag and hidden form fields to specify the destination of a form post.
     * If you do not specify the action parameter, the form post will be handled by the default 'save' method of
     * the current controller.
     * You can also specify additional parameters to change the default behavior of the form post. Example:
     *
     * <code>
     * $this->form_open(false, array('controller' => 'some_alternative_controller'));
     * </code>
     *
     * @param  string       $action     Controller method to handle the form post, defaults to 'save' (optional)
     * @param  array        $parameters Array of variables to submit through hidden form fields (optional)
     * @return void
     */
    function form_open($action = 'save', $parameters = array()) {
        $this->form_tag();
        if (!array_key_exists('action', $parameters)) $parameters['action'] = $action;
        echo $this->form_parameters($parameters);
    } // function form


    /**
     * Prints opening form tag.
     *
     * @return void
     */
    function form_tag() {
        echo "<form action='$this->base_url' method='post' name='{$this->model_name}' class='mform'>";
    } // function form_tag


    /**
     * Returns path to a partial view.
     * A partial is a part of a view, typically reused in multiple places within the same view, or shared by multiple views.
     * The method will first lookup the most specific path, i.e. by looking in the views directory for the current model.
     * If the partial cannot be found there, the method will lookup the views/shared path.
     *
     * @param   string  $partial    Name of the partial
     * @return                      Returns string
     */
    function get_partial_path($partial) {
        global $CFG, $soda_module_name;

        if (file_exists("{$CFG->dirroot}/mod/$soda_module_name/views/{$this->model_name}/_{$partial}.html")) {
            return "{$CFG->dirroot}/mod/$soda_module_name/views/{$this->model_name}/_{$partial}.html";
        }
        return "{$CFG->dirroot}/mod/$soda_module_name/views/shared/_{$partial}.html";
    } // function get_partial_path

} // class helper

?>

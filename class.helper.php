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
     * If you do not specify the action as part of the post parameters, the form post will be 
     * handled by the default 'save' method of the current controller.
     * You can also specify additional post parameters to change the default behavior of the form post. Example:
     *
     * <code>
     * $this->form_open(false, $post_parameters = array('controller' => 'some_alternative_controller'));
     * </code>
     *
     * The third argument lets you overwrite the default html attributes of the form tag. The defaults are:
     * - action: $this->base_url
     * - method: 'post'
     * - name:   $this->model_name
     * - class:  'mform'
     *
     * @param  string       $action          Controller method to handle the form post, defaults to 'save' (optional)
     * @param  array        $post_parameters Array of variables to submit through hidden form fields (optional)
     * @param  array        $html_attributes Array of attributes to include in form tag (optional)
     * @return void
     */
    function form_open($action = 'save', $post_parameters = array(), $html_attributes = array() ) {
        global $id;
        $this->form_tag($html_attributes);
        if (! array_key_exists('action', $post_parameters)) $post_parameters['action'] = $action;
        if (! array_key_exists('controller', $post_parameters) ) $post_parameters['controller'] = $this->model_name;
        if (! array_key_exists('id', $post_parameters) ) $post_parameters['id'] = $id;
        echo $this->create_hidden_fields($post_parameters);
    } // function form


    /**
     * Prints opening and closing form tags, followed by javascript to do an 
     * ajax post of the form.
     * You should specify the body of the form by calling this function with an 
     * anonymous function as the final argument ($displayer), which contains
     * the actual html code to display.
     *
     * Example:
     *
     * <code>
     * <h1>Items</h1>
     * <ul id='todotwo_list'>
     * <? 
     * foreach($items as $item) { 
     *     include($this->get_partial_path('single_item'));
     * } ?>
     * </ul>
     * <?= $this->ajax_form(array('action' => $this->get_url('action=test')), 'append', '#todotwo_list', function() { ?>
     *     <p>title: <input type="text" name="item[title]" value="" /><input type="submit" name="submit" value="Add"/></p>
     * <? }); ?>
     * </code>
     *
     * In this example, each 'title' is added to the unordered list as a list item (we are not showing
     * the server side code for handling the ajax call here).
     *
     * @param  array        $html_attributes Array of attributes to include in form tag (optional)
     * @param  string       $js_validate     Name of the javascript validation function (optional) - cancels submit if it returns false.
     *                                       The function is called with the trigger (i.e. the form) as its argument.
     * @param  string       $js_callback     Name of the javascript callback function to be called upon 'success'
     *                                       Please note that the original trigger object (i.e. the form) is made available in the variable 'trigger'
     * @param  string       $target          Target of the callback function (optional). When present, it is the 3rd argument in the callback function. Defaults to false.
     * @param  function     $displayer       Anonymous function containing the code for displaying the body of the form        
     * @return void
     */
    function ajax_form($html_attributes = array(), $js_validate = false, $js_callback, $target = false, $displayer) {
        global $id;
        if (! array_key_exists('id', $html_attributes) ) $html_attributes['id'] = "{$this->mod_name}_$id";
        echo $this->form_tag($html_attributes);
        echo $displayer();
        echo "</form>";
        $validate = ($js_validate) ? "if (! $js_validate(trigger)) return false;" : "";
        $target = ($target) ? ", '$target'" : "";
        $callback = "function(data) {{$js_callback}(data, trigger{$target});}";
        echo "<script type='text/javascript'>
                  $(document).ready(
                      function() {
                          //console.log('document ready!');
                          $('#{$html_attributes['id']}').submit(function() {
                              //console.log('submit detected');
                              var trigger = this;
                              $validate
                              $.post(
                                  $('#{$html_attributes['id']}').attr('action'),
                                  $('#{$html_attributes['id']}').serialize(),
                                  $callback
                              );
                              return false;
                          });
                      }
                  );
              </script>";
    } // function ajax_form


    /**
     * Converts all specified elements inside a container into ajax links.
     * Clicking an ajax link results in a post to a specified url.
     *
     * By default, only 'a' elements are turned into ajax links. The post
     * url is derived from the href attributes.
     *
     * Example:
     * <code>
     * <div id='todotwo_container'>
     *   <ul id='todotwo_list'>
     *      <li>My First Item <a href='?id=23&action=delete&controller=item&item_id=598'>delete</a></li>
     *   </ul>
     * </div>
     * <?= $this->ajax_link($container_id = 'todotwo_container', "a.delete", "remove_item"); ?>
     * </code>
     * 
     * This code will turn all hyperlinks inside 'todotwo_container' into ajax links.
     * If a user clicks the link, the 'li' item will be deleted.
     * 
     *
     * @param   string       $container_id    Dom id of the element which contains the links
     * @param   string       $selector        jQuery type selector of the elements to be converted into ajax links (optional) default: 'a'
     * @param   string       $js_callback     Name of javascript callback function which is executed upon completion of the ajax request (optional)
     *                                        The callback function is executed with 'data' and 'trigger' respectively as its parameters.
     *                                        If argument $target is present, then the javascript callback function is called with this arugment as well.
     * @param   string       $url             Post url, i.e. destination of the ajax request (optional), defaults to javascript 'this.href' 
     *                                        (which means: the href attribute of the event trigger)
     * @param   string       $event_type      Event which should trigger the ajax request (optional), defaults to 'click'
     * @param   string       $return          Value returned to event trigger (optional), defaults to javascript 'false'
     * @param   string       $target          Target of the callback function (optional). When present, it is the 3rd argument in the callback function. Defaults to false.
     * @return  void
     */
    function ajax_link($container_id, $selector = 'a', $js_callback = false, $url = false, $event_type = 'click', $return = false, $target = false) {
        $url = ($url) ? "'$url'" : "this.href";
        $target = ($target) ? ", '$target'" : "";
        $success = ($js_callback) ? ", success: function(data) {{$js_callback}(data, trigger$target);}" : "";
        $return_value = ($return) ? "true" : "false";
        echo "<script type='text/javascript'>
                  $(document).ready(
                      function () {
                          $('#$container_id').on('$event_type', '$selector', function() {
                              var trigger = this;
                              $.ajax({
                                  type: 'POST',
                                  url: $url
                                  $success
                              });
                              return $return_value;
                          });
                      }
                  );
              </script>";
    } // function ajax_link


    /**
     * Prints opening form tag.
     * 
     * The optional argument lets you overwrite the default html attributes of the form tag. The defaults are:
     * - action: $this->base_url
     * - method: 'post'
     * - name:   $this->model_name
     * - class:  'mform'
     *
     * @param  array        $html_attributes Array of attributes to include in form tag (optional)
     * @return void
     */
    function form_tag( $html_attributes = array() ) {
        if (!array_key_exists('action', $html_attributes)) $html_attributes['action'] = $this->base_url;
        if (!array_key_exists('method', $html_attributes)) $html_attributes['method'] = 'post';
        if (!array_key_exists('name', $html_attributes)) $html_attributes['name'] = $this->model_name;
        if (!array_key_exists('class', $html_attributes)) $html_attributes['class'] = 'mform';
        echo "<form {$this->create_html_attributes($html_attributes)}>";
    } // function form_tag


    function create_html_attributes($attributes) {
        if (! is_array($attributes) || !count($attributes) ) return "";
        $pairs = array();
        foreach($attributes as $name => $value) {
            $pairs[] = "{$name}='{$value}'";
        }
        return join(' ', $pairs);
    } // function create_html_attributes


    /**
     * Returns path to a partial view.
     * A partial is a part of a view, typically reused in multiple places within the same view, or shared by multiple views.
     * The method will first lookup the most specific path, i.e. by looking in the views directory for the current model.
     * If the partial cannot be found there, the method will lookup the views path of the related parent controller. The final
     * place to search is the views/shared path.
     *
     * If the partial name contains a '/' it is assumed that full path is already specified.
     *
     * @param   string  $partial    Name of the partial
     * @return                      Returns string
     */
    function get_partial_path($partial) {
        global $CFG, $soda_module_name;
        $plugin_type = $this->controller->plugin_type;

        if (strpos($partial, '/') !== false) {
            if (file_exists($partial)) {
                return $partial;
            }
        }
        if (file_exists("{$CFG->dirroot}/$plugin_type/$soda_module_name/views/{$this->model_name}/_{$partial}.html")) {
            return "{$CFG->dirroot}/$plugin_type/$soda_module_name/views/{$this->model_name}/_{$partial}.html";
        }
        $parent_views = static::model_name(get_parent_class($this->controller));
        if (file_exists("{$CFG->dirroot}/$plugin_type/$soda_module_name/views/{$parent_views}/_{$partial}.html")) {
            return "{$CFG->dirroot}/$plugin_type/$soda_module_name/views/{$parent_views}/_{$partial}.html";
        }
        return "{$CFG->dirroot}/$plugin_type/$soda_module_name/views/shared/_{$partial}.html";
    } // function get_partial_path



} // class helper

?>

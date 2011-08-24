<?php
//require_once("../../config.php");
require_once("lib.php");

class soda_constructor {

    var $user;
    var $user_id;
    var $cm_id;
    var $instance_id;
    var $post_operation = false;
    var $print_button;

    function __construct($module_name, $module_inhabitant = false, $print_button = true) {
        global $mode;
        $mode = optional_param('mode', 'normal', PARAM_RAW);
        $operation = optional_param('operation', false, PARAM_RAW);
        $id_postfix = ($operation) ? "-{$operation}" : "";
        page_id_and_class($pageid, $pageclass);
        $this->print_button = $print_button;
        $this->initialize_module($module_name, $module_inhabitant);
        echo "<div id='$pageid$id_postfix' style='text-align: left' class='compass_mode_$mode'>";
        // Temp
        echo "<style>
                .compass_helptext {
                    display:none;
                }

                .compass_helplink:hover .compass_helptext {
                    display:block;
                }

              </style>";
    } // function __construct


    function initialize_module($module_name, $module_inhabitant = false) {
        global $id, ${$module_name}, ${$module_name . '_id'}, $action, $course, $cm, $USER, $student;
        $id = required_param('id', PARAM_INT); 
        $student_id = optional_param('student_id', false, PARAM_INT); 
        ${$module_name . '_id'} = optional_param($module_name . '_id', false, PARAM_INT);
            
        $function_name = "{$module_name}_set_variables";
        $function_name($module_name);
        require_course_login($course, false, $cm);

        $function_name = $module_name . '_get_' . $module_name;
        if (! function_exists($function_name)) error("Function $function_name does not exist.");
        if (! (${$module_name} = $function_name($cm->instance)) ) {
            error("Course module is incorrect. Function call: $function_name({$cm->instance}) and ${$module_name} = " . print_object(${$module_name}));
        }

        $this->operation = optional_param('operation', false, PARAM_RAW);
        $this->instance_id = ${$module_name}->id;
        $this->cm_id = $id;
        $student = (($student_id) && ($student_id != $USER->id)) ? get_record('user', 'id', $student_id) : false;
        $this->user_id = ($student_id) ? $student_id : $USER->id;

        if ($module_inhabitant) {
            $module_inhabitant->operation = $this->operation;
            $module_inhabitant->instance_id = $this->instance_id;
            $module_inhabitant->cm_id = $this->cm_id;
            $module_inhabitant->user = $this->user;            
            $module_inhabitant->user_id = $this->user_id;
            if (isset($this->previous_page)) $module_inhabitant->previous_page = $this->previous_page;
            $module_inhabitant->soda = $this;
        }

        $str_mod_name_singular = get_string('modulename', $module_name);

        $navigation = build_navigation( get_string('modulename', $module_name) );
        print_header_simple(format_string(${$module_name}->name), "", $navigation, "", "", true,
                      update_module_button($cm->id, $course->id, $str_mod_name_singular), navmenu($course, $cm));
    } // function initialize_module


    function set_variables($object) {
        $object->operation = $this->operation;
        if ($this->post_operation) $object->post_operation = $this->post_operation;
        $object->instance_id = $this->instance_id;
        $object->cm_id = $this->cm_id;
        $object->user = $this->user;            
        $object->user_id = $this->user_id;
        if (isset($this->previous_page)) $object->previous_page = $this->previous_page;
    } // function set_variables


    // wrapper
    function close() {
        $this->close_module();
    } // function close


    function close_module() {
        global $course;
        // Dirty hack: this print button does not belong here, but this is a nice centralized place to put it.
        if ($this->print_button) {
            echo "<script type='text/javascript'>
                    function compass_print() {
                        if ( (typeof message === 'undefined') || (message === '') ){
                            if (typeof compass_customized_print_function !== 'undefined') return compass_customized_print_function();
                            window.print();
                            return;
                        }
                        alert(message);
                    }
                  </script>";
            echo "<div class='compass_form_footer' id='compass_footer'>
                    <!--
                    <div class='compass_form_buttons'>
                      <input type='button' value='Vorige' name='previous'/>
                      <input type='button' value='Volgende' name='next'/>
                      <input type='button' value='Opslaan' name='store'/>
                    </div>
                    -->
                    <div class='compass_print'>
                      <input onclick='compass_print();' type='button' id='compass_print_button' value='Printen'/>
                    </div>
                  </div>";
        }
        echo "</div><!-- class='mode_mijndossier' || class='mode_nomode' -->"; // class='mode_mijndossier' || class='mode_nomode'
        print_footer($course);
    } // function close_module

} // class soda_constructor
?>

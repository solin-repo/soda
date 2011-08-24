<?php

class starter {

    static function create_mod_library_functions($mod_name) {

        foreach(get_class_methods('soda') as $base_method) {
            $r = new ReflectionMethod('soda', $base_method);
            $params = $r->getParameters();
            $param_names = array();
            foreach ($params as $param) {
                $param_names[] = '$' . $param->getName();
            }
            $arguments = (count($param_names)) ? join(', ', $param_names) : '';
            $function_name = $mod_name . '_' . $base_method;
            if (! function_exists($function_name)) {
                $str = "function $function_name($arguments) { $mod_name::$base_method($arguments); }";
                // Better close your eyes now...
                eval($str);
            }
        }
        // special case
        if (! function_exists($mod_name . '_get_' . $mod_name) ) {
            eval("function $mod_name . '_get_' . $mod_name($arguments) { $mod_name::get_mod_by_id($mod_id); }");
        }
    } // function create_mod_library_functions
} // class starter 


class soda {
    static function add_instance($mod_instance) {}
    static function update_instance($mod_instance) {}
    static function delete_instance($id) {}
    static function user_outline($course, $user, $mod, $mod_instance) {}
    static function user_complete($course, $user, $mod, $planner) {}
    static function print_recent_activity($course, $isteacher, $timestart) {}
    static function cron() {}
    static function grades($mod_id) {}
    static function get_participants($mod_id) {}
    static function scale_used($mod_id, $scale_id) {}
    static function get_navigation($test) {echo 'Testing 1, 2, 3 and $test = ' . $test;}
    static function get_module_instance() {}
    static function get_mod_by_id($mod_id) {
        $mod_name = get_class();
        echo 'Testing 1, 2, 3 and $mod_id = ' . $mod_id;
    }
    static function set_variables($mod_nam) {}
} // class soda 


class planner extends soda {
    function __construct() {
        starter::create_mod_library_functions('planner');
    } // function __construct
} // class planner 

?>

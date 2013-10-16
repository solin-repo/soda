<?php

class widget {


    /**
     * This function loads a html 'partial' with the same name as the class.
     * E.g.: if the widget class is called 'user_selector', then the display function
     * will return a html file with the same name.
     *
     * The html file is treated as a mini template. The contents of the single argument 
     * with the display function, an array, will be accessible as variables in the html
     * document.
     *
     * @param  string $mod_name      Name of the module where the widget resides
     * @param  array  $data_array    Associative array with variable names pointing to corresponding values (optional)
     * @param  string $partial_name  Name of the partial that should be loaded (optional). If omitted, the name of the calling 
     *                               class is used.
     * @return string The processed html file is returned as a string
     */
    static function display($mod_name, $data_array = array(), $partial_name = false, $plugin_type = 'mod') {
        global $CFG;
        $partial_filename = ($p = $partial_name) ? $p : get_called_class(); 

        foreach($data_array as $variable_name => $value) {
            $$variable_name = $value;
        }
        ob_start();
        include_once(self::get_path($partial_name, $mod_name, $plugin_type));
        $contents = ob_get_clean();
        return $contents;
    } // function display


    static function get_path($partial_name, $mod_name, $plugin_type = 'mod') {
        global $CFG;
        $path = "{$CFG->dirroot}/$plugin_type/$mod_name/widgets/{$partial_name}.html";
        if (!file_exists($path)) error("could not find path $path");
        return $path;
    } // function get_path

} // class widget 

?>

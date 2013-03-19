<?php

class widget {

    static $partial_filename;

    /**
     * This function loads a html 'partial' with the same name as the class.
     * E.g.: if the widget class is called 'user_selector', then the display function
     * will return a html file with the same name.
     *
     * The html file is treated as a mini template. The contents of the single argument 
     * with the display function, an array, will be accessible as variables in the html
     * document.
     *
     * @param  array  $variables  An array of key value pairs. Each key will be made available
     *                            as a variable with the same name in the html partial.
     * @return string The processed html file is returned as a string
     */
    static function display($variables) {
        self::$partial_filename = get_called_class() . '.html'; 
    } // function display

} // class widget 

?>

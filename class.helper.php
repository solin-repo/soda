<?php

class helper {


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


    function __get($property) {
        if (!property_exists($this->controller, $property)) {
            throw new Exception("Unknown property [$property]");
        }
        return $this->controller->$property;
                
    } // function __get

} // class helper 

?>

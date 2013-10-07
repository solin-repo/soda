<?php
include_once("{$CFG->dirroot}/local/soda/class.controller.php");

class template_controller extends controller {

    function index() {
        $this->get_view();
    } // function index


    function show() {
    } // function show


    function edit() {
    } // function edit


    function delete() {
    } // function delete


    function create() {
        // To use the 'flash' message, provide a 3rd argument to redirect_to, containing the actual message:
        // $this->redirect_to( 'index', array('saved' => 1), array('notification' => get_string('message_saved', 'template')));
    } // function create

} // class template_controller 

?>

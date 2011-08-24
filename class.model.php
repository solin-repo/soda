<?php

class model {

    var $validation_rules = array();

    function __construct($properties) {
        foreach($properties as $key => $value) {
            $this->$key = $value;
        }               
        $this->define_validation_rules();
    } // function __construct


    function define_validation_rules() {
    } // function define_validation_rules


    function add_rule($field, $message, $rule_code) {
        $this->validation_rules[] = array('field' => $field, 'message' => $message, 'code' => $rule_code);
    } // function add_rule


    function get_properties() {
        $ref = new ReflectionObject($this);
        $properties = $ref->getProperties(ReflectionProperty::IS_PUBLIC);
        $result = array();
        foreach ($properties as $property) {
            false && $property = new ReflectionProperty();
            $result[$property->getName()] = $property->getValue($this);
        }               
        return $result;
    } // function get_properties


    function validate() {    
        // Turn properities into local variables. This is currently the only way to bring them into the scope 
        // of the validation code, because php 5.3 does not support closures over '$this'.
        foreach($this->get_properties() as $property => $value) {
            ${$property} = $value;
        }
        $valid = true;
        foreach($this->validation_rules as $rule) {
            $code = $rule['code'];
            if ( !$code(${$rule['field']}) ) {
                soda_error::add_error($rule['field'], $rule['message']);
                $valid = false;
            }
        }
        return $valid;
    } // function validate


    function delete() {
        $this->deleted = 1;
        if (! $this->id ) error("No record id found");
        return $this->save_without_validation($record_id);
    } // function delete


    function save($record_id = false) {
        if (!$this->validate()) return false;
        return $this->save_without_validation($record_id);
    } // function save


    function save_without_validation($record_id = false) {
        $this->timemodified = time();
        if ($record_id || (property_exists($this, 'id') && $this->id) ) {
            if ($record_id) $this->id = $record_id;
            if (!update_record($this->table_name, $this)) return false;
            return $this->id;
        }
        $this->timecreated = time();
        return $this->id = insert_record($this->table_name, $this);               
    } // function save_without_validation

} // class model 

?>

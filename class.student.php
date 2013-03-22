<?php
require_once("$CFG->dirroot/local/soda/class.user.php");

/////////////////////////////////////////////////////////////////////////////
//                                                                         //
// NOTICE OF COPYRIGHT                                                     //
//                                                                         //
// Moodle - Soda Module Constructor and MVC Framework                      //
//                                                                         //
// Copyright (C) 2013 Solin - www.solin.eu                                 //
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

class student extends user {

    static $table_name = 'user';

    /**
     * Returns users who only have the 'student' role in a specific course.
     *
     * @param   int     $course_id  id of the course for which to return the student users
     * @return  array   returns array of users
     */
    static function load_by_course($course_id, $where = false) {
        $where_clause = ($where) ? " AND $where " : '';
        return student::base_load(
            "SELECT u.*
             FROM {user} AS u
             INNER JOIN {role_assignments} ra ON ra.userid = u.id
             INNER JOIN {context} c ON c.id = ra.contextid AND c.contextlevel = '50' AND c.instanceid = :course_id
             INNER JOIN {role} r ON ra.roleid = r.id
             WHERE r.shortname = 'student' AND NOT EXISTS 
                (SELECT ra2.userid 
                 FROM {role_assignments} AS ra2, {role} AS r2 
                 WHERE ra2.userid = u.id AND ra2.roleid = r2.id AND r2.shortname NOT LIKE 'student')
             $where_clause",
            array("course_id" => $course_id) ); 
    } // function load_by_course

} // class student 
	
?>

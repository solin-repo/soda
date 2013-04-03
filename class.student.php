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
        // 'NOT EXISTS' subselect is necessary because a teacher may also have a student role (even in the same course)
        return student::base_load(
            "SELECT u.*
             FROM {user} AS u
             INNER JOIN {role_assignments} ra ON ra.userid = u.id
             INNER JOIN {context} c ON c.id = ra.contextid AND c.contextlevel = '50' AND c.instanceid = ?
             INNER JOIN {role} r ON ra.roleid = r.id
             WHERE r.shortname = 'student' AND u.deleted = 0 AND NOT EXISTS 
                 (SELECT u2.*
                  FROM {user} AS u2
                  INNER JOIN {role_assignments} ra2 ON ra2.userid = u2.id
                  INNER JOIN {context} c2 ON c2.id = ra2.contextid AND c2.contextlevel = '50' AND c2.instanceid = ?
                  INNER JOIN {role} r2 ON ra2.roleid = r2.id
                  WHERE r2.shortname NOT LIKE 'student' AND u2.id = u.id)
             $where_clause",
            array($course_id, $course_id ) ); 
        /*
                (SELECT ra2.userid 
                 FROM {role_assignments} AS ra2, {role} AS r2,  
                 WHERE ra2.userid = u.id AND ra2.roleid = r2.id AND r2.shortname NOT LIKE 'student')
         */
    } // function load_by_course




} // class student 
	
?>

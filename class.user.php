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

class user extends model {

    public static $table_name = 'user';
	
    /* Menno de Ridder, 24-11-2011
     *
     * This function creates a random password of
     * by default 8 characters.
     *
     * It returns an array with both the unhashed
     * password as the hashed password.
     * @param  int  $length  Length of the password (default = 8)
     * @param  string  $allowed_characters  Characters that shall be allowed in the password (default = 1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ)
     * @return  array(password => hashed_password)
     */
	function create_password($length = 8, $allowed_characters = "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ") {

		$password = '';
		
		for($i = 0; $i < $length; $i ++)
		{
			$char = substr($allowed_characters, mt_rand(0, strlen($allowed_characters)-1), 1);
            if (!strstr($password, $char)) {
                $password .= $char;
            } else {
                $i -= 1;
            }
		}
		return array($password=>hash_internal_user_password($password));
	}


    function full_name() {
        return $this->firstname . ' ' . $this->lastname;
    } // function full_name


    /**
     * Loads users who have a specific capability in the context of a given course.
     */
    static function load_by_capability_and_course_id($capability, $course_id, $current_group_id = false, $order_columns = "u.firstname, u.lastname") {
         $group_sql = ((boolean) $current_group_id) ? sprintf(" u.id IN (SELECT gm.userid FROM {groups_members} AS gm WHERE gm.groupid = %d ) AND ", $current_group_id) : "";
         return self::base_load(
            "SELECT u.*
             FROM {user} AS u
             INNER JOIN {role_assignments} ra ON ra.userid = u.id
             INNER JOIN {context} c ON c.id = ra.contextid AND c.contextlevel = '50' AND c.instanceid = ?
             INNER JOIN {role_capabilities} AS rc ON rc.roleid = ra.roleid
             WHERE $group_sql rc.capability LIKE ? AND u.deleted = 0 AND NOT EXISTS
                 (SELECT u2.*
                  FROM {user} AS u2
                  INNER JOIN {role_assignments} ra2 ON ra2.userid = u2.id
                  INNER JOIN {context} c2 ON c2.id = ra2.contextid AND c2.contextlevel = '50' AND c2.instanceid = ?
                  INNER JOIN {role_capabilities} AS rc2 ON rc2.roleid = ra2.roleid
                  WHERE rc2.capability NOT LIKE ? AND u2.id = u.id AND rc2.roleid <> rc.roleid)
            ORDER BY $order_columns",
            array($course_id, $capability, $course_id, $capability) );                
    } // function load_by_capability_and_course_id 
}
?>

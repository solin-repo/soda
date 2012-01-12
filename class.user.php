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
//     Menno de Ridder
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
}
?>

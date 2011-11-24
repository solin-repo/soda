<?php
class user extends model {
	
    /* Menno de Ridder, 24-11-2011
     *
     * This function creates a random password of
     * by default 8 characters.
     *
     * It returns an array with both the unhashed
     * password as the hashed password.
     * @param  int  $length  Length of the password (default = 8)
     * @return  array(password => hashed_password)
     */
	function create_password($length = 8) {

		$allowed_characters = "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ";
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

<?php
class user extends model {
	
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
		return $password;
	}
}
?>

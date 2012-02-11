<?php
#require_once("../../config.php");

$output = shell_exec( " cp -R template ../../mod/template 2>&1 " );
echo $output

?>

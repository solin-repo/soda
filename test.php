#!/usr/bin/php
<?php
//require_once("../../config.php");

if ($argc < 2) {
    exit("Please specify a name for your module\n");
}

$mod_name = $argv[1];
$dir_dest = "../../mod/$mod_name";
echo shell_exec(" find $dir_dest -iname '*template*' -exec rename 's/template/$mod_name/i' {} + ");


?>

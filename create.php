#!/usr/bin/php
<?php
define('CLI_SCRIPT', true);
include("../../config.php");
include("class.soda.php");

if ($argc < 2) {
    exit("Please specify a name for your new module\n");
}

$mod_name = $argv[1];
$dir_dest = "../../mod/$mod_name";
if (! shell_exec( " mkdir $dir_dest 2>&1") == null ) exit("Module $mod_name already exists!\n");
echo shell_exec(" cp -r -a template/* $dir_dest 2>&1 " );
echo shell_exec(" find $dir_dest -iname '*template*' -exec rename 's/template/$mod_name/i' {} + ");
shell_exec(" find $dir_dest -exec sed -i -e 's/template/$mod_name/g' {} \; 2>&1 ");
shell_exec(" find $dir_dest -exec sed -i -e 's/Template/" . ucfirst($mod_name) . "/g' {} \; 2>&1 ");
?>

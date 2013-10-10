#!/usr/bin/php
<?php
define('CLI_SCRIPT', true);
include("../../config.php");
include("class.soda.php");

$skeleton_dirs = array('helpers', 'views');
$skeleton_files = array('models', 'controllers');

$parsed_args = soda::arguments($argv);
if (!count($parsed_args['arguments']) && !count($parsed_args['commands']) ) exit("Please specify a module name\n");
$mod_name = (count($parsed_args['arguments']) ) ?  $parsed_args['arguments'][0] : $parsed_args['commands'][0];
$dir_dest = "../../mod/$mod_name";
$date_version = date("Ymd") . "00";
if ( shell_exec( " mkdir $dir_dest 2>&1") == null ) {
    echo shell_exec(" cp -r -a template/* $dir_dest 2>&1 " );
    echo shell_exec(" find $dir_dest -iname '*template*' -exec rename 's/template/$mod_name/i' {} + ");
    shell_exec(" find $dir_dest -exec sed -i -e 's/template/$mod_name/g' {} \; 2>&1 ");
    shell_exec(" find $dir_dest -exec sed -i -e 's/Template/" . ucfirst($mod_name) . "/g' {} \; 2>&1 ");
    shell_exec(" find $dir_dest -exec sed -i -e 's/DATE_VERSION/$date_version/g' {} \; 2>&1 ");
}
if ( !empty($parsed_args['options']['skeleton']) ) {
    $model_name = $parsed_args['options']['skeleton'];
    foreach($skeleton_dirs as $dir) {
        $dir_dest = "../../mod/$mod_name/$dir/$model_name";
        shell_exec(" cp -r -a template/$dir/* $dir_dest 2>&1 " );
    }
    foreach($skeleton_files as $dir) {
        $file_dest = "../../mod/$mod_name/$dir/$model_name.php";
        shell_exec(" cp -a template/$dir/template.php $file_dest 2>&1 " );
    }
    $dir_dest = "../../mod/$mod_name";
    shell_exec(" find $dir_dest -exec sed -i -e 's/template/$model_name/g' {} \; 2>&1 ");
    shell_exec(" find $dir_dest -exec sed -i -e 's/Template/" . ucfirst($model_name) . "/g' {} \; 2>&1 ");
}
?>

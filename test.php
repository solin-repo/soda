#!/usr/bin/php
<?php
define('CLI_SCRIPT', true);
include("../../config.php");
include("class.soda.php");
$parsed_args = soda::arguments($argv);
print_r($parsed_args);

if (!count($parsed_args['arguments']) && !count($parsed_args['commands']) ) exit("Please specify a module name");
exit (0)

?>

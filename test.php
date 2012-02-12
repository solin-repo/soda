#!/usr/bin/php
<?php
define('CLI_SCRIPT', true);
include("../../config.php");
include("class.soda.php");
print_r(soda::arguments($argv));
exit (0)

?>

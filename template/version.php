<?php
  // example contents for version.php
  defined('MOODLE_INTERNAL') || die();

  $module->version   = DATE_VERSION;     // The current module version (Date: YYYYMMDDXX)
  $module->requires  = 2011112900;       // Requires this Moodle version
  $module->component = 'mod_template';   // Full name of the plugin (used for diagnostics)
  $module->cron      = 0;
?>

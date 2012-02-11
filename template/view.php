<?php
    require_once("../../config.php");
    $id = required_param('id', PARAM_INT);
    header("Location: {$CFG->wwwroot}/mod/template/index.php?id=$id");
    exit;
?>

<?php
require 'database.php';
$doc = $db->content_blocks->findOne();
print_r($doc);
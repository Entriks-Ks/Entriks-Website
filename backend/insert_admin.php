<?php
require "database.php";

$hash = password_hash("Entriks2025!", PASSWORD_DEFAULT);

$db->admins->insertOne([
    "email" => "admin@entriks.com",
    "password" => $hash,
    "created_at" => new MongoDB\BSON\UTCDateTime()
]);

echo "Admin user created!";
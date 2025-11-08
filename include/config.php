<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "emik";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn -> connect_error){
    die ("anda kurang beruntung : " .$conn -> connect_error );
}
?>
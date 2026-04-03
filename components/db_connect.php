<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "abed_idm_hub";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

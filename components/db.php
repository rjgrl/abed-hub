<?php
$conn = new mysqli("localhost", "root", "", "abed_hub_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

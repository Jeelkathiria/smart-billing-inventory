<?php 

$host = 'localhost';
$dbname = 'smart_billing';
$username = 'root';
$password = 'Jeel@9920';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "Connected successfully";
}


?>
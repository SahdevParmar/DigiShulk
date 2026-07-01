<?php
$servername="sql103.infinityfree.com";
$username="if0_42310664";
$password="dShulk05k9";
$dbname="if0_42310664_digishulk_db";
$conn=new mysqli($servername,$username,$password,$dbname);

if($conn->connect_error){
    die("Connection failed:". $conn->connect_error);
}
echo("Connected successfully");
?>
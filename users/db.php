<?php
	$server='localhost';
	$user='root';
	$password='';
	$db='armis';
	$conn=new mysqli($server,$user,$password,$db);
	if ($conn->connect_error) 
	{
		die($conn->connect_error);
	}
?>
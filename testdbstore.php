<?php
$db = new PDO("mysql:host=mysql.beta.ponniyinselvan.in;dbname=betaps", "vittal", "Vittal\$ms7002");
//$db = new PDO("mysql:host=mysql.beta.ponniyinselvan.in;dbname=psvp", "vittal", "Vittal\$ms7002");
$sql = 'SELECT message_no, UNCOMPRESS(original_source) original_source FROM ygrp_messages WHERE message_no = ?';
$stmt = $db->prepare($sql);
$stmt->bindParam(1, $argv[1], PDO::PARAM_INT);
if ($stmt->execute() === false) {
	echo "Error Code ".$stmt->errorCode()." ==> ".print_r($stmt->errorInfo(), true);
}
$result = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($result);
?>

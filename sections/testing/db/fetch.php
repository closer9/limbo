<?php
$result = $SQL->query ("SELECT * FROM auth_logins");

while ($fetch = $SQL->fetch_result ($result))
	{
	echo $fetch['reason'] . '<br>';
	
	$user = $SQL->fetch_row ("SELECT * FROM auth_users WHERE authid = 1");
	
	echo $user['username'] . '!<br>';
	}
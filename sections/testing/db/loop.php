<?php
while ($fetch = $SQL->loop ("SELECT * FROM auth_logins"))
	{
	echo $fetch['reason'] . '<br>';
	
	$user = $SQL->fetch_row ("SELECT * FROM auth_users WHERE authid = 1");
	
	echo $user['username'] . '!<br>';
	}

while ($fetch = $SQL->loop ("SELECT * FROM auth_sessions"))
	{
	echo $fetch['sessionid'] . '<br>';
	
	$user = $SQL->fetch_row ("SELECT * FROM auth_users WHERE authid = 1");
	
	echo $user['username'] . '!<br>';
	}
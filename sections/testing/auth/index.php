<?php
if (isset ($_GET['logout']))
	{
	$AUTH->logout (false);
	
	redirect ($path . 'index');
	}

if (limbo::request ()->method == 'POST')
	{
	if ($AUTH->auth ($_POST['username'], $_POST['password']) === true)
		{
		if ($AUTH->check_session () === true)
			{
			$AUTH->cookie_save ();
			
			limbo::flash_set ('error', 'Login successful!');
			}
		}
		else
		{
		limbo::flash_set ('error', $AUTH->errormsg);
		}
	
	redirect ($path);
	}
?>

<p class="textc">
	<?php
	if (! empty ($AUTH->username))
		echo "Hello {$AUTH->username}. ";
	
	if ($AUTH->check_session ())
		{
		echo "You are logged in!";
		}
		else
		{
		echo "You are not logged in.";
		}
	?>
</p>

<?php
if ($l->flash_get ('error'))
	echo '<p class="textc">' . implode ('<br>', limbo::flash_get ('error')) . '</p>';
?>

<br>

<form action="index" method="post">
	<p>Username: <input type="text" name="username" value="<?php echo @$AUTH->username?>"></p>
	<p>Password: <input type="password" name="password"></p>
	<p><input type="submit" value="Login"></p>
</form>

<?php
if ($AUTH->check_session ())
	{
	echo '<br><p><a href="?logout">Logout</a></p>';
	}
?>

<br>

<p class="bold">Heartbeat: <span id="heartbeat-status"></span></p>

<br>

<pre>
<?php
print_r ($AUTH);
?>
</pre>
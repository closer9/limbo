<?php
if (limbo::request ()->method == 'POST')
	{
	echo $_POST['name'] . ' - ' . $_POST['number'] . '<br>';
	}
?>

<form action="index" method="post">
	<p>Name: <input type="text" name="name"></p>
	<p>Number: <input type="text" name="number"></p>
	<p><input type="submit"></p>
</form>
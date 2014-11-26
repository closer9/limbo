<?php
if (limbo::request ()->method == 'POST')
	{
	foreach (limbo\util\storage::put ('test', $_FILES) as $key => $file)
		{
		if ($file)
			{
			echo 'Uploaded: ' . $file['path'] . $file['name'] . '<br>';
			}
		}
	}
?>

<div style="border: 1px solid gray; width: 400px" class="center">
	<form action="index" method="post" enctype="multipart/form-data">
		<p>Single: <input type="file" name="my_file" style="width: 300px;" /></p>
		<p><input type="submit"></p>
	</form>
</div>

<br>

<div style="border: 1px solid gray; width: 400px" class="center">
	<form action="index" method="post" enctype="multipart/form-data">
		<p>Double 1: <input type="file" name="files[]" style="width: 300px;" /></p>
		<p>Double 2: <input type="file" name="files[]" style="width: 300px;" /></p>
		<p><input type="submit"></p>
	</form>
</div>

<h3>Current files in storage:</h3>
<ul>
	<?php
	foreach (limbo\util\file::filelist (config ('path.storage') . "files/test/", false) as $file)
		{
		$file = substr ($file, 6);
		
		echo '<li><a href="' . $root . 'limbo-storage/test/' . $file . '">' . $file . '</a></li>';
		}
	?>
</ul>
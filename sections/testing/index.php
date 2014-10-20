<ul>
	<?php
	foreach (limbo\util\file::filelist (__DIR__) as $file)
		{
		if (preg_match ('/index.php$/i', $file))
			{
			$link = ltrim (substr ($file, 0, -9), '/');
			
			echo '<li><a href="' . $path . $link . '">' . $link . '</a></li>';
			}
		}
	?>
</ul>
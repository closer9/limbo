<!DOCTYPE html>

<html lang="en-US">

<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta http-equiv="content-language" content="en-US" />
	<meta http-equiv="default-style" content="text/css" />

	<meta name="MSSmartTagsPreventParsing" content="true" />
	<meta name="description" content="" />
	<meta name="keywords" content="" />
	<meta name="author" content="Scott McKee (closer9@gmail.com)" />

	<link rel="shortcut icon" type="image/x-icon" href="<?php echo $root?>images/favicon.ico" />

	<?php
	if (config ('web.css'))
		{
		foreach (config ('web.css') as $script)
			echo "\n\t<link rel=\"stylesheet\" type=\"text/css\" href=\"" . $script . "\">";
		}

	if (config ('web.javascript'))
		{
		foreach (config ('web.javascript') as $script)
			echo "\n\t<script type=\"text/javascript\" src=\"" . $script . "\"></script>";
		}
	?>

	<script type="text/javascript">
		// <![CDATA[
		var query 		= '<?php echo base64_encode (limbo::request()->query)?>';
		var authid		= <?php echo (isset ($AUTH) && $AUTH->authid) ? $AUTH->authid : 0?>;
		var path		= '<?php echo config ('web.root')?>';

		$(document).ready (function ()
			{
			javascript_init ();
	
			if (authid > 0)
				{
				heartbeat_init ();
				}
			});
		// ]]>
	</script>

	<title><?php echo $title?></title>
</head>

<body>
<a id="top" name="top"></a>

<header class="splash">
	<div class="limbo">LIMBO</div>
</header>

<br>

<div class="textc white m20">
	<?php
	if (! is_writable (config ('path.storage')))
		{
		echo '<p>The storage directory is not writable.</p>';
		}
	?>
</div>

</body>
</html>
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
		var query 		= '<?php echo base64_encode (substr (limbo::request()->query, 4))?>';
		var authid		= <?php echo (isset ($AUTH) && $AUTH->verified) ? $AUTH->authid : 0?>;
		var path		= '<?php echo config ('web.root')?>';

		$(document).ready (function ()
			{
			javascript_init ();
	
			if (authid > 0)
				{
				heartbeat_init ();
				}
			
			<?php
			if (! empty (limbo::request()->request['popup']))
				{
				echo 'load_popup ("' . base64_decode (limbo::request()->request['popup']) . '");';
				}
 			?>
			});
		// ]]>
	</script>

	<title><?php echo $title?></title>
</head>

<body>
	<a id="top" name="top"></a>
	
	<header class="standard">
		<div class="limbo"><a href="<?php echo config ('web.root')?>">LIMBO</a></div>
		
		<div class="links">
			<div class="version textr">v.<?php echo limbo::$version?></div>
		</div>
	</header>
	
	<section><?php echo $content?></section>
	
	<div id="popup-box" class="hidden round-4">
		<div id="popup-header">
			<div id="popup-title"></div>
			<div id="popup-buttons">
				<div id="popup-close" class="hand"><img src="<?php echo $root?>images/icons/menu_close.png" class="icon14" /></div>
			</div>
		</div>
		
		<div id="popup-content">
			Loading...
		</div>
	</div>
	
	<div id="popup-background" class="wide high hidden">
		<div id="popup-loading" class="hidden"><img src="<?php echo $root?>images/icons/throbber.gif"></div>
	</div>
</body>
</html>
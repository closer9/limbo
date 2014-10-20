<?php
/*
 * Submits the form via an AJAX call.
 * 
 * Valid JSON codes to reply with:
 *  - message	Display a message
 *  - error		Display an error message
 *  - redirect	Redirect to another URL
 *  - popup		Open a popup
 *  - reload	Reload the page
 *  - close		Close any open popups
 *  - process	Process custom javascript code specified in 'content'
 */

if (limbo::request ()->method == 'POST')
	{
	$required['field1']	= trim ($_POST['field1']);
	$required['field2']	= trim ($_POST['field2']);
	
	foreach ($required as $key => $value)
		if (empty ($value)) $error[0] = '* You must fill out all the required fields.';
	
	if (empty ($error))
		{
		// Process the form here
		
		limbo::json (array (
			'code'		=> 'message',
			'content'	=> "You've successfully filled out the form!"
			));
		}
	
	limbo::json (array (
		'code'		=> 'error',
		'content'	=> implode ('<br>', $error)
		));
	}
?>

<div id="ajax-error" class="hidden textc" style="margin-bottom: 1em; color: red;"></div>
<div id="ajax-message" class="hidden textc" style="margin-bottom: 1em; color: green;"></div>

<form id="ajax-form" action="index" method="post">
	<table class="center">
		<tr><td style="width: 75px;">Field 1</td><td><input type="text" name="field1"></td></tr>
		<tr><td style="width: 75px;">Field 2</td><td><input type="text" name="field2"></td></tr>
		<tr><td colspan="2" class="textc"><input type="submit" value="AJAX Submit"></td></tr>
	</table>
</form>

<script type="text/javascript">
	// <![CDATA[
	$(document).ready (function ()
		{
		$('#ajax-form').ajaxform ({
			errorid: 	'#ajax-error',
			messageid:	'#ajax-message'
			});
		});
	// ]]>
</script>
function close_notice (key)
	{
	$("#notice_" + key).slideUp ('slow');
	}

function toggle_debug ()
	{
	$('#debug-box').toggle ();
	}

function dump (obj)
	{
	var out = '';

	for (var i in obj) { out += i + ": " + obj[i] + "\n"; }

	console.log (out);
	}

function ucfirst (str)
	{
	str += '';

	var first = str.charAt (0).toUpperCase ();

	return first + str.substr (1);
	}
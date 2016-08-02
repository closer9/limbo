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

function restripe_tables ()
	{
	$('table.list tr').removeClass ('stripe');
	$('table.list tr:visible:odd').addClass ('stripe');
	}

(function($)
	{
	jQuery.fn.focus_cursor = function ()
		{
		return this.each (function ()
			{
			$(this).focus ();
			
			if (this.setSelectionRange)
				{
				var len = $(this).val ().length * 2;
				
				this.setSelectionRange (len, len);
				}
				else
				{
				$(this).val ($(this).val ());
				}
			
			this.scrollTop = 999999;
			});
		};
	})(jQuery);
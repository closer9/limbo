/*
 * After a user authenticates this function helps keep their session alive 
 * and optionally tracks their progress throughout the site.
 */

var heartbeat_timer;
var heartbeat_active = true;

function heartbeat_init ()
	{
	heartbeat_timer = setTimeout ('heartbeat_init()', 15000);
	
	heartbeat ();
	}

function heartbeat (string)
	{
	var active = (heartbeat_active) ? '&active=true' : '';
	query = (string != undefined) ? string : query;
	
	$.get ('/limbo-heartbeat?query=' + query + active, function (results) {
		if (results.status == 0)
			{
			clearTimeout (heartbeat_timer);

			load_popup (path + 'limbo-modal/timeout?message=' + results.message + '&redirect=' + query, 'Session expired');

			popup_hold ();
			}
		
		$('#heartbeat-status').html (results.status);
		});

	heartbeat_active = false;
	}
/*
 * Framework javascript startup function
 */

function javascript_init ()
	{
	setTimeout (function () { $('.fadeout').fadeOut (); }, 8000);
	setTimeout (function () { $('.slideup').slideUp (); }, 8000);

	if (typeof reload_popups == 'function') { reload_popups (); }
		
	/* Set the default focus */
	$('#focus,.focus').focus ();

	/* Manage the stripes for table listings */
	$('table.list tr:nth-child(odd)').addClass ('stripe');
	$('table.list tr:nth-child(even)').removeClass ('stripe');

	/* Make the popup-box movable */
	$('#popup-box').draggable ({handle: '#popup-move'});
	
	if (jQuery().tooltip)
		{
		$('.tooltip').tooltip({relative: true, position: 'center right', predelay: 200});
		}

	if (jQuery().datetimepicker)
		{
		$('.datebox').datepicker({showAnim: 'fade', dateFormat: 'yy-mm-dd', duration: 'fast'});
		}

	if (jQuery().datetimepicker)
		{
		$('.datetimebox').datetimepicker({ampm: true});
		}
	
	if (jQuery().timepicker)
		{
		$('.timebox').timepicker({ampm: true, stepMinute: 15});
		}

	if (jQuery().pagination)
		{
		$.fn.pagination.settings['function_default'] = function ()
			{
			/* Reset any stripes in the popups */
			$('table.list tr:nth-child(odd)').addClass ('stripe');

			/* Try to reload any popup-links we sent to the page */
			if (typeof reload_popups == 'function')
				{
				reload_popups ();
				}
			};
		}

	$('#noticebox-close').click (function () {
		var line = $(this).parent ('div');
	
		$.ajax ({
			data:		'',
			type:		'get',
			url:		path + 'limbo-register?id=' + line.attr ('id'),
			dataType:	'json',
	
			success: function (results)
				{
				if (results == null)
					alert ('Could not save notice-box-close registration');
				else
					line.slideUp ('fast');
				}
			});
		});
	
	$('#popup-close').click (function () { disable_popup (); });
	$('#popup-background').click (function () { disable_popup (); });

	/* Look for the escape key to close the popup */
	$(document).keypress (function (e) {
		if (e.keyCode == 27 && popup_status == 1)
			{
			disable_popup ();
			}
		});
	
	// Allow for a custom init function
	if (typeof custom_init == 'function')
		{
		custom_init ();
		}

	reload_popups ();
	}

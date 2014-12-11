/*
 * This JQuery plugin handles the AJAX requests performed by various forms.
 */

(function ($)
	{
	$.fn.ajaxform = function (options)
		{
		/* Load the default options in addition to what was sent */
		var settings = $.extend({}, $.fn.ajaxform.defaults, options);
		
		/* We need to save a copy of ourselves */
		settings.form = $(this);
		
		/* Start your engines! */
		$.fn.ajaxform.init (settings);
		};
	
	$.fn.ajaxform.defaults =
		{
		/* Setup some defaults for the object */
		iframeid	: 'iframe-post-form',		// The name and id of the iframe
		errorid		: '#popup-error',			// The id of the error results box
		messageid	: '#popup-message',			// The id of the message results box
		initialize	: function () {},			// Function to process on form submit
		complete	: function (response) {},	// Function to process after the results have been received
		forms		: ''						// This is a marker for $(this) object
		};
	
	$.fn.ajaxform.init = function (options)
		{
		var element		= '';
		var results		= '';
		var status		= true;
		
		/* Add the iframe if we don't already have one */
		if ($('#' + options.iframeid).length == 0)
			$('body').append ('<iframe id="' + options.iframeid + '" name="' + options.iframeid + '" style="display: none;" />');
		
		/* Process the form */
		return options.form.each (function ()
			{
			element = $(this);
			
			/* Check to see if there are file inputs here */
			var file_inputs	= $('input:file:enabled', options.form);
			
			/* Lets clean up the form a little bit */
			element.attr ('action', element.attr ('action') + '&ajax');
			
			element.submit (function (event)
				{
				/* Process any onsubmit function data */
				if (options.initialize.apply (this) === false) { return status; }
				
				if (file_inputs.length > 0)
					{
					/* Set the enctype to multipart and point it to the iframe */
					element.attr ('enctype', 'multipart/form-data');
					element.attr ('target', options.iframeid);
					
					/* Send this to the iframe */
					results = $.fn.ajaxform.submit_iframe (options);
					}
					else
					{
					event.preventDefault();
					
					/* Do a standard JQuery AJAX call */
					results = $.fn.ajaxform.submit_ajax (options, element);
					}
				});
			});
		};
	
	$.fn.ajaxform.submit_iframe = function (options)
		{
		var iframe		= {};
		var response	= '';
		
		/* This will catch when the iframe is loaded */
		iframe = $('#' + options.iframeid).load (function ()
			{
			response = iframe.contents ().find ('body');
			
			/* Process the results in the iframe */
			$.fn.ajaxform.process (options, response.html ());
			
			/* Stop watching the iframe */
			iframe.unbind('load');
			
			/* Clean out the iframe contents */
			setTimeout (function () { response.html (''); }, 1);
			});
		};
	
	$.fn.ajaxform.submit_ajax = function (options, element)
		{
		$.ajax ({
			/* Use standard AJAX for this form, no file input */
			data:		element.serialize (),
			type:		element.attr ('method'),
			url:		element.attr ('action'),
			dataType:	'text',

			success: function (response)
				{
				/* Process the results from the AJAX call */
				$.fn.ajaxform.process (options, response);
				},

			error: function (request, status, error)
				{
				console.log (request.responseText);

				if (request.responseText.indexOf ("limbo-error-message") >= 0)
					{
					error = $(request.responseText).find ("#limbo-error-message").text ();
					}

				alert ('Error: ' + error);
				}
			});
		};
	
	$.fn.ajaxform.process = function (options, response)
		{
		var results = '';
		
		/* Try to parse the responce as JSON */
		try {
			results = jQuery.parseJSON (response);
			}
		catch (e)
			{
			alert ("Invalid JSON returned from the script:\n\n" + response);
			
			return false;
			}
		
		/* Process any complete function data */
		options.complete.apply (options.form, [results]);
		
		/* What do we do with the data? */
		switch (results.code)
			{
			case 'error':
				$(options.messageid).html (results.content).hide ();
				$(options.errorid).html (results.content).show ();
				break;
			
			case 'message':
				$(options.errorid).html (results.content).hide ();
				$(options.messageid).html (results.content).show ();
				break;
			
			case 'redirect':
				if (window.location == results.content)
					{ window.location.reload (); }
					else
					{ window.location = results.content; }
				break;
			
			case 'popup':
				load_popup (results.content);
				break;
			
			case 'reload':
				window.location.reload ();
				break;
			
			case 'close':
				disable_popup ();
				break;
			
			case 'process':
				var process = new Function (results.content);
				
				process ();
				break;
			
			default:
				alert ('Unknown code returned from ajax request');
				break;
			}
		};
	})(jQuery);
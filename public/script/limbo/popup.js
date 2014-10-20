/*
 * Collection Name: Popup functions (1.2)
 * Author: Scott McKee (closer9@gmail.com)
 * Requires: JQuery 1.4
 * ---
 * This collection of functions handles the popup functionality of the 
 * framework.
 */

var popup_status	= 0;
var popup_target	= 'popup-box';
var	popup_type		= 'page';
var popup_locked	= 0;
var popup_max_width	= 850;
var screen_position = scrolling_position ();

var cur_location	= '';
var cur_target		= '';
var cur_type		= '';
var cur_title		= '';

function load_popup (location, title, target, type)
	{
	/* Clean up some of the variables */
	cur_location 	= (typeof (location) == 'object') ? location.href : location;
	cur_target 		= (target == null) ? popup_target : target;
	cur_type 		= (type == null) ? popup_type : type;
	cur_title		= (title == null) ? '' : title;
	
	/* Get the current screen position */
	screen_position = scrolling_position();

	/* Make sure we append the proper variable type */
	if (cur_location.indexOf('?') != -1)
		page_request = cur_location + '&popup';
		else
		page_request = cur_location + '?popup';

	/* Don't do anything if the page is empty */
	if (page_request == '') return false;
	
	/* Update the title */
	$('#popup-title').html (cur_title);

	if (popup_status == 0)
		{
		$('#popup-background').fadeTo(0, .3, function () {
			popup_loading ();
			popup_fetch();
			});
		}
		else
		{
		popup_fetch();
		}
	}

function popup_fetch ()
	{
	if (cur_type == 'image')
		{
		img = new Image ();
		img.src = page_request;
		
		img.onload = function () {
			var width	= this.width;
			var height	= this.height;
  			var append	= '';
  			
  			if (this.width > popup_max_width)
  				{
				height	= (popup_max_width / this.width) * this.height;
				width	= popup_max_width;
				append	= '<div class="textc"><a href="' + cur_location + '">Click here to view the original</a></div>';
				
  				console.log ('Resized image: ' + width + ' x ' + height);
  				}
  			
  			popup_content ('<img src="' + page_request + '" style="width: ' + width + 'px; height: ' + height + 'px; margin-top: 15px;"/>' + append);
			};
		}
	
	if (cur_type == 'page')
		{
		$.ajax ({
			type:	'GET',
			url:	page_request,

			success: function (content, status, XMLHttpRequest)
				{
				/* If the results are a full webpage just relocate there */
				if (content.match (/DOCTYPE/) || XMLHttpRequest.status != 200)
					{
					window.location = cur_location; return;
					}
				
				popup_content (content);
				},
			
			/* If an error occures show it in a javascript alert */
			error: function (XMLHttpRequest, textStatus, errorThrown)
				{
				if (XMLHttpRequest.status > 0)
					alert ('AJAX Error: ' + textStatus + errorThrown + XMLHttpRequest.statusText);
				
				return false;
				}
			});
		}
	}

function popup_content (content)
	{
	/* If a popup already exists change the contents */
	if (popup_status == 1)
		{
		popup_close ();
		
		$('#popup-content').fadeTo (100, 0, function () {
			$(this).html (content);
			$(this).fadeTo (100, 1);
			$('#' + popup_target).fadeIn ('fast');
			});
		
		/* We need to pause for a second here */
		setTimeout ('post_processing ();', 200);
		}
	
	/* Load the contents and show the popup */
	else {
		$('#popup-content').html (content);
		$('#popup-loading').hide ();
        $('#' + popup_target).fadeIn ('fast');
		
		post_processing ();
		}
	}

function post_processing ()
	{
	popup_status = 1;
	
	$('#popup-content #crumbs').remove ();
	$('#popup-content .focus').focus ();
	$('#popup-close').click (function () { disable_popup (); });
	
	javascript_init ();
	center_popup ();
	reload_popups ();
	}

function center_popup ()
	{
	var window_height	= document.documentElement.clientHeight;
	var window_width	= document.documentElement.clientWidth;
	var popup_height	= $('#' + popup_target).height ();
	var popup_width		= $('#' + popup_target).width ();
	var position_top	= 100;
	var position_left	= window_width / 2 - popup_width / 2;
	
	/* Reposition the popup */
	$('#' + popup_target).css ({
		'top'	: (position_top < 10) ? 10 : position_top,
		'left'	: (position_left < 10) ? 10 : position_left
		});
	
	/* Go back to the screen position when we started the popup */
	scrollTo (screen_position[0], screen_position[1]);
	}

function disable_popup ()
	{
	if (popup_status == 1 && popup_locked == 0)
		{
		$('#popup-background').fadeOut ('fast');
		
		popup_close ();
		}
	}

function popup_loading ()
	{
	$('#popup-loading').fadeIn (1000);
	}

function popup_hold ()
	{
	$('#popup-close').hide ();
	
	popup_locked = 1;
	}

function popup_release ()
	{
	$('#popup-close').show ();
	
	popup_locked = 0;
	}

function popup_close ()
	{
	$('#' + popup_target).fadeOut ('fast');
	
	popup_status = 0;
	}

function reload_popup ()
	{
	load_popup (cur_location, cur_title, cur_target, cur_type);
	}

function reload_popups ()
	{
	$('.popup, .popup-link, .popup-image, .popup-disable').unbind ('click');
	
	$('.popup, .popup-link').click (function (event) {
		event.preventDefault ();
		
		load_popup ($(this).attr ('href'), $(this).attr ('title'), popup_target, 'page');
		
		if ($(this).attr ('rel') == 'hold')
			{
			popup_hold ();
			}
		});
	
	$('.popup-image').click (function (event) {
		event.preventDefault ();
		
		load_popup ($(this).attr ('href'), $(this).attr ('title'), popup_target, 'image');
		});

	$('.popup-disable').click (function (event) {
		event.preventDefault ();

		disable_popup ();
		});
	}

function scrolling_position ()
	{
	var position = [0, 0];

	if (typeof window.pageYOffset != 'undefined')
		{
		position = [
			window.pageXOffset,
			window.pageYOffset
		];
		}

	else if (typeof document.documentElement.scrollTop != 'undefined')
		{
		position = [
			document.documentElement.scrollLeft,
			document.documentElement.scrollTop
		];
		}

	else if (typeof document.body.scrollTop != 'undefined')
		{
		position = [
			document.body.scrollLeft,
			document.body.scrollTop
		];
		}

	return position;
	}
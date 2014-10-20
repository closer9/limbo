/*
 * Name: Pagination JQuery plugin (0.2)
 * Author: Scott McKee (closer9@gmail.com)
 * Requires: JQuery > 1.3
 * ----
 * $('#pag-nav').pagination ({
 * 		'target'	: '#pag-content',
 * 		'display'	: 4,
 * 		'query'		: 'ajax_processing_script.php',
 * 		'total'		: <?php echo $total?>
 *		});
 */

(function ($) {
	$.fn.pagination = function (options) {
		
		$.extend ($.fn.pagination.settings, options);
		
		$.fn.pagination.settings['this'] = $(this);
		
		$.fn.pagination.init ();
		};
	
	$.fn.pagination.settings = {
		'id'		: 0,		// The ID of this process
		'display'	: 10,		// How many results to display
		'buffer'	: 2,		// How many pages to show
		'throbber'	: '/images/icons/throbber_light.gif',
		'this'		: null,		// The pointer for the class
		
		'target'	: '',		// The name of the DOM element
		'query'		: '',		// The URL to get the list from
		'total'		: '',		// The total amount of results
		'page'		: 1,		// The current page
		'colms'		: '',		// What columns to sort by
		'order'		: '',		// What order to sort in
		'hide'		: false,	// Hide navigation if no pages exist
		
		'function_default'	: false,	// Any default post-processing functions go here
		'function'			: false		// Any page-specific processing
		};
	
	$.fn.pagination.init = function ()
		{
		if ($.fn.pagination.settings['total'] > 0)
			{
			$.fn.pagination.throbber ();
			$.fn.pagination.navigation ();
			$.fn.pagination.content ();
			}
		};
	
	$.fn.pagination.update = function (page)
		{
		$.fn.pagination.settings['page'] = page;
		
		$($.fn.pagination.settings['target']).fadeTo (100, 0.3);
		
		$.fn.pagination.navigation ();
		$.fn.pagination.content ();
		
		$($.fn.pagination.settings['target']).fadeTo (100, 1);
		};
			
	$.fn.pagination.throbber = function ()
		{
		$($.fn.pagination.settings['target']).html
			('<div class="textc vmid pt10"><img src="' + $.fn.pagination.settings['throbber'] + '"></div>');
		};
			
	$.fn.pagination.navigation = function ()
		{
		if ($.fn.pagination.settings['hide'])
			{
			if ($.fn.pagination.settings['total'] <= $.fn.pagination.settings['display'])
				{
				$.fn.pagination.settings['this'].hide ();
				
				return;
				}
			}
			else
			{
			$.fn.pagination.settings['this'].show ();
			}
		
		var page_last = Math.ceil ($.fn.pagination.settings['total'] / $.fn.pagination.settings['display']);
		var page_curr = $.fn.pagination.settings['page'];
		var page_buff = $.fn.pagination.settings['buffer'];
		var page_next = page_curr + 1;
		var page_prev = page_curr - 1;
		
		/* The buffer trigger pages */
		var page_min = (page_curr - page_buff);
		var page_max = (page_curr + page_buff);
		
		/* The table cells */
		var cell_1 = cell_2 = cell_3 = '';
		
		if (page_curr > 1)
			{
			cell_1 += '<span class="pag-button pag-nav-left round-2"><a href="Javascript: $.fn.pagination.update (1)">&lt;&lt;</a></span>';
			cell_1 += '<span class="pag-button pag-nav-left round-2"><a href="Javascript: $.fn.pagination.update (' + page_prev + ')">&lt;</a></span>';
			}
			else
			{
			cell_1 += '<span class="pag-button pag-nav-left round-2">&lt;&lt;</span>';
			cell_1 += '<span class="pag-button pag-nav-left round-2">&lt;</span>';
			}
		
		for (a = 1; a <= page_last; a ++)
			{
			/* Show the ellipses if we havent hit this buffer yet */
			if (a < page_min && page_min > page_buff)
				{
				a = page_min;
				cell_2 += '<span class="pag-button pag-nav-page round-2"><a href="Javascript: $.fn.pagination.update (1)">1</a></span>';
				cell_2 += '<span class="pag-nav-break">...</span>';
				}
			
			/* Show the current page */
			if (a == page_curr)
				cell_2 += '<span class="pag-button pag-nav-current round-2">' + a + '</span>';
				else
				cell_2 += '<span class="pag-button pag-nav-page round-2"><a href="Javascript: $.fn.pagination.update (' + a + ')">' + a + '</a></span>';
			
			/* Show the ellipses if we are past the buffer */
			if (a + 1 > page_max && page_max < page_last - 1)
				{
				cell_2 += '<span class="pag-nav-break">...</span>';
				cell_2 += '<span class="pag-button pag-nav-page round-2"><a href="Javascript: $.fn.pagination.update (' + page_last + ')">' + page_last + '</a></span>';
				
				break;
				}
			}
		
		if (page_curr != page_last)
			{
			cell_3 += '<span class="pag-button pag-nav-right round-2"><a href="Javascript: $.fn.pagination.update (' + page_next + ')">&gt;</a></span>';
			cell_3 += '<span class="pag-button pag-nav-right round-2"><a href="Javascript: $.fn.pagination.update (' + page_last + ')">&gt;&gt;</a></span>';
			}
			else
			{
			cell_3 += '<span class="pag-button pag-nav-right round-2">&gt;</span>';
			cell_3 += '<span class="pag-button pag-nav-right round-2">&gt;&gt;</span>';
			}
			
		html = '<table id="pag-table" class="wide"><tr>';
		html += '<td>' + cell_1 + '</td>';
		html += '<td class="textc">' + cell_2 + '</td>';
		html += '<td class="textr">' + cell_3 + '</td>';
		html += '</tr></table>';
		
		$.fn.pagination.settings['this'].html (html);
		};
			
	$.fn.pagination.content = function ()
		{
		var page_curr 	= $.fn.pagination.settings['page'];
		var page_disp 	= $.fn.pagination.settings['display'];
		var page_start	= (page_disp * page_curr) - page_disp;
		
		$.ajax ({
			type: 'get',
			url: $.fn.pagination.settings['query'] + '&ajax',
			dataType: 'html',
			data: {
				ajax:	'',
				colms:	$.fn.pagination.settings['colms'],
				order:	$.fn.pagination.settings['order'],
				start:	page_start,
				limit:	page_disp,
				page:	page_curr
				},
			
			success: function (results, status, XMLHttpRequest)
				{
				/* Check to see if we need to break out */
				if (results.match (/DOCTYPE/) || XMLHttpRequest.status != 200)
					{
					window.location = $.fn.pagination.settings['query']; return;
					}
				
				/* Output the results to the website */
				$($.fn.pagination.settings['target']).html (results);
				
				/* Load the default post-processing steps */
				$.fn.pagination.settings['function_default'].call ();
				
				/* Load any defined functions */
				if ($.fn.pagination.settings['function'] != false)
					$.fn.pagination.settings['function'].call ();
				}
			});
		};
	})(jQuery);
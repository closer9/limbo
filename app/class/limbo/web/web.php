<?php
namespace limbo\web;

use \limbo\error;
use \limbo\log;

/**
 * A collection of functions and methods relating to web requests
 * 
 * Class web
 * @package limbo\web
 */
class web
	{
	/**
	 * Triggers an error on the website
	 * 
	 * @param string $code The code to reply with and template to render
	 * @throws error
	 */
	public static function trigger ($code)
		{
		self::clear_buffer ();
		
		log::debug ('Code trigger: ' . $code);
		
		\limbo::render ('codes/' . $code, array (), 'content');
		\limbo::render (config ('web.template'), array ('title' => $code . ' Error'));
		
		exit (1);
		}

	/**
	 * Restart the OB returning the results of the previous one
	 *
	 * @param bool $implicit Perform an implicit flush or not
	 * @return string The contents of the previous output buffer
	 */
	public static function clear_buffer ($implicit = false)
		{
		$return = ob_get_clean ();
		
		if (ob_get_level ())
			while (@ob_end_clean () === false);

		ob_start (); ob_implicit_flush ($implicit);
		
		return $return;
		}
	
	/**
	 * Flush the write buffer(s) out to the client
	 *
	 * @param string $text Optional string to send before the flush
	 */
	public static function flush ($text = '')
		{
		if (! empty ($text))
			{
			echo $text;
			}
		
		ob_flush (); flush ();
		}
	
	/**
	 * Redirect to another page
	 *
	 * @param string $location		The relative location to redirect to
	 * @param array  $variables		GET variables to append to the URI
	 * @param int    $code			The optional redirect http code to send	
	 * 
	 * @returns bool Returns false on failure
	 */
	public static function redirect ($location, array $variables = array (), $code = 303)
		{
		if (\limbo::request ()->ajax)
			{
			\limbo::halt (400, "Redirect is disabled for ajax calls ({$location})");
			}
		
		$location 	= ltrim ($location, '/');
		$protocol	= config ('web.protocol');
		$hostname	= config ('web.hostname');
		$path		= config ('web.root');
		$options	= '';

		if (count ($variables) > 0)
			{
			$glue	= (strpos ($location, '?') !== false) ? '&' : '?';
			$pieces	= array ();

			foreach ($variables as $key => $value)
				{
				$pieces[] = "{$key}={$value}";
				}

			$options = $glue . implode ('&', $pieces);
			}
		
		// Build the absolute URL
		$url = "{$protocol}://{$hostname}{$path}{$location}{$options}";
		
		log::debug ("Redirecting to: {$url}");
		
		$response = new response ($url, $code, array ('Location' => $url));
		$response->send ();
		}

	/**
	 * Update the HTML title of the website
	 *
	 * @param string $input  	The text to set in the title
	 * @param bool   $append	Do we want to append or replace. Default: append
	 */
	public static function update_title ($input, $append = true)
		{
		if ($append)
			{
			$input = config ('web.title') . ' - ' . $input;
			}
		
		log::debug ('Updating title to: ' . $input);

		config ('web.title', $input);
		}
	}
<?php
namespace limbo\web;

use \limbo\util\string;
use \limbo\util\collection;

/**
 * This class collects all the information about the specific request made to the
 * application. Any server or user data should be contained to this object.
 * 
 * Class request
 * @package limbo\web
 */
class request
	{
	// Page information
	public $url;			// The raw url from the browser		/testing/request/?t=1
	public $pid;			// What the fixed url should be		/testing/request/index
	public $path;			// The local dir for the request	/testing/request/
	public $page;			// The actual file requested		/testing/request/index.php
	public $section;		// Full path to section				/www/site/sections/testing/request/
	public $file;			// Full path to the file			/www/site/sections/testing/request/index.php
	public $mime;			// The MIME type for the file		text/x-php
	
	// User information
	public $ip;				// The users IP address				50.34.2.206
	public $user_agent;		// The useragent					Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4)
	
	// Request information
	public $server;			// The server name					limbo.neg9.com
	public $port;			// The requested port				80
	public $method;			// The request method				GET
	public $scheme;			// The HTTP scheme					HTTP/1.0
	public $referrer;		// Whoever referred this page		Some URL
	public $ajax;			// Was this an AJAX request			(bool)
	public $type;			// The content type					CONTENT_TYPE
	public $length;			// The length of the request		0
	public $secure;			// Was the request secure?			(bool)
	public $etag;			// An optional ETag cache			The ETag or false
	public $modified;		// Any last modified cache			The date or false
	
	// HTTP Variables
	public $get;			// Collection of _GET variables
	public $post;			// Collection of _POST variables
	public $cookies;		// Collection of _COOKIE variables
	public $files;			// Collection of _FILES variables
	
	/**
	 * Start by building the objects data. Collecting it from wherever it may be. You can 
	 * override any of the default data by passing an optional array of overrides.
	 * 
	 * @param array $config
	 */
	public function __construct (array $config = array())
		{
		$defaults = array (
			'url' 			=> self::get_value ('REQUEST_URI', '/'),
			'pid'			=> self::get_pid (),
			'path'			=> substr (self::get_pid (), 0, strrpos ($this->pid, '/') + 1),
			'page'			=> substr (self::get_pid (), strpos ('/' . self::get_pid (), config ('path.section'))),
			'section'		=> null,
			'file'			=> null,
			'mime'			=> null,
			'method' 		=> self::get_method (),
			'referrer' 		=> self::get_value ('HTTP_REFERER'),
			'server'		=> self::get_value ('SERVER_NAME'),
			'ip' 			=> self::get_ip (),
			'port'			=> self::get_value ('SERVER_PORT'),
			'ajax' 			=> self::get_ajax (),
			'scheme' 		=> self::get_value ('SERVER_PROTOCOL', 'HTTP/1.1'),
			'user_agent' 	=> self::get_value ('HTTP_USER_AGENT'),
			'type' 			=> self::get_value ('CONTENT_TYPE'),
			'length' 		=> self::get_value ('CONTENT_LENGTH', self::get_length ()),
			'secure' 		=> self::get_value ('HTTPS', 'no') != 'no',
			'etag'			=> self::get_value ('HTTP_IF_NONE_MATCH', false),
			'modified'		=> self::get_value ('HTTP_IF_MODIFIED_SINCE', false)
			);
		
		if (! empty ($config))
			{
			foreach ($config as $key => $value)
				{
				$defaults[$key] = $value;
				}
			}
	
		$this->init ($defaults);
		}
	
	/**
	 * Takes in the defaults from the constructor and populates the class. After populating we
	 * try to clean up some of the values.
	 * 
	 * @param array $options The array of options to populate the class with
	 */
	private function init (array $options)
		{
		foreach ($options as $name => $value)
			{
			$this->$name = $value;
			}
		
		// Set the request variables
		$this->get 		= new collection ($_GET);
		$this->post 	= new collection ($_POST);
		$this->cookies 	= new collection ($_COOKIE);
		$this->files 	= new collection ($_FILES);
		
		// Try to figure out the real relative path and section of the request
		$fullpath 		= string::reduce_multiples (config ('path.section') . $this->page, '/');
		$this->path 	= substr ($this->pid, 0, strrpos ($this->pid, '/') + 1);
		$this->section 	= substr ($fullpath, 0, strrpos ($fullpath, '/') + 1);
		
		// Check for JSON input
		if (strpos ($this->type, 'application/json') === 0)
			{
			$body = $this->get_body();
			
			if ($body != '')
				{
				$data = json_decode ($body, true);
				
				if ($data != null)
					{
					$this->post->set_data ($data);
					}
				}
			}
		
		// Is the request for a PHP script?
		if (is_readable ($fullpath . '.php'))
			{
			$this->page = $this->page . '.php';
			}
		
		if (is_file (config ('path.section') . $this->page))
			{
			$this->file = string::reduce_multiples (config ('path.section') . $this->page, '/');
			
			if (function_exists ('finfo_file'))
				{
				$this->mime = finfo_file (finfo_open (FILEINFO_MIME_TYPE), $this->file);
				}
			}
		}
	
	/**
	 * Do some logic to figure out what the actual request should be for (and do some cleanup)
	 * 
	 * Example:
	 * /testing/request/   ->    /testing/request/index
	 * /testing/doof?d=1   ->    /testing/doof
	 * 
	 * @return string
	 */
	private static function get_pid ()
		{
		$pid = '/' . ((empty ($_REQUEST['pid'])) ? '' : filter_var ($_REQUEST['pid'], FILTER_SANITIZE_URL));
		$pid = preg_replace ('/\;|\s\s+|\*|\.php$|.database$/', '', $pid);
		$pid = (substr ($pid, -1) == '/') ? $pid . 'index' : $pid;
		
		return $pid;
		}
	
	/**
	 * Attempt to get a value from the web server. You can optionally set a default value
	 * if an actual value could not be found.
	 * 
	 * @param string $value		The value to search for
	 * @param string $default	What the default should be if a value cant be found
	 *
	 * @return string
	 */
	private static function get_value ($value, $default = '')
		{
		return isset ($_SERVER[$value]) ? $_SERVER[$value] : $default;
		}
	
	/**
	 * Try to figure out what method was used for this request
	 * 
	 * @return string
	 */
	private static function get_method ()
		{
		if (isset ($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']))
			{
			return $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
			}
		elseif (isset ($_REQUEST['_method']))
			{
			return $_REQUEST['_method'];
			}
	
		return self::get_value ('REQUEST_METHOD', 'GET');
		}
	
	/**
	 * Read in the body of the request into a static variable so we'll still have data
	 * in the case that this object is created a second time
	 * 
	 * @return string The request body
	 */
	public static function get_body ()
		{
		static $body;
		
		if (! is_null ($body))
			{
			return $body;
			}
		
		$method = self::get_method();
		
		if ($method == 'POST' || $method == 'PUT')
			{
			$body = file_get_contents ('php://input');
			}
		
		return $body;
		}
	
	/**
	 * Get the length of the request body
	 * 
	 * @return int
	 */
	private static function get_length ()
		{
		return strlen (self::get_body ());
		}
	
	/**
	 * Try to figure out what the users IP address should be. Loop though all the available
	 * server variables (in order of importance). If all else fails return false.
	 * 
	 * @return bool|string
	 */
	private static function get_ip ()
		{
		static $options = array (
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR'
			);
		
		foreach ($options as $key)
			{
			if (isset ($_SERVER[$key]))
				{
				return self::get_value ($key);
				}
			}
		
		return false;
		}
	
	/**
	 * Try to determine if the request was made via an XML request
	 * 
	 * @return bool
	 */
	private static function get_ajax ()
		{
		if (self::get_value ('X_REQUESTED_WITH') == 'XMLHttpRequest' || isset ($_REQUEST['ajax']))
			{
			return true;
			}
		
		return false;
		}
	}
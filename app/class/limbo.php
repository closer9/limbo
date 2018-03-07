<?php
/**
 * LIMBO
 *
 * @author Scott McKee <smckee@gmail.com>
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

use \limbo\log;
use \limbo\web\web;
use \limbo\util\file;

/**
 * This is the main class for the application. It builds and maintains all the other objects
 * used for compiling the request and response.
 * 
 * Class limbo
 */
class limbo
	{
	/**
	 * @var string The version of the Limbo application
	 */
	public static $version	= '0.1.5.1208';
	
	/**
	 * @var array Contains the array of configuration options
	 */
	public static $config;
	
	/**
	 * @var array Contains a list of variables that will be extracted for each template
	 */
	public static $globals;
	
	/**
	 * @var object The micro timestamp of when the framework started up
	 */
	public static $startup;
	
	/**
	 * @var object Our lovely IoC controller Pimple
	 */
	private static $ioc;
	
	/**
	 * During construction we are going to perform a lot of bootstrapping. Collecting
	 * the helper functions, setting up the autoloader, error handler, timezone, etc.
	 * 
	 * We also create our request and response objects. Those are used regardless of how
	 * the application is called.
	 * 
	 * If this request is from the web though, we'll need to pass processing on to the
	 * web_init() method. Otherwise our job here of booting the app is done.
	 *
	 * If this is from a CLI call then the rest of the processing is done there.
	 * 
	 * @param bool $bootstrap_only Do not process the page, just perform the startup steps
	 */
	public function __construct ($bootstrap_only = false)
		{
		require (__DIR__ . '/../startup/helpers.php');
		
		$this->load_config ();
		$this->autoload_registration ();
		
		self::$startup = \limbo\util\time::microtime_float ();
		
		set_error_handler (array ('\limbo', 'error_handler'));
		
		date_default_timezone_set (config ('limbo.timezone'));
		
		ini_set ('log_errors', 'On');
		ini_set ('error_log', config ('log.path') . 'php_errors.log');
		
		if (config ('limbo.production'))
			{
			ini_set ('display_errors', 'Off');
			ini_set ('error_reporting', E_ERROR | E_WARNING);
			}
			else
			{
			ini_set ('display_errors', 'On');
			ini_set ('error_reporting', E_ALL | E_STRICT);
			}
		
		// Setup our IoC container to hold our objects
		self::$ioc = new \limbo\pimple\container ();
		
		self::$ioc['config'] = function ()
			{
			// Utility for loading user created configurations
			return new \limbo\config (config ('limbo.config_group'));
			};
		
		self::$ioc['request'] = function ()
			{
			// Contains information regarding the current request
			return new \limbo\web\request ();
			};
		
		self::$ioc['response'] = function ()
			{
			// The response object that determines how we will respond to the request
			return new \limbo\web\response ();
			};
		
		register ('l', $this);
		
		// Is this a website request?
		if (self::invoked_from () == 'web')
			{
			$this->web_init ($bootstrap_only);
			}
		}
	
	/********************************************************************************
	 * Autoload methods
	 *******************************************************************************/
	
	public static function autoload_registration ()
		{
		spl_autoload_register (array (__CLASS__, 'autoload'));
		}
	
	/**
	 * Your run of the mill PSR-0 autoloader
	 * 
	 * @param string $class The class name to search for
	 */
	public static function autoload ($class)
		{
		$class = str_replace (array ('\\', '_'), DIRECTORY_SEPARATOR, $class);
		
		foreach ((array) config ('path.class') as $path)
			{
			$filename = $path . $class . '.php';
			
			if (is_readable ($filename))
				{
				require ($filename);
				
				return;
				}
			}
		}
	
	/********************************************************************************
	 * Initialization method
	 *******************************************************************************/
	
	/**
	 * This is the main processing unit of the application for web requests.
	 * 
	 * @param bool $bootstrap_only Do not process the page, just perform the startup steps
	 */
	public function web_init ($bootstrap_only = false)
		{
		config ('web.protocol', ((config ('web.ssl')) ? 'https' : 'http'));
		
		// Let's figure out where exactly we're being called from
		// and setup some useful variables.
		foreach (config ('web.domains') as $hostname)
			{
			if (self::request ()->server == $hostname)
				{
				config ('web.hostname', $hostname);
				
				// If this app requires SSL, make sure we have it.
				if (config ('web.ssl') && self::request ()->port == 80)
					{
					self::response ()
						->header ('Location', 'https://' . $hostname . self::request ()->url)
						->send ();
					}
				
				ini_set ('session.cookie_domain', $hostname);
				
				break;
				}
			}
		
		// Did we figure out a valid hostname? Redirect to a known one if not.
		if (config ('web.hostname') === null)
			{
			self::response ()
				->header ('Location', config ('web.protocol') . '://' . config ('web.domains')[0] . self::request ()->url)
				->send ();
			}
		
		// Generate an easy to call web url
		config ('web.http', config ('web.protocol') . '://' . config ('web.hostname') . config ('web.root'));
		
		limbo\log::debug ('Web request for: ' . self::request()->url);
		
		// Start the output buffer
		web::clear_buffer ();
		
		// We want to manage caching ourselves
		session_cache_limiter (false);
		
		// Begin the session
		if (! session_id ())
			{
			session_start ();
			}
		
		self::$ioc['router'] = function ()
			{
			// Our standard router object
			return new \limbo\web\router ();
			};
		
		self::$ioc['view'] = function ()
			{
			// A helper object that is used to build and process the various templates
			return new \limbo\view ();
			};
		
		self::$ioc['flash'] = function ()
			{
			// The manager object for handling the flash messages
			return new \limbo\web\flash ();
			};
		
		// Load the bootstrap files
		require (__DIR__ . '/../startup/startup.php');
		require (__DIR__ . '/../startup/routes.php');
		require (__DIR__ . '/../startup/custom.php');
		
		if ($bootstrap_only)
			{
			// We don't want to do any rendering
			return;
			}
		
		// Collect any section config files
		$this->load_section ();
		
		// Load the default route (the catch all)
		$this->load_default_route ();
		
		if (self::request ()->ajax)
			{
			self::response ()->cache (false);
			}
		
		// Loop though all the routes looking for a match
		while ($route = self::router ()->route (self::request ()))
			{
			if (! $route->dispatch ())
				{
				break;
				}
			
			self::router ()->next ();
			}
		
		self::stop (200);
		}
	
	/**
	 * Loads the configuration file for this instance. It will try to find a file named '<domain>.config.php'
	 * based on the current called domain name.
	 *
	 * If the script is called via CLI, it will look into the config files themselves, looking
	 * for the 'path.dir' variable to see if that config matches it's own directory structure.
	 *
	 * Finally, if no config file could be found, it will try to load the file 'default.config.php'
	 * in the app/configs directory.
	 */
	public function load_config ()
		{
		self::$config['config.dir']		= realpath (__DIR__ . '/../configs/') . '/';
		self::$config['config.file'] 	= 'default.config.php';
		
		if ($this->invoked_from () == 'web')
			{
			self::$config['web.domain'] = $_SERVER['HTTP_HOST'];
			
			if (is_file (self::$config['config.dir'] . self::$config['web.domain'] . '.config.php'))
				{
				self::$config['config.file'] = self::$config['web.domain'] . '.config.php';
				}
			}

		if ($this->invoked_from () == 'cli')
			{
			foreach (scandir (self::$config['config.dir']) as $file_name)
				{
				foreach (file (self::$config['config.dir'] . $file_name) as $line)
					{
					if (preg_match ("/path.dir\'\](\s+)= '(.*)';$/i", $line, $return))
						{
						if (preg_match ('/' . preg_quote ($return[2], '/') . '/', __DIR__))
							self::$config['config.file'] = $file_name;
						}
					}
				}
			}
		
		// Build the file path
		$config_file = self::$config['config.dir'] . self::$config['config.file'];
		
		if (! is_file ($config_file))
			{
			echo "Could not load a valid configuration file. Looking for: {$config_file}";
			
			exit (1);
			}
		
		require ($config_file);
		}
	
	
	/**
	 * This method takes the current request section (/some/section/path/) and loops through
	 * all the sections and subsections looking for additional configuration files.
	 */
	public function load_section ()
		{
		$sections	= array ();
		$directory 	= self::request ()->section;
		
		// Compile a list of all the preceding sections
		while (strlen ($directory) >= strlen (limbo::$config['path.dir']))
			{
			$sections[] = $directory;
			
			$directory = substr ($directory, 0, strrpos (rtrim ($directory, '/'), '/') + 1);
			}
		
		// Now loop through those sections looking for configuration files
		foreach (array_reverse ($sections) as $directory)
			{
			log::debug ('Looking for config file: ' . $directory . 'config.inc.php');
			
			if (is_readable ($directory . 'config.inc.php'))
				{
				log::debug ('Found config file! (' . $directory . 'config.inc.php)');
					
				file::import ($directory . 'config.inc.php');
				}
			
			if (is_readable ($directory . 'config.db.php'))
				{
				/* TODO: Read any migration files and process them */
				}
			}
		}
	
	
	/**
	 * This is the catch-all default route. This route makes sure that our request is valid and
	 * handles building the response accordingly. 
	 */
	public function load_default_route ()
		{
		self::router ()->build ('*', function ()
			{
			// Looks like we want a directory, append the slash and try again
			if (is_dir (config ('path.section') . self::request ()->page))
				{
				limbo\web\web::redirect (self::request ()->url . '/', array (), 301);
				}
			
			// If we don't have a file to read from, that's a 404
			if (self::request ()->file === null)
				{
				web::trigger ('404');
				}
			
			log::info ('Requesting: ' . self::request ()->file . ' (' . self::request ()->mime. ')');
			
			// If the request is for an image then throw it to the browser
			if (substr (self::request ()->mime, 0, 6) == 'image/')
				{
				self::response ()
					->header ('Content-type', self::request ()->mime)
					->write (file_get_contents (self::request ()->file))
					->send ();
				}
			
			// Setup some special template variables
			$templates = array (
				'compact'	=> 'compact',
				'ajax'		=> '',
				'save'		=> '',
				'popup'		=> '',
				);
			
			foreach ($templates as $keyword => $view)
				{
				if (isset (self::request ()->get[$keyword]) && empty (self::request ()->get[$keyword]))
					{
					config ('web.template', $view);
					}
				}
			
			if (self::ioc ('auth'))
				{
				self::ioc ('auth')->initialize ();
				}
			
			if (! config ('web.template'))
				{
				// We don't have a template to use, just render the requested file
				self::render (self::request ()->file, array (), '', true);
				}
				else
				{
				// Load the requested page into the view
				self::render (self::request ()->file, array (), 'content', true);
				
				// Load the template and render the page
				self::render (config ('web.template'), array (
					'title' => config ('web.title')
					));
				}
			});
		}
	
	/********************************************************************************
	 * Alternate way to connect to our methods
	 *******************************************************************************/
	
	/**
	 * @param string $config The name of the default config group to use
	 *
	 * @return \limbo\config
	 */
	public static function config ($config = null)
		{
		if ($config !== null)
			return self::ioc ('config')->group ($config);
		
		return self::ioc ('config');
		}
	
	/**
	 * @return \limbo\web\request
	 */
	public static function request ()
		{
		return self::ioc ('request');
		}
	
	/**
	 * @return \limbo\web\response
	 */
	public static function response ()
		{
		return self::ioc ('response');
		}
	
	/**
	 * @return \limbo\web\router
	 */
	public static function router ()
		{
		return self::ioc ('router');
		}
	
	/**
	 * @return \limbo\view
	 */
	public static function view ()
		{
		return self::ioc ('view');
		}
	
	/**
	 * @return \limbo\web\flash
	 */
	public static function flash ()
		{
		return self::ioc ('flash');
		}
	
	/********************************************************************************
	 * Setup the caching method to allow driver changing within the scripts
	 *******************************************************************************/
	
	/**
	 * Returns the container for the cache object utilizing the driver they requested
	 *
	 * @param string $driver The caching driver to use (defaults to the config setting)
	 * 
	 * @return \limbo\cache\cache IOC of the cache + driver object. Null if caching is off
	 */
	public static function cache ($driver = '')
		{
		if (config ('cache.manual'))
			{
			$driver = (empty ($driver)) ? config ('cache.driver') : $driver;
			
			if (empty (self::$ioc['cache-' . $driver]))
				{
				self::$ioc['cache.driver'] = $driver;
				
				self::$ioc["cache_{$driver}"] = function ($c)
					{
					$driver = '\\limbo\\cache\\drivers\\' . $c['cache.driver'];
					
					return new $driver ();
					};
				}
			
			return self::ioc ("cache_{$driver}");
			}
		
		return null;
		}
	
	/********************************************************************************
	 * Flash messaging methods
	 *******************************************************************************/
	
	/**
	 * Sets up a flash message for the next or current request.
	 * 
	 * @param string $group		The name of the messages grouping (error, info, etc.)
	 * @param string $message	The actual message to send
	 * @param bool   $now		Is the message for the current request?
	 */
	public static function flash_set ($group, $message, $now = false)
		{
		if ($now)
			{
			self::flash ()->now ($group, $message);
			}
			else
			{
			self::flash ()->set ($group, $message);
			}
		}
	
	/**
	 * Takes all the current flash messages and makes them available for the next request. You
	 * can optionally specify a specific flash group to keep. If one is not specified then all
	 * flash messages from all groups are held over.
	 * 
	 * @param string $group The optional name of the group messages to keep.
	 */
	public static function flash_save ($group = '')
		{
		self::flash ()->keep ($group);
		}
	
	/**
	 * Get an array of flash messages for the current request. You can optionally specify a flash
	 * group to limit your messages by.
	 * 
	 * @param string $group The group to filter your messages by (if empty, return all messages)
	 *
	 * @return array An array of flash messages
	 */
	public static function flash_get ($group = '')
		{
		if (! empty ($group))
			{
			return self::flash ()->get ($group);
			}
		
		return self::flash ()->get_all ();
		}
	
	/**
	 * Clear out any flash messages for a group. You can also clear out all messages if the group is
	 * not specified.
	 *
	 * @param string $group The name of the group to clear (clear all if empty)
	 */
	public static function flash_clear ($group = '')
		{
		self::flash ()->clear ($group);
		}
	
	/**
	 * Sometimes we'll want to close out the session data before the page is finished. This method
	 * allows us to save the queued flash messages before the object destructs on it's own.
	 */
	public static function flash_close ()
		{
		self::flash ()->close ();
		}
	
	/********************************************************************************
	 * Rendering methods
	 *******************************************************************************/
	
	/**
	 * Render a template as a key or send to the output buffer.
	 * 
	 * @param string $file		The name (or full path) to the template file to render
	 * @param array  $data		An array of data to make available to the file
	 * @param string $key		Save the output of this rendering to this variable name
	 * @param bool   $absolute	True if $file contains the full path
	 */
	public static function render ($file, array $data = array (), $key = '', $absolute = false)
		{
		if (! $absolute)
			{
			// Try to figure out the full path to the file
			$file = self::view ()->file_path ($file);
			}
		
		if (! empty ($key))
			{
			// We want to save this output into a variable called $key
			self::view ()->set ($key, self::view ()->file_fetch ($file, $data));
			}
			else
			{
			// Just render the page and send any additional data
			self::view ()->render ($file, $data);
			}
		}
	
	/********************************************************************************
	 * Misc methods
	 *******************************************************************************/
	
	/**
	 * Interface with the IoC container. You can grab objects and variables by simply
	 * passing the name of the object you want. If you specify a value (array, string,
	 * object, etc.) then it will attempt to set that data in the IoC.
	 * 
	 * @param string     $name
	 * @param null|mixed $value
	 *
	 * @return mixed
	 */
	public static function ioc ($name, $value = null)
		{
		if ($value !== null)
			{
			self::$ioc[$name] = $value;
			}
		
		return self::$ioc[$name];
		}
	
	/**
	 * This method is used to reply to ajax queries mostly. Resets the response object
	 * and turns off any caching. If the first paramater is an array, we'll automatically
	 * json_decode() it.
	 * 
	 * @param array|string $data	A string of data, or an array to encode
	 * @param int          $code	The HTTP response code to send
	 */
	public static function json ($data, $code = 200)
		{
		$body = (is_array ($data)) ? json_encode ($data) : $data;
		
		self::response ()
			->clear ()
			->cache (false)
			->status ($code)
			->header ('Content-Type', 'application/json')
			->write ($body)
			->send ();
		}
	
	/**
	 * Our normal error handler method. Takes in the standard error variables and deals
	 * with them accordingly.
	 * 
	 * @param int    $number	The error number
	 * @param string $string	The error string / message
	 * @param string $file		The file the error was triggered in
	 * @param string $line		The line number that triggered the error
	 *
	 * @throws \limbo\error
	 */
	public static function error_handler ($number, $string = '', $file = '', $line = '')
		{
		// Don't report any errors if this error is suppressed
		if (error_reporting () === 0 || ! ($number & error_reporting ()))
			{
			return;
			}
		
		log::error ("{$string} ({$file} [{$line}])");
		
		if ($number & (E_NOTICE + E_WARNING + E_USER_NOTICE + E_USER_WARNING + E_USER_ERROR))
			{
			echo "<b>Error</b>: {$string} ({$file} [{$line}])<br>";
			}
			else
			{
			throw new \limbo\error ("{$string} ({$file} [{$line}])");
			}
		}
	
	/**
	 *  Figure out where we were called from
	 * 
	 * @return string cli or web
	 */
	public static function invoked_from ()
		{
		if (php_sapi_name () === 'cli' OR defined ('STDIN'))
			{
			return 'cli';
			}
		
		return 'web';
		}
	
	/**
	 * Stops the application gracefully
	 * 
	 * @param int $code An optional HTTP response code
	 */
	public static function stop ($code = 200)
		{
		self::response ()
			->status ($code)
			->write (web::clear_buffer ())
			->send ();
		}
	
	/**
	 * Halts the application and delivers the supplied message
	 * 
	 * @param int    $code		An optional HTTP response code
	 * @param string $message	An optional message to display
	 */
	public static function halt ($code = 200, $message = '')
		{
		log::warning ("Halting with code {$code} {$message}");
		
		self::response ()
			->clear ()
			->cache (false)
			->status ($code)
			->write ($message)
			->send ();
		}
	}

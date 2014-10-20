<?php
namespace limbo\web;

/**
 * This class is used to store route objects for processing by the router class. This class
 * was heavily inspired by the Flight framework.
 * 
 * Class route
 * @package limbo\web
 */
class route
	{
	/**
	 * @var string The pattern to use for the route (URL)
	 */
	public $pattern;
	
	/**
	 * @var mixed The callback function to process the route
	 */
	public $callback;
	
	/**
	 * @var array The HTTP methods to match against
	 */
	public $methods = array ();
	
	/**
	 * @var array The route parameters
	 */
	public $params = array ();
	
	/**
	 * @var string The compiled regular expression
	 */
	public $regex;
	
	/**
	 * @var string The contents of the last segment
	 */
	public $splat = '';
	
	/**
	 * @var bool Do we want to pass ourself into the callback parameters?
	 */
	public $pass = false;
	
	/**
	 * The main builder for the route. All options are passed to the constructor and built placed
	 * into the router object (if we're called from there like we should be).
	 * 
	 * @param string $pattern	The URL pattern to match
	 * @param mixed  $callback	The Closure to execute upon matching
	 * @param array  $methods	The HTTP method(s) to require
	 * @param bool   $pass		Pass oneself back to the Closure?
	 */
	public function __construct ($pattern, $callback, $methods, $pass)
		{
		$this->pattern 	= $pattern;
		$this->callback = $callback;
		$this->methods 	= $methods;
		$this->pass 	= $pass;
		}
	
	/**
	 * Determines if the supplied URL matches the pattern for this object. I really liked the way
	 * that the Flight framework matched URLs, so I kind of stole it.
	 * 
	 * @param string $url The URL to match against
	 *
	 * @return bool
	 */
	public function match ($url)
		{
		if ($this->pattern === '*' || $this->pattern === $url)
			{
			if ($this->pass)
				{
				$this->params[] = $this;
				}
		
			return true;
			}

		// If the last character is a wildcard, build the splat
		if (substr ($this->pattern, -1) === '*')
			{
			$found	= 0;
			$length = strlen ($url);
			$count 	= substr_count ($this->pattern, '/');

			for ($i = 0; $i < $length; $i ++)
				{
				if ($url[$i] == '/')
					{
					$found ++;
					}
				
				if ($found == $count) break;
				}

			$this->splat = (string) substr ($url, $i + 1);
			}
		
		// Start building the regular expression. Thanks Mike Cao!
		$ids	= array ();
		$regex	= str_replace (array (')','/*'), array (')?','(/?|/.*?)'), $this->pattern);
		$regex	= preg_replace_callback (
			'#@([\w]+)(:([^/\(\)]*))?#',
			function ($matches) use (&$ids)
				{
				$ids[$matches[1]] = null;
				
				if (isset($matches[3]))
					{
					return '(?P<' . $matches[1] . '>' . $matches[3] . ')';
					}
				
				return '(?P<' . $matches[1] . '>[^/\?]+)';
				}, $regex);

		// Make sure we're ending the pattern with the proper slash
		$regex .= (substr ($this->pattern, -1) === '/') ? '?' : '/?';

		// If the route matches then build the parameter list
		if (preg_match ('#^' . $regex . '(?:\?.*)?$#i', $url, $matches))
			{
			foreach ($ids as $k => $v)
				{
				$this->params[$k] = (array_key_exists ($k, $matches)) ? urldecode ($matches[$k]) : null;
				}

			if ($this->pass)
				{
				$this->params[] = $this;
				}
			
			// Save the build expression for later
			$this->regex = $regex;

			return true;
			}
		
		return false;
		}

	/**
	 * Checks if the HTTP method matches the allowed route method(s)
	 *
	 * @param string $method The HTTP method to match against
	 * 
	 * @return bool
	 */
	public function method ($method)
		{
		return count (array_intersect (array ($method, '*'), $this->methods)) > 0;
		}
	
	/**
	 * Execute the callback for this route
	 * 
	 * @return mixed
	 */
	public function dispatch ()
		{
		return call_user_func_array ($this->callback, array_values ($this->params));
		}
	}
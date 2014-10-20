<?php
namespace limbo\web;

use \limbo\log;

/**
 * This class is used to build and maintain all the routes used for this application. You build your
 * routes using the build() method and they can be matched against the stored routes by using the route()
 * method.
 * 
 * Examples:
 * \limbo::router()->build ('/', function () {
 * 		echo 'hello world';
 * 		});
 * 
 * Specifying the method(s):
 * \limbo::router()->build ('POST|PUT /web/form', function () {
 * 		echo 'You put or posted!';
 * 		});
 * 
 * Required parameters:
 * \limbo::router()->build ('/user/@name', function ($name) {
 * 		echo "Hello {$name}!";
 * 		});
 * 
 * Parameters with regular expressions:
 * \limbo::router()->build ('/user/@id[0-9]{3}', function ($id) {
 * 		// Matches /user/123
 * 		// Wont match /user/1234
 * 		});
 * 
 * Optional parameters:
 * \limbo::router()->build ('/user/@name(/@action)', function ($name, $action) {
 * 		// Matches /user/scott/
 * 		// Matches /user/scott/delete
 * 		});
 * 
 * If built in /testing/app/config.inc.php:
 * \limbo::router()->build ('route_name', function () {
 * 		// Matches /testing/app/route_name
 * 		});
 * 
 * You can stop processing the route and continue on to the next one by simply returning true. If you return
 * false no more routes will be processed. You can also call the bail() method to clean up any potential 
 * output your route generated.
 * 
 * Class router
 * @package limbo\web
 */
class router implements \Iterator
	{
	protected $routes = array ();
	protected $index = 0;
	
	/**
	 * Returns the collection of routes in this object
	 * 
	 * @return array
	 */
	public function get_routes ()
		{
		return $this->routes;
		}
	
	/**
	 * Clear the routes array out, deleting any stored routes.
	 */
	public function clear ()
		{
		$this->routes = array();
		}
	
	/**
	 * Clear the buffer and bail out of the route. Continue to the next one. Called from
	 * a route this way:
	 * 
	 * return \limbo::$router->bail();
	 * 
	 * @param bool $continue Continue on to the next route
	 * 
	 * @return bool
	 */
	public function bail ($continue = true)
		{
		web::clear_buffer ();
		
		return ($continue) ? true : false;
		}
	
	/**
	 * Resets the routes index back to zero so you can iterate over them again
	 */
	public function reset ()
		{
		$this->index = 0;
		}
	
	/**
	 * Creates a new route. Using this method you specify the pattern, callback and if the route
	 * object should be passed back to the route. The pattern contains the URL you want to match
	 * for the route. You can limit the methods to match against at the beginning of the pattern.
	 * 
	 * Example:
	 * \limbo::$route->build ('/my/path', ...
	 * \limbo::$route->build ('POST /my/path', ...
	 * \limbo::$route->build ('POST|PUT /my/path', ...
	 * 
	 * If you don't specify a full path starting with a '/', then we'll prepend the current
	 * path to the beginning of the URL. This is useful when specifying routes inside 
	 * different sections of the website.
	 * 
	 * @param string $pattern		The method and pattern to match against
	 * @param mixed  $callback		The closure function to execute upon a match
	 * @param bool   $pass_route	Do you want to pass back the route object?
	 */
	public function build ($pattern, $callback, $pass_route = false)
		{
		$url		= $pattern;
		$methods 	= array ('*');
		
		// Check to see if we're specifying a method
		if (strpos ($pattern, ' ') !== false)
			{
			list ($method, $url) = explode(' ', trim ($pattern), 2);
			
			$methods = explode ('|', $method);
			}
		
		if ($url{0} !== '/' && $url{0} !== '*')
			{
			// If we don't specify the full path, prepend our local path
			$url = \limbo::request ()->path . $url;
			}
		
		// Build the route and throw it in the array
		$this->routes[] = new route ($url, $callback, $methods, $pass_route);
		
		log::debug ('Loaded a new route: ' . $url);
		}
	
	/**
	 * Loops though the list of routes looking for a match.
	 * 
	 * @param request $request The request object for this request
	 *
	 * @return bool|mixed
	 */
	public function route (request $request)
		{
		while (($route = $this->current ()) !== false)
			{
			if ($route->method ($request->method) && $route->match ($request->pid))
				{
				log::debug ("Found matched route: {$route->pattern}");
				
				return $route;
				}
			
			$this->next();
			}

		return false;
		}
	
	/********************************************************************************
	 * Iterator methods
	 *******************************************************************************/
	
	public function current ()
		{
		return isset ($this->routes[$this->index]) ? $this->routes[$this->index] : false;
		}
	
	public function next ()
		{
		$this->index ++;
		}
	
	public function rewind ()
		{
		$this->index --;
		}
	
	public function valid ()
		{
		return ($this->routes[$this->index]);
		}
	
	public function key ()
		{
		return $this->index;
		}
	}

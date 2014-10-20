<?php
namespace limbo;

/**
 * This class generates content from the templates. It can also store variables to pass
 * on to the template during render time.
 * 
 * Class view
 * @package limbo
 */
class view
	{
	/**
	 * @var string The path to the template directory
	 */
	private $path;
	
	/**
	 * @var array A list of variables to pass on to the template
	 */
	private $variables = array ();
	
	/**
	 * Build the view object and setup some defaults
	 */
	public function __construct ()
		{
		$this->path = config ('path.views');
		}
	
	/**
	 * Retrieve a variable from the class. If no variable is set then return an array
	 * of all the variables.
	 * 
	 * @param string|bool $var The variable to get
	 * 
	 * @return array if no variable is specified
	 * @return mixed if the variable is set
	 * @return bool if the variable is not set
	 */
	public function get ($var = false)
		{
		if ($var == false)
			{
			return $this->variables;
			}
		
		if (isset ($this->variables[$var]))
			{
			return $this->variables[$var];
			}
		
		return false;
		}
	
	/**
	 * Sets a new variable into the object. You can send an array with key => value pairs as
	 * the first parameter if you want to set more than one variable in a single call.
	 * 
	 * @param array|string $var		The variable name to set or an array of variables
	 * @param null|mixed   $value	The value of the variable
	 */
	public function set ($var, $value = null)
		{
		if (is_array ($var) || is_object ($var))
			{
			foreach ($var as $key => $value)
				{
				$this->variables[$key] = $value;
				}
			}
			else
			{
			$this->variables[$var] = $value;
			}
		}
	
	/**
	 * Clears a single variable or all the stored variables (by not sending a key).
	 * 
	 * @param null|string $var The name of the variable or null for all variables
	 */
	public function clear ($var = null)
		{
		if ($var === null)
			{
			$this->variables = array ();
			}
			else
			{
			unset ($this->variables[$var]);
			}
		}
	
	/**
	 * Extract the variables and execute the template file. You can specify an array 
	 * of variables to pass to the file.
	 * 
	 * @param string $template	The full path to the template file
	 * @param array  $data		An array of variables to pass on to the file
	 *
	 * @throws error if the template file is not readable
	 */
	public function render ($template, array $data = null)
		{
		if (! is_readable ($template))
			{
			throw new error ("Template file '{$template}' could not be read");
			}
		
		log::debug ("Rendering {$template}");
		
		if (is_array ($data))
			{
			$this->variables = array_merge ($this->variables, $data);
			}
		
		extract (\limbo::$globals);
		extract ($this->variables);
		
		require $template;
		}
	
	/**
	 * This method is used to capture the content of the template file and return it. It will not
	 * send it to the client.
	 * 
	 * @param string $file	The full path to the file to execute and capture
	 * @param array  $data	An optional array of variables to pass to the file
	 *
	 * @return string The output from executing the file
	 */
	public function file_fetch ($file, array $data = null)
		{
		ob_start ();
		
		$this->render ($file, $data);
		
		return ob_get_clean ();
		}
	
	/**
	 * Try to figure out the exact file (template) path.
	 * 
	 * @param string $file The name of the template or file
	 *
	 * @return string The full file path.
	 */
	public function file_path ($file)
		{
		if ((substr ($file, - 4) != '.php'))
			{
			$file .= '.php';
			}
		
		return $this->path . $file;
		}
	}

<?php
/*
 * This script loads various framework helpers. It basically takes those
 * simple static methods and assigns them to global functions that can
 * be called easily.
 */

if (! function_exists ('config'))
	{
	function config ($name = null, $value = null, array $options = array ())
		{
		$options = array_merge (array (
			'group' => 'limbo',
			'store' => false,
		    'sort'  => false,
			), $options);
		
		// Just dump the whole config from memory if no $name is specified
		if ($name === null && ksort (\limbo::$config))
			{
			return \limbo::$config;
			}
		
		// If the $name is an array, loop through and save each key => value pair
		if (is_array ($name))
			{
			foreach ($name as $key => $value)
				{
				config ($key, $value, $options);
				}
			
			return true;
			}
		
		// Save a single config option to memory / DB
		if ($value !== null)
			{
			\limbo::$config[$name] = $value;
			
			// Try to store this in the DB if the 'config' container has been setup
			if ($options['store'] && is_object (\limbo::ioc ('config')))
				{
				\limbo::config ($options['group'])->set ($name, $value);
				}
			
			return true;
			}
		
		// Check if $name exists and return the value
		if (isset (\limbo::$config[$name]))
			{
			if ($options['sort'])
				{
				switch ($options['sort'])
					{
					case 'sort':	sort (\limbo::$config[$name]); 	    break;
					case 'rsort':	rsort (\limbo::$config[$name]); 	break;
					case 'asort':	asort (\limbo::$config[$name]); 	break;
					case 'arsort':	arsort (\limbo::$config[$name]); 	break;
					case 'ksort':	ksort (\limbo::$config[$name]); 	break;
					case 'krsort':	krsort (\limbo::$config[$name]); 	break;
					}
				}
			
			return \limbo::$config[$name];
			}
		
		return null;
		}
	}

if (! function_exists ('register'))
	{
	function register ($global = null, $value = null)
		{
		if ($global === null)
			{
			return \limbo::$globals;
			}
		
		if (is_array ($global) || is_object ($global))
			{
			foreach ($global as $key => $value)
				{
				\limbo::$globals[$key] = $value;
				}
			
			return true;
			}
		
		if ($value !== null)
			\limbo::$globals[$global] = $value;
		
		if (isset (\limbo::$globals[$global]))
			return \limbo::$globals[$global];
		
		return null;
		}
	}

if (! function_exists ('is_registered'))
	{
	function is_registered ($global)
		{
		return isset (\limbo::$globals[$global]);
		}
	}

if (! function_exists ('redirect'))
	{
	function redirect ($location, $variables = array ())
		{
		limbo\web\web::redirect ($location, $variables);
		}
	}

if (! function_exists ('filelist'))
	{
	function filelist ($directory, $full_path = false, $recursive = true)
		{
		return limbo\util\file::filelist ($directory, $full_path, $recursive);
		}
	}

if (! function_exists ('shorten'))
	{
	function shorten ($input, $length, $html = false)
		{
		return limbo\util\strings::shorten ($input, $length, $html);
		}
	}

if (! function_exists ('update_title'))
	{
	function update_title ($input, $append = true)
		{
		limbo\web\web::update_title ($input, $append);
		}
	}

if (! function_exists ('hash_create'))
	{
	function hash_create ($input, $record_time = false)
		{
		return limbo\util\security::hash_create ($input, $record_time);
		}
	}

if (! function_exists ('hash_verify'))
	{
	function hash_verify ($hash, $input, $timeout = false)
		{
		return limbo\util\security::hash_verify ($hash, $input, $timeout);
		}
	}

if (! function_exists ('http'))
	{
	function http ()
		{
		if (! ($http = config ('web.http')))
			{
			$protocol	= (config ('web.ssl')) ? 'https' : 'http';
			$http		= $protocol . '://' . config ('web.domains')[0] . config ('web.root');
			}
		
		return $http;
		}
	}

if (! function_exists('dd'))
	{
	function dd ()
		{
		array_map (function ($x) { var_dump ($x); }, func_get_args()); die;
		}
	}

if (! function_exists ('auto_version'))
	{
	function auto_version ($file)
		{
		if (limbo::invoked_from () == 'cli' || ! ($mtime = filemtime ($_SERVER['DOCUMENT_ROOT'] . $file)))
			{
			return $file;
			}
		
		return preg_replace ('{\\.([^./]+)$}', ".{$mtime}.\$1", $file);
		}
	}
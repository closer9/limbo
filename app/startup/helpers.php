<?php
/*
 * This script loads various framework helpers. It basically takes those
 * simple static methods and assigns them to global functions that can
 * be called easily.
 */

if (! function_exists ('config'))
	{
	function config ($option = null, $value = null)
		{
		if ($option === null)
			{
			ksort (\limbo::$config);
			
			return \limbo::$config;
			}
		
		if (is_array ($option))
			{
			foreach ($option as $key => $value)
				\limbo::$config[$key] = $value;
			
			return true;
			}
		
		if ($value !== null)
			\limbo::$config[$option] = $value;
		
		if (isset (\limbo::$config[$option]))
			return \limbo::$config[$option];

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
		return limbo\util\string::shorten ($input, $length, $html);
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

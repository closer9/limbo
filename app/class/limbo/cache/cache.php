<?php
namespace limbo\cache;

use limbo\error;
use limbo\log;

abstract class cache implements driver
	{
	public function __construct (array $options = array ())
		{}
	
	public function __get ($name)
		{
		return $this->get ($name);
		}
	
	public function __set ($name, $data)
		{
		$value 	= $data[0];
		$time	= (isset ($data[1])) ? (int) $data[1] : 600;
		$option = (isset ($data[2])) ? $data[2] : array ();
		
		return $this->set ($name, $value, $time, $option);
		}
	
	public function set ($keyword, $value = '', $time = 0, $option = array ())
		{
		if (config ('cache.manual') == false)
			{
			return false;
			}
		
		if ((int) $time <= 0)
			{
			$time = 3600 * 24; // Default is 24 hours
			}
		
		$object = array (
			'value'			=> $value,
			'write_time'	=> time (),
			'expires_set'	=> $time,
			'expires_time'	=> time () + $time,
			);
		
		log::debug ("Setting new cached value for key '{$keyword}'");
		
		return $this->driver_set ($keyword, $object, $time, $option);
		}
	
	public function get ($keyword, $option = array ())
		{
		if (config ('cache.manual') == false)
			{
			return null;
			}
		
		log::debug ("Searching for cached key '{$keyword}'");
		
		if (($object = $this->driver_get ($keyword, $option)) == null)
			{
			return null;
			}
		
		return (isset ($option['all_keys']) && $option['all_keys']) ? $object : $object['value'];
		}
	
	public function info ($keyword)
		{
		return $this->get ($keyword, array ('all_keys'));
		}
	
	public function delete ($keyword, $option = array ())
		{
		log::debug ("Deleting cached key '{$keyword}'");
		
		return $this->driver_delete ($keyword, $option);
		}
	
	public function stats ($option = array ())
		{
		log::debug ("Fetching statistics for cached key '{$keyword}'");
		
		return $this->driver_stats ($option);
		}
	
	public function clean ($option = array ())
		{
		log::debug ("Cleaning up expired cache keys");
		
		return $this->driver_clean ($option);
		}
	
	public function flush ($option = array ())
		{
		log::debug ("Deleting all stored cache keys");
		
		return $this->driver_flush ($option);
		}
	
	public function existing ($keyword)
		{
		if (method_exists ($this, 'driver_existing'))
			{
			return $this->driver_existing ($keyword);
			}
		
		if ($this->get ($keyword) == null)
			{
			return false;
			}
		
		return true;
		}
	
	public function search ($query)
		{
		if (method_exists ($this, 'driver_search'))
			{
			return $this->driver_search ($query);
			}
		
		throw new error ('Search method is not supported by this driver');
		}
	
	public function increment ($keyword, $step = 1, array $option = array ())
		{
		if (($object = $this->get ($keyword, array ('all_keys' => true))) == null)
			{
			return false;
			}
		
		$value	= (int) $object['value'] + (int) $step;
		$time	= $object['expires_time'] - time ();
		
		$this->set ($keyword, $value, $time, $option);
		
		return true;
		}
	
	public function decrement ($keyword, $step = 1, array $option = array ())
		{
		if (($object = $this->get ($keyword, array ('all_keys' => true))) == null)
			{
			return false;
			}
		
		$value	= (int) $object['value'] - (int) $step;
		$time	= $object['expires_time'] - time ();
		
		$this->set ($keyword, $value, $time, $option);
		
		return true;
		}
	
	public function touch ($keyword, $time = 300, array $option = array ())
		{
		if (($object = $this->get ($keyword, array ('all_keys' => true))) == null)
			{
			return false;
			}
		
		$value	= $object['value'];
		$time	= $object['expires_time'] - time () + $time;
		
		$this->set ($keyword, $value, $time, $option);
		
		return true;
		}
	
	protected function encode ($data)
		{
		return serialize ($data);
		}
	
	protected function decode ($value)
		{
		if (($data = @unserialize ($value)) == false)
			{
			return $value;
			}
		
		return $data;
		}
	}
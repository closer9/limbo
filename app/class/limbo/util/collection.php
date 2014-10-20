<?php
namespace limbo\util;

/**
 * This is a basic collection class used to store data in a much more OOP style. Storing 
 * data in here is very much like storing it in an array, only better!
 * 
 * @package limbo\util
 */
class collection implements \ArrayAccess, \Iterator, \Countable
	{
	private $data;
	
	public function __construct (array $data = array ())
		{
		$this->data = $data;
		}
	
	/********************************************************************************
	 * Get/Set methods
	 *******************************************************************************/
	
	public function get_data ()
		{
		return $this->data;
		}
	
	public function set_data (array $data)
		{
		$this->data = $data;
		}
	
	public function clear ()
		{
		$this->data = array ();
		}
	
	public function keys ()
		{
		return array_keys ($this->data);
		}
	
	/********************************************************************************
	 * Magical methods
	 *******************************************************************************/
	
	public function __get ($key)
		{
		return isset ($this->data[$key]) ? $this->data[$key] : null;
		}
	
	public function __set ($key, $value)
		{
		$this->data[$key] = $value;
		}
	
	public function __isset ($key)
		{
		return isset($this->data[$key]);
		}
	
	public function __unset ($key)
		{
		unset($this->data[$key]);
		}
	
	/********************************************************************************
	 * Array methods
	 *******************************************************************************/
	
	public function offsetExists ($offset)
		{
		return isset($this->data[$offset]);
		}
	
	public function offsetGet ($offset)
		{
		return isset($this->data[$offset]) ? $this->data[$offset] : null;
		}
	
	public function offsetSet ($offset, $value)
		{
		if (is_null ($offset))
			{
			$this->data[] = $value;
			}
			else
			{
			$this->data[$offset] = $value;
			}
		}
	
	public function offsetUnset ($offset)
		{
		unset($this->data[$offset]);
		}
	
	/********************************************************************************
	 * Iterator methods
	 *******************************************************************************/
	
	public function current ()
		{
		return current ($this->data);
		}
	
	public function key ()
		{
		return key ($this->data);
		}
	
	public function next ()
		{
		return next ($this->data);
		}
	
	public function rewind ()
		{
		reset ($this->data);
		}
	
	public function valid ()
		{
		$key = key ($this->data);
		
		return ($key !== null && $key !== false);
		}
	
	/********************************************************************************
	 * Countable methods
	 *******************************************************************************/
	
	public function count ()
		{
		return sizeof ($this->data);
		}
	}

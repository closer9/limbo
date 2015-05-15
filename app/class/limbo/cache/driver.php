<?php
namespace limbo\cache;

interface driver
	{
	function __construct (array $options = array ());
	
	/*
	 * Check if this Cache driver is available for server or not
	 */
	function driver_validate ();
	
	/*
	 * Save something to the cache
	 */
	function driver_set ($keyword, $value = '', $time = 600, array $option = array ());
	
	/*
	 * Return null or value of cache
	 */
	function driver_get ($keyword, array $option = array ());
	
	/*
	 * Show stats of the cache
	 * 
	 * @return array (
	 * 		'size'		= The total size used by the driver
	 * 		'data'		= The values stored by the driver
	 *		)
	 */
	function driver_stats (array $option = array ());
	
	/*
	 * Delete a cache
	 */
	function driver_delete ($keyword, array $option = array ());
	
	/*
	 * Clean up any expired data stored in the driver
	 */
	function driver_clean (array $option = array ());
	
	/*
	 * Flush the whole drivers data
	 */
	function driver_flush (array $option = array ());	
	}
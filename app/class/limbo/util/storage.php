<?php
namespace limbo\util;

use limbo\error;
use limbo\log;

/**
 * Collection of storage related methods
 *
 * @package limbo\util
 */
class storage
	{
	/**
	 * This method takes the $_FILES global and parses the array. For each file it finds it
	 * moves it to the storage directory and returns the file information.
	 * 
	 * @param string $app				An identifier for the group of storage files
	 * @param array $incoming_files		Normally the $_FILES global goes here
	 * @param bool $overwrite			Overwrite files in storage
	 *
	 * @return array of file(s) and their information (size, type, name, path)
	 */
	public static function put ($app, array $incoming_files = array (), $overwrite = false)
		{
		$output = array ();
		
		if (is_array ($incoming_files))
			{
			$files = array_shift ($incoming_files);
			
			if (is_array ($files['error']))
				{
				// Looks like there were multiple files uploaded
				foreach ($files['error'] as $key => $error)
					{
					if ($error == UPLOAD_ERR_OK && is_uploaded_file ($files['tmp_name'][$key]))
						{
						$filename = ($overwrite) ? $files['name'][$key] : self::file_check ($app, $files['name'][$key]);
						
						if (move_uploaded_file ($files['tmp_name'][$key], self::path ($app, $filename, true)))
							{
							log::debug ("STORAGE - Saved {$files['name'][$key]} for {$app}");
							
							$output[] = array (
								'path' => self::path ($app, $files['name'][$key], false),
								'name' => $filename,
								'mime' => $files['type'][$key],
								'size' => $files['size'][$key],
								);
							}
						}
					}
				}
				else
				{
				// A single file was uploaded
				if (isset ($files['error']) && $files['error'] == UPLOAD_ERR_OK)
					{
					if (is_uploaded_file ($files['tmp_name']))
						{
						$filename = ($overwrite) ? $files['name'] : self::file_check ($app, $files['name']);
						
						if (move_uploaded_file ($files['tmp_name'], self::path ($app, $filename, true)))
							{
							log::debug ("STORAGE - Saved {$files['name']} for {$app}");
							
							$output[] = array (
								'path' => self::path ($app, $files['name'], false),
								'name' => $filename,
								'mime' => $files['type'],
								'size' => $files['size'],
								);
							}
						}
					}
				}
			}
		
		return $output;
		}
	
	/**
	 * Return the contents of a file in storage.
	 * 
	 * @param string $app	The name of the storage group
	 * @param string $file	The name of the file
	 *
	 * @return bool|string File contents or false on failure
	 */
	public static function get ($app, $file)
		{
		log::debug ("STORAGE - Reading {$file} for {$app}");
		
		if (self::file_verify ($app, $file))
			{
			return file_get_contents (self::path ($app, $file, true));
			}
		
		return false;
		}
	
	/**
	 * Remove a file from storage.
	 *
	 * @param string $app	The name of the storage group
	 * @param string $file	The name of the file
	 *
	 * @return bool true on success, false on failure
	 * @throws error if we can't delete the file
	 */
	public static function remove ($app, $file)
		{
		log::debug ("STORAGE - Removing {$file} for {$app}");
		
		if (self::file_verify ($app, $file))
			{
			try {
				unlink (self::path ($app, $file, true));
				}
			catch (\Exception $e)
				{
				throw new error ("Unable to delete file {$file} for {$app}");
				}
			
			if (! self::file_verify ($app, $file))
				{
				return true;
				}
			}
		
		return false;
		}
	
	/**
	 * Calculates the proper path that the supplied file name would reside in.
	 * 
	 * @param string $app	The name of the storage group
	 * @param string $file	The name of the file
	 * @param bool $name	Append the file name at the end of the path
	 * 
	 * @return string The full path to the storage directory for the file
	 * @throws error If we can't create the storage path
	 */
	public static function path ($app, $file, $name = true)
		{
		$storage = strtolower (config ('path.storage') . "files/{$app}/");
		
		// Three directories by default, make sure the filename is long enough
		for ($a = 0; $a <= 2; $a ++)
			{
			if (! empty ($file[$a]))
				{
				if ($file[$a] == '.') break;
				
				$storage .= strtolower ("{$file[$a]}/");
				}
			}
		
		if (! is_dir ($storage))
			{
			$umask = umask (0);
			
			if (! mkdir ($storage, 0777, true))
				{
				throw new error ("Unable to create {$storage}");
				}
			
			umask ($umask);
			}
		
		return ($name) ? $storage . $file : $storage;
		}
	
	/**
	 * Checks if the filename supplied is already in storage and renames it.
	 * 
	 * @param string $app	The name of the storage group
	 * @param string $file	The name of the file
	 *
	 * @return string The (new) filename
	 */
	public static function file_check ($app, $file)
		{
		$output = $file;
		
		if (self::file_verify ($app, $output))
			{
			$output = string::increment ($output, '-', 1, true);
			
			while (self::file_verify ($app, $output) === true)
				{
				$output = string::increment ($output, '-', 1, true);
				}
			
			log::debug ("STORAGE - Renaming {$file} to {$output} for {$app}");
			}
		
		return $output;
		}
	
	/**
	 * Verifies if the file is in storage or not.
	 * 
	 * @param string $app	The name of the storage group
	 * @param string $file	The name of the file
	 *
	 * @return bool
	 */
	public static function file_verify ($app, $file)
		{
		log::debug ("STORAGE - Verifying {$file} for {$app}");
		
		if (is_file (self::path ($app, $file, true)))
			{
			return true;
			}
		
		return false;
		}
	
	/**
	 * Takes the supplied file and sends it to the browser.
	 * 
	 * @param string $app	The name of the storage group
	 * @param string $file	The name of the file
	 * @param string $type	The mime type of the file
	 * @param int $size		The size of the file
	 * @param bool $attach	Send the file as an attachment to the browser
	 *
	 * @return bool
	 */
	public static function download ($app, $file, $type = '', $size = 0, $attach = false, $cache = false)
		{
		log::debug ("STORAGE - Downloading {$file} for {$app}");
		
		if (self::file_verify ($app, $file))
			{
			$size = ($size) ? $size : file::info (self::path ($app, $file, true))['size'];
			$type = ($type) ? $type : file::info (self::path ($app, $file, true))['mime'];
			$mode = ($attach) ? 'attachment' : 'inline';
			
			\limbo::response()
				->cache ($cache)
				->header ('Content-Type', $type)
				->header ('Content-Lenth', $size)
				->header ('Content-Disposition', "{$mode}; filename=\"{$file}\"")
				->write (self::get ($app, $file))
				->send ();
			}
		
		\limbo::halt (200, 'Could not find the specified file in storage.');
		}
	}

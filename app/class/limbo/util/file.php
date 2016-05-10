<?php
namespace limbo\util;

/**
 * Collection of file related methods/functions
 * 
 * Class file
 * @package limbo\util
 */
class file
	{
	/**
	 * Returns the file size in easily readable format using the largest container
	 * 
	 * @param int $input	The size in bytes
	 *
	 * @return bool|string	False if unknown, otherwise it returns the readable size
	 */
	static function size ($input)
		{
		foreach (array ('bytes', 'KB', 'MB', 'GB', 'TB') as $x)
			{
			if ($input < 1024.0) return sprintf ("%3.1f %s", $input, $x);

			$input /= 1024.0;
			}
		
		return false;
		}
	
	/**
	 * Gets the size and mime type of the supplied file
	 * 
	 * @param string $file The full path + name of the file
	 *
	 * @return bool if the file can't be found
	 */
	public static function info ($file)
		{
		if (! is_file ($file))
			{
			\limbo\log::debug ('Could not find file ' . $file);
			
			return false;
			}
		
		$info = new \finfo (FILEINFO_MIME_TYPE);
		
		$output['mime'] = $info->file ($file);
		$output['size'] = filesize ($file);
			
		return $output;
		}
	
	/**
	 * Compresses a file on disk using GZIP (appending .gz to the name by default)
	 *
	 * @param string  $file_in		Path to the file that you want to compress
	 * @param integer $level		GZIP compression level (default: 9)
	 * @param bool    $delete		Delete the source file when successful
	 * @param string  $destination	Optional \path\name.gz of the compressed file
	 *
	 * @return string New filename (with .gz appended) if success, or false if operation fails
	 *
	 * @throws \limbo\error on failure
	 */
	static function compress ($file_in, $level = 9, $delete = false, $destination = null)
		{
		$file_out = (is_null ($destination)) ? "{$file_in}.gz" : $destination;
		
		if (! function_exists ('gzopen'))
			{
			\limbo\log::error ("GZIP library is unavailable (http://php.net/manual/en/zlib.installation.php)");
			
			return false;
			}
		
		if (! ($fp_out = gzopen ($file_out, "wb{$level}")))
			{
			\limbo\log::error ("COMPRESS - Unable to create file: {$file_out}");
			
			return false;
			}
		
		if (is_file ($file_in) && $fp_in = fopen ($file_in, 'rb'))
			{
			\limbo\log::info ("COMPRESS - Compressing file: {$file_in}");
			
			while (! feof ($fp_in))
				{
				gzwrite ($fp_out, fread ($fp_in, 1024 * 512));
				}
			
			fclose ($fp_in);
			
			gzclose ($fp_out);
			
			if ($delete)
				{
				if (! unlink ($file_in))
					\limbo\log::warning ("COMPRESS - Unable to delete the source file: {$file_in}");
				}
			}
			else
			{
			\limbo\log::error ("COMPRESS - Unable to read the source file: {$file_in}");
			
			gzclose ($fp_out);
			unlink ($file_out);
			
			return false;
			}
		
		return $file_out;
		}
	
	/**
	 * Traverses a directory returning all the files in that directory (recursively or not)
	 * 
	 * @param string $directory		The directory path to look at
	 * @param bool   $full_path		Return the full path or just the file names
	 * @param bool   $recursive		Recursively scan all sub directories
	 * @param string $name			Restrict output to a specific name
	 * @param bool   $_follow		Used when recursively scanning, used to init the static vars
	 *
	 * @return array|bool False if the initial directory is unreadable, returns the file list otherwise
	 */
	static function filelist ($directory, $full_path = false, $recursive = true, $name = '*', $_follow = false)
		{
		static $_source		= '';
		static $_filelist	= array ();

		if ($fp = @opendir ($directory))
			{
			if ($_follow === false)
				{
				$_filelist	= array ();
				$_source	= strlen ($directory);
				$directory	= rtrim (realpath ($directory), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
				}

			while (($file = readdir ($fp)) !== false)
				{
				if ($file[0] !== '.')
					{
					if ($recursive && is_dir ($directory . $file))
						{
						file::filelist ($directory . $file . DIRECTORY_SEPARATOR, $full_path, $name, $recursive, true);
						}
						else
						{
						if ($name != '*' && $file != $name)
							continue;
						
						if ($full_path === true)
							$_filelist[] = $directory . $file;
							else
							$_filelist[] = substr ($directory . $file, $_source);
						}
					}
				}

			closedir ($fp);
			
			return $_filelist;
			}

		return false;
		}
	
	/**
	 * Recursively deletes a directory and anything it contains.
	 *
	 * @param string $directory The directory to delete
	 *
	 * @return bool
	 */
	public static function delete_tree ($directory)
		{
		$files = array_diff (scandir ($directory), array ('.', '..'));
		
		foreach ($files as $file)
			{
			if (is_dir ("{$directory}/{$file}"))
				{
				self::delete_tree ("{$directory}/{$file}");
				}
			else
				{
				unlink ("{$directory}/{$file}");
				}
			}
		
		return rmdir ($directory);
		}
	
	/**
	 * Includes a file with the added benefit of extracting all our globals to it. Very useful
	 * if we want our global variables to be present in all included files.
	 * 
	 * @param $file
	 */
	public static function import ($file)
		{
		extract (\limbo::$globals);
		
		include $file;
		}
	}
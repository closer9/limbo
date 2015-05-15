<?php
namespace limbo\cache\drivers;

use limbo\cache\cache;
use limbo\cache\driver;
use limbo\util\file;
use limbo\error;
use limbo\log;

class files extends cache implements driver
	{
	public function __construct (array $options = array ())
		{
		parent::__construct ($options);
		
		if (is_writable (config ('path.storage')))
			{
			if (! is_dir (config ('cache.path')))
				{
				$mask = umask (0);
				
				mkdir (config ('cache.path'), 0777, true);
				
				umask ($mask);
				}
			}
		
		if (! $this->driver_validate () && ! isset ($options['skip_error']))
			{
			throw new error ("Unable to validate the cache driver");
			}
		
		$this->driver_clean ();
		}
	
	public function driver_validate ()
		{
		if (! @is_writable (config ('cache.path')))
			{
			throw new error ('Unable to write to the cache directory');
			}
		
		return true;
		}
	
	private function encode_filename ($keyword)
		{
		return trim (trim (preg_replace ("/[^a-zA-Z0-9]+/", "_", $keyword), "_"));
		}
	
	private function file ($keyword)
		{
		$file = $this->encode_filename ($keyword) . '.cache';
		$path = strtolower (config ('cache.path'));
		
		// Making sure the keyword is long enough
		for ($a = 0; $a <= 2; $a ++)
			{
			if (! empty ($file[$a]))
				{
				if ($file[$a] == '.') break;
				
				$path .= strtolower ("{$file[$a]}/");
				}
			}
		
		if (! is_dir ($path))
			{
			$umask = umask (0);
			
			if (! mkdir ($path, 0777, true))
				{
				throw new error ("Unable to create {$path}");
				}
			
			umask ($umask);
			}
		
		return $path . $file;
		}
	
	
	public function driver_set ($keyword, $value = '', $time = 600, array $option = array ())
		{
		$writable = true;
		
		$file = $this->file ($keyword);
		$data = $this->encode ($value);
		
		if (isset ($option['skip']) && $option['skip'] == true && file_exists ($file))
			{
			$writable	= false;
			$content	= file_get_contents ($file);
			
			if ($this->expired ($this->decode ($content)))
				{
				$writable = true;
				}
			}
		
		if ($writable == true)
			{
			try {
				$f = fopen ($file, 'w+');
				
				fwrite ($f, $data);
					
				fclose ($f);
				}
			catch (\Exception $e)
				{
				log::error ("Unable to write to the cache file. {$e}");
				
				return false;
				}
			}
		
		return true;
		}
	
	public function driver_get ($keyword, array $option = array ())
		{
		$file = $this->file ($keyword);
		
		if (! file_exists ($file))
			{
			return null;
			}
		
		$object = $this->decode (file_get_contents ($file));
		
		$object['file'] = $file;
		
		if ($this->expired ($object))
			{
			@unlink ($file);
			
			return null;
			}
		
		return $object;
		}
	
	public function driver_delete ($keyword, array $option = array ())
		{
		if (@unlink ($this->file ($keyword)))
			{
			return true;
			}
		
		return false;
		}
	
	public function driver_stats (array $option = array ())
		{
		$files	= file::filelist (config ('cache.path'), true);
		$total	= 0;
		$data	= array ();
		
		foreach ($files as $file)
			{
			$total += filesize ($file);
			$object = $this->decode (file_get_contents ($file));
			$data[] = $object['value'];
			}
		
		return array (
			'size'	=> $total,
			'data'	=> $data,
			);
		}
	
	public function driver_clean (array $option = array ())
		{
		$files		= file::filelist (config ('cache.path'), true);
		$cleanup	= 0;
		
		foreach ($files as $file)
			{
			$size      = filesize ($file);
			$object    = $this->decode (file_get_contents ($file));
			
			if ($this->expired ($object))
				{
				@unlink ($file);
				
				$cleanup += $size;
				}
			}
		
		return $cleanup;
		}
	
	public function driver_flush (array $option = array ())
		{
		file::delete_tree (config ('cache.path'));
		
		$mask = umask (0);
		
		mkdir (config ('cache.path'), 0777, true);
		
		umask ($mask);
		}
	
	public function expired (array $object)
		{
		if (isset ($object['expires_time']) && time () >= $object['expires_time'])
			{
			return true;
			}
		
		return false;
		}
	}

<?php
namespace limbo\util;

use \limbo\log;

class security
	{
	/**
	 * Create a verification hash to make sure variables are not tampered with.
	 *
	 * @param array	$input 	 		List of variables to include
	 * @param mixed	$record_time	UNIX timestamp / true or false
	 * 
	 * @return string md5_hash
	 */
	public static function hash_create ($input, $record_time = false)
		{
		$input = (! is_array ($input)) ? array ($input) : $input;

		if ($record_time)
			{
			// If record time is true, just use the current time
			if (is_bool ($record_time))
				{
				$input[] = time ();
				}
				else
				{
				// Otherwise use the specified time
				$input[] = $record_time;
				}
			}
		
		// Setup the final string to hash, then hash it
		$real = config ('secure.salt') . '-(' . implode ('+', $input) . ')';
		$hash = md5 ($real);
		
		log::debug ('Hash create: ' . $real);
		log::debug ('Hash final: ' . $hash);

		return $hash;
		}

	/**
	 * Verify an already created hash against variables.
	 *
	 * @param string $hash		The hash you are testing
	 * @param array  $input		List of variables to test in the hash
	 * @param int    $timeout	Timeout length in seconds / false
	 * 
	 * @return bool
	 */
	public static function hash_verify ($hash, $input, $timeout = false)
		{
		$ctime = time ();

		if ($timeout)
			{
			// Loop backwards in time looking for a matching timestamp in the hash
			for ($time = time (); $time > $ctime - $timeout; $time --)
				{
				if ($hash == self::hash_create ((array) $input, $time))
					{
					return true;
					}
				}
			}
			else
			{
			if ($hash == self::hash_create ((array) $input))
				{
				return true;
				}
			}

		return false;
		}
	
	/**
	 * Encrypt data
	 *
	 * This method will encrypt data using a given key, vector, and cipher.
	 * By default, this will encrypt data using the RIJNDAEL/AES 256 bit cipher. You
	 * may override the default cipher and cipher mode by passing your own desired
	 * cipher and cipher mode as the final key-value array argument.
	 *
	 * @param  string $data The unencrypted data
	 * @param  string $key The encryption key
	 * @param  string $iv The encryption initialization vector
	 * @param  array  $settings Optional key-value array with custom algorithm and mode
	 * 
	 * @return string
	 */
	public static function encrypt ($data, $key, $iv, $settings = array ())
		{
		if ($data === '' || !extension_loaded ('mcrypt'))
			{
			return $data;
			}
		
		// Merge settings with defaults
		$settings = array_merge (array (
			'algorithm' => MCRYPT_RIJNDAEL_256,
			'mode'      => MCRYPT_MODE_CBC
			), $settings);
		
		$module 	= mcrypt_module_open ($settings['algorithm'], '', $settings['mode'], '');
		$ivSize		= mcrypt_enc_get_iv_size ($module);
		$keySize 	= mcrypt_enc_get_key_size ($module);
		
		// Validate IV
		if (strlen ($iv) > $ivSize)
			{
			$iv = substr ($iv, 0, $ivSize);
			}
		
		// Validate key
		if (strlen ($key) > $keySize)
			{
			$key = substr ($key, 0, $keySize);
			}
		
		// Encrypt value
		mcrypt_generic_init ($module, $key, $iv);
		$res = @mcrypt_generic ($module, $data);
		mcrypt_generic_deinit ($module);
		
		return $res;
		}
	
	/**
	 * Decrypt data
	 *
	 * This method will decrypt data using a given key, vector, and cipher.
	 * By default, this will decrypt data using the RIJNDAEL/AES 256 bit cipher. You
	 * may override the default cipher and cipher mode by passing your own desired
	 * cipher and cipher mode as the final key-value array argument.
	 *
	 * @param  string $data The encrypted data
	 * @param  string $key The encryption key
	 * @param  string $iv The encryption initialization vector
	 * @param  array  $settings Optional key-value array with custom algorithm and mode
	 * @return string
	 */
	public static function decrypt ($data, $key, $iv, $settings = array ())
		{
		if ($data === '' || !extension_loaded ('mcrypt'))
			{
			return $data;
			}
		
		// Merge settings with defaults
		$settings = array_merge (array (
			'algorithm' => MCRYPT_RIJNDAEL_256,
			'mode'      => MCRYPT_MODE_CBC
			), $settings);
		
		$module 	= mcrypt_module_open ($settings['algorithm'], '', $settings['mode'], '');
		$ivSize 	= mcrypt_enc_get_iv_size ($module);
		$keySize 	= mcrypt_enc_get_key_size ($module);
		
		// Validate IV
		if (strlen ($iv) > $ivSize)
			{
			$iv = substr ($iv, 0, $ivSize);
			}
		
		// Validate key
		if (strlen ($key) > $keySize)
			{
			$key = substr ($key, 0, $keySize);
			}
		
		// Decrypt value
		mcrypt_generic_init ($module, $key, $iv);
		$decryptedData = @mdecrypt_generic ($module, $data);
		$res = rtrim ($decryptedData, "\0");
		mcrypt_generic_deinit ($module);
		
		return $res;
		}
	
	
	/**
	 * Hashes the specified string using bcrypt
	 * 
	 * @param 	string 		$input 		The string you would like to hash
	 * @param 	int    		$rounds		Number of rounds to calculate (bigger = longer)
	 * @return 	string					The hashed string
	 */
	public static function bcrypt ($input, $rounds = 12)
		{
		if (CRYPT_BLOWFISH != 1)
			{
			throw new \limbo\error ('BCRYPT not supported in this installation.');
			}
		
		$work = str_pad ($rounds, 2, '0', STR_PAD_LEFT);
		
		if (function_exists ('openssl_random_pseudo_bytes'))
			{
			$salt = openssl_random_pseudo_bytes (16);
			}
			else
			{
			$salt = self::generate ('symbols', 40);
			}
		
		$salt = substr (strtr (base64_encode ($salt), '+', '.'), 0 , 22);
		
		return crypt ($input, '$2a$' . $work . '$' . $salt);
		}
	
	/**
	 * Generate a random string using special characters
	 * 
	 * @param 	string 	$method		The method used to generate the string
	 * @param 	int    	$length		The character length
	 * @return 	string
	 */
	public static function generate ($method = 'symbols', $length = 16)
		{
		$return		= '';
		$symbols	= array ('@', '#', '$', '%', '*', '+', '-', '.', '?', '_', '%');
		$numbers	= array ('2', '3', '4', '5', '6', '7', '8', '9');
		$letters	= array ('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'j', 'k', 'm', 'n', 'p',
							 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');
		
		switch ($method)
			{
			case 'md5':		return md5 (uniqid (mt_rand ()));
			case 'sha1':	return sha1 (uniqid (mt_rand (), true));
			case 'alpha':	$haystack = $letters; break;
			case 'numeric':	$haystack = $numbers; break;
			case 'mixed':	$haystack = array_merge ($numbers, $letters); break;
			
			default:
				$haystack = array_merge ($numbers, $letters, $symbols);
				break;
			}
		
		for ($a = 0; $a < $length; $a ++)
			{
			$return .= $haystack[rand (0, count ($haystack) - 1)];
			}
		
		return $return;
		}
	
	public static function generate_iv ()
		{
		$size	= mcrypt_get_iv_size (MCRYPT_CAST_256, MCRYPT_MODE_CFB);
		$iv		= mcrypt_create_iv ($size, MCRYPT_DEV_RANDOM);
		
		return $iv;
		}
	}
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
	 * @param string   $hash    The hash you are testing
	 * @param array    $input   List of variables to test in the hash
	 * @param int|bool $timeout Timeout length in seconds / false
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
	 * @param string $data     Data to be encrypted
	 * @param string $password The encryption password
	 * @param string $method   The cipher method to use
	 *
	 * @return string
	 * @throws \error
	 */
	public static function encrypt ($data, $password, $method = 'AES-256-CTR')
		{
		if (mb_strlen ($password, '8bit') !== 32)
			{
			throw new \error ("The encryption password needs to be 256-bit");
			}
		
		$length   = openssl_cipher_iv_length ($method);
		$password = openssl_digest ($password, 'md5', true);
		$iv       = self::generate_iv ($length);
		$data     = openssl_encrypt ($data, $method, $password, OPENSSL_RAW_DATA, $iv);
		
		return $iv . $data;
		}
	
	/**
	 * Decrypt data
	 *
	 * @param string $data     Data to be decrypted
	 * @param string $password The encryption password
	 * @param string $method   The cipher method to use
	 *
	 * @return string
	 * @throws \error
	 */
	public static function decrypt ($data, $password, $method = 'AES-256-CTR')
		{
		if (mb_strlen ($password, '8bit') !== 32)
			{
			throw new \error ("The encryption password needs to be 256-bit");
			}
		
		$length   = openssl_cipher_iv_length ($method);
		$password = openssl_digest ($password, 'md5', true);
		$iv 	  = mb_substr ($data, 0, $length, '8bit');
		$data	  = mb_substr ($data, $length, null, '8bit');
		
		return openssl_decrypt ($data, $method, $password, OPENSSL_RAW_DATA, $iv);
		}
	
	/**
	 * Generates a random string to use as an initialization vector when encrypting
	 * 
	 * @param int $length The length of the IV to generate
	 * 
	 * @return string
	 */
	public static function generate_iv ($length = 16)
		{
		if (function_exists ('random_bytes'))
			{
			return random_bytes ($length);
			}
		
		return openssl_random_pseudo_bytes ($length);
		}
	
	/**
	 * Hashes the specified string using bcrypt
	 * 
	 * @param  string $input  The string you would like to hash
	 * @param  int    $rounds Number of rounds to calculate (bigger = longer)
	 * 
	 * @return string
	 * @throws \error
	 */
	public static function bcrypt ($input, $rounds = 12)
		{
		if (CRYPT_BLOWFISH != 1)
			{
			throw new \error ('BCRYPT not supported in this installation.');
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
	}
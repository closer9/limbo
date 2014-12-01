<?php
namespace limbo\util;

/**
 * Collection of string related methods/functions
 * 
 * @package limbo\util
 */
class string
	{
	/**
	 * Shorten up a string by the given length
	 * 
	 * @param string $input		The string to shorten
	 * @param int    $length	The max length for the string
	 * @param bool   $html		Strip any HTML tags
	 *
	 * @return string
	 */
	static function shorten ($input, $length, $html = false)
		{
		$input = ($html) ? $input : strip_tags ($input);

		if (strlen ($input) <= $length) return $input;

		$last_space		= strrpos (substr ($input, 0, $length), ' ');
		$last_space		= ($last_space == 0) ? $length : $last_space;
		$trimmed_text	= substr ($input, 0, $last_space) . '...';

		return $trimmed_text;
		}
	
	/**
	 * Increases or appends a number to a string. Useful for when saving multiple files.
	 * 
	 * @param string $string		The string to append/update
	 * @param string $separator		The seperator used before the number
	 * @param int $start			Where to start counting
	 * @param bool $extension		Check for a file extension and move of the separator
	 *
	 * @return string
	 */
	static function increment ($string, $separator = '-', $start = 1, $extension = false)
		{
		if ($extension)
			{
			if (strlen ($string) >= 5 && ($last = strrpos ($string, '.', 5)) !== false)
				{
				$ext	= substr ($string, $last + 1);
				$string = substr ($string, 0, $last);
				}
			}
		
		preg_match ('/(.+)' . $separator . '([0-9]+)$/', $string, $match);
		
		if (isset ($match[2]))
			{
			$output = $match[1] . $separator . ($match[2] + 1);
			}
			else
			{
			$output = $string . $separator . $start;
			}
		
		if ($extension && ! empty ($ext))
			{
			$output .= ".{$ext}";
			}
		
		return $output;
		}
	
	/**
	 * Lets you know if a string is contained inside another string.
	 * 
	 * @param string $string	The string to search inside of (haystack)
	 * @param array $needles	Array of needles to search for in the string
	 *
	 * @return bool
	 */
	static function contains ($string, $needles)
		{
		foreach ((array) $needles as $needle)
			{
			if ($needle != '' && strpos ($string, $needle) !== false) return true;
			}

		return false;
		}
	
	
	/**
	 * Makes sure that there are no doubles of the specified character. Useful for file paths.
	 * 
	 * @param string $string		The string to check
	 * @param string $character		The character to check for doubles of
	 * @param bool   $trim			Do we want to trim while were here?
	 *
	 * @return string
	 */
	static function reduce_multiples ($string, $character = ',', $trim = false)
		{
		$string = preg_replace ('#' . preg_quote ($character, '#') . '{2,}#', $character, $string);
		
		return (true === $trim) ? trim ($string, $character) : $string;
		}
	}
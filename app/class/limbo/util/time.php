<?php
namespace limbo\util;

/**
 * Collection of time related methods and functions
 * 
 * Class time
 * @package limbo\util
 */
class time
	{
	/**
	 * Get the current time in microtime format
	 * 
	 * @return float
	 */
	static function microtime_float ()
		{
		list ($usec, $sec) = explode (' ', microtime ());

		return ((float) $usec + (float) $sec);
		}
	
	/**
	 * Tells you how many days ago a date was
	 * 
	 * @param string $date A date from the past
	 *
	 * @return string
	 */
	static function natural_time ($date)
		{
		if (is_int ($date))
			{
			$stamp = strtotime (date ('Y-m-d', $date));
			}
			else
			{
			$stamp = strtotime (date ('Y-m-d', strtotime ($date)));
			}

		$difference	= time () - $stamp;
		$days_open	= floor ($difference / 60 / 60 / 24);

		if (date ('Y-m-d', $stamp) == date ('Y-m-d'))
			{
			$return = 'Today';
			}
		elseif (date ("Y-m-d", $stamp) == date ('Y-m-d', strtotime (date ('Y-m-d')) - 1))
			{
			$return = 'Yesterday';
			}
		else
			{
			$return = ($days_open == 1) ? $days_open . " Day ago" : $days_open . " Days ago";
			}

		return $return;
		}
	
	/**
	 * Takes in an amount of seconds and tells you how much time has passed in those seconds
	 * 
	 * @param int $since The amount of time that has passed in seconds
	 *
	 * @return string
	 */
	static function time_since ($since)
		{
		$count 	= 0;
		$name	= '';

		$chunks = array (
			array (60 * 60 * 24 * 365 , 'year'),
			array (60 * 60 * 24 * 30 , 'month'),
			array (60 * 60 * 24 * 7, 'week'),
			array (60 * 60 * 24 , 'day'),
			array (60 * 60 , 'hour'),
			array (60 , 'minute'),
			array (1 , 'second')
			);

		for ($i = 0, $j = count ($chunks); $i < $j; $i++)
			{
			$seconds	= $chunks[$i][0];
			$name		= $chunks[$i][1];

			if (($count = floor ($since / $seconds)) != 0)
				{
				break;
				}
			}

		return ($count == 1) ? '1 ' . $name : "$count {$name}s";
		}
	
	static function small_time ($seconds)
		{
		return self::time_since ($seconds);
		}
	
	/**
	 * Display the time difference in natural time for both past and future.
	 *
	 * > echo limbo\util\time::difference ('@' . (time() + 12345), 2)
	 * > 3 hours, 25 minutes from now
	 *
	 * > echo limbo\util\time::difference ('@' . (time() - 654321))
	 * > 1 week ago
	 *
	 * @param string $datetime	The @timestamp or Y-m-d H:i:s datetime
	 * @param int $depth		The amount of detail to display 1 = least, 7 = max
	 * @param bool $append		Append the 'ago' or 'from now' string
	 * 
	 * @return string The time in a natural string
	 */
	static function difference ($datetime, $depth = 1, $append = true)
		{
		$now	= new \DateTime;
		$marker	= new \DateTime ($datetime);
		$diff	= $now->diff ($marker);
		
		$mode	= ($marker->getTimestamp () < time ()) ? ' ago' : ' from now';
		
		$diff->w = floor ($diff->d / 7);
		$diff->d -= $diff->w * 7;
		
		$string = array (
			'y' => 'year',
			'm' => 'month',
			'w' => 'week',
			'd' => 'day',
			'h' => 'hour',
			'i' => 'minute',
			's' => 'second',
		);
		
		foreach ($string as $key => &$value)
			{
			if ($diff->$key)
				{
				$value = $diff->$key . ' ' . $value . ($diff->$key == 1 ? '' : 's');
				}
				else
				{
				unset ($string[$key]);
				}
			}
		
		$string = array_slice ($string, 0, ($depth));
		
		if (abs ($marker->getTimestamp () - $now->getTimestamp ()) < 30)
			{
			return 'Just now';
			}
		
		return implode (', ', $string) . (($append) ? $mode : '');
		}
	
	/**
	 * Modify a submitted string date or unix timestamp by adding or subtracting a
	 * specified number of days.
	 *
	 * @param mixed		$date	The date in string or timestamp
	 * @param string	$mode	The modifier
	 * @param int    	$day	The number of days to adjust by
	 * @param string 	$format The output format (string or stamp)
	 *
	 * @return int|string
	 */
	static function modify_date ($date, $mode = '+', $day = 1, $format = 'stamp')
		{
		// Check if the submitted date is a string or stamp
		if (self::validate_date ($date))
			{
			$date = strtotime ($date);
			}
		
		// Create the new date in timestamp format
		$return = strtotime (date ('Y-m-d', $date) . " {$mode} {$day} day");
		
		if ($format == 'string')
			{
			return date ('Y-m-d', $return);
			}
		
		return $return;
		}
	
	/**
	 * Validates a submitted date string
	 *
	 * @param string $date The date to validate
	 *
	 * @return bool Returns true if the date is valid, false if not
	 */
	static function validate_date ($date)
		{
		$validate = \DateTime::createFromFormat ('Y-m-d', $date);
		
		return ($validate && $validate->format('Y-m-d') == $date);
		}
	}
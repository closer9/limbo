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
	 *
	 * @return string The time in a natural string
	 */
	static function difference ($datetime, $depth = 1)
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
		
		return implode (', ', $string) . $mode;
		}
	}
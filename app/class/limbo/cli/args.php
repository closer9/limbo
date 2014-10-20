<?php
namespace limbo\cli;

/**
 * This class has methods to help with CLI argument passing
 * 
 * @package limbo\cli
 */
class args
	{
	/**
	 * Takes in the CLI argument list and spits them out into a nicely formatted array
	 * 
	 * Example:
	 * $flags = args::parse_args ($argv);
	 * 
	 * @param array $argv The argument list ($args)
	 *
	 * @return array
	 */
	static function parse_args ($argv)
		{
		array_shift ($argv);

		$out = array();

		foreach ($argv as $arg)
			{
			if (substr ($arg, 0, 2) == '--')
				{
				$eqPos = strpos ($arg, '=');

				if ($eqPos === false)
					{
					$key = substr ($arg, 2);
					$out[$key] = isset ($out[$key]) ? $out[$key] : true;
					}
					else
					{
					$key = substr ($arg, 2, $eqPos - 2);
					$out[$key] = substr ($arg, $eqPos + 1);
					}
				}

			else if (substr ($arg, 0, 1) == '-')
				{
				if (substr ($arg, 2, 1) == '=')
					{
					$key = substr ($arg, 1, 1);
					$out[$key] = substr ($arg, 3);
					}
					else
					{
					$chars = str_split (substr ($arg, 1));

					foreach ($chars as $char)
						{
						$key = $char;
						$out[$key] = isset($out[$key]) ? $out[$key] : true;
						}
					}
				}
				else
				{
				$out[] = $arg;
				}
			}

		return $out;
		}
	
	/**
	 * Takes in the array from parse_args and checks whether the supplied flag or flags are
	 * present. You can check for multiple flags by sending a CSV string of flags to check.
	 * 
	 * Example:
	 * if (args::check_args ($flags, 'h,help'))
	 * 		{
	 * 		// Do something
	 * 		}
	 * 
	 * @param array $argvs	The list of arguments from parse_args()
	 * @param string $flag	The flag(s) to check for
	 *
	 * @return string|bool	Returns false on failure, returns the flag value if available
	 */
	static function check_args ($argvs, $flag)
		{
		$test = explode (",", $flag);

		foreach ($test as $argv)
			{
			if (isset ($argvs[$argv]))
				{
				return $argvs[$argv];
				}
			}

		return false;
		}
	}
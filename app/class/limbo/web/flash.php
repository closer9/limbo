<?php
namespace limbo\web;

use \limbo\log;

/**
 * Flash messages allow you to setup special messages that will persist to the next request from 
 * the user. This is useful for displaying messages from forms.
 * 
 * Class flash
 * @package limbo\web
 */
class flash implements \ArrayAccess, \IteratorAggregate, \Countable
	{
	/**
	 * @var array An array of all the flash messages
	 */
	protected $messages;
	
	/**
	 * Builds the messages array and loads the previous messages if any exist
	 * 
	 */
	public function __construct ()
		{
		$this->messages = array (
			'current' 	=> array (),
			'next' 		=> array (),
			'now'  		=> array ()
			);
		
		if (isset ($_SESSION['limbo.flash']))
			{
			// Get the saved messages from the session
			$this->messages['current'] = $_SESSION['limbo.flash'];
			}
		}
	
	/**
	 * Before we depart, save any flash messages that are to be displayed next time
	 * 
	 */
	public function __destruct ()
		{
		$this->close ();
		}
	
	/**
	 * Get all flash messages that belong to a specific group. Easier to filter the different
	 * types of messages this way.
	 * 
	 * @param string $group	The name to filter by (eg. error or info)
	 *
	 * @return array
	 */
	public function get ($group)
		{
		$return = array ();
		
		foreach ($this->get_all () as $name => $message)
			{
			if ($group == $name)
				$return[] = $message;
			}
		
		return $return;
		}
	
	/**
	 * Returns all the current flash messages
	 * 
	 * @return array
	 */
	public function get_all ()
		{
		return array_merge ($this->messages['current'], $this->messages['now']);
		}
	
	/**
	 * Sets a new flash message for the current viewing
	 * 
	 * @param string $group		The key to group this message with
	 * @param string $message	The flash message
	 */
	public function now ($group, $message)
		{
		log::debug ("Setting a flash message for now ({$group}) {$message}");
		
		$this->messages['now'][(string) $group] = $message;
		}
	
	/**
	 * Sets a new flash message for display on the next viewing
	 * 
	 * @param string $group
	 * @param string $message
	 */
	public function set ($group, $message)
		{
		log::debug ("Setting a flash message for next time ({$group}) {$message}");
		
		$this->messages['next'][(string) $group] = $message;
		}
	
	/**
	 * Sets all the current flash messages to be displayed again on the next viewing. You
	 * can optionally specify a certain group to keep.
	 * 
	 * @param string $keep_group The specific group to keep
	 */
	public function keep ($keep_group = '')
		{
		foreach ($this->messages['current'] as $group => $message)
			{
			if (! empty ($keep_group) && $group != $keep_group)
				{
				continue;
				}
			
			$this->messages['next'][$group] = $message;
			}
		}
	
	/**
	 * Clears out any saved flash messages. If no group is specified all messages will be cleared
	 *
	 * @param string $group
	 */
	public function clear ($group = '')
		{
		if (empty ($group))
			{
			log::debug ("Clearing all flash messages");
			
			$this->messages = array (
				'current' 	=> array (),
				'next' 		=> array (),
				'now'  		=> array ()
				);
			}
			else
			{
			log::debug ("Clearing all flash messages for group '{$group}'");
			
			unset ($this->messages['now'][(string) $group]);
			unset ($this->messages['next'][(string) $group]);
			unset ($this->messages['current'][(string) $group]);
			}
		}
	
	/**
	 * Take all the messages queued for the next session and save them.
	 */
	public function close ()
		{
		// Save the next messages into the session
		$_SESSION['limbo.flash'] = $this->messages['next'];
		}
	
	/********************************************************************************
	 * Array methods
	 *******************************************************************************/
	
	public function offsetExists ($offset)
		{
		$messages = $this->get_all ();
		
		return isset ($messages[$offset]);
		}
	
	public function offsetGet ($offset)
		{
		$messages = $this->get_all ();
		
		return isset ($messages[$offset]) ? $messages[$offset] : null;
		}
	
	public function offsetSet ($offset, $value)
		{
		$this->now ($offset, $value);
		}
	
	public function offsetUnset ($offset)
		{
		unset ($this->messages['current'][$offset]);
		unset ($this->messages['now'][$offset]);
		}
	
	/********************************************************************************
	 * Iterator methods
	 *******************************************************************************/
	
	public function getIterator ()
		{
		$messages = $this->get_all ();
		
		return new \ArrayIterator ($messages);
		}
	
	/********************************************************************************
	 * Countable methods
	 *******************************************************************************/
	
	public function count ()
		{
		return count ($this->get_all ());
		}
	}
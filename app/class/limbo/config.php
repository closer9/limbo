<?php
namespace limbo;

/**
 * This class is used to store configuration variables in a database. The main use case for this
 * is custom configuration for the various sections that users can create, not necessarily the main
 * Limbo configuration. Overhead for using this class is rather high so use this wisely. The variable
 * values can be arrays, they will be stored in the database as a JSON object automatically.
 * 
 * Usage for storing config variables:
 *   limbo::config ('group_name')->set ('variable_name', 'value');
 *   limbo::config ()->set ('variable_name', 'value', 'group_name');
 *   limbo::config ('group_name')->set (array (
 *      'var1' => 'value',
 *      'var2' => 'value'
 *      ));
 * 
 * Usage for retrieving config variables:
 *   limbo::config ('group_name')->load ();                      
 *   limbo::config ('group_name')->load ('specific_variable');
 *   limbo::config ()->load (null, 'group_name');
 * 
 * Usage for deleting config variables:
 *   limbo::config ('group_name')->delete ('variable_name');
 *   limbo::config ()->delete ('variable_name', 'group_name');
 *   limbo::config ('group_name')->set ('variable_name', null);
 * 
 * @package limbo
 */
class config
	{
	/**
	 * @var mysql The mysql object used to interact with the database
	 */
	protected $sql;
	
	/**
	 * @var string The table used to store the configuration data
	 */
	protected $sql_table;
	
	/**
	 * @var bool This check is used to make sure the DB and table are available first
	 */
	protected $enabled = false;
	
	/**
	 * @var string The current configuration group we're manipulating 
	 */
	public $group;
	
	/**
	 * The config class constructor. You can specify the default config group here.
	 *
	 * @param string $group The default config group we want to interact with
	 * 
	 * @return config
	 */
	public function __construct ($group)
		{
		log::debug ("CONFIG - Starting the configuration object");
		
		return $this->group ($group);
		}
	
	/**
	 * @param string $group The name of the group you want to use
	 *
	 * @return config
	 */
	public function group ($group)
		{
		if (is_object ($this->sql))
			$this->group = $this->sql->clean ($group);
			else
			$this->group = $group;
		
		return $this;
		}
	
	/**
	 * Checks for a database connection, attempts to connect and build the table if necessary. This
	 * method is called at every load() and save() mostly because those methods can be called before
	 * the SQL class is loaded. So we have to check if the SQL variable is registered then load the
	 * class from the IOC. Once its checked and passed, it sets $enabled to true and does not check again.
	 * 
	 * @return bool
	 */
	public function check ()
		{
		// Return true if we've already checked and everything is good
		if ($this->enabled) return true;
		
		if (is_registered ('SQL') && ! is_object ($this->sql))
			{
			log::debug ('CONFIG - SQL object is registered');
			
			$this->sql = \limbo::ioc ('sql');
			$this->sql_table = \limbo::$config['limbo.config_table'];
			
			if ($this->sql_table)
				{
				$build_file = config ('path.app') . 'sql/config.php';
				
				// Check if we're allowed to build tables and there is a SQL file
				if (config ('limbo.build_tables') && is_readable ($build_file))
					{
					log::debug ('CONFIG - Attempting to create the config DB table');
					
					if (! $this->sql->check_connection ())
						{
						$this->sql->connect ();
						}
					
					require ($build_file);
					
					if (isset ($sql['db_config']))
						{
						$this->sql->create_table ($this->sql_table, $sql['db_config']);
						}
					}
				
				return ($this->enabled = true);
				}
			}
		
		return ($this->enabled = false);
		}
	
	/**
	 * Load a configuration variable/value from the database into the Limbo class's main $config
	 * array to be called using the config() helper function. You can specify if you want to overwrite
	 * the same variable if it already exists or not. If you don't specify the name of a variable all
	 * of the variables for that group will be loaded.
	 * 
	 * @param string $name        The name of the variable you want to load
	 * @param null|string $group  The variable group you want to read from (overrules the objects group var)
	 * @param bool $overwrite     Overwrite the current variable in memory?
	 *
	 * @return config
	 */
	public function load ($name = '', $group = null, $overwrite = true)
		{
		if ($this->check ())
			{
			$group = ($group !== null) ? $this->sql->clean ($group) : $this->group;
			
			log::debug ("CONFIG - Loading '{$this->group}' configuration");
			
			while ($record = $this->sql->loop ("SELECT * FROM {$this->sql_table} WHERE `group` = '{$group}'"))
				{
				// We want a specific config option loaded, skip everything else
				if (! empty ($name) && $record['name'] != $name) continue;
				
				// We don't want to overwrite memory and this name is already set, skip it.
				if (! $overwrite && isset (\limbo::$config[$record['name']])) continue;
				
				if ($record['json'])
					{
					$record['value'] = json_decode (base64_decode ($record['value']), true);
					}
				
				if (is_null ($record['key']))
					\limbo::$config[$record['name']] = $record['value'];
					else
					\limbo::$config[$record['name']][$record['key']] = $record['value'];
				}
			}
		
		return $this;
		}
	
	/**
	 * Saves a configuration variable/value into the database. The $name variable can either be
	 * a string with $value containing the value of the variable, or you can provide an array as 
	 * the $name and the method will parse it saving off the key => value pairs. If the value
	 * is also an array (of any depth (almost)) it'll save it as base64 encoded JSON.
	 * 
	 * @param string|array $name        The name or the variable, or associative array of var/vals
	 * @param null|string|array $value  Optional value of the variable, or array of values
	 * @param null|string $group        The variable group you want to read from (overrules the objects group var)
	 * @param bool $auto_delete         Delete the variable from the DB if the value is empty
	 *                                  
	 * @return config
	 */
	public function set ($name, $value = null, $group = null, $auto_delete = true)
		{
		if ($this->check ())
			{
			$group = ($group !== null) ? $this->sql->clean ($group) : $this->group;
			
			if (is_array ($name))
				{
				foreach ($name as $key => $value)
					{
					// Were going to recall this method with each pair in the array
					if (is_numeric ($key))
						log::error ("The first parameter must be an associative array, '{$key}' is an invalid key");
						else
						$this->set ($key, $value, $group, $auto_delete);
					}
				
				return $this;
				}
			
			log::debug ("CONFIG - Saving '{$name}' to the '{$group}' configuration");
			
			if (is_array ($value))
				{
				foreach ($value as $key => $set)
					{
					if ($auto_delete && empty ($set))
						{
						$this->delete ($name, $key, $group);
						
						continue;
						}
					
					$json = (is_array ($set)) ? 1 : 0;
					
					$this->sql->insert (array (
						'id'    => md5 ($group . $name . $key),
						'group' => $group,
						'name'  => $name,
						'key'   => $key,
						'value' => ($json) ? base64_encode (json_encode ($set)) : $set,
						'json'  => $json,
						), $this->sql_table, true);
					}
				}
				else
				{
				if ($auto_delete && empty ($value))
					{
					return $this->delete ($name, null, $group);
					}
				
				$this->sql->insert (array (
					'id'    => md5 ($group . $name),
					'group' => $group,
					'name'  => $name,
					'value' => $value,
					), $this->sql_table, true);
				}
			}
		
		return $this;
		}
	
	/**
	 * Deletes a configuration option from the database and from the Limbo $config array. You can call this
	 * directly when wanting to remove variables or specify $auto_delete when setting up the variables. The
	 * second option is nice for when you want to limit null variables saving to the DB.
	 * 
	 * @param string $name         The name of the variable you want to delete
	 * @param null|string $group   The variable group you want to read from (overrules the objects group var)
	 *
	 * @return config
	 */
	public function delete ($name, $key = null, $group = null)
		{
		if ($this->check ())
			{
			$group = ($group !== null) ? $this->sql->clean ($group) : $this->group;
			
			log::debug ("CONFIG - Deleting '{$name}' from the '{$group}' configuration");
			
			if ($key !== null)
				{
				$this->sql->delete (array ('group' => $group, 'name' => $name, 'key' => $key), $this->sql_table);
				
				unset (\limbo::$config[$name][$key]);
				}
				else
				{
				$this->sql->delete (array ('group' => $group, 'name' => $name), $this->sql_table);
				
				unset (\limbo::$config[$name]);
				}
			}
		
		return $this;
		}
	}

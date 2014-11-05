<?php /*************************************************************
Class name: mysql Class (2.0.0)
Author: Scott McKee (closer9@gmail.com)
Requires: PHP 5 | MySQL >= 5.0.0
********************************************************************
This class is the wrapper i use for MySQLi connections. Its not as
good as PEAR::DB but i like mine better. Supports all simple PEAR
functions, and a little touch of my own stuff. It makes use of the
new PHP 5 handler mysqli, faster and uses MySQL 4.1.0 functions.
********************************************************************
$config['database.options'] = array (
	'connections' => array (
		'default'	=> array ('hostname', 'username', 'password', 'db'),
		'users'		=> array ('hostname', 'username', 'password', 'db'),
		),
	'email'	=> false,
	'check'	=> true,
	);
*******************************************************************/

namespace limbo;

class mysql
	{
	private $mysql;
	
	protected $db_default	= 'default';	// The default database server
	protected $db_hostname	= 'localhost';	// The server name
	protected $db_username	= '';			// The default Mysql username
	protected $db_password	= '';			// The default MySQL password
	protected $db_database	= '';			// What database to connect to
	
	protected $sql_result	= null;			// The results of the last query
	protected $sql_table	= '';			// The default table to use
	protected $sql_queries	= array ();		// Place to store remembered queries
	protected $sql_remember	= 5;			// How many queries to remember
	protected $sql_timeout	= 10;			// Connection timeout in seconds
	protected $sql_loop		= '';			// A save of the current loop
	protected $sql_compress	= true;			// Use compression
	
	public $connections		= array ();		// A list of server connection data
	public $counter			= 0;			// How many queries performed
	public $check			= false;		// Test the connection first
	public $status			= false;		// The connection status
	
	public function __construct (array $options = array (), $connect = '')
		{
		$this->set_options ($options);
		
		// Automatically try to connect to the server
		if (! empty ($connect)) $this->connect ($connect);
		
		return $this;
		}
	
	public function __destruct ()
		{
		$this->disconnect ();
		}
	
	/**
	 * Sets global class options
	 * 
	 * @param array $options
	 */
	public function set_options (array $options)
		{
		$disallowed = array ('mysql', 'counter', 'sql_queries', 'sql_result');
		
		foreach ($options as $name => $value)
			{
			if (isset ($this->$name) && ! in_array ($name, $disallowed))
				{
				$this->$name = $value;
				}
			}
		}
	
	/**
	 * Get information about the currently loaded DB
	 * 
	 * @return array
	 */
	public function connection_info ()
		{
		return array (
			'hostname'	=> $this->db_hostname,
			'username'	=> $this->db_username,
			'password'	=> $this->db_password,
			'status'	=> $this->status
			);
		}
	
	/**
	 * Start the connection to the database
	 * 
	 * @param string $options	An array of connection options or the name of a database
	 * @param bool   $check		Specifies if we want to just check if we can connect or not
	 *
	 * @return bool|\mysqli
	 * @throws error
	 */
	public function connect ($options = '', $check = false)
		{
		$verify = array ('db_hostname', 'db_database');
		
		// Specifying a DB to connect to?
		if (! is_array ($options))
			{
			$database	= $options;
			
			// No database specified, try to use the default
			if (empty ($database) && isset ($this->connections[$this->db_default]))
				{
				$database = $this->db_default;
				}
			
			if (! isset ($this->connections[$database]))
				{
				throw new error ('Parameter 1 needs to be an array or a valid predefined database name');
				}
			
			$options = array (
				'db_hostname'	=> $this->connections[$database][0],
				'db_username'	=> $this->connections[$database][1],
				'db_password'	=> $this->connections[$database][2],
				'db_database'	=> $this->connections[$database][3],
				);
			}
		
		// Import these connection options
		$this->set_options ($options);
		
		// Double check that we have everything
		foreach ($verify as $verify_option)
			{
			if (empty ($this->$verify_option))
				throw new error ("Missing connections option: {$verify_option}");
			}
		
		if (($this->mysql = mysqli_init ()) === false)
			{
			throw new error ('Unable to initialize the MySQLi object');
			}
		
		$this->mysql->options (MYSQLI_OPT_CONNECT_TIMEOUT, $this->sql_timeout);
		$this->mysql->options (MYSQLI_CLIENT_COMPRESS, $this->sql_compress);
		
		$this->mysql->real_connect (
			$this->db_hostname,
			$this->db_username,
			$this->db_password,
			$this->db_database
			);
		
		if (mysqli_connect_errno ())
			{
			// Looks like we just want to know if we can connect
			if ($this->check || $check)
				{
				return false;
				}
			
			// Let everyone know there was an issue
			throw new error ("MySQL connection failed ({$this->db_hostname}): " . mysqli_connect_error ());
			}
		
		if ($this->check_connection ())
			{
			log::debug ("MySQL - Connection to {$this->db_hostname} established");
			}
		
		return $this;
		}
	
	/**
	 * Checks the connection to the database. Tries to reconnect if no connection is available
	 * 
	 * @return bool
	 */
	public function check_connection ()
		{
		$this->status = false;
		
		if (isset ($this->mysql) && is_object ($this->mysql))
			{
			ini_set ('mysqli.reconnect', 1);
			
			if ($this->mysql->ping () === true)
				{
				return $this->status = true;
				}
			
			log::error ('Lost database connection: ' . $this->mysql->error);
			}
		
		return false;
		}
	
	/**
	 * Verify that a SQL table exists or not
	 * 
	 * @param string $table	Table name to check for
	 *
	 * @return bool
	 */
	public function check_table ($table)
		{
		return in_array ($table, $this->get_tables ());
		}
	
	/**
	 * Collects a list of the tables in a database and returns them in an array
	 * 
	 * @return array
	 */
	public function get_tables ($fresh = false)
		{
		static $return = array ();
		
		if ($fresh || count ($return) == 0)
			{
			$result = $this->prepare ("SHOW TABLES FROM `?`", array ($this->db_database));
			
			if (is_object ($result))
				{
				while ($fetch = $this->fetch_result ($result, false))
					{
					$return[] = $fetch[0];
					}
				}
			}
		
		return $return;
		}
	
	/**
	 * Check that a column exists for a specific table and return information about it
	 * 
	 * @param string $table		The table name to search in
	 * @param string $column	The name of the column to verify
	 *
	 * @return bool
	 */
	public function check_column ($table, $column)
		{
		$result = $this->prepare ("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?", array (
			$table,
			$column
			));
	
		/* Make sure we have a result to process */
		if (is_object ($result))
			{
			return $result->num_rows;
			}
		
		return false;
		}
	
	/**
	 * This is used to send a CREATE SQL block to the database, creating a table. The table
	 * name can be replaced if you put in %%?%% where the table name should be in the SQL query
	 * 
	 * @param string $table		The name of the table you're creating
	 * @param string $sql		The SQL query to create the table
	 *
	 * @return mixed			Returns false if table already exists. Returns the result set otherwise
	 */
	public function create_table ($table, $sql)
		{
		if ($this->check_table ($table))
			{
			log::debug ('Table "' . $table . '" already exists');
			
			return true;
			}
		
		log::debug ('Creating new table: ' . $table);
		
		return $this->query (preg_replace ('/\%\%\?\%\%/', $table, $sql));
		}
	
	/**
	 * Cleans the supplied string to prepare it for the database
	 * 
	 * @param string $input		The string to clean
	 *
	 * @return string
	 */
	public function clean ($input)
		{
		if (! empty ($input))
			{
			if (strpos ($input, '\\') !== false)
				{
				$input = stripslashes ($input);
				}
			
			// Try to clean up any windows style newlines
			$input = preg_replace ("/\\\\r\\\\n/", "\r\n", $input);
			}
		
		return $this->mysql->real_escape_string ($input);
		}
	
	/**
	 * Clean up a string value into it's actual SQL value
	 * 
	 * @param string $input		The string to clean
	 *
	 * @return float|int|string
	 */
	public function clean_value ($input)
		{
		$input = $this->clean ($input);
		
		if (is_bool ($input))
			{
			return ($input) ? 'TRUE' : 'FALSE';
			}
		elseif (is_int ($input))
			{
			return (int) $input;
			}
		elseif (is_float ($input))
			{
			return (float) $input;
			}
		elseif (is_null ($input))
			{
			return 'NULL';
			}
		elseif (is_string ($input))
			{
			return "'{$input}'";
			}
		
		return $input;
		}
	
	/**
	 * Strips any slashes from the supplied string and cleans up HTML special characters
	 * 
	 * @param string $input		The string to strip slashes from
	 *
	 * @return string
	 */
	public function strip ($input)
		{
		return htmlspecialchars (stripslashes ($input));
		}
	
	/**
	 * Frees the memory associated with a result
	 * 
	 * @param \mysqli_result $result	The result set to clear / null if you want to clear the internal set
	 */
	public function free_result (\mysqli_result $result = null)
		{
		if (is_object ($result))
			{
			$result->free ();
			}
			else
			{
			$this->sql_result->free ();
			
			unset ($this->sql_result);
			}
		}
	
	/**
	 * Change the database from one to another
	 * 
	 * @param string $database	The name of the database to switch to
	 *
	 * @throws error
	 */
	public function change_database ($database)
		{
		$this->check_connection ();
		
		if (! $this->mysql->select_db ($database))
			{
			throw new error ("Could not change databases: {$this->mysql->error}");
			}
		
		$this->db_database = $database;
		}
	
	/**
	 * Record a submitted query into an array
	 * 
	 * @param string $query
	 */
	private function record ($query)
		{
		log::debug ("MySQL - Query: {$query}");
		
		// Add the new record to the end of the array
		array_unshift ($this->sql_queries, $query);
		
		// Split the array into chunks of X
		$chunks = array_chunk ($this->sql_queries, $this->sql_remember);
		
		// Only save the first group of records
		$this->sql_queries = $chunks[0];
		
		$this->counter ++;
		}
	
	/**
	 * Truncate a table
	 * 
	 * @param string $table		The name of the table to truncate
	 *
	 * @return \mysqli_result
	 */
	public function truncate ($table = '')
		{
		$table = (empty ($table)) ? $this->sql_table : $this->clean ($table);
		
		/* Perform a truncate command on table */
		return $this->query ("TRUNCATE TABLE `{$table}`");
		}
	
	
	/**
	 * Execute a query on the database server
	 * 
	 * @param string $query		The query to execute
	 * @param bool $save		Save the result set internally as well as return it
	 *
	 * @return bool|\mysqli_result	True on un-savable queries / result set on all others
	 * @throws error				If no connection is available
	 */
	public function query ($query, $save = true)
		{
		$this->record ($query);
		
		if (! $this->check_connection ())
			{
			throw new error ('No MySQL connection available');
			}
		
		$result = $this->mysql->query ($query);
		
		if ($save)
			{
			$this->sql_result = $result;
			}
		
		return $result;
		}
	
	/**
	 * Prepare a MySQL query for the database and execute it. Variables are submitted via the second parameter
	 * and are properly cleaned and quoted before executing the query.
	 * 
	 * Example:
	 * $sql->prepare ("SELECT * FROM `?` WHERE username = ? AND status = ?", array (
	 *		'table_name',
	 * 		'myuser',
	 * 		'1'
	 * 		));
	 * 
	 * Would generate:
	 * SELECT * FROM `table_name` WHERE username = 'myuser' AND status = 1;
	 * 
	 * @param string $query		The query you want to execute
	 * @param array $variables	The list of variables you are injecting
	 *
	 * @return bool|\mysqli_result
	 * @throws error
	 */
	public function prepare ($query, $variables = array ())
		{
		$query = preg_replace ('/\?/', '%?%', $query);
		
		foreach ((array) $variables as $variable)
			{
			$position = strpos ($query, '%?%');
			
			if ($position === false)
				break;
			
			if (is_bool ($variable))
				{
				$replace = ($variable) ? 'TRUE' : 'FALSE';
				}
			
			elseif (is_int ($variable))
				{
				$replace = (int) $variable;
				}
			
			elseif (is_float ($variable))
				{
				$replace = (float) $variable;
				}
			
			elseif (is_null ($variable))
				{
				$replace = 'NULL';
				}
			
			else
				{
				$marker_before	= (isset ($query[$position - 1])) ? $query[$position - 1] : false;
				$marker_after	= (isset ($query[$position + 3])) ? $query[$position + 3] : false;
				
				if (preg_match ("#`|'|\"#", $marker_before) || preg_match ("#`|'|\"#", $marker_after))
					{
					// If there is a quote before or after the marker, don't add quotes
					$replace = $this->clean ($variable);
					}
					else
					{
					$replace = "'" . $this->clean ($variable) . "'";
					}
				}
				
			
			$query = substr_replace ($query, $replace, $position, 3);
			}
		
		return $this->query ($query);
		}
	
	/**
	 * Like prepare except this also fetches the first row and returns it
	 *
	 * @param string $query		The query you want to execute
	 * @param array $variables	The list of variables you are injecting
	 * @param bool  $assoc		Return an associative array with the results?
	 *
	 * @return array|bool|mixed
	 */
	public function prefetch ($query, $variables = array (), $assoc = true)
		{
		if ($result = $this->prepare ($query, $variables))
			{
			return $this->fetch_result ($result, $assoc);
			}
		
		return false;
		}
	
	/**
	 * Fetch a result row from the submitted query or the submitted result set. If no query or result
	 * set is supplied it will try to use any internally saved result sets.
	 * 
	 * @param string         $query		The optional query to execute
	 * @param bool           $assoc		Return the results as an array
	 * @param \mysqli_result $result	A previous result set to process
	 *
	 * @return bool|\mysqli_result		False on error, mysqli_result set on success
	 */
	public function fetch ($query = '', $assoc = true, \mysqli_result $result = null)
		{
		if (! empty ($query) && $result === null)
			{
			$result = $this->query ($query);
			}
		elseif (! is_object ($result))
			{
			$result = $this->sql_result;
			}
		
		if (is_object ($result))
			{
			if ($assoc === true || is_string ($assoc))
				{
				$return = $result->fetch_assoc ();
				}
				else
				{
				$return = $result->fetch_array ();
				}
			
			if (is_string ($assoc) || is_int ($assoc))
				{
				return $return[$assoc];
				}
			
			return $return;
			}

		return false;
		}
	
	/**
	 * Fetch a result row value from the submitted query or the submitted result set. If no query or result
	 * set is supplied it will try to use any internally saved result sets. 
	 * 
	 * @param string         $query		The optional query to execute
	 * @param string         $value		The array key of the value to return
	 * @param \mysqli_result $result	A previous result set to process
	 *
	 * @return mixed	The value from the database
	 */
	public function fetch_value ($query = '', $value, \mysqli_result $result = null)
		{
		if (! empty ($query) && $result == null)
			{
			$result = $this->query ($query);
			}
		elseif (! is_object ($result))
			{
			$result = $this->sql_result;
			}
		
		$return = $this->fetch_result ($result, true);
		
		return $return[$value];
		}
	
	/**
	 * Fetch a single row from the database. This method will keep the result set separated from the rest
	 * of the class, so it's safe to run while running another query.
	 * 
	 * @param string         $query		The optional query to execute
	 * @param bool           $assoc		Return the results as an array or not
	 * @param \mysqli_result $result	A previous result set to process
	 *
	 * @return bool|\mysqli_result
	 */
	public function fetch_row ($query, $assoc = true, \mysqli_result $result = null)
		{
		if (is_object ($result))
			{
			return $this->fetch_result ($result, $assoc);
			}
			else
			{
			return $this->fetch (null, $assoc, $this->query ($query, false));
			}
		}
	
	public function fetch_once ($query, $assoc = true)
		{
		log::warning ('Using depreciated method "mysql::fetch_once"');
		
		return $this->fetch_row ($query, $assoc);
		}
	
	/**
	 * This method just makes code easier to read when processing an established result set
	 * 
	 * @param \mysqli_result $result	The previously generated result set
	 * @param bool           $assoc		Return the results as an associative array or not
	 *
	 * @return \mysqli_result
	 */
	public function fetch_result (\mysqli_result $result, $assoc = true)
		{
		return $this->fetch (null, $assoc, $result);
		}
	
	/**
	 * This method makes it easier to code a looping SQL query by allowing you to write the SQL query
	 * inside of the loop itself. This method also keeps the query separate from the rest of the class allowing you
	 * to query the DB at other points inside the loop.
	 * 
	 * You can not prepare the query properly using this method however.
	 * 
	 * Example:
	 * while ($fetch = $SQL->loop ("SELECT * FROM users"))
	 * 		{
	 * 		echo $fetch['username'];
	 * 		}
	 * 
	 * @param string $query		The query you would like to execute
	 * @param bool $assoc		Return the results as an array or not
	 *
	 * @return \mysqli_result
	 */
	public function loop ($query, $assoc = true)
		{
		static $result;
		
		if ($query != $this->sql_loop)
			{
			$result	= null;
			$this->sql_loop = $query;
			
			$result = $this->query ($query, false);
			}
		
		return $this->fetch_result ($result, $assoc);
		}
	
	/**
	 * Returns the selected query as an array one row at a time
	 * 
	 * @param string $query		The optional query to execute
	 * @param null   $key		The key (column) of the array (row) to return
	 *
	 * @return array
	 */
	public function dump ($query = '', $key = null, \mysqli_result $result = null)
		{
		$return = array ();
		
		if (! empty ($query) && $result === null)
			{
			$result = $this->query ($query);
			}
		elseif (! is_object ($result))
			{
			$result = $this->sql_result;
			}
		
		if (is_object ($result))
			{
			while ($fetch = $this->fetch_result ($result))
				{
				if ($key === null)
					{
					$return[] = $fetch;
					}
					else
					{
					$return[$fetch[$key]] = $fetch;
					}
				}
			}
		
		return $return;
		}
	
	
	/**
	 * Return a specific single row based on a column value of that table, no SQL query required!
	 * 
	 * @param string|int $id		The value of the row your looking for
	 * @param string     $table		The name of the table
	 * @param string     $column	The column name that contains the ID
	 *
	 * @return bool|\mysqli_result
	 */
	public function id ($id, $table = '', $column = 'id')
		{
		return $this->select (array ($column => $id), $table);
		}
	
	public function get_id ($id, $table = '', $values = '*', $column = 'id')
		{
		log::warning ('Using depreciated method "mysql::get_id"');
		
		return $this->select (array ($column => $id), $table);
		}
	
	/**
	 * Selects a single row from the database based on any number of search paramaters
	 * 
	 * @param array  $params	The array of key => value search pairs
	 * @param string $table		The name of the table
	 *
	 * @return bool|array	False on failure, array of row data on success
	 */
	public function select (array $params, $table = '')
		{
		$search = array ();
		
		foreach ($params as $key => $value)
			{
			$key 	= $this->clean ($key);
			$value 	= $this->clean_value ($value);
			
			$search[] = "`{$key}` = {$value}";
			}
		
		if (count ($search) == 0)
			{
			throw new error ('No valid search parameters were specified');
			}
		
		$result = $this->prepare ("SELECT * FROM `?` WHERE " . implode (" AND ", $search), array (
			((empty ($table)) ? $this->sql_table : $table),
			));
		
		if ($this->num_rows (null, $result) > 0)
			{
			return $this->fetch_result ($result);
			}
		
		return false;
		}
	
	/**
	 * Inserts an array into the specified table. The array should be formatted in key => value
	 * style. Where key is the column name.
	 * 
	 * @param array  $input		The array to insert into the database
	 * @param string $table		The table to insert into
	 *
	 * @return mixed
	 */
	public function insert (array $input, $table = '')
		{
		$table		= (empty ($table)) ? $this->sql_table : $this->clean ($table);
		$add_key	= array ();
		$add_value	= array ();
		
		foreach ($input as $key => $value)
			{
			$key 	= $this->clean ($key);
			$value 	= $this->clean ($value);
			
			$add_key[] 		= "`{$key}`";
			$add_value[] 	= $this->clean_value ($value);
			}
		
		// Join all the keys and values together
		$keys	= implode (', ', $add_key);
		$values	= implode (', ', $add_value);
		
		$this->query ("INSERT INTO `{$table}` ({$keys}) VALUES ({$values})", false);
		
		return $this->mysql->insert_id;
		}
	
	/**
	 * Like insert, this method takes key => value pairs and updates the specified table with
	 * the results.
	 * 
	 * @param mixed  $id		The value of the row(s) you want to update (can be an array of id's)
	 * @param array  $update	The key => value pairs of data you want to update
	 * @param string $table		The name of the table
	 * @param string $marker	The column name of the $id
	 *
	 * @return mixed
	 */
	public function update ($id, array $update, $table = '', $marker = 'id')
		{
		$table 		= (empty ($table)) ? $this->sql_table : $this->clean ($table);
		$add_where 	= array ();
		$add_update = array ();
		
		foreach ($update as $key => $value)
			{
			$key 	= $this->clean ($key);
			$value 	= $this->clean_value ($value);
			
			$add_update[] = "`{$key}` = {$value}";
			}
		
		if (! is_array ($id))
			{
			$id = array ($marker => $id);
			}
		
		foreach ($id as $key => $value)
			{
			$key 	= $this->clean ($key);
			$value 	= $this->clean_value ($value);
			
			$add_where[] = "`{$key}` = {$value}";
			}
		
		// Join the update and where options together
		$update = implode (", ", $add_update);
		$where = implode (' AND ', $add_where);
		
		$this->query ("UPDATE `{$table}` SET {$update} WHERE {$where}", false);
		
		return $this->mysql->affected_rows;
		}
	
	/**
	 * Increase the specified column value by 1
	 * 
	 * @param string $id		The value of the column up want to use as a key (the ID)
	 * @param string $column	The column you want to increase
	 * @param string $table		The name of the table
	 * @param string $marker	The name of the column to use as a key
	 *
	 * @return int	The number of rows affected
	 */
	public function increase ($id, $column, $table = '', $marker = 'id')
		{
		$this->prepare ("UPDATE `?` SET `?` = (`?` + 1) WHERE `?` = ?", array (
			(empty ($table)) ? $this->sql_table : $table,
			$column,
			$column,
			$marker,
			$id
			));
		
		return $this->mysql->affected_rows;
		}
	
	/**
	 * Decrease the specified column value by 1
	 *
	 * @param string $id		The value of the column up want to use as a key (the ID)
	 * @param string $column	The column you want to increase
	 * @param string $table		The name of the table
	 * @param string $marker	The name of the column to use as a key
	 *
	 * @return mixed
	 */
	public function decrease ($id, $column, $table = '', $marker = 'id')
		{
		$this->prepare ("UPDATE `?` SET `?` = (`?` - 1) WHERE `?` = ?", array (
			(empty ($table)) ? $this->sql_table : $table,
			$column,
			$column,
			$marker,
			$id
			));
		
		return $this->mysql->affected_rows;
		}
	
	/**
	 * Get the number of rows that would be returned by a query
	 * 
	 * @param string         $query		The optional query to execute
	 * @param \mysqli_result $result	A previous query result set to use
	 *
	 * @return bool|int
	 * @throws error
	 */
	public function num_rows ($query = '', \mysqli_result $result = null)
		{
		if (! empty ($query) && $result === null)
			{
			$result = $this->query ($query);
			}
		elseif (! is_object ($result))
			{
			$result = $this->sql_result;
			}
		
		if (is_object ($result))
			{
			return $result->num_rows;
			}
		
		return false;
		}
	
	/**
	 * Delete a specific row in the database based on the specified column value
	 * 
	 * @param string $id			The value the row must have for the specified column
	 * @param string $table			The name of the table
	 * @param string $marker		The column name to compare the ID with
	 *
	 * @return \mysqli_result
	 */
	public function delete ($id, $table = '', $marker = 'id')
		{
		$table	= (empty ($table)) ? $this->sql_table : $this->clean ($table);
		$marker	= $this->clean ($marker);
		$id		= $this->clean ($id);
		
		return $this->query ("DELETE FROM `{$table}` WHERE `{$marker}` = '{$id}'", false);
		}
	
	public function del_id ($id, $table = '', $marker = 'id')
		{
		log::warning ('Using depreciated method "del_id"');
		
		return $this->delete ($id, $table, $marker);
		}
	
	/**
	 * Return the number of affected rows from the last query
	 * 
	 * @return mixed
	 */
	public function affected ()
		{
		return $this->mysql->affected_rows;
		}
	
	/**
	 * Close the MySQL connection to the database
	 */
	public function disconnect ()
		{
		if (isset ($this->mysql) && is_object ($this->mysql))
			{
			log::debug ('MySQL - Closing connection');
			
			@$this->mysql->close ();
			
			unset ($this->mysql);
			}
		}
	}

<?php /*************************************************************
Class name: Auth Class (2.5.002)
Author: Scott McKee (closer9@gmail.com)
Requires: PHP >= 5.2 | MySQLi Class >= 1.5.3
********************************************************************
This class is the main authentication class for my websites. It
has session id/ip checking and section access for any number of
access levels. It can also regenerate the session id with every
auth_check call, very good against session hijacking.
********************************************************************
Initializing:
---------------------------
$AUTH = new auth_class (array (
	'db_users'		=> 'porting_users',
	'db_info'		=> 'porting_userinfo',
	'db_sessions'	=> 'porting_sessions',
	'db_logins'		=> 'porting_logins',
	'validate'		=> true,
	'timeout'		=> 3600,
	'regenerate'	=> false,
	), $sql_resource);

$AUTH->initialize ();

Logging in:
---------------------------
if ($AUTH->auth ($username, $password) === true)
	{
	if ($AUTH->check_session () === true)
		{
		if ($cookies) $AUTH->cookie_save ();
			
		// Redirect to the protected area of the site
		}
	
	$AUTH->logout (true);
			
	$error = 'Login error: ' . $AUTH->errormsg;
	}

Verifying sessions:
----------------------------
// Check if authenticated or not
$AUTH->check_session ();

// Check if authenticated and has access to this section
$AUTH->check_section (47);

// Check if authenticated and has the proper rank
$AUTH->check_rank (24);

// Check if authenticated and is part of the specified group
$AUTH->check_group (3);

// Check if authenticated and is a specific user
$AUTH->check_authid (12);

*******************************************************************/

namespace limbo;

use \limbo\util\security;
use \limbo\web\web;

class auth
	{
	private $sql;
	
	protected $db_users		= 'auth_users';	// The default users table
	protected $db_settings	= '';			// User program settings (preferences)
	protected $db_info		= '';			// Extra user information (address, phone)
	protected $db_blocked	= '';			// List of blocked ip addresses
	protected $db_sessions	= '';			// Session information table
	protected $db_logins	= '';			// Record each user login attempt
	protected $db_groups	= '';			// Data about the different groups
	protected $db_audit		= '';			// An audit table for manually recording actions
	protected $db_heartbeat	= '';			// A place to record users online status
	
	private $cookie			= true;			// Allow auth cookies to be used and set
	private $cookie_path	= null;			// The default path to set the cookie in
	private $cookie_host	= null;			// The hostname of the website ($_SERVER['HTTP_HOST'])
	private $cookie_track	= false;		// Track the users progress via cookies
	
	private $pass_clear		= false;		// Allow unencrypted passwords in the database
	private $pass_salt		= '';			// The salt used for password hash creation
	private $pass_type		= 'bcrypt';		// Type of hash to use (md5, sha, crc, aes)
	private $pass_key		= '';			// The key used for AES encryption
	
	private $regenerate		= true;			// Regenerate the session id after login
	private $timeout		= 600;			// The amount of time until a session times out from inactivity
	private $superuser		= 100;			// The default superuser rank
	private $group			= 0;			// The default group ID to check for (0 = don't check)
	private $validate		= false;		// Check the IP address against the one used at login
	private $database		= 'default';	// The default database connection name to use
	private $lock_count		= 5;			// Maximum number of failed login attempts until locked
	private $lock_time		= 300;			// How long after a lock until they can try again		
	
	public $user			= array ();		// Default users data (username, rank)
	public $authid			= 0;			// The users authID
	public $groupid			= 0;			// The users groupID
	public $settings		= array ();		// User program settings
	public $username		= '';			// The current users username
	public $address			= null;			// The current users IP address
	public $session			= array ();		// Session information from the DB
	public $sections		= array ();		// List of allowed sections for this user
	public $progress		= array ();		// The users progress through the website
	public $errormsg		= '';			// The last error of the script
	public $verified		= false;		// Have we verified the user already
	
	/**
	 * Sets up the initial configuration of the class
	 * 
	 * @param array $options		Array of class options
	 * @param mysql $sql_resource	The initialized database object
	 * 
	 * @throws error
	 */
	public function __construct (array $options = array (), mysql $sql_resource)
		{
		$this->set_option ($options);
		
		if (empty ($this->db_users))
			{
			throw new error ('No user table specified in the configuration');
			}
		
		$this->sql = $sql_resource;
		
		if (! $this->sql->check_connection ())
			{
			// Try to make a connection if we're disconnected
			$this->sql->connect ($this->database);
			}
		
		// Are we supplied with a database layout
		if (is_readable (config ('path.app') . 'sql/auth.php'))
			{
			$sql = array ();
			
			log::debug ('Attempting to verify all authentication tables');
			
			require config ('path.app') . 'sql/auth.php';
			
			// If we're using specific functionality, make sure the table is setup
			if ($this->db_users) $this->sql->create_table ($this->db_users, $sql['db_users']);
			if ($this->db_settings) $this->sql->create_table ($this->db_settings, $sql['db_settings']);
			if ($this->db_info) $this->sql->create_table ($this->db_info, $sql['db_info']);
			if ($this->db_blocked) $this->sql->create_table ($this->db_blocked, $sql['db_blocked']);
			if ($this->db_sessions) $this->sql->create_table ($this->db_sessions, $sql['db_sessions']);
			if ($this->db_logins) $this->sql->create_table ($this->db_logins, $sql['db_logins']);
			if ($this->db_audit) $this->sql->create_table ($this->db_audit, $sql['db_audit']);
			if ($this->db_heartbeat) $this->sql->create_table ($this->db_heartbeat, $sql['db_heartbeat']);
			}
		
		// Back in 2004 this class used to collect the users IP
		$this->address = \limbo::request ()->ip;
		
		// Clear any expired heartbeats
		$this->heartbeat_cleanup ();
		
		// Clear any expired sessions
		$this->session_cleanup ();
		
		return $this;
		}
	
	/**
	 * Initializes the authentication class buy:
	 *
	 *  - Making sure the connection to the database exists
	 *  - Create any newly used tables
	 *  - Sets up any cookies
	 *  - Session and heartbeat table cleanup
	 *  - Starts tracking the user
	 * 
	 * @return object This object
	 */
	public function initialize ()
		{
		log::debug ('Initializing the authentication class');
		
		// Setup cookie settings (domain name, etc.)
		$this->cookie_init ();
		
		// Check the database for a matching session
		// Start a new one if none can be found
		$this->session_verify ();
		
		// Track this specific action
		$this->user_tracker ();
		
		return $this;
		}
	
	/********************************************************************************
	 * Setting / getting methods
	 *******************************************************************************/
	
	/**
	 * Sets up a single class option or specify an array to setup multiple options
	 * 
	 * @param string|array $option	Array of options or a string with the option name
	 * @param string $value			Optional value when setting a single variable
	 */
	public function set_option ($option, $value = '')
		{
		if (is_array ($option))
			{
			foreach ($option as $name => $value)
				{
				$this->$name = $value;
				}
			}
			else
			{
			$this->$option = $value;
			}
		}
	
	/**
	 * Returns a class variable / option
	 * 
	 * @param $option
	 *
	 * @return mixed
	 */
	public function get_option ($option)
		{
		return $this->$option;
		}
	
	/**
	 * Sets the groupid variable
	 * 
	 * @param $group
	 */
	public function set_group ($group)
		{
		$this->groupid = (int) $group;
		}
	
	/********************************************************************************
	 * Recording methods
	 *******************************************************************************/
	
	/**
	 * Logs a message into the errormsg variable. Always returns false so other methods
	 * can return false while also setting the last error message.
	 * 
	 * @param $message
	 *
	 * @return bool
	 */
	private function record_error ($message)
		{
		log::warning ('Auth error: ' . $message);
		
		$this->errormsg = $message;
		
		return false;
		}
	
	/**
	 * Records a login event into the database.
	 * 
	 * @param int    $status		The status type (0 - unsuccessful, 1 - successful)
	 * @param string $event			The event message
	 * @param string $username		Optional username that caused the event
	 *
	 * @return bool
	 */
	public function record_event ($status, $event = '', $username = '')
		{
		if ($this->db_logins)
			{
			if (! empty ($event))
				{
				log::debug ('Auth event: ' . $event);
				}
			
			$this->sql->insert (array (
				'authid'	=> @$this->authid,
				'group'		=> @$this->groupid,
				'status'	=> $status,
				'reason'	=> $event,
				'username'	=> ($username) ? $username : @$this->username,
				'datestamp'	=> date ('Y-m-d H:i:s'),
				'address'	=> $this->address
				), $this->db_logins);
			}
		
		return true;
		}
	
	/********************************************************************************
	 * Authentication methods
	 *******************************************************************************/
	
	/**
	 * Authenticates a username and password against the database. If unsuccessful the
	 * lockout timer is started. If successful then we have the option of converting
	 * their session to an authenticated session.
	 * 
	 * @param string $username	The username
	 * @param string $password	Unencrypted password
	 * @param bool   $start		Also start the authenticated session if successful
	 *
	 * @return bool
	 */
	public function auth ($username, $password, $start = true)
		{
		// Make sure we have non-empty credentials
		if (empty ($username) || empty ($password)) return false;
		
		log::debug ('Authenticating user: ' . $username);
		
		$username = filter_var ($username, FILTER_SANITIZE_STRING);
		$password = filter_var ($password, FILTER_SANITIZE_STRING);
		
		// Grab the user from the database and populate this class with their info
		$this->user (array ('username' => $username, 'group' => $this->group));
		
		if (! empty ($this->authid))
			{
			// Check if the account is locked or not
			if ($this->lockout_check ())
				return $this->record_error ('To many login attempts, try again later');
			
			if (empty ($this->user['status']))
				return $this->record_error ('The account is currently inactive or unverified');
			
			if ($this->groupid && isset ($this->user['group']) && ! $this->user['group']['status'])
				return $this->record_error ('The group for the account is disabled');
			
			// Check the password against the database
			if ($this->check ($password, $this->user['password']))
				{
				// Clear out any failed attempt counters
				$this->lockout_reset ();
				
				if ($start)
					{
					$this->start ();
					}
				
				log::info ('Authentication successful for ' . $username);
				
				return true;
				}
				else
				{
				// Log the failed attempt
				$this->record_event (0, 'Invalid Password');
				
				// Update the lockout record
				$this->lockout_failure ();
				}
			}
			else
			{
			$this->record_event (0, 'Invalid Username', $username);
			}
		
		return $this->record_error ('The username and/or password is invalid');
		}
	
	/**
	 * Check the supplied password against the encrypted password from the database
	 * 
	 * @param string $password		Plaintext password
	 * @param string $hash			Encrypted password
	 *
	 * @return bool
	 */
	private function check ($password, $hash)
		{
		if ($this->pass_type == 'bcrypt')
			{
			$password_enc = crypt ($password, $hash);
			}
			else
			{
			$password_enc = $this->encrypt ($password);
			}
		
		// We also have the option to allow clear passwords... eww
		if ($password_enc == $hash || ($password == $hash && $this->pass_clear))
			{
			return true;
			}
		
		return false;
		}
	
	/**
	 * Starts a new authenticated session (or converts a previous session).
	 * 
	 * @param string $username	Optional username to specify
	 *
	 * @return bool
	 */
	public function start ($username = '')
		{
		log::debug ('Starting a new authenticated session');
		
		if (! empty ($username))
			{
			// We are specifying the username, load the class with it's info
			$this->user (array ('username' => $username, 'group' => $this->group));
			}
		
		if (empty ($this->authid))
			{
			log::error ('Could not start a new session. No user specified or loaded.');
			
			return false;
			}
		
		log::debug ('Setting session variables');
		
		// Set all of our session variables
		$_SESSION['session_id']				= session_id ();
		$_SESSION['session_auth_id']		= $this->authid;
		$_SESSION['session_auth_username']	= @$this->user['username'];
		$_SESSION['session_auth_password']	= @$this->encrypt_session ($this->user['password']);
		$_SESSION['session_auth_sections']	= @$this->user['sections'];
		$_SESSION['session_auth_last']		= @$this->user['last_login'];
		
		// Update the last login time and the ip
		$this->sql->update ($this->authid, array (
			'last_ip'		=> $this->address,
			'last_login'	=> date ('Y-m-d H:i:s'),
			'try_count'		=> 0,
			'try_last'		=> 0,
			), $this->db_users, 'authid');
		
		if ($this->db_sessions)
			{
			log::debug ('Recording the session to the database');
			
			// Start up a session if we don't have one already
			if (empty ($this->session['id']))
				{
				$this->session_verify ();
				}
			
			// Change this session to an authenticated session
			$this->sql->update ($this->session['id'], array (
				'authid'	=> $this->authid,
				'stamp'		=> time (),
				'ip'		=> $this->address,
				), $this->db_sessions);
			}
		
		$this->session_regenerate ();
		
		return $this->record_event (1, 'Successful login');
		}
	
	/********************************************************************************
	 * Verification methods
	 *******************************************************************************/
	
	/**
	 * Checks for a valid session via session_check(). Has the option to redirect on failure.
	 * 
	 * @param string|bool $redirect Optional redirection URL if the check fails
	 *
	 * @return bool True on valid session, False or redirect on failure
	 */
	public function check_session ($redirect = false)
		{
		log::debug ('Checking for a registered session');
		
		if ($this->session_check () === true)
			{
			return true;
			}
		
		return self::redirect ($redirect);
		}
	
	/**
	 * Check if a user has access to a section. This is usually called on a script you want to limit
	 * access to, or in a section's config.inc.php script to check a collection of scripts.
	 * 
	 * @param int|array $section_array		The section id or an array of section ids
	 * @param bool      $redirect			Optional redirect URL on failure
	 * @param bool      $require_all		The user must validate against all the supplied sections
	 *
	 * @return bool True on valid session, False or redirect on failure
	 */
	public function check_section ($section_array, $redirect = false, $require_all = false)
		{
		if ($this->session_check () === true)
			{
			$passed_counter = 0;
			
			// Give access no matter what if the user is a superuser
			if ($this->user['rank'] >= $this->superuser)
				{
				return true;
				}
			
			// Compile a list of allowed sections for this user
			$users_sections = json_decode ($this->user['sections'], true);
			
			foreach ((array) $section_array as $section_id)
				{
				log::debug ('Checking if the user has access to section: ' . $section_id);
				
				if (in_array ($section_id, $users_sections))
					{
					$passed_counter ++;
					}
				}
			
			// Just check if they passed at least 1 section
			if ($passed_counter > 0 && $require_all == false)
				{
				return true;
				}
			
			// If they are required to pass all, make sure the passed counter matches
			if ($require_all && $passed_counter == count ((array) $section_array))
				{
				return true;
				}
			}
		
		return self::redirect ($redirect);
		}
	
	/**
	 * Make sure the user has the proper rank
	 * 
	 * @param int  $rank		The rank of the area you want to protect
	 * @param bool $redirect	Optional redirect URL on failure
	 *
	 * @return bool
	 */
	public function check_rank ($rank, $redirect = false)
		{
		if ($this->session_check () === true)
			{
			log::debug ('Checking if the user has the proper rank: ' . $rank);
			
			if ($this->user['rank'] >= $rank)
				{
				return true;
				}
			}
		
		return self::redirect ($redirect);
		}
	
	/**
	 * Make sure the user is in the proper group
	 * 
	 * @param int|array $group		The group id or an array of group ids
	 * @param bool      $redirect	Optional redirect URL on failure
	 *
	 * @return bool
	 */
	public function check_group ($group, $redirect = false)
		{
		if ($this->session_check () === true)
			{
			foreach ((array) $group as $check)
				{
				log::debug ('Checking if the user has the proper group: ' . $check);
				
				if ($this->user['group'] == $check)
					{
					return true;
					}
				}
			}
		
		return self::redirect ($redirect);
		}
	
	/**
	 * Make sure the user has the proper authid
	 * 
	 * @param int|array $authid		The authid or an array of authids
	 * @param bool      $redirect	Optional redirect URL on failure
	 *
	 * @return bool
	 */
	public function check_authid ($authid, $redirect = false)
		{
		if ($this->session_check () === true)
			{
			foreach ((array) $authid as $check)
				{
				log::debug ('Checking if the user has an authid of: ' . $check);
				
				if ($this->authid == $check)
					{
					return true;
					}
				}
			}
		
		return self::redirect ($redirect);
		}
	
	/**
	 * Verifies that the username supplied is valid. Also returns the user information as an array
	 * 
	 * @param string $username The username to verify
	 *
	 * @return array|bool Returns false on failure, The users info on success
	 */
	public function check_username ($username)
		{
		return $this->user (array ('username' => $username, 'group' => $this->group), false);
		}
	
	/********************************************************************************
	 * Session methods
	 *******************************************************************************/
	
	/**
	 * Starts recording a new session in the database and in the class
	 */
	private function session_start ()
		{
		log::debug ('Starting a brand new session');
		
		// Start a session if one is not active
		if (! session_id ())
			{
			session_start ();
			}
		
		$session = array (
			'authid'	=> 0,
			'sessionid'	=> session_id (),
			'stamp'		=> time (),
			'ip'		=> $this->address
			);
		
		// Remember this session inside the database
		$session['id'] = $this->sql->insert ($session, $this->db_sessions);
		
		// Lets also remember this info inside the class
		$this->session = $session;
		}
	
	/**
	 * Verifies that the current session is being recorded. If no session was found it starts up
	 * a new one. This method also regenerates the sessionid if the config says to
	 */
	private function session_verify ()
		{
		if (! $this->db_sessions) return;
		
		log::debug ('Checking the database for this session');
		
		// Check to see if we already have this session in the database
		$result = $this->sql->prepare ("SELECT * FROM `?` WHERE `sessionid` = ?", array (
			$this->db_sessions,
			session_id ()
			), false);
		
		if ($this->sql->num_rows (null, $result))
			{
			log::debug ('The session was found!');
			
			$this->session = $this->sql->fetch_result ($result);
			
			// Update the last stamp for this session and the sessionid (maybe it changed?)
			$this->sql->update ($this->session['id'], array (
				'stamp'		=> time (),
				'sessionid'	=> session_id (),
				), $this->db_sessions);
			}
			else
			{
			// Start a new session if one could not be found
			$this->session_start ();
			}
		
		$this->cookie_check ();
		}
	
	/**
	 * This is probably the most important method in this class as it makes gives the yes or no on
	 * whether or not the user is actually authenticated. The process goes like this:
	 * 
	 * - Verify that the class is populated with their info
	 * - Make sure their account is still active
	 * - Make sure they are part of the active group
	 * - Get their session from the database
	 * - Verify that the sessionid and (optionally) the IP match
	 * - Make sure the encrypted session password matches
	 * 
	 * @return bool
	 */
	public function session_check ()
		{
		log::debug ('Session validation has started');
		
		if (isset ($_SESSION['session_auth_id']) && isset ($_SESSION['session_auth_password']))
			{
			if (empty ($this->authid))
				{
				// Populate the class with the users information
				$this->user (array ('authid' => $_SESSION['session_auth_id']));
				}
			
			if (empty ($this->user['status']))
				{
				return $this->record_error ('No user information found (bad status)');
				}
			
			if ($this->verified)
				{
				log::debug ('Session has been previously verified. Skipping checks');
				
				return true;
				}
			
			if ($this->group > 0 && $this->user['group'] != $this->group)
				{
				return $this->record_error ('You do not belong to this group');
				}
			
			if ($this->db_sessions)
				{
				$result = $this->sql->prepare ("SELECT * FROM `?` WHERE `authid` = ?", array (
					$this->db_sessions,
					$this->authid
					), false);
				
				if (($count = $this->sql->num_rows (null, $result)))
					{
					if ($count == 1)
						{
						$session = $this->sql->fetch_result ($result);
						}
						else
						{
						while ($fetch = $this->sql->fetch_result ($result))
							{
							// We found multiple sessions for some reason, try to find the right one
							if ($fetch['sessionid'] == $_SESSION['session_id'])
								{
								$session = $fetch;
								}
							}
						}
					
					if (! isset ($session))
						{
						return $this->record_error ('Could not find your session data');
						}
					
					if ($session['sessionid'] != $_SESSION['session_id'])
						{
						return $this->record_error ('The sessionid in the database does not match');
						}
					
					// Do we want to validate the IP address every time? Make sure they match
					if ($this->validate && ($session['ip'] != $this->address))
						{
						return $this->record_error ('IP address failed validation. Maybe it changed?');
						}
					}
					else
					{
					return $this->record_error ('Session was not found in the database');
					}
				}
			
			// Make sure the saved sessionid matches our current sessionid
			if ($_SESSION['session_id'] == session_id ())
				{
				$check_password = $this->encrypt_session ($this->user['password']);
				
				// Make sure the hashed password matches the users password
				if ($_SESSION['session_auth_password'] == $check_password)
					{
					log::debug ('Session has been verified!');
					
					return $this->verified = true;
					}
					else
					{
					return $this->record_error ('Password validation of the session failed');
					}
				}
			}
		
		return $this->record_error ('No matching authenticated session found');
		}
	
	/**
	 * Regenerates the sessionid in the session, cookies, and optionally the database
	 * 
	 * @param bool $update_database
	 *
	 * @return bool
	 */
	public function session_regenerate ($update_database = true)
		{
		if ($this->db_sessions && $this->regenerate)
			{
			$old_session = session_id ();
			
			if (session_regenerate_id (true))
				{
				$new_session = session_id ();
				
				log::debug ('Regenerated the session id: ' . $old_session . ' -> ' . $new_session);
				
				// Reset the cookie if we need to
				//$this->cookie_set (session_name (), $new_session);
				
				// Update the backup session id
				$_SESSION['session_id'] = $new_session;
				
				if ($update_database)
					{
					$update = array ('sessionid' => $new_session);
					
					$this->sql->update ($old_session, $update, $this->db_sessions, 'sessionid');
					
					if ($this->db_heartbeat)
						{
						$this->sql->update ($old_session, $update, $this->db_heartbeat, 'sessionid');
						}
					}
				
				return true;
				}
			}
		
		return false;
		}
	
	/**
	 * Cleans up the session table by deleting any expired sessions
	 */
	private function session_cleanup ()
		{
		if ($this->db_sessions)
			{
			log::debug ('Clearing out any old sessions');
			
			$this->sql->prepare ("DELETE FROM `?` WHERE `stamp` <= ?", array (
				$this->db_sessions,
				(time () - $this->timeout)
				));
			}
		}
	
	/********************************************************************************
	 * Heartbeat methods
	 *******************************************************************************/
	
	/**
	 * Records heartbeat activity from the website into the database.
	 * 
	 * @param string $query The website information to record (PID, etc.)
	 * @param bool $active	Did the last heartbeat have user activity (typing, moving the mouse)
	 *
	 * @return bool
	 */
	public function heartbeat ($query, $active = false)
		{
		if ($this->session_check ())
			{
			if (! $this->db_heartbeat)
				{
				return true;
				}
			
			log::debug ('Checking for a valid heartbeat');
			
			parse_str ('pid=' . base64_decode ($query), $query);
			
			$location = array_shift ($query);
			
			if ($this->authid > 0)
				{
				if ($active)
					{
					$this->session_verify ();
					}
				
				if ($this->sql->id (session_id (), $this->db_heartbeat, 'sessionid'))
					{
					log::debug ('Updating an existing heartbeat record');
					
					$this->sql->update (session_id (), array (
						'stamp'		=> time (),
						'location'	=> $location,
						'options'	=> json_encode ($query),
					), $this->db_heartbeat, 'sessionid');
					}
				else
					{
					log::debug ('Creating a new heartbeat record');
					
					$this->sql->insert (array (
						'sessionid'	=> session_id (),
						'authid'	=> $this->authid,
						'stamp'		=> time (),
						'location'	=> $location,
						'options'	=> json_encode ($query),
					), $this->db_heartbeat);
					}
				}
			
			return true;
			}
		
		return false;
		}
	
	/**
	 * Cleans up the heartbeat table by deleting any expired records
	 * 
	 * @param int $seconds The amount of time until heartbeats expire
	 */
	private function heartbeat_cleanup ($seconds = 60)
		{
		if ($this->db_heartbeat)
			{
			log::debug ('Clearing out any old heartbeats');
			
			$this->sql->prepare ("DELETE FROM `?` WHERE `stamp` <= ?", array (
				$this->db_heartbeat,
				(time () - $seconds)
				));
			}
		}
	
	/********************************************************************************
	 * User management methods
	 *******************************************************************************/
	
	/**
	 * Collects the user's information from the database and populates the class variables. Can
	 * also be used to just grab the user info from the database if told _not_ to update the class.
	 * 
	 * The search array is a key => value pair of user data. Example:
	 * $auth->user (array ('username' => 'myuser'), false);
	 * 
	 * @param array $search
	 * @param bool  $update_class
	 *
	 * @return array|bool|\mysqli_result
	 * @throws error
	 */
	public function user (array $search, $update_class = true)
		{
		log::debug ('Fetching user from the database');
		
		// Try to fetch the user's information from the database
		$user = $this->sql->select ($search, $this->db_users);
		
		if (isset ($user['authid']))
			{
			if ($this->db_info)
				{
				// Collect any additional information about the user
				$info = $this->sql->select (array ('authid' => $user['authid']), $this->db_info);
				
				if (isset ($info['authid']))
					{
					// Merge it into the user array
					$user = array_merge ((array) $user, $info);
					}
				}
			
			if ($this->db_groups && $user['group'] > 0)
				{
				// Collect any group data from the database
				$user['groupdata'] = $this->sql->select (array ('group' => $user['group']), $this->db_groups);
				}
			
			if ($this->db_settings)
				{
				// Collect any user program settings
				$user['settings'] = $this->sql->select (array ('authid' => $user['authid']), $this->db_settings);
				}
			
			if ($update_class)
				{
				$this->user		= $user;
				$this->authid	= $user['authid'];
				$this->groupid	= $user['group'];
				$this->username	= $user['username'];
				$this->sections	= json_decode ($user['sections'], true);
				$this->settings = ($this->db_settings) ? $user['settings'] : array ();
				
				return true;
				}
			
			return $user;
			}
			
		if ($update_class)
			{
			// We failed to find a user
			$this->user = array ();
			}
		
		return false;
		}
	
	/**
	 * Creates a new user account in the database
	 * 
	 * @param string $username		The username
	 * @param string $password		The users unencrypted password
	 * @param array  $options		An additional list of options to set for the account
	 *
	 * @return bool|mixed
	 */
	public function user_add ($username, $password, array $options = array ())
		{
		log::info ('Adding a new user to the database: ' . $username);
		
		if ($this->check_username ($username))
			{
			return $this->record_error ('Username already in use');
			}
		
		$data = array (
			'username' 	=> $username,
			'password' 	=> $this->encrypt ($password),
			'group'		=> $this->group,
			);
		
		// Append any additional options
		foreach ($options as $key => $value)
			{
			$data[$key] = $value;
			}
		
		return $this->sql->insert ($data, $this->db_users);
		}
	
	/**
	 * Remove the user account from the database. If we're deleting our own account
	 * make sure it's logged out after.
	 * 
	 * @param int $authid	The authid of the account to delete
	 *
	 * @return bool
	 */
	public function user_delete ($authid)
		{
		log::info ('Deleting a user from the database: ' . $authid);
		
		$this->sql->delete ($authid, $this->db_users);
		
		// Did we just delete our own account?
		if ($authid == $this->authid && $this->sql->affected () > 0)
			{
			$this->logout ();
			}
		
		return true;
		}
	
	/**
	 * Update any additional user information for their account
	 *
	 * @param int   $authid		The authid of the account to update
	 * @param array $info		An array of information to update (in key => value format)
	 */
	public function user_update_info ($authid, $info)
		{
		if ($this->db_info)
			{
			$record = $this->sql->select (array ('authid' => $authid), $this->db_info);
			
			if (isset ($record['authid']))
				{
				$this->sql->update ($authid, $info, $this->db_info, 'authid');
				}
			else
				{
				$this->sql->insert (array_merge ($info, array ('authid' => $authid)), $this->db_info, 'authid');
				}
			}
		}
	
	/**
	 * Update the settings table for the user account
	 * 
	 * @param int   $authid		The authid of the account to update
	 * @param array $settings	An array of settings to apply (in key => value format)
	 */
	public function user_update_settings ($authid, array $settings)
		{
		if ($this->db_settings)
			{
			$record = $this->sql->select (array ('authid' => (int) $authid), $this->db_settings);
			
			if (isset ($record['authid']))
				{
				$this->sql->update ((int) $authid, $settings, $this->db_settings, 'authid');
				}
				else
				{
				$this->sql->insert (array_merge ($settings, array ('authid' => (int) $authid)), $this->db_settings, 'authid');
				}
			}
		}
	
	/**
	 * Updates the user's username in the database. If we're updating our own username
	 * make sure to adjust the class, cookies, and session information also.
	 * 
	 * @param int    $authid		The authid of the username to update
	 * @param string $username		The new username
	 *
	 * @return bool
	 */
	public function user_update_username ($authid, $username)
		{
		log::debug ('Changing the username for ' . $authid . ' to ' . $username);
		
		if ($this->check_username ($username))
			{
			return $this->record_error ('That username is already in use.');
			}
		
		if ($authid == $this->authid)
			{
			// Update this separately since the class could be loaded with another user
			$this->username = $username;
			}
		
		if ($authid == $_SESSION['session_auth_id'])
			{
			// Update our own session information
			$_SESSION['session_auth_username'] = $username;
			
			if ($this->cookie_get ('auth_username'))
				{
				$this->cookie_set ('auth_username',	$this->username, 1728000);
				}
			}
		
		$this->sql->update ($authid, array ('username' => $username), $this->db_users, 'authid');
		
		return true;
		}
	
	/**
	 * Updates the user's password in the database. If we're updating our own password
	 * make sure to adjust the class, cookies, and session information also.
	 * 
	 * @param int    $authid		The authid of the user to update
	 * @param string $password		The new unencrypted password
	 *
	 * @return string Returns the encrypted password
	 */
	public function user_update_password ($authid, $password)
		{
		log::warning ('Changing the users password: ' . $authid);
		
		$password = $this->encrypt ($password);
		
		if ($authid == $this->authid)
			{
			// Update the class first
			$this->user['password'] = $password;
			}
		
		if ($authid == @$_SESSION['session_auth_id'])
			{
			// Update our own session information
			$_SESSION['session_auth_password'] = $this->encrypt_session ($password);
			}
		
		$this->sql->update ($authid, array (
			'password' => $password,
			'last_refresh' => date ('Y-m-d')
			), $this->db_users, 'authid');
		
		return $password;
		}
	
	/**
	 * Updates the user's account status
	 * 
	 * @param int $authid	The authid of the user to update
	 * @param int $status	The new status
	 */
	public function user_update_status ($authid, $status)
		{
		$this->sql->update ($authid, array ('status' => (int) $status), $this->db_users, 'authid');
		}
	
	/**
	 * Updates the user's rank
	 *
	 * @param int $authid	The authid of the user to update
	 * @param int $rank		The new rank
	 */
	public function user_update_rank ($authid, $rank)
		{
		$this->sql->update ($authid, array ('rank' => (int) $rank), $this->db_users, 'authid');
		}
	
	/**
	 * Updates the user's group
	 *
	 * @param int $authid	The authid of the user to update
	 * @param int $group	The new group
	 */
	public function user_update_group ($authid, $group)
		{
		$this->sql->update ($authid, array ('group' => (int) $group), $this->db_users, 'authid');
		}
	
	/**
	 * This method is used to keep a record of the users activity throughout the site. As they
	 * navigate different pages, each one is recorded into a cookie that can be recalled via
	 * the website.
	 * 
	 * @return mixed
	 */
	private function user_tracker ()
		{
		$progress = '';
		
		if ($this->cookie && $this->cookie_track)
			{
			if (! $this->cookie_get ('user_tracker'))
				{
				$progress = json_decode ($this->cookie_get ('user_tracker'), true);
				}
			
			// Collect the new information
			$progress[]	= \limbo::request ()->url;
			$progress	= json_encode (array_slice ($progress, -5));
			
			// Set the latest chunk into the cookie
			$this->cookie_set ('user_tracker', $progress, 3600);
			}
		
		return $this->progress = json_decode ($progress);
		}
	
	/********************************************************************************
	 * Section management methods
	 *******************************************************************************/
	
	/**
	 * Gives a user account access to a section
	 * 
	 * @param int $authid		The authid of the user to update
	 * @param int $section		The ID of the new section
	 *
	 * @return bool
	 */
	public function section_add ($authid, $section)
		{
		log::debug ('Giving user ' . $authid . ' access to section ' . $section);
		
		if ($user = $this->user (array ('authid' => $authid), false))
			{
			$user_sections = json_decode ($this->user['sections'], true);
			
			if (! in_array ($section, $user_sections))
				{
				$user_sections[] = (int) $section;
				
				$this->sql->update ($authid, array (
					'sections' => json_encode ($user_sections)
					), $this->db_users, 'authid');
				
				return true;
				}
			
			log::debug ('User already has access to that section');
			}
		
		return false;
		}
	
	/**
	 * Removes a users access to a section
	 * 
	 * @param int $authid		The authid of the user to update
	 * @param int $section		The ID of the section to remove
	 *
	 * @return bool
	 */
	public function section_remove ($authid, $section)
		{
		log::debug ('Removing user ' . $authid . ' from section ' . $section);
		
		if ($user = $this->user (array ('authid' => $authid), false))
			{
			$user_sections = json_decode ($this->user['sections'], true);
			
			if ($key = array_search ($section, $user_sections))
				{
				unset ($user_sections[$key]);
				
				$this->sql->update ($authid, array (
					'sections' => json_encode (array_values ($user_sections))
					), $this->db_users, 'authid');
				
				return true;
				}
			}
		
		return false;
		}
	
	/**
	 * Resets a users section list to the provided array of sections
	 * 
	 * @param int   $authid		The authid of the user to update
	 * @param array $sections	New list of sections
	 *
	 * @return bool
	 */
	public function section_update ($authid, $sections)
		{
		log::debug ('Giving user ' . $authid . ' access to sections ' . implode (', ', $sections));
		
		if ($user = $this->user (array ('authid' => $authid), false))
			{
			$this->sql->update ($authid, array (
				'sections' => json_encode ((array) $sections)
				), $this->db_users, 'authid');
			
			return true;
			}
		
		return false;
		}
	
	/********************************************************************************
	 * Blocking methods
	 *******************************************************************************/
	
	/**
	 * Checks if the supplied identifier (keyname, usually the IP) is blocked for some reason.
	 * If it is blocked, it will return the reason.
	 * 
	 * @param string $keyname	The unique identifier for the block (usually the IP address)
	 *
	 * @return bool|string
	 */
	public function blocked_check ($keyname)
		{
		if ($this->db_blocked)
			{
			$result = $this->sql->prepare ("SELECT * FROM `?` WHERE `keyname` REGEXP '^?'", array (
				$this->db_blocked,
				$keyname
				), false);
			
			if ($this->sql->num_rows (null, $result))
				{
				$fetch = $this->sql->fetch_result ($result);
				
				return $this->sql->strip ($fetch['reason']);
				}
			}
		
		return false;
		}
	
	/**
	 * Add a block record into the database
	 * 
	 * @param string $keyname	A unique identifier for the block (The users IP perhaps)
	 * @param string $reason	The reason they are being blocked
	 */
	public function blocked_add ($keyname, $reason)
		{
		if ($this->db_blocked && ! empty ($keyname))
			{
			$this->sql->insert (array (
				'blocked'	=> date ('Y-m-d H:i:s'),
				'adminid'	=> $this->authid,
				'keyname'	=> $keyname,
				'reason'	=> $reason,
				), $this->db_blocked);
			}
		}
	
	/**
	 * Remove a block record from the database
	 * 
	 * @param $keyname
	 */
	public function blocked_remove ($keyname)
		{
		if ($this->db_blocked)
			{
			$this->sql->prepare ("DELETE * FROM `?` WHERE `keyname` = ?", array ($this->db_blocked, $keyname));
			}
		}
	
	/********************************************************************************
	 * Cookie methods
	 *******************************************************************************/
	
	/**
	 * Initialize the cookie setup
	 */
	private function cookie_init ()
		{
		if ($this->cookie)
			{
			if (! ini_get ('session.use_cookies'))
				{
				log::warning ('Cookies are disabled in the PHP ini but are enabled in the config');
				
				$this->cookie = false;
				}
				else
				{
				$this->cookie_host = config ('web.hostname');
				$this->cookie_path = config ('web.root');
				}
			}
		}
	
	/**
	 * Sets a cookie. You can set how long until it expires by specify the expire time in seconds.
	 * 
	 * @param string $name		The name of the cookie
	 * @param string $data		The value of the cookie
	 * @param bool   $expires	How long until it expires (in seconds) / or false
	 */
	public function cookie_set ($name, $data, $expires = false)
		{
		if ($this->cookie)
			{
			if ((int) $expires > 0)
				{
				setcookie ($name, $data, time () + $expires, $this->cookie_path, $this->cookie_host);
				}
				else
				{
				setcookie ($name, $data, 0, $this->cookie_path, $this->cookie_host);
				}
			}
		}
	
	/**
	 * Retrieve a cookie.
	 * 
	 * @param string $name	The name of the cookie
	 *
	 * @return bool
	 */
	public function cookie_get ($name)
		{
		if (isset ($_COOKIE[$name]))
			{
			return $_COOKIE[$name];
			}
		
		return false;
		}
	
	/**
	 * Takes the currently loaded user and saves cookies based on their info. This is usually done after
	 * the user logs in to the site.
	 */
	public function cookie_save ()
		{
		if ($this->cookie)
			{
			if (! empty ($this->username))
				{
				log::debug ('Try to save some cookies for this session for ' . $this->cookie_host);
				
				$this->cookie_set ('auth_id', $this->authid, 1728000);
				$this->cookie_set ('auth_username',	$this->username, 1728000);
				}
			}
		}
	
	/**
	 * Verifies that the two previous auth cookies are valid and loads the class with that users
	 * information. Lets the site remember users the next time they visit.
	 * 
	 * If all goes well, the class is loaded and the authid is tied to the session.
	 */
	public function cookie_check ()
		{
		if ($this->cookie)
			{
			log::debug ('Checking for valid authentication cookies');
			
			$authid		= $this->cookie_get ('auth_id');
			$username	= $this->cookie_get ('auth_username');
			
			if ($authid && $username)
				{
				// Query the database to validate the username and password
				$result = $this->sql->prepare ("SELECT * FROM `?` WHERE `authid` = ? AND `username` = ?", array (
					$this->db_users,
					$authid,
					$username,
					), false);
				
				if ($this->sql->num_rows (null, $result) > 0)
					{
					$this->session['authid'] = $authid;
					
					$this->user (array ('authid' => $this->session['authid']));
					}
				}
			}
		}
	
	/********************************************************************************
	 * Encryption methods
	 *******************************************************************************/
	
	/**
	 * Encrypt the input using the specified method. This is mostly used to encrypt the
	 * users password for inserting into the database.
	 * 
	 * @param string $input		The plaintext password (or some other string)
	 *
	 * @return string 			Returns the encrypted string
	 */
	private function encrypt ($input)
		{
		switch ($this->pass_type)
			{
			case 'sha':
				return sha1 ($this->pass_salt . $input);
			
			case 'crc':
				return sprintf ('%u', crc32 ($this->pass_salt . $input));
			
			case 'bcrypt':
				return security::bcrypt ($input);
			
			case 'aes':
				return security::encrypt ($input, $this->pass_key, security::generate_iv ());
			}
		
		return md5 ($this->pass_salt . $input);
		}
	
	/**
	 * This is a little validation method that is used to identify a users session. The result
	 * of this method is usually stored inside the session and is checked again every time we
	 * want to know if they are authenticated or not.
	 * 
	 * @param string $input		A string to input. Usually this is the encrypted password
	 *
	 * @return string			A md5 hash for unique identification
	 */
	private function encrypt_session ($input)
		{
		return md5 ($this->pass_salt . $this->address . '_auth_' . $input);
		}
	
	/********************************************************************************
	 * User lockout methods
	 *******************************************************************************/
	
	/**
	 * Checks if the user account is locked due to trying to authenticate unsuccessfully
	 * to many times. If they are still in the timeout period, it increases the users attempt
	 * count. If they are free to try again then clear out their timeout records to give them
	 * a fresh start.
	 * 
	 * @return bool Returns TRUE if the account is locked, FALSE if it's not locked
	 */
	private function lockout_check ()
		{
		if ($this->lock_count > 0 && isset ($this->user['try_count']))
			{
			if ($this->user['try_count'] >= $this->lock_count)
				{
				// Get the timestamp of when the timeout period is over
				$unlock_time = $this->user['try_last'] + $this->lock_time;
				
				if (time () < $unlock_time)
					{
					// Not enough time has passed. Increase their count and
					// start the clock over again, 5 more minutes please.
					$this->lockout_failure ();
					
					return true;
					}
					else
					{
					$this->lockout_reset ();
					}
				}
			}
		
		return false;
		}
	
	/**
	 * Increase the users login attempt counter and stamp this last failed attempt time
	 */
	private function lockout_failure ()
		{
		if (isset ($this->user['try_count']))
			{
			$this->user['try_count'] ++;
			
			log::debug ('Increasing the users failure count');
			
			$this->sql->update ($this->authid, array (
				'try_count'	=> $this->user['try_count'],
				'try_last'	=> time (),
				), $this->db_users, 'authid');
			}
		}
	
	/**
	 * Removes any previous login attempt counters
	 */
	private function lockout_reset ()
		{
		if (isset ($this->user['try_count']))
			{
			$this->sql->update ($this->authid, array (
				'try_count'	=> 0,
				'try_last'	=> 0,
				), $this->db_users, 'authid');
			}
		}
	
	/********************************************************************************
	 * Misc methods
	 *******************************************************************************/
	
	/**
	 * Logs a user activity into the database. This is usually called from within the
	 * application during special events such as creating records, deleting data, etc.
	 * 
	 * @param string $comment The comment to log
	 */
	public function audit ($comment)
		{
		if ($this->db_audit)
			{
			$this->sql->insert (array (
				'authid'	=> @$this->authid,
				'datestamp'	=> date ('Y-m-d H:i:s'),
				'script'	=> \limbo::request()->pid,
				'comment'	=> $comment
				), $this->db_audit);
			}
		}
	
	/**
	 * Redirect the user somewhere else. This is usually called when some sort of authentication
	 * method fails. They are usually redirected back to the login page to try again. Because of
	 * this we try to figure out where they failed at, this way they are sent back there once they
	 * authenticate again.
	 * 
	 * @param string $location The path to where you want them to go
	 *
	 * @return bool
	 */
	private static function redirect ($location)
		{
		if (! empty ($location))
			{
			// Figure out where to redirect them after they log back in
			if (strpos ($location, '?pid=') === false)
				$query = preg_replace ('/^pid\=/', '', $_SERVER['QUERY_STRING']);
				else
				$query = $_SERVER['QUERY_STRING'];
			
			web::redirect ($location, array ('redirect' => base64_encode ($query)));
			}
		
		return false;
		}
	
	/********************************************************************************
	 * Logout methods
	 *******************************************************************************/
	
	/**
	 * Logs the user out of the application. If a hard reset is specified then the session is
	 * deleted and cookies destroyed, otherwise the session is changed to unauthenticated and
	 * special session variables are removed.
	 * 
	 * @param bool   $hard_reset	Perform a hard reset or not
	 * @param string $redirect		Redirect to this path after logging the user out
	 */
	public function logout ($hard_reset = true, $redirect = '')
		{
		// Delete any heartbeats this user had
		if (session_id () && $this->db_heartbeat)
			{
			$this->sql->delete (session_id (), $this->db_heartbeat, 'sessionid');
			}
			
		if ($hard_reset)
			{
			$this->logout_reset ();
			}
			else
			{
			$this->logout_gently ();
			}
		
		self::redirect ($redirect);
		}
	
	/**
	 * Logs out the user forcefully. Deletes the session from the database, clears all cookies
	 * and destroys the session.
	 */
	private function logout_reset ()
		{
		log::debug ('Logging out (hard)');
		
		// Delete the session from the database
		if ($this->db_sessions && session_id ())
			{
			$this->sql->delete (session_id (), $this->db_sessions, 'sessionid');
			}
		
		// Delete the session cookie
		if (isset ($_COOKIE['PHPSESSID']))
			{
			setcookie ('PHPSESSID', '', 1, $this->cookie_path, $this->cookie_host);
			}
		
		if ($this->cookie)
			{
			// Delete any special session cookies we may have set
			setcookie ('auth_id', '', 1, $this->cookie_path, $this->cookie_host);
			setcookie ('auth_username', '', 1, $this->cookie_path, $this->cookie_host);
			}
		
		@session_destroy ();
		}
	
	/**
	 * Logs the user out gently. Simply changes the session to unauthenticated and deletes
	 * the session variables.
	 * 
	 * The session stays intact.
	 */
	private function logout_gently ()
		{
		log::debug ('Logging out (normal)');
		
		// Change the database session to unauthenticated
		if ($this->db_sessions && isset ($this->session['id']))
			{
			$this->sql->update ($this->session['id'], array (
				'authid'	=> 0,
				'stamp'		=> time (),
				'ip'		=> $this->address,
				), $this->db_sessions);
			}
		
		// Clear all the auth session variables
		foreach ($_SESSION as $name => $value)
			{
			if (preg_match ('/^session_auth_/', $name))
				{
				unset ($_SESSION[$name]);
				}
			}
		}
	}

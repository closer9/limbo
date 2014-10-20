<?php
namespace limbo\util;

use \limbo\log;

/**
 * First created - 04/30/2002
 * 
 * This was one of my first classes back in 2002. This class simply connects to a SMTP 
 * server and sends an e-mail. Not a lot to it really.
 * 
 * Class smtp
 * @package limbo\util
 */
class smtp
	{
	const CRLF	 = "\r\n";
	const LENGTH = 998;
	
	/**
	 * @var resource The socket resource used for the SMTP conection
	 */
	private $socket;
	
	/**
	 * @var string The hostname of the SMTP server
	 */
	private $server;
	
	/**
	 * @var int The default port to connect to
	 */
	private $port = 25;
	
	/**
	 * @var int The amount of time to wait for the connection in seconds
	 */
	private $timeout = 60;
	
	/**
	 * @var string The optional username to use to authenticate to the server
	 */
	private $username;
	
	/**
	 * @var string The optional password
	 */
	private $password;
	
	/**
	 * Build the SMTP object and setup any default configuration options. You can overwrite
	 * the defaults by sending the options as a key => value pair array.
	 * 
	 * @param array $options Any override options
	 */
	public function __construct (array $options = array ())
		{
		$this->server 	= config ('smtp.server');
		$this->username = config ('smtp.username');
		$this->password = config ('smtp.password');
		
		if (config ('smtp.port'))
			$this->port = config ('smtp.port');
		
		if (config ('smtp.timeout'))
			$this->timeout = config ('smtp.timeout');
		
		$this->set_options ($options);
        }
	
	/**
	 * Sets global class options
	 *
	 * @param array $options
	 */
	private function set_options (array $options)
		{
		foreach ($options as $name => $value)
			{
			$this->$name = $value;
			}
		}
	
	/**
	 * Initiates a connection to the SMTP server. We can also send any additional
	 * override options here. If there is a username specified, we'll also try 
	 * to authenticate.
	 * 
	 * @return bool true on successful connection, false on any failure
	 */
	public function connect (array $options = array ())
        {
		$this->set_options ($options);
		
		log::debug ("SMTP: Connecting to {$this->server}");
		
		$this->socket = fsockopen ($this->server, $this->port, $errno, $errstr, $this->timeout);
			
		if (! $this->socket)
			{
			log::error ("SMTP: Could not connect to {$this->server}");
			log::debug ("SMTP: {$errstr}");
			
			return false;
			}
		
		stream_set_timeout ($this->socket, $this->timeout, 0);
		
		$this->get_data();
		$this->hello ();
		
		if (! empty ($this->username))
			{
			return $this->authenticate ();
			}
		
		return true;
        }
	
	/**
	 * Verify that the connection is available.
	 * 
	 * @return bool
	 */
	private function check_connection ()
		{
		if (is_resource ($this->socket))
			{
			return true;
			}
		
		log::debug ('SMTP: No connection to the server');
		
		return false;
		}
	
	/**
	 * Close the connection to the server.
	 */
	public function close ()
        {
		if (is_resource ($this->socket))
			{
			log::debug ('SMTP: Closing the socket');
			
			fclose ($this->socket);
			}
        }
	
	/**
	 * Gracefully log out of the server and close the connection.
	 */
	public function quit ()
		{
		log::debug ('SMTP: Quitting');
		
		if ($this->send_command ('QUIT', 221))
			{
			$this->close ();
			}
		}
	
	/**
	 * Initiate the SMTP conversation by sending the HELO or EHLO command. We'll try
	 * to talk using the extended protocol (EHLO) first, and fall back to normal HELO 
	 * if there is an issue.
	 * 
	 * @return bool
	 */
	public function hello ()
		{
		return (boolean) ($this->send_hello ('EHLO') or $this->send_hello ('HELO'));
		}
	
	/**
	 * Actually send the hello commands.
	 * 
	 * @param string $type The hello type HELO or EHLO.
	 *
	 * @return bool
	 */
	private function send_hello ($type)
		{
		return $this->send_command ($type . ' ' . $this->server, 250);
		}
	
	/**
	 * Try to start a TLS session by sending the STARTTLS command and adjusting the socket
	 * to use crypto.
	 * 
	 * @return bool
	 */
	public function starttls ()
		{
		if ($this->send_command ('STARTTLS', 220))
			{
			if (stream_socket_enable_crypto ($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT))
				{
				return true;
				}
			}
		
		return false;
		}
	
	/**
	 * This method sends all the commands to the SMTP server and verifies that the code 
	 * we're expecting is valid or not.
	 * 
	 * @param string $command	The command to send to the server
	 * @param int    $code		The expected result code
	 *
	 * @return bool
	 */
	private function send_command ($command, $code)
		{
		log::debug ('SMTP: Sending the command: ' . $command);
		
		$this->send_data ($command);
		
		if ($this->check_code ($code))
			{
			return true;
			}

		return false;
		}
	
	/**
	 * Reads the stream from the server looking for the magic three digit code. If the code matches
	 * what we're expecting then return true. You can specify more than one code by sending an array
	 * of codes as the first parameter.
	 * 
	 * @param int|array $code The code (or codes) we're expecting to see
	 *
	 * @return bool
	 */
	private function check_code ($code)
		{
		$received = (int) substr ($this->get_data (), 0, 3);
		
		if (in_array ($received, (array) $code))
			{
			log::debug ('SMTP: Code check passed: ' . $received);
			
			return true;
			}
		
		log::debug ('SMTP: Code check failed. Expecting ' . implode (',', (array) $code) . ' but got ' . $received);
		
		return false;
		}
	
	/**
	 * Issue the reset command to the server. This tells the server to start over bringing it to 
	 * it's initial state.
	 * 
	 * @return bool
	 */
	public function reset ()
		{
		log::debug ('SMTP: Reset');
		
		return $this->send_command ('RSET', 250);
		}
	
	/**
	 * Try to authenticate to the server.
	 * 
	 * @return bool
	 */
	public function authenticate ()
        {
		log::debug ('SMTP: Authenticating');
		
		if (! $this->send_command ('AUTH LOGIN', 334))
			return false;
		
		if (! $this->send_command ($this->username, 334))
			return false;
		
		if (! $this->send_command ($this->password, 235))
			return false;

		return true;
        }
	
	/**
	 * Make sure a connection exists and send data to the socket.
	 * 
	 * @param string $data The data to send
	 *
	 * @return bool|int Returns the number of bytes written or false on failure
	 */
	private function send_data ($data)
		{
		if (! $this->check_connection ())
			return false;
		
		log::debug ('SMTP: Sending data: ' . $data);
        
		return fwrite ($this->socket, $data . self::CRLF);
        }
	
	/**
	 * Checks for a valid connection and tries to retrieve data from the server. This method
	 * really tries to check for server timeouts (they do happen), and will return whatever data
	 * it's received in the event that a timeout does happen.
	 * 
	 * @return bool|string Returns the data from the server, false on failure
	 */
	private function get_data ()
		{
		if (! $this->check_connection ())
			{
			return false;
			}
		
		$return		= '';
		$timeout	= time () + $this->timeout;
		
		while (is_resource ($this->socket) && ! feof ($this->socket))
			{
			$incoming = @fgets ($this->socket, 512);
			
			log::debug ('SMTP: Received data: ' . trim ($incoming));

			$return .= $incoming;

			if (isset ($incoming[3]) && $incoming[3] == ' ')
				{
				break;
				}

			$info = stream_get_meta_data ($this->socket);

			if ($info['timed_out'])
				{
				log::debug ('SMTP: Meta timeout while receiving data');

				break;
				}

			if (time () > $timeout)
				{
				log::debug ('SMTP: Hard timeout while receiving data');

				break;
				}
			}
		
		return trim ($return);
        }
	
	/**
	 * Sends a e-mail message to a list of recipients.
	 * 
	 * @param string|array $recipients	A list of recipients to send to (or just 1 as a string)
	 * @param string       $from		The e-mail address of the sender
	 * @param array        $header		An array of headers to send with the e-mail
	 * @param string       $body		The message body
	 *
	 * @return bool
	 */
	public function send_message ($recipients, $from, array $header, $body = '')
		{
		$this->send_command ("MAIL FROM: <{$from}>", 250);

		foreach ((array) $recipients as $address)
			{
			$this->send_command ('RCPT TO: <' . $address . '>', array (250, 251));
			}
		
		if ($this->send_command ('DATA', 354))
			{
			$headers = array ();
			
			foreach ($header as $key => $value)
				{
				$headers[] = $key . ": " . $value;
				}
			
			$headers = implode (self::CRLF, $headers);
			$headers = preg_replace ('/^\.[^.]/mis', '', $headers);

			$body = str_replace (self::CRLF . '.', self::CRLF . '..', $body);
			$body = (strlen ($body) > 0 && $body{0} == '.') ? '.' . $body : $body;

			$this->send_data ($headers . self::CRLF);
			$this->send_data ($body);
			
			if ($this->send_command ('.', 250))
				{
				return true;
				}
			}
		
		return false;
        }
	
	/**
	 * An easy to use wrapper for the send_message() method. Use this to send any easy to build 
	 * e-mail messages. Use send_message() if you want more control over the building of the e-mail.
	 * 
	 * @param array|string $to				An array of recipients (or a string with just 1 address)
	 * @param string       $from			The e-mail address of the sender
	 * @param string       $subject			The subject of the e-mail
	 * @param string       $message			The body of the message
	 * @param string       $content_type	The optional content-type
	 * @param array        $extra			Any additional e-mail headers to send
	 */
	public function mail ($to, $from, $subject, $message = '', $content_type = 'text/plain', $extra = array ())
		{
		$headers = array (
			'From'			=> $from,
			'To'			=> implode (', ', (array) $to),
			'Subject'		=> $subject,
			'Content-Type'	=> $content_type,
			);
		
		if (! $this->check_connection ())
			{
			$this->connect ();
			}
		
		// Send the e-mail message
		$this->send_message ((array) $to, $from, array_merge ($headers, $extra), $message);
		
		$this->quit ();
		}
    }
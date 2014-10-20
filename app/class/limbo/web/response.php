<?php
namespace limbo\web;

use limbo\error;
use \limbo\log;

/**
 * This class is used to respond to most of the requests generated from this application. Any time
 * a template is rendered, or a page redirected the populated data in this class is sent to the 
 * browser.
 * 
 * Class response
 * @package limbo\web
 */
class response
	{
	/**
	 * @var int The HTTP status code to reply with
	 */
	protected $code;
	
	/**
	 * @var array An array headers to send in a key => value pair format
	 */
	protected $headers;
	
	/**
	 * @var string The body to reply with
	 */
	protected $body;
	
	/**
	 * @var bool Remembers if we've sent the cache headers already or not
	 */
	protected $cache;
	
	/**
	 * @var array A nice list of HTTP response codes and it's description
	 */
	public static $codes = array (
		100 => 'Continue',
		101 => 'Switching Protocols',
		102 => 'Processing',
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		207 => 'Multi-Status',
		208 => 'Already Reported',
		226 => 'IM Used',
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		306 => '(Unused)',
		307 => 'Temporary Redirect',
		308 => 'Permanent Redirect',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Payload Too Large',
		414 => 'URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Range Not Satisfiable',
		417 => 'Expectation Failed',
		422 => 'Unprocessable Entity',
		423 => 'Locked',
		424 => 'Failed Dependency',
		426 => 'Upgrade Required',
		428 => 'Precondition Required',
		429 => 'Too Many Requests',
		431 => 'Request Header Fields Too Large',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
		506 => 'Variant Also Negotiates',
		507 => 'Insufficient Storage',
		508 => 'Loop Detected',
		510 => 'Not Extended',
		511 => 'Network Authentication Required'
		);
	
	/**
	 * Constructs the object and can optionally start building the response right away.
	 * 
	 * @param string $body		The body to send
	 * @param int    $code		The response code to reply with
	 * @param array  $headers	Any additional headers to send
	 */
	public function __construct ($body = '', $code = 200, array $headers = array ())
		{
		$this->clear ();
		
		$this->write ($body);
		$this->status ($code);
		$this->header ($headers);
		}
	
	/**
	 * Clears all the information we have saved for the response and sets the defaults.
	 * 
	 * @return $this
	 */
	public function clear ()
		{
		$this->code		= 200;
		$this->headers	= array ();
		$this->body		= '';
		$this->cache	= false;
		
		return $this;
		}
	
	/**
	 * Updates the current response code. If a new code is not supplied then we'll return
	 * the current code.
	 * 
	 * @param int|null $code The new response code
	 *
	 * @return $this|int Returns the current code or this object
	 * @throws \limbo\error on invalid response code
	 */
	public function status ($code = null)
		{
		if ($code === null)
			{
			return $this->code;
			}
		
		// Make sure that the supplied code is valid
		if (! array_key_exists ($code, self::$codes))
			{
			throw new \limbo\error ('Invalid status code');
			}
		
		$this->code = $code;
		
		return $this;
		}
	
	/**
	 * Sets or updates the response headers. You can supply an array of key => value
	 * pairs as the first parameter for setting multiple headers. You can also send
	 * an array of values for a specific header (Cache-Control anyone?)
	 * 
	 * @param string|array $name	An array of headers or the name of the header to set
	 * @param string|array $value	The value (or values) of the header
	 *
	 * @return $this
	 */
	public function header ($name, $value = '')
		{
		if (is_array ($name))
			{
			foreach ($name as $key => $value)
				{
				$this->headers[$key] = $value;
				}
			}
			else
			{
			$this->headers[$name] = $value;
			}
		
		return $this;
		}
	
	/**
	 * Appends data to the response body. You can optionally tell this method to replace the
	 * currently buffered body with this incoming string.
	 * 
	 * @param string $body		The data to append to or replace the response body
	 * @param bool   $replace	
	 *
	 * @return $this
	 */
	public function write ($body, $replace = false)
		{
		if ($replace)
			{
			$this->body = $body;
			}
			else
			{
			$this->body .= $body;
			}
		
		return $this;
		}
	
	/**
	 * Builds the caching headers for this request. The $expires variable can be a normal unix
	 * timestamp of the date the content is to expire, a readable date of that day, or the amount
	 * of time in seconds until the content expires.
	 * 
	 * If you send false as the first parameter then the content is assumed to not be cached.
	 * 
	 * @param bool|int $expires The expiration date or the amount of seconds until it expires
	 *
	 * @return $this
	 * 
	 * @throws error if the expires parameter is invalid
	 */
	public function cache ($expires)
		{
		$this->cache = true;
		
		if ($expires === false)
			{
			$this->header (array (
				'Pragma'		=> 'no-cache',
				'Expires'		=> 'Mon, 18 Sept 1980 04:07:22 GMT',
				'Cache-Control'	=> array (
					'no-store, no-cache, must-revalidate',
					'post-check=0, pre-check=0',
					'max-age=0'
					)
				));
			}
			else
			{
			if (is_int ($expires))
				{
				if ($expires < time ())
					{
					$expires = time () + $expires;
					}
				}
				else
				{
				$expires = strtotime ($expires);
				}
			
			$this->header (array (
				'Expires'		=> gmdate ('D, d M Y H:i:s', $expires) . ' GMT',
				'Cache-Control'	=> array (
					'public, max-age=' . ($expires - time ()) . ', must-revalidate'
					)
				));
			}
		
		return $this;
		}
	
	/**
	 * Send the ETag type caching header to the client.
	 * 
	 * @param string $tag	A unique ETag to identify this page as
	 * @param string $type	Either a strong or weak type setting
	 *
	 * @return $this
	 */
	public function etag ($tag, $type = 'strong')
		{
		$this->cache = true;
		
		// Check if we are receiving the ETag from this request
		if (\limbo::request ()->etag === $tag)
			{
			\limbo::halt (304);
			}
		
		$this->header ('ETag', (($type === 'weak') ? 'W/' : '') . $tag);
		
		return $this;
		}
	
	/**
	 * Send the Last-Modified type caching header to the client.
	 * 
	 * @param int $time A unix timestamp of the last modified time
	 *
	 * @return $this
	 */
	public function last_modified ($time)
		{
		$this->cache = true;
		
		// Check if we are receiving the response to the last-modified header
		if (\limbo::request ()->modified === $time)
			{
			\limbo::halt (304);
			}
		
		$this->header ('Last-Modified', gmdate ('D, d M Y H:i:s T', $time));
		
		return $this;
		}
	
	/**
	 * Send the array of headers to the client.
	 * 
	 * @return $this
	 */
	public function send_headers ()
		{
		if (\limbo::invoked_from () == 'cli')
			{
			// If this is from the CLI just send the status
			header ("Status: {$this->code} " . self::$codes[$this->code]);
			}
			else
			{
			// Send the full blown response header
			$header = \limbo::request ()->scheme . ' ' . $this->code . ' ' . self::$codes[$this->code];
			
			header ($header, true, $this->code);
			}
		
		// No caching information has been set yet
		if (! $this->cache)
			{
			log::debug ('Sending default caching headers');
			
			$this->cache ((config ('web.cache')) ? 3600 : false);
			}
		
		foreach ($this->headers as $field => $values)
			{
			if (is_array ($values))
				{
				foreach ($values as $value)
					{
					if (! empty ($value)) header ($field . ': ' . $value, false);
					}
				}
				else
				{
				if (! empty ($values)) header ($field . ': ' . $values);
				}
			}
		
		foreach (headers_list () as $header)
			{
			log::debug ("Sent header: {$header}");
			}
		
		return $this;
		}
	
	/**
	 * Begin sending the response.
	 */
	public function send ()
		{
		if (ob_get_length () > 0)
			{
			ob_end_clean ();
			}
		
		if (! headers_sent ())
			{
			$this->send_headers ();
			}
		
		log::debug ("Sending the page ({$this->code})");
		
		exit ($this->body);
		}
	}
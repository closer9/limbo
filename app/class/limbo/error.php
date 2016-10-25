<?php
/*
First created - 07/12/2003
Written By - Scott M (closer9@gmail.com)                     
*/

namespace limbo;

use \limbo\util\smtp;
use \limbo\web\web;

/**
 * Class error
 * @package limbo
 */
class error extends \Exception
	{
	/**
	 * @var bool Do we want to e-mail the error to administrator?
	 */
	private $mail = false;
	
	/**
	 * @var bool Display a simple error message rather than use a template
	 */
	private $simple	= true;
	
	/**
	 * @var bool The HTTP request code to send with the message
	 */
	private $response = 500;
	
	/**
	 * @var string The optional template name that will display the contents of the error message
	 */
	private $content = '';
	
	/**
	 * @var string The main template that the error will be displayed in (not optional)
	 */
	private $template = '';
	
	/**
	 * @var bool Log the error into the normal website log
	 */
	private $log = true;
	
	/**
	 * Errors are thrown into this constructor. You can also throw options at it to update the defaults.
	 * 
	 * @param string $message	The error message to display
	 * @param array  $params	Any optional override parameters
	 */
	public function __construct ($message, array $params = array ())
		{
		parent::__construct ($message);

		$this->mail 	= config ('error.mail');
		$this->simple 	= config ('error.simple');
		$this->content 	= config ('error.content');
		$this->template = config ('error.template');
		
		foreach ($params as $name => $value)
			{
			$this->$name = $value;
			}
		
		$this->display ();
		$this->send_email ();
		
		exit (2);
		}
	
	/**
	 * Send the error message to the browser and optionally log the message
	 */
	private function display ()
		{
		web::clear_buffer ();
		
		if ($this->log)
			{
			log::error ($this->getMessage());
			log::error ($this->getFile ());
			}
		
		// If it looks like this is from the CLI just display the message
		if (\limbo::invoked_from () == 'cli')
			{
			echo "* Error: {$this->getMessage ()} ({$this->getFile ()} [{$this->getLine ()}])\n";
			}
			else
			{
			// Calling for a simple message (or no template specified)
			if ($this->simple || ! $this->template)
				{
				$message = '<h2 style="color: red">An error has occurred</h2>' . $this->getMessage ();
				
				if (! config ('limbo.production'))
					{
					$message .= '<br><h6>File: ' . $this->getFile () . ' (' . $this->getLine (). ')</h6>';
					$message .= '<pre>' . print_r (debug_backtrace (0, 20), true) . '</pre>';
					}
				
				try {
					\limbo::response ()
						->status ($this->response)
						->write ($message)
						->send ();
					}
				catch (\Exception $e)
					{
					exit ($message);
					}
				}
			
			if (! empty ($this->content))
				{
				// Render the error message in the content template
				\limbo::render ($this->content, array (
					'message' 	=> $this->getMessage (),
					'file' 		=> $this->getFile (),
					'line' 		=> $this->getLine ()
					), 'content');
				
				// Render the content inside the display template
				\limbo::render ($this->template, array (
					'title' => config ('web.title') . ' - Error'
					));
				}
				else
				{
				// Just render the display template
				\limbo::render ($this->template, array (
					'title' => config ('web.title') . ' - Error',
					'message' 	=> $this->getMessage (),
					'file' 		=> $this->getFile (),
					'line' 		=> $this->getLine ()
					));
				}
			}
		
		\limbo::stop ($this->response);
		}
	
	/**
	 * Send an e-mail with the details of the error to the application notify address
	 */
	private function send_email ()
		{
		global $smtp;
		
		if (! $this->mail) return;
		
		$message	= "Error message: {$this->getMessage ()}\n"
					. "Filename: {$this->getFile ()} ({$this->getLine ()})\n"
					. "Referer: {$_SERVER['HTTP_REFERER']}\n"
					. "URL: {$_SERVER["REQUEST_URI"]}\n"
					. "Address: {$_SERVER['REMOTE_ADDR']}\n\n"
					. "Backtrace info:\n {$this->getTraceAsString ()}\n\n"
					. 'GET variables: ' . print_r ($_GET, true) . "\n\n"
					. 'POST variables: ' . print_r ($_POST, true) . "\n\n";
		
		if (isset ($_SESSION))
			{
			$message .= 'Session variables: ' . print_r ($_SESSION, true) . "\n\n";
			}
		
		if (! is_object ($smtp))
			{
			$smtp = new smtp ();
			}
		
		$smtp->mail (
			config ('admin.notify'),
			config ('error.from'),
			'Error report for ' . config ('hostname'),
			$message
			);
		}
	}
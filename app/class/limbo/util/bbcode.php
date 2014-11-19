<?php
namespace limbo\util;

class bbcode
	{
	public function parse ($content, $strip = false)
		{
		$content = bbcode::cleanup ($content);
		
		$content = bbcode::bold ($content);
		$content = bbcode::italic ($content);
		$content = bbcode::strike ($content);
		$content = bbcode::underline ($content);
		$content = bbcode::indent ($content);
		$content = bbcode::email ($content);
		$content = bbcode::unorderedList ($content);
		$content = bbcode::orderedList ($content);
		$content = bbcode::listItem ($content);
		$content = bbcode::color ($content);
		$content = bbcode::quote ($content);
		$content = bbcode::image ($content);
		
		$content = $this->code ($content);
		$content = $this->email_form ($content);
		$content = $this->size ($content);
		$content = $this->align ($content);
		
		$content = $this->url ($content);
		
	    if ($strip)
	    	{
	    	$content = strip_tags ($content);
	    	$content = htmlspecialchars ($content);
			}
	    
	    return $content;
		}
	
	public function highlight ($content, $highlight, $class = 'highlighter')
		{
		if (! is_array ($highlight))
			{
			/* Check if there are quotes to deal with */
			if (preg_match ('/(["])(.*?)\1/', $highlight, $matches))
				{
				$quoted = $matches[2];
				
				/* Remove the quoted part from the keywords */
				$highlight = preg_replace ('/' . $matches[0] . '/', '', $highlight);
				
				if (empty ($highlight))
					{
					$highlight = array ($quoted);
					}
					else
					{
					$highlight		= explode (' ', trim ($highlight));
					$highlight[]	= $quoted;
					}
				}
				else
				{
				$highlight = explode (' ', $highlight);
				}
			}
		
		foreach ($highlight as $keyword)
			{
			if (empty ($keyword)) continue;
			
			$keyword = preg_replace ('/"/', '', $keyword);
			
			if (stripos ($content, $keyword) !== false)
				{
				$content = preg_replace ('/(' . preg_quote ($keyword) . ')(?![^\[]*(\[\/url]|"]|\[\/img]))/i', '<span class="' . $class . '">$1</span>', $content);
				}
			}
		
		return $this->parse ($content);
		}
	
	private static function cleanup ($content)
	  	{
		$content = str_replace (array ("\r\n", "\r"), "\n", $content);
		$content = preg_replace ('/<[a-z=\"\s]*>\s*<\/(.*)>/Usi', '', $content);
		$content = nl2br (trim ($content));
		
		return $content;
		}
	
	
	/* [b] [/b] */
	private static function bold ($content)
		{
		return preg_replace	('/\[b\](.*)\[\/b\]/Usi', '<strong>\\1</strong>', $content);
		}

	/* [i] [/i] */
	private static function italic ($content)
		{
		return preg_replace	('/\[i\](.*)\[\/i\]/Usi', '<em>\\1</em>', $content);
		}

	/* [s] [/s] */
	private static function strike ($content)
		{
		return preg_replace	('/\[s\](.*)\[\/s\]/Usi', '<del>\\1</del>', $content);
		}

	/* [u] [/u] */
	private static function underline ($content)
		{
		return preg_replace ('/\[u\](.*)\[\/u\]/Usi', '<u>\\1</u>', $content);
		}
	
	/* [indent] [/indent] */
	private static function indent ($content)
		{
		return preg_replace	('/\[indent\](.*)\[\/indent\]/Usi', '<p class="indent">\\1</p>', $content);
		}
	
	/* [email(=?)] [/email] */
	private static function email ($content)
		{
		$content = preg_replace ('/\[email\](.*)\[\/email\]/Usi', '<a href="mailto:\\1">\\1</a>', $content);
		$content = preg_replace ('/\[email=(.*)\](.*)\[\/email\]/Usi', '<a href="mailto:\\1">\\2</a>', $content);

		return $content;
		}
	
	/* [list] [/list] */
	private static function unorderedList ($content)
		{
		$content = preg_replace ('/\[list\](.*)\[\/list\]/Usi', '<ul class="list compact">\\1</ul>', $content);

		return str_replace ("<ul>\n", '<ul>', $content);
		}
	
	/* [list=1] [/list] */
	private static function orderedList ($content)
		{
		$content = preg_replace ('/\[list=1\](.*)\[\/list\]/Usi', '<ol class="list compact">\\1</ol>', $content);

		return str_replace("<ol>\n", '<ol>', $content);
		}
	
	/* [*] */
	private static function listItem ($content)
		{
		$content = preg_replace ("/\[\*\](.*)\n/Usi", '<li>\\1</li>', $content);

		return str_replace ("</li>\n", '</li>', $content);
		}

	/* [color] [/color] */
	private static function color ($content)
		{
		return preg_replace ('/\[color=([a-z0-9#]*)\](.*)\[\/color\]/Usi', '<span style="color: \\1">\\2</span>', $content);
		}

	/* [quote] [/quote] */
	private static function quote ($content)
		{
		$content = preg_replace ('/\[quote(.*)\]\n/Uis', '[quote\\1]', $content);

		while (preg_match ('/\[quote\](.*)\[\/quote\]/Uis', $content))
		$content = preg_replace ('/\[quote\](.*)\[\/quote\]/Uis', '<div class="quotebox">\\1</div>', $content);

		while (preg_match ('/\[quote=(.*)\](.*)\[\/quote\]/Uis', $content))
		$content = preg_replace ('/\[quote=(.*)\](.*)\[\/quote\]/Uis', '<div class="quotebox"><p class="bold mb6">\\1 Wrote:</p>\\2</div>', $content);

		return $content;
		}

	/* [img] [/img] */
	private static function image ($content)
		{
		$content = preg_replace ('/\[img\](.*)\[\/img\]/Usie', "'<img src=\"\\1\" alt=\"' . basename('\\1') . '\" />'", $content);
		$content = preg_replace ('/\[img=(.*)\](.*)\[\/img\]/Usie', "'<a href=\"\\1\" class=\"photobox\"><img src=\"\\2\" alt=\"' . basename('\\1') . '\"/></a>'", $content);

		return $content;
		}
	
	private function code ($content)
		{
		$content = preg_replace ('/\[code\]\n/Uis', '[code]', $content);
		
		return preg_replace_callback ('/\[code\](.*)\[\/code\]/Usi', 'self::code_callback', $content);
		}
	
	private static function code_callback ($matches)
		{
		$text	= $matches[1];
		$text	= preg_replace ("/^\n|\<br(.\/|)\>/", '', $text);
		$text	= highlight_string ($text, true);
		
		return '<div class="codebox"><p class="bold mb6">Code:</p><p class="pre">' . $text . '</p></div>';
		}
	
	private function email_form ($content)
		{
		$content = preg_replace ('/\[email\-form\]\n/Uis', '[email\-form]', $content);
		
		return preg_replace_callback ('/\[email\-form="(.*)"\](.*)\[\/email\-form\]/Usi', 'self::email_form_callback', $content);
		}
	
	private static function email_form_callback ($matches)
		{
		global $l;
		
		$formid		= rand (1, 50000);
		$text		= $matches[2];
		$subject	= $matches[1];
		$text		= preg_replace ('/^\n|\<br(.\/|)\>/', '', $text);
		
		$output  = '';
		$output .= '<form id="email-form-' . $formid . '" action="' . config('web.root') . 'misc/bbcode_email?post" method="post">';
		$output .= '<input type="hidden" name="body" value="' . base64_encode ($text) . '" />';
		$output .= '<input type="hidden" name="subject" value="' . base64_encode ($subject) . '" />';
		$output .= 'Email Address: <input type="text" name="email" style="width: 150px"/>&nbsp;';
		$output .= '<input type="submit" value="Submit" />';
		$output .= '</form>';
		
		return $output;
		}
	
	private function size($content)
		{
		return preg_replace_callback ('/\[size=(.*)\](.*)\[\/size\]/Usi', 'self::size_callback', $content);
		}
	
	private static function size_callback ($matches)
		{
		$size   = strtolower($matches[1]);
		$text   = $matches[2];
		
		$sizes  = array ('large' => '100', 'x-large' => '200', 'small' => '50');
		
		if (array_key_exists($size, $sizes))
			$class = $size;
		
		elseif (in_array ($size, $sizes))
			$class = array_search ($size, $sizes);
		
		return (empty ($class)) ? '<span style="font-size: ' . $size . '">' . $text . '</span>' : '<span class="' . $class . '">' . $text . '</span>';
		}
	
	private function align ($content)
		{
		return preg_replace_callback ('/\[align=(.*)\](.*)\[\/align\]/Usi', 'self::align_callback', $content);
		}
	
	private static function align_callback ($matches)
		{
		$input		= strtolower ($matches[1]);
		$text		= $matches[2];
		$class 		= '';
		$options	= array ('left' => 'textl', 'right' => 'textr', 'center' => 'textc');
		
		foreach ($options as $key => $class)
			{
			if ($key == $input) break;
			}
		
		return (empty ($class)) ? $text : '<div class="' . $class . '">' . $text . '</div>';
		}
	
	private function url ($content)
		{
		$content = preg_replace_callback ('/\[url\](.*)\[\/url\]/Usi', 'self::url_callback', $content);
		$content = preg_replace_callback ('/\[url=(.*)\](.*)\[\/url\]/Usi', 'self::url_callback', $content);
		//$content = preg_replace_callback ('#(( |^)(((ftp|http|https|)://)|www\.)\S+)#mi', '\limbo\bbcode::url_callback', $content);
		
		return $content;
		}
	
	private static function url_callback ($matches)
	  	{
	  	$url = $text	= trim ($matches[1]);
	  	$matches[2]		= (isset ($matches[2])) ? trim ($matches[2]) : '';
		
		if (isset ($matches[2]) && strlen ($matches[2]) > 0)
			$text = $matches[2];
		elseif (! isset ($matches[2]) && strlen ($url) > 50)
			$text = substr ($url, 0, 45 - 3) . ' &hellip; ' . substr ($url, -5);
		
		$url = substr (strtolower ($url), 0, 3) == 'www' ? 'http://' . $url : $url;
			
		return ' <a href="' . $url . '" class="link">' . $text . '</a> ';
		}
	}
?>

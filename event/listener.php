<?php
/**
*
* @package phpBB Extension - pinterest
* @copyright (c) 2016 tas2580 (https://tas2580.net)
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace tas2580\pinterest\event;

/**
* @ignore
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;

	/** @var string php_ext */
	protected $php_ext;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/**
	 * Constructor
	 *
	 * @param \phpbb\controller\helper		$helper
	 * @param \phpbb\user				$user
	 * @param \phpbb\template\template	$template
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\cache\driver\driver_interface $cache, $php_ext, \phpbb\template\template $template, \phpbb\user $user)
	{
		$this->config = $config;
		$this->cache = $cache;
		$this->php_ext = $php_ext;
		$this->template = $template;
		$user->add_lang_ext('tas2580/pinterest', 'common');
		$this->board_url = generate_board_url();
	}

	/**
	* Assign functions defined in this class to event listeners in the core
	*
	* @return array
	* @static
	* @access public
	*/
	static public function getSubscribedEvents()
	{
		return array(
			'core.viewtopic_modify_post_row'	=> 'viewtopic_modify_post_row',
			'core.viewtopic_modify_post_data'		=> 'viewtopic_modify_post_data',
		);
	}

	/**
	 * Changes the regex replacement for second pass
	 *
	 * Based on phpBB.de - External Image as Link from Christian Schnegelberger<blackhawk87@phpbb.de> and Oliver Schramm <elsensee@phpbb.de>
	 *
	 * @param object $event
	 * @return null
	 * @access public
	 */
	public function viewtopic_modify_post_row($event)
	{
		$post_row = $event['post_row'];
		preg_match_all ('#src\=\"http(.*)\"#U', $post_row['MESSAGE'] ,$matches);

		foreach ($matches[1] as $img)
		{
			$this->template->assign_block_vars('pin_images', array(
				'IMG_URL'		=> 'http' . $img,
				'IMG'			=> urlencode('http' . $img),
				'COMMENT'		=> '',
				'URL'			=> urlencode($this->board_url . '/viewtopic.' . $this->php_ext .'?f=' . $event['forum_id'] . '&t=' . $event['topic_id'] . ($event['start'] <> 0 ? '&start=' . $event['start'] : '')),
			));
		}
	}

	public function viewtopic_modify_post_data($event)
	{
		$mime_types = array('image/png', 'image/jpeg');
		$enabled_forums = isset($this->config['socialbuttons_enable_forums']) ? explode(',', $this->config['socialbuttons_enable_forums']) : array();
		$enable_buttons = ((isset($this->config['socialbuttons_enable']) && $this->config['socialbuttons_enable']) || in_array($event['forum_id'], $enabled_forums));
		// Display the shares count
		if ($enable_buttons && isset($this->config['socialbuttons_showshares']) && $this->config['socialbuttons_showshares'])
		{

			$url = $this->board_url . '/viewtopic.' . $this->php_ext .'?f=' . $event['forum_id'] . '&t=' . $event['topic_id'] . ($event['start'] <> 0 ? '&start=' . $event['start'] : '');

			$shares = $this->get_share_count($url);
			$this->template->assign_vars(array(
				'SHARES_PINTEREST'		=> isset($shares['pinterest']) ? (int) $shares['pinterest'] : 0,
			));
		}
		foreach ($event['attachments'] as $attachments)
		{
			foreach ($attachments as $attachment)
			{
				if (in_array($attachment['mimetype'], $mime_types))
				{
					$this->template->assign_block_vars('pin_images', array(
						'IMG_URL'		=> $this->board_url .'/download/file.' . $this->php_ext . '?id=' . $attachment['attach_id'],
						'IMG'			=> urlencode($this->board_url .'/download/file.' . $this->php_ext . '?id=' . $attachment['attach_id']),
						'COMMENT'		=> $attachment['attach_comment'],
						'URL'			=> urlencode($this->board_url . '/viewtopic.' . $this->php_ext .'?f=' . $event['forum_id'] . '&t=' . $event['topic_id'] . ($event['start'] <> 0 ? '&start=' . $event['start'] : '')),
					));
				}
			}
		}
	}

	/**
	* Get the number of shares
	*
	* @param	string	$url		The URL to get the shares for
	* @return	array
	* @access private
	*/
	private function get_share_count($url)
	{
		$cache_time = isset($this->config['socialbuttons_cachetime']) ? $this->config['socialbuttons_cachetime'] : 0;
		$multiplicator = isset($this->config['socialbuttons_multiplicator']) ? $this->config['socialbuttons_multiplicator'] : 1;

		$cachetime = ((int) $cache_time * (int) $multiplicator);
		$cache_file = '_pinterest_' . $url;
		$shares = $this->cache->get($cache_file);

		// If cache is too old or we have no cache, query the platforms
		if ($shares === false)
		{
			$content = $querys = array();
			// Collect the querys
			$querys['pinterest'] = 'https://widgets.pinterest.com/v1/urls/count.json?url=' . $url  . '&ref=' . $url  . '&source=6&callback=PIN_1445603215817.f.callback[0]';

			// Do we have curl? We can query all platforms paralel what is mutch faster
			if (function_exists('curl_multi_init') && function_exists('curl_multi_exec'))
			{
				// Set curl options for each URL
				$mh = curl_multi_init();
				$handle = array();
				foreach ($querys as $platform => $query_url)
				{
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $query_url);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
					curl_setopt($ch, CURLOPT_NOBODY, false);
					curl_setopt($ch, CURLOPT_HEADER, false);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; rv:31.0) Gecko/20100101 Firefox/31.0');
					curl_multi_add_handle($mh, $ch);
					$handle[$platform] = $ch;
				}

				// Exec the query
				$running = 0;
				do
				{
					curl_multi_exec($mh, $running);
				}
				while ($running > 0);

				// Get the resonse
				foreach ($handle as $platform => $ch)
				{
					$handle = curl_multi_info_read($mh);
					$content[$platform] = curl_multi_getcontent($ch);
					curl_multi_remove_handle($mh, $handle['handle'] );
				}
				curl_multi_close($mh);
			}
			// No curl we have to do it the old way
			else
			{
				//Set the useragent
				$options  = array('http' => array('user_agent' => 'Mozilla/5.0 (Windows NT 6.1; rv:31.0) Gecko/20100101 Firefox/31.0'));
				$context  = stream_context_create($options);
				foreach ($querys as $platform => $query_url)
				{
					$content[$platform] = file_get_contents($query_url, false, $context);
				}
			}

			// Get the number of shares from response
			$matches = array();
			preg_match('#"count":([0-9]+)}#s', $content['pinterest'], $matches);
			$shares['pinterest'] =  isset($matches[1]) ? $matches[1] : 0;

			// Write data to cache
			$this->cache->put($cache_file, $shares, $cachetime);
			return $shares;
		}
		else
		{
			// return data from cache
			return $shares;
		}
	}
}

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
	public function __construct($php_ext, \phpbb\template\template $template, \phpbb\user $user)
	{
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
}

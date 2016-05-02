<?php

/**
 *
 * @package phpBB Extension - tas2580 lastpostlink
 * @copyright (c) 2016 tas2580 (https://tas2580.net)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace tas2580\lastpostlink\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event listener
 */
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var string phpbb_root_path */
	protected $phpbb_root_path;

	/** @var string php_ext */
	protected $php_ext;

	/**
	 * Constructor
	 *
	 * @param \phpbb\auth\auth				auth				Authentication object
	 * @param \phpbb\config\config			$config				Config Object
	 * @param \phpbb\template\template		$template			Template object
	 * @param \phpbb\request\request			$request			Request object
	 * @param \phpbb\user					$user				User Object
	 * @param \phpbb\path_helper				$path_helper		Controller helper object
	 * @param string                         $phpbb_root_path	phpbb_root_path
	 * @access public
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\request\request $request, $phpbb_root_path, $php_ext)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->request = $request;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext =$php_ext;
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
			'core.display_forums_modify_sql'			=> 'display_forums_modify_sql',
			'core.display_forums_modify_template_vars'	=> 'display_forums_modify_template_vars',
			'core.display_forums_modify_forum_rows'		=> 'display_forums_modify_forum_rows',
			'core.display_forums_modify_sql'			=> 'display_forums_modify_sql',
			'core.viewforum_modify_topicrow'			=> 'viewforum_modify_topicrow',
			'core.search_modify_tpl_ary'				=> 'search_modify_tpl_ary',
			'core.viewtopic_modify_post_row'			=> 'viewtopic_modify_post_row',
		);
	}

	/**
	 * Get informations for the last post from database
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function display_forums_modify_sql($event)
	{
		$sql_array = $event['sql_ary'];
		$sql_array['LEFT_JOIN'][] = array(
			'FROM' => array(TOPICS_TABLE => 't'),
			'ON' => "f.forum_last_post_id = t.topic_last_post_id"
		);
		$sql_array['SELECT'] .= ', t.topic_title, t.topic_id, t.topic_posts_approved, t.topic_posts_unapproved, t.topic_posts_softdeleted';
		$event['sql_ary'] = $sql_array;
	}

	/**
	 * Store informations for the last post in forum_rows array
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function display_forums_modify_forum_rows($event)
	{
		$forum_rows = $event['forum_rows'];
		if ($event['row']['forum_last_post_time'] == $forum_rows[$event['parent_id']]['forum_last_post_time'])
		{
			$forum_rows[$event['parent_id']]['topic_id_last_post'] =$event['row']['topic_id'];
			$event['forum_rows'] = $forum_rows;
		}
	}

	/**
	 * Rewrite links to last post in forum index
	 * also correct the path of the forum images if we are in a forum
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function display_forums_modify_template_vars($event)
	{
		$replies = $this->get_count('topic_posts', $event['row'], $event['row']['forum_id']) - 1;
		$url = $this->generate_topic_link($event['row']['forum_id_last_post'], $event['row']['topic_id_last_post']);

		$forum_row = $event['forum_row'];
		$forum_row['U_LAST_POST'] = $this->generate_lastpost_link($replies, $url) . '#p' . $event['row']['forum_last_post_id'];
		$event['forum_row'] = $forum_row;
	}

	/**
	 * Rewrite links in the search result
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function search_modify_tpl_ary($event)
	{
		$replies = $this->get_count('topic_posts', $event['row'], $event['row']['forum_id']) - 1;
		$url = $this->generate_topic_link($event['row']['forum_id'], $event['row']['topic_id']);

		$tpl_ary = $event['tpl_ary'];
		$tpl_ary['U_LAST_POST'] = $this->generate_lastpost_link($replies, $url) . '#p' . $event['row']['topic_last_post_id'];
		$event['tpl_ary'] = $tpl_ary;
	}

	/**
	 * Rewrite links to last post in forum view
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function viewforum_modify_topicrow($event)
	{
		$topic_row = $event['topic_row'];
		$topic_row['U_LAST_POST'] = $this->generate_lastpost_link($event['topic_row']['REPLIES'], $topic_row['U_VIEW_TOPIC']) . '#p' . $event['row']['topic_last_post_id'];
		$event['topic_row'] = $topic_row;
	}

	/**
	 * Rewrite mini post img link
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function viewtopic_modify_post_row($event)
	{
		$row = $event['post_row'];
		$row['U_MINI_POST'] = $this->generate_topic_link($event['topic_data']['forum_id'], $event['topic_data']['topic_id']) . '#p' . $event['row']['post_id'];
		$event['post_row'] = $row;
	}

	/**
	 * Generate link to topic
	 *
	 * @param int $forum_id
	 * @param int $topic_id
	 * @return string
	 */
	private function generate_topic_link($forum_id, $topic_id)
	{
		$start = $this->request->variable('start', 0);
		return append_sid($this->phpbb_root_path . 'viewtopic.' . $this->php_ext . '?f=' . $forum_id . '&t=' . $topic_id . (($start > 0) ? '&start=' . $start : ''));
	}

	/**
	 * Generate link to last post
	 *
	 * @global $_SID		string
	 * @param $replies		int			Replays in the topic
	 * @param $url		string		URL oft the topic
	 * @return			string		The URL with start included
	 */
	private function generate_lastpost_link($replies, $url)
	{
		$per_page = ($this->config['posts_per_page'] <= 0) ? 1 : $this->config['posts_per_page'];
		if (($replies + 1) > $per_page)
		{
			$times = 1;
			for ($j = 0; $j < $replies + 1; $j += $per_page)
			{
				$last_post_link = $url .  '&start=' . $j;
				$times++;
			}
		}
		else
		{
			$last_post_link = $url;
		}
		return append_sid($last_post_link);
	}

	/**
	 * Get the topics post count or the forums post/topic count based on permissions
	 *
	 * @param $mode		string	One of topic_posts, forum_posts or forum_topics
	 * @param $data		array		Array with the topic/forum data to calculate from
	 * @param $forum_id	int		The forum id is used for permission checks
	 * @return			int		Number of posts/topics the user can see in the topic/forum
	 */
	private function get_count($mode, $data, $forum_id)
	{
		if (!$this->auth->acl_get('m_approve', $forum_id))
		{
			return (int) $data[$mode . '_approved'];
		}

		return (int) $data[$mode . '_approved'] + (int) $data[$mode . '_unapproved'] + (int) $data[$mode . '_softdeleted'];
	}
}

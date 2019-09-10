<?php
/**
*
* @package Support Toolkit - No User Posts
* @version $Id$
* @copyright (c) 2009 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
 * @ignore
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

class no_user_posts
{
	/**
	* Display Options
	*
	* Output the options available
	*/
	function display_options()
	{
		global $db, $template;

		$sql = 'SELECT poster_id, post_id, post_time, post_username, post_subject, post_text, bbcode_uid, bbcode_bitfield, p.forum_id, f.forum_name, t.topic_id
			FROM ' . POSTS_TABLE . ' p
			JOIN ' . FORUMS_TABLE . ' f ON (f.forum_id = p.forum_id)
			JOIN ' . TOPICS_TABLE . ' t ON (t.topic_id = p.topic_id)
			ORDER BY post_id';
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$sql = 'SELECT user_id
				FROM ' . USERS_TABLE . '
				WHERE user_id = ' . $row['poster_id'];
			$res = $db->sql_query($sql);
			$user_id = $db->sql_fetchfield('user_id');
			if (empty($user_id))
			{
				$post_id = $row['post_id'];
				$message = generate_text_for_display($row['post_text'], $row['bbcode_uid'], $row['bbcode_bitfield'], OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES);

				$template->assign_block_vars('posts', array(
					'POST_ID'		=> $row['post_id'],
					'TOPIC_ID'		=> $row['topic_id'],
					'POST_SUBJECT'	=> $row['post_subject'],
					'POST_TEXT'		=> $message,
					'FORUM_NAME'	=> $row['forum_name'],
					'U_FORUM'		=> append_sid(PHPBB_ROOT_PATH . 'viewforum.' . PHP_EXT, array('f' => '' .$row['forum_id']. '')),
					'U_FIND_USER'	=> append_sid(PHPBB_ROOT_PATH . 'memberlist.' . PHP_EXT, array('mode' => 'searchuser', 'form' => 'select_user', 'field' => 'username_' .$row['post_id'] . '', 'select_single' => 'true', 'form' => 'stk_no_user_posts')),
				));
			}
		}
		$db->sql_freeresult($result);

		$template->assign_vars(array(
			'U_NO_USER_POSTS'			=> append_sid(STK_INDEX, array('c' => 'support', 't' => 'no_user_posts', 'submit' => 1)),
		));

		$template->set_filenames(array(
			'body' => 'tools/no_user_posts.html',
		));

		page_header(user_lang('NO_USER_POSTS'), false);
		page_footer();
	}

	/**
	* Run Tool
	*
	* Does the actual stuff we want the tool to do after submission
	*/
	function run_tool(&$error)
	{
		global $db, $lang, $request;

		if (isset($_POST['reassign']))
		{			$post_map = $request->variable('posts', array(0 => ''));
			foreach ($post_map as $post_id => $user_id)
			{
				if ($user_id == '')
				{
					unset($post_map[$post_id]);
				}
			}

			if (!sizeof($post_map))
			{
				trigger_error($lang['NO_AUTHOR_SELECTED'], E_USER_WARNING);
			}
			foreach ($post_map as $post_id => $author)
			{				$sql = 'SELECT user_id
					FROM ' . USERS_TABLE . '
						WHERE username_clean = \'' . $db->sql_escape(utf8_clean_string($author)) . '\'';
				$result = $db->sql_query($sql);
				$user_id = $db->sql_fetchfield('user_id');
				$db->sql_freeresult($result);
				if ($user_id)
				{					$post_username = ($user_id == ANONYMOUS) ? $user->lang['GUEST'] : '';					$sql = 'UPDATE ' . POSTS_TABLE . '
						SET poster_id = ' . $user_id . ', post_username = \'' . $post_username . '\'
						WHERE post_id = ' . $post_id;
						$db->sql_query($sql);
				}
			}
			sinc_stats();
			trigger_error(sprintf($lang['AUTHOR_POSTS_REASSIGNED'], sizeof($post_map)));
		}

		if (isset($_POST['delete']))
		{
			$post_ids = $request->variable('posts_del', array(0 => 0));

			if (!sizeof($post_ids))
			{
				trigger_error($lang['NO_POSTS_SELECTED'], E_USER_WARNING);
			}

			if (!function_exists('delete_posts'))
			{
				include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
			}

			$return = delete_posts('post_id', $post_ids);
			sinc_stats();
			trigger_error(sprintf($lang['POSTS_DELETED'], $return));
		}
	}
}

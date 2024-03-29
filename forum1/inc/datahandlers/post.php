<?php
/**
 * MyBB 1.4
 * Copyright � 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: post.php 4867 2010-04-11 03:37:10Z RyanGordon $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/*
EXAMPLE USE:

$post = get from POST data
$thread = get from DB using POST data id

$postHandler = new postDataHandler();
if($postHandler->validate_post($post))
{
	$postHandler->insert_post($post);
}

*/

/**
 * Post handling class, provides common structure to handle post data.
 *
 */
class PostDataHandler extends DataHandler
{
	/**
	* The language file used in the data handler.
	*
	* @var string
	*/
	var $language_file = 'datahandler_post';

	/**
	* The prefix for the language variables used in the data handler.
	*
	* @var string
	*/
	var $language_prefix = 'postdata';

	/**
	 * What are we performing?
	 * post = New post
	 * thread = New thread
	 * edit = Editing a thread or post
	 */
	var $action;

	/**
	 * Array of data inserted in to a post.
	 *
	 * @var array
	 */
	var $post_insert_data = array();

	/**
	 * Array of data used to update a post.
	 *
	 * @var array
	 */
	var $post_update_data = array();

	/**
	 * Post ID currently being manipulated by the datahandlers.
	 *
	 * @var int
	 */
	var $pid = 0;

	/**
	 * Array of data inserted in to a thread.
	 *
	 * @var array
	 */
	var $thread_insert_data = array();

	/**
	 * Array of data used to update a thread.
	 *
	 * @var array
	 */
	var $thread_update_data = array();

	/**
	 * Thread ID currently being manipulated by the datahandlers.
	 *
	 * @var int
	 */
	var $tid = 0;

	/**
	 * Verifies the author of a post and fetches the username if necessary.
	 *
	 * @return boolean True if the author information is valid, false if invalid.
	 */
	function verify_author()
	{
		global $mybb;

		$post = &$this->data;

		// Don't have a user ID at all - not good (note, a user id of 0 will still work).
		if(!isset($post['uid']))
		{
			$this->set_error("invalid_user_id");
			return false;
		}
		// If we have a user id but no username then fetch the username.
		else if($post['uid'] > 0 && $post['username'] == '')
		{
			$user = get_user($post['uid']);
			$post['username'] = $user['username'];
		}

		// After all of this, if we still don't have a username, force the username as "Guest" (Note, this is not translatable as it is always a fallback)
		if(!$post['username'])
		{
			$post['username'] = "Guest";
		}

		// Sanitize the username
		$post['username'] = htmlspecialchars_uni($post['username']);
		return true;
	}

	/**
	 * Verifies a post subject.
	 *
	 * @param string True if the subject is valid, false if invalid.
	 * @return boolean True when valid, false when not valid.
	 */
	function verify_subject()
	{
		global $db;
		$post = &$this->data;
		$subject = &$post['subject'];

		$subject = trim($subject);

		// Are we editing an existing thread or post?
		if($this->method == "update" && $post['pid'])
		{
			if(!$post['tid'])
			{
				$query = $db->simple_select("posts", "tid", "pid='".intval($post['pid'])."'");
				$post['tid'] = $db->fetch_field($query, "tid");
			}
			// Here we determine if we're editing the first post of a thread or not.
			$options = array(
				"limit" => 1,
				"limit_start" => 0,
				"order_by" => "dateline",
				"order_dir" => "asc"
			);
			$query = $db->simple_select("posts", "pid", "tid='".$post['tid']."'", $options);
			$first_check = $db->fetch_array($query);
			if($first_check['pid'] == $post['pid'])
			{
				$first_post = true;
			}
			else
			{
				$first_post = false;
			}

			// If this is the first post there needs to be a subject, else make it the default one.
			if(my_strlen($subject) == 0 && $first_post)
			{
				$this->set_error("firstpost_no_subject");
				return false;
			}
			elseif(my_strlen($subject) == 0)
			{
				$thread = get_thread($post['tid']);
				$subject = "RE: ".$thread['subject'];
			}
		}

		// This is a new post
		else if($this->action == "post")
		{
			if(my_strlen($subject) == 0)
			{
				$thread = get_thread($post['tid']);
				$subject = "RE: ".$thread['subject'];
			}
		}

		// This is a new thread and we require that a subject is present.
		else
		{
			if(my_strlen($subject) == 0)
			{
				$this->set_error("missing_subject");
				return false;
			}
		}

		// Subject is valid - return true.
		return true;
	}

	/**
	 * Verifies a post message.
	 *
	 * @param string The message content.
	 */
	function verify_message()
	{
		global $mybb;

		$post = &$this->data;
		$post['message'] = trim($post['message']);
		
		// Do we even have a message at all?
		if(my_strlen($post['message']) == 0)
		{
			$this->set_error("missing_message");
			return false;
		}

		// If this board has a maximum message length check if we're over it.
		else if(my_strlen($post['message']) > $mybb->settings['maxmessagelength'] && $mybb->settings['maxmessagelength'] > 0 && !is_moderator($post['fid'], "", $post['uid']))
		{
			$this->set_error("message_too_long", array($mybb->settings['maxmessagelength']));
			return false;
		}

		// And if we've got a minimum message length do we meet that requirement too?
		else if(my_strlen($post['message']) < $mybb->settings['minmessagelength'] && $mybb->settings['minmessagelength'] > 0 && !is_moderator($post['fid'], "", $post['uid']))
		{
			$this->set_error("message_too_short", array($mybb->settings['minmessagelength']));
			return false;
		}
		return true;
	}

	/**
	 * Verifies the specified post options are correct.
	 *
	 * @return boolean True
	 */
	function verify_options()
	{
		$options = &$this->data['options'];

		// Verify yes/no options.
		$this->verify_yesno_option($options, 'signature', 0);
		$this->verify_yesno_option($options, 'disablesmilies', 0);

		return true;
	}

	/**
	* Verify that the user is not flooding the system.
	*
	* @return boolean True
	*/
	function verify_post_flooding()
	{
		global $mybb;

		$post = &$this->data;

		// Check if post flooding is enabled within MyBB or if the admin override option is specified.
		if($mybb->settings['postfloodcheck'] == 1 && $post['uid'] != 0 && $this->admin_override == false)
		{
			if($this->verify_post_merge(true) !== true)
			{
				return true;
			}
			
			// Fetch the user information for this post - used to check their last post date.
			$user = get_user($post['uid']);

			// A little bit of calculation magic and moderator status checking.
			if(TIME_NOW-$user['lastpost'] <= $mybb->settings['postfloodsecs'] && !is_moderator($post['fid'], "", $user['uid']))
			{
				// Oops, user has been flooding - throw back error message.
				$time_to_wait = ($mybb->settings['postfloodsecs'] - (TIME_NOW-$user['lastpost'])) + 1;
				if($time_to_wait == 1)
				{
					$this->set_error("post_flooding_one_second");
				}
				else
				{
					$this->set_error("post_flooding", array($time_to_wait));
				}
				return false;
			}
		}
		// All is well that ends well - return true.
		return true;
	}
	
	function verify_post_merge($simple_mode=false)
	{
		global $mybb, $db, $session;
		
		$post = &$this->data;
		
		// Are we starting a new thread?
		if(!$post['tid'])
		{
			return true;
		}
		
		// Are we even turned on?
		if(empty($mybb->settings['postmergemins']))
		{
			return true;
		}
		
		// Assign a default separator if none is specified
		if(trim($mybb->settings['postmergesep']) == "")
		{
			$mybb->settings['postmergesep'] = "[hr]";
		}
		
		// Check to see if this person is in a usergroup that is excluded
		if(trim($mybb->settings['postmergeuignore']) != "")
		{
			$gids = explode(',', $mybb->settings['postmergeuignore']);
			$gids = array_map('intval', $gids);
			
			
			$user_usergroups = explode(',', $mybb->user['usergroup'].",".$mybb->user['additionalgroups']);
			if(count(array_intersect($user_usergroups, $gids)) > 0)
			{
				return true;
			}			
		}
		
		// Select the lastpost and fid information for this thread
		$query = $db->simple_select("threads", "lastpost,fid", "lastposteruid='".$post['uid']."' AND tid='".$post['tid']."'", array('limit' => '1'));
		$thread = $db->fetch_array($query);
		
		// Check to see if the same author has posted within the merge post time limit
		if((intval($mybb->settings['postmergemins']) != 0 && trim($mybb->settings['postmergemins']) != "") && (TIME_NOW-$thread['lastpost']) > (intval($mybb->settings['postmergemins'])*60))
		{
			return true;
		}
		
		if(strstr($mybb->settings['postmergefignore'], ','))
		{
			$fids = explode(',', $mybb->settings['postmergefignore']);
			foreach($fids as $key => $forumid)
			{
				$fid[] = intval($forumid);
			}
			
			if(in_array($thread['fid'], $fid))
			{
				return true;
			}
			
		}
		else if(trim($mybb->settings['postmergefignore']) != "" && $thread['fid'] == intval($mybb->settings['postmergefignore']))
		{
			return true;
		}
		
		if($simple_mode == true)
		{
			return false;
		}
		
		if($post['uid'])
		{
			$user_check = "uid='".$post['uid']."'";
		}
		else
		{
			$user_check = "ipaddress='".$db->escape_string($session->ipaddress)."'";
		}
		
		$query = $db->simple_select("posts", "pid,message,visible,posthash", "{$user_check} AND tid='".$post['tid']."' AND dateline='".$thread['lastpost']."'", array('order_by' => 'pid', 'order_dir' => 'DESC', 'limit' => 1));
		return $db->fetch_array($query);
	}

	/**
	* Verifies the image count.
	*
	* @return boolean True when valid, false when not valid.
	*/
	function verify_image_count()
	{
		global $mybb, $db;

		$post = &$this->data;

		// Get the permissions of the user who is making this post or thread
		$permissions = user_permissions($post['uid']);

		// Fetch the forum this post is being made in
		if(!$post['fid'])
		{
			$query = $db->simple_select('posts', 'fid', "pid = '{$post['pid']}'");
			$post['fid'] = $db->fetch_field($query, 'fid');
		}
		$forum = get_forum($post['fid']);

		// Check if this post contains more images than the forum allows
		if($post['savedraft'] != 1 && $mybb->settings['maxpostimages'] != 0 && $permissions['cancp'] != 1)
		{
			require_once MYBB_ROOT."inc/class_parser.php";
			$parser = new postParser;

			// Parse the message.
			$parser_options = array(
				"allow_html" => $forum['allowhtml'],
				"allow_mycode" => $forum['allowmycode'],
				"allow_imgcode" => $forum['allowimgcode'],
				"filter_badwords" => 1
			);

			if($post['options']['disablesmilies'] != 1)
			{
				$parser_options['allow_smilies'] = $forum['allowsmilies'];
			}
			else
			{
				$parser_options['allow_smilies'] = 0;
			}

			$image_check = $parser->parse_message($post['message'], $parser_options);

			// And count the number of image tags in the message.
			$image_count = substr_count($image_check, "<img");
			if($image_count > $mybb->settings['maxpostimages'])
			{
				// Throw back a message if over the count with the number of images as well as the maximum number of images per post.
				$this->set_error("too_many_images", array(1 => $image_count, 2 => $mybb->settings['maxpostimages']));
				return false;
			}
		}
	}

	/**
	* Verify the reply-to post.
	*
	* @return boolean True when valid, false when not valid.
	*/
	function verify_reply_to()
	{
		global $db;
		$post = &$this->data;

		// Check if the post being replied to actually exists in this thread.
		if($post['replyto'])
		{
			$query = $db->simple_select("posts", "pid", "pid='".intval($post['replyto'])."'");
			$valid_post = $db->fetch_array($query);
			if(!$valid_post['pid'])
			{
				$post['replyto'] = 0;
			}
			else
			{
				return true;
			}
		}

		// If this post isn't a reply to a specific post, attach it to the first post.
		if(!$post['replyto'])
		{
			$options = array(
				"limit_start" => 0,
				"limit" => 1,
				"order_by" => "dateline",
				"order_dir" => "asc"
			);
			$query = $db->simple_select("posts", "pid", "tid='{$post['tid']}'", $options);
			$reply_to = $db->fetch_array($query);
			$post['replyto'] = $reply_to['pid'];
		}

		return true;
	}

	/**
	* Verify the post icon.
	*
	* @return boolean True when valid, false when not valid.
	*/
	function verify_post_icon()
	{
		global $cache;

		$post = &$this->data;

		// If we don't assign it as 0.
		if(!$post['icon'] || $post['icon'] < 0)
		{
			$post['icon'] = 0;
		}
		return true;
	}

	/**
	* Verify the dateline.
	*
	* @return boolean True when valid, false when not valid.
	*/
	function verify_dateline()
	{
		$dateline = &$this->data['dateline'];

		// The date has to be numeric and > 0.
		if($dateline < 0 || is_numeric($dateline) == false)
		{
			$dateline = TIME_NOW;
		}
	}

	/**
	 * Validate a post.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_post()
	{
		global $mybb, $db, $plugins;

		$post = &$this->data;
		$time = TIME_NOW;
		
		$this->action = "post";
		
		if($this->method != "update" && !$post['savedraft'])
		{
			$this->verify_post_flooding();
		}

		// Verify all post assets.

		if($this->method == "insert" || array_key_exists('uid', $post))
		{
			$this->verify_author();
		}

		if($this->method == "insert" || array_key_exists('subject', $post))
		{
			$this->verify_subject();
		}

		if($this->method == "insert" || array_key_exists('message', $post))
		{
			$this->verify_message();
			$this->verify_image_count();
		}

		if($this->method == "insert" || array_key_exists('dateline', $post))
		{
			$this->verify_dateline();
		}

		if($this->method == "insert" || array_key_exists('replyto', $post))
		{
			$this->verify_reply_to();
		}

		if($this->method == "insert" || array_key_exists('icon', $post))
		{
			$this->verify_post_icon();
		}

		if($this->method == "insert" || array_key_exists('options', $post))
		{
			$this->verify_options();
		}

		$plugins->run_hooks_by_ref("datahandler_post_validate_post", $this);

		// We are done validating, return.
		$this->set_validated(true);
		if(count($this->get_errors()) > 0)
		{
			return false;
		}
		else
		{
			return true;
		}
	}


	/**
	 * Insert a post into the database.
	 *
	 * @return array Array of new post details, pid and visibility.
	 */
	function insert_post()
	{
		global $db, $mybb, $plugins, $cache, $lang;

		$post = &$this->data;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The post needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The post is not valid.");
		}

		// This post is being saved as a draft.
		if($post['savedraft'])
		{
			$visible = -2;
		}
		
		// Otherwise this post is being made now and we have a bit to do.
		else
		{
			// Automatic subscription to the thread
			if($post['options']['subscriptionmethod'] != "" && $post['uid'] > 0)
			{
				switch($post['options']['subscriptionmethod'])
				{
					case "instant":
						$notification = 1;
						break;
					default:
						$notification = 0;
				}

				require_once MYBB_ROOT."inc/functions_user.php";
				add_subscribed_thread($post['tid'], $notification, $post['uid']);
			}

			// Perform any selected moderation tools.
			if(is_moderator($post['fid'], "", $post['uid']))
			{
				$lang->load($this->language_file, true);
				
				// Fetch the thread
				$thread = get_thread($post['tid']);

				$modoptions = $post['modoptions'];
				$modlogdata['fid'] = $thread['fid'];
				$modlogdata['tid'] = $thread['tid'];

				// Close the thread.
				if($modoptions['closethread'] == 1 && $thread['closed'] != 1)
				{
					$newclosed = "closed=1";
					log_moderator_action($modlogdata, $lang->thread_closed);
				}

				// Open the thread.
				if($modoptions['closethread'] != 1 && $thread['closed'] == 1)
				{
					$newclosed = "closed=0";
					log_moderator_action($modlogdata, $lang->thread_opened);
				}

				// Stick the thread.
				if($modoptions['stickthread'] == 1 && $thread['sticky'] != 1)
				{
					$newstick = "sticky='1'";
					log_moderator_action($modlogdata, $lang->thread_stuck);
				}

				// Unstick the thread.
				if($modoptions['stickthread'] != 1 && $thread['sticky'])
				{
					$newstick = "sticky='0'";					
					log_moderator_action($modlogdata, $lang->thread_unstuck);
				}

				// Execute moderation options.
				if($newstick && $newclosed)
				{
					$sep = ",";
				}
				if($newstick || $newclosed)
				{
					$db->write_query("
						UPDATE ".TABLE_PREFIX."threads
						SET {$newclosed}{$sep}{$newstick}
						WHERE tid='{$thread['tid']}'
					");
				}
			}

			// Fetch the forum this post is being made in
			$forum = get_forum($post['fid']);

			// Decide on the visibility of this post.
			if($forum['modposts'] == 1 && !is_moderator($thread['fid'], "", $post['uid']))
			{
				$visible = 0;
			}
			else
			{
				$visible = 1;
			}

			// Are posts from this user being moderated? Change visibility
			if($mybb->user['uid'] == $post['uid'] && $mybb->user['moderateposts'] == 1)
			{
				$visible = 0;
			}
		}
		
		if($this->method != "update" && $visible == 1)
		{
			$double_post = $this->verify_post_merge();

			// Only combine if they are both invisible (mod queue'd forum) or both visible
			if($double_post !== true && $double_post['visible'] == $visible)
			{
				$this->pid = $double_post['pid'];
				
				$post['message'] = $double_post['message'] .= $mybb->settings['postmergesep']."\n".$post['message'];
				$update_query = array(
					"message" => $db->escape_string($double_post['message'])
				);
				$update_query['edituid'] = intval($post['uid']);
				$update_query['edittime'] = TIME_NOW;
				$query = $db->update_query("posts", $update_query, "pid='".$double_post['pid']."'");
				
				// Assign any uploaded attachments with the specific posthash to the merged post.
				if($double_post['posthash'])
				{
					$post['posthash'] = $db->escape_string($post['posthash']);
					$double_post['posthash'] = $db->escape_string($double_post['posthash']);
					
					$query = $db->simple_select("attachments", "COUNT(aid) AS attachmentcount", "pid='0' AND visible='1' AND posthash='{$post['posthash']}'");
					$attachmentcount = $db->fetch_field($query, "attachmentcount");
				
					if($attachmentcount > 0)
					{
						// Update forum count
						update_thread_counters($post['tid'], array('attachmentcount' => "+{$attachmentcount}"));
					}
					
					$attachmentassign = array(
						"pid" => $double_post['pid'],
						"posthash" => $double_post['posthash'],
					);
					$db->update_query("attachments", $attachmentassign, "posthash='{$post['posthash']}'");
				
					$post['posthash'] = $double_post['posthash'];
				}
			
				// Return the post's pid and whether or not it is visible.
				return array(
					"pid" => $double_post['pid'],
					"visible" => $visible
				);
			}
		}
		
		if($visible == 1)
		{
			$now = TIME_NOW;
			if($forum['usepostcounts'] != 0)
			{
				$queryadd = ",postnum=postnum+1";
			}
			else
			{
				$queryadd = '';
			}
			$db->write_query("UPDATE ".TABLE_PREFIX."users SET lastpost='{$now}' {$queryadd} WHERE uid='{$post['uid']}'");
		}

		$post['pid'] = intval($post['pid']);
		$post['uid'] = intval($post['uid']);

		if($post['pid'] > 0)
		{
			$query = $db->simple_select("posts", "tid", "pid='{$post['pid']}' AND uid='{$post['uid']}' AND visible='-2'");
			$draft_check = $db->fetch_field($query, "tid");
		}
		else
		{
			$draft_check = false;
		}

		// Are we updating a post which is already a draft? Perhaps changing it into a visible post?
		if($draft_check)
		{
			// Update a post that is a draft
			$this->post_update_data = array(
				"subject" => $db->escape_string($post['subject']),
				"icon" => intval($post['icon']),
				"uid" => $post['uid'],
				"username" => $db->escape_string($post['username']),
				"dateline" => intval($post['dateline']),
				"message" => $db->escape_string($post['message']),
				"ipaddress" => $db->escape_string($post['ipaddress']),
				"longipaddress" => intval(ip2long($post['ipaddress'])),
				"includesig" => $post['options']['signature'],
				"smilieoff" => $post['options']['disablesmilies'],
				"visible" => $visible,
				"posthash" => $db->escape_string($post['posthash'])
			);

			$plugins->run_hooks_by_ref("datahandler_post_insert_post", $this);

			$db->update_query("posts", $this->post_update_data, "pid='{$post['pid']}'");
			$this->pid = $post['pid'];
		}
		else
		{
			// Insert the post.
			$this->post_insert_data = array(
				"tid" => intval($post['tid']),
				"replyto" => intval($post['replyto']),
				"fid" => intval($post['fid']),
				"subject" => $db->escape_string($post['subject']),
				"icon" => intval($post['icon']),
				"uid" => $post['uid'],
				"username" => $db->escape_string($post['username']),
				"dateline" => $post['dateline'],
				"message" => $db->escape_string($post['message']),
				"ipaddress" => $db->escape_string($post['ipaddress']),
				"longipaddress" => intval(ip2long($post['ipaddress'])),
				"includesig" => $post['options']['signature'],
				"smilieoff" => $post['options']['disablesmilies'],
				"visible" => $visible,
				"posthash" => $db->escape_string($post['posthash'])
			);

			$plugins->run_hooks_by_ref("datahandler_post_insert_post", $this);

			$this->pid = $db->insert_query("posts", $this->post_insert_data);
		}
		

		// Assign any uploaded attachments with the specific posthash to the newly created post.
		if($post['posthash'])
		{
			$post['posthash'] = $db->escape_string($post['posthash']);
			$attachmentassign = array(
				"pid" => $this->pid
			);
			$db->update_query("attachments", $attachmentassign, "posthash='{$post['posthash']}'");
		}

		if($visible == 1)
		{
			$thread = get_thread($post['tid']);
			require_once MYBB_ROOT.'inc/class_parser.php';
			$parser = new Postparser;
			
			$done_users = array();
			
			$subject = $parser->parse_badwords($thread['subject']);
			$excerpt = $parser->text_parse_message($post['message'], array('me_username' => $post['username'], 'filter_badwords' => 1, 'safe_html' => 1));
			$excerpt = my_substr($excerpt, 0, $mybb->settings['subscribeexcerpt']).$lang->emailbit_viewthread;

			// Fetch any users subscribed to this thread receiving instant notification and queue up their subscription notices
			$query = $db->query("
				SELECT u.username, u.email, u.uid, u.language, u.loginkey, u.salt, u.regdate, s.subscriptionkey
				FROM ".TABLE_PREFIX."threadsubscriptions s
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=s.uid)
				WHERE s.notification='1' AND s.tid='{$post['tid']}'
				AND s.uid != '{$post['uid']}'
				AND u.lastactive>'{$thread['lastpost']}'
			");
			while($subscribedmember = $db->fetch_array($query))
			{
				if($done_users[$subscribedmember['uid']])
				{
					continue;
				}
				$done_users[$subscribedmember['uid']] = 1;
				
				$forumpermissions = forum_permissions($thread['fid'], $subscribedmember['uid']);
				if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
				{
				    continue;
				}
				
				if($subscribedmember['language'] != '' && $lang->language_exists($subscribedmember['language']))
				{
					$uselang = $subscribedmember['language'];
				}
				elseif($mybb->settings['orig_bblanguage'])
				{
					$uselang = $mybb->settings['orig_bblanguage'];
				}
				else
				{
					$uselang = "english";
				}

				if($uselang == $mybb->settings['bblanguage'])
				{
					$emailsubject = $lang->emailsubject_subscription;
					$emailmessage = $lang->email_subscription;
				}
				else
				{
					if(!isset($langcache[$uselang]['emailsubject_subscription']))
					{
						$userlang = new MyLanguage;
						$userlang->set_path(MYBB_ROOT."inc/languages");
						$userlang->set_language($uselang);
						$userlang->load("messages");
						$langcache[$uselang]['emailsubject_subscription'] = $userlang->emailsubject_subscription;
						$langcache[$uselang]['email_subscription'] = $userlang->email_subscription;
						unset($userlang);
					}
					$emailsubject = $langcache[$uselang]['emailsubject_subscription'];
					$emailmessage = $langcache[$uselang]['email_subscription'];
				}
				$emailsubject = $lang->sprintf($emailsubject, $subject);
				
				$post_code = md5($subscribedmember['loginkey'].$subscribedmember['salt'].$subscribedmember['regdate']);				
				$emailmessage = $lang->sprintf($emailmessage, $subscribedmember['username'], $post['username'], $mybb->settings['bbname'], $subject, $excerpt, $mybb->settings['bburl'], str_replace("&amp;", "&", get_thread_link($thread['tid'], 0, "newpost")), $thread['tid'], $subscribedmember['subscriptionkey'], $post_code);
				$new_email = array(
					"mailto" => $db->escape_string($subscribedmember['email']),
					"mailfrom" => '',
					"subject" => $db->escape_string($emailsubject),
					"message" => $db->escape_string($emailmessage),
					"headers" => ''
				);
				$db->insert_query("mailqueue", $new_email);
				unset($userlang);
				$queued_email = 1;
			}
			// Have one or more emails been queued? Update the queue count
			if($queued_email == 1)
			{
				$cache->update_mailqueue();
			}
			$thread_update['replies'] = "+1";

			// Update forum count
			update_thread_counters($post['tid'], $thread_update);
			update_forum_counters($post['fid'], array("posts" => "+1"));
		}
		// Post is stuck in moderation queue
		else if($visible == 0)
		{
			// Update the unapproved posts count for the current thread and current forum
			update_thread_counters($post['tid'], array("unapprovedposts" => "+1"));
			update_forum_counters($post['fid'], array("unapprovedposts" => "+1"));
		}

		// Return the post's pid and whether or not it is visible.
		return array(
			"pid" => $this->pid,
			"visible" => $visible
		);
	}

	/**
	 * Validate a thread.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_thread()
	{
		global $mybb, $db, $plugins;

		$thread = &$this->data;

		// Validate all thread assets.
		
		if(!$thread['savedraft'])
		{
			$this->verify_post_flooding();
		}

		if($this->method == "insert" || array_key_exists('uid', $thread))
		{
			$this->verify_author();
		}

		if($this->method == "insert" || array_key_exists('subject', $thread))
		{
			$this->verify_subject();
		}

		if($this->method == "insert" || array_key_exists('message', $thread))
		{
			$this->verify_message();
			$this->verify_image_count();
		}

		if($this->method == "insert" || array_key_exists('dateline', $thread))
		{
			$this->verify_dateline();
		}

		if($this->method == "insert" || array_key_exists('icon', $thread))
		{
			$this->verify_post_icon();
		}

		if($this->method == "insert" || array_key_exists('options', $thread))
		{
			$this->verify_options();
		}

		$plugins->run_hooks_by_ref("datahandler_post_validate_thread", $this);

		// We are done validating, return.
		$this->set_validated(true);
		if(count($this->get_errors()) > 0)
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	 * Insert a thread into the database.
	 *
	 * @return array Array of new thread details, tid and visibility.
	 */
	function insert_thread()
	{
		global $db, $mybb, $plugins, $cache, $lang;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The thread needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The thread is not valid.");
		}

		$thread = &$this->data;

		// Fetch the forum this thread is being made in
		$forum = get_forum($thread['fid']);

		// This thread is being saved as a draft.
		if($thread['savedraft'])
		{
			$visible = -2;
		}

		// Thread is being made now and we have a bit to do.
		else
		{

			// Decide on the visibility of this post.
			if(($forum['modthreads'] == 1 || $forum['modposts'] == 1) && !is_moderator($thread['fid'], "", $thread['uid']))
			{
				$visible = 0;
			}
			else
			{
				$visible = 1;
			}

			// Are posts from this user being moderated? Change visibility
			if($mybb->user['uid'] == $thread['uid'] && $mybb->user['moderateposts'] == 1)
			{
				$visible = 0;
			}
		}

		// Have a post ID but not a thread ID - fetch thread ID
		if($thread['pid'] && !$thread['tid'])
		{
			$query = $db->simple_select("posts", "tid", "pid='{$thread['pid']}");
			$thread['tid'] = $db->fetch_field($query, "tid");
		}

		if($thread['pid'] > 0)
		{
			$query = $db->simple_select("posts", "pid", "pid='{$thread['pid']}' AND uid='{$thread['uid']}' AND visible='-2'");
			$draft_check = $db->fetch_field($query, "pid");
		}
		else
		{
			$draft_check = false;
		}

		// Are we updating a post which is already a draft? Perhaps changing it into a visible post?
		if($draft_check)
		{
			$this->thread_insert_data = array(
				"subject" => $db->escape_string($thread['subject']),
				"icon" => intval($thread['icon']),
				"username" => $db->escape_string($thread['username']),
				"dateline" => intval($thread['dateline']),
				"lastpost" => intval($thread['dateline']),
				"lastposter" => $db->escape_string($thread['username']),
				"visible" => $visible
			);

			$plugins->run_hooks_by_ref("datahandler_post_insert_thread", $this);

			$db->update_query("threads", $this->thread_insert_data, "tid='{$thread['tid']}'");

			$this->post_insert_data = array(
				"subject" => $db->escape_string($thread['subject']),
				"icon" => intval($thread['icon']),
				"username" => $db->escape_string($thread['username']),
				"dateline" => intval($thread['dateline']),
				"message" => $db->escape_string($thread['message']),
				"ipaddress" => $db->escape_string(get_ip()),
				"includesig" => $thread['options']['signature'],
				"smilieoff" => $thread['options']['disablesmilies'],
				"visible" => $visible,
				"posthash" => $db->escape_string($thread['posthash'])
			);
			$plugins->run_hooks_by_ref("datahandler_post_insert_thread_post", $this);

			$db->update_query("posts", $this->post_insert_data, "pid='{$thread['pid']}'");
			$this->tid = $thread['tid'];
			$this->pid = $thread['pid'];
		}

		// Inserting a new thread into the database.
		else
		{
			$this->thread_insert_data = array(
				"fid" => $thread['fid'],
				"subject" => $db->escape_string($thread['subject']),
				"icon" => intval($thread['icon']),
				"uid" => $thread['uid'],
				"username" => $db->escape_string($thread['username']),
				"dateline" => intval($thread['dateline']),
				"lastpost" => intval($thread['dateline']),
				"lastposter" => $db->escape_string($thread['username']),
				"views" => 0,
				"replies" => 0,
				"visible" => $visible,
				"notes" => ''
			);

			$plugins->run_hooks_by_ref("datahandler_post_insert_thread", $this);

			$this->tid = $db->insert_query("threads", $this->thread_insert_data);

			$this->post_insert_data = array(
				"tid" => $this->tid,
				"fid" => $thread['fid'],
				"subject" => $db->escape_string($thread['subject']),
				"icon" => intval($thread['icon']),
				"uid" => $thread['uid'],
				"username" => $db->escape_string($thread['username']),
				"dateline" => intval($thread['dateline']),
				"message" => $db->escape_string($thread['message']),
				"ipaddress" => $db->escape_string(get_ip()),
				"longipaddress" => intval(ip2long(get_ip())),
				"includesig" => $thread['options']['signature'],
				"smilieoff" => $thread['options']['disablesmilies'],
				"visible" => $visible,
				"posthash" => $db->escape_string($thread['posthash'])
			);
			$plugins->run_hooks_by_ref("datahandler_post_insert_thread_post", $this);

			$this->pid = $db->insert_query("posts", $this->post_insert_data);

			// Now that we have the post id for this first post, update the threads table.
			$firstpostup = array("firstpost" => $this->pid);
			$db->update_query("threads", $firstpostup, "tid='{$this->tid}'");
		}

		// If we're not saving a draft there are some things we need to check now
		if(!$thread['savedraft'])
		{
			if($thread['options']['subscriptionmethod'] != "" && $thread['uid'] > 0)
			{
				switch($thread['options']['subscriptionmethod'])
				{
					case "instant":
						$notification = 1;
						break;
					default:
						$notification = 0;
				}

				require_once MYBB_ROOT."inc/functions_user.php";
				add_subscribed_thread($this->tid, $notification, $thread['uid']);
			}

			// Perform any selected moderation tools.
			if(is_moderator($thread['fid'], "", $thread['uid']) && is_array($thread['modoptions']))
			{
				$lang->load($this->language_file, true);
				
				$modoptions = $thread['modoptions'];
				$modlogdata['fid'] = $this->tid;
				$modlogdata['tid'] = $thread['tid'];

				// Close the thread.
				if($modoptions['closethread'] == 1)
				{
					$newclosed = "closed=1";
					log_moderator_action($modlogdata, $lang->thread_closed);
				}

				// Stick the thread.
				if($modoptions['stickthread'] == 1)
				{
					$newstick = "sticky='1'";
					log_moderator_action($modlogdata, $lang->thread_stuck);
				}

				// Execute moderation options.
				if($newstick && $newclosed)
				{
					$sep = ",";
				}
				if($newstick || $newclosed)
				{
					$db->write_query("
						UPDATE ".TABLE_PREFIX."threads
						SET $newclosed$sep$newstick
						WHERE tid='{$this->tid}'
					");
				}
			}
			if($visible == 1)
			{
				// If we have a registered user then update their post count and last post times.
				if($thread['uid'] > 0)
				{
					$user = get_user($thread['uid']);
					$update_query = array();
					// Only update the lastpost column of the user if the date of the thread is newer than their last post.
					if($thread['dateline'] > $user['lastpost'])
					{
						$update_query[] = "lastpost='".$thread['dateline']."'";
					}
					// Update the post count if this forum allows post counts to be tracked
					if($forum['usepostcounts'] != 0)
					{
						$update_query[] = "postnum=postnum+1";
					}

					// Only update the table if we need to.
					if(!empty($update_query))
					{
						$update_query = implode(", ", $update_query);
						$db->write_query("UPDATE ".TABLE_PREFIX."users SET $update_query WHERE uid='".$thread['uid']."'");
					}
				}
				
				if(!$forum['lastpost'])
				{
					$forum['lastpost'] = 0;
				}
				
				$done_users = array();
				
				// Queue up any forum subscription notices to users who are subscribed to this forum.
				$excerpt = my_substr($thread['message'], 0, $mybb->settings['subscribeexcerpt']).$lang->emailbit_viewthread;
				
				// Parse badwords
				require_once MYBB_ROOT."inc/class_parser.php";
				$parser = new postParser;
				$excerpt = $parser->parse_badwords($excerpt);

				$query = $db->query("
					SELECT u.username, u.email, u.uid, u.language, u.loginkey, u.salt, u.regdate
					FROM ".TABLE_PREFIX."forumsubscriptions fs
					LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=fs.uid)
					LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup)
					WHERE fs.fid='".intval($thread['fid'])."'
					AND fs.uid != '".intval($thread['uid'])."'
					AND u.lastactive > '{$forum['lastpost']}'
					AND g.isbannedgroup != 1
				");
				while($subscribedmember = $db->fetch_array($query))
				{
					if($done_users[$subscribedmember['uid']])
					{
						continue;
					}
					$done_users[$subscribedmember['uid']] = 1;
					
					$forumpermissions = forum_permissions($thread['fid'], $subscribedmember['uid']);
					if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
					{
					    continue;
					}
					
					// Determine the language pack we'll be using to send this email in and load it if it isn't already.
					if($subscribedmember['language'] != '' && $lang->language_exists($subscribedmember['language']))
					{
						$uselang = $subscribedmember['language'];
					}
					else if($mybb->settings['bblanguage'])
					{
						$uselang = $mybb->settings['bblanguage'];
					}
					else
					{
						$uselang = "english";
					}

					if($uselang == $mybb->settings['bblanguage'])
					{
						$emailsubject = $lang->emailsubject_forumsubscription;
						$emailmessage = $lang->email_forumsubscription;
					}
					else
					{
						if(!isset($langcache[$uselang]['emailsubject_forumsubscription']))
						{
							$userlang = new MyLanguage;
							$userlang->set_path(MYBB_ROOT."inc/languages");
							$userlang->set_language($uselang);
							$userlang->load("messages");
							$langcache[$uselang]['emailsubject_forumsubscription'] = $userlang->emailsubject_forumsubscription;
							$langcache[$uselang]['email_forumsubscription'] = $userlang->email_forumsubscription;
							unset($userlang);
						}
						$emailsubject = $langcache[$uselang]['emailsubject_forumsubscription'];
						$emailmessage = $langcache[$uselang]['email_forumsubscription'];
					}
					$emailsubject = $lang->sprintf($emailsubject, $forum['name']);
					
					$post_code = md5($subscribedmember['loginkey'].$subscribedmember['salt'].$subscribedmember['regdate']);
					$emailmessage = $lang->sprintf($emailmessage, $subscribedmember['username'], $thread['username'], $forum['name'], $mybb->settings['bbname'], $thread['subject'], $excerpt, $mybb->settings['bburl'], get_thread_link($this->tid), $thread['fid'], $post_code);
					$new_email = array(
						"mailto" => $db->escape_string($subscribedmember['email']),
						"mailfrom" => '',
						"subject" => $db->escape_string($emailsubject),
						"message" => $db->escape_string($emailmessage),
						"headers" => ''
					);
					$db->insert_query("mailqueue", $new_email);
					unset($userlang);
					$queued_email = 1;
				}
				// Have one or more emails been queued? Update the queue count
				if($queued_email == 1)
				{
					$cache->update_mailqueue();
				}
			}
		}

		// Assign any uploaded attachments with the specific posthash to the newly created post.
		if($thread['posthash'])
		{
			$thread['posthash'] = $db->escape_string($thread['posthash']);
			$attachmentassign = array(
				"pid" => $this->pid
			);
			$db->update_query("attachments", $attachmentassign, "posthash='{$thread['posthash']}'");
		}
		
		if($visible == 1)
		{
			update_thread_data($this->tid);
			update_forum_counters($thread['fid'], array("threads" => "+1", "posts" => "+1"));
		}
		else if($visible == 0)
		{
			update_thread_data($this->tid);
			update_thread_counters($this->tid, array("replies" => 0, "unapprovedposts" => 1));
			update_forum_counters($thread['fid'], array("unapprovedthreads" => "+1", "unapprovedposts" => "+1"));
		}
		
		$query = $db->simple_select("attachments", "COUNT(aid) AS attachmentcount", "pid='{$this->pid}' AND visible='1'");
		$attachmentcount = $db->fetch_field($query, "attachmentcount");
		if($attachmentcount > 0)
		{
			update_thread_counters($this->tid, array("attachmentcount" => "+{$attachmentcount}"));
		}

		// Return the post's pid and whether or not it is visible.
		return array(
			"pid" => $this->pid,
			"tid" => $this->tid,
			"visible" => $visible
		);
	}

	/**
	 * Updates a post that is already in the database.
	 *
	 */
	function update_post()
	{
		global $db, $mybb, $plugins;

		// Yes, validating is required.
		if($this->get_validated() != true)
		{
			die("The post needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The post is not valid.");
		}

		$post = &$this->data;

		$post['pid'] = intval($post['pid']);
		
		$existing_post = get_post($post['pid']);
		$post['tid'] = $existing_post['tid'];
		$post['fid'] = $existing_post['fid'];
		
		$forum = get_forum($post['fid']);

		// Decide on the visibility of this post.
		if(isset($post['visible']) && $post['visible'] != $existing_post['visible'])
        {
            if($forum['mod_edit_posts'] == 1 && !is_moderator($post['fid'], "", $post['uid']))
            {
                if($existing_post['visible'] == 1)
                {
                    update_thread_data($existing_post['tid']);
                    update_thread_counters($existing_post['tid'], array('replies' => '-1', 'unapprovedposts' => '+1'));
                    update_forum_counters($existing_post['fid'], array('unapprovedthreads' => '+1', 'unapprovedposts' => '+1'));
                    
                    // Subtract from the users post count
                    // Update the post count if this forum allows post counts to be tracked
                    if($forum['usepostcounts'] != 0)
                    {
                        $db->write_query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum-1 WHERE uid='{$existing_post['uid']}'");
                    }
                }
                $visible = 0;
            }
            else
            {
                if($existing_post['visible'] == 0)
                {
                    update_thread_data($existing_post['tid']);
                    update_thread_counters($existing_post['tid'], array('replies' => '+1', 'unapprovedposts' => '-1'));
                    update_forum_counters($existing_post['fid'], array('unapprovedthreads' => '-1', 'unapprovedposts' => '-1'));
                    
                    // Update the post count if this forum allows post counts to be tracked
                    if($forum['usepostcounts'] != 0)
                    {
                        $db->write_query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum+1 WHERE uid='{$existing_post['uid']}'");
                    }
                }
                $visible = 1;
            }
        }
        else
        {
			$visible = 0;
			if($forum['mod_edit_posts'] != 1 || is_moderator($post['fid'], "", $post['uid']))
			{
				$visible = 1;
			}
        }

		// Check if this is the first post in a thread.
		$options = array(
			"order_by" => "dateline",
			"order_dir" => "asc",
			"limit_start" => 0,
			"limit" => 1
		);
		$query = $db->simple_select("posts", "pid", "tid='".intval($post['tid'])."'", $options);
		$first_post_check = $db->fetch_array($query);
		if($first_post_check['pid'] == $post['pid'])
		{
			$first_post = true;
		}
		else
		{
			$first_post = false;
		}
		
		if($existing_post['visible'] == 0)
		{
			$visible = 0;
		}
		
		// Update the thread details that might have been changed first.
		if($first_post)
		{			
			$this->tid = $post['tid'];

			$this->thread_update_data['visible'] = $visible;

			if(isset($post['subject']))
			{
				$this->thread_update_data['subject'] = $db->escape_string($post['subject']);
			}

			if(isset($post['icon']))
			{
				$this->thread_update_data['icon'] = intval($post['icon']);
			}
			if(count($this->thread_update_data) > 0)
			{
				$plugins->run_hooks_by_ref("datahandler_post_update_thread", $this);

				$db->update_query("threads", $this->thread_update_data, "tid='".intval($post['tid'])."'");
			}
		}

		// Prepare array for post updating.

		$this->pid = $post['pid'];

		if(isset($post['subject']))
		{
			$this->post_update_data['subject'] = $db->escape_string($post['subject']);
		}

		if(isset($post['message']))
		{
			$this->post_update_data['message'] = $db->escape_string($post['message']);
		}

		if(isset($post['icon']))
		{
			$this->post_update_data['icon'] = intval($post['icon']);
		}

		if(isset($post['options']))
		{
			if(isset($post['options']['disablesmilies']))
			{
				$this->post_update_data['smilieoff'] = $db->escape_string($post['options']['disablesmilies']);
			}
			if(isset($post['options']['signature']))
			{
				$this->post_update_data['includesig'] = $db->escape_string($post['options']['signature']);
			}
		}

		// If we need to show the edited by, let's do so.
		if(($mybb->settings['showeditedby'] == 1 && !is_moderator($post['fid'], "caneditposts", $post['edit_uid'])) || ($mybb->settings['showeditedbyadmin'] == 1 && is_moderator($post['fid'], "caneditposts", $post['edit_uid'])))
		{
			$this->post_update_data['edituid'] = intval($post['edit_uid']);
			$this->post_update_data['edittime'] = TIME_NOW;
		}

		$this->post_update_data['visible'] = $visible;
		
		$plugins->run_hooks_by_ref("datahandler_post_update", $this);

		$db->update_query("posts", $this->post_update_data, "pid='".intval($post['pid'])."'");

		// Automatic subscription to the thread
		if($post['options']['subscriptionmethod'] != "" && $post['uid'] > 0)
		{
			switch($post['options']['subscriptionmethod'])
			{
				case "instant":
					$notification = 1;
					break;
				default:
					$notification = 0;
			}
			require_once MYBB_ROOT."inc/functions_user.php";
			add_subscribed_thread($post['tid'], $notification, $post['uid']);
		}
		else
		{
			$db->delete_query("threadsubscriptions", "uid='".intval($post['uid'])."' AND tid='".intval($post['tid'])."'");
		}

		update_forum_lastpost($post['fid']);

		return array(
			'visible' => $visible,
			'first_post' => $first_post
		);
	}
}
?>
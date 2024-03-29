<?php
/**
 * MyBB 1.4
 * Copyright � 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: class_custommoderation.php 4392 2009-07-05 21:36:50Z RyanGordon $
 */
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/**
 * Used to execute a custom moderation tool
 *
 */

class CustomModeration extends Moderation
{
	/**
	 * Get info on a tool
	 *
	 * @param int Tool ID
	 * @param mixed Thread IDs
	 * @param mixed Post IDs
	 * @return mixed Returns tool data (tid, type, name, description) in an array, otherwise boolean false.
	 */
	function tool_info($tool_id)
	{
		global $db;

		// Get tool info
		$query = $db->simple_select("modtools", 'tid, type, name, description', 'tid="'.intval($tool_id).'"');
		$tool = $db->fetch_array($query);
		if(!$tool['tid'])
		{
			return false;
		}
		else
		{
			return $tool;
		}
	}

	/**
	 * Execute Custom Moderation Tool
	 *
	 * @param int Tool ID
	 * @param mixed Thread ID(s)
	 * @param mixed Post IDs
	 * @return string 'forum' or 'default' indicating where to redirect
	 */
	function execute($tool_id, $tids=0, $pids=0)
	{
		global $db;

		// Get tool info
		$query = $db->simple_select("modtools", '*', 'tid="'.intval($tool_id).'"');
		$tool = $db->fetch_array($query);
		if(!$tool['tid'])
		{
			return false;
		}

		// Format single tid and pid
		if(!is_array($tids))
		{
			$tids = array($tids);
		}
		if(!is_array($pids))
		{
			$pids = array($pids);
		}

		// Unserialize custom moderation
		$post_options = unserialize($tool['postoptions']);
		$thread_options = unserialize($tool['threadoptions']);

		// If the tool type is a post tool, then execute the post moderation
		if($tool['type'] == 'p')
		{
			$this->execute_post_moderation($post_options, $pids, $tids);
		}
		// Always execute thead moderation
		$this->execute_thread_moderation($thread_options, $tids);

		// If the thread is deleted, indicate to the calling script to redirect to the forum, and not the nonexistant thread
		if($thread_options['deletethread'] == 1)
		{
			return 'forum';
		}
		return 'default';
	}

	/**
	 * Execute Inline Post Moderation
	 *
	 * @param array Moderation information
	 * @param mixed Post IDs
	 * @param array Thread IDs (in order of dateline ascending)
	 * @return boolean true
	 */
	function execute_post_moderation($post_options, $pids, $tid)
	{
		global $db, $mybb;

		if(is_array($tid))
		{
			$tid = intval($tid[0]); // There's only 1 thread when doing inline post moderation
			// The thread chosen is the first thread in the array of tids.
			// It is recommended that this be the tid of the oldest post
		}

		// Get the information about thread
		$thread = get_thread($tid);

		// If deleting posts, only do that
		if($post_options['deleteposts'] == 1)
		{
			foreach($pids as $pid)
			{
				$this->delete_post($pid);
			}
		}
		else
		{
			if($post_options['mergeposts'] == 1) // Merge posts
			{
				$this->merge_posts($pids);
			}

			if($post_options['approveposts'] == 'approve') // Approve posts
			{
				$this->approve_posts($pids);
			}
			elseif($post_options['approveposts'] == 'unapprove') // Unapprove posts
			{
				$this->unapprove_posts($pids);
			}
			elseif($post_options['approveposts'] == 'toggle') // Toggle post visibility
			{
				$this->toggle_post_visibility($pids);
			}

			if($post_options['splitposts'] > 0 || $post_options['splitposts'] == -2) // Split posts
			{
				if($post_options['splitposts'] == -2)
				{
					$post_options['splitposts'] = $thread['fid'];
				}
				if(empty($post_options['splitpostsnewsubject']))
				{
					// Enter in a subject if a predefined one does not exist.
					$post_options['splitpostsnewsubject'] = '[split] '.$thread['subject'];
				}
				$new_subject = str_ireplace('{subject}', $thread['subject'], $post_options['splitpostsnewsubject']);
				$new_tid = $this->split_posts($pids, $tid, $post_options['splitposts'], $new_subject);
				if($post_options['splitpostsclose'] == 'close') // Close new thread
				{
					$this->close_threads($new_tid);
				}
				if($post_options['splitpostsstick'] == 'stick') // Stick new thread
				{
					$this->stick_threads($new_tid);
				}
				if($post_options['splitpostsunapprove'] == 'unapprove') // Unapprove new thread
				{
					$this->unapprove_threads($new_tid, $thread['fid']);
				}
				if(!empty($post_options['splitpostsaddreply'])) // Add reply to new thread
				{
					require_once MYBB_ROOT."inc/datahandlers/post.php";
					$posthandler = new PostDataHandler("insert");

					if(empty($post_options['splitpostsreplysubject']))
					{
						$post_options['splitpostsreplysubject'] = 'RE: '.$new_subject;
					}	
					else
					{
						$post_options['splitpostsreplysubject'] = str_ireplace('{username}', $mybb->user['username'], $thread_options['replysubject']);
						$post_options['splitpostsreplysubject'] = str_ireplace('{subject}', $new_subject, $post_options['splitpostsreplysubject']);
					}
					
					// Set the post data that came from the input to the $post array.
					$post = array(
						"tid" => $new_tid,
						"fid" => $post_options['splitposts'],
						"subject" => $post_options['splitpostsreplysubject'],
						"uid" => $mybb->user['uid'],
						"username" => $mybb->user['username'],
						"message" => $post_options['splitpostsaddreply'],
						"ipaddress" => $db->escape_string(get_ip()),
					);
					// Set up the post options from the input.
					$post['options'] = array(
						"signature" => 1,
						"emailnotify" => 0,
						"disablesmilies" => 0
					);

					$posthandler->set_data($post);

					if($posthandler->validate_post($post))
					{
						$posthandler->insert_post($post);
					}					
				}
			}
		}
		return true;
	}

	/**
	 * Execute Normal and Inline Thread Moderation
	 *
	 * @param array Moderation information
	 * @param mixed Thread IDs
	 * @return boolean true
	 */
	function execute_thread_moderation($thread_options, $tids)
	{
		global $db, $mybb;

		$tid = intval($tids[0]); // Take the first thread to get thread data from
		$query = $db->simple_select("threads", 'fid', "tid='$tid'");
		$thread = $db->fetch_array($query);

		// If deleting threads, only do that
		if($thread_options['deletethread'] == 1)
		{
			foreach($tids as $tid)
			{
				$this->delete_thread($tid);
			}
		}
		else
		{
			if($thread_options['mergethreads'] == 1 && count($tids) > 1) // Merge Threads (ugly temp code until find better fix)
			{
				$tid_list = implode(',', $tids);
				$options = array('order_by' => 'dateline', 'order_dir' => 'DESC');
				$query = $db->simple_select("threads", 'tid, subject', "tid IN ($tid_list)", $options); // Select threads from newest to oldest
				$last_tid = 0;
				while($tid = $db->fetch_array($query))
				{
					if($last_tid != 0)
					{
						$this->merge_threads($last_tid, $tid['tid'], $tid['subject']); // And keep merging them until we get down to one thread. 
					}
					$last_tid = $tid['tid'];
				}
			}
			if($thread_options['deletepoll'] == 1) // Delete poll
			{
				foreach($tids as $tid)
				{
					$this->delete_poll($tid);
				}
			}
			if($thread_options['removeredirects'] == 1) // Remove redirects
			{
				foreach($tids as $tid)
				{
					$this->remove_redirects($tid);
				}
			}

			if($thread_options['approvethread'] == 'approve') // Approve thread
			{
				$this->approve_threads($tids, $thread['fid']);
			}
			elseif($thread_options['approvethread'] == 'unapprove') // Unapprove thread
			{
				$this->unapprove_threads($tids, $thread['fid']);
			}
			elseif($thread_options['approvethread'] == 'toggle') // Toggle thread visibility
			{
				$this->toggle_thread_visibility($tids, $thread['fid']);
			}

			if($thread_options['openthread'] == 'open') // Open thread
			{
				$this->open_threads($tids);
			}
			elseif($thread_options['openthread'] == 'close') // Close thread
			{
				$this->close_threads($tids);
			}
			elseif($thread_options['openthread'] == 'toggle') // Toggle thread visibility
			{
				$this->toggle_thread_status($tids);
			}

			if(my_strtolower(trim($thread_options['newsubject'])) != '{subject}') // Update thread subjects
			{
				$this->change_thread_subject($tids, $thread_options['newsubject']);
			}
			if(!empty($thread_options['addreply'])) // Add reply to thread
			{
				$tid_list = implode(',', $tids);
				$query = $db->simple_select("threads", 'fid, subject, tid, firstpost', "tid IN ($tid_list) AND closed NOT LIKE 'moved|%'");
				require_once MYBB_ROOT."inc/datahandlers/post.php";
				
				// Loop threads adding a reply to each one
				while($thread = $db->fetch_array($query))
				{
					$posthandler = new PostDataHandler("insert");
			
					if(empty($thread_options['replysubject']))
                    {
                        $new_subject = 'RE: '.$thread['subject'];
                    }
                    else
                    {
                        $new_subject = str_ireplace('{username}', $mybb->user['username'], $thread_options['replysubject']);
                        $new_subject = str_ireplace('{subject}', $thread['subject'], $new_subject);
                    }
    
                    // Set the post data that came from the input to the $post array.
                    $post = array(
                        "tid" => $thread['tid'],
                        "replyto" => $thread['firstpost'],
                        "fid" => $thread['fid'],
                        "subject" => $new_subject,
						"uid" => $mybb->user['uid'],
						"username" => $mybb->user['username'],
						"message" => $thread_options['addreply'],
						"ipaddress" => $db->escape_string(get_ip()),
					);
					// Set up the post options from the input.
					$post['options'] = array(
						"signature" => 1,
						"emailnotify" => 0,
						"disablesmilies" => 0
					);
	
					$posthandler->set_data($post);
					if($posthandler->validate_post($post))
					{
						$posthandler->insert_post($post);
					}
				}
			}
			if($thread_options['movethread'] > 0 && $thread_options['movethread'] != $thread['fid']) // Move thread
			{
				if($thread_options['movethreadredirect'] == 1) // Move Thread with redirect
				{
					$time = TIME_NOW + ($thread_options['movethreadredirectexpire'] * 86400);
					foreach($tids as $tid)
					{
						$this->move_thread($tid, $thread_options['movethread'], 'redirect', $time);
					}
				}
				else // Normal move
				{
					$this->move_threads($tids, $thread_options['movethread']);
				}
			}
			if($thread_options['copythread'] > 0 || $thread_options['copythread'] == -2) // Copy thread
			{
				if($thread_options['copythread'] == -2)
				{
					$thread_options['copythread'] = $thread['fid'];
				}
				foreach($tids as $tid)
				{
					$new_tid = $this->move_thread($tid, $thread_options['copythread'], 'copy');
				}
			}
		}
		return true;
	}
}
?>

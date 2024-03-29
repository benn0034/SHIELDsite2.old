<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: pm.php 4502 2009-11-12 17:02:09Z Tomm $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/**
 * PM handling class, provides common structure to handle private messaging data.
 *
 */
class PMDataHandler extends DataHandler
{
	/**
	* The language file used in the data handler.
	*
	* @var string
	*/
	var $language_file = 'datahandler_pm';

	/**
	* The prefix for the language variables used in the data handler.
	*
	* @var string
	*/
	var $language_prefix = 'pmdata';
	
	/**
	 * Array of data inserted in to a private message.
	 *
	 * @var array
	 */
	var $pm_insert_data = array();

	/**
	 * Array of data used to update a private message.
	 *
	 * @var array
	 */
	var $pm_update_data = array();
	
	/**
	 * PM ID currently being manipulated by the datahandlers.
	 */
	var $pmid = 0;	

	/**
	 * Verifies a private message subject.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_subject()
	{
		$subject = &$this->data['subject'];

		// Subject is over 85 characters, too long.
		if(my_strlen($subject) > 85)
		{
			$this->set_error("too_long_subject");
			return false;
		}
		// No subject, apply the default [no subject]
		if(!trim($subject))
		{
			$this->set_error("missing_subject");
			return false;
		}
		return true;
	}

	/**
	 * Verifies if a message for a PM is valid.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_message()
	{
		$message = &$this->data['message'];

		// No message, return an error.
		if(trim($message) == '')
		{
			$this->set_error("missing_message");
			return false;
		}
		return true;
	}

	/**
	 * Verifies if the specified sender is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_sender()
	{
		global $db, $mybb, $lang;

		$pm = &$this->data;

		// Return if we've already validated
		if($pm['sender']) return true;

		// Fetch the senders profile data.
		$sender = get_user($pm['fromid']);

		// Collect user permissions for the sender.
		$sender_permissions = user_permissions($pm['fromid']);

		// Check if the sender is over their quota or not - if they are, disable draft sending
		if($pm['options']['savecopy'] != 0 && !$pm['saveasdraft'])
		{
			if($sender_permissions['pmquota'] != "0" && $sender['totalpms'] >= $sender_permissions['pmquota'] && $this->admin_override != true)
			{
				$pm['options']['savecopy'] = 0;
			}
		}

		// Assign the sender information to the data.
		$pm['sender'] = array(
			"uid" => $sender['uid'],
			"username" => $sender['username']
		);

		return true;
	}

	/**
	 * Verifies if an array of recipients for a private message are valid
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_recipient()
	{
		global $db, $mybb, $lang;

		$pm = &$this->data;

		$recipients = array();

		$invalid_recipients = array();
		// We have our recipient usernames but need to fetch user IDs
		if(array_key_exists("to", $pm))
		{
			if((count($pm['to']) <= 0 || trim(implode("", $pm['to'])) == "") && !$pm['saveasdraft'])
			{
				$this->set_error("no_recipients");
				return false;
			}

			foreach(array("to", "bcc") as $recipient_type)
			{
				if(!is_array($pm[$recipient_type]))
				{
					$pm[$recipient_type] = array($pm[$recipient_type]);
				}
				foreach($pm[$recipient_type] as $username)
				{
					$username = trim($username);
					if(empty($username))
					{
						continue;
					}
					// Check that this recipient actually exists
					$query = $db->simple_select("users", "*", "username='".$db->escape_string($username)."'");
					$user = $db->fetch_array($query);
					if($recipient_type == "bcc")
					{
						$user['bcc'] = 1;
					}
					if($user['uid'])
					{
						$recipients[] = $user;
					}
					else
					{
						$invalid_recipients[] = $username;
					}
				}
			}
		}
		// We have recipient IDs
		else
		{
			foreach(array("toid", "bccid") as $recipient_type)
			{
				if(count($pm['toid']) <= 0)
				{
					$this->set_error("no_recipients");
					return false;
				}
				if(is_array($pm[$recipient_type]))
				{
					foreach($pm[$recipient_type] as $uid)
					{
						// Check that this recipient actually exists
						$query = $db->simple_select("users", "*", "uid='".intval($uid)."'");
						$user = $db->fetch_array($query);
						if($recipient_type == "bccid")
						{
							$user['bcc'] = 1;
						}
						if($user['uid'])
						{
							$recipients[] = $user;
						}
						else
						{
							$invalid_recipients[] = $uid;
						}
					}
				}
			}
		}

		// If we have one or more invalid recipients and we're not saving a draft, error
		if(count($invalid_recipients) > 0)
		{
			$invalid_recipients = implode(", ", array_map("htmlspecialchars_uni", $invalid_recipients));
			$this->set_error("invalid_recipients", array($invalid_recipients));
			return false;
		}

		$sender_permissions = user_permissions($pm['fromid']);

		// Are we trying to send this message to more users than the permissions allow?
		if($sender_permissions['maxpmrecipients'] > 0 && count($recipients) > $sender_permissions['maxpmrecipients'] && $this->admin_override != true)
		{
			$this->set_error("too_many_recipients", array($sender_permissions['maxpmrecipients']));
		}

		// Now we're done with that we loop through each recipient
		foreach($recipients as $user)
		{
			// Collect group permissions for this recipient.
			$recipient_permissions = user_permissions($user['uid']);
	
			// See if the sender is on the recipients ignore list and that either
			// - admin_override is set or
			// - sender is an administrator
			if($this->admin_override != true && $sender_permissions['cancp'] != 1)
			{
				$ignorelist = explode(",", $user['ignorelist']);
				foreach($ignorelist as $uid)
				{
					if($uid == $pm['fromid'])
					{
						$this->set_error("recipient_is_ignoring", array($user['username']));
					}
				}
				
				// Can the recipient actually receive private messages based on their permissions or user setting?
				if(($user['receivepms'] == 0 || $recipient_permissions['canusepms'] == 0) && !$pm['saveasdraft'])
				{
					$this->set_error("recipient_pms_disabled", array($user['username']));
					return false;
				}
			}
	
			// Check to see if the user has reached their private message quota - if they have, email them.
			if($recipient_permissions['pmquota'] != "0" && $user['totalpms'] >= $recipient_permissions['pmquota'] && $recipient_permissions['cancp'] != 1 && $sender_permissions['cancp'] != 1 && !$pm['saveasdraft'] && !$this->admin_override)
			{
				if(trim($user['language']) != '' && $lang->language_exists($user['language']))
				{
					$uselang = trim($user['language']);
				}
				elseif($mybb->settings['bblanguage'])
				{
					$uselang = $mybb->settings['bblanguage'];
				}
				else
				{
					$uselang = "english";
				}
				if($uselang == $mybb->settings['bblanguage'] || !$uselang)
				{
					$emailsubject = $lang->emailsubject_reachedpmquota;
					$emailmessage = $lang->email_reachedpmquota;
				}
				else
				{
					$userlang = new MyLanguage;
					$userlang->set_path(MYBB_ROOT."inc/languages");
					$userlang->set_language($uselang);
					$userlang->load("messages");
					$emailsubject = $userlang->emailsubject_reachedpmquota;
					$emailmessage = $userlang->email_reachedpmquota;
				}
				$emailmessage = $lang->sprintf($emailmessage, $user['username'], $mybb->settings['bbname'], $mybb->settings['bburl']);
				$emailsubject = $lang->sprintf($emailsubject, $mybb->settings['bbname']);
				my_mail($user['email'], $emailsubject, $emailmessage);
	
				if($this->admin_override != true)
				{
					$this->set_error("recipient_reached_quota", array($user['username']));
				}
			}
	
			// Everything looks good, assign some specifics about the recipient
			$pm['recipients'][$user['uid']] = array(
				"uid" => $user['uid'],
				"username" => $user['username'],
				"email" => $user['email'],
				"lastactive" => $user['lastactive'],
				"pmnotice" => $user['pmnotice'],
				"pmnotify" => $user['pmnotify'],
				"language" => $user['language']
			);
			
			// If this recipient is defined as a BCC recipient, save it
			if($user['bcc'] == 1)
			{
				$pm['recipients'][$user['uid']]['bcc'] = 1;
			}
		}
		return true;
	}
	
	/**
	* Verify that the user is not flooding the system.
	* Temporary fix until a better one can be made for 1.6
	*
	* @return boolean True
	*/
	function verify_pm_flooding()
	{
		global $mybb, $db;

		$pm = &$this->data;
		
		// Check if post flooding is enabled within MyBB or if the admin override option is specified.
		if($mybb->settings['postfloodcheck'] == 1 && $pm['fromid'] != 0 && $this->admin_override == false)
		{
			// Fetch the senders profile data.
			$sender = get_user($pm['fromid']);
			
			// Calculate last post
			$query = $db->simple_select("privatemessages", "dateline", "fromid='".$db->escape_string($pm['fromid'])."' AND toid != '0'", array('order_by' => 'dateline', 'order_dir' => 'desc', 'limit' => 1));
			$sender['lastpm'] = $db->fetch_field($query, "dateline");

			// A little bit of calculation magic and moderator status checking.
			if(TIME_NOW-$sender['lastpm'] <= $mybb->settings['postfloodsecs'] && !is_moderator("", "", $pm['fromid']))
			{
				// Oops, user has been flooding - throw back error message.
				$time_to_wait = ($mybb->settings['postfloodsecs'] - (TIME_NOW-$sender['lastpm'])) + 1;
				if($time_to_wait == 1)
				{
					$this->set_error("pm_flooding_one_second");
				}
				else
				{
					$this->set_error("pm_flooding", array($time_to_wait));
				}
				return false;
			}
		}
		// All is well that ends well - return true.
		return true;
	}

	/**
	 * Verifies if the various 'options' for sending PMs are valid.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_options()
	{
		$options = &$this->data['options'];

		$this->verify_yesno_option($options, 'signature', 1);
		$this->verify_yesno_option($options, 'savecopy', 1);
		$this->verify_yesno_option($options, 'disablesmilies', 0);

		// Requesting a read receipt?
		if(isset($options['readreceipt']) && $options['readreceipt'] == 1)
		{
			$options['readreceipt'] = 1;
		}
		else
		{
			$options['readreceipt'] = 0;
		}
		return true;
	}

	/**
	 * Validate an entire private message.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_pm()
	{
		global $plugins;

		$pm = &$this->data;

		// Verify all PM assets.
		$this->verify_subject();

		$this->verify_sender();

		$this->verify_recipient();
		
		$this->verify_message();

		$this->verify_options();

		$plugins->run_hooks_by_ref("datahandler_pm_validate", $this);

		// Choose the appropriate folder to save in.
		if($pm['saveasdraft'])
		{
			$pm['folder'] = 3;
		}
		else
		{
			$pm['folder'] = 1;
		}

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
	 * Insert a new private message.
	 *
	 * @return array Array of PM useful data.
	 */
	function insert_pm()
	{
		global $db, $mybb, $plugins, $lang;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The PM needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The PM is not valid.");
		}

		// Assign data to common variable
		$pm = &$this->data;

		$pm['pmid'] = intval($pm['pmid']);

		if(!$pm['icon'] || $pm['icon'] < 0)
		{
			$pm['icon'] = 0;
		}

		$uid = 0;

		if(!is_array($pm['recipients']))
		{
			$recipient_list = array();
		}
		else
		{
			// Build recipient list
			foreach($pm['recipients'] as $recipient)
			{
				if($recipient['bcc'])
				{
					$recipient_list['bcc'][] = $recipient['uid'];
				}
				else
				{
					$recipient_list['to'][] = $recipient['uid'];
					$uid = $recipient['uid'];
				}
			}
		}
		$recipient_list = serialize($recipient_list);

		$this->pm_insert_data = array(
			'fromid' => intval($pm['sender']['uid']),
			'folder' => $pm['folder'],
			'subject' => $db->escape_string($pm['subject']),
			'icon' => intval($pm['icon']),
			'message' => $db->escape_string($pm['message']),
			'dateline' => TIME_NOW,
			'status' => 0,
			'includesig' => $pm['options']['signature'],
			'smilieoff' => $pm['options']['disablesmilies'],
			'receipt' => intval($pm['options']['readreceipt']),
			'readtime' => 0,
			'recipients' => $db->escape_string($recipient_list)
		);

		// Check if we're updating a draft or not.
		$query = $db->simple_select("privatemessages", "pmid, deletetime", "folder='3' AND uid='".intval($pm['sender']['uid'])."' AND pmid='{$pm['pmid']}'");
		$draftcheck = $db->fetch_array($query);

		// This PM was previously a draft
		if($draftcheck['pmid'])
		{
			if($draftcheck['deletetime'])
			{
				// This draft was a reply to a PM
				$pm['pmid'] = $draftcheck['deletetime'];
				$pm['do'] = "reply";
			}

			// Delete the old draft as we no longer need it
			$db->delete_query("privatemessages", "pmid='{$draftcheck['pmid']}'");
		}

		// Saving this message as a draft
		if($pm['saveasdraft'])
		{
			$this->pm_insert_data['uid'] = $pm['sender']['uid'];

			// If this is a reply, then piggyback into the deletetime to let us know in the future
			if($pm['do'] == "reply" || $pm['do'] == "replyall")
			{
				$this->pm_insert_data['deletetime'] = $pm['pmid'];
			}

			$plugins->run_hooks_by_ref("datahandler_pm_insert_updatedraft", $this);
			$db->insert_query("privatemessages", $this->pm_insert_data);

			// If this is a draft, end it here - below deals with complete messages
			return array(
				"draftsaved" => 1
			);
		}

		// Save a copy of the PM for each of our recipients
		foreach($pm['recipients'] as $recipient)
		{
			// Send email notification of new PM if it is enabled for the recipient
			$query = $db->simple_select("privatemessages", "dateline", "uid='".$recipient['uid']."' AND folder='1'", array('order_by' => 'dateline', 'order_dir' => 'desc', 'limit' => 1));
			$lastpm = $db->fetch_array($query);
			if($recipient['pmnotify'] == 1 && $recipient['lastactive'] > $lastpm['dateline'])
			{
				if($recipient['language'] != "" && $lang->language_exists($recipient['language']))
				{
					$uselang = $recipient['language'];
				}
				elseif($mybb->settings['bblanguage'])
				{
					$uselang = $mybb->settings['bblanguage'];
				}
				else
				{
					$uselang = "english";
				}
				if($uselang == $mybb->settings['bblanguage'] && !empty($lang->emailsubject_newpm))
				{
					$emailsubject = $lang->emailsubject_newpm;
					$emailmessage = $lang->email_newpm;
				}
				else
				{
					$userlang = new MyLanguage;
					$userlang->set_path(MYBB_ROOT."inc/languages");
					$userlang->set_language($uselang);
					$userlang->load("messages");
					$emailsubject = $userlang->emailsubject_newpm;
					$emailmessage = $userlang->email_newpm;
				}
				
				if(!$pm['sender']['username'])
				{
					$pm['sender']['username'] = 'MyBB Engine';
				}
				
				$emailmessage = $lang->sprintf($emailmessage, $recipient['username'], $pm['sender']['username'], $mybb->settings['bbname'], $mybb->settings['bburl']);
				$emailsubject = $lang->sprintf($emailsubject, $mybb->settings['bbname']);
				my_mail($recipient['email'], $emailsubject, $emailmessage);
			}

			$this->pm_insert_data['uid'] = $recipient['uid'];
			$this->pm_insert_data['toid'] = $recipient['uid'];

			$plugins->run_hooks_by_ref("datahandler_pm_insert", $this);
			$this->pmid = $db->insert_query("privatemessages", $this->pm_insert_data);

			// If PM noices/alerts are on, show!
			if($recipient['pmnotice'] == 1)
			{
				$updated_user = array(
					"pmnotice" => 2
				);
				$db->update_query("users", $updated_user, "uid='{$recipient['uid']}'");
			}

			// Update private message count (total, new and unread) for recipient
			require_once MYBB_ROOT."/inc/functions_user.php";
			update_pm_count($recipient['uid'], 7, $recipient['lastactive']);
		}

		// Are we replying or forwarding an existing PM?
		if($pm['pmid'])
		{
			if($pm['do'] == "reply" || $pm['do'] == "replyall")
			{
				$sql_array = array(
					'status' => 3,
					'statustime' => TIME_NOW
				);
				$db->update_query("privatemessages", $sql_array, "pmid={$pm['pmid']} AND uid={$pm['sender']['uid']}");
			}
			elseif($pm['do'] == "forward")
			{
				$sql_array = array(
					'status' => 4,
					'statustime' => TIME_NOW
				);
				$db->update_query("privatemessages", $sql_array, "pmid={$pm['pmid']} AND uid={$pm['sender']['uid']}");
			}
		}

		// If we're saving a copy
		if($pm['options']['savecopy'] != 0)
		{
			if(count($recipient_list['to']) == 1)
			{
				$this->pm_insert_data['toid'] = $uid;
			}
			else
			{
				$this->pm_insert_data['toid'] = 0;
			}
			$this->pm_insert_data['uid'] = intval($pm['sender']['uid']);
			$this->pm_insert_data['folder'] = 2;
			$this->pm_insert_data['status'] = 1;
			$this->pm_insert_data['receipt'] = 0;

			$plugins->run_hooks_by_ref("datahandler_pm_insert_savedcopy", $this);
			$db->insert_query("privatemessages", $this->pm_insert_data);

			// Because the sender saved a copy, update their total pm count
			require_once MYBB_ROOT."/inc/functions_user.php";
			update_pm_count($pm['sender']['uid'], 1);
		}

		// Return back with appropriate data
		return array(
			"messagesent" => 1
		);
	}
}
?>

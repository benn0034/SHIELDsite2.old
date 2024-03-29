<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

/**
 * Upgrade Script: MyBB 1.4 or 1.4.1
 */


$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade13_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	flush();

	if($db->type == "mysql" || $db->type == "mysqli")
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminsessions ADD INDEX ( `uid` )");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminsessions ADD INDEX ( `dateline` )");
	}
	
	if($db->type != "sqlite2" && $db->type != "sqlite3")
	{
		if($db->index_exists("users", "username"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP KEY username");
		}
		
		$query = $db->simple_select("users", "username, uid", "1=1 GROUP BY username HAVING count(*) > 1");
		while($user = $db->fetch_array($query))
		{
			$db->update_query("users", array('username' => $user['username']."_dup".$user['uid']), "uid='{$user['uid']}'", 1);
		}
		
		if($db->type == "pgsql")
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD UNIQUE(username)");
		}
		else
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD UNIQUE KEY username (username)");
		}
	}
	
	if($db->type == "pgsql")
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE longregip longregip int NOT NULL default '0'");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE longlastip longlastip int NOT NULL default '0'");
	
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE longipaddress longipaddress int NOT NULL default '0'");
	}
	else
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE longregip longregip int(11) NOT NULL default '0'");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE longlastip longlastip int(11) NOT NULL default '0'");
	
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE longipaddress longipaddress int(11) NOT NULL default '0'");
	}

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("13_dbchanges1");
}

function upgrade13_dbchanges1()
{
	global $db, $output;
	
	$output->print_header("Post IP Repair Conversion");

	if(!$_POST['ipspage'])
	{
		$ipp = 5000;
	}
	else
	{
		$ipp = $_POST['ipspage'];
	}

	if($_POST['ipstart'])
	{
		$startat = $_POST['ipstart'];
		$upper = $startat+$ipp;
		$lower = $startat;
	}
	else
	{
		$startat = 0;
		$upper = $ipp;
		$lower = 1;
	}

	$query = $db->simple_select("posts", "COUNT(pid) AS ipcount");
	$cnt = $db->fetch_array($query);
	
	if($upper > $cnt['ipcount'])
	{
		$upper = $cnt['ipcount'];
	}

	echo "<p>Repairing ip {$lower} to {$upper} ({$cnt['ipcount']} Total)</p>";
	flush();
	
	$ipaddress = false;
	
	$query = $db->simple_select("posts", "ipaddress, longipaddress, pid", "", array('limit_start' => $lower, 'limit' => $ipp));
	while($post = $db->fetch_array($query))
	{
		// Have we already converted this ip?
		if(ip2long($post['ipaddress']) < 0)
		{
			$db->update_query("posts", array('longipaddress' => ip2long($post['ipaddress'])), "pid = '{$post['pid']}'");
		}
		$ipaddress = true;
	}
	
	$remaining = $upper-$cnt['ipcount'];
	if($remaining && $ipaddress)
	{
		$nextact = "13_dbchanges1";
		$startat = $startat+$ipp;
		$contents = "<p><input type=\"hidden\" name=\"ipspage\" value=\"$ipp\" /><input type=\"hidden\" name=\"ipstart\" value=\"$startat\" />Done. Click Next to move on to the next set of post ips.</p>";
	}
	else
	{
		$nextact = "13_dbchanges2";
		$contents = "<p>Done</p><p>All post ips have been converted to the new ip format. Click next to continue.</p>";
	}
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">window.onload = function() { var button = $$('.submit_button'); if(button[0]) { button[0].value = 'Automatically Redirecting...'; button[0].disabled = true; button[0].style.color = '#aaa'; button[0].style.borderColor = '#aaa'; document.forms[0].submit(); }}</script>";

	$output->print_footer($nextact);
}

function upgrade13_dbchanges2()
{
	global $db, $output;
	
	$output->print_header("User IP Repair Conversion");

	if(!$_POST['ipspage'])
	{
		$ipp = 5000;
	}
	else
	{
		$ipp = $_POST['ipspage'];
	}

	if($_POST['ipstart'])
	{
		$startat = $_POST['ipstart'];
		$upper = $startat+$ipp;
		$lower = $startat;
	}
	else
	{
		$startat = 0;
		$upper = $ipp;
		$lower = 1;
	}

	$query = $db->simple_select("users", "COUNT(uid) AS ipcount");
	$cnt = $db->fetch_array($query);
	
	if($upper > $cnt['ipcount'])
	{
		$upper = $cnt['ipcount'];
	}

	$contents .= "<p>Repairing ip {$lower} to {$upper} ({$cnt['ipcount']} Total)</p>";
	
	$ipaddress = false;
	$update_array = array();
	
	$query = $db->simple_select("users", "regip, lastip, longlastip, longregip, uid", "", array('limit_start' => $lower, 'limit' => $ipp));
	while($user = $db->fetch_array($query))
	{
		// Have we already converted this ip?
		if(ip2long($user['regip']) < 0)
		{
			$update_array['longregip'] = intval(ip2long($user['regip']));
		}
		
		if(ip2long($user['lastip']) < 0)
		{
			$update_array['longlastip'] = intval(ip2long($user['lastip']));
		}
		
		if(!empty($update_array))
		{
			$db->update_query("users", $update_array, "uid = '{$user['uid']}'");
		}
		
		$update_array = array();
		$ipaddress = true;
	}

	$remaining = $upper-$cnt['ipcount'];
	if($remaining && $ipaddress)
	{
		$nextact = "13_dbchanges2";
		$startat = $startat+$ipp;
		$contents .= "<p><input type=\"hidden\" name=\"ipspage\" value=\"$ipp\" /><input type=\"hidden\" name=\"ipstart\" value=\"$startat\" />Done. Click Next to move on to the next set of user ips.</p>";
	}
	else
	{
		$nextact = "13_done";
		$contents .= "<p>Done</p><p>All user ips have been converted to the new ip format. Click next to continue.</p>";
	}
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">window.onload = function() { var button = $$('.submit_button'); if(button[0]) { button[0].value = 'Automatically Redirecting...'; button[0].disabled = true; button[0].style.color = '#aaa'; button[0].style.borderColor = '#aaa'; document.forms[0].submit(); }}</script>";

	$output->print_footer($nextact);	
}

?>
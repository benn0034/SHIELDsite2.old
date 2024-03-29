<?php
/**
 * MyBB 1.4
 * Copyright � 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

$page->extra_header .= "
<script type=\"text/javascript\">
//<![CDATA[
var save_changes_lang_string = '{$lang->save_changes_js}';
var delete_lang_string = '{$lang->delete}';
var file_lang_string = '{$lang->file}';
var globally_lang_string = '{$lang->globally}';
var specific_actions_lang_string = '{$lang->specific_actions}';
var specific_actions_desc_lang_string = '{$lang->specific_actions_desc}';
var delete_confirm_lang_string = '{$lang->delete_confirm_js}';
//]]>
</script>";

if($mybb->input['action'] == "xmlhttp_stylesheet" && $mybb->request_method == "post")
{
	// Fetch the theme we want to edit this stylesheet in
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);
	
	if(!$theme['tid'])
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	$parent_list = make_parent_theme_list($theme['tid']);
	$parent_list = implode(',', $parent_list);
	if(!$parent_list)
	{
		$parent_list = 1;
	}
	
	$query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string($mybb->input['file'])."' AND tid IN ({$parent_list})", array('order_by' => 'tid', 'order_dir' => 'desc', 'limit' => 1));
	$stylesheet = $db->fetch_array($query);
	
	// Does the theme not exist?
	if(!$stylesheet['sid'])
	{
		flash_message($lang->error_invalid_stylesheet, 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	$css_array = css_to_array($stylesheet['stylesheet']);
	$selector_list = get_selectors_as_options($css_array, $mybb->input['selector']);
	$editable_selector = $css_array[$mybb->input['selector']];
	$properties = parse_css_properties($editable_selector['values']);
	
	$form = new Form("index.php?module=style/themes&amp;action=stylesheet_properties", "post", "selector_form", 0, "", true);
	echo $form->generate_hidden_field("tid", $mybb->input['tid'], array('id' => "tid"))."\n";
	echo $form->generate_hidden_field("file", htmlspecialchars_uni($mybb->input['selector']), array('id' => "file"))."\n";
	echo $form->generate_hidden_field("selector", htmlspecialchars_uni($mybb->input['selector']), array('id' => 'hidden_selector'))."\n";
	
	$table = new Table;	
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[background]', $properties['background'], array('id' => 'css_bits[background]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->background}</strong></div>", array('style' => 'width: 20%;'));
	$table->construct_cell("<strong>{$lang->extra_css_atribs}</strong><br /><div style=\"align: center;\">".$form->generate_text_area('css_bits[extra]', $properties['extra'], array('id' => 'css_bits[extra]', 'style' => 'width: 98%;', 'rows' => '19'))."</div>", array('rowspan' => 8));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[color]', $properties['color'], array('id' => 'css_bits[color]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->color}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[width]', $properties['width'], array('id' => 'css_bits[width]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->width}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[font_family]', $properties['font-family'], array('id' => 'css_bits[font_family]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->font_family}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[font_size]', $properties['font-size'], array('id' => 'css_bits[font_size]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->font_size}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[font_style]', $properties['font-style'], array('id' => 'css_bits[font_style]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->font_style}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[font_weight]', $properties['font-weight'], array('id' => 'css_bits[font_weight]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->font_weight}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[text_decoration]', $properties['text-decoration'], array('id' => 'css_bits[text_decoration]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->text_decoration}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	
	$table->output(htmlspecialchars_uni($editable_selector['class_name'])."<span id=\"saved\" style=\"color: #FEE0C6;\"></span>");
	exit;
}

$page->add_breadcrumb_item($lang->themes, "index.php?module=style/themes");

if($mybb->input['action'] == "add" || $mybb->input['action'] == "import" || !$mybb->input['action'])
{
	$sub_tabs['themes'] = array(
		'title' => $lang->themes,
		'link' => "index.php?module=style/themes",
		'description' => $lang->themes_desc
	);

	$sub_tabs['create_theme'] = array(
		'title' => $lang->create_new_theme,
		'link' => "index.php?module=style/themes&amp;action=add",
		'description' => $lang->create_new_theme_desc
	);

	$sub_tabs['import_theme'] = array(
		'title' => $lang->import_a_theme,
		'link' => "index.php?module=style/themes&amp;action=import",
		'description' => $lang->import_a_theme_desc
	);
}

if($mybb->input['action'] == "import")
{
	if($mybb->request_method == "post")
	{
		if(!$_FILES['local_file'] && !$mybb->input['url'])
		{
			$errors[] = $lang->error_missing_url;
		}
		
		if(!$errors)
		{
			// Find out if there was an uploaded file
			if($_FILES['local_file']['error'] != 4)
			{
				// Find out if there was an error with the uploaded file
				if($_FILES['local_file']['error'] != 0)
				{
					$errors[] = $lang->error_uploadfailed.$lang->error_uploadfailed_detail;
					switch($_FILES['local_file']['error'])
					{
						case 1: // UPLOAD_ERR_INI_SIZE
							$errors[] = $lang->error_uploadfailed_php1;
							break;
						case 2: // UPLOAD_ERR_FORM_SIZE
							$errors[] = $lang->error_uploadfailed_php2;
							break;
						case 3: // UPLOAD_ERR_PARTIAL
							$errors[] = $lang->error_uploadfailed_php3;
							break;
						case 6: // UPLOAD_ERR_NO_TMP_DIR
							$errors[] = $lang->error_uploadfailed_php6;
							break;
						case 7: // UPLOAD_ERR_CANT_WRITE
							$errors[] = $lang->error_uploadfailed_php7;
							break;
						default:
							$errors[] = $lang->sprintf($lang->error_uploadfailed_phpx, $_FILES['local_file']['error']);
							break;
					}
				}
				
				if(!$errors)
				{
					// Was the temporary file found?
					if(!is_uploaded_file($_FILES['local_file']['tmp_name']))
					{
						$errors[] = $lang->error_uploadfailed_lost;
					}
					// Get the contents
					$contents = @file_get_contents($_FILES['local_file']['tmp_name']);
					// Delete the temporary file if possible
					@unlink($_FILES['local_file']['tmp_name']);
					// Are there contents?
					if(!trim($contents))
					{
						$errors[] = $lang->error_uploadfailed_nocontents;
					}
				}
			}
			else if(!empty($mybb->input['url']))
			{
				// Get the contents
				$contents = @fetch_remote_file($mybb->input['url']);
				if(!$contents)
				{
					$errors[] = $lang->error_local_file;
				}
			}
			else
			{
				// UPLOAD_ERR_NO_FILE
				$errors[] = $lang->error_uploadfailed_php4;
			}
			
			if(!$errors)
			{
				$options = array(
					'no_stylesheets' => ($mybb->input['import_stylesheets'] ? 0 : 1),
					'no_templates' => ($mybb->input['import_templates'] ? 0 : 1),
					'version_compat' => intval($mybb->input['version_compat']),
					'parent' => intval($mybb->input['tid']),
					'force_name_check' => true,
				);
				$theme_id = import_theme_xml($contents, $options);
				
				if($theme_id > -1)
				{
					// Log admin action
					log_admin_action($theme_id);
			
					flash_message($lang->success_imported_theme, 'success');
					admin_redirect("index.php?module=style/themes&action=edit&tid=".$theme_id);
				}
				else
				{
					switch($theme_id)
					{
						case -1:
							$errors[] = $lang->error_uploadfailed_nocontents;
							break;
						case -2:
							$errors[] = $lang->error_invalid_version;
							break;
						case -3:
							$errors[] = $lang->error_theme_already_exists;
							break;
					}
				}
			}
		}
	}
	
	$query = $db->simple_select("themes", "tid, name");
	while($theme = $db->fetch_array($query))
	{
		$themes[$theme['tid']] = $theme['name'];
	}
	
	$page->add_breadcrumb_item($lang->import_a_theme, "index.php?module=style/themes&amp;action=import");
	
	$page->output_header("{$lang->themes} - {$lang->import_a_theme}");
	
	$page->output_nav_tabs($sub_tabs, 'import_theme');
	
	if($errors)
	{
		$page->output_inline_error($errors);
		
		if($mybb->input['import'] == 1)
		{
			$import_checked[1] = "";
			$import_checked[2] = "checked=\"checked\"";
		}
		else
		{
			$import_checked[1] = "checked=\"checked\"";
			$import_checked[2] = "";
		}
	}
	else
	{
		$import_checked[1] = "checked=\"checked\"";
		$import_checked[2] = "";
		
		$mybb->input['import_stylesheets'] = true;
		$mybb->input['import_templates'] = true;
	}
	
	$form = new Form("index.php?module=style/themes&amp;action=import", "post", "", 1);
	
	$actions = '<script type="text/javascript">
    function checkAction(id)
    {
        var checked = \'\';
		
        $$(\'.\'+id+\'s_check\').each(function(e)
        {
            if(e.checked == true)
            {
                checked = e.value;
            }
        });
        $$(\'.\'+id+\'s\').each(function(e)
        {
        	Element.hide(e);
        });
        if($(id+\'_\'+checked))
        {
            Element.show(id+\'_\'+checked);
        }
    }    
</script>
	<dl style="margin-top: 0; margin-bottom: 0; width: 35%;">
	<dt><label style="display: block;"><input type="radio" name="import" value="0" '.$import_checked[1].' class="imports_check" onclick="checkAction(\'import\');" style="vertical-align: middle;" /> '.$lang->local_file.'</label></dt>
		<dd style="margin-top: 0; margin-bottom: 0; width: 100%;" id="import_0" class="imports">
	<table cellpadding="4">
				<tr>
					<td>'.$form->generate_file_upload_box("local_file", array('style' => 'width: 230px;')).'</td>
				</tr>
		</table>
		</dd>	
		<dt><label style="display: block;"><input type="radio" name="import" value="1" '.$import_checked[2].' class="imports_check" onclick="checkAction(\'import\');" style="vertical-align: middle;" /> '.$lang->url.'</label></dt>
		<dd style="margin-top: 0; margin-bottom: 0; width: 100%;" id="import_1" class="imports">
		<table cellpadding="4">
				<tr>
					<td>'.$form->generate_text_box("url", $mybb->input['file']).'</td>
				</tr>
		</table></dd>
	</dl>
	<script type="text/javascript">
	checkAction(\'import\');
	</script>';
	
	$form_container = new FormContainer($lang->import_a_theme);
	$form_container->output_row($lang->import_from, $lang->import_from_desc, $actions, 'file');
	$form_container->output_row($lang->parent_theme, $lang->parent_theme_desc, $form->generate_select_box('tid', $themes, $mybb->input['tid'], array('id' => 'tid')), 'tid');
	$form_container->output_row($lang->new_name, $lang->new_name_desc, $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->advanced_options, "", $form->generate_check_box('version_compat', '1', $lang->ignore_version_compatibility, array('checked' => $mybb->input['version_compat'], 'id' => 'version_compat'))."<br /><small>{$lang->ignore_version_compat_desc}</small><br />".$form->generate_check_box('import_stylesheets', '1', $lang->import_stylesheets, array('checked' => $mybb->input['import_stylesheets'], 'id' => 'import_stylesheets'))."<br /><small>{$lang->import_stylesheets_desc}</small><br />".$form->generate_check_box('import_templates', '1', $lang->import_templates, array('checked' => $mybb->input['import_templates'], 'id' => 'import_templates'))."<br /><small>{$lang->import_templates_desc}</small>");
	
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button($lang->import_theme);

	$form->output_submit_wrapper($buttons);
	
	$form->end();
	
	$page->output_footer();
}

if($mybb->input['action'] == "export")
{
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$theme['tid'])
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style/themes");
	}

	if($mybb->request_method == "post")
	{
		$properties = unserialize($theme['properties']);
	
		$xml = "<?xml version=\"1.0\" encoding=\"{$lang->settings['charset']}\"?".">\r\n";
		$xml .= "<theme name=\"".htmlspecialchars_uni($theme['name'])."\" version=\"".$mybb->version_code."\">\r\n";
		$xml .= "\t<properties>\r\n";
		foreach($properties as $property => $value)
		{
			if($property == "inherited") continue;
			
			$xml .= "\t\t<{$property}><![CDATA[{$value}]]></{$property}>\r\n";
		}
		$xml .= "\t</properties>\r\n";
		
		// Fetch list of all of the stylesheets for this theme
		$file_stylesheets = unserialize($theme['stylesheets']);
		
		$stylesheets = array();
		$inherited_load = array();
		
		// Now we loop through the list of stylesheets for each file
		foreach($file_stylesheets as $file => $action_stylesheet)
		{
			if($file == 'inherited')
			{
				continue;
			}
			
			foreach($action_stylesheet as $action => $style)
			{
				foreach($style as $stylesheet)
				{
					$stylesheets[$stylesheet]['applied_to'][$file][] = $action;
					if(is_array($file_stylesheets['inherited'][$file."_".$action]) && in_array($stylesheet, array_keys($file_stylesheets['inherited'][$file."_".$action])))
					{
						$stylesheets[$stylesheet]['inherited'] = $file_stylesheets['inherited'][$file."_".$action];
						foreach($file_stylesheets['inherited'][$file."_".$action] as $value)
						{
							$inherited_load[] = $value;
						}
					}
				}
			}
		}
		
		$inherited_load[] = $mybb->input['tid'];
		$inherited_load = array_unique($inherited_load);
		
		$inherited_themes = array();
		if(count($inherited_load) > 0)
		{
			$query = $db->simple_select("themes", "tid, name", "tid IN (".implode(",", $inherited_load).")");
			while($inherited_theme = $db->fetch_array($query))
			{
				$inherited_themes[$inherited_theme['tid']] = $inherited_theme['name'];
			}
		}
		
		$theme_stylesheets = array();
		
		if(count($inherited_load) > 0)
		{
			$query = $db->simple_select("themestylesheets", "*", "tid IN (".implode(",", $inherited_load).")", array('order_by' => 'tid', 'order_dir' => 'desc'));
			while($theme_stylesheet = $db->fetch_array($query))
			{
				if(!$theme_stylesheets[$theme_stylesheet['cachefile']])
				{
					$theme_stylesheets[$theme_stylesheet['cachefile']] = $theme_stylesheet;
					$theme_stylesheets[$theme_stylesheet['sid']] = $theme_stylesheet['cachefile'];
				}
			}
		}
		
		$xml .= "\t<stylesheets>\r\n";
		foreach($stylesheets as $filename => $style)
		{
			if(strpos($filename, 'css.php?stylesheet=') !== false)
			{
				$style['sid'] = (integer)str_replace('css.php?stylesheet=', '', $filename);
				$filename = $theme_stylesheets[$style['sid']];
			}
			else
			{
				$filename = basename($filename);
				$style['sid'] = $theme_stylesheets[$filename]['sid'];
			}
			
			$style['tid'] = $theme_stylesheets[$filename]['tid'];
			
			if($mybb->input['custom_theme'] == 1 && $style['tid'] != $mybb->input['tid'])
			{
				continue;
			}
			
			// Has the file on the file system been modified?
			resync_stylesheet($theme_stylesheets[$filename]);
			
			$style['sid'] = $theme_stylesheets[$filename]['sid'];
			
			$attachedto = $theme_stylesheets[$filename]['attachedto'];
			$stylesheet = $theme_stylesheets[$filename]['stylesheet'];
	
			if($attachedto)
			{
				$attachedto = "attachedto=\"{$attachedto}\" ";
			}
			
			$filename = $theme_stylesheets[$filename]['name'];
			
			$xml .= "\t\t<stylesheet name=\"{$filename}\" {$attachedto}version=\"{$mybb->version_code}\"><![CDATA[{$stylesheet}]]>\r\n\t\t</stylesheet>\r\n";
		
		}
		$xml .= "\t</stylesheets>\r\n";
		
		if($mybb->input['include_templates'] != 0)
		{
			$xml .= "\t<templates>\r\n";
			$query = $db->simple_select("templates", "*", "sid='".$properties['templateset']."'");
			while($template = $db->fetch_array($query))
			{
				$xml .= "\t\t<template name=\"{$template['title']}\" version=\"{$template['version']}\"><![CDATA[{$template['template']}]]></template>\r\n";
			}
			$xml .= "\t</templates>\r\n";
		}
		$xml .= "</theme>";
		
		// Log admin action
		log_admin_action($theme['tid'], $theme['name']);
		
		$theme['name'] = rawurlencode($theme['name']);
		header("Content-disposition: attachment; filename=".$theme['name']."-theme.xml");
		header("Content-type: application/octet-stream");
		header("Content-Length: ".strlen($xml));
		header("Pragma: no-cache");
		header("Expires: 0");
		echo $xml;
		exit;
	}
	
	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), "index.php?module=style/themes&amp;action=edit&amp;tid={$mybb->input['tid']}");
		
	$page->add_breadcrumb_item($lang->export_theme, "index.php?module=style/themes&amp;action=export");
	
	$page->output_header("{$lang->themes} - {$lang->export_theme}");
	
	$sub_tabs['edit_stylesheets'] = array(
		'title' => $lang->edit_stylesheets,
		'link' => "index.php?module=style/themes&amp;action=edit&amp;tid={$mybb->input['tid']}",
	);

	$sub_tabs['add_stylesheet'] = array(
		'title' => $lang->add_stylesheet,
		'link' => "index.php?module=style/themes&amp;action=add_stylesheet&amp;tid={$mybb->input['tid']}",
	);
	
	$sub_tabs['export_theme'] = array(
		'title' => $lang->export_theme,
		'link' => "index.php?module=style/themes&amp;action=export&amp;tid={$mybb->input['tid']}",
		'description' => $lang->export_theme_desc
	);
	
	$page->output_nav_tabs($sub_tabs, 'export_theme');
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	
	$form = new Form("index.php?module=style/themes&amp;action=export", "post");
	echo $form->generate_hidden_field("tid", $theme['tid']);
	
	$form_container = new FormContainer($lang->export_theme.": ".htmlspecialchars_uni($theme['name']));
	$form_container->output_row($lang->include_custom_only, $lang->include_custom_only_desc, $form->generate_yes_no_radio('custom_theme', $mybb->input['custom_theme']), 'custom_theme');
	$form_container->output_row($lang->include_templates, $lang->include_templates_desc, $form->generate_yes_no_radio('include_templates', $mybb->input['include_templates']), 'include_templates');
	
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button($lang->export_theme);

	$form->output_submit_wrapper($buttons);
	
	$form->end();
	
	$page->output_footer();
}

if($mybb->input['action'] == "add")
{
	if($mybb->request_method == "post")
	{
		if(!$mybb->input['name'])
		{
			$errors[] = $lang->error_missing_name;
		}
		
		if(!$errors)
		{
			$tid = build_new_theme($mybb->input['name'], null, $mybb->input['tid']);
			
			// Log admin action
			log_admin_action($mybb->input['name'], $tid);
			
			flash_message($lang->success_theme_created, 'success');
			admin_redirect("index.php?module=style/themes&action=edit&tid=".$tid);
		}
	}
	
	$query = $db->simple_select("themes", "tid, name");
	while($theme = $db->fetch_array($query))
	{
		$themes[$theme['tid']] = $theme['name'];
	}
	
	$page->add_breadcrumb_item($lang->create_new_theme, "index.php?module=style/themes&amp;action=add");
	
	$page->output_header("{$lang->themes} - {$lang->create_new_theme}");
	
	$page->output_nav_tabs($sub_tabs, 'create_theme');
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	
	$form = new Form("index.php?module=style/themes&amp;action=add", "post");
	
	$form_container = new FormContainer($lang->create_a_theme);
	$form_container->output_row($lang->name, $lang->name_desc, $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->parent_theme, $lang->parent_theme_desc, $form->generate_select_box('tid', $themes, $mybb->input['tid'], array('id' => 'tid')), 'tid');
	
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button($lang->create_new_theme);

	$form->output_submit_wrapper($buttons);
	
	$form->end();
	
	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);

	// Does the theme not exist? or are we trying to delete the master?
	if(!$theme['tid'] || $theme['tid'] == 1)
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style/themes");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=style/themes");
	}

	if($mybb->request_method == "post")
	{		
		$inherited_theme_cache = array();
		
		$query = $db->simple_select("themes", "tid,stylesheets", "tid != '{$theme['tid']}'", array('order_by' => "pid, name"));
		while($theme2 = $db->fetch_array($query))
		{
			$theme2['stylesheets'] = unserialize($theme2['stylesheets']);
			
			if(!$theme2['stylesheets']['inherited'])
			{
				continue;
			}
			
			$inherited_theme_cache[$theme2['tid']] = $theme2['stylesheets']['inherited'];
		}
		
		$inherited_stylesheets = false;
		
		// Are any other themes relying on stylesheets from this theme? Get a list and show an error
		foreach($inherited_theme_cache as $tid => $inherited)
		{
			foreach($inherited as $file => $value)
			{
				foreach($value as $filepath => $val)
				{
					if(strpos($filepath, "cache/themes/theme{$theme['tid']}") !== false)
					{
						$inherited_stylesheets = true;
					}
				}
			}
		}
		
		if($inherited_stylesheets == true)
		{			
			flash_message($lang->error_inheriting_stylesheets, 'error');
			admin_redirect("index.php?module=style/themes");
		}
		
		$query = $db->simple_select("themestylesheets", "cachefile", "tid='{$theme['tid']}'");
		while($cachefile = $db->fetch_array($query))
		{
			@unlink(MYBB_ROOT."cache/themes/theme{$theme['tid']}/{$cachefile['cachefile']}");
		}
		@unlink(MYBB_ROOT."cache/themes/theme{$theme['tid']}/index.html");
		
		$db->delete_query("themestylesheets", "tid='{$theme['tid']}'");
		
		// Update the CSS file list for this theme
		update_theme_stylesheet_list($theme['tid']);
		
		$db->update_query("users", array('style' => 0), "style='{$theme['tid']}'");
		
		@rmdir(MYBB_ROOT."cache/themes/theme{$theme['tid']}/");
		
		$children = make_child_theme_list($theme['tid']);
		$child_tid = $children[0];
			
		$db->update_query("themes", array('pid' => $theme['pid']), "tid='{$child_tid}'");
				
		$db->delete_query("themes", "tid='{$theme['tid']}'", 1);
		
		// Log admin action
		log_admin_action($theme['tid'], $theme['name']);
		
		flash_message($lang->success_theme_deleted, 'success');
		admin_redirect("index.php?module=style/themes");
	}
	else
	{		
		$page->output_confirm_action("index.php?module=style/themes&amp;action=delete&amp;tid={$theme['tid']}", $lang->confirm_theme_deletion);
	}
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);
	
	// Does the theme not exist?
	if(!$theme['tid'])
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	if($mybb->request_method == "post")
	{
		$properties = array(
			'templateset' => intval($mybb->input['templateset']),
			'editortheme' => $mybb->input['editortheme'],
			'imgdir' => $mybb->input['imgdir'],
			'logo' => $mybb->input['logo'],
			'tablespace' => intval($mybb->input['tablespace']),
			'borderwidth' => intval($mybb->input['borderwidth'])
		);
		
		if($properties['templateset'] <= 0)
		{
			$errors[] = $lang->error_invalid_templateset;
		}
		
		$allowedgroups = array();
		if(is_array($mybb->input['allowedgroups']))
		{
			foreach($mybb->input['allowedgroups'] as $gid)
			{
				if($gid == "all")
				{
					$allowedgroups = "all";
					break;
				}
				$gid = intval($gid);
				$allowedgroups[$gid] = $gid;
			}
		}
		if(is_array($allowedgroups))
		{
			$allowedgroups = implode(",", $allowedgroups);
		}
		
		$update_array = array(
			'name' => $db->escape_string($mybb->input['name']),
			'pid' => intval($mybb->input['pid']),
			'allowedgroups' => $allowedgroups,
			'properties' => $db->escape_string(serialize($properties))
		);
		
		// perform validation
		if(!$update_array['name'])
		{
			$errors[] = $lang->error_missing_name;
		}
		
		if($update_array['pid'])
		{
			$parent_check = $db->fetch_field($db->simple_select("themes", "tid", "tid='".$update_array['pid']."'"), "tid");
			if(!$parent_check)
			{
				$errors[] = $lang->error_invalid_parent_theme;
			}
		}
		if($properties['templateset'])
		{
			$ts_check = $db->fetch_field($db->simple_select("templatesets", "sid", "sid='".$properties['templateset']."'"), "sid");
			if(!$ts_check)
			{
				unset($properties['templateset']);
			}
		}
		if(!$properties['templateset'])
		{
			$errors[] = $lang->error_invalid_templateset;
		}
		if(!$properties['editortheme'] || !@is_dir(MYBB_ROOT."jscripts/editor_themes/".$properties['editortheme']))
		{
			$errors[] = $lang->error_invalid_editortheme;
		}
		
		if(empty($errors))
		{
			$db->update_query("themes", $update_array, "tid='{$theme['tid']}'");
			
			// Log admin action
			log_admin_action($theme['tid'], $theme['name']);
			
			flash_message($lang->success_theme_properties_updated, 'success');
			admin_redirect("index.php?module=style/themes&action=edit&tid={$theme['tid']}");
		}
	}
		
	// Fetch list of all of the stylesheets for this theme
	$file_stylesheets = unserialize($theme['stylesheets']);
	
	$stylesheets = array();
	$inherited_load = array();
	
	// Now we loop through the list of stylesheets for each file
	foreach($file_stylesheets as $file => $action_stylesheet)
	{
		if($file == 'inherited')
		{
			continue;
		}
		
		foreach($action_stylesheet as $action => $style)
		{
			foreach($style as $stylesheet)
			{
				$stylesheets[$stylesheet]['applied_to'][$file][] = $action;
				if(is_array($file_stylesheets['inherited'][$file."_".$action]) && in_array($stylesheet, array_keys($file_stylesheets['inherited'][$file."_".$action])))
				{
					$stylesheets[$stylesheet]['inherited'] = $file_stylesheets['inherited'][$file."_".$action];
					foreach($file_stylesheets['inherited'][$file."_".$action] as $value)
					{
						$inherited_load[] = $value;
					}
				}
			}
		}
	}
	
	
	$inherited_load[] = $mybb->input['tid'];
	$inherited_load = array_unique($inherited_load);
	
	$inherited_themes = array();
	if(count($inherited_load) > 0)
	{
		$query = $db->simple_select("themes", "tid, name", "tid IN (".implode(",", $inherited_load).")");
		while($inherited_theme = $db->fetch_array($query))
		{
			$inherited_themes[$inherited_theme['tid']] = $inherited_theme['name'];
		}
	}
	
	$theme_stylesheets = array();
	
	if(count($inherited_load) > 0)
	{
		$query = $db->simple_select("themestylesheets", "*", "", array('order_by' => 'sid DESC, tid', 'order_dir' => 'desc'));
		while($theme_stylesheet = $db->fetch_array($query))
		{
			if(!$theme_stylesheets[$theme_stylesheet['cachefile']] && in_array($theme_stylesheet['tid'], $inherited_load))
			{
				$theme_stylesheets[$theme_stylesheet['cachefile']] = $theme_stylesheet;
			}
			
			$theme_stylesheets[$theme_stylesheet['sid']] = $theme_stylesheet['cachefile'];
		}
	}
	
	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), "index.php?module=style/themes&amp;action=edit&amp;tid={$mybb->input['tid']}");
	
	$page->output_header("{$lang->themes} - {$lang->stylesheets}");
	
	$sub_tabs['edit_stylesheets'] = array(
		'title' => $lang->edit_stylesheets,
		'link' => "index.php?module=style/themes&amp;action=edit&amp;tid={$mybb->input['tid']}",
		'description' => $lang->edit_stylesheets_desc
	);

	$sub_tabs['add_stylesheet'] = array(
		'title' => $lang->add_stylesheet,
		'link' => "index.php?module=style/themes&amp;action=add_stylesheet&amp;tid={$mybb->input['tid']}",
	);
	
	$sub_tabs['export_theme'] = array(
		'title' => $lang->export_theme,
		'link' => "index.php?module=style/themes&amp;action=export&amp;tid={$mybb->input['tid']}"
	);
	
	$page->output_nav_tabs($sub_tabs, 'edit_stylesheets');
	
	$table = new Table;
	$table->construct_header($lang->stylesheets);
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));
	
	foreach($stylesheets as $filename => $style)
	{
		if(strpos($filename, 'css.php?stylesheet=') !== false)
		{
			$style['sid'] = (integer)str_replace('css.php?stylesheet=', '', $filename);
			$filename = $theme_stylesheets[$style['sid']];
		}
		else
		{
			$filename = basename($filename);
			$style['sid'] = $theme_stylesheets[$filename]['sid'];
		}
		
		// Has the file on the file system been modified?
		resync_stylesheet($theme_stylesheets[$filename]);
		
		$filename = $theme_stylesheets[$filename]['name'];
		
		$inherited = "";
		$inherited_ary = array();
		if(is_array($style['inherited']))
		{
			foreach($style['inherited'] as $tid)
			{
				if($inherited_themes[$tid])
				{
					$inherited_ary[$tid] = $inherited_themes[$tid];
				}
			}
		}
		
		if(!empty($inherited_ary))
		{
			$inherited = " <small>({$lang->inherited_from}";
			$sep = " ";
			$inherited_count = count($inherited_ary);
			$count = 0;
			
			foreach($inherited_ary as $tid => $file)
			{
				if($count == $applied_to_count && $count != 0)
				{
					$sep = ", {$lang->and} ";
				}
				
				$inherited .= $sep.$file;
				$sep = ", ";
				
				++$count;
			}
			$inherited .= ")</small>";
		}
		
		if(is_array($style['applied_to']) && $style['applied_to']['global'][0] != "global")
		{
			$attached_to = "<small>{$lang->attached_to}";
			
			$applied_to_count = count($style['applied_to']);
			$count = 0;
			$sep = " ";
			$name = "";
			foreach($style['applied_to'] as $name => $actions)
			{
				if(!$name)
				{
					continue;
				}
				
				++$count;
				
				if($actions[0] != "global")
				{
					$name = "{$name} ({$lang->actions}: ".implode(',', $actions).")";
				}
				
				if($count == $applied_to_count && $count > 1)
				{
					$sep = ", {$lang->and} ";
				}
				$attached_to .= $sep.$name;
				
				$sep = ", ";
			}
			
			$attached_to .= "</small>";
		}
		else
		{
			$attached_to = "<small>{$lang->attached_to_all_pages}</small>";
		}
		
		$popup = new PopupMenu("style_{$style['sid']}", $lang->options);
		
		$popup->add_item($lang->edit_style, "index.php?module=style/themes&amp;action=edit_stylesheet&amp;file=".htmlspecialchars_uni($filename)."&amp;tid={$theme['tid']}");
		$popup->add_item($lang->properties, "index.php?module=style/themes&amp;action=stylesheet_properties&amp;file=".htmlspecialchars_uni($filename)."&amp;tid={$theme['tid']}");
		
		if($inherited == "")
		{
			$popup->add_item($lang->delete_revert, "index.php?module=style/themes&amp;action=delete_stylesheet&amp;file=".htmlspecialchars_uni($filename)."&amp;tid={$theme['tid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_stylesheet_deletion}')");
		}
		
		$table->construct_cell("<strong><a href=\"index.php?module=style/themes&amp;action=edit_stylesheet&amp;file=".htmlspecialchars_uni($filename)."&amp;tid={$theme['tid']}\">{$filename}</a></strong>{$inherited}<br />{$attached_to}");
		$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		$table->construct_row();
	}
	
	$table->output("{$lang->stylesheets_in} ".htmlspecialchars_uni($theme['name']));
	
	// Theme Properties table
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	
	$properties = unserialize($theme['properties']);
	$form = new Form("index.php?module=style/themes&amp;action=edit", "post", "edit");
	echo $form->generate_hidden_field("tid", $theme['tid']);
	$form_container = new FormContainer($lang->edit_theme_properties);
	$form_container->output_row($lang->name." <em>*</em>", $lang->name_desc_edit, $form->generate_text_box('name', $theme['name'], array('id' => 'name')), 'name');
	
	$options = build_theme_array($theme['tid']);
	$form_container->output_row($lang->parent_theme." <em>*</em>", $lang->parent_theme_desc, $form->generate_select_box('pid', $options, $theme['pid'], array('id' => 'pid')), 'pid');
	
	$options = array();
	$query = $db->simple_select("usergroups", "gid, title", "gid != '1'", array('order_by' => 'title'));
	$options['all'] = $lang->all_user_groups;
	while($usergroup = $db->fetch_array($query))
	{
		$options[(int)$usergroup['gid']] = $usergroup['title'];
	}
	if(!$theme['allowedgroups'])
	{
		$theme['allowedgroups'] = "all";
	}
	$form_container->output_row($lang->allowed_user_groups, $lang->allowed_user_groups_desc, $form->generate_select_box('allowedgroups[]', $options, explode(",", $theme['allowedgroups']), array('id' => 'allowedgroups', 'multiple' => true, 'size' => 5)), 'allowedgroups');
	
	$options = array();
	$query = $db->simple_select("templatesets", "*", "", array('order_by' => 'title'));
	while($templateset = $db->fetch_array($query))
	{
		$options[intval($templateset['sid'])] = $templateset['title'];
	}
	$form_container->output_row($lang->template_set." <em>*</em>", $lang->template_set_desc, $form->generate_select_box('templateset', $options, $properties['templateset'], array('id' => 'templateset')), 'templateset');
	
	$options = array();
	$editor_theme_root = MYBB_ROOT."jscripts/editor_themes/";
	if($dh = @opendir($editor_theme_root))
	{
		while($dir = readdir($dh))
		{
			if($dir == ".svn" || $dir == "." || $dir == ".." || !is_dir($editor_theme_root.$dir))
			{
				continue;
			}
			$options[$dir] = ucfirst(str_replace('_', ' ', $dir));
		}
	}
	
	$form_container->output_row($lang->editor_theme." <em>*</em>", $lang->editor_theme_desc, $form->generate_select_box('editortheme', $options, $properties['editortheme'], array('id' => 'editortheme')), 'editortheme');
	
	$form_container->output_row($lang->img_directory, $lang->img_directory_desc, $form->generate_text_box('imgdir', $properties['imgdir'], array('id' => 'imgdir')), 'imgdir');
	$form_container->output_row($lang->logo, $lang->logo_desc, $form->generate_text_box('logo', $properties['logo'], array('id' => 'boardlogo')), 'logo');
	$form_container->output_row($lang->table_spacing, $lang->table_spacing_desc, $form->generate_text_box('tablespace', $properties['tablespace'], array('id' => 'tablespace')), 'tablespace');
	$form_container->output_row($lang->inner_border, $lang->inner_border_desc, $form->generate_text_box('borderwidth', $properties['borderwidth'], array('id' => 'borderwidth')), 'borderwidth');
	
	$form_container->end();
	$buttons = array();
	$buttons[] = $form->generate_submit_button($lang->save_theme_properties);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();
} 

if($mybb->input['action'] == "stylesheet_properties")
{
	// Fetch the theme we want to edit this stylesheet in
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);
	
	if(!$theme['tid'])
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	$parent_list = make_parent_theme_list($theme['tid']);
	$parent_list = implode(',', $parent_list);
	if(!$parent_list)
	{
		$parent_list = 1;
	}
	
	$query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string($mybb->input['file'])."' AND tid IN ({$parent_list})", array('order_by' => 'tid', 'order_dir' => 'desc', 'limit' => 1));
	$stylesheet = $db->fetch_array($query);
	
	// Does the theme not exist?
	if(!$stylesheet['sid'])
	{
		flash_message($lang->error_invalid_stylesheet, 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	// Fetch list of all of the stylesheets for this theme
	$file_stylesheets = unserialize($theme['stylesheets']);
	
	$stylesheets = array();
	$inherited_load = array();
	
	// Now we loop through the list of stylesheets for each file
	foreach($file_stylesheets as $file => $action_stylesheet)
	{
		if($file == 'inherited')
		{
			continue;
		}
		
		foreach($action_stylesheet as $action => $style)
		{
			foreach($style as $stylesheet2)
			{
				$stylesheets[$stylesheet2]['applied_to'][$file][] = $action;
				if(is_array($file_stylesheets['inherited'][$file."_".$action]) && in_array($stylesheet2, array_keys($file_stylesheets['inherited'][$file."_".$action])))
				{
					$stylesheets[$stylesheet2]['inherited'] = $file_stylesheets['inherited'][$file."_".$action];
					foreach($file_stylesheets['inherited'][$file."_".$action] as $value)
					{
						$inherited_load[] = $value;
					}
				}
			}
		}
	}
	
	foreach($stylesheets as $file => $stylesheet2)
	{
		if(is_array($stylesheet2['inherited']))
		{
			foreach($stylesheet2['inherited'] as $inherited_file => $tid)
			{
				$stylesheet2['inherited'][basename($inherited_file)] = $tid;
				unset($stylesheet2['inherited'][$inherited_file]);
			}
		}
		
		unset($stylesheets[$file]);
		$stylesheets[basename($file)] = $stylesheet2;
	}
	
	if(!array_key_exists($stylesheet['cachefile'], $stylesheets) && array_key_exists("css.php?stylesheet=".$stylesheet['tid'], $stylesheets))
	{
		$stylesheet['cachefile'] = "css.php?stylesheet=".$stylesheet['tid'];
	}
	
	$this_stylesheet = $stylesheets[$stylesheet['cachefile']];
	unset($stylesheets);
	
	if($mybb->request_method == "post")
	{
		if(!$mybb->input['name'])
		{
			$errors[] = $lang->error_missing_stylesheet_name;
		}
		
		if(!$errors)
		{
			// Theme & stylesheet theme ID do not match, editing inherited - we copy to local theme
			if($theme['tid'] != $stylesheet['tid'])
			{
				$stylesheet['sid'] = copy_stylesheet_to_theme($stylesheet, $theme['tid']);
			}
			
			$attached = array();
			
			if($mybb->input['attach'] == 1)
			{
				// Our stylesheet is attached to custom pages in MyBB
				foreach($mybb->input as $id => $value)
				{
					$actions_list = "";
					$attached_to = $value;
					
					if(strpos($id, 'attached_') !== false)
					{
						// We have a custom attached file				
						$attached_id = intval(str_replace('attached_', '', $id));
						
						if($mybb->input['action_'.$attached_id] == 1)
						{
							// We have custom actions for attached files							
							$actions_list = $mybb->input['action_list_'.$attached_id];
						}
						
						if($actions_list)
						{
							$attached_to .= "?".$actions_list;
						}
						
						$attached[] = $attached_to;
					}
				}
			}
			
			// Update Stylesheet			
			$update_array = array(
				'name' => $db->escape_string($mybb->input['name']),
				'attachedto' => $db->escape_string(implode('|', $attached))
			);
			
			if($stylesheet['name'] != $mybb->input['name'])
			{
				$update_array['cachefile'] = $db->escape_string(str_replace('/', '', $mybb->input['name']));
			}
			
			$db->update_query("themestylesheets", $update_array, "sid='{$stylesheet['sid']}'", 1);
			
			// If the name changed, re-cache our stylesheet
			if($stylesheet['name'] != $mybb->input['name'])
			{
				$db->update_query("themestylesheets", array('lastmodified' => TIME_NOW), "sid='{$stylesheet['sid']}'", 1);
				if(!cache_stylesheet($theme['tid'], str_replace('/', '', $mybb->input['name']), $theme['stylesheet']))
				{
					$db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet={$stylesheet['sid']}"), "sid='{$stylesheet['sid']}'", 1);
				}
				@unlink(MYBB_ROOT."cache/themes/theme{$theme['tid']}/{$stylesheet['cachefile']}");
			}
			
			// Update the CSS file list for this theme
			update_theme_stylesheet_list($theme['tid']);
			
			// Log admin action
			log_admin_action($stylesheet['sid'], $mybb->input['name'], $theme['tid'], $theme['name']);
		
			flash_message($lang->success_stylesheet_properties_updated, 'success');
			admin_redirect("index.php?module=style/themes&action=stylesheet_properties&tid={$theme['tid']}&file=".htmlspecialchars_uni($mybb->input['file']));
		}
	}
	
	
	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), "index.php?module=style/themes&amp;action=edit&amp;tid={$mybb->input['tid']}");
	$page->add_breadcrumb_item(htmlspecialchars_uni($stylesheet['name'])." {$lang->properties}", "index.php?module=style/themes&amp;action=edit_properties&amp;tid={$mybb->input['tid']}");
	
	$page->output_header("{$lang->themes} - {$lang->stylesheet_properties}");

	// If the stylesheet and theme do not match, we must be editing something that is inherited
	if($this_stylesheet['inherited'][$stylesheet['name']])
	{
		$query = $db->simple_select("themes", "name", "tid='{$stylesheet['tid']}'");
		$stylesheet_parent = htmlspecialchars_uni($db->fetch_field($query, 'name'));
		
		// Show inherited warning
		if($stylesheet['tid'] == 1)
		{
			$page->output_alert($lang->sprintf($lang->stylesheet_inherited_default, $stylesheet_parent));
		}
		else
		{
			$page->output_alert($lang->sprintf($lang->stylesheet_inherited, $stylesheet_parent));
		}
	}
	
	$applied_to = $this_stylesheet['applied_to'];
	unset($this_stylesheet);
	
	if($errors)
	{
		$page->output_inline_error($errors);
		
		foreach($mybb->input as $name => $value)
		{
			if(strpos($name, "attached") !== false)
			{
				list(, $id) = explode('_', $name);
				$id = intval($id);
				
				$applied_to[$value] = array(0 => 'global');
				
				if($mybb->input['action_'.$id] == 1)
				{
					$applied_to[$value] = explode(',', $mybb->input['action_list_'.$id]);
				}
			}
		}
	}
	else
	{
		$mybb->input['name'] = $stylesheet['name'];
	}
	
	$form = new Form("index.php?module=style/themes&amp;action=stylesheet_properties", "post");
	
	$specific_files = "<div id=\"attach_1\" class=\"attachs\">";
	$count = 0;
	
	if(is_array($applied_to) && $applied_to['global'][0] != "global")
	{
		$check_actions = "";
		
		$global_checked[2] = "checked=\"checked\"";
		$global_checked[1] = "";
		
		foreach($applied_to as $name => $actions)
		{
			$short_name = substr($name, 0, -4);			

			$action_list = "";
			if($actions[0] != "global")
			{
				$action_list = implode(',', $actions);
			}
			
			if($actions[0] == "global")
			{
				$global_action_checked[1] = "checked=\"checked\"";
				$global_action_checked[2] = "";
			}
			else
			{
				$global_action_checked[2] = "checked=\"checked\"";
				$global_action_checked[1] = "";
			}
			
			$specific_file = "<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_{$count}\" value=\"0\" {$global_action_checked[1]} class=\"action_{$count}s_check\" onclick=\"checkAction('action_{$count}');\" style=\"vertical-align: middle;\" /> {$lang->globally}</label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_{$count}\" value=\"1\" {$global_action_checked[2]} class=\"action_{$count}s_check\" onclick=\"checkAction('action_{$count}');\" style=\"vertical-align: middle;\" /> {$lang->specific_actions}</label></dt>
			<dd style=\"margin-top: 4px;\" id=\"action_{$count}_1\" class=\"action_{$count}s\">
			<small class=\"description\">{$lang->specific_actions_desc}</small>
			<table cellpadding=\"4\">
				<tr>
					<td>".$form->generate_text_box('action_list_'.$count, $action_list, array('id' => 'action_list_'.$count, 'style' => 'width: 190px;'))."</td>
				</tr>
			</table>
		</dd>
		</dl>";
			
			$form_container = new FormContainer();
			$form_container->output_row("", "", "<span style=\"float: right;\"><a href=\"\" id=\"delete_img_{$count}\"><img src=\"styles/{$page->style}/images/icons/cross.gif\" alt=\"{$lang->delete}\" title=\"{$lang->delete}\" /></a></span>{$lang->file} &nbsp;".$form->generate_text_box("attached_{$count}", $name, array('id' => "attached_{$count}", 'style' => 'width: 200px;')), "attached_{$count}");
	
			$form_container->output_row("", "", $specific_file);
	
			$specific_files .= "<div id=\"attached_form_{$count}\">".$form_container->end(true)."</div><div id=\"attach_box_".($count+1)."\"></div>";
			
			$check_actions .= "\n\tcheckAction('action_{$count}');";
			
			++$count;
		}
	}
	else
	{
		$global_checked[1] = "checked=\"checked\"";
		$global_checked[2] = "";
	}
	
	$specific_files .= "</div>";
	
	$actions = '<script type="text/javascript">
    function checkAction(id)
    {
        var checked = \'\';
		
        $$(\'.\'+id+\'s_check\').each(function(e)
        {
            if(e.checked == true)
            {
                checked = e.value;
            }
        });
        $$(\'.\'+id+\'s\').each(function(e)
        {
        	Element.hide(e);
        });
        if($(id+\'_\'+checked))
        {
            Element.show(id+\'_\'+checked);
        }
    }    
</script>
	<dl style="margin-top: 0; margin-bottom: 0; width: 40%;">
	<dt><label style="display: block;"><input type="radio" name="attach" value="0" '.$global_checked[1].' class="attachs_check" onclick="checkAction(\'attach\');" style="vertical-align: middle;" /> '.$lang->globally.'</label></dt>
		<dt><label style="display: block;"><input type="radio" name="attach" value="1" '.$global_checked[2].' class="attachs_check" onclick="checkAction(\'attach\');" style="vertical-align: middle;" /> '.$lang->specific_files.' (<a id="new_specific_file">'.$lang->add_another.'</a>)</label></dt><br />
		'.$specific_files.'
	</dl>
	<script type="text/javascript">
	checkAction(\'attach\');'.$check_actions.'
	</script>';
	
	echo $form->generate_hidden_field("file", htmlspecialchars_uni($stylesheet['name']))."<br />\n";
	echo $form->generate_hidden_field("tid", $theme['tid'])."<br />\n";

	$form_container = new FormContainer("{$lang->edit_stylesheet_properties_for} ".htmlspecialchars_uni($stylesheet['name']));
	$form_container->output_row($lang->file_name, $lang->file_name_desc, $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name', 'style' => 'width: 200px;')), 'name');
	
	$form_container->output_row($lang->attached_to, $lang->attached_to_desc, $actions);
	
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button($lang->save_stylesheet_properties);

	$form->output_submit_wrapper($buttons);
	
	echo '<script type="text/javascript" src="./jscripts/themes.js"></script>';
	echo '<script type="text/javascript">

Event.observe(window, "load", function() {
//<![CDATA[
    new ThemeSelector(\''.$count.'\');
});
//]]>
</script>';
	
	$form->end();
	
	$page->output_footer();
}

// Shows the page where you can actually edit a particular selector or the whole stylesheet
if($mybb->input['action'] == "edit_stylesheet" && (!$mybb->input['mode'] || $mybb->input['mode'] == "simple"))
{
	// Fetch the theme we want to edit this stylesheet in
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);
	
	if(!$theme['tid'])
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	$parent_list = make_parent_theme_list($theme['tid']);
	$parent_list = implode(',', $parent_list);
	if(!$parent_list)
	{
		$parent_list = 1;
	}
	
	$query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string($mybb->input['file'])."' AND tid IN ({$parent_list})", array('order_by' => 'tid', 'order_dir' => 'desc', 'limit' => 1));
	$stylesheet = $db->fetch_array($query);
	
	// Does the theme not exist?
	if(!$stylesheet['sid'])
	{
		flash_message($lang->error_invalid_stylesheet, 'error');
		admin_redirect("index.php?module=style/themes");
	}

	if($mybb->request_method == "post")
	{
		$sid = $stylesheet['sid'];
		
		// Theme & stylesheet theme ID do not match, editing inherited - we copy to local theme
		if($theme['tid'] != $stylesheet['tid'])
		{
			$sid = copy_stylesheet_to_theme($stylesheet, $theme['tid']);
		}

		// Insert the modified CSS
		$new_stylesheet = $stylesheet['stylesheet'];
		
		if($mybb->input['serialized'] == 1)
		{
			$mybb->input['css_bits'] = unserialize($mybb->input['css_bits']);
		}

		$css_to_insert = '';
		foreach($mybb->input['css_bits'] as $field => $value)
		{
			if(!trim($value) || !trim($field))
			{
				continue;
			}
			
			if($field == "extra")
			{
				$css_to_insert .= $value."\n";
			}
			else
			{
				$field = str_replace("_", "-", $field);
				$css_to_insert .= "{$field}: {$value};\n";
			}
		}
		
		$new_stylesheet = insert_into_css($css_to_insert, $mybb->input['selector'], $new_stylesheet);

		// Now we have the new stylesheet, save it
		$updated_stylesheet = array(
			"cachefile" => $db->escape_string($stylesheet['name']),
			"stylesheet" => $db->escape_string(unfix_css_urls($new_stylesheet)),
			"lastmodified" => TIME_NOW
		);
		$db->update_query("themestylesheets", $updated_stylesheet, "sid='{$sid}'");

		// Cache the stylesheet to the file		
		if(!cache_stylesheet($theme['tid'], $stylesheet['name'], $new_stylesheet))
		{
			$db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet={$sid}"), "sid='{$sid}'", 1);
		}

		// Update the CSS file list for this theme
		update_theme_stylesheet_list($theme['tid']);

		// Log admin action
		log_admin_action($theme['name'], $stylesheet['name']);

		if(!$mybb->input['ajax'])
		{			
			flash_message($lang->success_stylesheet_updated, 'success');
				
			if($mybb->input['save_close'])
			{
				admin_redirect("index.php?module=style/themes&action=edit&tid={$theme['tid']}");
			}
			else
			{
				admin_redirect("index.php?module=style/themes&action=edit_stylesheet&tid={$theme['tid']}&file={$stylesheet['name']}");
			}
		}
		else
		{
			echo "1";
			exit;
		}
	}
	
	// Has the file on the file system been modified?
	if(resync_stylesheet($stylesheet))
	{
		// Need to refetch new stylesheet as it was modified
		$query = $db->simple_select("themestylesheets", "stylesheet", "sid='{$stylesheet['sid']}'");
		$stylesheet['stylesheet'] = $db->fetch_field($query, 'stylesheet');
	}
	
	$css_array = css_to_array($stylesheet['stylesheet']);
	$selector_list = get_selectors_as_options($css_array, $mybb->input['selector']);
	
	// Do we not have any selectors? Send em to the full edit page
	if(!$selector_list)
	{
		flash_message($lang->error_cannot_parse, 'error');
		admin_redirect("index.php?module=style/themes&action=edit_stylesheet&tid={$theme['tid']}&file=".htmlspecialchars_uni($stylesheet['name'])."&mode=advanced");
		exit;
	}
	
	// Fetch list of all of the stylesheets for this theme
	$file_stylesheets = unserialize($theme['stylesheets']);
	
	$stylesheets = array();
	$inherited_load = array();
	
	// Now we loop through the list of stylesheets for each file
	foreach($file_stylesheets as $file => $action_stylesheet)
	{
		if($file == 'inherited')
		{
			continue;
		}
		
		foreach($action_stylesheet as $action => $style)
		{
			foreach($style as $stylesheet2)
			{
				$stylesheets[$stylesheet2]['applied_to'][$file][] = $action;
				if(is_array($file_stylesheets['inherited'][$file."_".$action]) && in_array($stylesheet2, array_keys($file_stylesheets['inherited'][$file."_".$action])))
				{
					$stylesheets[$stylesheet2]['inherited'] = $file_stylesheets['inherited'][$file."_".$action];
					foreach($file_stylesheets['inherited'][$file."_".$action] as $value)
					{
						$inherited_load[] = $value;
					}
				}
			}
		}
	}
	
	foreach($stylesheets as $file => $stylesheet2)
	{
		if(is_array($stylesheet2['inherited']))
		{
			foreach($stylesheet2['inherited'] as $inherited_file => $tid)
			{
				$stylesheet2['inherited'][basename($inherited_file)] = $tid;
				unset($stylesheet2['inherited'][$inherited_file]);
			}
		}
		
		$stylesheets[basename($file)] = $stylesheet2;
		unset($stylesheets[$file]);
	}
	
	$this_stylesheet = $stylesheets[$stylesheet['name']];	
	unset($stylesheets);
	
	$page->extra_header .= "
	<script type=\"text/javascript\">
	var my_post_key = '".$mybb->post_code."';
	</script>";
	
	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), "index.php?module=style/themes&amp;action=edit&amp;tid={$mybb->input['tid']}");
	$page->add_breadcrumb_item("{$lang->editing} ".htmlspecialchars_uni($stylesheet['name']), "index.php?module=style/themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;file=".htmlspecialchars_uni($mybb->input['file'])."&amp;mode=simple");
	
	$page->output_header("{$lang->themes} - {$lang->edit_stylesheets}");

	// If the stylesheet and theme do not match, we must be editing something that is inherited
	if($this_stylesheet['inherited'][$stylesheet['name']])
	{
		$query = $db->simple_select("themes", "name", "tid='{$stylesheet['tid']}'");
		$stylesheet_parent = htmlspecialchars_uni($db->fetch_field($query, 'name'));
		
		// Show inherited warning
		if($stylesheet['tid'] == 1)
		{
			$page->output_alert($lang->sprintf($lang->stylesheet_inherited_default, $stylesheet_parent), "ajax_alert");
		}
		else
		{
			$page->output_alert($lang->sprintf($lang->stylesheet_inherited, $stylesheet_parent), "ajax_alert");
		}
	}
	
	$sub_tabs['edit_stylesheet'] = array(
		'title' => $lang->edit_stylesheet_simple_mode,
		'link' => "index.php?module=style/themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;file=".htmlspecialchars_uni($mybb->input['file'])."&amp;mode=simple",
		'description' => $lang->edit_stylesheet_simple_mode_desc
	);

	$sub_tabs['edit_stylesheet_advanced'] = array(
		'title' => $lang->edit_stylesheet_advanced_mode,
		'link' => "index.php?module=style/themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;file=".htmlspecialchars_uni($mybb->input['file'])."&amp;mode=advanced",
	);
	
	$page->output_nav_tabs($sub_tabs, 'edit_stylesheet');
	
	// Output the selection box
	$form = new Form("index.php", "get", "selector_form");
	echo $form->generate_hidden_field("module", "style/themes")."\n";
	echo $form->generate_hidden_field("action", "edit_stylesheet")."\n";
	echo $form->generate_hidden_field("tid", $mybb->input['tid'])."\n";
	echo $form->generate_hidden_field("file", htmlspecialchars_uni($mybb->input['file']))."\n";	
	
	echo "{$lang->selector}: <select id=\"selector\" name=\"selector\">\n{$selector_list}</select> <span id=\"mini_spinner\">".$form->generate_submit_button($lang->go)."</span><br /><br />\n";

	$form->end();

	// Haven't chosen a selector to edit, show the first one from the stylesheet
	if(!$mybb->input['selector'])
	{
		reset($css_array);
		$selector = key($css_array);
		$editable_selector = $css_array[$selector];
	}
	// Show a specific selector
	else
	{
		$editable_selector = $css_array[$mybb->input['selector']];
		$selector = $mybb->input['selector'];
	}
	
	// Get the properties from this item
	$properties = parse_css_properties($editable_selector['values']);
	
	$form = new Form("index.php?module=style/themes&amp;action=edit_stylesheet", "post");
	echo $form->generate_hidden_field("tid", $mybb->input['tid'], array('id' => "tid"))."\n";
	echo $form->generate_hidden_field("file", htmlspecialchars_uni($mybb->input['file']), array('id' => "file"))."\n";
	echo $form->generate_hidden_field("selector", htmlspecialchars_uni($selector), array('id' => 'hidden_selector'))."\n";
	
	echo "<div id=\"stylesheet\">";
	$table = new Table;	
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[background]', $properties['background'], array('id' => 'css_bits[background]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->background}</strong></div>", array('style' => 'width: 20%;'));
	$table->construct_cell("<strong>{$lang->extra_css_atribs}</strong><br /><div style=\"align: center;\">".$form->generate_text_area('css_bits[extra]', $properties['extra'], array('id' => 'css_bits[extra]', 'style' => 'width: 98%;', 'rows' => '19'))."</div>", array('rowspan' => 8));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[color]', $properties['color'], array('id' => 'css_bits[color]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->color}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[width]', $properties['width'], array('id' => 'css_bits[width]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->width}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[font_family]', $properties['font-family'], array('id' => 'css_bits[font_family]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->font_family}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[font_size]', $properties['font-size'], array('id' => 'css_bits[font_size]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->font_size}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[font_style]', $properties['font-style'], array('id' => 'css_bits[font_style]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->font_style}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[font_weight]', $properties['font-weight'], array('id' => 'css_bits[font_weight]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->font_weight}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[text_decoration]', $properties['text-decoration'], array('id' => 'css_bits[text_decoration]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->text_decoration}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	
	$table->output(htmlspecialchars_uni($editable_selector['class_name'])."<span id=\"saved\" style=\"color: #FEE0C6;\"></span>");
	
	echo "</div>";
	
	$buttons[] = $form->generate_reset_button($lang->reset);
	$buttons[] = $form->generate_submit_button($lang->save_changes, array('id' => 'save', 'name' => 'save'));
	$buttons[] = $form->generate_submit_button($lang->save_changes_and_close, array('id' => 'save_close', 'name' => 'save_close'));

	$form->output_submit_wrapper($buttons);
	
	echo '<script type="text/javascript" src="./jscripts/themes.js"></script>';
	echo '<script type="text/javascript">

Event.observe(window, "load", function() {
//<![CDATA[
    new ThemeSelector("./index.php?module=style/themes&action=xmlhttp_stylesheet", "./index.php?module=style/themes&action=edit_stylesheet", $("selector"), $("stylesheet"), "'.htmlspecialchars_uni($mybb->input['file']).'", $("selector_form"), "'.$mybb->input['tid'].'");
});
//]]>
</script>';

	$form->end();
	
	$page->output_footer();
}

if($mybb->input['action'] == "edit_stylesheet" && $mybb->input['mode'] == "advanced")
{
	// Fetch the theme we want to edit this stylesheet in
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);
	
	if(!$theme['tid'])
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	$parent_list = make_parent_theme_list($theme['tid']);
	$parent_list = implode(',', $parent_list);
	if(!$parent_list)
	{
		$parent_list = 1;
	}
	
	$query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string($mybb->input['file'])."' AND tid IN ({$parent_list})", array('order_by' => 'tid', 'order_dir' => 'desc', 'limit' => 1));
	$stylesheet = $db->fetch_array($query);
	
	// Does the theme not exist?
	if(!$stylesheet['sid'])
	{
		flash_message($lang->error_invalid_stylesheet, 'error');
		admin_redirect("index.php?module=style/themes");
	}

	if($mybb->request_method == "post")
	{
		$sid = $stylesheet['sid'];
		
		// Theme & stylesheet theme ID do not match, editing inherited - we copy to local theme
		if($theme['tid'] != $stylesheet['tid'])
		{
			$sid = copy_stylesheet_to_theme($stylesheet, $theme['tid']);
		}

		// Now we have the new stylesheet, save it
		$updated_stylesheet = array(
			"cachefile" => $db->escape_string($stylesheet['name']),
			"stylesheet" => $db->escape_string(unfix_css_urls($mybb->input['stylesheet'])),
			"lastmodified" => TIME_NOW
		);
		$db->update_query("themestylesheets", $updated_stylesheet, "sid='{$sid}'");
		
		// Cache the stylesheet to the file		
		if(!cache_stylesheet($theme['tid'], $stylesheet['name'], $mybb->input['stylesheet']))
		{
			$db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet={$sid}"), "sid='{$sid}'", 1);
		}

		// Update the CSS file list for this theme
		update_theme_stylesheet_list($theme['tid']);

		// Log admin action
		log_admin_action($theme['name'], $stylesheet['name']);

		flash_message($lang->success_stylesheet_updated, 'success');
		
		if(!$mybb->input['save_close'])
		{
			admin_redirect("index.php?module=style/themes&action=edit_stylesheet&file=".htmlspecialchars_uni($stylesheet['name'])."&tid={$theme['tid']}&mode=advanced");
		}
		else
		{
			admin_redirect("index.php?module=style/themes&action=edit&tid={$theme['tid']}");
		}
	}
	
	// Fetch list of all of the stylesheets for this theme
	$file_stylesheets = unserialize($theme['stylesheets']);
	
	$stylesheets = array();
	$inherited_load = array();
	
	// Now we loop through the list of stylesheets for each file
	foreach($file_stylesheets as $file => $action_stylesheet)
	{
		if($file == 'inherited')
		{
			continue;
		}
		
		foreach($action_stylesheet as $action => $style)
		{
			foreach($style as $stylesheet2)
			{
				$stylesheets[$stylesheet2]['applied_to'][$file][] = $action;
				if(is_array($file_stylesheets['inherited'][$file."_".$action]) && in_array($stylesheet2, array_keys($file_stylesheets['inherited'][$file."_".$action])))
				{
					$stylesheets[$stylesheet2]['inherited'] = $file_stylesheets['inherited'][$file."_".$action];
					foreach($file_stylesheets['inherited'][$file."_".$action] as $value)
					{
						$inherited_load[] = $value;
					}
				}
			}
		}
	}
	
	foreach($stylesheets as $file => $stylesheet2)
	{
		if(is_array($stylesheet2['inherited']))
		{
			foreach($stylesheet2['inherited'] as $inherited_file => $tid)
			{
				$stylesheet2['inherited'][basename($inherited_file)] = $tid;
				unset($stylesheet2['inherited'][$inherited_file]);
			}
		}
		
		$stylesheets[basename($file)] = $stylesheet2;
		unset($stylesheets[$file]);
	}
	
	$this_stylesheet = $stylesheets[$stylesheet['name']];	
	unset($stylesheets);
	
	if($admin_options['codepress'] != 0)
	{
		$page->extra_header .= '
	<link type="text/css" href="./jscripts/codepress/languages/codepress-css.css" rel="stylesheet" id="cp-lang-style" />
	<script type="text/javascript" src="./jscripts/codepress/codepress.js"></script>
	<script type="text/javascript">
		CodePress.language = \'css\';
	</script>';
	}
	
	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), "index.php?module=style/themes&amp;action=edit&amp;tid={$mybb->input['tid']}");
	$page->add_breadcrumb_item("{$lang->editing} ".htmlspecialchars_uni($stylesheet['name']), "index.php?module=style/themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;file=".htmlspecialchars_uni($mybb->input['file'])."&amp;mode=advanced");
	
	$page->output_header("{$lang->themes} - {$lang->edit_stylesheet_advanced_mode}");

	// If the stylesheet and theme do not match, we must be editing something that is inherited
	if($this_stylesheet['inherited'][$stylesheet['name']])
	{
		$query = $db->simple_select("themes", "name", "tid='{$stylesheet['tid']}'");
		$stylesheet_parent = htmlspecialchars_uni($db->fetch_field($query, 'name'));
		
		// Show inherited warning
		if($stylesheet['tid'] == 1)
		{
			$page->output_alert($lang->sprintf($lang->stylesheet_inherited_default, $stylesheet_parent));
		}
		else
		{
			$page->output_alert($lang->sprintf($lang->stylesheet_inherited, $stylesheet_parent));
		}
	}
	
	$sub_tabs['edit_stylesheet'] = array(
		'title' => $lang->edit_stylesheet_simple_mode,
		'link' => "index.php?module=style/themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;file=".htmlspecialchars_uni($mybb->input['file'])."&amp;mode=simple"
	);

	$sub_tabs['edit_stylesheet_advanced'] = array(
		'title' => $lang->edit_stylesheet_advanced_mode,
		'link' => "index.php?module=style/themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;file=".htmlspecialchars_uni($mybb->input['file'])."&amp;mode=advanced",
		'description' => $lang->edit_stylesheet_advanced_mode_desc
	);
	
	$page->output_nav_tabs($sub_tabs, 'edit_stylesheet_advanced');

	// Has the file on the file system been modified?
	if(resync_stylesheet($stylesheet))
	{
		// Need to refetch new stylesheet as it was modified
		$query = $db->simple_select("themestylesheets", "stylesheet", "sid='{$stylesheet['sid']}'");
		$stylesheet['stylesheet'] = $db->fetch_field($query, 'stylesheet');
	}
	
	$form = new Form("index.php?module=style/themes&amp;action=edit_stylesheet&amp;mode=advanced", "post", "edit_stylesheet");
	echo $form->generate_hidden_field("tid", $mybb->input['tid'])."\n";
	echo $form->generate_hidden_field("file", htmlspecialchars_uni($mybb->input['file']))."\n";
	
	$table = new Table;	
	$table->construct_cell($form->generate_text_area('stylesheet', $stylesheet['stylesheet'], array('id' => 'stylesheet', 'style' => 'width: 99%;', 'class' => 'codepress css', 'rows' => '30')));
	$table->construct_row();
	$table->output("{$lang->full_stylesheet_for} ".htmlspecialchars_uni($stylesheet['name']));
	
	$buttons[] = $form->generate_reset_button($lang->reset);
	$buttons[] = $form->generate_submit_button($lang->save_changes, array('id' => 'save', 'name' => 'save'));
	$buttons[] = $form->generate_submit_button($lang->save_changes_and_close, array('id' => 'save_close', 'name' => 'save_close'));

	$form->output_submit_wrapper($buttons);

	$form->end();
	
	if($admin_options['codepress'] != 0)
	{
		echo "<script type=\"text/javascript\">
	Event.observe('edit_stylesheet', 'submit', function()
	{
		if($('stylesheet_cp')) {
			var area = $('stylesheet_cp');
			area.id = 'stylesheet';
			area.value = stylesheet.getCode();
			area.disabled = false;
		}
	});
</script>";
	}
	
	$page->output_footer();
}

if($mybb->input['action'] == "delete_stylesheet")
{
	// Fetch the theme we want to edit this stylesheet in
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);
	
	if(!$theme['tid'])
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	$parent_list = make_parent_theme_list($theme['tid']);
	$parent_list = implode(',', $parent_list);
	if(!$parent_list)
	{
		$parent_list = 1;
	}
	
	$query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string($mybb->input['file'])."' AND tid IN ({$parent_list})", array('order_by' => 'tid', 'order_dir' => 'desc', 'limit' => 1));
	$stylesheet = $db->fetch_array($query);
	
	// Does the theme not exist? or are we trying to delete the master?
	if(!$stylesheet['sid'] || $stylesheet['tid'] == 1)
	{
		flash_message($lang->error_invalid_stylesheet, 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=style/themes");
	}

	if($mybb->request_method == "post")
	{
		$db->delete_query("themestylesheets", "sid='{$stylesheet['sid']}'", 1);
		@unlink(MYBB_ROOT."cache/themes/theme{$theme['tid']}/{$stylesheet['cachefile']}");
		
		// Update the CSS file list for this theme
		update_theme_stylesheet_list($theme['tid']);
		
		// Log admin action
		log_admin_action($stylesheet['sid'], $stylesheet['name'], $theme['tid'], $theme['name']);
		
		flash_message($lang->success_stylesheet_deleted, 'success');
		admin_redirect("index.php?module=style/themes&action=edit&tid={$theme['tid']}");
	}
	else
	{		
		$page->output_confirm_action("index.php?module=style/themes&amp;action=force&amp;tid={$theme['tid']}", $lang->confirm_stylesheet_deletion);
	}
}

if($mybb->input['action'] == "add_stylesheet")
{
	// Fetch the theme we want to edit this stylesheet in
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);
	
	if(!$theme['tid'])
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	// Fetch list of all of the stylesheets for this theme
	$file_stylesheets = unserialize($theme['stylesheets']);
	
	$stylesheets = array();
	$inherited_load = array();
	
	// Now we loop through the list of stylesheets for each file
	foreach($file_stylesheets as $file => $action_stylesheet)
	{
		if($file == 'inherited')
		{
			continue;
		}
		
		foreach($action_stylesheet as $action => $style)
		{
			foreach($style as $stylesheet2)
			{
				$stylesheets[$stylesheet2]['applied_to'][$file][] = $action;
				if(is_array($file_stylesheets['inherited'][$file."_".$action]) && in_array($stylesheet2, array_keys($file_stylesheets['inherited'][$file."_".$action])))
				{
					$stylesheets[$stylesheet2]['inherited'] = $file_stylesheets['inherited'][$file."_".$action];
					foreach($file_stylesheets['inherited'][$file."_".$action] as $value)
					{
						$inherited_load[] = $value;
					}
				}
			}
		}
	}
	
	foreach($stylesheets as $file => $stylesheet2)
	{
		if(is_array($stylesheet2['inherited']))
		{
			foreach($stylesheet2['inherited'] as $inherited_file => $tid)
			{
				$stylesheet2['inherited'][basename($inherited_file)] = $tid;
				unset($stylesheet2['inherited'][$inherited_file]);
			}
		}
		
		$stylesheets[basename($file)] = $stylesheet2;
		unset($stylesheets[$file]);
	}
	
	if($mybb->request_method == "post")
	{
		if(!$mybb->input['name'])
		{
			$errors[] = $lang->error_missing_stylesheet_name;
		}
		
		if(!$errors)
		{
			if($mybb->input['add_type'] == 1)
			{
				// Import from a current stylesheet
				$parent_list = make_parent_theme_list($theme['tid']);
				$parent_list = implode(',', $parent_list);
				
				$query = $db->simple_select("themestylesheets", "stylesheet", "name='".$db->escape_string($mybb->input['import'])."' AND tid IN ({$parent_list})", array('limit' => 1, 'order_by' => 'tid', 'order_dir' => 'desc'));
				$stylesheet = $db->fetch_field($query, "stylesheet");
			}
			else
			{
				// Custom stylesheet
				$stylesheet = $mybb->input['stylesheet'];
			}
			
			$attached = array();
			
			if($mybb->input['attach'] == 1)
			{
				// Our stylesheet is attached to custom pages in MyBB
				foreach($mybb->input as $id => $value)
				{
					$actions_list = "";
					$attached_to = "";
					
					if(strpos($id, 'attached_') !== false)
					{
						// We have a custom attached file				
						$attached_id = intval(str_replace('attached_', '', $id));
						
						if($mybb->input['action_'.$attached_id] == 1)
						{
							// We have custom actions for attached files							
							$actions_list = $mybb->input['action_list_'.$attached_id];
						}
						
						if($actions_list)
						{
							$attached_to = $value."?".$actions_list;
						}
						
						$attached[] = $attached_to;
					}
				}
			}
			
			// Add Stylesheet			
			$insert_array = array(
				'name' => $db->escape_string($mybb->input['name']),
				'tid' => intval($mybb->input['tid']),
				'attachedto' => implode('|', array_map(array($db, "escape_string"), $attached)),
				'stylesheet' => $db->escape_string($stylesheet),
				'cachefile' => $db->escape_string(str_replace('/', '', $mybb->input['name'])),
				'lastmodified' => TIME_NOW
			);			
			
			$sid = $db->insert_query("themestylesheets", $insert_array);
			
			if(!cache_stylesheet($theme['tid'], str_replace('/', '', $mybb->input['name']), $stylesheet))
			{
				$db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet={$sid}"), "sid='{$sid}'", 1);
			}
			
			// Update the CSS file list for this theme
			update_theme_stylesheet_list($theme['tid']);
			
			// Log admin action
			log_admin_action($sid, $mybb->input['name'], $theme['tid'], $theme['name']);
		
			flash_message($lang->success_stylesheet_added, 'success');
			admin_redirect("index.php?module=style/themes&action=edit_stylesheet&tid={$mybb->input['tid']}&sid={$sid}&file=".urlencode($mybb->input['name']));
		}
	}
	
	if($admin_options['codepress'] != 0)
	{
		$page->extra_header .= '
	<link type="text/css" href="./jscripts/codepress/languages/codepress-css.css" rel="stylesheet" id="cp-lang-style" />
	<script type="text/javascript" src="./jscripts/codepress/codepress.js"></script>
	<script type="text/javascript">
		CodePress.language = \'css\';
	</script>';
	}
	
	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), "index.php?module=style/themes&amp;action=edit&amp;tid={$mybb->input['tid']}");
	$page->add_breadcrumb_item($lang->add_stylesheet);
	
	$page->output_header("{$lang->themes} - {$lang->add_stylesheet}");
	
	$sub_tabs['edit_stylesheets'] = array(
		'title' => $lang->edit_stylesheets,
		'link' => "index.php?module=style/themes&amp;action=edit&amp;tid={$mybb->input['tid']}"
	);

	$sub_tabs['add_stylesheet'] = array(
		'title' => $lang->add_stylesheet,
		'link' => "index.php?module=style/themes&amp;action=add_stylesheet&amp;tid={$mybb->input['tid']}",
		'description' => $lang->add_stylesheet_desc
	);
	
	$sub_tabs['export_theme'] = array(
		'title' => $lang->export_theme,
		'link' => "index.php?module=style/themes&amp;action=export&amp;tid={$mybb->input['tid']}"
	);
	
	$page->output_nav_tabs($sub_tabs, 'add_stylesheet');
	
	if($errors)
	{
		$page->output_inline_error($errors);
		
		foreach($mybb->input as $name => $value)
		{
			if(strpos($name, "attached") !== false)
			{
				list(, $id) = explode('_', $name);
				$id = intval($id);
				
				$mybb->input['applied_to'][$value] = array(0 => 'global');
				
				if($mybb->input['action_'.$id] == 1)
				{
					$mybb->input['applied_to'][$value] = explode(',', $mybb->input['action_list_'.$id]);
				}
			}
		}
		
		if($mybb->input['add_type'] == 1)
		{
			$add_checked[1] = "checked=\"checked\"";
			$add_checked[2] = "";
		}
		else
		{
			$add_checked[2] = "checked=\"checked\"";
			$add_checked[1] = "";
		}
	}
	else
	{
		$mybb->input['name'] = $stylesheet['name'];
	}
	
	$form = new Form("index.php?module=style/themes&amp;action=add_stylesheet", "post", "add_stylesheet");
	
	echo $form->generate_hidden_field("tid", $mybb->input['tid'])."\n";
	
	$specific_files = "<div id=\"attach_1\" class=\"attachs\">";
	$count = 0;
	
	if(is_array($mybb->input['applied_to']) && $mybb->input['applied_to']['global'][0] != "global")
	{
		$check_actions = "";
		
		$global_checked[2] = "checked=\"checked\"";
		$global_checked[1] = "";
		
		foreach($mybb->input['applied_to'] as $name => $actions)
		{
			$short_name = substr($name, 0, -4);
			
			$action_list = "";
			if($actions[0] != "global")
			{
				$action_list = implode(',', $actions);
			}
			
			if($actions[0] == "global")
			{
				$global_action_checked[1] = "checked=\"checked\"";
				$global_action_checked[2] = "";
			}
			else
			{
				$global_action_checked[2] = "checked=\"checked\"";
				$global_action_checked[1] = "";
			}
			
			$specific_file = "<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_{$count}\" value=\"0\" {$global_action_checked[1]} class=\"action_{$count}s_check\" onclick=\"checkAction('action_{$count}');\" style=\"vertical-align: middle;\" /> {$lang->globally}</label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_{$count}\" value=\"1\" {$global_action_checked[2]} class=\"action_{$count}s_check\" onclick=\"checkAction('action_{$count}');\" style=\"vertical-align: middle;\" /> {$lang->specific_actions}</label></dt>
			<dd style=\"margin-top: 4px;\" id=\"action_{$count}_1\" class=\"action_{$count}s\">
			<small class=\"description\">{$lang->specific_actions_desc}</small>
			<table cellpadding=\"4\">
				<tr>
					<td>".$form->generate_text_box('action_list_'.$count, $action_list, array('id' => 'action_list_'.$count, 'style' => 'width: 190px;'))."</td>
				</tr>
			</table>
		</dd>
		</dl>";
			
			$form_container = new FormContainer();
			$form_container->output_row("", "", "<span style=\"float: right;\"><a href=\"\" id=\"delete_img_{$count}\"><img src=\"styles/{$page->style}/images/icons/cross.gif\" alt=\"{$lang->delete}\" title=\"{$lang->delete}\" /></a></span>{$lang->file} &nbsp;".$form->generate_text_box("attached_{$count}", $name, array('id' => "attached_{$count}", 'style' => 'width: 200px;')), "attached_{$count}");
	
			$form_container->output_row("", "", $specific_file);
	
			$specific_files .= "<div id=\"attached_form_{$count}\">".$form_container->end(true)."</div><div id=\"attach_box_{$count}\"></div>";
			
			$check_actions .= "\n\tcheckAction('action_{$count}');";
			
			++$count;
		}
	}
	else
	{
		$global_checked[1] = "checked=\"checked\"";
		$global_checked[2] = "";
	}
	
	$actions = '<script type="text/javascript">
    function checkAction(id)
    {
        var checked = \'\';
		
        $$(\'.\'+id+\'s_check\').each(function(e)
        {
            if(e.checked == true)
            {
                checked = e.value;
            }
        });
        $$(\'.\'+id+\'s\').each(function(e)
        {
        	Element.hide(e);
        });
        if($(id+\'_\'+checked))
        {
            Element.show(id+\'_\'+checked);
        }
    }    
</script>
	<dl style="margin-top: 0; margin-bottom: 0; width: 40%;">
	<dt><label style="display: block;"><input type="radio" name="attach" value="0" '.$global_checked[1].' class="attachs_check" onclick="checkAction(\'attach\');" style="vertical-align: middle;" /> '.$lang->globally.'</label></dt>
		<dt><label style="display: block;"><input type="radio" name="attach" value="1" '.$global_checked[2].' class="attachs_check" onclick="checkAction(\'attach\');" style="vertical-align: middle;" /> '.$lang->specific_files.' (<a id="new_specific_file">'.$lang->add_another.'</a>)</label></dt><br />
		'.$specific_files.'
	</dl>
	<script type="text/javascript">
	checkAction(\'attach\');'.$check_actions.'
	</script>';
	
	echo $form->generate_hidden_field("sid", $stylesheet['sid'])."<br />\n";
	
	$form_container = new FormContainer("{$lang->add_stylesheet_to} ".htmlspecialchars_uni($theme['name']));
	$form_container->output_row($lang->file_name, $lang->file_name_desc, $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name', 'style' => 'width: 200px;')), 'name');
	
	$form_container->output_row($lang->attached_to, $lang->attached_to_desc, $actions);
	
	$sheetnames = array();
	foreach($stylesheets as $filename => $style)
	{
		$sheetnames[basename($filename)] = basename($filename);
	}
	
	$actions = "<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"add_type\" value=\"1\" {$add_checked[1]} class=\"adds_check\" onclick=\"checkAction('add');\" style=\"vertical-align: middle;\" /> <strong>{$lang->import_stylesheet_from}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"add_1\" class=\"adds\">
			<table cellpadding=\"4\">
				<tr>
					<td>".$form->generate_select_box('import', $sheetnames, $mybb->input['import'], array('id' => 'import'))."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"add_type\" value=\"2\" {$add_checked[2]} class=\"adds_check\" onclick=\"checkAction('add');\" style=\"vertical-align: middle;\" /> <strong>{$lang->write_own}</strong></label></dt>
		<span id=\"add_2\" class=\"adds\"><br />".$form->generate_text_area('stylesheet', $mybb->input['stylesheet'], array('id' => 'stylesheet', 'style' => 'width: 99%;', 'class' => 'codepress css', 'rows' => '30'))."</span>
	</dl>";
	
	$form_container->output_row("", "", $actions);
	
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button($lang->save_stylesheet);

	$form->output_submit_wrapper($buttons);
	
	if($admin_options['codepress'] != 0)
	{
		echo "<script type=\"text/javascript\">
	Event.observe('add_stylesheet', 'submit', function()
	{
		if($('stylesheet_cp')) {
			var area = $('stylesheet_cp');
			area.id = 'stylesheet';
			area.value = stylesheet.getCode();
			area.disabled = false;
		}
	});
</script>\n";
	}

	echo '<script type="text/javascript" src="./jscripts/themes.js"></script>';
	echo '<script type="text/javascript">
Event.observe(window, "load", function() {
//<![CDATA[
    new ThemeSelector(\''.$count.'\');
	checkAction(\'add\');
});
//]]>
</script>';
	
	$form->end();
	
	$page->output_footer();
}

if($mybb->input['action'] == "set_default")
{
	if(!verify_post_check($mybb->input['my_post_key']))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$theme['tid'])
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	$db->update_query("themes", array('def' => 0));
	$db->update_query("themes", array('def' => 1), "tid='".intval($mybb->input['tid'])."'");
	
	// Log admin action
	log_admin_action($theme['tid'], $theme['name']);

	flash_message($lang->success_theme_set_default, 'success');
	admin_redirect("index.php?module=style/themes");
}

if($mybb->input['action'] == "force")
{
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$theme['tid'])
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=style/themes");
	}

	if($mybb->request_method == "post")
	{
		$updated_users = array(
			"style" => $theme['tid']
		);
		
		$db->update_query("users", $updated_users);

		// Log admin action
		log_admin_action($theme['tid'], $theme['name']);

		flash_message($lang->success_theme_forced, 'success');
		admin_redirect("index.php?module=style/themes");
	}
	else
	{		
		$page->output_confirm_action("index.php?module=style/themes&amp;action=force&amp;tid={$theme['tid']}", $lang->confirm_theme_forced);
	}
}

if(!$mybb->input['action'])
{	
	$page->output_header($lang->themes);
	
	$page->output_nav_tabs($sub_tabs, 'themes');

	$table = new Table;
	$table->construct_header($lang->theme);
	$table->construct_header($lang->num_users, array("class" => "align_center", "width" => 100));
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));

	build_theme_list();

	$table->output($lang->themes);
	
	$page->output_footer();
}

?>

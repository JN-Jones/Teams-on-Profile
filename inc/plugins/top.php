<?php
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("member_profile_end", "top_profile");
$plugins->add_hook("postbit", "top_postbit");

function top_info()
{
	return array(
		"name"			=> "Team on Profile",
		"description"	=> "Zeigt Benutzergruppen im Profil und Postbit",
		"website"		=> "http://jonesboard.de/",
		"author"		=> "Jones",
		"authorsite"	=> "http://jonesboard.de/",
		"version"		=> "1.3.1",
		"guid" 			=> "",
		"compatibility" => "17*,18*",
		"myplugins_id"	=> "teams-on-profile"
	);
}

function top_activate()
{
	global $db;

	$group = array(
		"name" => "top",
		"title" => "Team on Profile",
		"description" => "",
		"disporder" => "1",
		"isdefault" => "0",
	);
	$gid = $db->insert_query("settinggroups", $group);

	$setting = array(
		"name" => "top_team",
		"title" => "Sollen nur Gruppen, die auch auf der Teamseite gezeigt werden, im Profil erscheinen?",
		"description" => "",
		"optionscode" => "yesno",
		"value" => "no",
		"disporder" => "1",
		"gid" => (int)$gid,
	);
	$db->insert_query("settings", $setting);

	$setting = array(
		"name" => "top_teamleader",
		"title" => "Sollen Gruppen, die der Benutzer leitet, seperat angezeigt werden?",
		"description" => "Zeigt auch Gruppen, die nicht zum Team gehören an (Überschreibt vorige Einstellung)",
		"optionscode" => "yesno",
		"value" => "no",
		"disporder" => "2",
		"gid" => (int)$gid,
	);
	$db->insert_query("settings", $setting);

	$setting = array(
		"name" => "top_postbit",
		"title" => "Sollen die Gruppen auch im Postbit gezeigt werden?",
		"description" => "",
		"optionscode" => "yesno",
		"value" => "yes",
		"disporder" => "3",
		"gid" => (int)$gid,
	);
	$db->insert_query("settings", $setting);

	$setting = array(
		"name" => "top_groups",
		"title" => "Welche Gruppen sollen nicht angezeigt werden? (ID, mit Komma getrennt)",
		"description" => "",
		"optionscode" => "text",
		"value" => "1, 2",
		"disporder" => "4",
		"gid" => (int)$gid,
	);
	$db->insert_query("settings", $setting);
	rebuild_settings();

	$template="
{\$leader}
<tr>
	<td class=\"trow1\"><strong>{\$lang->top_status}:</strong></td>
	<td class=\"trow1\">{\$status}</td>
</tr>
<tr>
	<td class=\"trow2\"><strong>{\$lang->top_teams}:</strong></td>
	<td class=\"trow2\">{\$teams}</td>
</tr>";
	$templatearray = array(
		"title" => "member_profile_top",
		"template" => $template,
		"sid" => "-2",
	);
	$db->insert_query("templates", $templatearray);

	$template="
<tr>
	<td class=\"trow1\"><strong>{\$lang->top_groupsleader}:</strong></td>
	<td class=\"trow1\">{\$leaders}</td>
</tr>";
	$templatearray = array(
		"title" => "member_profile_topl",
		"template" => $template,
		"sid" => "-2",
	);
	$db->insert_query("templates", $templatearray);

	$template="
<a href=\"\" id=\"groups_{\$post[\'pid\']}\">{\$lang->top_groups}</a>
<div id=\"groups_{\$post[\'pid\']}_popup\" class=\"popup_menu\" style=\"display: none;\">
{\$popup}
</div>
<script type=\"text/javascript\">
// <!--
	if(use_xmlhttprequest == \"1\")
	{
		$(\"#groups_{\$post[\'pid\']}\").popupMenu();
	}
// -->
</script>";
	$templatearray = array(
		"title" => "postbit_top",
		"template" => $template,
		"sid" => "-2",
	);
	$db->insert_query("templates", $templatearray);

	require MYBB_ROOT."inc/adminfunctions_templates.php";
	find_replace_templatesets("member_profile", "#".preg_quote('{$reputation}')."#i", '{$reputation}{$top}');
	find_replace_templatesets("postbit", "#".preg_quote('{$post[\'button_edit\']}')."#i", '{$post[\'top\']}{$post[\'button_edit\']}');
	find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'button_edit\']}')."#i", '{$post[\'top\']}{$post[\'button_edit\']}');
}

function top_deactivate()
{
	global $db;
	$query = $db->simple_select("settinggroups", "gid", "name='top'");
	$g = $db->fetch_array($query);
	$db->delete_query("settinggroups", "gid='".$g['gid']."'");
	$db->delete_query("settings", "gid='".$g['gid']."'");
	rebuild_settings();

	$db->delete_query("templates", "title='member_profile_top'");
	$db->delete_query("templates", "title='member_profile_topl'");
	$db->delete_query("templates", "title='postbit_top'");
	require MYBB_ROOT."inc/adminfunctions_templates.php";
	find_replace_templatesets("member_profile", "#".preg_quote('{$top}')."#i", "", 0);
	find_replace_templatesets("postbit", "#".preg_quote('{$post[\'top\']}')."#i", "", 0);
	find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'top\']}')."#i", "", 0);
}

function top_postbit($post)
{
	global $templates, $mybb, $lang;

	$lang->load("top");
	
	if(!$mybb->settings['top_postbit'])
		return $post;

	$show = top_create($post);

	if(is_array($show)) {
		foreach($show as $group) {
			if(empty($group))
				continue;

			$popup .= "<div class=\"popup_item_container\"><div class=\"popup_item\">{$group}</div></div>";
		}
	}

	if($popup != "")
		eval("\$post['top'] = \"".$templates->get("postbit_top")."\";");
	return $post;
}

function top_profile()
{
	global $memprofile, $templates, $top, $lang, $mybb;

	$lang->load("top");

	$show = top_create($memprofile, false);

	if(is_array($show['sec']))
		$teams = implode(", ", $show['sec']);
	else
		$teams = "-";

	$status = $show['primar'];
	
	if($mybb->settings['top_teamleader'] && is_array($show['leader'])) {
		$leaders = implode(", ", $show['leader']);
		eval("\$leader = \"".$templates->get("member_profile_topl")."\";");
	}

	eval("\$top = \"".$templates->get("member_profile_top")."\";");
}

function top_create($user, $one=true)
{
	global $groupscache, $mybb, $db;
	

	if($user['additionalgroups'] != "")
		$groups = explode(",", $user['additionalgroups']);
	else
		$groups = array();
	$primar = $user['usergroup'];

	$groups = array_filter($groups, "top_filter");

	if(!$one && $mybb->settings['top_teamleader']) {
		$query = $db->simple_select("groupleaders", "gid", "uid='{$user['uid']}'");
		while($leader = $db->fetch_array($query)) {
			if(top_filter($leader['gid']))
				$team['leader'][] = $leader['gid'];
		}
	}
	if(!$team['leader'])
		$team['leader'] = array();

	foreach($groups as $group) {
		if(in_array($group, $team['leader']))
			continue;
		$group = $groupscache[$group];
		if(($group['showforumteam'] == "1" && $mybb->settings['top_team']) || !$mybb->settings['top_team']) {
			$string = str_replace("{username}", $group['title'], $group['namestyle']);
			$team['sec'][] = $string;
		}
	}
	$group = $groupscache[$primar];
	$team['primar'] = str_replace("{username}", $group['title'], $group['namestyle']);

	if($one) {
		if(!is_array($team['sec']))
			$team = array($team['primar']);
		else
			$team = array_merge(array($team['primar']), $team['sec']);
	} else {
		foreach($team['leader'] as $leader) {
			$group = $groupscache[$leader];
			$team['leader1'][] = str_replace("{username}", $group['title'], $group['namestyle']);			
		}
		$team['leader'] = $team['leader1'];
		unset($team['leader1']);
	}
	return $team;
}

function top_filter($var)
{
	global $mybb;
	$g = explode(",", trim($mybb->settings['top_groups']));
	return !in_array($var, $g);
}
?>
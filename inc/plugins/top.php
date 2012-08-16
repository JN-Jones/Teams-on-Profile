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
		"website"		=> "http://mybbdemo.tk/",
		"author"		=> "Jones",
		"authorsite"	=> "http://mybbdemo.tk",
		"version"		=> "1.2",
		"guid" 			=> "",
		"compatibility" => "*"
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
        "optionscode" => "yesno",
        "value" => "no",
        "disporder" => "1",
        "gid" => intval($gid),
        );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "top_postbit",
        "title" => "Sollen die Gruppen auch im Postbit gezeigt werden?",
        "optionscode" => "yesno",
        "value" => "yes",
        "disporder" => "2",
        "gid" => intval($gid),
        );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "top_groups",
        "title" => "Welche Gruppen sollen nicht angezeigt werden? (ID, mit Komma getrennt)",
        "optionscode" => "text",
        "value" => "1, 2",
        "disporder" => "3",
        "gid" => intval($gid),
        );
    $db->insert_query("settings", $setting);
    rebuild_settings();

    $template="
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
<a href=\"\" id=\"groups_{\$post[\'pid\']}\">{\$lang->top_groups}</a>
<div id=\"groups_{\$post[\'pid\']}_popup\" class=\"popup_menu\" style=\"display: none;\">
{\$popup}
</div>
<script type=\"text/javascript\">
// <!--
	if(use_xmlhttprequest == \"1\")
	{
		new PopupMenu(\"groups_{\$post[\'pid\']}\");
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
	
    if($post['additionalgroups'] != "")
		$groups = explode(",", $post['additionalgroups']);
	$groups[] = $post['usergroup'];

	$show = top_create($groups);

	if(is_array($show)) {
		foreach($show as $group) {
			$popup .= "<div class=\"popup_item_container\"><div class=\"popup_item\">{$group}</div></div>";
		}
	}

	if($popup != "")
		eval("\$post['top'] = \"".$templates->get("postbit_top")."\";");
	return $post;
}

function top_profile()
{
	global $memprofile, $templates, $top, $lang;

	$lang->load("top");

	if($memprofile['additionalgroups'] != "")
		$groups = explode(",", $memprofile['additionalgroups']);
	else
		$groups = array();
	$prim = $memprofile['usergroup'];

	$show = top_create($groups, $prim);

	if(is_array($show['sec']))
	    $teams = implode(", ", $show['sec']);
	else
		$teams = "-";

	$status = $show['primar'];

    eval("\$top = \"".$templates->get("member_profile_top")."\";");
}

function top_create($groups, $primar=false)
{
	global $groupscache, $mybb;
	
	$groups = array_filter($groups, "top_filter");

	foreach($groups as $group) {
		$group = $groupscache[$group];
		if(($group['showforumteam'] == "1" && $mybb->settings['top_team']) || !$mybb->settings['top_team']) {
			$string = str_replace("{username}", $group['title'], $group['namestyle']);
		    $showteam['sec'][] = $string;
		}
	}
	if($primar) {
		$group = $groupscache[$primar];
		$showteam['primar'] = str_replace("{username}", $group['title'], $group['namestyle']);
	} else
		$showteam = $showteam['sec'];

	return $showteam;
}

function top_filter($var)
{
	global $mybb;
	$g = explode(",", trim($mybb->settings['top_groups']));
	return !in_array($var, $g);
}
?>
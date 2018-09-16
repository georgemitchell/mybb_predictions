<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Make sure we can't access this file directly from the browser.
if(!defined('IN_MYBB'))
{
	die('This file cannot be accessed directly.');
}

// cache templates - this is important when it comes to performance
// THIS_SCRIPT is defined by some of the MyBB scripts, including index.php
if(defined('THIS_SCRIPT'))
{
    global $templatelist;

    if(isset($templatelist))
    {
        $templatelist .= ',';
    }

	if(THIS_SCRIPT== 'index.php')
	{
		$templatelist .= 'predictions_index, predictions_predictionrow';
	}
	elseif(THIS_SCRIPT== 'showthread.php')
	{
		$templatelist .= 'predictions_post, predictions_predictionrow';
	}
}

if(defined('IN_ADMINCP'))
{
	// Add our hello_settings() function to the setting management module to load language strings.
	$plugins->add_hook('admin_config_settings_manage', 'predictions_settings');
	$plugins->add_hook('admin_config_settings_change', 'predictions_settings');
	$plugins->add_hook('admin_config_settings_start', 'predictions_settings');
	// We could hook at 'admin_config_settings_begin' only for simplicity sake.
}
else
{
	// Add our latest_game() function to the forumdisplay_start hook so it gets executed on the main forum page
	$plugins->add_hook('forumdisplay_start', 'predictions_set_latest_game');
	$plugins->add_hook('forumdisplay_end', 'predictions_forum_show_score');
	$plugins->add_hook('forumdisplay_thread', 'predictions_thread_show_score');
	$plugins->add_hook('newthread_end', 'predictions_prediction_box');
	$plugins->add_hook('newthread_do_newthread_end', 'predictions_prediction_thread');
	$plugins->add_hook('showthread_start', 'predictions_thread_game');
	$plugins->add_hook('showthread_end', 'predictions_ajax_action');
	$plugins->add_hook('xmlhttp', 'predictions_ajax_action');

	// Add our hello_new() function to the misc_start hook so our misc.php?action=hello inserts a new message into the created DB table.
	$plugins->add_hook('misc_start', 'predictions_new');
}

function predictions_info()
{
	global $lang;
	$lang->load('predictions');

	/**
	 * Array of information about the plugin.
	 * name: The name of the plugin
	 * description: Description of what the plugin does
	 * website: The website the plugin is maintained at (Optional)
	 * author: The name of the author of the plugin
	 * authorsite: The URL to the website of the author (Optional)
	 * version: The version number of the plugin
	 * compatibility: A CSV list of MyBB versions supported. Ex, '121,123', '12*'. Wildcards supported.
	 * codename: An unique code name to be used by updated from the official MyBB Mods community.
	 */
	return array(
		'name'			=> 'Predictions',
		'description'	=> $lang->predictions_desc,
		'website'		=> 'https://github.com/georgemitchell/mybb_predictions',
		'author'		=> 'A guy who spent a lot of time in Sweet Hall',
		'authorsite'	=> 'https://georgeofallages.com',
		'version'		=> '1.0',
		'compatibility'	=> '18*',
		'codename'		=> 'predictions'
	);
}

/*
 * _activate():
 *    Called whenever a plugin is activated via the Admin CP. This should essentially make a plugin
 *    'visible' by adding templates/template changes, language changes etc.
*/
function predictions_activate()
{
	global $db, $lang;
	$lang->load('predictions');

	$add_team = <<<ADD_TEAM
	<form method="POST" action="predictions.php">
		<input type="hidden" name="my_post_key" value="{\$mybb->post_code}" />
		<input type="hidden" name="action" value="add_team" />
		<div class="tborder">
			<div class="thead">Add a team</div>
			<div class="trow1 rowbit">
				<div class="formbit_label col-sm-2 strong">Team Name:</div>
				<div class="formbit_field col-sm-10">
					<input type="text" class="textbox" name="name" size="40" maxlength="128" value="" tabindex="1" />
				</div>
			</div>
			<div class="trow1 rowbit">
				<div class="formbit_label col-sm-2 strong">Logo:</div>
				<div class="formbit_field col-sm-10">
					<input type="text" class="textbox" name="logo" size="40" maxlength="256" value="" tabindex="2" />
				</div>
			</div>
			<div class="trow1 rowbit">
				<div class="formbit_label col-sm-2 strong">Hex Color:</div>
				<div class="formbit_field col-sm-10">
					<input type="text" class="textbox" name="color" size="8" maxlength="8" value="" tabindex="3" />
				</div>
			</div>
			<div class="trow1 rowbit">
				<input type="submit" name="submit" class="button" value="{\$lang->predictions_add_team}" />
			</div>
		</div>
	</form>
ADD_TEAM;

	$add_game = <<<ADD_GAME
	<form method="POST" action="predictions.php">
		<input type="hidden" name="my_post_key" value="{\$mybb->post_code}" />
		<input type="hidden" name="action" value="add_game" />
		<div class="tborder">
			<div class="thead">Add a game</div>
			<div class="trow1 rowbit">
				<div class="formbit_label col-sm-2 strong">Season:</div>
				<div class="formbit_field col-sm-10">
					<select class="selectbox" name="season" value="" tabindex="1">
						<option value="2016">2016</option>
						<option value="2017">2017</option>
						<option value="2018" selected>2018</option>
						<option value="2019">2019</option>
						<option value="2020">2020</option>
					</select>
				</div>
			</div>
			<div class="trow1 rowbit">
				<div class="formbit_label col-sm-2 strong">Away Team:</div>
				<div class="formbit_field col-sm-10">
					<select class="selectbox" name="season" value="" tabindex="1">
						{\$teams}
					</select>
				</div>
			</div>
			<div class="trow1 rowbit">
				<input type="submit" name="submit" class="button" value="{\$lang->predictions_add_game}" />
			</div>
		</div>
	</form>
ADD_GAME;

	$latest_game = <<<LATEST_GAME
	<div class="row">
		<img style="width:24px;height:24px" src="{\$game['away_logo']}" />
		{\$game['away_team']}
		{\$game['away_score']}
		vs.
		<img style="width:24px;height:24px" src="{\$game['home_logo']}" />
		{\$game['home_team']}
		{\$game['home_score']}
		<a href="{\$mybb->settings['bburl']}/showthread.php?tid={\$game['thread_id']}">{\$game['num_predictions']} Predictions</a>
	</div>
LATEST_GAME;

	$thread_game = <<<THREAD_GAME
	<div class="row">
	<div class="col-md-4">
		<div class="row" style="display: flex;justify-content:  center;align-items: center;">
			<div class="col-md-9">
				<div class="row">
					<div class="span12 text-center team_name" data="away">
						{\$game['away_name']}
					</div>
				</div>
				<div class="row">
					<div class="span12 text-center">
						<img src="{\$mybb->asset_url}{\$game['away_logo']}" />
					</div>
				</div>
			</div>
			<div class="col-md-3 text-center">
				<span id="predictions_thread_game_away_score" style="font-family: Arial, Helvetica, sans-serif; font-weight: bold; font-size: 42px">{$game['away_score']}</span><br />
				<span style="color: #999999; font-family: Arial, Helvetica, sans-serif; font-size: 24px">{$game['away_actual']}</span>
			</div>
		</div>
	</div>
	<div class="col-md-1 text-center">
		<div class="row">
			<div class="span12 text-center team_name" data="user">&nbsp;
			</div>
		</div>
		<div class="row" style="display: flex;justify-content:  center;align-items: center;">
			<span  style="font-family: Arial, Helvetica, sans-serif; font-weight: bold; font-size: 42px">Vs.</span>
		</div>
	</div>
	<div class="col-md-4">
		<div class="row" style="display: flex;justify-content:  center;align-items: center;">
			<div class="col-md-3 text-center">
				<span id="predictions_thread_game_home_score"  style="font-family: Arial, Helvetica, sans-serif; font-weight: bold; font-size: 42px">{$game['home_score']}</span><br />
				<span style="color: #999999; font-family: Arial, Helvetica, sans-serif; font-size: 24px">{$game['home_actual']}</span>
			</div>
			<div class="col-md-9">
				<div class="row">
					<div class="span12 text-center team_name" data="home">
						{\$game['home_name']}
					</div>
				</div>
				<div class="row">
					<div class="span12 text-center">
						<img src="{\$mybb->asset_url}{\$game['home_logo']}" />
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="col-md-3">
		<div id="predictions_thread_game_interactive">
		<div class="row">
			{\$predictions_stats_panel}
			{\$predictions_predict_panel}
		</div>
		<div class="row">
			{\$predictions_toggle_button}
		</div>
		</div>
		<div id="predictions_thread_game_loading" style="display: none">
			<img src="{\$mybb->asset_url}/images/predictions/ajax-loader.gif" />
		</div>
	</div>
</div>
{$predictions_predict_script}
THREAD_GAME;

	$thread_game_stats = <<<THREAD_GAME_STATS
	<div id="predictions_stats_panel">
		<div class="row">
			<div class="col-md-6">{\$lang->predictions_took_the_over}:</div>
			<div class="col-md-6" id="predictions_stats_over">
				{\$stats['over']['user']} ({\$stats['over']['away']} - {\$stats['over']['home']})
			</div>
		</div>
		<div class="row">
			<div class="col-md-6">{\$lang->predictions_took_the_under}:</div>
			<div class="col-md-6" id="predictions_stats_under">
				{\$stats['under']['user']} ({\$stats['under']['away']} - {\$stats['under']['home']})
			</div>
		</div>
		<div class="row">
			<div class="col-md-6">{\$lang->predictions_redhot}:</div>
			<div class="col-md-6" id="predictions_stats_redhot">
				{\$stats['red_hot']['user']} ({\$stats['red_hot']['away']} - {\$stats['red_hot']['home']})
			</div>
		</div>
		<div class="row">
			<div class="col-md-6">{\$lang->predictions_shame}:</div>
			<div class="col-md-6" id="predictions_stats_over">
				{\$stats['shame']['user']} ({\$stats['shame']['away']} - {\$stats['shame']['home']})
			</div>
		</div>
	</div>
THREAD_GAME_STATS;

	$thread_game_form = <<<THREAD_GAME_FORM
	<div id="predictions_predict_panel" style="display:none">
		<form id="predictions_prefict_form">
		<input type="hidden" id="csrf_token" name="csrf_token" value="{\$mybb->post_code}">
		<input type="hidden" id="game_id" name="game_id" value="{\$game['game_id']}">
		<input type="hidden" id="user_id" name="user_id" value="{\$mybb->user['uid']}">
		<input type="hidden" id="prediction_id" name="prediction_id" value="{\$existing_prediction['prediction_id']}">
		<div class="form-row">
			<div class="form-group col-md-3">
      			<label for="predictions_away_score">{\$game['away_team']}</label>
      			<input class="form-control" id="predictions_away_score" name="predictions_away_score" required="true" placeholder="score" value="{\$existing_prediction['away_score']}">
			</div>
			<div class="form-group col-md-9">
				<label for="predictions_away_nickname">&nbsp;</label>
				<input type="text" class="form-control" id="predictions_away_nickname" name="predictions_away_nickname" placeholder="Clever nickname (optional)" size="40" maxlength="128" value="{\$existing_prediction['away_nickname']}" tabindex="1" />
			</div>
		</div>
		<div class="form-row">
			<div class="form-group col-md-3">
      			<label for="predictions_home_score">{\$game['home_team']}</label>
      			<input type="email" class="form-control" id="predictions_home_score" name="predictions_home_score" placeholder="score" value="{\$existing_prediction['home_score']}">
			</div>
			<div class="form-group col-md-9">
				<label for="predictions_home_nickname">&nbsp;</label>
				<input type="text" class="form-control" id="predictions_home_nickname" name="predictions_home_nickname" placeholder="Clever nickname (optional)" size="40" maxlength="128" value="{\$existing_prediction['home_nickname']}" tabindex="1" />
			</div>
		</div>
		<div class="row text-center">
			<a href="javascript:predictions_make_prediction();" class="button" id="predictions_predict_button"><span>{\$predictions_action_text}</span></a>
		</div>
		</form>
	</div>
THREAD_GAME_FORM;

	$prediction_box = <<<PREDICTION_BOX
	<div class="{\$bgcolor2} rowbit">

	<div class="formbit_label col-sm-2 strong">{\$lang->predictions_thread_title}:</div>
	  
	<div class="formbit_field col-sm-10"><label><input type="checkbox" class="checkbox" name="post_prediction" value="1" {\$post_prediction_checked} /><strong>{\$lang->predictions_thread_check}</strong></label><br />
				{\$lang->predictions_thread_games} <select name="post_gameid">{\$predictions_game_options}</select></div>
	  
	</div>
PREDICTION_BOX;

	$predictions_index = <<<PREDICTIONS_INDEX
	<form action="predictions.php">
		<input type="hidden" name="action" value="predictions_select_game" />
		<div class="tborder">
			<div class="thead">Select a game</div>
			<div class="trow1 rowbit">
				<div class="formbit_label col-sm-2 strong">Season:</div>
				<div class="formbit_field col-sm-10">
					2018
				</div>
			</div>
			<div class="trow1 rowbit">
				<div class="formbit_label col-sm-2 strong">Game:</div>
				<div class="formbit_field col-sm-10">
					<select class="selectbox" name="game_id" value="" tabindex="1">
						{\$predictions_game_options}
					</select>
				</div>
			</div>
			<div class="trow1 rowbit">
				<input type="submit" name="submit" class="button" value="{\$lang->predictions_see_game_results}" />
			</div>
		</div>
	</form>
	{\$predictions_update_actual_score}
	<br />
	{\$predictions_game_results}
PREDICTIONS_INDEX;

$predictions_list = <<<PREDICTIONS_LIST
<div class="tborder">
<div class="thead">Results</div>
<div class="trow1 rowbit">
	<div class="formbit_label col-sm-4 strong">{\$predictions_results_columns[0]}</div>
	<div class="formbit_label col-sm-4 strong">{\$predictions_results_columns[1]}</div>
	<div class="formbit_label col-sm-4 strong">{\$predictions_results_columns[2]}</div>
</div>
{\$predictions_predictions_results}
</div>
PREDICTIONS_LIST;

$predictions_row = <<<PREDICTIONS_ROW
<div class="trow1 rowbit">
	<div class="formbit_field col-sm-4">
		{\$prediction[0]}
	</div>
	<div class="formbit_field col-sm-4">
		{\$prediction[1]}
	</div>
	<div class="formbit_field col-sm-4">
		{\$prediction[2]}
	</div>
</div>
PREDICTIONS_ROW;

$update_actual_score = <<<UPDATE_ACTUAL
	<br />
	<form action="{\$mybb->settings['bburl']}/predictions.php" method="POST">
		<input type="hidden" name="action" value="predictions_update_actual" />
		<input type="hidden" name="csrf_token" value="{\$mybb->post_code}" />
		<input type="hidden" name="game_id" value="{\$game_id}" />
		<input type="hidden" name="team_is_home" value={\$team_is_home} />
		<div class="tborder">
			<div class="thead">Update Actual Score</div>
			<div class="trow1 rowbit">
				<div class="formbit_label col-sm-2 strong">{\$away_team}:</div>
				<div class="formbit_field col-sm-10">
				<input type="text" class="textbox" name="away_actual" size="4" maxlength="8" value="{\$away_actual}" tabindex="1" />
				</div>
			</div>
			<div class="trow1 rowbit">
				<div class="formbit_label col-sm-2 strong">{\$home_team}:</div>
				<div class="formbit_field col-sm-10">
				<input type="text" class="textbox" name="home_actual" size="4" maxlength="8" value="{\$home_actual}" tabindex="2" />
				</div>
			</div>
			<div class="trow1 rowbit">
				<input type="submit" name="submit" class="button" value="{\$lang->predictions_update_actual_score}" />
			</div>
		</div>
	</form>
UPDATE_ACTUAL;

	// Add a new template (hello_index) to our global templates (sid = -1)
	$templatearray = array(
	'prediction_box' => $prediction_box,
	'latest_game' => $latest_game,
	'thread_game' => $thread_game,
	'thread_game_stats' => $thread_game_stats,
	'thread_game_form' => $thread_game_form,
	'toggle_button' => '<a href="javascript:predictions_toggle_panel();" class="button" id="predictions_toggle_panel_button"><span id="predictions_action_text">{$predictions_action_text}</span></a>',
	'add_team' => $add_team,
	'add_game' => $add_game,
	'index' => $predictions_index,
	'list' => $predictions_list,
	'row' => $predictions_row,
	'update_actual_score' => $update_actual_score
	);

	$group = array(
		'prefix' => $db->escape_string('predictions'),
		'title' => $db->escape_string('Predictions')
	);

	// Update or create template group:
	$query = $db->simple_select('templategroups', 'prefix', "prefix='{$group['prefix']}'");

	if($db->fetch_field($query, 'prefix'))
	{
		$db->update_query('templategroups', $group, "prefix='{$group['prefix']}'");
	}
	else
	{
		$db->insert_query('templategroups', $group);
	}

	// Query already existing templates.
	$query = $db->simple_select('templates', 'tid,title,template', "sid=-2 AND (title='{$group['prefix']}' OR title LIKE '{$group['prefix']}=_%' ESCAPE '=')");

	$templates = $duplicates = array();

	while($row = $db->fetch_array($query))
	{
		$title = $row['title'];
		$row['tid'] = (int)$row['tid'];

		if(isset($templates[$title]))
		{
			// PluginLibrary had a bug that caused duplicated templates.
			$duplicates[] = $row['tid'];
			$templates[$title]['template'] = false; // force update later
		}
		else
		{
			$templates[$title] = $row;
		}
	}

	// Delete duplicated master templates, if they exist.
	if($duplicates)
	{
		$db->delete_query('templates', 'tid IN ('.implode(",", $duplicates).')');
	}

	// Update or create templates.
	foreach($templatearray as $name => $code)
	{
		if(strlen($name))
		{
			$name = "predictions_{$name}";
		}
		else
		{
			$name = "predictions";
		}

		$template = array(
			'title' => $db->escape_string($name),
			'template' => $db->escape_string($code),
			'version' => 1,
			'sid' => -2,
			'dateline' => TIME_NOW
		);

		// Update
		if(isset($templates[$name]))
		{
			if($templates[$name]['template'] !== $code)
			{
				// Update version for custom templates if present
				$db->update_query('templates', array('version' => 0), "title='{$template['title']}'");

				// Update master template
				$db->update_query('templates', $template, "tid={$templates[$name]['tid']}");
			}
		}
		// Create
		else
		{
			$db->insert_query('templates', $template);
		}

		// Remove this template from the earlier queried list.
		unset($templates[$name]);
	}

	// Remove no longer used templates.
	foreach($templates as $name => $row)
	{
		$db->delete_query('templates', "title='{$db->escape_string($name)}'");
	}

	// Settings group array details
	$group = array(
		'name' => 'predictions',
		'title' => $db->escape_string($lang->setting_group_predictions),
		'description' => $db->escape_string($lang->setting_group_predictions_desc),
		'isdefault' => 0
	);

	// Check if the group already exists.
	$query = $db->simple_select('settinggroups', 'gid', "name='predictions'");

	if($gid = (int)$db->fetch_field($query, 'gid'))
	{
		// We already have a group. Update title and description.
		$db->update_query('settinggroups', $group, "gid='{$gid}'");
	}
	else
	{
		// We don't have a group. Create one with proper disporder.
		$query = $db->simple_select('settinggroups', 'MAX(disporder) AS disporder');
		$disporder = (int)$db->fetch_field($query, 'disporder');

		$group['disporder'] = ++$disporder;

		$gid = (int)$db->insert_query('settinggroups', $group);
	}

	// Deprecate all the old entries.
	$db->update_query('settings', array('description' => 'PREDICTIONSDELETEMARKER'), "gid='{$gid}'");

	// add settings
	$settings = array(
	'enablethread'	=> array(
		'optionscode'	=> 'yesno',
		'value'			=> 1
	),
	'enablegame' => array(
		'optionscode' => 'yesno',
		'value' => 1
	),
	'latestgame'	=> array(
		'optionscode'	=> 'yesno',
		'value'			=> 1
	));

	$disporder = 0;

	// Create and/or update settings.
	foreach($settings as $key => $setting)
	{
		// Prefix all keys with group name.
		$key = "predictions_{$key}";

		$lang_var_title = "setting_{$key}";
		$lang_var_description = "setting_{$key}_desc";

		$setting['title'] = $lang->{$lang_var_title};
		$setting['description'] = $lang->{$lang_var_description};

		// Filter valid entries.
		$setting = array_intersect_key($setting,
			array(
				'title' => 0,
				'description' => 0,
				'optionscode' => 0,
				'value' => 0,
		));

		// Escape input values.
		$setting = array_map(array($db, 'escape_string'), $setting);

		// Add missing default values.
		++$disporder;

		$setting = array_merge(
			array('description' => '',
				'optionscode' => 'yesno',
				'value' => 0,
				'disporder' => $disporder),
		$setting);

		$setting['name'] = $db->escape_string($key);
		$setting['gid'] = $gid;

		// Check if the setting already exists.
		$query = $db->simple_select('settings', 'sid', "gid='{$gid}' AND name='{$setting['name']}'");

		if($sid = $db->fetch_field($query, 'sid'))
		{
			// It exists, update it, but keep value intact.
			unset($setting['value']);
			$db->update_query('settings', $setting, "sid='{$sid}'");
		}
		else
		{
			// It doesn't exist, create it.
			$db->insert_query('settings', $setting);
			// Maybe use $db->insert_query_multiple somehow
		}
	}

	// Delete deprecated entries.
	$db->delete_query('settings', "gid='{$gid}' AND description='PREDICTIONSDELETEMARKER'");

	// This is required so it updates the settings.php file as well and not only the database - they must be synchronized!
	rebuild_settings();

	// Include this file because it is where find_replace_templatesets is defined
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

	// Edit the forumdisplay template and add our variable to above {$threadslist}
	find_replace_templatesets('forumdisplay', '#'.preg_quote('{$threadslist}').'#', "{\$predictions_latest_game}\n{\$threadslist}");
	
	// Edit the newthread template and add our variable to below {$pollbox}
	find_replace_templatesets('newthread', '#'.preg_quote('{$pollbox}').'#', "{\$pollbox}\n{\$predictions_prediction_box}");
	
	// Edit the showthread template and add our variable to below {$pollbox}
	find_replace_templatesets('showthread', '#'.preg_quote('{$pollbox}').'#', "{\$pollbox}\n{\$predictions_thread_game}");
}

/*
 * _deactivate():
 *    Called whenever a plugin is deactivated. This should essentially 'hide' the plugin from view
 *    by removing templates/template changes etc. It should not, however, remove any information
 *    such as tables, fields etc - that should be handled by an _uninstall routine. When a plugin is
 *    uninstalled, this routine will also be called before _uninstall() if the plugin is active.
*/
function predictions_deactivate()
{
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

	// remove template edits
	find_replace_templatesets('forumdisplay', '#'.preg_quote('{$predictions_latest_game}').'#', '');
	find_replace_templatesets('newthread', '#'.preg_quote('{$predictions_prediction_box}').'#', '');
	find_replace_templatesets('showthread', '#'.preg_quote('{$predictions_thread_game}').'#', '');
}

/*
 * _install():
 *   Called whenever a plugin is installed by clicking the 'Install' button in the plugin manager.
 *   If no install routine exists, the install button is not shown and it assumed any work will be
 *   performed in the _activate() routine.
*/
function predictions_install()
{
	global $db;

	// Create our table collation
	$collation = $db->build_create_table_collation();

	if(!$db->table_exists('predictions_forum'))
	{
		switch($db->type)
		{
			case "pgsql":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."hello_messages (
					mid serial,
					message varchar(100) NOT NULL default '',
					PRIMARY KEY (mid)
				);");
				break;
			case "sqlite":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."predictions_forum (
                    forum_id INTEGER PRIMARY KEY
				);");
				break;
			default:
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."predictions_forum (
					forum_id INTEGER PRIMARY KEY,
					FOREIGN KEY (forum_id)
						REFERENCES ".TABLE_PREFIX."forums(fid)
				) ENGINE=MyISAM{$collation};");
				break;
		}
	}

    if(!$db->table_exists('predictions_conference'))
	{
		switch($db->type)
		{
			case "pgsql":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."hello_messages (
					mid serial,
					message varchar(100) NOT NULL default '',
					PRIMARY KEY (mid)
				);");
				break;
			case "sqlite":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."predictions_conference (
                    tid INTEGER PRIMARY KEY,
                    name varchar(128) NOT NULL,
                    logo varchar(256) NOT NULL,
                    color varchar(8) NOT NULL
				);");
				break;
			default:
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."predictions_conference (
					conference_id int unsigned NOT NULL auto_increment,
					name varchar(64) NOT NULL,
					PRIMARY KEY (conference_id)
				) ENGINE=MyISAM{$collation};");
				break;
		}
	}
	
    if(!$db->table_exists('predictions_team'))
	{
		switch($db->type)
		{
			case "pgsql":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."hello_messages (
					mid serial,
					message varchar(100) NOT NULL default '',
					PRIMARY KEY (mid)
				);");
				break;
			case "sqlite":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."predictions_team (
                    tid INTEGER PRIMARY KEY,
                    name varchar(128) NOT NULL,
                    logo varchar(256) NOT NULL,
                    color varchar(8) NOT NULL
				);");
				break;
			default:
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."predictions_team (
					team_id int unsigned NOT NULL auto_increment,
					conference_id INTEGER NOT NULL,
					name varchar(64) NOT NULL,
					abbreviation varchar(8) NOT NULL,
					mascot varchar(64) NOT NULL,
                    logo varchar(256) NOT NULL,
                    color varchar(8) NOT NULL,
					PRIMARY KEY (team_id),
					FOREIGN KEY (conference_id)
						REFERENCES ".TABLE_PREFIX."predictions_conference(conference_id)
				) ENGINE=MyISAM{$collation};");
				break;
		}
    }

	if(!$db->table_exists('predictions_game'))
	{
		switch($db->type)
		{
			case "pgsql":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."hello_messages (
					mid serial,
					message varchar(100) NOT NULL default '',
					PRIMARY KEY (mid)
				);");
				break;
			case "sqlite":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."predictions_game (
					gid INTEGER PRIMARY KEY,
					tid INTEGER NULL,
                    season INTEGER NOT NULL,
                    home_tid INTEGER NOT NULL,
                    away_tid INTEGER NOT NULL,
                    prediction_time DATETIME_INT NOT NULL,
					game_time DATETIME_INT NOT NULL,
					FOREIGN KEY(tid) REFERENCES ".TABLE_PREFIX."posts(tid),
                    FOREIGN KEY(home_tid) REFERENCES ".TABLE_PREFIX."predictions_team(tid),
                    FOREIGN KEY(away_tid) REFERENCES ".TABLE_PREFIX."predictions_team(tid)
				);");
				break;
			default:
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."predictions_game (
					game_id int unsigned NOT NULL auto_increment,
					thread_id INTEGER NULL,
					season INTEGER NOT NULL,
                    home_team_id INTEGER NOT NULL,
                    away_team_id INTEGER NOT NULL,
                    prediction_time DATETIME NOT NULL,
					game_time DATETIME NOT NULL,
					home_score INTEGER NULL,
					away_score INTEGER NULL,
					PRIMARY KEY (game_id),
					FOREIGN KEY (thread_id)
						REFERENCES ".TABLE_PREFIX."threads(tid),
					FOREIGN KEY (home_team_id)
						REFERENCES ".TABLE_PREFIX."predictions_team(team_id),
					FOREIGN KEY (away_team_id)
						REFERENCES ".TABLE_PREFIX."predictions_team(team_id)
				) ENGINE=MyISAM{$collation};");
				break;
		}
    }
    
	// Create predictions table if it doesn't exist already
	if(!$db->table_exists('predictions_prediction'))
	{
		switch($db->type)
		{
			case "pgsql":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."hello_messages (
					mid serial,
					message varchar(100) NOT NULL default '',
					PRIMARY KEY (mid)
				);");
				break;
			case "sqlite":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."predictions_prediction (
                    pid INTEGER PRIMARY KEY,
                    gid INTEGER NOT NULL,
                    home_score INTEGER NOT NULL,
                    home_nickname varchar(128),
                    away_score INTEGER NOT NULL,
                    away_nickname varchar(128),
                    points INTEGER NULL,
                    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(gid) REFERENCES ".TABLE_PREFIX."predictions_game(gid)
				);");
				break;
			default:
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."predictions_prediction (
					prediction_id int unsigned NOT NULL auto_increment,
					game_id INTEGER NOT NULL,
					user_id INTEGER NOT NULL,
					home_score INTEGER NOT NULL,
                    home_nickname varchar(128),
                    away_score INTEGER NOT NULL,
                    away_nickname varchar(128),
                    points INTEGER NULL,
                    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (prediction_id),
					FOREIGN KEY (game_id)
						REFERENCES ".TABLE_PREFIX."predictions_game(game_id),
					FOREIGN KEY (user_id)
						REFERENCES ".TABLE_PREFIX."users(uid),
					UNIQUE KEY game_user_unique (game_id, user_id)
				) ENGINE=MyISAM{$collation};");
				break;
		}
	}
	
	predictions_insert_data();
}

/*
 * _is_installed():
 *   Called on the plugin management page to establish if a plugin is already installed or not.
 *   This should return TRUE if the plugin is installed (by checking tables, fields etc) or FALSE
 *   if the plugin is not installed.
*/
function predictions_is_installed()
{
	global $db;

	// If the table exists then it means the plugin is installed because we only drop it on uninstallation
	return $db->table_exists('predictions_prediction');
}

/*
 * _uninstall():
 *    Called whenever a plugin is to be uninstalled. This should remove ALL traces of the plugin
 *    from the installation (tables etc). If it does not exist, uninstall button is not shown.
*/
function predictions_uninstall()
{
	global $db, $mybb;

	if($mybb->request_method != 'post')
	{
		global $page, $lang;
		$lang->load('predictions');

		$page->output_confirm_action('index.php?module=config-plugins&action=deactivate&uninstall=1&plugin=predictions', $lang->predictions_uninstall_message, $lang->predictions_uninstall);
	}

	// Delete template groups.
	$db->delete_query('templategroups', "prefix='predictions'");

	// Delete templates belonging to template groups.
	$db->delete_query('templates', "title='predictions' OR title LIKE 'predictions_%'");

	// Delete settings group
	$db->delete_query('settinggroups', "name='predictions'");

	// Remove the settings
	$db->delete_query('settings', "name IN ('predictions_enablethread','predictions_latestgame', 'predicitons_enablegame')");

	// This is required so it updates the settings.php file as well and not only the database - they must be synchronized!
	rebuild_settings();

	// Drop tables if desired
	if(!isset($mybb->input['no']))
	{
        $db->drop_table('predictions_prediction');
        $db->drop_table('predictions_game');
		$db->drop_table('predictions_team');
		$db->drop_table('predictions_conference');
		$db->drop_table('predictions_forum');
	}
}

/*
 * Loads the settings language strings.
*/
function predictions_settings()
{
	global $lang;

	// Load our language file
	$lang->load('predictions');
}

function predictions_set_latest_game() {
	global $db, $latest_game;

	$query = $db->query("
		SELECT a.abbreviation as away_team, a.logo as away_logo, h.abbreviation as home_team, h.logo as home_logo, ROUND(AVG(p.away_score)) as away_score, ROUND(AVG(p.home_score)) as home_score, COUNT(p.prediction_id) as num_predictions, g.thread_id
		FROM ".TABLE_PREFIX."predictions_game g
		INNER JOIN ".TABLE_PREFIX."predictions_team a ON (a.team_id=g.away_team_id)
		INNER JOIN ".TABLE_PREFIX."predictions_team h ON (h.team_id=g.home_team_id)
		LEFT OUTER JOIN ".TABLE_PREFIX."predictions_prediction p ON (p.game_id=g.game_id)
		WHERE g.prediction_time < NOW() and g.game_time > NOW() and g.thread_id IS NOT NULL
		GROUP BY a.abbreviation, a.logo, h.abbreviation, h.logo, g.game_time, g.thread_id
		ORDER BY g.game_time ASC
		LIMIT 1
	");
	$row = $db->fetch_array($query);
	if(is_null($row)) {
		return;
	}

	$latest_game = $row;
	if (is_null($latest_game['home_score'])) {
		$latest_game["home_score"] = "?";
		$latest_game["away_score"] = "?";
	}
}

class ThreadScore {
    var $thread_id;
    var $game_id;
	var $away_score;
	var $home_score;
	var $away_team;
	var $home_team;
	var $winners;
	var $winning_points;

    function __construct($row) {
		$this->prediction_id = $row['thread_id'];
		$this->game_id = $row['game_id'];
		$this->away_score = $row['away_score'];
		$this->home_score = $row['home_score'];
		$this->away_team = $row['away_team'];
		$this->home_team = $row['home_team'];
		$this->winners = array();
		$this->winning_points = 0;
    }

    function add_score($score) {
		$int_score = (int)$score['score'];
		if ($int_score >= $this->winning_points) {
			array_push($this->winners, $score['winner']);
			$this->winning_points = $int_score;
		}
    }
}

function predictions_thread_show_score()
{
	global $thread, $latest_game, $tids, $db;

	global $thread_games;
	
	if(!isset($thread_games)) {
		$query = $db->query("
		select * from(
			select g.thread_id, g.game_id, round(avg(p.away_score)) as away_score, a.abbreviation as away_team, round(avg(p.home_score)) as home_score, h.abbreviation as home_team, null as score, null as winner
			from ".TABLE_PREFIX."predictions_prediction p
			inner join ".TABLE_PREFIX."predictions_game g on g.game_id = p.game_id and p.points is null
			inner join ".TABLE_PREFIX."predictions_team a on g.away_team_id = a.team_id
			inner join ".TABLE_PREFIX."predictions_team h on g.home_team_id = h.team_id
			group by g.game_id, g.thread_id, a.abbreviation, h.abbreviation
			UNION 
			select g.thread_id, g.game_id, g.away_score, g.home_score, a.abbreviation as away_team, h.abbreviation as home_team, max(p.points) as score, u.username as winner
			from ".TABLE_PREFIX."predictions_prediction p
			inner join ".TABLE_PREFIX."predictions_game g on g.game_id = p.game_id and p.points is not null
			inner join ".TABLE_PREFIX."users u on p.user_id = u.uid
			inner join ".TABLE_PREFIX."predictions_team a on g.away_team_id = a.team_id
			inner join ".TABLE_PREFIX."predictions_team h on g.home_team_id = h.team_id
			group by g.thread_id, g.game_id, g.away_score, g.home_score, a.abbreviation, h.abbreviation, u.username) as raw
		WHERE thread_id in (" . $tids . ")
		ORDER BY thread_id, score desc
		");
		global $thread_games;
		$thread_games = array();
		while($row = $db->fetch_array($query)) {
			if(!array_key_exists($row['thread_id'], $thread_games)) {
				$thread_games[$row['thread_id']] = new ThreadScore($row);
			}
			if(!is_null($row['winner'])) {
				$thread_games[$row['thread_id']]->add_score($row);
			}
		}
	}

	if(array_key_exists($thread['tid'], $thread_games)) {
		$extra_thread_title = "";
		$thread_game_info = $thread_games[$thread['tid']];
		if (count($thread_game_info->winners) > 1) {
			$first = true;
			$extra_thread_title = " (winners: ";
			foreach($thread_game_info->winners as $winner) {
				if($first) {
					$extra_thread_title .= $winner;
					$first = false;
				} else {
					$extra_thread_title .= ", " . $winner;
				}
			}
			$extra_thread_title .= ")";
		} else if (count($thread_game_info->winners) > 0) {
			$extra_thread_title = " (winner: " . $thread_game_info->winners[0] . ")";
		} else {
			$extra_thread_title = " (".$thread_game_info->away_team." ".$thread_game_info->away_score.", ".$thread_game_info->home_team." ".$thread_game_info->home_score.")";
		}
		$thread["subject"] .= $extra_thread_title;
	}
	
}
/*
 * Displays the current game if there's one pending
 */
function predictions_forum_show_score()
{
	global $settings, $foruminfo, $db, $latest_game;

	// Only run this function is the setting is set to yes
	if($settings['predictions_latestgame'] == 0)
	{
		return;
	}
	// Also check that this forum is eligible for showing the latest game
	$check = $db->simple_select("predictions_forum", "forum_id", "forum_id=".$foruminfo['fid']);
	if($check->num_rows == 0) {
		return;
	}
	global $lang, $templates;

	global $predictions_latest_game;

	// Load our language file
	if(!isset($lang->predictions))
	{
		$lang->load('predictions');
	}

	$game = $latest_game;
	$predictions_latest_game = eval($templates->render('predictions_latest_game'));

}

function console_log( $data ){
	echo '<script>';
	echo 'console.log('. json_encode( $data ) .')';
	echo '</script>';
  }


function predictions_add_team()
{
	global $mybb;

	// If we're not running the 'add_team' action as specified in our form, get out of there.
	if($mybb->get_input('action') != 'add_team')
	{
		return;
	}

	// Only accept POST
	if($mybb->request_method != 'post')
	{
		error_no_permission();
	}

	global $lang;

	// Correct post key? This is important to prevent CSRF
	verify_post_check($mybb->get_input('my_post_key'));

	// Load our language file
	$lang->load('predictions');

	$name = trim($mybb->get_input('name'));
	$logo = trim($mybb->get_input('logo'));
	$color = trim($mybb->get_input('color'));
	
	// Name cannot be empty
	if(!$name || my_strlen($name) > 128)
	{
		error($lang->predictions_name_empty);
	}

	// Logo cannot be empty
	if(!$logo || my_strlen($logo) > 256)
	{
		error($lang->predictions_logo_empty);
	}

	// Color cannot be empty
	if(!$color || my_strlen($color) > 8)
	{
		error($lang->predictions_color_empty);
	}

	global $db;

	// Escape input data
	$name = $db->escape_string($name);
	$logo = $db->escape_string($name);
	$color = $db->escape_string($name);

	// Insert into database
	$db->insert_query('predictions_team', array('name' => $name, 'logo' => $logo, 'color' => $color));

	// Redirect to index.php with a message
	redirect('predictions.php', $lang->predictions_team_added);
}


function predictions_prediction_box()
{
	
	global $mybb, $lang, $templates, $bgcolor2, $forum, $settings, $db;

	// Only run this function is the setting is set to yes
	if($settings['predictions_enablethread'] == 0)
	{
		return;
	}
	// Also check that this forum is eligible for showing the latest game
	$check = $db->simple_select("predictions_forum", "forum_id", "forum_id=".$forum['fid']);
	if($check->num_rows == 0) {
		return;
	}


	// Load our language file
	if(!isset($lang->predictions))
	{
		$lang->load('predictions');
	}

	static $pb;

	// Only retreive the latest game from the database if it was not retrieved already
	if(!isset($pb))
	{
		// Retreive eligible games
		$query = $db->query("
			SELECT g.game_id, a.name as away_name, h.name as home_name, g.game_time
			FROM ".TABLE_PREFIX."predictions_game g
			INNER JOIN ".TABLE_PREFIX."predictions_team a ON (a.team_id=g.away_team_id)
			INNER JOIN ".TABLE_PREFIX."predictions_team h ON (h.team_id=g.home_team_id)
			WHERE g.prediction_time < NOW() and g.game_time > NOW() AND g.thread_id IS NULL
			ORDER BY g.game_time ASC
		");
		$predictions_game_options = "";
		while($row = $db->fetch_array($query)) {
			$predictions_game_options .= '<option value="'.$row["game_id"].'">'.$row["away_name"].' at '.$row["home_name"].'</option>';
		}

		if($predictions_game_options == "") {
			return;
		}
		
		$pb = eval($templates->render('predictions_prediction_box'));
	}
	global $predictions_prediction_box;
	$predictions_prediction_box = $pb;
}

function predictions_prediction_thread()
{
	global $mybb, $tid, $db;
	if($mybb->get_input('action') == 'do_newthread')
	{
		$post_prediction = $mybb->get_input('post_prediction');
		if($post_prediction) {
			$gid = $mybb->get_input('post_gameid');
			$db->update_query("predictions_game", array('thread_id' => $tid), "game_id=".$gid);
		}
	}
}

function predictions_insert_data()
{
	global $db;
	require MYBB_ROOT.'inc/config.php';
	
	require_once MYBB_ROOT."admin/modules/predictions/mysql_db_inserts.php";
	foreach($inserts as $val)
	{
		$val = preg_replace('#mybb_(\S+?)([\s\.,]|$)#', $config['database']['table_prefix'].'\\1\\2', $val);
		$db->query($val);
	}

}

function predictions_calculate_game_stats($db, $uid, $game_id, $is_at_home) {
	$count = 0;
	$home_total = 0;
	$away_total = 0;
	$max = 0;
	$max_details = null;
	$min = null;
	$min_details = null;
	$max_margin_home = 0;
	$max_margin_home_details = null;
	$max_margin_away = 0;
	$max_margin_away_details = null;
	$query = $db->query("
		SELECT p.prediction_id, u.uid, u.username, p.away_score, p.away_nickname, p.home_score, p.home_nickname
		from ".TABLE_PREFIX."predictions_prediction p
		INNER JOIN ".TABLE_PREFIX."users u ON (p.user_id = u.uid)
		WHERE p.game_id=".$game_id
	);
	$user_prediction = null;
	$nicknames = array();
	while($prediction = $db->fetch_array($query)) {
		if($prediction['uid'] == $uid) {
			$user_prediction = $prediction;

		}
		$home_total += (int)$prediction['home_score'];
		$away_total += (int)$prediction['away_score'];
		$total = (int)$prediction['home_score'] + (int)$prediction['away_score'];
		if($total > $max) {
			$max = $total;
			$max_details = array(
				"user" => $prediction['username'],
				"home" => $prediction['home_score'],
				"away" => $prediction['away_score']
			);
		}
		if(($total < $min) || is_null($min)) {
			$min = $total;
			$min_details = array(
				"user" => $prediction['username'],
				"home" => $prediction['home_score'],
				"away" => $prediction['away_score']
			);
		}
		$home_margin = $prediction['home_score'] - $prediction['away_score'];
		if($home_margin > $max_margin_home) {
			$max_margin_home = $home_margin;
			$max_margin_home_details = array(
				"user" => $prediction['username'],
				"home" => $prediction['home_score'],
				"away" => $prediction['away_score']
			);
		}
		$away_margin = $prediction['away_score'] - $prediction['home_score'];
		if($away_margin > $max_margin_away) {
			$max_margin_away = $away_margin;
			$max_margin_away_details = array(
				"user" => $prediction['username'],
				"home" => $prediction['home_score'],
				"away" => $prediction['away_score']
			);
		}
		if($prediction['home_nickname'] != "") {
			array_push($nicknames, array(
				"home" => $prediction['home_nickname'],
				"away" => $prediction['away_nickname'],
				"user" => $prediction['username']
			));
		}
		$count += 1;
	}
	$home_avg = "?";
	$away_avg = "?";
	if($count > 0) {
		$home_avg = round($home_total / $count);
		$away_avg = round($away_total / $count);
	}
	$output = array(
		"count" => $count,
		"home_avg" => $home_avg,
		"away_avg" => $away_avg,
		"over" => $max_details,
		"under" => $min_details,
		"user_prediction" => $user_prediction,
		"nicknames" => $nicknames
	);

	if($is_at_home) {
		$output["red_hot"] = $max_margin_home_details;
		$output["shame"] = $max_margin_away_details;
	} else {
		$output["red_hot"] = $max_margin_away_details;
		$output["shame"] = $max_margin_home_details;
	}
	return $output;
}

function predictions_thread_game()
{
	global $settings, $thread, $db, $lang, $templates, $user, $mybb;

	// Only run this function is the setting is set to yes
	if($settings['predictions_enablegame'] == 0)
	{
		return;
	}

	// Load our language file
	if(!isset($lang->predictions))
	{
		$lang->load('predictions');
	}

	static $thread_game;

	// Only retreive the latest game from the database if it was not retrieved already
	if(!isset($thread_game))
	{
		// Retreive the game for the current thread
		$query = $db->query("
			SELECT g.game_id, CASE WHEN g.game_time > NOW() THEN true ELSE false END AS is_predictable, g.home_score as home_actual, g.away_score as away_actual, a.team_id as away_id, a.name as away_name, a.logo as away_logo, a.abbreviation as away_team, h.team_id as home_id, h.name as home_name, h.logo as home_logo, h.abbreviation as home_team
			FROM ".TABLE_PREFIX."predictions_game g
			INNER JOIN ".TABLE_PREFIX."predictions_team a ON (a.team_id=g.away_team_id)
			INNER JOIN ".TABLE_PREFIX."predictions_team h ON (h.team_id=g.home_team_id)
			WHERE g.thread_id=".$thread['tid']
		);
		$game = $db->fetch_array($query);
		if(is_null($game)) {
			// if there's no game associated with this thread, we're done.
			return;
		}
		
		// Currently hardcoding stanford as the "main" team
		$stanford_id = 151;

		// Get the current logged in user_id (0 if anonymous)
		$user_id = $mybb->user['uid'];

		// Get the stats dictionary
		$stats = predictions_calculate_game_stats($db, $mybb->user['uid'], $game["game_id"], $stanford_id == $game["home_id"]);
		
		// render the stats panel (blank if there aren't any predictions yet)
		if($stats["count"] == 0) {
			$predictions_stats_panel = '<div id="predictions_stats_panel"><i>Not enough predictions to show stats</i></div>';
		} else {
			$predictions_stats_panel = eval($templates->render('predictions_thread_game_stats'));
		}

		// Update the scores values in the game dictionary as calculated in the stats
		$game["home_score"] = $stats["home_avg"];
		$game["away_score"] = $stats["away_avg"];

		if($user_id == 0 || !$game["is_predictable"]) {
			// no need to have a predictions panel as anonymous users or games in the past can't make predictions
			$predictions_toggle_button = "";
			$predictions_predict_panel = "";
		} else {
			$predictions_action_text = "";
			// determine whether there is an existing prediction that should be updated			
			if(is_null($stats["user_prediction"])) {
				$predictions_action_text = "Make Prediction";
			} else {
				$predictions_action_text = "Update Prediction";
			}
			$predictions_toggle_button = eval($templates->render('predictions_toggle_button'));

			// render our form
			$existing_prediction = $stats["user_prediction"];
			$predictions_predict_panel = eval($templates->render('predictions_thread_game_form'));

		}

		$nicknames_js = 'var nicknames = [{"home": "' . $game["home_name"] . '", "away": "' . $game["away_name"] . '", "user": ""}';
		foreach($stats["nicknames"] as &$nickname) {
			$nicknames_js .= ',{"home": "' . $nickname["home"]. '", "away": "'. $nickname["away"] . '", "user": "' . $nickname["user"] . '"}';
		}
		$nicknames_js .= '];';
		// This Javascript will handle the client side validation and ajax submission
		$predictions_predict_script = <<<PREDICT_SCRIPT
	<script language="javascript">
	$nicknames_js
	var nickname_index = 0;

	$(document).ready(function(){
		if(nicknames.length > 1) {
			setTimeout(next_nickname, 4000);
		}
	});

	function next_nickname() {
		nickname_index++;
		if(nickname_index >= nicknames.length) {
			nickname_index = 0;
		}
		$(".team_name").fadeOut(1000, function() {
			if(nicknames[nickname_index][$(this).attr("data")] == "") {
				$(this).html("&nbsp;");
			} else {
				$(this).text(nicknames[nickname_index][$(this).attr("data")]);
			}
			$(this).fadeIn(1000, function() {
				if($(this).attr("data") == "user") {
					setTimeout(next_nickname, 4000);
				}
			});
		});
	}

	function predictions_toggle_panel() {
		if ($("#predictions_predict_panel").is(":hidden")) {
			$("#predictions_stats_panel").hide();
			$("#predictions_predict_panel").show();
			$("#predictions_action_text").text("Show Stats");
		} else {
			$("#predictions_predict_panel").hide();
			$("#predictions_stats_panel").show();
			$("#predictions_action_text").text("$predictions_action_text");
		}
	}

	function update_statline(stats, div_id, dict_key) {
		if (stats[dict_key] == null) {
			$(div_id).text("");
		} else {
			$(div_id).text(stats[dict_key]["user"] + " (" + stats[dict_key]["away"] + " - " + stats[dict_key]["home"] + ")");
		}
	}

	function predictions_make_prediction() {
		var prediction_id = parseInt($("#prediction_id").val().trim());
		var home_score = parseInt($("#predictions_home_score").val().trim());
		var away_score = parseInt($("#predictions_away_score").val().trim());
		var home_nickname = $("#predictions_home_nickname").val().trim();
		var away_nickname = $("#predictions_away_nickname").val().trim();

		var valid = true;
		if (isNaN(home_score)) { 
			$("#predictions_home_score").addClass("error");
			valid = false;
		} else {
			$("#predictions_home_score").removeClass("error");
		}

		if (isNaN(away_score)) { 
			$("#predictions_away_score").addClass("error");
			valid = false;
		} else {
			$("#predictions_away_score").removeClass("error");
		}

		if(valid) {
			var post_data = {
				csrf_token: $("#csrf_token").val(),
				action: "predictions_make_prediction",
				home_score: home_score,
				away_score: away_score,
				home_nickname: home_nickname,
				away_nickname: away_nickname,
				user_id: $("#user_id").val(),
				game_id: $("#game_id").val()
			};
			if(!isNaN(prediction_id)) {
				post_data["prediction_id"] = prediction_id;
			};
			$("#predictions_thread_game_interactive").hide();
			$("#predictions_thread_game_loading").show();
			$.post("{$mybb->settings['bburl']}/xmlhttp.php", post_data, function( data ) {
				$("#predictions_thread_game_away_score").text(data["away_avg"]);
				$("#predictions_thread_game_home_score").text(data["home_avg"]);
				if("error" in data) {
					$("#predictions_stats_panel").replaceWith(data["error"]);
					$("#predictions_toggle_panel_button").hide();
				}
				else if("stats_html" in data) {
					$("#predictions_stats_panel").replaceWith(data["stats_html"]);
					$("#predictions_toggle_panel_button").text("Update Prediction");
				} else {
					update_statline(data, "#predictions_stats_over", "over");
					update_statline(data, "#predictions_stats_under", "under");
					update_statline(data, "#predictions_stats_redhot", "red_hot");
					update_statline(data, "#predictions_stats_shame", "shame");
				}
				$("#predictions_thread_game_loading").hide();
				$("#predictions_thread_game_interactive").show();
				predictions_toggle_panel();
			});
		}
	}

	</script>
PREDICT_SCRIPT;

		

		$thread_game = eval($templates->render('predictions_thread_game'));

	}
	global $predictions_thread_game;
	$predictions_thread_game = $thread_game;
}

function predictions_ajax()
{
	global $settings, $thread, $db, $lang, $templates, $user;

	// Only run this function is the setting is set to yes
	if($settings['predictions_enablegame'] == 0)
	{
		return;
	}

}

function predictions_ajax_action()
{
	global $mybb, $charset, $db, $templates;

	$stats = null;
    if($mybb->get_input('action') == 'predictions_make_prediction')
    {
		if($mybb->request_method != 'post') {
			return;
		}
		// Prevent CSRF
		verify_post_check($mybb->get_input('csrf_token'));

		$existing_prediction_id = $mybb->get_input("prediction_id");
		
		// Update or create template group:
		$query = $db->query("select case when game_time > NOW() then true else false end as is_eligible from mybb_predictions_game where game_id=".$mybb->get_input('game_id'));

		if($db->fetch_field($query, 'is_eligible'))
		{
			$args = array(
				'game_id' => $mybb->get_input('game_id'),
				'user_id' => $mybb->get_input('user_id'),
				'home_score' => $mybb->get_input('home_score'),
				'away_score' => $mybb->get_input('away_score')
			);
	
			if($mybb->get_input('home_nickname') != "") {
				$args['home_nickname'] = $db->escape_string($mybb->get_input('home_nickname'));
			}
			if($mybb->get_input('away_nickname') != "") {
				$args['away_nickname'] = $db->escape_string($mybb->get_input('away_nickname'));
			}
	
			if($existing_prediction_id == "") {
				$db->insert_query('predictions_prediction', $args);
			} else {
				$db->update_query('predictions_prediction', $args, "prediction_id=".$existing_prediction_id);
			}
		
			$stanford_id = 151;
			$stats = predictions_calculate_game_stats($db, $args['user_id'], $args['game_id'], $stanford_id);
	
			if($stats['count'] == 1) {
				global $lang;
				if(!isset($lang->predictions))
				{
					$lang->load('predictions');
				}
	
				$predictions_stats_panel = eval($templates->render('predictions_thread_game_stats'));
				$stats["stats_html"] = $predictions_stats_panel;
			}
		}
		else
		{
			$stats["error"] = "Too late!  This game started in the past.";
		}

        header("Content-type: application/json; charset={$charset}");
        echo json_encode($stats);
        exit;
    }
}
<?php
	/**************************************************************************\
	* phpGroupWare - Preferences                                               *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$templates = $GLOBALS['phpgw']->common->list_templates();
	while (list($var,$value) = each($templates))
	{
		$_templates[$var] = $templates[$var]['title'];
	}

	$themes = $GLOBALS['phpgw']->common->list_themes();
	while (list(,$value) = each($themes))
	{
		$_themes[$value] = $value;
	}

	create_input_box('Max matches per page','maxmatchs',
		'Any listing in phpGW will show you this number or entries or lines per page.<br>
		To many slow down the page display, to less will cost you the overview.',15,3);
	create_select_box('Interface/Template Selection','template_set',$_templates,
		'A template defines the layout of phpGroupWare and it contains icons vor each application.');
	create_select_box('Theme (colors/fonts) Selection','theme',$_themes,
		'A theme defines the colors and fonts used by the template.');

	$navbar_format = array(
		'icons'          => lang('Icons only'),
		'icons_and_text' => lang('Icons and text'),
		'text'           => lang('Text only')
	);
	create_select_box('Show navigation bar as','navbar_format',$navbar_format,
		'You can show the applications as icons only, icons with app-name or both.');

	$format = $GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat'];
	$format = ($format ? $format : 'Y/m/d') . ', ';
	if ($GLOBALS['phpgw_info']['user']['preferences']['common']['timeformat'] == '12')
	{
		$format .= 'h:i a';
	}
	else
	{
		$format .= 'H:i';
	}
	for ($i = -23; $i<24; $i++)
	{
		$t = time() + $i * 60*60;
		$tz_offset[$i] = $i . ' ' . lang('hours').': ' . date($format,$t);
	}
	create_select_box('Time zone offset','tz_offset',$tz_offset,
		'How many hours are you in front or after the timezone of the server.<br>
		If you are in the same time zone as the server select 0 hours, 
		else select your locale date and time.',0);

	$date_formats = array(
		'm/d/Y' => 'm/d/Y',
		'm-d-Y' => 'm-d-Y',
		'm.d.Y' => 'm.d.Y',
		'Y/d/m' => 'Y/d/m',
		'Y-d-m' => 'Y-d-m',
		'Y.d.m' => 'Y.d.m',
		'Y/m/d' => 'Y/m/d',
		'Y-m-d' => 'Y-m-d',
		'Y.m.d' => 'Y.m.d',
		'd/m/Y' => 'd/m/Y',
		'd-m-Y' => 'd-m-Y',
		'd.m.Y' => 'd.m.Y'
	);
	create_select_box('Date format','dateformat',$date_formats,
		'How should phpGroupWare display dates for you.');

	$time_formats = array(
		'12' => lang('12 hour'),
		'24' => lang('24 hour')
	);
	create_select_box('Time format','timeformat',$time_formats,
		'Do you prefer a 24 hour time format, or a 12 hour one with am/pm attached.');

	$sbox = createobject('phpgwapi.sbox');
	create_select_box('Country','country',$sbox->country_array,
		'In which country are you. This is used to set certain defaults for you.');

	$db2 = $GLOBALS['phpgw']->db;
	$GLOBALS['phpgw']->db->query("select distinct lang from phpgw_lang",__LINE__,__FILE__);
	while ($GLOBALS['phpgw']->db->next_record())
	{
//		$phpgw_info['installed_langs'][$phpgw->db->f('lang')] = $phpgw->db->f('lang');

		$db2->query("select lang_name from phpgw_languages where lang_id = '"
			. $GLOBALS['phpgw']->db->f('lang') . "'",__LINE__,__FILE__);
		$db2->next_record();

		// When its not in the phpgw_languages table, it will show ??? in the field
		// otherwise
		if ($db2->f('lang_name'))
		{
			$langs[$GLOBALS['phpgw']->db->f('lang')] = $db2->f('lang_name');
		}
	}
	foreach ($langs as $key => $name)	// if we have a translation use it
	{
		$trans = lang($name);
		if ($trans != $name . '*')
		{
			$langs[$key] = $trans;
		}
	}
	create_select_box('Language','lang',$langs,
		'Select the language of texts and messages within phpGroupWare.<br>
		Some languages may not contain all messages, in that case you will see an english message.');
	
	// preference.php handles this function
	if (is_admin())
	{
		create_check_box('Show current users on navigation bar','show_currentusers',
			'Should the number of active sessions be displayed for you all the time.');
	}

	reset($GLOBALS['phpgw_info']['user']['apps']);
	while (list($app) = each($GLOBALS['phpgw_info']['user']['apps']))
	{
		if ($GLOBALS['phpgw_info']['apps'][$app]['status'] != 2 && $app)
		{
			$user_apps[$app] = $GLOBALS['phpgw_info']['apps'][$app]['title'] ? $GLOBALS['phpgw_info']['apps'][$app]['title'] : lang($app);
		}
	}
	create_select_box('Default application','default_app',$user_apps,
		"This is the application which will be started when you enter phpGroupWare or click on the homepage icon.<br>
		You can also have more than one applications showing up on the homepage, if you don't 
		choose a specific application here (has to be configured in the preferences of 
		each applicaton).");

	create_input_box('Currency','currency',
		'Which currency symbole or name should be used in phpGroupWare.');
		
	create_check_box('Show helpmessages by default','show_help',
		'Should the help messages always be shown when you enter the preferences or only on request.');

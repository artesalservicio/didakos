<?php
// $Id: gradebook_view_result.php 725 2007-04-24 07:27:11Z stijn $
/*
==============================================================================
	Dokeos - elearning and course management software

	Copyright (c) 2006 Dokeos S.A.
	Copyright (c) 2006 Ghent University (UGent)
	Copyright (c) various contributors

	For a full list of contributors, see "credits.txt".
	The full license can be read in "license.txt".

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	See the GNU General Public License for more details.

	Contact address: Dokeos, 44 rue des palais, B-1030 Brussels, Belgium
	Mail: info@dokeos.com
==============================================================================
*/
$language_file= 'gradebook';
$cidReset= true;
include_once ('../inc/global.inc.php');
include_once ('lib/be.inc.php');
include_once ('lib/gradebook_functions.inc.php');
include_once ('lib/fe/scoredisplayform.class.php');
include_once ('lib/scoredisplay.class.php');
api_block_anonymous_users();
api_protect_admin_script();

$htmlHeadXtra[]= '
  <script language="JavaScript">
  function plusItem(item)
  {
		document.getElementById(item).style.display = "inline";
    	document.getElementById("plus-"+item).style.display = "none";
   	 	document.getElementById("min-"+(item-1)).style.display = "none";
   	 	document.getElementById("min-"+(item)).style.display = "inline";
   	 	document.getElementById("plus-"+(item+1)).style.display = "inline";
	 	document.getElementById("txta-"+(item)).value = "100";
	 	document.getElementById("txta-"+(item-1)).value = "";
  }
  
  function minItem(item)
   {
    if (item != 1)
	{
     document.getElementById(item).style.display = "none";
	 document.getElementById("txta-"+item).value = "";
	 document.getElementById("txtb-"+item).value = "";
     document.getElementById("plus-"+item).style.display = "inline";
     document.getElementById("min-"+(item-1)).style.display = "inline";
	 document.getElementById("txta-"+(item-1)).value = "100";

	}
	if (item = 1)
	{	
		document.getElementById("min-"+(item)).style.display = "none";
	}
  }
 </script>';

$interbreadcrumb[]= array (
	'url' => 'gradebook.php',
	'name' => get_lang('Gradebook'
));
$displayscore= ScoreDisplay :: instance();
$customdisplays = $displayscore->get_custom_score_display_settings();
$nr_items =(count($customdisplays)!='0')?count($customdisplays):'1';
	
$scoreform= new ScoreDisplayForm('scoring_system_form',
								 api_get_self() . '?selectcat=' . $_GET['selectcat']
								 );
if ($scoreform->validate())
{
	$values= $scoreform->exportValues();


	// create new array of custom display settings
	// this loop also checks if all score ranges are unique
	
	$scoringdisplay= array ();
	$ranges_ok = true;
	$endscore= $values['endscore'];
	$displaytext= $values['displaytext'];
	for ($counter= 1; $ranges_ok && $counter <= 20; $counter++)
	{
		$setting= array ();
		$setting['score']= $endscore[$counter];
		$setting['display']= $displaytext[$counter];
		if (!empty($setting['score']))
		{
			foreach ($scoringdisplay as $passed_entry)
			{
				if ($passed_entry['score'] == $setting['score'])
					$ranges_ok = false;
			}
			$scoringdisplay[]= $setting;
		}
	}

	if (!$ranges_ok)
	{
		header('Location: ' . api_get_self() . '?nouniqueranges=&selectcat=' . $_GET['selectcat']);
		exit;
	}


	// update color settings
	$displayscore->set_coloring_enabled(($values['enablescorecolor'] == '1') ? true : false);
	if ($displayscore->is_coloring_enabled())
		$displayscore->set_color_split_value($values['scorecolpercent']);

	// update custom display settings
	$displayscore->set_custom(($values['enablescore'] == '1') ? true : false);
	$displayscore->set_upperlimit_included(($values['includeupperlimit'] == '1') ? true : false);
	if ($displayscore->is_custom() && !empty($scoringdisplay))
		$displayscore->update_custom_score_display_settings($scoringdisplay);

	header('Location: ' . api_get_self() . '?scoringupdated=&selectcat=' . $_GET['selectcat']);
	exit;
}

Display :: display_header(get_lang('ScoreEdit'));
if (isset ($_GET['scoringupdated']))
	Display :: display_confirmation_message(get_lang('ScoringUpdated'),false);
if (isset ($_GET['nouniqueranges']))
	Display :: display_error_message(get_lang('NoUniqueScoreRanges'),false);
echo '<div class="maincontent">';
$scoreform->display();
echo '</div>';
Display :: display_footer();
?>

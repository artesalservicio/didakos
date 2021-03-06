<?php
// $Id: gradebook_view_result.php 479 2007-04-12 11:50:58Z stijn $
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
include_once ('lib/fe/dataform.class.php');
include_once ('lib/fe/userform.class.php');
include_once ('lib/flatview_data_generator.class.php');
include_once ('lib/fe/flatviewtable.class.php');
include_once ('lib/fe/displaygradebook.php');
include_once ('lib/fe/exportgradebook.php');
include_once (api_get_path(LIBRARY_PATH).'ezpdf/class.ezpdf.php');
include_once ('lib/scoredisplay.class.php');
api_block_anonymous_users();
block_students();


if (isset ($_POST['submit']) && isset ($_POST['keyword']))
{
	header('Location: ' . api_get_self() . '?selectcat=' . Security::remove_XSS($_GET['selectcat'])
											   . '&search='.Security::remove_XSS($_POST['keyword']));
	exit;
}


$interbreadcrumb[]= array (
	'url' => 'gradebook.php',
	'name' => get_lang('Gradebook'
));
$showeval= (isset ($_POST['showeval']) ? '1' : '0');
$showlink= (isset ($_POST['showlink']) ? '1' : '0');
if (($showlink == '0') && ($showeval == '0'))
{
	$showlink= '1';
	$showeval= '1';
}
$cat= Category :: load($_GET['selectcat']);
if ($showeval)
	$alleval= $cat[0]->get_evaluations($_GET['userid'], true);
if ($showlink)
	$alllinks= $cat[0]->get_links($_GET['userid'], true);
if (isset ($export_flatview_form) && (!$file_type == 'pdf'))
	Display :: display_normal_message($export_flatview_form->toHtml(),false);
$simple_search_form= new UserForm(UserForm :: TYPE_SIMPLE_SEARCH, null, 'simple_search_form', null, api_get_self() . '?selectcat=' . $_GET['selectcat']);
$values= $simple_search_form->exportValues();
$keyword = '';
if (isset($_GET['search']) && !empty($_GET['search']))
	$keyword = $_GET['search'];
if ($simple_search_form->validate() && (empty($keyword)))
	$keyword = $values['keyword'];

if (!empty($keyword))
	$users= find_students($keyword);
else
	$users= get_all_users($alleval, $alllinks);

if (isset ($_GET['exportpdf']))
{
	$interbreadcrumb[]= array (
		'url' => api_get_self().'?selectcat=' . $_GET['selectcat'],
		'name' => get_lang('FlatView')
		);
	$export_pdf_form= new DataForm(DataForm :: TYPE_EXPORT_PDF, 'export_pdf_form', null, api_get_self() . '?exportpdf=&offset='.$_GET['offset'].'&selectcat=' . $_GET['selectcat'],'_blank');
	if (!$export_pdf_form->validate())
		Display :: display_header(get_lang('ExportPDF'));
	if ($export_pdf_form->validate())	
	{
		$printable_data = get_printable_data ($users,$alleval, $alllinks);

		$export= $export_pdf_form->exportValues();
		$format = $export['orientation'];
		$pdf =& new Cezpdf('a4',$format); //format is 'portrait' or 'landscape'
		export_pdf($pdf,$printable_data[1],$printable_data[0],$format);
		exit;		
	}

}


if (isset ($_GET['print']))
{
	$printable_data = get_printable_data ($users,$alleval, $alllinks);
	echo print_table($printable_data[1],$printable_data[0], get_lang('FlatView'), $cat[0]->get_name());
	exit;
}


$addparams= array ('selectcat' => $cat[0]->get_id());
if (isset($_GET['search']))
	$addparams['search'] = $keyword;


$offset = (isset($_GET['offset'])?$_GET['offset']:'0');
$flatviewtable= new FlatViewTable($cat[0], $users, $alleval, $alllinks, true, $offset, $addparams);
	
if (isset($_GET['exportpdf']))
{
	echo '<div class="normal-message">';
	$export_pdf_form->display();
	echo '</div>';
}
else
	Display :: display_header(get_lang('FlatView'));

DisplayGradebook :: display_header_flatview($cat[0], $showeval, $showlink, $simple_search_form);
$flatviewtable->display();
Display :: display_footer();


function get_printable_data ($users,$alleval, $alllinks)
{
	$datagen = new FlatViewDataGenerator ($users,$alleval, $alllinks);

	$offset = (isset($_GET['offset'])?$_GET['offset']:'0');

   	$count = (($offset+10) > $datagen->get_total_items_count()) ?
      		 ($datagen->get_total_items_count()-$offset) : 10;

	$header_names = $datagen->get_header_names($offset,$count);
	$data_array = $datagen->get_data(FlatViewDataGenerator :: FVDG_SORT_LASTNAME,0,null,$offset,$count,true);	
	
	$newarray = array();
	foreach ($data_array as $data)
		$newarray[] = array_slice($data, 1);
	
	return array ($header_names, $newarray);
}

?>

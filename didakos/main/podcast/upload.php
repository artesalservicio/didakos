<?php // $Id: upload.php 14802 2008-04-09 12:53:59Z elixir_inter $
/*
==============================================================================
	Dokeos - elearning and course management software

	Copyright (c) 2004-2005 Dokeos S.A.
	Copyright (c) 2003 Ghent University (UGent)
	Copyright (c) 2001 Universite catholique de Louvain (UCL)
	Copyright (c) various contributors

	For a full list of contributors, see "credits.txt".
	The full license can be read in "license.txt".

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	See the GNU General Public License for more details.

	Contact: Dokeos, 181 rue Royale, B-1000 Brussels, Belgium, info@dokeos.com
==============================================================================
*/
/**
==============================================================================
* Main script for the documents tool
*
* This script allows the user to manage files and directories on a remote http server.
*
* The user can : - navigate through files and directories.
*				 - upload a file
*				 - delete, copy a file or a directory
*				 - edit properties & content (name, comments, html content)
*
* The script is organised in four sections.
*
* 1) Execute the command called by the user
*				Note: somme commands of this section are organised in two steps.
*			    The script always begins with the second step,
*			    so it allows to return more easily to the first step.
*
*				Note (March 2004) some editing functions (renaming, commenting)
*				are moved to a separate page, edit_document.php. This is also
*				where xml and other stuff should be added.
*
* 2) Define the directory to display
*
* 3) Read files and directories from the directory defined in part 2
* 4) Display all of that on an HTML page
*
* @todo eliminate code duplication between
* document/document.php, scormdocument.php
*
* @package dokeos.document
==============================================================================
*/

/*
==============================================================================
		INIT SECTION
==============================================================================
*/

// name of the language file that needs to be included
$language_file = 'document';


// global settings initialisation
// also provides access to main api (inc/lib/main_api.lib.php)
include("../inc/global.inc.php");
//include('document.inc.php');

/*
-----------------------------------------------------------
	Variables
	- some need defining before inclusion of libraries
-----------------------------------------------------------
*/
$is_allowed_to_edit = api_is_allowed_to_edit();

$courseDir   = $_course['path']."/podcast";
$sys_course_path = api_get_path(SYS_COURSE_PATH);
$base_work_dir = $sys_course_path.$courseDir;
$noPHP_SELF=true;

//this needs cleaning!
if(isset($_SESSION['_gid']) && $_SESSION['_gid']!='') //if the group id is set, check if the user has the right to be here
{
	//needed for group related stuff
	include_once(api_get_path(LIBRARY_PATH) . 'groupmanager.lib.php');
	//get group info
	$group_properties = GroupManager::get_group_properties($_SESSION['_gid']);
	$noPHP_SELF=true;

	if($is_allowed_to_edit || GroupManager::is_user_in_group($_user['user_id'],$_SESSION['_gid'])) //only courseadmin or group members allowed
	{
		$to_group_id = $_SESSION['_gid'];
		$req_gid = '&amp;gidReq='.$_SESSION['_gid'];
		$interbreadcrumb[]= array ("url"=>"../group/group_space.php?gidReq=".$_SESSION['_gid'], "name"=> get_lang('GroupSpace'));
	}
	else
	{
		api_not_allowed(true);
	}
}
elseif($is_allowed_to_edit) //admin for "regular" upload, no group documents
{
	$to_group_id = 0;
	$req_gid = '';
}
else  //no course admin and no group member...
{
	api_not_allowed(true);
}

//what's the current path?
if(isset($_GET['path']) && $_GET['path']!='')
{
	$path = $_GET['path'];
}
elseif (isset($_POST['curdirpath']))
{
	$path = $_POST['curdirpath'];
}
else
{
	$path = '/';
}

/*
-----------------------------------------------------------
	Libraries
-----------------------------------------------------------
*/

//many useful functions in main_api.lib.php, by default included

include_once(api_get_path(LIBRARY_PATH) . 'fileUpload.lib.php');
include_once(api_get_path(LIBRARY_PATH) . 'events.lib.inc.php');
include_once(api_get_path(LIBRARY_PATH) . 'document.lib.php');

//check the path
//if the path is not found (no document id), set the path to /
if(!DocumentManager::get_document_id($_course,$path))
{
	$path = '/';
}
//group docs can only be uploaded in the group directory
if($to_group_id!=0 && $path=='/')
{
	$path = $group_properties['directory'];
}

//if we want to unzip a file, we need the library
if (isset($_POST['unzip']) && $_POST['unzip'] == 1)
{
	include(api_get_path(LIBRARY_PATH).'pclzip/pclzip.lib.php');
}
/*
-----------------------------------------------------------
	Variables
-----------------------------------------------------------
*/
$max_filled_space = DocumentManager::get_course_quota();

/*
-----------------------------------------------------------
	Header
-----------------------------------------------------------
*/

$nameTools = 'Subir un podcast';
$interbreadcrumb[]=array("url"=>"./podcast.php?", "name"=> 'Podcast');
Display::display_header($nameTools,"Doc");

if($to_group_id !=0) //add group name after for group documents
{
	$add_group_to_title = ' ('.$group_properties['name'].')';
}
//show the title
api_display_tool_title($nameTools.$add_group_to_title);

/*
-----------------------------------------------------------
	Here we do all the work
-----------------------------------------------------------
*/

//user has submitted a file
if(isset($_FILES['user_upload']))
{
	//echo("<pre>");
	//print_r($_FILES['user_upload']);
	//echo("</pre>");

	$upload_ok = process_uploaded_file($_FILES['user_upload']);
	if($upload_ok)
	{
		//file got on the server without problems, now process it
		$new_path = handle_uploaded_podcast($_course, $_FILES['user_upload'],$base_work_dir,$_POST['curdirpath'],$_user['user_id'],$to_group_id,$to_user_id,$max_filled_space,$_POST['unzip'],$_POST['if_exists']);
    	
	$new_comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    	$new_title = isset($_POST['title']) ? trim($_POST['title']) : '';

    	if ($new_path && ($new_comment || $new_title))

    	if (($docid = DocumentManager::get_podcast_id($_course, $new_path)))
    	{
        	$table_document = Database::get_course_table(TABLE_PODCAST);
        	$ct = '';
        	if ($new_comment) $ct .= ", comment='$new_comment'";
        	if ($new_title)   $ct .= ", title='$new_title'";
        	api_sql_query("UPDATE $table_document SET" . substr($ct, 1) .
        	    " WHERE id = '$docid'", __FILE__, __LINE__);	
    	}
		//check for missing images in html files
		$missing_files = check_for_missing_files($base_work_dir.$new_path);
		if($missing_files)
		{
			//show a form to upload the missing files
			Display::display_normal_message(build_missing_files_form($missing_files,$_POST['curdirpath'],$_FILES['user_upload']['name']),false);
		}
	}
}
//missing images are submitted
if(isset($_POST['submit_image']))
{
	$number_of_uploaded_images = count($_FILES['img_file']['name']);
	//if images are uploaded
	if ($number_of_uploaded_images > 0)
	{
		//we could also create a function for this, I'm not sure...
		//create a directory for the missing files
		$img_directory = str_replace('.','_',$_POST['related_file']."_files");
		$missing_files_dir = create_unexisting_directory($_course,$_user['user_id'],$to_group_id,$to_user_id,$base_work_dir,$img_directory);
		//put the uploaded files in the new directory and get the paths
		$paths_to_replace_in_file = move_uploaded_file_collection_into_directory($_course, $_FILES['img_file'],$base_work_dir,$missing_files_dir,$_user['user_id'],$to_group_id,$to_user_id,$max_filled_space);
		//open the html file and replace the paths
		replace_img_path_in_html_file($_POST['img_file_path'],$paths_to_replace_in_file,$base_work_dir.$_POST['related_file']);
		//update parent folders
		item_property_update_on_folder($_course,$_POST['curdirpath'],$_user['user_id']);
	}
}

//tracking not needed here?
//event_access_tool(TOOL_DOCUMENT);

/*============================================================================*/
?>

<div id="folderselector">
<?php
//form to select directory
//$folders = DocumentManager::get_all_document_folders($_course,$to_group_id,$is_allowed_to_edit);
//echo(build_directory_selector($folders,$path,$group_properties['directory']));
?>
</div>

<!-- start upload form -->

<?php

require_once (api_get_path(LIBRARY_PATH).'formvalidator/FormValidator.class.php');

$form = new FormValidator('upload','POST',api_get_self(),'','enctype="multipart/form-data"');

$form->addElement('hidden','curdirpath',$path);

$form->addElement('file','user_upload',get_lang('File'),'id="user_upload" size="45"');
$form->addElement('text','title',get_lang('Title'),'size="20" style="width:300px;"');
$form->addElement('textarea','comment',get_lang('Comment'),'wrap="virtual" style="width:300px;"');

//$form->addElement('checkbox','unzip',get_lang('Options'),get_lang('Uncompress'),'onclick="check_unzip()" value="1"');

$form->addElement('radio', 'if_exists', get_lang('UplWhatIfFileExists'), get_lang('UplDoNothing'), 'nothing');
$form->addElement('radio', 'if_exists', '', get_lang('UplOverwriteLong'), 'overwrite');
$form->addElement('radio', 'if_exists', '', get_lang('UplRenameLong'), 'rename');

$form->addElement('submit', 'submitDocument', get_lang('Ok'));

$form->add_real_progress_bar('DocumentUpload','user_upload');

$form->display();

?>

<!-- end upload form -->

 <!-- so they can get back to the documents   -->
 <p><?php echo (get_lang('Back'));?> <?php echo (get_lang('To'));?> <a href="podcast.php?cidReq=<?php echo $_GET['cidReq']; ?>"><?php echo ('podcast');?></a></p>

<?php
/*
==============================================================================
		FOOTER
==============================================================================
*/
Display::display_footer();
?>

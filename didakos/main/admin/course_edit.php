<?php

// $Id: course_edit.php 15245 2008-05-08 16:53:52Z juliomontoya $
/*
==============================================================================
	Dokeos - elearning and course management software

	Copyright (c) 2004 Dokeos SPRL
	Copyright (c) 2003 Ghent University (UGent)
	Copyright (c) 2001 Universite catholique de Louvain (UCL)
	Copyright (c) Olivier Brouckaert
	Copyright (c) Bart Mollet, Hogeschool Gent

	For a full list of contributors, see "credits.txt".
	The full license can be read in "license.txt".

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	See the GNU General Public License for more details.

	Contact address: Dokeos, rue du Corbeau, 108, B-1030 Brussels, Belgium
	Mail: info@dokeos.com
==============================================================================
*/
/**
==============================================================================
*	@package dokeos.admin
==============================================================================
*/
/*
==============================================================================
		INIT SECTION
==============================================================================
*/

// name of the language file that needs to be included 
$language_file = 'admin';
$cidReset = true;
include ('../inc/global.inc.php');
$this_section=SECTION_PLATFORM_ADMIN;

api_protect_admin_script();
include (api_get_path(LIBRARY_PATH).'fileManage.lib.php');
require_once (api_get_path(LIBRARY_PATH).'formvalidator/FormValidator.class.php');
require_once (api_get_path(LIBRARY_PATH).'course.lib.php');
$course_table = Database::get_main_table(TABLE_MAIN_COURSE);
$course_user_table = Database::get_main_table(TABLE_MAIN_COURSE_USER);
$course_code = isset($_GET['course_code']) ? $_GET['course_code'] : $_POST['code'];
$noPHP_SELF = true;
$tool_name = get_lang('ModifyCourseInfo');
$interbreadcrumb[] = array ("url" => 'index.php', "name" => get_lang('PlatformAdmin'));
$interbreadcrumb[] = array ("url" => "course_list.php", "name" => get_lang('AdminCourses'));


//cambio - fd: obtenemos la tabla de herramientas del curso que vamos a modificar
$datos = CourseManager::get_course_information($course_code);
$tool_table = Database :: get_course_table(TABLE_TOOL_LIST,$datos["db_name"]);


/*
-----------------------------------------------------------
	Libraries
-----------------------------------------------------------
*/
/*
==============================================================================
		FUNCTIONS
==============================================================================
*/

/*
==============================================================================
		MAIN CODE
==============================================================================
*/
// Get all course categories
$table_user = Database :: get_main_table(TABLE_MAIN_USER);


//Get the course infos
$sql = "SELECT * FROM $course_table WHERE code='".Database::escape_string($course_code)."'";
$result = api_sql_query($sql, __FILE__, __LINE__);
if (Database::num_rows($result) != 1)
{
	header('Location: course_list.php');
	exit ();
}
$course = Database::fetch_array($result,'ASSOC');

// Get course teachers
$table_course_user = Database :: get_main_table(TABLE_MAIN_COURSE_USER);
$sql = "SELECT user.user_id,lastname,firstname FROM $table_user as user,$table_course_user as course_user WHERE course_user.status='1' AND course_user.user_id=user.user_id AND course_user.course_code='".$course_code."' ORDER BY lastname,firstname";
$res = api_sql_query($sql,__FILE__,__LINE__);
$course_teachers = array();
while($obj = Database::fetch_object($res))
{
		$course_teachers[$obj->user_id] = $obj->lastname.' '.$obj->firstname;
}

// Get all possible teachers without the course teachers
$sql = "SELECT user_id,lastname,firstname FROM $table_user WHERE status='1' ORDER BY lastname,firstname";
$res = api_sql_query($sql,__FILE__,__LINE__);
$teachers = array();

$platform_teachers[0] = '-- '.get_lang('NoManager').' --';
while($obj = Database::fetch_object($res))
{		
	if(!array_key_exists($obj->user_id,$course_teachers)){
		$teachers[$obj->user_id] = $obj->lastname.' '.$obj->firstname;
	}
	

	if($course['tutor_name']==$course_teachers[$obj->user_id]){
		$course['tutor_name']=$obj->user_id;
	}
	//We add in the array platform teachers 
	$platform_teachers[$obj->user_id] = $obj->lastname.' '.$obj->firstname;
}

//Case where there is no teacher in the course
if(count($course_teachers)==0){
	$sql='SELECT tutor_name FROM '.$course_table.' WHERE code="'.$course_code.'"';
	$res = api_sql_query($sql,__FILE__,__LINE__);
	$tutor_name=Database::result($res,0,0);
	$course['tutor_name']=array_search($tutor_name,$platform_teachers);
}

// Build the form
$form = new FormValidator('update_course');
$form->addElement('hidden','code',$course_code);
$form->add_textfield('visual_code', get_lang('CourseCode'));
$form->applyFilter('visual_code','strtoupper');
//$form->add_textfield('tutor_name', get_lang('CourseTitular'));
$form->addElement('select', 'tutor_name', get_lang('CourseTitular'), $platform_teachers);
//$form->addElement('select', 'course_teachers', get_lang('CourseTeachers'), $teachers, 'multiple=multiple size="4" style="width: 150px;"');

$group=array();
$group[] = FormValidator::createElement('select', 'platform_teachers', '', $teachers, 'id="platform_teachers" multiple=multiple size="4" style="width: 150px;"');
$group[] = FormValidator::createElement('select', 'course_teachers', '', $course_teachers, 'id="course_teachers" multiple=multiple size="4" style="width: 150px;"');

$element_template = <<<EOT
	<div class="row">
		<div class="label">
			<!-- BEGIN required --><span class="form_required">*</span> <!-- END required -->{label}
		</div>
		<div class="formw" style="display:inline">
			<table cellpadding="0" cellspacing="0">
				<tr>
					<!-- BEGIN error --><span class="form_error">{error}</span><br /><!-- END error -->	<td>{element}</td>
				</tr>
			</table>
		</div>
	</div>
EOT;
	
$renderer = $form->defaultRenderer();
$renderer -> setElementTemplate($element_template, 'group');
$form -> addGroup($group,'group',get_lang('CourseTeachers'),'</td><td width="50" align="center"><input type="button" onclick="moveItem(document.getElementById(\'platform_teachers\'), document.getElementById(\'course_teachers\'))" value=">>"><br><br><input type="button" onclick="moveItem(document.getElementById(\'course_teachers\'), document.getElementById(\'platform_teachers\'))" value="<<"></td><td>');

$form->add_textfield( 'title', get_lang('Title'),true, array ('size' => '60'));
$categories_select = $form->addElement('select', 'category_code', get_lang('CourseFaculty'), $categories);
CourseManager::select_and_sort_categories($categories_select);
$form->add_textfield( 'department_name', get_lang('CourseDepartment'), false,array ('size' => '60'));
$form->add_textfield( 'department_url', get_lang('CourseDepartmentURL'),false, array ('size' => '60'));
$form->addElement('select_language', 'course_language', get_lang('CourseLanguage'));
$form->addElement('radio', 'visibility', get_lang("CourseAccess"), get_lang('OpenToTheWorld'), COURSE_VISIBILITY_OPEN_WORLD);
$form->addElement('radio', 'visibility', null, get_lang('OpenToThePlatform'), COURSE_VISIBILITY_OPEN_PLATFORM);
$form->addElement('radio', 'visibility', null, get_lang('Private'), COURSE_VISIBILITY_REGISTERED);
$form->addElement('radio', 'visibility', null, get_lang('CourseVisibilityClosed'), COURSE_VISIBILITY_CLOSED);
$form->addElement('radio', 'subscribe', get_lang('Subscription'), get_lang('Allowed'), 1);
$form->addElement('radio', 'subscribe', null, get_lang('Denied'), 0);
$form->addElement('radio', 'unsubscribe', get_lang('Unsubscription'), get_lang('AllowedToUnsubscribe'), 1);
$form->addElement('radio', 'unsubscribe', null, get_lang('NotAllowedToUnsubscribe'), 0);
$form->addElement('text','disk_quota',get_lang('CourseQuota'));
	//## vlab egarcia 20/07/10
	// Primero tenemos que consultar si el curso dispone o no ya del recurso VLAB, podemos verlo buscando en la tabla de herramientas tool.
	$sql = "select count(*) as total from " . $tool_table . " where name='Vlab'";
	$res = api_sql_query($sql, __FILE__, __LINE__);
	$datos = Database::fetch_row($res);
	
	
	if ($datos[0] > 0)
	{
	  // die("aqui");
		//$form->addElement('label', 'Vlab Activado: Si');
		//$form->addElement('checkbox', 'vlab activado', 'vlab activado', '');
		$form->add_textfield( 'vlab_activado', '', false,array ('size' => '60','disabled' => 'true', 'value' => 'vlab activado'));
	}
	else
	{
		$form->addElement('checkbox', 'vlab', 'Añadir Vlab', '');
	}
        //## vlab


$form->addRule('disk_quota', get_lang('ThisFieldIsRequired'),'required');
$form->addRule('disk_quota',get_lang('ThisFieldShouldBeNumeric'),'numeric');
$form->addElement('button', null, get_lang('Ok'), 'onclick="valide()"');
// Set some default values

$course_db_name = $course['db_name'];
$course['title']=html_entity_decode($course['title'],ENT_QUOTES,$charset);
$form->setDefaults($course);
// Validate form
if( $form->validate())
{
	$course = $form->getSubmitValues();
	$dbName = $_POST['dbName'];
	$course_code = $course['code'];
	$visual_code = $course['visual_code'];
	
	$tutor_id = $course['tutor_name'];
	$tutor_name=$platform_teachers[$tutor_id];
	
	$teachers = $course['group']['course_teachers'];
	
	$title = $course['title'];
	$category_code = $course['category_code'];
	$department_name = $course['department_name'];
	$department_url = $course['department_url'];
	$course_language = $course['course_language'];
	$disk_quota = $course['disk_quota'];
	$visibility = $course['visibility'];
	$subscribe = $course['subscribe'];
	$unsubscribe = $course['unsubscribe'];
	if (!stristr($department_url, 'http://'))
	{
		$department_url = 'http://'.$department_url;
	}
	$sql = "UPDATE $course_table SET course_language='".Database::escape_string($course_language)."',
								title='".Database::escape_string($title)."',
								category_code='".Database::escape_string($category_code)."',
								tutor_name='".Database::escape_string($tutor_name)."',
								visual_code='".Database::escape_string($visual_code)."',
								department_name='".Database::escape_string($department_name)."',
								department_url='".Database::escape_string($department_url)."',
								disk_quota='".Database::escape_string($disk_quota)."',
								visibility = '".Database::escape_string($visibility)."', 
								subscribe = '".Database::escape_string($subscribe)."',
								unsubscribe='".Database::escape_string($unsubscribe)."'
							WHERE code='".Database::escape_string($course_code)."'";
	api_sql_query($sql, __FILE__, __LINE__);
	
	
	
        //cambio fd - #VLAB - si se ha seleccionado,añadimos la herramienta del vlab al curso
	if ($course['vlab'] == 1)
        {
	    $datos = CourseManager::get_course_information($course_code);
            $tool_table = Database :: get_course_table(TABLE_TOOL_LIST,$datos["db_name"]);
	    $sql = "INSERT INTO  ".$tool_table."  VALUES ('', 'Vlab','vlab/index.php','vlab.png','1','0','squaregrey.gif','0','_self','interaction')";	
	     api_sql_query($sql, __FILE__, __LINE__);
	 //## 10/01/2012 - cambiofd - vlab - eliminada la creacion de la plataforma en el esquema vlab
	 }

	//FIN #VLAB

	
	
	
	$sql='DELETE FROM '.$course_user_table.' WHERE course_code="'.Database::escape_string($course_code).'" AND status="1"';
	api_sql_query($sql, __FILE__, __LINE__);
	
	if(count($teachers)>0){
		
		foreach($teachers as $key){
			
			//We check if the teacher is already subscribed as student in this course 
			$sql_select_teacher = 'SELECT 1 FROM '.$course_user_table.' WHERE user_id = "'.$key.'" AND course_code = "'.$course_code.'" AND status<>"1"';
			$result = api_sql_query($sql_select_teacher, __FILE__, __LINE__);
			
			if(Database::num_rows($result) == 1){
				$sql = 'UPDATE '.$course_user_table.' SET status = "1" WHERE course_code = "'.$course_code.'" AND user_id = "'.$key.'"';
			}
			else{
				$sql = "INSERT INTO ".$course_user_table . " SET
					course_code = '".Database::escape_string($course_code). "',
					user_id = '".$key . "',
					status = '1',
					role = '',
					tutor_id='0',
					sort='0',
					user_course_cat='0'";
			}
			api_sql_query($sql, __FILE__, __LINE__);
		}
		
	}
	
	$sql = "INSERT IGNORE INTO ".$course_user_table . " SET
				course_code = '".Database::escape_string($course_code). "',
				user_id = '".$tutor_id . "',
				status = '1',
				role = '',
				tutor_id='0',
				sort='0',
				user_course_cat='0'";
	api_sql_query($sql, __FILE__, __LINE__);
	
	$forum_config_table = Database::get_course_table(TOOL_FORUM_CONFIG_TABLE,$course_db_name);
	$sql = "UPDATE ".$forum_config_table." SET default_lang='".Database::escape_string($course_language)."'";
	header('Location: course_list.php');
	exit ();
}
Display::display_header($tool_name);

echo "<script>
function moveItem(origin , destination){
	
	for(var i = 0 ; i<origin.options.length ; i++) {
		if(origin.options[i].selected) {	
			destination.options[destination.length] = new Option(origin.options[i].text,origin.options[i].value);
			origin.options[i]=null;	
			i = i-1;
		}
	}
	destination.selectedIndex = -1;
	sortOptions(destination.options);
	
}

function sortOptions(options) { 

	newOptions = new Array();
	for (i = 0 ; i<options.length ; i++)
		newOptions[i] = options[i];
		
	newOptions = newOptions.sort(mysort);	
	options.length = 0;
	for(i = 0 ; i < newOptions.length ; i++)
		options[i] = newOptions[i];
	
}

function mysort(a, b){
	if(a.text.toLowerCase() > b.text.toLowerCase()){
		return 1;
	}
	if(a.text.toLowerCase() < b.text.toLowerCase()){
		return -1;
	}
	return 0;
}

function valide(){
	var options = document.getElementById('course_teachers').options;
	for (i = 0 ; i<options.length ; i++)
		options[i].selected = true;
	document.update_course.submit();
}
</script>";
//api_display_tool_title($tool_name);
// Display the form
$form->display();
/*
==============================================================================
		FOOTER 
==============================================================================
*/
Display :: display_footer();
?>
<?php
// $Id: course_add.php 14291 2008-02-14 08:17:23Z elixir_inter $
/*
==============================================================================
	Dokeos - elearning and course management software

	Copyright (c) 2004 Dokeos S.A.
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

	Contact: Dokeos, 181 rue Royale, B-1000 Brussels, Belgium, info@dokeos.com
==============================================================================
*/
/**
==============================================================================
*	@package dokeos.admin
==============================================================================
*/
 /*
==============================================================================
  Página modificada por Formación Digital

Autor: Eduardo Garcia
Página incial: course_add.php (1.8.5)
Página actual: fd_course_add.php
Descripción: Página de creación de cursos
  
==============================================================================
*/
/*
==============================================================================
		INIT SECTION
==============================================================================
*/

// name of the language file that needs to be included 
$language_file = array('admin','create_course');
$cidReset = true;
require_once ('../inc/global.inc.php');
$this_section=SECTION_PLATFORM_ADMIN;

api_protect_gestor_script();
require_once (api_get_path(LIBRARY_PATH).'fileManage.lib.php');
require_once (api_get_path(CONFIGURATION_PATH).'add_course.conf.php');
require_once (api_get_path(LIBRARY_PATH).'add_course.lib.inc.php');
require_once (api_get_path(LIBRARY_PATH).'course.lib.php');
require_once (api_get_path(LIBRARY_PATH).'formvalidator/FormValidator.class.php');
$table_course = Database::get_main_table(TABLE_MAIN_COURSE);
$tool_name = get_lang('AddCourse');
$interbreadcrumb[] = array ("url" => 'index.php', "name" => get_lang('PlatformAdmin'));

/*
==============================================================================
		MAIN CODE
==============================================================================
*/



// Get all possible teachers
$table_user = Database :: get_main_table(TABLE_MAIN_USER);
$sql = "SELECT user_id,lastname,firstname FROM $table_user WHERE status=1 ORDER BY lastname,firstname";
$res = api_sql_query($sql,__FILE__,__LINE__);
$teachers = array();
$teachers[0] = '-- '.get_lang('NoManager').' --';
while($obj = mysql_fetch_object($res))
{
		$teachers[$obj->user_id] = $obj->lastname.' '.$obj->firstname;
}
// Build the form
// Eliminamos ciertas opciones que no se utilizan y forzamos ciertos valores. Cambiamos tb el orden.
$form = new FormValidator('update_course');
$form->add_textfield( 'visual_code', get_lang('CourseCode'),true,array('size'=>'20','maxlength'=>20));
$form->applyFilter('visual_code','strtoupper');
$form->add_textfield('title', get_lang('Title'),true, array ('size' => '60'));
$form->addRule('wanted_code',get_lang('Max'),'maxlength',20);
$form->addElement('select', 'tutor_id', get_lang('CourseTitular'), $teachers);
$form->addElement('select', 'course_teachers', get_lang('CourseTeachers'), $teachers, 'multiple=multiple size=10');
$categories_select = $form->addElement('select', 'category_code', get_lang('CourseFaculty'), $categories);
CourseManager::select_and_sort_categories($categories_select);
//$form->add_textfield('department_name', get_lang('CourseDepartment'),false, array ('size' => '60'));
//$form->add_textfield('department_url', get_lang('CourseDepartmentURL'),false, array ('size' => '60'));
$form->addElement('select_language', 'course_language', get_lang('CourseLanguage'));
//$form->addElement('radio', 'visibility', get_lang("CourseAccess"), get_lang('OpenToTheWorld'), COURSE_VISIBILITY_OPEN_WORLD);
//$form->addElement('radio', 'visibility', null, get_lang('OpenToThePlatform'), COURSE_VISIBILITY_OPEN_PLATFORM);
$form->addElement('radio', 'visibility', get_lang("CourseAccess"), get_lang('Private'), COURSE_VISIBILITY_REGISTERED);
//$form->addElement('radio', 'visibility', null, get_lang('CourseVisibilityClosed'), COURSE_VISIBILITY_CLOSED);
//$form->addElement('radio', 'subscribe', get_lang('Subscription'), get_lang('Allowed'), 1);
$form->addElement('radio', 'subscribe', get_lang('Subscription'), get_lang('Denied'), 0);
//$form->addElement('radio', 'unsubscribe', get_lang('Unsubscription'), get_lang('AllowedToUnsubscribe'), 1);
$form->addElement('radio', 'unsubscribe',  get_lang('Unsubscription'), get_lang('NotAllowedToUnsubscribe'), 0);
$form->add_textfield('disk_quota',get_lang('CourseQuota'));
$form->addRule('disk_quota',get_lang('ThisFieldShouldBeNumeric'),'numeric');
$form->add_progress_bar();
$form->addElement('submit', null, get_lang('Ok'));
// Set some default values
$values['course_language'] = get_setting('platformLanguage');
$values['disk_quota'] = get_setting('default_document_quotum');
$values['visibility'] = COURSE_VISIBILITY_REGISTERED;
$values['subscribe'] = 0;
$values['unsubscribe'] = 0;
reset($teachers);
$values['course_teachers'] = key($teachers);
$form->setDefaults($values);

// Validate form


if( $form->validate())
{
	//Insertamos aqui la revisión de que el codigo del curso no esté repetido
	$course = $form->exportValues();
	$code = $course['visual_code'];
	
	//CAMBIO FD - obtenemos la lista de cursos
	$array_cursos = CourseManager::get_courses_list();
	//comprobamos que el codigo del curso no este ya insertado

	$repetido=false;
	foreach($array_cursos as $indice=>$valor)
	{
	   if($array_cursos[$indice]['code']==$code)
	   {
	   
	    $repetido = true;
	   }
	}
	
	if ($repetido==false)
	{
		$tutor_name = $teachers[$course['tutor_id']];
		$teacher_id = $course['tutor_id'];
		$course_teachers = $course['course_teachers'];
		$test=false;
	
		//The course tutor has been selected in the teachers list so we must remove him to avoid double records in the database
		foreach($course_teachers as $key=>$value){
			if($value==$teacher_id){
				unset($course_teachers[$key]);
				break;
			}
		}

		$title = $course['title'];
		$category = $course['category_code'];
		$department_name = $course['department_name'];
		$department_url = $course['department_url'];
		$course_language = $course['course_language'];
		$disk_quota = $course['disk_quota'];
		if (!stristr($department_url, 'http://'))
		{
			$department_url = 'http://'.$department_url;
		}
		if(trim($code) == ''){
			$code = generate_course_code(substr($title,0,20));
		}
		$keys = define_course_keys($code, "", $_configuration['db_prefix']);
		if (sizeof($keys))
		{
			$currentCourseCode = $keys["currentCourseCode"];
			$currentCourseId = $keys["currentCourseId"];
			$currentCourseDbName = $keys["currentCourseDbName"];
			$currentCourseRepository = $keys["currentCourseRepository"];
			$expiration_date = time() + $firstExpirationDelay;
			prepare_course_repository($currentCourseRepository, $currentCourseId);
			update_Db_course($currentCourseDbName);
			$pictures_array=fill_course_repository($currentCourseRepository);
			fill_Db_course($currentCourseDbName, $currentCourseRepository, $course_language,$pictures_array);
			register_course($currentCourseId, $currentCourseCode, $currentCourseRepository, $currentCourseDbName, $tutor_name, $category, $title, $course_language, $teacher_id, $expiration_date,$course_teachers);
			$sql = "UPDATE $table_course SET disk_quota = '".$disk_quota."', visibility = '".mysql_real_escape_string($course['visibility'])."', subscribe = '".mysql_real_escape_string($course['subscribe'])."', unsubscribe='".mysql_real_escape_string($course['unsubscribe'])."' WHERE code = '".$currentCourseId."'";
			api_sql_query($sql,__FILE__,__LINE__);
			header('Location: fd_lista_cursos.php');
			exit ();
		}
	} //end if repetido
}

Display::display_header($tool_name);

// Para mostrar la cabecera de error dependiendo del valor de $repetido
if ($repetido==true)
{
	Display::display_warning_message('Error, el codigo del curso ya existe');
}

// Display the form
$form->display();
/*
==============================================================================
		FOOTER
==============================================================================
*/
Display :: display_footer();
?>

<?php
/**
 * @package SP Page Builder
 * @author JoomShaper http://www.joomshaper.com
 * @copyright Copyright (c) 2010 - 2015 JoomShaper
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
*/
//no direct accees
defined ('_JEXEC') or die ('resticted aceess');

require_once ( JPATH_COMPONENT .'/builder/builder_layout.php' );

jimport( 'joomla.filesystem.file' );
$input  			= JFactory::getApplication()->input;

if ($input->get('import') === 'local') {
	$file 				= $input->files->get('importLayout');

	if($file) {
		$content = JFile::read($file['tmp_name']);
		echo dataLayoutBuilder(json_decode( $content ));
	}
	else
	{
		echo '<h1>There is no such template</h1>';
	}
	die();
}
else
{
	$template_name = $_POST['template'];
	$path = JPATH_COMPONENT.'/builder/templates/'.$template_name.'.json';
	if (JFile::exists($path))
	{
		$content = JFile::read($path);
		echo dataLayoutBuilder(json_decode( $content ));
	}
	else
	{
		echo '<h1>There is no such template</h1>';
	}
	die();
}
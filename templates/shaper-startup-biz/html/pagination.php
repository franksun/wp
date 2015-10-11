<?php
/**
 * @package Helix Framework
 * @author JoomShaper http://www.joomshaper.com
 * @copyright Copyright (c) 2010 - 2015 JoomShaper
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
*/
//no direct accees
defined ('_JEXEC') or die ('resticted aceess');

function pagination_list_render($list) {
	// Initialize variables
	$html = '<ul class="pagination">';
	
	$html .= $list['start']['data'];
	$html .= $list['previous']['data'];

	foreach ($list['pages'] as $page) {
		$html .= $page['data'];
	}

	$html .= $list['next']['data'];
	$html .= $list['end']['data'];

	$html .= "</ul>";
	
	return $html;

}

function pagination_item_active(&$item) {
	
	$cls = '';

	if ($item->text == JText::_('Start')) { $item->text = '<i class="fa fa-angle-double-left"></i>'; $cls = "start";}
    if ($item->text == JText::_('End')) { $item->text = '<i class="fa fa-angle-double-right"></i>'; $cls = "end";}
    if ($item->text == JText::_('Next')) { $item->text = '<i class="fa fa-angle-right"></i>'; $cls = "next";}
    if ($item->text == JText::_('Prev')) { $item->text = '<i class="fa fa-angle-left"></i>'; $cls = "previous";}
    
	if ($item->text == JText::_('First')) { $cls = "first";}
    if ($item->text == JText::_('Last'))   { $cls = "last";}
	
    return "<li><a class='" . $cls . "' href='" . $item->link . "'>" . $item->text . "</a></li>";
}

function pagination_item_inactive( &$item ) {

	if ($item->text == JText::_('Start')) { $item->text = '<i class="fa fa-angle-double-left"></i>';}
    if ($item->text == JText::_('End')) { $item->text = '<i class="fa fa-angle-double-right"></i>';}
    if ($item->text == JText::_('Next')) { $item->text = '<i class="fa fa-angle-right"></i>';}
    if ($item->text == JText::_('Prev')) { $item->text = '<i class="fa fa-angle-left"></i>';}

	$cls = (int)$item->text > 0 ? 'active': 'disabled';
	return "<li class='" . $cls . "'><a>" . $item->text . "</a></li>";
}

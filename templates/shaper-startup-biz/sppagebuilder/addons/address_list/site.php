<?php

defined ('_JEXEC') or die('resticted aceess');

AddonParser::addAddon('sp_address_list','sp_address_list_addon');

function sp_address_list_addon($atts){

	extract(spAddonAtts(array(
		"title" 				=> '',
		"heading_selector" 		=> 'h3',
		"title_fontsize" 		=> '',
		"title_text_color" 		=> '',
		"title_margin_top" 		=> '',
		"title_margin_bottom" 	=> '',	
		"title_bg" 				=> '',		
		"ai_bg_color" 			=> '',
		"email" 				=> '',
		"phone" 				=> '',
		"location" 				=> '',
		"class"					=>'',
		), $atts));

	// Generate additional info bg color
	if ($ai_bg_color) {
		$ai_bg_color ='background-color: '.$ai_bg_color.';';
	}

	
	$output  = '<div class="sppb-addon sppb-addon-al ' . $class . ' "> ';

	$title_style = '';

	$output .= '<div class="sppb-addon-al-image-wrapper ">';
	if($title) {
		$title_style = '';
		if($title_margin_top) $title_style .= 'margin-top:' . (int) $title_margin_top . 'px;';
		if($title_margin_bottom) $title_style .= 'margin-bottom:' . (int) $title_margin_bottom . 'px;';
		if($title_text_color) $title_style .= 'color:' . $title_text_color  . ';';
		if($title_fontsize) $title_style .= 'font-size:'.$title_fontsize.'px;line-height:'.$title_fontsize.'px;';

		$output .= '<'.$heading_selector.' class="sppb-al-title" style="' . $title_style . '">' . $title . '</'.$heading_selector.'>';
	}
	$output .= '<img class="sppb-img-responsive" src="'.$title_bg.'" alt="'.$title.'" />';
	$output .= '</div>'; //END:: sppb-addon-al-image-wrapper

	$output .= '<div class="sppb-addon-al-additional-infos" style=" ' . $ai_bg_color . ' ">';

	// if email has
	if ($email) {	
		$output .= '<div class="sppb-addon-al-additional-info">';
		$output .= '<div class="sppb-addon-al-icon">';
		$output .= '<i class="fa fa-envelope"></i>';
		$output .= '</div>';
		$output .= '<div class="sppb-addon-al-text"> ' . $email . ' </div>';
		$output .= '</div>'; //END:: sppb-addon-al-additional-info
	}

	// if phone has
	if ($phone) {
		$output .= '<div class="sppb-addon-al-additional-info">';
		$output .= '<div class="sppb-addon-al-icon">';
		$output .= '<i class="fa fa-phone"></i>';
		$output .= '</div>';
		$output .= '<div class="sppb-addon-al-text">' . $phone . '</div>';
		$output .= '</div>'; //END:: sppb-addon-al-additional-info
	}

	// if location has
	if ($location) {
		$output .= '<div class="sppb-addon-al-additional-info">';
		$output .= '<div class="sppb-addon-al-icon">';
		$output .= '<i class="fa fa-map-marker"></i>';
		$output .= '</div>';
		$output .= '<div class="sppb-addon-al-text">' . $location . '</div>';
		$output .= '</div>'; //END:: sppb-addon-al-additional-info
	}


	$output .= '</div>'; //END:: sppb-addon-al-additional-infos

	$output .= '</div>'; //END:: sppb-addon-al
 

	



	

	return $output;

}
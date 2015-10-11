<?php
SpAddonsConfig::addonConfig(
	array( 
		'type'=>'content',
		'addon_name'=>'sp_address_list',
		'category'=>'startup-biz',
		'title'=>JText::_('COM_SPPAGEBUILDER_ADDON_ADDRESS_LIST'),
		'desc'=>JText::_('COM_SPPAGEBUILDER_ADDON_ADDRESS_LIST_DESC'),
		'attr'=>array(
			'title'=>array(
				'type'=>'text', 
				'title'=>JText::_('COM_SPPAGEBUILDER_ADDON_AL_TITLE'),
				'desc'=>JText::_('COM_SPPAGEBUILDER_ADDON_AL_TITLE_DESC'),
				'std'=>'Address List title',
				),

			'heading_selector'=>array(
				'type'=>'select', 
				'title'=>JText::_('COM_SPPAGEBUILDER_ADDON_HEADINGS'),
				'desc'=>JText::_('COM_SPPAGEBUILDER_ADDON_HEADINGS_DESC'),
				'values'=>array(
					'h1'=>JText::_('COM_SPPAGEBUILDER_ADDON_HEADINGS_H1'),
					'h2'=>JText::_('COM_SPPAGEBUILDER_ADDON_HEADINGS_H2'),
					'h3'=>JText::_('COM_SPPAGEBUILDER_ADDON_HEADINGS_H3'),
					'h4'=>JText::_('COM_SPPAGEBUILDER_ADDON_HEADINGS_H4'),
					'h5'=>JText::_('COM_SPPAGEBUILDER_ADDON_HEADINGS_H5'),
					'h6'=>JText::_('COM_SPPAGEBUILDER_ADDON_HEADINGS_H6'),
					),
				'std'=>'h3',
			),

			'title_fontsize'=>array(
				'type'=>'number', 
				'title'=>JText::_('COM_SPPAGEBUILDER_ADDON_TITLE_FONT_SIZE'),
				'desc'=>JText::_('COM_SPPAGEBUILDER_ADDON_TITLE_FONT_SIZE_DESC'),
				'std'=>''
				),

			'title_text_color'=>array(
				'type'=>'color',
				'title'=>JText::_('COM_SPPAGEBUILDER_ADDON_TITLE_TEXT_COLOR'),
				'desc'=>JText::_('COM_SPPAGEBUILDER_ADDON_TITLE_TEXT_COLOR_DESC'),
				),	

			'title_margin_top'=>array(
				'type'=>'number',
				'title'=>JText::_('COM_SPPAGEBUILDER_ADDON_TITLE_MARGIN_TOP'),
				'desc'=>JText::_('COM_SPPAGEBUILDER_ADDON_TITLE_MARGIN_TOP_DESC'),
				'placeholder'=>'10',
				),

			'title_margin_bottom'=>array(
				'type'=>'number',
				'title'=>JText::_('COM_SPPAGEBUILDER_ADDON_TITLE_MARGIN_BOTTOM'),
				'desc'=>JText::_('COM_SPPAGEBUILDER_ADDON_TITLE_MARGIN_BOTTOM_DESC'),
				'placeholder'=>'10',
				),


			'title_bg'=>array(
				'type'=>'media', 
				'title'=>JText::_('COM_SPPAGEBUILDER_ADDON_AL_TITLE_BG'),
				'desc'=>JText::_('COM_SPPAGEBUILDER_ADDON_AL_TITLE_BG_DESC'),
				),

			'separator'=>array(
				'type'=>'separator',
				'title'=>JText::_('COM_SPPAGEBUILDER_ADDON_AL_ADDITIONAL_INFO'),
				),

			'ai_bg_color'=>array(
				'type'=>'color',
				'title'=>JText::_('COM_SPPAGEBUILDER_ADDON_AL_ADDITIONAL_INFO_BG_COLOR'),
				'desc'=>JText::_('COM_SPPAGEBUILDER_ADDON_AL_ADDITIONAL_INFO_BG_COLOR_DESC'),
				),

			'email'=>array(
				'type'=>'text', 
				'title'=>JText::_('COM_SPPAGEBUILDER_ADDON_AL_EMIAL'),
				'desc'=>JText::_('COM_SPPAGEBUILDER_ADDON_AL_EMIAL_DESC'),
				),

			'phone'=>array(
				'type'=>'text', 
				'title'=>JText::_('COM_SPPAGEBUILDER_ADDON_AL_PHONE'),
				'desc'=>JText::_('COM_SPPAGEBUILDER_ADDON_AL_PHONE_DESC'),
				),

			'location'=>array(
				'type'=>'text', 
				'title'=>JText::_('COM_SPPAGEBUILDER_ADDON_AL_LOCATION'),
				'desc'=>JText::_('COM_SPPAGEBUILDER_ADDON_AL_LOCATION_DESC'),
				),

			'class'=>array(
				'type'=>'text', 
				'title'=>JText::_('COM_SPPAGEBUILDER_ADDON_CLASS'),
				'desc'=>JText::_('COM_SPPAGEBUILDER_ADDON_CLASS_DESC'),
				'std'=> ''
				),
			)
		)
	);
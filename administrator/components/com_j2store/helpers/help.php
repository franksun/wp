<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined('_JEXEC') or die;

/**
 * J2Store help texts and videos.
 */

class J2Help {

	public static $instance = null;
	
	public function __construct($properties=null) {

	}

	public static function getInstance(array $config = array())
	{
		if (!self::$instance)
		{
			self::$instance = new self($config);
		}

		return self::$instance;
	}
	
	public function watch_video_tutorials() {
		$html = '<div class="video-tutorial panel panel-solid-info">
				<p class="panel-body">'.JText::_('J2STORE_VIDEO_TUTORIALS_HELP_TEXT').'
						 				<a class="btn btn-success" target="_blank" href="http://j2store.org/support/video-tutorials/32-version-3x-video-tutorials.html">
						 					'.JText::_('J2STORE_WATCH').'</a>
						 			</p>
						 		</div>';
		return $html;
	}
	
}	
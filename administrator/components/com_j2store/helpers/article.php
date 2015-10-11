<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined ( '_JEXEC' ) or die ();
class J2Article {

	public static $instance = null;
	protected $state;

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
	
	

	/**
	 *
	 * @return unknown_type
	 */
	public function display( $articleid )
	{
		$html = '';
		if(empty($articleid)) {
			return;
		}
		//try loading language associations
		if(version_compare(JVERSION, '3.3', 'gt')) {
			$id = $this->getAssociatedArticle($articleid);
			if($id && is_int($id)) {
				$articleid = $id;
			}
		}
		$item = $this->getArticle($articleid);
		$mainframe = JFactory::getApplication();
		// Return html if the load fails
		if (!$item->id)
		{
			return $html;
		}
	
		$item->title = JFilterOutput::ampReplace($item->title);
	
		$item->text = '';
	
		$item->text = $item->introtext . chr(13).chr(13) . $item->fulltext;
	
		$limitstart	= JRequest::getVar('limitstart', 0, '', 'int');
		$params		=$mainframe->getParams('com_content');
	
		$html .= $item->text;
	
		return $html;
	}
	
	public function getArticle($id) {
		static $sets;
	
		if ( !is_array( $sets ) )
		{
			$sets = array( );
		}
		if ( !isset( $sets[$id] ) )
		{
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('*')->from('#__content')->where('id='.$id);
			$db->setQuery($query);
			$sets[$id] = $db->loadObject();
		}
		return $sets[$id];
	}
	
	public function getArticleByAlias($alias) {
		static $sets;
	
		if ( !is_array( $sets ) )
		{
			$sets = array( );
		}
		if ( !isset( $sets[$alias] ) )
		{
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('*')->from('#__content')
			->where($db->quoteName('alias') . ' = ' . $db->quote($alias));			
			$db->setQuery($query);
			$sets[$alias] = $db->loadObject();
		}
		return $sets[$alias];
	}
	
	public function getAssociatedArticle($id) {
	
		$associated_id =0;
		require_once JPATH_SITE . '/components/com_content/helpers/route.php';
	
		require_once(JPATH_SITE.'/components/com_content/helpers/association.php');
		$result = ContentHelperAssociation::getAssociations($id, 'article');
		$tag = JFactory::getLanguage()->getTag();
		if(isset($result[$tag])) {
			$parts = JString::parse_url($result[$tag]);
			parse_str($parts['query'], $vars);
			if(isset($vars['id'])) {
				$splits = explode(':', $vars['id']);
			}
			$associated_id = (int) $splits[0];
		}
	
		if(isset($associated_id) && $associated_id) {
			$id = $associated_id;
		}
		return $id;
	}
	
}	
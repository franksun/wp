<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined('_JEXEC') or die;
class J2StoreControllerFiltergroups extends F0FController
{

	function deleteproductfiltervalues(){
		$o_id = $this->input->getInt('productfiltervalue_id');
		$productfilter = F0FTable::getAnInstance('filter','J2StoreTable');
		$json = array();
		$json['success']['msg'] = JText::_('J2STORE_PRODUCT_FILTER_VALUE_DELETE_SUCCESS');
		if(!$productfilter->delete($o_id)){
			$json['error']['msg'] = JText::_('J2STORE_PRODUCT_FILTER_VALUE_DELETE_ERROR');
		}
		echo json_encode($json);
		JFactory::getApplication()->close();
	}
}

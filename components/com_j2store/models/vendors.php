<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined('_JEXEC') or die;
class J2StoreModelVendors extends F0FModel {

	public function &getItem($id = null)
	{
		$user = JFactory::getUser();
		$this->record = F0FTable::getAnInstance('Vendoruser','J2StoreTable');
		$this->record->load($user->id);

		$this->record->products = F0FModel::getTmpInstance('Products' ,'J2StoreModel')
		->vendor_id($this->record->j2store_vendor_id)
		->enabled(1)
		->getList();
		return $this->record;
	}

}


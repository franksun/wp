<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined('_JEXEC') or die;
class J2StoreControllerCpanels extends F0FController
{
	 public function execute($task) {
		if(!in_array($task, array('browse'))) {
			$task = 'browse';
		}
		parent::execute($task);
	}

	protected function onBeforeBrowse() {
		$db = JFactory::getDbo();

		$config = J2Store::config();
		$installation_complete = $config->get('installation_complete', 0);
		if(!$installation_complete) {
			//installation not completed
			JFactory::getApplication()->redirect('index.php?option=com_j2store&view=postconfig');
		}

		//first check if the currency table has a default records at least.
		$rows = F0FModel::getTmpInstance('Currencies', 'J2StoreModel')->enabled(1)->getList();
		if(count($rows) < 1) {
			//no records found. Dumb default data
			F0FModel::getTmpInstance('Currencies', 'J2StoreModel')->create_currency_by_code('USD', 'USD');
		}
		//update schema
		$dbInstaller = new F0FDatabaseInstaller(array(
				'dbinstaller_directory'	=> JPATH_ADMINISTRATOR . '/components/com_j2store/sql/xml'
		));
		$dbInstaller->updateSchema();

		//update cart table
		$cols = $db->getTableColumns('#__j2store_carts');
		$cols_to_delete = array('product_id', 'vendor_id', 'variant_id', 'product_type', 'product_options', 'product_qty');
		foreach($cols_to_delete as $key) {
			if(array_key_exists($key, $cols)) {
				$db->setQuery('ALTER TABLE #__j2store_carts DROP COLUMN '.$key);
				try {
					$db->execute();
				}catch(Exception $e) {
					echo $e->getMessage();
				}
			}
		}
		
		return parent::onBeforeBrowse();
	}
}
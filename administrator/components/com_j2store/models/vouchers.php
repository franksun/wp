<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
// No direct access to this file
defined('_JEXEC') or die;

class J2StoreModelVouchers extends F0FModel {
	
	public function getVoucher($code) {
		$status = true;

		$vouchers = $this->enabled(1)->voucher_code($code)->getList();		
		if(count($vouchers) > 1) {
			//duplicate vouchers found. 
			$status = false;
			return $status; 
		}
		
	
		if (isset($vouchers[0]) && $vouchers[0]) {
			$voucher = $vouchers[0];
			$db = JFactory::getDbo();
			$params = J2Store::config();

			//sum of voucher history
			$query = $db->getQuery(true)->select('SUM(amount) as total')->from('#__j2store_voucherhistories')
														-> where('voucher_id='.$db->q($voucher->j2store_voucher_id))
														->group('voucher_id');
			$voucher_history = $db->setQuery($query)->loadAssoc();
			
			if ($voucher_history) {
				$amount = $voucher->voucher_value + $voucher_history['total'];
			} else {
				$amount = $voucher->voucher_value;
			}
				
			if ($amount <= 0) {
				$status = false;
			}
		} else {
			$status = false;
		}
	
		if ($status) {
			$return = array(
					'voucher_id'       => $voucher->j2store_voucher_id,
					'voucher_code'     => $voucher->voucher_code,
					'voucher_to_email'  => $voucher->email_to,
					'message'          => $voucher->email_body,
					'amount'           => $amount,
					'enabled'           => $voucher->enabled,
					'created_on'       => $voucher->created_on
			);
			
			return (object) $return;
		}

		return $status;
	}
	
	public function sendVouchers($cids) {
		$app = JFactory::getApplication ();
		$config = JFactory::getConfig ();
		$params = J2Store::config ();
		
		$sitename = $config->get ( 'sitename' );
		
		$emailHelper = J2Store::email ();
		
		$mailfrom = $config->get ( 'mailfrom' );
		$fromname = $config->get ( 'fromname' );
		
		$failed = 0;
		foreach ( $cids as $cid ) {
			$voucherTable = F0FTable::getAnInstance ( 'Voucher', 'J2StoreTable' )->getClone ();
			$voucherTable->load ( $cid );			
			
			$mailer = JFactory::getMailer ();
			$mailer->setSender ( array (
					$mailfrom,
					$fromname 
			) );
			$mailer->isHtml(true);
			$mailer->addRecipient ( $voucherTable->email_to );
			$mailer->setSubject ( $voucherTable->subject );
			// parse inline images before setting the body
			$emailHelper->processInlineImages ( $voucherTable->email_body, $mailer );
			$mailer->setBody ( $voucherTable->email_body );			
			//Allow plugins to modify
			J2Store::plugin ()->event ( 'BeforeSendVoucher', array ($voucherTable,&$mailer));			
			if($mailer->Send () !== true) {
				$this->setError(JText::sprintf('J2STORE_VOUCHERS_SENDING_FAILED_TO_RECEIPIENT', $voucherTable->email_to));
				$failed++;
			}
			
			J2Store::plugin ()->event ( 'AfterSendVoucher', array ($voucherTable,&$mailer));
			$mailer = null;
		}
		
		if($failed > 0) return false;
		
		return true;
	}
	
	public function getVoucherHistory() {
		
		$app = JFactory::getApplication();
		$id = $app->input->getInt('id', 0);
		
		if($id < 1) return array();
		
		$voucher_history_model = F0FModel::getTmpInstance('Voucherhistories', 'J2StoreModel');
		$items = $voucher_history_model->voucher_id($id)->getList();
		if(count($items)) {
			foreach($items as &$item) {
				$order = F0FTable::getAnInstance('Order', 'J2StoreTable')->getClone();
				$order->load(array('order_id'=>$item->order_id));
				$item->order = $order;
			}
		}	
		return $items;
	}
}
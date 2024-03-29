<?php
/*------------------------------------------------------------------------
# com_j2store - J2Store
# ------------------------------------------------------------------------
# author    Ramesh Elamathi - Weblogicx India http://www.weblogicxindia.com
# copyright Copyright (C) 2014 - 19 Weblogicxindia.com. All Rights Reserved.
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
# Websites: http://j2store.org
# Technical Support:  Forum - http://j2store.org/forum/index.html
-------------------------------------------------------------------------*/

/** ensure this file is being included by a parent file */
defined('_JEXEC') or die('Restricted access');

require_once (JPATH_ADMINISTRATOR.'/components/com_j2store/library/plugins/payment.php');

class plgJ2StorePayment_banktransfer extends J2StorePaymentPlugin
{
	/**
	 * @var $_element  string  Should always correspond with the plugin's filename,
	 *                         forcing it to be unique
	 */
    var $_element    = 'payment_banktransfer';

	function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage( 'com_j2store', JPATH_ADMINISTRATOR );
	}
	

	/**
	 * @param $order     object    Order table object
	 */
	
	function _beforePayment($order) {
		//get surcharge if any
		$surcharge = 0;
	
		$surcharge_percent = $this->params->get('surcharge_percent', 0);
		$surcharge_fixed = $this->params->get('surcharge_fixed', 0);
		if((float) $surcharge_percent > 0 || (float) $surcharge_fixed > 0) {
	
			//percentage
			if((float) $surcharge_percent > 0) {
				$surcharge += ($order->order_total * (float) $surcharge_percent) / 100;
			}
	
			if((float) $surcharge_fixed > 0) {
				$surcharge += (float) $surcharge_fixed;
			}
			//make sure it is formated to 2 decimals
			
		/* 	if($this->params->get('surcharge_tax', 0) && $surcharge > 0) {
				$taxprofile_id = $this->params->get('surcharge_tax', 0);
				//surcharge taxable. So get the tax.
				$taxModel = F0FModel::getTmpInstance ( 'TaxProfiles', 'J2StoreModel' );
				$taxrates = $taxModel->getTaxwithRates ( round($surcharge, 2), $taxprofile_id);
				if (isset ( $taxrates->taxtotal )) {
					$order->addOrderTaxes($taxrates->taxes);
				}
			} */
	
			$order->order_surcharge = round($surcharge, 2);
			$order->getTotals(false);
		}
	
	}


    /**
     * Prepares the payment form
     * and returns HTML Form to be displayed to the user
     * generally will have a message saying, 'confirm entries, then click complete order'
     *
     * @param $data     array       form post data
     * @return string   HTML to display
     */
    function _prePayment( $data )
    {
        // prepare the payment form

        $vars = new JObject();
        $vars->order_id = $data['order_id'];
        $vars->orderpayment_id = $data['orderpayment_id'];
        $vars->orderpayment_amount = $data['orderpayment_amount'];
        $vars->orderpayment_type = $this->_element;
        $vars->bank_information = $this->params->get('bank_information', '');

        $vars->display_name = $this->params->get('display_name', JText::_( "PLG_J2STORE_PAYMENT_BANKTRANSFER"));
        $vars->onbeforepayment_text = $this->params->get('onbeforepayment', '');
        $vars->button_text = $this->params->get('button_text', 'J2STORE_PLACE_ORDER');
        $html = $this->_getLayout('prepayment', $vars);
        return $html;
    }

	/**
	 * Processes the payment form
	 * and returns HTML to be displayed to the user
	 * generally with a success/failed message
	 *
	 * @param $data array
	 *        	form post data
	 * @return string HTML to display
	 */
	function _postPayment($data) {
		// Process the payment
		$app = JFactory::getApplication ();
		$vars = new JObject ();
		$html = '';
		$order_id = $app->input->getString( 'order_id' );
		F0FTable::addIncludePath ( JPATH_ADMINISTRATOR . '/components/com_j2store/tables' );
		$order = F0FTable::getInstance ( 'Order', 'J2StoreTable' )->getClone ();
		if ($order->load ( array (
				'order_id' => $order_id
		) )) {
			$bank_information = $this->params->get ( 'bank_information', '' );

			if (JString::strlen ( $bank_information ) > 5) {

				$html = '<br />';
				$html .= '<strong>' . JText::_ ( 'J2STORE_BANK_TRANSFER_INSTRUCTIONS' ) . '</strong>';
				$html .= '<br />';
				$html .= $bank_information;
				$order->customer_note = $order->customer_note . $html;
			}

			$order_state_id = $this->params->get ( 'payment_status', 4 ); // DEFAULT: PENDING
			if ($order_state_id == 1) {

				// set order to confirmed and set the payment process complete.
				$order->payment_complete ();
			} else {
				// set the chosen order status and force notify customer
				$order->update_status ( $order_state_id, true );
				// also reduce stock
				$order->reduce_order_stock ();
			}

			if ($order->store ()) {
				$vars->onafterpayment_text = $this->params->get ( 'onafterpayment', '' );
				$order->empty_cart();
				$html = $this->_getLayout ( 'postpayment', $vars );

				// append the article with cash payment information
				$html .= $this->_displayArticle ();
			} else {
				$html = $this->params->get ( 'onerrorpayment', '' );
				$html .= $order->getError ();
			}
		} else {
			// order not found
			$html = $this->params->get ( 'onerrorpayment', '' );
		}
		return $html;
	}

    /**
     * Prepares variables and
     * Renders the form for collecting payment info
     *
     * @return unknown_type
     */
    function _renderForm( $data )
    {
    	$user = JFactory::getUser();
        $vars = new JObject();
        $vars->onselection_text = $this->params->get('onselection', '');
        $html = $this->_getLayout('form', $vars);
        return $html;
    }
}

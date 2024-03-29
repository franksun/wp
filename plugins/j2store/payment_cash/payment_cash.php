<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
/** ensure this file is being included by a parent file */
defined('_JEXEC') or die('Restricted access');

require_once (JPATH_ADMINISTRATOR.'/components/com_j2store/library/plugins/payment.php');

class plgJ2StorePayment_cash extends J2StorePaymentPlugin
{
	/**
	 * @var $_element  string  Should always correspond with the plugin's filename,
	 *                         forcing it to be unique
	 */
    var $_element    = 'payment_cash';

	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for plugins
	 * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
	 * This causes problems with cross-referencing necessary for the observer design pattern.
	 *
	 * @param object $subject The object to observe
	 * @param 	array  $config  An array that holds the plugin configuration
	 * @since 2.5
	 */
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

			$order->order_surcharge = round($surcharge, 2);
			$order->getTotals();
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
        $vars->orderpayment_amount = $data['orderpayment_amount'];
        $vars->orderpayment_type = $this->_element;

        $vars->display_name = $this->params->get('display_name', JText::_( "PLG_J2STORE_PAYMENT_CASH"));
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
     * @param $data     array       form post data
     * @return string   HTML to display
     */
    function _postPayment( $data )
    {
        // Process the payment
        $app = JFactory::getApplication();
        $vars = new JObject();
        $html = '';
        $order_id = $app->input->getString('order_id');
        F0FTable::addIncludePath ( JPATH_ADMINISTRATOR . '/components/com_j2store/tables' );
        $order = F0FTable::getInstance ( 'Order', 'J2StoreTable' )->getClone ();
        if ($order->load ( array (
        		'order_id' => $order_id
        ) )) {


			$payment_status = $this->getPaymentStatus($this->params->get('payment_status', 4));
			$order_state_id = $this->params->get('payment_status', 4); // DEFAULT: PENDING

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
				//empty the cart
				$order->empty_cart();
				$vars->onafterpayment_text = $this->params->get('onafterpayment', '');

				$html = $this->_getLayout('postpayment', $vars);

				// append the article with cash payment information
				$html .= $this->_displayArticle();
			} else {
				$html  = $this->params->get('onerrorpayment', '');
				$html .= $order->getError();
			}

        }else {
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

    function getPaymentStatus($payment_status) {
    	$status = '';
    	switch($payment_status) {

    		case 1:
    			$status = JText::_('J2STORE_CONFIRMED');
    			break;

    		case 3:
    			$status = JText::_('J2STORE_FAILED');
    			break;

    		default:
    		case 4:
    			$status = JText::_('J2STORE_PENDING');
    			break;
    	}
    	return $status;
    }
}

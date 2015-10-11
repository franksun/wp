<?php
/*------------------------------------------------------------------------
# mod_j2store_cart - J2 Store Cart
# ------------------------------------------------------------------------
# author    Sasi varna kumar - Weblogicx India http://www.weblogicxindia.com
# copyright Copyright (C) 2014 - 19 Weblogicxindia.com. All Rights Reserved.
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
# Websites: http://j2store.org
# Technical Support:  Forum - http://j2store.org/forum/index.html
-------------------------------------------------------------------------*/



// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );
if (!defined('F0F_INCLUDED'))
{
	include_once JPATH_LIBRARIES . '/f0f/include.php';
}
require_once( dirname(__FILE__).'/helper.php' );
JFactory::getLanguage()->load('com_j2store', JPATH_ADMINISTRATOR);
$moduleclass_sfx = $params->get('moduleclass_sfx','');
$link_type = $params->get('link_type','link');
$currency = J2Store::currency();

$document = JFactory::getDocument();
$document->addStyleSheet(JUri::root().'modules/mod_j2store_cart/css/j2store_cart.css');
$list = modJ2StoreCartHelper::getItems();

$advanced_list = modJ2StoreCartHelper::getAdavcedItems();
$model = F0FModel::getTmpInstance('Carts','J2StoreModel');
$checkout_url = $model->getCheckoutUrl();
$custom_css = $params->get('custom_css', '');
$document->addStyleDeclaration(strip_tags($custom_css));
require( JModuleHelper::getLayoutPath('mod_j2store_cart', $params->get('layout', 'default')));
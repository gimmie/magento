<?php
class Gimmie_WidgetPage_IndexController extends Mage_Core_Controller_Front_Action {
  
  public function proxyAction() {
    $session = Mage::getSingleton('customer/session');
    if ($session->isLoggedIn()) {
      $customer = $session->getCustomer()->getData();
      $email = $customer['email'];
      
      echo 'Hello, World';
    }
    else {
      $this->getResponse()->setRedirect(Mage::getUrl('customer/account'));
    }
  }
  
}
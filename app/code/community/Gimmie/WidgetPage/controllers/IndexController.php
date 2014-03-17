<?php
class Gimmie_WidgetPage_IndexController extends Mage_Core_Controller_Front_Action {
  
  public function proxyAction() {
    require_once(Mage::getBaseDir('lib').'/Gimmie/Gimmie.sdk.php');
    
    $session = Mage::getSingleton('customer/session');
    if ($session->isLoggedIn()) {
      $customer = $session->getCustomer()->getData();
      $email = $customer['email'];
      
      $target = $_REQUEST['target'];
      
      $dfd = Mage::getStoreConfig('Gimmie');
      $config = $dfd['general'];
      
      $key = $config['consumer_key'];
      $secret = $config['secret_key'];
      
      $endpoint = 'https://api.gimmieworld.com'.$target;
      
      $access_token = $email;
      $access_token_secret = $secret;
      
      $params = array();
      $sig_method = new OAuthSignatureMethod_HMAC_SHA1();
      $consumer = new OAuthConsumer($key, $secret, NULL);
      $token = new OAuthConsumer($access_token, $access_token_secret);
      
      $json = '{"response":{"success":false}, "error":{}}';
      if (!is_null($endpoint)) {
        $acc_req = OAuthRequest::from_consumer_and_token($consumer, $token, 'GET', $endpoint, $params);
        $acc_req->sign_request($sig_method, $consumer, $token);
        
        $json = file_get_contents($acc_req);
      }
      
      header('Access-Control-Allow-Origin: *');
      header('Content-type: application/json');
      print($json);
      exit();
    }
    else {
      $this->getResponse()->setRedirect(Mage::getUrl('customer/account'));
    }
  }
  
}

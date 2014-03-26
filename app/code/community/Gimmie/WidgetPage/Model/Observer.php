<?php
class Gimmie_WidgetPage_Model_Observer
{

  const COOKIE_KEY_SOURCE = 'gimmie_widgetpage_source';

  private function getConfig($name) {
    $dfd = Mage::getStoreConfig('Gimmie');
    return $dfd[$name];
  }

  private function getGimmie($email) {
    require_once(Mage::getBaseDir('lib').'/Gimmie/Gimmie.sdk.php');
    $config = $this->getConfig('general');
    $key = $config['consumer_key'];
    $secret = $config['secret_key'];

    $gimmie = Gimmie::getInstance($key, $secret);
    $gimmie->set_user($email);
    return $gimmie;
  }

  public function captureReferral(Varien_Event_Observer $observer)
  {
    if (array_key_exists('id', $_GET)) {
      // here we will save the referrer affiliate ID
      Mage::getModel('core/cookie')->set(
        self::COOKIE_KEY_SOURCE,
        $_GET['id'],
        30 * 86400);
    }
  }

  public function triggerReferral($event) {
    $event = 'did_magento_user_referral_other_user';
    $generalConfig = $this->getConfig('general');
    $pointsConfig = $this->getConfig('points');

    if ($generalConfig['gimmie_enable'] && $pointsConfig['gimmie_trigger_'.$event]) {
      $id= Mage::getModel('core/cookie')->get(
        Gimmie_Customerpage_Model_Observer::COOKIE_KEY_SOURCE
      );

      $customerData = Mage::getModel('customer/customer')->load($id)->getData();
      $email = $customerData['email'];

      $this->getGimmie($email)->trigger($event);
    }
  }

  public function giveoutPointsAndTriggerPurchased($event)
  {
    $order = $event->getOrder();
    $generalConfig = $this->getConfig('general');
    $pointsConfig = $this->getConfig('points');
    
    function entity_name($item) {
      return $item->getData('entity_name');
    }
    function shipment_only($item) {
      return $item == 'shipment';
    }
    $actions = array_map("entity_name", $order->getAllStatusHistory());    
    
    $lastAction = end($actions);
    $shipments = array_filter($actions, "shipment_only");    
    $shouldTriggerGimmie = ($lastAction == 'shipment' && count($shipments) == 1);

    if ($order->hasShipments() && $shouldTriggerGimmie && $generalConfig['gimmie_enable']) {
      $email = $order->getCustomerEmail();

      $purchased_event = 'did_magento_user_purchased_item';
      if ($pointsConfig["gimmie_trigger_$purchased_event"]) {
        $this->getGimmie($email)->trigger($purchased_event);
      }

      $date = getdate(strtotime($order->getCustomerDob()));
      $birthMonth = $date['mon'];
      $currentMonth = date('n');

      $birthmonth_event = 'did_magento_user_purchased_on_birthday_month';
      if ($pointsConfig["gimmie_trigger_$birthmonth_event"] && ($birthMonth == $currentMonth)) {
        $this->getGimmie($email)->trigger($birthmonth_event);
      }

      $amountWithoutTax = $order->getGrandTotal() - $order->getShippingAmount();

      $dollarExchanges = is_numeric($pointsConfig['purchase_exchange_dollar']) ? intval($pointsConfig['purchase_exchange_dollar']) : -1;
      $pointsExchanges = is_numeric($pointsConfig['purchase_exchange_points']) ? intval($pointsConfig['purchase_exchange_points']) : -1;

      if (is_numeric($amountWithoutTax) && $amountWithoutTax > 0 && $dollarExchanges > 0 && $pointsExchanges > 0) {
        $totalPoints = floor($amountWithoutTax / $dollarExchanges) * $pointsExchanges;
        error_log("Award $totalPoints for spending $amountWithoutTax");
        if ($totalPoints > 0) {
          $this->getGimmie($email)->change_points($totalPoints, "Award $totalPoints for spending $amountWithoutTax");
        }
      }

    }
  }
  
  public function monthTopSpender($observer) {
    $event = 'did_magento_user_spent_the_most';
    $generalConfig = $this->getConfig('general');
    $pointsConfig = $this->getConfig('points');
    
    if ($generalConfig['gimmie_enable'] && $pointsConfig["gimmie_trigger_$event"]) {

      $date = getdate(strtotime('-1 months'));
      $targetMonth = $date['mon'];
      $targetYear = $date['year'];

      $first = date('Y-m-d', mktime(0, 0, 0, $targetMonth, 1, $targetYear));
      $last = date('Y-m-t', mktime(0, 0, 0, $targetMonth, 1, $targetYear));

      $read = Mage::getModel('sales/order')->getCollection()->getConnection('core_read');
      $cursor = $read->query('SELECT * , SUM( grand_total ) AS `grand_total` FROM `sales_flat_order` WHERE (created_at >= :from) AND (created_at <= :to) AND `status` = "complete" ORDER BY `grand_total`    DESC LIMIT 0,1', array('from' => $first, 'to' => $last));
      $row = $cursor->fetch();

      $email = $row['customer_email'];
      $this->getGimmie($email)->trigger($event);
    }

  }
  
}

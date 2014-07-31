<?php
// Map functions
function entity_name($item) {
  return $item->getData('entity_name');
}
function shipment_only($item) {
  return $item == 'shipment';
}

// Observer class
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
    if (array_key_exists('gmref', $_GET)) {
      // here we will save the referrer affiliate ID
      Mage::getModel('core/cookie')->set(
        self::COOKIE_KEY_SOURCE,
        $_GET['gmref'],
        30 * 86400);
    }

    return $observer;
  }

  public function flagNewCustomer(Varien_Event_Observer $observer) {
    $object = $observer->getEvent()->getDataObject();
    if ($object->isObjectNew()) {
      $object->newCustomer = true;
    }

    return $observer;
  }

  public function triggerReferral(Varien_Event_Observer $observer) {
    $generalConfig = $this->getConfig('general');
    if ($generalConfig['gimmie_enable']) {
      $pointsConfig = $this->getConfig('points');

      $id = Mage::getModel('core/cookie')->get(self::COOKIE_KEY_SOURCE);
      $object = $observer->getEvent()->getDataObject();

      $triggered = $object->triggered;
      $newCustomer = $object->newCustomer;

      if (!isset($triggered) && isset($newCustomer) && !empty($id)) {
        $object->triggered = true;

        $event = 'refer_a_friend';
        if ($pointsConfig['gimmie_trigger_'.$event]) {
          $customerData = Mage::getModel('customer/customer')->load($id)->getData();
          $email = $customerData['email'];

          $this->getGimmie($email)->trigger($event);
        }

      }

    }

    return $observer;
  }

  public function registerSuccess(Varien_Event_Observer $observer) {
    return $this->triggerEvent('register_user');
  }

  public function loginSuccess(Varien_Event_Observer $observer) {
    return $this->triggerEvent('login_user');
  }

  public function giveoutPointsAndTriggerPurchased(Varien_Event_Observer $event)
  {
    $order = $event->getOrder();
    $generalConfig = $this->getConfig('general');
    $pointsConfig = $this->getConfig('points');
    $actions = array_map("entity_name", $order->getAllStatusHistory());

    $lastAction = end($actions);
    $shipments = array_filter($actions, "shipment_only");
    $shouldTriggerGimmie = ($lastAction == 'shipment' && count($shipments) == 1);

    if ($order->hasShipments() && $shouldTriggerGimmie && $generalConfig['gimmie_enable']) {
      $email = $order->getCustomerEmail();

      $purchased_event = 'purchase_item';
      if ($pointsConfig["gimmie_trigger_$purchased_event"]) {
        $this->getGimmie($email)->trigger($purchased_event);
      }

      $date = getdate(strtotime($order->getCustomerDob()));
      $birthMonth = $date['mon'];
      $currentMonth = date('n');

      $birthmonth_event = 'purchase_item_in_birthday_month';
      if ($pointsConfig["gimmie_trigger_$birthmonth_event"] && ($birthMonth == $currentMonth)) {
        $this->getGimmie($email)->trigger($birthmonth_event);
      }

    }

    return $event;
  }

  public function subscribeNewsletter(Varien_Event_Observer $observer)
  {
    $event = 'subscribe_newsletter';
    if ($this->isEventEnable($event)) {
      $subscriber = $observer->getEvent()->getDataObject();
      $data = $subscriber->getData();

      $statusChange = $subscriber->getIsStatusChanged();
      if ($data['subscriber_status'] == "1" && $statusChange == true) {
        $this->getGimmie($email)->trigger($event);
      }
    }

    return $observer;
  }

  public function monthTopSpender(Varien_Event_Observer $observer) {
    $event = 'top_spender_of_the_month';

    if ($this->isEventEnable($event)) {
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

    return $observer;
  }

  private function triggerEvent($event, $observer) {
    if ($this->isEventEnable($event)) {
      $customer = $observer->getCustomer()->getData();
      $email = $customer['email'];

      $this->getGimmie($email)->trigger($event);

      Mage::log ("Trigger $event for $email");
    }

    return $observer;
  }

  private function isEventEnable($eventName) {
    $generalConfig = $this->getConfig('general');
    $pointsConfig = $this->getConfig('points');

    $enable = $generalConfig['gimmie_enable'] == true && $pointsConfig['gimmie_trigger_'.$eventName] == true;
    return $enable;
  }

}

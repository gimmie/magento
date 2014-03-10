<?php
require_once(Mage::getBaseDir('lib').'/Gimmie/Gimmie.sdk.php');

class Gimmie_Customerpage_Model_Observer
{

  var $gimmie = Array(
    "gimmie_trigger_purchase" => "did_purchase",
    "gimmie_trigger_topspender" => "top_spender",
    "gimmie_trigger_birthdaypurchase" => "did_birthday_purchase",
    "gimmie_trigger_referral" => "did_referral"
  );

  const COOKIE_KEY_SOURCE = 'gimmie_customerpage_source';

  private function getConfig() {
    $dfd = Mage::getStoreConfig('Gimmie');
    return $dfd['message'];
  }

  private function getGimmie($config, $email) {
    $key = $config['consumer_key'];
    $secret = $config['secret_key'];

    $gimmie = Gimmie::getInstance($key, $secret);
    $gimmie->login($email);
    return $gimmie;
  }

  public function captureReferral(Varien_Event_Observer $observer)
  {
    $utmSource =$_GET['id'];

    if ($utmSource) {
      // here we will save the referrer affiliate ID
      Mage::getModel('core/cookie')->set(
        self::COOKIE_KEY_SOURCE,
        $utmSource,
        30 * 86400);
    }

    $dfd = Mage::getStoreConfig('Gimmie');
  }

  public function triggerReferral($event) {
    $event = 'did_magento_user_referral_other_user';
    $config = $this->getConfig();

    if ($config['gimmie_enabled'] && $config['gimmie_trigger_'.$event]) {
      $id= Mage::getModel('core/cookie')->get(
        Gimmie_Customerpage_Model_Observer::COOKIE_KEY_SOURCE
      );

      $customerData = Mage::getModel('customer/customer')->load($id)->getData();
      $email = $customerData['email'];

      $this->getGimmie($config, $email)->trigger($event);
    }
  }

  public function giveoutPointsAndTriggerPurchased($event)
  {
    $config = $this->getConfig();
    if ($order->getState() == Mage_Sales_Model_Order::STATE_COMPLETE && $config['gimmie_enabled']) {

      $order_id = $event->getOrder()->getId();
      $order = Mage::getModel('sales/order')->load($order_id);
      $email = $order->getCustomerEmail();

      $purchased_event = 'did_magento_user_purchased_item';
      if ($config["gimmie_trigger_$purchased_event"]) {
        $this->getGimmie($config, $email)->trigger($purchased_event);
      }

      $date = getdate(strtotime($order->getCustomerDob()));
      $birthMonth = $date['mon'];
      $currentMonth = date('n');

      $birthmonth_event = 'did_magento_user_born_this_month';
      if ($config["gimmie_trigger_$birthmonth_event"] && ($birthMonth == $currentMonth)) {
        $this->getGimmie($config, $email)->trigger($birthmonth_event);
      }

      $amountWithoutTax = $order->getGrandTotal() - $order->getShippingAmount();

      $dollarExchanges = is_numeric($config['purchase_exchange_dollar']) ? intval($config['purchase_exchange_dollar']) : -1;
      $pointsExchanges = is_numeric($config['purchase_exchange_points']) ? intval($config['purchase_exchange_points']) : -1;

      if (is_numeric($amountWithoutTax) && $amountWithoutTax > 0 && $dollarExchanges > 0 && $pointsExchanges > 0) {
        $totalPoints = $amountWithoutTax / $dollarExchanges * $pointsExchanges;
        $this->getGimmie($config, $email)->change_points($totalPoints, "Award $totalPoints for spending $amountWithoutTax");
      }

    }
  }

  public function monthTopSpender(Varian_Event_Observer $observer) {

    $event = 'did_magento_user_spent_the_most';
    $config = $this->getConfig();
    if ($config['gimmie_enabled'] && $config["gimmie_trigger_$event"]) {

      $date = getdate(strtotime('-1 months'));
      $targetMonth = $date['mon'];
      $targetYear = $date['year'];

      $first = date('Y-m-d', mktime(0, 0, 0, $targetMonth, 1, $targetYear));
      $last = date('Y-m-t', mktime(0, 0, 0, $targetMonth, 1, $targetYear));

      $read = Mage::getModel('sales/order')->getCollection()->getConnection('core_read');
      $cursor = $read->query('SELECT * , SUM( grand_total ) AS `grand_total` FROM `sales_flat_order` WHERE (created_at >= :from) AND (created_at <= :to) AND `status` = "complete" ORDER BY `grand_total`    DESC LIMIT 0,1', array('from' => $first, 'to' => $last));
      $row = $cursor->fetch();

      $email = $row['customer_email'];
      $this->getGimmie($config, $email)->trigger($event);
    }

  }

  public function toOptionArray()
  {
    $countries = json_decode(file_get_contents('https://raw.github.com/mledoze/countries/master/dist/countries.json'), true);
    foreach ($countries as &$country) {
      $country['value'] = $country['cca2'];
      $country['label'] = $country['name'];
    }
    
    return $countries;
  }
}

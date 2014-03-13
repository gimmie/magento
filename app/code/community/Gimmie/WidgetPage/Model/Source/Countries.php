<?php
class Gimmie_WidgetPage_Model_Source_Countries {
  
  public function toOptionArray() {
    $countries = json_decode(file_get_contents('https://raw.github.com/mledoze/countries/master/dist/countries.json'), true);
    foreach ($countries as &$country) {
      $country['value'] = $country['cca2'];
      $country['label'] = $country['name'];
    }
    $countries = array_merge(
      array(array('value' => 'auto', 'label' => 'Auto Detect')),
      $countries
    );
    return $countries;
  }
  
}
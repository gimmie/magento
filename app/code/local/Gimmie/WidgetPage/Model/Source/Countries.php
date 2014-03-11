<?php
class Gimmie_WidgetPage_Model_Source_Countries {
  
  public function __construct() {
    $this->countries = json_decode(file_get_contents('https://raw.github.com/mledoze/countries/master/dist/countries.json'), true);
    foreach ($this->countries as &$country) {
      $country['value'] = $country['cca2'];
      $country['label'] = $country['name'];
    }
    $this->countries = array_merge(
      array(array('value' => 'auto', 'label' => 'Auto Detect')),
      $this->countries
    );
  }
  
  public function toOptionArray() {
    return $this->countries;
  }
  
}
<?php
class Gimmie_WidgetPage_Block_Navigation extends Mage_Page_Block_Template_Links_Block
{

  protected $_position = 40;

  protected function _toHtml() {
    $text = "Rewards";

    $this->_label = $text;
    $this->_title = $text;
    $this->_url = "#";

    Mage::log(parent::_toHtml());

    return parent::_toHtml();
  }

  public function getAParams() {
    return 'class="gm-open-catalog"';
  }

}
?>

<?php
class Gimmie_WidgetPage_Block_Navigation extends Mage_Page_Block_Template_Links_Block
{

  protected $_position = 40;

  protected function _toHtml() {
    $enableTopMenu = Mage::getStoreConfig('Gimmie/views/enable_top_links_menu');
    if ($enableTopMenu) {
      $text = Mage::getStoreConfig('Gimmie/views/top_link_menu_name');
      $this->_label = $text;
      $this->_title = $text;
      $this->_url = "#";
      return parent::_toHtml();
    }

    return '';
  }

  public function getAParams() {
    return 'class="gm-open-catalog"';
  }

}
?>

<?php

class Template_Model_Design_Category extends Core_Model_Default {

    protected $_blocks;

    public function __construct($params = []) {
        parent::__construct($params);
        $this->_db_table = 'Template_Model_Db_Table_Design_Category';
        return $this;
    }

}

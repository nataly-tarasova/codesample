<?php

/**
 * Data mapper, common for all data mappers
 *
 *
 *  @uses   Tnv_Model_DbTable_Abstract
 *  @author Nataly Tarasova
 *
 */
class Tnv_Model_DataMapper {
    /**
     *
     * @var Tnv_Model_DbTable_Abstract
     */
    protected $_dbTable;
    protected $_name;
    protected $_collection;
    protected $_element;
    protected $cache;
    /**
     * constructor
     */
    public function __construct($name = '') {
        if (empty ( $name )) {
            $className = get_called_class ();
            $name = str_ireplace ( 'Tnv_Model_DataMapper_', '', $className );
        }
        if (strlen ( $name ) > 0) {
            $this->_name = $name;
        }
    }
    
    /**
     * Specify Zend_Db_Table instance to use for data operations
     *
     * @param Tnv_Model_DbTable_Abstract $dbTable            
     * @return Tnv_Model_DataMapper
     */
    public function setDbTable($dbTable = '') {
        if (is_string ( $dbTable )) {
            if (empty ( $this->_name ) || $this->_name == 'Abstract') {
                $this->_name = str_ireplace ( 'Tnv_Model_DbTable_', '', $dbTable );
            }
            if (file_exists ( MODELS_ROOT . 'DbTable/' . $this->_name . '.php' ) === true) {
                $dbTable = new $dbTable ();
            } else {
                $dbTable = new Tnv_Model_DbTable_Abstract ( array (
                        'name' => $this->_name 
                ) );
            }
        }
        
        if (! $dbTable instanceof Tnv_Model_DbTable_Abstract) {
            throw new Exception ( 'Invalid table data gateway provided' );
        }
        $this->_dbTable = $dbTable;
        return $this;
    }
    
    /**
     * Get registered Zend_Db_Table instance
     *
     * @return Zend_Db_Table_Abstract
     */
    public function getDbTable() {
        if (null === $this->_dbTable) {
            $this->setDbTable ( 'Tnv_Model_DbTable_' . $this->_name );
        }
        return $this->_dbTable;
    }
    public function setCollection($collection = '') {
        if (empty ( $collection )) {
            $collectionName = 'Tnv_Model_Collection_' . $this->_name;
            if (file_exists ( MODELS_ROOT . 'Collection/' . $this->_name . '.php' ) === true) {
                $collection = new $collectionName ();
            } else {
                $collection = new Tnv_Model_Collection_Abstract ();
            }
        }
        if (! $collection instanceof Tnv_Model_Collection_Abstract) {
            throw new Exception ( 'Invalid collection provided' );
        }
        $this->_collection = $collection;
        return $this;
    }
    public function getCollection() {
        if (null === $this->_collection) {
            $this->setCollection ();
        }
        return $this->_collection;
    }
    public function setElement($element = '') {
        if (empty ( $element )) {
            $elementName = 'Tnv_Model_Element_' . $this->_name;
            if (file_exists ( MODELS_ROOT . 'Element/' . $this->_name . '.php' ) === true) {
                $element = new $elementName ();
            } else {
                $element = new Tnv_Model_Element_Abstract ();
            }
        }
        if (! $element instanceof Tnv_Model_Element_Abstract) {
            throw new Exception ( 'Invalid element provided' );
        }
        $this->_element = $element;
        return $this;
    }
    public function getElement() {
        if (null === $this->_element) {
            $this->setElement ();
        }
        return $this->_element;
    }
    
    /**
     * Save an entry
     *
     * @param array $data            
     * @return void
     */
    public function saveRow($data) {
        if (! isset ( $data ['id'] )) {
            return $this->getDbTable ()->saveRow ( $data );
        } else {
            $id = ( int ) $data ['id'];
            unset ( $data ['id'] );
            return $this->getDbTable ()->saveRow ( $data, $id );
        }
    }
    
    /**
     * delete an entry
     *
     * @param array $data
     *            - array for where
     * @return void
     */
    public function delete($where) {
        if (is_array ( $where ))
            $where = $this->getDbTable ()->createSQL ( $where );
        return $this->getDbTable ()->delete ( $where );
    }
    
    /**
     * delete an entry
     *
     * @param array $data
     *            - array for where
     * @return void
     */
    public function update($data, $where) {
        if (is_array ( $where ))
            $where = $this->getDbTable ()->createSQL ( $where );
        return $this->getDbTable ()->update ( $data, $where );
    }
    
    /**
     * Find entry by id
     *
     * @param int $id            
     * @param Tnv_Model_Element_Abstract $element            
     * @return void
     */
    public function find($id) {
        $result = $this->getDbTable ()->find ( $id );
        if (0 == count ( $result )) {
            return;
        }
        $row = $result->current ();
        $this->getElement ()->setData ( $row );
    }
    
    /**
     * Fetch all entries
     *
     * @return void
     */
    public function getData($params, $order = '', $group = '', $cols = '*', $join = '', $joinType = '', $limit = 0, $from = 0) {
        if (isset ( $this->cache ) && $this->cache instanceof Zend_Cache_Core) {
            $key = $this->setCacheKey ( array (
                    $params,
                    $order,
                    $group,
                    $cols,
                    $join,
                    $joinType,
                    $limit,
                    $from 
            ) );
            if (! ($this->cache->test ( $key ))) {
                if (is_array ( $params )) {
                    $data = $this->getDbTable ()->selectData ( $params, $order, $group, $cols, $join, $joinType, $limit, $from );
                } else {
                    $data = $this->getDbTable ()->getAllData ( $params, $order, $group, $cols, $join, $joinType, $limit, $from );
                }
                $this->cache->save ( $data, $key );
            } else {
                $data = $this->cache->load ( $key );
            }
        } else {
            if (is_array ( $params )) {
                $data = $this->getDbTable ()->selectData ( $params, $order, $group, $cols, $join, $joinType, $limit, $from );
            } else {
                $data = $this->getDbTable ()->getAllData ( $params, $order, $group, $cols, $join, $joinType, $limit, $from );
            }
        }
        $element = $this->getElement ();
        $this->getCollection ()->setElementObj ( $element );
        $this->getCollection ()->setData ( $data );
        return $this->getCollection ();
    }
    protected function setCacheKey(array $params) {
        $key = '';
        foreach ( $params as $val ) {
            if (is_array ( $val ))
                $key .= serialize ( $val );
            else
                $key .= $val;
        }
        return $this->_name . md5 ( $key );
    }
}

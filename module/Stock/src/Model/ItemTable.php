<?php
namespace Stock\Model;

use Laminas\Db\Sql\Expression;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

class ItemTable extends AbstractTableGateway 
{
	protected $table = 'st_items'; //tablename

	public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }	

	/**
	 * Return All records of table
	 * @return Array
	 */
	public function getAll()
	{  
	    $adapter = $this->adapter;
	    $sql = new Sql($adapter);
	    $select = $sql->select();
	    $select->from($this->table)
			   ->order(array('code ASC'));
	    $selectString = $sql->getSqlStringForSqlObject($select);
	    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    return $results;
	}
	
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function get($param)
	{
		$where = ( is_array($param) )? $param: array('i.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('i'=>$this->table))
			   ->join(array('u'=>'st_uom'), 'u.id = i.uom', array('st_uom_code'=>'code', 'st_uom_id' => 'id'))
			   ->where($where)
			   ->order(array('code ASC'));
		
	    $selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString; exit;
	    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    
	    return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getitemByClass($param)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('i'=>$this->table))
	           ->join(array('ig'=>'st_item_group'), 'ig.id = i.item_group')
	           ->join(array('ic'=>'st_item_class'), 'ic.id = ig.item_class')
			   ->columns(array(
				'id','code',
				))
			   ->where(array('ig.id' => $param))
			   
			   ->order(array('code ASC'));
		
	    $selectString = $sql->getSqlStringForSqlObject($select);
	  //  echo $selectString; exit;
	    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		print_r( $results);exit;
	    return $results;
	}
	
	
	/**
	 * Return column value of given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getColumn($param, $column)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$fetch = array($column);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns($fetch);
		$select->where($where);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    $columns='';
		foreach ($results as $result):
		$columns =  $result[$column];
		endforeach;
		 
		return $columns;
	}
	
	/**
	 * Save record
	 * @param String $array
	 * @return Int
	 */
	public function save($data)
	{
	    if ( !is_array($data) ) $data = $data->toArray();
	    $id = isset($data['id']) ? (int)$data['id'] : 0;
	    
	    if ( $id > 0 )
	    {
	    	$result = ($this->update($data, array('id'=>$id)))?$id:0;
	    } else {
	        $this->insert($data);
	    	$result = $this->getLastInsertValue(); 
	    }	    	    
	    return $result;	     
	}

	/**
     *  Delete a record
     *  @param int $id
     *  @return true | false
     */
	public function remove($id)
	{
		return $this->delete(array('id' => $id));
	}
	/**
	* check particular row is present in the table 
	* with given column and its value
	* MODIFIED FOR FROM VALIDATION (REMOTE VALIDATOR) 
	*/
	public function isPresent($column, $value)
	{
		$column = $column; $value = $value;
		$resultSet = $this->select(function(Select $select) use ($column, $value){
			$select->where(array($column => $value));
		});
		
		$resultSet = $resultSet->toArray();
		return (sizeof($resultSet)>0)?FALSE:TRUE;
	}  
	
	/**
	 * get Item According to Item and PO Number
	 * @param int $id
	 * @return Sring $column
	 */
	public function getItem($porder_id)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		
		$sub0 = new Select('pur_po_details');
		$sub0->columns(array('item'))
			->where(array('purchase_order' => $porder_id));
		
		$select->from($this->table)
			->where->in('id', $sub0);
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * get distinct items for Moving items
	 * @return array
	 */
	public function getMovingItem()
	{
		$adapter = $this->adapter;
	
		$sub0 = new Select('st_moving_items');
		$sub0->columns(array('item' => new Expression('DISTINCT(item)')));
	
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
		->where->in('id', $sub0);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	 public function getItemBy($subgroup)
	{
		//echo $act."<br>".$class."<br>".$group."<br>";exit;
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->order(array('code ASC'));
			   if($subgroup != '-1'){
					$select->where(array('item_subgroup'=>$subgroup));
				}
		
	    $selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
	    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    
	    return $results;
	}
	/**
	 * Return Count
	 * @param Array $id
	 * @return $count
	*/
	public function getCount($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array('num' => new \Laminas\Db\Sql\Expression('COUNT(*)')));
		if($param):
			$select->where($where);
		endif;
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		foreach ($results as $result):
			$num =  $result['num'];
		endforeach;
		return $num; 
	}
}

<?php
namespace Store\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class IssueDetailsTable extends AbstractTableGateway 
{
	protected $table = 'in_issue_details'; //tablename
    
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
	    $select->from(array('d' => $this->table))
			   ->join(array('u'=>'st_uom'),'u.id = d.uom', array('uom'=>'code','uom_id' => 'id'))
			   ->join(array('i'=>'st_items'),'i.id = d.item', array('item'=>'code','item_id' => 'id'))
	    	   ->order(array('id DESC'));
	    
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
		$where = ( is_array($param) )? $param: array('d.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('i'=>'in_items'),'i.id = d.item', array('item'=>'code','item_id' => 'id'))
			   ->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
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
	* 
	*/
	public function isPresent($column, $value)
	{
		$column = $column; $value = $value;
		$resultSet = $this->select(function(Select $select) use ($column, $value){
			$select->where(array($column => $value));
		});
		
		$resultSet = $resultSet->toArray();
		return (sizeof($resultSet)>0)? TRUE:FALSE;
	}  
	
	/**
	 * Return id's|columns'value  which is not present in given array
	 * @param Array $param
	 * @param String column
	 * @return Array
	 */
	public function getNotIn($param, $column='id', $where=NULL)
	{
		$param = ( is_array($param) )? $param: array($param);
		$where = (is_array($column)) ? $column: $where;
		$column = (is_array($column)) ? 'id' : $column;
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = new Select();
		$select->from($this->table)
		->columns(array('id'))
		->where->notIn($column, $param);
		if ($where != Null)
		{
			$select->where($where);
		}
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		return $results;
	}
	
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @param date
	 * @return Array
	 */
	public function getfreightBySupDate($from_location, $to_location, $supplier, $start_date, $end_date)
	{		
		$status = array(3);
		$transportCharge = array(0);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		
		$select->from(array('dd'=>$this->table))
		       ->join(array('d'=>'st_dispatch'), 'dd.dispatch = d.id', array('dispatch_date', 'challan_no', 'from_location', 'to_location', 'status'))
	           ->join(array('i'=>'st_items'), 'dd.item = i.id', array('item_id' => 'id', 'supplier', 'transportation_charge'));
	      
	    if($from_location != -1):
			$select->where(array('d.from_location'=>$from_location));
		endif;
		if($to_location != -1):
			$select->where(array('d.to_location'=>$to_location));
		endif;
		if($supplier != ""):
			$select->where(array('i.supplier'=>$supplier));
		endif;
		$select->where->between('d.dispatch_date', $start_date, $end_date)
			   ->where->in('d.status', $status)
			   ->where->in('i.transportation_charge', $transportCharge);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @param date
	 * @return Array
	 */
	public function getDistinctISGID($param)
	{	
        $where = ( is_array($param) )? $param: array($param);	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		
		$select->from(array('prd'=>$this->table))
	           ->join(array('i'=>'in_items'), 'prd.item = i.id', array())
	           ->join(array('sg'=>'in_item_subgroup'), 'i.item_sub_group = sg.id', array())
			   ->columns(array(new Expression('DISTINCT(item_sub_group) as item_sub_group_id')));
		$select->where($where);		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
		/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @param date
	 * @return Array
	 */
	public function getAmount($param,$item_group_id)
	{	
        $where = ( is_array($param) )? $param: array($param);	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		
		$select->from(array('prd'=>$this->table))
	           ->join(array('i'=>'in_items'), 'prd.item = i.id', array())
	           ->join(array('sg'=>'in_item_subgroup'), 'i.item_sub_group = sg.id', array())
			   ->columns(array(
					'sum' => new Expression('SUM(prd.quantity*prd.rate)')
			    ));
		$select->where(array('sg.id'=>$item_group_id));		
		$select->where($where);		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
		return $column;
	}
        /**
	 * Store Report
	 * Return sum  value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getSUM($start_date,$end_date,$location,$param, $column)
	{
		$where = ( is_array($param) )? $param: array($param);
		
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('id'=>$this->table))
		       ->join(array('i'=>'in_issue'),'i.id=id.issue',array());
		$select->columns(array(
			'sum' => new Expression('SUM('.$column.')')
		));
		$select->where->between('i.issue_date',$start_date,$end_date);
		if($location != 0):
		$select->where->equalTo('i.from_location',$location);
		endif;
		$select->where($where);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
	
		return $column;
	}
        /**
	 * Store Report
	 * Return sum  value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getSUMQty($end_date,$location,$param, $column)
	{
		$where = ( is_array($param) )? $param: array($param);
		
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('id'=>$this->table))
		       ->join(array('i'=>'in_issue'),'i.id=id.issue',array());
		$select->columns(array(
			'sum' => new Expression('SUM('.$column.')')
		));
		$select->where->lessThanOrEqualTo('i.issue_date',$end_date);
		if($location != 0):
		$select->where->equalTo('i.from_location',$location);
		endif;
		$select->where($where);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
	
		return $column;
	}

       
	/**
	 * Store Report
	 * Return sum  value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getSUMAmt($end_date,$location,$param, $column)
	{
		$where = ( is_array($param) )? $param: array($param);
		
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('id'=>$this->table))
		       ->join(array('i'=>'in_issue'),'i.id=id.issue',array());
		$select->columns(array(
			'sum' => new Expression('SUM('.$column.')')
		));
		$select->where->lessThanOrEqualTo('i.issue_date',$end_date);
		if($location != 0):
		$select->where->equalTo('i.from_location',$location);
		endif;
		$select->where($where);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
	
		return $column;
	}
       /**
	 * Store Report
	 * Return sum  value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getSUMQty1($start_date,$end_date,$location,$param, $column)
	{
		$where = ( is_array($param) )? $param: array($param);
		
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('id'=>$this->table))
		       ->join(array('i'=>'in_issue'),'i.id=id.issue',array());
		$select->columns(array(
			'sum' => new Expression('SUM('.$column.')')
		));
		$select->where->between('i.issue_date',$start_date,$end_date);
		if($location != 0):
		$select->where->equalTo('i.from_location',$location);
		endif;
		$select->where($where);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
	
		return $column;
	}
	
	/**
	 * Store Report
	 * Return sum  value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getSUMAmt1($start_date,$end_date,$location,$param, $column)
	{
		$where = ( is_array($param) )? $param: array($param);
		
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('id'=>$this->table))
		       ->join(array('i'=>'in_issue'),'i.id=id.issue',array());
		$select->columns(array(
			'sum' => new Expression('SUM('.$column.')')
		));
		$select->where->between('i.issue_date',$start_date,$end_date);
		if($location != 0):
		$select->where->equalTo('i.from_location',$location);
		endif;
		$select->where($where);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
	
		return $column;
	}
}


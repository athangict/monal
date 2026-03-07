<?php
namespace Stock\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class MovingItemSpTable extends AbstractTableGateway 
{
	protected $table = 'st_moving_items_sp'; //tablename

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
	    $select->from($this->table);
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
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
		       ->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getByItemLocation($item, $location)
	{		
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		if($item != '-1'){
			$select->where(array('item'=>$item));
		}
		if($location != '-1'){
			$select->where(array('location'=>$location));
		}

		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return column value of given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getRateColumn($param,$date)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('m' => $this->table))
				->join(array('bd'=>'st_batch_details'),'m.batch = bd.batch', array('sp'=>'selling_price'));
		$select->where($where)
			   ->order(array('m.sp_effect_date DESC'))
			   ->Limit(1);
		$select->where->lessThanOrEqualTo('m.sp_effect_date', $date); 
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
		$columns =  $result['sp'];
		endforeach;
		 
		return $columns;
	}
      /**
	 * Return column value of given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getBatchColumn($param,$date)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->where($where)
			   ->order(array('sp_effect_date DESC'))
			   ->Limit(1);
		//$select->where->lessThanOrEqualTo('sp_effect_date', $date); 
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
		    $columns =  $result['batch'];
		endforeach;
		 
		return $columns;
	}
	 /**
	 * Return column value of given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getBatchColumn1($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->where($where)
			   ->order(array('sp_effect_date DESC'))
			   ->Limit(1);

		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
		    $columns =  $result['batch'];
		endforeach;
		 
		return $columns;
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
	 * Return sum value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getStockBalance($column, $where=NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'sum' => new Expression('SUM('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
		$column =  $result['sum'];
		endforeach;
	
		return $column;
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
	 * Save record by Item_id and Location
	 * @param String $array
	 * @return Int
	 */
	public function updateByItemLocation($item, $location, $data)
	{
		if($item > 0 && $location > 0 && is_array($data))
		{
			$result = ($this->update($data, array('location'=>$location, 'item'=>$item)))?1:0;
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
	 * Return Min value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMin($column, $where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'min' => new Expression('MIN('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
		$column =  $result['min'];
		endforeach;
	
		return $column;
	}
	
	/**
	 * Return max value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMax($column, $where=NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'max' => new Expression('MAX('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
		$column =  $result['max'];
		endforeach;
	
		return $column;
	}
	
	/**
	 * DISPATCH
	 * Return Distinct Items in the Activity
	 * @param Int $activity
	 * @return Array
	 */
	public function getMovingItems($activity)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('m'=>$this->table))
			   ->join(array('i'=>'st_items'), 'i.id = m.item', array('code','activity','valuation'))
			   ->columns(array(new Expression('DISTINCT(m.item) as id')))
			   ->where(array('activity'=>$activity,'valuation'=>'1'))
			   ->order(array('id'));
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * SALES
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getSaleItems($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->where($where)
				->where->greaterThan('quantity','0.00');
				
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo "<pre>"; print_r($selectString); exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * STOCK MOVEMENT
	 * Return max value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getSUM($where=NULL, $column)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'sum' => new Expression('SUM('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
			$select->where->greaterThan('quantity','0');
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
	
		return $column;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getCount($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'count' => new Expression('COUNT(location)')
		));
		$select->where($where);
		$select->where->greaterThan('quantity','0');
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
			$column =  $result['count'];
		endforeach;
	
		return $column;
	}
	/**
	 * Return records of given condition array
	 * @param Int $column
	 * @param Int $param
	 * @return Array
	 */
	public function getMaxRow($column,$param)
	{
		$where = ( is_array($param) )? $param: array('ba.id' => $param);
		$adapter = $this->adapter;
		
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MAX('.$column.')')
		));
		
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->where($where)
				->where($column);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return column value of given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getEffectDateItems($param) 
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$date = date('Y-m-d');
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('m' => $this->table))
				->join(array('bd'=>'st_batch_details'),'m.batch = bd.batch', array('sp'=>'selling_price'))
				->join(array('i'=>'st_items'),'m.item = i.id', array('item_name'=>'name'));
		$select->where($where)
			   ->order(array('m.sp_effect_date DESC'))
			   ->Limit(5);
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		 
		return $results;
	}
/**
	 * SELLING PRICE
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @param date
	 * @return Array
	 */
	public function getByItemDate($item_id,$start_date, $end_date)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->where(array('item'=>$item_id))
			   ->where->between('sp_effect_date', $start_date, $end_date);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * SELLING PRICE
	 * Return column value of given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getBatchByDate($param,$date)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('m' => $this->table));
		$select->where($where)
			   ->order(array('m.sp_effect_date DESC'))
			   ->Limit(1);
		$select->where->lessThanOrEqualTo('m.sp_effect_date', $date); 
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		 
		return $results;
	}
	/**
	 * SELLING PRICE
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @param date
	 * @return Array
	 */
	public function getSellingPrice($start_date, $end_date, $item, $activity)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('m' => $this->table))
		       ->join(array('i'=>'st_items'), 'i.id = m.item', array())
			   ->where(array('i.activity' => $activity))
			   ->order(array('m.item ASC', 'sp_effect_date ASC'))
			   ->where->between('m.sp_effect_date', $start_date, $end_date);
			   if($item != '-1'){
				   $select->where(array('m.item' => $item));
			   }
			   
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * SELLING PRICE
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @param date
	 * @return Array
	 */
	public function getspEffectdate($start_date, $end_date)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('m' => $this->table))
		       ->join(array('i'=>'st_items'), 'i.id = m.item', array('code'))
			   ->order(array('m.id ASC', 'm.sp_effect_date ASC'))
			   ->where->between('m.sp_effect_date', $start_date, $end_date);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return max value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMaxBatchdate($column,$where=NULL,$start_date,$end_date)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
				//->where->between('b.sp_effect_date',$start_date,$end_date)
				->where->lessThanOrEqualTo('b.sp_effect_date',$end_date);
		$select->columns(array(
				'max' => new Expression('MAX('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
		$column =  $result['max'];
		endforeach;
	
		return $column;
	}
	/**
	 * Return max value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMaxBatchID($batc_id,$where=NULL,$item)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table));
		$select->columns(array(
				'max' => new Expression('MAX('.$batc_id.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
		$column =  $result['max'];
		endforeach;
	
		return $column;
	}
/**
	 * Return row value of given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getBatchRowbyLocation($param,$max_spe_date,$location)
	{
		$threemonth = date('Y-m-d', strtotime($max_spe_date.'-3 months'));
		$where = ( is_array($param) )? $param: array('id' => $param);
		
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
		        ->join(array('bd'=>'st_batch_details'), 'b.batch = bd.batch', array()) 
			    ->where($where);
				$select->where->between('b.sp_effect_date',$threemonth,$max_spe_date); 
				$select->where->lessThanOrEqualTo('b.sp_effect_date',$max_spe_date); 
				$select->where->equalTo('bd.location',$location); 
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		 
		return $results;
	}
	
	/**
	 * Return max value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMaxBatchdateforAllLocs($column,$where=NULL,$end_date)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
				->where->lessThanOrEqualTo('b.sp_effect_date',$end_date);
		$select->columns(array(
				'max' => new Expression('MAX('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
		$column =  $result['max'];
		endforeach;
	
		return $column;
	}
	/**
	 * Return row value of given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getMaxBatchIdforAllLocs($column,$param,$max_spe_date)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$threemonth = date('Y-m-d', strtotime($max_spe_date.'-3 months'));
			
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
		    ->join(array('bd'=>'st_batch_details'), 'b.batch = bd.batch', array()) 
			->where($where);
		$select->where->between('b.sp_effect_date',$threemonth,$max_spe_date); 
		$select->where->lessThanOrEqualTo('b.sp_effect_date',$max_spe_date); 
		$select->columns(array(
				'max' => new Expression('MAX('.$column.')')
		));   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		 
		foreach ($results as $result):
		$column =  $result['max'];
		endforeach;
	
		return $column;
	}
	/**
	 * Return row value of given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getBatchRow($param,$max_spe_date)
	{
		$threemonth = date('Y-m-d', strtotime($max_spe_date.'-3 months'));
		$where = ( is_array($param) )? $param: array('id' => $param);
		
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->where($where);
		$select->where->between('sp_effect_date',$threemonth,$max_spe_date); 
		$select->where->lessThanOrEqualTo('sp_effect_date',$max_spe_date); 
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		 
		return $results;
	}
	/**
	 * Return max value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMaxBatchdateByLocation($column,$where=NULL,$end_date,$location)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
				->join(array('bd'=>'st_batch_details'), 'b.batch = bd.batch', array())
				->where->lessThanOrEqualTo('b.sp_effect_date',$end_date)
				->where->equalTo('bd.location',$location);
		$select->columns(array(
				'max' => new Expression('MAX('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
		$column =  $result['max'];
		endforeach;
	
		return $column;
	}
	/**
	 * Return max value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMaxBatchIdByLocation($column,$where=NULL,$max_spe_date,$location)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
				->join(array('bd'=>'st_batch_details'), 'b.batch = bd.batch', array())
				->where->lessThanOrequalTo('b.sp_effect_date',$max_spe_date)
				->where->equalTo('bd.location',$location);
		$select->columns(array(
				'max' => new Expression('MAX('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
		$column =  $result['max'];
		endforeach;
	
		return $column;
	}
}

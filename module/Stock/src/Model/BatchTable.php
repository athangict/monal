<?php
namespace Stock\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class BatchTable extends AbstractTableGateway 
{
	protected $table = 'st_batch'; //tablename

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
	    $select->from(array('b'=>$this->table))
	    	   ->join(array('i'=>'st_items'), 'i.id = b.item', array('item_code'=>'code', 'item_id' => 'id'))
	    	   ->join(array('u'=>'st_uom'), 'u.id = b.uom', array('uom_code'=>'code', 'uom_id' => 'id'))
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
	public function getBatchSpEffected($param)
	{
		$where = ( is_array($param) )? $param: array('b.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table), array('id','item','uom','author'))
			   ->join(array('d'=>'st_batch_details'), 'd.batch = b.id', array('quantity', 'location', 'selling_price'))
		       ->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 * getBatchForMISp -> get batch to update in St_Moving_Items_Sp
	 */
	public function getBatchForMISp($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b' => $this->table), array('id','item','uom','author'));
		$select->join(array('i' => 'st_items'), 'i.id = b.item', array())
				->where(array('i.valuation' => '1'))
			   ->where($where);
		
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
	public function getBatchdetails($param)
	{
		$where = ( is_array($param) )? $param: array('b.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
			   ->join(array('i'=>'st_items'), 'i.id = b.item', array('item_code'=>'code', 'item_id' => 'id', 'valuation'))
			   ->join(array('bd'=>'st_batch_details'), 'b.id = bd.batch', array())
		       ->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
         /**
	 * Return max value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMaxbat($column,$where=NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
				->join(array('d'=>'st_batch_details'),'d.batch=b.id');
				//->where->greaterThan('d.quantity','0.00');
		$select->columns(array(
				'max' => new Expression('MIN('.$column.')')
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
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function get($param)
	{
		$where = ( is_array($param) )? $param: array('b.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
			   ->join(array('i'=>'st_items'), 'i.id = b.item', array('item_code'=>'code', 'item_id' => 'id', 'valuation'))
			   ->join(array('u'=>'st_uom'), 'u.id = b.uom', array('uom_code'=>'code', 'uom_id' => 'id'))
		       ->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getMIbatch($param)
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
	 * Return records of given condition array
	 * @param Int $column
	 * @param Int $param
	 * @return Array
	 */
	public function getMonthlyBatch($prefix_code)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->where->like('batch', $prefix_code."%");
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		return  $results;
	}
	
	/**
	 * DISPATCH
	 * Return Distinct Items in the Activity
	 * @param Int $activity
	 * @return Array
	 */
	public function getBatchItems($activity)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
			   ->join(array('i'=>'st_items'), 'i.id = b.item', array('code','name','activity','valuation'))
			   ->columns(array(new Expression('DISTINCT(b.item) as id')))
			   ->where(array('activity'=>$activity,'valuation'=>'0'))
			   ->order(array('id'));
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
/**
	 * DISPATCH 2
	 * Return All distinct column records of table
	 * @return Array
	 */
	public function getDispatchItems($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
			->join(array('d'=>'st_batch_details'), 'd.batch = b.id', array())
    		->join(array('i'=>'st_items'), 'i.id = b.item', array('name','code'))
    		->columns(array(new Expression('DISTINCT(b.item) as item_id')));
		$select->where($where)
		       ->order(array('i.name'));
		$select->where(array('i.valuation'=>0))
				->where->greaterThan('d.quantity','0.00');
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return max value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMax($column,$where=NULL)
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
	public function getMaxbatch($column,$where=NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
				->join(array('d'=>'st_batch_details'),'d.batch=b.id')
				->where->greaterThan('d.quantity','0.00');
		$select->columns(array(
				'max' => new Expression('MIN('.$column.')')
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
	 * SALES
	 * Return All distinct column records of table
	 * @return Array
	 */
	public function getSaleItems($param,$assigned_act_array=NULL)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
			->join(array('d'=>'st_batch_details'), 'd.batch = b.id', array())
    		->join(array('i'=>'st_items'), 'i.id = b.item', array('item'=>'code', 'item_id' => 'id', 'valuation'=>'valuation'))
    		->columns(array(new Expression('DISTINCT(b.item) as item')));
		$select->where($where);
		$select->where(array('valuation'=>0))
				->where->greaterThan('d.quantity','0.00');
				if($assigned_act_array!=NULL){
					$select->where(array('i.activity'=>$assigned_act_array));
				}	
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo "<pre>"; print_r($selectString); exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * SALES
	 * Return All batches by item
	 * @param Array
	 * @return Array
	 */
	public function getSalesBatch($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
				->join(array('d'=>'st_batch_details'), 'b.id = d.batch', array('quantity','location'))
				->where($where)
				->where->greaterThan('d.quantity','0.00');
				//->where//->lessThanOrEqualTo('b.end_date',date('Y-m-d'))
						//->or
						//->equalTo('b.end_date','0000-00-00');
				
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo "<pre>"; print_r($selectString); exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
        /**
	 * Sam
	 * Return All batches by item
	 * @param Array
	 * @return Array
	 */
	public function getSamBatch($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
				->join(array('d'=>'st_batch_details'), 'b.id = d.batch', array('quantity','location'))
				->where($where)
				->where->greaterThanOrEqualTo('d.quantity','0.00');
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo "<pre>"; print_r($selectString); exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * STOCK MOVEMENT
	 * Return distinct item 
	 * @param Int | Array
	 * @return Array
	 */
	public function getSMDistinctItems($param,$item)
	{
		$where = ( is_array($param) )? $param: array('b.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
			   //->join(array('d'=>'st_batch_details'), 'd.batch = b.id', array('location','quantity'))
			   ->join(array('i'=>'st_items'), 'i.id = b.item', array('code','name','activity','supplier','valuation'))
			   ->columns(array(new Expression('DISTINCT(b.item) as item_id')))
			   ->where($where)
			   ->order(array('i.supplier','b.item'));
			   if($item!='-1'){
					$select->where(array('b.item'=>$item));
				}
			   //->where->greaterThan('d.quantity','0')
		       //->where->between('b.batch_date', $start_date, $end_date);
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * STOCK MOVEMENT
	 * Return distinct item 
	 * @param Int | Array
	 * @return Array
	 */
	public function getBatchDistinctItems($param)
	{
		$where = ( is_array($param) )? $param: array('b.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
			   //->join(array('d'=>'st_batch_details'), 'd.batch = b.id', array('location','quantity'))
			   ->join(array('i'=>'st_items'), 'i.id = b.item', array('code','name','activity','supplier','valuation'))
			   ->columns(array(new Expression('DISTINCT(b.item) as item_id')))
			   ->where($where)
			   ->order(array('i.supplier','b.item'));
			   //->where->greaterThan('d.quantity','0')
		       //->where->between('b.batch_date', $start_date, $end_date);
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * STOCK Movement
	 * Return Batches of a item
	 * @param Array | Int
	 * @return Array
	 * Search the batch for same landed cost after 6 months from now only
	 */
	public function getExistingBatch($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$sixmonth = date('Y-m-d', strtotime(date('Y-m-d').'-7 months'));
		
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->where($where)
			    ->where->greaterThan('batch_date',$sixmonth);
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	
	/**
	 * Return records of given year and month
	 * @param Int $id
	 * @return Array
	 */
	public function getDateWise($column,$year,$month)
	{	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
	    	   ->join(array('i'=>'st_items'), 'i.id = b.item', array('item_code'=>'code', 'item_id' => 'id'))
	    	   ->join(array('u'=>'st_uom'), 'u.id = b.uom', array('uom_code'=>'code', 'uom_id' => 'id'))
                 ->columns(array(
					'id','batch','item','uom','location','quantity','unit_uom','barcode','landed_cost','batch_date','expiry_date','end_date',
					'costing','status','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ))->having(array('year' => $year, 'month' => $month))
	    	   ->order(array('id ASC'));
		       
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
    /**
	 * STOCK MOVEMENT 2
	 * Return Item Batches
	 * @param Int | Array
	 * @return Array
	 */
	public function getDistinctItemBatch($param,$item)
	{
		$where = ( is_array($param) )? $param: array('b.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
			   ->join(array('i'=>'st_items'), 'i.id = b.item', array('code','name','activity','supplier','valuation'))
			   ->where($where)
			   ->order(array('i.supplier','i.code'));
				if($item!='-1'){
					$select->where(array('b.item'=>$item));
				}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
/**
	 * STOCK MOVEMENT 3
	 * Return distinct item 
	 * @param Int | Array
	 * @return Array
	 */
	public function getLocationBI($param,$item)
	{
		$where = ( is_array($param) )? $param: array('b.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
			   ->join(array('d'=>'st_batch_details'), 'd.batch = b.id', array())
			   ->join(array('i'=>'st_items'), 'i.id = b.item', array())
			   ->columns(array('id'))
			   ->where($where)
			   ->order(array('i.supplier','b.item'));
			   if($item!='-1'){
					$select->where(array('b.item'=>$item));
				}
		$select->where->greaterThanOrEqualTo('d.quantity','0');
		       //->where->between('b.batch_date', $start_date, $end_date);
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
/** 
	 * STOCK MOVEMENT 3
	 * Return Item Batches
	 * @param Int | Array
	 * @return Array
	 */
	public function getDtls($param)
	{
		$where = ( is_array($param) )? $param: array('b.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
			   ->join(array('i'=>'st_items'), 'i.id = b.item', array('code','name','activity','supplier','valuation'))
			   ->where($where)
			   ->order(array('i.supplier','i.code'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * STOCK MOVEMENT 4 - fetch distinct items
	 * Return distinct item 
	 * @param Int | Array
	 * @return Array
	 */
	public function fetchSMDistinctItems($param,$item)
	{
		$where = ( is_array($param) )? $param: array('b.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
			   //->join(array('d'=>'st_batch_details'), 'd.batch = b.id', array('location','quantity'))
			   ->join(array('i'=>'st_items'), 'i.id = b.item', array('code','name','activity','supplier','valuation'))
			   ->columns(array(new Expression('DISTINCT(b.item) as item_id')))
			   ->where($where)
			   ->order(array('i.supplier','b.item'));
			   if($item!='-1'){
					$select->where(array('b.item'=>$item));
				}
			   //->where->greaterThan('d.quantity','0')
		       //->where->between('b.batch_date', $start_date, $end_date);
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * STOCK MOVEMENT 4 - filter process
	 * Return distinct batch
	 * @param Int | Array
	 * @return Array
	 */
	public function filterSMBatch($location,$param,$col_loc,$item)
	{
		$where = ( is_array($param) )? $param: array('b.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
			   ->join(array('i'=>'st_items'), 'i.id = b.item', array())
			   ->join(array('d'=>'st_batch_details'), 'd.batch = b.id', array());
			   $select->columns(array(
					'id' => new Expression('DISTINCT(b.id)')
			    ));
			   $select->where($where)
			   ->order(array('i.supplier','b.item'));
			if($location != '-1'){
				$select->where(array($col_loc=> $location));
			}
			if($item!='-1'){
				$select->where(array('b.item'=>$item));
			}
		$select->where->greaterThan('d.quantity','0');
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/** 
	 * STOCK MOVEMENT 4 - fetch batch details
	 * Return Item Batches
	 * @param Int | Array
	 * @return Array
	 */
	public function fetchSMBatchDetails($param)
	{
		$where = ( is_array($param) )? $param: array('b.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
			   ->join(array('i'=>'st_items'), 'i.id = b.item', array('code','activity','supplier','valuation'))
			   ->where($where)
			   ->order(array('i.supplier','i.name'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * SELLING PRICE
	 * Return distinct item 
	 * @param Int | Array
	 * @return Array
	 */
	public function getDis($where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b' => $this->table))
				->join(array('i'=>'st_items'), 'i.id = b.item', array('code','name'))
			   ->columns(array(new Expression('DISTINCT(item) as item_id')));
			if($where!=NULL){
				$select->where($where);
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
	public function getByItemDate($item_id,$start_date, $end_date)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->where(array('item'=>$item_id))
			   ->where->between('batch_date', $start_date, $end_date);
		
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
			   ->order(array('m.batch_date DESC'))
			   ->Limit(1);
		$select->where->lessThanOrEqualTo('m.batch_date', $date); 
			   
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
		$select->from(array('b' => $this->table))
		       ->join(array('i'=>'st_items'), 'i.id = b.item', array())
			   ->where(array('i.valuation' => 0, 'i.activity' => $activity))
			   ->order(array('b.item ASC', 'b.batch_date ASC'))
			   ->where->between('b.batch_date', $start_date, $end_date);
			   if($item != '-1'){
				   $select->where(array('b.item' => $item));
			   }
			   
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
    /**
	 * STOCK MOVEMENT 4 - filter process
	 * Return distinct batch
	 * @param Int | Array
	 * @return Array
	 */
	public function filterSMBatch1($start_date,$end_date,$location,$activity,$item)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>'st_batch'))
			   ->join(array('i'=>'st_items'), 'i.id = b.item', array())
			   ->join(array('d'=>'st_batch_details'), 'd.batch = b.id', array())
               ->columns(array('id' => new Expression('DISTINCT(b.id)')));
		$select->where(array('i.activity'=>$activity));
		if($location != '-1'){
		$select->where(array('d.location'=>$location));}
		if($item!='-1'){
		$select->where(array('b.item'=>$item));}
		$select->where->greaterThan('d.quantity','0');
	    $purchase = $this->getDistinctPB($end_date,$location,$activity,$item);
	    $dispatch = $this->getDistinctDB($start_date,$end_date,$location,$activity,$item);
	    $receipt = $this->getDistinctRB($end_date,$location,$activity,$item);
	    $tras_list_date = $this->getDistinctIB($end_date,$location,$activity,$item);
	    $tras_list_status = $this->getDistinctISB($end_date,$location,$activity,$item);
	    $sales = $this->getDistinctSB($start_date,$end_date,$location,$activity,$item);
	    $sam = $this->getDistinctSAB($start_date,$end_date,$location,$activity,$item);
	    $opening = $this->getDistinctOB($start_date,$location,$activity,$item);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString ; exit;
		$list = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$row_list = array_merge($list,$opening,$purchase,$dispatch,$receipt,$tras_list_date,$tras_list_status,$sales,$sam);
		$row_unique = array_unique($row_list, SORT_REGULAR);
		return $row_unique;
	}
	/**
	 * Distinct Batch
	 * Return distinct item 
	 * @param Int | Array
	 * @return Array
	 */
	public function getDistinctPB($end_date,$location,$activity,$item)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select = new Select(array('pd'=>"pur_pr_details"));
		$select->join(array('p'=>'pur_purchase_receipt'),'p.id = pd.purchase_receipt', array())
		       ->join(array('i'=>'st_items'),'pd.item = i.id', array())
		       ->columns(array('id' => new Expression('DISTINCT(pd.batch)')))
			   ->where->lessThanOrEqualTo('prn_date',$end_date)
			   ->where->notEqualTo('pd.batch',NULL)
			   ->where->EqualTo('p.status','3');
		$select->where(array('i.activity'=>$activity));
		if($location != '-1'){
		$select->where(array('p.location' => $location));
		}
		if($item!='-1'){
		$select->where(array('pd.item'=>$item));
		}   
		$selectString = $sql->getSqlStringForSqlObject($select);
	//	echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Distinct Batch
	 * Return distinct item 
	 * @param Int | Array
	 * @return Array
	 */
	public function getDistinctDB($start_date,$end_date,$location,$activity,$item)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select = new Select(array('dd'=>"st_dispatch_details"));
		$select->join(array('d'=>'st_dispatch'),'d.id = dd.dispatch', array())
		       ->join(array('i'=>'st_items'),'i.id = dd.item', array());
		$select->columns(array('id' => new Expression('DISTINCT(dd.batch)')))
		       ->where->between('dispatch_date',$start_date,$end_date);
		$select->where(array('d.status'=>array('2','3','10')));
		$select->where(array('i.activity'=>$activity));
		if($location != '-1'){
		$select->where(array('d.from_location' => $location));
		}
		if($item!='-1'){
		$select->where(array('dd.item'=>$item));
		}   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Distinct Batch
	 * Return distinct item 
	 * @param Int | Array
	 * @return Array
	 */
	public function getDistinctRB($end_date,$location,$activity,$item)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select = new Select(array('dd'=>"st_dispatch_details"));
		$select ->join(array('d'=>'st_dispatch'),'d.id = dd.dispatch', array())
		        ->join(array('i'=>'st_items'),'i.id = dd.item', array());
		$select->columns(array('id' => new Expression('DISTINCT(dd.batch)')))
				->where->lessThanOrEqualTo('received_on',$end_date)
				->where->notEqualTo('dd.batch',NULL)
				->where->EqualTo('d.status','3');
		$select->where(array('i.activity'=>$activity));
		if($location != '-1'){
		$select->where(array('d.to_location' => $location));
		}
		if($item!='-1'){
		$select->where(array('dd.item'=>$item));
		}   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Distinct Batch
	 * Return distinct item 
	 * @param Int | Array
	 * @return Array
	 */
	public function getDistinctIB($end_date,$location,$activity,$item)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select = new Select(array('dd'=>"st_dispatch_details"));
		$select ->join(array('d'=>'st_dispatch'),'d.id = dd.dispatch', array())
		        ->join(array('i'=>'st_items'),'i.id = dd.item', array());
		$select->columns(array('id' => new Expression('DISTINCT(dd.batch)')))
		               ->where->lessThanOrEqualTo('dispatch_date',$end_date)
				       ->where->greaterThan('received_on',$end_date)
				       ->where->notEqualTo('dd.batch',NULL);
	    $select->where(array('d.status'=>array('2','3')));
		$select->where(array('i.activity'=>$activity));
		if($location != '-1'){
		$select->where(array('d.to_location' => $location));
		}
		if($item!='-1'){
		$select->where(array('dd.item'=>$item));
		}   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Distinct Batch
	 * Return distinct item 
	 * @param Int | Array
	 * @return Array
	 */
	public function getDistinctISB($end_date,$location,$activity,$item)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select = new Select(array('dd'=>"st_dispatch_details"));
		$select ->join(array('d'=>'st_dispatch'),'d.id = dd.dispatch', array())
		        ->join(array('i'=>'st_items'),'i.id = dd.item', array());
		$select->columns(array('id' => new Expression('DISTINCT(dd.batch)')))
		               ->where->lessThanOrEqualTo('dispatch_date',$end_date)
				       ->where->notEqualTo('dd.batch',NULL);
	    $select->where(array('d.status'=>array('2','10')));
		$select->where(array('i.activity'=>$activity));
		if($location != '-1'){
		$select->where(array('d.to_location' => $location));
		}
		if($item!='-1'){
		$select->where(array('dd.item'=>$item));
		}   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Distinct Batch
	 * Return distinct item 
	 * @param Int | Array
	 * @return Array
	 */
	public function getDistinctSB($start_date,$end_date,$location,$activity,$item)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select = new Select(array('sd'=>"sl_sales_dtls"));
		$select ->join(array('s'=>'sl_sales'),'sd.sales = s.sales_no', array())
		        -> join(array('i'=>'st_items'),'i.id = sd.item', array());
		$select->columns(array('id' => new Expression('DISTINCT(sd.batch)')))
		               ->where->lessThan('s.sales_date',$end_date);
		$select->where(array('i.activity'=>$activity));
		if($location != '-1'){
		$select->where(array('s.location' => $location));
		}
		if($item!='-1'){
		$select->where(array('sd.item'=>$item));
		}   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Distinct Batch
	 * Return distinct item 
	 * @param Int | Array
	 * @return Array
	 */
	public function getDistinctSAB($start_date,$end_date,$location,$activity,$item)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select = new Select(array('sd'=>"st_sam_details"));
		$select ->join(array('s'=>'st_sam'),'sd.sam = s.id', array())
		     ->join(array('i'=>'st_items'),'sd.item = i.id', array());
		$select->columns(array('id' => new Expression('DISTINCT(sd.batch)')));	
		$select->where->between('s.sam_date',$start_date,$end_date);		
		$select->where(array('i.activity'=>$activity));
		if($location != '-1'){
		$select->where(array('s.location' => $location));
		}
		if($item!='-1'){
		$select->where(array('sd.item'=>$item));
		}   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Distinct Batch
	 * Return distinct item 
	 * @param Int | Array
	 * @return Array
	 */
	public function getDistinctOB($start_date,$location,$activity,$item)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select = new Select(array('od'=>"st_opening_stock_dtls"));
		$select ->join(array('o'=>'st_opening_stock'),'o.id = od.opening_stock', array())
	            ->join(array('b'=>'st_batch'),'o.batch = b.batch', array())
		        ->join(array('i'=>'st_items'),'b.item = i.id', array());
		$select->columns(array('id' => new Expression('DISTINCT(b.id)')));	
		$select->where->lessThanOrEqualTo('o.opening_date',$start_date);	
		$select->where(array('i.activity'=>$activity));
		if($location != '-1'){
		$select->where(array('od.location' => $location));
		}
		if($item!='-1'){
		$select->where(array('o.item'=>$item));
		}   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
}

<?php
namespace Store\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class StoreSpareTable extends AbstractTableGateway 
{
	protected $table = 'in_storespare'; //tablename

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
	public function get($param)
	{
		$where = ( is_array($param) )? $param: array('sp.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('sp'=>$this->table))
			   ->join(array('i'=>'in_items'), 'i.id = sp.item', array('item_code'=>'code', 'item_id' => 'id', 'valuation'))
			   ->join(array('u'=>'st_uom'), 'u.id = sp.uom', array('uom_code'=>'code', 'uom_id' => 'id'))
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
	 * STORE REPORT - fetch distinct items for Asset
	 * Return distinct item 
	 * @param Int | Array
	 * @return Array
	 */
	public function fetchDistinctItems($param,$item)
	{
		$where = ( is_array($param) )? $param: array('a.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ssp'=>$this->table))
			   ->join(array('i'=>'in_items'), 'i.id = ssp.item', array('code','name','item_group'))
			   ->columns(array(new Expression('DISTINCT(ssp.item) as item_id')))
			   ->where($where)
			   ->order(array('ssp.item'));
			   if($item!='-1'){
					$select->where(array('ssp.item'=>$item));
				}			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
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
	public function getMonthlySSPCode($prefix_code)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->where->like('storespare',$prefix_code."%");
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
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
		$select->from(array('ssp'=>$this->table))
			->join(array('sspd'=>'in_storespare_details'), 'sspd.storespare = ssp.id', array())
    		->join(array('i'=>'in_items'), 'i.id = ssp.item', array('name','code'))
    		->columns(array(new Expression('DISTINCT(ssp.item) as item_id')));
		$select->where($where)
		       ->order(array('i.name'))
				->where->greaterThan('sspd.quantity','0.00');
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo "<pre>"; print_r($selectString); exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Issue
	 * Return All batches by item
	 * @param Array
	 * @return Array
	 */
	public function getIssuesSsp($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ssp'=>$this->table))
				->join(array('sspd'=>'in_storespare_details'), 'ssp.id = sspd.storespare', array('quantity','location'))
				->where($where)
				->where->greaterThan('sspd.quantity','0.00');
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo "<pre>"; print_r($selectString); exit;
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
	public function getMaxstorespare($column,$where=NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ssp'=>$this->table))
		        ->join(array('sspd'=>'in_storespare_details'),'sspd.storespare=ssp.id')
 				->where->greaterThan('sspd.quantity','0.00');
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
	public function getSaleItems($param)
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
	 * Return All distinct column records of table
	 * @return Array
	 */
	public function getIssueItems($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ssp'=>$this->table))
			->join(array('sspd'=>'in_storespare_details'), 'sspd.storespare = ssp.id', array())
    		->join(array('i'=>'in_items'), 'i.id = ssp.item', array('name'))
    		->columns(array(new Expression('DISTINCT(ssp.item) as item_id')));
		$select->where($where)
		       ->order(array('i.name'))
			   ->where->greaterThan('sspd.quantity','0.00');
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit; 
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
	 * Function to create Code
	 * @param Array | Int
	 * @return Array
	 */
	public function getExistingSSPCode($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->where($where);
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
	 * Data preparation
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
				->join(array('i'=>'st_items'), 'i.id = b.item', array('name'))
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
	 * STOCK MOVEMENT 4 - filter process
	 * Return distinct batch
	 * @param Int | Array
	 * @return Array
	 */
	public function filterSRSsp($location,$param,$col_loc,$item_sub_group,$item)
	{
		$where = ( is_array($param) )? $param: array('ssp.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ssp'=>$this->table))
			   ->join(array('i'=>'in_items'), 'i.id = ssp.item', array())
			   ->join(array('sspd'=>'in_storespare_details'), 'sspd.storespare = ssp.id', array());
			   $select->columns(array(
					'id' => new Expression('DISTINCT(ssp.id)')
			    ));
			   $select->where($where)
			   ->order(array('ssp.item'));
			if($location != '-1'){
				$select->where(array($col_loc=> $location));
			}
			if($item!='-1'){
				$select->where(array('ssp.item'=>$item));
			}
                        if($item_sub_group!='-1'){
					$select->where(array('i.item_sub_group'=>$item_sub_group));
			    }
		//$select->where->greaterThan('sspd.quantity','0');
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
    /** 
	 * STOCK MOVEMENT 4 - fetch stores and spares details
	 * Return Item Batches
	 * @param Int | Array
	 * @return Array
	 */
	public function fetchSRSspDetails($param)
	{
		$where = ( is_array($param) )? $param: array('ssp.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ssp'=>$this->table))
			   ->join(array('i'=>'in_items'), 'i.id = ssp.item', array('code','name','item_group','valuation','item_sub_group'))
			   ->where($where)
			   ->order(array('i.code'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Store and Spare
	 * Return All batches by item
	 * @param Array
	 * @return Array
	 */
	public function getStoreSpare($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ssp'=>$this->table))
				->join(array('sspd'=>'in_storespare_details'), 'ssp.id = sspd.storespare', array('quantity','location'))
				->join(array('u'=>'st_uom'), 'u.id = ssp.uom', array('uom'=>'code', 'st_uom_id' => 'id'))
				->where($where)
				->where->greaterThan('sspd.quantity','0.00');				
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
}


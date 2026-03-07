<?php
namespace Stock\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class OpeningTable extends AbstractTableGateway 
{
	protected $table = 'st_opening'; //tablename

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
		$where = ( is_array($param) )? $param: array('b.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
			   ->join(array('i'=>'st_items'), 'i.id = b.item', array('item_code'=>'code', 'item_id' => 'id', 'valuation'))
			   ->join(array('u'=>'st_uom'), 'u.id = b.uom', array('uom_code'=>'code', 'uom_id' => 'id'))
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
		print_r($results);
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
			   ->join(array('i'=>'st_items'), 'i.id = b.item', array('code','activity','valuation'))
			   ->columns(array(new Expression('DISTINCT(b.item) as id')))
			   ->where(array('activity'=>$activity,'valuation'=>'0'))
			   ->order(array('id'));
			   
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
	 
	public function getSMBatchItems($param)
	{
		$where = ( is_array($param) )? $param: array('b.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
			   ->join(array('d'=>'st_batch_details'), 'd.batch = b.id', array('location','quantity','selling_price'))
			   //->join(array('i'=>'st_items'), 'i.id = b.item', array('code','activity','supplier','valuation'))
			   ->where($where)
			   ->order(array('d.batch'));
			   //->where->greaterThan('d.quantity','0')
		       //->where->between('b.batch_date', $start_date, $end_date);
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	*/
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
					'sp_effect_date','costing','status','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ))->having(array('year' => $year, 'month' => $month))
	    	   ->order(array('id DESC'));
		       
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
}

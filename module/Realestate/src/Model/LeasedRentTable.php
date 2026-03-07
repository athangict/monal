<?php
namespace Realestate\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class LeasedRentTable extends AbstractTableGateway 
{
	protected $table = 'rs_leased_rent'; //tablename

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
	/**customed getAll()
	 * Return All records of table
	 * @return Array
	 */
	public function getAllwithName()
	{  
	    $adapter = $this->adapter;
	    $sql = new Sql($adapter);
	    $select = $sql->select();
	     $select->from(array('lr' => $this->table));
		// 		->join(array('l'=>'rs_estate_location'),'l.id=lr.location',array('location_name'=>'location'))
		// 		->join(array('s'=>'rs_storage_type'),'s.id=lr.leased_godown',array('storage'))
		// 		->order('id DESC');
	    
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
	 * Return Distinct value of the column
	 * @param Array $where
	 * @param String $column
	 * @return Array | Int
	 */
	public function getDistinct($column,$where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'asset_id' => new Expression('DISTINCT('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		
		 $selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		$column = array();
		foreach ($results as $result):
			array_push($column,$result['asset_id']);
		endforeach;
	
		return $column;
	}
/**
	 * Return Distinct value of the column
	 * @param Array $where
	 * @param String $column
	 * @return Array | Int
	 */
	public function getTenant($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('lr'=>$this->table))
		->join(array('b'=>'rs_building'), 'lr.building = b.id');
		if($where!=NULL){
			$select->where($where);
		}
		
		 $selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	/**
	 * Return records of given condition array
	 * @param Array $data
	 * @return Array
	 */
	public function getReportflat($data,$start_date,$end_date)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
	    $select = $sql->select();
		$select->from($this->table)
		->where->between('start_date','end_date',$start_date,$end_date);
		if($data['assetid'] != '-1'){
			$select->where(array('assetid'=>$data['assetid']));
		}
		if($data['region'] != '-1'){
			$select->where(array('region'=>$data['region']));
		}
		if($data['location'] != '-1'){
			$select->where(array('location'=>$data['location']));
		}
		if($data['block'] != '-1'){
			$select->where(array('block'=>$data['block']));
		}
		
	    $selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}

	/**
	 * Return all payrollrecords in month if its payroll is prepared
	 * @return Array
	 */
	public function getRent($year)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table, array('month', 'year'))
			   ->group(array('month','year'));
		$select->order(array('month DESC', 'year DESC'));
		if($year != '-1'):
			$select->where(array('year' => $year));
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return all payrollrecords in month if its payroll is prepared
	 * @return Array
	 */
		public function getrentlist($param)
	{
		$where = ( is_array($param) )? $param: array('lr.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('lr'=>$this->table))
		->join(array('b'=>'rs_building'), 'lr.building = b.id')
		->join(array('bm'=>'rs_building_master'), 'b.asset = bm.id')
		       ->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
}

<?php
namespace Fleet\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

use Laminas\Form\Element;
use Laminas\Form\Form;

class TransportVehicleLogTable extends AbstractTableGateway 
{
	protected $table = 'tp_vehicle_log'; //tablename

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
	    $select->from(array('rqd' => $this->table))
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
		$where = ( is_array($param) )? $param: array('vl.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('vl' => $this->table))
			       // ->join(array('so'=>'tp_sanction_order'),'so.id = vl.sanction_order', array('sanction_order_id' => 'id'))
			        //->join(array('l'=>'adm_location'),'l.id = vl.location', array('location' =>'location','location_id' => 'id'))
			       // ->join(array('a'=>'adm_activity'),'a.id = vl.cost_center', array('cost_center' =>'activity','cost_center_id' => 'id'))
			    ->where($where)
	    	   ->order(array('id ASC'));
		
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
	public function getMaxVLID($column,$where=NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('vl'=>$this->table));
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
	 * get sum by SOID
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSumbySOID($start_date,$end_date, $column, $where)
	{		
		//extract($options);
		
		$sub0 = new Select("tp_sanction_order");
		$sub0->columns(array("id"))
			 ->where(array("status" => "3")) //committed status
			 ->where->between('sanction_order_date', $start_date, $end_date);
			 
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where($where);
		$select->where->in('sanction_order', $sub0);
    
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   
	   return $sum;	    
	}
        /**
	 * 
	 * get repair id present in repair details
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getTransportLogs($start_date,$end_date,$where)
	{		
		//extract($start_date,$end_date);	
		
		$sub1 = new Select("tp_sanction_order");
		$sub1->columns(array("id"))
	        ->where(array("status" => "3"))		
		    ->where->between('sanction_order_date',$start_date,$end_date);
		
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
		       ->where($where)
		       ->where->in('sanction_order', $sub1);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray(); 
	    return $results;	  
	}
	/**
	 * Vehicle Report
	 * Return distinct transports 
	 * @param Int | Array
	 * @return Array
	 */
	public function getDistinctTransport($location,$license_plate,$start_date,$end_date)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('vl'=>$this->table))
				->join(array('so'=>'tp_sanction_order'),'vl.sanction_order = so.id')
			   ->join(array('a'=>'tp_transport'),'a.id = so.license_plate',array('license_plate'=>'license_plate','license_plate' =>'id'))
			  // ->join(array('l'=>'adm_location'),'l.id = rm.location',array('location'=>'location','location_id' =>'id'))
			  // ->join(array('hr'=>'hr_employee'),'hr.id = rm.driver', array('driver'=>'full_name','driver_id' => 'id'))
			  // ->columns(array(new Expression('DISTINCT(rm.transport) as transport')))
			   //->order(array('rmd.license_plate'))
			  // ->where(array("rm.status" => "4"))	
			   ->where->between('so.sanction_order_date',$start_date,$end_date);
			  if($location != '-1'){
				$select->where(array('so.location'=>$location));
			}  
			if($license_plate != '-1'){
				$select->where(array('so.license_plate'=>$license_plate));
			}  
		$selectString = $sql->getSqlStringForSqlObject($select);
	  //  echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
}

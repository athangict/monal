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

class VehicleRequisitionTable extends AbstractTableGateway 
{
	protected $table = 'tp_requisition';   //tablename

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
		$where = ( is_array($param) )? $param: array('rq.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('rq'=>$this->table))
			    ->join(array('rqd'=>'tp_requisition_dtls'),'rq.id = rqd.requisition', array('requisition_id' => 'id'))
			   	->join(array('a'=>'fa_assets'),'a.id = rq.transport', array('transport'=>'code','transport_id' => 'id'))
               	->join(array('l'=>'adm_location'),'l.id = rq.location', array('location'=>'location','location_id' => 'id'))
			   	->join(array('hr'=>'hr_employee'),'hr.id = rq.driver', array('driver'=>'full_name','driver_id' => 'id'))
		        ->where($where)
				->order(array('id DESC'));		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return records of given year and month
	 * @param Int $id
	 * @return Array
	 */
	public function getLocDateWise($column,$year,$month,$param)
	{		
	    $where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
		        ->columns(array(
					'id', 'requisition_no', 'issue_date', 'transport','location','driver','total_quantity','previous_reading','current_reading','km_covered','remarks','status','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ))->having(array('year' => $year, 'month' => $month))
			   ->order(array('id ASC'));
			   $select->where($where);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo "<pre>"; print_r($selectString); exit;
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
	 * Return records of given condition array
	 * @param Int $column
	 * @param Int $param
	 * @return Array
	 */
	public function getMonthlyRQ($prefix_PO_code)
	{  
		$adapter = $this->adapter;			
		$sql = new Sql($adapter);		
		$select = $sql->select();
		$select->from($this->table);		
		$select->where->like('requisition_no', $prefix_PO_code."%");	
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return  $results;
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
	 * Vehicle Report
	 * Return distinct transports 
	 * @param Int | Array
	 * @return Array
	 */
	public function getDistinctTransport($transport,$start_date,$end_date)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('vr'=>$this->table))
			   ->join(array('a'=>'fa_assets'),'a.id = vr.transport',array('transport'=>'code','transport_id' =>'id'))
			   ->join(array('l'=>'adm_location'),'l.id = vr.location',array('location'=>'location','location_id' =>'id'))
			   ->join(array('hr'=>'hr_employee'),'hr.id = vr.driver', array('driver'=>'full_name','driver_id' => 'id'))
			   ->columns(array(new Expression('DISTINCT(vr.transport) as transport')))
			   ->order(array('vr.transport'))
			   ->where(array("vr.status" => "3"))	
			   ->where->between('issue_date',$start_date,$end_date);
			if($transport != '-1'){
				$select->where(array('vr.transport'=>$transport));
			}  
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}	
	
	/**
	 * 
	 * get repair id present in repair details
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getRequisition($start_date,$end_date,$where)
	{		
		//extract($start_date,$end_date);	
		
		$sub1 = new Select("tp_requisition_dtls");
		$sub1->columns(array("requisition"));
		$sub1->where($where);
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
               ->where(array("status" => "3"))		
               ->where->between('issue_date',$start_date,$end_date);
		$select->where->in('id', $sub1);
		$select->order(array('issue_date ASC'));
		$select->order(array('id ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray(); 
	    return $results;	  
	}
}

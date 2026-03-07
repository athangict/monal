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

class TransportSanctionOrderTable extends AbstractTableGateway 
{
	protected $table = 'tp_sanction_order';   //tablename

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
	    	   ->order(array('id ASC'));
	    
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
		$where = ( is_array($param) )? $param: array('so.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('so'=>$this->table))
			    //->join(array('vl'=>'tp_vehicle_log'),'so.id = vl.sanction_order', array('sanction_order_id' => 'id'))
			   	//->join(array('a'=>'fa_assets'),'a.id = so.transport', array('transport'=>'code','transport_id' => 'id'))
               	->join(array('l'=>'adm_location'),'l.id = so.location', array('location'=>'location','location_id' => 'id'))
			   	->join(array('hr'=>'hr_employee'),'hr.id = so.driver', array('driver'=>'full_name','driver_id' => 'id'))
		        ->where($where)
				->order(array('id DESC'));		
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
	public function getLocDateWise($column,$year,$month,$param)
	{		
	    $where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
		        ->columns(array(
					'id', 'sanction_order_no', 'sanction_order_date','subhead','head','location','driver','start_date','end_date','opening_tank','closing_tank','last_reading','present_reading','total_fuel','total_fuel_consumed','total_km','payment_amt','status','author','created','modified',
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
	public function getMonthlySO($prefix_PO_code)
	{  
		$adapter = $this->adapter;			
		$sql = new Sql($adapter);		
		$select = $sql->select();
		$select->from($this->table);		
		$select->where->like('sanction_order_no', $prefix_PO_code."%");	
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return  $results;
	}	
	/**
	 * Return max value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMaxSID($column,$where=NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('so'=>$this->table));
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
	 * 
	 * get sanction order id present in vehicle logs details
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getSanctionOrderforPol($start_date,$end_date,$where)
	{		
		//extract($start_date,$end_date);	
		
		$sub1 = new Select("tp_vehicle_log");
		$sub1->columns(array("sanction_order"));
		$sub1->where($where);
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
               ->where(array("status" => "3"))		
               ->where->between('sanction_order_date',$start_date,$end_date);
		$select->where->in('id', $sub1);
		$select->order(array('sanction_order_date ASC'));
		$select->order(array('id ASC'));
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
	public function getDistinctTransport($transport,$start_date,$end_date)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('so'=>$this->table))
			   ->join(array('a'=>'fa_assets'),'a.id = so.transport',array('transport'=>'code','transport_id' =>'id'))
			   ->join(array('l'=>'adm_location'),'l.id = so.location',array('location'=>'location','location_id' =>'id'))
			   ->join(array('hr'=>'hr_employee'),'hr.id = so.driver', array('driver'=>'full_name','driver_id' => 'id'))
			   ->columns(array(new Expression('DISTINCT(so.transport) as transport')))
			   ->order(array('so.transport'))
			   ->where(array("so.status" => "3"))	
			   ->where->between('sanction_order_date',$start_date,$end_date);
			if($transport != '-1'){
				$select->where(array('so.transport'=>$transport));
			}  
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
/**
	 * get sum by RID
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getGroupSum($column,$location, $license, $start_date, $end_date,$where)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();

		$select->from(array('so' => $this->table))
				->join(array('tp' => 'tp_vehicle_register'), 'tp.subhead = so.subhead')
			   ->join(array('h' => 'fa_head'), 'h.id = so.head')
			   ->join(array('g' => 'fa_group'), 'g.id = h.group',array('group' => 'name', 'group_id' => 'id'))

			   ->columns(array(
				'sum' => new Expression('SUM('.$column.')'),
			))
			   ->where($where)
			   ->where->between('so.sanction_order_date', $start_date, $end_date);

		if ($location != '-1') {
			$select->where(array('so.location' => $location));
		}

		if ($license != '-1') {
			$select->where(array('tp.license_plate' => $license));
		}

		$selectString = $sql->getSqlStringForSqlObject($select);
		// echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		foreach($results as $result);
		$sum=(!empty($result['sum']))?$result['sum']:0; 
		return $sum;
	}
/**
	 * get sum by RID
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getDistinctHead($column,$location, $license, $start_date, $end_date,$where)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();

		$select->from(array('so' => $this->table))
		->join(array('tp' => 'tp_vehicle_register'), 'tp.subhead = so.subhead')
			   ->join(array('h' => 'fa_head'), 'h.id = so.head',array('head' => 'name', 'head_id' => 'id'))
			   ->columns(array(
				'sum' => new Expression('SUM('.$column.')'),
			))
			   ->group('h.id')
			   ->where($where)
			  ->where->between('so.sanction_order_date', $start_date, $end_date);

		if ($location != '-1') {
			$select->where(array('so.location' => $location));
		}

		if ($license != '-1') {
			$select->where(array('so.license_plate' => $license));
		}

		$selectString = $sql->getSqlStringForSqlObject($select);
		// echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * get sum by RID
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getDistinctSubHead($column,$location, $license, $start_date, $end_date,$where)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();

		$select->from(array('so' => $this->table))
				->join(array('tp' => 'tp_vehicle_register'), 'tp.subhead = so.subhead')
			   ->join(array('sh' => 'fa_sub_head'), 'sh.id = so.subhead',array('subhead' => 'name', 'subhead_id' => 'id'))
			   ->columns(array(
				'sum' => new Expression('SUM('.$column.')'),
			))
			   ->group('sh.id')
			   ->where($where)
			   ->where->between('so.sanction_order_date', $start_date, $end_date);

		if ($location != '-1') {
			$select->where(array('so.location' => $location));
		}

		if ($license != '-1') {
			$select->where(array('tp.license_plate' => $license));
		}

		$selectString = $sql->getSqlStringForSqlObject($select);
		// echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}	
}

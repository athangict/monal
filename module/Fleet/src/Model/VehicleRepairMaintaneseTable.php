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

class VehicleRepairMaintaneseTable extends AbstractTableGateway 
{
	protected $table = 'tp_repair';   //tablename

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
		$where = ( is_array($param) )? $param: array('rm.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('rm'=>$this->table))
			    ->join(array('rmd'=>'tp_repair_dtls'),'rm.id = rmd.repair', array('repair_id' => 'id'))
	    	   // ->join(array('p'=>'fa_party'),'p.id = rm.supplier', array('supplier'=>'code','supplier_id' => 'id'))
			   	->join(array('l'=>'adm_location'),'l.id = rm.location', array('location'=>'location','location_id' => 'id'))
			   //	->join(array('sa'=>'adm_activity'),'sa.id = rm.cost_center', array('cost_center'=>'activity','activity_id' => 'id'))
			   	//->join(array('a'=>'fa_assets'),'a.id = rm.transport', array('transport'=>'code','transport_id' => 'id'))
			   //	->join(array('hr'=>'hr_employee'),'hr.id = rm.driver', array('driver'=>'full_name','driver_id' => 'id'))
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
					'id', 'repair_no', 'work_order_date', 'transport','driver','cost_center','supplier','work_order_ref','total_amount','remarks','status','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ))->having(array('year' => $year, 'month' => $month))
			   ->order(array('id DESC'));
			   $select->where($where);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo "<pre>"; print_r($selectString); exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Update the transaction ID
	 * Save record
	 * @repair_id String $array
	 * @tran_id Int
	 * @return Int
	 */
	public function updateRecords($repair_id, $tran_id)
	{
	    $adapter = $this->adapter;
		$sql = new Sql($adapter);
		$update = $sql->update();
		$update->table($this->table)
			   ->set(array('transaction' => $tran_id))
		       ->where->in('id',$repair_id);
		$statement = $sql->prepareStatementForSqlObject($update);
		$result = $statement->execute(); 
		
	    return $result;	
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
	public function getMonthlyRM($prefix_PO_code)
	{  
		$adapter = $this->adapter;			
		$sql = new Sql($adapter);		
		$select = $sql->select();
		$select->from($this->table);		
		$select->where->like('repair_no', $prefix_PO_code."%");	
			
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
	 * 
	 * get repair id present in repair details
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getRepair($start_date,$end_date,$where)
	{		
		//extract($start_date,$end_date);	
		
		$sub1 = new Select("tp_repair_dtls");
		$sub1->columns(array("repair"));
		$sub1->where($where);
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
               ->where(array("status" => "3"))		
               ->where->between('work_order_date',$start_date,$end_date);
		$select->where->in('id', $sub1);
		$select->order(array('work_order_date ASC'));
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
		$select->from(array('rm'=>$this->table))
			   ->join(array('a'=>'fa_assets'),'a.id = rm.transport',array('transport'=>'code','transport_id' =>'id'))
			   ->join(array('l'=>'adm_location'),'l.id = rm.location',array('location'=>'location','location_id' =>'id'))
			   ->join(array('hr'=>'hr_employee'),'hr.id = rm.driver', array('driver'=>'full_name','driver_id' => 'id'))
			   ->columns(array(new Expression('DISTINCT(rm.transport) as transport')))
			   ->order(array('rm.transport'))
			   ->where(array("rm.status" => "3"))	
			   ->where->between('work_order_date',$start_date,$end_date);
			if($transport != '-1'){
				$select->where(array('rm.transport'=>$transport));
			}  
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}	

}

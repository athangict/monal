<?php
namespace Hr\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class LeaveEncashTable extends AbstractTableGateway 
{
	protected $table = 'hr_leave_encash'; //tablename

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
	    $select->from(array('ld'=>$this->table))
				->join(array('e'=>'hr_employee'), 'e.id=ld.employee', array('employee_id'=>'id','emp_id', 'full_name'));
		$select->order('ld.encash_date DESC');
	   
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
		$column =  $result[$column];
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
	 * Return records of given condition array
	 * @param Int $column
	 * @param Int $param
	 * @return Array
	 */
	public function getMaxRow($column,$param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MAX('.$column.')')
		));
		//$sub0 = $sub0->toArray();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array($this->table))
				->where($where)
				->where($column);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return records of given condition array
	 * @param Int $column
	 * @param Int $param
	 * @return Array
	 */
	public function getMinRow($column,$param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
	
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MIN('.$column.')')
		));
		//$sub0 = $sub0->toArray();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array($this->table))
			   ->where($where)
			   ->where($column);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
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
		$select->from(array('ld'=>$this->table))
				->join(array('e'=>'hr_employee'), 'e.id=ld.employee', array('employee_id'=>'id','emp_id', 'full_name'))
                 ->columns(array(
					'id','employee','encash_date','no_of_encashed_days','leave_balance','leave_balance_date','payment_amount',
					'encash_sub_head','deduction','deduction_sub_head','remarks','leave_official',
					'remark_log','status','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ))->having(array('year' => $year, 'month' => $month))
	    	   ->order(array('id DESC'));
		       
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	public function getEncash($column,$year,$param)
	{	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
                 ->columns(array(
					'id','employee','encash_date','no_of_encashed_days','leave_balance','leave_balance_date','payment_amount',
					'encash_sub_head','deduction','deduction_sub_head','remarks','leave_official',
					'remark_log','status','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
			   ))->having(array('year' => $year))
			   ->where($param);
		       
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	public function getEncashLeave($data,$year,$month,$column,$where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ld' => $this->table))
           ->join(array('em' => 'hr_employee'), 'ld.employee=em.id', array('id', 'location', 'activity', 'department','full_name','designation'))
            ->columns(array(
					'id','employee','encash_date','no_of_encashed_days','leave_balance','leave_balance_date','payment_amount',
					'encash_sub_head','deduction','deduction_sub_head','remarks','leave_official',
					'remark_log','status','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ))->having(array('year' => $year, 'month' => $month))
	    	   ->order(array('id DESC'));
		$select->columns(array(
			'leave_type' => new Expression('DISTINCT('.$column.')'),
			'no_of_days' => new Expression('SUM(ld.no_of_days)'),
		))->group(array('ld.employee'));
		if($data['location'] != '-1'){
			$select->where(array('em.location'=>$data['location']));
		}
		if($data['division'] != '-1'){
			$select->where(array('em.activity'=>$data['division']));
		}
		if($data['department'] != '-1'){
			$select->where(array('em.department'=>$data['department']));
		}
		if($data['leave'] != '-1'){
			$select->where(array('ld.leave_type'=>$data['leave']));
		}
		if($where!=NULL){
			$select->where($where);
		}
	$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}//Get Service  
	
	

	public function getEncashReport($data, $start_date, $end_date,$where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ld' => $this->table))
			->join(array('em' => 'hr_employee'), 'ld.employee = em.id', array('location', 'department', 'full_name', 'designation'))
			->join(array('l' => 'adm_location'), 'l.id=em.location', array('location'))
			->join(array('r' => 'adm_region'), 'l.region=r.id', array('region'))
			->where->nest()
			->greaterThanOrEqualTo('ld.encash_date', $start_date)
			->and
			->lessThanOrEqualTo('ld.encash_date', $end_date)
			->unnest();
		if ($data['location'] != '-1') {
			$select->where(array('em.location' => $data['location']));
		}
		if ($data['region'] != '-1') {
			$select->where(array('l.region' => $data['region']));
		}
		if ($data['department'] != '-1') {
			$select->where(array('em.department' => $data['department']));
		}
		if ($where != NULL) {
			$select->where($where);
		}


		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getLETDS($param)
	{
		//$where = ( is_array($param) )? $param: array('e.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('le'=>$this->table))
			   ->join(array('hr'=>'hr_employee'), 'hr.id = le.employee', array('emp_id'=> 'id'))
	           ->order(array('hr.id ASC'));
				$select->where(array('hr.status'=>1));
		$selectString = $sql->getSqlStringForSqlObject($select);
		echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getEMPTDS($param)
	{
		//$where = ( is_array($param) )? $param: array('e.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('le'=>$this->table))
			   ->join(array('hr'=>'hr_employee'), 'hr.id = le.employee', array('emp_id'=> 'id'))
			   //->join(array('std'=>'hr_sitting_dtls'), 'hr.id = std.employee', array('std_amount'=> 'payment_amount', 'std_deduction'=> 'deduction'))
			   //->join(array('st'=>'hr_sitting'), 'st.id = std.sitting_id', array('sitting_date'=> 'date'))
	           ->where(array("le.status" => "4"))
			   ->where->between('encash_date', $param['start_date'],$param['end_date']);
				//->columns(array(
				  // 'employee' => new Expression('DISTINCT hr.id') // Select distinct based on the hr.id (employee)
			   // ))
			   //->order(array('hr.id ASC'));
				//$select->where(array('le.status'=>4));
		//$select->where->between('le.encash_date', $param['start_date'], $param['end_date']);
        //$select->where->OR->between('st.date', $param['start_date'], $param['end_date']);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}

}

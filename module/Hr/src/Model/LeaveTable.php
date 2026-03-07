<?php
namespace Hr\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

use Laminas\Db\Sql\Expression;

use Laminas\Db\TableGateway\TableGateway;

class LeaveTable extends AbstractTableGateway 
{
	protected $table = 'hr_leave'; //tablename

	public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }	

	/**
	 * Return All records of table
	 * @return Array
	 */
	public function getAll($emp_id=0, $leave_official=NULL)
	{  
	    $adapter = $this->adapter;
	    $sql = new Sql($adapter);
	    $select = $sql->select();
	    $select->from(array('ld'=>$this->table))
				->join(array('lt'=>'hr_leave_type'), 'lt.id=ld.leave_type', array('leave_id'=>'id','type'))
				->join(array('e'=>'hr_employee'), 'e.id=ld.employee', array('employee_id'=>'id','emp_id', 'full_name'));
		$where = new Where();
	    if($emp_id>0):
			$where->equalTo('ld.employee',$emp_id);
		endif;
		if(!empty($leave_official)):
			$where->OR->equalTo('ld.leave_official', $leave_official);
		endif;		
		$select->where($where)
				->order(array('ld.start_date DESC'));
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
		$where = ( is_array($param) )? $param: array('ld.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ld'=>$this->table))
				->join(array('lt'=>'hr_leave_type'), 'lt.id=ld.leave_type', array('leave_id'=>'id','type'))
				->join(array('e'=>'hr_employee'), 'e.id=ld.employee', array('employee_id'=>'id','emp_id', 'full_name'))
	   
		       ->where($where);
			   $select->order('start_date DESC');
		
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
		$where = ( is_array($param) )? $param: array('ld.id' => $param);
		$adapter = $this->adapter;
		
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MAX('.$column.')')
		));
		//$sub0 = $sub0->toArray();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ld'=>$this->table))
				->join(array('lt'=>'hr_leave_type'), 'lt.id=ld.leave_type', array('leave_id'=>'id','type'))
				->join(array('e'=>'hr_employee'), 'e.id=ld.employee', array('em'=>'id','emp_id', 'full_name'))
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
		$where = ( is_array($param) )? $param: array('ld.id' => $param);
		$adapter = $this->adapter;
	
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MIN('.$column.')')
		));
		//$sub0 = $sub0->toArray();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ld'=>$this->table))
				->join(array('lt'=>'hr_leave_type'), 'lt.id=ld.leave_type', array('leave_id'=>'id','type'))
				->join(array('e'=>'hr_employee'), 'e.id=ld.employee', array('em'=>'id','emp_id', 'full_name'))
		->where($where)
		->where($column);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return leave records for a given year
	 * @param string $column
	 * @param Int|String $minvalue
	 * @param Int|String $maxvalue
	 * @param Array $where
	 * @return Array
	 */
	public function getLeave($column, $minvalue, $maxvalue, $where=NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ld' => $this->table))
				->join(array('lt' => 'hr_leave_type'), 'ld.leave_type=lt.id', array('leave_type_id' => 'id', 'leave_type' => 'type'))
				->where->between($column, $minvalue, $maxvalue);
		
		if($where!=NULL){
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
	public function getDailyLeave($data,$date, $where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);

		// Add the condition to check if the given date falls within the leave period
		$select->where->nest()
			->lessThanOrEqualTo('start_date', $date)
			->and
			->greaterThanOrEqualTo('end_date', $date)
		->unnest();

		if ($where != NULL) {
			$select->where($where);
		}

		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
 * Return records of given condition array | given id
 * @param Array|Int $where (optional)
 * @return Array
 */
public function getDashLeave($where = NULL)
{
    // Get the current date in 'Y-m-d' format
    $currentDate = date('Y-m-d');

    // Get the database adapter
    $adapter = $this->adapter;
    
    // Create a new SQL object
    $sql = new Sql($adapter);
    
    // Create a new Select object
    $select = $sql->select();
    
    // Specify the table to select from
    $select->from($this->table);

    // Add the condition to check if the current date falls within the leave period
    $select->where->nest()
        ->lessThanOrEqualTo('start_date', $currentDate)
        ->and
        ->greaterThanOrEqualTo('end_date', $currentDate)
    ->unnest();

    // If additional conditions are provided, add them to the query
    if ($where != NULL) {
        $select->where($where);
    }

    // Get the SQL string for the constructed query
    $selectString = $sql->getSqlStringForSqlObject($select);
    
    // Execute the query and convert the result to an array
    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
    
    // Return the results
    return $results;
}

	//Get Service  
	public function getYearlyLeave($data,$start_date,$end_date, $where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ld' => $this->table))
            ->join(array('em' => 'hr_employee'), 'ld.employee=em.id', array( 'department','full_name','designation'))
			->join(array('l' => 'adm_location'), 'l.id=em.location', array('location'))
			->join(array('r' => 'adm_region'), 'l.region=r.id', array('region'))
           ->where->nest()
           ->lessThanOrEqualTo('ld.start_date', $end_date)
           ->and
           ->greaterThanOrEqualTo('ld.end_date', $start_date)
           ->unnest();
		if($data['location'] != '-1'){
			$select->where(array('em.location'=>$data['location']));
		}
		if($data['region'] != '-1'){
			$select->where(array('l.region'=>$data['region']));
		}
		
		if($data['leave'] != '-1'){
			$select->where(array('ld.leave_type'=>$data['leave']));
		}
		
		$select->where(array('ld.status'=>8));
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	//Get Service  
	/*public function getYearlyLeave($data,$start_date,$end_date, $column,$where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ld' => $this->table))
           ->join(array('em' => 'hr_employee'), 'ld.employee=em.id', array('id', 'location', 'activity', 'department','full_name','designation'))
           ->where->nest()
           ->lessThanOrEqualTo('ld.start_date', $end_date)
           ->and
           ->greaterThanOrEqualTo('ld.end_date', $start_date)
           ->unnest();
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
	}*/
	public function getBalanceReport($data,$where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->where->between('start_date', $data['start_date'], $data['end_date']);

		if ($where != NULL) {
			$select->where($where);
		}

		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();

		return $results;
	}
	/**
	 * Return Distinct value of the column
	 * @param Array $where
	 * @param String $column
	 * @return Array | Int
	 */
	public function getPendingLeave($column, $where = NULL,$user){
	 $adapter = $this->adapter;
    $sql = new Sql($adapter);

    // Subquery: Get the max a.id per ns.id
    $subSelect = $sql->select();
    $subSelect->from(['a1' => 'sys_activity_logs'])
              ->columns([
                  'process_id','send_to',
                  'max_id' => new Expression('MAX(id)')
              ])
              ->group('process_id');

    // Main query
    $select = $sql->select();
    $select->from(['l' => $this->table])
           ->join(['a' => 'sys_activity_logs'], 'l.id = a.process_id')
           ->join(['latest' => $subSelect], 'a.process_id = latest.process_id AND a.id = latest.max_id', [])
           ->columns(['*'])
		   ->order('l.start_date DESC'); // gets all columns from ns

    if ($where !== null) {
        $select->where($where);
    }
	$select->where->notEqualTo('l.author', $user);

    $selectString = $sql->getSqlStringForSqlObject($select);
    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();

    return $results;}
	/**
	 * Return Distinct value of the column
	 * @param Array $where
	 * @param String $column
	 * @return Array | Int
	 */
	public function getActionByMe($where = NULL,$user)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		
		$select->from(['l' => $this->table])
			->join(['a' => 'sys_activity_logs'], 'l.id = a.process_id',['process_id'])
			->order('l.start_date DESC')
			->where->notEqualTo('l.author', $user);
		
		if ($where !== NULL) {
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();

		return $results;
	}


}

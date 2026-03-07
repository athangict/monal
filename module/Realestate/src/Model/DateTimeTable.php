<?php
namespace Realestate\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class DateTimeTable extends AbstractTableGateway 
{
	protected $table = 'rs_floor'; //tablename

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
	    $select->from(array('st'=>$this->table))
				// ->join(array('l'=>'rs_estate_location'),'l.id=st.location',array('location_name'=>'location'))
				// ->join(array('o'=>'rs_operator'),'o.id=st.operator',array('operator_name'=>'operator'))
				->order('id ASC');
	    
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
	 * function to insert and delete form hr_temp_payroll table
	 * if the employee status changes or new employee is added
	 */
	public function prepareDateTime($data)
	{
		extract($data);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		
		//delete from temp payroll if employee is resigned, retired or others.... re realestate
		$re = new Select('re_rent');
		$re->columns(array('rent'=>'id'))
				 ->where(array('status'=>array(1,4,5)));
				 
		$delete = $sql->delete();
		$delete->from($this->table);
		$delete->where->notin('rent',$re);
		
		$deleteString = $sql->getSqlStringForSqlObject($delete);
		$del_result = $adapter->query($deleteString, $adapter::QUERY_MODE_EXECUTE);
		//end of delete
		
		//insertion of new employee if any tr means rents report
		$tr_rent = new Select($this->table);
		$tr_rent->columns(array('rent'));
		
		$new_rent = new Select('re_rent');
		$new_rent->columns(array('rent'=>'id'))
					->where(array('status'=>array(1,4,5)))
					->where->notin('id', $tr_rent);
							
		$new_reString = $sql->getSqlStringForSqlObject($new_rent);
		
		$new_rents= $adapter->query($new_reString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		foreach($new_rents as $row):
				
			$tent_history->where(array('rent'=>$row['rent']));
			
			$tent_historyString = $sql->getSqlStringForSqlObject($tent_history);		
			$tent_historys= $adapter->query($tent_historyString, $adapter::QUERY_MODE_EXECUTE)->toArray();
			foreach($tent_historys as $ten_row);
			$new_data = array(
				'year'=> $year,
				'month'=> $month,
				'status' => '0',
				'author'=>$author,
				'created'=>$created,
				'modified'=>$modified
			);
			$this->insert($new_data);
		endforeach;
		// end of insertion of new employee
		
		//update temp_payroll with latest employee history id
		foreach($this->getAll() as $rss_rent):
			$tent_history = new Select(array('eh'=>'re_tent_history'));
			$tent_history->columns(array(
					'start_date' => new Expression('MAX(start_date)'),
					'tent_his' => new Expression('MAX(id)')
			));
			$tent_history->where(array('employee'=>$rss_rent['employee']));
			
			$tent_historyString = $sql->getSqlStringForSqlObject($tent_history);		
			$tent_historys= $adapter->query($tent_historyString, $adapter::QUERY_MODE_EXECUTE)->toArray();
			foreach($tent_historys as $ten_row);
			
			$data = array(
				'tent_his'=>$ten_row['tent_his'],
				'month'=>$month, 
				'year'=>$year, 
			);
			$this->update($data, array('rent'=>$rss_rent['rent']));
		endforeach;
	}
	
	/**
	 * Return all payrollrecords in month if its payroll is prepared
	 * @return Array
	 */
	/**
	 * Return Min value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMin($where = NULL, $column)
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
	public function getMax($where=NULL, $column)
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
		$select->from($this->table)
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
		$select->from($this->table)
		->where($where)
		->where($column);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
}

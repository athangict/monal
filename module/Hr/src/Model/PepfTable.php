<?php
namespace Hr\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class PepfTable extends AbstractTableGateway 
{
	protected $table = 'hr_pepf'; //tablename

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
	public function getforReport($param)
	{
		$where = ( is_array($param) )? $param: array('pr.id' => $param);
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
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$columns="";
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
				'column' => new Expression('DISTINCT('.$column.')')
		));
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
	 * Return all rentrecords in month if its rent is prepared
	 * @return Array
	 */
	public function getpepf($year)
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
	 * Return all rentrecords in month if its rent is prepared
	 * @return Array
	 */
	public function getPepfByMonth($year,$month)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('pe'=>$this->table))
		->join(array('hr'=>'hr_employee'), 'hr.id = pe.employee')
		->columns(array(
						'pf_id' => 'id',
						'pe_pf' => 'pe_pf','basic_salary' => 'basic_salary','pf_status'=>'status',
				))
		->order(array('hr.full_name'));
		if($year != '-1'):
			$select->where(array('pe.year' => $year,'pe.month'=>$month));
		endif;

		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
		/**
	 * Rent
	 * Get Sum of a column
	**/
	public function getSum($param,$column)
	{
		$param = (is_array($param))? $param: array($param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'sum' => new Expression('SUM('.$column.')')
		));
		$select->where($param);
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
		return $column;
	}
	/**
	 * Rent
	 * Get No of Entries
	**/
	public function getCount($param)
	{
		$param = (is_array($param))? $param: array($param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'count' => new Expression('COUNT(id)')
		));
		$select->where($param);
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		foreach ($results as $result):
			$column =  $result['count'];
		endforeach;
		return $column;
	}
	

}

<?php
namespace Sales\Model;


use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class RegistrationTable extends AbstractTableGateway 
{
	protected $table = 'po_box_rg'; //tablename

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
		$where = (is_array($param) )? $param: array('t.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
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
	public function getLocDateWise($column,$year,$month,$location,$param)
	{		
	    $where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
		        ->columns(array(
					'id', 'location', 'region', 'postbox_no','name','cid','email','mobile','tphone','organisation','building_no','current_location','registration_date', 'status','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ))->having(array('year' => $year, 'month' => $month))
			   ->order(array('id DESC'));
			   $select->where($where);
			  if ($location != '-1')
			  {
				$select->where(array('location'=>$location));
			  }
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
	 * get location id present in location
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getTransportReport($region,$where)
	{		
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
		       ->where($where);
			if($region != '-1'){
				$select->where(array('region'=>$region));
			} 
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
	public function getDistinctRegion($region)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
			   ->join(array('r'=>'adm_region'),'r.id = t.region',array('region'=>'region','region_id' =>'id'))
			   ->columns(array(new Expression('DISTINCT(t.region) as region')))
			   ->order(array('t.region'));
			if($region != '-1'){
				$select->where(array('t.region'=>$region));
			}  
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	public function getDateWise($column,$year)
	{	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
                 ->columns(array('id','postbox_no','name','registration_date','status',
					'year' => new Expression('YEAR('.$column.')'),
			   ))->having(array('year' => $year))
	    	   ->order(array('id DESC'));
		   
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
		/**
	* Return Distinct value of the column
	* @param Array $where
	* @param String $column
	* @return Array | Int
	*/
	public function getDistinct($column, $where = NULL)
	{
	$adapter = $this->adapter;
	$sql = new Sql($adapter);
	$select = $sql->select();
	$select->from($this->table);
	$select->columns(array(
	'distinct' => new Expression('DISTINCT(' . $column . ')')
	));
	//$select->order(array($column.' ASC'));
	if($where!=NULL){
	$select->where($where)
	->order(array($column.' ASC'));
	}
	$selectString = $sql->getSqlStringForSqlObject($select);
	$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();

	$column = array();
	foreach ($results as $result):
	array_push($column,$result['distinct']);
	endforeach;

	return $column;
	}
	
	
//Get PO Box Report  
	public function getPOReport($data,$start_date,$end_date, $where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('r'=>$this->table))
			   ->join(array('n'=>'po_box_renew'),'r.id = n.registration_id')
		->where->between('n.renewal_date',$start_date,$end_date);
		if($data['location'] != '-1'){
			$select->where(array('r.location'=>$data['location']));
		}
		if($data['postbox'] != '-1'){
			$select->where(array('r.postbox_no'=>$data['postbox']));
		}
		if($data['status'] != '-1'){
			$select->where(array('r.status'=>$data['status']));
		}
		
		if($where!=NULL){
			$select->where($where);
		}
		 $selectString = $sql->getSqlStringForSqlObject($select);
		 $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	//Get PO Box Report  
	public function getPOList($data,$where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('r'=>$this->table));
		if($data['location'] != '-1'){
			$select->where(array('r.location'=>$data['location']));
		}
		if($data['postbox'] != '-1'){
			$select->where(array('r.postbox_no'=>$data['postbox']));
		}
		if($data['status'] != '-1'){
			$select->where(array('r.status'=>$data['status']));
		}
		if($where!=NULL){
			$select->where($where);
		}
		 $selectString = $sql->getSqlStringForSqlObject($select);
		 $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
}

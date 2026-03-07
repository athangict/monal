<?php
namespace Sales\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class MarketTable extends AbstractTableGateway 
{
	protected $table = 'sl_market_rate'; //tablename

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
	    $select->from(array('mr'=>$this->table))
	    	   ->join(array('l'=>'adm_location'),'l.id = mr.location', array('location_name'=>'location','location_id' => 'id'))
	    	   ->order(array('id DESC'));
	    $selectString = $sql->getSqlStringForSqlObject($select);
	    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    return $results;
	}
	
	/**
	 * Return records of given condition array | given id
	 * @param Int | Array $param
	 * @return Array
	 */
	public function get($param)
	{
		$where = ( is_array($param) )? $param: array('mr.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('mr'=>$this->table))
	    	   ->join(array('l'=>'adm_location'),'l.id = mr.location', array('location_name'=>'location','location_id' => 'id'))
	    	   ->where($where);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int | Array $param
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
					'id', 'document_no', 'document_date', 'location', 'status','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ))->having(array('year' => $year, 'month' => $month))
			   ->order(array('status'=>array(1,3)))
			   ->order(array('id DESC'));
			   $select->where($where);
			  if ($location != '-1')
			  {
				$select->where(array('location'=>$location));
			  }
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo "<pre>"; print_r($selectString);// exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
       /**
	 * Commit Credit Receipt
	 * Save record
	 * @allsalesID String $array
	 * @tran_id Int
	 * @return Int
	 */
	public function updateRecords($receipt_id, $tran_id)
	{
	    $adapter = $this->adapter;
		$sql = new Sql($adapter);
		$update = $sql->update();
		$update->table($this->table)
			   ->set(array('transaction' => $tran_id))
		       ->where->in('id',$receipt_id);
		$statement = $sql->prepareStatementForSqlObject($update);
		$result = $statement->execute(); 

	    return $result;	
	}
	/**
     * Return column value of given where condition | id
     * @param Int|array $parma
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

	/**
	 * Return records of given condition array
	 * @param Int $column
	 * @param Int $param
	 * @return Array
	 */
	public function getMonthlyDO($prefix_DO_code)
	{  
		$adapter = $this->adapter;			
		$sql = new Sql($adapter);		
		$select = $sql->select();
		$select->from($this->table);		
		$select->where->like('document_no', $prefix_DO_code."%");	
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return  $results;
	}
		
	/**
	 * CUSTOMER OUTSTANDING STATEMENT
	 * Return sum of a column of given condition
	 * @param Int|array $parma
     * @param String $column
	 * @return String | Int
	**/
	public function getSum($column,$where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'sum' => new Expression('SUM('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
		//echo $column;exit;
		return $column;
	}
	/**
	 * CUSTOMER OUTSTANDING STATEMENT
	 * Return distinct customers 
	 * @param Int | Array
	 * @return Array
	 */
	public function getDistinctCustomers($param,$start_date)
	{
		$where = ( is_array($param) )? $param: array('r.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('r'=>$this->table))
			   ->join(array('rd'=>'sl_receipt_dtls'),'r.id = rd.receipt',array())
			   ->columns(array(new Expression('SUM(rd.received_amount + rd.tds - rd.penalty) as sum')))
			   ->where($where)
			   ->where->lessThanOrEqualTo('r.receipt_date',$start_date);
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
		//echo $column;exit;
		return $column;
	}
	/**
	 * CUSTOMER OUTSTANDING STATEMENT
	 * Return distinct customers 
	 * @param Int | Array
	 * @return Array
	 */
	public function getCustomerOutstanding($param,$start_date)
	{
		$where = ( is_array($param) )? $param: array('r.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('r'=>$this->table))
			   ->join(array('rd'=>'sl_receipt_dtls'),'r.id = rd.receipt',array())
			   ->columns(array(new Expression('SUM(rd.received_amount + rd.tds - rd.penalty) as sum')))
			   ->where($where)
			   ->where->lessThanOrEqualTo('r.receipt_date',$start_date);
			   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
		//echo $column;exit;
		return $column;
	}		
}	



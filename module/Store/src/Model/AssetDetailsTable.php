<?php
namespace Store\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class AssetDetailsTable extends AbstractTableGateway 
{
	protected $table = 'in_asset_dtls'; //tablename

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
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getSortByLocationName($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('b'=>$this->table))
		      ->join(array('l'=>'sys_location'), 'b.location = l.id', array('location' => 'id', 'location_name' => 'location'))
              ->where($where)
			  ->order(array('location_name ASC'));	      
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	public function getByBatchLocation($batch, $location)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->where(array('batch'=>$batch))
				->where(array('location'=>$location));	
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
	 * Return sum value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getStockBalance($column, $where=NULL)
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
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
		$column =  $result['sum'];
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
	public function remove($param)
	{   
	    $where = ( is_array($param) )? $param: array('id' => $param);
		return $this->delete($where);
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
	 * STOCK MOVEMENT
	 * Return max value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getSUM($where=NULL, $column)
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
			$select->where->greaterThan('quantity','0');
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
	
		return $column;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getCount($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'count' => new Expression('COUNT(location)')
		));
		$select->where($where);
		$select->where->greaterThan('quantity','0');
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
			$column =  $result['count'];
		endforeach;
	
		return $column;
	}
        
        /**
	 * Return Asset Amount  which is not present in Issue Details 
	 * @param Array $param
	 * @param String column
	 * @return Array
	 */
	public function getSumAmount($item,$location)
	{			 
		$sub0 = new Select(array('id'=>"in_issue_details"));
		$sub0->columns(array("assetssp"))
			 ->where->equalTo('item',$item);	 		
			 
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ad'=>$this->table))
			   ->join(array('a'=>'in_asset'), 'a.id = ad.asset', array());
	    $select->where->equalTo('a.item', $item);
	    $select->where->equalTo('ad.location', $location);
		$select ->columns(array(
					'sum' => new Expression('SUM(ad.amount)')
			    ));
	    $select->where->Notin('a.id', $sub0);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray(); 
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
	    return $column;
	}
	
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getSumQty($item,$location)
	{
		$sub0 = new Select(array('id'=>"in_issue_details"));
		$sub0->columns(array("assetssp"))
			 ->where->equalTo('item',$item);	 		
			 
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ad'=>$this->table))
			   ->join(array('a'=>'in_asset'), 'a.id = ad.asset', array());
	    $select->where->equalTo('a.item', $item);
	    $select->where->equalTo('ad.location', $location);
		
		$select->columns(array(
					'sum' => new Expression('Sum(ad.quantity)')
			    ));
	    $select->where->Notin('a.id', $sub0);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray(); 
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
	    return $column;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getAssetDetails($item,$location)
	{
		$sub0 = new Select(array('id'=>"in_issue_details"));
		$sub0->columns(array("assetssp"))
			 ->where->equalTo('item',$item);	 		
			 
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ad'=>$this->table))
			   ->join(array('a'=>'in_asset'), 'a.id = ad.asset', array())
	           ->where->equalTo('a.item', $item)
	           ->where->equalTo('ad.location', $location)
	           ->where->Notin('a.id', $sub0);
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
	public function getSumAmt($location,$param)
	{		
        $where = ( is_array($param) )? $param: array('id' => $param);
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('ad'=>$this->table))
		       ->join(array('a'=>'in_asset'), 'a.id = ad.asset', array());
		$select->columns(array(
					'sum' => new Expression('SUM(ad.amount)')
			    ));
		if($location != '-1'){
			$select->where(array('ad.location' => $location));
		}
		$select->where($where);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray(); 
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
	    return $column;
	}
}

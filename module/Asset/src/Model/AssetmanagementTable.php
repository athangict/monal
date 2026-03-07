<?php
namespace Asset\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class AssetmanagementTable extends AbstractTableGateway 
{
	protected $table = 'ast_asset'; //tablename

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
	    $select->from(array('a'=>$this->table))
				->join(array('l'=>'adm_location'), 'l.id=a.location', array('location'=>'location',  'location_id'=>'id'))
				->join(array('r'=>'adm_region'), 'r.id=l.region', array('region'=>'region',  'region_id'=>'id'));
	    $selectString = $sql->getSqlStringForSqlObject($select);
		//echo  $selectString;
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
		$where = ( is_array($param) )? $param: array('a.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('a'=>$this->table))
				->join(array('l'=>'adm_location'), 'l.id=a.location', array('location'=>'location',  'location_id'=>'id'))
				->join(array('r'=>'adm_region'), 'r.id=l.region', array('region'=>'region',  'region_id'=>'id'))
		        ->where($where)
				->order('a.assetid');
		
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getasset($param)
	{
		$where = ( is_array($param) )? $param: array('a.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('a'=>$this->table))
				->join(array('l'=>'adm_location'), 'l.id=a.location', array('location'=>'location',  'location_id'=>'id'))
				->join(array('r'=>'adm_region'), 'r.id=l.region', array('region'=>'region',  'region_id'=>'id'))
		        ->where($where);
	    $select->where(array("a.status" =>1));
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getActiveassets($param)
	{
		$where = ( is_array($param) )? $param: array('a.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->where(array("status" => $param));
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getassetopening($asset_type, $end_date,$column)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array( new Expression('SUM('.$column.') as totalasset')))
		       ->where(array("asset_type" => $asset_type))
		       ->where->lessThanOrEqualTo('putin_date',$end_date);
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
	public function getdispose($asset_type,$end_date,$column)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array( new Expression('SUM('.$column.') as sumdispose')))
		       ->where(array("status" =>[19,23]))//dispose
		       ->where(array("asset_type" => $asset_type));
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
	public function getadditions($asset_type,$current_date, $end_date,$column)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array( new Expression('SUM('.$column.') as totaladditionsum')))
		       ->where(array("status" =>[1,22]))
		       ->where(array("asset_type" => $asset_type))
		       ->where->between('putin_date', $current_date, $end_date);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
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
            $columns='';
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
		$where = ( is_array($param) )? $param: array('a.id' => $param);
		$adapter = $this->adapter;
		
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MAX('.$column.')')
		));
		//$sub0 = $sub0->toArray();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('a'=>$this->table))
				->join(array('f'=>'fa_fund'), 'f.id=a.fund', array('fund'=>'code',  'fund_id'=>'id'))
				->join(array('act'=>'adm_activity'), 'act.id=a.activity', array('activity'=>'activity',  'activity_id'=>'id'))
				->join(array('d'=>'adm_department'), 'd.id=act.department', array('department'=>'department',  'department_id'=>'id'))
				->join(array('l'=>'adm_location'), 'l.id=a.location', array('location'=>'location',  'location_id'=>'id'))
				->join(array('r'=>'adm_region'), 'r.id=l.region', array('region'=>'region',  'region_id'=>'id'))
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
		$where = ( is_array($param) )? $param: array('a.id' => $param);
		$adapter = $this->adapter;
	
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MIN('.$column.')')
		));
		//$sub0 = $sub0->toArray();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('a'=>$this->table))
				->join(array('f'=>'fa_fund'), 'f.id=a.fund', array('fund'=>'code',  'fund_id'=>'id'))
				->join(array('act'=>'adm_activity'), 'act.id=a.activity', array('activity'=>'activity',  'activity_id'=>'id'))
				->join(array('d'=>'adm_department'), 'd.id=act.department', array('department'=>'department',  'department_id'=>'id'))
				->join(array('l'=>'adm_location'), 'l.id=a.location', array('location'=>'location',  'location_id'=>'id'))
				->join(array('r'=>'adm_region'), 'r.id=l.region', array('region'=>'region',  'region_id'=>'id'))
		->where($where)
		->where($column);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getbyassettype($typeid,$location,$status)
	{		
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		if($typeid != '-1'){
			$select->where(array('asset_type'=>$typeid));
		}
		if($location != '-1'){
			$select->where(array('location'=>$location));
		}
        if($status != '-1'){
			$select->where(array('status'=>$status));
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
	public function getPreDep($asset_type,$column)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array( new Expression('SUM('.$column.') as totalpredepsum')))
		       ->where(array("asset_type" => $asset_type));
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
	public function getdisposedDep($asset_type,$column)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array( new Expression('SUM('.$column.') as totaldisposal')))
		       ->where(array("asset_type" => $asset_type,'status'=>19));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of asset with type and custodian
	 * @param Int $id
	 * @return Array
	 */
	public function getbyassettypeCu($typeid,$location,$custodian,$status,$currentdate)
	{		
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->where(array('putin_date < ?' => $currentdate));
		if($typeid != '-1'){
			$select->where(array('asset_type'=>$typeid));
		}
		if($location != '-1'){
			$select->where(array('location'=>$location));
		}
        if($custodian != '-1'){
			$select->where(array('custodian'=>$custodian));
		}
		if($status != '-1'){
			$select->where(array('status'=>$status));
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
}


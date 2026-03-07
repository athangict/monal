<?php
namespace Asset\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class DepreciationTable extends AbstractTableGateway 
{
	protected $table = 'ast_depreciate'; //tablename

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
	 * Return all rentrecords in month if its rent is prepared
	 * @return Array
	 */
	public function getDep($year)
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
	public function getDepByMonth($year,$month,$type)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table, array('month', 'year'));
		$select->order(array('month DESC', 'year DESC'));
		if($year != '-1'):
			$select->where(array('year' => $year,'month'=>$month));
		endif;
		if($type != '-1'):
			$select->where(array('type' => $type));
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
	public function getPreDep($asset_type,$year,$column)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array( new Expression('SUM('.$column.') as totalpredepsum')))
		       ->where(array("type" => $asset_type))
			   ->where->between('year', '2024', $year);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	public function getPreDepindividual($id,$asset_type,$year,$column)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array( new Expression('SUM('.$column.') as totalpredepsum')))
		       ->where(array("type" => $asset_type,"asset" => $id))
			   ->where->between('year', '2024', $year);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}

	public function getCurrentDep($asset_type,$year,$column)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array( new Expression('SUM('.$column.') as totalcurrentsum')))
		       ->where(array('type' => $asset_type,'year'=>$year,'status'=>4));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	public function getCurrentDepIndividual($asset_id,$year,$column)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array( new Expression('SUM('.$column.') as totalcurrentsum')))
		       ->where(array('asset' => $asset_id,'year'=>$year,'status'=>4));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	public function getAccdep($asset_type)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d'=>$this->table))
		->join(array('a'=>'ast_asset'),'a.id=d.asset')
		->columns(array(
            'totalaccdep' => new Expression('SUM(d.dep_amount)')
        ))
		->where(array('d.type' => $asset_type,'a.status'=>19,'d.status'=>4));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
}


<?php
namespace Sales\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class SchemeTable extends AbstractTableGateway 
{
	protected $table = 'sl_scheme'; //tablename

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
	    $select->from(array('sc'=>$this->table))
	    	   ->join(array('p'=>'fa_party'),'p.id = sc.supplier', array('supplier'=>'code','supplier_id' => 'id'))
	    	   ->join(array('sct'=>'sl_scheme_type'),'sct.id = sc.scheme_type', array('scheme_type'=>'scheme_type','scheme_type_id' => 'id'))
	           ->join(array('a'=>'adm_activity'),'a.id = sc.activity', array('activity'=>'activity','activity_id' => 'id'))
	    	   ->join(array('i'=>'st_items'), 'i.id = sc.item', array('item'=>'code', 'item_id'=>'id'))

	    	   ->order(array('id DESC'));
	    
	    $selectString = $sql->getSqlStringForSqlObject($select);
		echo $selectString;
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
		$select->from($this->table)
                 ->columns(array(
					'id','scheme_no','scheme_date','activity','supplier','sub_head','item','scheme_type',
					'from_date','to_date','note','claim','status','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ))->having(array('year' => $year))
	    	   ->order(array('id DESC'));
			   if($month != '-1'){
				   $select->having(array('month' => $month));
			   }
		       
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
		$where = ( is_array($param) )? $param: array('sc.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('sc'=>$this->table))
			   ->join(array('p'=>'fa_party'),'p.id = sc.supplier', array('supplier'=>'code','supplier_id' => 'id'))
	    	   ->join(array('sct'=>'sl_scheme_type'),'sct.id = sc.scheme_type', array('scheme_type'=>'scheme_type','scheme_type_id' => 'id'))
	           ->join(array('a'=>'adm_activity'),'a.id = sc.activity', array('activity'=>'activity','activity_id' => 'id'))
	    	   ->join(array('i'=>'st_items'), 'i.id = sc.item', array('item'=>'code', 'item_id'=>'id'))
		       ->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
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
	public function getMonthlySC($prefix_SC_code)
	{  
		$adapter = $this->adapter;			
		$sql = new Sql($adapter);		
		$select = $sql->select();
		$select->from($this->table);		
		$select->where->like('scheme_no', $prefix_SC_code."%");	
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return  $results;
	}	
	/**
	 * Get Scheme for Discounts in POS
	 * Return records of given condition array | given id
	 * @param Int | Array $param
	 * @return Array
	 */
	public function getDiscount($param,$sales_date)
	{
		$where = ( is_array($param) )? $param: array('sc.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array(
					'id','scheme_no','scheme_date','activity','supplier','sub_head','item',
					'scheme_type','note','claim','status','author','created','modified',
					'from_date' => new Expression('from_date'),
					'to_date' => new Expression('to_date'),
				))
		       ->where($where)
			   ->where->lessThanOrEqualTo('from_date',$sales_date)
			   ->where->greaterThanOrEqualTo('to_date',$sales_date);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
}	


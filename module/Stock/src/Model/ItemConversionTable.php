<?php
namespace Stock\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class ItemConversionTable extends AbstractTableGateway 
{
	protected $table = 'st_item_conversion'; //tablename

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
	 * Return records of given year and month
	 * @param Int $id
	 * @return Array
	 */
	public function getDateWise($column, $year, $month)
	{	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$con = array(
					'id','conversion_date','conversion_no','location','item','batch','uom','issued_qty','basic_qty','landed_cost','converted_item','converted_item_uom',
                    'converted_qty','converted_basic_qty', 'status', 'author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   );
        $select->from($this->table)
                   ->columns($con)->having(array('year' => $year, 'month' => $month))
				   ->order(array('id DESC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return records of given start date and end date
	 * @param Int | Array $param
	 * @param Date $start_date
	 * @param Date $end_date
	 * @return Array
	 */
	public function getByDate($start_date,$end_date,$param)
	{	
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->where($where)
				->order(array('id ASC'))
				->where->between('conversion_date', $start_date, $end_date);
				
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
	 * Return records of given condition array
	 * @param Int $column
	 * @param Int $param
	 * @return Array
	 */
	public function getMonthlyDC($prefix_PO_code)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->where->like('conversion_no', $prefix_PO_code."%");
			
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
	 * STOCK MOVEMENT
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function getSMOpeningSUM($start_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThan($col_date,$start_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
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
	 * STOCK MOVEMENT
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function getSMQuantitySUM($start_date, $end_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				$select->where->between($col_date, $start_date, $end_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
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
	 * STOCK MOVEMENT 4 - filter process
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function filterSMBatchfrom($start_date, $end_date, $location, $param, $col_date, $col_loc,$array,$item)
	{  
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('c' => $this->table))
		       ->join(array('i'=>'st_items'), 'i.id = c.item', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(batch)')
			    ));
				$select->where($where);
				$select->where->between($col_date, $start_date, $end_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('item'=>$item));
			    }
				if(sizeof($array)>0):
					$select->where->notIn('batch',$array);
				endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	/**
	 * STOCK MOVEMENT 4 - filter process
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function filterSMBatchto($start_date, $end_date, $location, $param, $col_date, $col_loc,$array,$item)
	{  
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('c' => $this->table))
		       ->join(array('i'=>'st_items'), 'i.id = c.converted_item', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(converted_item_batch)')
			    ));
				$select->where($where);
				$select->where->between($col_date, $start_date, $end_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('converted_item'=>$item));
			    }
				if(sizeof($array)>0):
					$select->where->notIn('converted_item_batch',$array);
				endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	/**
	 * STOCK MOVEMENT 4 - fetch opening sum
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSMOpeningSUM($start_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThan($col_date,$start_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
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
	 * STOCK MOVEMENT 4 - fetch sum of transaction quantity
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSMQuantitySUM($start_date, $end_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				$select->where->between($col_date, $start_date, $end_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
	
		return $column;
	}
}

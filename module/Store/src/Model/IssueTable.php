<?php
namespace Store\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class IssueTable extends AbstractTableGateway 
{
	protected $table = 'in_issue'; //tablename

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
	public function getDateWise($column, $year, $month, $location, $param, $flag)
	{	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$con = array(
					'id','issue_date','challan_no','from_location','to_location','cost_center','item_group','fcb_transport','vehicle_no','note',
					'status','received_by','received_on','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ); 
		if($flag){
			$select->from($this->table)
                   ->columns($con)->having(array('year' => $year, 'month' => $month))
				   ->order(array('id DESC'));			
		}
		else{ 
			$select->from($this->table)
                   ->columns($con)->having(array('year' => $year, 'month' => $month))
				   ->order(array('id DESC'))	
				   ->where->equalTo('from_location', $location)
						->or
						->in('to_location', $param);
		}	   
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
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
				->where->between('dispatch_date', $start_date, $end_date);
				
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
	public function getMonthlyINO($prefix_PO_code)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->where->like('challan_no', $prefix_PO_code."%");
			
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
	 * Return records of given condition array | given id
	 * @param Start_date 
	 * @param Int $id
	 * @return Array
	*/ 
	public function getSMOpening($start_date, $param, $column)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
		        ->where($where)
				->order(array('id DESC'))
				->where->lessThan($column,$start_date);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return records of given condition array | given id
	 * @param Start_date & End_date
	 * @param Int $id
	 * @return Array
	*/ 
	public function getSMQuantity($start_date, $end_date, $param, $column)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
		        ->where($where)
				->order(array('id DESC'))
				//->where->lessThan($column,$start_date);
				->where->between($column, $start_date, $end_date);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param End_date
	 * @param Int $id
	 * @return Array
	*/ 
	public function getSMIntransitQuantity($end_date, $param, $column)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
		        ->where($where)
				->order(array('id DESC'))
				->where->lessThanOrEqualTo($column, $end_date);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * STOCK MOVEMENT
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function getSMOpeningSUM($start_date, $location, $param, $col_date, $col_loc, $col_qty,$openingDate)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'st_dispatch_details'), 'd.id = dd.dispatch', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThan($col_date,$start_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
                                        $select->where->greaterThanOrEqualTo($col_date,$openingDate);
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
	 * STORE REPORT - fetch opening sum 
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSROpeningforSspSUMATM($start_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
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
	 * STORE REPORT - fetch opening sum 
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSROpeningforSspSUMRt($start_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
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
		$select->from(array('d' => $this->table))
				->join(array('dd'=>'st_dispatch_details'), 'd.id = dd.dispatch', array());
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
	 * STOCK MOVEMENT
	 * Return sum of given condition array | given id
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function getSMIntransitQtySUM($end_date, $location, $param, $col_date, $col_loc, $col_qty,$openingDate)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'st_dispatch_details'), 'd.id = dd.dispatch', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThanOrEqualTo($col_date,$end_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
					$select->where->greaterThanOrEqualTo($col_date,$openingDate);
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
	 * Issue REPORT
	 * Return records of given condition array | given id
	 * @param Start_date & End_date
	 * @param Int $id
	 * @return Array
	*/ 
	public function getIssue($item_group, $source_loc, $destination_loc, $start_date, $end_date)
	{   
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('i' => $this->table))
			   ->join(array('d'=>'in_issue_details'), 'i.id = d.issue', array('item','issue','uom','assetssp','rate','quantity','accepted_quantity','demage_quantity','sound_quantity','shortage_quantity','amount','remarks'))
			   ->order(array('i.id DESC'))
			   ->where->between('i.issue_date', $start_date, $end_date);
		if($item_group != '-1'){
			$select->where(array('i.item_group' => $item_group));
		}
		if($source_loc != '-1'){
			$select->where(array('i.from_location' =>$source_loc));
		}
		if($destination_loc != '-1'){
			$select->where(array('i.to_location' =>$destination_loc));
		}
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
	public function filterSRItem($start_date, $end_date, $location, $param, $col_date, $col_loc,$item)
	{  
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
				->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
				$select->columns(array(
					'item' => new Expression('DISTINCT(dd.item)')
			    ));
				$select->where($where);
				$select->where->between($col_date, $start_date, $end_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('dd.item'=>$item));
			    }
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	/**
	 * STOCK MOVEMENT 4 - filter process
	 * Return sum of given condition array | given id
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function filterSRItemTransitDate($end_date, $location, $param, $dispatch_date, $received_date, $col_loc,$item)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
				$select->columns(array(
					'item' => new Expression('DISTINCT(dd.item)')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThanOrEqualTo($dispatch_date,$end_date);
				$select->where->greaterThan($received_date,$end_date);
				
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('dd.item'=>$item));
			    }
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
	public function filterSRReceiptItem($end_date, $location, $param, $col_date, $col_loc,$item)
	{  
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
				->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
				$select->columns(array(
					'item' => new Expression('DISTINCT(dd.item)')
			    ));
				$select->where($where);
				$select->where->lessThanOrEqualTo($col_date,$end_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('dd.item'=>$item));
			    }
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	/**
	 * STOCK MOVEMENT 4 - filter process
	 * Return sum of given condition array | given id
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function filterSRItemTransitStatus($end_date, $location, $param, $dispatch_date, $col_loc,$item)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
				$select->columns(array(
					'item' => new Expression('DISTINCT(dd.item)')
			    ));
				$select->where($where);
				$select->where->lessThanOrEqualTo($dispatch_date,$end_date);
				
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('dd.item'=>$item));
			    }
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
	public function fetchSROpeningSUM($start_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
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
	public function fetchSRQuantitySUM($start_date, $end_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
				->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
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
	 * STOCK MOVEMENT 4 - fetch sum of in transit quantity by date
	 * Return sum of given condition array | given id
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSRTransitQtyDateSUM($end_date, $location, $param, $dispatch_date, $received_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThanOrEqualTo($dispatch_date,$end_date);
				$select->where->greaterThan($received_date,$end_date);
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
	 * STOCK MOVEMENT 4 - fetch sum of in transit quantity by status
	 * Return sum of given condition array | given id
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSRTransitQtyStatusSUM($end_date, $location, $param, $dispatch_date, $received_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThanOrEqualTo($dispatch_date,$end_date);
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
	 * STOCK MOVEMENT 4 - filter process for store and spare
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function filterSRSsp($start_date, $end_date, $location, $param, $col_date, $col_loc,$array,$item_sub_group,$item)
	{  
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
				->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array())
                               ->join(array('i'=>'in_items'), 'i.id = dd.item', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(dd.assetssp)')
			    ));
				$select->where($where);
				$select->where->between($col_date, $start_date, $end_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('dd.item'=>$item));
			    }
				if(sizeof($array)>0):
					$select->where->notIn('dd.assetssp',$array);
				endif;
                            if($item_sub_group!='-1'){
					$select->where(array('i.item_sub_group'=>$item_sub_group));
			    }
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	
	/**
	 * STOCK MOVEMENT 4 - filter process for store and spare
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function filterSMReceiptSsp($end_date, $location, $param, $col_date, $col_loc,$array,$item_sub_group,$item)
	{  
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
				->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array())
                                ->join(array('i'=>'in_items'), 'i.id = dd.item', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(dd.assetssp)')
			    ));
				$select->where($where);
				$select->where->lessThanOrEqualTo($col_date,$end_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('dd.item'=>$item));
			    }
				if(sizeof($array)>0):
					$select->where->notIn('dd.assetssp',$array);
				endif;
                            if($item_sub_group!='-1'){
					$select->where(array('i.item_sub_group'=>$item_sub_group));
			    }
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	/**
	 * STOCK MOVEMENT 4 - filter process for store and spare
	 * Return sum of given condition array | given id
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function filterSRSspTransitDate($end_date, $location, $param, $dispatch_date, $received_date, $col_loc,$array,$item_sub_group,$item)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array())
                           			   ->join(array('i'=>'in_items'), 'i.id = dd.item', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(dd.assetssp)')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThanOrEqualTo($dispatch_date,$end_date);
				$select->where->greaterThan($received_date,$end_date);
				
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('dd.item'=>$item));
			    }
				if(sizeof($array)>0):
					$select->where->notIn('dd.assetssp',$array);
				endif;
                            if($item_sub_group!='-1'){
					$select->where(array('i.item_sub_group'=>$item_sub_group));
			    }
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		return $results;
	}
	
	/**
	 * STOCK MOVEMENT 4 - filter process for store and spare
	 * Return sum of given condition array | given id
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function filterSRSspTransitStatus($end_date, $location, $param, $dispatch_date, $col_loc,$array,$item_sub_group,$item)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array())
                           ->join(array('i'=>'in_items'), 'i.id = dd.item', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(dd.assetssp)')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThanOrEqualTo($dispatch_date,$end_date);
				
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('dd.item'=>$item));
			    }
				if(sizeof($array)>0):
					$select->where->notIn('dd.assetssp',$array);
				endif;
                              if($item_sub_group!='-1'){
					$select->where(array('i.item_sub_group'=>$item_sub_group));
			    }
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
	public function fetchSROpeningforSspSUM($start_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
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
	 * STOCK MOVEMENT 4 - fetch sum of transaction quantity for stores and spares
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSRQuantityforSsspSUM($start_date, $end_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
				->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
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
	 * STOCK MOVEMENT 4 - fetch sum of transaction quantity  for stores and spares
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSRQuantityforSspSUM($start_date, $end_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
				->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
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
	 * STORE REPORT - fetch sum of transaction quantity for stores and spares
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSRQuantityforSsspSUMATM($start_date, $end_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
				->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
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
	 * STORE REPORT - fetch sum of transaction quantity for stores and spares
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSRQuantityforSsspSUMRt($start_date, $end_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
				->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
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
	 * STOCK MOVEMENT 4 - fetch sum of in transit quantity by date
	 * Return sum of given condition array | given id
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSRTransitQtyDateforSspSUM($end_date, $location, $param, $dispatch_date, $received_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				$select->where->lessThan($dispatch_date,$end_date);
				$select->where->greaterThan($received_date,$end_date);
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
	 * STOCK MOVEMENT 4 - fetch sum of in transit quantity by status
	 * Return sum of given condition array | given id
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSMTransitQtyStatusforSspSUM($end_date, $location, $param, $dispatch_date, $received_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThan($dispatch_date,$end_date);
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
	 * STOCK MOVEMENT 4 - filter process for Assets
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function filterSRAsset($start_date, $end_date, $location, $param, $col_date, $col_loc,$array,$item_sub_group,$item)
	{  
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
				->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array())
                                ->join(array('i'=>'in_items'), 'i.id = dd.item', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(dd.assetssp)')
			    ));
				$select->where($where);
				$select->where->between($col_date, $start_date, $end_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('dd.item'=>$item));
			         }
				if(sizeof($array)>0):
					$select->where->notIn('dd.assetssp',$array);
				endif;
                                if($item_sub_group!='-1'){
				    $select->where(array('i.item_sub_group'=>$item_sub_group));
			        }
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	/**
	 * STOCK MOVEMENT 4 - filter process for Assets
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function filterSRReceiptAsset($end_date, $location, $param, $col_date, $col_loc,$array,$item_sub_group,$item)
	{  
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
				->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array())
                                ->join(array('i'=>'in_items'), 'i.id = dd.item', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(dd.assetssp)')
			    ));
				$select->where($where);
				$select->where->lessThanOrEqualTo($col_date,$end_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('dd.item'=>$item));
			    }
				if(sizeof($array)>0):
					$select->where->notIn('dd.assetssp',$array);
				endif;
                            if($item_sub_group!='-1'){
					$select->where(array('i.item_sub_group'=>$item_sub_group));
			    }
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	
	/**
	 * STOCK MOVEMENT 4 - filter process
	 * Return sum of given condition array | given id
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function filterSRAssetTransitDate($end_date, $location, $param, $dispatch_date, $received_date, $col_loc,$array,$item_sub_group,$item)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array())
                             ->join(array('i'=>'in_items'), 'i.id = dd.item', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(dd.assetssp)')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThanOrEqualTo($dispatch_date,$end_date);
				$select->where->greaterThan($received_date,$end_date);
				
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('dd.item'=>$item));
			    }
				if(sizeof($array)>0):
					$select->where->notIn('dd.assetssp',$array);
				endif;
                            if($item_sub_group!='-1'){
					$select->where(array('i.item_sub_group'=>$item_sub_group));
			    }
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		return $results;
	}
	
	/**
	 * STOCK MOVEMENT 4 - filter process
	 * Return sum of given condition array | given id
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function filterSRAssetTransitStatus($end_date, $location, $param, $dispatch_date, $col_loc,$array,$item_sub_group,$item)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array())
                           ->join(array('i'=>'in_items'),'i.id=dd.item',array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(dd.assetssp)')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThanOrEqualTo($dispatch_date,$end_date);
				
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('dd.item'=>$item));
			    }
				if(sizeof($array)>0):
					$select->where->notIn('dd.assetssp',$array);
				endif;
                            if($item_sub_group!='-1'){
					$select->where(array('i.item_sub_group'=>$item_sub_group));
			    }
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		return $results;
	}
	
	/**
	 * STORE REPORT - fetch opening sum for asset
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSROpeningforAssetSUM($start_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
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
	public function fetchSRQuantityforAssetSUM($start_date, $end_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
				->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
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
	 * STOCK MOVEMENT 4 - fetch sum of in transit quantity by date
	 * Return sum of given condition array | given id
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSRTransitQtyDateforAssetSUM($end_date, $location, $param, $dispatch_date, $received_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				$select->where->lessThanOrEqualTo($dispatch_date,$end_date);
				$select->where->greaterThan($received_date,$end_date);
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
	 * STOCK MOVEMENT 4 - fetch sum of in transit quantity by status
	 * Return sum of given condition array | given id
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSRTransitQtyStatusforAssetSUM($end_date, $location, $param, $dispatch_date, $received_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'in_issue_details'), 'd.id = dd.issue', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThanOrEqualTo($dispatch_date,$end_date);
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

<?php
namespace Stock\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class DispatchTable extends AbstractTableGateway 
{
	protected $table = 'st_dispatch'; //tablename

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
					'id','dispatch_date','challan_no','from_location','to_location','activity','fcb_transport',
					'transporter','vehicle_no','party','note','status','received_by','received_on','goodrequest_no','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ); 
		//if($flag){
			$select->from($this->table)
                   ->columns($con)->having(array('year' => $year, 'month' => $month))
				   ->order(array('id DESC'));			
		//}
		//else{ 
			$select->from($this->table)
                   ->columns($con)->having(array('year' => $year, 'month' => $month))
				   ->order(array('id DESC'))	
				   ->where->equalTo('from_location', $location)
							//->in('activity',$activity)
						->or
						->in('to_location', $param);
						//->in('activity',$activity);
		//}	   
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
	 * STOCK MOVEMENT 4 - fetch sum of in transit quantity by date
	 * Return sum of given condition array | given id
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSMTransitQtyDateSUM2($start_date,$end_date, $location, $param, $dispatch_date, $received_date, $col_loc, $col_qty)
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
				$select->where->lessThanOrEqualTo($dispatch_date,$end_date);
				$select->where->greaterThan($received_date,$end_date);
				$select->where->between('d.dispatch_date',$start_date,$end_date);
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
	public function fetchSMTransitQtyStatusSUM2($start_date,$end_date, $location, $param, $dispatch_date, $received_date, $col_loc, $col_qty)
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
				$select->where->lessThanOrEqualTo($dispatch_date,$end_date);
			    $select->where->between('d.dispatch_date',$start_date,$end_date);
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
	public function fetchSMTransitQtyDateSUM1($end_date, $location, $param, $dispatch_date, $received_date, $col_loc, $col_qty)
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
				$select->where->lessThanOrEqualTo($dispatch_date,$end_date);
				$select->where->greaterThan($received_date,$end_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
		$selectString = $sql->getSqlStringForSqlObject($select);
//		echo $selectString; exit;
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
	public function fetchSMTransitQtyStatusSUM1($end_date, $location, $param, $dispatch_date, $received_date, $col_loc, $col_qty)
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
	* MODIFIED FOR FROM VALIDATION (REMOTE VALIDATOR) 
	*/
	public function isPresent($column, $value)
	{
		$column = $column; $value = $value;
		$resultSet = $this->select(function(Select $select) use ($column, $value){
			$select->where(array($column => $value));
		});
		
		$resultSet = $resultSet->toArray();
		return (sizeof($resultSet)>0)?FALSE:TRUE;
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
	 * DISPATCH REPORT
	 * Return records of given condition array | given id
	 * @param Start_date & End_date
	 * @param Int $id
	 * @return Array
	*/ 
	public function getDispatch($data)
	{   
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'st_dispatch_details'), 'd.id = dd.dispatch', array('item','batch','basic_uom','basic_quantity','accepted_qty','sound_qty','damage_qty','shortage_qty','remarks'))
			   ->join(array('i'=>'st_items'), 'i.id = dd.item')
			   ->join(array('g' =>'st_item_group'), 'g.id = i.item_group')
			   ->join(array('c' =>'st_item_class'), 'c.id = g.item_class')
			   ->order(array('d.id DESC'))
			   ->where->between('d.dispatch_date', $data['start_date'], $data['end_date']);
		
		if($data['source_location'] != '-1'){
			$select->where(array('d.from_location' => $data['source_location']));
		}
		if($data['destination_location']!= '-1'){
			$select->where(array('d.to_location' => $data['destination_location']));
		}
		if($data['class'] != '-1'){
			$select->where(array('c.id' => $data['class']));
		}
		if($data['group'] != '-1'){
			$select->where(array('g.id' => $data['group']));
		}
		if($data['item'] != '-1'){
			$select->where(array('dd.item' => $data['item']));
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
/**
	 * STOCK MOVEMENT 3
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function getDistinctIB($start_date, $end_date, $location, $param, $col_date, $col_loc)//,$batch_array
	{  
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
				->join(array('dd'=>'st_dispatch_details'), 'd.id = dd.dispatch', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(dd.batch)')
			    ));
				$select->where($where);
				$select->where->between($col_date, $start_date, $end_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	/**
	 * STOCK MOVEMENT 3
	 * Return sum of given condition array | given id
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function getDistinctIBTransit($end_date, $location, $param, $col_date, $col_loc)//,$batch_array
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'st_dispatch_details'), 'd.id = dd.dispatch', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(dd.batch)')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThanOrEqualTo($col_date,$end_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		return $results;
	}
        //intransit from stock reconcilation
	public function getSRIntransitQtyTwoSUM($start_date,$end_date,$location, $param, $col_date,$col_loc, $col_qty)
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
				$select->where->between($col_date,$start_date,$end_date);
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
	public function filterSMBatch($start_date, $end_date, $location, $param, $col_date, $col_loc,$array,$item)
	{  
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
				->join(array('dd'=>'st_dispatch_details'), 'd.id = dd.dispatch', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(dd.batch)')
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
					$select->where->notIn('dd.batch',$array);
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
	public function filterSMReceiptBatch($end_date, $location, $param, $col_date, $col_loc,$array,$item)
	{  
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
				->join(array('dd'=>'st_dispatch_details'), 'd.id = dd.dispatch', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(dd.batch)')
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
					$select->where->notIn('dd.batch',$array);
				endif;
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
	public function filterSMBatchTransitDate($end_date, $location, $param, $dispatch_date, $received_date, $col_loc,$array,$item)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'st_dispatch_details'), 'd.id = dd.dispatch', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(dd.batch)')
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
					$select->where->notIn('dd.batch',$array);
				endif;
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
	public function filterSMBatchTransitStatus($end_date, $location, $param, $dispatch_date, $col_loc,$array,$item)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'st_dispatch_details'), 'd.id = dd.dispatch', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(dd.batch)')
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
					$select->where->notIn('dd.batch',$array);
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
	public function fetchSMOpeningSUM($start_date, $location, $param, $col_date, $col_loc, $col_qty,$openingDate)
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
	//	echo $selectString; exit;
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
             //		echo $selectString; exit;
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
	public function fetchSMTransitQtyDateSUM($end_date, $location, $param, $dispatch_date, $received_date, $col_loc, $col_qty)
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
	public function fetchSMTransitQtyStatusSUM($end_date, $location, $param, $dispatch_date, $received_date, $col_loc, $col_qty)
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
	 * STOCK MOVEMENT 4 - fetch sum of in transit quantity by date
	 * Return sum of given condition array | given id
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSMTQDateSUM($end_date,$location,$batch_id,$item)
	{   
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
	           ->join(array('dd'=>'st_dispatch_details'), 'd.id = dd.dispatch', array());
        $select->columns(array(
			'sum' => new Expression('SUM(dd.basic_quantity)')
		));
		$select->where(array('dd.batch'=>$batch_id,'dd.item'=>$item))
			   ->where(array('d.status'=>array(2,3)))
			   ->where->lessThanOrEqualTo('d.dispatch_date',$end_date)
			   ->where->greaterThan('d.received_on',$end_date);
		if($location != '-1'){
		$select->where(array('d.to_location'=>$location));}
		$in_transit_status = $this->fetchSMTQStatusSUM($end_date,$location,$batch_id,$item);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		foreach ($results as $result):
			$column =  $result['sum'] + $in_transit_status;
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
	public function fetchSMTQStatusSUM($end_date,$location,$batch_id,$item)
	{   
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('dd'=>'st_dispatch_details'), 'd.id = dd.dispatch', array());
				$select->columns(array(
					'sum' => new Expression('SUM(dd.basic_quantity)')
			    ));
			    $select->where(array('dd.batch'=>$batch_id,'dd.item'=>$item,'d.status'=>array(2,10)));
				$select->where->lessThanOrEqualTo('d.dispatch_date',$end_date);
				if($location != '-1'){
					$select->where(array('d.to_location' => $location));
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
	 * Return sum of given column and condition array | given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getSumto($location,$item, $data)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			->join(array('dd' => 'st_dispatch_details'), 'd.id = dd.dispatch')
			->columns(array(
				'sum' => new Expression('SUM(' . 'dd.accepted_qty' . ')')
			))
			->where->between('d.dispatch_date', $data['start_date'], $data['end_date']);
		$select->where(array('d.to_location' => $location, 'dd.item' => $item,'d.status'=>4));
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		$result = current($results) ?: array('sum' => 0);

		// Return the sum
		return $result['sum'];
	}
	/**
	 * Return sum of given column and condition array | given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getSumPending($location,$item, $data)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			->join(array('dd' => 'st_dispatch_details'), 'd.id = dd.dispatch')
			->columns(array(
				'sum' => new Expression('SUM(' . 'dd.quantity' . ')')
			))	->where->between('d.dispatch_date', $data['start_date'], $data['end_date']);
		$select->where(array('d.to_location' => $location, 'dd.item' => $item,'d.status'=>3));
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		$result = current($results) ?: array('sum' => 0);

		// Return the sum
		return $result['sum'];
	}
	/**
	 * Return sum of given column and condition array | given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
		public function getSumfrom($location,$item, $data)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			->join(array('dd' => 'st_dispatch_details'), 'd.id = dd.dispatch')
			->columns(array(
				'sum' => new Expression('SUM(' . 'dd.quantity' . ')')
			))
			->where->between('d.dispatch_date', $data['start_date'], $data['end_date']);
		$select->where(array('d.from_location' => $location, 'dd.item' => $item,'d.status'=>4));
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();

		$result = current($results) ?: array('sum' => 0);

		// Return the sum
		return $result['sum'];
	}
	/**
	 * Return sum of given column and condition array | given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getPreSumto($location,$item, $data)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			->join(array('dd' => 'st_dispatch_details'), 'd.id = dd.dispatch')
			->columns(array(
				'sum' => new Expression('SUM(' . 'dd.accepted_qty' . ')')
			))
			->where->lessThan('d.dispatch_date', $data['start_date']);
		$select->where(array('d.to_location' => $location, 'dd.item' => $item,'d.status'=>4));
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		$result = current($results) ?: array('sum' => 0);

		// Return the sum
		return $result['sum'];
	}
	/**
	 * Return sum of given column and condition array | given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
		public function getPreSumfrom($location,$item, $data)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			->join(array('dd' => 'st_dispatch_details'), 'd.id = dd.dispatch')
			->columns(array(
				'sum' => new Expression('SUM(' . 'dd.accepted_qty' . ')')
			))
			->where->lessThan('d.dispatch_date', $data['start_date']);
		$select->where(array('d.from_location' => $location, 'dd.item' => $item,'d.status'=>[3,4]));
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();

		$result = current($results) ?: array('sum' => 0);

		// Return the sum
		return $result['sum'];
	}
	/**
	 * Return records of given year and month
	 * @param Int $id
	 * @return Array
	 */	
	public function getDateWiseAdmin($column, $year, $month)
	{	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$con = array(
					'id','challan_no','dispatch_date','from_location','to_location','activity','goodrequest_no',
					'status','note','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ); 
		//if($flag){
			$select->from($this->table)
                   ->columns($con)->having(array('year' => $year, 'month' => $month))
				   ->order(array('id DESC'));			
		//}
		//else{ 
			$select->from($this->table)
                   ->columns($con)->having(array('year' => $year, 'month' => $month))
				   ->order(array('id DESC'));	
				 /*  ->where->equalTo('from_location', $location)
						->or
						->equalTo('to_location', $location); 	*/		
		//}	   
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
}

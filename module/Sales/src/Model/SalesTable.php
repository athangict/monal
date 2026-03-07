<?php
namespace Sales\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class SalesTable extends AbstractTableGateway 
{
	protected $table = 'sl_sales'; //tablename

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
	 * END OF SESSION
	 * Save record
	 * @allsalesID String $array
	 * @tran_id Int
	 * @return Int
	 */
	public function updateRecords($allsalesID, $tran_id)
	{
	    $adapter = $this->adapter;
		$sql = new Sql($adapter);
		$update = $sql->update();
		$update->table($this->table)
			   ->set(array('transaction' => $tran_id))
		       ->where->in('id',$allsalesID);
		$statement = $sql->prepareStatementForSqlObject($update);
		$result = $statement->execute(); 
		
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
	public function getMin($column,$where = NULL)
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
        //returns max sales date 
	public function getMaxTranDate($where=NULL, $column)
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
		$select->where->notEqualTo('transaction', 0);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
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
	public function getMonthlySL($prefix_SL_code)
	{  
		$adapter = $this->adapter;			
		$sql = new Sql($adapter);		
		$select = $sql->select();
		$select->from($this->table);		
		$select->where->like('sales_no', $prefix_SL_code."%");	
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		//print_r($results);exit;
		return  $results;
	}
	/**
	 * Return sum of given column and condition array | given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getSum($param,$column)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->columns(array(
				'sum' => new Expression('SUM('.$column.')')
				))
		       ->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
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
	public function getSumCredit($param,$column1,$column2)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->columns(array(
				'sum' => new Expression('SUM('.$column1.'-'.$column2.')')
				))
		       ->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
		
		return $column;
	}
	
	/**
	 * Return records of given year and month
	 * @param Int $id
	 * @return Array
	 */
	public function getDateWise($column,$year,$month,$location)
	{	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
                 ->columns(array(
					'id', 'sales_no', 'sales_date', 'location', 'credit', 'customer', 'due_date', 'payment_type', 'account_no', 
					'payment_amount', 'received_amount', 'transaction', 'status', 'author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ))->having(array('year' => $year))
	    	   ->order(array('id DESC'));
		    if($location!= '-1'){
				$select->where(array('location'=>$location));
			}
			if($month != '-1'){
				$select->having(array('month' => $month));
			}
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given year and month
	 * @param Int $id
	 * @return Array
	 */
	public function getDateWiseConsumable($column,$year,$month,$location,$param)
	{	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
				->join(array('sd'=>'sl_sales_dtls'),'s.id =sd.sales ',('item'))
				->join(array('i'=>'st_items'),'i.id =sd.item',('item_group'))
                 ->columns(array(
					'id', 'sales_no', 'sales_date', 'location', 'credit', 'customer', 'due_date', 'payment_type', 'account_no', 
					'payment_amount', 'received_amount', 'transaction', 'author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
					'status'=>'status',
			   ))->having(array('year' => $year))
			   ->group('s.id')
	    	   ->order(array('s.id DESC'));
		    if($location!= '-1'){
				$select->where(array('location'=>$location));
			} 
			if($month!= '-1'){
				$select->having(array('month' => $month));
			}
			$select->where($param);
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return records of given year and month
	 * @param Int $id
	 * @return Array
	 */
	public function getDateLocWise($column,$year,$month,$loc,$param)
	{	
		$where = ( is_array($param) )? $param: array('po.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
                 ->columns(array(
					'id', 'sales_no', 'sales_date', 'location', 'credit', 'customer', 'due_date', 'payment_type', 'cheque_no', 
					'payment_amount', 'received_amount', 'transaction', 'status', 'author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ))->having(array('year' => $year, 'month' => $month))
			   ->order(array('id DESC'));
			   $select->where($where);
			  if ($loc != '-1')
			  {
				$select->where(array('location'=>$loc));
			  }
	    	   
		       
		$selectString = $sql->getSqlStringForSqlObject($select);
		echo "<pre>"; print_r($selectString); exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given year and month
	 * @param Int $id
	 * @return Array
	 */
	public function getDateLocCreditWise($column,$year,$month,$loc,$credit,$param,$admin_loc)
	{	
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
                 ->columns(array(
					'id', 'sales_no', 'sales_date', 'location', 'credit', 'customer', 'due_date', 'payment_type', 'cheque_no', 
					'payment_amount', 'received_amount', 'transaction', 'status', 'author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ))->having(array('year' => $year))
			   ->order(array('id DESC'));
			   $select->where($where);
			  if ($loc != '-1')
			  {
				$select->where(array('location'=>$loc));
			  }else{
				$select->where->in('location', $admin_loc);
			  }
			   if ($credit != '-1')
			  {
				$select->where(array('credit'=>$credit));
			  }
	    	  if($month != '-1'){
				$select->having(array('month' => $month));
			  }
		       
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo "<pre>"; print_r($selectString); exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return id's|columns'value  which is not present in given array
	 * @param Array $param
	 * @param String column
	 * @return Array
	 */
	public function getNotIn($param, $column='id', $where=NULL)
	{
		$param = ( is_array($param) )? $param: array($param);
		$where = (is_array($column)) ? $column: $where;
		$column = (is_array($column)) ? 'id' : $column;
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = new Select();
		$select->from($this->table)
		->columns(array('id'))
		->where->notIn($column, $param);
		
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
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
	 * CUSTOMER OUTSTANDING STATEMENT
	 * Return distinct customers 
	 * @param Int | Array
	 * @return Array
	 */
	public function getDistinctCustomers($param,$location,$customer,$start_date)
	{
		$where = ( is_array($param) )? $param: array('s.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
			   ->join(array('p'=>'fa_party'),'p.id = s.customer',array('customer' => 'id','customer_code'=>'name'))
			   ->columns(array(
						'location' => 'location',
						'payment_amount' => new Expression('SUM(s.payment_amount)')
				))
			   ->where($where)
			   ->order(array('s.location ASC','p.name ASC'))
			   ->group (array('p.id'))
			   ->where->lessThanOrEqualTo('sales_date',$start_date);
			   
			if($location != '-1'){
				$select->where(array('s.location'=>$location));
			} 
			if($customer != '-1'){
				$select->where(array('s.customer'=>$customer));
			}  
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}	
    /**
	 * Get customer outstanding
	 * @param Int $id
	 * @param date
	 * @return Array
	 */
	public function getCustomerOutstanding($param,$start_date,$location)
	{
		$where = ( is_array($param) )? $param: array('sd.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
		        ->where($where)
				->where->lessThanOrEqualTo('sales_date',$start_date);
				if($location != '-1'){
					$select->where(array('s.location'=>$location));
				}					
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * SALES STATEMENT REPORT
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getSalesLocation($data, $column) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
				->join(array('l'=>'adm_location'),'s.location = l.id', array('location_name' => 'location'))
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array())
				->join(array('i'=>'st_items'), 'sd.item = i.id', array())
				->columns(array(
				'location' => new Expression('DISTINCT(s.location)')
				))
				->order(array('l.location ASC'))
				->where->between($column, $data['start_date'], $data['end_date']);
			if($data['activity'] != '-1'):
				$select->where(array('i.activity'=>$data['activity']));
			endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;;
	}
	/**
	 * SALES STATEMENT REPORT
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getSalesStatementGross($data, $location, $column, $credit) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array())
				->join(array('i'=>'st_items'), 'sd.item = i.id', array())
				->columns(array(
				'sum' => new Expression('SUM(rate * quantity)')
				))
				->where(array('location'=>$location, 'credit'=>$credit,'s.status'=>'3'))
				->where->between($column, $data['start_date'], $data['end_date']);
			if($data['activity'] != '-1'):
				$select->where(array('activity'=>$data['activity']));
			endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
		
		return $column;
	}
	
	/**
	 * SALES STATEMENT REPORT
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getSalesStatementDis($data, $location, $column, $credit) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array())
				->join(array('i'=>'st_items'), 'sd.item = i.id', array())
				->columns(array(
				'sum' => new Expression('SUM(discount_qty)')
				))
				->where(array('location'=>$location, 'free_item'=>'0', 'free_item_uom'=> '0', 'credit'=>$credit,'s.status'=>'3'))
				->where->between($column, $data['start_date'], $data['end_date']);
			if($data['activity'] != '-1'):
				$select->where(array('activity'=>$data['activity']));
			endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
		
		return $column;;
	}
	/**
	 * PRODUCT WISE SALES REPORT
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getDistinctSupplier($data) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array())
				->join(array('i'=>'st_items'), 'sd.item = i.id', array())
				->join(array('p'=>'fa_party'), 'p.id = i.supplier', array('name'))
				->columns(array(
					'supplier' => new Expression('DISTINCT(i.supplier)')
				))
				->order(array('p.name ASC'))
                                ->where->equalTo('s.status','3')
				->where->between('sales_date', $data['start_date'], $data['end_date']);
			if($data['location'] != '-1'):
				$select->where(array('s.location'=>$data['location']));
			endif;
			if($data['activity'] != '-1'):
				$select->where(array('i.activity'=>$data['activity']));
			endif;
			if($data['payment'] != '-1'):
				$select->where(array('s.credit'=>$data['payment']));
			endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;;
	}
	/**
	 * PRODUCT WISE SALES REPORT
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getDistinctItems($data,$supplier) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array())
				->join(array('i'=>'st_items'), 'sd.item = i.id', array('name','uom'))
				->join(array('p'=>'fa_party'), 'p.id = i.supplier', array())
				->columns(array(
					'item' => new Expression('DISTINCT(sd.item)')
				))
				->where(array('i.supplier'=>$supplier))
				->order(array('i.name ASC'))
				->where->between('sales_date', $data['start_date'], $data['end_date']);
			if($data['location'] != '-1'):
				$select->where(array('s.location'=>$data['location']));
			endif;
			if($data['activity'] != '-1'):
				$select->where(array('i.activity'=>$data['activity']));
			endif;
			if($data['payment'] != '-1'):
				$select->where(array('s.credit'=>$data['payment']));
			endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;;
	}
	/**
	 * PRODUCT WISE SALES REPORT
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getItemSales($data,$supplier,$item) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array('item','uom','rate','quantity','free_item','free_item_uom','discount_qty'))
				->join(array('i'=>'st_items'), 'sd.item = i.id', array())
				->join(array('p'=>'fa_party'), 'p.id = i.supplier', array())
				->where(array('sd.item' => $item))
				->where(array('i.supplier'=>$supplier))
				->where->between('sales_date', $data['start_date'], $data['end_date']);
			if($data['location'] != '-1'):
				$select->where(array('s.location'=>$data['location']));
			endif;
			if($data['activity'] != '-1'):
				$select->where(array('i.activity'=>$data['activity']));
			endif;
			if($data['payment'] != '-1'):
				$select->where(array('s.credit'=>$data['payment']));
			endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;;
	}
	/**
	 * PRODUCT WISE SALES REPORT
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getSuppliersItem($data,$supplier) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array('item','uom','rate','quantity','free_item','free_item_uom','discount_qty'))
				->join(array('i'=>'st_items'), 'sd.item = i.id', array('basic_uom'=> 'uom'))
				->join(array('p'=>'fa_party'), 'p.id = i.supplier', array())
				->where(array('i.supplier'=>$supplier))
                                ->where->equalTo('s.status','3')
				->where->between('sales_date', $data['start_date'], $data['end_date']);
			if($data['location'] != '-1'):
				$select->where(array('s.location'=>$data['location']));
			endif;
			if($data['activity'] != '-1'):
				$select->where(array('i.activity'=>$data['activity']));
			endif;
			if($data['payment'] != '-1'):
				$select->where(array('s.credit'=>$data['payment']));
			endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;;
	}
	/**
	 * PRODUCT WISE SALES REPORT
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getDistinctItemgroups($data) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array())
				->join(array('i'=>'st_items'), 'sd.item = i.id', array())
				->join(array('isg'=>'st_item_subgroup'), 'isg.id = i.item_subgroup', array())
				->join(array('ig'=>'st_item_group'),'ig.id = isg.item_group', array('itemgroup_name'=>'item_group'))
				->columns(array(
					'itemgroup_id' => new Expression('DISTINCT(isg.item_group)')
				))
				->order(array('isg.item_group ASC'))
                                ->where->equalTo('s.status','3')
				->where->between('sales_date', $data['start_date'], $data['end_date']);
			if($data['location'] != '-1'):
				$select->where(array('s.location'=>$data['location']));
			endif;
			if($data['activity'] != '-1'):
				$select->where(array('i.activity'=>$data['activity']));
			endif;
			if($data['payment'] != '-1'):
				$select->where(array('s.credit'=>$data['payment']));
			endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;;
	}
	/**
	 * PRODUCT WISE SALES REPORT
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getGroupItem($data,$item_group) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array('item','uom','rate','quantity','free_item','free_item_uom','discount_qty'))
				->join(array('i'=>'st_items'), 'sd.item = i.id', array('basic_uom'=> 'uom'))
				->join(array('isg'=>'st_item_subgroup'), 'isg.id = i.item_subgroup', array())
				->where(array('isg.item_group'=>$item_group))
                                ->where->equalTo('s.status','3')
				->where->between('sales_date', $data['start_date'], $data['end_date']);
			if($data['location'] != '-1'):
				$select->where(array('s.location'=>$data['location']));
			endif;
			if($data['activity'] != '-1'):
				$select->where(array('i.activity'=>$data['activity']));
			endif;
			if($data['payment'] != '-1'):
				$select->where(array('s.credit'=>$data['payment']));
			endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;;
	}
	/**
	 * PRODUCT WISE SALES REPORT
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getDistinctGroupItems($data,$item_group) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array())
				->join(array('i'=>'st_items'), 'sd.item = i.id', array('name','uom'))
				->join(array('isg'=>'st_item_subgroup'), 'isg.id = i.item_subgroup', array())
				->columns(array(
					'item' => new Expression('DISTINCT(sd.item)')
				))
				->where(array('isg.item_group'=>$item_group))
				->order(array('i.name ASC'))
				->where->between('sales_date', $data['start_date'], $data['end_date']);
			if($data['location'] != '-1'):
				$select->where(array('s.location'=>$data['location']));
			endif;
			if($data['activity'] != '-1'):
				$select->where(array('i.activity'=>$data['activity']));
			endif;
			if($data['payment'] != '-1'):
				$select->where(array('s.credit'=>$data['payment']));
			endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;;
	}
	/**
	 * PRODUCT WISE SALES REPORT
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getItemGroupSales($data,$item_group,$item) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array('item','uom','rate','quantity','free_item','free_item_uom','discount_qty'))
				->join(array('i'=>'st_items'), 'sd.item = i.id', array())
				->join(array('isg'=>'st_item_subgroup'), 'isg.id = i.item_subgroup', array())
				->where(array('sd.item' => $item))
				->where(array('isg.item_group'=>$item_group))
				->where->between('sales_date', $data['start_date'], $data['end_date']);
			if($data['location'] != '-1'):
				$select->where(array('s.location'=>$data['location']));
			endif;
			if($data['activity'] != '-1'):
				$select->where(array('i.activity'=>$data['activity']));
			endif;
			if($data['payment'] != '-1'):
				$select->where(array('s.credit'=>$data['payment']));
			endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;;
	}
	/**
	 * SALES REGISTER
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getSales($data) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array())
				->join(array('i'=>'st_items'), 'sd.item = i.id', array())
				->join(array('l'=>'adm_location'), 'l.id = s.location', array())
				->columns(array(
					'sales_no' => new Expression('DISTINCT(sd.sales)'),
					'sales_date','credit','customer',
				))
				->where(array('s.status'=>'3'))  
				->order(array('s.sales_no ASC'))
				->where->between('sales_date', $data['start_date'], $data['end_date']);
			if($data['region'] != '-1'):
				$select->where(array('l.region'=>$data['region']));
			endif;
			if($data['location'] != '-1'):
				$select->where(array('s.location'=>$data['location']));
			endif;
			if($data['itemgroup'] != '-1'):
				$select->where(array('i.item_group'=>$data['itemgroup']));
			endif;
			if($data['activity'] != '-1'):
				$select->where(array('i.activity'=>$data['activity']));
			endif;
			if($data['payment'] != '-1'):
				$select->where(array('s.credit'=>$data['payment']));
			endif;
			if($data['customer'] != '-1'):
				$select->where(array('s.customer'=>$data['customer']));
			endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;;
	}
	/**
	 * SALES REGISTER
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getSalesDetails($data, $sales_no) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table),array())
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array('sl_item'=>'item','sl_uom'=>'uom','rate','quantity','free_item','free_item_uom','discount_qty'))
				->join(array('i'=>'st_items'), 'sd.item = i.id', array('basic_uom'=>'uom'))
				->join(array('l'=>'adm_location'), 'l.id = s.location', array())
				->where(array('sd.sales' => $sales_no))
				->where->between('sales_date', $data['start_date'], $data['end_date']);
			if($data['region'] != '-1'):
				$select->where(array('l.region'=>$data['region']));
			endif;
			if($data['location'] != '-1'):
				$select->where(array('s.location'=>$data['location']));
			endif;
			if($data['itemgroup'] != '-1'):
				$select->where(array('i.item_group'=>$data['itemgroup']));
			endif;
			if($data['activity'] != '-1'):
				$select->where(array('i.activity'=>$data['activity']));
			endif;
			if($data['payment'] != '-1'):
				$select->where(array('s.credit'=>$data['payment']));
			endif;
			if($data['customer'] != '-1'):
				$select->where(array('s.customer'=>$data['customer']));
			endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;;
	}
	/**
	 * SALES REGISTER
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getSalesItems($data, $sales_no) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table),array())
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array())
				->join(array('i'=>'st_items'), 'sd.item = i.id', array('code'))
				->join(array('l'=>'adm_location'), 'l.id = s.location', array())
				->columns(array(
					'sl_item' => new Expression('DISTINCT(sd.item)'),
				))
				->where(array('sd.sales' => $sales_no))
				->order(array('sd.item ASC'))
				->where->between('sales_date', $data['start_date'], $data['end_date']);
			if($data['region'] != '-1'):
				$select->where(array('l.region'=>$data['region']));
			endif;
			if($data['location'] != '-1'):
				$select->where(array('s.location'=>$data['location']));
			endif;
			if($data['itemgroup'] != '-1'):
				$select->where(array('i.item_group'=>$data['itemgroup']));
			endif;
			if($data['activity'] != '-1'):
				$select->where(array('i.activity'=>$data['activity']));
			endif;
			if($data['payment'] != '-1'):
				$select->where(array('s.credit'=>$data['payment']));
			endif;
			if($data['customer'] != '-1'):
				$select->where(array('s.customer'=>$data['customer']));
			endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;;
	}
	/**
	 * SALES REGISTER
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getSaleItemDtls($data, $sales_no, $sl_item) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table),array())
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array('sl_item'=>'item','sl_uom'=>'uom','rate','quantity','free_item','free_item_uom','discount_qty'))
				->join(array('i'=>'st_items'), 'sd.item = i.id', array('basic_uom'=>'uom'))
				->join(array('l'=>'adm_location'), 'l.id = s.location', array())
				->where(array('sd.sales' => $sales_no,'sd.item'=>$sl_item))
				->where->between('sales_date', $data['start_date'], $data['end_date']);
			if($data['region'] != '-1'):
				$select->where(array('l.region'=>$data['region']));
			endif;
			if($data['location'] != '-1'):
				$select->where(array('s.location'=>$data['location']));
			endif;
			if($data['itemgroup'] != '-1'):
				$select->where(array('i.item_group'=>$data['itemgroup']));
			endif;
			if($data['activity'] != '-1'):
				$select->where(array('i.activity'=>$data['activity']));
			endif;
			if($data['payment'] != '-1'):
				$select->where(array('s.credit'=>$data['payment']));
			endif;
			if($data['customer'] != '-1'):
				$select->where(array('s.customer'=>$data['customer']));
			endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;;
	}
	/**
	 * Contractor Invoice
	 * Return records of given start date and end date
	 * @param Int | Array $param
	 * @param Date $start_date
	 * @param Date $end_date
	 * @return Array
	 */
	public function getByDate($start_date,$end_date,$param)
	{	
		$where = ( is_array($param) )? $param: array('s.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array('item','uom','quantity'))
				->join(array('i'=>'st_items'), 'sd.item = i.id', array('activity','basic_uom'=>'uom','item_code'=>'code'))
				->where($where)
				->order(array('s.id ASC'))
				->where->between('sales_date', $start_date, $end_date);
				
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
	public function getSMOpeningSUM($start_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s' => $this->table))
			   ->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array());
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
		$select->from(array('s' => $this->table))
			   ->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array());
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
	 * GRAPH DATA REPRESENTATION
	 * Return Count value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getGraphData($year, $month, $location)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'received_amount',
				'year' => new Expression('YEAR(sales_date)'),
				'month' => new Expression('MONTH(sales_date)'),
			   ))->having(array('year' => $year,'month' => $month));
		$select->where(array('location' => $location));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$column = 0;
		foreach ($results as $result):
			$column +=  $result['received_amount'];
		endforeach;
	
		return $column;
	}
	/**
	 * QUANTITY * RATE SUM
	 * Return Count value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getSaleAmt($first,$last,$location) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array())
				->join(array('i'=>'st_items'), 'sd.item = i.id', array('name','activity'))
				->columns(array(
					'sum' => new Expression('SUM(rate * quantity)')
				))
				->where(array('s.location'=>$location))
				->order(array('sum DESC'))
				->Limit(5)
				->group(array('sd.item'))
				->where->between('s.sales_date', $first, $last);
				
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	/**
	 * QUANTITY * RATE SUM
	 * Return Count value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getSaleCount($first,$last,$location) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table))
				->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array())
				->join(array('i'=>'st_items'), 'sd.item = i.id', array('name','activity','uom'))
				->columns(array(
					'count' => new Expression('SUM(basic_quantity)')
				))
				->where(array('s.location'=>$location))
				->order(array('count DESC'))
				->Limit(5)
				->group(array('sd.item'))
				->where->between('s.sales_date', $first, $last);
				
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
	public function getDistinctIB($start_date, $end_date, $location, $param, $col_date, $col_loc)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s' => $this->table))
			   ->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array())
			   ->join(array('i'=>'st_items'), 'sd.item = i.id', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(sd.batch)')
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
        //both credit and cash for Stock Recouncilation
	public function getSRQuantitySUM($start_date, $end_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s' => $this->table))
			   ->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array());
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
	public function filterSMBatch($start_date, $end_date, $location, $param, $col_date, $col_loc,$array,$item)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s' => $this->table))
			   ->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array())
			   ->join(array('i'=>'st_items'), 'sd.item = i.id', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(sd.batch)')
			    ));
				$select->where($where);
				$select->where->between($col_date, $start_date, $end_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('sd.item'=>$item));
			    }
				if(sizeof($array)>0):
					$select->where->notIn('sd.batch',$array);
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
		$select->from(array('s' => $this->table))
			   ->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array());
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
		$select->from(array('s' => $this->table))
			   ->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array());
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
	 * SALES RECONCILIATION 
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function reconcileSUM($start_date, $end_date, $location, $param, $col_date, $col_loc)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s' => $this->table))
			   ->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array());
				$select->columns(array(
					'sum' => new Expression('SUM(sd.rate*sd.quantity)')
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
	 * STOCK MOVEMENT 4 - fetch opening sum
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchFIOpeningSUM($start_date, $location, $param, $col_date, $col_loc,$col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s' => $this->table))
			   ->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array());
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
	public function fetchFIQuantitySUM($start_date, $end_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s' => $this->table))
			   ->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array());
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
	 * LOCATION WISE PRODUCT WISE
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getDistinctLocation($data) 
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s'=>$this->table),array())
				->join(array('l'=>'adm_location'), 'l.id = s.location', array('location'=>'location'))
				->columns(array(
					'id' => new Expression('DISTINCT(s.location)'),
				))
				->order(array('l.location ASC'))
				->where->between('sales_date', $data['start_date'], $data['end_date']);
			if($data['payment']!='-1'):
			    $select->where(array('s.credit' => $data['payment']));
			endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo '<pre>';print_r($data); //exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;;
	}
	/**
	 * END OF SESSION RECONCILATION
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function getBooking($sales_date,$location,$credit)
	{   
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('s' => $this->table))
			   ->join(array('sd'=>'sl_sales_dtls'), 's.sales_no = sd.sales', array());
				$select->columns(array(
					'sum' => new Expression('SUM(rate*quantity)')
			    ));
		    	$select->where(array('s.sales_date'=> $sales_date, 's.status' => '3','s.credit'=>$credit));
				if($location != '-1'){
					$select->where(array('s.location' => $location));
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
	public function getSumCurrent($param,$data)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('sl' => $this->table))
			->join(array('sld' => 'sl_sales_dtls'), 'sl.id = sld.sales')
			->columns(array(
				'sum' => new Expression('SUM(' . 'sld.quantity' . ')')
			))
			->where($where)
			->where->between('sl.sales_date', $data['start_date'], $data['end_date']);

		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo($selectString);exit;
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
		public function getSumPre($location,$item, $data)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('sl' => $this->table))
			->join(array('sld' => 'sl_sales_dtls'), 'sl.id = sld.sales')
			->columns(array(
				'sum' => new Expression('SUM(' . 'sld.quantity' . ')')
			))
			->where->lessThan('sl.sales_date', $data['start_date']);

		// Use the where object for the between conditions
		$select->where(array('sl.location' => $location, 'sld.item' => $item,'sl.status'=>4));
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();

		$result = current($results) ?: array('sum' => 0);

		// Return the sum
		return $result['sum'];
	}
}	


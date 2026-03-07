<?php
namespace Accounts\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class TransactionTable extends AbstractTableGateway 
{
	protected $table = 'fa_transaction'; //tablename

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
	    $select->from(array('t'=>$this->table))
				->join(array('j'=>'fa_journal'),'j.id = t.voucher_type', array('voucher_type'=>'journal','voucher_id' => 'id'))
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
		$where = ( is_array($param) )? $param: array('t.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
				->join(array('j'=>'fa_journal'),'j.id = t.voucher_type', array('voucher_type'=>'journal','voucher_id' => 'id'))
		        ->where($where)
		        ->order(array('id DESC'));
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @param date
	 * @return Array
	 */
	public function getBytransactionDate($start_date, $end_date)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->where->between('voucher_date', $start_date, $end_date);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	public function getBytransactionDateType($start_date, $end_date,$param)
	{
		$where = ( is_array($param) )? $param: array('t.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->where($where)
			   ->where->between('voucher_date', $start_date, $end_date);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @param date
	 * @return Array
	 */
	public function getTransactionIdDatewise($start_date, $end_date,$param)
	{
		$where = ( is_array($param) )? $param: array('t.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->where($where)
			    ->where->between('voucher_date', $start_date, $end_date);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @param date
	 * @return Array
	 */
	public function getTransacIdDatewise($start_date, $end_date,$param)
	{
		$where = ( is_array($param) )? $param: array('t.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->where($where)
			    ->where->between('voucher_date', $start_date, $end_date);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/*/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @param date
	 * @return Array
	 */
	public function getVouchersPresent($start_date,$closing_date,$location_id,$head_id)
	{
		//$where = ( is_array($param) )? $param: array('t.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
				->join(array('td'=>'fa_transaction_details'),'t.id = td.transaction', array('location', 'head'))
				->columns(array(new Expression('DISTINCT(t.voucher_type) as voucher_type')))
				->where(array('location'=>$location_id,'head' =>$head_id))
			    ->where->between('voucher_date', $start_date, $closing_date);
			    $select->where(array('t.status'=>'4'));//added 4 instead of 3
			    $select->order(array('voucher_date ASC'));
			    $select->order(array('t.id ASC'));
		  	    $select->order(array('t.created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit; 
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * GET DISTINCT TRANSACTION ID FOR CASH
	 * Return records of given condition array | given id
	 * @param Start_date
	 * @param Int $id
	 * @return Array
	*/
        /** GET DISTINCT TRANSACTION ID 
	 * Return records of given condition array | given id
	 * @param Start_date
	 * @param Int $id
	 * @return Array
	*/ 
	public function getDisinctTransactionBAID($bank_account,$start_date,$end_date)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
			   ->join(array('td'=>'fa_transaction_details'),'t.id = td.transaction', array())
			   ->columns(array(new Expression('DISTINCT(t.id) as transaction_id')))
			   ->where(array('sub_head'=>$bank_account))
			   ->where->between('voucher_date', $start_date,$end_date);
		$select->where(array('t.status'=>'4'));//added 4 instead of 3
		$select->order(array('voucher_date ASC'));
		$select->order(array('t.id ASC'));
		$select->order(array('t.created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString; exit; 
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	} 
	public function getDisinctTID($voucher_type,$opening_date,$closing_date,$location_id,$head_id)
	{
		//$where = ( is_array($param) )? $param: array('t.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
			->join(array('td'=>'fa_transaction_details'),'t.id = td.transaction', array())
			->columns(array(new Expression('DISTINCT(t.id) as transaction_id')))
			->where(array('location'=>$location_id, 'head'=>$head_id,'voucher_type'=>$voucher_type))
			->where->between('voucher_date', $opening_date, $closing_date);
			 $select->where(array('t.status'=>'4'));//added 4 instead of 3
			$select->order(array('voucher_date ASC'));
			$select->order(array('t.id ASC'));
			$select->order(array('t.created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit; 
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
        /** GET DISTINCT TRANSACTION ID FOR BANK
	 * Return records of given condition array | given id
	 * @param Start_date
	 * @param Int $id
	 * @return Array
	*/ 
	public function getDisinctTransactionID($opening_date,$closing_date,$subhead_id)
	{
		$where = ( is_array($param) )? $param: array('t.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
			->join(array('td'=>'fa_transaction_details'),'t.id = td.transaction', array())
			->columns(array(new Expression('DISTINCT(t.id) as transaction_id')))
			->where(array('sub_head'=>$subhead_id))
			->where->between('voucher_date', $opening_date,$closing_date);
			$select->where(array('t.status'=>'4'));//added 4 instead of 3
			$select->order(array('voucher_date ASC'));
			$select->order(array('t.id ASC'));
			$select->order(array('t.created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString; exit; 
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Start_date
	 * @param Int $id
	 * @return Array
	*/ 
	public function getTransacId($start_date, $end_date,$location_id)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
			->join(array('td'=>'fa_transaction_details'),'t.id = td.transaction', array())
			->columns(array(new Expression('DISTINCT(t.id) as transaction_id')))
			->where->between('voucher_date', $start_date, $end_date);
			if($location_id != '-1'){
				$select->where(array('location'=>$location_id));
			}
			$select->where(array('t.status'=>'4'));//added 4 instead of 3
			$select->order(array('voucher_date ASC'));
			$select->order(array('t.id ASC'));
			$select->order(array('t.created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit; 
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * 
	 * get transaction id present in transactions details
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getTransactionIDforLedger($location,$activity,$start_date,$end_date,$where)
	{		
		//extract($start_date,$end_date);	
		
		$sub1 = new Select("fa_transaction_details");
		$sub1->columns(array("transaction"));
		$sub1->where($where);
		if($location != -1):
			$sub1->where(array("location" => $location));
		endif;
		if($activity != -1):
			$sub1->where(array("activity" => $activity));
		endif;
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
               ->where(array("status" => "4"))	//added 4 instead of 3	
               ->where->between('voucher_date',$start_date,$end_date);
		$select->where->in('id', $sub1);
		$select->order(array('voucher_date ASC'));
		$select->order(array('id ASC'));
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
	public function getMin($column, $where = NULL )
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'min' => new Expression('MIN('.$column.')'),
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
	public function getMaxRow($column,$param)
	{
		$where = ( is_array($param) )? $param: array('t.id' => $param);
		$adapter = $this->adapter;
		
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MAX('.$column.')')
		));
		//$sub0 = $sub0->toArray();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
				->join(array('j'=>'fa_journal'),'j.id = t.voucher_type', array('voucher_type'=>'journal','vourcher_id' => 'id'))
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
		$where = ( is_array($param) )? $param: array('t.id' => $param);
		$adapter = $this->adapter;
	
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MIN('.$column.')')
		));
		//$sub0 = $sub0->toArray();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
				->join(array('j'=>'fa_journal'),'j.id = t.voucher_type', array('voucher_type'=>'journal','vourcher_id' => 'id'))
		->where($where)
		->where($column);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return latest serail of given date array
	 * @param Int $year
	 * @return Int
	 */
	public function getSerial($prefix_PO_code)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->where->like('voucher_no', $prefix_PO_code."%");
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit; 
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		return  $results;
	}
	
    /**
	 * Return records of given year and month
	 * @param Int $id
	 * @return Array
	 */
	public function getDateWise($column,$year,$month,$date,$user_region)
	{	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
				->join(array('j'=>'fa_journal'),'j.id = t.voucher_type', array('voucher_type'=>'journal','voucher_id' => 'id'))
                 ->columns(array(
					'id','voucher_date','voucher_type','doc_id','doc_type','voucher_no','voucher_amount','remark','region','against','reconcile_status',
					'status','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
					'date' => new Expression('DAY('.$column.')'),
			   ))->having(array('year' => $year))
	    	   ->order(array('id DESC'));
		if($user_region != '-1'){
			$select->having(array('t.region' => $user_region));
		}
		if($month != '-1'){
			$select->having(array('month' => $month));
		}
		if($date != '-1'){
			$select->having(array('date' => $date));
		}
		       
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	 /**
	 * Return records of given year and month
	 * @param Int $id
	 * @return Array
	 */
	public function getDateWiseStatus($column,$year,$month,$date,$status,$user_region)
	{	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
				->join(array('j'=>'fa_journal'),'j.id = t.voucher_type', array('voucher_type'=>'journal','voucher_id' => 'id'))
                 ->columns(array(
					'id','voucher_date','voucher_type','doc_id','doc_type','voucher_no','voucher_amount','remark','region','against','reconcile_status',
					'status','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
					'date' => new Expression('DAY('.$column.')'),
			   ))->having(array('year' => $year))
	    	   ->order(array('id DESC'));
		if($user_region != '-1'){
			$select->having(array('t.region' => $user_region));
		}
		if($month != '-1'){
			$select->having(array('month' => $month));
		}
		if($date != '-1'){
			$select->having(array('date' => $date));
		}
		if($status != '-1'){
			$select->having(array('status' => $status));
		}
		       
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given year and month
	 * @param Int $id
	 * @return Array
	 */
	public function getDateWiseA($column,$year,$month,$date)
	{	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
				->join(array('j'=>'fa_journal'),'j.id = t.voucher_type', array('voucher_type'=>'journal','voucher_id' => 'id'))
                 ->columns(array(
					'id','voucher_date','voucher_type','doc_id','doc_type','voucher_no','voucher_amount','remark','region','against','reconcile_status',
					'status','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
					'date' => new Expression('DAY('.$column.')'),
			   ))->having(array('year' => $year))
	    	   ->order(array('id DESC'));
		if($month != '-1'){
			$select->having(array('month' => $month));
		}
		if($date != '-1'){
			$select->having(array('date' => $date));
		}
		       
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given year and month
	 * @param Int $id
	 * @return Array
	 */
	public function getvoucherwise($column,$year,$month,$date,$user_region,$voucher)
	{	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
				->join(array('j'=>'fa_journal'),'j.id = t.voucher_type', array('voucher_type'=>'journal','voucher_id' => 'id'))
                 ->columns(array(
					'id','voucher_date','voucher_type','doc_id','doc_type','voucher_no','voucher_amount','remark','region','against','against_vid',
					'status','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
					'date' => new Expression('DAY('.$column.')'),
			   ))->having(array('year' => $year))
	    	   ->order(array('id DESC'));
		if($user_region != '-1'){
			$select->having(array('t.region' => $user_region));
		}
		if($voucher != ''){
			$select->having(array('t.against_vid' => $voucher));
		}
		if($month != '-1'){
			$select->having(array('month' => $month));
		}
		if($date != '-1'){
			$select->having(array('date' => $date)); 
		}  
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	//viewrent action - work of real estate 
	public function getMonthWiseData($column,$year,$month)
	{	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();    
		$select->from(array('t'=>$this->table))
				->join(array('td'=>'fa_transaction_details'),'t.id = td.transaction', array('head','debit','credit'))
				//->join(array('j'=>'fa_journal'),'j.id = t.voucher_type', array('voucher_type'=>'journal','voucher_id' => 'id'))
				->join(array('sh'=>'fa_sub_head'),'sh.id = td.sub_head', array('code'))
                 ->columns(array(
					'id','voucher_date','voucher_type','doc_id','doc_type','voucher_no','voucher_amount','remark',
					'status','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ))
			   ->having(array('year' => $year,'month' => $month))
			   //->having(array('month' => $month))
	    	   ->order(array('code ASC'));
		$select->where->equalTo('td.head',222);
		$select->where->equalTo('t.voucher_type',4);//added 4 instead of 3
		       
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * VOUCHER CHECKING REPORT
	 * Return records of given condition array | given id
	 * @param $data
	 * @return Array
	*/ 
	public function getVoucherChecking($data)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
			->join(array('td'=>'fa_transaction_details'),'t.id = td.transaction', array('location'));
			$select->columns(array(
					'id' => new Expression('Distinct(t.id)'),
					'voucher_date','voucher_type','voucher_no',
					'sum_debit' => new Expression('SUM(debit)'),
					'sum_credit' => new Expression('SUM(credit)'),
					'location_concat' => new Expression('GROUP_CONCAT(location)'),
				))->group('transaction');
			$select->where->between('voucher_date', $data['start_date'], $data['end_date']);
			$select->where(array('t.status'=>'4'));//added 4 instead of 3
			$select->order(array('voucher_date ASC','t.id ASC','t.created ASC'));
			if($data['journal'] != '-1'){
				$select->having(array('voucher_type' => $data['journal']));
			}
			if($data['location'] != '-1'){
				$select->having(array('location' => $data['location']));
			} 
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit; 
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
}


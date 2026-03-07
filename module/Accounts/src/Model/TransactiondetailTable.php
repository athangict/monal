<?php
namespace Accounts\Model;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class TransactiondetailTable extends AbstractTableGateway 
{
	protected $table = 'fa_transaction_details'; 

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
	    $select->from(array('td'=>$this->table))
			   ->join(array('t'=>'fa_transaction'),'t.id = td.transaction', array('transaction_id' => 'id'))
			   ->join(array('l'=>'adm_location'), 'l.id = td.location', array('location', 'location_id' => 'id'))
		       ->join(array('a'=>'adm_activity'), 'a.id = td.activity', array('activity', 'activity_id' => 'id'))
		       ->join(array('h'=>'fa_head'), 'h.id = td.head', array('head' => 'code', 'head_id' => 'id'))
			   ->join(array('sh'=>'fa_sub_head'), 'sh.id = td.sub_head', array('sub_head' => 'code', 'sub_head_id' => 'id'));
	    
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
		$where = ( is_array($param) )? $param: array('td.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select->from(array('td'=>$this->table))
			   ->join(array('t'=>'fa_transaction'),'t.id = td.transaction', array('transaction_id' => 'id', 'voucher_type'))
			   ->join(array('l'=>'adm_location'), 'l.id = td.location', array('location', 'location_id' => 'id'))
			   //->join(array('a'=>'adm_activity'), 'a.id = td.activity', array('activity', 'activity_id' => 'id'))
			   ->join(array('h'=>'fa_head'), 'h.id = td.head', array('head' => 'code', 'head_id' => 'id'))
			   ->join(array('sh'=>'fa_sub_head'), 'sh.id = td.sub_head', array('sub_head' => 'code', 'sub_head_name' => 'name','sub_head_id' => 'id'))
		       ->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getTDS($data)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select->from(array('td'=>$this->table))
			   ->join(array('t'=>'fa_transaction'),'t.id = td.transaction', array('transaction_id' => 'id', 'voucher_type'))
			   ->join(array('l'=>'adm_location'), 'l.id = td.location', array('location', 'location_id' => 'id'))
			   //->join(array('a'=>'adm_activity'), 'a.id = td.activity', array('activity', 'activity_id' => 'id'))
			   ->join(array('h'=>'fa_head'), 'h.id = td.head', array('head' => 'code', 'head_id' => 'id'))
			   ->join(array('sh'=>'fa_sub_head'), 'sh.id = td.sub_head', array('sub_head' => 'code', 'sub_head_name' => 'name','sub_head_id' => 'id'));
	    $select->where(array("sub_head" =>$data['subhead'],"td.voucher_types"=>12))
			   ->where->between('voucher_dates',$data['start_date'],$data['end_date']);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getTDSALL($data)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select->from(array('td'=>$this->table))
			   ->join(array('t'=>'fa_transaction'),'t.id = td.transaction', array('transaction_id' => 'id', 'voucher_type'))
			   ->join(array('l'=>'adm_location'), 'l.id = td.location', array('location', 'location_id' => 'id'))
			   //->join(array('a'=>'adm_activity'), 'a.id = td.activity', array('activity', 'activity_id' => 'id'))
			   ->join(array('h'=>'fa_head'), 'h.id = td.head', array('head' => 'code', 'head_id' => 'id'))
			   ->join(array('sh'=>'fa_sub_head'), 'sh.id = td.sub_head', array('sub_head' => 'code', 'sub_head_name' => 'name','sub_head_id' => 'id'));
	    $select->where(array("sub_head" =>$data['subhead']))
			   ->where->between('voucher_dates',$data['start_date'],$data['end_date']);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getforreconcile($data)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select->from(array('td'=>$this->table))
			   ->join(array('sh'=>'fa_sub_head'),'sh.id = td.sub_head', array('subhead_id' => 'id'));
		$select->where->between('voucher_dates',$data['start_date'],$data['end_date']);
		if($data['bank'] != '-1'):
			$select->where(array("sub_head" => $data['bank']));
		else:
		   $select->where(array("sh.type" => 3));
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getbyhead($ledger, $subledger,$start_date,$end_date)
	{
		//$where = ( is_array($param) )? $param: array('td.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select->from($this->table);
		$select->where(array("status" => "4"))//committed status
			   ->where->between('voucher_dates', $start_date, $end_date);
		if($ledger != -1):
			$select->where(array("head" => $ledger));
		else:
		    $select->where(array("head" => array(105,143)));
		endif;
		if($subledger != -1):
			$select->where(array("sub_head" => $subledger));
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * get bank account based on location
	 * @param Int $id
	 * @return Array
	 */
	public function gettransactiondtls($sub_head,$location)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
		       ->where(array('sub_head'=>$sub_head))
			   ->where(array('location'=>$location))
			   ->where(array('reconcile'=>0));
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
    /*
	*Type wise record 1-manual, 2-system input
	*if 2, TDS-head(236) is excluded statistically with debit > 0
	*if 1, credit > 0 are taken
	*/
	public function getTypeWise($param,$type)
	{
		$where = ( is_array($param) )? $param: array('td.id' => $param);
		$type = array($type);
		$head = array(236);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select->from(array('td'=>$this->table))
				->join(array('t'=>'fa_transaction'),'t.id = td.transaction', array('transaction_id' => 'id', 'voucher_type'))
				->join(array('l'=>'adm_location'), 'l.id = td.location', array('location', 'location_id' => 'id'))
				->join(array('a'=>'adm_activity'), 'a.id = td.activity', array('activity', 'activity_id' => 'id'))
				->join(array('h'=>'fa_head'), 'h.id = td.head', array('head' => 'code', 'head_id' => 'id'))
				->join(array('sh'=>'fa_sub_head'), 'sh.id = td.sub_head', array('sub_head' => 'code', 'sub_head_id' => 'id'))
		        ->where($where);
		if($type['0'] == '2'){
			$select->where->greaterThan('td.debit','0')
				   ->where->notIn('td.head',$head);
		}else{
			$select->where->greaterThan('td.credit','0');
		}
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
		$columns='';
		foreach ($results as $result):
			$columns =  $result[$column];
		endforeach; 
		return $columns;       
    }
 
	public function getLocation($param)
	{
		$where = ( is_array($param) )? $param: array('td.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select->from(array('td'=>$this->table))
			   ->join(array('l'=>'adm_location'), 'l.id = td.location', array('location', 'location_id' => 'id'))
		       ->where($where);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
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
     *  @param Int $id
     *  @return true | false
     */
	public function remove($id)
	{
		return $this->delete(array('id' => $id));
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
		$where = ( is_array($param) )? $param: array('td.id' => $param);
		$adapter = $this->adapter;
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MAX('.$column.')')
		));
		//$sub0 = $sub0->toArray();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('td'=>$this->table))
			   ->join(array('t'=>'fa_transaction'),'t.id = td.transaction', array('transaction_id' => 'id'))
			   ->join(array('l'=>'adm_location'), 'l.id = td.location', array('location', 'location_id' => 'id'))
			   ->join(array('a'=>'adm_activity'), 'a.id = td.activity', array('activity', 'activity_id' => 'id'))
			   ->join(array('h'=>'fa_head'), 'h.id = td.head', array('head' => 'code', 'head_id' => 'id'))
			   ->join(array('sh'=>'fa_sub_head'), 'sh.id = td.sub_head', array('sub_head' => 'code', 'sub_head_id' => 'id'))
			   ->join(array('brt'=>'fa_bank_ref_type'), 'brt.id = td.bank_ref_type', array('bank_ref_type', 'bank_ref_type_id' => 'id'))
			   ->where($where)
			   ->where($column);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return records of given condition array
	 * @param Stirng $column
	 * @param Int $param
	 * @return Array
	 */
	public function getMinRow($column,$param)
	{
		$where = ( is_array($param) )? $param: array('td.id' => $param);
		$adapter = $this->adapter;
	
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MIN('.$column.')')
		));
		//$sub0 = $sub0->toArray();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('td'=>$this->table))
			   ->join(array('t'=>'fa_transaction'),'t.id = td.transaction', array('transaction_id' => 'id'))
			   ->join(array('l'=>'adm_location'), 'l.id = td.location', array('location', 'location_id' => 'id'))
			   ->join(array('a'=>'adm_activity'), 'a.id = td.activity', array('activity', 'activity_id' => 'id'))
			   ->join(array('h'=>'fa_head'), 'h.id = td.head', array('head' => 'code', 'head_id' => 'id'))
			   ->join(array('sh'=>'fa_sub_head'), 'sh.id = td.sub_head', array('sub_head' => 'code', 'sub_head_id' => 'id'))
			   ->join(array('brt'=>'fa_bank_ref_type'), 'brt.id = td.bank_ref_type', array('bank_ref_type', 'bank_ref_type_id' => 'id'))
		->where($where)
		->where($column);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return id's|columns'value  which is not present in given array
	 * @param Array $param
	 * @param String column
	 * @return Array
	 */
	public function getNotIn($param, $column='id')
	{
		$param = ( is_array($param) )? $param: array($param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = new Select();
		$select->from($this->table)
			   ->columns(array('id'))
			   ->where(array('type'=>1))// type = 1 meaning usere inputted data
			   ->where->notIn($column, $param);
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
    /**
	 * Return id's|columns'value  which is not present in given array
	 * @param Array $param
	 * @param String column
	 * @return Array
	 */
	public function getNotInDtl($param, $column='id', $where=NULL)
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
		if ($where != Null)
		{
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
    /** xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-----------UPDATE QUERY FOR REPORT-------------xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx */
	/**NEW UPDATE FOR TRIAL BAANCE---------------------------------------------------------------------------------------------------------- */
    /**
	 * get sum by class
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param int $class
	 * @return int
	 */
	public function getSumbyClass($activity,$region,$location,$start_date,$end_date, $column, $class)
	{	
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $start_date, $end_date);
		$sub1 = new Select("fa_group");
		$sub1->columns(array("id"))
			->where(array("class" => $class));
		
		$sub2 = new Select("fa_head");
		$sub2->columns(array("id"))
			->where->in("group", $sub1);	   
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->where->in('head', $sub2);
		$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by group
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSumbyGroup($activity,$region,$location,$start_date,$end_date, $column, $group)
	{		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $start_date, $end_date);
		$sub1 = new Select("fa_head");
		$sub1->columns(array("id"))
			 ->where(array("group"=>$group));

		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->where->in('head', $sub1);
		$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; //exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}   
	/**
	 * get sum by head
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $head
	 * @return int
	 */
	public function getSumbyHead($activity,$region,$location,$start_date,$end_date, $column, $head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
		       ->join(array('h'=>'fa_head'), 'h.id=t.head', array('head'=>'name', 'head_id'=>'id'));
		$select->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("h.id" => $head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $start_date, $end_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		//$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
	    $selectString ; //exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
		 // $htype =  $result['name'];
	   endforeach;  
	   return $sum;
	}
	/**
	 * get sum by head
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $head
	 * @return int
	 */
	public function getSumbyHeads($activity,$region,$location,$start_date,$end_date, $column, $head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
		       ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("head" => $head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $start_date, $end_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		//$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
	    $selectString ; //exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
		 // $htype =  $result['name'];
	   endforeach;  
	   return $sum;
	}
	/**
	 * get sum by subhead
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbySubhead($activity,$region,$location,$start_date,$end_date, $column, $sub_head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("sub_head" => $sub_head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $start_date, $end_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**GET OPENING BALANCE FOR TRIAL BALANCe------------------------------------------------------------------------------------ */
	/**
	 * Calculate opening balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getOpeningBalance($activity,$region,$location,$start_date,$end_date, $id, $tier)
	{	 		
		if($tier == 1):
			$total_debit = $this->getSumbySubheadTBOpening($activity,$region,$location,$start_date, 'debit', $id);
			$total_credit = $this->getSumbySubheadTBOpening($activity,$region,$location,$start_date, 'credit', $id);  			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHeadTBOpening($activity,$region,$location,$start_date, 'debit', $id);
			$total_credit = $this->getSumbyHeadTBOpening($activity,$region,$location,$start_date, 'credit', $id);	
		elseif($tier == 3):
			$total_debit = $this->getSumbyGroupTBOpening($activity,$region,$location,$start_date, 'debit', $id);
			$total_credit = $this->getSumbyGroupTBOpening($activity,$region,$location,$start_date, 'credit', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClassTBOpening($activity,$region,$location,$start_date, 'debit', $id);
			$total_credit = $this->getSumbyClassTBOpening($activity,$region,$location,$start_date, 'credit', $id);
		endif;
		return  $total_debit - $total_credit;		
	}
	/**
	 * get sum by subhead TBO
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbySubheadTBOpening($activity,$region,$location,$start_date,$column, $sub_head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("sub_head" => $sub_head))
			   ->where(array("status" => "4")); //committed status
		$select->where->lessThan('voucher_dates', $start_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	
	/**
	 * get sum by head TBO
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $head
	 * @return int
	 */
	public function getSumbyHeadTBOpening($activity,$region,$location,$start_date, $column, $head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("head" => $head))
			   ->where(array("status" => "4")) //committed status
			   ->where->lessThan('voucher_dates', $start_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
        $select->order(array('transaction ASC'));
		$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString ; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;
	}
	/**
	 * get sum by group TBO
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSumbyGroupTBOpening($activity,$region,$location,$start_date, $column, $group)
	{	
		$sub1 = new Select("fa_head");
		$sub1->columns(array("id"))
			 ->where(array("group"=>$group));
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("status" => "4")) //committed status
			   ->where->lessThan('voucher_dates', $start_date);
			   
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->where->in('head', $sub1);
        $select->order(array('transaction ASC'));
		$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by class for TBO
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param int $class
	 * @return int
	 */
	public function getSumbyClassTBOpening($activity,$region,$location,$start_date, $column, $class)
	{		
		$sub1 = new Select("fa_group");
		$sub1->columns(array("id"))
			 ->where(array("class" => $class));
		
		$sub2 = new Select("fa_head");
		$sub2->columns(array("id"))
			 ->where->in("group", $sub1);
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("status" => "4")) //committed status
			   ->where->lessThan('voucher_dates', $start_date);
			   
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->where->in('head', $sub2);
        $select->order(array('transaction ASC'));
		$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**GET CLOSING BALANCE FOR TRIAL BALANCE FROM ASSET & LAIBILITIES----------------------------------------------------------------------------------------------- */
	/**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceAL($activity,$region,$location,$start_date,$end_date, $id, $tier)
	{		
		//extract($options);
		
		$adapter = $this->adapter;  
		$sql = new Sql($adapter);
		
		$year = date('Y', strtotime($start_date));
		$year1 = date('Y', strtotime($start_date)) - 10;
        $starting_date = date('Y-m-d',strtotime('01-01-'.$year1));
		$ending_date = $end_date;
		//echo $starting_date;
		if($tier == 1):
			$total_debit = $this->getSumbySubhead($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbySubhead($activity,$region,$location,$starting_date,$ending_date, 'credit', $id); 			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHead($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyHead($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);		
		elseif($tier == 3):
			$total_debit = $this->getSumbyGroup($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyGroup($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClass($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyClass($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		endif;
		return  $total_debit - $total_credit;	
	}
	/** GET CLOSING BALANCE FOR TRIAL BALANCE FROM INCOME & EXPENSES--------------------------------------------------------------------------------------------------*/
	/**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceIE($activity,$region,$location,$start_date,$end_date, $id, $tier)
	{		
		//extract($options);
		
		$adapter = $this->adapter;  
		$sql = new Sql($adapter);
		
		$year = date('Y', strtotime($start_date));
        $starting_date = date('Y-m-d',strtotime($start_date));
		$ending_date = $end_date;
		//echo $tier; exit;
		if($tier == 1):
			$total_debit = $this->getSumbySubhead($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbySubhead($activity,$region,$location,$starting_date,$ending_date, 'credit', $id); 			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHead($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyHead($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);		
		elseif($tier == 3):
			$total_debit = $this->getSumbyGroup($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyGroup($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClass($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyClass($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		endif;
		return $total_debit - $total_credit;	
	}
	/*--------------------------For Remapping closing----------------------------*/
	/**GET CLOSING BALANCE FOR TRIAL BALANCE FROM ASSET & LAIBILITIES----------------------------------------------------------------------------------------------- */
	/**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceALS($activity,$region,$location,$start_date,$end_date, $id, $tier)
	{		
		//extract($options);
		
		$adapter = $this->adapter;  
		$sql = new Sql($adapter);
		
		$year = date('Y', strtotime($start_date));
		$year1 = date('Y', strtotime($start_date)) - 10;
        $starting_date = date('Y-m-d',strtotime('01-01-'.$year1));
		$ending_date = $end_date;
		//echo $starting_date;
		if($tier == 1):
			$total_debit = $this->getSumbySubhead($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbySubhead($activity,$region,$location,$starting_date,$ending_date, 'credit', $id); 			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHeads($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyHeads($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);		
		elseif($tier == 3):
			$total_debit = $this->getSumbyGroup($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyGroup($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClass($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyClass($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		endif;
		return  $total_debit - $total_credit;	
	}
	/** GET CLOSING BALANCE FOR TRIAL BALANCE FROM INCOME & EXPENSES--------------------------------------------------------------------------------------------------*/
	/**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceIES($activity,$region,$location,$start_date,$end_date, $id, $tier)
	{		
		//extract($options);
		
		$adapter = $this->adapter;  
		$sql = new Sql($adapter);
		
		$year = date('Y', strtotime($start_date));
        $starting_date = date('Y-m-d',strtotime($start_date));
		$ending_date = $end_date;
		//echo $tier; exit;
		if($tier == 1):
			$total_debit = $this->getSumbySubhead($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbySubhead($activity,$region,$location,$starting_date,$ending_date, 'credit', $id); 			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHeads($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyHeads($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);		
		elseif($tier == 3):
			$total_debit = $this->getSumbyGroup($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyGroup($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClass($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyClass($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		endif;
		return $total_debit - $total_credit;	
	}
	/**END OF THE TRIAL BALANCE xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx 
	 * CHECK CLOSING BALANCE OF AL AND IE BECAUSE THERE IS NO DIFFERENCE B/W THEM
	*/

	/**START OF THE BALANCE SHEET-------------------------------------------------------------------------------------------------------- */
	/**CLOSING BALANCE FOR BALANCE SHEET PRESENT----------------------------------------------------------------------------------------- */
	/**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceforPresBS($activity,$region,$location,$starting_date,$ending_date, $id,$class_id,$tier)
	{	
		$starting_date = $starting_date;
		$ending_date = $ending_date;
        $adapter = $this->adapter;  
		$sql = new Sql($adapter);
		$year = date('Y', strtotime($starting_date));
		$year1 = date('Y', strtotime($starting_date)) - 8;
        $starting_date = date('Y-m-d',strtotime('01-01-'.$year1));
		$ending_date = $ending_date;
		if($tier == 1):
			$total_debit = $this->getSumbySubheadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbySubheadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);  			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHeadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyHeadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);			
		elseif($tier == 3):
     		$total_debit = $this->getSumbyGroupforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyGroupforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClassforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyClassforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		endif;
		if($class_id=='1'){
			return $total_debit - $total_credit;	
		}else{
			return $total_credit - $total_debit;
		}					
		}
		/**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceforPresBSCLASS($activity,$region,$location,$starting_date,$ending_date, $id,$tier)
	{	
		$starting_date = $starting_date;
		$ending_date = $ending_date;
        $adapter = $this->adapter;  
		$sql = new Sql($adapter);
		$year = date('Y', strtotime($starting_date));
		$year1 = date('Y', strtotime($starting_date)) - 8;
        $starting_date = date('Y-m-d',strtotime('01-01-'.$year1));
		$ending_date = $ending_date;
		if($tier == 1):
			$total_debit = $this->getSumbySubheadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbySubheadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);  			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHeadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyHeadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);			
		elseif($tier == 3):
     		$total_debit = $this->getSumbyGroupforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyGroupforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClassforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyClassforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		endif;
		return $total_debit - $total_credit;	
		
		}
	/**
	 * get sum by subhead
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbySubheadforPresBS($activity,$region,$location,$starting_date,$ending_date, $column, $sub_head)
	{	
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("sub_head" => $sub_head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit; 
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by head
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $head
	 * @return int
	 */
	public function getSumbyHeadforPresBS($activity,$region,$location,$starting_date,$ending_date,$column, $head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
		       ->join(array('h'=>'fa_head'), 'h.id=t.head', array('head'=>'name', 'head_id'=>'id'));
		$select->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("h.id" => $head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		// $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by group
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSumbyGroupforPresBS($activity,$region,$location,$starting_date,$ending_date, $column, $group)
	{		
		$sub1 = new Select("fa_head");
		$sub1->columns(array("id"))
			 ->where(array("group"=>$group));
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
			   
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->where->in('head', $sub1);

		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/* get sum by class
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param int $class
	 * @return int
	 */
	public function getSumbyClassforPresBS($activity,$region,$location,$starting_date,$ending_date, $column, $class)
	{		
		
		$sub1 = new Select("fa_group");
		$sub1->columns(array("id"))
			 ->where(array("class" => $class));
		
		$sub2 = new Select("fa_head");
		$sub2->columns(array("id"))
			 ->where->in("group", $sub1);
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date,$ending_date);
			   
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->where->in('head', $sub2);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit; 
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**GET CLOSING BALANCE FOR BALANCESHEET PREVIOUS------------------------------------------------------------------------------------ */
    /**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceforPrevBS($activity,$region,$location,$starting_date,$ending_date, $id, $tier)
	{	
		$starting_date = $starting_date;
		$ending_date = $ending_date;
		$adapter = $this->adapter;  
		$sql = new Sql($adapter);
		$year = date('Y', strtotime($starting_date));
		$year1 = date('Y', strtotime($starting_date)) - 10;
        $starting_date = date('Y-m-d',strtotime('01-01-'.$year1));
		$ending_date = $ending_date;
		if($tier == 1):
			$total_debit = $this->getSumbySubheadforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbySubheadforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);  			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHeadforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyHeadforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);			
		elseif($tier == 3):
     		$total_debit = $this->getSumbyGroupforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyGroupforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClassforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyClassforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		endif;
		return $total_debit - $total_credit;			
	}
	 /**
	 * get sum by subhead
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbySubheadforPrevBS($activity,$region,$location,$starting_date,$ending_date, $column, $sub_head)
	{
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("sub_head" => $sub_head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by head
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $head
	 * @return int
	 */
	public function getSumbyHeadforPrevBS($activity,$region,$location,$starting_date,$ending_date,$column, $head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		 $select->from(array('t'=>$this->table))
		       ->join(array('h'=>'fa_head'), 'h.id=t.head', array('head'=>'name', 'head_id'=>'id'));
		$select->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("h.id" => $head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;

		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	 /**
	 * get sum by group
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSumbyGroupforPrevBS($activity,$region,$location,$starting_date,$ending_date, $column, $group)
	{		 
		$sub1 = new Select("fa_head");
		$sub1->columns(array("id"))
			 ->where(array("group"=>$group));
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
			   
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->where->in('head', $sub1);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by class
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param int $class
	 * @return int
	 */
	public function getSumbyClassforPrevBS($activity,$region,$location,$starting_date,$ending_date, $column, $class)
	{		
		$sub1 = new Select("fa_group");
		$sub1->columns(array("id"))
			 ->where(array("class" => $class));
		
		$sub2 = new Select("fa_head");
		$sub2->columns(array("id"))
			 ->where->in("group", $sub1);
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date,$ending_date);
			   
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->where->in('head', $sub2);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit; 
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**BALANCE SHEET-------------------------------------------------------------------------------------------------------- */
	/**Heads for subhead----------------------------------------------------------------------------------------- */
	/**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceforPresBSS($activity,$region,$location,$starting_date,$ending_date, $id, $tier)
	{	
		$starting_date = $starting_date;
		$ending_date = $ending_date;
        $adapter = $this->adapter;  
		$sql = new Sql($adapter);
		$year = date('Y', strtotime($starting_date));
		$year1 = date('Y', strtotime($starting_date)) - 10;
        $starting_date = date('Y-m-d',strtotime('01-01-'.$year1));
		$ending_date = $ending_date;
		if($tier == 1):
			$total_debit = $this->getSumbySubheadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbySubheadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);  			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHeadforPresBSS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyHeadforPresBSS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);			
		elseif($tier == 3):
     		$total_debit = $this->getSumbyGroupforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyGroupforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClassforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyClassforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		endif;
		return $total_debit - $total_credit;				
	}
		/**
	 * get sum by head
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $head
	 * @return int
	 */
	public function getSumbyHeadforPresBSS($activity,$region,$location,$starting_date,$ending_date,$column, $head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table));
		$select->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("head" => $head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		// $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**END xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx*/

	/**START PROFIT LOSS STATEMENT----------------------------------------------------------------------------------------------- */
	/**GET CLOSING BALANCE FOR PROFIT LOSS PRESENT----------------------------------------------------------------------------------------------- */
	/**GET CLOSING BALANCE FOR PROFIT LOSS PRESENT----------------------------------------------------------------------------------------------- */
	/**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceforPresPLS($activity,$region,$location,$starting_date,$ending_date, $id, $tier)
	{	
		$starting_date = $starting_date;
		$ending_date = $ending_date;
		if($tier == 1):
			$total_debit = $this->getSumbySubheadforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbySubheadforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);  			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHeadforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyHeadforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);			
		elseif($tier == 3):
     		$total_debit = $this->getSumbyGroupforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyGroupforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClassforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyClassforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		endif;
		return $total_debit - $total_credit;	
		if($id=='4'||$id=='9'||$id=='10'){
			return $total_debit - $total_credit;	
		}else{
			return $total_credit - $total_debit;
		}
		
	}
	/**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceforPresPLSCLASS($activity,$region,$location,$starting_date,$ending_date, $id,$class_id, $tier)
	{	
		$starting_date = $starting_date;
		$ending_date = $ending_date;
		if($tier == 1):
			$total_debit = $this->getSumbySubheadforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbySubheadforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);  			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHeadforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyHeadforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);			
		elseif($tier == 3):
     		$total_debit = $this->getSumbyGroupforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyGroupforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClassforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyClassforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		endif;
		if($class_id=='4'||$class_id=='9'||$class_id=='10'){
			return $total_debit - $total_credit;	
		}else{
			return $total_credit - $total_debit;
		}
	}
	/**GET CLOSING BALANCE FOR PROFIT LOSS PRESENT//HEAD CANGED----------------------------------------------------------------------------------------------- */
	/**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceforPresPLSS($activity,$region,$location,$starting_date,$ending_date, $id, $tier)
	{	
		$starting_date = $starting_date;
		$ending_date = $ending_date;
		if($tier == 1):
			$total_debit = $this->getSumbySubheadforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbySubheadforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);  			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHeadforPresPLSS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyHeadforPresPLSS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);			
		elseif($tier == 3):
     		$total_debit = $this->getSumbyGroupforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyGroupforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClassforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyClassforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		endif;
		return $total_debit - $total_credit;	
		
	}
	/**
	 * get sum by subhead
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbySubheadforPresPLS($activity,$region,$location,$starting_date,$ending_date, $column, $sub_head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("sub_head" => $sub_head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by head
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $head
	 * @return int
	 */
	public function getSumbyHeadforPresPLS($activity,$region,$location,$starting_date,$ending_date,$column, $head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
		       ->join(array('h'=>'fa_head'), 'h.id=t.head', array('head'=>'name', 'head_id'=>'id'));
		$select ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("h.id" => $head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by head
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $head
	 * @return int
	 */
	public function getSumbyHeadforPresPLSS($activity,$region,$location,$starting_date,$ending_date,$column, $head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
		       ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("head" => $head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by group
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSumbyGroupforPresPLS($activity,$region,$location,$starting_date,$ending_date, $column, $group)
	{			 
		$sub1 = new Select("fa_head");
		$sub1->columns(array("id"))
			 ->where(array("group"=>$group));
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
			   
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->where->in('head', $sub1);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by class
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param int $class
	 * @return int
	 */
	public function getSumbyClassforPresPLS($activity,$region,$location,$starting_date,$ending_date, $column, $class)
	{		
		$sub1 = new Select("fa_group");
		$sub1->columns(array("id"))
			 ->where(array("class" => $class));
		
		$sub2 = new Select("fa_head");
		$sub2->columns(array("id"))
			 ->where->in("group", $sub1);
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date,$ending_date);
			   
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->where->in('head', $sub2);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit; 
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**GET CLOSING BALANCE FOR PROFIT LOSS PREVIOUS---------------------------------------------------------------------------------- */
	/**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceforPrevPLS($activity,$region,$location,$starting_date,$ending_date, $id, $tier)
	{	
		$starting_date = $starting_date;
		$ending_date = $ending_date;
		if($tier == 1):
			$total_debit = $this->getSumbySubheadforPrevPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbySubheadforPrevPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);  			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHeadforPrevPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyHeadforPrevPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);			
		elseif($tier == 3):
     		$total_debit = $this->getSumbyGroupforPrevPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyGroupforPrevPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClassforPrevPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyClassforPrevPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		endif;
		return $total_debit - $total_credit;	
	}
	/**
	 * get sum by class
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param int $class
	 * @return int
	 */
	public function getSumbyClassforPrevPLS($activity,$region,$location,$starting_date,$ending_date, $column, $class)
	{		
		$sub0 = new Select("fa_transaction");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4")) //committed status
			 ->where->between('voucher_date', $starting_date,$ending_date);
		
		$sub1 = new Select("fa_group");
		$sub1->columns(array("id"))
			 ->where(array("class" => $class));
		
		$sub2 = new Select("fa_head");
		$sub2->columns(array("id"))
			 ->where->in("group", $sub1);
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')));
			   
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->where->in('head', $sub2)
			   ->where->in('transaction', $sub0);

		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit; 
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	
	/**
	 * get sum by group
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSumbyGroupforPrevPLS($activity,$region,$location,$starting_date,$ending_date, $column, $group)
	{		
		$sub0 = new Select("fa_transaction");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4")) //committed status
			 ->where->between('voucher_date', $starting_date, $ending_date);
			 
		$sub1 = new Select("fa_head");
		$sub1->columns(array("id"))
			 ->where(array("group"=>$group));
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')));
			   
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->where->in('head', $sub1)
			   ->where->in('transaction', $sub0);

		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   
	   return $sum;	    
	}
	
	
	/**
	 * get sum by head
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $head
	 * @return int
	 */
	public function getSumbyHeadforPrevPLS($activity,$region,$location,$starting_date,$ending_date,$column, $head)
	{
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
		       ->join(array('h'=>'fa_head'), 'h.id=t.head', array('head'=>'name', 'head_id'=>'id'));
		$select->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("h.id" => $head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   
	   return $sum;	    
	}
	
	/**
	 * get sum by subhead
	  * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbySubheadforPrevPLS($activity,$region,$location,$starting_date,$ending_date, $column, $sub_head)
	{
		//extract($options);		
		$sub0 = new Select("fa_transaction");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4")) //committed status
			 ->where->between('voucher_date', $starting_date, $ending_date);
				
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("sub_head" => $sub_head));
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->where->in('transaction', $sub0);

		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   
	   return $sum;	    
	}
	/**END xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx */
	/**LEDGERS & SUB LEDGER ----------------------------------------------------------------------------------------------------- */
	/**
	 * Calculate opening balance for sub ledger//
	 * @param Array $options
	 * @param Int $id
	 * @return Int
	 */
	public function getOpeningBalanceforSHLedgerALoc($location,$start_date,$id,$class)
	{	
		$total_debit = $this->getSumbySubheadforLedgerOpeningALoc($location,$start_date, 'debit', $id);
		$total_credit = $this->getSumbySubheadforLedgerOpeningALoc($location,$start_date, 'credit', $id);
		/*ASSET & EXPENSE(Debit-Credit) && INCOME & LIBILITIES(Credit-Debit)*/
		if($class=='1'|| $class=='4'){
			return $total_debit - $total_credit;	
		}else{
			return $total_credit - $total_debit;
		}				
	}
	/**IMA-REPORT----------------------------------------------------------------------------------------------------- */
	/**
	 * Calculate opening balance for sub ledger//
	 * @param Array $options
	 * @param Int $id
	 * @return Int
	 */
	public function getOpeningBalanceforIMAR($location,$currency,$start_date,$id,$class)
	{	
		$total_debit = $this->getSumbySubheadforIMACR($location,$currency,$start_date, 'debit_sdr', $id);
		$total_credit = $this->getSumbySubheadforIMACR($location,$currency,$start_date, 'credit_sdr', $id);
		/*ASSET & EXPENSE(Debit-Credit) && INCOME & LIBILITIES(Credit-Debit)*/
		if($class=='1'|| $class=='4'){
			return $total_debit - $total_credit;	
		}else{
			return $total_credit - $total_debit;
		}				
	}
	/**
	 * get sum by subhead for sub ledger opening Location and activity wise
	  * @param String $column
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbySubheadforLedgerOpeningALoc($location,$start_date,$column,$sub_head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("sub_head" => $sub_head))
			   ->where(array("status" => "4")) //committed status
			   ->where->lessThan('voucher_dates',$start_date);
        $select->order(array('transaction ASC'));
		$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by subhead for sub ledger opening Location and activity wise
	  * @param String $column
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbySubheadforIMACR($location,$currency,$start_date,$column,$sub_head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("sub_head" => $sub_head,"currency"=>$currency))
			   ->where(array("status" => "4")) //committed status
			   ->where->lessThan('voucher_dates',$start_date);
        $select->order(array('transaction ASC'));
		$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get transaction id present in transactions details
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getTransactionDtlIDLforLedger($location,$activity,$start_date,$end_date,$where)
	{		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->where(array("status" => "4")) //committed status
			   ->where->lessThan('voucher_dates',$start_date);
		$select->where($where);
        $select->order(array('id ASC'));
	
		if($location != -1):
			$select->where(array("location" => $location));
		endif;
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray(); 
	    return $results;	  
	}
	 /**
	 * get sum by subhead for Ledger
	  * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbyTransactionDIDforLedger( $activity,$location,$start_date,$end_date,$column,$where)
	{	
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $start_date, $end_date);
			   //->where($where);
			   if($location != -1):
				    $select->where(array("location" => $location));
			   endif;
			   if($activity != -1):
				    $select->where(array("activity" => $activity));
			   endif;
		$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString;  exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}

	/**
	 * Calculate opening balance for sub ledger
	 * @param Array $options
	 * @param Int $id
	 * @return Int
	 */
	public function getOpeningBalanceforSHLedger($start_date,$id,$class)
	{	
		$adapter = $this->adapter;  
		$sql = new Sql($adapter);
		$year = date('Y', strtotime($start_date));
		$date = $start_date;
		$starting_date = date('Y-m-d', strtotime('01-01-2022'));//2017 to 2022
		
		$date = strtotime($date);
		$date = strtotime("-1 day", $date);
		$closing_date = date('Y-m-d', $date);
		$ending_date = $closing_date;
			
		$total_debit = $this->getSumbySubheadforLedgerOpening($starting_date,$ending_date, 'debit', $id);
		$total_credit = $this->getSumbySubheadforLedgerOpening($starting_date,$ending_date, 'credit', $id); 
		if($class=='1'){
			//ASSET
			return $total_debit - $total_credit;	
		}else{
			//LAIBILITIES
			return $total_credit - $total_debit;
		}	
	}
	/**
	 * Calculate opening balance for sub ledger
	 * @param Array $options
	 * @param Int $id
	 * @return Int
	 */
	public function getOpeningBalanceforSHLedgerIMA($start_date,$id,$class)
	{	
		$adapter = $this->adapter;  
		$sql = new Sql($adapter);
		$year = date('Y', strtotime($start_date));
		$date = $start_date;
		$starting_date = date('Y-m-d', strtotime('01-01-2022'));//2017 to 2022
		
		$date = strtotime($date);
		$date = strtotime("-1 day", $date);
		$closing_date = date('Y-m-d', $date);
		$ending_date = $closing_date;
			
		$total_debit = $this->getSumbySubheadforLedgerOpening($starting_date,$ending_date, 'debit_sdr', $id);
		$total_credit = $this->getSumbySubheadforLedgerOpening($starting_date,$ending_date, 'credit_sdr', $id); 
		if($class=='1'){
			//ASSET
			return $total_debit - $total_credit;	
		}else{
			//LAIBILITIES
			return $total_credit - $total_debit;
		}	
	}
	/**
	 * get sum by subhead for sub ledger opening
	  * @param String $column
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbySubheadforLedgerOpening($start_date,$end_date,$column,$sub_head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("sub_head" => $sub_head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates',$start_date,$end_date);
		$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	    foreach ($results as $result):
		  $sum =  $result['total'];
	    endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by subhead for Ledger
	  * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbyTransactionIDforLedger($location,$activity,$start_date,$end_date,$column,$where)
	{
		//extract($start_date,$end_date);		
		$sub0 = new Select("fa_transaction");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4")) //committed status
			 ->where->between('voucher_date', $start_date, $end_date);
				
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where($where);
			   if($location != -1):
				    $select->where(array("location" => $location));
			   endif;
			   if($activity != -1):
				    $select->where(array("activity" => $activity));
			   endif;
		$select->where->in('transaction', $sub0);
        $select->order(array('transaction ASC'));
		$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString;  exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by subhead for Ledger
	  * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbyTransactionIDforLedgerIMA($location,$currency,$activity,$start_date,$end_date,$column,$where)
	{
		//extract($start_date,$end_date);		
		$sub0 = new Select("fa_transaction");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4")) //committed status
			 ->where->between('voucher_date', $start_date, $end_date);
				
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where($where);
			   if($location != -1):
				    $select->where(array("location" => $location));
			   endif;
			   if($activity != -1):
				    $select->where(array("activity" => $activity));
			   endif;
			    if($currency != -1):
				    $select->where(array("currency" => $currency));
			   endif;
		$select->where->in('transaction', $sub0);
        $select->order(array('transaction ASC'));
		$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString;  exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * Calculate opening balance for ledger
	 * @param Array $options
	 * @param Int $id
	 * @return Int
	 */
	public function getOpeningBalanceforHLedger($start_date,$id)
	{	
		$adapter = $this->adapter;  
		$sql = new Sql($adapter);
		$year = date('Y', strtotime($start_date));
		$date = $start_date;
		$starting_date = date('Y-m-d', strtotime('01-01-2022'));//changed 2017 to 2022
		
	    $date = strtotime($date);
	    $date = strtotime("-1 day", $date);
	    $closing_date = date('Y-m-d', $date);
		$ending_date = $closing_date;
	
		$total_debit = $this->getSumbyheadforLedgerOpening($starting_date,$ending_date,'debit', $id);
		$total_credit = $this->getSumbyheadforLedgerOpening($starting_date,$ending_date, 'credit', $id); 
 			
		return $total_debit - $total_credit;				
	}
	/**
	 * get sum by subhead for ledger opening
	 * @param String $column
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbyheadforLedgerOpening($start_date,$end_date,$column,$head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("head" => $head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates',$start_date,$end_date);
		$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/** END xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx*/
	/** GENERAL LEDGER - ANNEXTURE------------------------------------------------------------------------------------------------- */
	/**
	 * Calculate opening balance for annexture
	 * @param Array $options
	 * @param Int $id
	 * @return Int
	 */
	public function getOpeningBalanceforAnnexture($start_date,$id,$class)
	{	
		$total_debit = $this->getSumbySubheadforAnnextureOpening($start_date, 'debit', $id);
		$total_credit = $this->getSumbySubheadforAnnextureOpening($start_date ,'credit', $id); 
		/*ASSET & EXPENSE(Debit-Credit) && INCOME & LIBILITIES(Credit-Debit)*/
		if($class=='1'|| $class=='4'){
			return $total_debit - $total_credit;	
		}else{
			return $total_credit - $total_debit;
		}
					
	}
	public function getOpeningBalanceforcashbank($start_date,$id)
	{	
		$total_debit = $this->getSumbySubheadforAnnextureOpening($start_date, 'debit', $id);
		$total_credit = $this->getSumbySubheadforAnnextureOpening($start_date ,'credit', $id); 
		/*ASSET & EXPENSE(Debit-Credit) && INCOME & LIBILITIES(Credit-Debit)*/
			return $total_debit - $total_credit;
					
	}
	/**
	 * get sum by subhead for annexture opening
	  * @param String $column
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbySubheadforAnnextureOpening($start_date,$column,$sub_head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("sub_head" => $sub_head))
			   ->where(array("status" => "4")) //committed status
			   ->where->lessThan('voucher_dates',$start_date);
		$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
	   //echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by subhead for annexture
	  * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbySubheadforAnnexture($location,$start_date,$end_date, $column,$sub_head)
	{	
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("sub_head" => $sub_head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $start_date, $end_date);
			   if($location != -1):
				    $select->where(array("location" => $location));
			   endif;
		$select->order(array('created ASC'));

		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}

	/** END of  ANNEXTURE xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx */
	/** BANK BOOK-------------------------------------------------------------------------------------------------------------- */
	/**
	 * Calculate opening balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getOpeningBalanceforBA($end_date, $subhead_id)
	{		
		$total_debit = $this->getSumbySubheadforBA($end_date, 'debit', $subhead_id);
		$total_credit = $this->getSumbySubheadforBA($end_date, 'credit', $subhead_id); 	
		return $total_debit - $total_credit;	
	}
	/**
	 * get sum by subhead
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbySubheadforBA($start_date, $column, $sub_head)
	{		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("sub_head" => $sub_head))
			   ->where(array("status" => "4")) //committed status
			   ->where->lessThan('voucher_dates',$start_date);
		$select->order(array('created ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	 /**
	 * Get bank Report
	 * @param Int $id
	 * @param date
	 * @return Array
	 */
	public function getBank($where,$bank_account)
	{		
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('td'=>$this->table))
			->join(array('t'=>'fa_transaction'),'t.id = td.transaction', array('cheque_no','voucher_no','voucher_date','status','remark'));
		$select->where(array('td.sub_head'=>$bank_account));
		$select->where($where);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/** END OF BANK BOOK xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx */
	/** CASH BOOK ----------------------------------------------------------------------------------------------------- */
	/**
	 * Get cash Report
	 * @param Int $id
	 * @param date
	 * @return Array
	 */
	public function getCash($voucher_type,$opening_date,$closing_date,$where)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('td'=>$this->table))
				->join(array('t'=>'fa_transaction'),'t.id = td.transaction', array('voucher_type','voucher_no','voucher_date','status','remark'))
		        ->where($where)
		        ->where(array('t.status'=>4))//changed 3 to 4-singye
				->order(array('voucher_date ASC'))	
				->order(array('t.created ASC'))	
			    ->where(array('voucher_type'=>$voucher_type))
				->where->between('voucher_date', $opening_date, $closing_date);	
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Cash Book Head For Pling Head Office
	 * Return records of given condition array
	 * @param Stirng $column
	 * @param Int $param
	 * @return Array
	 */
	public function getCashBookHead($param)
	{
        $where = ( is_array($param) )? $param: array('td.id' => $param);
	    $adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('td'=>$this->table))
				->join(array('h'=>'fa_head'), 'h.id = td.head', array('head' => 'code'));
		$select->columns(array(
				'max' => new Expression('MAX(td.id)')
		));
		$select->where($where);
		$select->where->notIn('h.id', array(210));
		
		$selectString = $sql->getSqlStringForSqlObject($select);
	//	echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		foreach ($results as $result):
			$columns =  $result['head'];
		endforeach;
		return $columns;
	}
	/**END OF THE CASH BOOK xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx */
	/** NOT USED BALANCE SHEET FOR QUERY FOR REPORT---------------------------------------------------------------------------- */
	/**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceforPresBS1($activity,$region,$location,$starting_date,$ending_date, $id, $tier)
	{	
		$starting_date = $starting_date;
		$ending_date = $ending_date;
		if($tier == 1):
		 
			$total_debit = $this->getSumbySubheadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbySubheadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);  			
		elseif($tier == 2):
		
			$total_debit = $this->getSumbyHeadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyHeadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);			
		elseif($tier == 3):
		
     		$total_debit = $this->getSumbyGroupforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyGroupforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		elseif($tier == 4):
		
			$total_debit = $this->getSumbyClassforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyClassforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		endif;
		return $total_debit - $total_credit;	
	}
       
    /**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceforPrevBS1($activity,$region,$location,$starting_date,$ending_date, $id, $tier)
	{	
		$starting_date = $starting_date;
		$ending_date = $ending_date;
		if($tier == 1):
			$total_debit = $this->getSumbySubheadforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbySubheadforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);  			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHeadforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyHeadforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);			
		elseif($tier == 3):
     		$total_debit = $this->getSumbyGroupforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyGroupforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClassforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyClassforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		endif;
		return $total_debit - $total_credit;	
	}
	
	
	/**
	 * Calculate opening balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getOpeningBalanceforBSPLS($options, $id, $tier)
	{	//print_r($options); echo $tier; exit; 
		extract($options);		
		$adapter = $this->adapter;  
		$sql = new Sql($adapter);
		$year = date('Y', strtotime($start_date));
		
		$starting_date = date('Y-m-d', strtotime('01-01-'.$year));
		$ending_date = $start_date;
		
		$filter = array(
			'start_date' => $starting_date,
			'end_date'   => $ending_date,
			'activity'   => $activity,
			'region'     => $region,
			'location'   => $location,
		);
		$sub0 = new Select("fa_closing_balance");
		
		if($tier == 1):
			$sub0->columns(array( new Expression('SUM(closing_cr) as total_closing_cr'), new Expression('SUM(closing_dr) as total_closing_dr')));
			$sub0->where(array("year" => $year-2, 'id' => $id));			
			$total_debit = $this->getSumbySubheadforBSPLS($filter, 'debit', $id);
			$total_credit = $this->getSumbySubheadforBSPLS($filter, 'credit', $id);  			
		elseif($tier == 2):
			$sub1 = new Select("fa_sub_head");
			$sub1->columns(array("id"))
				->where(array("head"=>$id));
			
			$sub0->columns(array( new Expression('SUM(closing_cr) as total_closing_cr'), new Expression('SUM(closing_dr) as total_closing_dr')));
			$sub0->where(array("year" => $year-2))
				->where->in('sub_head',$sub1);
			
			$total_debit = $this->getSumbyHeadforBSPLS($filter, 'debit', $id);
			$total_credit = $this->getSumbyHeadforBSPLS($filter, 'credit', $id);			
		elseif($tier == 3):
			$sub2 = new Select("fa_head");
			$sub2->columns(array("id"))
				->where(array("group" => $id));
		
			$sub1 = new Select("fa_sub_head");
			$sub1->columns(array("id"))
				->where->in('head',$sub2);
				
			$sub0->columns(array( new Expression('SUM(closing_cr) as total_closing_cr'), new Expression('SUM(closing_dr) as total_closing_dr')));
			$sub0->where(array("year" => $year-2))
				->where->in('sub_head',$sub1);
			
			$total_debit = $this->getSumbyGroupforBSPLS($filter, 'debit', $id);
			$total_credit = $this->getSumbyGroupforBSPLS($filter, 'credit', $id);
		elseif($tier == 4):
			$sub3 = new Select("fa_group");
			$sub3->columns(array("id"))
				->where(array("class" => $id));
		
			$sub2 = new Select("fa_head");
			$sub2->columns(array("id"))
				->where->in('group', $sub3);
			
			$sub1 = new Select("fa_sub_head");
			$sub1->columns(array("id"))
				->where->in('head',$sub2);
			
			$sub0->columns(array( new Expression('SUM(closing_cr) as total_closing_cr'), new Expression('SUM(closing_dr) as total_closing_dr')));
			$sub0->where(array("year" => $year-2))
				->where->in('sub_head',$sub1);
		
			$total_debit = $this->getSumbyClassforBSPLS($filter, 'debit', $id);
			
			$total_credit = $this->getSumbyClassforBSPLS($filter, 'credit', $id);
		endif;
		
		$selectString = $sql->getSqlStringForSqlObject($sub0);	
        //echo $selectString; exit;		
		$balances = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();	
		foreach($balances as $balance); 
		return $balance['total_closing_dr'] - $balance['total_closing_cr'] + $total_debit - $total_credit;				
	}
	/**END of BS1 xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx */
	/**START PROFIT LOSS STATEMENT----------------------------------------------------------------------------------------------- */
	/**GET CLOSING BALANCE FOR PROFIT LOSS PRESENT----------------------------------------------------------------------------------------------- */
	/**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceforPresPLSA($activity,$region,$location,$starting_date,$ending_date, $id, $tier)
	{	
		$starting_date = $starting_date;
		$ending_date = $ending_date;
		if($tier == 1):
			$total_debit = $this->getSumbySubheadforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbySubheadforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);  			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHeadforPresPLSA($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyHeadforPresPLSA($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);			
		elseif($tier == 3):
     		$total_debit = $this->getSumbyGroupforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyGroupforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClassforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyClassforPresPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		endif;
		return $total_debit - $total_credit;	
	} 
	/**
	 * get sum by head
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $head
	 * @return int
	 */
	public function getSumbyHeadforPresPLSA($activity,$region,$location,$starting_date,$ending_date,$column, $head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
		       ->join(array('h'=>'fa_head'), 'h.id=t.head', array('head'=>'name', 'head_id'=>'id'))
			   ->join(array('fa'=>'fa_head_format_audit'), 'fa.id=h.format_audit', array('name', 'format_audit_id'=>'id'));
		$select ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("fa.id" => $head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**GET CLOSING BALANCE FOR PROFIT LOSS PREVIOUS---------------------------------------------------------------------------------- */
	/**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceforPrevPLSA($activity,$region,$location,$starting_date,$ending_date, $id, $tier)
	{	
		$starting_date = $starting_date;
		$ending_date = $ending_date;
		if($tier == 1):
			$total_debit = $this->getSumbySubheadforPrevPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbySubheadforPrevPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);  			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHeadforPrevPLSA($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyHeadforPrevPLSA($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);			
		elseif($tier == 3):
     		$total_debit = $this->getSumbyGroupforPrevPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyGroupforPrevPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClassforPrevPLS($activity,$region,$location,$starting_date,$ending_date, 'debit', $id);
			$total_credit = $this->getSumbyClassforPrevPLS($activity,$region,$location,$starting_date,$ending_date, 'credit', $id);
		endif;
		return $total_debit - $total_credit;	
	}
	/**
	 * get sum by head
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $head
	 * @return int
	 */
	public function getSumbyHeadforPrevPLSA($activity,$region,$location,$starting_date,$ending_date,$column, $head)
	{
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
		       ->join(array('h'=>'fa_head'), 'h.id=t.head', array('head'=>'name', 'head_id'=>'id'))
			   ->join(array('ht'=>'fa_head_format'), 'ht.id=h.format', array('name', 'format_id'=>'id'))
			   ->join(array('fa'=>'fa_head_format_audit'), 'fa.id=h.format_audit', array('name', 'format_audit_id'=>'id'));
		$select->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("ht.id" => $head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   
	   return $sum;	    
	}
		/**START OF THE BALANCE SHEET-------------------------------------------------------------------------------------------------------- */
	/**CLOSING BALANCE FOR BALANCE SHEET PRESENT----------------------------------------------------------------------------------------- */
	/**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceforPresBSsdr($activity,$region,$location,$starting_date,$ending_date, $id, $tier)
	{	
		$starting_date = $starting_date;
		$ending_date = $ending_date;
        $adapter = $this->adapter;  
		$sql = new Sql($adapter);
		$year = date('Y', strtotime($starting_date));
		$year1 = date('Y', strtotime($starting_date)) - 10;
        $starting_date = date('Y-m-d',strtotime('01-01-'.$year1));
		$ending_date = $ending_date;
		if($tier == 1):
			$total_debit = $this->getSumbySubheadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit_sdr', $id);
			$total_credit = $this->getSumbySubheadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit_sdr', $id);  			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHeadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit_sdr', $id);
			$total_credit = $this->getSumbyHeadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit_sdr', $id);			
		elseif($tier == 3):
     		$total_debit = $this->getSumbyGroupforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit_sdr', $id);
			$total_credit = $this->getSumbyGroupforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit_sdr', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClassforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit_sdr', $id);
			$total_credit = $this->getSumbyClassforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit_sdr', $id);
		endif;
		return $total_debit - $total_credit;				
	}
	/**
	 * get sum by subhead
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbySubheadforPresBSsdr($activity,$region,$location,$starting_date,$ending_date, $column, $sub_head)
	{	
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("sub_head" => $sub_head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit; 
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by head
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $head
	 * @return int
	 */
	public function getSumbyHeadforPresBSsdr($activity,$region,$location,$starting_date,$ending_date,$column, $head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table))
		       ->join(array('h'=>'fa_head'), 'h.id=t.head', array('head'=>'name', 'head_id'=>'id'))
			   ->join(array('ht'=>'fa_head_format'), 'ht.id=h.format', array('name', 'format_id'=>'id'));
		$select->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("ht.id" => $head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		// $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by group
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSumbyGroupforPresBSsdr($activity,$region,$location,$starting_date,$ending_date, $column, $group)
	{		
		$sub1 = new Select("fa_head");
		$sub1->columns(array("id"))
			 ->where(array("group"=>$group));
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
			   
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->where->in('head', $sub1);

		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/* get sum by class
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param int $class
	 * @return int
	 */
	public function getSumbyClassforPresBSsdr($activity,$region,$location,$starting_date,$ending_date, $column, $class)
	{		
		
		$sub1 = new Select("fa_group");
		$sub1->columns(array("id"))
			 ->where(array("class" => $class));
		
		$sub2 = new Select("fa_head");
		$sub2->columns(array("id"))
			 ->where->in("group", $sub1);
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date,$ending_date);
			   
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->where->in('head', $sub2);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit; 
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**GET CLOSING BALANCE FOR BALANCESHEET PREVIOUS------------------------------------------------------------------------------------ */
    /**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceforPrevBSsdr($activity,$region,$location,$starting_date,$ending_date, $id, $tier)
	{	
		$starting_date = $starting_date;
		$ending_date = $ending_date;
		$adapter = $this->adapter;  
		$sql = new Sql($adapter);
		$year = date('Y', strtotime($starting_date));
		$year1 = date('Y', strtotime($starting_date)) - 10;
        $starting_date = date('Y-m-d',strtotime('01-01-'.$year1));
		$ending_date = $ending_date;
		if($tier == 1):
			$total_debit = $this->getSumbySubheadforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'debit_sdr', $id);
			$total_credit = $this->getSumbySubheadforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'credit_sdr', $id);  			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHeadforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'debit_sdr', $id);
			$total_credit = $this->getSumbyHeadforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'credit_sdr', $id);			
		elseif($tier == 3):
     		$total_debit = $this->getSumbyGroupforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'debit_sdr', $id);
			$total_credit = $this->getSumbyGroupforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'credit_sdr', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClassforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'debit_sdr', $id);
			$total_credit = $this->getSumbyClassforPrevBS($activity,$region,$location,$starting_date,$ending_date, 'credit_sdr', $id);
		endif;
		return $total_debit - $total_credit;			
	}
	 /**
	 * get sum by subhead
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $sub_head
	 * @return int
	 */		
	public function getSumbySubheadforPrevBSsdr($activity,$region,$location,$starting_date,$ending_date, $column, $sub_head)
	{
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("sub_head" => $sub_head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by head
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $head
	 * @return int
	 */
	public function getSumbyHeadforPrevBSsdr($activity,$region,$location,$starting_date,$ending_date,$column, $head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		 $select->from(array('t'=>$this->table))
		       ->join(array('h'=>'fa_head'), 'h.id=t.head', array('head'=>'name', 'head_id'=>'id'))
			   ->join(array('ht'=>'fa_head_format'), 'ht.id=h.format', array('name', 'format_id'=>'id'));
		$select->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("ht.id" => $head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;

		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	 /**
	 * get sum by group
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSumbyGroupforPrevBSsdr($activity,$region,$location,$starting_date,$ending_date, $column, $group)
	{		 
		$sub1 = new Select("fa_head");
		$sub1->columns(array("id"))
			 ->where(array("group"=>$group));
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
			   
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->where->in('head', $sub1);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**
	 * get sum by class
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param int $class
	 * @return int
	 */
	public function getSumbyClassforPrevBSsdr($activity,$region,$location,$starting_date,$ending_date, $column, $class)
	{		
		$sub1 = new Select("fa_group");
		$sub1->columns(array("id"))
			 ->where(array("class" => $class));
		
		$sub2 = new Select("fa_head");
		$sub2->columns(array("id"))
			 ->where->in("group", $sub1);
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date,$ending_date);
			   
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$select->where->in('head', $sub2);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit; 
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**BALANCE SHEET-------------------------------------------------------------------------------------------------------- */
	/**Heads for subhead----------------------------------------------------------------------------------------- */
	/**
	 * Calculate closing balance
	 * @param Array $options
	 * @param Int $id
	 * @param Ind $tier
	 * @return Int
	 */
	public function getClosingBalanceforPresBSSsdr($activity,$region,$location,$starting_date,$ending_date, $id, $tier)
	{	
		$starting_date = $starting_date;
		$ending_date = $ending_date;
        $adapter = $this->adapter;  
		$sql = new Sql($adapter);
		$year = date('Y', strtotime($starting_date));
		$year1 = date('Y', strtotime($starting_date)) - 10;
        $starting_date = date('Y-m-d',strtotime('01-01-'.$year1));
		$ending_date = $ending_date;
		if($tier == 1):
			$total_debit = $this->getSumbySubheadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit_sdr', $id);
			$total_credit = $this->getSumbySubheadforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit_sdr', $id);  			
		elseif($tier == 2):
			$total_debit = $this->getSumbyHeadforPresBSS($activity,$region,$location,$starting_date,$ending_date, 'debit_sdr', $id);
			$total_credit = $this->getSumbyHeadforPresBSS($activity,$region,$location,$starting_date,$ending_date, 'credit_sdr', $id);			
		elseif($tier == 3):
     		$total_debit = $this->getSumbyGroupforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit_sdr', $id);
			$total_credit = $this->getSumbyGroupforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit_sdr', $id);
		elseif($tier == 4):
			$total_debit = $this->getSumbyClassforPresBS($activity,$region,$location,$starting_date,$ending_date, 'debit_sdr', $id);
			$total_credit = $this->getSumbyClassforPresBS($activity,$region,$location,$starting_date,$ending_date, 'credit_sdr', $id);
		endif;
		return $total_debit - $total_credit;				
	}
		/**
	 * get sum by head
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $head
	 * @return int
	 */
	public function getSumbyHeadforPresBSSsdr($activity,$region,$location,$starting_date,$ending_date,$column, $head)
	{
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('t'=>$this->table));
		$select->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where(array("head" => $head))
			   ->where(array("status" => "4")) //committed status
			   ->where->between('voucher_dates', $starting_date, $ending_date);
		if($activity != -1):
			$select->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$select->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$select->where->in("location", $sub_loc);
			endif;
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		// $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   return $sum;	    
	}
	/**-----------------------------------REPORT FOR COST PROFIT POST OFFICE WISE**-------------------------------------------------------------/
	/**
	 * Total sum in Class
	 * @return Array
	 */
	public function sumbyclass($classid,$start_date,$end_date,$location,$region,$debit,$credit)
	{  
	    $adapter = $this->adapter;
	    $sql = new Sql($adapter);
	    $select = $sql->select();
	    $select->from(array("bt" => $this->table))
	    	   ->join(array("h"=>"fa_head"),"h.id=bt.head", array("head"=>"code","head_id" => "id"))
			   ->join(array("g"=>"fa_group"),"g.id=h.group", array("group"=>"code","group_id" => "id"))
			   ->join(array("c"=>"fa_class"),"c.id=g.class", array("class"=>"code","class_id" => "id"))
			   ->join(array("lc"=>"adm_location"),"lc.id=bt.location", array("locations"=>"location","location_id" => "id"))
			   ->join(array("rg"=>"adm_region"),"rg.id=lc.region", array("regions"=>"region","region_id" => "id"));
		$select->columns([
				'debit' => new Expression('SUM(' . $debit . ')'),
				'credit' => new Expression('SUM(' . $credit . ')'),
				])
		       ->where(array("c.id" => $classid))
			   ->where->between('voucher_dates', $start_date, $end_date);
		 if($region != '-1'){
			$select->where(array('rg.id'=>$region));
		}
		if($location != '-1'){
			$select->where(array('lc.id'=>$location));
		}
	    $selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit; 
	    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    return $results;
	}
	/**
	 * Total Sum In Group
	 * @return Array
	 */
	public function sumbygroup($groupid,$start_date,$end_date,$location,$region,$debit,$credit)
	{  
	    $adapter = $this->adapter;
	    $sql = new Sql($adapter);
	    $select = $sql->select();
	    $select->from(array("bt" => $this->table))
	    	   ->join(array("h"=>"fa_head"),"h.id=bt.head", array("head"=>"code","head_id" => "id"))
			   ->join(array("g"=>"fa_group"),"g.id=h.group", array("group"=>"code","group_id" => "id"))
			   ->join(array("lc"=>"adm_location"),"lc.id=bt.location", array("locations"=>"location","location_id" => "id"))
			   ->join(array("rg"=>"adm_region"),"rg.id=lc.region", array("regions"=>"region","region_id" => "id"));
		$select->columns([
				'debit' => new Expression('SUM(' . $debit . ')'),
				'credit' => new Expression('SUM(' . $credit . ')'),
				])
		       ->where(array("g.id" => $groupid))
			   ->where->between('voucher_dates', $start_date, $end_date);
        if($region != '-1'){
			$select->where(array('rg.id'=>$region));
		}	   
	    if($location != '-1'){
			$select->where(array('lc.id'=>$location));
		}
	    $selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
	    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    return $results;
	}
	/**
	 * Get Sum in Head(Sum of all subhead amount under particular head)
	 * @param Int $id
	 * @return Array
	 */
	public function sumbyhead($head,$start_date,$end_date,$location,$region,$debit,$credit)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array("bt" => $this->table)) 
		       ->join(array("lc"=>"adm_location"),"lc.id=bt.location", array("locations"=>"location","location_id" => "id"))
			   ->join(array("rg"=>"adm_region"),"rg.id=lc.region", array("regions"=>"region","region_id" => "id"));
	    $select->columns([
				'debit' => new Expression('SUM(' . $debit . ')'),
				'credit' => new Expression('SUM(' . $credit . ')'),
				])
		       ->where(array("bt.head" => $head))
			   ->where->between('voucher_dates', $start_date, $end_date);
		if($region != '-1'){
			$select->where(array('rg.id'=>$region));
		} 
		if($location != '-1'){
			$select->where(array('lc.location'=>$location));
		}  
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Get Subhead Based Amount
	 * Region Table Joined to Get data in region basis from Budet Forecasting Table
	 * @param Int $id
	 * @return Array
	 */
	public function getsubheadbased($subhead,$start_date,$end_date,$location,$region,$debit,$credit)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array("bt" => $this->table)) 
		       ->join(array("lc"=>"adm_location"),"lc.id=bt.location", array("locations"=>"location","location_id" => "id"))
			   ->join(array("rg"=>"adm_region"),"rg.id=lc.region", array("regions"=>"region","region_id" => "id"));
	    $select->columns([
				'debit' => new Expression('SUM(' . $debit . ')'),
				'credit' => new Expression('SUM(' . $credit . ')'),
				])
		       ->where(array("bt.sub_head" => $subhead))
			   ->where->between('voucher_dates', $start_date, $end_date);
		if($region != '-1'){
			$select->where(array('rg.id'=>$region));
		} 
		if($location != '-1'){
			$select->where(array('lc.location'=>$location));
		}  	
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Get Subhead Based Amount
	 * Region Table Joined to Get data in region basis from Budet Forecasting Table
	 * @param Int $id
	 * @return Array
	 */
	public function getSumofsubheadd($subhead,$column)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array("bt" => $this->table));
	    $select->columns([
				'debit' => new Expression('SUM(bt.'.$column.')',)])
		       ->where(array("bt.sub_head" => $subhead));	
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Get Subhead Based Amount
	 * Region Table Joined to Get data in region basis from Budet Forecasting Table
	 * @param Int $id
	 * @return Array
	 */
	public function getSumofsubheadc($subhead,$column)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array("bt" => $this->table));
	    $select->columns([
				'debit' => new Expression('SUM(bt.'.$column.')',)])
		       ->where(array("bt.sub_head" => $subhead));	
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getTransaction($param)
	{
		$where = ( is_array($param) )? $param: array('td.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select->from(array('td'=>$this->table))
				 ->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Get Sum in Head(Sum of all subhead amount under particular head)
	 * @param Int $id
	 * @return Array
	 */
	public function getsum($column,$param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from( $this->table);
	    $select->columns([
				'sum' => new Expression('SUM(' . $column . ')'),
				])
		       ->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString;exit;
		$columns='';
		foreach ($results as $result):
			$columns =  $result[$column];
		endforeach; 
		return $columns;   
	}
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getbyheadC($ledger, $subledger,$currency,$start_date,$end_date)
	{
		//$where = ( is_array($param) )? $param: array('td.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select->from($this->table);
		$select->where(array("status" => "4"))//committed status
			   ->where->between('voucher_dates', $start_date, $end_date);
		if($ledger != -1):
			$select->where(array("head" => $ledger));
		endif;
		if($subledger != -1):
			$select->where(array("sub_head" => $subledger));
		endif;
		if($currency != -1):
			$select->where(array("currency" => $currency));
		endif;
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	//Get invoice  
	public function getInvoiceDue($data,$start_date,$end_date, $where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('td'=>$this->table))
		->join(array('t'=>'fa_transaction'),'td.transaction=t.id',array('voucher_no','remark'))
		->where->between('td.voucher_dates',$start_date,$end_date);
		if($data['head'] != '-1'){
			$select->where(array('td.head'=>$data['head']));
		}
		if($data['subhead'] != '-1'){
			$select->where(array('td.sub_head'=>$data['subhead']));
		}
		if($data['location'] != '-1'){
			$select->where(array('td.location'=>$data['location']));
		}
		$select->where->greaterThan('td.debit', 0);
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	public function getCreditsubhead($param)
	{
		$where = ( is_array($param) )? $param: array('td.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select->from($this->table)
		       ->where($where);
		$select->where->greaterThan('credit', 0);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
}

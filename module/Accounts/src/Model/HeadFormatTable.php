<?php
namespace Accounts\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class HeadFormatTable extends AbstractTableGateway 
{
	protected $table = 'fa_head_format'; //tablename

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
	    $select->from(array('h'=>$this->table))
				->join(array('g'=>'fa_group'), 'g.id=h.group', array('group'=>'name', 'group_id'=>'id'))
				->join(array('c'=>'fa_class'), 'c.id=g.class', array('class'=>'name', 'class_id'=>'id'))
				->join(array('ht'=>'fa_head_type'), 'ht.id=h.head_type', array('head_type', 'headtype_id'=>'id'))
				->order(array('code ASC'));
	    
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
		$where = ( is_array($param) )? $param: array('h.id' => $param);
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
			$columns ='';
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
	 * 
	 * get Head present in transactions details
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getTransactionHeadforBSPLS($options, $where)
	{
		extract($options);
		
		$year = date('Y', strtotime($start_date)) - 2;		
		$sub = new Select("fa_closing_balance");	
		$sub->columns(array("sub_head"))
		    ->where(array("year"=>$year));
			
		$sub9 = new select("fa_sub_head");
		$sub9->columns(array("head"))
		     ->where->in("id", $sub);
			 
		$sub0 = new Select("fa_transaction");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4")) //committed status
			 ->where->between('voucher_date', $start_date, $end_date);
			 
		$sub1 = new Select("fa_transaction_details");
		$sub1->columns(array("head"));
		if($activity != -1):
			$sub1->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$sub1->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$sub1->where->in("location", $sub_loc);
			endif;
		endif;
		$sub1->where->in("transaction", $sub0);
		 
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->where($where)
			   ->where
			        ->nest
				       ->in('id', $sub1)
				       ->OR->in('id', $sub9)				
			        ->unnest;

		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	    return $results;
	}
        /**
	 * 
	 * get Head present in transactions details
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getTransactionHeadforBS($activity,$region,$location,$start_date,$end_date, $where)
	{
		
		
		$prevoius_start_year = date('y', strtotime($start_date)) - 1;
		$prevoius_start_month = date('m', strtotime($start_date));
		$prevoius_start_day = date('d', strtotime($start_date));
		$pre_end_year = date('y', strtotime($end_date)) - 1;
		$pre_end_month = date('m', strtotime($end_date));
		$pre_end_day = date('d', strtotime($end_date));
		$pre_starting_date = date('Y-m-d', strtotime($prevoius_start_year.'-'.$prevoius_start_month.'-'.$prevoius_start_day));
		$pre_ending_date = date('Y-m-d', strtotime($pre_end_year.'-'.$pre_end_month.'-'.$pre_end_day));
		
		$year = date('Y', strtotime($start_date));		
		$sub = new Select("fa_closing_balance");	
		$sub->columns(array("sub_head"))
		    ->where->lessThanOrEqualTo("year",$year);
			
		$sub9 = new select("fa_sub_head");
		$sub9->columns(array("head"))
		     ->where->in("id", $sub);
			 
		$sub0 = new Select("fa_transaction");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4")) //committed status
			 ->where->between('voucher_date', $start_date, $end_date)
			 ->Or->where->between('voucher_date', $pre_starting_date, $pre_ending_date);
			 
		$sub1 = new Select("fa_transaction_details");
		$sub1->columns(array("head"));
		if($activity != -1):
			$sub1->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$sub1->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$sub1->where->in("location", $sub_loc);
			endif;
		endif;
		$sub1->where->in("transaction", $sub0);
		 
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->where($where)
			   ->where
			        ->nest
				       ->in('id', $sub1)
				       ->OR->in('id', $sub9)				
			        ->unnest;

		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	    return $results;
	}
 /**
	 * 
	 * get Head present in transactions details
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getTransactionHeadforPLS($activity,$region,$location,$start_date,$end_date, $where)
	{
		
		
		$prevoius_start_year = date('y', strtotime($start_date)) - 1;
		$prevoius_start_month = date('m', strtotime($start_date));
		$prevoius_start_day = date('d', strtotime($start_date));
		$pre_end_year = date('y', strtotime($end_date)) - 1;
		$pre_end_month = date('m', strtotime($end_date));
		$pre_end_day = date('d', strtotime($end_date));
		$pre_starting_date = date('Y-m-d', strtotime($prevoius_start_year.'-'.$prevoius_start_month.'-'.$prevoius_start_day));
		$pre_ending_date = date('Y-m-d', strtotime($pre_end_year.'-'.$pre_end_month.'-'.$pre_end_day));
		
		$year = date('Y', strtotime($start_date));		
		$sub = new Select("fa_closing_balance");	
		$sub->columns(array("sub_head"))
		    ->where->lessThanOrEqualTo("year",$year);
			
		$sub9 = new select("fa_sub_head");
		$sub9->columns(array("head"))
		     ->where->in("id", $sub);
			 
		$sub0 = new Select("fa_transaction");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4")) //committed status
			 ->where->between('voucher_date', $start_date, $end_date)
			 ->Or->where->between('voucher_date', $pre_starting_date, $pre_ending_date);
			 
		$sub1 = new Select("fa_transaction_details");
		$sub1->columns(array("head"));
		if($activity != -1):
			$sub1->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$sub1->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$sub1->where->in("location", $sub_loc);
			endif;
		endif;
		$sub1->where->in("transaction", $sub0);
		 
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->where($where)
			   ->where
			        ->nest
				       ->in('id', $sub1)
				       ->OR->in('id', $sub9)				
			        ->unnest;

		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	    return $results;
	}
	/**
	 * 
	 * get Head present in transactions details
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getTransactionHead($activity,$region,$location,$start_date,$end_date,$where)
	{
		//extract($options);
		
		$year = date('Y', strtotime($start_date));		
		$sub = new Select("fa_closing_balance");	
		$sub->columns(array("sub_head"))
		    ->where->lessThanOrEqualTo("year",$year);
			
		$sub9 = new select("fa_sub_head");
		$sub9->columns(array("head"))
		     ->where->in("id", $sub);
			 
		$sub0 = new Select("fa_transaction");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4")) //committed status
			 ->where->between('voucher_date', $start_date, $end_date)
		          ->OR->lessThan('voucher_date', $start_date); 
		$sub1 = new Select("fa_transaction_details");
		$sub1->columns(array("head"));
		if($activity != -1):
			$sub1->where(array("activity" => $activity));
		endif;
		if($region != -1):
			if($location != -1):
				$sub1->where(array("location" => $location));
			else:
				$sub_loc = new Select("adm_location");
				$sub_loc ->columns(array("id"))
						 ->where(array("region" => $region));
				$sub1->where->in("location", $sub_loc);
			endif;
		endif;
		$sub1->where->in("transaction", $sub0);
		 
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->where($where)
			   ->where
			        ->nest
				       ->in('id', $sub1)
				       ->OR->in('id', $sub9)				
              			        ->unnest;
                		$select->order('code');

		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	    return $results;
	}
	/**
	 * 
	 * get Head present in transactions details
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getTransactionHeadforLedger($location,$activity,$start_date,$end_date,$where)
	{
		//extract($options);	
		
		$year = date('Y', strtotime($start_date));		
		$sub = new Select("fa_closing_balance");	
		$sub->columns(array("head"))
		    ->where->lessThanOrEqualTo("year",$year);
			 
		$sub0 = new Select("fa_transaction");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4"))
			 ->where->between('voucher_date', $start_date, $end_date)
			 ->OR->lessThan('voucher_date', $start_date);
			 
		$sub1 = new Select("fa_transaction_details");
		$sub1->columns(array("head"));
		
		if($location != -1):
			$sub1->where(array("location" => $location));
		endif;
		if($activity != -1):
			$sub1->where(array("activity" => $activity));
		endif;
		
		$sub1->where->in("transaction", $sub0);
		 
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->where($where)			  
			   ->where
			        ->nest
				       ->in('id', $sub1)
				       ->OR->in('id', $sub)				
			        ->unnest;

		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray(); 
	    return $results;
	}
	/**
	 * Return records of given condition array
	 * @param Int $column
	 * @param Int $param
	 * @return Array
	 */
	public function getMaxRow($column,$param)
	{
		$where = ( is_array($param) )? $param: array('h.id' => $param);
		$adapter = $this->adapter;
		
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MAX('.$column.')')
		));
		//$sub0 = $sub0->toArray();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('h'=>$this->table))
				->join(array('g'=>'fa_group'), 'g.id=h.group', array('group'=>'name', 'group_id'=>'id'))
				->join(array('c'=>'fa_class'), 'c.id=g.class', array('class'=>'name', 'class_id'=>'id'))
				->join(array('ht'=>'fa_head_type'), 'ht.id=h.head_type', array('head_type', 'headtype_id'=>'id'))
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
		$where = ( is_array($param) )? $param: array('h.id' => $param);
		$adapter = $this->adapter;
	
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MIN('.$column.')')
		));
		//$sub0 = $sub0->toArray();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('h'=>$this->table))
				->join(array('g'=>'fa_group'), 'g.id=h.group', array('group'=>'name', 'group_id'=>'id'))
				->join(array('c'=>'fa_class'), 'c.id=g.class', array('class'=>'name', 'class_id'=>'id'))
				->join(array('ht'=>'fa_head_type'), 'ht.id=h.head_type', array('head_type', 'headtype_id'=>'id'))
		->where($where)
		->where($column);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**GET DINSTINCT YEAR----------------------------------------------------------------------------------------- */
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getDistinctheadtype($data)
	{
		$adapter = $this->adapter;
	    $sql = new Sql($adapter);
	    $select = $sql->select();
	    $select->from($this->table)
		       ->columns(array(new Expression('DISTINCT(format) as format')));
	    $selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
}


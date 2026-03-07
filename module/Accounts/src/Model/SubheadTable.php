<?php
namespace Accounts\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class SubheadTable extends AbstractTableGateway 
{
	protected $table = 'fa_sub_head'; //tablename

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
	    $select->from(array('sh'=>$this->table))
				->join(array('h'=>'fa_head'), 'h.id=sh.head', array('head'=>'name', 'head_id'=>'id'))
				->join(array('g'=>'fa_group'), 'g.id=h.group', array('group'=>'name', 'group_id'=>'id'))
				->join(array('c'=>'fa_class'), 'c.id=g.class', array('class'=>'name', 'class_id'=>'id'))
				->join(array('ht'=>'fa_head_type'), 'ht.id=h.head_type', array('head_type', 'headtype_id'=>'id'))
				->join(array('t'=>'fa_type'), 't.id=sh.type', array('type', 'type_id'=>'id', 'type_class'=>'class'))
	            ->order(array('code ASC'));
	    $selectString = $sql->getSqlStringForSqlObject($select);
	    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    return $results;
	}
	
	/**
	 * Return records of given condition Array
	 * @param Array
	 * @param Array
	 * @return Array
	 */
	public function get($param, $order=NULL)
	{
		$where = ( is_array($param) )? $param: array('sh.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select->from(array('sh'=>$this->table))
				->join(array('h'=>'fa_head'), 'h.id=sh.head', array('head'=>'name', 'head_id'=>'id'))
				->join(array('g'=>'fa_group'), 'g.id=h.group', array('group'=>'name', 'group_id'=>'id'))
				->join(array('c'=>'fa_class'), 'c.id=g.class', array('class'=>'name', 'class_id'=>'id'))
				->join(array('ht'=>'fa_head_type'), 'ht.id=h.head_type', array('head_type', 'headtype_id'=>'id'))
				->join(array('t'=>'fa_type'), 't.id=sh.type', array('type', 'type_id'=>'id', 'type_class'=>'class'))
		        ->where($where);

            $select->order('sh.code');
	    if($order != NULL):
	    	$select->order($order);
	    endif;
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
		/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getbanks($param)
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
			//echo $selectString;exit;
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
        //echo $result;exit;		
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
	 * Return Min value of the column
	 * @param String $column
	 * @param Array $where
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
	 * @param String $column
	 * @param Array $where
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
	 * 
	 * get Subhead present in transactions details
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getTransactionSubheadforAnnexture($start_date,$end_date,$where)
	{		
		//extract($start_date,$end_date);	
		
		$year = date('Y', strtotime($start_date));		
		$sub = new Select("fa_closing_balance");	
		$sub->columns(array("sub_head"))
		    ->where->lessThanOrEqualTo("year",$year);
			 
		$sub0 = new Select("fa_transaction");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4"))
			 ->where->between('voucher_date', $start_date, $end_date)
			 ->OR->lessThan('voucher_date', $start_date);
			 
		$sub1 = new Select("fa_transaction_details");
		$sub1->columns(array("sub_head"));
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
	 * 
	 * get Subhead present in transactions details
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getTransactionSubheadforLedger($location,$activity,$start_date,$end_date,$where)
	{		
		//extract($options);	
		
		$year = date('Y', strtotime($start_date));		
		$sub = new Select("fa_closing_balance");	
		$sub->columns(array("sub_head"))
		    ->where->lessThanOrEqualTo("year",$year);
			 
		$sub0 = new Select("fa_transaction");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4"))
			 ->where->between('voucher_date', $start_date, $end_date)
			 ->OR->lessThan('voucher_date', $start_date);
			 
		$sub1 = new Select("fa_transaction_details");
		$sub1->columns(array("sub_head"));
		
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
	 * 
	 * get Subhead present in transactions details
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getTransactionSubhead($activity,$region,$location,$start_date,$end_date, $where)
	{		
		//extract($options);	
		
		$year = date('Y', strtotime($start_date));		
		$sub = new Select("fa_closing_balance");	
		$sub->columns(array("sub_head"))
		    ->where->lessThanOrEqualTo("year",$year);
			 
		$sub0 = new Select("fa_transaction");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4"))
			 ->where->between('voucher_date', $start_date, $end_date)
                          ->OR->lessThan('voucher_date', $start_date);
			 
		$sub1 = new Select("fa_transaction_details");
		$sub1->columns(array("sub_head"));
		
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
				       ->OR->in('id', $sub)				
			        ->unnest;
                 		$select->order('code');
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray(); 
	    return $results;	  
	}
	/**
	 * 
	 * get Subhead present in transactions details
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getTransactionSubheadforBS($activity,$region,$location,$start_date,$end_date, $where)
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
			 
		$sub0 = new Select("fa_transaction");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4"))
			 ->where->between('voucher_date', $start_date, $end_date)
			 ->OR->where->between('voucher_date', $pre_starting_date, $pre_ending_date);

			 
		$sub1 = new Select("fa_transaction_details");
		$sub1->columns(array("sub_head"));
		
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
				       ->OR->in('id', $sub)				
			        ->unnest;

		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray(); 
	    return $results;	  
	}
        /**
	 * 
	 * get Subhead present in transactions details
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getTransactionSubheadforPLS($activity,$region,$location,$start_date,$end_date, $where)
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
			 
		$sub0 = new Select("fa_transaction");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4"))//changed from 3-4
			 ->where->between('voucher_date', $start_date, $end_date)
			 ->OR->where->between('voucher_date', $pre_starting_date, $pre_ending_date);

			 
		$sub1 = new Select("fa_transaction_details");
		$sub1->columns(array("sub_head"));
		
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
		$where = ( is_array($param) )? $param: array('sh.id' => $param);
		$adapter = $this->adapter;
		
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MAX('.$column.')')
		));
		//$sub0 = $sub0->toArray();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('sh'=>$this->table))
				->join(array('h'=>'fa_head'), 'h.id=sh.head', array('head'=>'name', 'head_id'=>'id'))
				->join(array('g'=>'fa_group'), 'g.id=h.group', array('group'=>'name', 'group_id'=>'id'))
				->join(array('c'=>'fa_class'), 'c.id=g.class', array('class'=>'name', 'class_id'=>'id'))
				->join(array('ht'=>'fa_head_type'), 'ht.id=h.head_type', array('head_type', 'headtype_id'=>'id'))
				->join(array('t'=>'fa_type'), 't.id=sh.type', array('type', 'type_id'=>'id', 'type_class'=>'class'))
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
		$where = ( is_array($param) )? $param: array('sh.id' => $param);
		$adapter = $this->adapter;
	
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MIN('.$column.')')
		));
		//$sub0 = $sub0->toArray();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('sh'=>$this->table))
				->join(array('h'=>'fa_head'), 'h.id=sh.head', array('head'=>'name', 'head_id'=>'id'))
				->join(array('g'=>'fa_group'), 'g.id=h.group', array('group'=>'name', 'group_id'=>'id'))
				->join(array('c'=>'fa_class'), 'c.id=g.class', array('class'=>'name', 'class_id'=>'id'))
				->join(array('ht'=>'fa_head_type'), 'ht.id=h.head_type', array('head_type', 'headtype_id'=>'id'))
				->join(array('t'=>'fa_type'), 't.id=sh.type', array('type', 'type_id'=>'id',  'type_class'=>'class'))
		->where($where)
		->where($column);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return count base on some condition
	 * @param Array $where
	 * @param Int
	 */
	public function getCount($where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			->columns(array('count' => new Expression('COUNT(*)')));
		
		if($where != NULL):
			$select->where($where);
		endif;
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		foreach($results as $row);		
		return $row['count'];
	}
	
	/**
	 * check particular row is present in the table
	 * with given column and its value
	 * @param Array $where
	 * @return Boolean
	 *
	 */
	public function isPresent($where)
	{
		if($where != NULL && ($this->getCount($where) > 0)):
		return TRUE;
		endif;
	
		return FALSE;
	}
	
	/**
	 * Return records of given condition Array
	 * @param Array
	 * @param Array
	 * @return Array
	 */
	public function getSubheadforBS($param, $order=NULL)
	{
		$where = ( is_array($param) )? $param: array('sh.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select->from(array('sh'=>$this->table))
				->join(array('h'=>'fa_head'), 'h.id=sh.head', array('head'=>'name', 'head_id'=>'id'))
				->where($where);
	    if($order != NULL):
	    	$select->order($order);
	    endif;
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * View of Subhead Table	
	 * Return records of given condition Array
	 * @param Array
	 * @param Array
	 * @return Array
	 */
	public function getSubhead($data)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select->from(array('sh'=>$this->table))
				->join(array('h'=>'fa_head'), 'h.id=sh.head', array('head'=>'code', 'head_id'=>'id'))
				->join(array('g'=>'fa_group'), 'g.id=h.group', array('group'=>'code', 'group_id'=>'id'))
				->join(array('c'=>'fa_class'), 'c.id=g.class', array('class'=>'code', 'class_id'=>'id'))
				->join(array('ht'=>'fa_head_type'), 'ht.id=h.head_type', array('head_type', 'headtype_id'=>'id'))
				->join(array('t'=>'fa_type'), 't.id=sh.type', array('type', 'type_id'=>'id', 'type_class'=>'class'))
				->order(array('sh.code DESC'));
		if($data['class'] != '-1'){
			$select->where(array('c.id' => $data['class']));
		}
		if($data['group'] != '-1'){
			$select->where(array('g.id' => $data['group']));
		}
		if($data['head'] != '-1'){
			$select->where(array('h.id' => $data['head']));
		}
		if($data['group'] == '-1'){
			//$select->limit('100');
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;
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
		if ($where != Null)
		{
			$select->where($where);
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
    public function getSubheadfht($param, $column)
    {         
            $where = ( is_array($param) )? $param: array('id' => $param);
            $fetch = array($column);
            $adapter = $this->adapter;       
            $sql = new Sql($adapter);
            $select = $sql->select();
            $select->from(array('sh'=>$this->table))
			->join(array('h'=>'fa_head'), 'h.id = sh.head')
			->join(array('ht'=>'fa_head_type'), 'ht.id = h.head_type');
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
}


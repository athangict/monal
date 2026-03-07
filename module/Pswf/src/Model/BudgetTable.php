<?php
namespace Accounts\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class BudgetTable extends AbstractTableGateway 
{
	protected $table = 'fa_budget_forecasting'; //tablename

	public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
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
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
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
	    $select->from(array("cb" => $this->table))
	    	   ->join(array("sh"=>"fa_sub_head"),"sh.id=cb.sub_head", array("sub_head"=>"sub_head","sub_head_id" => "id"));
	    
	    $selectString = $sql->getSqlStringForSqlObject($select);
	    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    return $results;
	}
	/**
	 * Total sum in Class
	 * @return Array
	 */
	public function getsumbyclass($classid,$year,$month,$location,$region,$column)
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
		$select->columns(array( new Expression('SUM('.$column.') as totalofclass')))
		       ->where(array("c.id" => $classid))
			   ->where(array("bt.month" => $month));
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
	public function getsumbygroup($groupid,$year,$month,$location,$region,$column)
	{  
	    $adapter = $this->adapter;
	    $sql = new Sql($adapter);
	    $select = $sql->select();
	    $select->from(array("bt" => $this->table))
	    	   ->join(array("h"=>"fa_head"),"h.id=bt.head", array("head"=>"code","head_id" => "id"))
			   ->join(array("g"=>"fa_group"),"g.id=h.group", array("group"=>"code","group_id" => "id"))
			   ->join(array("lc"=>"adm_location"),"lc.id=bt.location", array("locations"=>"location","location_id" => "id"))
			   ->join(array("rg"=>"adm_region"),"rg.id=lc.region", array("regions"=>"region","region_id" => "id"));
		$select->columns(array( new Expression('SUM('.$column.') as totalofgroup')))
		       ->where(array("g.id" => $groupid))
			   ->where(array("bt.month" => $month));
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
	public function getsumbyhead($head,$year,$month,$location,$region,$column)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array("bt" => $this->table)) 
		       ->join(array("lc"=>"adm_location"),"lc.id=bt.location", array("locations"=>"location","location_id" => "id"))
			   ->join(array("rg"=>"adm_region"),"rg.id=lc.region", array("regions"=>"region","region_id" => "id"));
	    $select->columns(array( new Expression('SUM('.$column.') as totalofhead')))
		       ->where(array("bt.head" => $head))
			   ->where(array("bt.month" => $month));
		if($region != '-1'){
			$select->where(array('rg.id'=>$region));
		} 
		if($year != '-1'){
			$select->where(array('year'=>$year));
		} 
		if($month != '-1'){
			$select->where(array('bt.month'=>$month));
		} 
		if($location != '-1'){
			$select->where(array('lc.location'=>$location));
		}  	
	    if($head != '-1'){
			$select->where(array('bt.head'=>$head));
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
	public function getsubheadbased($subhead,$year,$location,$month,$region)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array("bt" => $this->table)) 
		       ->join(array("lc"=>"adm_location"),"lc.id=bt.location", array("locations"=>"location","location_id" => "id"))
			   ->join(array("rg"=>"adm_region"),"rg.id=lc.region", array("regions"=>"region","region_id" => "id"));
		if($region != '-1'){
			$select->where(array('rg.id'=>$region));
		} 
		if($year != '-1'){
			$select->where(array('year'=>$year));
		} 
		if($month != '-1'){
			$select->where(array('month'=>$month));
		} 
		if($location != '-1'){
			$select->where(array('lc.location'=>$location));
		}  	
	    if($subhead != '-1'){
			$select->where(array('sub_head'=>$subhead));
		} 
		
		$selectString = $sql->getSqlStringForSqlObject($select);
	    //echo $selectString;exit;
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
		//echo  $selectString;
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
	public function getMax($column,$where=NULL)
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
	
}


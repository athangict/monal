<?php
namespace Fleet\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class VehicleRepairMaintaneseDtlsTable extends AbstractTableGateway 
{
	protected $table = 'tp_repair_dtls'; //tablename

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
	    $select->from(array('rmd' => $this->table))
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
		$where = ( is_array($param) )? $param: array('rmd.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('rmd' => $this->table))
					->join(array('rm'=>'tp_repair'),'rm.id = rmd.repair', array())
					//->join(array('vp'=>'tp_vehicle_parts'),'vp.id = rmd.service_item', array('service_item'=>'code','item_id' => 'id'))
			    ->where($where)
	    	   ->order(array('id DESC'));
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
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
	 * get sum by RID
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSumbyRID($start_date,$end_date, $column, $where)
	{		
		//extract($options);
		
		$sub0 = new Select("tp_repair");
		$sub0->columns(array("id"))
			 ->where(array("status" => "3")) //committed status
			 ->where->between('work_order_date', $start_date, $end_date);
			 
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns(array( new Expression('SUM('.$column.') as total')))
			   ->where($where);
		$select->where->in('repair', $sub0);
    
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['total'];
	   endforeach;  
	   
	   return $sum;	    
	}
		/**
	 * Return sum value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Decimal
	 */
	public function getSumIncome($column,$location, $subhead, $start_date, $end_date,$type)
	{
		$sub0 = new Select("tp_repair");
		$sub0->columns(array("id"))
			 ->where(array('status' => '4','type' => $type)) // committed status
			 ->where->between('work_order_date', $start_date, $end_date);

		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
			'sum' => new Expression('SUM('.$column.')')
		));
		$select->where->in('repair', $sub0);

		if ($subhead != '-1') {
			$select->where(array('service' => $subhead));
		}if ($location != '-1') {
			$select->where(array('location' => $subhead));
		}

		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		$sum = 0;
		foreach ($results as $result) {
			$sum = $result['sum'];
		}

		return number_format((float)$sum, 2, '.', ',');
	}
     /**
	 * 
	 * get repair details id present in repair 
	 * @param Date $start_date
	 * @param Date $end_date
	 * 
	 **/
	public function getrepairDetails($start_date,$end_date,$where)
	{		
		//extract($start_date,$end_date);	
		
		$sub1 = new Select("tp_repair");
		$sub1->columns(array("id"))
	        ->where(array("status" => "3"))		
		    ->where->between('work_order_date',$start_date,$end_date);
		
		
		$adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
		       ->where($where)
		       ->where->in('repair', $sub1);
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray(); 
	    return $results;	  
	}
	/**
	 * Vehicle Report
	 * Return distinct transports 
	 * @param Int | Array
	 * @return Array
	 */
	public function getDistinctTransport($location,$license_plate,$start_date,$end_date)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('rmd'=>$this->table))
				->join(array('rm'=>'tp_repair'),'rm.id = rmd.repair')
			   ->join(array('a'=>'tp_transport'),'a.id = rmd.license_plate',array('license_plate'=>'license_plate','license_plate' =>'id'))
			  // ->join(array('l'=>'adm_location'),'l.id = rm.location',array('location'=>'location','location_id' =>'id'))
			  // ->join(array('hr'=>'hr_employee'),'hr.id = rm.driver', array('driver'=>'full_name','driver_id' => 'id'))
			  // ->columns(array(new Expression('DISTINCT(rm.transport) as transport')))
			   //->order(array('rmd.license_plate'))
			  // ->where(array("rm.status" => "4"))	
			   ->where->between('rm.work_order_date',$start_date,$end_date);
			  if($location != '-1'){
				$select->where(array('rmd.location'=>$location));
			}  
			if($license_plate != '-1'){
				$select->where(array('rmd.license_plate'=>$license_plate));
			}  
		$selectString = $sql->getSqlStringForSqlObject($select);
	  //  echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}	
	/**
	 * Vehicle Report
	 * Return distinct transports 
	 * @param Int | Array
	 * @return Array
	 */
	public function getDistinctIncome($column, $location, $license_plate, $start_date, $end_date, $type)
	{
		$sub0 = new Select("tp_repair");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4","type"=>$type)) // committed status
			 ->where->between('work_order_date', $start_date, $end_date);
		
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();

		$select->from($this->table)
			   ->columns(array(
				'sum' => new Expression('SUM('.$column.')'),
			))
			  ->where->in('repair', $sub0);;

		if ($location != '-1') {
			$select->where(array('location' => $location));
		}
		if ($license_plate != '-1') {
			$select->where(array('license_plate' => $license_plate));
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
	   // echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		//echo '<pre>';print_r($results);//exit;
		$sum = 0;
		foreach ($results as $result) {
			$sum = $result['sum'];
		}

		return number_format((float)$sum, 2, '.', ',');
	}
	/**
	 * get sum by RID
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getDistinctClass($location, $license, $start_date, $end_date)
	{
		$sub0 = new Select("tp_repair");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4")) // committed status
			 ->where->between('work_order_date', $start_date, $end_date);

		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();

		$select->from(array('rmd' => $this->table))
			   ->join(array('h' => 'fa_head'), 'h.id = rmd.service')
			   ->join(array('g' => 'fa_group'), 'g.id = h.group')
			   ->join(array('c' => 'fa_class'), 'c.id = g.class', array('class' => 'code', 'class_id' => 'id'))
			   ->group('c.id')
			   ->where->in('rmd.repair', $sub0);

		if ($location != '-1') {
			$select->where(array('rmd.location' => $location));
		}

		if ($license != '-1') {
			$select->where(array('rmd.license_plate' => $license));
		}

		$selectString = $sql->getSqlStringForSqlObject($select);
		// echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * get sum by RID
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getDistinctGroup($column,$location, $license, $start_date, $end_date,$where)
	{
		/*$sub0 = new Select("tp_repair");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4")) // committed status
			 ->where->between('work_order_date', $start_date, $end_date);

		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();

		$select->from(array('rmd' => $this->table))
				->join(array('tp' => 'tp_vehicle_register'), 'tp.subhead = rmd.service_item')
			   ->join(array('h' => 'fa_head'), 'h.id = rmd.service')
			   ->join(array('g' => 'fa_group'), 'g.id = h.group',array('group' => 'name', 'group_id' => 'id'))

			   ->columns(array(
				'sum' => new Expression('SUM('.$column.')'),
			))
			   ->group('g.id')
			   ->where($where)
			   ->where->in('rmd.repair', $sub0);

		if ($location != '-1') {
			$select->where(array('rmd.location' => $location));
		}

		if ($license != '-1') {
			$select->where(array('tp.license_plate' => $license));
		}

		$selectString = $sql->getSqlStringForSqlObject($select);
		// echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results; */
		$adapter = $this->adapter;
		$sql = new Sql($adapter);

		// --- Subquery 0: get IDs with status 4 and date range ---
		$sub0 = new Select("tp_repair");
		$sub0->columns(["id"])
			 ->where(["status" => "4"])
			 ->where->between('work_order_date', $start_date, $end_date);

		// --- Query 1: From repair_maintenance_detail ---
		$select1 = $sql->select();
		$select1->from(['rmd' => $this->table])
			->columns([
				'sum' => new Expression('SUM('.$column.')'),
				'sumSO' => new Expression('0') // add zero for consistency with union
			])
			->join(['tp' => 'tp_vehicle_register'], 'tp.subhead = rmd.service_item', [])
			->join(['h' => 'fa_head'], 'h.id = rmd.service', [])
			->join(['g' => 'fa_group'], 'g.id = h.group', [
				'group_id' => 'id',
				'group' => 'name'
			])
			->where($where)
			->where->in('rmd.repair', $sub0);

		if ($location != '-1') {
			$select1->where(['rmd.location' => $location]);
		}
		if ($license != '-1') {
			$select1->where(['tp.license_plate' => $license]);
		}

		// --- Query 2: From tp_sanction_order ---
		$select2 = $sql->select();
		$select2->from(['so' => 'tp_sanction_order'])
    ->columns([
        'sum' => new Expression('0'),
        'sumSO' => new Expression('SUM(so.amount)')
    ])
    ->join(['tp' => 'tp_vehicle_register'], 'tp.subhead = so.subhead', [])
    ->join(['h' => 'fa_head'], 'h.id = tp.head', [])
    ->join(['g' => 'fa_group'], 'g.id = h.group', [
        'group_id' => 'id',
        'group' => 'name'
    ])
	->where(array('so.status'=>4))
			->where->between('so.sanction_order_date', $start_date, $end_date);

		if ($location != '-1') {
			$select2->where(['so.location' => $location]);
		}
		if ($license != '-1') {
			$select2->where(['tp.license_plate' => $license]);
		}

		// --- Combine with UNION and wrap in new SELECT to get DISTINCT ---
		$select1Sql = $sql->getSqlStringForSqlObject($select1);
		$select2Sql = $sql->getSqlStringForSqlObject($select2);

		//echo "\n--- Select 1 (from rmd): ---\n" . $select1Sql;
		//echo "\n\n--- Select 2 (from so): ---\n" . $select2Sql;

		//$unionSql = "SELECT DISTINCT group_id, `group` FROM (({$select1Sql}) UNION ({$select2Sql})) AS grp_combined";
				$unionSql = "
			SELECT group_id, `group`, SUM(sum) AS sum, SUM(sumSO) AS sumSO
			FROM (
				{$select1Sql}
				UNION ALL
				{$select2Sql}
			) AS grp_combined
			GROUP BY group_id, `group`
		";
		//echo "\n\n--- Final UNION SQL ---\n" . $unionSql;

		//exit; // prevent query execution during debugging

		$results = $adapter->query($unionSql, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
		
	}
	/**
	 * get sum by RID
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getDistinctHead($column,$location, $license, $start_date, $end_date,$where)
	{
		$sub0 = new Select("tp_repair");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4")) // committed status
			 ->where->between('work_order_date', $start_date, $end_date);

		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();

		$select->from(array('rmd' => $this->table))
		->join(array('tp' => 'tp_vehicle_register'), 'tp.subhead = rmd.service_item')
			   ->join(array('h' => 'fa_head'), 'h.id = rmd.service',array('head' => 'name', 'head_id' => 'id'))
			   ->columns(array(
				'sum' => new Expression('SUM('.$column.')'),
			))
			   ->group('h.id')
			   ->where($where)
			   ->where->in('rmd.repair', $sub0);

		if ($location != '-1') {
			$select->where(array('rmd.location' => $location));
		}

		if ($license != '-1') {
			$select->where(array('tp.license_plate' => $license));
		}

		$selectString = $sql->getSqlStringForSqlObject($select);
		// echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * get sum by RID
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getDistinctSubHead($column,$location, $license, $start_date, $end_date,$where)
	{
		$sub0 = new Select("tp_repair");
		$sub0->columns(array("id"))
			 ->where(array("status" => "4")) // committed status
			 ->where->between('work_order_date', $start_date, $end_date);

		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();

		$select->from(array('rmd' => $this->table))
				->join(array('tp' => 'tp_vehicle_register'), 'tp.subhead = rmd.service_item')
			   ->join(array('sh' => 'fa_sub_head'), 'sh.id = rmd.service_item',array('subhead' => 'name', 'subhead_id' => 'id'))
			   ->columns(array(
				'sum' => new Expression('SUM('.$column.')'),
			))
			   ->group('sh.id')
			   ->where($where)
			   ->where->in('rmd.repair', $sub0);

		if ($location != '-1') {
			$select->where(array('rmd.location' => $location));
		}

		if ($license != '-1') {
			$select->where(array('tp.license_plate' => $license));
		}

		$selectString = $sql->getSqlStringForSqlObject($select);
		// echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
}

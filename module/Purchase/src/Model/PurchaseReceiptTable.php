<?php
namespace Purchase\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class PurchaseReceiptTable extends AbstractTableGateway 
{
	protected $table = 'pur_purchase_receipt';   //tablename

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
	    $select->from(array('pr'=>$this->table))
	    	   //->join(array('p'=>'fa_party'),'p.id = pr.supplier', array('supplier'=>'code','supplier_name'=>'name','supplier_id' => 'id'))
	    	   //->join(array('l'=>'adm_location'),'l.id = pr.location', array('location'=>'location','location_id' => 'id'))
	          // ->join(array('a'=>'adm_activity'),'a.id = pr.activity', array('activity'=>'activity','activity_id' => 'id'))
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
	public function getDateWise($column,$year,$month,$location)
	{	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
                 ->columns(array(
					'id','prn_no','purchase_order','prn_date','location','supplier',
					'note','status','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ))->having(array('year' => $year))
	    	   ->order(array('id DESC'));
			   if($location != '-1'):
					$select->where(array('location' => $location));
			   endif;
			   if($month != '-1'):
					$select->having(array('month' => $month));
			   endif;
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
				->where->between('prn_date', $start_date, $end_date);
				
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
			    //->join(array('p'=>'fa_party'),'p.id = pr.supplier', array('supplier'=>'code', 'supplier_name'=> 'name', 'supplier_id' => 'id'))
			   // ->join(array('l'=>'adm_location'),'l.id = pr.location', array('location'=>'location','location_id' => 'id'))
		        ->where($where)
		        ->order(array('id DESC'));
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
			return (sizeof($resultSet)>0)?FALSE:TRUE;
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
		public function getMonthlyPR($prefix_PR_code)
	{  
		$adapter = $this->adapter;			
		$sql = new Sql($adapter);		
		$select = $sql->select();
		$select->from($this->table);		
		$select->where->like('prn_no', $prefix_PR_code."%");	
			
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
		$select->from(array('p' => $this->table))
			   ->join(array('pd'=>'pur_pr_details'), 'p.id = pd.purchase_receipt', array())
			   ->join(array('b'=>'st_batch'), 'b.id = pd.batch', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThan($col_date,$start_date);
				$select->where->notEqualTo('pd.batch',NULL);
				$select->where->EqualTo('b.status','3');
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				//$select->where->notEqualTo('pd.batch','0');
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
		$select->from(array('p' => $this->table))
			   ->join(array('pd'=>'pur_pr_details'), 'p.id = pd.purchase_receipt', array())
			   ->join(array('b'=>'st_batch'), 'b.id = pd.batch', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				$select->where->between($col_date, $start_date, $end_date);
				$select->where->notEqualTo('pd.batch',NULL);
				$select->where->EqualTo('b.status','3');
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				//$select->where->notEqualTo('pd.batch','0');
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
	
		return $column;
	}
/**
	 * Return count value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getCount($column, $where=NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'count' => new Expression('Count('.$column.')')
		));
		
		if($where!=NULL){
			$select->where($where);
		}
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
		  $column =  $result['count'];
		endforeach;	
		return $column;
	}
/**
	 * STOCK MOVEMENT 3
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function getDistinctIB($start_date, $end_date, $location, $param, $col_date, $col_loc,$batch_array)
	{  
		$arr = array();
		foreach($batch_array as $batch_arr):
			array_push($arr, $batch_arr['id']);
		endforeach;
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('p' => $this->table))
			   ->join(array('pd'=>'pur_pr_details'), 'p.id = pd.purchase_receipt', array())
			   ->join(array('b'=>'st_batch'), 'b.id = pd.batch', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(pd.batch)')
			    ));
				$select->where($where);
				$select->where->between($col_date, $start_date, $end_date);
				$select->where->notEqualTo('pd.batch',NULL);
				$select->where->EqualTo('b.status','3');
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if(sizeof($arr)>0):
					$select->where->notIn('pd.batch',$arr);
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
	public function filterSMBatch($end_date, $location, $param, $col_date, $col_loc,$array,$item)
	{  
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('p' => $this->table))
			   ->join(array('pd'=>'pur_pr_details'), 'p.id = pd.purchase_receipt', array())
			   ->join(array('b'=>'st_batch'), 'b.id = pd.batch', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(pd.batch)')
			    ));
				$select->where($where);
				$select->where->lessThanOrEqualTo($col_date,$end_date);
				$select->where->notEqualTo('pd.batch',NULL);
				$select->where->EqualTo('b.status','3');
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('pd.item'=>$item));
			    }
				if(sizeof($array)>0):
					$select->where->notIn('pd.batch',$array);
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
		$select->from(array('p' => $this->table))
			   ->join(array('pd'=>'pur_pr_details'), 'p.id = pd.purchase_receipt', array())
			   ->join(array('b'=>'st_batch'), 'b.id = pd.batch', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThan($col_date,$start_date);
				$select->where->notEqualTo('pd.batch',NULL);
				$select->where->EqualTo('b.status','3');
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				//$select->where->notEqualTo('pd.batch','0');
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
		$select->from(array('p' => $this->table))
			   ->join(array('pd'=>'pur_pr_details'), 'p.id = pd.purchase_receipt', array())
			   ->join(array('b'=>'st_batch'), 'b.id = pd.batch', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				$select->where->between($col_date, $start_date, $end_date);
				$select->where->notEqualTo('pd.batch',NULL);
				$select->where->EqualTo('b.status','3');
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				//$select->where->notEqualTo('pd.batch','0');
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
	
		return $column;
	}
	/**
	 * STOCK MOVEMENT 4 - fetch closing sum
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSMDetails($start_date,$end_date,$location,$batch_id,$item)
	{   	
        $adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('p' => $this->table))
			   ->join(array('pd'=>'pur_pr_details'), 'p.id = pd.purchase_receipt', array())
			   ->join(array('b'=>'st_batch'), 'b.id = pd.batch', array());
				$select->columns(array(
					'sum' => new Expression('SUM(pd.basic_sound_qty)')
			    ));
				$select->where->between('prn_date', $start_date, $end_date);
				$select->where->EqualTo('b.status','3');
			    $select->where(array('pd.batch'=>$batch_id));
				if($location != '-1'){
					$select->where(array('p.location' => $location));
				}
				if($item != '-1'){
					$select->where(array('pd.item' => $item));
				}
		$dispatch = $this->getSMDSum($start_date,$end_date,$location,$batch_id,$item);
		$in_receipt = $this->getSMRSum($start_date,$end_date,$location,$batch_id,$item);
		$cash_sale = $this->getSMCSSum($start_date,$end_date,$location,$batch_id,$item);
		$credit_sale = $this->getSMCRSSum($start_date,$end_date,$location,$batch_id,$item);
		$scheme = $this->getSMFQSum($start_date,$end_date,$location,$batch_id,$item);
		$fsadjustment = $this->getSMFSSum($start_date,$end_date,$location,$batch_id,$item);
		$dump = $this->getSMDPSum($start_date,$end_date,$location,$batch_id,$item);
		$sale_promotion = $this->getSMSPSum($start_date,$end_date,$location,$batch_id,$item);
		$shortage = $this->getSMSSum($start_date,$end_date,$location,$batch_id,$item);
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		foreach ($results as $result);
		$purchase = $result['sum'];
		$arries = array('purchase'=>$purchase,'dispatch'=>$dispatch,'cash_sale'=>$cash_sale,'credit_sale'=>$credit_sale,'scheme'=>$scheme,
		'fsadjustment'=>$fsadjustment,'dump'=>$dump,'sale_promotion'=>$sale_promotion,'shortage'=>$shortage);
		$arries = array($arries);
		return $arries;
	}
	/**
	 * get sum by batch
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSMDSum($start_date,$end_date,$location,$batch_id,$item)
	{	
        $adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select = new Select(array('dd'=>"st_dispatch_details"));
		$select ->join(array('d'=>'st_dispatch'),'d.id = dd.dispatch', array());
		$select->columns(array('sum' => new Expression('SUM(dd.basic_quantity)')))
		       ->where(array('dd.batch'=>$batch_id,'dd.item'=>$item))
		       ->where(array('d.status'=>array(2,10,3)))
			   ->where->between('d.dispatch_date',$start_date,$end_date);
		if($location != '-1'){
		$select->where(array('d.from_location' => $location));}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['sum'];
	   endforeach;  
	   
	   return $sum;	    
	}
		/**
	 * get sum by batch
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSMRSum($start_date,$end_date,$location,$batch_id,$item)
	{	
	    $adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select = new Select(array('dd'=>"st_dispatch_details"));
		$select ->join(array('d'=>'st_dispatch'),'d.id = dd.dispatch', array());
		$select->columns(array('sum' => new Expression('SUM(dd.accepted_qty)')))
				->where(array('dd.batch'=>$batch_id,'dd.item'=>$item))
				->where(array('d.status'=>array(3)))
				->where->between('d.received_on',$start_date,$end_date);
		if($location != '-1'){
		$select->where(array('d.to_location' => $location));}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['sum'];
	   endforeach;  
	   
	   return $sum;	    
	}
	/**
	 * get sum by batch
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSMCSSum($start_date,$end_date,$location,$batch_id,$item)
	{	
	    $adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select = new Select(array('sd'=>"sl_sales_dtls"));
		$select ->join(array('s'=>'sl_sales'),'s.sales_no = sd.sales', array());
		$select->columns(array('sum' => new Expression('SUM(sd.basic_quantity)')))
		       ->where(array('sd.batch'=>$batch_id,'sd.item'=>$item))
		       ->where(array('s.status'=>3,'s.credit'=>array(0)))
			   ->where->between('s.sales_date',$start_date,$end_date);
		if($location != '-1'){
		$select->where(array('s.location' => $location));}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['sum'];
	   endforeach;  
	   
	   return $sum;	    
	}
	/**
	 * get sum by batch
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSMCRSSum($start_date,$end_date,$location,$batch_id,$item)
	{	
	    $adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select = new Select(array('sd'=>"sl_sales_dtls"));
		$select ->join(array('s'=>'sl_sales'),'s.sales_no = sd.sales', array());
		$select->columns(array('sum' => new Expression('SUM(sd.basic_quantity)')))
		       ->where(array('sd.batch'=>$batch_id,'sd.item'=>$item))
		       ->where(array('s.status'=>3,'s.credit'=>array(1)))
			   ->where->between('s.sales_date',$start_date,$end_date);
		if($location != '-1'){
		$select->where(array('s.location' => $location));}
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['sum'];
	   endforeach;  
	   
	   return $sum;	    
	}
		/**
	 * get sum by batch
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSMFQSum($start_date,$end_date,$location,$batch_id,$item)
	{	
        $adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select = new Select(array('sd'=>"sl_sales_dtls"));
		$select ->join(array('s'=>'sl_sales'),'s.sales_no = sd.sales', array());
		$select->columns(array('sum' => new Expression('SUM(sd.discount_qty)')))
		       ->where(array('sd.batch'=>$batch_id,'sd.free_item'=>$item))
		       ->where(array('s.status'=>3,'s.credit'=>array(1,0)))
			   ->where->between('s.sales_date',$start_date,$end_date);
        if($location != '-1'){
		$select->where(array('s.location' => $location));}				  
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['sum'];
	   endforeach;  
	   
	   return $sum;	    
	}
	/**
	 * get sum by batch
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSMFSSum($start_date,$end_date,$location,$batch_id,$item)
	{	
	    $adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select = new Select(array('sd'=>"st_sam_details"));
		$select ->join(array('s'=>'st_sam'),'s.id = sd.sam', array());
		$select ->columns(array('sum' => new Expression('SUM(sd.basic_quantity)')))
		        ->where(array('sd.batch'=>$batch_id,'sd.sam_type'=>array('5'),'sd.item'=>$item))
		        ->where(array('s.status'=>3))
			    ->where->between('s.sam_date',$start_date,$end_date);
		if($location != '-1'){
		$select->where(array('s.location' => $location));}	
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['sum'];
	   endforeach;  
	   
	   return $sum;	    
	}
	/**
	 * get sum by batch
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSMDPSum($start_date,$end_date,$location,$batch_id,$item)
	{	
	    $adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
        $select = new Select(array('sd'=>"st_sam_details"));
		$select ->join(array('s'=>'st_sam'),'s.id = sd.sam', array());
		$select ->columns(array('sum' => new Expression('SUM(sd.basic_quantity)')))
		        ->where(array('sd.batch'=>$batch_id,'sd.sam_type'=>array('1','2'),'sd.item'=>$item))
		        ->where(array('s.status'=>3))
			    ->where->between('s.sam_date',$start_date,$end_date);
		if($location != '-1'){
		$select->where(array('s.location' => $location));}	
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['sum'];
	   endforeach;  
	   
	   return $sum;	    
	}
    /**
	 * get sum by batch
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSMSPSum($start_date,$end_date,$location,$batch_id,$item)
	{	
	    $adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
        $select = new Select(array('sd'=>"st_sam_details"));
		$select ->join(array('s'=>'st_sam'),'s.id = sd.sam', array());
		$select ->columns(array('sum' => new Expression('SUM(sd.basic_quantity)')))
		        ->where(array('sd.batch'=>$batch_id,'sd.sam_type'=>array(4),'sd.item'=>$item))
		        ->where(array('s.status'=>3))
			    ->where->between('s.sam_date',$start_date,$end_date);
		if($location != '-1'){
		$select->where(array('s.location' => $location));}	
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['sum'];
	   endforeach;  
	   
	   return $sum;	    
	}
	/**
	 * get sum by batch
	 * @param String $column
	 * @param Array $options
	 * @param String $column
	 * @param Int $group
	 * @return int
	 */
	public function getSMSSum($start_date,$end_date,$location,$batch_id,$item)
	{	
	    $adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
        $select = new Select(array('sd'=>"st_sam_details"));
		$select ->join(array('s'=>'st_sam'),'s.id = sd.sam', array());
		$select ->columns(array('sum' => new Expression('SUM(sd.basic_quantity)')))
		        ->where(array('sd.batch'=>$batch_id,'sd.sam_type'=>array(3),'sd.item'=>$item))
		        ->where(array('s.status'=>3))
			    ->where->between('s.sam_date',$start_date,$end_date);
		if($location != '-1'){
		$select->where(array('s.location' => $location));}	
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
		
	   foreach ($results as $result):
		  $sum =  $result['sum'];
	   endforeach;  
	   
	   return $sum;	    
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
		$select->from(array('p' => $this->table))
			->join(array('pd' => 'pur_pr_details'), 'p.id = pd.purchase_receipt')
			->columns(array(
				'sum' => new Expression('SUM(' . 'pd.accept_qty' . ')')
			))
			->where($where)
			->where->between('p.prn_date', $data['start_date'], $data['end_date']);

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
		public function getSumPre($location,$item, $data)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('p' => $this->table))
			->join(array('pd' => 'pur_pr_details'), 'p.id = pd.purchase_receipt')
			->columns(array(
				'sum' => new Expression('SUM(' . 'pd.accept_qty' . ')')
			))
			->where->lessThan('p.prn_date', $data['start_date']);

		// Use the where object for the between conditions
		$select->where(array('p.location' => $location, 'pd.item' => $item,'p.status'=>4));
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
		public function getrate($param, $data)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('p' => $this->table))
			->join(array('pd' => 'pur_pr_details'), 'p.id = pd.purchase_receipt')
			->columns(array(
				'rate' => new Expression('rate')
			))
			->where($where)
			->where->between('p.prn_date', $data['start_date'], $data['end_date']);

		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		$result = current($results) ?: array('rate' => 0);

		// Return the sum
		return $result['rate'];
	}
	/**
	 * Return id's|columns'value  which is not present in given array
	 * @param Array $param
	 * @param String column
	 * @return Array
	 */
	public function getpr($param)
	{
	$param = (is_array($param)) ? $param : array($param);
    $adapter = $this->adapter;
    $sql = new Sql($adapter);
    $select = new Select();
    
    // Constructing the SQL query
    $select->from(array('r' => $this->table))
           ->join(array('b' => 'pur_payment'), 'b.prn_no = r.id', array(), 'left')
           ->where($param)
           ->where->isNull('b.prn_no');
    
    // Get the SQL string
    $selectString = $sql->getSqlStringForSqlObject($select);
    
    // Execute the query
    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
    //print_r($results);exit;
    return $results;
	}
}

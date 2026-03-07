<?php
namespace Stock\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class OpeningStockTable extends AbstractTableGateway 
{
	protected $table = 'st_opening_stock'; //tablename

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
				->order(array('id ASC'));
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
	 * get By Activity ID
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getByActivity($item)
	{
		//$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('op'=>$this->table))
	          // ->join(array('i'=>'st_items'), 'i.id = op.item', array('item_code' => 'name' ,'activity' => 'activity'))
			  // ->join(array('u' =>'st_uom'), 'u.id = op.uom', array('uom_code' => 'code'))
			   ->order(array('op.id ASC'));
		if($item != '-1'):	   
		    $select->where(array('op.item' => $item));
		endif;	   
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
         /**
	 * get By Author
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
        public function getByAuthor($user_id)
	{
		//$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('op'=>$this->table))
	           ->join(array('i'=>'st_items'), 'i.id = op.item', array('item_code' => 'name'))
			   ->join(array('u' =>'st_uom'), 'u.id = op.uom', array('uom_code' => 'code'))
			   ->order(array('op.id ASC'))
		       ->where(array('i.activity' => 7));  
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * STOCK MOVEMENT
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
	 * STOCK MOVEMENT
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function getSMOpeningSUM($start_date, $location, $param)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('o' => $this->table))
			   ->join(array('od'=>'st_opening_stock_dtls'), 'o.id = od.opening_stock', array());
				$select->columns(array(
					'sum' => new Expression('SUM(od.quantity)')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThanOrEqualTo('o.opening_date',$start_date);
				if($location != '-1'){
					$select->where(array('od.location' => $location));
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
	 * Return distinct batch 
	 * @param Int | Array
	 * @return Array
	 */
	public function filterSMBatch($start_date, $location, $param, $col_date, $col_loc,$array,$item)
	{
		$where = ( is_array($param) )? $param: array('b.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('o'=>$this->table))
			   ->join(array('od'=>'st_opening_stock_dtls'), 'o.id = od.opening_stock', array())
			   ->join(array('b'=>'st_batch'), 'o.batch = b.batch', array())
			   ->join(array('i'=>'st_items'), 'i.id = b.item', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(b.id)')
			    ));
				$select->where($where);
				$select->where->lessThanOrEqualTo($col_date,$start_date);
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('o.item'=>$item));
			    }
				if(sizeof($array)>0):
					$select->where->notIn('b.id',$array);
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
	public function fetchSMOpeningSUM($start_date, $location, $param)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('o' => $this->table))
			   ->join(array('od'=>'st_opening_stock_dtls'), 'o.id = od.opening_stock', array());
				$select->columns(array(
					'sum' => new Expression('SUM(od.quantity)')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThanOrEqualTo('o.opening_date',$start_date);
				if($location != '-1'){
					$select->where(array('od.location' => $location));
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
	public function checkOpeningStock($start_date,$location,$batch_id,$batch,$item)
	{   	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('o' => $this->table))
			   ->join(array('od'=>'st_opening_stock_dtls'), 'o.id = od.opening_stock', array());
				$select->columns(array(
					'sum' => new Expression('SUM(od.quantity)')
			    ))
				->where(array('o.batch'=>$batch,'o.item'=>$item));
	    $select->where->lessThanOrEqualTo('o.opening_date',$start_date);
		if($location != '-1'){
	    $select->where(array('od.location' => $location));}
		$op_purchase = $this->getPOSum($start_date,$location,$batch_id,$item);
		$op_dispatch = $this->getDOSum($start_date,$location,$batch_id,$item);
		$op_receive = $this->getROSum($start_date,$location,$batch_id,$item);
		$op_sale = $this->getSOSum($start_date,$location,$batch_id,$item);
		$op_FA = $this->getFSOSum($start_date,$location,$batch_id,$item);
		$op_sam = $this->getSAMOSum($start_date,$location,$batch_id,$item);
		$op_FIQ = $this->getFIOSum($start_date,$location,$batch_id,$item);
		$selectString = $sql->getSqlStringForSqlObject($select);
		$openings = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		foreach ($openings as $opening);
		$op_opening = $opening['sum'];
		$arries = array('op_purchase'=>$op_purchase,'op_dispatch'=>$op_dispatch,'op_receive'=>$op_receive,'op_sale'=>$op_sale,'op_FA'=>$op_FA,'op_sam'=>$op_sam,
		'op_FIQ'=>$op_FIQ,'op_opening'=>$op_opening);
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
	public function getPOSum($start_date,$location,$batch_id,$item)
	{	
        $adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select = new Select(array('pd'=>"pur_pr_details"));
		$select ->join(array('p'=>'pur_purchase_receipt'),'p.id = pd.purchase_receipt', array());
		$select->columns(array('sum' => new Expression('SUM(pd.basic_sound_qty)')))
				->where(array('pd.batch'=>$batch_id,'pd.item'=>$item))
				->where(array('p.status'=>3))
				->where->notEqualTo('pd.batch',NULL)
				->where->lessThan('p.prn_date',$start_date);
		if($location != '-1'){
		$select->where(array('p.location' => $location));}			
		$selectString = $sql->getSqlStringForSqlObject($select);
	//	echo $selectString; exit;
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
	public function getDOSum($start_date,$location,$batch_id,$item)
	{	
	    $adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select = new Select(array('dd'=>"st_dispatch_details"));
		$select ->join(array('d'=>'st_dispatch'),'d.id = dd.dispatch', array());
		$select->columns(array('sum' => new Expression('SUM(dd.basic_quantity)')))
		       ->where(array('dd.batch'=>$batch_id,'dd.item'=>$item))
		       ->where(array('d.status'=>array(2,10,3)))
			   ->where->lessThan('d.dispatch_date',$start_date);
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
	public function getROSum($start_date,$location,$batch_id,$item)
	{	
	    $adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select = new Select(array('dd'=>"st_dispatch_details"));
		$select ->join(array('d'=>'st_dispatch'),'d.id = dd.dispatch', array());
		$select->columns(array('sum' => new Expression('SUM(dd.accepted_qty)')))
				->where(array('dd.batch'=>$batch_id,'dd.item'=>$item))
				->where(array('d.status'=>array(3)))
				->where->lessThan('d.received_on',$start_date);
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
	public function getSOSum($start_date,$location,$batch_id,$item)
	{	
	    $adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select = new Select(array('sd'=>"sl_sales_dtls"));
		$select ->join(array('s'=>'sl_sales'),'s.sales_no = sd.sales', array());
		$select->columns(array('sum' => new Expression('SUM(sd.basic_quantity)')))
		       ->where(array('sd.batch'=>$batch_id,'sd.item'=>$item))
		       ->where(array('s.status'=>3,'s.credit'=>array(1,0)))
			   ->where->lessThan('s.sales_date',$start_date);
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
	public function getFSOSum($start_date,$location,$batch_id,$item)
	{	
	    $adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
	    $select = new Select(array('sd'=>"st_sam_details"));
		$select ->join(array('s'=>'st_sam'),'s.id = sd.sam', array());
		$select ->columns(array('sum' => new Expression('SUM(sd.basic_quantity)')))
		        ->where(array('sd.batch'=>$batch_id,'sd.sam_type'=>array('5'),'sd.item'=>$item))
		        ->where(array('s.status'=>3))
			    ->where->lessThan('s.sam_date',$start_date);
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
	public function getSAMOSum($start_date,$location,$batch_id,$item)
	{	
	    $adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
        $select = new Select(array('sd'=>"st_sam_details"));
		$select ->join(array('s'=>'st_sam'),'s.id = sd.sam', array());
		$select ->columns(array('sum' => new Expression('SUM(sd.basic_quantity)')))
		        ->where(array('sd.batch'=>$batch_id,'sd.sam_type'=>array('1','2','3','4'),'sd.item'=>$item))
		        ->where(array('s.status'=>3))
			    ->where->lessThan('s.sam_date',$start_date);
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
	public function getFIOSum($start_date,$location,$batch_id,$item)
	{	
        $adapter = $this->adapter;  	 
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select = new Select(array('sd'=>"sl_sales_dtls"));
		$select ->join(array('s'=>'sl_sales'),'s.sales_no = sd.sales', array());
		$select->columns(array('sum' => new Expression('SUM(sd.discount_qty)')))
		       ->where(array('sd.batch'=>$batch_id,'sd.free_item'=>$item))
		       ->where(array('s.status'=>3,'s.credit'=>array(1,0)))
			   ->where->lessThan('s.sales_date',$start_date);
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
	
}

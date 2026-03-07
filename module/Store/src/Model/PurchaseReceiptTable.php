<?php
namespace Store\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class PurchaseReceiptTable extends AbstractTableGateway 
{
	protected $table = 'in_purchase_receipt';   //tablename

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
	    	   ->join(array('l'=>'sys_location'),'l.id = pr.location', array('location'=>'location','location_id' => 'id'))
	           ->join(array('a'=>'sys_activity'),'a.id = pr.cost_center', array('cost_center'=>'activity','cost_center_id' => 'id'))
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
	public function getDateWise($column,$year,$month,$item_group)
	{	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
                 ->columns(array(
					'id','location','cost_center','purchase_receipt_no','purchase_order','purchase_receipt_date','supplier',
					'challan_no','challan_date','payment_amt','payment_status','item_group','note','status','author','created','modified',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ))->having(array('year' => $year))
	    	   ->order(array('id DESC'));
			   if($item_group != '-1'):
				    $select->where(array('item_group' => $item_group));
			   endif;
			   if($month != '-1'):
					$select->having(array('month' => $month));
			   endif;
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
				->where->between('purchase_receipt_date', $start_date, $end_date);
				
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
		$where = ( is_array($param) )? $param: array('pr.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('pr'=>$this->table))
			    ->join(array('l'=>'sys_location'),'l.id = pr.location', array('location'=>'location','location_id' => 'id'))
			    ->join(array('a'=>'sys_activity'),'a.id = pr.cost_center', array('activity'=>'activity','activity_id' => 'id'))
			    ->join(array('g'=>'in_item_group'),'g.id = pr.item_group', array('item_group'=>'name','item_group_id' => 'id'))
		        ->where($where)
		        ->order(array('id DESC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;	
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
		$select->where->like('purchase_receipt_no', $prefix_PR_code."%");		
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
	 * STORE REPORT 4 - filter process
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function filterSRAsset($end_date, $location, $param, $col_date, $col_loc,$array,$item_sub_group,$item)
	{  
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('p' => $this->table))
			   ->join(array('pad'=>'in_purchase_asset_receipt_details'), 'p.id = pad.purchase_receipt', array())
			   ->join(array('a'=>'in_asset'), 'a.id = pad.assetssp', array())
                            ->join(array('i'=>'in_items'), 'i.id = pad.item', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(pad.assetssp)')
			    ));
				$select->where($where);
				$select->where->lessThanOrEqualTo($col_date,$end_date);
				$select->where->notEqualTo('pad.assetssp',NULL);
				$select->where->EqualTo('a.status','3');
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('pad.item'=>$item));
			    }
				if(sizeof($array)>0):
					$select->where->notIn('pad.assetssp',$array);
				endif;
                          if($item_sub_group!='-1'){
					$select->where(array('i.item_sub_group'=>$item_sub_group));
			    }
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	/**
	 * STORE REPORT
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
	 * STORE REPORT 4 - filter process fro stores and spares
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function filterSRSsp($end_date, $location, $param, $col_date, $col_loc,$array,$item_sub_group,$item)
	{  
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('p' => $this->table))
			   ->join(array('pd'=>'in_purchase_receipt_dtls'), 'p.id = pd.purchase_receipt', array())
			   ->join(array('ssp'=>'in_storespare'), 'ssp.id = pd.assetssp', array())
                            ->join(array('i'=>'in_items'), 'i.id = pd.item', array());
				$select->columns(array(
					'id' => new Expression('DISTINCT(pd.assetssp)')
			    ));
				$select->where($where);
				$select->where->lessThanOrEqualTo($col_date,$end_date);
				$select->where->notEqualTo('pd.assetssp',NULL);
				$select->where->EqualTo('ssp.status','3');
				if($location != '-1'){
					$select->where(array($col_loc => $location));
				}
				if($item!='-1'){
					$select->where(array('pd.item'=>$item));
			    }
				if(sizeof($array)>0):
					$select->where->notIn('pd.assetssp',$array);
				endif;
                           if($item_sub_group!='-1'){
					$select->where(array('i.item_sub_group'=>$item_sub_group));
			    }
		$selectString = $sql->getSqlStringForSqlObject($select);
	       // echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
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
	 * STOCK MOVEMENT 4 - fetch opening sum
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSROpeningforSspSUM($start_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('p' => $this->table))
			   ->join(array('pd'=>'in_purchase_receipt_dtls'), 'p.id = pd.purchase_receipt', array())
			   ->join(array('ssp'=>'in_storespare'), 'ssp.id = pd.assetssp', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				$select->where->lessThan($col_date,$start_date);
				$select->where->notEqualTo('pd.assetssp',NULL);
				$select->where->EqualTo('ssp.status','3');
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
	public function fetchSRQuantityforSspSUM($start_date, $end_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('p' => $this->table))
			   ->join(array('pd'=>'in_purchase_receipt_dtls'), 'p.id = pd.purchase_receipt', array())
			   ->join(array('ssp'=>'in_storespare'), 'ssp.id = pd.assetssp', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				$select->where->between($col_date, $start_date, $end_date);
				$select->where->notEqualTo('pd.assetssp',NULL);
				$select->where->EqualTo('ssp.status','3');
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
	 * STORE REPORT- fetch sum of transaction quantity
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSRQuantityforSspSUMATM($start_date, $end_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('p' => $this->table))
			   ->join(array('pd'=>'in_purchase_receipt_dtls'), 'p.id = pd.purchase_receipt', array())
			   ->join(array('ssp'=>'in_storespare'), 'ssp.id = pd.assetssp', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				$select->where->between($col_date, $start_date, $end_date);
				$select->where->notEqualTo('pd.assetssp',NULL);
				$select->where->EqualTo('ssp.status','3');
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
	 * STORE REPORT- fetch sum of transaction quantity
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSRQuantityforSspSUMRt($start_date, $end_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('p' => $this->table))
			   ->join(array('pd'=>'in_purchase_receipt_dtls'), 'p.id = pd.purchase_receipt', array())
			   ->join(array('ssp'=>'in_storespare'), 'ssp.id = pd.assetssp', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				$select->where->between($col_date, $start_date, $end_date);
				$select->where->notEqualTo('pd.assetssp',NULL);
				$select->where->EqualTo('ssp.status','3');
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
	 * STORE REPORT - fetch opening sum
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSROpeningforSspSUMATM($start_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('p' => $this->table))
			   ->join(array('pd'=>'in_purchase_receipt_dtls'), 'p.id = pd.purchase_receipt', array())
			   ->join(array('ssp'=>'in_storespare'), 'ssp.id = pd.assetssp', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				$select->where->lessThan($col_date,$start_date);
				$select->where->notEqualTo('pd.assetssp',NULL);
				$select->where->EqualTo('ssp.status','3');
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
	 * STORE REPORT 4 - fetch opening sum
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSROpeningforSspSUMRt($start_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('p' => $this->table))
			   ->join(array('pd'=>'in_purchase_receipt_dtls'), 'p.id = pd.purchase_receipt', array())
			   ->join(array('ssp'=>'in_storespare'), 'ssp.id = pd.assetssp', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				$select->where->lessThan($col_date,$start_date);
				$select->where->notEqualTo('pd.assetssp',NULL);
				$select->where->EqualTo('ssp.status','3');
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
	 * STOCK MOVEMENT 4 - fetch opening sum for Asset
	 * Return sum of given condition array | given id
	 * @param $Start_date 
	 * @param $location
	 * @param $param
	 * @return SUM
	**/ 
	public function fetchSROpeningforAssetSUM($start_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('p' => $this->table))
			   ->join(array('pad'=>'in_purchase_asset_receipt_details'), 'p.id = pad.purchase_receipt', array())
			   ->join(array('a'=>'in_asset'), 'a.id = pad.assetssp', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				//$select->order(array('o.id DESC'));
				$select->where->lessThan($col_date,$start_date);
				$select->where->notEqualTo('pad.assetssp',NULL);
				$select->where->EqualTo('a.status','3');
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
	public function fetchSRQuantityforAssetSUM($start_date, $end_date, $location, $param, $col_date, $col_loc, $col_qty)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('p' => $this->table))
			   ->join(array('pad'=>'in_purchase_asset_receipt_details'), 'p.id = pad.purchase_receipt', array())
			   ->join(array('a'=>'in_asset'), 'a.id = pad.assetssp', array());
				$select->columns(array(
					'sum' => new Expression('SUM('.$col_qty.')')
			    ));
				$select->where($where);
				$select->where->between($col_date, $start_date, $end_date);
				$select->where->notEqualTo('pad.assetssp',NULL);
				$select->where->EqualTo('a.status','3');
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
}

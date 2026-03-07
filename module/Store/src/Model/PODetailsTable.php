<?php
namespace Store\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class PODetailsTable extends AbstractTableGateway 
{
	protected $table = 'in_purchase_order_dtls';   //tablename

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
		$where = ( is_array($param) )? $param: array('d.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d' => $this->table))
			   ->join(array('i' =>'in_items'),'i.id = d.item', array('item'=>'code','item_id' => 'id'))
			   ->join(array('u'=>'st_uom'), 'u.id = i.uom', array('st_uom_code'=>'code', 'st_uom_id' => 'id'))
			   ->where($where);
		
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
	public function getPODetails($start_date, $end_date, $param, $column)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('d'=>$this->table))			 
			   ->join(array('po' => 'pur_purchase_order'), 'po.id = d.purchase_order', array('purchase_no' => 'po_no', 'supplier','activity','po_date','po_amount', 'note'))
			   ->where($param)
			   ->order(array('id DESC'))			
			   ->where->between($column, $start_date, $end_date);
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
	public function save($data, $data1 = NULL)
	{
		$data = (!is_array($data))? $data->toArray() : $data;
	    
		if(is_array($data[0])):
		
			$adapter = $this->adapter;
			$connection = $adapter->getDriver()->getConnection();
			
			$connection->beginTransaction();			
				foreach($data as $data_row):
					$id = isset($data_row['id']) ? (int)$data_row['id'] : 0;			    
				    if ( $id > 0 )
				    {
				    	$result = ($this->update($data_row, array('id'=>$id)))?$id:0;
				    } else {
				        $this->insert($data_row);
				    	$result = $this->getLastInsertValue(); 
				    }
				    if($result == 0):
						$connection->rollback();//if any error rollback the transaction
				    	return 0;
				    endif;
				 endforeach;
				if($data1!=NULL):
					$pr_table = new TableGateway('pur_purchase_receipt', $adapter);
					$id = isset($data1['id']) ? (int)$data1['id'] : 0;
					$result = ($pr_table->update($data1, array('id'=>$id)))? 1 : 0 ;
					if($result == 0):
						$connection->rollback();
						return 0;
					endif;
				endif;
			 $connection->commit();// commit on success;
			 return 1;
		else:
			$id = isset($data['id']) ? (int)$data['id'] : 0;
		    if ( $id > 0 )
		    {
		    	$result = ($this->update($data, array('id'=>$id)))?$id:0;
		    } else {
		        $this->insert($data);
		    	$result = $this->getLastInsertValue(); 
		    }
		endif;	    	    
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
	public function getMonthlyPR($prefix_PO_code)
	{  
		$adapter = $this->adapter;			
		$sql = new Sql($adapter);		
		$select = $sql->select();
		$select->from($this->table);		
		$select->where->like('purchase_order_no', $prefix_PO_code."%");	
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return  $results;
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
	
}

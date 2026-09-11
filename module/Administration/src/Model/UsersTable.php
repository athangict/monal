<?php
namespace Administration\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class UsersTable extends AbstractTableGateway //implements AdapterAwareInterface
{
	protected $table = 'sys_users'; //tablename
	private $hasUuidColumn = null;
	
	public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

	/**
	 * Return All records of table
	 * @return Array
	 */
	public function getAll($permission)
	{
	    $adapter = $this->adapter;
	    $sql = new Sql($adapter);
	    $select = $sql->select();
	    $select->from($this->table)
				->order('id desc');
		if($permission['location_permit']!='-1'){$select->where->in('location', $permission['location_permit']);}
		if($permission['activity_permit']!='-1'){$select->where->in('activity', $permission['activity_permit']);}
	    if($permission['status_permit']!='-1'){$select->where->in('status', $permission['status_permit']);}
	    if($permission['onlyifcreator_permit']!='-1'){$select->where(array('author'=>$permission['onlyifcreator_permit']));}
	    
		$selectString = $sql->getSqlStringForSqlObject($select);
	    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    return $results;
	}
	/**
	 * Return All records of table
	 * @return Array
	 */
	public function getUsers(){  
		$adapter = $this->adapter;
	    $sql = new Sql($adapter);
	    $select = $sql->select();
	    $select->from($this->table);
	    
	    $selectString = $sql->getSqlStringForSqlObject($select);
	    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    return $results;
	}
    /**
	 * Return records of given id
	 * @param Int $id
	 * @return Array
	 */
	public function get($param)
	{  
		$where = $this->normalizeUserWhere($param);
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
		$where = $this->normalizeUserWhere($param);
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
		$where = null;
		$id = 0;

		if (isset($data['id']) && $data['id'] !== '' && $data['id'] !== null) {
			$idParam = $data['id'];
			if (is_numeric($idParam)) {
				$id = (int) $idParam;
				$where = array('id' => $id);
			} elseif ($this->isUuid((string) $idParam) && $this->hasUuidColumn()) {
				$where = array('uuid' => strtolower((string) $idParam));
				unset($data['id']);
			}
		}
		
		if ($where !== null)
		{
			$result = ($this->update($data, $where)) ? (($id > 0) ? $id : 1) : 0;
		} else {
			if ($this->hasUuidColumn() && empty($data['uuid'])) {
				$data['uuid'] = $this->generateUuidV4();
			}
			$this->insert($data);
			$result = $this->getLastInsertValue(); 
		}	    	    
		return $result;	 	     
	}

	/**
     *  Return Boolean
     *  @param int $id
     *  @return true | false
     */
	public function remove($id)
	{
		return $this->delete($this->normalizeUserWhere($id));
	}

	public function getUuidById($id)
	{
		if (!$this->hasUuidColumn()) {
			return '';
		}

		$uuid = (string) $this->getColumn((int) $id, 'uuid');
		return trim($uuid);
	}

	private function normalizeUserWhere($param)
	{
		if (!is_array($param)) {
			if (is_numeric($param)) {
				return array('id' => (int) $param);
			}

			if ($this->isUuid((string) $param) && $this->hasUuidColumn()) {
				return array('uuid' => strtolower((string) $param));
			}

			return array('id' => $param);
		}

		if (isset($param['id']) && !is_numeric($param['id']) && $this->isUuid((string) $param['id']) && $this->hasUuidColumn()) {
			$param['uuid'] = strtolower((string) $param['id']);
			unset($param['id']);
		}

		if (isset($param['uuid'])) {
			$param['uuid'] = strtolower(trim((string) $param['uuid']));
		}

		return $param;
	}

	private function isUuid($value)
	{
		return (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', (string) $value);
	}

	private function generateUuidV4()
	{
		try {
			$bytes = random_bytes(16);
		} catch (\Exception $e) {
			return sprintf(
				'%s-%s-4%s-%s%s-%s',
				substr(sha1(uniqid((string) mt_rand(), true)), 0, 8),
				substr(sha1(uniqid((string) mt_rand(), true)), 8, 4),
				substr(sha1(uniqid((string) mt_rand(), true)), 12, 3),
				dechex((hexdec(substr(sha1(uniqid((string) mt_rand(), true)), 15, 1)) & 0x3) | 0x8),
				substr(sha1(uniqid((string) mt_rand(), true)), 16, 3),
				substr(sha1(uniqid((string) mt_rand(), true)), 19, 12)
			);
		}

		$bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
		$bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

		$hex = bin2hex($bytes);
		return sprintf(
			'%s-%s-%s-%s-%s',
			substr($hex, 0, 8),
			substr($hex, 8, 4),
			substr($hex, 12, 4),
			substr($hex, 16, 4),
			substr($hex, 20, 12)
		);
	}

	private function hasUuidColumn()
	{
		if ($this->hasUuidColumn !== null) {
			return $this->hasUuidColumn;
		}

		$adapter = $this->adapter;
		$table = str_replace('`', '``', $this->table);
		$query = "SHOW COLUMNS FROM `{$table}` LIKE 'uuid'";
		$rows = $adapter->query($query, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$this->hasUuidColumn = !empty($rows);

		return $this->hasUuidColumn;
	}
	/**
	 * Return Min value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMin($where = NULL, $column = NULL)
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
	public function getMax($where=NULL, $column = NULL)
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
	* check particular row is present in the table 
	* with given column and its value
	* MODIFIED FOR FROM VALIDATION (REMOTE VALIDATOR) 
	*/
	public function checkAvailability($column, $value)
	{
		$column = $column; $value = $value;
		$resultSet = $this->select(function(Select $select) use ($column, $value){
		$select->where(array($column => $value));
		});
		$resultSet = $resultSet->toArray();
		return (sizeof($resultSet)>0)?false:true;
	}
	
	/**
	 * Return Count value of the column
	 * @param Array $where
	 * @return String | Int
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
	 * Return Count value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getSum($column, $where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'sum' => new Expression('SUM('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
	
		return $column;
	}
	/**
	 * Return id's|columns'value  which is not present in given array
	 * @param Array $param
	 * @param String column
	 * @return Array
	 */
	public function getNotIn($param, $column='employee', $where=NULL)
	{
		$param = ( is_array($param) )? $param: array($param);
		$where = (is_array($column)) ? $column: $where;
		//$column = (is_array($column)) ? 'employee' : $column;
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = new Select();
		$select->from($this->table)
		//->columns(array('employee'))
		->where->notIn($column, $param);
			$select->where(array('status'=>1));
		
	  $selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
}
<?php
namespace Hr\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Hr\Model As Hr;
class HreportController extends AbstractActionController
{   
	private $_container;
	protected $_table; 		// database table
	protected $_user; 		// user detail
	protected $_login_id; 	// logined user id
	protected $_login_role; // logined user role
	protected $_author; 	// logined user id
	protected $_created; 	// current date to be used as created dated
	protected $_modified; 	// current date to be used as modified date
	protected $_config; 	// configuration details
	protected $_dir; 		// default file directory
	protected $_id; 		// route parameter id, usally used by crude
	protected $_auth; 		// checking authentication
	protected $_permission; // permission plugin
    
	public function __construct(ContainerInterface $container)
    {
        $this->_container = $container;
    }
	/**
	 * Laminas Default TableGateway
	 * Table name as the parameter
	 * returns obj
	 */
	public function getDefaultTable($table)
	{
		$this->_table = new TableGateway($table, $this->_container->get('Laminas\Db\Adapter\Adapter'));
		return $this->_table;
	}

   /**
	 * User defined Model
	 * Table name as the parameter
	 * returns obj
	 */
	public function getDefinedTable($table)
    {
        $definedTable = $this->_container->get($table);
        return $definedTable;
    }
    /**
	 * initial set up
	 * general variables are defined here
	 */
	public function init()
	{
		$this->_auth = new AuthenticationService;
		if(!$this->_auth->hasIdentity()):
			$this->flashMessenger()->addMessage('error^ You dont have right to access this page!');
   	        $this->redirect()->toRoute('auth', array('action' => 'login'));
		endif;
		
		if(!isset($this->_config)) {
			$this->_config = $this->_container->get('Config');
		}
		if(!isset($this->_user)) {
		    $this->_user = $this->identity();
		}
		if(!isset($this->_login_id)){
			$this->_login_id = $this->_user->id; 
		}
		if(!isset($this->_login_role)){
			$this->_login_role = $this->_user->role; 
		}
		if(!isset($this->_highest_role)){
			$this->_highest_role = $this->getDefinedTable(Acl\RolesTable::class)->getMax($column='id'); 	
		}
		if(!isset($this->_lowest_role)){
			$this->_lowest_role = $this->getDefinedTable(Acl\RolesTable::class)->getMin($column='id'); 
		}
		if(!isset($this->_author)){
			$this->_author = $this->_user->id; 
		}
		
		$this->_id = $this->params()->fromRoute('id');
		
		$this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

		$this->_permissionObj =  $this->PermissionPlugin();
		$this->_permission = $this->_permissionObj->permission($this->getEvent());
	}
	
	
	/**
	 * indexAction of HreportController
	**/
	public function indexAction()
	{
		$this->init();
		
		return new ViewModel(array(
					'title' => 'Index Page',
		));	
	}
	/**
	 * HR Genral Report
	**/
	public function generalAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$region = $form['region'];
			$location = $form['location'];
			$department = $form['department'];
			$activity = $form['activity'];
			$position_level = $form['position_level'];
			$q_level = $form['q_level'];
			$emp_status = $form['emp_status'];
		}else{
			$region = '-1';
			$location = '-1';
			$region = '-1';
			$department = '-1';
			$activity ='-1';
			$position_level = '-1';
			$q_level = '-1';
			$emp_status = '-1';
		}			
		$data = array(
				'region' => $region,
				'location' => $location,
				'department' => $department,
				'activity' => $activity,
				'position_level' => $position_level,
				'q_level' => $q_level,
				'emp_status' => $emp_status,
		);
		$ViewModel = new ViewModel(array(
				'title' 			=> "General Report",
				'regionObj'			=> $this->getDefinedTable(Administration\RegionTable::class),
				'locationObj' 		=> $this->getDefinedTable(Administration\LocationTable::class),
				'departmentObj'		=> $this->getDefinedTable(Administration\DepartmentTable::class),
				'activityObj'    	=> $this->getDefinedTable(Administration\ActivityTable::class),
				'positionlevelObj'  => $this->getDefinedTable(Hr\PositionlevelTable::class),
				'qlevelObj'         => $this->getDefinedTable(Hr\QualificationLevelTable::class),
				'empstatusObj'      => $this->getDefinedTable(Hr\EmployeeStatusTable::class),
				'data'              => $data,
				'employeeObj'		=> $this->getDefinedTable(Hr\EmployeeTable::class),
		));
        // $this->layout('layout/reportlayout');
		return $ViewModel;
	}
	/**
	 * Get Activity by DepartmentTable
	**/
	public function getactivityAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		$department_id = $form['department'];
		$activities = $this->getDefinedTable(Administration\ActivityTable::class)->get(array('a.department' => $department_id));
		
		$act.="<option value='-1'>All</option>";
		foreach($activities as $activity):
			$act .="<option value='".$activity['id']."'>".$activity['activity']."</option>";
		endforeach;
		echo json_encode(array(
				'newdata' => $act,
		));
		exit;
	}
}
?>

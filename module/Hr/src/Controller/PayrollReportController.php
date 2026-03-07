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

class PayrollReportController extends AbstractActionController
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
	 *  index action
	 */
	public function indexAction()
	{
		$this->init();

		return new ViewModel();
	}
	
	/**
	 * report action
	 */
	public function payregisterAction(){
		$this->init();
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
			$department = $form['department'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
			$department = '-1';
		}
		//list($region, $location, $department, $year, $month) = explode("-", $this->_id . '-0-0-0-0-0');
		$data = array(
				'year' => $year,
				'month' => $month,
				'region' => $region,
				'location' => $location,
				'department' => $department,
		);
		//echo "testing"; exit;
		$ViewModel = new ViewModel(array(
				'title' => 'Payroll Report',
				//'employee' => $this->getDefinedTable(Hr\EmployeeTable::class)->getEmpforReport($data),
				'earningHead' => $this->getDefinedTable(Hr\PayheadTable::class)->get(array('deduction'=>0,'display'=>1)),
				'deductionHead' => $this->getDefinedTable(Hr\PayheadTable::class)->get(array('deduction'=>1,'display'=>1)),
				'payrollObj' => $this->getDefinedTable(Hr\PayrollTable::class),
				'paydetailObj' => $this->getDefinedTable(Hr\PaydetailTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'department' => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
				//'payrolls' => $this->getDefinedTable(Hr\PayrollTable::class)->get($this->_id),
				'location' => $this->getDefinedTable(Administration\LocationTable::class)->get(array('region'=>$data['region'])),
				'data' => $data,
				'minYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMin('year'),
				'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
				'departmentObj' => $this->getDefinedTable(Administration\DepartmentTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
		));
        //$this->layout('layout/reportlayout');
		return $ViewModel;
	}
	
	/**
	 * controlsummary of payroll
	 */
	public function controlsummaryAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
			$activity = $form['activity'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
			$activity = '-1';
		}	
		$data = array(
				'year' => $year,
				'month' => $month,
				'region' => $region,
				'location' => $location,
				'activity' => $activity,
		);
		$ViewModel = new ViewModel(array(
				'title' => 'Control Summary',
				'earningHead' => $this->getDefinedTable(Hr\PayheadTable::class)->get(array('deduction'=>0)),
				'deductionHead' => $this->getDefinedTable(Hr\PayheadTable::class)->get(array('deduction'=>1)),
				'payrollObj' => $this->getDefinedTable(Hr\PayrollTable::class),
				'paydetailObj' => $this->getDefinedTable(Hr\PaydetailTable::class),
				'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'data' => $data,
				'minYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMin('year'),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
		));
		//$this->layout('layout/reportlayout');
		return $ViewModel;
	}
	/**
	* pay head report
	*/
	public function phreportAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
			$payheads = $form['payhead'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
			$payheads = array('1');
		}
		if(empty($payheads)):
			$payheads = array('1'); //default selection
		endif;
		if($year == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$year = ($max_month == 12)? $max_year+1 : $max_year;
		endif;
		if($month == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$month = ($max_month == 12)? 1 : $max_month+1;
		endif;
		$data = array(
				'year'=>$year,
				'month'=> $month,
				'region'=> $region,
				'location'=> $location
		);
		$ViewModel = new ViewModel(array(
				'title' 	 => 'Pay Head Report',
				'payheads'	 => $payheads,
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
				'paydetailObj' => $this->getDefinedTable(Hr\PaydetailTable::class),
				'payrollObj' => $this->getDefinedTable(Hr\PayrollTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'department' => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
				'location' => $this->getDefinedTable(Administration\LocationTable::class)->select(array('region'=>$data['region'])),
				'data' => $data,
				'minYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMin('year'),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
				'pepfObj' => $this->getDefinedTable(Hr\PepfTable::class),
				'tpnObj' => $this->getDefinedTable(Hr\TpnTable::class),
		));
		//$this->layout('layout/reportlayout');
		return $ViewModel;
	}

	/**
	* loan reports 
	*/
	public function loanreportAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
			$payheads = $form['payhead'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
			$payheads = 9;
		}
		if($year == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$year = ($max_month == 12)? $max_year+1 : $max_year;
		endif;
		if($month == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$month = ($max_month == 12)? 1 : $max_month+1;
		endif;
		$data = array(
				'year'=>$year,
				'month'=> $month,
				'region'=> $region,
				'location'=> $location,
				'payheads'=> $payheads,
		);
		$ViewModel = new ViewModel(array(
				'title' 	 	=> 'Loan Report',
				'payheads' 	=> $data['payheads'],
				'payheadObj' 	=> $this->getDefinedTable(Hr\PayheadTable::class),
				'tpnObj' 	=> $this->getDefinedTable(Hr\TpnTable::class),
				'employeeObj' 	=> $this->getDefinedTable(Hr\EmployeeTable::class),
				'paydetailObj'  => $this->getDefinedTable(Hr\PaydetailTable::class),
				'payrollObj' 	=> $this->getDefinedTable(Hr\PayrollTable::class),
				'payroll' 	=> $this->getDefinedTable(Hr\PayrollTable::class)->getforReportByLoc($data),
				'payheadtypeObj'=> $this->getDefinedTable(Hr\PayheadtypeTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'department' => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
				'location' => $this->getDefinedTable(Administration\LocationTable::class)->select(array('region'=>$data['region'])),
				'data' => $data,
				'minYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMin('year'),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
				'positionlevelObj'=> $this->getDefinedTable(Hr\PositionlevelTable::class),
		));
		//$this->layout('layout/reportlayout');
		return $ViewModel;
	}
	/**
	* group insurence scheme action
	*/
	public function gisAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
		}
		if($year == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$year = ($max_month == 12)? $max_year+1 : $max_year;
		endif;
		if($month == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$month = ($max_month == 12)? 1 : $max_month+1;
		endif;
		$data = array(
				'year'=>$year,
				'month'=> $month,
				'data_region'=> $region,
				'data_location'=> $location,
		);
		$ViewModel = new ViewModel(array(
				'title' 	 	=> 'Group Insurence Scheme',
				'payheadObj' 	=> $this->getDefinedTable(Hr\PayheadTable::class),
				'employeeObj'   => $this->getDefinedTable(Hr\EmployeeTable::class),
				'paydetailObj'  => $this->getDefinedTable(Hr\PaydetailTable::class),
				'payrollObj' 	=> $this->getDefinedTable(Hr\PayrollTable::class),
				'payheadtypeObj'=> $this->getDefinedTable(Hr\PayheadtypeTable::class),
				'paygroupObj'=> $this->getDefinedTable(Hr\PaygroupTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'department' => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
				'location' => $this->getDefinedTable(Administration\LocationTable::class)->select(array('region'=>$data['data_region'])),
				'data' => $data,
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
		));
		//$this->layout('layout/reportlayout');
		return $ViewModel;
	}
	/**
	* saving action
	*/
	public function savingAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
		}
		if($year == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$year = ($max_month == 12)? $max_year+1 : $max_year;
		endif;
		if($month == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$month = ($max_month == 12)? 1 : $max_month+1;
		endif;
		
		$data = array(
				'year'=>$year,
				'month'=> $month,
				'region'=> $region,
				'location'=> $location,
		);
		$ViewModel = new ViewModel(array(
				'title' 	 	=> 'Bank Report',
				'payheadObj' 	=> $this->getDefinedTable(Hr\PayheadTable::class),
				'paydetailObj'  => $this->getDefinedTable(Hr\PaydetailTable::class),
				'payrollObj' 	=> $this->getDefinedTable(Hr\PayrollTable::class),
				'payheadtypeObj'=> $this->getDefinedTable(Hr\PayheadtypeTable::class),
				'employeeObj'   => $this->getDefinedTable(Hr\EmployeeTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'department' => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
				'location' => $this->getDefinedTable(Administration\LocationTable::class)->select(array('region'=>$data['region'])),
				'data' => $data,
				'minYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMin('year'),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
		));
		//$this->layout('layout/reportlayout');
		return $ViewModel;
	}
	/**
	* saving action
	*/
	public function cashreportAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
		}
		if($year == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$year = ($max_month == 12)? $max_year+1 : $max_year;
		endif;
		if($month == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$month = ($max_month == 12)? 1 : $max_month+1;
		endif;
		
		$data = array(
				'year'=>$year,
				'month'=> $month,
				'region'=> $region,
				'location'=> $location,
		);
		//print_r($data);exit;
		$ViewModel = new ViewModel(array(
				'title' 	 	=> 'Cash Report',
				'payheadObj' 	=> $this->getDefinedTable(Hr\PayheadTable::class),
				'paydetailObj'  => $this->getDefinedTable(Hr\PaydetailTable::class),
				'payrollObj' 	=> $this->getDefinedTable(Hr\PayrollTable::class),
				'payheadtypeObj'=> $this->getDefinedTable(Hr\PayheadtypeTable::class),
				'employeeObj'   => $this->getDefinedTable(Hr\EmployeeTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'department' => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
				'location' => $this->getDefinedTable(Administration\LocationTable::class)->select(array('region'=>$data['region'])),
				'data' => $data,
				'minYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMin('year'),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
		));
		//$this->layout('layout/reportlayout');
		return $ViewModel;
	}
	/**
	 * Provident Fund Report
	**/
	public function pfreportAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
		}
		if($year == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$year = ($max_month == 12)? $max_year+1 : $max_year;
		endif;
		if($month == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$month = ($max_month == 12)? 1 : $max_month+1;
		endif;
		$data = array(
			'year' => $year,
			'month' => $month,
			'region' => $region,
			'location' => $location,
		);
		$empdata=$this->getDefinedTable(Hr\EmployeeTable::class)->getPf($data);
		//print_r($empdata);exit;
		$ViewModel = new ViewModel(array(
				'title' 	 	=> 'Provident Fund Report',
				'payheadObj' 	=> $this->getDefinedTable(Hr\PayheadTable::class),
				'employeeObj'   => $this->getDefinedTable(Hr\EmployeeTable::class),
				'paydetailObj'  => $this->getDefinedTable(Hr\PaydetailTable::class),
				'payrollObj' 	=> $this->getDefinedTable(Hr\PayrollTable::class),
				'payheadtypeObj'=> $this->getDefinedTable(Hr\PayheadtypeTable::class),
				'paygroupObj'=> $this->getDefinedTable(Hr\PaygroupTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'activity' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'location' => $this->getDefinedTable(Administration\LocationTable::class)->select(array('region'=>$data['region'])),
				'data' => $data,
				'minYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMin('year'),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
				'pepfObj' => $this->getDefinedTable(Hr\PepfTable::class),
				'empdata'=>$empdata,
		));
		//$this->layout('layout/accreportlayout');
		return $ViewModel;
	}
	/**
	 * Health Tax and Personal Income Tax
	**/
	public function htpitreportAction()
	{
		$this->init();
		$locations = $this->getDefinedTable(Administration\UsersTable::class)->getcolumn($this->_user->id,'location');
		$role = explode(',',$this->getDefinedTable(Administration\UsersTable::class)->getcolumn($this->_user->id,'role'));
		$region=$this->getDefinedTable(Administration\UsersTable::class)->getcolumn($this->_user->id,'region');
		$empId =$this->getDefinedTable(Administration\UsersTable::class)->getcolumn($this->_user->id,'employee');
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
		}
		if($year == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$year = ($max_month == 12)? $max_year+1 : $max_year;
		endif;
		if($month == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$month = ($max_month == 12)? 1 : $max_month+1;
		endif;
		$data = array(
				'year' => $year,
				'month' => $month,
				'region' => $region,
				'location' => $location,
		);
	    $ViewModel = new ViewModel(array(
				'title' 	 		=> 'HT & PIT Report',
				'payheadObj' 		=> $this->getDefinedTable(Hr\PayheadTable::class),
				'employeeObj'   	=> $this->getDefinedTable(Hr\EmployeeTable::class),
				'paydetailObj' 	 	=> $this->getDefinedTable(Hr\PaydetailTable::class),
				'payrollObj' 		=> $this->getDefinedTable(Hr\PayrollTable::class),
				'payheadtypeObj'	=> $this->getDefinedTable(Hr\PayheadtypeTable::class),
				'paygroupObj'		=> $this->getDefinedTable(Hr\PaygroupTable::class),
				'role'				=> $role,
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'location' => $this->getDefinedTable(Administration\LocationTable::class)->select(array('region'=>$data['region'])),
				'data' => $data,
				'emp_id'=>$empId,
				'voucher'	=> $this->getDefinedTable(Hr\PitVoucherTable::class),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
				'tpnObj' => $this->getDefinedTable(Hr\TpnTable::class),

		));
		//$this->layout('layout/accreportlayout');
		return $ViewModel;
	}
	/**
	 * Update Voucher Action
	 */
	public function updatevoucherAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$year = date('Y',strtotime($form['date']));
			$month = date('m',strtotime($form['date']));
			$day = date('d',strtotime($form['date']));
			$voucherlist=$this->getDefinedTable(Hr\PitVoucherTable::class)->getDateWise('date',$year,$month,array('type'=>$form['type']));
			foreach($voucherlist as $vou);
			if(!empty($voucherlist)){
				if(($month==6 ||$month==12) && $form['type']!=1 && $day<=15){
					
					$list=$this->getDefinedTable(Hr\PitVoucherTable::class)->getDateWiseDay($year.'-'.$month.'-01',$year.'-'.$month.'-15',array('type'=>$form['type']));
					foreach($list as $lists);
					if(!empty($list)){
						$id=$lists['id'];
					}
					else{
						$id='';
					}
				}
				else if(($month==6 ||$month==12) && $form['type']!=1 && $day>15){
					
					$list=$this->getDefinedTable(Hr\PitVoucherTable::class)->getDateWiseDay($year.'-'.$month.'-15',$year.'-'.$month.'-31',array('type'=>$form['type']));
					foreach($list as $lists);
					if(!empty($list)){
						$id=$lists['id'];
					}
					else{
						$id='';
					}
				}
				else{
					$id=$vou['id'];
				}
				
				
			}
			else{
				$id='';
			}
				if(!empty($id)){
					$data = array(	
						'id'		=>$id,
						'date' 		=> $form['date'],
						'voucher' 	=> $form['voucher'],
						'type' 	=> $form['type'],
						'author' 	=>$this->_author,
						'modified'  =>$this->_modified,
				);
			}
			else{
				$data = array(	
					'date' 		=> $form['date'],
					'voucher' 	=> $form['voucher'],
					'type' 	=> $form['type'],
					'author' 	=>$this->_author,
					'created' 	=>$this->_created,
					'modified' 	=>$this->_modified,
				);
			}
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\PitVoucherTable::class)->save($data);	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Voucher Updated successfully");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to Update Voucher");
			endif;
			return $this->redirect()->toRoute('preport',array('action' => 'htpitreport'));
			
		endif;
		
		
		$ViewModel = new ViewModel(array(
				'title' 			=> 'Update Voucher',
				'voucher'			=> $this->getDefinedTable(Hr\PitVoucherTable::class),
			));		 
			$ViewModel->setTerminal(True);
			return $ViewModel;	
	}
		/**
	 * TDS Certificate Action
	 */
	public function tdscertificateAction()
	{
		$this->init();
		$arr = explode('-',$this->_id);
		return new ViewModel(array(
				'title' 			=> 'Salary Slip',
				'empId' 			=> $arr[0],
				'year' 				=> $arr[1],
				'employeeObj' 		=> $this->getDefinedTable(Hr\EmployeeTable::class),
				'emphistoryObj' 	=> $this->getDefinedTable(Hr\EmpHistoryTable::class),
				'payheadObj' 		=> $this->getDefinedTable(Hr\PayheadTable::class),
				'payrolls' 			=> $this->getDefinedTable(Hr\PayrollTable::class)->get(array('pr.employee'=>$arr[0],'pr.year'=>$arr[1])),
				'payrollObj' 		=> $this->getDefinedTable(Hr\PayrollTable::class),
				'paydetailObj' 		=> $this->getDefinedTable(Hr\PaydetailTable::class),
				'tpnObj' 			=> $this->getDefinedTable(Hr\TpnTable::class),
				'voucherObj' 		=> $this->getDefinedTable(Hr\PitVoucherTable::class),
				'leaveenchasObj'	=> $this->getDefinedTable(Hr\LeaveEncashTable::class),
				'leaveenchasObj'	=> $this->getDefinedTable(Hr\LeaveEncashTable::class),  
				'sittingfeeObj'		=> $this->getDefinedTable(Hr\SittingfeeTable::class),  
				'bonusObj'			=> $this->getDefinedTable(Hr\BonusTable::class),
		));
	}
	/**
	 * payslip Action
	 */
	public function payslipAction()
	{
		$this->init();
		$locations = $this->getDefinedTable(Administration\UsersTable::class)->getcolumn($this->_user->id,'location');
		$role = explode(',',$this->getDefinedTable(Administration\UsersTable::class)->getcolumn($this->_user->id,'role'));
		$region=$this->getDefinedTable(Administration\UsersTable::class)->getcolumn($this->_user->id,'region');
		$empId =$this->getDefinedTable(Administration\UsersTable::class)->getcolumn($this->_user->id,'employee');
		if($this->getRequest()->isPost()):
			$request = $this->getRequest()->getPost();
			$year = $request['year'];
			$month = $request['month'];
			$region = $request['region'];
			$location = $request['location'];
			$employee = $request['employee'];
		else:
			$employee = $empId;//set default employee to -1 meaning all employee
			$location = $locations;//set default location to -1 meaning all employee
			$region = $region;//set default region to -1 meaning all employee			
			$month =  date('m');
			$year = date('Y');
			if($this->_id > 0):
				list($employee, $year, $month) = explode('-', $this->_id);
				$location = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($employee,'location');
				$region = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($location,'region');
			endif;
		endif;
		
		
		return new ViewModel(array(
				'title' 			=> 'Salary Slip',
				'year' 				=> $year,
				'month' 			=> $month,
				'regionObj' 			=> $this->getDefinedTable(Administration\RegionTable::class),
				'locationObj' 		=> $this->getDefinedTable(Administration\LocationTable::class),
				'region_id' 		=> $region,
				'location_id' 		=> $location,
				'employee_id' 		=> $employee,
				'role' 				=> $role,
				'empId' 			=> $empId,
				'employeeObj' 		=> $this->getDefinedTable(Hr\EmployeeTable::class),
				'emphistoryObj' 	=> $this->getDefinedTable(Hr\EmpHistoryTable::class),
				'payheadObj' 		=> $this->getDefinedTable(Hr\PayheadTable::class),
				'payrollObj' 		=> $this->getDefinedTable(Hr\PayrollTable::class),
				'paydetailObj' 		=> $this->getDefinedTable(Hr\PaydetailTable::class),
				'tpnObj' 		=> $this->getDefinedTable(Hr\TpnTable::class),
		));
	}
	/**
	 * Ajax to get the employee according to location
	**/
	public function getemployeeAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		
		$location_id = $form['location'];
		$employees = $this->getDefinedTable(Hr\EmployeeTable::class)->get(array('e.location'=>$location_id,'e.status'=>array(1,4,5)));
		
		$emp ="<option value='-1'>All</option>";
		foreach($employees as $employee):
			$emp .="<option value='".$employee['id']."'>".$employee['full_name']."</option>";
		endforeach;
		echo json_encode(array(
				'emp' => $emp,
		));
		exit;
	}
	/**
	 * Provident Fund Report
	**/
	public function hctdsreportAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
			$activity = $form['activity'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
			$activity = '-1';
		}
		if($year == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$year = ($max_month == 12)? $max_year+1 : $max_year;
		endif;
		if($month == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$month = ($max_month == 12)? 1 : $max_month+1;
		endif;
		$data = array(
				'year' => $year,
				'month' => $month,
				'region' => $region,
				'location' => $location,
				'activity' => $activity,
		);
		//print_r($data);exit;
		$ViewModel = new ViewModel(array(
				'title' 	 	=> 'Provident Fund Report',
				'payheadObj' 	=> $this->getDefinedTable(Hr\PayheadTable::class),
				'employeeObj'   => $this->getDefinedTable(Hr\EmployeeTable::class),
				'paydetailObj'  => $this->getDefinedTable(Hr\PaydetailTable::class),
				'payrollObj' 	=> $this->getDefinedTable(Hr\PayrollTable::class),
				'payheadtypeObj'=> $this->getDefinedTable(Hr\PayheadtypeTable::class),
				'paygroupObj'=> $this->getDefinedTable(Hr\PaygroupTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'activity' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'location' => $this->getDefinedTable(Administration\LocationTable::class)->select(array('region'=>$data['region'])),
				'data' => $data,
				'minYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMin('year'),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'tpnObj' => $this->getDefinedTable(Hr\TpnTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
		));
		//$this->layout('layout/accreportlayout');
		return $ViewModel;
	}
	public function getvoucherAction()
	{
		$form = $this->getRequest()->getPost();
		$typeid =$form['typeId'];
		$date =$form['date'];
		$year = date('Y',strtotime($date));
		$month = date('m',strtotime($date));
		$voucherlist=$this->getDefinedTable(Hr\PitVoucherTable::class)->getDateWise('date',$year,$month,array('type'=>$typeid));
		if(!empty($voucherlist)){foreach($voucherlist as $vou);
			$id=$vou['id'];
			$voucher=$vou['voucher'];
			
		}
		else{
			$id='';
			$voucher="";
		}
		
		
		echo json_encode(array(
			'id' => $id,
			'voucher' => $voucher,
		));
		exit;
	}
}

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
use Accounts\Model As Accounts;

class PayrollController extends AbstractActionController
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

		//$this->_permissionObj =  $this->PermissionPlugin();
		//$this->_permission = $this->_permissionObj->permission($this->getEvent());
	}
	
	
	/** funtion to get employee list 
	 * base on authority
	 */
	public function getEmployee()
	{		
		$emp_id = $this->_user->employee;
		$locations = explode(',',$this->getDefinedTable(Acl\UsersTable::class)->getcolumn($this->_user->id,'admin_location'));
		if($this->_login_role==$this->_highest_role):
			$employeelist = $this->getDefinedTable(Hr\EmployeeTable::class)->getAll();
		else:
			$employeelist = $this->getDefinedTable(Hr\EmployeeTable::class)->getAll($emp_id, $locations);
		endif;
		return $employeelist;
	} 
	
	/**
	 *  Monthly pay index action
	 */
	public function indexAction()
	{
		$this->init();	
		$year = ($this->_id == 0)? date('Y'):$this->_id;	
		return new ViewModel(array(
				'title'  => 'Payroll',
				'payroll' => $this->getDefinedTable(Hr\PayrollTable::class)->getPayroll($year),
				'temppayroll' => $this->getDefinedTable(Hr\TempPayrollTable::class)->getPayroll($year),
				'minYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMin('year'),
				'year' => $year,
				'payrollObj' => $this->getDefinedTable(Hr\PayrollTable::class),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
		));
	}
	/**
	 *  payroll action displays pay detail action of particular month
	 * 
	 */
	public function payrollAction()
	{
		$this->init();
		list($year, $month) = explode('-', $this->_id);
		$month = ($month == 0)? date('m'):$month;
		$year = ($year == 0)? date('Y'):$year;
		if(!$this->getDefinedTable(Hr\PayrollTable::class)->isPresent(array('month'=>$month, 'year'=>$year))):
			$this->redirect()->toRoute('payroll',array('action'=>'definepayroll','id'=>$year.'-'.$month));
		endif;		
		return new ViewModel(array(
				'title'  => 'Payroll',
				'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
				'payroll' => $this->getDefinedTable(Hr\PayrollTable::class)->get(array('month'=>$month, 'year'=>$year)),
				'month' => $month,
				'year' => $year,
                'locationObj'=>$this->getDefinedTable(Administration\LocationTable::class),
                'deptObj' =>$this->getDefinedTable(Administration\DepartmentTable::class),
				'actObj' =>$this->getDefinedTable(Administration\ActivityTable::class),
				'transactionObj' =>$this->getDefinedTable(Accounts\TransactionTable::class),
				'bookingbutton' => (sizeof($this->getDefinedTable(Hr\SalarybookingTable::class)->get(array('month'=> $month,'year'=> $year,'salary_advance'=>'1')))> 0)? True:False,
				'advancebutton' => (sizeof($this->getDefinedTable(Hr\SalarybookingTable::class)->get(array('month'=> $month,'year'=> $year,'salary_advance'=>'2')))> 0)? True:False,
		));
	}
		/*
	 * Add Revenue No
	 */
	public function addrevenuenoAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();	
			foreach($this->getDefinedTable(Hr\PayrollTable::class)->getAll() as $payrolls):
				
			$data = array(
					'id' => $payrolls['id'],
					'revenue_no' => $form['revenue_no'],
				);
			echo'<pre>';print_r($data);	
			endforeach;	exit;
			//$data = $this->_safedataObj->rteSafe($data);
			//$this->_connection->beginTransaction(); //***Transaction begins here***//
			//$result = $this->getDefinedTable(Hr\PayrollTable::Class)->save($data);			
			if($result > 0):
				//changes in paystructure should affect other payheads
				foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get($result) as $row);				
				$result1 = $this->calculatePayheadAmount($row);
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Pay head successfully added to Pay Structure");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to add new pay head");
				endif;
				//end
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new pay head");
			endif;
			$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
			return $this->redirect()->toUrl($redirectUrl);
			//return $this->redirect()->toRoute('payroll', array('action'=>'paystructure', 'id' => $this->_id));
		}
		$viewModel = new ViewModel(array(
				'employee' => $this->_id,
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),
		));

		$viewModel->setTerminal(True);
		return $viewModel;
	}
	/*
	 * generate /update (define) payroll for new month
	 * updation will be all done in tempayroll table
	 */
	public function definepayrollAction()
	{
		$this->init();
		$this->_id = isset($this->_id)?$this->_id:date('Y-m-d');
		list($year, $month) = explode('-', $this->_id);	  
	
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
		if($this->getDefinedTable(Hr\PayrollTable::class)->isPresent(array('month'=>$month, 'year'=>$year))):
			$this->redirect()->toRoute('payroll',array('id'=>$year.'-'.$month));
		endif;
		$data=array(
			'month'=> $month,
			'year' => $year,
			'author'=> $this->_author,
			'created'=>$this->_created,
			'modified' => $this->_modified
		);
		//prepare temporary payroll
		$this->getDefinedTable(Hr\TempPayrollTable::class)->prepareTempPayroll($data);
		foreach($this->getDefinedTable(Hr\TempPayrollTable::class)->get(array('pr.status'=>'0')) as $temp_payroll):
			$employee = $temp_payroll['employee'];
			$total_earning = 0;		
			$total_deduction = 0;
			$total_actual_earning = 0;
			$total_actual_deduction = 0;
			foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('sd.employee' => $employee, 'pht.deduction'=>'1')) as $paydetails):
				if($paydetails['dlwp']==1):
					$amount = $paydetails['amount'] - ($paydetails['amount']/$temp_payroll['working_days']) * $temp_payroll['leave_without_pay'];
				else:
					$amount = $paydetails['amount'];
				endif;
				if($paydetails['roundup']==1):
					$amount =round($amount);
				endif;
				$total_deduction = $total_deduction + $amount;
				$total_actual_deduction = $total_actual_deduction + $paydetails['amount'];
			endforeach;	
			foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('sd.employee' => $employee, 'pht.deduction'=>'0')) as $paydetails):
				if($paydetails['dlwp']==1):
					$amount = $paydetails['amount'] - ($paydetails['amount']/$temp_payroll['working_days']) * $temp_payroll['leave_without_pay'];
				else:
					$amount = $paydetails['amount'];
				endif;
				if($paydetails['roundup']==1):
					$amount =round($amount);
				endif;
				$total_earning = $total_earning + $amount;
				$total_actual_earning = $total_actual_earning + $paydetails['amount'];
			endforeach;				
			$leave_encashment = $temp_payroll['leave_encashment'];
			$bonus = $temp_payroll['bonus'];
			$net_pay = $total_earning + $leave_encashment + $bonus - $total_deduction;
			$earning_dlwp = $total_actual_earning - $total_earning;
			$deduction_dlwp = $total_actual_deduction - $total_deduction;
			$data1 = array(
				'id'	=> $temp_payroll['id'],
				'gross' => $total_earning,
				'total_deduction' => $total_deduction,
				'net_pay' => $net_pay,
				'earning_dlwp' => $earning_dlwp,
				'deduction_dlwp' => $deduction_dlwp,
				'status' => '1', // initiated
				'author' =>$this->_author,
				'modified' =>$this->_modified,
			);			
			$data1 = $this->_safedataObj->rteSafe($data1);			
			
			$result1 = $this->getDefinedTable(Hr\TempPayrollTable::class)->save($data1);
		endforeach;
		
		return new ViewModel(array(
			'title' => 'Add Pay roll',
			'month' => $month,
			'year' => $year,
			'temppayroll' => $this->getDefinedTable(Hr\TempPayrollTable::class)->getAll(),
			'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
			'payStructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),

		));
	}
	
	/**
	 * define pay payroll for a particular month
	 */
	public function definepayAction(){
		$this->init();
		if(isset($this->_id) & $this->_id!=0):
			$payheads = explode('-', $this->_id);
		endif;
		if(sizeof($payheads)==0):
			$payheads = array('1'); //default selection
		endif;
		return new ViewModel(array(
				'title' => 'Define Pay Detail',
				'employees'=> $this->getDefinedTable(Hr\EmployeeTable::class)->get(array('e.status'=>'1')),
				'payheads'=> $payheads,
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),
		));
	}
	
	/*
	 * Edit/update payroll -- basically temporary payroll
	 */
	public function editpayrollAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();	
			$employee = $this->getDefinedTable(Hr\TempPayrollTable::class)->getColumn($this->_id,'employee');
			$total_earning = 0;
			$total_deduction = 0;
			$total_actual_earning = 0;
			$total_actual_deduction = 0;
			$e_dlwp = 0;
			$d_dlwp = 0;
			foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('employee' => $employee)) as $paydetails):		
				if($paydetails['deduction'] == "1"){
					if($paydetails['dlwp']==1):
						$amount = $paydetails['amount'] - ($paydetails['amount']/$form['working_days']) * $form['leave_without_pay'];
					else:
						$amount = $paydetails['amount'];
					endif;
					$final_amt = $amount = $paydetails['amount'] - $amount;
					$final_amt = round($final_amt,2);
					$d_dlwp += $final_amt;
					if($paydetails['roundup']==1):
						$amount =round($amount);
					endif;
					$total_deduction = $total_deduction + $amount;
					$total_actual_deduction = $total_actual_deduction + $paydetails['amount'];
				}
				else
				{
					if($paydetails['dlwp']==1):
						$amount = $paydetails['amount'] - ($paydetails['amount']/$form['working_days']) * $form['leave_without_pay'];
					else:
						$amount = $paydetails['amount'];
					endif;
					$final_amt = $amount = $paydetails['amount'] - $amount;
					$final_amt = round($final_amt,2);
					$e_dlwp += $final_amt;
					if($paydetails['roundup']==1):
						$amount =round($amount);
					endif;
					$total_earning = $total_earning + $amount;
					$total_actual_earning = $total_actual_earning + $paydetails['amount'];
				}
			endforeach;				
			$leave_encashment = $form['leave_encashment'];
			$bonus = $form['bonus'];
			$net_pay = $total_actual_earning + $leave_encashment + $bonus - $total_actual_deduction - $e_dlwp - $d_dlwp;
			//$earning_dlwp = $total_actual_earning - $total_earning;
			//$deduction_dlwp = $total_actual_deduction - $total_deduction;
			$earning_dlwp = $e_dlwp;
			$deduction_dlwp = $d_dlwp;
			$data = array(
					'id'	=> $this->_id,
					'year' => $form['year'],
					'month' => $form['month'],
					'working_days' => $form['working_days'],
					'leave_without_pay' => $form['leave_without_pay'],
					'gross' => $total_actual_earning,
					'total_deduction' => $total_actual_deduction,
					'bonus' => $form['bonus'],
					'leave_encashment' => $leave_encashment,
					'net_pay' => $net_pay,
					'earning_dlwp' => $earning_dlwp,
					'deduction_dlwp' => $deduction_dlwp,
					'status' => '1', // initiated
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\TempPayrollTable::class)->save($data);
			if($result > 0):
				if($form['cash'] == 1){
					$bank_account_no = '0';
				}else{
					$bank_account_no = $form['bank_account_no'];
				}
				$hr_data = array(
						'id' => $employee,
						'bank_account_no' => $bank_account_no,
				);
				$hr_result = $this->getDefinedTable(Hr\EmployeeTable::class)->save($hr_data);
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Payroll succesfully updated");
				//return $this->redirect()->toRoute('payroll', array('action'=>'payroll','id'=>$form['year'].'-'.$form['month']));
				return $this->redirect()->toRoute('payroll', array('action'=>'editpayroll', 'id'=>$this->_id));
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to update Payroll");
				return $this->redirect()->toRoute('payroll', array('action'=>'editpayroll', 'id'=>$this->_id));
			endif;
		}	
		return new ViewModel(array(
				'title' => 'Edit Pay roll',
				'payroll' => $this->getDefinedTable(Hr\TempPayrollTable::class)->get($this->_id),
		        'tempPrObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),
				'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),

		));
	}
	
	/**
	 * define pay :edit paydetail
	 */
	public function editpaydetailAction(){
		$this->init();
		list($employee, $payhead, $payheads) = explode('-', $this->_id);
		if($this->getRequest()->isPost()):
			$form=$this->getRequest()->getPost();	
			$roundup = $this->getDefinedTable(Hr\PayheadTable::class)->getColumn($payhead, 'roundup');
			if($roundup == 1):
				$form['amount'] = round($form['amount']);
			endif;		
			if($form['id'] > 0):
				$data = array(
					'id' => $form['id'],
					'pay_head' => $payhead,
					'percent' => $form['percent'],
					'amount' => $form['amount'],
					'dlwp' => $form['dlwp'],
					'ref_no' => $form['ref_no'],
					'remarks' => $form['remarks'],
					'modified' =>$this->_modified,	
				);
			else:
				$data = array(
						'employee' => $employee,
						'pay_head' => $payhead,
						'percent' => $form['percent'],
						'amount' => $form['amount'],
						'dlwp' => $form['dlwp'],
						'ref_no' => $form['ref_no'],
						'remarks' => $form['remarks'],
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
				);
			endif;
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction();//****Transaction begins here ***//
			$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
			if($result > 0):
				//changes in paystructure should affect other payheads and temporary payroll
				foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get($result) as $row);				
				$result1 = $this->calculatePayheadAmount($row);
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ Pay Detail successfully Updated");	
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to Update");
				endif;
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to Update");
			endif;
			return $this->redirect()->toRoute('payroll', array('action'=>'definepay','id'=>$payheads));		
		else:
			$ViewModel = new ViewModel(array(
					'title' => 'Define Payhead',
					'head_type' => $this->getDefinedTable(Hr\PayheadTable::class)->getColumn($payhead, 'type'),
					'paystructure' => $this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('employee'=>$employee, 'sd.pay_head'=> $payhead)),
					'pay_head_id' => $payhead,
					'get_id' => $this->_id,
					'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),
					'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
					'paygroupObj' => $this->getDefinedTable(Hr\PaygroupTable::class),
					'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class)
			));
			$ViewModel->setTerminal(True);
			return $ViewModel;
		endif;
	}
	
	/**
	 * define pay :delete paydetail
	 */
	public function deletepaydetailAction(){
		$this->init();
		list($employee, $payhead, $payheads) = explode('-', $this->_id);
		foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('sd.employee'=>$employee, 'sd.pay_head'=>$payhead)) as $row);
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->remove($row['id']);
		if($result > 0):
			//changes in paystructure should affect other payheads
			$result1 = $this->calculatePayheadAmount($row);
			if($result1 > 0):
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Paydetail deleted successfully");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to delete Paydetail");
			endif;
			//end			
		else:
			$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessenger()->addMessage("error^ Failed to delete Paydetail");
		endif;
		$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
		return $this->redirect()->toUrl($redirectUrl);
	}
	
	/**
	 * define pay :delteAll/resetAll paydetail
	 */
	public function deleteallpaydetailAction(){
		$this->init();
		list($payhead, $payheads) = explode('-', $this->_id);
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('sd.pay_head'=>$payhead)) as $row):
			$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->remove($row['id']);
			if($result > 0):
				//changes in paystructure should affect other payheads
				$result1 = $this->calculatePayheadAmount($row);
				if($result1 <= 0):
					break;
				endif;
				//end			
			else:
				$result1 =0;
				break;
			endif;
		endforeach;
		if($result1 > 0):
			$this->_connection->commit(); // commit transaction on success
			$this->flashMessenger()->addMessage("success^ Paydetail deleted successfully");
		else:
			$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessenger()->addMessage("error^ Failed to delete Paydetail");
		endif;
		$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
		return $this->redirect()->toUrl($redirectUrl);
	}
	/*
	 * Add earning and deduction to paystructure
	 */
	public function addAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();	
			$roundup = $this->getDefinedTable(Hr\PayheadTable::class)->getColumn($form['pay_head'], 'roundup');
			if($roundup == 1):
				$form['amount'] = round($form['amount']);
			endif;					
			$data = array(
					'employee' => $this->_id,
					'pay_head' => $form['pay_head'],
					'percent' => $form['percent'],
					'amount' => $form['amount'],
					'dlwp' => $form['dlwp'],
					'ref_no' => $form['ref_no'],
					'remarks' => $form['remarks'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);			
			if($result > 0):
				//changes in paystructure should affect other payheads
				foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get($result) as $row);				
				$result1 = $this->calculatePayheadAmount($row);
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Pay head successfully added to Pay Structure");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to add new pay head");
				endif;
				//end
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new pay head");
			endif;
			$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
			return $this->redirect()->toUrl($redirectUrl);
			//return $this->redirect()->toRoute('payroll', array('action'=>'paystructure', 'id' => $this->_id));
		}
		$viewModel = new ViewModel(array(
				'employee' => $this->_id,
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),
		));

		$viewModel->setTerminal(True);
		return $viewModel;
	}

	/*
	 * Edit earning & dediction to paystructure
	* */
	public function editAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$roundup = $this->getDefinedTable(Hr\PayheadTable::class)->getColumn($form['pay_head'], 'roundup');
			if($roundup == 1):
				$form['amount'] = round($form['amount']);
			endif;
			$data = array(
					'id' => $this->_id,
					'pay_head' => $form['pay_head'],
					'percent' => $form['percent'],
					'amount' => $form['amount'],
					'dlwp' => $form['dlwp'],
					'ref_no' => $form['ref_no'],
					'remarks' => $form['remarks'],
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
			if($result > 0):			
				//changes in paystructure should affect other payheads
				foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get($this->_id) as $row);				
				$result1 = $this->calculatePayheadAmount($row);
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ Pay Structure successfully Updated");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to Update pay detail in Paystructure");
				endif;
				//end
			else:
				$this->flashMessenger()->addMessage("error^ Failed to Update pay detail in Paystructure");
			endif;
			$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
			return $this->redirect()->toUrl($redirectUrl);
			//return $this->redirect()->toRoute('payroll', array('action'=>'paystructure', 'id' => $employee_id));			
		}
		$viewModel = new ViewModel(array(
				'title' => 'Edit Earning/Deduction',
				'paystructure' => $this->getDefinedTable(Hr\PaystructureTable::Class)->get($this->_id),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
				'paygroupObj' => $this->getDefinedTable(Hr\PaygroupTable::class),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class)
		));
		$viewModel->setTerminal(True);
		return $viewModel;
	}

	/**
	 * action to delete pay head from paystructure
	 */
	public function deleteAction()
	{
		$this->init();
		$employee = $this->getDefinedTable(Hr\PaystructureTable::Class)->getColumn($this->_id,'employee');
		foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get($this->_id) as $row);
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->remove($this->_id);
		if($result > 0):
			//changes in paystructure should affect other payheads
			$result1 = $this->calculatePayheadAmount($row);
			if($result1 > 0):
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Payhead deleted successfully");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to delete Payhead");
			endif;
			//end			
		else:
			$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessenger()->addMessage("error^ Failed to delete Payhead");
		endif;
		$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
		return $this->redirect()->toUrl($redirectUrl);
	}
	/*
	 * Action for add earnings and deductions to temp payroll
	 */
	public function addprAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();	
			$roundup = $this->getDefinedTable(Hr\PayheadTable::class)->getColumn($form['pay_head'], 'roundup');
			if($roundup == 1):
				$form['amount'] = round($form['amount']);
			endif;
			if(empty($form['percent'])):
				$percent=0;
			else:
				$percent=$form['percent'];
			endif;
			if(empty($form['dlwp'])):
				$dlwp=0;
			else:
				$dlwp=$form['dlwp'];
			endif;
			$data = array(
					'employee' => $this->_id,
					'pay_head' => $form['pay_head'],
					'percent' => $percent,
					'amount' => $form['amount'],
					'dlwp' => $dlwp,
					'ref_no' => $form['ref_no'],
					'remarks' => $form['remarks'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($data);exit;
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);				
			if($result > 0):
				//changes in paystructure should affect other payheads
				foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get($result) as $row);				
				$result1 = $this->calculatePayheadAmount($row);
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Pay head successfully added");	
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to add new pay head");
				endif;
				//end
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to add new pay head");
			endif;
			$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
			return $this->redirect()->toUrl($redirectUrl);
			//return $this->redirect()->toRoute('payroll', array('action'=>'paystructure', 'id' => $this->_id));
		}
		$viewModel = new ViewModel(array(
				'employee' => $this->_id,
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),
				'employeedetails' => $this->getDefinedTable(Hr\EmployeeTable::class)->get($this->_id),
		));

		$viewModel->setTerminal(True);
		return $viewModel;
	}

	/*
	 * Edit earning & dediction to temp payroll
	* */
	public function editprAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$roundup = $this->getDefinedTable(Hr\PayheadTable::class)->getColumn($form['pay_head'], 'roundup');
			if($roundup == 1):
				$form['amount'] = round($form['amount']);
			endif;
			if(empty($form['percent'])):
				$percent=0;
			else:
				$percent=$form['percent'];
			endif;
			if(empty($form['dlwp'])):
				$dlwp=0;
			else:
				$dlwp=$form['dlwp'];
			endif;
			$data = array(
					'id' => $this->_id,
					'pay_head' => $form['pay_head'],
					'percent' => $percent,
					'amount' => $form['amount'],
					'dlwp' => $dlwp,
					'ref_no' => $form['ref_no'],
					'remarks' => $form['remarks'],
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
			if($result > 0):
				//changes in paystructure should affect other payheads and temporary payroll
				foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get($this->_id) as $row);				
				$result1 = $this->calculatePayheadAmount($row);	
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ Pay detail successfully Updated");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to Update pay detail");
				endif;
				//end
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to Update pay detail");
			endif;	
			$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
			return $this->redirect()->toUrl($redirectUrl);
			//return $this->redirect()->toRoute('payroll', array('action'=>'paystructure', 'id' => $employee));			
		}
		$viewModel = new ViewModel(array(
				'title' => 'Edit Earning/Deduction',
				'paystructure' => $this->getDefinedTable(Hr\PaystructureTable::Class)->get($this->_id),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
				'paygroupObj' => $this->getDefinedTable(Hr\PaygroupTable::class),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class)
		));
		$viewModel->setTerminal(True);
		return $viewModel;
	}

	/**
	 * action to delete
	 */
	public function deleteprAction()
	{
		$this->init();
		$employee = $this->getDefinedTable(Hr\PaystructureTable::Class)->getColumn($this->_id,'employee');
		foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get($this->_id) as $row);
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->remove($this->_id);
		if($result > 0):
			//changes in paystructure should affect other payheads
			$result1 = $this->calculatePayheadAmount($row);
			if($result1 > 0):
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Payhead deleted successfully");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to delete Payhead");
			endif;
			//end			
		else:
			$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessenger()->addMessage("error^ Failed to delete Payhead");
		endif;
		$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
		return $this->redirect()->toUrl($redirectUrl);
	}
	/*
	 * Ajax response action to get payslab type
	 * actual amount(value), percent, slab
	* */

	public function getslabtypeAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$request = $this->getRequest()->getPost();			
			$ViewModel = new ViewModel(array(
					'employee' => $request['employee'],
					'pay_head' => $request['pay_head'],
					'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
					'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),
					'tempPayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
					'payslabTable' => $this->getDefinedTable(Hr\PaySlabTable::class),
					'paygroupObj' => $this->getDefinedTable(Hr\PaygroupTable::class)
			));
			$ViewModel->setTerminal(True);
			return $ViewModel;
		endif;
		exit;
	}
	
		/**
	 * delete pepf  action
	 */
		public function deletepeAction()
	{
		
		$this->init();
		foreach($this->getDefinedTable(Hr\PepfTable::Class)->get($this->_id) as $pfs);
		//print_r($pfs);exit;
		$month=$pfs['month'];
		$year=$pfs['year'];
		$result =$this->getDefinedTable(Hr\PepfTable::Class)->remove($pfs['id']);
		if($result > 0):
				$this->flashMessenger()->addMessage("success^ deleted successfully");
			else:
				
				$this->flashMessenger()->addMessage("error^ Failed to delete");
			endif;
			//end			
		
			return $this->redirect()->toRoute('payroll',array('action' => 'viewpepf','id'=>$year.'-'.$month));	
		
	}
	/*
	 * Submit payroll to the accounts section
	* */
	public function submitpayrollAction()
	{
		$this->init();
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		foreach($this->getDefinedTable(Hr\TempPayrollTable::class)->getAll() as $temp_payroll):
			$payroll_data = array(
				'employee' => $temp_payroll['employee'],
				'emp_his' => $temp_payroll['emp_his'],
				'year' => $temp_payroll['year'],
				'month' => $temp_payroll['month'],
				'working_days' => $temp_payroll['working_days'],
				'leave_without_pay' => $temp_payroll['leave_without_pay'],
				//'activity' => $temp_payroll['activity'],
				'gross' => $temp_payroll['gross'],
				'total_deduction' => $temp_payroll['total_deduction'],
				'bonus' => $temp_payroll['bonus'],
				'leave_encashment' => $temp_payroll['leave_encashment'],
				'net_pay' => $temp_payroll['net_pay'],
				'earning_dlwp' => $temp_payroll['earning_dlwp'],
				'deduction_dlwp' => $temp_payroll['deduction_dlwp'],
				'status' => '1', // initiated
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			$payroll_data = $this->_safedataObj->rteSafe($payroll_data);
			$result = $this->getDefinedTable(Hr\PayrollTable::class)->save($payroll_data);
			if($result > 0):
				foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('employee'=> $temp_payroll['employee'])) as $pay_detail):
					$default_amt = $pay_detail['amount'];
					if($pay_detail['dlwp'] == 1):
						$working_days = $temp_payroll['working_days'];
						$leave_without_pay = $temp_payroll['leave_without_pay'];
						$amt = ($default_amt / $working_days)*$leave_without_pay;
						$final_amt = $default_amt - $amt;
					else:
						$final_amt = $default_amt;
					endif;
					if($pay_detail['roundup'] == 1):
						$final_amt = round($final_amt);
					endif;
					$paydetail_data = array(
						'pay_roll' => $result,
						'pay_head' => $pay_detail['pay_head_id'],
						'amount' => $final_amt,
						'actual_amount' => $default_amt,
						'ref_no' => $pay_detail['ref_no'],
						'remarks' => $pay_detail['remarks'],
						'author' => $this->_author,
						'created' => $this->_created,
						'modified' =>$this->_modified,
					);
					$paydetail_data = $this->_safedataObj->rteSafe($paydetail_data);
					$result1 = $this->getDefinedTable(Hr\PaydetailTable::class)->save($paydetail_data);
					if($result1 <= 0):
						break;
					endif;
				endforeach;
				if($result1 <= 0):
					break;
				endif;
			else:
				break;
			endif;
		endforeach; 
		if($result1 > 0 && $result > 0):
			$this->_connection->commit(); // Success transaction
			$this->flashMessenger()->addMessage("success^ Payroll successfully submitted");	
		else:
			$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessenger()->addMessage("error^ Failed while submitting payroll, Try again after some time");	
			return $this->redirect()->toRoute('payroll', array('action'=>'definepayroll'));
		endif;
		return $this->redirect()->toRoute('payroll', array('action'=>'index'));
	}
	
	/**
	 * payslip Action
	 */
	public function pepfAction()
	{
		$this->init();
        if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
		}else{
			$year = date('Y');
			$month = (int)date('m');
        }
        $admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
	
        return new ViewModel(array( 
            'title' => 'PE PF',
            'year' =>$year,
            'month' =>$month,
            'role'=>$this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'role'),
            'pepf' => $this->getDefinedTable(Hr\PepfTable::class)->getpepf($year),
          //  'pepflist' => $this->getDefinedTable(Hr\PepfTable::class)->get(array('month'=> $month,'year'=>$year)),
			'admin_locs' => $admin_locs,
			'pepfObj' => $this->getDefinedTable(Hr\PepfTable::class),
			//'lesserentObj' => $this->getDefinedTable(Realestate\LeasedRentTable::class),
    
        ));
	}
	public function generatepepfAction()
    {
        $this->init();
        $my = explode('-', $this->_id);
        $employee = $this->getDefinedTable(Hr\PayrollTable::class)->get(array('pr.year'=>$my[0],'pr.month'=>$my[1]));
		//echo '<pre>';print_r($employee);exit;
			foreach($employee as $row):
			$basic_pay=$this->getDefinedTable(Hr\PaydetailTable::class)->getColumn(array('pay_roll'=>$row['id'],'pay_head'=>1),'actual_amount');
			if(!empty($basic_pay)){
			   $data = array(
					'employee' 	=> $row['employee'],
					'basic_salary' 	=> $basic_pay,
					'location' 	=> $row['location_id'],
					'pe_pf' 		=> ceil($basic_pay*0.15),
					'year' 		=> $my[0],
					'month' 	=> $my[1],
					'ref_no' 	=> $my[1].",".$my[0],
					'status' 	=> 2,
					'author' 	=> $this->_author,
					'created' 	=> $this->_created,
					'modified' 	=> $this->_modified,
				);
				//echo '<pre>';print_r($data);
			$result = $this->getDefinedTable(Hr\PepfTable::class)->save($data);
			}
			endforeach;
			//exit;
            if ($result) {
                $this->flashMessenger()->addMessage("success^ Action Successful");
            } else {

                $this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
            }
            return $this->redirect()->toRoute('payroll',array('action' => 'pepf'));

    }
	public function submitpepfAction()
    {
        $this->init();
        $my = explode('-', $this->_id);
        $pepf = $this->getDefinedTable(Hr\PepfTable::class)->get(array('year'=>$my[0],'month'=>$my[1]));
        $locationlist = $this->getDefinedTable(Hr\PepfTable::class)->getDistinct('location',array('year'=>$my[0],'month'=>$my[1]));
		//echo '<pre>';print_r($locationlist);exit;
			foreach($pepf as $row):
			
			   $data = array(
					'id' 	=> $row['id'],
					'status' 	=> 4,
					'author' 	=> $this->_author,
					'modified' 	=> $this->_modified,
				);
				//echo '<pre>';print_r($data);
			//$this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Hr\PepfTable::class)->save($data);
			endforeach;
		//	exit;
            if($result){
			$day=date('d');
			$pepf_date=$my[0].'-'.$my[1].'-'.$day;
			$location=$this->getDefinedTable(Administration\UsersTable::class)->getcolumn($this->_author, 'location');
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($location, 'prefix');
			$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(23,'prefix');
			$date = date('ym',strtotime($pepf_date));
				$tmp_VCNo = $loc.'-'.$prefix.$date;
				
				$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
				
				$pltp_no_list = array();
				foreach($results as $result):
					array_push($pltp_no_list, substr($result['voucher_no'], -3));
				endforeach;
				$next_serial = max($pltp_no_list) + 1;
					
				switch(strlen($next_serial)){
					case 1: $next_dc_serial = "0000".$next_serial; break;
					case 2: $next_dc_serial = "000".$next_serial;  break;
					case 3: $next_dc_serial = "00".$next_serial;   break;
					case 4: $next_dc_serial = "0".$next_serial;    break;
					default: $next_dc_serial = $next_serial;       break;
				}	
				$voucher_no = $tmp_VCNo.$next_dc_serial;
				$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($location,'region');
					$data1 = array(
						'voucher_date' =>$pepf_date,
						'voucher_type' => 23,
						'region'   =>$region,
						'doc_id'   =>"PE PF",
						'voucher_no' => $voucher_no,
						'voucher_amount' => $this->getDefinedTable(Hr\PepfTable::class)->getSum(array('year'=>$my[0],'month'=>$my[1]),'pe_pf'),
						'status' => 4, // status initiated 
						'author' =>$this->_author,
						'created' =>$this->_created,  
						'modified' =>$this->_modified,
					);
					$this->_connection->beginTransaction();
					$resultt = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
					$tdetailsdata = array(
						'transaction' => $resultt,
						'voucher_dates' =>$pepf_date,
						'voucher_types' => 23,
						'location' => $location,
						'head' =>150,
						'sub_head' =>2552,
						'bank_ref_type' => '',
						'debit' =>'0.000',
						'against'=>0,
						'credit' => $this->getDefinedTable(Hr\PepfTable::class)->getSum(array('year'=>$my[0],'month'=>$my[1]),'pe_pf'),
						'ref_no'=> "", 
						'type' => '1',//user inputted  data
						'status' => 4, // status initiated
						'activity'=>$location,
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
					$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
					foreach($locationlist as $locs):
					$tdetailsdata = array(
						'transaction' => $resultt,
						'voucher_dates' => $pepf_date,
						'voucher_types' => 23,
						'location' => $locs['column'],
						'head' =>196,
						'sub_head' =>2544,
						'bank_ref_type' => '',
						'debit' =>$this->getDefinedTable(Hr\PepfTable::class)->getSum(array('year'=>$my[0],'month'=>$my[1],'location'=>$locs['column']),'pe_pf'),
						'credit' =>'0.000',
						'against' =>'0',
						'ref_no'=> "", 
						'type' => '1',//user inputted  data
						'status' => 4, // status initiated
						'activity'=>$locs['column'],
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
					$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
					endforeach;
				$this->_connection->commit();
                $this->flashMessenger()->addMessage("success^ Action Successful");
            } else {
				$this->_connection->rollback();
                $this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
            }
            return $this->redirect()->toRoute('payroll',array('action' => 'viewpepf','id'=>$my[0].'-'.$my[1]));

    }
	
	//edit building
    public function viewpepfAction()
    {
        $this->init();
		if(isset($this->_id) & $this->_id!=0):
			$my = explode('-', $this->_id);
		endif;
		if(sizeof($my)==0):
			$my = array('1'); //default selection
		endif;
	
        $role=$this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'role');
        
       //print_r($locationlist);exit;
        return new ViewModel(array(
            'title' 			=> 'Details',
			'pepf' 				=> $this->getDefinedTable(Hr\PepfTable::class)->getPepfByMonth($my[0],$my[1]),
			'month'				=> $my[1],
			'year'				=> $my[0],
            'employeeObj' 		    => $this->getDefinedTable(Hr\EmployeeTable::class),
            'locationObj' 		=> $this->getDefinedTable(Administration\LocationTable::class),
      
        ));
    }
	
	/**
	 * Monthly salary booking in transaction
	 */
	public function booksalaryAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			// booking to transaction
			if(isset($form['voucher_date']) && isset($form['voucher_amount'])):
				//generate voucher no
				$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
				$date = date('ym',strtotime($form['voucher_date']));
				$tmp_VCNo = $loc.$prefix.$date;
				//$serial = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($date) + 1;
				
				$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
				
				$pltp_no_list = array();
				foreach($results as $result):
					array_push($pltp_no_list, substr($result['voucher_no'], 8));
				endforeach;
				$next_serial = max($pltp_no_list) + 1;
				switch(strlen($next_serial)){
					case 1: $next_dc_serial = "000".$next_serial; break;
					case 2: $next_dc_serial = "00".$next_serial;  break;
					case 3: $next_dc_serial = "0".$next_serial;   break;
					default: $next_dc_serial = $next_serial;       break;
				}	
				$voucher_no = $tmp_VCNo.$next_dc_serial;
					
				//$voucher_no = $loc.$prefix.$date.$serial;
				$data1 = array(
						'voucher_date' => $form['voucher_date'],
						'voucher_type' => $form['voucher_type'],
						'doc_id' => $form['doc_id'],
						'doc_type' => $form['doc_type'],
						'voucher_no' => $voucher_no,
						'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
						'remark' => $form['remark'],
						'status' => '1',
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
				);
				$data1 = $this->_safedataObj->rteSafe($data1);
				$this->_connection->beginTransaction(); //***Transaction begins here***//
				//$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
				if($result > 0):
					//insert into salarybooking table
					$sb_data = array(
							'transaction' => $result,
							'year' => $form['year'],
							'month' => $form['month'],
							'salary_advance' => '1',
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
					);
					//$result1 = $this->getDefinedTable(Hr\SalarybookingTable::class)->save($sb_data);
					if($result1 > 0):
						//insert into transactiondetail table from payroll table
						$data = array(
							'year' => $form['year'],
							'month' => $form['month'],
						);			
						$locations = $this->getDefinedTable(Hr\PayrollTable::class)->salaryBookingLocation($data);
						
						foreach($locations as $loc_row):
							$activities = $this->getDefinedTable(Hr\PayrollTable::class)->salaryBookingActivity($loc_row['location_id']);
							foreach($activities as $act_row):
								$sh_data = array(
									'year' => $data['year'],
									'month' => $data['month'],
									'location' => $loc_row['location_id'],
									'activity' => $act_row['activity_id'],
									'deduction' => '0',
								);
								$subheads = $this->getDefinedTable(Hr\PayrollTable::class)->salaryBookingSubhead($sh_data);
								foreach($subheads as $subhead_row):
									$filter = array(
										'year' => $sh_data['year'],
										'month' => $sh_data['month'],
										'location' => $sh_data['location'],
										'activity' => $sh_data['activity'],
										'subhead' => $subhead_row['ref_id'],
										'region' => '-1',
										'department' => '-1',													
									);
									$amt = $this->getDefinedTable(Hr\PaydetailTable::class)->getAmtforSummary($filter);
									
									if((int)$amt > 0):
										if($subhead_row['deduction'] == 1):
											$credit_amt = $amt;
											$debit_amt = '0.00';
										else:
											$credit_amt = '0.00';
											$debit_amt = $amt;
										endif;
										$tdtlsdata = array(
												'transaction' => $result,
												'location' => $loc_row['location_id'],
												'activity' => $act_row['activity_id'],
												'head' => $subhead_row['head_id'],
												'sub_head' => $subhead_row['id'],
												'bank_ref_type' => '',
												'cheque_no' => '',
												'debit' => $debit_amt,
												'credit' => $credit_amt,
												'ref_no'=> '',
												'type' => '2',//system generated data
												'author' =>$this->_author,
												'created' =>$this->_created,
												'modified' =>$this->_modified,
										);
										$tdtlsdata = $this->_safedataObj->rteSafe($tdtlsdata);
										//$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdtlsdata);
										if($result2 <= 0):
											break;
										endif;
									endif;
								endforeach;
								if($result2 <= 0):
									break;
								endif;
							endforeach;
							if($result2 <= 0):
								break;
							endif;
						endforeach;
						
						$sh_data2 = array(
							'year' => $data['year'],
							'month' => $data['month'],
							'location' => '-1',
							'activity' => '-1',
							'deduction' => '1',
						);
						$subheads2 = $this->getDefinedTable(Hr\PayrollTable::class)->salaryBookingSubhead($sh_data2);
						foreach($subheads2 as $subhead_row2):
							$filter2 = array(
								'year' => $sh_data2['year'],
								'month' => $sh_data2['month'],
								'location' => '-1',
								'activity' => '-1',
								'subhead' => $subhead_row2['ref_id'],
								'region' => '-1',
								'department' => '-1',													
							);
							$amt2 = $this->getDefinedTable(Hr\PaydetailTable::class)->getAmtforSummary($filter2);
							
							if((int)$amt2 > 0):
								if($subhead_row2['deduction'] == 1):
									$credit_amt2 = $amt2;
									$debit_amt2 = '0.00';
								else:
									$credit_amt2 = '0.00';
									$debit_amt2 = $amt2;
								endif;
								$tdtlsdata2 = array(
										'transaction' => $result,
										'location' => '7',
										'activity' => '5',
										'head' => $subhead_row2['head_id'],
										'sub_head' => $subhead_row2['id'],
										'bank_ref_type' => '',
										'cheque_no' => '',
										'debit' => $debit_amt2,
										'credit' => $credit_amt2,
										'ref_no'=> '',
										'type' => '2',//system generated data
										'author' =>$this->_author,
										'created' =>$this->_created,
										'modified' =>$this->_modified,
								);
								$tdtlsdata2 = $this->_safedataObj->rteSafe($tdtlsdata2);
								//$result4 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdtlsdata2);
								if($result4 <= 0):
									break;
								endif;
							endif;
						endforeach;
						if($result2 > 0 && $result4 >0):
							//insert into transactiondetail table from form
							$location= $form['location'];
							$activity= $form['activity'];
							$head= $form['head'];
							$sub_head= $form['sub_head'];
							$cheque_no= $form['cheque_no'];
							$debit= $form['debit'];
							$credit= $form['credit'];
							for($i=0; $i < sizeof($activity); $i++):
								if(isset($activity[$i]) && is_numeric($activity[$i])):
									$tdetailsdata = array(
											'transaction' => $result,
											'location' => $location[$i],
											'activity' => $activity[$i],
											'head' => $head[$i],
											'sub_head' => $sub_head[$i],
											'bank_ref_type' => '',
											'cheque_no' => $cheque_no[$i],
											'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
											'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
											'ref_no'=> '',
											'type' => '1',//user inputted  data
											'author' =>$this->_author,
											'created' =>$this->_created,
											'modified' =>$this->_modified,
									);
									$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
									//$result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
									if($result3 <= 0):
										break;
									endif;
								endif;
							endfor;
							if($result3 > 0):
								$this->_connection->commit(); // commit transaction on success
								$this->flashMessenger()->addMessage("success^ New Transaction successfully added | ".$voucher_no);
								return $this->redirect()->toRoute('transaction', array('action' =>'viewtransaction', 'id' => $result));
							else:
								$this->_connection->rollback(); // rollback the transaction on failure
								$this->flashMessenger()->addMessage("error^ Failed to book salary to transaction");
							endif;
						else:
							$this->_connection->rollback(); // rollback the transaction on failure
							$this->flashMessenger()->addMessage("error^ Failed to book salary to transaction");
						endif;
					else:
						$this->_connection->rollback(); // rollback the transaction on failure
						$this->flashMessenger()->addMessage("error^ Failed to book salary to transaction");
					endif;
				else:
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Transaction successfully added | ".$voucher_no);
					return $this->redirect()->toRoute('transaction', array('action' =>'viewtransaction', 'id' => $result));
				endif;
				return $this->redirect()->toRoute('payroll', array('action'=>'payroll', 'id'=> $form['year'].'-'.$form['month']));
			else:
				if(isset($form['year']) && isset($form['month'])):
					//check if all the payheads have subheads for booking
					$payhead_types = $this->getDefinedTable(Hr\PayheadtypeTable::class)->getNotIn();
					$data = array(
							'year' => $form['year'],
							'month' => $form['month'],
					);			
					$locations = $this->getDefinedTable(Hr\PayrollTable::class)->salaryBookingLocation($data);
					return new ViewModel(array(
						'title'  => 'Salary Booking',
						'data' => $data,
						'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
						'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
						'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
						'journals' => $this->getDefinedTable(Accounts\JournalTable::class)->getAll(),
						'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
						'heads' => $this->getDefinedTable(Accounts\HeadTable::class)->getAll(),
						'locations' => $locations,
						'payrollObj' => $this->getDefinedTable(Hr\PayrollTable::class),
						'paydetailObj' => $this->getDefinedTable(Hr\PaydetailTable::class),
						'payhead_types' => $payhead_types,
					));				
				endif;
			endif;
		endif;
		$this->flashMessenger()->addMessage("error^ Failed to book salary to transaction");	
		
		return $this->redirect()->toRoute('payroll', array('action'=>'payroll', 'id'=> $form['year'].'-'.$form['month']));
	}
	/**
	 * Process Bill Action
	 *
	 */
	public function commitpayrollAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()):		
			$form = $this->getRequest()->getPost()->toArray();	
			
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			/*Get users under destination location with sub role Depoy Manager*/
				$region = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('id'=>$this->_author),'region');
				$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(12,'prefix');
				$date = date('ym',strtotime(date('Y-m-d')));
				$tmp_VCNo = $loc.'-'.$prefix.$date;
				
				$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
				
				$pltp_no_list = array();
				foreach($results as $result):
					array_push($pltp_no_list, substr($result['voucher_no'], 14));
				endforeach;
				$next_serial = max($pltp_no_list) + 1;
					
				switch(strlen($next_serial)){
					case 1: $next_dc_serial = "0000".$next_serial; break;
					case 2: $next_dc_serial = "000".$next_serial;  break;
					case 3: $next_dc_serial = "00".$next_serial;   break;
					case 4: $next_dc_serial = "0".$next_serial;    break;
					default: $next_dc_serial = $next_serial;       break;
				}	
				$voucher_no = $tmp_VCNo.$next_dc_serial;
			//if($form['action'] == "1")  
			   // {    /* Send bill */
			$location= $this->_user->location;
			$payrollNetAmount = $this->getDefinedTable(Hr\PayrollTable::class)->getSumGross('gross',array('year'=>$form['year'],'month'=>$form['month']));
			$payrollNetPay = $this->getDefinedTable(Hr\PayrollTable::class)->getSumGross('net_pay',array('year'=>$form['year'],'month'=>$form['month']));
		   //print_r($payrollNetPay);exit;
		   $yearMonth=$form['year'].'-'.$form['month'];
			$day=1;
			$voucher_date=$yearMonth.'-'.$day;
					$data = array(
						'voucher_date' 		=> $voucher_date,
						'voucher_type' 		=> 12,
						'region'   			=>$region,
						'doc_id'   			=>"Payroll",
						'voucher_no' 		=> $voucher_no,
						'voucher_amount' 	=> str_replace( ",", "",$payrollNetAmount),
						'status' 			=> 3, // status initiated 
						'remark'			=>$yearMonth,
						'author' 			=>$this->_author,
						'created' 			=>$this->_created,  
						'modified' 			=>$this->_modified,
					);
					$resultTrans = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data);
					if($resultTrans >0){
						$flow=array(
							'flow' 				=> 5,
							'application' 		=> $resultTrans,
							'activity'			=>$location,
							//'role_id'   		=>130,
							'actor'   			=> 6,
							'action' 			=> "3|4",
							'routing' 			=> 2,
							'status' 			=> 3, // status initiated 
							'routing_status'	=>2,
							'action_performed'	=>1,
							'description'		=>"Payroll",
							'author' 			=>$this->_author,
							'created' 			=>$this->_created,  
							'modified' 			=>$this->_modified,
						);
						$flow=$this->getDefinedTable(Administration\FlowTransactionTable::class)->save($flow);
						$PayheadDistinct=$this->getDefinedTable(Hr\PaydetailTable::class)->getDistinct('ph.payhead_type',array('deduction'=>0));
						$data1=array(
						'year'=>$form['year'],
						'month'=>$form['month']);
						$Payhead=$this->getDefinedTable(Hr\PaydetailTable::class)->getDistinctReport('location',$data1,array('deduction'=>0));
						foreach($Payhead as $Payheads):
						$subhead=$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('ref_id'=>$Payheads['id'],'type'=>'5'),'id');
						$head=$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('ref_id'=>$Payheads['id'],'type'=>'5'),'head');
						$empexpensedata = array(
							'transaction' => $resultTrans,
							'voucher_dates' => $data['voucher_date'],
							'voucher_types' => 12,
							'location' => $Payheads['location'],
							'head' =>$head,
							'sub_head' =>$subhead,
							'bank_ref_type' => '',
							'debit' =>$Payheads['amount'],
							'credit' =>'0.00',
							'ref_no'=> 'PAYROLL', 
							'type' => '1',//user inputted  data  
							'status' => 3, // status appied
							'activity'=>$Payheads['location'],
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
					   $empexpensedata = $this->_safedataObj->rteSafe($empexpensedata);
					   $result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($empexpensedata);
					endforeach;
					/**Current Liabilities */
					$PayheadDed=$this->getDefinedTable(Hr\PaydetailTable::class)->getDistinctDeduction('ph.payhead_type',$data1,array('deduction'=>1));
						foreach($PayheadDed as $PayheadDeds):
							$subhead=$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('ref_id'=>$PayheadDeds['id'],'type'=>'5'),'id');
							$head=$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('ref_id'=>$PayheadDeds['id'],'type'=>'5'),'head');
						$cldata = array(
							'transaction' => $resultTrans,
							'voucher_dates' => $data['voucher_date'],
							'voucher_types' => 12,
							'location' => $location,
							'head' =>$head,
							'sub_head' =>$subhead,
							'bank_ref_type' => '',
							'debit' =>'0.00',
							'credit' =>$PayheadDeds['amount'],
							'ref_no'=> 'PAYROLL', 
							'type' => '1',//user inputted  data
							'status' => 3, // status applied
							'activity'=>$location,
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						
						$cldata = $this->_safedataObj->rteSafe($cldata);
						$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($cldata);
					endforeach;
					$employeeDeduction=$this->getDefinedTable(Hr\PaydetailTable::class)->getEmployeeDed('location',$data1,array('ph.payhead_type'=>30));
						foreach($employeeDeduction as $employeeDeductions):
							$subhead=$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('ref_id'=>$employeeDeductions['id'],'type'=>'5'),'id');
							$head=$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('ref_id'=>$employeeDeductions['id'],'type'=>'5'),'head');
							
						$empdeductiondata = array(
							'transaction' => $resultTrans,
							'voucher_dates' => $data['voucher_date'],
							'voucher_types' => 12,
							'location' => $employeeDeductions['location'],
							'head' =>$head,
							'sub_head' =>$subhead,
							'bank_ref_type' => '',
							'debit' =>'0.00',
							'credit' =>$employeeDeductions['amount'],
							'ref_no'=> 'PAYROLL', 
							'type' => '1',//user inputted  data
							'status' => 3, // status applied
							'activity'=>$employeeDeductions['location'],
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$empdeductiondata = $this->_safedataObj->rteSafe($empdeductiondata);
						$result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($empdeductiondata);
					endforeach;
					$netpaydata = array(
							'transaction' => $resultTrans,
							'voucher_dates' => $data['voucher_date'],
							'voucher_types' => 12,
							'location' => $location,
							'head' =>'36',
							'sub_head' =>'172',
							'bank_ref_type' => '',
							'debit' =>'0.00',
							'credit' =>str_replace( ",", "",$payrollNetPay),
							'ref_no'=> 'PAYROLL', 
							'type' => '1',//user inputted  data
							'status' => 3, // status applied
							'activity'=>$location,
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$netpaydata = $this->_safedataObj->rteSafe($netpaydata);
						$result4 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($netpaydata);
					}
							
				if($result1):
			    	$notification_data = array(
					    'route'         => 'transaction',
						'action'        => 'againstdebit',
						'key' 		    => $resultTrans,
						'description'   => 'Payroll',
						'author'	    => $this->_author,
						'created'       => $this->_created,
						'modified'      => $this->_modified,   
					);
					//print_r($notification_data);exit;
					$notificationResult = $this->getDefinedTable(Acl\NotificationTable::class)->save($notification_data);
					//echo $notificationResult; exit;
					if($notificationResult > 0 ){	
						$user = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>array('6')));
						foreach($user as $row):						    
						    $user_location_id = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($row['id'], 'location');
						    if($user_location_id == $sourceLocation ):						
							    $notify_data = array(
								    'notification' => $notificationResult,
									'user'    	   => $row['id'],
									'flag'    	 => '0',
									'desc'    	 => 'New PayrolL',
									'author'	 => $this->_author,
									'created'    => $this->_created,
									'modified'   => $this->_modified,  
 								);
								//print_r($notify_data);exit;
								$notifyResult = $this->getDefinedTable(Acl\NotifyTable::class)->save($notify_data);
							endif;
						endforeach;
					}
				$this->_connection->commit(); // commit transaction over success
				$this->flashMessenger()->addMessage("success^Submitted the data");
			else:
			    $this->flashMessenger()->addMessage("error^ Cannot send request");			  
			endif;
			return $this->redirect()->toRoute('payroll',array('action'=>'payroll'));
		endif; 		
		$viewModel =  new ViewModel(array(
			'title' => 'Payroll',
			'id'  => $this->_id,
			'userID' => $this->_author,
		));	
		$viewModel->setTerminal('false');
        return $viewModel;	
	}
	/**
	 * Monthly salary booking in transaction
	 */
	public function bookadvancesalaryAction()
	{
		$this->init();
		
		list($year, $month) = explode('-', $this->_id);
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			// booking to transaction
			if(isset($form['voucher_date']) && isset($form['voucher_amount'])):
				//generate voucher no
				$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
				$date = date('ym',strtotime($form['voucher_date']));
				$tmp_VCNo = $loc.$prefix.$date;
				
				$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
				
				$pltp_no_list = array();
				foreach($results as $result):
					array_push($pltp_no_list, substr($result['voucher_no'], 8));
				endforeach;
				$next_serial = max($pltp_no_list) + 1;
				switch(strlen($next_serial)){
					case 1: $next_dc_serial = "000".$next_serial; break;
					case 2: $next_dc_serial = "00".$next_serial;  break;
					case 3: $next_dc_serial = "0".$next_serial;   break;
					default: $next_dc_serial = $next_serial;       break;
				}	
				$voucher_no = $tmp_VCNo.$next_dc_serial;
				
				$data1 = array(
						'voucher_date' => $form['voucher_date'],
						'voucher_type' => $form['voucher_type'],
						'doc_id' => $form['doc_id'],
						'doc_type' => $form['doc_type'],
						'voucher_no' => $voucher_no,
						'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
						'remark' => $form['remark'],
						'status' => '3',
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
				);
				$data1 = $this->_safedataObj->rteSafe($data1);
				$this->_connection->beginTransaction(); //***Transaction begins here***//
				$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
				if($result > 0):
					//insert into salarybooking table
					$sb_data = array(
							'transaction' => $result,
							'year' => $form['year'],
							'month' => $form['month'],
							'salary_advance' => '2',
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
					);
					$result1 = $this->getDefinedTable(Hr\SalarybookingTable::class)->save($sb_data);
					if($result1 > 0):
						//insert into transactiondetail table from payroll table
						$data = array(
							'year' => $form['year'],
							'month' => $form['month'],
						);			
						$locations = $this->getDefinedTable(Hr\PayrollTable::class)->salaryBookingLocation($data);
						
						foreach($locations as $loc_row):
							$activities = $this->getDefinedTable(Hr\PayrollTable::class)->salaryBookingActivity($loc_row['location_id']);
							foreach($activities as $act_row):
								$sh_data = array(
									'year' => $data['year'],
									'month' => $data['month'],
									'location' => $loc_row['location_id'],
									'activity' => $act_row['activity_id'],
								);
								$subheads = $this->getDefinedTable(Hr\PayrollTable::class)->salaryAdvanceSubhead($sh_data);
								foreach($subheads as $subhead_row):
									$payroll_id = $this->getDefinedTable(Hr\PayrollTable::class)->getColumn(array('employee' =>$subhead_row['ref_id'],'year'=>$data['year'],'month'=>$data['month']),'id'); 	
									$amt = $this->getDefinedTable(Hr\PaydetailTable::class)->getColumn(array('pay_roll'=>$payroll_id,'pay_head'=>'12'),'amount');
									
									if((int)$amt > 0):
										$credit_amt = $amt;
										$debit_amt = '0.00';
										
										$tdtlsdata = array(
												'transaction' => $result,
												'location' => $loc_row['location_id'],
												'activity' => $act_row['activity_id'],
												'head' => $subhead_row['head_id'],
												'sub_head' => $subhead_row['id'],
												'bank_ref_type' => '',
												'cheque_no' => '',
												'debit' => $debit_amt,
												'credit' => $credit_amt,
												'ref_no'=> '',
												'type' => '2',//system generated data
												'author' =>$this->_author,
												'created' =>$this->_created,
												'modified' =>$this->_modified,
										);
										
										$tdtlsdata = $this->_safedataObj->rteSafe($tdtlsdata);
										$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdtlsdata);
									endif;
								endforeach;
							endforeach;
						endforeach;
						
						if($result2 > 0):
							//insert into transactiondetail table from form
							$location= $form['location'];
							$activity= $form['activity'];
							$head= $form['head'];
							$sub_head= $form['sub_head'];
							$cheque_no= $form['cheque_no'];
							$debit= $form['debit'];
							$credit= $form['credit'];
							for($i=0; $i < sizeof($activity); $i++):
								if(isset($activity[$i]) && is_numeric($activity[$i])):
									$tdetailsdata = array(
											'transaction' => $result,
											'location' => $location[$i],
											'activity' => $activity[$i],
											'head' => $head[$i],
											'sub_head' => $sub_head[$i],
											'bank_ref_type' => '',
											'cheque_no' => $cheque_no[$i],
											'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
											'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
											'ref_no'=> '',
											'type' => '1',//user inputted  data
											'author' =>$this->_author,
											'created' =>$this->_created,
											'modified' =>$this->_modified,
									);
									$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
									$result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
								endif;
							endfor;
							if($result3 > 0):
								$this->_connection->commit(); // commit transaction on success
								$this->flashMessenger()->addMessage("success^ New Transaction successfully added | ".$voucher_no);
								return $this->redirect()->toRoute('transaction', array('action' =>'viewtransaction', 'id' => $result));
							else:
								$this->_connection->rollback(); // rollback the transaction on failure
								$this->flashMessenger()->addMessage("error^ Failed to book salary to transaction. Please transaction details");
							endif;
						else:
							$this->_connection->rollback(); // rollback the transaction on failure
							$this->flashMessenger()->addMessage("error^ Failed to book advance salary to transaction. Please check transaction details.");
						endif;
					else:
						$this->_connection->rollback(); // rollback the transaction on failure
						$this->flashMessenger()->addMessage("error^ Failed to book advance salary to transaction. Please Check the transaction year and month.");
					endif;
				else:
					$this->_connection->rollback(); // rollback the transaction on failure
					$this->flashMessenger()->addMessage("error^ Failed to book advance salary to transaction. Please check transaction fields");
				endif;
			else:
				$this->_connection->rollback(); // rollback the transaction on failure
				$this->flashMessenger()->addMessage("error^ Failed to book advance salary to transaction. Please check voucher date and amount.");
			endif;
			return $this->redirect()->toRoute('payroll', array('action'=>'payroll', 'id'=> $form['year'].'-'.$form['month']));
		else:
			if(isset($year) && isset($month)):
				$data = array(
						'year' => $year,
						'month' => $month,
				);			
				$locations = $this->getDefinedTable(Hr\PayrollTable::class)->salaryBookingLocation($data);
				return new ViewModel(array(
					'title'  => 'Advance Salary Booking',
					'data' => $data,
					'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
					'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
					'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
					'journals' => $this->getDefinedTable(Accounts\JournalTable::class)->getAll(),
					'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
					'heads' => $this->getDefinedTable(Accounts\HeadTable::class)->getAll(),
					'locations' => $locations,
					'payrollObj' => $this->getDefinedTable(Hr\PayrollTable::class),
					'paydetailObj' => $this->getDefinedTable(Hr\PaydetailTable::class),
					'employeeObj'  => $this->getDefinedTable(Hr\EmployeeTable::class),
				));				
			endif;
		endif;
	}
	
	/*
	 * function to calculate payhead amount on change of payheads
	 */
	public function calculatePayheadAmount($paystructure){
		$payhead_id =$paystructure['pay_head_id'];	
		$employee = $paystructure['employee'];
		$payhead_type = $this->getDefinedTable(Hr\PayheadTable::class)->getColumn($payhead_id, 'payhead_type');
		$deduction = $this->getDefinedTable(Hr\PayheadtypeTable::class)->getColumn($payhead_type, 'deduction');

		if($deduction == 1):
			$affected_ps = $this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('sd.employee'=>$employee, 'ph.against'=> $payhead_id));
		else:
			$affected_ps = $this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('sd.employee'=>$employee, 'ph.against'=> array($payhead_id,'-1','-2')));
		endif;

        $againstGrossPH = array(); 
        $againstPitNet = array(); 
		foreach($affected_ps as $aff_ps):
			if($aff_ps['against'] == '-1'):
				array_push($againstGrossPH, $aff_ps);
				//$base_amount = $this->getDefinedTable(Hr\TempPayrollTable::class)->getColumn(array('employee'=>$employee),'gross');
			elseif($aff_ps['against'] == '-2'):
				array_push($againstPitNet, $aff_ps);
			else:
				$base_amount = $this->getDefinedTable(Hr\PaystructureTable::Class)->getColumn(array('employee'=>$employee, 'pay_head'=>$aff_ps['against']),'amount');
			endif;

			if($aff_ps['type'] == 2 && $aff_ps['against'] != '-1' && $aff_ps['against'] != '-2'):				
				$amount = ($base_amount*$aff_ps['percent'])/100;
				if($aff_ps['roundup'] == 1):
					$amount = round($amount);
				endif;
				$data = array(
					'id' => $aff_ps['id'],
					'amount' => $amount,
					'author' =>$this->_author,
					'modified' =>$this->_modified,
				);
				$data = $this->_safedataObj->rteSafe($data);
				$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
		    elseif($aff_ps['type'] == 3 && $aff_ps['against'] != '-1' && $aff_ps['against'] != '-2'):	
				$rate=0;  $base=0;  $value=0;  $min=0;
				foreach($this->getDefinedTable(Hr\PaySlabTable::class)->get(array('pay_head' => $aff_ps['pay_head_id'])) as $payslab):
					if($base_amount>=$payslab['from_range'] && $base_amount<=$payslab['to_range']):
						break;
					endif;
				endforeach;
				if($payslab['formula'] == 1):
					$rate = $payslab['rate'];
					$base = $payslab['base'];
					$min = $payslab['from_range'];
					if($base_amount > 158701):
						$amount = ((($base_amount - 83338)/100)*$rate)+$base;
					else:
						$amount = (intval(($base_amount - $min)/100)*$rate)+$base;
					endif;
				else:
					$amount=$payslab['value'];
				endif;
				if($aff_ps['roundup'] == 1):
					$amount = round($amount);
				endif;
				$data = array(
					'id' => $aff_ps['id'],
					'amount' => $amount,
					'author' =>$this->_author,
					'modified' =>$this->_modified,
				);
				$data = $this->_safedataObj->rteSafe($data);
				$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
			endif;
		endforeach;
		
		//making changes to temp payroll
		foreach($this->getDefinedTable(Hr\TempPayrollTable::class)->get(array('pr.employee' => $employee)) as $temp_payroll);				
		$total_earning = 0;		
		$total_deduction = 0;
		$total_actual_earning = 0;
		$total_actual_deduction = 0;
		foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('sd.employee' => $employee, 'pht.deduction'=>'1')) as $paydetails):
			if($paydetails['dlwp']==1):
				$amount = $paydetails['amount'] - ($paydetails['amount']/$temp_payroll['working_days']) * $temp_payroll['leave_without_pay'];
			else:
				$amount = $paydetails['amount'];
			endif;
			$total_deduction = $total_deduction + $amount;
			$total_actual_deduction = $total_actual_deduction + $paydetails['amount'];
		endforeach;	
		foreach($this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('sd.employee' => $employee, 'pht.deduction'=>'0')) as $paydetails):
			if($paydetails['dlwp']==1):
				$amount = $paydetails['amount'] - ($paydetails['amount']/$temp_payroll['working_days']) * $temp_payroll['leave_without_pay'];
			else:
				$amount = $paydetails['amount'];
			endif;
			$total_earning = $total_earning + $amount;
			$total_actual_earning = $total_actual_earning + $paydetails['amount'];
		endforeach;				
		$leave_encashment = $temp_payroll['leave_encashment'];
		$bonus = $temp_payroll['bonus'];
		$net_pay = $total_earning + $leave_encashment + $bonus - $total_deduction;
		$earning_dlwp = $total_actual_earning - $total_earning;
		$deduction_dlwp = $total_actual_deduction - $total_deduction;
		$data1 = array(
				'id'	=> $temp_payroll['id'],
				'gross' => $total_actual_earning,
				'total_deduction' => $total_actual_deduction,
				'net_pay' => $net_pay,
				'earning_dlwp' => $earning_dlwp,
				'deduction_dlwp' => $deduction_dlwp,
				'status' => '1', // initiated
				'author' =>$this->_author,
				'modified' =>$this->_modified,
		);	
			//echo "<pre>";print_r($data1);exit;
		$data1 = $this->_safedataObj->rteSafe($data1);
		$result1 = $this->getDefinedTable(Hr\TempPayrollTable::class)->save($data1);
		if($result1):
			if(sizeof($againstGrossPH)>0){
			   foreach($againstGrossPH as $aff_ps):
				   $base_amount = $this->getDefinedTable(Hr\TempPayrollTable::class)->getColumn(array('employee'=>$employee),'gross');
				   if($aff_ps['type'] == 2){
					  $amount = ($base_amount*$aff_ps['percent'])/100;
						if($aff_ps['roundup'] == 1):
							$amount = round($amount);
						endif;
						$data = array(
							'id' => $aff_ps['id'],
							'amount' => $amount,
							'author' =>$this->_author,
							'modified' =>$this->_modified,
						);
						$data = $this->_safedataObj->rteSafe($data);
						$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
				   }
				   elseif($aff_ps['type'] == 3){
					 $rate=0;  $base=0;  $value=0;  $min=0;
						foreach($this->getDefinedTable(Hr\PaySlabTable::class)->get(array('pay_head' => $aff_ps['pay_head_id'])) as $payslab):
							if($base_amount>=$payslab['from_range'] && $base_amount<=$payslab['to_range']):
								break;
							endif;
						endforeach;
						if($payslab['formula'] == 1):
							$rate = $payslab['rate'];
							$base = $payslab['base'];
							$min = $payslab['from_range'];
							if($base_amount > 158701):
								$amount = ((($base_amount - 83338)/100)*$rate)+$base;
							else:
								$amount = (intval(($base_amount - $min)/100)*$rate)+$base;
							endif;
						else:
							$amount=$payslab['value'];
						endif;
						if($aff_ps['roundup'] == 1):
							$amount = round($amount);
						endif;
						$data = array(
							'id' => $aff_ps['id'],
							'amount' => $amount,
							'author' =>$this->_author,
							'modified' =>$this->_modified,
						);
						$data = $this->_safedataObj->rteSafe($data);
						$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
				   }
			   endforeach;
			}
			if(sizeof($againstPitNet)>0){
			   foreach($againstPitNet as $aff_ps):
				   $Gross_amount = $this->getDefinedTable(Hr\TempPayrollTable::class)->getColumn(array('employee'=>$employee),'gross');
				   $PFDed = $this->getDefinedTable(Hr\PaystructureTable::Class)->getColumn(array('employee'=>$employee, 'pay_head'=>7),'amount');
				   $GISDed = $this->getDefinedTable(Hr\PaystructureTable::Class)->getColumn(array('employee'=>$employee, 'pay_head'=>6),'amount');
				   $base_amount = $Gross_amount - $PFDed - $GISDed;
				   if($aff_ps['type'] == 2){
					  $amount = ($base_amount*$aff_ps['percent'])/100;
						if($aff_ps['roundup'] == 1):
							$amount = round($amount);
						endif;
						$data = array(
							'id' => $aff_ps['id'],
							'amount' => $amount,
							'author' =>$this->_author,
							'modified' =>$this->_modified,
						);
						$data = $this->_safedataObj->rteSafe($data);
						$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
				   }
				   elseif($aff_ps['type'] == 3){
					 $rate=0;  $base=0;  $value=0;  $min=0;
						foreach($this->getDefinedTable(Hr\PaySlabTable::class)->get(array('pay_head' => $aff_ps['pay_head_id'])) as $payslab):
							if($base_amount>=$payslab['from_range'] && $base_amount<=$payslab['to_range']):
								break;
							endif;
						endforeach;
						if($payslab['formula'] == 1):
							$rate = $payslab['rate'];
							$base = $payslab['base'];
							$min = $payslab['from_range'];
							if($base_amount > 158701):
								$amount = ((($base_amount - 83338)/100)*$rate)+$base;
							else:
								$amount = (intval(($base_amount - $min)/100)*$rate)+$base;
							endif;
						else:
							$amount=$payslab['value'];
						endif;
						if($aff_ps['roundup'] == 1):
							$amount = round($amount);
						endif;
						$data = array(
							'id' => $aff_ps['id'],
							'amount' => $amount,
							'author' =>$this->_author,
							'modified' =>$this->_modified,
						);
						$data = $this->_safedataObj->rteSafe($data);
						$result = $this->getDefinedTable(Hr\PaystructureTable::Class)->save($data);
				   }
			   endforeach;
			}
          return $result1;
		endif; 		
	}
	
	/*
	 * Action to add pay structure
	**/
	public function paystructureAction()
	{
		$this->init();

		return new ViewModel(array(
				'title' => 'Pay Structure',
				'id' => $this->_id,
				'employee' => $this->getDefinedTable(Hr\EmployeeTable::class)->get($this->_id),
				'emphistoryObj' => $this->getDefinedTable(Hr\EmpHistoryTable::class),
				'pay_heads' => $this->getDefinedTable(Hr\PayheadTable::class)->getAll(),
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
				'paystructure' => $this->getDefinedTable(Hr\PaystructureTable::Class)->get(array('employee' => $this->_id)),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::Class),

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
	 *  PBVI  action
	 */
	public function pbviAction()
	{
		$this->init();	 
        if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
		}else{
			$year = date('Y');
        }
		return new ViewModel(array(
				'title'  => 'PBVI',
				'pbvi' => $this->getDefinedTable(Hr\BonusTable::class)->getAll(),
				'pbviObj' => $this->getDefinedTable(Hr\BonusTable::class),
				'year'	=> $year,
			
		));
	}
	/**
	 *  PBVI  action
	 */
	public function generatepbviAction()
	{
		$this->init();	
		$year =$this->_id;	
		$data = array(
			'year' 		=> $year,
			'status' 	=> 2,
			'author' 	=> $this->_author,
			'created' 	=> $this->_created,
			'modified' 	=> $this->_modified,
		);
		//echo '<pre>';print_r($data);exit;
		$this->_connection->beginTransaction();
		$result = $this->getDefinedTable(Hr\BonusTable::class)->save($data);
			
			if ($result) {
				$employee=$this->getDefinedTable(Hr\EmployeeTable::class)->getEmpPBVI(array('b.year'=>$year));

			foreach($employee as $emp):
				$emp_his=$this->getDefinedTable(Hr\EmpHistoryTable::class)->getMaxRow('id',array('eh.employee'=>$emp['id']));
				foreach($emp_his as $his);
				$his_year=date('Y',strtotime($his['start_date']));
				
				if(($his_year<$year && $emp['status']!=1)){
					continue;
				}
				$data1=array(
					'bonus_id'	=> $result,
					'employee'	=> $emp['id'],
					'location'	=> $emp['location'],
				);
				//echo '<pre>';print_r($data1);
				$result1 = $this->getDefinedTable(Hr\BonusDtlsTable::class)->save($data1);
			endforeach;
			//exit;
			$this->_connection->commit();
               $this->flashMessenger()->addMessage("success^ Action Successful");
            } else {
				$this->_connection->rollback();
                $this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
            }
           return $this->redirect()->toRoute('payroll',array('action' => 'pbvi'));
		
	}
	public function viewpbviAction()
	{
		$this->init();	
		
        // if($this->getRequest()->isPost()){
		// 	$form = $this->getRequest()->getPost();
		// 	$year = $form['year'];
		// }else{
		// 	$year = date('Y');
        // }
		return new ViewModel(array(
				'title'  => 'PBVI',
				'pbvid' => $this->getDefinedTable(Hr\BonusDtlsTable::class)->get(array('bonus_id'=>$this->_id)),
				'pbviObj' => $this->getDefinedTable(Hr\BonusTable::class),
				'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				//'year'	=> $year,
				'pbvi_id'  => $this->_id,
			
		));
	}
	public function editpbviAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$pbvi_id=$this->getDefinedTable(Hr\BonusDtlsTable::class)->getColumn($this->_id,'bonus_id');

			$data=array(
			        'id' => $this->_id,
					'gross_salary' => $form['salary'],
					'percentage' => $form['percentage'],
					'gross' => $form['gross'],
					'tds' => $form['tds'],
					'other_deduction' => $form['other_ded'],
					'net' => $form['net'],
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Hr\BonusDtlsTable::class)->save($data);
	
			if($result):
				$data1=array(
			        'id' => $pbvi_id,
					'total_salary' =>  $this->getDefinedTable(Hr\BonusDtlsTable::class)->getSum('gross_salary',array('bonus_id'=>$pbvi_id)),
					'total_gross' => $this->getDefinedTable(Hr\BonusDtlsTable::class)->getSum('gross',array('bonus_id'=>$pbvi_id)),
					'total_tds' => $this->getDefinedTable(Hr\BonusDtlsTable::class)->getSum('tds',array('bonus_id'=>$pbvi_id)),
					'total_other_ded' => $this->getDefinedTable(Hr\BonusDtlsTable::class)->getSum('other_deduction',array('bonus_id'=>$pbvi_id)),
					'total_net' => $this->getDefinedTable(Hr\BonusDtlsTable::class)->getSum('net',array('bonus_id'=>$pbvi_id)),
					'author' => $this->_author,
					'modified' => $this->_modified,
	
			);
			//echo '<pre>';print_r($data1);exit;
			$result1 = $this->getDefinedTable(Hr\BonusTable::class)->save($data1);
			$this->_connection->commit();
			$this->flashMessenger()->addMessage("success^ successfully Updated");
			else:
				$this->_connection->rollback();
			$this->flashMessenger()->addMessage("Failed^ Failed to Update details");
			endif;
			return $this->redirect()->toRoute('payroll', array('action' => 'viewpbvi', 'id' => $pbvi_id));
		}
		$ViewModel = new ViewModel(array(
				'id' => $this->_id,
				'title' => 'Edit PBVI',
				'pbvid' => $this->getDefinedTable(Hr\BonusDtlsTable::class)->get($this->_id),
				'empObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}

	public function submitpbviAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();

			$data=array(
			        'id' 		=> $form['id'],
					'status' 	=> 4,
					'date'		=>$form['date'],
					'remarks'	=> $form['remarks'],
					'author' 	=> $this->_author,
					'modified' 	=> $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Hr\BonusTable::class)->save($data);
	
			if($result):
				foreach($this->getDefinedTable(Hr\BonusTable::class)->get($result) as $pbvi);
					$location = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('id'=>$this->_author),'location');
					$region = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('id'=>$this->_author),'region');
					$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
					$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(12,'prefix');
					$date = date('ym',strtotime(date('Y-m-d')));
					$tmp_VCNo = $loc.'-'.$prefix.$date;
					
					$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
					
					$pltp_no_list = array();
					foreach($results as $result):
						array_push($pltp_no_list, substr($result['voucher_no'], -4));
					endforeach;
					$next_serial = max($pltp_no_list) + 1;
						
					switch(strlen($next_serial)){
						case 1: $next_dc_serial = "0000".$next_serial; break;
						case 2: $next_dc_serial = "000".$next_serial;  break;
						case 3: $next_dc_serial = "00".$next_serial;   break;
						case 4: $next_dc_serial = "0".$next_serial;    break;
						default: $next_dc_serial = $next_serial;       break;
					}	
					$voucher_no = $tmp_VCNo.$next_dc_serial;
					
							$data = array(
								'voucher_date' 		=> $form['date'],
								'voucher_type' 		=> 12,
								'region'   			=>$region,
								'doc_id'   			=>"PBVI",
								'voucher_no' 		=> $voucher_no,
								'voucher_amount' 	=> $pbvi['total_gross'],
								'status' 			=> 3, // status initiated 
								'remark'			=>$form['remarks'],
								'against' 	  	=> 0,
								'author' 			=>$this->_author,
								'created' 			=>$this->_created,  
								'modified' 			=>$this->_modified,
							);
							$resultTrans = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data);
							//if($resultTrans >0){
								$flow=array(
									'flow' 				=> 5,
									'application' 		=> $resultTrans,
									'activity'			=>$location,
									//'role_id'   		=>' ',
									'actor'   			=>6,
									'action' 			=> "3|4",
									'routing' 			=> 2,
									'status' 			=> 3, // status applied 
									'routing_status'	=>2,
									'action_performed'	=>1,
									'description'		=>"PBVI",
									'author' 			=>$this->_author,
									'created' 			=>$this->_created,  
									'modified' 			=>$this->_modified,
								);
								$flow=$this->getDefinedTable(Administration\FlowTransactionTable::class)->save($flow);
								$transactionDtls1 = array(
									'transaction' => $resultTrans,
									'voucher_dates' => $form['date'],
									'voucher_types' => 12,
									'location' => $location,
									'head' 	  		=> 170,
									'sub_head' 	  	=> 2647,
									'bank_ref_type' => '',
									'debit' =>$pbvi['total_gross'],
									'credit' =>'0.00',
									'ref_no'=> '', 
									'against' 	  	=> 0,
									'type' => '1',//user inputted  data  
									'status' => 3, // status appied
									'activity'=>$location,
									'author' =>$this->_author,
									'created' =>$this->_created,
									'modified' =>$this->_modified,
								);
							$transactionDtls1 = $this->_safedataObj->rteSafe($transactionDtls1);
							$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($transactionDtls1);
							$transactionDtls2 = array(
									'transaction' => $resultTrans,
									'voucher_dates' => $form['date'],
									'voucher_types' => 12,
									'location' => $location,
									'head' =>36,
									'sub_head' =>172,
									'bank_ref_type' => '',
									'debit' =>'0.00',
									'credit' =>$pbvi['total_net'],
									'ref_no'=> "", 
									'type' => '1',//user inputted  data
									'status' => 3, // status applied
									'against' 	  	=> 0,
									'activity'=>$location,
									'author' =>$this->_author,
									'created' =>$this->_created,
									'modified' =>$this->_modified,
								);
								$transactionDtls2 = $this->_safedataObj->rteSafe($transactionDtls2);
								$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($transactionDtls2);
								$transactionDtls3 = array(
									'transaction' => $resultTrans,
									'voucher_dates' => $form['date'],
									'voucher_types' => 12,
									'location' => $location,
									'head' =>150,
									'sub_head' =>2939,
									'bank_ref_type' => '',
									'debit' =>'0.00',
									'credit' =>$pbvi['total_tds'],
									'ref_no'=> "", 
									'type' => '1',//user inputted  data
									'status' => 3, // status applied
									'against' 	  	=> 0,
									'activity'=>$location,
									'author' =>$this->_author,
									'created' =>$this->_created,
									'modified' =>$this->_modified,
								);
								$transactionDtls3 = $this->_safedataObj->rteSafe($transactionDtls3);
								$result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($transactionDtls3);
								$transactionDtls4 = array(
									'transaction' => $resultTrans,
									'voucher_dates' => $form['date'],
									'voucher_types' => 12,
									'location' => $location,
									'head' =>150,
									'sub_head' =>2965,
									'bank_ref_type' => '',
									'debit' =>'0.00',
									'credit' =>$pbvi['total_other_ded'],
									'ref_no'=> "", 
									'type' => '1',//user inputted  data
									'status' => 3, // status applied
									'against' 	  	=> 0,
									'activity'=>$location,
									'author' =>$this->_author,
									'created' =>$this->_created,
									'modified' =>$this->_modified,
								);
								$transactionDtls4 = $this->_safedataObj->rteSafe($transactionDtls4);
								$result4 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($transactionDtls4);
								$data2 = array(
									'id' 	=> $pbvi['id'],
									'transaction' => $resultTrans,
									);
								$data2 =  $this->_safedataObj->rteSafe($data2);
								$result5 = $this->getDefinedTable(Hr\BonusTable::class)->save($data2);
						if($result4):
							$notification_data = array(
								'route'         => 'transaction',
								'action'        => 'againstdebit',
								'key' 		    => $resultTrans,
								'description'   => 'PBVI Submitted',
								'author'	    => $this->_author,
								'created'       => $this->_created,
								'modified'      => $this->_modified,   
							);
							//print_r($notification_data);exit;
							$notificationResult = $this->getDefinedTable(Acl\NotificationTable::class)->save($notification_data);
							if($notificationResult > 0 ){	
								$user = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>array('6')));
								foreach($user as $row):						    
									$user_location_id = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($row['id'], 'location');
									//if($user_location_id == $location ):						
										$notify_data = array(
											'notification' => $notificationResult,
											'user'    	   => $row['id'],
											'flag'    	 => '0',
											'desc'    	 => 'PBVI Submitted ',
											'author'	 => $this->_author,
											'created'    => $this->_created,
											'modified'   => $this->_modified,  
										);
										$notifyResult = $this->getDefinedTable(Acl\NotifyTable::class)->save($notify_data);
									//endif;
								endforeach;
							}
						endif;
			$this->_connection->commit();
			$this->flashMessenger()->addMessage("success^ successfully Updated");
			else:
				$this->_connection->rollback();
			$this->flashMessenger()->addMessage("Failed^ Failed to Update details");
			endif;
			
			return $this->redirect()->toRoute('payroll', array('action' => 'viewpbvi', 'id' => $form['id']));
		}
		$ViewModel = new ViewModel(array(
				'id' => $this->_id,
				'title' => 'Edit PBVI',
				'pbvi' => $this->getDefinedTable(Hr\BonusTable::class)->get($this->_id),
				'empObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Ajax to get the employee according to location
	**/
	public function getbasicAction()
	{
		$this->init();
		
		// Get the POST data
		$form = $this->getRequest()->getPost();
		
		// Extract employeeID
		$employee = $form['employeeID'];
		
		// Query the database for the employee's salary details
		$salaryDtls = $this->getDefinedTable(Hr\PaystructureTable::class)->get(array(
			'sd.employee' => $employee, 
			'pht.deduction' => 0, 
			'sd.pay_head' => 1
		));  
		
		// Check if results were found
		if (!empty($salaryDtls)) {
			foreach ($salaryDtls as $sd) {
				$basic = $sd['amount'];  // Extract the 'amount' field from the result
			}
		} else {
			$basic = 0;  // Default to 0 or some value if no results found
		}
		
		// Return the response as JSON
		echo json_encode(array(
			'basic' => $basic,
		));
		
		// Exit to ensure no additional output is sent
		exit;
	}
	/**
 * Ajax to get the deduction based on the amount 
**/
public function gettdsAction()
{
    $this->init();
    
    // Get the POST data
    $form = $this->getRequest()->getPost();
    
    // Extract employeeID and amount
   // $employee = $form['employeeID'];
    $amount = $form['amount'];
    
    // Get the pay slab based on the amount
    $encashSlabs = $this->getDefinedTable(Hr\PaySlabTable::class)->getPaySlabForTotalEarning($amount);
    
    $PIT_deduct = 0; // Initialize PIT deduction
    
    // Process the encash slabs
    foreach ($encashSlabs as $encashSlab) {
        
        if ($encashSlab['rate'] > 0 || empty($encashSlab['rate'])) {
            
            if ($encashSlab['rate'] != 0) {
                $from = $encashSlab['from_range'];
                $count = 0;
                
                while ($amount >= $from) { // Assuming $amount is total earning
                    $count++;
                    if ($count == 1) {
                        $PIT_deduct = $encashSlab['base']; // First slab's base deduction
                    } else {
                        $PIT_deduct = ($encashSlab['rate'] * ($count - 1)) + $encashSlab['base']; // Deduction for higher slabs
                    }
                    $from += 100; // Increment the range
                }
                
            } else {
                $PIT_deduct = $encashSlab['value']; // Use the fixed value if the rate is zero
            }
        }
    }
    
    // Return the response as JSON
    echo json_encode(array(
        'tds' => $PIT_deduct,
    ));
    
    // Exit to ensure no additional output is sent
    exit;
}
}

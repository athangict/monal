<?php
namespace Pswf\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Accounts\Model As Accounts;
use Hr\Model As Hr;
use Administration\Model As Administration;
use Pswf\Model As Pswf;
class AdvancesalaryController extends AbstractActionController
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
    protected $_safedataObj; // safedata controller plugin
	
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

		if(!isset($this->_author)){
			$this->_author = $this->_user->id;  
		}

		$this->_id = $this->params()->fromRoute('id');

		$this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
	
	/**
	 * Advance Salary index action
	**/
	public function indexAction()
	{
		$this->init();
		
		return new ViewModel(array(
			'title'          => "Advance Salary",
			'advance_salary' => $this->getDefinedTable(Accounts\AdvanceSalaryTable::class)->getAll(),
			'payheadObj'     => $this->getDefinedTable(Hr\PayheadtypeTable::class),
		));
	}
	
	/**
	 * Add Advance Salary
	**/
	public function addadvancesalaryAction()
	{
		$this->init();
		//echo $this->_id;exit;
		$advances=0;
		$advance_dtls=0;
		$params = explode("-", $this->_id);
		//echo '<pre>';print_r($params);exit;
		$employee_id = $params['0'];
		$payhead_id = $params['1'];
		if($employee_id > 0 && $payhead_id > 0):
			$advances = $this->getDefinedTable(Accounts\AdvanceSalaryTable::class)->get(array('employee' => $employee_id, 'pay_head' => $payhead_id));
			foreach($advances as $advance);
			$advance_dtls = $this->getDefinedTable(Accounts\AdvanceSalaryDtlsTable::class)->get(array('advance_salary' => $advance['id']));
		endif;
		$payhead_id='';
		$data = array(
			'employee_id' => $employee_id,
			'payhead_id'  => $payhead_id,
		);	
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			if($form['advance_id']):
				$data = array(
					'id'                => $form['advance_id'], 
					'initial_date'      => $form['initial_date'],
					'total_amount'      => $form['total_amount'],
					'total_deduction'   => $form['total_deduction'],
					'author'            => $this->_author,
					'modified'          => $this->_modified,
				);
			else:
				$data = array(
					'employee'          => $form['employee'],
					'pay_head'          => $form['payhead'],
					'initial_date'      => $form['initial_date'],
					'total_amount'      => $form['total_amount'],
					'total_deduction'   => $form['total_deduction'],
					'author'            => $this->_author,
					'created'           => $this->_created,
					'modified'          => $this->_modified,
				);
			endif;
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); 
			$result = $this->getDefinedTable(Accounts\AdvanceSalaryTable::class)->save($data);
			if($result):
				$adv_dtl_id = $form['adv_dtl_id'];
				$advance_ded_type = $form['advance_ded_type'];
				$avail_date = $form['avail_date'];
				$end_date = $form['end_date'];
				$amount = $form['amount'];
				$monthly_deduction = $form['monthly_deduction'];
				$status = $form['status'];
				
				for($i=0; $i<sizeof($advance_ded_type);$i++):
					if(isset($advance_ded_type[$i]) && $advance_ded_type[$i] > 0):
						if(isset($adv_dtl_id[$i]) && $adv_dtl_id[$i] > 0):
							$details = array(
								'id'                => $adv_dtl_id[$i],
								'advance_salary'    => $result,
								'avail_date'        => $avail_date[$i],
								'end_date'          => $end_date[$i],
								'advance_ded_type'  => $advance_ded_type[$i],
								'amount'            => $amount[$i],
								'monthly_deduction' => $monthly_deduction[$i],
								'status'            => $status[$i],
								'transaction'       => '',
								'author'            => $this->_author,
								'modified'          => $this->_modified,
							);
						else:
							$details = array(
								'advance_salary'    => $result,
								'avail_date'        => $avail_date[$i],
								'end_date'          => $end_date[$i],
								'advance_ded_type'  => $advance_ded_type[$i],
								'amount'            => $amount[$i],
								'monthly_deduction' => $monthly_deduction[$i],
								'status'            => '1',
								'transaction'       => '',
								'author'            => $this->_author,
								'created'           => $this->_created,
								'modified'          => $this->_modified,
							);
						endif;
					endif;
					$details = $this->_safedataObj->rteSafe($details);
					$result1 = $this->getDefinedTable(Accounts\AdvanceSalaryDtlsTable::class)->save($details);
				endfor;
				
				if($result1):
					$this->_connection->commit(); 
					$this->flashMessenger()->addMessage("success^ Successfully added advance salary");
					return $this->redirect()->toRoute('advancesalary', array('action' =>'viewadvancesalary', 'id'=>$result));
				else:
					$this->_connection->rollback(); 
					$this->flashMessenger()->addMessage("error^ Failed to add advance salary");
				endif;
			else:
				$this->_connection->rollback(); 
				$this->flashMessenger()->addMessage("error^ Unsuccessful to added new advance salary");
			endif;
		endif;
		return new ViewModel(array(
			'title'        => "Advance Salary Deduction",
			'employees'    => $this->getDefinedTable(Hr\EmployeeTable::class)->getEmpByStatus(array(1,7)),
			'payheads'     => $this->getDefinedTable(Hr\PayheadtypeTable::class)->get(array('deduction' => 1)),
			'data'         => $data,
			'advances'     => $advances,
			'advance_dtls' => $advance_dtls,
			'advance_ded_types' => $this->getDefinedTable(Accounts\AdvanceDedTypeTable::class)->getAll(),
			'payrollObj'   => $this->getDefinedTable(Hr\PayrollTable::class),
		));
	}
	/**
	 * Get Employee's advance salary details 
	**/ 
	public function getemployeedtlsAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getpost();
		
		$employee_id = $form['employee'];
		$employee_dtls = $this->getDefinedTable(Hr\EmployeeTable::class)->get($employee_id);
		foreach($employee_dtls as $employee);
		$latest_payroll_id = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('id',array('employee' => $employee_id));
		$payroll_dtls = $this->getDefinedTable(Hr\PayrollTable::class)->get($latest_payroll_id);
		foreach($payroll_dtls as $payroll);
		$net_pay = $payroll['gross'] - $payroll['total_deduction'];
		$data = "<h6>Employee Details</h6>
				<div class='row'>
					<div class='col-lg-3'>
						<ul class='list-unstyled spaced'>
							<li>
								<i class='ace-icon fa fa-caret-right blue'></i>Name:  <b class='red'>".$employee['full_name']."</b>
							</li>
							<li>
								<i class='ace-icon fa fa-caret-right blue'></i>Cid No:  <b class='red'>".$employee['cid']."</b>
							</li>
						</ul>
					</div>
					<div class='col-lg-3'>
						<ul class='list-unstyled spaced'>
							<li>
								<i class='ace-icon fa fa-caret-right blue'></i>Employee ID:  <b class='red'>".$employee['emp_id']."</b>
							</li>
							<li>
								<i class='ace-icon fa fa-caret-right blue'></i>Activity:  <b class='red'>".$employee['activity']."</b>
							</li>
						</ul>
					</div>
					<div class='col-lg-3'>
						<ul class='list-unstyled spaced'>
							<li>
								<i class='ace-icon fa fa-caret-right blue'></i>Position Title:  <b class='red'>".$employee['position_title']."</b>
							</li>
							<li>
								<i class='ace-icon fa fa-caret-right blue'></i>Department:  <b class='red'>".$employee['department']."</b>
							</li>
						</ul>
					</div>
					<div class='col-lg-3'>
						<ul class='list-unstyled spaced'>
							<li>
								<i class='ace-icon fa fa-caret-right blue'></i>Region:  <b class='red'>".$employee['region']."</b>
							</li>
							<li>
								<i class='ace-icon fa fa-caret-right blue'></i>Location:  <b class='red'>".$employee['location']."</b>
							</li>
						</ul>
					</div>
				</div>
				<h6>Pay Details</h6>
				<div class='row'>
					<div class='col-lg-3'>
						<ul class='list-unstyled spaced'>
							<li>
								<i class='ace-icon fa fa-caret-right blue'></i>Date:  <b class='red'>".$payroll['year']."-".$payroll['month']."</b>
							</li>
						</ul>
					</div>
					<div class='col-lg-3'>
						<ul class='list-unstyled spaced'>
							<li>
								<i class='ace-icon fa fa-caret-right blue'></i>Gross Pay:  <b class='red'>".$payroll['gross']."</b>
							</li>
						</ul>
					</div>
					<div class='col-lg-3'>
						<ul class='list-unstyled spaced'>
							<li>
								<i class='ace-icon fa fa-caret-right blue'></i>Total Deduction:  <b class='red'>".$payroll['total_deduction']."</b>
							</li>
						</ul>
					</div>
					<div class='col-lg-3'>
						<ul class='list-unstyled spaced'>
							<li>
								<i class='ace-icon fa fa-caret-right blue'></i>Net pay:  <b class='red'>".$net_pay."</b>
							</li>
						</ul>
					</div>
				</div>";
		echo json_encode(array(
			'emp_details' => $data,
		));
		exit;
	}
	/**
	 * View Advance Salary
	**/
	public function viewadvancesalaryAction()
	{
		$this->init();
		$test=$this->getDefinedTable(Accounts\AdvanceSalaryTable::class)->get($this->_id);
		echo '<pre>';print_r($test);exit;
		return new ViewModel(array(
			'title'           => 'View Advance Salary',
			'advsalary'       => $this->getDefinedTable(Accounts\AdvanceSalaryTable::class)->get($this->_id),
			'advsal_details'  => $this->getDefinedTable(Accounts\AdvanceSalaryDtlsTable::class)->get(array('advance_salary' => $this->_id)),
			'adv_ded_typeObj' => $this->getDefinedTable(Accounts\AdvanceDedTypeTable::class),
			'payheadObj'      => $this->getDefinedTable(Hr\PayheadtypeTable::class),
			'userObj'         => $this->getDefinedTable(Administration\UsersTable::class),
			'payrollObj'      => $this->getDefinedTable(Hr\PayrollTable::class),
		));
	}
	/**
	 * Confirm Advance Salary Deduction
	**/
	public function commitadvsalaryAction()
	{
		$this->init();
		
		$adv_salarys = $this->getDefinedTable(Accounts\AdvanceSalaryTable::class)->get($this->_id);
		foreach($adv_salarys as $adv_sal);
		$pay_structures = $this->getDefinedTable(Hr\PaystructureTable::class)->get(array('sd.employee' => $adv_sal['employee'], 'sd.pay_head' => $adv_sal['pay_head']));
		if(sizeof($pay_structures) > 0): //CHECK IF THE PAYHEAD EXIST IN THE PAY STRUCTURE
			foreach($pay_structures as $pay_struct);
			$temp_payrolls = $this->getDefinedTable(Hr\TempPayrollTable::class)->get(array('pr.employee' => $adv_sal['employee']));
			foreach($temp_payrolls as $temp_payroll);
			//Remove Payhead's previous amount
			$total_deduction = $temp_payroll['total_deduction'] - $pay_struct['amount'];
			$net_pay = $temp_payroll['net_pay'] + $pay_struct['amount'];
			//Now Add Payhead's new amount
			$new_total_deduction = $total_deduction + $adv_sal['total_deduction'];
			$new_net_pay = $net_pay - $adv_sal['total_deduction'];
			$temp_payroll_data = array(
				'id'              => $temp_payroll['id'],
				'total_deduction' => $new_total_deduction,
				'net_pay'         => $new_net_pay,
				'author'   => $this->_author,
				'modified' => $this->_modified,
			);
			$temp_payroll_data = $this->_safedataObj->rteSafe($temp_payroll_data);
			$this->_connection->beginTransaction(); 
			$temp_result = $this->getDefinedTable(Hr\TempPayrollTable::class)->save($temp_payroll_data);
			if($temp_result): //change the paystructure
				$pay_struct_data =  array(
					'id'      => $pay_struct['id'],
					'amount'  => $adv_sal['total_deduction'],
					'author'   => $this->_author,
					'modified' => $this->_modified,
				);
				$pay_struct_data = $this->_safedataObj->rteSafe($pay_struct_data);
				$pay_result = $this->getDefinedTable(Hr\PaystructureTable::class)->save($pay_struct_data);
				if($pay_result):
					$adv_sal_dtls = $this->getDefinedTable(Accounts\AdvanceSalaryDtlsTable::class)->get(array('advance_salary' => $this->_id,'status' => '1'));
					if(sizeof($adv_sal_dtls) > 0):
						foreach($adv_sal_dtls as $adv_sal_dtl):
							$data = array(
								'id'       => $adv_sal_dtl['id'],
								'status'   => '2',
								'author'   => $this->_author,
								'modified' => $this->_modified,
							);
							$data = $this->_safedataObj->rteSafe($data);
							$result = $this->getDefinedTable(Accounts\AdvanceSalaryDtlsTable::class)->save($data);
						endforeach;
						if($result):
							$this->_connection->commit(); 
							$this->flashMessenger()->addMessage("success^ Successfully added advance salary deduction to payroll");
						else:
							$this->_connection->rollback(); 
							$this->flashMessenger()->addMessage("error^ Unsuccessful to added advance salary deduction to payroll");
						endif;
					else:
						$this->_connection->commit(); 
						$this->flashMessenger()->addMessage("success^ Successfully added advance salary deduction to payroll");
					endif;
				else:
					$this->_connection->rollback(); 
					$this->flashMessenger()->addMessage("error^ Failed to add to pay structure for payroll generation");
				endif;
			else:
				$this->_connection->rollback(); 
				$this->flashMessenger()->addMessage("error^ Failed to add to payroll details");
			endif;
		else: //ADD NEW PAY HEAD TO THE PAY STRUCTURE 
			$pay_struct_data = array(
				'employee' => $adv_sal['employee'],
				'pay_head' => $adv_sal['pay_head'],
				'percent'  => '0',
				'amount'   => $adv_sal['total_deduction'],
				'dlwp'     => '0',
				'ref_no'   => '',
				'remarks'  => '',
				'author'   => $this->_author,
				'created'  => $this->_created,
				'modified' => $this->_created,
			);
			$pay_struct_data = $this->_safedataObj->rteSafe($pay_struct_data);
			$this->_connection->beginTransaction(); 
			$pay_result = $this->getDefinedTable(Hr\PaystructureTable::class)->save($pay_struct_data);
			if($pay_result):
				$temp_payrolls = $this->getDefinedTable(Hr\TempPayrollTable::class)->get(array('pr.employee' => $adv_sal['employee']));
				foreach($temp_payrolls as $temp_payroll);
				//echo"<pre>";print_r($temp_payroll);
				//Remove Payhead's previous amount
				$total_deduction = $temp_payroll['total_deduction'];
				$net_pay = $temp_payroll['net_pay'];
				
				//Now Add Payhead's new amount
				$new_total_deduction = $total_deduction + $adv_sal['total_deduction'];
				$new_net_pay = $net_pay - $adv_sal['total_deduction'];
				$temp_payroll_data = array(
					'id'              => $temp_payroll['id'],
					'total_deduction' => $new_total_deduction,
					'net_pay'         => $new_net_pay,
					'author'   => $this->_author,
					'modified' => $this->_modified,
				);
				$temp_payroll_data = $this->_safedataObj->rteSafe($temp_payroll_data);
				$temp_result = $this->getDefinedTable(Hr\TempPayrollTable::class)->save($temp_payroll_data);
				if($temp_result):
					$adv_sal_dtls = $this->getDefinedTable(Accounts\AdvanceSalaryDtlsTable::class)->get(array('advance_salary' => $this->_id,'status' => '1'));
					if(sizeof($adv_sal_dtls) > 0):
						foreach($adv_sal_dtls as $adv_sal_dtl):
							$data = array(
								'id'       => $adv_sal_dtl['id'],
								'status'   => '2',
								'author'   => $this->_author,
								'modified' => $this->_modified,
							);
							$data = $this->_safedataObj->rteSafe($data);
							$result = $this->getDefinedTable(Accounts\AdvanceSalaryDtlsTable::class)->save($data);
						endforeach;
						if($result):
							$this->_connection->commit(); 
							$this->flashMessenger()->addMessage("success^ Successfully added advance salary deduction to payroll");
						else:
							$this->_connection->rollback(); 
							$this->flashMessenger()->addMessage("error^ Unsuccessful to added advance salary deduction to payroll");
						endif;
					else:
						$this->_connection->commit(); 
						$this->flashMessenger()->addMessage("success^ Successfully added advance salary deduction to payroll");
					endif;
				else:
					$this->_connection->rollback(); 
					$this->flashMessenger()->addMessage("error^ Failed to add to pay structure for payroll generation");
				endif;
			else:
				$this->_connection->rollback(); 
				$this->flashMessenger()->addMessage("error^ Failed to add pay head to the pay structure for payroll generation");
			endif;
		endif;
		return $this->redirect()->toRoute('advancesalary', array('action' =>'viewadvancesalary', 'id'=>$this->_id));
	}
	/**
	 * Received Advance Salary Deduction
	**/
	public function receivedadvsalAction()
	{
		$this->init();
		//echo $this->_id;
		$end_date = $this->getDefinedTable(Accounts\AdvanceSalaryDtlsTable::class)->getColumn($this->_id,'end_date');
		if(strtotime(date('Y-m-d')) > strtotime($end_date)){
			$data = array(
				'id'       => $this->_id,
				'status'   => '3',
				'author'   => $this->_author,
				'modified' => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Accounts\AdvanceSalaryDtlsTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully committed advance salary deduction");
			else:
				$this->flashMessenger()->addMessage("error^ Unsuccessful to commit advance salary deduction");
			endif;
		}else{
			$this->flashMessenger()->addMessage("notice^ The date is not expired");
		}
		$advance_salary_id = $this->getDefinedTable(Accounts\AdvanceSalaryDtlsTable::class)->getColumn($this->_id,'advance_salary');
		return $this->redirect()->toRoute('advancesalary', array('action' =>'viewadvancesalary', 'id'=>$advance_salary_id));
	}
}

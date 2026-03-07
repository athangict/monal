<?php
namespace Hr\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Application\Model As Application;
use Hr\Model As Hr;
use Accounts\Model As Accounts;

class NotesheetController extends AbstractActionController
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
		$this->_user = $this->identity();		
		$this->_login_id = $this->_user->id;  
		$this->_login_role = $this->_user->role;  
		$this->_author = $this->_user->id;  
		$this->_userloc = $this->_user->location;  
		$this->_id = $this->params()->fromRoute('id');		
	    $this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');		
		//$this->_dir =realpath($fileManagerDir);
		//$this->_safedataObj =  $this->SafeDataPlugin();
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();


	}
	
	/**
	 *  Index 
	 */
	public function indexAction()
	{
		$this->init();		
		return new ViewModel(array(
			));
		
	} 
	
	/**
	 * Sheet action
	 */
	public function sheetAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();				
			$year = $form['year'];
			$month = $form['month'];				
			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;
			$data = array(
					'year' => $year,
					'month' => $month,
			);
		}else{
			$month = isset($_GET['month']) ? $_GET['month'] : 0;
			$year = isset($_GET['year']) ? $_GET['year'] : 0;
			$month = date('m');
			$year = date('Y');
			$data = array(
					'year' => $year,
					'month' => $month,
			);
		}
		$sheet = $this->getDefinedTable(Hr\NotesheetTable::class)->getDateWise('date',$year,$month);
		//foreach($sheet as $sheets):
		//echo'<pre>';print_r($sheets);
		//if($sheets['author']==$this->_user->id):
			$notesheet = $this->getDefinedTable(Hr\NotesheetTable::class)->getDateWise('date',$year,$month,array('author'=>$this->_user->id));
		//else:
			//$notesheet = $this->getDefinedTable(Hr\NotesheetTable::class)->getDateWise('date',$year,$month,array('flow'=>6));
		//endif;
		//endforeach;exit;
		return new ViewModel(array(
			'title' => 'Sheet',
			'data' => $data,
			'minYear'=>$this->getDefinedTable(Hr\NotesheetTable::class)->getMin('date'),
			'sheet' => $notesheet,
		));
	}
	/**
	 * NoteSheet pending
	 */
	public function pendingAction()
	{
		$this->init();
		$sheet = $this->getDefinedTable(Hr\NotesheetTable::class)->getPendingSheet('a.id',array('a.process'=>522,'a.send_to'=>$this->_user->id),$this->_user->id);
		return new ViewModel(array(
			'title' => 'Sheet',
			'sheet' => $sheet,
			'user'=>$this->_user->id,
			'note'=>$this->getDefinedTable(Administration\FlowTransactionTable::class),
		));
	}
	/**
	 * Notesheet action
	 */
	public function actionbymeAction()
	{
		$this->init();
		$sheet = $this->getDefinedTable(Hr\NotesheetTable::class)->getActionByMe(array('a.process'=>522,'a.author'=>$this->_user->id),$this->_user->id);
		return new ViewModel(array(
			'title' => 'Sheet',
			'sheet' => $sheet,
			'user'=>$this->_user->id,
			'note'=>$this->getDefinedTable(Administration\FlowTransactionTable::class),
		));
	}
	/**
	 *Add Sheet Action
	 **/
	public function addsheetAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$employee = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'employee');
			$getNo = $this->getDefinedTable(Hr\NotesheetTable::class)->getLastNo($no);
			if($getNo > 0):
				$Number = $getNo+1;
			else:
				$Number =1;
			endif;
			//print_r($getNo);exit;
			$data=array(
					'date' => $form['date'],
					'number' => $Number,
					//'submit_to' => $form['submit_to'],
					'priority_type' => $form['priority_type'],
					'subject' => $form['subject'],
					'description' => $form['description'],
					'employee' => $employee,
					'status' => 2,
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($data);exit;
			//$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\NotesheetTable::class)->save($data);
			$flow_result = $this->flowinitiation('522', $result);
			
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Notesheet  successfully initiated");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to initiate Notesheet");
			endif;
			return $this->redirect()->toRoute('notesheet',array('action' => 'viewsheet','id'=>$result));
		}
		$number = $this->getDefinedTable(Hr\NotesheetTable::class)->getMax('number');		
		return new ViewModel(array(
			'title' => 'Sheet',
			'login_id'=>$this->_login_id,
			'slnumber'=>$number,
			'sheet' => $this->getDefinedTable(Hr\NotesheetTable::class)->getAll(),
		));
	}
	/**
	 *Edit Sheet Action
	 **/
	public function editsheetAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			//$employee = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'employee');
			
			//print_r($getNo);exit;
			$data=array(
					'id' => $form['id'],
					'date' => $form['date'],
					//'number' => $Number,
					//'submit_to' => $form['submit_to'],
					'priority_type' => $form['priority_type'],
					'subject' => $form['subject'],
					'description' => $form['description'],
					//'employee' => $employee,
					'status' => 2,
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($data);exit;
			//$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\NotesheetTable::class)->save($data);
			$flow_result = $this->flowinitiation('522', $result);
			
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Notesheet  successfully initiated");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to initiate Notesheet");
			endif;
			return $this->redirect()->toRoute('notesheet',array('action' => 'viewsheet','id'=>$form['id']));
		}
		$number = $this->getDefinedTable(Hr\NotesheetTable::class)->getMax('number');		
		return new ViewModel(array(
			'title' => 'Sheet',
			'login_id'=>$this->_login_id,
			'slnumber'=>$number,
			'sheet' => $this->getDefinedTable(Hr\NotesheetTable::class)->get($this->_id),
		));
	}
	/**
	 *  Appointment type action
	 */
	public function viewsheetAction()
	{
		$this->init();
		$params = explode("-", $this->_id);
		if (isset($params['1']) && $params['1'] == '1' && isset($params['2']) && $params['2'] > 0) {
			$flag = $this->getDefinedTable(Acl\NotifyTable::class)->getColumn($params['2'], 'flag'); 
				if($flag == "0") {
					$notify = array('id' => $params['2'], 'flag'=>'1');
					$this->getDefinedTable(Acl\NotifyTable::class)->save($notify); 	
				}				
		}
		return new ViewModel(array(
			'title' => 'View Notesheet',
			'login_id'=>$this->_login_id,
			'sheet' => $this->getDefinedTable(Hr\NotesheetTable::class)->get($this->_id),
			'employeeObj'    => $this->getDefinedTable(Hr\EmployeeTable::class), 
			'userObj'        => $this->getDefinedTable(Administration\UsersTable::class), 
			'flowtransactionObj' => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'      => $this->getDefinedTable(Administration\FlowActionTable::class),
			'activityObj'      => $this->getDefinedTable(Acl\ActivityLogTable::class),
			'empObj'      => $this->getDefinedTable(Hr\EmployeeTable::class),
			'userObj'      => $this->getDefinedTable(Administration\UsersTable::class),
		));
		
	} 
	/**
	 *  process leave action
	 */
	public function processAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()){	
			$form = $this->getRequest()->getpost();
			$process_id	= '522';
			$flow_id = $form['flow'];
		
			if(empty($form['action'])):$action_id=0; else:$action_id = $form['action'];endif;
			$leave_id = $form['leave'];
			$remark = $form['remarks'];
			$application_focal=$form['focal'];
			$role= $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('id'=>$application_focal),'role');
			$current_flow = $this->getDefinedTable(Administration\FlowTransactionTable::class)->get($flow_id);
				foreach($current_flow as $flow);
			$next_activity_no = $flow['activity'] + 1;
			$action_performed = $this->getDefinedTable(Administration\FlowActionTable::class)->getColumn($action_id, 'action');
			$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get(array('flow'=>$flow['flow'],'action_performed'=>$action_id));
			foreach($privileges as $privilege);
			$app_data = array(
				'id'		=> $leave_id,				
				'status' 	=> $privilege['status_changed_to'],			
				'modified'  => $this->_modified
			);
			$app_data = $this->_safedataObj->rteSafe($app_data);
			$this->_connection->beginTransaction();
			$app_result = $this->getDefinedTable(Hr\NotesheetTable::class)->save($app_data);
			if($app_result):
				$activity_data = array(
						'process'      => $process_id,
						'process_id'   => $leave_id,
						'status'       => $privilege['status_changed_to'],
						'remarks'      => $remark,
						//'role'         => $role,
						'send_to'      => $application_focal,
						'author'	   => $this->_author,
						'created'      => $this->_created,
						'modified'     => $this->_modified,  
				);
				$activity_data = $this->_safedataObj->rteSafe($activity_data);
				$activity_result = $this->getDefinedTable(Acl\ActivityLogTable::class)->save($activity_data);
				if($activity_result):
					//if($privilege['route_to_role']):
						$flow_data = array(
							'flow'          => $flow['flow'],
							'role_id'       =>$application_focal,
							'application'   => $leave_id,
							'activity'      => $next_activity_no,
							'actor'         => $privilege['route_to_role'],
							'status'        => $privilege['status_changed_to'],
							'action'        => $privilege['action'],
							'routing'       => $flow['actor'],
							'routing_status'=> $flow['status'],
							'description'   => $remark,
							'process'       => $process_id,
							'author'        => $this->_author,
							'created'       => $this->_created,
							'modified'      => $this->_modified
						);
						$flow_data = $this->_safedataObj->rteSafe($flow_data);
						$flow_result = $this->getDefinedTable(Administration\FlowTransactionTable::class)->save($flow_data);
						if($flow_result > 0):
							$this->notify($leave_id,$privilege['id'],$remark,$flow_result);
							$this->getDefinedTable(Administration\FlowTransactionTable::class)->performed($flow_id);
							$this->_connection->commit();
							$this->flashMessenger()->addMessage("success^ Successfully performed application action <strong>".$action_performed."</strong>!");
						else:
							$this->_connection->rollback();
							$this->flashMessenger()->addMessage("error^ Failed to update application work flow for <strong>".$action_performed."</strong> action.");
						endif;
				/*	else:
						$remove_transaction_flows = $this->getDefinedTable(Administration\FlowTransactionTable::class)->remove($application_id);
						$this->_connection->commit();
						$this->flashMessenger()->addMessage("success^ Successfully Removed and approved or rejected or aborted the application.");
					endif;*/
				else:
					$this->_connection->rollback(); 
					$this->flashMessenger()->addMessage("error^ Failed to register the application in activity log.");
				endif;
			else:
				$this->_connection->rollback(); 
				$this->flashMessenger()->addMessage("error^ Failed to update application status for forward action.");
			endif;
			return $this->redirect()->toRoute('notesheet', array('action'=>'viewsheet', 'id' => $leave_id));
		}

		$login = array(
			'login_id'      => $this->_login_id,
			'login_role'    => $this->_login_role,
		); 
		//$focal=$this->getDefinedTable(Administration\UsersTable::class)->get(array('role',3));
		//echo '<>pre';print_r($focal);exit;
		$viewModel =  new ViewModel(array(
			'title'              => 'Protection Works Application Actions',
			'flow_id'            => $this->_id,
			'login'              => $login,
			'role'               =>$this->_login_role,
			'leaveObj'      	 => $this->getDefinedTable(Hr\NotesheetTable::class),
			'empObj'      	     => $this->getDefinedTable(Hr\EmployeeTable::class),
			'flowprivilegeObj'   => $this->getDefinedTable(Administration\FlowPrivilegeTable::class),
			'flowtransactionObj' => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'      => $this->getDefinedTable(Administration\FlowActionTable::class),
			'roleObj'            => $this->getDefinedTable(Acl\RolesTable::class),
			'focals' 			=> $this->getDefinedTable(Administration\UsersTable::class)->getNotIn('0','employee'),
			'focalsObj'         => $this->getDefinedTable(Administration\UsersTable::class),
			'employeeObj'        => $this->getDefinedTable(Hr\EmployeeTable::class), 
			'departmentObj'        => $this->getDefinedTable(Administration\ActivityTable::class), 
            'locationObj'        => $this->getDefinedTable(Administration\LocationTable::class),			
		));
		$viewModel->setTerminal(true);
        return $viewModel;		
	}
	/**
	 * Notification Action
	 */
	public function notify($leave_id,$privilege_id,$remarks = NULL,$flow_result)
	{
		$userlists='';
		$applications = $this->getDefinedTable(Hr\LeaveTable::class)->get($leave_id);
		foreach($applications as $app);
		$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get($privilege_id);
		foreach ($privileges as $flow) {
			$notify_msg = $app['employee']." - ".$flow['description']."<br>[".$remarks."]";
			$notification_data = array(
				'route'         => 'notesheet',
				'action'        => 'viewsheet',
				'key' 		    => $leave_id,
				'description'   => $notify_msg,
				'author'	    => $this->_author,
				'created'       => $this->_created,
				'modified'      => $this->_modified,   
			);
			//echo '<pre>';print_r($notification_data);exit;
			$notificationResult = $this->getDefinedTable(Acl\NotificationTable::class)->save($notification_data);
			if($notificationResult > 0 ){
				$notification_array = explode("|", $flow['route_notification_to']);
				if(sizeof($notification_array)>0){
					for($k=0;$k<sizeof($notification_array);$k++){
						$focalusers=$this->getDefinedTable(Administration\FlowTransactionTable::class)->get(array('id'=>$flow_result));
						foreach($focalusers as $applicationfocal):
						$focal_id = $applicationfocal['role_id'];
						//if($notification_array[$k]=='2'){
							if(!empty($focal_id)):
							   $userlists = $this->getDefinedTable(Administration\UsersTable::class)->get(array('id'=>$focal_id,'status'=>'1'));
							else:
								 $userlists = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>$notification_array[$k],'status'=>'1'));
							endif;
						//}
						endforeach;
					}
				}
				$email_array = [];
				$loop_count = 1;
				foreach($userlists as $userlist):
					$notify_data = array(
						'notification' => $notificationResult,
						'user'    	   => $userlist['id'],
						'flag'    	   => '0',
						'desc'    	   => $notify_msg,
						'author'	   => $this->_author,
						'created'      => $this->_created,
						'modified'     => $this->_modified,  
					);
					if($flow['notification'] == 1){
						$notifyResult = $this->getDefinedTable(Acl\NotifyTable::class)->save($notify_data);
					}
					if($loop_count == 1){
						$recipient_email = $userlist['email'];
						$recipient_name = $userlist['name'];
					}else{
						array_push($email_array, ['email'=>$userlist['email'],'name'=>$userlist['name']]);
					}
					$loop_count += 1;
				endforeach;
				
			}               	
		}
	}
	/**
	 * FLOW Function -- Initiation
	 */
	public function flowinitiation($process_id, $application_id)
	{
		$flow_id = $this->getDefinedTable(Administration\FlowTable::class)->getColumn(array('process'=>$process_id),'id');
		if($flow_id):
			$flow_role = $this->getDefinedTable(Administration\FlowTable::class)->getColumn($flow_id,'role');
			$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get(array('flow'=>$flow_id,'action_performed'=>'0'));
			foreach($privileges as $privilege);
			$data = array(
				'flow'             => $flow_id,
				'application'      => $application_id,
				'process'          => $process_id,
				'activity'         => 1,
				'actor'            => $privilege['route_to_role'],
				'status'           => $privilege['status_changed_to'],
				'action'           => $privilege['action'],
				'routing'          => $flow_role,
				'routing_status'   => $privilege['status_changed_to'],
				'action_performed' => 0,
				'description'      => $privilege['description'],
				'author'           => $this->_author,
				'created'          => $this->_created,
				'modified'         => $this->_modified
			);
			$data = $this->_safedataObj->rteSafe($data);
			$flow_result = $this->getDefinedTable(Administration\FlowTransactionTable::class)->save($data);
			return $flow_result;
		else:
			return '0';
		endif;
	}
	
	/*Sitting Fee/
	
	/**
	 * leave encashment
	*/
	public function sittingAction(){
		$this->init();
		
		return new ViewModel(array(
			'title'			=> 'Sitting Fee',
			'userID'  		=> $this->_login_id,
			'sittingObj' 	=> $this->getDefinedTable(Hr\SittingfeeTable::class),
			'employeeObj'   => $this->getDefinedTable(Hr\EmployeeTable::class),
			'userObj'       => $this->getDefinedTable(Administration\UsersTable::class),
			'flowtransObj'	=>$this->getDefinedTable(Administration\FlowTransactionTable::class),
		));
	}
	/**
	 *Add Sitting Fee
	*/
	public function addsittingAction(){
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost()->toArray();		
		   $data = array(							
				'date'           	 => $form['date'],
				'purpose'         	 => $form['purpose'],
				'amount_ttl'         => $form['total_amount'],
				'deduction_ttl'      => $form['total_deduction'],
				'payment_amount_ttl' => $form['total_pamount'],
				'author'             => $this->_author,
				'created'            => $this->_created,
				'modified'           => $this->_modified					
				);
				//print_r($data);exit;
				$data = $this->_safedataObj->rteSafe($data);
			    $result = $this->getDefinedTable(Hr\SittingfeeTable::class)->save($data);	
				$flow_result = $this->flowinitiation('526', $result);
			
			if($result > 0):
				$applicant		= $form['applicant'];
				$amount   		= $form['amount'];
				$deduction      = $form['deduction'];
				$payment_amount        = $form['payment_amount'];
				for($i=0; $i < sizeof($applicant); $i++):
				//$date1= explode('/', $date[$i]);
					$sitting_details = array(
		      					'sitting_id' 		=> $result,
								'employee'  	 	=> $applicant[$i],
								'amount'      		=> $amount[$i],
								'deduction'     	=> $deduction[$i],
								'payment_amount'    => $payment_amount[$i],
					   
						);
		     		$sitting_details   = $this->_safedataObj->rteSafe($sitting_details);
			     	$this->getDefinedTable(Hr\SittingfeeDtlsTable::class)->save($sitting_details);	
				endfor;
				if($result > 0 ){
				   $this->flashMessenger()->addMessage("success^ Successfully saved the Sitting Fee");
				   return $this->redirect()->toRoute('notesheet', array('action' => 'viewsittingfee','id'=>$result));
				}			
				else{
				  $this->flashMessenger()->addMessage("error^ Failed to add Sitting Fee, Try again");
				  return $this->redirect()->toRoute('notesheet', array('action' => 'sitting'));
				}	
			endif;
		endif;
		return new ViewModel(array(
			'title'=> 'Add Sitting Fee',
			'userID'       => $this->_login_id,
			'login_emp_ID' => $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'employee'),
			'employeeObj'  => $this->getDefinedTable(Hr\EmployeeTable::class),
			'userObj'      => $this->getDefinedTable(Administration\UsersTable::class), 
		));
				
	}
	
	/**
	 *edit Sitting Fee
	*/
	public function editsittingAction(){
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost()->toArray();		
		   $data = array(	
				'id'               => $form['sitting'],						
				'date'           	 => $form['date'],
				'purpose'         	 => $form['purpose'],
				'amount_ttl'         => $form['total_amount'],
				'deduction_ttl'      => $form['total_deduction'],
				'payment_amount_ttl' => $form['total_pamount'],
				'author'             => $this->_author,
				'created'            => $this->_created,
				'modified'           => $this->_modified					
				);
				
				$data = $this->_safedataObj->rteSafe($data);
			    $result = $this->getDefinedTable(Hr\SittingfeeTable::class)->save($data);	
				//$flow_result = $this->flowinitiation('526', $result);
			
			if($result > 0):
				$sittingdtl		= $form['sittingdtl'];
				$applicant		= $form['applicant'];
				$amount   		= $form['amount'];
				$deduction      = $form['deduction'];
				$payment_amount        = $form['payment_amount'];
				for($i=0; $i < sizeof($sittingdtl); $i++):
					if(isset($applicant[$i]) && $applicant[$i] > 0):
					$sitting_details = array(
								'id' 				=> $sittingdtl[$i],
		      					'sitting_id' 		=> $result,
								'employee'  	 	=> $applicant[$i],
								'amount'      		=> $amount[$i],
								'deduction'     	=> $deduction[$i],
								'payment_amount'    => $payment_amount[$i],
					   
						);
					$sitting_details   = $this->_safedataObj->rteSafe($sitting_details);
			     	$this->getDefinedTable(Hr\SittingfeeDtlsTable::class)->save($sitting_details);	
					endif;
				endfor;
				if(sizeof($sittingdtl)!=sizeof($applicant)){
					for($i=sizeof($sittingdtl); $i < sizeof($applicant); $i++):
						if(isset($applicant[$i]) && $applicant[$i] > 0):
							
							$sitting_details = array(
									'sitting_id' 		=> $result,
									'employee'  	 	=> $applicant[$i],
									'amount'      		=> $amount[$i],
									'deduction'     	=> $deduction[$i],
									'payment_amount'    => $payment_amount[$i],
									'author'    	 => $this->_author,
									'created'   	 => $this->_created,
									'modified'  	 => $this->_modified
							);
							$sitting_details   = $this->_safedataObj->rteSafe($sitting_details);
							$this->getDefinedTable(Hr\SittingfeeDtlsTable::class)->save($sitting_details);		
						   endif; 		     
					endfor;
					}
	
				if($result > 0 ){
				   $this->flashMessenger()->addMessage("success^ Successfully saved the Sitting Fee");
				   return $this->redirect()->toRoute('notesheet', array('action' => 'viewsittingfee','id'=>$result));
				}			
				else{
				  $this->flashMessenger()->addMessage("error^ Failed to add Sitting Fee, Try again");
				  return $this->redirect()->toRoute('notesheet', array('action' => 'sitting'));
				}	
			endif;
		endif;
		return new ViewModel(array(
			'title'=> 'Add Sitting Fee',
			'userID'       => $this->_login_id,
			'login_emp_ID' => $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'employee'),
			'employeeObj'  => $this->getDefinedTable(Hr\EmployeeTable::class),
			'userObj'      => $this->getDefinedTable(Administration\UsersTable::class), 
			'sittingfee'      => $this->getDefinedTable(Hr\SittingfeeTable::class)->get($this->_id), 
			'sittingfeedtls'      => $this->getDefinedTable(Hr\SittingfeeDtlsTable::class), 
		));
				
	}
	/**
	 *Add Sitting Fee Details
	*/
	public function addsittingdtlsAction(){
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost()->toArray();		
		   $data = array(							
				'employee'           => $form['applicant'],
				'sitting_id'         	 => $form['sitting_id'],
				'amount'         	 => $form['amount'],
				'deduction'          => $form['deduction'],
				'payment_amount'     => $form['payment_amount'],
				'status'         	 => 2,
				'date'         	 	=> $form['date'],
				'remarks'         	 	=> $form['remarks'],
				'author'             => $this->_author,
				'created'            => $this->_created,
				'modified'           => $this->_modified					
				);
				//print_r($data);exit;
				$data = $this->_safedataObj->rteSafe($data);
			    $result = $this->getDefinedTable(Hr\SittingfeeDtlsTable::class)->save($data);	
				
				if($result > 0 ){
				   $this->flashMessenger()->addMessage("success^ Successfully saved the Sitting Fee");
				   return $this->redirect()->toRoute('notesheet', array('action' => 'viewsittingfee','id'=>$data['sitting_id']));
				}			
				else{
				  $this->flashMessenger()->addMessage("error^ Failed to add Sitting Fee, Try again");
				  return $this->redirect()->toRoute('notesheet', array('action' => 'sitting'));
				}	
		endif;
		$viewModel =  new ViewModel(array(
			'title'=> 'Add Sitting Fee',
			'userID'       => $this->_login_id,
			'login_emp_ID' => $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'employee'),
			'employeeObj'  => $this->getDefinedTable(Hr\EmployeeTable::class),
			'leaveTypes'   => $this->getDefinedTable(Hr\LeaveTypeTable::class)->getAll(),
			'userObj'      => $this->getDefinedTable(Administration\UsersTable::class), 
			'sitting'      => $this->getDefinedTable(Hr\SittingfeeTable::class)->get($this->_id), 
		));
		$viewModel->setTerminal(true);
        return $viewModel;		
		
	}
	
	/**
	 * View 
	*/
	public function viewsittingfeeAction(){
		$this->init();
		$params = explode("-", $this->_id);
		if (isset($params['1']) && $params['1'] == '1' && isset($params['2']) && $params['2'] > 0) {
			$flag = $this->getDefinedTable(Acl\NotifyTable::class)->getColumn($params['2'], 'flag'); 
				if($flag == "0") {
					$notify = array('id' => $params['2'], 'flag'=>'1');
					$this->getDefinedTable(Acl\NotifyTable::class)->save($notify); 	
				}				
		}
		return new ViewModel(array(
			'title'			=> 'View Sitting Fee',
			'userID'  		=> $this->_login_id,
			'sittingdtlsObj' 	=> $this->getDefinedTable(Hr\SittingfeeDtlsTable::class),
			'sittings' 	=> $this->getDefinedTable(Hr\SittingfeeTable::class)->get($this->_id),
			'employeeObj'   => $this->getDefinedTable(Hr\EmployeeTable::class),
			'userObj'       => $this->getDefinedTable(Administration\UsersTable::class),
			'flowtransactionObj'  => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'       => $this->getDefinedTable(Administration\FlowActionTable::class), 
		));
	}

	/**sitting Fee process */
	public function sittingprocessAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()):		
			$form = $this->getRequest()->getPost()->toArray();		
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			   /* Send Request */
					$data = array(
						'id'			=> $form['sitting'],
						'status' 		=> 4,
						//'remarks'        => $form['remarks'],
						'author'	    => $this->_author,
						'modified'      => $this->_modified,
				    );
					$message = "Successfully Applied";
					$desc = "New Sitting Fee Applied";
					/*Get users under destination location with sub role Depoy Manager*/
					//$sourceLocation = $this->getDefinedTable(Fleet\VehicleRepairMaintaneseTable::class)->getColumn($form['tp_id'], 'location');			
			$result = $this->getDefinedTable(Hr\SittingfeeTable::class)->save($data);		
			if($result):
				$xpenseresult =$form['sitting'];
			
					$sittingfee = $this->getDefinedTable(Hr\SittingfeeTable::class)->get(array('id'=>$xpenseresult));
					foreach($sittingfee as $sittingfees);
					$location= $this->_user->location;
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
					
							$data = array(
								'voucher_date' 		=> $sittingfees['date'],
								'voucher_type' 		=> 12,
								'region'   			=>$region,
								'doc_id'   			=>"Sitting fee",
								'voucher_no' 		=> $voucher_no,
								'voucher_amount' 	=> $sittingfees['payment_amount_ttl'],
								'status' 			=> 3, // status initiated 
								'remark'			=>$sittingfees['purpose'],
								'author' 			=>$this->_author,
								'created' 			=>$this->_created,  
								'modified' 			=>$this->_modified,
							);
							$data = $this->_safedataObj->rteSafe($data);
							$resultTrans = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data);
							//if($resultTrans >0){
								$flow=array(
									'flow' 				=> 2,
									'application' 		=> $resultTrans,
									'activity'			=> $location,
									//'role_id'   		=>' ',
									'actor'   			=> 6,
									'action' 			=> "3|4",
									'routing' 			=> 2,
									'status' 			=> 3, // status initiated 
									'routing_status'	=> 2,
									'action_performed'	=> 1,
									'description'		=>"Sitting Fee",
									'author' 			=>$this->_author,
									'created' 			=>$this->_created,  
									'modified' 			=>$this->_modified,
								);
								$flow=$this->getDefinedTable(Administration\FlowTransactionTable::class)->save($flow);
								$transactionDtls1 = array(
									'transaction' => $resultTrans,
									'voucher_dates' => $data['voucher_date'],
									'voucher_types' => 12,
									'location' => $location,
									'head' =>'196',
									'sub_head' =>'2724',
									'bank_ref_type' => '',
									'debit' =>$sittingfees['amount_ttl'],
									'credit' =>'0.00',
									'ref_no'=> 'Sittingfee', 
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
									'voucher_dates' => $data['voucher_date'],
									'voucher_types' => 12,
									'location' => $location,
									'head' =>'150',
									'sub_head' =>'2723',
									'bank_ref_type' => '',
									'debit' =>'0.00',
									'credit' =>$sittingfees['deduction_ttl'],
									'ref_no'=> 'Sittingfee', 
									'type' => '1',//user inputted  data
									'status' => 3, // status applied
									'activity'=>$data['voucher_amount'],
									'author' =>$this->_author,
									'created' =>$this->_created,
									'modified' =>$this->_modified,
								);
								$transactionDtls2 = $this->_safedataObj->rteSafe($transactionDtls2);
								$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($transactionDtls2);
								$transactionDtls3 = array(
									'transaction' => $resultTrans,
									'voucher_dates' => $data['voucher_date'],
									'voucher_types' => 12,
									'location' => $location,
									'head' =>'36',
									'sub_head' =>'172',
									'bank_ref_type' => '',
									'credit' =>$sittingfees['payment_amount_ttl'],
									'debit' =>'0.00',
									'ref_no'=> 'Sittingfee' , 
									'type' => '1',//user inputted  data
									'status' => 3, // status applied
									'activity'=>$data['voucher_amount'],
									'author' =>$this->_author,
									'created' =>$this->_created,
									'modified' =>$this->_modified,
								);
								$transactionDtls3 = $this->_safedataObj->rteSafe($transactionDtls3);
								$result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($transactionDtls3);
								$data2 = array(
									'id' 	=> $sittingfees['id'],
									'transaction' => $resultTrans,
									);
								$data2 =  $this->_safedataObj->rteSafe($data2);
								$result4 = $this->getDefinedTable(Hr\SittingfeeTable::class)->save($data2);
						if($result4):
							$notification_data = array(
								'route'         => 'transaction',
								'action'        => 'againstdebit',
								'key' 		    => $resultTrans,
								'description'   => 'Sitting Fee Applied',
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
											'desc'    	 => 'Sitting Fee Applied',
											'author'	 => $this->_author,
											'created'    => $this->_created,
											'modified'   => $this->_modified,  
										);
										$notifyResult = $this->getDefinedTable(Acl\NotifyTable::class)->save($notify_data);
									//endif;
								endforeach;
							}
						endif;
				$this->_connection->commit(); // commit transaction over success
				$this->flashMessenger()->addMessage("success^".$message);
			else:
			    $this->_connection->rollback(); // rollback transaction over failure
			    $this->flashMessenger()->addMessage("error^ Cannot send good request");			  
			endif;
			return $this->redirect()->toRoute('notesheet',array('action'=>'sitting'));
		endif; 		
		$login = array(
			'login_id'      => $this->_login_id,
			'login_role'    => $this->_login_role,
		); 		
		$viewModel = new ViewModel(array(			
			'title' => 'Sitting Fee Application',
			'flow_id'            => $this->_id,
			'login'              => $login,
			'role'               =>$this->_login_role,
			'sittingObj'      	 => $this->getDefinedTable(Hr\SittingfeeTable::class),
			'flowprivilegeObj'   => $this->getDefinedTable(Administration\FlowPrivilegeTable::class),
			'flowtransactionObj' => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'      => $this->getDefinedTable(Administration\FlowActionTable::class),
			'roleObj'            => $this->getDefinedTable(Acl\RolesTable::class),
			'focals' => $this->getDefinedTable(Administration\UsersTable::class)->getNotIn('0','employee'),
			'focalsObj' 		 => $this->getDefinedTable(Administration\UsersTable::class),
			'employeeObj'        => $this->getDefinedTable(Hr\EmployeeTable::class), 
			'departmentObj'        => $this->getDefinedTable(Administration\ActivityTable::class),    
		));
		$viewModel->setTerminal(True);
		return $viewModel;
	}
	/**
	 * Notification Action
	 */
	public function notifyforencash($leave_id,$privilege_id,$remarks = NULL,$flow_result)
	{
		$userlists='';
		$applications = $this->getDefinedTable(Hr\LeaveEncashTable::class)->get($leave_id);
		foreach($applications as $app);
		$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get($privilege_id);
		foreach ($privileges as $flow) {
			$notify_msg = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($app['employee'],'full_name')." - ".$flow['description']."<br>[".$remarks."]";
			$notification_data = array(
				'route'         => 'notesheet',
				'action'        => 'viewsittingfee',
				'key' 		    => $leave_id,
				'description'   => $notify_msg,
				'author'	    => $this->_author,
				'created'       => $this->_created,
				'modified'      => $this->_modified,   
			);
			//echo '<pre>';print_r($notification_data);exit;
			$notificationResult = $this->getDefinedTable(Acl\NotificationTable::class)->save($notification_data);
			if($notificationResult > 0 ){
				$notification_array = explode("|", $flow['route_notification_to']);
				if(sizeof($notification_array)>0){
					for($k=0;$k<sizeof($notification_array);$k++){
						$focalusers=$this->getDefinedTable(Administration\FlowTransactionTable::class)->get(array('id'=>$flow_result));
						foreach($focalusers as $applicationfocal):
						$focal_id = $applicationfocal['role_id'];
						//if($notification_array[$k]=='2'){
							if(!empty($focal_id)):
							   $userlists = $this->getDefinedTable(Administration\UsersTable::class)->get(array('id'=>$focal_id,'status'=>'1'));
							else:
								 $userlists = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>$notification_array[$k],'status'=>'1'));
							endif;
						//}
						endforeach;
					}
				}
				//echo '<pre>';print_r($userlists);exit;
				$email_array = [];
				$loop_count = 1;
				foreach($userlists as $userlist):
					$notify_data = array(
						'notification' => $notificationResult,
						'user'    	   => $userlist['id'],
						'flag'    	   => '0',
						'desc'    	   => $notify_msg,
						'author'	   => $this->_author,
						'created'      => $this->_created,
						'modified'     => $this->_modified,  
					);

					if($flow['notification'] == 1){
						$notifyResult = $this->getDefinedTable(Acl\NotifyTable::class)->save($notify_data);
					}
					if($loop_count == 1){
						$recipient_email = $userlist['email'];
						$recipient_name = $userlist['name'];
					}else{
						array_push($email_array, ['email'=>$userlist['email'],'name'=>$userlist['name']]);
					}
					$loop_count += 1;
				endforeach;
				
			}               	
		}
	}
	
}




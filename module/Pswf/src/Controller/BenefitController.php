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
use Acl\Model As Acl;
class BenefitController extends AbstractActionController
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
	 * master action
	**/
	public function masterAction()
	{
		$this->init();
		
		return new ViewModel(array(
			'title'          => "Master",
			'psheadObj'    => $this->getDefinedTable(Pswf\HeadTable::class),
			'category' => $this->getDefinedTable(Pswf\CategoryTable::class)->getAll(),
		));
	}
	
	/**
	 * Add Advance Salary
	**/
	public function addmasterAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			$data = array(
				'name'           => $form['name'],
				'head_coa'       => $form['head'],
				//'status'         => $form['status'],
				'author'         => $this->_author,
				'created'        => $this->_created,
				'modified'       => $this->_modified
			);
			$result = $this->getDefinedTable(Pswf\CategoryTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added Data."); 	             
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new data.");	 	             
			endif;
			return $this->redirect()->toRoute('benefit', array('action' => 'master'));
		}
		$ViewModel = new ViewModel([
			'title'        => 'Add Master',
			'ps_heads'      => $this->getDefinedTable(Pswf\HeadTable::class)->getAll(),
		]); 
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *  Edit Master
	 */
	public function editmasterAction()
	{
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$master_id = $array_id[0];
		$page = (sizeof($array_id)>1)?$array_id[1]:'';
		
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
            $data = array(  
				'id'             => $form['master_id'],
				'name'           => $form['name'],
				'head_coa'       => $form['head'],
				//'status'         => $form['status'],
				'author'         => $this->_author,
				'modified'       => $this->_modified
            );
            $data = $this->_safedataObj->rteSafe($data);
			//echo '<pre>';print_r($data);exit;
			$this->_connection->beginTransaction();
            $result = $this->getDefinedTable(Pswf\CategoryTable::class)->save($data);
            if($result > 0):
				$this->_connection->commit();
                $this->flashMessenger()->addMessage("success^ successfully edited Befefit");
            else:
				$this->_connection->rollback();
                $this->flashMessenger()->addMessage("error^ Failed to edit Befefit");
            endif;
			return $this->redirect()->toRoute('benefit', array('action'=>'master'));
        }		
		$ViewModel = new ViewModel([
			'title'        => 'Edit Master',
			'page'         => $page,
			'masters'      => $this->getDefinedTable(Pswf\CategoryTable::class)->get($master_id),
			'psheadObj'    => $this->getDefinedTable(Pswf\HeadTable::class),
		]);
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Index action
	**/
	public function indexAction()
	{
		$this->init();
		$user_id=$this->_login_id;
		$user_role=$this->_login_role;
		return new ViewModel(array(
			'title'       => "Benefit List",
			'user'        =>$user_id,
			'role'        =>$user_role,
			'categoryObj' => $this->getDefinedTable(Pswf\CategoryTable::class),
			'benefit'     => $this->getDefinedTable(Pswf\BenefitTable::class)->getAll(),
			'subheadObj'  => $this->getDefinedTable(Pswf\SubheadTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
		));
	}
	public function fetchsubheadsAction() {
		$form = $this->getRequest()->getPost();
		$categoryId = $form['categoryId'];
        $headId = $this->getDefinedTable(Pswf\CategoryTable::class)->getColumn($categoryId,'head_coa');
		// Fetch employees based on category ID (adjust this according to your database and model)
		$subheads = $this->getDefinedTable(Pswf\SubheadTable::class)->get(['head' => $headId]);
	     // Prepare the options for the employee dropdown
		$options = '';
		foreach ($subheads as $psh) {
			 $options .= '<option value="' . $psh['id'] . '">' . $psh['name'] . '</option>';
		}
		
		// Return the data as JSON
		echo json_encode(['subheads' => $options]);
		exit;
	}
	/**
	 * benefit Pending with me action
	**/
	public function benefitpendingAction()
	{
		$this->init();
		$user_id=$this->_login_id;
		$user_role=$this->_login_role;
		$benefit = $this->getDefinedTable(Pswf\BenefitTable::class)->getPendingBenefit('a.id',array('a.process'=>1307,'a.send_to'=>$this->_user->id),$this->_user->id);
		//echo '<pre>';print_r($benefit);exit;
		return new ViewModel(array(
			'title'       => "Benefit List",
			'user'        =>$user_id,
			'role'        =>$user_role,
			'categoryObj' => $this->getDefinedTable(Pswf\CategoryTable::class),
			'benefit'     => $benefit,
			'subheadObj'  => $this->getDefinedTable(Pswf\SubheadTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
		));
	}
	/**
	 * benefit Pending with me action
	**/
	public function benefitactionbymeAction()
	{
		$this->init();
		$user_id=$this->_login_id;
		$user_role=$this->_login_role;
		$benefit = $this->getDefinedTable(Pswf\BenefitTable::class)->getActionByMe(array('a.process'=>1307,'a.author'=>$this->_user->id),$this->_user->id);
		return new ViewModel(array(
			'title'       => "Benefit List",
			'user'        =>$user_id,
			'role'        =>$user_role,
			'categoryObj' => $this->getDefinedTable(Pswf\CategoryTable::class),
			'benefit'     => $benefit,
			'subheadObj'  => $this->getDefinedTable(Pswf\SubheadTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
		));
	}
	
	/**
	 * Add Benefit
	**/
	public function addbenefitAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			$userLoc = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author, 'location');
			$data=array(
				'date' 				=> $form['date'],
				'transaction_id' 	=> 0,
				'category' 			=> $form['category'],
				'subhead' 			=> $form['subhead'],
				'employee' 			=> $form['employee'],//need to capture only if category is 134
				'requested_amt' 	=> 0.000,
				'approved_amt'    	=> 0.000,
				'relationship' 		=> 0,
				'subject' 			=> $form['subject'],
				'description' 		=> $form['description'],
				'location' 		    => $userLoc,
				'status' 			=> 4,
				'author' 			=> $this->_author,
				'created' 			=> $this->_created,
				'modified'			=> $this->_modified,
			);
			//echo'<pre>';print_r($data);exit;
			$result = $this->getDefinedTable(Pswf\BenefitTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added Data."); 	             
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new data.");	 	             
			endif;
			return $this->redirect()->toRoute('benefit', array('action' => 'index'));
		}
		return new ViewModel(array(
			'title' => 'Benefit History',
			'login_id' => $this->_login_id,
			'category' => $this->getDefinedTable(Pswf\CategoryTable::class)->getAll(),
			'sheet' => $this->getDefinedTable(Hr\NotesheetTable::class)->getAll(),
			'employee' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAllEmp(),
			'relationships' => $this->getDefinedTable(Pswf\RelationshipTable::class)->getAll(),
		));
	}
	/**
	 * Apply benefit
	 **/
	public function applyAction()
	{
		$this->init();
		if ($this->getRequest()->isPost()) {
			$form = $this->getRequest()->getPost();
			$data_exist = $this->getDefinedTable(Pswf\BenefitTable::class)->get([
				'employee' => $form['employee'], 
				'status' => 4, 
				'category' => [1, 3, 4]
			]);
			$userLoc = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author, 'location');
			
			$beneficiary = $form['employee'];
			$relationship = $form['relationship'];
			if ($form['category'] == 5) {
				if ($data_exist) {
					// If data exists for category 5, show error message and redirect back to apply
					$this->flashMessenger()->addMessage("error^ Employee not eligible for Benefit Application");
					return $this->redirect()->toRoute('benefit', array('action' => 'apply'));
				} else {
					// Proceed to save the benefit data for category 5
					$data = array(
						'date'               => $form['date'],
						'category'           => $form['category'],
						'subhead'            => $form['subhead'],
						'employee'           => (!empty($beneficiary)) ? $beneficiary : 0, 
						'requested_amt'      => $form['requested_amt'],
						'relationship'       => (!empty($relationship)) ? $relationship : 0,
						'subject'            => $form['subject'],
						'description'        => $form['description'],
						'location'           => $userLoc,
						'status'             => 2,
						'author'             => $this->_author,
						'created'            => $this->_created,
						'modified'           => $this->_modified,
					);
					$result = $this->getDefinedTable(Pswf\BenefitTable::class)->save($data);
					$flow_result = $this->flowinitiation('1307', $result);
					if ($result > 0) {
						$this->flashMessenger()->addMessage("success^ Data successfully initiated");
					} else {
						$this->flashMessenger()->addMessage("error^ Failed to initiate Benefit");
					}
					return $this->redirect()->toRoute('benefit', array('action' => 'viewbenefit', 'id' => $result));
				}
			} else {
				if ($data_exist || $form['category'] != 5) {
					$data = array(
						'date'               => $form['date'],
						'category'           => $form['category'],
						'subhead'            => $form['subhead'],
						'employee'           => (!empty($beneficiary)) ? $beneficiary : 0,
						'requested_amt'      => $form['requested_amt'],
						'relationship'       => (!empty($relationship)) ? $relationship : 0,
						'subject'            => $form['subject'],
						'description'        => $form['description'],
						'location'           => $userLoc,
						'status'             => 2,
						'author'             => $this->_author,
						'created'            => $this->_created,
						'modified'           => $this->_modified,
					);
					$result = $this->getDefinedTable(Pswf\BenefitTable::class)->save($data);
					$flow_result = $this->flowinitiation('1307', $result);
					if ($result > 0) {
						$this->flashMessenger()->addMessage("success^ Data successfully initiated");
					} else {
						$this->flashMessenger()->addMessage("error^ Failed to initiate Benefit");
					}
					return $this->redirect()->toRoute('benefit', array('action' => 'viewbenefit', 'id' => $result));
				} else {
					$this->flashMessenger()->addMessage("error^ Employee not eligible for Benefit Application");
					return $this->redirect()->toRoute('benefit', array('action' => 'apply'));
				}
			}
		}
		return new ViewModel(array(
			'title' => 'Benefit Application',
			'login_id' => $this->_login_id,
			'category' => $this->getDefinedTable(Pswf\CategoryTable::class)->getAll(),
			'sheet' => $this->getDefinedTable(Hr\NotesheetTable::class)->getAll(),
			'employee' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAllEmp(),
			'relationships' => $this->getDefinedTable(Pswf\RelationshipTable::class)->getAll(),
		));
	}

	/**
	 *  Update BY FINANCE-ALLOCATE the benefit amount
	 */
	public function editbenefitAction()
	{
		$this->init();
		$id = $this->_id;
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
            $data = array(  
				'id'                => $form['benefit_id'],
				'approved_amt'      => $form['benefit_amount'],
				'author'            => $this->_author,
				'modified'          => $this->_modified
            );
            $data = $this->_safedataObj->rteSafe($data);
			//echo '<pre>';print_r($data);exit;
			$this->_connection->beginTransaction();
            $result = $this->getDefinedTable(Pswf\BenefitTable::class)->save($data);
            if($result > 0):
				$this->_connection->commit();
                $this->flashMessenger()->addMessage("success^ successfully Updated the Benefit Amout");
            else:
				$this->_connection->rollback();
                $this->flashMessenger()->addMessage("error^ Failed to Updated the Benefit Amout");
            endif;
			return $this->redirect()->toRoute('benefit',array('action' => 'viewbenefit','id'=>$id));
        }		
		$ViewModel = new ViewModel([
			'title'        => 'Update Benefit',
			'benefits'     => $this->getDefinedTable(Pswf\BenefitTable::class)->get($id),
		]);
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *  view benefit
	 */
	public function viewbenefitAction()
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
			'title' 				=> 'View Benefit',
			'login_id'				=> $this->_login_id,
			'benefit' 				=> $this->getDefinedTable(Pswf\BenefitTable::class)->get($this->_id),
			'employeeObj'    		=> $this->getDefinedTable(Hr\EmployeeTable::class), 
			'categoryObj' 				=> $this->getDefinedTable(Pswf\CategoryTable::class),
			'userObj'        		=> $this->getDefinedTable(Administration\UsersTable::class), 
			'flowtransactionObj' 	=> $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'      	=> $this->getDefinedTable(Administration\FlowActionTable::class),
			'activityObj'     		=> $this->getDefinedTable(Acl\ActivityLogTable::class),
			'empObj'      			=> $this->getDefinedTable(Hr\EmployeeTable::class),
			'userObj'      			=> $this->getDefinedTable(Administration\UsersTable::class),
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
			$process_id	= '1307';
			$flow_id = $form['flow'];
		
			if(empty($form['action'])):$action_id=0; else:$action_id = $form['action'];endif;
			$benefit_id = $form['benefit'];
			$remark = $form['remarks'];
			$application_focal=$form['focal'];
			$role= $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('id'=>$application_focal),'role');
			$current_flow = $this->getDefinedTable(Administration\FlowTransactionTable::class)->get($flow_id);
				foreach($current_flow as $flow);
			$next_activity_no = $flow['activity'] + 1;
			$action_performed = $this->getDefinedTable(Administration\FlowActionTable::class)->getColumn($action_id, 'action');
			$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get(array('flow'=>$flow['flow'],'action_performed'=>$action_id));
			foreach($privileges as $privilege);
			if($privilege['status_changed_to']==4){
				/*Transaction Start-Create when approve the benefit*/
				$location= $this->_user->location;
				$region = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('id'=>$this->_author),'region');
				$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(25,'prefix');
				$date = date('ym',strtotime(date('Y-m-d')));
				$tmp_VCNo = $loc.'-'.$prefix.$date;
				
				$results = $this->getDefinedTable(Pswf\TransactionTable::class)->getSerial($tmp_VCNo);
				
				$pltp_no_list = array();
				foreach($results as $re):
					array_push($pltp_no_list, substr($re['voucher_no'], 14));
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
				
				$benefits = $this->getDefinedTable(Pswf\BenefitTable::class)->get($benefit_id);
				foreach($benefits as $bfts);
				
				$dataT = array(
					'voucher_date' 		=> $bfts['date'],
					'voucher_type' 		=> 12,
					'region'   			=>$region,
					'doc_id'   			=>"PSWF-Application",
					'doc_type'          =>"PSWF",
					'voucher_no' 		=> $voucher_no,
					'voucher_amount' 	=> $bfts['approved_amt'],
					'status' 			=>3, //$privilege['status_changed_to'], 
					'remark'			=>$bfts['subject'],
					'author' 			=>$bfts['author'],
					'created' 			=>$this->_created,  
					'modified' 			=>$this->_modified,
				);
				$dataT = $this->_safedataObj->rteSafe($dataT);
				$resultTrans = $this->getDefinedTable(Pswf\TransactionTable::class)->save($dataT);
				if($resultTrans){
					/**TRANSACTION[double-booking[1]]*/
					$tdetailsdata1 = array(
						'transaction' => $resultTrans,
						'voucher_dates' => $dataT['voucher_date'],
						'voucher_types' => $dataT['voucher_type'],
						'location' => $location,
						'activity' =>$location,
						'against' =>0,
						'currency' =>1,
						'rate' =>'0.000',
						'credit_sdr' =>'0.000',
						'head' =>$this->getDefinedTable(Pswf\SubheadTable::class)->getColumn($bfts['subhead'],'head'),
						'sub_head' => $bfts['subhead'],
						'bank_ref_type' => '',
						'cheque_no' => 'chea',
						'debit' => $bfts['approved_amt'],
						'credit' => '0.000',
						'ref_no'=> 'test1234', 
						'type' => '1',//user inputted  data
						'status' => 3,//$privilege['status_changed_to'],//suppose to be 4
						'author' =>$bfts['author'],
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata1);
					/**TRANSACTION[double-booking[2]]*/
					$tdetailsdata2 = array(
						'transaction' => $resultTrans,
						'voucher_dates' => $dataT['voucher_date'],
						'voucher_types' => $dataT['voucher_type'],
						'location' => $location,
						'activity' =>$location,
						'against' =>0,
						'currency' =>1,
						'rate' =>'0.000',
						'credit_sdr' =>'0.000',
						'head' => 2,//Bank Head
						'sub_head' => 2,//Bank SUB-Head
						'bank_ref_type' => '',
						'cheque_no' => 'chea',
						'debit' => '0.000',
						'credit' => $bfts['approved_amt'],//BANKRE-PAYMENT 
						'ref_no'=> 'test1234', 
						'type' => '1',//user inputted  data
						'status' => 3,//$privilege['status_changed_to'], //suppose to be 4
						'author' =>$bfts['author'],
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$result2 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata2);
				}
				/*END-------------TRANSACTION-UPDATE*/
			}
			
			/*START-------------BENEFIT-UPDATE*/
			$app_data = array(
				'id'		     => $benefit_id,		
                'transaction_id' => isset($resultTrans) ? $resultTrans : 0,				
				'status' 	     => $privilege['status_changed_to'],			
				'modified'       => $this->_modified
			);
			$app_data = $this->_safedataObj->rteSafe($app_data);
			$this->_connection->beginTransaction();
			$app_result = $this->getDefinedTable(Pswf\BenefitTable::class)->save($app_data);
			/*END-------------BENEFIT-UPDATE*/
			if($app_result):
				$activity_data = array(
					'process'      => $process_id,
					'process_id'   => $benefit_id,
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
							'application'   => $benefit_id,
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
							$this->notify($benefit_id,$privilege['id'],$remark,$flow_result);
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
			return $this->redirect()->toRoute('benefit', array('action'=>'viewbenefit', 'id' => $benefit_id));
		}

		$login = array(
			'login_id'      => $this->_login_id,
			'login_role'    => $this->_login_role,
		); 
		//$focal=$this->getDefinedTable(Administration\UsersTable::class)->get(array('role',3));
		//echo '<>pre';print_r($focal);exit;
		$viewModel =  new ViewModel(array(
			'title'              => 'Process',
			'flow_id'            => $this->_id,
			'login'              => $login,
			'role'               =>$this->_login_role,
			'benefitObj'      	 => $this->getDefinedTable(Pswf\BenefitTable::class),
			'flowprivilegeObj'   => $this->getDefinedTable(Administration\FlowPrivilegeTable::class),
			'flowtransactionObj' => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'      => $this->getDefinedTable(Administration\FlowActionTable::class),
			'roleObj'            => $this->getDefinedTable(Acl\RolesTable::class),
			'focals' 			=> $this->getDefinedTable(Administration\UsersTable::class)->getNotIn('0','employee'),
			'focalsObj' => $this->getDefinedTable(Administration\UsersTable::class),
			'employeeObj'        => $this->getDefinedTable(Hr\EmployeeTable::class), 
			'departmentObj'        => $this->getDefinedTable(Administration\ActivityTable::class),   
		));
		$viewModel->setTerminal(true);
        return $viewModel;		
	}
	/**
	 * Notification Action
	 */
	public function notify($benefit_id,$privilege_id,$remarks = NULL,$flow_result)
	{
		$userlists='';
		$applications = $this->getDefinedTable(Pswf\BenefitTable::class)->get($benefit_id);
		foreach($applications as $app);
		$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get($privilege_id);
		foreach ($privileges as $flow) {
			$notify_msg = $app['employee']." - ".$flow['description']."<br>[".$remarks."]";
			$notification_data = array(
				'route'         => 'benefit',
				'action'        => 'viewbenefit',
				'key' 		    => $benefit_id,
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
	public function flowinitiation($process_id, $result)
	{
		$flow_id = $this->getDefinedTable(Administration\FlowTable::class)->getColumn(array('process'=>$process_id),'id');
		if($flow_id):
			$flow_role = $this->getDefinedTable(Administration\FlowTable::class)->getColumn($flow_id,'role');
			$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get(array('flow'=>$flow_id,'action_performed'=>'0'));
			foreach($privileges as $privilege);
			$data = array(
				'flow'             => $flow_id,
				'application'      => $result,
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
	/**
	 * history action
	**/
	public function historyAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$status = $form['emp_status'];
			$location = $form['location'];
		}else{
			$status = '-1';
			$location = '-1';
		}
		$data = array(
			'status' => $status,
			'location' => $location,
		);
		return new ViewModel(array(
			'title'          => "Benefit History",
			'employee' => $this->getDefinedTable(Hr\EmployeeTable::class)->getEmp($data),
			'location' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'data' => $data,
			'department' => $this->getDefinedTable(Administration\DepartmentTable::class),
			'division' => $this->getDefinedTable(Administration\ActivityTable::class),
			'section' => $this->getDefinedTable(Administration\SectionTable::class),
			'ptitle' => $this->getDefinedTable(Hr\PositiontitleTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'empStatus' => $this->getDefinedTable(Hr\EmployeeStatusTable::class),
		));
	}
	/**
	 * master action
	**/
	public function viewhistoryAction()
	{
		$this->init();
		return new ViewModel(array(
			'title'          => "View History",
			'employeeObj' 	 => $this->getDefinedTable(Hr\EmployeeTable::class),
			'empbenefit' 	 => $this->getDefinedTable(Pswf\BenefitTable::class)->get(array('employee'=>$this->_id)),
			'id'			 =>$this->_id,
			'categoryObj' 	 => $this->getDefinedTable(Pswf\CategoryTable::class),
		));
	}	
	/**
	 * addemphistory action
	 */
	public function addhistoryAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();	
			$data = array(	
				'employee' => $this->_id,
				'category' => $form['category'],
				'date'=>$form['date'],
				'subject'=>$form['subject'],
				'location'=>$this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($this->_id,'location'),
				'requested_amt' => $form['requested_amt'],
				'approved_amt' => $form['approved_amt'],
				'description' => $form['description'],
				'status' => 8,
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);		
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\BenefitTable::class)->save($data);				
			if($result > 0):			
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Benefit History added");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("Failed^ Failed to add new Benefit History");
				endif;
			return $this->redirect()->toRoute('benefit', array('action'=>'viewhistory', 'id'=>$this->_id));			 
		}
		return new ViewModel(array(
			'title' => 'New History',
			'emp_id'   => $this->_id,
			'category' => $this->getDefinedTable(Pswf\CategoryTable::class)->getAll(),
			'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
		));	
	}
    /**
	 *  PSWF-EMI-REPAYMENT-------------------
	 *  Benefits EMI-REPAYMENT
	 */
	public function emiAction()
	{
		$this->init();	
		$year = ($this->_id == 0)? date('Y'):$this->_id;	
		return new ViewModel(array(
			'title'  => 'Benefit EMI',
			'emi' => $this->getDefinedTable(Pswf\BenefitemiTable::class)->getEmi($year),'year' => $year,
			'emiObj' => $this->getDefinedTable(Pswf\BenefitemiTable::class),
		));
	}
	/**
	 *  Generate EMI-REPAYMENT
	 */
	public function generateemiAction()
    {
        $this->init();
		$year = date("Y", strtotime(date('Y-m-d')));
		$month = date("m", strtotime(date('Y-m-d')));
		$data_check = $this->getDefinedTable(Pswf\BenefitemiTable::class)->getEMIByMonth($year,$month);
        
		if(!$data_check){
			$benefits = $this->getDefinedTable(Pswf\BenefitTable::class)->get(array('category'=>[2,3],'status'=>4));
			foreach($benefits as $row):
			   $data = array(
					'employee' 	=> $row['employee'],
					'location' 	=> $row['location'],
					'sub_head' => $row['subhead'],
					'year' 		=> $year,
					'month' 	=> $month,
					'benefit_amount' => $row['approved_amt'],
					'emi'      => 0.000,
					'ref_no' 	=> $month.'-'.$year,
					'status' 	=> 2,
					'author' 	=> $this->_author,
					'created' 	=> $this->_created,
					'modified' 	=> $this->_modified,
				);
				$result = $this->getDefinedTable(Pswf\BenefitemiTable::class)->save($data);
			endforeach;
            if ($result) {
                $this->flashMessenger()->addMessage("success^ Action Successful");
            } else {

                $this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
            }
		}else{
			$this->flashMessenger()->addMessage("error^ The EMI has been already Generated!");
		}
        return $this->redirect()->toRoute('benefit',array('action' => 'emi'));
    }
	/**
	 * EMI-REPAYMENT-Details-EDIT
	 */
	public function emidetailsAction()
    {
       $this->init();
		if(isset($this->_id) & $this->_id!=0):
			$my = explode('-', $this->_id);
		endif;
		if(sizeof($my)==0):
			$my = array('1'); //default selection
		endif;
	
        $role=$this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'role');
        $admin_locs=$this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
		
       //print_r($my);exit;
        return new ViewModel(array(
            'title' 			=> 'Details',
			'emi' 				=> $this->getDefinedTable(Pswf\BenefitemiTable::class)->getEMIByMonth($my[0],$my[1]),
			'month'				=> $my[1],
			'year'				=> $my[0],
			'admin_locs'        =>$admin_locs,
			'employee'          => $this->getDefinedTable(HR\EmployeeTable::class),
			'locationObj'          => $this->getDefinedTable(Administration\LocationTable::class),
      
        ));
    }
	 //edit EMI details
    public function editemidetailsAction()
    {
        $this->init();
        if ($this->getRequest()->isPost()) {
            $form = $this->getRequest()->getPost();
            $data = array(
				'id'		=> $this->_id,
                'emi' 		=> $form['emi'],
                'modified' 	=> $this->_modified,
            );
            $data = $this->_safedataObj->rteSafe($data);
            $result = $this->getDefinedTable(Pswf\BenefitemiTable::class)->save($data);
            if ($result) {
                $this->flashMessenger()->addMessage("success^ Action Successful");
            } else {
                $this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
            }
           
            return $this->redirect()->toRoute('benefit',array('action' => 'emidetails','id' => $form['year']."-".$form['month'] ));
            
        }
        $ViewModel = new ViewModel([
            'title' => 'Edit EMI Details.',
            'emi' => $this->getDefinedTable(Pswf\BenefitemiTable::class)->get($this->_id),
            'location' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
        ]);
		$ViewModel->setTerminal(True);
		return $ViewModel;
    }
	/*
	 * Submit EMI to the accounts section
	* */
	public function submitAction()
	{
		
		$this->init();
		$data = explode('-', $this->_id);
		//echo '<pre>';print_r($data);exit;
		$year=$data[0];
		$month=$data[1];
		$emiadvances = $this->getDefinedTable(Pswf\BenefitemiTable::class)->get(array('year'=>$year,'month'=>$month));
		foreach($emiadvances as $row):
		   $data = array(
				'id' 	=> $row['id'],
				'status' 	=> 4,
				'modified' 	=> $this->_modified,
			);
		$result = $this->getDefinedTable(Pswf\BenefitemiTable::class)->save($data);
		endforeach;
		$submit_date  = $year.'-'.$month.'-30';
		$location=$this->getDefinedTable(Administration\UsersTable::class)->getcolumn($this->_author, 'location');
		$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($location, 'prefix');
		$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getcolumn(4,'prefix');
		$date = date('ym',strtotime($submit_date));
		$tmp_VCNo = $loc.'-'.$prefix.$date;
			
		$results = $this->getDefinedTable(Pswf\TransactionTable::class)->getSerial($tmp_VCNo);
		
		$pltp_no_list = array();
		foreach($results as $result):
			array_push($pltp_no_list, substr($result['voucher_no'],-3));
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
		$dataT = array(
			'voucher_date' =>$submit_date,
			'voucher_type' => 4,
			'region'   =>$region,
			'doc_id'   =>"Benefits",
			'voucher_no' => $voucher_no,
			'voucher_amount' => $this->getDefinedTable(Pswf\BenefitemiTable::class)->getSum(array('year'=>$year,'month'=>$month),'emi'),
			'status' => 4, // status initiated 
			'author' =>$this->_author,
			'created' =>$this->_created,  
			'modified' =>$this->_modified,
		);
		$this->_connection->beginTransaction();
		$resultt = $this->getDefinedTable(Pswf\TransactionTable::class)->save($dataT);
		if($resultt>0){
			/*BANK-DATA*/			
			$tdetailsdata1 = array(
				'transaction' => $resultt,
				'voucher_dates' =>$submit_date,
				'voucher_types' => 4,
				'location' => $location,
				'head' =>2,
				'sub_head' =>2,
				'bank_ref_type' => '',
				'debit' =>$this->getDefinedTable(Pswf\BenefitemiTable::class)->getSum(array('year'=>$year,'month'=>$month),'emi'),
				'against'=>0,
				'credit' => '0.000',
				'ref_no'=> "", 
				'type' => '1',//user inputted  data
				'status' => 4, // status initiated
				'activity'=>$location,
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($tdetailsdata1);exit;
			$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata1);
			$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata1);
			foreach($this->getDefinedTable(Pswf\BenefitemiTable::class)->get(array('year'=>$year,'month'=>$month)) as $benefits):
			$head=$this->getDefinedTable(Pswf\SubheadTable::class)->getColumn($benefits['sub_head'],'head');
			/*BENEFITS DATA*/
			$tdetailsdata = array(
				'transaction' => $resultt,
				'voucher_dates' =>$submit_date,
				'voucher_types' => 4,
				'location' => $benefits['location'],
				'head' =>$head,//FINd the head
				'sub_head' =>$benefits['sub_head'],
				'bank_ref_type' => '',
				'debit' =>'0.000',
				'against'=>0,
				'credit' =>$benefits['emi'],
				'ref_no'=> "", 
				'type' => '1',//user inputted  data
				'status' => 4, // status initiated
				'activity'=>$location,
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($tdetailsdata);exit;
			$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
			$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);
			endforeach;
		    $this->_connection->commit();
		    $this->flashMessenger()->addMessage("success^ Action Successful");
		}
		else{
			$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessenger()->addMessage("error^ Failed while submitting payroll, Try again after some time");	
			return $this->redirect()->toRoute('benefit', array('action'=>'emidetails'));
		}
		return $this->redirect()->toRoute('benefit', array('action'=>'emi'));
	}
	
}

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
use Accounts\Model As Accounts;
use Hr\Model As Hr;

class TravelController extends AbstractActionController
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
	 * Travel action
	 */
	public function travelAction()
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
		//$sheet = $this->getDefinedTable(Hr\NotesheetTable::class)->getDateWise('date',$year,$month);
		$travel = $this->getDefinedTable(Hr\TATable::class)->getDateWise('date',$year,$month,array('author'=>$this->_user->id));
		
		return new ViewModel(array(
			'title' => 'Travel',
			'minYear' => $this->getDefinedTable(Hr\TATable::class)->getMin('date'),
			'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
			'data' => $data,
			'ta' => $travel,
		));
	}
	/**
	 * Travel Pending action
	 */
	public function travelpendingAction()
	{
		$this->init();
		$travel = $this->getDefinedTable(Hr\TATable::class)->getPendingTA('a.id',array('a.process'=>523,'a.send_to'=>$this->_user->id),$this->_user->id);
		
		return new ViewModel(array(
			'title' => 'Travel Pending',
			'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
		
			'ta' => $travel,
		));
	}/**
	 * Sheet action
	 */
	public function travelactionbymeAction()
	{
		$this->init();
		$travel = $this->getDefinedTable(Hr\TATable::class)->getActionByMe(array('a.process'=>523,'a.author'=>$this->_user->id),$this->_user->id);
		
		return new ViewModel(array(
			'title' => 'Travel',
			'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
		
			'ta' => $travel,
		));
	}
	/**
	 *Add Travel Action
	 **/
	public function addtravelAction()
	{
		$this->init();
		//print_r($Number);exit;
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$getNo = $this->getDefinedTable(Hr\TATable::class)->getLastNo();
			if($getNo > 0){
				$Number = $getNo+1;
			}
			else{
				$Number =1;
			}
			
			if($form['advance']>0.00){
				if($form['debit']=="0" || $form['cedit']=="0"){
					//print_r($form['advance']);
					$this->flashMessenger()->addMessage("Fail To Add TA^ Please Select Debit and credit Subhead");
					return $this->redirect()->toRoute('travel',array('action' => 'addtravel'));
				 }
			}
			$data=array(
					'date' 				=> $form['ta_date'],
					'ta_no' 			=> $Number,
					'employee' 			=> $form['employee'],
					'purpose' 			=> $form['ta_purpose'],
					'estimated_expense' => $form['estimated_expense'],
					'advance' 			=> $form['advance'],
					'debit' 			=> $form['debit'],
					'debit_ref' 		=> $form['debit_ref'],
					'type' 				=> $form['type'],
					'credit' 			=> $form['credit'],
					'credit_ref' 		=> $form['credit_ref'],
					'status' 			=> 2,
					'author' 			=>$this->_author,
					'created' 			=>$this->_created,
					'modified' 			=>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\TATable::class)->save($data);
			$flow_result = $this->flowinitiation('523', $result);
			
			if($result > 0):
				$from_station		= $form['from_station'];
				$from_date   		= $form['from_date'];
				$travel_mode   		= $form['travel_mode'];
				$to_station      	= $form['to_station'];
				$to_date          	= $form['to_date'];
				$halt     			= $form['halt'];
				$purpose         	= $form['purpose'];
				for($i=0; $i < sizeof($travel_mode); $i++):
				$fromdate= explode('/', $from_date[$i]);
				$todate= explode('/', $to_date[$i]);
					$ta_details = array(
		      					'ta' 				=> $result,
					     		'from_station'      => $from_station[$i],
								'from_date'     	=> $fromdate[2].'-'.$fromdate[0].'-'.$fromdate[1],
								'travel_mode'     	=> $travel_mode[$i],
								'to_station'     	=> $to_station[$i],
					     		'to_date'           => $todate[2].'-'.$todate[0].'-'.$todate[1],
					     		'halt'  	 		=> $halt[$i],
					     		'purpose'      	 	=> $purpose[$i],
					      		'author'    	 	=> $this->_author,
					      		'created'   	 	=> $this->_created,
					      		'modified'  	 	=> $this->_modified
						);
		     		$ta_details   = $this->_safedataObj->rteSafe($ta_details);
			     	$this->getDefinedTable(Hr\TADetailsTable::class)->save($ta_details);	
				endfor;
				$this->flashMessenger()->addMessage("success^ Travel Authorization is successfully initiated");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to initiate Travel Authorization");
			endif;
			return $this->redirect()->toRoute('travel',array('action' => 'viewtravel','id'=>$result));
		}	
		return new ViewModel(array(
			'title' => 'Travel',
			'login_id'=>$this->_login_id,
			'employee' => $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'employee'),
			'employeeObj'		=> $this->getDefinedTable(Hr\EmployeeTable::class),
			'debit'		=> $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>64)),
			'credit'		=> $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>142)),
			'focal' => $this->getDefinedTable(Administration\UsersTable::class)->getNotIn('0','employee'),
			'purpose'	=> $this->getDefinedTable(Hr\TravelPurposeTable::class)->getAll(),
		));
	}
	/**
	 *Edit Travel Action
	 **/
	public function edittravelAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			//$employee = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'employee');
			
			//print_r($getNo);exit;
			if($form['advance']>0){
				if($form['debit']==0 || $form['cedit']==0){
					$this->flashMessenger()->addMessage("Fail To Add TA^ Please Select Debit and credit Subhead");
					return $this->redirect()->toRoute('travel',array('action' => 'addtravel'));
				}
			}
			$data=array(
					'id'				=> $this->_id,
					'date' 				=> $form['ta_date'],
					'employee' 			=> $form['employee'],
					'purpose' 			=> $form['ta_purpose'],
					'estimated_expense' => $form['estimated_expense'],
					'type' 				=> $form['type'],
					'advance' 			=> $form['advance'],
					'debit' 			=> $form['debit'],
					'debit_ref' 		=> $form['debit_ref'],
					'credit' 			=> $form['credit'],
					'credit_ref' 		=> $form['credit_ref'],
					'status' 			=> 2,
					'author' 			=>$this->_author,
					'created' 			=>$this->_created,
					'modified' 			=>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\TATable::class)->save($data);
			if($result > 0):
				$id			 		 =	$form['id'];
				$from_station		= $form['from_station'];
				$from_date   		= $form['from_date'];
				$travel_mode   		= $form['travel_mode'];
				$to_station      	= $form['to_station'];
				$to_date          	= $form['to_date'];
				$halt     			= $form['halt'];
				$purpose         	= $form['purpose'];
				for($i=0; $i < sizeof($id); $i++):
				$fromdate= explode('/', $from_date[$i]);
				$todate= explode('/', $to_date[$i]);
					$ta_details = array(
								'id'				=> $id[$i],
		      					'ta' 				=> $result,
					     		'from_station'      => $from_station[$i],
								'from_date'     	=> $fromdate[2].'-'.$fromdate[0].'-'.$fromdate[1],
								'travel_mode'     	=> $travel_mode[$i],
								'to_station'     	=> $to_station[$i],
					     		'to_date'           => $todate[2].'-'.$todate[0].'-'.$todate[1],
					     		'halt'  	 		=> $halt[$i],
					     		'purpose'      	 	=> $purpose[$i],
					      		'author'    	 	=> $this->_author,
					      		'created'   	 	=> $this->_created,
					      		'modified'  	 	=> $this->_modified
						);
		     		$ta_details   = $this->_safedataObj->rteSafe($ta_details);
			     	$this->getDefinedTable(Hr\TADetailsTable::class)->save($ta_details);	
				endfor;
				if(sizeof($id)<sizeof($travel_mode)){
				for($i=sizeof($id); $i < sizeof($travel_mode); $i++):
				$fromdate= explode('/', $from_date[$i]);
				$todate= explode('/', $to_date[$i]);
					$ta_details = array(
		      					'ta' 				=> $result,
					     		'from_station'      => $from_station[$i],
								'from_date'     	=> $fromdate[2].'-'.$fromdate[0].'-'.$fromdate[1],
								'travel_mode'     	=> $travel_mode[$i],
								'to_station'     	=> $to_station[$i],
					     		'to_date'           => $todate[2].'-'.$todate[0].'-'.$todate[1],
					     		'halt'  	 		=> $halt[$i],
					     		'purpose'      	 	=> $purpose[$i],
					      		'author'    	 	=> $this->_author,
					      		'created'   	 	=> $this->_created,
					      		'modified'  	 	=> $this->_modified
						);
		     		$ta_details   = $this->_safedataObj->rteSafe($ta_details);
			     	$this->getDefinedTable(Hr\TADetailsTable::class)->save($ta_details);
					endfor;
				}
				$this->flashMessenger()->addMessage("success^ TA  successfully Edited");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to edit TA");
			endif;
			return $this->redirect()->toRoute('travel',array('action' => 'viewtravel','id'=>$result));
		}		
		return new ViewModel(array(
			'title' => 'Travel',
			'login_id'=>$this->_login_id,
			'travel' => $this->getDefinedTable(Hr\TATable::class)->get($this->_id),
			'ta_details' => $this->getDefinedTable(Hr\TADetailsTable::class),
			'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
			'employee' => $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'employee'),
			'debit'		=> $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>64)),
			'credit'		=> $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>142)),
			'purpose'	=> $this->getDefinedTable(Hr\TravelPurposeTable::class)->getAll(),
		));
	}
	/**
	 *  View Travel action
	 */
	public function viewtravelAction()
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
			'title' => 'View TA',
			'login_id'=>$this->_login_id,
			'travel' => $this->getDefinedTable(Hr\TATable::class)->get($this->_id),
			'traveldetails' => $this->getDefinedTable(Hr\TADetailsTable::class),
			'employeeObj'    => $this->getDefinedTable(Hr\EmployeeTable::class), 
			'userObj'        => $this->getDefinedTable(Administration\UsersTable::class), 
			'flowtransactionObj' => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'      => $this->getDefinedTable(Administration\FlowActionTable::class),
			'activityObj'      => $this->getDefinedTable(Acl\ActivityLogTable::class),
			'empObj'      => $this->getDefinedTable(Hr\EmployeeTable::class),
			'userObj'      => $this->getDefinedTable(Administration\UsersTable::class),
			'subheadObj'      => $this->getDefinedTable(Accounts\SubheadTable::class),
			'purposeObj'	=> $this->getDefinedTable(Hr\TravelPurposeTable::class),
		));
		
	} 
	 /* Delete ta details action
	 */
	public function deleteAction()
	{
		$this->init(); 
		foreach($this->getDefinedTable(Hr\TADetailsTable::Class)->get($this->_id) as $tadetails);
		$result = $this->getDefinedTable(Hr\TADetailsTable::Class)->remove($this->_id);
		if($result > 0):

				$this->flashMessenger()->addMessage("success^ Item deleted successfully");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to delete item");
			endif;
			//end			
		
			return $this->redirect()->toRoute('travel',array('action' => 'edittravel','id'=>$tadetails['ta']));	
	}
	/**
	 * claim action
	 */
	public function claimAction()
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
		//$sheet = $this->getDefinedTable(Hr\TAClaimTable::class)->getDateWise('date',$year,$month);
			$claim = $this->getDefinedTable(Hr\TAClaimTable::class)->getDateWise('date',$year,$month,array('author'=>$this->_user->id));
		
		return new ViewModel(array(
			'title' => 'Claim',
			'minYear' => $this->getDefinedTable(Hr\TAClaimTable::class)->getMin('date'),
			'data' => $data,
			'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
			'claim' => $claim,
		));
	}/**
	 * claim action
	 */
	public function claimpendingAction()
	{
		$this->init();
		
			$claim = $this->getDefinedTable(Hr\TAClaimTable::class)->getPendingTC('a.id',array('a.process'=>524,'a.send_to'=>$this->_user->id),$this->_user->id);
		
		return new ViewModel(array(
			'title' => 'Claim',
			'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
			'claim' => $claim,
		));
	}/**
	 * claim action
	 */
	public function claimactionbymeAction()
	{
		$this->init();
			$claim = $this->getDefinedTable(Hr\TAClaimTable::class)->getActionByMe(array('a.process'=>524,'a.send_to'=>$this->_user->id),$this->_user->id);
		
		return new ViewModel(array(
			'title' => 'Claim',
			'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
			'claim' => $claim,
		));
	}
	/**
	 *Add Travel Action
	 **/
	public function addclaimAction()
	{
		$this->init();		
		$employee=$this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'employee');
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$employee=$this->getDefinedTable(Hr\TATable::class)->getColumn($form['ta_no'],'employee');
			//$this->_connection->beginTransaction();
			$data=array(
					'date' 				=> $form['claim_date'],
					'ta' 				=> $form['ta_no'],
					'actual_expense' 	=> $form['actual_expense'],
					'employee' 			=> $employee,
					'estimated_expense' => $form['estimated_expense'],
					'advance' 			=> $form['advance'],
					'amount_claim' 		=> $form['amount_claim'],
					'tot_da' 			=> $form['da_amount'],
					'tot_mileage' 		=> $form['mileage_amount'],
					'tot_hotel' 		=> $form['hotel_amount'],
					'tot_other' 		=> $form['other_amount'],
					'status' 			=> 2,
					'author' 			=>$this->_author,
					'created' 			=>$this->_created,
					'modified' 			=>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\TAClaimTable::class)->save($data);
			$flow_result = $this->flowinitiation('524', $result);
			
			if($result > 0):
				$date          		= $form['date'];
				$from				= $form['from'];
				$to   				= $form['to'];
				$departure      	= $form['departure'];
				$arrival          	= $form['arrival'];
				$da     			= $form['da'];
				$mileage         	= $form['mileage'];
				$hotel         		= $form['hotel'];
				$other         		= $form['other'];
				$total         		= $form['total'];
				$remarks         	= $form['remarks'];
				
				for($i=0; $i < sizeof($from); $i++):
				$date1= explode('/', $date[$i]);
					$claim_details = array(
		      					'claim' 			=> $result,
								'date'           	=> $date1[2].'-'.$date1[0].'-'.$date1[1],
					     		'from'      		=> $from[$i],
								'to'     			=> $to[$i],
								'departure'     	=> $departure[$i],
					     		'arrival'  	 		=> $arrival[$i],
					     		'da'      	 		=> $da[$i],
					     		'mileage'      	 	=> $mileage[$i],
								'hotel'      	 	=> $hotel[$i],
								'other'      	 	=> $other[$i],
					     		'total'      	 	=> $total[$i],
					     		'remarks'      	 	=> $remarks[$i],
					   
						);
		     		$claim_details   = $this->_safedataObj->rteSafe($claim_details);
			     	$this->getDefinedTable(Hr\TAClaimDetailsTable::class)->save($claim_details);	
				endfor;
				$head=$form['head'];
				$subhead=$form['subhead'];
				$ref=$form['ref_no'];
				$credit=$form['credit'];
				$debit=$form['debit'];
				for($j=0; $j < sizeof($head); $j++):
						$data1 = array(
									'claim' 			=> $result,
									'head' 				=> $head[$j],
									'subhead'      		=> $subhead[$j],
									'ref_no'     		=> $ref_no[$j],
									'credit'  	 		=> ($credit[$j]=="")?0.00:$credit[$j],
									'debit'  	 		=> ($debit[$j]=="")?0.00:$debit[$j],
						   
							);
						 $data1   = $this->_safedataObj->rteSafe($data1);
						 $this->getDefinedTable(Hr\ClaimSubheadTable::class)->save($data1);	
					endfor;
				//	$this->_connection->commit();
				$this->flashMessenger()->addMessage("success^ Travel Authorization is successfully initiated");
			else:
			//	$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to initiate Travel Authorization");
			endif;
			return $this->redirect()->toRoute('travel',array('action' => 'viewclaim','id'=>$result));
		}	
		return new ViewModel(array(
			'title' => 'Travel Claim',
			'login_id'=>$this->_login_id,
			'employee' => $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'employee'),
			'employeeObj'		=> $this->getDefinedTable(Hr\EmployeeTable::class),
			'ta'				=> $this->getDefinedTable(Hr\TATable::class)->getta(array('ta.employee'=>$employee,'ta.status'=>8)),
			'heads'		=> $this->getDefinedTable(Accounts\HeadTable::class)->get(array('h.id'=>[36,38,203,204,64,142,179,188,217,218])),
			'focal' => $this->getDefinedTable(Administration\UsersTable::class)->getNotIn('0','employee'),
		));
	}
	/**
	 *Edit Travel Claim Action
	 **/
	public function editclaimAction()
	{
		$this->init();
		$employee=$this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'employee');
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			if($form['amount_claim']<0){
				$credit=0;
				$payment=$form['payment'];
			}
			else{
				$credit=$form['credit'];
				$payment=0;
			}
			$data=array(
					'id'				=> $this->_id,
					'date' 				=> $form['claim_date'],
					'ta' 				=> $form['ta_no'],
					'actual_expense' 	=> $form['actual_expense'],
					'employee' 			=> $employee,
					'estimated_expense' => $form['estimated_expense'],
					'advance' 			=> $form['advance'],
					'amount_claim' 		=> $form['amount_claim'],
					'tot_da' 			=> $form['da_amount'],
					'tot_mileage' 		=> $form['mileage_amount'],
					'tot_hotel' 		=> $form['hotel_amount'],
					'tot_other' 		=> $form['other_amount'],
					'status' 			=> 2,
					'author' 			=>$this->_author,
					'created' 			=>$this->_created,
					'modified' 			=>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\TAClaimTable::class)->save($data);
			if($result > 0):
				$id			 		= $form['id'];
				$date          		= $form['date'];
				$from		= $form['from'];
				$to   		= $form['to'];
				$departure      	= $form['departure'];
				$arrival          		= $form['arrival'];
				$da     			= $form['da'];
				$mileage         	= $form['mileage'];
				$hotel         	= $form['hotel'];
				$other         	= $form['other'];
				$total         	= $form['total'];
				$remarks         	= $form['remarks'];
				for($i=0; $i < sizeof($id); $i++):
				$date1= explode('/', $date[$i]);
					$claim_details = array(
						'id'				=> $id[$i],
						'claim' 			=> $result,
						'date'           	=> $date1[2].'-'.$date1[0].'-'.$date1[1],
						 'from'      		=> $from[$i],
						'to'     			=> $to[$i],
						'departure'     	=> $departure[$i],
						'arrival'  	 		=> $arrival[$i],
						'da'      	 		=> $da[$i],
						'mileage'      	 	=> $mileage[$i],
						'hotel'      	 	=> $hotel[$i],
						'other'      	 	=> $other[$i],
						'total'      	 	=> $total[$i],
						'remarks'      	 	=> $remarks[$i],
					   
						);
		     		$claim_details   = $this->_safedataObj->rteSafe($claim_details);
			     	$this->getDefinedTable(Hr\TAClaimDetailsTable::class)->save($claim_details);	
				endfor;
				if(sizeof($id)!=sizeof($from)){
				for($i=sizeof($id); $i < sizeof($from); $i++):
				$date1= explode('/', $date[$i]);
					$claim_details = array(
						'claim' 			=> $result,
						'date'           	=> $date1[2].'-'.$date1[0].'-'.$date1[1],
						'from'      		=> $from[$i],
						'to'     			=> $to[$i],
						'departure'     	=> $departure[$i],
						'arrival'  	 		=> $arrival[$i],
						'da'      	 		=> $da[$i],
						'mileage'      	 	=> $mileage[$i],
						'hotel'      	 	=> $hotel[$i],
						'other'      	 	=> $other[$i],
						'total'      	 	=> $total[$i],
						'remarks'      	 	=> $remarks[$i],
					   
						);
		     		$claim_details   = $this->_safedataObj->rteSafe($claim_details);
			     	$this->getDefinedTable(Hr\TAClaimDetailsTable::class)->save($claim_details);
					endfor;
				}

				$subid= $form['subid'];
				$head=$form['head'];
				$subhead=$form['subhead'];
				$ref=$form['ref_no'];
				$credit=$form['credit'];
				$debit=$form['debit'];
				for($j=0; $j < sizeof($subid); $j++):
					$data1 = array(
								'id'				=> $subid[$j],
								'claim' 			=> $result,
								'head' 				=> $head[$j],
								'subhead'      		=> $subhead[$j],
								'ref_no'     		=> $ref_no[$j],
								'credit'  	 		=> ($credit[$j]=="")?0.00:$credit[$j],
								'debit'  	 		=> ($debit[$j]=="")?0.00:$debit[$j],
					   
						);
					 $data1   = $this->_safedataObj->rteSafe($data1);
					 $this->getDefinedTable(Hr\ClaimSubheadTable::class)->save($data1);	
				endfor;
				if(sizeof($subid)!=sizeof($head)){
				for($j=sizeof($id); $j < sizeof($head); $j++):
					
						$data1 = array(
									'claim' 			=> $result,
									'head' 				=> $head[$j],
									'subhead'      		=> $subhead[$j],
									'ref_no'     		=> $ref_no[$j],
									'credit'  	 		=> ($credit[$j]=="")?0.00:$credit[$j],
									'debit'  	 		=> ($debit[$j]=="")?0.00:$debit[$j],
						   
							);
						 $data1   = $this->_safedataObj->rteSafe($data1);
						 $this->getDefinedTable(Hr\ClaimSubheadTable::class)->save($data1);	
					endfor;
				}
				$this->flashMessenger()->addMessage("success^ TA  successfully Edited");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to edit TA");
			endif;
			return $this->redirect()->toRoute('travel',array('action' => 'viewclaim','id'=>$result));
		}		
		return new ViewModel(array(
			'title' 			=> 'Edit Travel Claim',
			'login_id'			=>$this->_login_id,
			'travelObj' 		=> $this->getDefinedTable(Hr\TATable::class),
			'claims' 			=> $this->getDefinedTable(Hr\TAClaimTable::class)->get($this->_id),
			'claim_details' 	=> $this->getDefinedTable(Hr\TAClaimDetailsTable::class),
			'employeeObj' 		=> $this->getDefinedTable(Hr\EmployeeTable::class),
			'employee' 			=> $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'employee'),
			'ta'				=> $this->getDefinedTable(Hr\TATable::class)->get(array('employee'=>$employee,'status'=>8)),
			'heads'		=> $this->getDefinedTable(Accounts\HeadTable::class)->get(array('h.id'=>[36,38,203,204,64,142,179,188,217,218])),
			'subObj' 			=> $this->getDefinedTable(Hr\ClaimSubheadTable::class),
			'subhead' 			=> $this->getDefinedTable(Accounts\SubheadTable::class)->getAll(),
		));
	}
	/* Delete Claim details action
	 */
	public function deleteclaimAction()
	{
		$this->init(); 
		foreach($this->getDefinedTable(Hr\TAClaimDetailsTable::Class)->get($this->_id) as $claimdetails);
		$result = $this->getDefinedTable(Hr\TAClaimDetailsTable::Class)->remove($this->_id);
		if($result > 0):

				$this->flashMessenger()->addMessage("success^ deleted successfully");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to delete");
			endif;
			//end			
		
			return $this->redirect()->toRoute('travel',array('action' => 'editclaim','id'=>$claimdetails['claim']));	
	}
	/**
	 *  View Travel action
	 */
	public function viewclaimAction()
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
			'title' => 'View TA Claim',
			'login_id'=>$this->_login_id,
			'claim' => $this->getDefinedTable(Hr\TAClaimTable::class)->get($this->_id),
			'claimdetails' => $this->getDefinedTable(Hr\TAClaimDetailsTable::class),
			'taObj' 			=> $this->getDefinedTable(Hr\TATable::class),
			'employeeObj'    => $this->getDefinedTable(Hr\EmployeeTable::class), 
			'userObj'        => $this->getDefinedTable(Administration\UsersTable::class), 
			'flowtransactionObj' => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'      => $this->getDefinedTable(Administration\FlowActionTable::class),
			'activityObj'      => $this->getDefinedTable(Acl\ActivityLogTable::class),
			'empObj'      => $this->getDefinedTable(Hr\EmployeeTable::class),
			'userObj'      => $this->getDefinedTable(Administration\UsersTable::class),
			'claimshObj'      => $this->getDefinedTable(Hr\ClaimSubheadTable::class),
			'subheadObj' 			=> $this->getDefinedTable(Accounts\SubheadTable::class),
			'headObj'		=> $this->getDefinedTable(Accounts\HeadTable::class),
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
			$process_id	= '523';
			$flow_id = $form['flow'];
			if(empty($form['action'])):$action_id=0; else:$action_id = $form['action'];endif;
			$ta_id = $form['ta'];
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
				'id'		=> $ta_id,				
				'status' 	=> $privilege['status_changed_to'],			
				'modified'  => $this->_modified
			);
			$app_data = $this->_safedataObj->rteSafe($app_data);
			$this->_connection->beginTransaction();
			$app_result = $this->getDefinedTable(Hr\TATable::class)->save($app_data);
			
			$tas=$this->getDefinedTable(Hr\TATable::class)->get($app_result);
			foreach($tas as $talist);
			//forward to finance if status is 8 
			$user=$this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('employee'=>$talist['employee']),'id');
				if($talist['status']==8 && $talist['advance']>0)
				{
					$location = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('employee'=>$talist['employee']), 'location');
					$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($location, 'prefix');
					$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(11,'prefix');
					$date = date('ym',strtotime(date('Y-m-d')));
					$tmp_VCNo = $loc.'-'.$prefix.$date;
					
					$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
					
					$pltp_no_list = array();
					foreach($results as $result):
						array_push($pltp_no_list, substr($result['voucher_no'], 13));
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
						'voucher_date' 		=> $talist['date'],
						'voucher_type' 		=> 11,
						'region'   			=>$region,
						'doc_id'   			=>"Travel Authorization",
						'voucher_no' 		=> $voucher_no,
						'voucher_amount' 	=> str_replace( ",", "",$talist['advance']),
						'status' 			=> 3, // status initiated 
						'remark'			=>$talist['id'],
						'author' 			=>$user,
						'created' 			=>$this->_created,  
						'modified' 			=>$this->_modified,
					);
					$resultt = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
					if($resultt >0){
						$flow1=array(
							'flow' 				=> 2,
							'application' 		=> $resultt,
							'activity'			=>$location,
							'actor'   			=>5,
							'action' 			=> "3|4",
							'routing' 			=> 2,
							'status' 			=> 3, // status initiated 
							'routing_status'	=>2,
							'action_performed'	=>1,
							'description'		=>"Travel Authorization",
							'author' 			=> $this->_author,
							'created' 			=> $this->_created,  
							'modified' 			=> $this->_modified,
						);
						$flow1=$this->getDefinedTable(Administration\FlowTransactionTable::class)->save($flow1);
						$tdetailsdata = array(
							'transaction' => $resultt,
							'voucher_dates' => $talist['date'],
							'voucher_types' => 11,
							'location' => $location,
							'head' => $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($talist['debit'],'head'),
							'sub_head' =>$talist['debit'],
							'bank_ref_type' => '',
							'debit' =>$talist['advance'],
							'credit' =>'0.00',
							'ref_no'=> $talist['debit_ref'], 
							'type' => '1',//user inputted  data  
							'status' => 3, // status appied
							'activity'=>$location,
							'author' =>$user,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
						$tdetailsdata = array(
							'transaction' => $resultt,
							'voucher_dates' => $talist['date'],
							'voucher_types' => 11,
							'location' => $location,
							'head' => $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($talist['credit'],'head'),
							'sub_head' =>$talist['credit'],
							'bank_ref_type' => '',
							'debit' =>'0.00',
							'credit' =>$talist['advance'],
							'ref_no'=> $talist['debit_ref'], 
							'type' => '1',//user inputted  data  
							'status' => 3, // status appied
							'activity'=>$location,
							'author' =>$user,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
						if($result1>0){
							$data = array(
								'id'		=> $ta_id,				
								'transaction' 	=> $resultt,			
								'modified'  => $this->_modified
							);
							$data = $this->_safedataObj->rteSafe($data);
							$re = $this->getDefinedTable(Hr\TATable::class)->save($data);
						}
						$message = "Successfully Forwarded TA";
						$br = "Travel Authorization Forwarded"; 
						$notification_data = array(
							'route'         => 'transaction',
							'action'        => 'viewcredit',
							'key' 		    => $resultt,
							'description'   => $br,
							'author'	    => $this->_author,
							'created'       => $this->_created,
							'modified'      => $this->_modified,   
						);
						//print_r($notification_data);exit;
						$notificationResult = $this->getDefinedTable(Acl\NotificationTable::class)->save($notification_data);
						//echo $notificationResult; exit;
						if($notificationResult > 0 ){	
							
								$user = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>'5'));
								foreach($user as $row):						    
									$user_location_id = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($row['id'], 'location');
									if($user_location_id == $sourceLocation ):						
										$notify_data = array(
											'notification' => $notificationResult,
											'user'    	   => $row['id'],
											'flag'    	 => '0',
											'desc'    	 => $br,
											'author'	 => $this->_author,
											'created'    => $this->_created,
											'modified'   => $this->_modified,  
										);
										//print_r($notify_data);exit;
										$notifyResult = $this->getDefinedTable(Acl\NotifyTable::class)->save($notify_data);
									endif;
								endforeach;
							}
						}
				}
			if($app_result):
				$activity_data = array(
						'process'      => $process_id,
						'process_id'   => $ta_id,
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
							'application'   => $ta_id,
							'activity'      => $next_activity_no,
							'actor'         => $privilege['route_to_role'],
							'status'        => $privilege['status_changed_to'],
							'action'        => $privilege['action'],
							'routing'       => $flow['actor'],
							'routing_status'=> $flow['status'],
							'description'   => $remark,
							'author'        => $this->_author,
							'created'       => $this->_created,
							'modified'      => $this->_modified
						);
						$flow_data = $this->_safedataObj->rteSafe($flow_data);
						$flow_result = $this->getDefinedTable(Administration\FlowTransactionTable::class)->save($flow_data);
						if($flow_result > 0):
							$this->notify($ta_id,$privilege['id'],$remark,$flow_result);
							$this->getDefinedTable(Administration\FlowTransactionTable::class)->performed($flow_id);
							$this->_connection->commit();
							$this->flashMessenger()->addMessage("success^ Successfully performed application action <strong>".$action_performed."</strong>!");
						else:
							$this->_connection->rollback();
							$this->flashMessenger()->addMessage("error^ Failed to update application work flow for <strong>".$action_performed."</strong> action.");
						endif;
				else:
					$this->_connection->rollback(); 
					$this->flashMessenger()->addMessage("error^ Failed to register the application in activity log.");
				endif;
			else:
				$this->_connection->rollback(); 
				$this->flashMessenger()->addMessage("error^ Failed to update application status for forward action.");
			endif;
			return $this->redirect()->toRoute('travel', array('action'=>'viewtravel', 'id' => $ta_id));
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
			'taObj'      	 => $this->getDefinedTable(Hr\TATable::class),
			'empObj'      	 => $this->getDefinedTable(Hr\EmployeeTable::class),
			'flowprivilegeObj'   => $this->getDefinedTable(Administration\FlowPrivilegeTable::class),
			'flowtransactionObj' => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'      => $this->getDefinedTable(Administration\FlowActionTable::class),
			'roleObj'            => $this->getDefinedTable(Acl\RolesTable::class),
			'focals' => $this->getDefinedTable(Administration\UsersTable::class)->getNotIn('0','employee'),
			'focalsObj' => $this->getDefinedTable(Administration\UsersTable::class),
			'employeeObj'        => $this->getDefinedTable(Hr\EmployeeTable::class), 
			'activityObj'        => $this->getDefinedTable(Administration\ActivityTable::class),  
		));
		$viewModel->setTerminal(true);
        return $viewModel;		
	}
	/**
	 * Notification Action
	 */
	public function notify($travel_id,$privilege_id,$remarks = NULL,$flow_result)
	{
		$userlists='';
		$applications = $this->getDefinedTable(Hr\TATable::class)->get($travel_id);
		foreach($applications as $app);
		$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get($privilege_id);
		$emp=$this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($app['employee'],'full_name');
		foreach ($privileges as $flow) {
			$notify_msg = $emp." - ".$flow['description']."<br>[".$remarks."]";
			$notification_data = array(
				'route'         => 'travel',
				'action'        => 'viewtravel',
				'key' 		    => $travel_id,
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
		//print_r($flow_id);
		//print_r($process_id);exit;
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
	/**
	 *  process claim action
	 */
	public function processclaimAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()){	
			$form = $this->getRequest()->getpost();
			$process_id	= '524';
			$flow_id = $form['flow'];
			if(empty($form['action'])):$action_id=0; else:$action_id = $form['action'];endif;
			$claim_id = $form['claim'];
			$remark = $form['remarks'];
			$application_focal=$form['focal'];
			$role= $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('id'=>$application_focal),'role');
			$current_flow = $this->getDefinedTable(Administration\FlowTransactionTable::class)->get($flow_id);
			foreach($current_flow as $flow);
			$next_activity_no = $flow['activity'] + 1;
			$action_performed = $this->getDefinedTable(Administration\FlowActionTable::class)->getColumn($action_id, 'action');
			$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get(array('flow'=>$flow['flow'],'action_performed'=>$action_id));
			foreach($privileges as $privilege);
			//print_r($flow_id);exit;
			$app_data = array(
				'id'		=> $claim_id,				
				'status' 	=> $privilege['status_changed_to'],			
				'modified'  => $this->_modified
			);
			$app_data = $this->_safedataObj->rteSafe($app_data);
			$this->_connection->beginTransaction();
			$app_result = $this->getDefinedTable(Hr\TAClaimTable::class)->save($app_data);
			
			$claim=$this->getDefinedTable(Hr\TAClaimTable::class)->get($app_result);
			foreach($claim as $claims);
			if($claims['status']==8 )
			{
				$location = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('employee'=>$claims['employee']), 'location');
				$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(11,'prefix');
				$date = date('ym',strtotime(date('Y-m-d')));
				$tmp_VCNo = $loc.'-'.$prefix.$date;
				
				$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
				
				$pltp_no_list = array();
				foreach($results as $result):
					array_push($pltp_no_list, substr($result['voucher_no'], 13));
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
				$user=$this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('employee'=>$claims['employee']),'id');
					$data1 = array(
						'voucher_date' 		=> $claims['date'],
						'voucher_type' 		=> 11,
						'region'   			=>$region,
						'doc_id'   			=>"Travel Claim",
						'voucher_no' 		=> $voucher_no,
						'voucher_amount' 	=> str_replace( ",", "",$claims['actual_expense']),
						'status' 			=> 3, // status initiated 
						'remark'			=>$claims['id'],
						'author' 			=>$user,
						'created' 			=>$this->_created,  
						'modified' 			=>$this->_modified,
					);
					$resultt = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);

					if($resultt)
					{
						$flow1=array(
							'flow' 				=> 2,
							'application' 		=> $resultt,
							'activity'			=>$location,
							'actor'   			=>5,
							'action' 			=> "3|4",
							'routing' 			=> 2,
							'status' 			=> 3, // status initiated 
							'routing_status'	=>2,
							'action_performed'	=>1,
							'description'		=>"Travel Claim",
							'author' 			=>$this->_author,
							'created' 			=>$this->_created,  
							'modified' 			=>$this->_modified,
						);
						$flow1=$this->getDefinedTable(Administration\FlowTransactionTable::class)->save($flow1);

						$subheaddetails=$this->getDefinedTable(Hr\ClaimSubheadTable::class)->get(array('claim'=>$claims['id']));
						foreach($subheaddetails as $rows):
							if( $rows['credit']>0){
								$tdetailsdata = array(
									'transaction' => $resultt,
									'voucher_dates' => $claims['date'],
									'voucher_types' => 11,
									'location' => $location,
									'reconcile' =>0,
									'against' =>0,
									'head' =>$rows['head'],
									'sub_head' =>$rows['subhead'],
									'bank_ref_type' => '',
									'debit' =>'0.00',
									'credit' =>$rows['credit'],
									'ref_no'=> $rows['ref_no'], 
									'type' => '1',//user inputted  data  
									'status' => 3, // status appied
									'activity'=>$location,
									'author' =>$user,
									'created' =>$this->_created,
									'modified' =>$this->_modified,
								);
								$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
								$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
							}
							else{
								$tdetailsdata = array(
									'transaction' => $resultt,
									'voucher_dates' => $claims['date'],
									'voucher_types' => 11,
									'location' => $location,
									'head' =>$rows['head'],
									'reconcile' =>0,
									'against' =>0,
									'sub_head' =>$rows['subhead'],
									'bank_ref_type' => '',
									'debit' =>$rows['debit'],
									'credit' =>'0.00',
									'ref_no'=> $rows['ref_no'], 
									'type' => '1',//user inputted  data  
									'status' => 3, // status appied
									'activity'=>$location,
									'author' =>$user,
									'created' =>$this->_created,
									'modified' =>$this->_modified,
								);
								$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
								$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
							}
						endforeach;
							$data = array(
								'id'		=> $claim_id,				
								'transaction' 	=> $resultt,			
								'modified'  => $this->_modified
							);
							$data = $this->_safedataObj->rteSafe($data);
							$re = $this->getDefinedTable(Hr\TAClaimTable::class)->save($data);
					}
						$message = "Successfully Forwarded Travel Claim";
						$br = "Travel Claim Forwarded"; 
						$notification_data = array(
							'route'         => 'transaction',
							'action'        => 'viewcredit',
							'key' 		    => $resultt,
							'description'   => $br,
							'author'	    => $this->_author,
							'created'       => $this->_created,
							'modified'      => $this->_modified,   
						);
						//print_r($notification_data);exit;
						$notificationResult = $this->getDefinedTable(Acl\NotificationTable::class)->save($notification_data);
						//echo $notificationResult; exit;
						if($notificationResult > 0 ){	
							
								$user = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>'5'));
								foreach($user as $row):						    
									$user_location_id = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($row['id'], 'location');
									if($user_location_id == $sourceLocation ):						
										$notify_data = array(
											'notification' => $notificationResult,
											'user'    	   => $row['id'],
											'flag'    	 => '0',
											'desc'    	 => $br,
											'author'	 => $this->_author,
											'created'    => $this->_created,
											'modified'   => $this->_modified,  
										);
										//print_r($notify_data);exit;
										$notifyResult = $this->getDefinedTable(Acl\NotifyTable::class)->save($notify_data);
									endif;
								endforeach;
							}
						
					
			}
			if($app_result):
				$activity_data = array(
						'process'      => $process_id,
						'process_id'   => $claim_id,
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
							'application'   => $claim_id,
							'activity'      => $next_activity_no,
							'actor'         => $privilege['route_to_role'],
							'status'        => $privilege['status_changed_to'],
							'action'        => $privilege['action'],
							'routing'       => $flow['actor'],
							'routing_status'=> $flow['status'],
							'description'   => $remark,
							'author'        => $this->_author,
							'created'       => $this->_created,
							'modified'      => $this->_modified
						);
						$flow_data = $this->_safedataObj->rteSafe($flow_data);
						$flow_result = $this->getDefinedTable(Administration\FlowTransactionTable::class)->save($flow_data);
						if($flow_result > 0):
							$this->notifyclaim($claim_id,$privilege['id'],$remark,$flow_result);
							$this->getDefinedTable(Administration\FlowTransactionTable::class)->performed($flow_id);
							$this->_connection->commit();
							$this->flashMessenger()->addMessage("success^ Successfully performed application action <strong>".$action_performed."</strong>!");
						else:
							$this->_connection->rollback();
							$this->flashMessenger()->addMessage("error^ Failed to update application work flow for <strong>".$action_performed."</strong> action.");
						endif;
				else:
					$this->_connection->rollback(); 
					$this->flashMessenger()->addMessage("error^ Failed to register the application in activity log.");
				endif;
			else:
				$this->_connection->rollback(); 
				$this->flashMessenger()->addMessage("error^ Failed to update application status for forward action.");
			endif;
			return $this->redirect()->toRoute('travel', array('action'=>'viewclaim', 'id' => $claim_id));
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
			'claimObj'      	 => $this->getDefinedTable(Hr\TAClaimTable::class),
			'empObj'      	 => $this->getDefinedTable(Hr\EmployeeTable::class),
			'flowprivilegeObj'   => $this->getDefinedTable(Administration\FlowPrivilegeTable::class),
			'flowtransactionObj' => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'      => $this->getDefinedTable(Administration\FlowActionTable::class),
			'roleObj'            => $this->getDefinedTable(Acl\RolesTable::class),
			'focals' => $this->getDefinedTable(Administration\UsersTable::class)->getNotIn('0','employee'),
			'focalsObj' 		=> $this->getDefinedTable(Administration\UsersTable::class),
			'employeeObj'        => $this->getDefinedTable(Hr\EmployeeTable::class), 
			'activityObj'        => $this->getDefinedTable(Administration\ActivityTable::class),   
		));
		$viewModel->setTerminal(true);
        return $viewModel;		
	}
	/**
	 * Notification Action for travel claim
	 */
	public function notifyclaim($calim_id,$privilege_id,$remarks = NULL,$flow_result)
	{
		$userlists='';
		$applications = $this->getDefinedTable(Hr\TAClaimTable::class)->get($calim_id);
		foreach($applications as $app);
		$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get($privilege_id);
		$emp=$this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($app['employee'],'full_name');
		foreach ($privileges as $flow) {
			$notify_msg = $emp." - ".$flow['description']."<br>[".$remarks."]";
			$notification_data = array(
				'route'         => 'travel',
				'action'        => 'viewclaim',
				'key' 		    => $calim_id,
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
	public function getexpenseAction()
	{
		$form = $this->getRequest()->getPost();
		$taid =$form['taId'];
		$talist=$this->getDefinedTable(Hr\TATable::class)->get($taid);
		foreach($talist as $ta);
		$transaction=$this->getDefinedTable(Accounts\TransactionTable::class)->get($ta['transaction']);
		if(!empty($transaction)):
			foreach($transaction as $trans);
			$advance= $trans['voucher_amount'];
		else:
			$advance=0.00;
		endif;
		
		foreach($talist as $row):
			//$advance=$row['advance'];
			$estimated_expense=$row['estimated_expense'];
			if($row['type']==1){
			$sub_head = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>203));
			}
			else{
				$sub_head = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>204));
			}
				$subheadlist ="<option value=''></option>";
			foreach($sub_head as $row):
				$subheadlist .="<option value='".$row['id']."'>".$row['name']."</option>";
			endforeach;
		endforeach;
		echo json_encode(array(
			'advance' => $advance,
			'estimated_expense' => $estimated_expense,
			'subheadlist'=>$subheadlist ,
		));
		exit;
	}
	public function getsubheadAction()
	{
		$form = $this->getRequest()->getPost();
		$headid =$form['headId'];
		$sub_head = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>$headid));
		
		$subheads ="<option value='-1'>select</option>";
			foreach($sub_head as $row):
				$subheads .="<option value='".$row['id']."'>".$row['name']."</option>";
			endforeach;
		
		echo json_encode(array(
			'subheads'=>$subheads ,
		));
		exit;
	}
	
}




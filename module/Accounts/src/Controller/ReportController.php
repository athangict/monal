<?php
namespace Accounts\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Administration\Model As Administration;
use Acl\Model As Acl;
use Accounts\Model As Accounts;
use Hr\Model As Hr;
class ReportController extends AbstractActionController
{   
	protected $_connection;
	protected $_safedataObj;
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
		
		if(!isset($this->_config)){
			$this->_config = $this->_container->get('Config');
		}
		if(!isset($this->_user)){
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
	
	/**TRIAL BALANCE ACTION----------------------------------------------------------------------------------------------------*/
	public function trialbalanceAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$tier = 4;
			$activity = $form['location'];//$form['activity'];removed
			$region = $form['region'];
            $location = $form['location'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
		else:
			$tier=4;
			$activity='-1';
			$region = '-1';
			$location = '-1';
			$start_date  = date('Y-01-01');
			$end_date   = date('Y-m-d');
		endif;
		$data = array(
		    'tier'     => $tier,
			'activity' => $activity,
			'region' => $region,
			'location' => $location,
			'start_date' => $start_date,
			'end_date'  => $end_date,
		);
		$existingPeriodSnapshot = $this->getDefinedTable(Accounts\PeriodsnapshotTable::class)->getByPeriod($end_date, $region, $location);
		$hasExistingCloseSnapshot = !empty($existingPeriodSnapshot);
		$canForceReclose = $this->hasPrivilegedMonthlyCloseRole();
		$role=explode(",", (string) ($this->_login_role ?? ''));//Multiple Role
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if(in_array($this->_login_role,array(100,99,8,6,2,17,5,7))):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		$ViewModel = new ViewModel(array(
			'classObj' => $this->getDefinedTable(Accounts\ClassTable::class),
			'groupObj' => $this->getDefinedTable(Accounts\GroupTable::class),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
			'headtypeObj' => $this->getDefinedTable(Accounts\HeadtypeTable::class),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'transactiondetailObj' => $this->getDefinedTable(Accounts\TransactiondetailTable::class),
			'data' => $data,
			'region' => $regions,
			'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
			'userID' => $this->_author,
		));
		return $ViewModel;
	}
	/** BALANCE SHEET ACTION-------------------------------------------------------------------------------------------------- */
	public function balancesheetAction(){
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$tier = 4;
			$activity = $form['location'];
			$region = $form['region'];
            $location = $form['location'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
		else:
			$tier=4;
			$activity='-1';
			$region = '-1';
			$location = '-1';
			$start_date  = date('Y-m-d');
			$end_date   = date('Y-m-d');
		endif;
		$data = array(
		    'tier'     => $tier,
			'activity' => $activity,
			'region' => $region,
			'location' => $location,
			'start_date' => $start_date,
			'end_date'  => $end_date,
		);
		$pre_starting_date = date('Y-m-d', strtotime('-1 year', strtotime($start_date)));
		$pre_ending_date = date('Y-m-d', strtotime('-1 year', strtotime($end_date)));
		$retainedCurrent = $this->getRetainedEarningsValue($activity, $region, $location, $start_date, $end_date);
		$retainedPrevious = $this->getRetainedEarningsValue($activity, $region, $location, $pre_starting_date, $pre_ending_date);
		$role=explode(",", (string) ($this->_login_role ?? ''));//Multiple Role
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if(in_array($this->_login_role,array(100,99,8,6,2,17,5,7))):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		$ViewModel = new ViewModel(array(
			'classObj' => $this->getDefinedTable(Accounts\ClassTable::class),
			'groupObj' => $this->getDefinedTable(Accounts\GroupTable::class),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'transactiondetailObj' => $this->getDefinedTable(Accounts\TransactiondetailTable::class),
			'data' => $data,
			'region' => $regions,
			'minDate' => $this->getDefinedTable(Accounts\TransactionTable::class)->getMin('voucher_date'),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
			'retainedCurrent' => $retainedCurrent,
			'retainedPrevious' => $retainedPrevious,
			'userID' => $this->_author,
		));
		return $ViewModel; 
	}
    /**PROFIT LOSS ACTION-------------------------------------------------------------------------------------------------------------*/
	public function profitlossAction(){
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			if(isset($form['run_monthly_close']) && (int)$form['run_monthly_close'] === 1):
				$region = (isset($form['region']) && $form['region'] !== '') ? (int)$form['region'] : -1;
				$location = (isset($form['location']) && $form['location'] !== '') ? (int)$form['location'] : -1;
				$periodStart = isset($form['period_start']) ? $form['period_start'] : date('Y-m-01');
				$periodEnd = isset($form['period_end']) ? $form['period_end'] : date('Y-m-t');
				$forceReclose = (isset($form['force_reclose']) && (int)$form['force_reclose'] === 1) ? 1 : 0;
				$closeNote = isset($form['close_note']) ? trim((string)$form['close_note']) : '';
				$this->runMonthlyCloseSnapshot($periodStart, $periodEnd, $region, $location, $forceReclose, $closeNote);
				$tier = 4;
				$activity = ($location > 0) ? $location : -1;
				$start_date = $periodStart;
				$end_date = $periodEnd;
			else:
				$tier = 4;
				$activity = $form['location'];
				$region = $form['region'];
	            $location = $form['location'];
				$start_date = $form['start_date'];
				$end_date = $form['end_date'];
			endif;
		else:
			$tier=4;
			$activity='-1';
			$region = '-1';
			$location = '-1';
			$start_date  = date('Y-01-01');
			$end_date   = date('Y-m-d');
		endif;
		$data = array(
		    'tier'     => $tier,
			'activity' => $activity,
			'region' => $region,
			'location' => $location,
			'start_date' => $start_date,
			'end_date'  => $end_date,
		);
		$existingPeriodSnapshot = $this->getDefinedTable(Accounts\PeriodsnapshotTable::class)->getByPeriod($end_date, $region, $location);
		$hasExistingCloseSnapshot = !empty($existingPeriodSnapshot);
		$canForceReclose = $this->hasPrivilegedMonthlyCloseRole();
		$role=explode(",", (string) ($this->_login_role ?? ''));//Multiple Role
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if(in_array($this->_login_role,array(100,99,8,6,2,17,5,7))):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		$ViewModel = new ViewModel(array(
			'classObj' => $this->getDefinedTable(Accounts\ClassTable::class),
			'groupObj' => $this->getDefinedTable(Accounts\GroupTable::class),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'transactiondetailObj' => $this->getDefinedTable(Accounts\TransactiondetailTable::class),
			'data' => $data,
			'region' => $regions,
			'minDate' => $this->getDefinedTable(Accounts\TransactionTable::class)->getMin('voucher_date'),
			'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
			'canForceReclose' => $canForceReclose,
			'hasExistingCloseSnapshot' => $hasExistingCloseSnapshot,
			'userID' => $this->_author,
		));
		return $ViewModel;
	}
	/**PROFIT LOSS FOR AUDIT ACTION-------------------------------------------------------------------------------------------------------------*/
	/*public function plauditAction(){
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$tier = $form['tier'];
			$activity = $form['activity'];
			$region = $form['region'];
            $location = $form['location'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
		else:
			$tier=0;
			$activity=0;
			$region = 0;
			$location = 0;
			$start_date  = date('Y-01-01');
			$end_date   = date('Y-m-d');
		endif;
		$data = array(
		    'tier'     => $tier,
			'activity' => $activity,
			'region' => $region,
			'location' => $location,
			'start_date' => $start_date,
			'end_date'  => $end_date,
		);
		$ViewModel = new ViewModel(array(
			'classObj' => $this->getDefinedTable(Accounts\ClassTable::class),
			'groupObj' => $this->getDefinedTable(Accounts\GroupTable::class),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'transactiondetailObj' => $this->getDefinedTable(Accounts\TransactiondetailTable::class),
			'headauditObj' => $this->getDefinedTable(Accounts\HeadAuditTable::class),
			'data' => $data,
			'minDate' => $this->getDefinedTable(Accounts\TransactionTable::class)->getMin('voucher_date'),
			'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
            //'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
			'userID' => $this->_author,
		));
		return $ViewModel;
	}*/
	/**GET CASH ACCOUNT ACTION*******************************************************************************************************/
	public function getcashaccountAction()
	{
		$this->init();
		$lc='';
		$form = $this->getRequest()->getPost();
		$location_id = $form['location'];
		$cashaccount = $this->getDefinedTable(Accounts\CashaccountTable::class)->getca(array('location' => $location_id));
		
		$lc.="<option value='-1'>All</option>";
		foreach($cashaccount as $ca):
			$lc.= "<option value='".$ca['id']."'>".$ca['cash_account_code'].'-'.$ca['cash_account_name']."</option>";
		endforeach;
		echo json_encode(array(
			'ca' => $lc,
		));
		exit;
	}
	/**CASH BOOK ACTION-----------------------------------------------------------------------------------------------------------------*/
	public function cashbookAction(){
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
			$cash_details = $form['cash_details'];
		}else{
			$location = 0;
			$start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
			$cash_details =0;
		}
		$loc = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($location, 'location');
		return new ViewModel(array(
			'title' => "Cash Book for -".$loc." from ".$start_date." till ".$end_date,
			'location_id' => $location,
			'start_date'=> $start_date,
			'end_date' => $end_date,
			'cash_detail' => $cash_details,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'transactionObj' => $this->getDefinedTable(Accounts\TransactionTable::class),
			'transactiondetailObj'=> $this->getDefinedTable(Accounts\TransactiondetailTable::class),
			'journalObj' => $this->getDefinedTable(Accounts\JournalTable::class),
			'headObj'=> $this->getDefinedTable(Accounts\HeadTable::class),
			'subheadObj'=> $this->getDefinedTable(Accounts\SubheadTable::class),
			'cashAccountObj'  => $this->getDefinedTable(Accounts\CashaccountTable::class),
			'closingObj'  => $this->getDefinedTable(Accounts\ClosingbalanceTable::class),
			//'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
			'userID' => $this->_author,
		));
	}
	/**GET BANK ACCOUNT ACTION *****************************************************************************************************/
	public function getbankAccAction()
	{
		$this->init();
		$lc='';
		$form = $this->getRequest()->getPost();
		$location_id = $form['location'];
		$bankacc = $this->getDefinedTable(Accounts\BankaccountTable::class)->getba(array('location' => $location_id));
		$lc.="<option value='-1'>All</option>";
		foreach($bankacc as $ba):
			$lc .= "<option value='" . $ba['id'] . "'>" . $ba['code'] . "-" . $ba['account'] . "</option>";
		endforeach;
		echo json_encode(array(
			'ba' => $lc,
		));
		exit;
	}
	/**BANK BOOK ACTION0---------------------------------------------------------------------------------------------------------------- */
	public function bankbookAction(){
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$bank_account = $form['bank_account'];
			$basubhead=$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(['ref_id'=>$bank_account,'type'=>3],'id');
			//echo '<pre>';print_r($basubhead);exit;
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
		else:
			$basubhead = '-1';
		    $start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
		endif;
		$data = array(
			'bank_account' => $basubhead,
			'start_date' => $start_date,
			'end_date'  => $end_date,
		);
		//echo '<pre>';print_r($bank_account);
		//echo '<pre>';print_r($data);exit;
		return new ViewModel(array(
			'title' => "Bank Book",
			'data' => $data,
			'bank_account' =>$basubhead,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'transactionObj' => $this->getDefinedTable(Accounts\TransactionTable::class),
			'transactiondetailObj'=> $this->getDefinedTable(Accounts\TransactiondetailTable::class),
			'subheadObj'=> $this->getDefinedTable(Accounts\SubheadTable::class),
			'bankaccObj'=> $this->getDefinedTable(Accounts\BankaccountTable::class),
			'closingObj'  => $this->getDefinedTable(Accounts\ClosingbalanceTable::class),
			// 'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
			'userID' => $this->_author,
		));
	}
	 /**
	 * get bank account by location
	 */
	public function getbankaccountAction(){
		$this->init();
		$baccs='';
		$form = $this->getRequest()->getpost();			
		$locationID =$form['location_id'];
				
		$baccs.="<option value=''></option>";
		$baccounts = $this->getDefinedTable(Accounts\BankaccountTable::class)->get(array('ba.location'=>$locationID));
		
		foreach($baccounts as $ba):
			$baccs .="<option value='".$ba['id']."'>".$ba['account']."</option>";
		endforeach;			
		echo json_encode(array(
			'bankacc' => $baccs,
		));
	    exit;
	}
	/**GET LEDGER & SUB_LEDGER ACTION--------------------------------------------------------------------------------------------- */
	public function ledgerAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
			$activity = $form['location'];//$form['activity'];
			$head = $form['head'];
			$sub_head = $form['sub_head'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
            $location_details = $form['location_details'];
		else:
			$location='-1';
			$activity='-1';
			$head='-1';
			$sub_head='-1';
			$location_details = '';
			$start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
		endif;
		$data = array(
			'location' => $location,
			'activity' => $activity,
			'head' => $head,
			'sub_head' => $sub_head,
			'start_date' => $start_date,
			'end_date' => $end_date,
		);
		$group_id = $this->getDefinedTable(Accounts\HeadTable::class)->getColumn($head,'group');
		$class_id = $this->getDefinedTable(Accounts\GroupTable::class)->getColumn($group_id,'class');
		$role=explode(",", (string) ($this->_login_role ?? ''));//Multiple Role
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if(in_array($this->_login_role,array(100,99,8,6,2,17,5,7))):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		$ViewModel =  new ViewModel(array(
			'title' => "Ledger & Sub-Ledger",
			'data' => $data,
			'region' => $regions,
			'class' => $class_id,
			'location_details' => $location_details,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'transactionObj' => $this->getDefinedTable(Accounts\TransactionTable::class),
			'journalObj' => $this->getDefinedTable(Accounts\JournalTable::class),
			'transactiondetailObj'=> $this->getDefinedTable(Accounts\TransactiondetailTable::class),
			'closingbalanceObj'=> $this->getDefinedTable(Accounts\ClosingbalanceTable::class),
		    //'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
			'userID' => $this->_author,
		));
		return $ViewModel; 
	}
	
    /**GENERAL LEDGER -ANNEXTURE------------------------------------------------------------------------------------------------------ */
	public function annextureAction()
	{   
	    $this->init();
        $location_details = '';
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$head = $form['head'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
			$location = $form['location'];
            $location_details = $form['location_details'];
		else:
			$head = '-1';
			$location = '-1';
			$start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
		endif;
		$data = array(
			'head' => $head,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'location' =>$location,
		);
		$group_id = $this->getDefinedTable(Accounts\HeadTable::class)->getColumn($head,'group');
		$class_id = $this->getDefinedTable(Accounts\GroupTable::class)->getColumn($group_id,'class');
		$role=explode(",", (string) ($this->_login_role ?? ''));//Multiple Role
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if(in_array($this->_login_role,array(100,99,8,6,2,17,5,7))):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		$ViewModel =  new ViewModel(array(
            'location_details' => $location_details,
			'headObj'    => $this->getDefinedTable(Accounts\HeadTable::class),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'data'       => $data,
			'region'       =>$regions,
			'class'      => $class_id,
			'transactiondetailObj' => $this->getDefinedTable(Accounts\TransactiondetailTable::class),
            'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'userID'     => $this->_author,
		));
		return $ViewModel; 
	}
	
    /**CASH & BANK ------------------------------------------------------------------------------------------------------ */
	public function cashbankAction()
	{   
	    $this->init();
        $location_details = '';
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
			$location = $form['location'];
            $location_details = $form['location_details'];
		else:
			$location = '-1';
			$start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
		endif;
		$data = array(
			'start_date' => $start_date,
			'end_date' => $end_date,
			'location' =>$location,
		);
		$role=explode(",", (string) ($this->_login_role ?? ''));//Multiple Role
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if(in_array($this->_login_role,array(100,99,8,6,2,17,5,7))):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		$ViewModel =  new ViewModel(array(
            'location_details' => $location_details,
			'headObj'    => $this->getDefinedTable(Accounts\HeadTable::class),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'data'       => $data,
			'region'       =>$regions,
			'transactiondetailObj' => $this->getDefinedTable(Accounts\TransactiondetailTable::class),
            'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'userID'     => $this->_author,
		));
		return $ViewModel; 
	}
    /** VOUCHER CHECK ACTION--------------------------------------------------------------------------------------------------------- */
	public function vouchercheckAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
			$vouchertypes = $form['vouchertype'];
		else:
		    $vouchertypes='-1';
			$location = '-1';
			$start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
		endif;
		$data = array(
		    'journal' =>$vouchertypes,
			'location' => $location,
			'start_date' => date('Y-m-d',strtotime($start_date)),
			'end_date' => date('Y-m-d',strtotime($end_date)),
		);
		$role=explode(",", (string) ($this->_login_role ?? ''));//Multiple Role
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if(in_array($this->_login_role,array(100,99,8,6,2,17,5,7))):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		$ViewModel =  new ViewModel(array(
			'title'          => "Voucher Checking",
			'data'           => $data,
			'region'         => $regions,
			'locationObj'    => $this->getDefinedTable(Administration\LocationTable::class),
			'transactionObj' => $this->getDefinedTable(Accounts\TransactionTable::class),
			'journalObj'     => $this->getDefinedTable(Accounts\JournalTable::class),
		));
		return $ViewModel; 
	}
	/**GET SUBHEAD ACCORDING TO HEAD****************************************************************************************************/
	public function getsubheadAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		$head_id = $form['head'];
		$subheads = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('head'=>$head_id));
		$sub_heads .="<option value='-1'>All</option>";
		foreach($subheads as $subhead):
			$sub_heads .="<option value='".$subhead['id']."'>".$subhead['code']."</option>";
		endforeach;
		echo json_encode(array(
			'subhead' => $sub_heads,
		));
		exit;
	}
	/** RECONCILATION-----------------------------------------------------------------------------------------------------*/
	public function addreconcilationAction(){
		$this->init();
		$year=0;
		$min_year=0;
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$transdtls_id = $form['transactiondtl_id'];
			$trans_date = $form['reconcile_date'];
			$trans_subhead = $form['sub_head'];
			if(!empty($form['reconcile'])){
				$reconcile =1;
			}else{
				$reconcile =0;
			}
			$data = array(
			   'id'  => $transdtls_id,
			   'reconcile' =>$reconcile,
			   'reconcile_date' => $trans_date[$i],
			   'author' =>$this->_author,					
			   'modified' =>$this->_modified,
			);
			$result = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($data);			
			if($result > 0 ){
				$this->flashMessenger()->addMessage('success^ Successfully Reconciled !');
				$this->redirect()->toRoute('report', array('action' => 'reconcilationlist'));	
			}	
		endif; 
		$today_date   = date('Y-m-d');
		return new ViewModel(array(
			'title'  => ' Bank Reconcilation',
			'todaydate'=>$today_date,
			'transObj' => $this->getDefinedTable(Accounts\TransactiondetailTable::class),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),	

   		));
	}
	/**-- TRANSACTIONS NOT RECONCILED----------------------------------------------------------------------*/
	public function transactionlistAction()
	{
		$this->init();		
		$param = explode('-', (string) $this->_id);
		$head = $param['0'];
		$login_id=$this->_login_id;
		$user_location = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($login_id,'admin_location');
		$ViewModel = new ViewModel(array(
			'head'       => $head,
			'user_location'  =>$user_location,
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),	
			'transactiondetailsObj' => $this->getDefinedTable(Accounts\TransactiondetailTable::class),	
			'cashaccountObj' => $this->getDefinedTable(Accounts\CashaccountTable::class),			
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),	
			'bankaccountObj' => $this->getDefinedTable(Accounts\BankaccountTable::class),
			'partyObj'  => $this->getDefinedTable(Accounts\PartyTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**---------------------------RECONCILATION------------------------------------------------------------- */
	/**  reconcilation action */
	public function reconcilationlistAction()
	{
		$this->init();
		$start_date = $this->params()->fromRoute('start_date', date('Y-m-d')); // Default to today's date
        $end_date = $this->params()->fromRoute('end_date', date('Y-m-d')); // Default to today's date
        $bank = $this->params()->fromRoute('bank', '-1'); // Default to -1 (All banks)
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
			$reconcile =$form['reconcile'];
			if($form['bank']!='-1'){
			    $bank_ref=$this->getDefinedTable(Accounts\SubheadTable::class)->getbanks(array('ref_id'=>$form['bank'],'type'=>3));
				foreach($bank_ref as $subheadbank);
				if(!empty($subheadbank)){
				   $bank=$subheadbank['id'];
				}else{
					$bank=$form['bank'];
				}
			}else{
				$bank=$form['bank'];
			}
			$banksel=$form['bank'];
		}else{
			$start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
			$bank = '-1';
			$banksel = '-1';
		}	
		$data = array(
			'start_date' => $start_date,
			'end_date' => $end_date,
            'bank' =>$bank,	
            'bankselected' =>$banksel,				
		);
		$transTable = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->getforreconcile($data);
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($transTable));
		$page = 1;
		if ($this->params()->fromRoute('page')) {
			$page = $this->params()->fromRoute('page');
		}
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(100000);
		$paginator->setPageRange(8);
		$user_id=$this->_login_id;
		$user_role=$this->_login_role;
		$location=$this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
		//echo '<pre>';print_r($location);exit;
		return new ViewModel(array(
			'title'         =>'RECONCILATION',
			'user'          =>$user_id,
			'role'          =>$user_role,
			'paginator'     =>$paginator,
			'page'          => $page,
			'data'          =>$data,
			'transactiondetailObj'=> $this->getDefinedTable(Accounts\TransactiondetailTable::class),
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'voucherObj'    =>$this->getDefinedTable(Accounts\JournalTable::class),
			'location'       =>$location,
			'regionObj'     =>$this->getDefinedTable(Administration\RegionTable::class),
			'subheadObj'     =>$this->getDefinedTable(Accounts\SubheadTable::class),
			'bankObj'         =>$this->getDefinedTable(Accounts\BankaccountTable::class),
			'transactionObj' =>$this->getDefinedTable(Accounts\TransactionTable::class),
   		));
	}
	/** BANK RECONCILE */
	public function reconcilationAction()
    {
        $this->init();
	    $array_id = explode("_", (string) $this->_id);
		$startDate = isset($array_id[2])?$array_id[2]:'';
		$endDate = isset($array_id[3])?$array_id[3]:'';
		$bank = isset($array_id[4])?$array_id[4]:'';
		//echo '<pre>';print_r($startDate);exit;
        if ($this->getRequest()->isPost()) {
            $form = $this->getRequest()->getPost();
			$date = date('ym',strtotime($form['voucher_dates']));
			if(!empty($form['reconcile'])){
				$reconcile=1;
			}else{
				$reconcile=0;
			}
            $data = array(
                'id' => $this->_id,
				'reconcile' =>$reconcile,
				'reconcile_date' =>$form['voucher_dates'],
				'author' =>$this->_author,					
				'modified' =>$this->_modified,
            );
            //echo '<pre>';print_r($data);exit;
            //$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($data);
			if($result > 0 ){
				$this->flashMessenger()->addMessage('success^ Successfully Reconciled!');
				$this->redirect()->toRoute('report', [
					'action' => 'reconcilationlist',
					'start_date' => $startDate,
					'end_date' => $endDate,
					'bank' => $bank
				]);	
			}
        }
        $ViewModel = new ViewModel(array(
			'title'  => ' Bank Reconcilation',
			'transObj' => $this->getDefinedTable(Accounts\TransactiondetailTable::class),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),	
			'headtype' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
			'types'	=> $this->getDefinedTable(Accounts\TypeTable::class)->getAll(),
			'voucherObj'    =>$this->getDefinedTable(Accounts\JournalTable::class),
			'locationObj'   =>$this->getDefinedTable(Administration\LocationTable::class),
			'regionObj'     =>$this->getDefinedTable(Administration\RegionTable::class),
			'subheadObj'     =>$this->getDefinedTable(Accounts\SubheadTable::class),
			'tdetailsObj' => $this->getDefinedTable(Accounts\TransactiondetailTable::class),
			'bankings' => $this->getDefinedTable(Accounts\TransactiondetailTable::class)->get($this->_id),
			'tdetails' => $this->getDefinedTable(Accounts\TransactiondetailTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
    }
	/**---------------------------SDR REPORT------------------------------------------------------------- */
	/**  sdr action */
	public function sdrAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
			$ledger = $form['ledger'];
			$subledger = $form['subledger'];
		}else{
            $start_date =  date('Y-m-d');
			$end_date =  date('Y-m-d');
			$ledger ='-1';
			$subledger ='-1';
		}	
		$data = array(
			'start_date' => $start_date,
			'end_date' => $end_date,
			'ledger' => $ledger,
			'subledger' => $subledger,
		);
		//echo '<pre>';print_r($data);exit;
		$transTable = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->getbyhead($data['ledger'],$data['subledger'],$start_date,$end_date);
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($transTable));
		//echo '<pre>';print_r($paginator);exit;
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10000);
		$paginator->setPageRange(8);
		
		return new ViewModel(array(
			'title'         => 'debit',
			'paginator'     => $paginator,
			'page'          => $page,
			'data'          => $data,
			'headObj'       =>$this->getDefinedTable(Accounts\HeadTable::class),
			'subheadObj'    =>$this->getDefinedTable(Accounts\SubheadTable::class),
   		));
	}
	 /**--VIEW COST PROFIT REPORT POST OFFICE WISE FORECASTING ------------------------------------------------------------------------------------------*/
    public function costprofitAction()
	{
		$this->init();		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$tier = $form['tier'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
			$region = $form['region'];
            $loc = $form['location'];
		else:
		    $tier =0;
			$start_date = date('Y-m-d');
			$end_date = date('Y-m-d');
			$region = '-1';
            $loc = '-1';
		endif;
		$data = array(
		    'tier'   => $tier,
		    'start_date'=>$start_date,
			'end_date'=>$end_date,
			'region' => $region,
			'location' => $loc,
		);
		//echo '<pre>';print_r($data);exit;
		return new ViewModel(array(
			'title'  => 'Cost Profit Report',
			'data' =>$data,
			'tdetailsObj' => $this->getDefinedTable(Accounts\TransactiondetailTable::class),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
			'groupObj' => $this->getDefinedTable(Accounts\GroupTable::class),
			'classObj' => $this->getDefinedTable(Accounts\ClassTable::class), 
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
   		));
	} 
	public function fetchreportlevelAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		$report = isset($form['report']) ? $form['report'] : '';
		$level = isset($form['level']) ? $form['level'] : '';
		$parentId = isset($form['parent_id']) ? (int)$form['parent_id'] : 0;

		$filters = array(
			'activity' => isset($form['activity']) ? $form['activity'] : '-1',
			'region' => isset($form['region']) ? $form['region'] : '-1',
			'location' => isset($form['location']) ? $form['location'] : '-1',
			'start_date' => isset($form['start_date']) ? $form['start_date'] : date('Y-01-01'),
			'end_date' => isset($form['end_date']) ? $form['end_date'] : date('Y-m-d'),
		);

		if($parentId <= 0 || empty($report) || empty($level)):
			echo json_encode(array('success' => 0, 'rows' => ''));
			exit;
		endif;

		$rows = '';
		if($report == 'trialbalance'):
			$rows = $this->buildTrialBalanceLazyRows($level, $parentId, $filters);
		elseif($report == 'balancesheet'):
			$rows = $this->buildBalanceSheetLazyRows($level, $parentId, $filters);
		elseif($report == 'profitloss'):
			$rows = $this->buildProfitLossLazyRows($level, $parentId, $filters);
		endif;

		echo json_encode(array('success' => 1, 'rows' => $rows));
		exit;
	}

	private function buildTrialBalanceLazyRows($level, $parentId, $filters)
	{
		$rows = '';
		$activity = $filters['activity'];
		$region = $filters['region'];
		$location = $filters['location'];
		$startDate = $filters['start_date'];
		$endDate = $filters['end_date'];

		$transactiondetailObj = $this->getDefinedTable(Accounts\TransactiondetailTable::class);
		$groupObj = $this->getDefinedTable(Accounts\GroupTable::class);
		$headObj = $this->getDefinedTable(Accounts\HeadTable::class);
		$subheadObj = $this->getDefinedTable(Accounts\SubheadTable::class);

		if($level == 'group'):
			foreach($groupObj->getTransactionGroup($activity, $region, $location, $startDate, $endDate, array('class' => $parentId)) as $grouprow):
				$total_debit = $transactiondetailObj->getSumbyGroup($activity,$region,$location,$startDate,$endDate,'debit',$grouprow['id']);
				$total_credit = $transactiondetailObj->getSumbyGroup($activity,$region,$location,$startDate,$endDate,'credit',$grouprow['id']);

				if($grouprow['class'] == '1' || $grouprow['class'] == '2'):
					$opening_balance = $transactiondetailObj->getOpeningBalance($activity,$region,$location,$startDate,$endDate,$grouprow['id'],3);
					$closing_balance = $transactiondetailObj->getClosingBalanceAL($activity,$region,$location,$startDate,$endDate,$grouprow['id'],3);
				else:
					$opening_balance = 0;
					$closing_balance = $transactiondetailObj->getClosingBalanceIE($activity,$region,$location,$startDate,$endDate,$grouprow['id'],3);
				endif;

				$cr_or_dr_ob = ($opening_balance == '' || $opening_balance == '0') ? '' : (($opening_balance < 0) ? 'Cr' : 'Dr');
				$cr_or_dr_cb = ($closing_balance == '' || $closing_balance == '0') ? '' : (($closing_balance < 0) ? 'Cr' : 'Dr');
				if($opening_balance < 0): $opening_balance = -$opening_balance; endif;
				if($closing_balance < 0): $closing_balance = -$closing_balance; endif;

				if(($opening_balance!=0) || ($closing_balance!=0) || ($total_debit!=0) || ($total_credit!=0)):
					$rows .= '<tr class="info tb-row tb-group" data-level="group" data-node="group-'.$grouprow['id'].'" data-parent="class-'.$parentId.'">'
						.'<td>&nbsp;&nbsp;&nbsp;<a href="#" class="tb-toggle" data-node="group-'.$grouprow['id'].'"><span class="tb-caret">+</span> '.$this->esc($grouprow['name']).'</a></td>'
						.'<td style="text-align:right">'.$this->fmt($opening_balance).'</td>'
						.'<td>'.$cr_or_dr_ob.'</td>'
						.'<td style="text-align:right">'.$this->fmt($total_debit).'</td>'
						.'<td style="text-align:right">'.$this->fmt($total_credit).'</td>'
						.'<td style="text-align:right">'.$this->fmt($closing_balance).'</td>'
						.'<td>'.$cr_or_dr_cb.'</td>'
						.'</tr>';
				endif;
			endforeach;
		elseif($level == 'head'):
			foreach($headObj->getTransactionHead($activity,$region,$location,$startDate,$endDate,array('group'=>$parentId)) as $headrow):
				$total_debit = $transactiondetailObj->getSumbyHead($activity,$region,$location,$startDate,$endDate,'debit',$headrow['id']);
				$total_credit = $transactiondetailObj->getSumbyHead($activity,$region,$location,$startDate,$endDate,'credit',$headrow['id']);
				$class_id = $groupObj->getColumn(array('id'=>$headrow['group'],'class'=>array(1,2)),'class');

				if($class_id == '1' || $class_id == '2'):
					$opening_balance = $transactiondetailObj->getOpeningBalance($activity,$region,$location,$startDate,$endDate,$headrow['id'],2);
					$closing_balance = $transactiondetailObj->getClosingBalanceAL($activity,$region,$location,$startDate,$endDate,$headrow['id'],2);
				else:
					$opening_balance = 0;
					$closing_balance = $transactiondetailObj->getClosingBalanceIE($activity,$region,$location,$startDate,$endDate,$headrow['id'],2);
				endif;

				$cr_or_dr_ob = ($opening_balance == '' || $opening_balance == '0') ? '' : (($opening_balance < 0) ? 'Cr' : 'Dr');
				$cr_or_dr_cb = ($closing_balance == '' || $closing_balance == '0') ? '' : (($closing_balance < 0) ? 'Cr' : 'Dr');
				if($opening_balance < 0): $opening_balance = -$opening_balance; endif;
				if($closing_balance < 0): $closing_balance = -$closing_balance; endif;

				if(($opening_balance!=0) || ($closing_balance!=0) || ($total_debit!=0) || ($total_credit!=0)):
					$rows .= '<tr class="success tb-row tb-head" data-level="head" data-node="head-'.$headrow['id'].'" data-parent="group-'.$parentId.'">'
						.'<td class="">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" class="tb-toggle" data-node="head-'.$headrow['id'].'"><span class="tb-caret">+</span> '.$this->esc($headObj->getColumn($headrow['id'],'code')).'</a></td>'
						.'<td style="text-align:right">'.$this->fmt($opening_balance).'</td>'
						.'<td>'.$cr_or_dr_ob.'</td>'
						.'<td style="text-align:right">'.$this->fmt($total_debit).'</td>'
						.'<td style="text-align:right">'.$this->fmt($total_credit).'</td>'
						.'<td style="text-align:right">'.$this->fmt($closing_balance).'</td>'
						.'<td>'.$cr_or_dr_cb.'</td>'
						.'</tr>';
				endif;
			endforeach;
		elseif($level == 'subhead'):
			foreach($subheadObj->getTransactionSubhead($activity,$region,$location,$startDate,$endDate,array('head'=>$parentId)) as $subheadrow):
				$total_debit = $transactiondetailObj->getSumbySubhead($activity,$region,$location,$startDate,$endDate,'debit',$subheadrow['id']);
				$total_credit = $transactiondetailObj->getSumbySubhead($activity,$region,$location,$startDate,$endDate,'credit',$subheadrow['id']);
				$head_id = $subheadObj->getColumn($subheadrow['id'],'head');
				$group_id = $headObj->getColumn($head_id,'group');
				$class_id = $groupObj->getColumn($group_id,'class');

				if($class_id == '1' || $class_id == '2'):
					$opening_balance = $transactiondetailObj->getOpeningBalance($activity,$region,$location,$startDate,$endDate,$subheadrow['id'],1);
					$closing_balance = $transactiondetailObj->getClosingBalanceAL($activity,$region,$location,$startDate,$endDate,$subheadrow['id'],1);
				else:
					$opening_balance = 0;
					$closing_balance = $transactiondetailObj->getClosingBalanceIE($activity,$region,$location,$startDate,$endDate,$subheadrow['id'],1);
				endif;

				$cr_or_dr_ob = ($opening_balance == '' || $opening_balance == '0') ? '' : (($opening_balance < 0) ? 'Cr' : 'Dr');
				$cr_or_dr_cb = ($closing_balance == '' || $closing_balance == '0') ? '' : (($closing_balance < 0) ? 'Cr' : 'Dr');
				if($opening_balance < 0): $opening_balance = -$opening_balance; endif;
				if($closing_balance < 0): $closing_balance = -$closing_balance; endif;

				if(($opening_balance!=0) || ($closing_balance!=0) || ($total_debit!=0) || ($total_credit!=0)):
					$rows .= '<tr class="subheadrow tb-row tb-subhead" data-level="subhead" data-parent="head-'.$parentId.'">'
						.'<td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'.$this->esc($subheadrow['code'].'-'.$subheadrow['name']).'</td>'
						.'<td style="text-align:right">'.$this->fmt($opening_balance).'</td>'
						.'<td>'.$cr_or_dr_ob.'</td>'
						.'<td style="text-align:right">'.$this->fmt($total_debit).'</td>'
						.'<td style="text-align:right">'.$this->fmt($total_credit).'</td>'
						.'<td style="text-align:right">'.$this->fmt($closing_balance).'</td>'
						.'<td>'.$cr_or_dr_cb.'</td>'
						.'</tr>';
				endif;
			endforeach;
		endif;

		return $rows;
	}

	private function buildBalanceSheetLazyRows($level, $parentId, $filters)
	{
		$rows = '';
		$activity = $filters['activity'];
		$region = $filters['region'];
		$location = $filters['location'];
		$startDate = $filters['start_date'];
		$endDate = $filters['end_date'];
		list($pre_starting_date, $pre_ending_date) = $this->previousPeriod($startDate, $endDate);

		$transactiondetailObj = $this->getDefinedTable(Accounts\TransactiondetailTable::class);
		$groupObj = $this->getDefinedTable(Accounts\GroupTable::class);
		$headObj = $this->getDefinedTable(Accounts\HeadTable::class);
		$subheadObj = $this->getDefinedTable(Accounts\SubheadTable::class);

		if($level == 'group'):
			foreach($groupObj->getTransactionGroupforBS($activity,$region,$location,$startDate,$endDate,array('class'=>$parentId)) as $grouprow):
				$class_id = $groupObj->getColumn($grouprow['id'],'class');
				$pres = $transactiondetailObj->getClosingBalanceforPresBS($activity,$region,$location,$startDate,$endDate,$grouprow['id'],$class_id,3);
				$prev = $transactiondetailObj->getClosingBalanceforPrevBS($activity,$region,$location,$pre_starting_date,$pre_ending_date,$grouprow['id'],$class_id,3);
				$rows .= '<tr class="grouprow tb-row tb-group" data-level="group" data-node="group-'.$grouprow['id'].'" data-parent="class-'.$parentId.'">'
					.'<td class="text-success"><strong><a href="#" class="tb-toggle" data-node="group-'.$grouprow['id'].'"><span class="tb-caret">+</span> '.$this->esc($grouprow['name']).'</a></strong></td>'
					.'<td class="text-success" style="text-align:right"><strong>'.number_format((float)$pres,2,'.',',').'</strong></td>'
					.'<td class="text-success" style="text-align:right"><strong>'.number_format((float)$prev,2,'.',',').'</strong></td>'
					.'</tr>';
			endforeach;
		elseif($level == 'head'):
			foreach($headObj->getTransactionHeadforBS($activity,$region,$location,$startDate,$endDate,array('group'=>$parentId)) as $headrow):
				$group_id = $headObj->getColumn($headrow['id'],'group');
				$class_id = $groupObj->getColumn($group_id,'class');
				$pres = $transactiondetailObj->getClosingBalanceforPresBS($activity,$region,$location,$startDate,$endDate,$headrow['id'],$class_id,2);
				$prev = $transactiondetailObj->getClosingBalanceforPrevBS($activity,$region,$location,$pre_starting_date,$pre_ending_date,$headrow['id'],2);
				$rows .= '<tr class="headrow tb-row tb-head" data-level="head" data-node="head-'.$headrow['id'].'" data-parent="group-'.$parentId.'">'
					.'<td class="text-secondary"><strong><a href="#" class="tb-toggle" data-node="head-'.$headrow['id'].'"><span class="tb-caret">+</span> '.$this->esc($headObj->getColumn($headrow['id'],'name')).'</a></strong></td>'
					.'<td style="text-align:right"><strong>'.number_format((float)$pres,2,'.',',').'</strong></td>'
					.'<td style="text-align:right"><strong>'.number_format((float)$prev,2,'.',',').'</strong></td>'
					.'</tr>';
			endforeach;
		elseif($level == 'subhead'):
			foreach($subheadObj->getTransactionSubheadforBS($activity,$region,$location,$startDate,$endDate,array('head'=>$parentId)) as $subheadrow):
				$head_id = $subheadObj->getColumn($subheadrow['id'],'head');
				$group_id = $headObj->getColumn($head_id,'group');
				$class_id = $groupObj->getColumn($group_id,'class');
				$pres = $transactiondetailObj->getClosingBalanceforPresBS($activity,$region,$location,$startDate,$endDate,$subheadrow['id'],$class_id,1);
				$prev = $transactiondetailObj->getClosingBalanceforPrevBS($activity,$region,$location,$pre_starting_date,$pre_ending_date,$subheadrow['id'],1);
				$rows .= '<tr class="subheadrow tb-row tb-subhead" data-level="subhead" data-parent="head-'.$parentId.'">'
					.'<td>&nbsp;&nbsp;&nbsp;&nbsp;'.$this->esc($subheadrow['code'].'-'.$subheadrow['name']).'</td>'
					.'<td style="text-align:right">'.number_format((float)$pres,2,'.',',').'</td>'
					.'<td style="text-align:right">'.number_format((float)$prev,2,'.',',').'</td>'
					.'</tr>';
			endforeach;
		endif;

		return $rows;
	}

	private function buildProfitLossLazyRows($level, $parentId, $filters)
	{
		$rows = '';
		$activity = $filters['activity'];
		$region = $filters['region'];
		$location = $filters['location'];
		$startDate = $filters['start_date'];
		$endDate = $filters['end_date'];
		list($pre_starting_date, $pre_ending_date) = $this->previousPeriod($startDate, $endDate);

		$transactiondetailObj = $this->getDefinedTable(Accounts\TransactiondetailTable::class);
		$groupObj = $this->getDefinedTable(Accounts\GroupTable::class);
		$headObj = $this->getDefinedTable(Accounts\HeadTable::class);
		$subheadObj = $this->getDefinedTable(Accounts\SubheadTable::class);

		if($level == 'group'):
			foreach($groupObj->getTransactionGroup($activity,$region,$location,$startDate,$endDate,array('class'=>$parentId)) as $grouprow):
				$class_id = $groupObj->getColumn($grouprow['id'],'class');
				$pres = $transactiondetailObj->getClosingBalanceforPresPLSCLASS($activity,$region,$location,$startDate,$endDate,$grouprow['id'],$class_id,3);
				$prev = $transactiondetailObj->getClosingBalanceforPrevPLS($activity,$region,$location,$pre_starting_date,$pre_ending_date,$grouprow['id'],3);
				$rows .= '<tr class="info tb-row tb-group" data-level="group" data-node="group-'.$grouprow['id'].'" data-parent="class-'.$parentId.'">'
					.'<td class="text-danger">&nbsp;<a href="#" class="tb-toggle" data-node="group-'.$grouprow['id'].'"><span class="tb-caret">+</span> '.$this->esc($grouprow['name']).'</a></td>'
					.'<td style="text-align:right">'.number_format((float)$pres,2,'.',',').'</td>'
					.'<td style="text-align:right">'.number_format((float)$prev,2,'.',',').'</td>'
					.'</tr>';
			endforeach;
		elseif($level == 'head'):
			foreach($headObj->getTransactionHead($activity,$region,$location,$startDate,$endDate,array('group'=>$parentId)) as $headrow):
				$group_id = $headObj->getColumn($headrow['id'],'group');
				$class_id = $groupObj->getColumn($group_id,'class');
				$pres = $transactiondetailObj->getClosingBalanceforPresPLSCLASS($activity,$region,$location,$startDate,$endDate,$headrow['id'],$class_id,2);
				$prev = $transactiondetailObj->getClosingBalanceforPrevPLS($activity,$region,$location,$pre_starting_date,$pre_ending_date,$headrow['id'],2);
				$rows .= '<tr class="success tb-row tb-head" data-level="head" data-node="head-'.$headrow['id'].'" data-parent="group-'.$parentId.'">'
					.'<td class="text-success">&nbsp;&nbsp;<a href="#" class="tb-toggle" data-node="head-'.$headrow['id'].'"><span class="tb-caret">+</span> '.$this->esc($headObj->getColumn($headrow['id'],'name')).'</a></td>'
					.'<td style="text-align:right">'.number_format((float)$pres,2,'.',',').'</td>'
					.'<td style="text-align:right">'.number_format((float)$prev,2,'.',',').'</td>'
					.'</tr>';
			endforeach;
		elseif($level == 'subhead'):
			foreach($subheadObj->getTransactionSubhead($activity,$region,$location,$startDate,$endDate,array('head'=>$parentId)) as $subheadrow):
				$head_id = $subheadObj->getColumn($subheadrow['id'],'head');
				$group_id = $headObj->getColumn($head_id,'group');
				$class_id = $groupObj->getColumn($group_id,'class');
				$pres = $transactiondetailObj->getClosingBalanceforPresPLSCLASS($activity,$region,$location,$startDate,$endDate,$subheadrow['id'],$class_id,1);
				$prev = $transactiondetailObj->getClosingBalanceforPrevPLS($activity,$region,$location,$pre_starting_date,$pre_ending_date,$subheadrow['id'],1);
				$rows .= '<tr class="subheadrow tb-row tb-subhead" data-level="subhead" data-parent="head-'.$parentId.'">'
					.'<td>&nbsp;&nbsp;&nbsp;&nbsp;'.$this->esc($subheadrow['code'].'-'.$subheadrow['name']).'</td>'
					.'<td style="text-align:right">'.number_format((float)$pres,2,'.',',').'</td>'
					.'<td style="text-align:right">'.number_format((float)$prev,2,'.',',').'</td>'
					.'</tr>';
			endforeach;
		endif;

		return $rows;
	}

	private function previousPeriod($startDate, $endDate)
	{
		$pre_starting_date = date('Y-m-d', strtotime('-1 year', strtotime($startDate)));
		$pre_ending_date = date('Y-m-d', strtotime('-1 year', strtotime($endDate)));
		return array($pre_starting_date, $pre_ending_date);
	}

	private function fmt($number)
	{
		return number_format((float)$number, 2, '.', ',');
	}

	private function esc($value)
	{
		return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	}

	/**GET TDS REPORT ACTION *****************************************************************************************************/
	public function tdsreportAction()
	{   
	    $this->init();
        $location_details = '';
		$taxationTypeId = $this->getTaxationTypeIdForReport();
		$taxationOptions = array();
		$defaultSubHead = '';
		$taxationRows = $this->getDefinedTable(Accounts\TaxationTable::class)->getAll();
		foreach($taxationRows as $taxationRow):
			$taxationSubheads = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.ref_id' => $taxationRow['id'], 'sh.type' => $taxationTypeId));
			if(empty($taxationSubheads)):
				continue;
			endif;
			$taxationSubHeadId = (string)$taxationSubheads[0]['id'];
			$taxationCode = isset($taxationRow['class']) ? trim((string)$taxationRow['class']) : '';
			$taxationName = isset($taxationRow['taxation']) ? trim((string)$taxationRow['taxation']) : '';
			$taxationLabel = ($taxationCode !== '') ? ($taxationName.' ('.$taxationCode.')') : $taxationName;
			$reportMode = (strtoupper($taxationCode) === 'CA-TDS') ? 'all' : 'standard';
			$taxationOptions[] = array(
				'sub_head' => $taxationSubHeadId,
				'taxation' => $taxationName,
				'code' => $taxationCode,
				'label' => $taxationLabel,
				'report_mode' => $reportMode,
			);
			if($defaultSubHead === ''):
				$defaultSubHead = $taxationSubHeadId;
			endif;
		endforeach;
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
            $subhead = $form['sub_head'];
		else:
			$subhead = $defaultSubHead;
			$start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
		endif;
		$selectedTaxationLabel = '';
		$selectedTaxationMode = 'standard';
		$taxationSubheadMap = array();
		foreach($taxationOptions as $taxationOption):
			$taxationSubheadMap[$taxationOption['sub_head']] = $taxationOption;
		endforeach;
		$isTaxationSelection = isset($taxationSubheadMap[(string)$subhead]);
		if(!$isTaxationSelection && $defaultSubHead !== '' && isset($taxationSubheadMap[$defaultSubHead])):
			$subhead = $defaultSubHead;
			$isTaxationSelection = true;
		endif;
		if($isTaxationSelection):
			$selectedTaxationLabel = $taxationSubheadMap[(string)$subhead]['label'];
			$selectedTaxationMode = $taxationSubheadMap[(string)$subhead]['report_mode'];
		endif;
		$data = array(
			'start_date' => $start_date,
			'end_date' => $end_date,
			'subhead' =>$subhead,
			'taxation_mode' => $selectedTaxationMode,
		);
		//echo '<pre>';print_r($data);exit;
		$ViewModel =  new ViewModel(array(
            'sub_head' => $subhead,
			'taxationOptions' => $taxationOptions,
			'isTaxationSelection' => $isTaxationSelection,
			'selectedTaxationLabel' => $selectedTaxationLabel,
			'selectedTaxationMode' => $selectedTaxationMode,
			'headObj'    => $this->getDefinedTable(Accounts\HeadTable::class),
			'partyObj'   => $this->getDefinedTable(Accounts\PartyTable::class),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'data'       => $data,
			'transactiondetailObj' => $this->getDefinedTable(Accounts\TransactiondetailTable::class),
			'transactionObj' => $this->getDefinedTable(Accounts\TransactionTable::class),
            'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'userID'     => $this->_author,
			'partyroleObj'=>$this->getDefinedTable(Accounts\PartyroleTable::class),
		));
		return $ViewModel; 
	}

	private function getTaxationTypeIdForReport()
	{
		$typeRows = $this->getDefinedTable(Accounts\TypeTable::class)->get(array('type' => 'Taxation'));
		if(!empty($typeRows) && isset($typeRows[0]['id'])):
			return (int)$typeRows[0]['id'];
		endif;
		return 0;
	}

	private function getNetProfitForPeriod($activity, $region, $location, $startDate, $endDate)
	{
		$netProfit = 0.0;
		$profitLossClasses = $this->getDefinedTable(Accounts\ClassTable::class)->getProfitlossClass($activity, $region, $location, $startDate, $endDate);
		foreach($profitLossClasses as $classRow):
			$classId = isset($classRow['id']) ? $classRow['id'] : 0;
			if($classId > 0):
				$netProfit += (float)$this->getDefinedTable(Accounts\TransactiondetailTable::class)->getClosingBalanceforPresPLSCLASS(
					$activity,
					$region,
					$location,
					$startDate,
					$endDate,
					$classId,
					$classId,
					4
				);
			endif;
		endforeach;
		return $netProfit;
	}

	private function getRetainedEarningsValue($activity, $region, $location, $startDate, $endDate)
	{
		$periodSnapshot = $this->getDefinedTable(Accounts\PeriodsnapshotTable::class)->getByPeriod($endDate, $region, $location);
		if(!empty($periodSnapshot) && isset($periodSnapshot['retained_earnings'])):
			return (float)$periodSnapshot['retained_earnings'];
		endif;
		$openingRetained = 0.0;
		$latestSnapshot = $this->getDefinedTable(Accounts\PeriodsnapshotTable::class)->getLatestBefore($startDate, $region, $location);
		if(!empty($latestSnapshot) && isset($latestSnapshot['retained_earnings'])):
			$openingRetained = (float)$latestSnapshot['retained_earnings'];
		endif;
		$periodNetProfit = $this->getNetProfitForPeriod($activity, $region, $location, $startDate, $endDate);
		return $openingRetained + $periodNetProfit;
	}

	private function hasPrivilegedMonthlyCloseRole()
	{
		$privilegedRoles = array('100','99');
		$roles = explode(',', (string)$this->_login_role);
		foreach($roles as $role):
			if(in_array(trim($role), $privilegedRoles, true)):
				return true;
			endif;
		endforeach;
		return in_array((string)$this->_login_role, $privilegedRoles, true);
	}

	private function runMonthlyCloseSnapshot($periodStart, $periodEnd, $region, $location, $forceReclose = 0, $closeNote = '')
	{
		$activity = ($location > 0) ? $location : -1;
		$existingSnapshot = $this->getDefinedTable(Accounts\PeriodsnapshotTable::class)->getByPeriod($periodEnd, $region, $location);
		$isAdmin = $this->hasPrivilegedMonthlyCloseRole();

		if(!empty($existingSnapshot) && (int)$forceReclose !== 1):
			$this->flashMessenger()->addMessage('Failed^ This period is already closed. Use Force Re-close (admin) to recompute.');
			return;
		endif;
		if((int)$forceReclose === 1 && !$isAdmin):
			$this->flashMessenger()->addMessage('Failed^ You do not have permission to force re-close.');
			return;
		endif;

		$connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();
		$connection->beginTransaction();
		try{
			$openingRetained = 0.0;
			$latestSnapshot = $this->getDefinedTable(Accounts\PeriodsnapshotTable::class)->getLatestBefore($periodStart, $region, $location);
			if(!empty($latestSnapshot) && isset($latestSnapshot['retained_earnings'])):
				$openingRetained = (float)$latestSnapshot['retained_earnings'];
			endif;
			$periodNetProfit = $this->getNetProfitForPeriod($activity, $region, $location, $periodStart, $periodEnd);
			$retainedEarnings = $openingRetained + $periodNetProfit;
			$snapshotData = array(
				'period_end' => $periodEnd,
				'start_date' => $periodStart,
				'region' => $region,
				'location' => $location,
				'net_profit' => $periodNetProfit,
				'retained_earnings' => $retainedEarnings,
				'author' => $this->_author,
				'modified' => $this->_modified,
			);
			if(!empty($existingSnapshot) && isset($existingSnapshot['id'])):
				$snapshotData['id'] = $existingSnapshot['id'];
			else:
				$snapshotData['created'] = $this->_created;
			endif;
			$result = $this->getDefinedTable(Accounts\PeriodsnapshotTable::class)->save($snapshotData);
			if($result > 0):
				$runNo = $this->getDefinedTable(Accounts\PeriodsnapshotlogTable::class)->getMaxRunNo($periodEnd, $region, $location) + 1;
				$logData = array(
					'period_end' => $periodEnd,
					'start_date' => $periodStart,
					'region' => $region,
					'location' => $location,
					'action' => (!empty($existingSnapshot) ? 'RECLOSE' : 'CLOSE'),
					'run_no' => $runNo,
					'force_reclose' => (int)$forceReclose,
					'note' => $closeNote,
					'net_profit' => $periodNetProfit,
					'retained_earnings' => $retainedEarnings,
					'author' => $this->_author,
					'created' => $this->_created,
				);
				$this->getDefinedTable(Accounts\PeriodsnapshotlogTable::class)->save($logData);
				$connection->commit();
				$this->flashMessenger()->addMessage('success^ Monthly close snapshot saved successfully.');
			else:
				$connection->rollback();
				$this->flashMessenger()->addMessage('Failed^ Failed to save monthly close snapshot.');
			endif;
		}catch(\Exception $e){
			$connection->rollback();
			throw $e;
		}
	}
}

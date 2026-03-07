<?php
namespace Pswf\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Administration\Model As Administration;
use Acl\Model As Acl;
use Pswf\Model As Pswf;

class ReportController extends AbstractActionController
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
			$tier = $form['tier'];
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
		//print_r($options); exit;
		$ViewModel = new ViewModel(array(
			'classObj' => $this->getDefinedTable(Pswf\ClassTable::class),
			'groupObj' => $this->getDefinedTable(Pswf\GroupTable::class),
			'headObj' => $this->getDefinedTable(Pswf\HeadTable::class),
			'headtypeObj' => $this->getDefinedTable(Pswf\HeadtypeTable::class),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'transactiondetailObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class),
			'data' => $data,
			'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
            // 'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
			'userID' => $this->_author,
		));
		return $ViewModel; 
	}	
	/** BALANCE SHEET ACTION-------------------------------------------------------------------------------------------------- */
	public function balancesheetAction(){
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$tier = $form['tier'];
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
		$ViewModel = new ViewModel(array(
			'classObj' => $this->getDefinedTable(Pswf\ClassTable::class),
			'groupObj' => $this->getDefinedTable(Pswf\GroupTable::class),
			'headObj' => $this->getDefinedTable(Pswf\HeadTable::class),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'transactiondetailObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class),
			'data' => $data,
			'minDate' => $this->getDefinedTable(Pswf\TransactionTable::class)->getMin('voucher_date'),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
            //'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
			'userID' => $this->_author,
		));
		return $ViewModel; 
	}
    /**PROFIT LOSS ACTION-------------------------------------------------------------------------------------------------------------*/
	public function profitlossAction(){
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$tier = $form['tier'];
			$activity = $form['location'];
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
			'classObj' => $this->getDefinedTable(Pswf\ClassTable::class),
			'groupObj' => $this->getDefinedTable(Pswf\GroupTable::class),
			'headObj' => $this->getDefinedTable(Pswf\HeadTable::class),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'transactiondetailObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class),
			'data' => $data,
			'minDate' => $this->getDefinedTable(Pswf\TransactionTable::class)->getMin('voucher_date'),
			'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
            //'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
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
			'classObj' => $this->getDefinedTable(Pswf\ClassTable::class),
			'groupObj' => $this->getDefinedTable(Pswf\GroupTable::class),
			'headObj' => $this->getDefinedTable(Pswf\HeadTable::class),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'transactiondetailObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class),
			'headauditObj' => $this->getDefinedTable(Pswf\HeadAuditTable::class),
			'data' => $data,
			'minDate' => $this->getDefinedTable(Pswf\TransactionTable::class)->getMin('voucher_date'),
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
		$cashaccount = $this->getDefinedTable(Pswf\CashaccountTable::class)->getca(array('location' => $location_id));
		
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
			'transactionObj' => $this->getDefinedTable(Pswf\TransactionTable::class),
			'transactiondetailObj'=> $this->getDefinedTable(Pswf\TransactiondetailTable::class),
			'journalObj' => $this->getDefinedTable(Pswf\JournalTable::class),
			'headObj'=> $this->getDefinedTable(Pswf\HeadTable::class),
			'subheadObj'=> $this->getDefinedTable(Pswf\SubheadTable::class),
			'cashAccountObj'  => $this->getDefinedTable(Pswf\CashaccountTable::class),
			'closingObj'  => $this->getDefinedTable(Pswf\ClosingbalanceTable::class),
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
		$bankacc = $this->getDefinedTable(Pswf\BankaccountTable::class)->getba(array('location' => $location_id));
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
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
		else:
			$bank_account = 0;
		    $start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
		endif;
		$data = array(
			'bank_account' => $bank_account,
			'start_date' => $start_date,
			'end_date'  => $end_date,
		);
		return new ViewModel(array(
			'title' => "Bank Book",
			'data' => $data,
			'bank_account' =>$bank_account,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'transactionObj' => $this->getDefinedTable(Pswf\TransactionTable::class),
			'transactiondetailObj'=> $this->getDefinedTable(Pswf\TransactiondetailTable::class),
			'subheadObj'=> $this->getDefinedTable(Pswf\SubheadTable::class),
			'bankaccObj'=> $this->getDefinedTable(Pswf\BankaccountTable::class),
			'closingObj'  => $this->getDefinedTable(Pswf\ClosingbalanceTable::class),
			// 'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
			'userID' => $this->_author,
		));
	}
	 /**
	 * get bank account by location
	 */
	public function getbankaccountAction(){
		$this->init();
		$form = $this->getRequest()->getpost();			
		$locationID = $form['location_id'];
				
		$subhead.="<option value=''></option>";
		$subheads = $this->getDefinedTable(Pswf\BankaccountTable::class)->get(array('ba.location'=>$locationID));
		
		foreach($subheads as $sub_head):
			$subhead .="<option value='".$sub_head['id']."'>".$sub_head['code']."</option>";
		endforeach;			
		echo json_encode(array(
			'subhead' => $subhead,
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
			$activity = '-1';//$form['activity'];
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
		$group_id = $this->getDefinedTable(Pswf\HeadTable::class)->getColumn($head,'group');
		$class_id = $this->getDefinedTable(Pswf\GroupTable::class)->getColumn($group_id,'class');
		$ViewModel =  new ViewModel(array(
			'title' => "Ledger & Sub-Ledger",
			'data' => $data,
			'class' => $class_id,
			'location_details' => $location_details,
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
			'headObj' => $this->getDefinedTable(Pswf\HeadTable::class),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'transactionObj' => $this->getDefinedTable(Pswf\TransactionTable::class),
			'journalObj' => $this->getDefinedTable(Pswf\JournalTable::class),
			'transactiondetailObj'=> $this->getDefinedTable(Pswf\TransactiondetailTable::class),
			'closingbalanceObj'=> $this->getDefinedTable(Pswf\ClosingbalanceTable::class),
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
		$group_id = $this->getDefinedTable(Pswf\HeadTable::class)->getColumn($head,'group');
		$class_id = $this->getDefinedTable(Pswf\GroupTable::class)->getColumn($group_id,'class');
		$ViewModel =  new ViewModel(array(
            'location_details' => $location_details,
			'headObj'    => $this->getDefinedTable(Pswf\HeadTable::class),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'data'       => $data,
			'class'      => $class_id,
			'transactiondetailObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class),
            // 'userRoleObj'=> $this->getDefinedTable(Acl\UserroleTable::class),
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
		$ViewModel =  new ViewModel(array(
			'data'           => $data,
			'locationObj'    => $this->getDefinedTable(Administration\LocationTable::class),
			'transactionObj' => $this->getDefinedTable(Pswf\TransactionTable::class),
			'journalObj'     => $this->getDefinedTable(Pswf\JournalTable::class),
		));
		return $ViewModel; 
	}
	/**GET SUBHEAD ACCORDING TO HEAD****************************************************************************************************/
	public function getsubheadAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		$head_id = $form['head'];
		$subheads = $this->getDefinedTable(Pswf\SubheadTable::class)->get(array('head'=>$head_id));
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
			//echo '<pre>'; print_r($data);
			$result = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($data);
              //endif; 				
			if($result > 0 ){
				$this->flashMessenger()->addMessage('success^ Successfully Reconciled !');
				$this->redirect()->toRoute('report', array('action' => 'reconcilationlist'));	
			}	
		endif; 
		//exit;
		$today_date   = date('Y-m-d');
		return new ViewModel(array(
			'title'  => ' Bank Reconcilation',
			'todaydate'=>$today_date,
			'transObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'headObj' => $this->getDefinedTable(Pswf\HeadTable::class),	

   		));
	}
	/**-- TRANSACTIONS NOT RECONCILED----------------------------------------------------------------------*/
	public function transactionlistAction()
	{
		$this->init();		
		$param = explode('-',$this->_id);
		$head = $param['0'];
		$login_id=$this->_login_id;
		$user_location = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($login_id,'admin_location');
		$ViewModel = new ViewModel(array(
			'head'       => $head,
			'user_location'  =>$user_location,
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'headObj' => $this->getDefinedTable(Pswf\HeadTable::class),	
			'transactiondetailsObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class),	
			'cashaccountObj' => $this->getDefinedTable(Pswf\CashaccountTable::class),			
			'headObj' => $this->getDefinedTable(Pswf\HeadTable::class),	
			'bankaccountObj' => $this->getDefinedTable(Pswf\BankaccountTable::class),
			'partyObj'  => $this->getDefinedTable(Pswf\PartyTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**---------------------------RECONCILATION------------------------------------------------------------- */
	/**  reconcilation action */
	public function reconcilationlistAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
			$reconcile =$form['reconcile'];
			if($form['bank']!='-1'){
			    $bank_ref=$this->getDefinedTable(Pswf\SubheadTable::class)->getbanks(array('ref_id'=>$form['bank'],'type'=>3));
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
			$location = '-1';
			$start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
			$reconcile = 1;
			$bank = '-1';
			$banksel = '-1';
		}	
		$data = array(
			'location'=>$location,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'reconcile' =>$reconcile,
            'bank' =>$bank,	
            'bankselected' =>$banksel,				
		);
	    //echo '<pre>';print_r($data);exit;
		$transTable = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->getforreconcile($data['location'],$data['start_date'],$data['end_date'],$data['reconcile'],$data['bank']);
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($transTable));
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10000);
		$paginator->setPageRange(8);
		$user_id=$this->_login_id;
		$user_role=$this->_login_role;
		return new ViewModel(array(
			'title'         =>'RECONCILATION',
			'user'          =>$user_id,
			'role'          =>$user_role,
			'paginator'     =>$paginator,
			'page'          => $page,
			'data'          =>$data,
			'transactiondetailObj'=> $this->getDefinedTable(Pswf\TransactiondetailTable::class),
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'voucherObj'    =>$this->getDefinedTable(Pswf\JournalTable::class),
			'locationObj'   =>$this->getDefinedTable(Administration\LocationTable::class),
			'regionObj'     =>$this->getDefinedTable(Administration\RegionTable::class),
			'subheadObj'     =>$this->getDefinedTable(Pswf\SubheadTable::class),
			'bankObj'         =>$this->getDefinedTable(Pswf\BankaccountTable::class),
   		));
	}
	/** BANK RECONCILE */
	public function reconcilationAction()
    {
        $this->init();
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
				'voucher_dates' => $form['voucher_dates'],
				'reconcile' =>$reconcile,
				'reconcile_date' =>date('Y-m-d'),
				'author' =>$this->_author,					
				'modified' =>$this->_modified,
            );
            //echo '<pre>';print_r($data);exit;
            $data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($data);
			if($result > 0 ){
				$this->flashMessenger()->addMessage('success^ Successfully Reconciled!');
				$this->redirect()->toRoute('report', array('action' => 'reconcilationlist'));	
			}
        }
        $ViewModel = new ViewModel(array(
			'title'  => ' Bank Reconcilation',
			'transObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'headObj' => $this->getDefinedTable(Pswf\HeadTable::class),	
			'headtype' => $this->getDefinedTable(Pswf\HeadtypeTable::class)->getAll(),
			'types'	=> $this->getDefinedTable(Pswf\TypeTable::class)->getAll(),
			'voucherObj'    =>$this->getDefinedTable(Pswf\JournalTable::class),
			'locationObj'   =>$this->getDefinedTable(Administration\LocationTable::class),
			'regionObj'     =>$this->getDefinedTable(Administration\RegionTable::class),
			'subheadObj'     =>$this->getDefinedTable(Pswf\SubheadTable::class),
			'tdetailsObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class),
			'bankings' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get($this->_id),
			'tdetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get($this->_id),
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
		$transTable = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->getbyhead($data['ledger'],$data['subledger'],$start_date,$end_date);
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
			'headObj'       =>$this->getDefinedTable(Pswf\HeadTable::class),
			'subheadObj'    =>$this->getDefinedTable(Pswf\SubheadTable::class),
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
			'tdetailsObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'headObj' => $this->getDefinedTable(Pswf\HeadTable::class),
			'groupObj' => $this->getDefinedTable(Pswf\GroupTable::class),
			'classObj' => $this->getDefinedTable(Pswf\ClassTable::class), 
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
   		));
	} 
}

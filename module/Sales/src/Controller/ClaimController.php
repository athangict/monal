<?php
namespace Sales\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Sales\Model As Sales;
use Administration\Model As Administration;
use Acl\Model As Acl;
use Accounts\Model As Accounts;
class ClaimController extends AbstractActionController
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
		//$this->_dir =realpath($fileManagerDir);

		$this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

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
	* claim action
	*/
	public function claimAction()
	{
		$this->init();
		$year = '';
		$month = '';
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
		}
		$month = ($month == 0)? date('m'):$month;
		$year = ($year == 0)? date('Y'):$year;
		
		$data = array(
				'year' => $year,
				'month' => $month,
		);
		return new ViewModel(array(
			'title'       => 'Claims for Sales',
			'claimObj'    => $this->getDefinedTable(Sales\ClaimTable::class),
			'statusObj'   => $this->getDefinedTable(Acl\StatusTable::class),
			'claimtypeObj'=> $this->getDefinedTable(Sales\ClaimTypeTable::class),
			'minYear' => $this->getDefinedTable(Sales\ClaimTable::class)->getMin('claim_date'),
			'data' => $data,
		));
	}

	/**
	* view claims action
	*/
	public function viewclaimAction()
	{
		$this->init();

		return new ViewModel(array(
			'claims'	   => $this->getDefinedTable(Sales\ClaimTable::class)->get($this->_id),
			'statusObj'    => $this->getDefinedTable(Acl\StatusTable::class),
			'claimtypeObj' => $this->getDefinedTable(Sales\ClaimTypeTable::class),
			'activityObj'  => $this->getDefinedTable(Administration\ActivityTable::class),
			'supplierObj'  => $this->getDefinedTable(Accounts\PartyTable::class),
			'subheadObj'   => $this->getDefinedTable(Accounts\SubheadTable::class),
			'locationObj'  => $this->getDefinedTable(Administration\LocationTable::class),
			'claimreturns' => $this->getDefinedTable(Sales\ClaimReturnTable::class)->get(array('claim'=>$this->_id)),
			'transactionObj' => $this->getDefinedTable(Accounts\TransactionTable::class),
		));
	}
	/**
	* add claim action
	*/
	public function addclaimAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();			
			$date = date('ym',strtotime($form['claim_date']));					
			$tmp_CLNo = "CL".$date; 			
			$results = $this->getDefinedTable(Sales\ClaimTable::class)->getMonthlyCL($tmp_CLNo);
			
			if(sizeof($results) < 1 ):
				$next_serial = "001";
		  	else:
			    $cl_no_list = array();
	            foreach($results as $result):
			        array_push($cl_no_list, substr($result['claim_no'], 8)); 
			    endforeach;
	            $next_serial = max($cl_no_list) + 1;
	        endif;    
			
			switch(strlen($next_serial)){
			    case 1: $next_cl_serial = "000".$next_serial;  break;
			    case 1: $next_cl_serial = "00".$next_serial;  break;
			    case 2: $next_cl_serial = "0".$next_serial;   break;
			   default: $next_cl_serial = $next_serial;       break; 
			}					   
			$claim_no = $tmp_CLNo.$next_cl_serial;
			$data = array(
					 'claim_no'     => $claim_no, 
					 'claim_type'   => $form['claim_type'],
					 'claim_date'   => $form['claim_date'],
					 'location'     => $form['location'],
					 'activity'     => $form['activity'],
					 'supplier'     => $form['supplier'],
					 'head'         => $form['head'],
					 'amount'       => $form['amount'],
					 'note'         => $form['note'],
					 'sub_head'     => $form['sub_head'],
					 'status' 		=> '1',
					 'author'       => $this->_author,
					 'created'      => $this->_created,
					 'modified'     => $this->_modified
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\ClaimTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Claims successfully added with CL No. :". $claim_no);
				return $this->redirect()->toRoute('claim', array('action' =>'viewclaim', 'id' => $result));
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new Claims");
				return $this->redirect()->toRoute('claim', array('action'=>'addclaim'));
			endif;		
		}	 

		return new ViewModel(array(
			'title'       => 'Add Claims',
			'claimtypeObj'=> $this->getDefinedTable(Sales\ClaimTypeTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'activities'  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'suppliers'	  => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'1')),
			'heads'	  => $this->getDefinedTable(Accounts\HeadTable::class)->getAll(),
		));
	}
	/*
	 * Get List of UOM
	* From Sub head Table
	*/
	public function getshbysupplierAction()
	{
		$this->init();

		$supplierID = $this->_id;

		$ViewModel = new ViewModel(array(
				'supplierID' => $supplierID,
				'subheads' => $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.type'=>'2', 'ref_id'=>$supplierID)),
		));
	
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Action for getting Account Subhead
	 */
	
	public function getsubheadAction()
	{		
		$form = $this->getRequest()->getPost();
		$headId = $form['headId'];
		$head_list = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('head'=>$headId));
		
		$subhead = "<option value=''></option>";
		foreach($head_list as $head_lists):
			$subhead.="<option value='".$head_lists['id']."'>".$head_lists['code']."</option>";
		endforeach;
		echo json_encode(array(
				'subhead' => $subhead,
		));
		exit;
	}
	/**
	* edit claims
	*/
	public function editclaimAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();	
			
			$data = array(
					 'id'			=> $form['cl_id'],
					 'claim_no'     => $form['claim_no'], 
					 'claim_type'   => $form['claim_type'],
					 'claim_date'   => $form['claim_date'],
					 'location'     => $form['location'],
					 'activity'     => $form['activity'],
					 'supplier'     => $form['supplier'],
					 'head'         => $form['head'],
					 'amount'       => $form['amount'],
					 'note'         => $form['note'],
					 'sub_head'     => $form['sub_head'],
					 'status' 		=> '2',
					 'author'       => $this->_author,
					 'modified'     => $this->_modified
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\ClaimTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Claims successfully edited with CL No. :". $form['claim_no']);
				return $this->redirect()->toRoute('claim', array('action' =>'viewclaim', 'id' => $result));
			else:
				$this->flashMessenger()->addMessage("error^ Failed to edit Claims");
				return $this->redirect()->toRoute('claim', array('action'=>'editclaim', 'id'=> $this->_id));
			endif;		
		}	

		return new ViewModel(array(
			'title'       => 'Edit Claims',
			'claims'	  => $this->getDefinedTable(Sales\ClaimTable::class)->get($this->_id),
			'claimtypeObj'=> $this->getDefinedTable(Sales\ClaimTypeTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
			'supplierObj' => $this->getDefinedTable(Accounts\PartyTable::class),
			'subheadsObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
		    'heads'	  => $this->getDefinedTable(Accounts\HeadTable::class)->getAll(),
		));
	}	
	
	/**
	 *  Booking claims
	 */
	public function bookingclaimAction(){
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$claim_id = $form['claim_id'];
			//generate voucher no
			    $loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
				$date = date('ym',strtotime($form['claim_date']));
				$tmp_VcNo = $loc.$prefix.$date;	
				$results = $this->getDefinedTable(Sales\ClaimTable::class)->getMonthlyCL($tmp_VcNo);
			
				$pltp_no_list = array();
				foreach($results as $result):
					array_push($pltp_no_list, substr($result['voucher_no'], 8));
				endforeach;
				$next_serial = max($pltp_no_list) + 1;
					
				switch(strlen($next_serial)){
					case 1: $next_cl_serial = "000".$next_serial; break;
					case 2: $next_cl_serial = "00".$next_serial;  break;
					case 3: $next_cl_serial = "0".$next_serial;   break;
					default: $next_cl_serial = $next_serial;       break;
				}
			$voucher_no = $tmp_VcNo.$next_cl_serial;
			$data1 = array(
					'voucher_date' => $form['voucher_date'],
					'voucher_type' => $form['voucher_type'],
					'doc_id' => $form['doc_id'],
					'doc_type' => $form['doc_type'],
					'voucher_no' => $voucher_no,
					'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
        			'remark' => $form['remark'],
        			'status' => 3, // 
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data1 = $this->_safedataObj->rteSafe($data1);
			$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
			if($result > 0){
				$location= $form['location'];
				$activity= $form['activity'];
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
							'head' => $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($sub_head[$i], $column='head'),
							'sub_head' => $sub_head[$i],
							'bank_ref_type' => '',
							'cheque_no' => $cheque_no[$i],
							'debit' => (isset($debit[$i]))? $debit[$i]:'0.000',
							'credit' => (isset($credit[$i]))? $credit[$i]:'0.000',
							'ref_no'=> '', 
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
					endif;
				endfor;
				$update_data1 = array(
						'id' => $claim_id,
						'transaction' => $result,
						'status' 		=> 3,
						'author'	    => $this->_author,
						'modified'      => $this->_modified,
					);
				$result1 = $this->getDefinedTable(Sales\ClaimTable::class)->save($update_data1);
				$this->flashMessenger()->addMessage("success^ booking claims successfully added | ".$voucher_no);
				return $this->redirect()->toRoute('claim', array('action' =>'viewclaim', 'id' => $claim_id));
			}
			else
			{
				$this->flashMessenger()->addMessage("Failed^ Failed to book claims");		
				return $this->redirect()->toRoute('claim');
			}
		}
		return new ViewModel(array(
				'title'		  => 'Booking Claims',
				'claims'	  => $this->getDefinedTable(Sales\ClaimTable::class)->get($this->_id),
				'claimtypeObj'=> $this->getDefinedTable(Sales\ClaimTypeTable::class),
		        'activities'  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
        	    'locations'   => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
        	    'regions' 	  => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
        	    'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
		        'journals'    => $this->getDefinedTable(Accounts\JournalTable::class)->getAll(),
		        'subheadObj'  => $this->getDefinedTable(Accounts\SubheadTable::class),
		        'heads'       => $this->getDefinedTable(Accounts\HeadTable::class)->getAll(),
				//'bank_ref_types'=> $this->getDefinedTable('Accounts\BankreftypeTable')->getAll(),
		));
	}
	/**
	 * cancel claims
	 * 
	 */
	public function cancelclaimAction(){
		$this->init();
		
		$claims = $this->getDefinedTable(Sales\ClaimTable::class)->get($this->_id);
		
		foreach ($claims as $claim):
			$data = array(
					'id'			=>$this->_id,
					'status' 		=> 4,
					'author'	    => $this->_author,
					'modified'      => $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\ClaimTable::class)->save($data);
			if($result):
			$this->flashMessenger()->addMessage("success^ Successfully cancelled Claims");
			return $this->redirect()->toRoute('claim');
			endif;
		endforeach;
	}

	/**
	* add return claims
	*/
	public function addreturnclaimAction()
	{
		$this->init();
		$claim_id = $this->_id;
		$tot_amount = $this->getDefinedTable(Sales\ClaimTable::class)->getColumn($claim_id, 'amount');
		$claimreturns = $this->getDefinedTable(Sales\ClaimReturnTable::class)->get(array('claim'=>$claim_id));
		foreach($claimreturns as $claimreturn):
			$tot_clAmt += $claimreturn['amount'];
		endforeach;	
		$left_clreturn = $tot_amount - $tot_clAmt;
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$cl_id = $form['claim_id'];
			$data = array(
					'claim' => $cl_id,
					'ref_no' => $form['ref_no'],
					'return_date' => $form['return_date'],
					'amount' => $form['amount'],
					'note' => $form['note'],
					'status' => '1',
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\ClaimReturnTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New return claim successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add return claims");
			endif;
		  return $this->redirect()->toRoute('claim', array('action'=>'viewclaim', 'id'=> $cl_id));
		}

		$ViewModel = new ViewModel(array(
			'claim_id'  => $claim_id,
			'left_clreturn' => $left_clreturn,

		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}

	/**
	* edit return claims
	*/
	public function editreturnclaimAction()
	{
		$this->init();

		$rtn_claim_id = $this->_id;
		$claimID = $this->getDefinedTable(Sales\ClaimReturnTable::class)->getColumn($rtn_claim_id, 'claim');
		$tot_amount = $this->getDefinedTable(Sales\ClaimTable::class)->getColumn($claimID, 'amount');
		$claimreturns = $this->getDefinedTable(Sales\ClaimReturnTable::class)->get(array('claim'=>$claimID, 'status'=>'3'));
		foreach($claimreturns as $claimreturn):
			$tot_clAmt += $claimreturn['amount'];
		endforeach;	
		$left_clreturn = $tot_amount - $tot_clAmt;
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$claim_id = $form['claim_id'];
			$data = array(
					'id' => $form['rtn_claim_id'],
					'claim' => $claim_id,
					'ref_no' => $form['ref_no'],
					'return_date' => $form['return_date'],
					'amount' => $form['amount'],
					'note' => $form['note'],
					'status' => '2',
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\ClaimReturnTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Return claim successfully edited");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to edit return claims");
			endif;
		  return $this->redirect()->toRoute('claim', array('action'=>'viewclaim', 'id'=> $claim_id));
		}

		$ViewModel = new ViewModel(array(
			'claimreturns' => $this->getDefinedTable(Sales\ClaimReturnTable::class)->get($rtn_claim_id),
			'left_clreturn' => $left_clreturn,
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/*
	*Delete Claims
	*/
	public function deletereturnclaimAction()
	{
		$this->init();

		$return_cl_id = $this->_id;
		$claim_id = $this->getDefinedTable(Sales\ClaimReturnTable::class)->getColumn($return_cl_id,'claim');
        $result = $this->getDefinedTable(Sales\ClaimReturnTable::class)->remove($return_cl_id);
    	if($result > 0):
		    $this->flashMessenger()->addMessage("success^ Return claims deleted");
		else:
		    $this->flashMessenger()->addMessage("notice^ Failed to delete return claims");
		endif;
        return $this->redirect()->toRoute('claim', array('action' => 'viewclaim', 'id' => $claim_id));
	}
	/**
	 *  send claims
	 */
	public function commitreturnclaimAction(){
		$this->init();
		$returnclaims = $this->getDefinedTable(Sales\ClaimReturnTable::class)->get($this->_id);
		
		foreach ($returnclaims as $returnclaim):
			$claim_id = $returnclaim['claim'];
			$data = array(
					'id'			=>$this->_id,
					'status' 		=> 3,
					'author'	    => $this->_author,
					'modified'      => $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\ClaimReturnTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully committed return Claims");
				return $this->redirect()->toRoute('claim', array('action'=>'viewclaim', 'id'=>$claim_id));
			endif;
		endforeach;
	}
	/**
	 *  Receive Return claims
	 */
	public function receivereturnclaimAction(){
		$this->init();
		$claim_id = $this->_id;
		$claims = $this->getDefinedTable(Sales\ClaimTable::class)->get($this->_id);
		$returnclaims = $this->getDefinedTable(Sales\ClaimReturnTable::class)->get(array('claim'=>$this->_id));
		$i = 0;
		foreach($returnclaims as $returnclaim):
			$rt_status = $returnclaim['status'];
			if($rt_status != 3):
				$i = 1;
			endif;
		endforeach;
		if($i == 0):
			foreach ($claims as $claim):
				$data = array(
						'id'			=>$this->_id,
						'status' 		=> 5,
						'author'	    => $this->_author,
						'modified'      => $this->_modified,
				);
				$data   = $this->_safedataObj->rteSafe($data);
				$result = $this->getDefinedTable(Sales\ClaimTable::class)->save($data);
				if($result):
					$this->flashMessenger()->addMessage("success^ Successfully Received return Claims");
					return $this->redirect()->toRoute('claim');
				endif;
			endforeach;
		else:
			$this->flashMessenger()->addMessage("error^ Failed to receive! Commit all return claims first to Receive All");
			return $this->redirect()->toRoute('claim', array('action' => 'viewclaim', 'id' => $claim_id));
		endif;
	}
}

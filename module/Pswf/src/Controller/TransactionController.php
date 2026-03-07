<?php
namespace Pswf\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use DOMPDFModule\View\Model\PdfModel;
use Interop\Container\ContainerInterface;
use Administration\Model As Administration;//
use Accounts\Model As Accounts;
use Acl\Model As Acl;
use Hr\Model As Hr;
use Fleet\Model As Fleet;
use Purchase\Model As Purchase;
use Pswf\Model As Pswf;
use Laminas\EventManager\EventManagerInterface;
class TransactionController extends AbstractActionController
{   
	private $_container;    // database table 
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
    protected $_safedataObj; //safedata controller plugin
	protected $_connection; //Transaction connection
    
	
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
	
	public function setEventManager(EventManagerInterface $events)
	{
		parent::setEventManager($events);
		$controller = $this;
		$events->attach('dispatch', function ($e) use ($controller) {
			$controller->layout('layout/reportlayout');
		}, 100);
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
		if(!isset($this->_login_location_type)){
			$this->_login_location_type = $this->_user->location_type; 
		}

		$this->_id = $this->params()->fromRoute('id');

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
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$date = $form['date'];
		}else{
            $month = date('m');
			$year =date('Y');
			$date =date('d');
		}	
		$data = array(
			'year' => $year,
			'month' => $month,
			'date' => $date,
		);
		
    	//work to get total no. of days, accord. to year n month  date('L', strtotime($data['year']))
		if($data['month'] > 7){
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 31; break;
			default:
				$maxdate = 30; break;
		}
		}else{
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 30; break;
			default:
				$maxdate = 31; break;
		}
		}
		$user_id=$this->_login_id;
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role==100||$this->_login_role==99):
		    $transTable = $this->getDefinedTable(Pswf\TransactionTable::class)->getDateWiseA('voucher_date',$year,$month,$date);
		else:
		    $transTable = $this->getDefinedTable(Pswf\TransactionTable::class)->getDateWise('voucher_date',$year,$month,$date,$user_region);
		endif;
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($transTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10000);
		$paginator->setPageRange(8);
		
		//echo $maxdate; exit;
		$user_id=$this->_login_id;
		//echo $user_id; exit;
		$user_role=$this->_login_role;
		return new ViewModel(array(
			'title'         => 'Journal Transaction',
			'user'          =>$user_id,
			'role'          =>$user_role,
			'paginator'     => $paginator,
			'page'          => $page,
			'data'          => $data,
			'maxdate'       => $maxdate,
			'voucherObj'    =>$this->getDefinedTable(Accounts\JournalTable::class),
   		));
	}
	/**
	 * CALCULATE THE SDR AMOUNT BASED ON KG/NOS
	**/
	public function calculatedAction()
	{
		$this->init();
		$amount='';
		$form = $this->getRequest()->getPost();
		
		$item_id =$form['item'];
		$weightRate =$form['weight'];
		$nosRate =$form['nos'];
		$weightAMT = $this->getDefinedTable(Pswf\ItemTable::class)->getColumn($item_id,'sdr_weight');
		$nosAMT = $this->getDefinedTable(Pswf\ItemTable::class)->getColumn($item_id,'sdr_nos');
		$amount= $weightAMT * $weightRate + $nosAMT * $nosRate;

		echo json_encode(array(
			'amountcalculated' => $amount,
		));
		exit;
	}
	/**
	 * @TRANSACTION CRUD----------------------------------------------
	 * @ADD Transaction[JOURNAL-PAYABLE/RECEIVEABLE]
	 */
	public function addtransactionAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$tmp_VCNo = $loc.'-'.$prefix.$date;
			$results = $this->getDefinedTable(Pswf\TransactionTable::class)->getSerial($tmp_VCNo);
			$pltp_no_list = array();
			foreach($results as $result):
				array_push($pltp_no_list, substr($result['voucher_no'], -5));
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
			$region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
			if(!empty($form['currency'])){
				$currency=$form['currency'];
				$rate=$form['rate'];
				$sdr_amount=$form['amount_usd'];
			}
			else{
				$currency=1;
				$rate=0.00;
				$sdr_amount=0.000;
			}
			$data1 = array(
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'region'   =>$region,
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				'voucher_no' => $voucher_no,
				'cheque_no' => $form['cheque_no1'],
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'remark' => $form['remark'],
				'status' => 2, // status initiated 
				'author' =>$this->_author,
				'created' =>$this->_created,  
				'modified' =>$this->_modified,
			);
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				$location= $form['location'];
				$sub_head= $form['sub_head'];
				$head= $form['head'];
				$cheque_no= $form['cheque_no']; 
				if($cheque_no!=NULL){
					$cheque_no=$cheque_no;
				}else{
					$cheque_no='0';
				}
				$debit= $form['debit'];
				$credit= $form['credit'];
				$reference= $form['reference'];
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						$tdetailsdata = array(
							'transaction' => $result,
							'voucher_dates' => $form['voucher_date'],
							'voucher_types' => $form['voucher_type'],
							'location' => $location[$i],
							'activity' => $location[$i],
							'currency' =>$currency,
							'against' =>0,
							'rate' =>$rate,
							'debit_sdr' =>$sdr_amount,
							'head' =>  $head[$i],
							'sub_head' => $sub_head[$i],
							'bank_ref_type' => '',
							'cheque_no' => $cheque_no[$i],
							'debit' => (isset($debit[$i]))? $debit[$i]:'0.000',
							'credit' => (isset($credit[$i]))? $credit[$i]:'0.000',
							'ref_no'=> $reference[$i], 
							'type' => '1',
							'status' => 2,
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);
						if($result1 <= 0):
							break;
						endif;
					endif;
				endfor;
				if($result1 > 0):
					$this->_connection->commit();
					$this->flashMessenger()->addMessage("success^ New Transaction successfully added | ".$voucher_no);
					return $this->redirect()->toRoute('ptransaction', array('action' =>'viewtransaction', 'id' => $result));
				else:
					$this->_connection->rollback();
					$this->flashMessenger()->addMessage("Failed^ Failed to add new Transaction");		
					return $this->redirect()->toRoute('ptransaction');
				endif;				
			}
			else
			{
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("Failed^ Failed to add new Transaction");		
				return $this->redirect()->toRoute('ptransaction');
			}
		}
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		$role=explode(",",$this->_login_role);//Multiple Role
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99 || $this->_login_role==8|| $this->_login_role==6|| $role==array(2,17)|| $role==array(5,6)):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		$user_admin_location=$this->getDefinedTable(Administration\UsersTable::class)->get($this->_login_id,'admin_location');
		$today_date   = date('Y-m-d');
		return new ViewModel(array(
			'title'  => 'Add Journal transaction',
			'todaydate' =>$today_date,
			'userlocation'=>$user_admin_location,
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'regions' => $regions,
			'currencies' => $this->getDefinedTable(Accounts\CurrencyTable::class)->getAll(),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Accounts\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->getAll(),
		));
	}
	/**
	 * Delete Journal Transaction Action
	 */
	public function deletejournalAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$voucher = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id,'voucher_type');
			$transactiondetails_id = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('td.transaction'=>$this->_id));
			foreach($transactiondetails_id as $transactiondetails_ids):
				$result = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->remove($transactiondetails_ids['id']);
			endforeach;
			if($result):
			$result2 = $this->getDefinedTable(Pswf\TransactionTable::class)->remove($this->_id);
			endif;
			if($result2 > 0):
				$this->flashMessenger()->addMessage("success^ successfully deleted  data");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to delete  data");
			endif;
			if($voucher==1):
				return $this->redirect()->toRoute('transaction', array('action'=>'index'));
			else:
				return $this->redirect()->toRoute('transaction', array('action'=>'index'));
			endif;
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Delete Journal',
			'trans'    =>$this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * @EDIT Transaction
	 */
	public function edittransactionAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($this->_user->location, 'location_code');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getColumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$serial = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
			preg_match('/([\d]+)/', $serial, $match );
			$serial = substr($match[0],4);
			$voucher_no = $loc.$prefix.$date.$serial;
			$created_author=$form['created_author'];
			$data1 = array(
				'id' => $this->_id,
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'cheque_no' => $form['cheque_no1'],
				'remark' => $form['remark'],
				'author'=>$created_author,
				'status' => 2,
				'modified' =>$this->_modified,
			);			
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				$tdetails_id = $form['id'];
				$location= $form['location'];
				$head= $form['head'];
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no1'];
				$debit= $form['debit'];
				$credit= $form['credit'];
				$reference= $form['reference'];
				$bank_ref_type='DFT2024';
				$delete_rows = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->getNotInDtl($tdetails_id, array('transaction' => $result));
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						if($tdetails_id[$i]>0):
							$tdetailsdata = array(
								'id' => $tdetails_id[$i],
								'transaction' => $result,
								'location' => $location[$i],
								'activity' => $location[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'bank_ref_type' => $bank_ref_type,
								'cheque_no' => $cheque_no,
								'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
								'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
								'voucher_dates' => $form['voucher_date'],
							    'voucher_types' => $form['voucher_type'],
								'ref_no'=> $reference[$i],  
								'type' => '1',
								'modified' =>$this->_modified,
							);
						else:
							$tdetailsdata = array(
								'transaction' => $result,
								'location' => $location[$i],
								'activity' => $location[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'bank_ref_type' => $bank_ref_type,
								'cheque_no' => $cheque_no,
								'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
								'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
								'voucher_dates' => $form['voucher_date'],
							    'voucher_types' => $form['voucher_type'],
								'ref_no'=> $reference[$i], 
								'type' => '1',
								'author'=>$created_author,
								'created' => $this->_created,
								'modified' =>$this->_modified,
							);
						endif;
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);				
					endif;
				endfor;
				foreach($delete_rows as $delete_row):
					$this->getDefinedTable(Pswf\TransactiondetailTable::class)->remove($delete_row['id']);
				endforeach;
				$this->_connection->commit(); 
				$this->flashMessenger()->addMessage("success^ Transaction successfully updated | ".$voucher_no);
				return $this->redirect()->toRoute('transaction', array('action' =>'viewtransaction', 'id' => $this->_id));
			}
			else
			{
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to modify  Transaction");	
				return $this->redirect()->toRoute('transaction', array('action' =>'viewtransaction', 'id' => $this->_id));
			}
		}
		$role=explode(",",$this->_login_role);//Multiple Role
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99 || $role==array(2,17)|| $role==8):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		
		return new ViewModel(array(
			'title'  => 'Update transaction',
			'login_id'  =>$this->_login_id,
			'transactions' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'tdetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id)),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'regions' => $regions,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->getAll(),
			'tdetailsObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)
		));
	}
	
	/**
	 * get journal view
	 * 
	 **/
	public function viewtransactionAction(){
		$this->init();
		if($this->_login_role==100):
		    $edit_option = 1;
		else:
		    $edit_option = 0;
		endif;
		return new ViewModel(array(
	    	'login_id'     =>$this->_login_id,
			'edit_option'  =>$edit_option,
			'transactionrow' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'transactiondetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction' => $this->_id)),
			'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
		));
	}
	/**
	 * view all transactions
	 * 
	 **/
	public function viewdetailsAction(){
		$this->init();
		if($this->_login_role==99 || $this->_login_role==100):
		    $edit_option = 1;
		else:
		    $edit_option = 0;
		endif;
		return new ViewModel(array(
	    	'login_id'     =>$this->_login_id,
			'edit_option'  =>$edit_option,
			'transactionrow' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'transactiondetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction' => $this->_id)),
			'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
            'bank_ref_typeObj' => $this->getDefinedTable(Pswf\BankreftypeTable::class),
		));
	}
	/**
	 * commitTransaction action
	 **/
	public function commitJRAction(){
		$this->init();
		//echo "This is my testing"; exit;
		$data = array(
			'id' => $this->_id,
			'status' => 4, // status committed 
			'modified' => $this->_modified,
		);		
		$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data);
		$voucher_no = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
		if($result>0){
			$ids = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id));
			//echo '<pre>';print_r($ids);exit;
			foreach($ids as $tasid):
				$data = array(
					'id' => $tasid['id'],
					'status' => 4, // status committed 
					'modified' => $this->_modified,
				);	
			    $result = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($data);
		    endforeach;
		}
		if($result):
			$this->flashMessenger()->addMessage("success^ Transaction Commited Successfully |PSWF ".$voucher_no);
		endif;
		return $this->redirect()->toRoute("ptransaction", array("action"=>"viewtransaction", "id" => $this->_id));
	}
	/**---------------------------PAYMENT------------------------------------------------------------- */
	/**
	 *  index action
	 */
	public function creditAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$date = $form['date'];
			$status = $form['status'];
		}else{
            $month = date('m');
			$year =date('Y');
			$date =date('d');
			$status ='-1';
		}	
		$data = array(
			'year' => $year,
			'month' => $month,
			'date' => $date,
			'status' => $status,
		);
		
    	//work to get total no. of days, accord. to year n month  date('L', strtotime($data['year']))
		if($data['month'] > 7){
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 31; break;
			default:
				$maxdate = 30; break;
		}
		}else{
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 30; break;
			default:
				$maxdate = 31; break;
		} 
		}
		$user_id=$this->_login_id;
		if($this->_login_role !=100 && $this->_login_role !=99):
		    $user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		else:
		   $user_region='-1';
		endif;
		$transTable = $this->getDefinedTable(Pswf\TransactionTable::class)->getDateWiseStatus('voucher_date',$year,$month,$date,$status,$user_region);
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($transTable));
		//echo '<pre>';print_r($transTable);exit;	
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10000);
		$paginator->setPageRange(8);
		
		//echo $maxdate; exit;
		
		$user_role=$this->_login_role;
		//echo '<pre>';print_r($user_region);exit;
		return new ViewModel(array(
			'title'         =>'Expense Requisition',
			'user'          =>$user_id,
		    'role'          =>$user_role,
			'paginator'     => $paginator,
			'page'          => $page,
			'data'          => $data,
			'maxdate'       => $maxdate,
			'voucherObj'    =>$this->getDefinedTable(Pswf\JournalTable::class),
			'statuslist'    => $this->getDefinedTable(Acl\StatusTable::class)->get(array('id'=>[2,3,4,6])),
   		));
	}
	/**
	 *  add transaction action
	 */
	public function addcreditAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$tmp_VCNo = $loc.'-'.$prefix.$date;
			//$serial = $this->getDefinedTable("Pswf\TransactionTable")->getSerial($tmp_VCNo);
			
			$results = $this->getDefinedTable(Pswf\TransactionTable::class)->getSerial($tmp_VCNo);
			
			$pltp_no_list = array();
			foreach($results as $result):
				array_push($pltp_no_list, substr($result['voucher_no'],-5));
			endforeach;
			$next_serial = max($pltp_no_list) + 1; 
			//echo '<pre>';print_r($next_serial);exit;
				
			switch(strlen($next_serial)){
				case 1: $next_dc_serial = "0000".$next_serial; break;
				case 2: $next_dc_serial = "000".$next_serial;  break;
				case 3: $next_dc_serial = "00".$next_serial;   break;
				case 4: $next_dc_serial = "0".$next_serial;    break;
				default: $next_dc_serial = $next_serial;       break;
			}	
			$voucher_no = $tmp_VCNo.$next_dc_serial;
			//echo '<pre>';print_r($form['currency']);exit;
			$location=$form['location'][0];
			$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($location,'region');
            if(!empty($form['currency'])){
				$currency=$form['currency'];
				$rate=$form['rate'];
				$sdr_amount=$form['amount_usd'];
			}
			else{
				$currency=1;
				$rate=0.00;
				$sdr_amount=0.000;
			}
			
			$data1 = array(
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'region'     =>$region,
				'currency' =>$currency,
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				'voucher_no' => $voucher_no,
				'cheque_no' => $form['cheque_no1'],
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'remark' => $form['remark'],
				'status' => 2, // status initiated 
				'author' =>$this->_author,
				'created' =>$this->_created,  
				'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($data1);exit;
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				$flow_result = $this->flowinitiation('422', $result);
				$location= $form['location'];
				//$activity= $form['activity']; 
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no']; 
				$debit= $form['debit'];
				$credit= $form['credit'];
				$reference= $form['reference'];
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						$tdetailsdata = array(
							'transaction' => $result,
							'voucher_dates' => $form['voucher_date'],
							'voucher_types' => $form['voucher_type'],
							'location' => $location[$i],
							'activity' =>$location[$i],
							'against' =>0,
							'currency' =>$currency,
							'rate' =>$rate,
							'credit_sdr' =>$sdr_amount,
							'head' => $this->getDefinedTable(Pswf\SubheadTable::class)->getColumn($sub_head[$i], $column='head'),
							'sub_head' => $sub_head[$i],
							'bank_ref_type' => '',
							'cheque_no' => $cheque_no[$i],
							'debit' => (isset($debit[$i]))? $debit[$i]:'0.000',
							'credit' => (isset($credit[$i]))? $credit[$i]:'0.000',
							'ref_no'=> $reference[$i], 
							'type' => '1',//user inputted  data
							'status' => 2, // status initiated
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						//echo '<pre>';print_r($tdetailsdata);exit;
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);
						if($result1 <= 0):
							break;
						endif;
					endif;
				endfor;
				/**ADDED FOR SDR TRANSACTION */
				if($form['international']==1):
					if($form['weight']!=0):
						$sdr_details=array(
							'transaction' => $result,
							'voucher_date' => $form['voucher_date'],
							'voucher_type' => $form['voucher_type'],
							'currency' => 2,
							'item' => $form['item'],
							'weight' => $form['weight'],
							'sdr' => $form['sdr'],
							'totalsdr'=>$form['amount_usd'],
							'rate' => $form['rate'],
							'location' => $location[0],
							'status' => 1,
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);  
						$this->getDefinedTable(Pswf\SdrTable::class)->save($sdr_details);
					endif;
					if($form['no']!=0):
						$sdr_details=array(
							'transaction' => $result,
							'voucher_date' => $form['voucher_date'],
							'voucher_type' => $form['voucher_type'],
							'currency' => 2,
							'item' => $form['item'],
							'weight' => $form['no'],
							'sdr' => $form['sdrno'],
							'totalsdr'=>$form['amount_usd'],
							'rate' => $form['rate'],
							'location' => $location[0],
							'status' => 1,
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);  
						$this->getDefinedTable(Pswf\SdrTable::class)->save($sdr_details);
					endif;
				endif;
				/**END OF SDR TRANSACTION */
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Requisition successfully added | ".$voucher_no);
					return $this->redirect()->toRoute('transaction', array('action' =>'viewcredit', 'id' => $result));
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("Failed^ Failed to add new Requisition");		
					return $this->redirect()->toRoute('transaction');
				endif;				
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("Failed^ Failed to add new Requisition");		
				return $this->redirect()->toRoute('transaction');
			}
		}
		$role=explode(",",$this->_login_role);//Multiple Role
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99 || $role==array(2,17)):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		$user_admin_location=$this->getDefinedTable(Administration\UsersTable::class)->get($this->_login_id,'admin_location');
		$today_date   = date('Y-m-d');
		return new ViewModel(array(
			'title'  => 'Add Expense Requisition',
			'vdate'=>$today_date,
			'userlocation' =>$user_admin_location,
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'regions' => $regions,
			'items' => $this->getDefinedTable(Pswf\ItemTable::class)->getAll(),
			'currencies' => $this->getDefinedTable(Pswf\CurrencyTable::class)->getAll(),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->getHeads(),
		));
	}
	
	/**
	 *  edit transaction action
	 */
	public function editcreditAction()
	{
		$this->init();
		$status = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id,'status');
		if($status > 3):
			$this->redirect()->toRoute('transaction');
		endif;
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($this->_user->location, 'location_code');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getColumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$serial = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
			preg_match('/([\d]+)/', $serial, $match );
			$serial = substr($match[0],4);
			$voucher_no = $loc.$prefix.$date.$serial;
			
			$data1 = array(
				'id' => $this->_id,
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				/*'voucher_no' => $voucher_no,*/
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'cheque_no' => $form['cheque_no1'],
				'remark' => $form['remark'],
				'status' => $form['status'],// status pending 
				'modified' =>$this->_modified,
			);			
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				$tdetails_id = $form['id'];
				$location= $form['location'];
				$activity= $form['location'];//they dont wanted activity but it is used in process
				$head= $form['head']; 
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no1'];
				$debit= $form['debit'];
				$credit= $form['credit'];
				$reference= $form['reference'];
				$delete_rows = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->getNotInDtl($tdetails_id, array('transaction' => $result));
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						if($tdetails_id[$i]>0):
							$tdetailsdata = array(
								'id' => $tdetails_id[$i],
								'transaction' => $result,
								'voucher_dates' => $form['voucher_date'],
								'voucher_types' => $form['voucher_type'],
								'location' => $location[$i],
								'activity' => $location[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'bank_ref_type' => '',
								'cheque_no' => $cheque_no[$i],
								'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
								'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
								'ref_no'=> $reference[$i], 
								'type' => '1',//user inputted  data
								'author' =>$this->_author,
								'modified' =>$this->_modified,
							);
						else:
							$tdetailsdata = array(
								'transaction' => $result,
								'voucher_dates' => $form['voucher_date'],
								'voucher_types' => $form['voucher_type'],
								'location' => $location[$i],
								'activity' => $location[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'bank_ref_type' => '',
								'cheque_no' => $cheque_no[$i],
								'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
								'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
								'ref_no'=> $reference[$i], 
								'type' => '1',//user inputted  data
							    'author' => $this->_author,
								'created' => $this->_created,//user inputted  data
								'modified' =>$this->_modified,
							);  
						endif;
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);				
					endif;
				endfor;
				//deleting deleted table rows form database table
				foreach($delete_rows as $delete_row):
					$this->getDefinedTable(Pswf\TransactiondetailTable::class)->remove($delete_row['id']);
				endforeach;
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Requisition successfully updated | ".$voucher_no);
				return $this->redirect()->toRoute('transaction', array('action' =>'viewcredit', 'id' => $this->_id));
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to modify  Requisition");	
				return $this->redirect()->toRoute('transaction', array('action' =>'viewcredit', 'id' => $this->_id));
			}
		}
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99 ||  $this->_login_role==5):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		return new ViewModel(array(
			'title'  => 'Update Expense Requisition',
			'login_id'     =>$this->_login_id,
			'transactions' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'tdetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id)),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'regions' => $regions,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->getAll(),
			'tdetailsObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)
		));
	}
	/**
	Expense Requisition View
	 **/
	public function viewcreditAction(){
		$this->init();
		$application_id = $this->_id;
		/**--Disapearing the notification--*/
		$params = explode("-", $this->_id);
		if (isset($params['1']) && $params['1'] == '1' && isset($params['2']) && $params['2'] > 0) {
			$flag = $this->getDefinedTable(Acl\NotifyTable::class)->getColumn($params['2'], 'flag'); 
				if($flag == "0") {
					$notify = array('id' => $params['2'], 'flag'=>'1','priority'=>'0');//priority red dot(==0....remove)
					$this->getDefinedTable(Acl\NotifyTable::class)->save($notify); 	
				}				
		}
		/*end--*/
		return new ViewModel(array(
		    'login_id'     =>$this->_login_id,
		    'role'          =>$this->_login_role,
			'transactionrow' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'transactiondetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction' => $this->_id)),
			'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
            'bank_ref_typeObj' => $this->getDefinedTable(Pswf\BankreftypeTable::class),
			'flowtransactionObj'  => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'       => $this->getDefinedTable(Administration\FlowActionTable::class),   
			'login_role'          => $this->_login_role,
			'application'        => $this->getDefinedTable(Pswf\TransactionTable::class)->get($application_id),
			'claimObj'        => $this->getDefinedTable(Hr\TAClaimTable::class),
			'claimdetails'        => $this->getDefinedTable(Hr\TAClaimDetailsTable::class),
			'taObj'        => $this->getDefinedTable(Hr\TATable::class),
			'tadetails'        => $this->getDefinedTable(Hr\TADetailsTable::class),
		));
	}
	/**
	 * EXPENSE
	 */
	public function expenseAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$date = $form['date'];
		}else{
            $month = date('m');
			$year =date('Y');
			$date =date('d');
		}	
		$data = array(
			'year' => $year,
			'month' => $month,
			'date' => $date,
		);
		
    	//work to get total no. of days, accord. to year n month  date('L', strtotime($data['year']))
		if($data['month'] > 7){
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 31; break;
			default:
				$maxdate = 30; break;
		}
		}else{
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 30; break;
			default:
				$maxdate = 31; break;
		} 
		}
		$user_id=$this->_login_id;
		if($this->_login_role !=100 && $this->_login_role !=99):
		    $user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		else:
		   $user_region='-1';
		endif;
		$transTable = $this->getDefinedTable(Pswf\TransactionTable::class)->getDateWise('voucher_date',$year,$month,$date,$user_region);
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($transTable));
		//echo '<pre>';print_r($transTable);exit;	
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10000);
		$paginator->setPageRange(8);
		$user_role=$this->_login_role;
		return new ViewModel(array(
			'title'         =>'Payment Release',
			'user'          =>$user_id,
		    'role'          =>$user_role,
			'paginator'     => $paginator,
			'page'          => $page,
			'data'          => $data,
			'maxdate'       => $maxdate,
			'voucherObj'    =>$this->getDefinedTable(Pswf\JournalTable::class),
   		));
	}
	/**
	 *  add transaction action
	 */
	public function addexpenseAction()
	{
		$this->init();
		$application_id = $this->_id;
		$voucher='';
		//echo '<pre>';print_r($application_id);exit;
		$getvouceher=$this->getDefinedTable(Pswf\TransactionTable::class)->get($application_id);
		$loc= $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction' => $application_id));
		//echo '<pre>';print_r($loc);exit;
		foreach($loc as $loc_id);
		$location_id=$loc_id['location_id'];
		//echo '<pre>';print_r($location_id);exit;
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$location=$form['location'][0];
			//echo '<pre>';print_r($location);exit;
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($location, 'prefix');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$tmp_VCNo = $loc.'-'.$prefix.$date;
			//$serial = $this->getDefinedTable("Pswf\TransactionTable")->getSerial($tmp_VCNo);
			
			$results = $this->getDefinedTable(Pswf\TransactionTable::class)->getSerial($tmp_VCNo);
			
			$pltp_no_list = array();
			foreach($results as $result):
				array_push($pltp_no_list, substr($result['voucher_no'], -5));
			endforeach;
			$next_serial = max($pltp_no_list) + 1; 
				
			switch(strlen($next_serial)){
				case 1: $next_dc_serial = "0000".$next_serial; break;
				case 2: $next_dc_serial = "000".$next_serial;  break;
				case 3: $next_dc_serial = "00".$next_serial;   break;
				case 4: $next_dc_serial = "0".$next_serial;   break;
				default: $next_dc_serial = $next_serial;      break;
			}	
			$voucher_no = $tmp_VCNo.$next_dc_serial;
			$location=$form['location'][0];
			$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($location,'region');
            //echo '<pre>';print_r($voucher_no);exit;
			$data1 = array(
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'region'     =>$region,
				'against'    =>1,
				'against_vid' =>$form['against_vid'],
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				'voucher_no' => $voucher_no,
				'cheque_no' => $form['cheque_no1'],
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'remark' => $form['remark'],
				'status' => 2, // status initiated 
				'author' =>$this->_author,
				'created' =>$this->_created,  
				'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($data1);exit;
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				$flow_result = $this->flowinitiation('427', $result);
				$location= $form['location'];
				//$activity= $form['activity']; 
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no']; 
				$debit= $form['debit'];
				$credit= $form['credit'];
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						$tdetailsdata = array(
							'transaction' => $result,
							'voucher_dates' => $form['voucher_date'],
							'voucher_types' => $form['voucher_type'],
							'location' => $location[$i],
							'activity' => $location[$i],
							'head' => $this->getDefinedTable(Pswf\SubheadTable::class)->getColumn($sub_head[$i], $column='head'),
							'sub_head' => $sub_head[$i],
							'bank_ref_type' => '',
							'cheque_no' => $cheque_no[$i],
							'debit' => (isset($debit[$i]))? $debit[$i]:'0.000',
							'credit' => (isset($credit[$i]))? $credit[$i]:'0.000',
							'ref_no'=> '', 
							'type' => '1',//user inputted  data
							'status' => 2, // status initiated
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);
						if($result1 <= 0):
							break;
						endif;
					endif;
				endfor;
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Transaction successfully added | ".$voucher_no);
					return $this->redirect()->toRoute('transaction', array('action' =>'viewexpense', 'id' => $result));
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("Failed^ Failed to add new Transaction");		
					return $this->redirect()->toRoute('transaction');
				endif;				
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("Failed^ Failed to add new Transaction");		
				return $this->redirect()->toRoute('transaction');
			}
		}
		$today_date   = date('Y-m-d');
		//$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		return new ViewModel(array(
			'title'  => 'Add Payment',
			'voucher'   =>$getvouceher,
			'todaydate'=>$today_date,
			'location_id' =>$location_id,
			'transactiondetails'=>$loc,
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			//'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->getAll(),
		));
	}
	
	
	/**
	 *  edit expense action
	 */
	public function editexpenseAction()
	{
		$this->init();
		$status = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id,'status');
		if($status >= 3):
			$this->redirect()->toRoute('transaction');
		endif;
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($this->_user->location, 'location_code');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getColumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$serial = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
			preg_match('/([\d]+)/', $serial, $match );
			$serial = substr($match[0],4);
			$voucher_no = $loc.$prefix.$date.$serial;
			
			$data1 = array(
				'id' => $this->_id,
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				/*'voucher_no' => $voucher_no,*/
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'cheque_no' => $form['cheque_no1'],
				'remark' => $form['remark'],
				'status' => 2, // status pending 
				'modified' =>$this->_modified,
			);			
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				$tdetails_id = $form['id'];
				$location= $form['location'];
				$activity= $form['activity'];
				$head= $form['head']; 
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no1'];
				$debit= $form['debit'];
				$credit= $form['credit'];
				$delete_rows = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->getNotInDtl($tdetails_id, array('transaction' => $result));
				for($i=0; $i < sizeof($activity); $i++):
					if(isset($activity[$i]) && is_numeric($activity[$i])):
						if($tdetails_id[$i]>0):
							$tdetailsdata = array(
								'id' => $tdetails_id[$i],
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
								'modified' =>$this->_modified,
							);
						else:
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
								'author' => $this->_author,
								'created' => $this->_created,//user inputted  data
								'modified' =>$this->_modified,
							);  
						endif;
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);				
					endif;
				endfor;
				foreach($delete_rows as $delete_row):
					$this->getDefinedTable(Pswf\TransactiondetailTable::class)->remove($delete_row['id']);
				endforeach;
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Transaction successfully updated | ".$voucher_no);
				return $this->redirect()->toRoute('transaction', array('action' =>'viewexpense', 'id' => $this->_id));
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to modify  Transaction");	
				return $this->redirect()->toRoute('transaction', array('action' =>'viewexpense', 'id' => $this->_id));
			}
		}
		return new ViewModel(array(
			'title'  => 'Update Expense',
			'login_id'     =>$this->_login_id,
			'transactions' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'tdetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id)),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->getAll(),
			'tdetailsObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)
		));
	}
	/**
	 * get journal view
	 * 
	 **/
	public function viewexpenseAction(){
		$this->init();
		$application_id = $this->_id;
		return new ViewModel(array(
		    'login_id'     =>$this->_login_id,
		    'role'          =>$this->_login_role,
			'transactionrow' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'transactiondetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction' => $this->_id)),
			'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
            'bank_ref_typeObj' => $this->getDefinedTable(Accounts\BankreftypeTable::class),
			'flowtransactionObj'  => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'       => $this->getDefinedTable(Administration\FlowActionTable::class),   
			'login_role'          => $this->_login_role,
			'application'        => $this->getDefinedTable(Pswf\TransactionTable::class)->get($application_id),
		));
	}
	
	/**
	 * Credit application Flow Action
	 * processAction
	 * @app_actions, @app_privileges, @app_transaction_flow @sys_status, @sys_roles
	 */
	public function processrAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()){	
			$form = $this->getRequest()->getpost();
			$process_id	= '427';
			$flow_id = $form['flow'];
			$action_id = $form['action'];
			$application_id = $form['application'];
			$remark = $form['remarks'];
			$application_focal=$form['focal'];
			$current_flow = $this->getDefinedTable(Administration\FlowTransactionTable::class)->get($flow_id);
			foreach($current_flow as $flow);
			$next_activity_no = $flow['activity'] + 1;
			$action_performed = $this->getDefinedTable(Administration\FlowActionTable::class)->getColumn($action_id, 'action');
			$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get(array('flow'=>$flow['flow'],'action_performed'=>$action_id));
			foreach($privileges as $privilege);
			$transactiondetailsid=$this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$application_id));
			$app_data = array(
				'id'		=> $application_id,				
				'status' 	=> $privilege['status_changed_to'],			
				'modified'  => $this->_modified
			);
			foreach($transactiondetailsid as $id):
			$app_data_details = array(
				'id'          => $id['id'],				
				'status' 	  => $privilege['status_changed_to'],			
				'modified'    => $this->_modified
			);
			$app_results = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($app_data_details);
			endforeach;
			$app_data = $this->_safedataObj->rteSafe($app_data);
			$this->_connection->beginTransaction();
			$app_result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($app_data);
			if($app_result):
				$activity_data = array(
					'process'      => $process_id,
					'process_id'   => $application_id,
					'status'       => $privilege['status_changed_to'],
					'remarks'      => $remark,
					'role'         => $flow['actor'],
					'author'	   => $this->_author,
					'created'      => $this->_created,
					'modified'     => $this->_modified,  
				);
				$activity_data = $this->_safedataObj->rteSafe($activity_data);
				$activity_result = $this->getDefinedTable(Acl\ActivityLogTable::class)->save($activity_data);
				if($app_result):
					if($privilege['route_to_role']):
						$flow_data = array(
							'flow'          => $flow['flow'],
							'role_id'       =>$application_focal,
							'application'   => $application_id,
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
						$flow_result = $this->getDefinedTable(Administration\FlowTransactionTable::class)->save($flow_data);
						if($flow_result):
							$this->notify($application_id,$privilege['id'],$remark,$flow_result);
							$this->getDefinedTable(Administration\FlowTransactionTable::class)->performed($flow_id);
							$this->_connection->commit();
							$this->flashMessenger()->addMessage("success^ Successfully applied <strong>".$action_performed."</strong>!");
						else:
							$this->_connection->rollback();
							$this->flashMessenger()->addMessage("error^ Failed to update application apply <strong>".$action_performed."</strong> action.");
						endif;
					else:
						$remove_transaction_flows = $this->getDefinedTable(Administration\FlowTransactionTable::class)->remove($application_id);
						$this->_connection->commit();
						$this->flashMessenger()->addMessage("success^ Successfully Updated Application.");
					endif;
				else:
					$this->_connection->rollback(); 
					$this->flashMessenger()->addMessage("error^ Failed to register the forward action in activity log.");
				endif;
			else:
				$this->_connection->rollback(); 
				$this->flashMessenger()->addMessage("error^ Failed to update Application status for forward action.");
			endif;
			return $this->redirect()->toRoute('transaction', array('action'=>'viewexpense', 'id' => $application_id));
		}

		$login = array(
			'login_id'      => $this->_login_id,
			'login_role'    => $this->_login_role,
		); 
		//$focal=$this->getDefinedTable(Administration\UsersTable::class)->get(array('role',3));
		//echo '<>pre';print_r($focal);exit;
		$viewModel =  new ViewModel(array(
			'title'              => 'Payment Applicaton',
			'flow_id'            => $this->_id,
			'login'              => $login,
			'role'               =>$this->_login_role,
			'transactionObj'     => $this->getDefinedTable(Pswf\TransactionTable::class),
			'flowprivilegeObj'   => $this->getDefinedTable(Administration\FlowPrivilegeTable::class),
			'flowtransactionObj' => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'      => $this->getDefinedTable(Administration\FlowActionTable::class),
			'roleObj'            => $this->getDefinedTable(Acl\RolesTable::class),
			//'focals'             => $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>3)),   
		));
		$viewModel->setTerminal(true);
        return $viewModel;		
	}
	/**
	 * commit action
	 **/
	public function commitCAction(){
		$this->init();
		//echo "This is my testing"; exit;
		$data = array(
			'id' => $this->_id,
			'status' => 4, // status committed 
			'modified' => $this->_modified,
		);		
		$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data);
		$voucher_no = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
		if($result>0){
			$ids = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id));
			//echo '<pre>';print_r($ids);exit;
			foreach($ids as $tasid):
				$data = array(
					'id' => $tasid['id'],
					'status' => 4, // status committed 
					'modified' => $this->_modified,
				);	
			    $result = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($data);
		    endforeach;
		}
		if($result):
			$this->flashMessenger()->addMessage("success^ Credit Transaction Commited Successfully | ".$voucher_no);
		endif;
		return $this->redirect()->toRoute("transaction", array("action"=>"viewcredit", "id" => $this->_id));
	}
	/**
	 * FLOW Function -- Initiation
	 */
	public function flowinitiation($process_id, $application_id)
	{   
		$flow_id = $this->getDefinedTable(Administration\FlowTable::class)->getColumn(array('process'=>$process_id),'id');
		//echo $flow_id; exit;
		$flow_role = $this->getDefinedTable(Administration\FlowTable::class)->getColumn($flow_id,'role');
		$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get(array('flow'=>$flow_id,'action_performed'=>'0'));
		//echo '<pre>'; print_r($privileges);exit;
		if($flow_id):
			foreach($privileges as $privilege);
			$data = array(
				'flow'             => $flow_id,
				'application'      => $application_id,
				'activity'         => 1,
				'actor'            => $privilege['route_to_role'],
				'status'           => $privilege['status_changed_to'],
				'action'           => $privilege['action'],
				'routing'          => $flow_role,
				'routing_status'   => $privilege['status_changed_to'],
				'action_performed' => $privilege['action_performed'],
				'description'      => "Expense Application Initiated",
				'author'           => $this->_author,
				'created'          => $this->_created,
				'modified'         => $this->_modified
			);
			$flow_result = $this->getDefinedTable(Administration\FlowTransactionTable::class)->save($data);
			return $flow_result;
		else:
			return '0';
		endif;
	}
	/**
	 * Credit application Flow Action
	 * processAction
	 * @app_actions, @app_privileges, @app_transaction_flow @sys_status, @sys_roles
	 */
	public function processAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()){	
			$form = $this->getRequest()->getpost();
			$process_id	= '422';
			$flow_id = $form['flow'];
			$action_id = $form['action'];
			$application_id = $form['application'];
			$remark = $form['remarks'];
			$application_focal=$this->_author;
			$current_flow = $this->getDefinedTable(Administration\FlowTransactionTable::class)->get($flow_id);
			foreach($current_flow as $flow);
			$next_activity_no = $flow['activity'] + 1;
			$action_performed = $this->getDefinedTable(Administration\FlowActionTable::class)->getColumn($action_id, 'action');
			$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get(array('flow'=>$flow['flow'],'action_performed'=>$action_id));
			foreach($privileges as $privilege);
			$transactiondetailsid=$this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$application_id));
			$app_data = array(
				'id'		=> $application_id,				
				'status' 	=> $privilege['status_changed_to'],			
				'modified'  => $this->_modified
			);
			foreach($transactiondetailsid as $id):
			$app_data_details = array(
				'id'          => $id['id'],					
				'status' 	  => $privilege['status_changed_to'],			
				'modified'    => $this->_modified
			);
			$app_results = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($app_data_details);
			endforeach;
			$app_data = $this->_safedataObj->rteSafe($app_data);
			$this->_connection->beginTransaction();
			$app_result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($app_data);
			
			foreach($this->getDefinedTable(Pswf\TransactionTable::class)->get($app_result) as $trans);
			if($trans['doc_id']=="billing"){
				$billID=$this->getDefinedTable(Purchase\PaymentTable::class)->getColumn(array('transaction'=>$application_id),'id');
				$bill=array(
					'id'=>$billID,
					'status'=>$trans['status'],
					'modified'  => $this->_modified
				);
				$billresult=$this->getDefinedTable(Purchase\PaymentTable::class)->save($bill);
			}
			if($trans['doc_id']=="POL Expense"){
				$pol=array(
					'id'=>$trans['remark'],
					'status'=>$trans['status'],
					'modified'  => $this->_modified
				);
				$polresult=$this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->save($pol);
			}
			if($trans['doc_id']=="Transport Expense"){
				$tpID=$this->getDefinedTable(Fleet\VehicleRepairMaintaneseTable::class)->getColumn(array('transaction'=>$application_id),'id');
				$tp=array(
					'id'=>$tpID,
					'status'=>$trans['status'],
					'modified'  => $this->_modified
				);
				$tpresult=$this->getDefinedTable(Fleet\VehicleRepairMaintaneseTable::class)->save($tp);
			}
			if($app_result):
				$activity_data = array(
					'process'      => $process_id,
					'process_id'   => $application_id,
					'status'       => $privilege['status_changed_to'],
					'remarks'      => $remark,
					'role'         => $flow['actor'],
					'author'	   => $this->_author,
					'created'      => $this->_created,
					'modified'     => $this->_modified,  
				);
				$activity_data = $this->_safedataObj->rteSafe($activity_data);
				$activity_result = $this->getDefinedTable(Acl\ActivityLogTable::class)->save($activity_data);
				if($app_result):
					if($privilege['route_to_role']):
						$flow_data = array(
							'flow'          => $flow['flow'],
							'role_id'       =>$application_focal,
							'application'   => $application_id,
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
						//echo '<>pre';print_r($flow_data);exit;
						$flow_result = $this->getDefinedTable(Administration\FlowTransactionTable::class)->save($flow_data);
						if($flow_result):
							$this->notify($application_id,$privilege['id'],$remark,$flow_data['role_id']);
							$this->getDefinedTable(Administration\FlowTransactionTable::class)->performed($flow_id);
							$this->_connection->commit();
							$this->flashMessenger()->addMessage("success^ Successfully applied <strong>".$action_performed."</strong>!");
						else:
							$this->_connection->rollback();
							$this->flashMessenger()->addMessage("error^ Failed to update application apply <strong>".$action_performed."</strong> action.");
						endif;
					else:
						$remove_transaction_flows = $this->getDefinedTable(Administration\FlowTransactionTable::class)->remove($application_id);
						$this->_connection->commit();
						$this->flashMessenger()->addMessage("success^ Successfully Removed and approved or rejected or aborted the Application.");
					endif;
				else:
					$this->_connection->rollback(); 
					$this->flashMessenger()->addMessage("error^ Failed to register the forward action in activity log.");
				endif;
			else:
				$this->_connection->rollback(); 
				$this->flashMessenger()->addMessage("error^ Failed to update Application status for forward action.");
			endif;
			return $this->redirect()->toRoute('transaction', array('action'=>'viewcredit', 'id' => $application_id));
		}

		$login = array(
			'login_id'      => $this->_login_id,
			'login_role'    => $this->_login_role,
		); 
		//$focal=$this->getDefinedTable(Administration\UsersTable::class)->get(array('role',3));
		//echo '<>pre';print_r($focal);exit;
		$viewModel =  new ViewModel(array(
			'title'              => 'Expense Application',
			'flow_id'            => $this->_id,
			'login'              => $login,
			'role'               =>$this->_login_role,
			'transactionObj'     => $this->getDefinedTable(Pswf\TransactionTable::class),
			'flowprivilegeObj'   => $this->getDefinedTable(Administration\FlowPrivilegeTable::class),
			'flowtransactionObj' => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'      => $this->getDefinedTable(Administration\FlowActionTable::class),
			'roleObj'            => $this->getDefinedTable(Acl\RolesTable::class),
			//'focals'             => $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>3)),   
		));
		$viewModel->setTerminal(true);
        return $viewModel;		
	}/**--NOTIFY FUNCTION-/
	/**
	 * Notification Action
	 */
	public function notify($application_id,$privilege_id,$remarks = NULL,$role_id)
	{
		$userlists='';
		$applications = $this->getDefinedTable(Pswf\TransactionTable::class)->get($application_id);
		foreach($applications as $app);
		$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get($privilege_id);
		foreach ($privileges as $flow) {
			$notify_msg = $app['voucher_no']." - ".$flow['description']."<br>[".$remarks."]";
			$notification_data = array(
				'route'         => 'transaction',
				'action'        => 'viewcredit',
				'key' 		    => $application_id,
				'description'   => $notify_msg,
				'author'	    => $this->_author,
				'created'       => $this->_created,
				'modified'      => $this->_modified,   
			);
			//echo '<>pre';print_r($privileges);exit;
			$notificationResult = $this->getDefinedTable(Acl\NotificationTable::class)->save($notification_data);
			if($notificationResult > 0 ){
				$notification_array = explode("|", $flow['route_notification_to']);
				//echo '<pre>';print_r($notification_array);
				if(sizeof($notification_array)>0){
					for($k=0;$k<sizeof($notification_array);$k++){
						$focalusers=$this->getDefinedTable(Administration\FlowTransactionTable::class)->get(array('id'=>$role_id));
						foreach($focalusers as $applicationfocal):
						$focal_id = $applicationfocal['role_id'];
						if($notification_array[$k]=='2'){
							//Role 2- User Level
							$userlists = $this->getDefinedTable(Administration\UsersTable::class)->get(array('id'=>$focal_id,'status'=>'1'));
							echo 'Condition1';
						}
						elseif($notification_array[$k]=='3'){
							//Role 3- Regional Level
							$region=$this->getDefinedTable(Administration\UsersTable::class)->getColumn($focal_id,'region');
							$userlists = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>$notification_array[$k],'region'=>$region,'status'=>'1'));
							echo 'Condition2';
						}
						else{
							//Role 3- Financial Division level
							$userlists = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>$notification_array[$k],'status'=>'1'));
							echo 'Condition3';
						}
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
	/**---------------------------RECEIPT------------------------------------------------------------- */
	/**
	 *  index action
	 */
	public function debitAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$date = $form['date'];
		}else{
            $month = date('m');
			$year =date('Y');
			$date =date('d');
		}	
		$data = array(
			'year' => $year,
			'month' => $month,
			'date' => $date,
		);
		
    	//work to get total no. of days, accord. to year n month  date('L', strtotime($data['year']))
		if($data['month'] > 7){
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 31; break;
			default:
				$maxdate = 30; break;
		}
		}else{
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 30; break;
			default:
				$maxdate = 31; break;
		}
		}
		$user_id=$this->_login_id;
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role==100||$this->_login_role==99):
		    $transTable = $this->getDefinedTable(Pswf\TransactionTable::class)->getDateWiseA('voucher_date',$year,$month,$date);
		else:
		    $transTable = $this->getDefinedTable(Pswf\TransactionTable::class)->getDateWise('voucher_date',$year,$month,$date,$user_region);
		endif;
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($transTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10000);
		$paginator->setPageRange(8);
		
		//echo $maxdate; exit;
		$user_id=$this->_login_id;
		$user_role=$this->_login_role;
		return new ViewModel(array(
			'title'         => 'debit',
			'user'          =>$user_id,
			'role'          =>$user_role,
			'paginator'     => $paginator,
			'page'          => $page,
			'data'          => $data,
			'maxdate'       => $maxdate,
			'voucherObj'    =>$this->getDefinedTable(Pswf\JournalTable::class),
   		));
	}
	/**
	 *  add RECEIPT TRANSACTION action
	 */
	public function adddebitAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$tmp_VCNo = $loc.'-'.$prefix.$date;
			//$serial = $this->getDefinedTable("Pswf\TransactionTable")->getSerial($tmp_VCNo);
			
			$results = $this->getDefinedTable(Pswf\TransactionTable::class)->getSerial($tmp_VCNo);
			
			$pltp_no_list = array();
			foreach($results as $result):
				array_push($pltp_no_list, substr($result['voucher_no'], -5));
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
			$location=$form['location'][0];
			$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($location,'region');
			$data1 = array(
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'region'  => $region,
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				'voucher_no' => $voucher_no,
				'cheque_no' => $form['cheque_no1'],
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'remark' => $form['remark'],
				'status' => 2, // status initiated 
				'author' =>$this->_author,
				'created' =>$this->_created,  
				'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($data1);exit;
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				$location= $form['location'];
				//$activity= $form['activity']; 
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no']; 
				$debit= $form['debit'];
				$credit= $form['credit'];
				$bank_trans_journal= $form['bank_journal'];
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						$tdetailsdata = array(
							'transaction' => $result,
							'voucher_dates' => $form['voucher_date'],
							'voucher_types' => $form['voucher_type'],
							'location' => $location[$i],
							'activity' => $location[$i],
							'head' => $this->getDefinedTable(Pswf\SubheadTable::class)->getColumn($sub_head[$i], $column='head'),
							'sub_head' => $sub_head[$i],
							'bank_ref_type' => '',
							'bank_trans_journal' => $bank_trans_journal[$i],
							'cheque_no' => $cheque_no[$i],
							'debit' => (isset($debit[$i]))? $debit[$i]:'0.000',
							'credit' => (isset($credit[$i]))? $credit[$i]:'0.000',
							'ref_no'=> '', 
							'type' => '1',//user inputted  data
							'status' => 2, // status initiated
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);
						if($result1 <= 0):
							break;
						endif;
					endif;
				endfor;
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Receipt successfully added | ".$voucher_no);
					return $this->redirect()->toRoute('transaction', array('action' =>'viewdebit', 'id' => $result));
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("Failed^ Failed to add new Receipt");		
					return $this->redirect()->toRoute('transaction');
				endif;				
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("Failed^ Failed to add new Receipt");		
				return $this->redirect()->toRoute('transaction');
			}
		}
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		$role=explode(",",$this->_login_role);//Multiple Role
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99 || $this->_login_role==8|| $this->_login_role==6|| $role==array(2,17)|| $role==array(5,6)):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		$user_admin_location=$this->getDefinedTable(Administration\UsersTable::class)->get($this->_login_id,'admin_location');
		$today_date   = date('Y-m-d');
		return new ViewModel(array(
			'title'  => 'Add Debit',
			'userlocation'=>$user_admin_location,
			'todaydate' =>$today_date,
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			//'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'regions' => $regions,
			'currencies' => $this->getDefinedTable(Pswf\CurrencyTable::class)->getAll(),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->getAll(),
		));
	}
	/**
	 *  edit transaction action
	 */
	public function editdebitAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($this->_user->location, 'location_code');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getColumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$serial = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
			preg_match('/([\d]+)/', $serial, $match );
			$serial = substr($match[0],4);
			$voucher_no = $loc.$prefix.$date.$serial;
			$created_author =$form['created_author'];
			$data1 = array(
				'id' => $this->_id,
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				/*'voucher_no' => $voucher_no,*/
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'cheque_no' => $form['cheque_no1'],
				'remark' => $form['remark'],
				'author' => $created_author,
				'status' => 2, // status pending 
				'modified' =>$this->_modified,
			);			   
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				$tdetails_id = $form['id'];
				$location= $form['location'];
				//$activity= $form['activity'];
				$head= $form['head'];
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no1'];
				$debit= $form['debit'];
				$credit= $form['credit'];
				$bank_trans_journal= $form['bank_journal'];
				$delete_rows = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->getNotInDtl($tdetails_id, array('transaction' => $result));
				for($i=0; $i < sizeof(location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						if($tdetails_id[$i]>0):
							$tdetailsdata = array(
								'id' => $tdetails_id[$i],
								'transaction' => $result,
								'location' => $location[$i],
								'activity' =>$location[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'bank_ref_type' => '',
								'cheque_no' => $cheque_no[$i],
								'bank_trans_journal' => $bank_trans_journal[$i],
								'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
								'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
								'voucher_dates' => $form['voucher_date'],
							    'voucher_types' => $form['voucher_type'],
								'ref_no'=> '', 
								'type' => '1',//user inputted  data
								'modified' =>$this->_modified,
							);
						else:
							$tdetailsdata = array(
								'transaction' => $result,
								'location' => $location[$i],
								'activity' => $location[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'bank_ref_type' => '',
								'bank_trans_journal' => $bank_trans_journal[$i],
								'cheque_no' => $cheque_no[$i],
								'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
								'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
								'voucher_dates' => $form['voucher_date'],
							    'voucher_types' => $form['voucher_type'],
								'ref_no'=> '', 
								'type' => '1',//user inputted  data
								'author' => $created_author,
								'created' => $this->_created,//user inputted  data
								'modified' =>$this->_modified,
							);
						endif;
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);				
					endif;
				endfor;
				//deleting deleted table rows form database table
				foreach($delete_rows as $delete_row):
					$this->getDefinedTable(Pswf\TransactiondetailTable::class)->remove($delete_row['id']);
				endforeach;
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Receipt successfully updated | ".$voucher_no);
				return $this->redirect()->toRoute('transaction', array('action' =>'viewdebit', 'id' => $this->_id));
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to modify  Receipt");	
				return $this->redirect()->toRoute('transaction', array('action' =>'viewtdebit', 'id' => $this->_id));
			}
		}
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		//echo '';print_r('hello');exit;
		return new ViewModel(array(
			'title'  => 'Update Receipt',
			'login_id'  =>$this->_login_id,
			'transactions' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'tdetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id)),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'regions' => $regions,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->getAll(),
			'tdetailsObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)
		));
	}
	/**
	 * get journal view
	 * 
	 **/
	public function viewdebitAction(){
		$this->init();
		if($this->_login_role==100):
		    $edit_option = 1;
		else:
		    $edit_option = 0;
		endif;
		return new ViewModel(array(
		    'login_id'  =>$this->_login_id,
			'edit_option'=>$edit_option,
			'transactionrow' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'transactiondetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction' => $this->_id)),
			'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
            'bank_ref_typeObj' => $this->getDefinedTable(Pswf\BankreftypeTable::class),
		));
	}
	/**
	 * commit action
	 **/
	public function commitDAction(){
		$this->init();
		//echo "This is my testing"; exit;
		$data = array(
			'id' => $this->_id,
			'status' =>4, // status committed 
			'modified' => $this->_modified,
		);		
		$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data);
		$voucher_no = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
		if($result>0){
			$ids = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id));
			//echo '<pre>';print_r($ids);exit;
			foreach($ids as $tasid):
				$data = array(
					'id' => $tasid['id'],
					'status' => 4, // status committed 
					'modified' => $this->_modified,
				);	
			    $result = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($data);
		    endforeach;
		}
		if($result):
			$this->flashMessenger()->addMessage("success^ Receipt Commited Successfully | ".$voucher_no);
		endif;
		return $this->redirect()->toRoute("transaction", array("action"=>"viewdebit", "id" => $this->_id));
	}
	/**---------------------------CONTRA------------------------------------------------------------- */
	/**
	 *  index action
	 */
	public function contraAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$date = $form['date'];
		}else{
            $month = date('m');
			$year =date('Y');
			$date =date('d');
		}	
		$data = array(
			'year' => $year,
			'month' => $month,
			'date' => $date,
		);
		
    	//work to get total no. of days, accord. to year n month  date('L', strtotime($data['year']))
		if($data['month'] > 7){
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 31; break;
			default:
				$maxdate = 30; break;
		}
		}else{
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 30; break;
			default:
				$maxdate = 31; break;
		}
		}
		$user_id=$this->_login_id;
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role==100||$this->_login_role==99):
		    $transTable = $this->getDefinedTable(Pswf\TransactionTable::class)->getDateWiseA('voucher_date',$year,$month,$date);
		else:
		    $transTable = $this->getDefinedTable(Pswf\TransactionTable::class)->getDateWise('voucher_date',$year,$month,$date,$user_region);
		endif;
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($transTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10000);
		$paginator->setPageRange(8);
		
		//echo $maxdate; exit;
		$user_id=$this->_login_id;
		$user_role=$this->_login_role;
		return new ViewModel(array(
			'title'         => 'Contra Transaction',
			'user'          =>$user_id,
			'role'          =>$user_role,
			'paginator'     => $paginator,
			'page'          => $page,
			'data'          => $data,
			'maxdate'       => $maxdate,
			'voucherObj'    =>$this->getDefinedTable(Pswf\JournalTable::class),
			
		));
	}
	/**
	 * Delete  Action
	 */
	public function deletecontraAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$voucher = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id,'voucher_type');
			$transactiondetails_id = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('td.transaction'=>$this->_id));
			foreach($transactiondetails_id as $transactiondetails_ids):
				$result = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->remove($transactiondetails_ids['id']);
			endforeach;
			if($result):
			$result2 = $this->getDefinedTable(Pswf\TransactionTable::class)->remove($this->_id);
			endif;
			if($result2 > 0):
				$this->flashMessenger()->addMessage("success^ successfully deleted  data");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to delete  data");
			endif;
			if($voucher==1):
				return $this->redirect()->toRoute('transaction', array('action'=>'contra'));
			else:
				return $this->redirect()->toRoute('transaction', array('action'=>'debit'));
			endif;
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Delete Contra',
			'trans'    =>$this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *  add transaction action
	 */
	public function addcontraAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$tmp_VCNo = $loc.'-'.$prefix.$date;
			//$serial = $this->getDefinedTable("Pswf\TransactionTable")->getSerial($tmp_VCNo);
			
			$results = $this->getDefinedTable(Pswf\TransactionTable::class)->getSerial($tmp_VCNo);
			
			$pltp_no_list = array();
			foreach($results as $result):
				array_push($pltp_no_list, substr($result['voucher_no'], -5));
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
			$location=$form['location'][0];
			$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($location,'region');
			$data1 = array(
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'region'   =>$region,
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				'voucher_no' => $voucher_no,
				'cheque_no' => $form['cheque_no1'],
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'remark' => $form['remark'],
				'status' => 2, // status initiated 
				'author' =>$this->_author,
				'created' =>$this->_created,  
				'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($data1);exit;
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				$location= $form['location'];
				//$activity= $form['activity']; 
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no']; 
				if($cheque_no!=null){
					$cheque_no=$cheque_no;
				}else{
					$cheque_no='0';
				}
				$debit= $form['debit'];
				$credit= $form['credit'];
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						$tdetailsdata = array(
							'transaction' => $result,
							'voucher_dates' => $form['voucher_date'],
							'voucher_types' => $form['voucher_type'],
							'location' => $location[$i],
							'activity' => $location[$i],
							'head' => $this->getDefinedTable(Pswf\SubheadTable::class)->getColumn($sub_head[$i], $column='head'),
							'sub_head' => $sub_head[$i],
							'bank_ref_type' => '',
							'cheque_no' => $cheque_no[$i],
							'debit' => (isset($debit[$i]))? $debit[$i]:'0.000',
							'credit' => (isset($credit[$i]))? $credit[$i]:'0.000',
							'ref_no'=> '', 
							'type' => '1',//user inputted  data
							'status' => 2, // status initiated
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);
						if($result1 <= 0):
							break;
						endif;
					endif;
				endfor;
				/**ADDED FOR SDR TRANSACTION */
				if($form['international']==1):
					$sdr_details=array(
						'transaction' => $result,
						'voucher_type' => $form['voucher_type'],
						'currency' => 2,
						'item' => $form['item'],
						'weight' => $form['weight'],
						'sdr' => $form['sdr'],
						'totalsdr'=>$form['amount_usd'],
						'rate' => $form['rate'],
						'location' => $location[0],
						'status' => 1,
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);  
					$this->getDefinedTable(Pswf\SdrTable::class)->save($sdr_details);
				endif;
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Contra successfully added | ".$voucher_no);
					return $this->redirect()->toRoute('transaction', array('action' =>'viewcontra', 'id' => $result));
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("Failed^ Failed to add new Contra");		
					return $this->redirect()->toRoute('transaction');
				endif;				
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("Failed^ Failed to add new Contra");		
				return $this->redirect()->toRoute('transaction');
			}
		}
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		$role=explode(",",$this->_login_role);//Multiple Role
		if($this->_login_role ==100|| $this->_login_role==99 ||$this->_login_role==6 ||$this->_login_role==8||$role==array(5,6)):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		$user_admin_location=$this->getDefinedTable(Administration\UsersTable::class)->get($this->_login_id,'admin_location');
		$today_date   = date('Y-m-d');
		return new ViewModel(array(
			'title'  => 'Add Contra Transaction',
			'todaydate' =>$today_date,
			'userlocation'=>$user_admin_location,
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'regions' => $regions,
			'items' => $this->getDefinedTable(Pswf\ItemTable::class)->getAll(),
			'currencies' => $this->getDefinedTable(Pswf\CurrencyTable::class)->getAll(),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->getAll(),
		));
	}
	/**
	 *  edit transaction action
	 */
	public function editcontraAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($this->_user->location, 'location_code');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getColumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$serial = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
			preg_match('/([\d]+)/', $serial, $match );
			$serial = substr($match[0],4);
			$voucher_no = $loc.$prefix.$date.$serial;
			$created_author = $form['created_author'];
			$data1 = array(
				'id' => $this->_id,
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				/*'voucher_no' => $voucher_no,*/
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'cheque_no' => $form['cheque_no1'],
				'remark' => $form['remark'],
				'author' =>$created_author,
				'status' => 2, // status pending 
				'modified' =>$this->_modified,
			);			
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				$tdetails_id = $form['id'];
				$location= $form['location'];
				//$activity= $form['activity'];
				$head= $form['head'];
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no1'];
				$debit= $form['debit'];
				$credit= $form['credit'];
				$delete_rows = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->getNotInDtl($tdetails_id, array('transaction' => $result));
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						if($tdetails_id[$i]>0):
							$tdetailsdata = array(
								'id' => $tdetails_id[$i],
								'transaction' => $result,
								'location' => $location[$i],
								'activity' => $location[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'bank_ref_type' => '',
								'cheque_no' => $cheque_no[$i],
								'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
								'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
								'voucher_dates' => $form['voucher_date'],
							    'voucher_types' => $form['voucher_type'],
								'ref_no'=> '', 
								'type' => '1',//user inputted  data
								'status' => 2, // status pending 
								'modified' =>$this->_modified,
							);
						else:
							$tdetailsdata = array(
								'transaction' => $result,
								'location' => $location[$i],
								'activity' => $location[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'bank_ref_type' => '',
								'cheque_no' => $cheque_no[$i],
								'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
								'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
								'voucher_dates' => $form['voucher_date'],
							    'voucher_types' => $form['voucher_type'],
								'ref_no'=> '', 
								'type' => '1',//user inputted  data
								'author' =>$created_author,
								'created' => $this->_created,//user inputted  data
								'status' => 2, // status pending 
								'modified' =>$this->_modified,
							);
						endif;
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);				
					endif;
				endfor;
				//deleting deleted table rows form database table
				foreach($delete_rows as $delete_row):
					$this->getDefinedTable(Pswf\TransactiondetailTable::class)->remove($delete_row['id']);
				endforeach;
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Contra successfully updated | ".$voucher_no);
				return $this->redirect()->toRoute('transaction', array('action' =>'viewcontra', 'id' => $this->_id));
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to modify  Contra");	
				return $this->redirect()->toRoute('transaction', array('action' =>'viewcontra', 'id' => $this->_id));
			}
		}
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		return new ViewModel(array(
			'title'  => 'Update Contra Transaction',
			'login_id'  =>$this->_login_id,
			'transactions' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'tdetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id)),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'regions' => $regions,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->getAll(),
			'tdetailsObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)
		));
	}
	/**
	 * get journal view
	 * 
	 **/
	public function viewcontraAction(){
		$this->init();
		if($this->_login_role==100):
		    $edit_option = 1;
		else:
		    $edit_option = 0;
		endif;
		return new ViewModel(array(
		    'title'  => 'View Contra',
		    'login_id'  =>$this->_login_id,
			'edit_option'=>$edit_option,
			'transactionrow' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'transactiondetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction' => $this->_id)),
			'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
            'bank_ref_typeObj' => $this->getDefinedTable(Pswf\BankreftypeTable::class),
		));
	}
	/**
	 * commit action
	 **/
	public function commitCOAction(){
		$this->init();
		//echo "This is my testing"; exit;
		$data = array(
			'id' => $this->_id,
			'status' =>4, // status committed 
			'modified' => $this->_modified,
		);		
		$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data);
		$voucher_no = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
		if($result>0){
			$ids = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id));
			//echo '<pre>';print_r($ids);exit;
			foreach($ids as $tasid):
				$data = array(
					'id' => $tasid['id'],
					'status' =>4, // status committed 
					'modified' => $this->_modified,
				);	
			    $result = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($data);
		    endforeach;
		}
		if($result):
			$this->flashMessenger()->addMessage("success^ Contra Transaction Commited Successfully | ".$voucher_no);
		endif;
		return $this->redirect()->toRoute("transaction", array("action"=>"viewcontra", "id" => $this->_id));
	}
    /**
	 * money receipt print
	 **/
	public function receiptprintAction(){
		$this->init();
		return new ViewModel(array(
			'transactionrow' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'transactiondetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction' => $this->_id)),
			'transactiondetailsObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class),
		));
	}
	/**---------------------------RECEIPT------------------------------------------------------------- */
	/**
	 *  index action
	 */
	public function againstAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$date = $form['date'];
			$voucher = $form['voucher'];
		}else{
            $month = date('m');
			$year =date('Y');
			$date =date('d');
			$voucher='';
		}	
		$data = array(
			'year' => $year,
			'month' => $month,
			'date' => $date,
			'voucher' => $voucher,
		);
		//echo '<pre>';print_r($data);exit;
    	//work to get total no. of days, accord. to year n month  date('L', strtotime($data['year']))
		if($data['month'] > 7){
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 31; break;
			default:
				$maxdate = 30; break;
		}
		}else{
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 30; break;
			default:
				$maxdate = 31; break;
		}
		}
		$user_id=$this->_login_id;
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role==100||$this->_login_role==99):
		    $transTable = $this->getDefinedTable(Pswf\TransactionTable::class)->getDateWiseA('voucher_date',$year,$month,$date);
		else:
		    $transTable = $this->getDefinedTable(Pswf\TransactionTable::class)->getDateWise('voucher_date',$year,$month,$date,$user_region);
		endif;
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($transTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10000);
		$paginator->setPageRange(8);
		
		//echo $maxdate; exit;
		$user_id=$this->_login_id;
		$user_role=$this->_login_role;
		return new ViewModel(array(
			'title'         => 'Against Credit',
			'user'          =>$user_id,
			'role'          =>$user_role,
			'paginator'     => $paginator,
			'page'          => $page,
			'data'          => $data,
			'maxdate'       => $maxdate,
			'voucherObj'    =>$this->getDefinedTable(Pswf\JournalTable::class),
			'vouchers'      =>$this->getDefinedTable(Pswf\TransactionTable::class)->get(array('against'=>0)),
			
   		));
	}
	/**
	 * Add transaction action
	 */
	public function addagainstcreditAction()
	{
		$this->init();
		$application_id = $this->_id;
		$voucher='';
		//echo '<pre>';print_r($application_id);exit;
		$getvouceher=$this->getDefinedTable(Pswf\TransactionTable::class)->get($application_id);
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$tmp_VCNo = $loc.'-'.$prefix.$date;
			//$serial = $this->getDefinedTable("Pswf\TransactionTable")->getSerial($tmp_VCNo);
			
			$results = $this->getDefinedTable(Pswf\TransactionTable::class)->getSerial($tmp_VCNo);
			
			$pltp_no_list = array();
			foreach($results as $result):
				array_push($pltp_no_list, substr($result['voucher_no'], -5));
			endforeach;
			$next_serial = max($pltp_no_list) + 1; 
				
			switch(strlen($next_serial)){
				case 1: $next_dc_serial = "0000".$next_serial; break;
				case 2: $next_dc_serial = "000".$next_serial;  break;
				case 3: $next_dc_serial = "00".$next_serial;   break;
				case 4: $next_dc_serial = "0".$next_serial;   break;
				default: $next_dc_serial = $next_serial;      break;
			}	
			$v_no = $tmp_VCNo.$next_dc_serial;
			$location=$form['location'][0];
			$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($location,'region');
			// if(!empty($form['currency'])){
			// 	$currency=$form['currency'];
			// 	$rate=$form['rate'];
			// 	$sdr_amount=$form['amount_usd'];
			// }
			// else{
			// 	$currency=1;
			// 	$rate=0;
			// 	$sdr_amount=0;
			// }
			// $rateinput=$form['rate'];
		    // $sdr_amountinput=$form['amount_usd'];
			// $rateinput = number_format($rateinput, 3, '.', '');
            // $sdr_amountinput = number_format($sdr_amountinput, 3, '.', '');

			//echo '<pre>';print_r($form['international']);exit;
			$getvouceher=$this->getDefinedTable(Pswf\TransactionTable::class)->get($application_id);
            foreach($getvouceher as $voucher);
			$against_vid= implode(',',$form['reference']);
			//echo '<pre>';print_r($getvouceher);exit;
			$data1 = array(
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'region'     =>$region,
				'against'    =>1,
				'against_vid' =>$against_vid,
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				'voucher_no' => $v_no,
				'cheque_no' => $form['cheque_no1'],
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'remark' => $form['remark'],
				'status' => 2, // status 
				'author' =>$this->_author,
				'created' =>$this->_created,  
				'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($data1);
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				//$flow_result = $this->flowinitiation('422', $result);
				$location= $form['location'];
				//$activity= $form['activity']; 
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no']; 
				$debit= $form['debit'];
				$credit= $form['credit'];
				$against= $form['reference'];
				//$currency= $form['currency'];
				//$rate= $form['rate'];
				//$amount_usd= $form['amount_usd'];
				 //$rate='0.000';
				 //$amount_usd= '0.000';
				 //$currency=1;
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
					    /**FOR AGAINST UPDATE-*/
						 $against_st[$i]=0;
					    if($credit[$i]!='0.000'):
							$validity[$i]= $this->getDefinedTable(Pswf\TransactiondetailTable::class)->getColumn($against[$i],'debit');
					     	$against_st[$i] = ($validity[$i] != $credit[$i]) ? '3' : 0; 
						 endif;
					  /**END-*/
						 
						// $rate=$this->getDefinedTable(Pswf\TransactiondetailTable::class)->getColumn($against[$i],'rate');
						// $sdr_amount=$this->getDefinedTable(Pswf\TransactiondetailTable::class)->getColumn($against[$i],'debit_sdr');
						//echo '<pre>';print_r($rate);exit
						$tdetailsdata = array(
							'transaction' => $result,
							'voucher_dates' => $form['voucher_date'],
							'voucher_types' => $form['voucher_type'],
							'location' => $location[$i],
							'activity' => $location[$i],
							'against' =>(!empty($against[$i]))? $against[$i]:0,
							'rate' =>'0.000',
							'credit_sdr' =>'0.000',
							'head' => $this->getDefinedTable(Pswf\SubheadTable::class)->getColumn($sub_head[$i], $column='head'),
							'sub_head' => $sub_head[$i],
							'bank_ref_type' => '',
							'cheque_no' =>(isset($cheque_no[$i]))? $cheque_no[$i]:'DFT20052024',
							'debit' => (isset($debit[$i]))? $debit[$i]:'0.000',
							'credit' => (isset($credit[$i]))? $credit[$i]:'0.000',
							'currency'	=> 1,
							'ref_no'=> '', 
							'type' => '1',//user inputted  data
							'against_status' => $against_st[$i], // status against directly commit 
							'status' => 2, // status against directly commit 
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,  
						);
						//echo '<pre>';print_r($tdetailsdata);exit;
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);
						if($result1 <= 0):
							break;
						endif;
					endif;
				endfor;
				// if($form['international']==1):
				//     $location= $form['location'];
				// 	for($i=0; $i < sizeof($against); $i++):
				// 		if(isset($against[$i]) && is_numeric($against[$i])):
				// 			$deta=$this->getDefinedTable(Pswf\TransactiondetailTable::class)->getColumn($against[$i],'transaction');
				// 			$sdr_data=$this->getDefinedTable(Pswf\SdrTable::class)->get(array('transaction'=>$deta));
				// 			//echo '<pre>';print_r($sdr_data);exit;
				// 			foreach($sdr_data as $dat):
				// 			$sdr_details=array(
				// 				'transaction' => $result,
				// 				'against'=>$against[$i],
				// 				'voucher_date' => $form['voucher_date'],
				// 				'voucher_type' => $form['voucher_type'],
				// 				'currency' => 2,
				// 				'item' => $dat['item'],
				// 				'weight' => (isset($dat['weight']))? $dat['weight']:0,
				// 				'sdr' => $dat['sdr'],
				// 				'credit_sdr'=>(!empty($sdr_amountinput))? $sdr_amountinput:$dat['debit_sdr'],
				// 				'rate' =>(!empty($rateinput))? $rateinput:$dat['rate'],
				// 				'location' => (!empty($form['location'][$i]))? $form['location'][$i]:0,
				// 				'status' => 1,
				// 				'author' =>$this->_author,
				// 				'created' =>$this->_created,
				// 				'modified' =>$this->_modified,//
				// 			);  
				// 			//echo '<pre>';print_r($sdr_details);
				// 			$sdr_details = $this->_safedataObj->rteSafe($sdr_details);
				// 			$result1 = $this->getDefinedTable(Pswf\SdrTable::class)->save($sdr_details);
				// 			endforeach;
				// 		endif;
				// 	endfor;
				// endif;//exit;
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Transaction successfully added | ".$voucher_no);
					return $this->redirect()->toRoute('transaction', array('action' =>'viewagainst', 'id' => $result));
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("Failed^ Failed to add new Transaction");		
					return $this->redirect()->toRoute('transaction');
				endif;				
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
		 		$this->flashMessenger()->addMessage("Failed^ Failed to add new Transaction");		
				return $this->redirect()->toRoute('transaction');
			}
		}
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
	    /* role=8-Western Union User
           role=6-casher*/	
		   
		$role=explode(",",$this->_login_role);//Multiple Role
		if($this->_login_role ==100|| $this->_login_role==99 || $this->_login_role==5|| $this->_login_role==8 || $this->_login_role==6 || $role==array(2,17)):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;//exit;
		//echo '<pre>';print_r($voucher);exit;
		$user_admin_location=$this->getDefinedTable(Administration\UsersTable::class)->get($this->_login_id,'admin_location');
		$today_date   = date('Y-m-d');
		return new ViewModel(array(
			'title'  => 'Add Against',
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			//'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'regions' => $regions,
			'todaydate' =>$today_date,
			'userlocation' => $user_admin_location,
			'voucher'   =>$getvouceher,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->getAll(),
			'currencies' => $this->getDefinedTable(Pswf\CurrencyTable::class)->getAll(),
		));
	}
	
	/**
	 *  edit transaction action
	 */
	public function editagainstAction()
	{
		$this->init();
		$status = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id,'status');
		if($status >= 3):
			$this->redirect()->toRoute('transaction');
		endif;
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($this->_user->location, 'location_code');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getColumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$serial = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
			preg_match('/([\d]+)/', $serial, $match );
			$serial = substr($match[0],4);
			$voucher_no = $loc.$prefix.$date.$serial;
			
			$data1 = array(
				'id' => $this->_id,
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				/*'voucher_no' => $voucher_no,*/
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'cheque_no' => $form['cheque_no1'],
				'remark' => $form['remark'],
				'status' => 2, // status pending 
				'modified' =>$this->_modified,
			);			
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				$tdetails_id = $form['id'];
				$location= $form['location'];
				//$activity= $form['activity'];
				$head= $form['head']; 
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no1'];
				$debit= $form['debit'];
				$credit= $form['credit'];
				$delete_rows = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->getNotInDtl($tdetails_id, array('transaction' => $result));
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						if($tdetails_id[$i]>0):
							$tdetailsdata = array(
								'id' => $tdetails_id[$i],
								'transaction' => $result,
								'location' => $location[$i],
								'activity' => $location[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'bank_ref_type' => '',
								'cheque_no' => $cheque_no[$i],
								'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
								'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
								'ref_no'=> '', 
								'type' => '1',//user inputted  data
								'status' => 2, // status pending 
								'modified' =>$this->_modified,
							);
						else:
							$tdetailsdata = array(
								'transaction' => $result,
								'location' => $location[$i],
								'activity' => $location[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'bank_ref_type' => '',
								'cheque_no' => $cheque_no[$i],
								'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
								'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
								'ref_no'=> '', 
								'type' => '1',//user inputted  data
								'status' => 2, // status pending 
								'author' => $this->_author,
								'created' => $this->_created,//user inputted  data
								'modified' =>$this->_modified,
							);  
						endif;
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);				
					endif;
				endfor;
				//deleting deleted table rows form database table
				foreach($delete_rows as $delete_row):
					$this->getDefinedTable(Pswf\TransactiondetailTable::class)->remove($delete_row['id']);
				endforeach;
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Transaction successfully updated | ".$voucher_no);
				return $this->redirect()->toRoute('transaction', array('action' =>'viewagainst', 'id' => $this->_id));
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to modify  Transaction");	
				return $this->redirect()->toRoute('transaction', array('action' =>'viewagainst', 'id' => $this->_id));
			}
		}
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99||$this->_login_role==5):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		return new ViewModel(array(
			'title'  => 'Update Against',
			'login_id'   =>$this->_login_id,
			'transactions' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'tdetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id)),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'regions' => $regions,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->getAll(),
			'tdetailsObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)
		));
	}
	/**
	 * get against view
	 * 
	 **/
	public function viewagainstAction(){
		$this->init();
		return new ViewModel(array(
		    'login_id'  =>$this->_login_id,
			'transactionrow' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'transactiondetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction' => $this->_id)),
			'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
            'bank_ref_typeObj' => $this->getDefinedTable(Accounts\BankreftypeTable::class),
		));
	}
	/**
	 * commit addagainstcredit action
	 **/
	public function commitAAction(){
		$this->init();
		//echo "This is my testing"; exit;
		$data = array(
			'id' => $this->_id,
			'status' =>4, // status committed 
			'modified' => $this->_modified,
		);		
		$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data);
		$voucher_no = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
		if($result>0){
			$ids = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id));
			//echo '<pre>';print_r($ids);exit;
			foreach($ids as $tasid):
				$data = array(
					'id' => $tasid['id'],
					'status' =>4, // status committed 
					'modified' => $this->_modified,
				);	
			    $results = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($data);
			endforeach;
		}
		if($results):
			foreach($ids as $tasid):
				$againstID =  $this->getDefinedTable(Pswf\TransactiondetailTable::class)->getColumn($tasid['id'], 'against');
				$creditAmount =  $this->getDefinedTable(Pswf\TransactiondetailTable::class)->getColumn($tasid['id'], 'credit');
				if($againstID!=null && $againstID!=0):
					if($creditAmount!='0.00'):
						if($tasid['against_status']==3):
							$against=0;
						else:
							$against=$tasid['id'];
						endif;
						$update_against = array(
							'id' => $againstID,
							'against'=>$against,
							'modified' => $this->_modified,
						);
						//echo '<pre>';print_r($update_against);
						$results1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($update_against);
					endif;
				endif;
		    endforeach;//exit;
			$this->flashMessenger()->addMessage("success^  Transaction Commited Successfully | ".$voucher_no);
		endif;
		return $this->redirect()->toRoute("transaction", array("action"=>"viewagainst", "id" => $this->_id));
	}
	/**ADD AGAINST DEBIT_AGAINST--------------------------------------
	 * It fetch credit from previous transaction and autofill the debit
	 */
	public function againstdebitAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$date = $form['date'];
			$voucher = $form['voucher'];
		}else{
            $month = date('m');
			$year =date('Y');
			$date =date('d');
			$voucher='';
		}	
		$data = array(
			'year' => $year,
			'month' => $month,
			'date' => $date,
			'voucher' => $voucher,
		);
		//echo '<pre>';print_r($data);exit;
    	//work to get total no. of days, accord. to year n month  date('L', strtotime($data['year']))
		if($data['month'] > 7){
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 31; break;
			default:
				$maxdate = 30; break;
		}
		}else{
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 30; break;
			default:
				$maxdate = 31; break;
		}
		}
		$user_id=$this->_login_id;
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role==100||$this->_login_role==99):
		    $transTable = $this->getDefinedTable(Pswf\TransactionTable::class)->getDateWiseA('voucher_date',$year,$month,$date);
		else:
		    $transTable = $this->getDefinedTable(Pswf\TransactionTable::class)->getDateWise('voucher_date',$year,$month,$date,$user_region);
		endif;
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($transTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10000);
		$paginator->setPageRange(8);
		
		//echo $maxdate; exit;
		$user_id=$this->_login_id;
		$user_role=$this->_login_role;
		return new ViewModel(array(
			'title'         => 'Against Debit',
			'user'          =>$user_id,
			'role'          =>$user_role,
			'paginator'     => $paginator,
			'page'          => $page,
			'data'          => $data,
			'maxdate'       => $maxdate,
			'voucherObj'    =>$this->getDefinedTable(Pswf\JournalTable::class),
			'vouchers'      =>$this->getDefinedTable(Pswf\TransactionTable::class)->get(array('against'=>0)),
			
   		));
	}
	public function addagainstdebitAction()
	{
		$this->init();
		$application_id = $this->_id;
		$voucher='';
		$getvouceher=$this->getDefinedTable(Pswf\TransactionTable::class)->get($application_id);
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$tmp_VCNo = $loc.'-'.$prefix.$date;
			//$serial = $this->getDefinedTable("Pswf\TransactionTable")->getSerial($tmp_VCNo);
			
			$results = $this->getDefinedTable(Pswf\TransactionTable::class)->getSerial($tmp_VCNo);
			
			$pltp_no_list = array();
			foreach($results as $result):
				array_push($pltp_no_list, substr($result['voucher_no'], -5));
			endforeach;
			$next_serial = max($pltp_no_list) + 1; 
				
			switch(strlen($next_serial)){
				case 1: $next_dc_serial = "0000".$next_serial; break;
				case 2: $next_dc_serial = "000".$next_serial;  break;
				case 3: $next_dc_serial = "00".$next_serial;   break;
				case 4: $next_dc_serial = "0".$next_serial;   break;
				default: $next_dc_serial = $next_serial;      break;
			}	
			$against_no = $tmp_VCNo.$next_dc_serial;
			$location=$form['location'][0];
			$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($location,'region');
			$getvouceher=$this->getDefinedTable(Pswf\TransactionTable::class)->get($application_id);
            foreach($getvouceher as $voucher);
			$against_vid= implode(',',$form['reference']);
			//echo '<pre>';print_r($form['reference']);exit;
			$data1 = array(
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'region'     =>$region,
				'against'    =>1,
				'against_vid' =>$against_vid,
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				'voucher_no' => $against_no,
				'cheque_no' => $form['cheque_no1'],
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'remark' => $form['remark'],
				'status' => 2, // status 
				'author' =>$this->_author,
				'created' =>$this->_created,  
				'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($data1);
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				$flow_result = $this->flowinitiation('427', $result);
				$location= $form['location'];
				$activity= $form['location']; 
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no']; 
				$debit= $form['debit'];
				$credit= $form['credit'];
				$against= $form['reference'];
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						$tdetailsdata = array(
							'transaction' => $result,
							'voucher_dates' => $form['voucher_date'],
							'voucher_types' => $form['voucher_type'],
							'location' => $location[$i],
							'activity' => $activity[$i],
							'against' => (isset($against[$i]))? $against[$i]:'0',
							'head' => $this->getDefinedTable(Pswf\SubheadTable::class)->getColumn($sub_head[$i], $column='head'),
							'sub_head' => $sub_head[$i],
							'bank_ref_type' => '',
							'cheque_no' => $cheque_no[$i],
							'debit' => (isset($debit[$i]))? $debit[$i]:'0.000',
							'credit' => (isset($credit[$i]))? $credit[$i]:'0.000',
							'ref_no'=> '', 
							'type' => '1',//user inputted  data
							'status' => 2, // status against directly commit 
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,  
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);
						if($result1 <= 0):
							break;
						endif;
					endif;
				endfor;
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Transaction successfully added |PSWF ".$voucher_no);
					return $this->redirect()->toRoute('ptransaction', array('action' =>'viewagainstdebit', 'id' => $result));
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("Failed^ Failed to add new Transaction");		
					return $this->redirect()->toRoute('pt');
				endif;				
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
		 		$this->flashMessenger()->addMessage("Failed^ Failed to add new Transaction");		
				return $this->redirect()->toRoute('ptransaction');
			}
		}
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99 || $this->_login_role==5 || $this->_login_role==6):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;//exit;
		//echo '<pre>';print_r($voucher);exit;
		$user_admin_location=$this->getDefinedTable(Administration\UsersTable::class)->get($this->_login_id,'admin_location');
		$today_date   = date('Y-m-d');
		return new ViewModel(array(
			'title'  => 'Add Against Debit',
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			//'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'regions' => $regions,
			'todaydate' =>$today_date,
			'userlocation' => $user_admin_location,
			'voucher'   =>$getvouceher,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->getAll(),
		));
	}
	/**
	 *  edit against debit action
	 */
	public function editagainstdebitAction()
	{
		$this->init();
		$status = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id,'status');
		//if($status >= 3):
			//$this->redirect()->toRoute('transaction');
		//endif;
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($this->_user->location, 'location_code');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getColumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$serial = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
			preg_match('/([\d]+)/', $serial, $match );
			$serial = substr($match[0],4);
			$voucher_no = $loc.$prefix.$date.$serial;
			
			$data1 = array(
				'id' => $this->_id,
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				/*'voucher_no' => $voucher_no,*/
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'cheque_no' => $form['cheque_no1'],
				'remark' => $form['remark'],
				//'status' => 2, // status pending 
				'modified' =>$this->_modified,
			);			
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				$tdetails_id = $form['id'];
				$location= $form['location'];
				//$activity= $form['activity'];
				$head= $form['head']; 
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no1'];
				$debit= $form['debit'];
				$credit= $form['credit'];
				$delete_rows = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->getNotInDtl($tdetails_id, array('transaction' => $result));
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						if($tdetails_id[$i]>0):
							$tdetailsdata = array(
								'id' => $tdetails_id[$i],
								'transaction' => $result,
								'location' => $location[$i],
								'activity' => $location[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'bank_ref_type' => '',
								'cheque_no' => $cheque_no[$i],
								'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
								'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
								'ref_no'=> '', 
								'type' => '1',//user inputted  data
								'status' => 2, // status pending 
								'modified' =>$this->_modified,
							);
						else:
							$tdetailsdata = array(
								'transaction' => $result,
								'location' => $location[$i],
								'activity' => $location[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'bank_ref_type' => '',
								'cheque_no' => $cheque_no[$i],
								'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
								'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
								'ref_no'=> '', 
								'type' => '1',//user inputted  data
								'status' => 2, // status pending 
								'author' => $this->_author,
								'created' => $this->_created,//user inputted  data
								'modified' =>$this->_modified,
							);  
						endif;
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);				
					endif;
				endfor;
				//deleting deleted table rows form database table
				foreach($delete_rows as $delete_row):
					$this->getDefinedTable(Pswf\TransactiondetailTable::class)->remove($delete_row['id']);
				endforeach;
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Transaction successfully updated | ".$voucher_no);
				return $this->redirect()->toRoute('ptransaction', array('action' =>'viewagainstdebit', 'id' => $this->_id));
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to modify  Transaction");	
				return $this->redirect()->toRoute('ptransaction', array('action' =>'viewagainstdebit', 'id' => $this->_id));
			}
		}
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99||$this->_login_role==5||$this->_login_role==6):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		return new ViewModel(array(
			'title'  => 'Update Against',
			'login_id'   =>$this->_login_id,
			'transactions' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'tdetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id)),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'regions' => $regions,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->getAll(),
			'tdetailsObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)
		));
	}
	/**
	 *  edit against debit action
	 */
	public function editagainstpayrollAction()
	{
		$this->init();
		$status = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id,'status');
		//if($status >= 3):
			//$this->redirect()->toRoute('transaction');
		//endif;
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($this->_user->location, 'location_code');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getColumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$serial = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
			preg_match('/([\d]+)/', $serial, $match );
			$serial = substr($match[0],4);
			$voucher_no = $loc.$prefix.$date.$serial;
			
			$data1 = array(
				'id' => $this->_id,
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				/*'voucher_no' => $voucher_no,*/
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'cheque_no' => $form['cheque_no1'],
				'remark' => $form['remark'],
				'status' => $status, // status pending 
				'modified' =>$this->_modified,
			);			
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				foreach($this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$result)) as $dtl):
					$data2 = array(
						'id' => $dtl['id'],
						'transaction' => $result,
						'voucher_dates' =>$form['voucher_date'],
						'modified' =>$this->_modified,
					);			
					$data2 = $this->_safedataObj->rteSafe($data2);
					$result = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($data2);
				endforeach;
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Transaction successfully updated | ".$voucher_no);
				return $this->redirect()->toRoute('transaction', array('action' =>'viewagainstdebit', 'id' => $this->_id));
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to modify  Transaction");	
				return $this->redirect()->toRoute('transaction', array('action' =>'viewagainstdebit', 'id' => $this->_id));
			}
		}
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99||$this->_login_role==5||$this->_login_role==6):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		return new ViewModel(array(
			'title'  => 'Update Against',
			'login_id'   =>$this->_login_id,
			'transactions' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'tdetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id)),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'regions' => $regions,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->getAll(),
			'tdetailsObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)
		));
	}
	/**
	 * get against debit view
	 **/
	public function viewagainstdebitAction(){
		$this->init();
		$application_id = $this->_id;
		return new ViewModel(array(
		    'login_id'     =>$this->_login_id,
		    'role'          =>$this->_login_role,
			'transactionrow' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'transactiondetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction' => $this->_id)),
			'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
            'bank_ref_typeObj' => $this->getDefinedTable(Accounts\BankreftypeTable::class),
			'flowtransactionObj'  => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'       => $this->getDefinedTable(Administration\FlowActionTable::class),   
			'login_role'          => $this->_login_role,
			'application'        => $this->getDefinedTable(Pswf\TransactionTable::class)->get($application_id),
			'encashObj'        => $this->getDefinedTable(Hr\LeaveEncashTable::class),
		));
	}
	/**
	 * commit action
	 **/
	public function commitADebitAction(){
		$this->init();
		//echo "This is my testing"; exit;
		$data = array(
			'id' => $this->_id,
			'status' =>4, 
			'modified' => $this->_modified,
		);		
		$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data);
		$voucher_no = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
		if($result>0){
			$ids = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id));
			//echo '<pre>';print_r($ids);exit;
			foreach($ids as $tasid):
				$data = array(
					'id' => $tasid['id'],
					'status' =>4, // status committed 
					'modified' => $this->_modified,
				);	
			    $result = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($data);
		    endforeach;
		}
		if($result):
			$this->flashMessenger()->addMessage("success^ Contra Transaction Commited Successfully | ".$voucher_no);
		endif;
		return $this->redirect()->toRoute("ptransaction", array("action"=>"viewagainstdebit", "id" => $this->_id));
	}
	/**
	 *  IWR ------------------------------------------------------------------------------------------------------------
	 */
	public function iwrAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$date = $form['date'];
		}else{
            $month = date('m');
			$year =date('Y');
			$date =date('d');
		}	
		$data = array(
			'year' => $year,
			'month' => $month,
			'date' => $date,
		);
		
    	//work to get total no. of days, accord. to year n month  date('L', strtotime($data['year']))
		if($data['month'] > 7){
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 31; break;
			default:
				$maxdate = 30; break;
		}
		}else{
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 30; break;
			default:
				$maxdate = 31; break;
		}
		}
		$user_id=$this->_login_id;
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role==100||$this->_login_role==99):
		    $transTable = $this->getDefinedTable(Pswf\TransactionTable::class)->getDateWiseA('voucher_date',$year,$month,$date);
		else:
		    $transTable = $this->getDefinedTable(Pswf\TransactionTable::class)->getDateWise('voucher_date',$year,$month,$date,$user_region);
		endif;
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($transTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10000);
		$paginator->setPageRange(8);
		
		//echo $maxdate; exit;
		$user_id=$this->_login_id;
		$user_role=$this->_login_role;
		return new ViewModel(array(
			'title'         => 'IWR Payment',
			'user'          =>$user_id,
			'role'          =>$user_role,
			'paginator'     => $paginator,
			'page'          => $page,
			'data'          => $data,
			'maxdate'       => $maxdate,
			'voucherObj'    =>$this->getDefinedTable(Pswf\JournalTable::class),
   		));
	}
	/**
	 *  add IWR action
	 */
	public function addiwrAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$tmp_VCNo = $loc.'-'.$prefix.$date;
			//$serial = $this->getDefinedTable("Pswf\TransactionTable")->getSerial($tmp_VCNo);
			
			$results = $this->getDefinedTable(Pswf\TransactionTable::class)->getSerial($tmp_VCNo);
			
			$pltp_no_list = array();
			foreach($results as $result):
				array_push($pltp_no_list, substr($result['voucher_no'], -5));
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
			$location=$form['location'][0];
			$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($location,'region');
			$data1 = array(
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'region'   =>$region,
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				'voucher_no' => $voucher_no,
				'cheque_no' => $form['cheque_no1'],
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'remark' => $form['remark'],
				'status' => 2, // status initiated 
				'author' =>$this->_author,
				'created' =>$this->_created,  
				'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($data1);exit;
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				$location= $form['location'];
				//$activity= $form['activity']; 
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no']; 
				$debit= $form['debit'];
				$ref_no= $form['ref_no'];
				$credit= $form['credit'];
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						$tdetailsdata = array(
							'transaction' => $result,
							'voucher_dates' => $form['voucher_date'],
							'voucher_types' => $form['voucher_type'],
							'location' => $location[$i],
							'activity' => $location[$i],
							'against' =>0,
							'head' => $this->getDefinedTable(Pswf\SubheadTable::class)->getColumn($sub_head[$i], $column='head'),
							'sub_head' => $sub_head[$i],
							'bank_ref_type' => '',
							'cheque_no' => $cheque_no[$i],
							'debit' => (isset($debit[$i]))? $debit[$i]:'0.000',
							'credit' => (isset($credit[$i]))? $credit[$i]:'0.000',
							'ref_no'=> $ref_no[$i], 
							'type' => '1',//user inputted  data
							'status' => 2, // status initiated
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);
						if($result1 <= 0):
							break;
						endif;
					endif;
				endfor;
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Payment IWR successfully added | ".$voucher_no);
					return $this->redirect()->toRoute('transaction', array('action' =>'viewiwr', 'id' => $result));
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("Failed^ Failed to add Payment IWR");		
					return $this->redirect()->toRoute('transaction');
				endif;				
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("Failed^ Failed to add Payment IWR");		
				return $this->redirect()->toRoute('transaction');
			}
		}
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		//$test= $this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
	    if($this->_login_role ==100|| $this->_login_role==99|| $this->_login_role==8):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		$user_admin_location=$this->getDefinedTable(Administration\UsersTable::class)->get($this->_login_id,'admin_location');
		$today_date   = date('Y-m-d');
		/*HEAD USED FOR IWR PAYMENT----------------------------------------------------------------*/
		$head = ['31','69','109','30','32','125','56','57','58','59','220','221','222','223'];
		return new ViewModel(array(
			'title'  => 'Add IWR transaction',
			'todaydate' =>$today_date,
			'userlocation'=>$user_admin_location,
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'regions' => $regions,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->get(array('h.id'=>$head)),
		));
	}
	
	/**
	 *  edit IWR action
	 */
	public function editiwrAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($this->_user->location, 'location_code');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getColumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$serial = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
			preg_match('/([\d]+)/', $serial, $match );
			$serial = substr($match[0],4);
			$voucher_no = $loc.$prefix.$date.$serial;
			$created_author = $form['created_author'];
			
			$data1 = array(
				'id' => $this->_id,
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				/*'voucher_no' => $voucher_no,*/
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'cheque_no' => $form['cheque_no1'],
				'remark' => $form['remark'],
				'author' =>$created_author,
				'status' => 2, // status pending 
				'modified' =>$this->_modified,
			);		
            //echo  '<pre>';print_r($data1);exit;	
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				$tdetails_id = $form['id'];
				$location= $form['location'];
				//$activity= $form['activity'];
				$head= $form['head'];
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no1'];
				$debit= $form['debit'];
			    $ref_no= $form['ref_no'];
				$credit= $form['credit'];
				$delete_rows = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->getNotInDtl($tdetails_id, array('transaction' => $result));
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						if($tdetails_id[$i]>0):
							$tdetailsdata = array(
								'id' => $tdetails_id[$i],
								'transaction' => $result,
								'location' => $location[$i],
								'activity' => $location[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'bank_ref_type' => '',
								'cheque_no' => $cheque_no[$i],
								'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
								'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
								'voucher_dates' => $form['voucher_date'],
							    'voucher_types' => $form['voucher_type'],
								'ref_no'=> $ref_no[$i], 
								'type' => '1',//user inputted  data
								'status' => 2, // status pending 
								'modified' =>$this->_modified,
							);
						else:
							$tdetailsdata = array(
								'transaction' => $result,
								'location' => $location[$i],
								'activity' => $location[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'bank_ref_type' => '',
								'cheque_no' => $cheque_no[$i],
								'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
								'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
								'voucher_dates' => $form['voucher_date'],
							    'voucher_types' => $form['voucher_type'],
								'ref_no'=> $ref_no[$i], 
								'type' => '1',//user inputted  data
								'status' => 2, // status pending 
								'author' =>$created_author,
								'created' => $this->_created,//user inputted  data
								'modified' =>$this->_modified,
							);
						endif;
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);				
					endif;
				endfor;
				//deleting deleted table rows form database table
				foreach($delete_rows as $delete_row):
					$this->getDefinedTable(Pswf\TransactiondetailTable::class)->remove($delete_row['id']);
				endforeach;
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Payment IWR successfully updated | ".$voucher_no);
				return $this->redirect()->toRoute('transaction', array('action' =>'viewiwr', 'id' => $this->_id));
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to modify  Payment IWR");	
				return $this->redirect()->toRoute('transaction', array('action' =>'viewiwr', 'id' => $this->_id));
			}
		}
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		return new ViewModel(array(
			'title'  => 'Update transaction',
			'login_id'  =>$this->_login_id,
			'transactions' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'tdetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id)),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'regions' => $regions,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->getAll(),
			'tdetailsObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)
		));
	}
	/**
	 * VIEW IWR--------------------------------------------------------------------------------------------------------
	 **/
	public function viewiwrAction(){
		$this->init();
		if($this->_login_role==100):
		    $edit_option = 1;
		else:
		    $edit_option = 0;
		endif;
		return new ViewModel(array(
	    	'login_id'     =>$this->_login_id,
			'edit_option'  =>$edit_option,
			'transactionrow' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'transactiondetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction' => $this->_id)),
			'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
            'bank_ref_typeObj' => $this->getDefinedTable(Pswf\BankreftypeTable::class),
		));
	}
	/**
	 * COMMIT IWR--------------------------------------------------------------------------------------------------------------------
	 **/
	public function commitiwrAction(){
		$this->init();
		$data = array(
			'id' => $this->_id,
			'status' => 4, 
			'modified' => $this->_modified,
		);		
		$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data);
		$voucher_no = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
		if($result>0){
			$ids = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id));
			foreach($ids as $tasid):
				$data = array(
					'id' => $tasid['id'],
					'status' => 4,
					'modified' => $this->_modified,
				);	
			    $result = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($data);
		    endforeach;
		}
		if($result):
			$this->flashMessenger()->addMessage("success^ IWR Commited Successfully | ".$voucher_no);
		endif;
		return $this->redirect()->toRoute("transaction", array("action"=>"viewiwr", "id" => $this->_id));
	}
	/**
	 *  IWR RECEIPT(AGAINST IWR) ------------------------------------------------------------------------------------------------------------
	 */
	public function iwrreceiptAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$date = $form['date'];
		}else{
            $month = date('m');
			$year =date('Y');
			$date =date('d');
		}	
		$data = array(
			'year' => $year,
			'month' => $month,
			'date' => $date,
		);
		
    	//work to get total no. of days, accord. to year n month  date('L', strtotime($data['year']))
		if($data['month'] > 7){
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 31; break;
			default:
				$maxdate = 30; break;
		}
		}else{
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 30; break;
			default:
				$maxdate = 31; break;
		}
		}
		$user_id=$this->_login_id;
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role==100||$this->_login_role==99||$this->_login_role==8):
		    $transTable = $this->getDefinedTable(Pswf\TransactionTable::class)->getDateWiseA('voucher_date',$year,$month,$date);
		else:
		    $transTable = $this->getDefinedTable(Pswf\TransactionTable::class)->getDateWise('voucher_date',$year,$month,$date,$user_region);
		endif;
		//echo '<pre>';print_r($transTable);exit;
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($transTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10000);
		$paginator->setPageRange(8);
		
		//echo $maxdate; exit;
		$user_id=$this->_login_id;
		$user_role=$this->_login_role;
		return new ViewModel(array(
			'title'         => 'IWR',
			'user'          =>$user_id,
			'role'          =>$user_role,
			'paginator'     => $paginator,
			'page'          => $page,
			'data'          => $data,
			'maxdate'       => $maxdate,
			'voucherObj'    =>$this->getDefinedTable(Pswf\JournalTable::class),
   		));
	}
	/**
	 *  ADD IWR RECEIPT ACTION-------------------------------------------------------------------------------------------------------
	 */
	public function addiwrreceiptAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$tmp_VCNo = $loc.'-'.$prefix.$date;
			
			$results = $this->getDefinedTable(Pswf\TransactionTable::class)->getSerial($tmp_VCNo);
			
			$pltp_no_list = array();
			foreach($results as $result):
				array_push($pltp_no_list, substr($result['voucher_no'], -5));
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
			$location=$form['location'][0];
			//VID = Transaction Details Id
			$against_vid= implode(',',$form['vid']);
			//echo '<pre>';print_r(sizeof($against_vid));
			$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($location,'region');
			$data1 = array(
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'region'   =>$region,
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				'voucher_no' => $voucher_no,
				'against'   =>1,//Against Id is multiple so not recorded
				'against_vid'  =>$against_vid,//Against Id is multiple so not recorded
				'cheque_no' => $form['cheque_no1'],
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'remark' => $form['remark'],
				'status' => 2, // status initiated 
				'author' =>$this->_author,
				'created' =>$this->_created,  
				'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($data1);exit;
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				$location= $form['location'];
				//$activity= $form['activity']; 
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no']; 
				$debit= $form['debit'];
				$credit= $form['credit'];
				$against= $form['vid'];
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
					    $against_st[$i]=0;
						$validity[$i]='0.000';
						//$against[$i]=0;
					    if($credit[$i]!='0.000'):
							$validity[$i]= $this->getDefinedTable(Pswf\TransactiondetailTable::class)->getColumn($against[$i],'debit');
						   // Calculate against_st based on validity and debit
					     	$against_st[$i] = ($validity[$i] != $credit[$i]) ? '3' : 0; // Set to '3' if condition met, otherwise empty
						endif;
						$tdetailsdata = array(
							'transaction' => $result,
							'voucher_dates' => $form['voucher_date'],
							'voucher_types' => $form['voucher_type'],
							'location' => $location[$i],
							'activity' => $location[$i],
							'against' => (!empty($against[$i]))? $against[$i]:0,
							'head' => $this->getDefinedTable(Pswf\SubheadTable::class)->getColumn($sub_head[$i], $column='head'),
							'sub_head' => $sub_head[$i],
							'bank_ref_type' => '',
							'cheque_no' => (!empty($cheque_no[$i]))? $cheque_no[$i]:'DFT01102023',
							'debit' => (!empty($debit[$i]))? $debit[$i]:'0.000',
							'credit' => (!empty($credit[$i]))? $credit[$i]:'0.000',
							'ref_no'=> '', 
							'type' => '1',//user inputted  data
							'against_status' => $against_st[$i], // status initiated
							'status' => 2, // status initiated
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						//echo '<pre>';print_r($tdetailsdata);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);
						if($result1>0):
							/*if(!empty($against_vid[$i])):
								$updatedata1= array(
									'id' => $result,
									'against'   =>1,//Against Id is multiple so not recorded
									'against_vid'  =>$this->getDefinedTable(Pswf\TransactiondetailTable::class)->getColumn($against_vid[$i],'transaction'),//Against Id is multiple so not recorded
									'modified' =>$this->_modified,
								);
								$result2 = $this->getDefinedTable(Pswf\TransactionTable::class)->save($updatedata1);
							endif;*/
						endif;
					endif;
				endfor;//exit;
				/*--Commission Data Saved in the different table as commission*/
				$commission= $form['commission'];
				$decimal= $form['decimal'];
				$remitance= $form['remitance'];
				$a_commission= $form['actual_commission'];
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						$commissiondata = array(
						    'transaction' =>$tdetailsdata['transaction'],
							'tdetails' =>$result1,
							'transaction_date' =>$tdetailsdata['voucher_dates'],
							'location' => $location[$i],
							'actual_commission' => $a_commission,
							'commission_amt' => (!empty($commission[$i]))? $commission[$i]:'0.000',
							'decimal_amt' => (!empty($decimal[$i]))? $decimal[$i]:'0.000',
							'remitance_amt' => (!empty($remitance[$i]))? $remitance[$i]:'0.000',
							'status' => 2, // status initiated
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						//echo '<pre>';print_r($commissiondata);exit;
						$commissiondata = $this->_safedataObj->rteSafe($commissiondata);
						$result1 = $this->getDefinedTable(Pswf\CommissionTable::class)->save($commissiondata);
						if($result1 < 0):
							break;
						endif;
					endif;
				endfor;
				/**DECIMAL GAIN-INCOME ---------------------------------------------*/
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						$tdetailsdata = array(
							'transaction' => $result,
							'voucher_dates' => $form['voucher_date'],
							'voucher_types' => $form['voucher_type'],
							'location' => $location[$i],
							'activity' => $location[$i],
							'against' =>1,
							'head' => 185,
							'sub_head' =>377,
							'bank_ref_type' => '',
							'cheque_no' => (!empty($cheque_no[$i]))? $cheque_no[$i]:'DFT01102023',
							'debit' => '0.000',
							'credit' => (!empty($decimal[$i]))? $decimal[$i]:'0.000',
							'ref_no'=> '', 
							'type' => '1',//user inputted  data
							'status' => 2, // status initiated
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);
					endif;
				endfor;
				/**COMMISSION-INCOME ---------------------------------------------*/
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						$tdetailsdata = array(
							'transaction' => $result,
							'voucher_dates' => $form['voucher_date'],
							'voucher_types' => $form['voucher_type'],
							'location' => $location[$i],
							'activity' => $location[$i],
							'against' =>1,
							'head' => 185,
							'sub_head' =>378,
							'bank_ref_type' => '',
							'cheque_no' => (!empty($cheque_no[$i]))? $cheque_no[$i]:'DFT01102023',
							'debit' => '0.000',
							'credit' => (!empty($commission[$i]))? $commission[$i]:'0.000',
							'ref_no'=> '', 
							'type' => '1',//user inputted  data
							'status' => 2, // status initiated
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);
					endif;
				endfor;
				/**REMITANCE(Bank Charges)-EXPENSE ---------------------------------------------*/
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						$tdetailsdata = array(
							'transaction' => $result,
							'voucher_dates' => $form['voucher_date'],
							'voucher_types' => $form['voucher_type'],
							'location' => $location[$i],
							'activity' => $location[$i],
							'against' =>1,
							'head' => 193,
							'sub_head' => 1566,
							'bank_ref_type' => '',
							'cheque_no' => (!empty($cheque_no[$i]))? $cheque_no[$i]:'DFT01102023',
							'debit' => (!empty($remitance[$i]))? $remitance[$i]:'0.000',
							'credit' => '0.000',
							'ref_no'=> '', 
							'type' => '1',//user inputted  data
							'status' => 2, // status initiated
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);
					endif;
				endfor;
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Payment IWR Receipt successfully added | ".$voucher_no);
					return $this->redirect()->toRoute('transaction', array('action' =>'viewiwrreceipt', 'id' => $result));
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("Failed^ Failed to add Payment IWR Receipt");		
					return $this->redirect()->toRoute('transaction');
				endif;				
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("Failed^ Failed to add Payment IWR Receipt");		
				return $this->redirect()->toRoute('transaction');
			}
		}
		$user_admin_location=$this->getDefinedTable(Administration\UsersTable::class)->get($this->_login_id,'admin_location');
		$today_date   = date('Y-m-d');
		/*HEAD USED FOR IWR PAYMENT----------------------------------------------------------------*/
		$head = ['31','69','109','30','32','125','56','57','58','59','220','221','222','223'];
		return new ViewModel(array(
			'title'  => 'IWR Receipt',
			'todaydate' =>$today_date,
			'userlocation'=>$user_admin_location,
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->get(array('h.id'=>$head)),
			'transactions' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('voucher_types'=>5,'t.status'=>4,'td.against'=>0)),
		));
	}
	/**
	 *  EDIT IWR RECEIPT ACTION-------------------------------------------------------------------------------------------
	 */
	public function editiwrreceiptAction()
	{
		$this->init();
		$status = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id,'status');
		if($status >= 3):
			$this->redirect()->toRoute('transaction');
		endif;
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($this->_user->location, 'location_code');
			$prefix = $this->getDefinedTable(Pswf\JournalTable::class)->getColumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$serial = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
			preg_match('/([\d]+)/', $serial, $match );
			$serial = substr($match[0],4);
			$voucher_no = $loc.$prefix.$date.$serial;
			$cheque_no='Default';
			$data1 = array(
				'id' => $this->_id,
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				/*'voucher_no' => $voucher_no,*/
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'cheque_no' => $form['cheque_no1'],
				'remark' => $form['remark'],
				'status' => 2, 
				'modified' =>$this->_modified,
			);			
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data1);
			if($result > 0){
				$tdetails_id = $form['id'];
				$location= $form['location'];
				//$activity= $form['activity'];
				$head= $form['head'];
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no1'];
				$debit= $form['debit'];
				$credit= $form['credit'];
				$delete_rows = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->getNotInDtl($tdetails_id, array('transaction' => $result));
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						if($tdetails_id[$i]>0):
							$tdetailsdata = array(
								'id' => $tdetails_id[$i],
								'transaction' => $result,
								'location' => $location[$i],
								'activity' => $location[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'bank_ref_type' => '',
								'cheque_no' => $cheque_no[$i],
								'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
								'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
								'ref_no'=> '', 
								'type' => '1',
								'status' => 2, // status pending 
								'modified' =>$this->_modified,
							);
						else:
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
								'type' => '1',
								'status' => 2, // status pending 
								'author' => $this->_author,
								'created' => $this->_created,
								'modified' =>$this->_modified,
							);
						endif;
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($tdetailsdata);				
					endif;
				endfor;
				foreach($delete_rows as $delete_row):
					$this->getDefinedTable(Pswf\TransactiondetailTable::class)->remove($delete_row['id']);
				endforeach;
				$this->_connection->commit(); 
				$this->flashMessenger()->addMessage("success^ Payment IWR successfully updated | ".$voucher_no);
				return $this->redirect()->toRoute('transaction', array('action' =>'viewiwrreceipt', 'id' => $this->_id));
			}
			else
			{
				$this->_connection->rollback(); 
				$this->flashMessenger()->addMessage("error^ Failed to modify  Payment IWR");	
				return $this->redirect()->toRoute('transaction', array('action' =>'viewiwrreceipt', 'id' => $this->_id));
			}
		}
		return new ViewModel(array(
			'title'  => 'Update IWR Receipt',
			'login_id'  =>$this->_login_id,
			'transactions' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'tdetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id)),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Pswf\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Pswf\SubheadTable::class),
			'heads' => $this->getDefinedTable(Pswf\HeadTable::class)->getAll(),
			'tdetailsObj' => $this->getDefinedTable(Pswf\TransactiondetailTable::class),
			'commissionObj' => $this->getDefinedTable(Pswf\CommissionTable::class)
		));
	}
	/**
	 * view IWR RECEIPT------------------------------------------------------------------------------------------------------
	 **/
	public function viewiwrreceiptAction(){
		$this->init();
		//$commission = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction' => $this->_id));
		//foreach(($commission as $ca);
		//$transaction_id=$ca['transaction'];
		$commisamount = $this->getDefinedTable(Pswf\CommissionTable::class)->get(array('transaction'=> $this->_id));
		foreach($commisamount as $co);
		$amount=$co['actual_commission'];
		//echo '<pre>';print_r($amount);exit;
		return new ViewModel(array(
		    'login_id'  =>$this->_login_id,
			'transactionrow' => $this->getDefinedTable(Pswf\TransactionTable::class)->get($this->_id),
			'transactiondetails' => $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction' => $this->_id)),
			'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
            'bank_ref_typeObj' => $this->getDefinedTable(Pswf\BankreftypeTable::class),
			'commissionObj' => $this->getDefinedTable(Pswf\CommissionTable::class),
			'camount' => $amount,
		));
	}
	/**
	 * COMMIT IWR RECEIPT------------------------------------------------------------------------------------------------------
	 **/
	public function commitiwrreceiptAction(){
		$this->init();
		$data = array(
			'id' => $this->_id,
			'status' =>4, 
			'modified' => $this->_modified,
		);		
		$result = $this->getDefinedTable(Pswf\TransactionTable::class)->save($data);
		$voucher_no = $this->getDefinedTable(Pswf\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
		if($result>0){
			$ids = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('transaction'=>$this->_id));
			foreach($ids as $tasid):
				$data = array(
					'id' => $tasid['id'],
					'status' =>4, 
					'modified' => $this->_modified,
				);	
			    $results = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($data);
		    endforeach;
			$commissionids = $this->getDefinedTable(Pswf\CommissionTable::class)->get(array('transaction'=>$this->_id));
			//echo '<pre>';print_r($commissionids);exit;
			foreach($commissionids as $cid):
				$dataCom = array(
					'id' => $cid['id'],
					'status' => 4, 
					'modified' => $this->_modified,
				);	
			    $results1 = $this->getDefinedTable(Pswf\CommissionTable::class)->save($dataCom);
		    endforeach;
		}
		if($results1):
			foreach($ids as $tasid):
				$againstID =  $this->getDefinedTable(Pswf\TransactiondetailTable::class)->getColumn($tasid['id'], 'against');
				$creditAmount =  $this->getDefinedTable(Pswf\TransactiondetailTable::class)->getColumn($tasid['id'], 'credit');
				if($creditAmount!='0.00'):
					if($tasid['against_status']==3):
						$against=0;
					else:
						$against=$tasid['id'];
					endif;
					$update_against = array(
						'id' => $againstID,
						'against'=>$against,
						'modified' => $this->_modified,
					);
					//echo '<pre>';print_r($update_against);
					$results1 = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->save($update_against);
				endif;
		    endforeach;
			//exit;
		return $this->redirect()->toRoute("transaction", array("action"=>"viewiwrreceipt", "id" => $this->_id));
	endif;
	}
	/**
	 * GET PREVIOUS TRANSACTION DATA-------------------------------------------------------------------------------------------
	**/
	public function getvprevAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$vd_id =$form['id'];
		$vid = '';
		$hd = '';
		$shd = '';
		$loc = '';
		$debit='';
		$tdetails = $this->getDefinedTable(Pswf\TransactiondetailTable::class)->get(array('td.id' => $vd_id));
		foreach($tdetails as $hds);
		    $vid.= $hds['id'];
			$hd.= $hds['head_id'];
			$shd.= $hds['sub_head_id'];
			$loc.= $hds['location_id'];
			$debit.= $hds['debit'];
		echo json_encode(array(
		    'vid' => $vid,
			'credit' => $debit,
			'head'  =>$hd,
			'subhead' =>$shd,
			'loc' =>$loc,
		));
		exit;
	}
/**
 * GET SUBHEADS---------------------------------------------------------------------------------------------------------------------
 */
public function getSubheadsAction()
{
  $form = $this->getRequest()->getPost();
  $head_id = $form['headId'];
  
  $subheads = array(); // Array to store subhead options
  
  $sbh = $this->getDefinedTable(Pswf\SubheadTable::class)->get(array('head'=>$head_id));
  foreach($sbh as $sb) {
    $subheads[] = array(
      'value' => $sb['id'],
      'text' => $sb['code'].'-'.$sb['name'],
    );
  }
  $response = array(
    'subhead' => $subheads
  );
  echo json_encode($response);
  exit;
}
}

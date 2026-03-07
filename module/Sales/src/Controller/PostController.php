<?php
namespace Sales\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Stdlib\ArrayObject;
use Laminas\Validator\File\Size;
use Laminas\Validator\File\Extension;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Administration\Model As Administration;
use Acl\Model As Acl;
use Sales\Model As Sales;
use Accounts\Model As Accounts;

class PostController extends AbstractActionController
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
		
		$this->_config = $this->_container->get('Config');
		
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
	
	public function indexAction()
	{
		$this->init();
		
		return new ViewModel( array(
			) );
	}
	/**
	 * fuel action
	 */
	public function pomasterAction()
	{
		$this->init();
		// echo 'hi';exit;
		return new ViewModel( array(
			'title' => "Size Postbox",
			'pos' => $this->getDefinedTable(Sales\PomasterTable::class) -> getAll(),
		));
	}
	
	public function addpomasterAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'post_box_size' => $form['post_box_size'],
					'key_charges' => $form['key_charges'],
					'rate' => $form['rate'],
					'amount' => $form['amount'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Postbox\PomasterTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add");
			endif;
			return $this->redirect()->toRoute('postbox',array('action' => 'pomaster'));
		}
		$ViewModel = new ViewModel(array(
			
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * edit uom Action
	 **/
	public function editpomasterAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest();
			$data=array(
					'id' => $this->_id,
					'post_box_size' => $form->getPost('post_box_size'),
					'key_charges' => $form->getPost('key_charges'),
					'rate' => $form->getPost('rate'),
					'amount' => $form->getPost('amount'),

					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\PomasterTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update");
			endif;
			return $this->redirect()->toRoute('postbox',array('action' => 'pomaster'));
		}
		$ViewModel = new ViewModel(array(
				'pos' => $this->getDefinedTable(Sales\PomasterTable::class)->get($this->_id),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Post box Number action
	 */
	public function postboxnumberAction()
	{
		$this->init();
		// echo 'hi';exit;
		return new ViewModel(array(
			'title' 		=> "Postbox Number",
			'pos' 			=> $this->getDefinedTable(Sales\PostboxNumberTable::class) -> getAll(),
			'regionObj'     => $this->getDefinedTable(Administration\RegionTable::class),
			'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
			'posizeObj' 	=> $this->getDefinedTable(Sales\PomasterTable::class),
		));
	}
	/**
	 * Add Post box Number action
	 */
	public function addponumberAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$check = $this->getDefinedTable(Sales\PostboxNumberTable::class)->get(array('location'=>$form['location'],'po_number'=>$form['postbox_no']));
			if(sizeof($check)==1):
				$this->flashMessenger()->addMessage("warning^ Post box already exit in the given location");
				return $this->redirect()->toRoute('postbox',array('action' => 'postboxnumber'));
			else:
			$data = array(
					'po_size' => $form['post_box_size'],
					'region' => $form['region'],
					'location' => $form['location'],
					'po_number' => $form['postbox_no'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\PostboxNumberTable::class)->save($data);
			endif;
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully added");
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to add");
			endif;
			return $this->redirect()->toRoute('postbox',array('action' => 'postboxnumber'));
		}
		$ViewModel = new ViewModel(array(
			'title' 		  => "Add PostBox",
			'regionObj'       => $this->getDefinedTable(Administration\RegionTable::class),
			'location' 	      => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'postsizeObj'     => $this->getDefinedTable(Sales\PomasterTable::class),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Edit Post box Number action
	 */
	public function editponumberAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'id' => $form['po_id'],
					'po_size' => $form['post_box_size'],
					'region' => $form['region'],
					'location' => $form['location'],
					'po_number' => $form['postbox_no'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\PostboxNumberTable::class)->save($data);
	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully added");
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to add");
			endif;
			return $this->redirect()->toRoute('postbox',array('action' => 'postboxnumber'));
		}
		$ViewModel = new ViewModel(array(
			'title' 		  => "Edit PostBox",
			'postbox' 			=> $this->getDefinedTable(Sales\PostboxNumberTable::class)->get($this->_id),
			'region'       => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'location' 	      => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'postsizeObj'     => $this->getDefinedTable(Sales\PomasterTable::class),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *  registration action
	 */
	public function registrationAction()
	{
		$this->init();
		return new ViewModel( array(
			'title' => " PO_Box ",
			'postboxs' => $this->getDefinedTable(Sales\RegistrationTable::class)->getAll(),
			'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
			
		));
	}
	/**
	 *  Add Postbox action
	 */
	public function addpostboxAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()){
			
			$form = $this->getRequest()->getpost();	
			$postbox_fee = $this->getDefinedTable(Sales\PomasterTable::class)->getColumn(array('id'=>$form['post_box_size']),'amount');
			//print_r($postbox_fee);exit;
			if(empty($form['tphone'])):$tphone='0';else:$tphone=$form['tphone'];endif;
			if(empty($form['journal_no'])):$journal_no='0';else:$journal_no=$form['journal_no'];endif;
			if(empty($form['cid'])):$cid='0';else:$cid=$form['cid'];endif;
			$year = date('Y', strtotime($form['registration_date']));
			$postbox_no = $this->getDefinedTable(Sales\PostboxNumberTable::class)->getColumn(array('id'=>$form['postbox_no']),'po_number');
			$data = array(
				'region'              => $form['region'], 					 
				'location'            => $form['location'],
				'postbox_no'          => $form['postbox_no'],
				'post_box_size'       => $form['post_box_size'],
				'registration_date'   => $form['registration_date'],
				'name'                => $form['name'],
				'cid'                 => $cid,
				'mobile'              => $form['mobile'],
				'tphone'              => $tphone,
				'email'               => $form['email'],
				'organisation'        => $form['organizationName'],
				'building_no'         => $form['building_no'],
				'address'         	  => $form['address'],
				'journal_no'          => $journal_no,
				'year'          	  => $year,
				'status'              => '1', 
				'key_rate'            => $form['key_rate'],
				'po_rate'             => $form['po_rate'],
				'author'              => $this->_author,
				'created'             => $this->_created,
				'modified'            => $this->_modified
			);
			//echo'<pre>';print_r($data);exit;
			$data   = $this->_safedataObj->rteSafe($data);
            $this->_connection->beginTransaction();
			$poboxresult = $this->getDefinedTable(Sales\RegistrationTable::class)->save($data);
			/**Transaction */
				$region = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('id'=>$this->_author),'region');
				$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(9,'prefix');
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
			
				$data2 = array(
					'voucher_date' 	  	=> $form['registration_date'],
					'voucher_type' 	  	=> 9,
					'region' 	  		=> $region,
					'voucher_no' 	  	=> $voucher_no,
					'voucher_amount' 	=> $postbox_fee,
					'status' 	  		=> 4,
					'doc_id' 	  		=> "postbox",
					'doc_type' 	  		=> "",
					'remark' 	  		=> $postbox_no,
					'author' 			=>$this->_author,
					'created' 			=>$this->_created,
					'modified' 			=>$this->_modified,
				);
				$data2 =  $this->_safedataObj->rteSafe($data2);
				//$this->_connection->beginTransaction(); //***Transaction begins here***//*/
				$result2 = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data2);
				$data3 = array(
					'transaction' 	=> $result2,
					'voucher_dates' => $data2['voucher_date'],
					'voucher_types' 	=> 9,
					'location' 	  	=> $data['location'],
					'head' 	  		=> '152',
					'sub_head' 	  	=> '325',
					'activity'		=>$data['location'],
					'debit' 	  	=> 0,
					'credit' 	  	=> $data2['voucher_amount'],
					'ref_no' 	  	=> 0,
					'status' 	  		=> 4,
					'author' 			 =>$this->_author,
					'created' 			 =>$this->_created,
					'modified' 			 =>$this->_modified,
				);
				
				$data3 =  $this->_safedataObj->rteSafe($data3);
				$result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($data3);
				/**If paid in Cash */
				
				if($form['cash']==0):
					$bankacc = $form['bank_acc'];
					$subhead=0;
						$cash_subhead = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.ref_id'=>$bankacc,'sh.type'=>3));
						//echo '<pre>';print_r($cash_subhead);	
						foreach($cash_subhead  as $cash_subheads):
							$subhead=$cash_subheads['id'];	
								$head=$cash_subheads['head_id'];
						endforeach;	
					$data4 = array(
						'transaction' 	=> $result2,
						'voucher_dates' => $data2['voucher_date'],
						'voucher_types' 	=> 9,
						'location' 	  	=> $data['location'],
						'activity'			=>$data['location'],
						'head' 	  		=> $head,
						'sub_head' 	  	=> $subhead,
						'debit' 	  	=> $data2['voucher_amount'],
						'credit' 	  	=> 0,
						'status' 	  	=> 4,
						'bank_trans_journal'=>$form['journal_no'],
						'author' 		=>$this->_author,
						'created' 		=>$this->_created,
						'modified' 		=>$this->_modified,
					);
				else:/**If paid deposited into Bank Account  */
					$cashacc = $form['cash_acc'];
					$subhead=0;
					$head=0;
						$cash_subhead = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.ref_id'=>$cashacc,'sh.type'=>6));
						foreach($cash_subhead  as $cash_subheads):
							$subhead=$cash_subheads['id'];	
								$head=$cash_subheads['head_id'];	
						endforeach;	
					$data4 = array(
						'transaction' 	=> $result2,
						'voucher_dates' => $data2['voucher_date'],
						'voucher_types' => 9,
						'location' 	  	=> $data['location'],
						'activity'      => $data['location'],
						'head' 	  		=> $head,
						'sub_head' 	  	=> $subhead,
						'debit' 	  	=> $data2['voucher_amount'],
						'credit' 	  	=> 0,
						'status' 	  	=> 4,
						'author' 		=> $this->_author,
						'created' 		=> $this->_created,
						'modified' 		=> $this->_modified,
					);
				endif;
				/**Condtion ended */
				$data4 =  $this->_safedataObj->rteSafe($data4);
				$result4 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($data4);
				$data5 = array(
					'id' 	=> $poboxresult,
					'transaction_id' => $result2,
				);
				$data5 =  $this->_safedataObj->rteSafe($data5);
				$result5 = $this->getDefinedTable(Sales\RegistrationTable::class)->save($data5);
				$data6 = array(
					'id' 	=> $form['postbox_no'],
					'status' => 1,
				);
				$data6 =  $this->_safedataObj->rteSafe($data6);
				$result6 = $this->getDefinedTable(Sales\PostboxNumberTable::class)->save($data6);
				if($data6 > 0):
					$this->_connection->commit(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("success^ successfully added new data");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("notice^ Failed to add new data");
				endif;
				return $this->redirect()->toRoute('postbox', array('action' =>'addpostbox'));
		
		}	
		return new ViewModel( array(
			
			'title' 		  => "Add PostBox",
			'id'              =>$this->_id,
			'regionObj'       => $this->getDefinedTable(Administration\RegionTable::class),
			'location' 	      => $this->getDefinedTable(Administration\LocationTable::class),
			'postsizeObj'     => $this->getDefinedTable(Sales\PomasterTable::class),
			'user_location' => $this->_userloc,
			'user_role' => $this->_user->role,			
		));
	}
	/**
	 *  Add Old Postbox action
	 */
	public function addoldpostboxAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()){
			
			$form = $this->getRequest()->getpost();	
			$postbox_fee = $this->getDefinedTable(Sales\PomasterTable::class)->getColumn(array('id'=>$form['post_box_size']),'amount');
			//print_r($postbox_fee);exit;
			if(empty($form['tphone'])):$tphone='0';else:$tphone=$form['tphone'];endif;
			if(empty($form['journal_no'])):$journal_no='0';else:$journal_no=$form['journal_no'];endif;
			if(empty($form['cid'])):$cid='0';else:$cid=$form['cid'];endif;
			$year = date('Y', strtotime($form['registration_date']));
			$postbox_no = $this->getDefinedTable(Sales\PostboxNumberTable::class)->getColumn(array('id'=>$form['postbox_no']),'po_number');
			if($form['post_box_size']==2):echo $po_rate=1000;echo $key_rate=300; else:echo $po_rate=500;echo $key_rate=200; endif;
			$data = array(
				'region'              => $form['region'], 					 
				'location'            => $form['location'],
				'postbox_no'          => $form['postbox_no'],
				'post_box_size'       => $form['post_box_size'],
				'registration_date'   => $form['registration_date'],
				'name'                => $form['name'],
				'cid'                 => $cid,
				'mobile'              => $form['mobile'],
				'tphone'              => $tphone,
				'email'               => $form['email'],
				'organisation'        => $form['organizationName'],
				'building_no'         => $form['building_no'],
				'address'         	  => $form['address'],
				'journal_no'          => $journal_no,
				'year'          	  => $year,
				'status'              => '1', 
				'key_rate'            => $key_rate,
				'po_rate'             => $po_rate,
				'author'              => $this->_author,
				'created'             => $this->_created,
				'modified'            => $this->_modified
			);
			//echo'<pre>';print_r($data);exit;
			$data   = $this->_safedataObj->rteSafe($data);
            $this->_connection->beginTransaction();
			$poboxresult = $this->getDefinedTable(Sales\RegistrationTable::class)->save($data);
			$data2 = array(
				'id' 	=> $form['postbox_no'],
				'status' => 1,
			);
			$data2 =  $this->_safedataObj->rteSafe($data2);
			$result2 = $this->getDefinedTable(Sales\PostboxNumberTable::class)->save($data2);
			if($data2 > 0):
				$this->_connection->commit(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("success^ successfully added new data");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("notice^ Failed to add new data");
			endif;
			return $this->redirect()->toRoute('postbox', array('action' =>'addpostbox'));
		
		}	
		return new ViewModel( array(
			
			'title' 		  => "Add PostBox",
			'id'              =>$this->_id,
			'regionObj'       => $this->getDefinedTable(Administration\RegionTable::class),
			'location' 	      => $this->getDefinedTable(Administration\LocationTable::class),
			'postsizeObj'     => $this->getDefinedTable(Sales\PomasterTable::class),
			'user_location' => $this->_userloc,
			'user_role' => $this->_user->role,			
		));
	}
	
	/**
	 *  View Postbox action
	 */
	public function viewregAction()
	{
		$this->init();		
		return new ViewModel( array(
				'title' => "View Registration",
				'id'             =>$this->_id,
                'postboxs'       => $this->getDefinedTable(Sales\RegistrationTable::class)->getAll(),
				'regionObj'      => $this->getDefinedTable(Administration\LocationTable::class),
				'locationObj' 	 => $this->getDefinedTable(Administration\LocationTable::class),
		));
	}
	/**
	 *  Renew  action
	 */
	public function renewAction()
	{
		$this->init();
		return new ViewModel( array(
			 'title' => " Renew ",
			 'id'              =>$this->_id,
			 'postboxsObj' => $this->getDefinedTable(Sales\RegistrationTable::class),
			 'renews' => $this->getDefinedTable(Sales\RenewTable::class)->getAll(),
			 'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
			 'regionObj'       => $this->getDefinedTable(Administration\RegionTable::class),

		));
	}
	/**
	 *  Renew Postbox action
	 */
	public function renewpostAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()){

			$form = $this->getRequest()->getpost();		
			if(empty($form['journal_no'])):$journal_no='0';else:$journal_no=$form['journal_no'];endif;
			$year = date('Y', strtotime($form['renewal_date']));
			$postbox_no = $this->getDefinedTable(Sales\PostboxNumberTable::class)->getColumn(array('id'=>$form['postbox_no']),'po_number');			
			$data = array(
				
				'renewal_year'        => $year,
				'renewal_amount'      => $form['renewal_amount'],
				'registration_id'     => $form['registration_id'],
				'registration_name'     => $form['registration_name'],
				'postbox_no'     => $form['postbox_no'],
				'registration_year'     => $form['registration_year'],
				'renewal_date'     => $form['renewal_date'],
				'status'              => 1, 
				'author'              => $this->_author,
				'created'             => $this->_created,
				'modified'            => $this->_modified
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$poboxrenewresult = $this->getDefinedTable(Sales\RenewTable::class)->save($data);
				$region = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('id'=>$this->_author),'region');
				$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(9,'prefix');
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
			
				$data2 = array(
					'voucher_date' 	  	=> $form['renewal_date'],
					'voucher_type' 	  	=> 9,
					'region' 	  		=> $region,
					'voucher_no' 	  	=> $voucher_no,
					'voucher_amount' 	=> $form['renewal_amount'],
					'status' 	  		=> 4,
					'doc_id' 	  		=> "postbox renew",
					'doc_type' 	  		=> "",
					'remark' 	  		=> $postbox_no,
					'author' 			 =>$this->_author,
					'created' 			 =>$this->_created,
					'modified' 			 =>$this->_modified,
				);
				$data2 =  $this->_safedataObj->rteSafe($data2);
				
				$result2 = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data2);
				$data3 = array(
					'transaction' 	=> $result2,
					'voucher_dates' => $data2['voucher_date'],
					'voucher_types' 	=> 9,
					'location' 	  	=> $form['location'],
					'head' 	  		=> '152',
					'sub_head' 	  	=> '325',
					'activity'		=>$form['location'],
					'debit' 	  	=> 0,
					'credit' 	  	=> $data2['voucher_amount'],
					'ref_no' 	  	=> 0,
					'status' 	  		=> 4,
					'author' 			 =>$this->_author,
					'created' 			 =>$this->_created,
					'modified' 			 =>$this->_modified,
				);
				$data3 =  $this->_safedataObj->rteSafe($data3);
				$result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($data3);
				/**If paid in Cash */
				
				if($form['cash']==0):
					$bankacc = $form['account_no'];
					$subhead=0;
					$cash_subhead = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.ref_id'=>$bankacc,'sh.type'=>3));
						//echo '<pre>';print_r($cash_subhead);	
						foreach($cash_subhead  as $cash_subheads):
							$subhead=$cash_subheads['id'];	
								$head=$cash_subheads['head_id'];
						endforeach;	
					$data4 = array(
						'transaction' 	=> $result2,
						'voucher_dates' => $data2['voucher_date'],
						'voucher_types' 	=> 9,
						'location' 	  	=> $form['location'],
						'activity'			=>$form['location'],
						'head' 	  		=> $head,
						'sub_head' 	  	=> $subhead,
						'debit' 	  	=> $data2['voucher_amount'],
						'credit' 	  	=> 0,
						'status' 	  	=> 4,
						'bank_trans_journal'=>$form['journal_no'],
						'author' 		=>$this->_author,
						'created' 		=>$this->_created,
						'modified' 		=>$this->_modified,
					);
				else:/**If paid deposited into Bank Account  */
					$cashacc = $form['account_no'];
					$subhead=0;
					$head=0;
						$cash_subhead = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.ref_id'=>$cashacc,'sh.type'=>6));
						foreach($cash_subhead  as $cash_subheads):
							$subhead=$cash_subheads['id'];	
								$head=$cash_subheads['head_id'];	
						endforeach;	
					$data4 = array(
						'transaction' 	=> $result2,
						'voucher_dates' => $data2['voucher_date'],
						'voucher_types' => 9,
						'location' 	  	=> $form['location'],
						'activity'      => $form['location'],
						'head' 	  		=> $head,
						'sub_head' 	  	=> $subhead,
						'debit' 	  	=> $data2['voucher_amount'],
						'credit' 	  	=> 0,
						'status' 	  	=> 4,
						'author' 		=> $this->_author,
						'created' 		=> $this->_created,
						'modified' 		=> $this->_modified,
					);
				endif;
				/**Condtion ended */
				$data4 =  $this->_safedataObj->rteSafe($data4);
				$result4 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($data4);
				$data5 = array(
					'id' 	=> $poboxrenewresult,
					'transaction_id' => $result2,
				);
				$data5 =  $this->_safedataObj->rteSafe($data5);
				$result5 = $this->getDefinedTable(Sales\RenewTable::class)->save($data5);
				if($poboxrenewresult > 0):
					$this->_connection->commit(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("success^ successfully added new data");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("notice^ Failed to add new data");
				endif;
				return $this->redirect()->toRoute('postbox', array('action' =>'viewrenew', 'id' => $form['registration_year']));
		}	
		$ViewModel = new ViewModel(array(
			'title' 		  => "Renewpost",
			'id'              => $this->_id,
			'renews'    	  => $this->getDefinedTable(Sales\RenewTable::class),
			'postboxs'        => $this->getDefinedTable(Sales\RegistrationTable::class)->get($this->_id),
			'regionObj'       => $this->getDefinedTable(Administration\RegionTable::class),
			'statusObj'       => $this->getDefinedTable(Acl\StatusTable::class),
			'location' 	      => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'cashacc' 	      => $this->getDefinedTable(Accounts\CashaccountTable::class),
			'bankacc' 	      => $this->getDefinedTable(Accounts\BankaccountTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
		
	}
	/**
	 * fuel action
	 */
	public function viewpoAction()
	{
		$this->init();
		// echo 'hi';exit;
		return new ViewModel( array(
			'title' => "View PO",
			'postbox' => $this->getDefinedTable(Sales\RegistrationTable::class)->get($this->_id),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'poboxObj' => $this->getDefinedTable(Sales\PostboxNumberTable::class),
			'posizeObj' => $this->getDefinedTable(Sales\PomasterTable::class),
			'renew' => $this->getDefinedTable(Sales\RenewTable::class),
		));
	}
	/**
	 *  Edit Postbox action
	 */
	public function editregdetailsAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()){
			
			$form = $this->getRequest()->getpost();	
			//$postbox_fee = $this->getDefinedTable(Sales\PomasterTable::class)->getColumn(array('id'=>$form['post_box_size']),'amount');
			if(empty($form['tphone'])):$tphone='0';else:$tphone=$form['tphone'];endif;
			//if(empty($form['journal_no'])):$journal_no='0';else:$journal_no=$form['journal_no'];endif;
			$year = date('Y', strtotime($form['registration_date']));
			 $this->_connection->beginTransaction();
			$data = array(
				'id'              => $form['id'], 		
				'region'              => $form['region'], 					 
				'location'            => $form['location'],
				//'postbox_no'          => $form['postbox_no'],
				//'post_box_size'       => $form['post_box_size'],
				'registration_date'   => $form['registration_date'],
				'name'                => $form['name'],
				'cid'                 => $form['cid'],
				'mobile'              => $form['mobile'],
				'tphone'              => $tphone,
				'email'               => $form['email'],
				'organisation'        => $form['organizationName'],
				'building_no'         => $form['building_no'],
				'address'         	  => $form['address'],
				//'journal_no'          => $journal_no,
				'year'          	  => $year,
				'status'              => '1', 
				'author'              => $this->_author,
				'created'             => $this->_created,
				'modified'            => $this->_modified
			);
			//echo'<pre>';print_r($data);exit;
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\RegistrationTable::class)->save($data);
				
				if($result > 0):
					$this->_connection->commit(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("success^ successfully edited  data");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("notice^ Failed to edit data");
				endif;
				return $this->redirect()->toRoute('postbox', array('action' =>'editregdetails','id'=>$form['id']));
		
		}	
		return new ViewModel( array(
			
			'title' 		  => "Add PostBox",
			'id'              =>$this->_id,
			'regionObj'       => $this->getDefinedTable(Administration\RegionTable::class),
			'location' 	      => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'postsizeObj'     => $this->getDefinedTable(Sales\PomasterTable::class),
			'postbox'     => $this->getDefinedTable(Sales\RegistrationTable::class)->get($this->_id),
		));
	}
	/**
	 * Cancel Postbox action
	 */
	public function cancelpoAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()){
			
			$form = $this->getRequest()->getpost();	
			$data = array(
				'id'              => $form['id'], 		
				'status'              => '5', 
				'author'              => $this->_author,
				'created'             => $this->_created,
				'modified'            => $this->_modified
			);
			//echo'<pre>';print_r($data);exit;
			$data   = $this->_safedataObj->rteSafe($data);
			 $this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Sales\RegistrationTable::class)->save($data);
				
				if($result > 0):
				 foreach($this->getDefinedTable(Sales\RegistrationTable::class)->get($result) as $por);
				$data1 = array(
				'id'              => $por['postbox_no'], 		
				'status'              => '5', 
				'author'              => $this->_author,
				'modified'            => $this->_modified
			);
			//echo'<pre>';print_r($data);exit;
			$data1   = $this->_safedataObj->rteSafe($data1);
			$result1 = $this->getDefinedTable(Sales\PostboxNumberTable::class)->save($data1);
					$this->_connection->commit(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("success^ successfully edited  data");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("notice^ Failed to edit data");
				endif;
				return $this->redirect()->toRoute('postbox', array('action' =>'viewrenew'));
		
		}	
		$ViewModel = new ViewModel(array(
			'title' 		  => "Cancel PostBox",
			'postbox'     => $this->getDefinedTable(Sales\RegistrationTable::class)->get($this->_id),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/*
	 * Delete Registration Action
	 */ 
	public function deleteporegAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
				$year=$form['year'];
			$transaction_id = $this->getDefinedTable(Sales\RegistrationTable::class)->getColumn($form['post_id'],'transaction_id');
			/**Delete from Transaction Table */
			$transac_data = array(
				'id' => $transaction_id,
			);
			$result2 = $this->getDefinedTable(Accounts\TransactionTable::class)->remove($transac_data);
			$transactiondetails_id = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->getTransaction(array('transaction'=>$transaction_id));
			/**Delete from Transaction Details Table */	
			foreach($transactiondetails_id as $transactiondetails_ids):
				$transacdtls_data = array(
					'id'=>$transactiondetails_ids['id'],
					'transaction' => $transaction_id,
				);
				$result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->remove($transacdtls_data);
			endforeach;
			$postdata = array(
					'id' => $form['post_id'],
			);
			$result4= $this->getDefinedTable(Sales\RegistrationTable::class)->remove($postdata);
			$postnumber = array(
					'id' => $form['postbox_no'],
					'status' => 0,
			);
			//print_r($postnumber);exit;
			$result5= $this->getDefinedTable(Sales\PostboxNumberTable::class)->save($postnumber);
			if($result5 > 0):
				$this->flashMessenger()->addMessage("success^ successfully deleted the transactions");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to delete  the transactions");
			endif;
			return $this->redirect()->toRoute('postbox', array('action'=>'viewrenew','id'=>$year));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Delete Post Box Registration',
			'postboxreg' => $this->getDefinedTable(Sales\RegistrationTable::class)->get($this->_id),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/*
	 * Delete Renewed postbox Action
	 */ 
	public function deleterenewAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year=$form['registration_year'];
			
			$transaction_id = $this->getDefinedTable(Sales\RenewTable::class)->getColumn($form['renew_id'],'transaction_id');
			/**Delete from Transaction Table */
			$transac_data = array(
				'id' => $transaction_id,
			);
			$result2 = $this->getDefinedTable(Accounts\TransactionTable::class)->remove($transac_data);
			$transactiondetails_id = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->getTransaction(array('transaction'=>$transaction_id));
			/**Delete from Transaction Details Table */	
			foreach($transactiondetails_id as $transactiondetails_ids):
				$transacdtls_data = array(
					'id'=>$transactiondetails_ids['id'],
					'transaction' => $transaction_id,
				);
				$result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->remove($transacdtls_data);
			endforeach;
			$postdata = array(
					'id' => $form['renew_id'],
			);
			$result4 = $this->getDefinedTable(Sales\RenewTable::class)->remove($postdata);
			if($result4 > 0):
				$this->flashMessenger()->addMessage("success^ successfully deleted the transactions");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to delete  the transactions");
			endif;
			return $this->redirect()->toRoute('postbox', array('action'=>'viewrenew','id'=>$year));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Delete Post Box Renewal',
			'renew' => $this->getDefinedTable(Sales\RenewTable::class)->get($this->_id),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *  Edit Renew action
	 */
    public function editrenewAction()
    {
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest();
			$data=array(
					'id' => $this->_id,
					
					'renewal_year' => $form->getPost('renewal_year'),
					'renewal_amount' => $form->getPost('renewal_amount'),
					
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Postbox\RenewTable::class)->save($data);

			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update");
			endif;
			return $this->redirect()->toRoute('renew',array('action' => 'viewrenew'));
		}
		return new ViewModel(array(
				'renews' => $this->getDefinedTable(Sales\RenewTable::class)->get($this->_id),
			));
	}
	/**
	 *  View Renew action
	 */
	public function viewrenewAction()
	{
		$this->init();
			$array_id = explode("_", $this->_id);
			$location = (sizeof($array_id)>1)?$array_id[0]:'-1';
			$postbox = (sizeof($array_id)>1)?$array_id[1]:'-1';
			if($this->getRequest()->isPost())
			{
				$form      	 = $this->getRequest()->getPost();
				$location     = $form['location'];
				$postbox     = $form['postbox'];
				$start_date     = $form['start_date'];
				$end_date     = $form['end_date'];
				$status     = $form['status'];
			}else{
				$location ='-1';
				$postbox = '-1';
				$status = '-1';
				$start_date =date('Y-m-d');
				$end_date   = date('Y-m-d');
			}

			$data = array(
				'location'    => $location,
				'postbox'  => $postbox,
				'status'  => $status,
				'start_date' => $start_date,
				'end_date'   => $end_date,
			);
			$postboxTable = $this->getDefinedTable(Sales\RegistrationTable::class)->getPOList($data);
				//echo '<pre>';print_r($postboxTable);exit;
			
		return new ViewModel([
			'title'     => "View Renew",
			'id'        => $this->_id,
			'postboxTable'      => $postboxTable,
			'data'            => $data,
			'renews'    => $this->getDefinedTable(Sales\RenewTable::class),
			//'postboxs'  => $this->getDefinedTable(Sales\RegistrationTable::class)->getDateWise('registration_date',$this->_id),
			'postboxs'  => $this->getDefinedTable(Sales\RegistrationTable::class)->getAll(),
			'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
			'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
			'ponumberObj' 	=> $this->getDefinedTable(Sales\PostboxNumberTable::class),
			'statusObj' 	=> $this->getDefinedTable(Acl\StatusTable::class),
	]);
	}
	/**
	 *  Print action
	 */
	public function printAction()
	{
		$this->init();
	
		return new ViewModel([
			'title'     => "View Renew",
			'id'        => $this->_id,
			'renews'    => $this->getDefinedTable(Sales\RenewTable::class),
			'postboxs'  => $this->getDefinedTable(Sales\RegistrationTable::class)->getDateWise('registration_date',$this->_id),
			'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
			'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),

			
		]);
	}
	public function poreportAction()
	{
		{	
			$this->init();
			$array_id = explode("_", $this->_id);
			$location = (sizeof($array_id)>1)?$array_id[0]:'-1';
			$postbox = (sizeof($array_id)>1)?$array_id[1]:'-1';
			if($this->getRequest()->isPost())
			{
				$form      	 = $this->getRequest()->getPost();
				$location     = $form['location'];
				$postbox     = $form['postbox'];
				$start_date = $form['start_date'];
				$end_date   = $form['end_date'];
				$status		= $form['status'];
			}else{
				$location ='-1';
				$postbox = '-1';
				$start_date =date('Y-m-d');
				$end_date   = date('Y-m-d');
				$status     ='-1';
			}

			$data = array(
				'location'    => $location,
				'postbox'  => $postbox,
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'status'     => $status,
			);
			$postboxTable = $this->getDefinedTable(Sales\RegistrationTable::class)->getPOReport($data,$start_date,$end_date);
			return new ViewModel(array(
				'title' => 'Post Box Report',
				'paginator'       => $postboxTable,
				'data'            => $data,
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'ponumberObj' => $this->getDefinedTable(Sales\PostboxNumberTable::class),
				'posizeObj' => $this->getDefinedTable(Sales\PomasterTable::class),
			)); 
		} 

		
	}
	/**
	 * Get Location
	 */
	public function getlocationAction()
	{		
		$form = $this->getRequest()->getPost();
		$regId = $form['regId'];
		$reg= $this->getDefinedTable(Administration\LocationTable::class)->get(array('region'=>$regId));
		
		$location = "<option value=''></option>";
		foreach($reg as $regs):
			$location.="<option value='".$regs['id']."'>".$regs['location']."</option>";
		endforeach;
			echo json_encode(array(
				'location' => $location,
		));
		exit;
	}
	/**
	 * Get post box
	 */
	public function getpostboxAction()
	{		
		$form = $this->getRequest()->getPost();
		$regId = $form['regId'];
		$locId = $form['locId'];
		$pbox= $this->getDefinedTable(Sales\PostboxNumberTable::class)->get(array('region'=>$regId,'location'=>$locId,'status'=>[0,5]));
		
		$postbox_no = "<option value=''></option>";
		foreach($pbox as $pboxs):
			$postbox_no.="<option value='".$pboxs['id']."'>".$pboxs['po_number']."</option>";
		endforeach;
			echo json_encode(array(
				'postbox_no' => $postbox_no,
		));
		exit;
	}
	/**
	 * Get PO size
	 */
	public function getposizeAction()
	{       
		$form = $this->getRequest()->getPost();
		$poId = $form['poId'];
		$po= $this->getDefinedTable(Sales\PostboxNumberTable::class)->get(array('id'=>$poId));
		foreach($po as $pos);
		$posize=$this->getDefinedTable(Sales\PomasterTable::class)->get(array('id'=>$pos['po_size']));
		
		$post_box_size = "<option value=''></option>";
		foreach($posize as $posizes):
			$selected = ($posizes['id'] == $pos['po_size']) ? 'selected' : ''; // Check if the option should be selected
			$post_box_size.="<option value='".$posizes['id']."' $selected>".$posizes['post_box_size']."</option>";
			$po_rate=$posizes['rate'];
			$key_rate=$posizes['key_charges'];
		endforeach;
		
		echo json_encode(array(
			'post_box_size' => $post_box_size,
			'selected_value' => $pos['po_size'],
			'po_rate' => $po_rate,
			'key_rate' => $key_rate			// Send the selected value
		));
		exit;
	}
	
/**
	 * Get bank and cash account
	 */
	public function getbankcashaccAction()
	{		
		$form = $this->getRequest()->getPost();
		$locId = $form['locId'];
		$bank= $this->getDefinedTable(Accounts\BankaccountTable::class)->getbankaccount(array('location'=>$locId));
		$cash= $this->getDefinedTable(Accounts\CashaccountTable::class)->getcashaccount(array('location'=>$locId));
		$bankaccount = "<option value=''></option>";
		$cashaccount = "<option value=''></option>";
		foreach($cash as $cashs):
			$cashaccount.="<option value='".$cashs['id']."'>".$cashs['cash_account_code'].'-'.$cashs['cash_account_name']."</option>";
		endforeach;
		foreach($bank as $banks):
			$bankaccount.="<option value='".$banks['id']."'>".$banks['branch'].'-'.$banks['account']."</option>";
		endforeach;
			echo json_encode(array(
				'cashaccount' => $cashaccount,
				'bankaccount' => $bankaccount,
		));
		exit;
	}
	/**
	 * getacount - Get item based on location
	 * **/
	public function getBCAccountAction()
	{
		$form = $this->getRequest()->getPost();
		$payment =$form['paymentId'];
		$locationID=$form['locationId'];
		if($payment==1){
			$accountlist= $this->getDefinedTable(Accounts\CashaccountTable::class)->get(array('ca.location'=>$locationID));
		}
		else{
			$accountlist= $this->getDefinedTable(Accounts\BankaccountTable::class)->get(array('ba.location'=>$locationID));	
		
		}
		$acc = "<option value=''>None</option>";
		foreach($accountlist as $accountlists):
			if($payment==1){
			$acc.="<option value='".$accountlists['id']."'>".$accountlists['cash_account_name']."</option>";
			}
			else{
				$acc.="<option value='".$accountlists['id']."'>".$accountlists['code'].'-'.$accountlists['account']."</option>";
			}
		endforeach;
		echo json_encode(array(
				'account' => $acc,
		));
		exit;
	}
	/**
	 * checkavailability Action
	**/
	public function getcheckavailabilityAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		$type='postbox_no';
		switch ($type) {
			case 'postbox_no':
				$postbox_no =$form['postbox_no'];
				
				// Check the item code existence ...
				$result = $this->getDefinedTable(Sales\PostboxNumberTable::class)->isPresent('po_number', $postbox_no);
				break;

			
		}
		
		echo json_encode(array(
					'valid' => $result,
		));
		exit;
	}
	
}
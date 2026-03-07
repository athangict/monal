<?php
namespace Sales\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Accounts\Model As Accounts;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Stock\Model As Stock;
use Sales\Model As Sales;
use Hr\Model As Hr;
class MuseumController extends AbstractActionController
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
	protected $_userloc; //location of the current user
	

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
		
	
		if(!isset($this->_userloc)){
			$this->_userloc = $this->_user->location;  
		}
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
    /**
	 * index Action of MasterController
	 */
    public function indexAction()
    {  
    	$this->init();
		return new ViewModel(array(
			'title' => 'index',
		));
	}
	/**
	 *  Master action
	 */
    public function feesAction()
	{
		$this->init();
        return new ViewModel(array(
			'title'            => 'Fees',
			'fees' => $this->getDefinedTable(Sales\MuseumFeeTable::class)->getAll(),
			'category' => $this->getDefinedTable(Sales\CategoryTable::class),
		)); 
	}
	/**
	 * add fees type
	 */
    public function addfeesAction()
    {
		$this->init();
		$page = $this->_id;
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			$data = array(
				'fee'  => $form['fees'],
				'category'    => $form['category'],
				'status'         => 1,
				'author'         => $this->_author,
				'created'        => $this->_created,
				'modified'       => $this->_modified
			);
			$result = $this->getDefinedTable(Sales\MuseumFeeTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added new Fee."); 	             
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new Fee.");	 	             
			endif;
			return $this->redirect()->toRoute('museum', array('action'=>'fees'));
		}
		$ViewModel = new ViewModel([
			'title'        => 'Add Fees',
			'category'         => $this->getDefinedTable(Sales\CategoryTable::class)->getAll(),
		]); 
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * edit fees type
	 */
    public function editfeesAction()
    {
		$this->init();
		$page = $this->_id;
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			$data = array(
				'id'			=>$this->_id,
				'fee' 			=> $form['fees'],
				'category'    	=> $form['category'],
				'status'         => 1,
				'author'         => $this->_author,
				'created'        => $this->_created,
				'modified'       => $this->_modified
			);
			$result = $this->getDefinedTable(Sales\MuseumFeeTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added new Fee."); 	             
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new Fee.");	 	             
			endif;
			return $this->redirect()->toRoute('museum', array('action'=>'fees'));
		}
		$ViewModel = new ViewModel([
			'title'        => 'Add Fees',
			'category'         => $this->getDefinedTable(Sales\CategoryTable::class)->getAll(),
			'fees'         => $this->getDefinedTable(Sales\MuseumFeeTable::class)->get($this->_id),
		]); 
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *  Category action
	 */
    public function categoryAction()
	{
		$this->init();
        return new ViewModel(array(
			'title'            => 'Category',
			'category' => $this->getDefinedTable(Sales\CategoryTable::class)->getAll(),
		)); 
	}
	/**
	 * add category Action
	 */
    public function addcategoryAction()
    {
		$this->init();
		$page = $this->_id;
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			$data = array(
				'category'  => $form['category'],
				'status'         => 1,
				'author'         => $this->_author,
				'created'        => $this->_created,
				'modified'       => $this->_modified
			);
			$result = $this->getDefinedTable(Sales\CategoryTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added new Category."); 	             
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new Category.");	 	             
			endif;
			return $this->redirect()->toRoute('museum', array('action'=>'category'));
		}
		$ViewModel = new ViewModel([
			'title'        => 'Add Category',
			'category'         => $this->getDefinedTable(Sales\MuseumFeeTable::class)->getAll(),
		]); 
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * add category Action
	 */
    public function editcategoryAction()
    {
		$this->init();
		$page = $this->_id;
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			$data = array(
				'id'=>$this->_id,
				'category'  => $form['category'],
				'status'         => 1,
				'author'         => $this->_author,
				'created'        => $this->_created,
				'modified'       => $this->_modified
			);
			$result = $this->getDefinedTable(Sales\CategoryTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added new Category."); 	             
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new Category.");	 	             
			endif;
			return $this->redirect()->toRoute('museum', array('action'=>'category'));
		}
		$ViewModel = new ViewModel([
			'title'        => 'Add Category',
			'category'         => $this->getDefinedTable(Sales\CategoryTable::class)->get($this->_id),
		]); 
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
		/**
	 *  Category action
	 */
    public function feecollectionAction()
	{
		$this->init();
        return new ViewModel(array(
			'title'            => 'Fee Collection',
			'collection' => $this->getDefinedTable(Sales\CollectionTable::class)->getAll(),
			'category' => $this->getDefinedTable(Sales\CategoryTable::class),
			'fees' => $this->getDefinedTable(Sales\MuseumFeeTable::class),
		)); 
	}
	/**
	 * add category Action
	 */
    public function addfeecollectionAction()
    {
		$this->init();
		$page = $this->_id;
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			$phone=($form['payment_mode']==1)?$form['mobile']:0;
			$data = array(
				'date'		=> $form['date'],
				'category'  => $form['category'],
				'fees'  	=> $form['fees'],
				'heads'  	=> $form['heads'],
				'payment_mode'=>$form['payment_mode'],
				'total'  	=> $form['total'],
				'jrnl_no'  	=> $form['jrnl_no'],
				'mobile'  	=> $phone,
				'status'    => 2,
				'author'    => $this->_author,
				'created'   => $this->_created,
				'modified'  => $this->_modified
			);
			$result = $this->getDefinedTable(Sales\CollectionTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added new Category."); 	             
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new Category.");	 	             
			endif;
			return $this->redirect()->toRoute('museum', array('action'=>'view','id'=>$result));
		}
		$ViewModel = new ViewModel([
			'title'        => 'Add Fee Collection',
			'category'         => $this->getDefinedTable(Sales\CategoryTable::class)->getAll(),
			'fees'         => $this->getDefinedTable(Sales\MuseumFeeTable::class),
		]); 
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * add category Action
	 */
    public function editfeecollectionAction()
    {
		$this->init();
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			$phone=($form['payment_mode']=='1')?$form['mobile']:0;
			$jrn=($form['payment_mode']=='1')?$form['jrnl_no']:"";
			$data = array(
				'id'			=>$this->_id,
				'category'  	=> $form['category'],
				'fees'  	 	=> $form['fees'],
				'heads'  	 	=> $form['heads'],
				'total'  	 	=> $form['total'],
				'jrnl_no'  		=> $jrn,
				'payment_mode'=>$form['payment_mode'],
				'mobile'  		=> $phone,
				'status'        => 2,
				'author'        => $this->_author,
				'created'       => $this->_created,
				'modified'      => $this->_modified
			);
			$result = $this->getDefinedTable(Sales\CollectionTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added new Category."); 	             
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new Category.");	 	             
			endif;
			return $this->redirect()->toRoute('museum', array('action'=>'view','id'=>$this->_id));
		}
		$ViewModel = new ViewModel([
			'title'        => 'Add Fee Collection',
			'collection'         => $this->getDefinedTable(Sales\CollectionTable::class)->get($this->_id),
			'category'         => $this->getDefinedTable(Sales\CategoryTable::class)->getAll(),
			'fees'         => $this->getDefinedTable(Sales\MuseumFeeTable::class),
		]); 
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *  view action
	 */
    public function viewAction()
	{
		$this->init();
        return new ViewModel(array(
			'title'            => 'View',
			'collection' => $this->getDefinedTable(Sales\CollectionTable::class)->get($this->_id),
			'category' => $this->getDefinedTable(Sales\CategoryTable::class),
			'fees' => $this->getDefinedTable(Sales\MuseumFeeTable::class),
		)); 
	}
	/**
	 * Commit Action
	 */
    public function commitAction()
    {
		$this->init();
		$page = $this->_id;
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			/**
			 * Generating voucher no
			 */
			$source_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($source_locs, 'prefix');
			$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(14,'prefix');
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
			/**
			 * Generating voucher no ended
			 */
			 $region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($source_locs,'region');
			 $data = array(
				'id'  	 => $this->_id,
				'status'         => 4,
				'author'         => $this->_author,
				'created'        => $this->_created,
				'modified'       => $this->_modified
			);
			$result = $this->getDefinedTable(Sales\CollectionTable::class)->save($data);
			if($result>0):
			foreach($this->getDefinedTable(Sales\CollectionTable::class)->get($this->_id) as $museum);
			$data1 = array(
					'voucher_date' => $museum['date'],
					'voucher_type' => 14,
					'region'   =>$region,
					'doc_id'   =>"museum",
					'voucher_no' => $voucher_no,
					'voucher_amount' => str_replace( ",", "",$museum['total']),
					'remark' =>$museum['id'],
					'status' => 4, // status initiated 
					'author' =>$this->_author,
					'created' =>$this->_created,  
					'modified' =>$this->_modified,
				);
				$resultt = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
				$tdetailsdata = array(
					'transaction' => $resultt,
					'voucher_dates' => $museum['date'],
					'voucher_types' => 14,
					'location' => $source_locs,
					'head' =>152,
					'sub_head' =>323,
					'bank_ref_type' => '',
					'debit' =>'0.000',
					'credit' =>$museum['total'],
					'ref_no'=>'', 
					'type' => '1',//user inputted  data
					'status' => 4, // status initiated
					'activity'=>$source_locs,
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);
				$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
				$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
				if($museum['payment_mode']==0){
					$head=55;
					$subhead=526;
				}
				else{
					$head=28;
					$subhead=180;
				}
				$tdetailsdata = array(
					'transaction' => $resultt,
					'voucher_dates' => $museum['date'],
					'voucher_types' => 14,
					'location' => $source_locs,
					'head' =>$head,
					'sub_head' =>$subhead,
					'bank_ref_type' => '',
					'debit' =>$museum['total'],
					'credit' =>'0.000',
					'ref_no'=>'', 
					'bank_trans_journal'=>$museum['jrnl_no'],
					'type' => '1',//user inputted  data
					'status' => 4, // status initiated
					'activity'=>$source_locs,
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);
				$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
				$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
				$this->flashMessenger()->addMessage("success^ Successfully added new Category."); 	             
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new Category.");	 	             
			endif;
			return $this->redirect()->toRoute('museum', array('action'=>'feecollection'));
		}
		$ViewModel = new ViewModel([
			'title'        => 'Add Fee Collection',
			'collection'         => $this->getDefinedTable(Sales\CollectionTable::class)->get($this->_id),
			'fees'         => $this->getDefinedTable(Sales\MuseumFeeTable::class),
		]); 
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	public function reportAction()
	{
		$this->init();
			$array_id = explode("_", $this->_id);
			$category = (sizeof($array_id)>1)?$array_id[0]:'-1';
			if($this->getRequest()->isPost())
			{
				$form      	 = $this->getRequest()->getPost();
				$start_date = $form['start_date'];
				$end_date   = $form['end_date'];
				$category   = $form['category'];
			}else{
				$start_date =date('Y-m-d');
				$end_date   = date('Y-m-d');
				$category   = '-1';
			}

			$data = array(
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'category'  => $category,
			);
		
			$collectionTable = $this->getDefinedTable(Sales\CollectionTable::class)->getMuseumReport($data,$start_date,$end_date);
			$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($collectionTable));
			
			$page = 1;
			if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
			$paginator->setCurrentPageNumber((int)$page);
			$paginator->setItemCountPerPage(1000);
			$paginator->setPageRange(8);
			
			return new ViewModel(array(
				'title' => 'Museum Report',
				'paginator'       => $paginator,
				'data'            => $data,
				'page'            => $page,
				'categoryObj' => $this->getDefinedTable(Sales\CategoryTable::class),
				'category' => $this->getDefinedTable(Sales\CategoryTable::class)->getAll(),
			)); 
	}
	
	/**
	 * getrate - Get rate based on item and location
	 * **/
	public function getfeesAction()
	{
		$form = $this->getRequest()->getPost();
		$category =$form['category'];
		$feeslist = $this->getDefinedTable(Sales\MuseumFeeTable::class)->get(array('category'=>$category));
		foreach($feeslist as $feeslist):
			$feesOptions = $feeslist['fee'];
		endforeach;

		echo json_encode(array(
			'fees' => $feesOptions,
		));

		exit;
	}
	
}

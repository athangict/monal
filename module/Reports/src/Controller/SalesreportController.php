<?php
namespace Reports\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Sales\Model As Sales;
use Stock\Model As Stock;
class SalesreportController extends AbstractActionController
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
		
		$this->_id = $this->params()->fromRoute('id');
		
	    $this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		//$this->_safedataObj = $this->SafeDataPlugin();
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();
		

	}
	
	public function indexAction()
	{
		$this->init();
		return new ViewModel();
	}
	
   /**
	 * Customer OutStanding Statement
	 */
	public function customeroutstandingAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
			$customer = $form['customer'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
		endif;
		$data = array(
			'location' => $location,
			'customer' => $customer,
			'start_date' => $start_date,
			'end_date' => $end_date,
		);
		
		return new ViewModel(array(
				'title' => "Customer Outstanding Statement",
				'data'          => $data,
				'regions'       => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
				'customerObj'   => $this->getDefinedTable(Accounts\PartyTable::class),
				'salesObj'      => $this->getDefinedTable(Sales\SalesTable::class),
				'receiptObj'    => $this->getDefinedTable(Sales\ReceiptTable::class),
			    'receiptDtlObj' => $this->getDefinedTable(Sales\ReceiptDtlsTable::class),
		));
	}
	/**
	 * get items by activity
	**/
	public function getitemactivityAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		
		$activity_id = $form['activity'];
		$stock_items = $this->getDefinedTable(Stock\BatchTable::class)->getSMDistinctItems(array('i.activity'=>$activity_id),'-1');
		$items .="<option value='-1'>All</option>";
		foreach($stock_items as $stock_item):
			$items .="<option value='".$stock_item['item_id']."'>".$stock_item['code']."</option>";
		endforeach;
		
		echo json_encode(array(
				'item' => $items,
				'selected_item' => -1,
		));
		exit;
	}
	
	/**
	 * Sales Statement
	**/
	public function salesstatementAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$activity = $form['activity'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
		endif;
		$data = array(
			'activity' => $activity,
			'start_date' => $start_date,
			'end_date' => $end_date,
		);
		
		return new ViewModel(array(
				'title' => "Sales Statement",
				'data'  => $data,
				'salesObj' => $this->getDefinedTable(Sales\SalesTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
		));
	}
	/**
	 * Product Wise Sales Report
	**/
	public function productwiseAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$data = array(
					'location' => $form['location'],
					'activity' => $form['activity'],
					'payment' => $form['payment'],
					'start_date' => $form['start_date'],
					'end_date' => $form['end_date'],
					'item_details' => $form['item_details'],
			);
		else:
			$data = array(
					'location' => '-1',
					'activity' => '',
					'payment' => '-1',
					'item_details' => '',
			);
		endif;
		
		return new ViewModel(array(
				'title' => 'Product Wise Sales Report',
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'data' => $data,
				'salesObj' => $this->getDefinedTable(Sales\SalesTable::class),
				'itemuomObj' => $this->getDefinedTable(Stock\ItemUomTable::class),
		));
	}
	/**
	 * Sales Register
	**/
	public function salesregisterAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$data = array(
					'region' => $form['region'],
					'location' => $form['location'],
					'activity' => $form['activity'],
					'itemgroup' => $form['itemgroup'],
					'payment' => $form['payment'],
					'customer' => $form['customer'],
					'start_date' => $form['start_date'],
					'end_date' => $form['end_date'],
			);
			//echo "<pre>"; print_r($data); exit;
		else://Administration\LocationTable::class
			$data = array(
					'region' => '-1',
					'location' => '-1',
					'activity' => '-1',
					'itemgroup' => '-1',
					'payment' => '-1',
					'customer' => '-1',
			);
		endif;
		
		return new ViewModel(array(
				'title' => 'Sales Register',
				'data' => $data,
				'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'itemgroupObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
				'partyObj' => $this->getDefinedTable(Accounts\PartyTable::class),
				'salesObj' => $this->getDefinedTable(Sales\SalesTable::class),
				'itemuomObj' => $this->getDefinedTable(Stock\ItemUomTable::class),
		));
	}
	/**
	 * get Location By Region
	**/
	public function getlocationAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		$region_id = $form['region'];
		
		$locations = $this->getDefinedTable(Administration\LocationTable::class)->getSalesLocation($region_id);
		$loc .="<option value='-1'>All</option>";
		foreach($locations as $location):
			$loc.= "<option value='".$location['id']."'>".$location['location']."</option>";
		endforeach;
		
		echo json_encode(array(
				'location' => $loc,
		));
		exit;
	}
	/**
	 * get Customer if the Payment is Credit
	**/
	public function getcustomerAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		$payment = $form['payment'];
		if($payment == 1){
			$customers = $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role' => '8'));
			$cus.="<option value='-1'>All</option>";
			foreach($customers as $customer):
				$cus.= "<option value='".$customer['id']."'>".$customer['code']."</option>";
			endforeach;
		}else{
			$cus.="<option value='-1'>All</option>";
		}
		
		echo json_encode(array(
				'customer' => $cus,
		));
		exit;
	}
	/**
	 * Location Product Wise Sales Report
	**/
	public function locationproductwiseAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$data = array(
					'location' => $form['location'],
					'activity' => $form['activity'],
					'payment' => $form['payment'],
					'start_date' => $form['start_date'],
					'end_date' => $form['end_date'],
					'item_details' => $form['item_details'],
			);
		else:
			$data = array(
					'location' => '-1',
					'activity' => '',
					'payment' => '-1',
					'item_details' => '',
					'start_date' => '',
					'end_date' => '',
			);
		endif;
		
		return new ViewModel(array(
				'title' => 'Location Product Wise Sales Report',
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'data' => $data,
				'salesObj' => $this->getDefinedTable(Sales\SalesTable::class),
				'itemuomObj' => $this->getDefinedTable(Stock\ItemUomTable::class),
		));
	}
}

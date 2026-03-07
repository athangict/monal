<?php
namespace Reports\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Laminas\EventManager\EventManagerInterface;
use Interop\Container\ContainerInterface;
use Accounts\Model As Accounts;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Stock\Model As Stock;
use Purchase\Model As Purchase;
use Sales\Model As Sales;
use Reports\Model As Reports;
class StockreconcilreportController extends AbstractActionController
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
		$this->_user_loc = $this->_user->location; 

		$this->_id = $this->params()->fromRoute('id');
		
	    $this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		//$this->_safedataObj = $this->SafeDataPlugin();
        $this->_permissionObj =  $this->PermissionPlugin();
		$this->_permissionObj->permission($this->getEvent());
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
	/**
	 * index Action
	 */
	public function indexAction()
	{
		$this->init();
		return new ViewModel();
	}
	
	/**
	 * Stock Reconcilation
	 */
	public function stockreconcilAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
			$activity = $form['activity'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
			//$item = $form['item'];
		else:
			//$location_type = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($this->_user_loc,'location_type');
			//$loc = (in_array($location_type,array(1,2,7,8)))?$this->_user_loc:'-1';
			$location = '-1';
			$activity = '-1';
			$start_date = date('Y-m-d');
			$end_date = date('Y-m-d');
			//$item = '-1';
		endif;
		$data = array(
			'location' => $location,
			'activity' => $activity,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'item' => '-1',
		);
		return new ViewModel(array(
				'title'      	=> "Stock Reconcilation",
				'data'        	=> $data,
				'regions'     	=> $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' 	=> $this->getDefinedTable(Administration\ActivityTable::class),
				'stock_items' 	=> $this->getDefinedTable(Stock\BatchTable::class)->getSMDistinctItems(array('i.activity'=>$activity,'b.status'=>'3'),'-1'),
				'partyObj'   	=> $this->getDefinedTable(Accounts\PartyTable::class),
				'itemObj'     	=> $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'      	=> $this->getDefinedTable(Stock\UomTable::class),
				'batchObj'    	=> $this->getDefinedTable(Stock\BatchTable::class),
				'batch_dtlsObj' => $this->getDefinedTable(Stock\BatchDetailsTable::class),
				'movingObj'     => $this->getDefinedTable(Stock\MovingItemTable::class),
				'openingObj'    => $this->getDefinedTable(Stock\OpeningStockTable::class),
				'pur_receiptObj'=> $this->getDefinedTable(Purchase\PurchaseReceiptTable::class),
				'dispatchObj'   => $this->getDefinedTable(Stock\DispatchTable::class),
				'salesObj'      => $this->getDefinedTable(Sales\SalesTable::class),
				'samObj'        => $this->getDefinedTable(Stock\SamTable::class),
				'itemconversionObj' => $this->getDefinedTable(Stock\ItemConversionTable::class),
				'openingdateObj' => $this->getDefinedTable(Reports\OpeningDateTable::class),
				'movingitemspObj' => $this->getDefinedTable(Stock\MovingItemSpTable::class),
				'transitlossObj' => $this->getDefinedTable(Stock\TransitLossTable::class),
		));
	}
	/**
	 * Sales Reconcilation
	 */
	public function salesreconcileAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
			$activity = $form['activity'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
			$item = $form['item'];
		else:
			$location_type = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($this->_user_loc,'location_type');
			$loc = (in_array($location_type,array(1,2,7,8)))?$this->_user_loc:'-1';
			$location = $loc;
			$activity = '-1';
			$start_date = date('Y-m-d');
			$end_date = date('Y-m-d');
			$item = '-1';
		endif;
		$data = array(
			'location' => $location,
			'activity' => $activity,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'item' => $item,
		);
		return new ViewModel(array(
				'title'      	    => "Sales Reconcilation",
				'data'        	    => $data,
				'regions'     	    => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' 	    => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' 	    => $this->getDefinedTable(Administration\ActivityTable::class),
				'stock_items' 	    => $this->getDefinedTable(Stock\BatchTable::class)->fetchSMDistinctItems(array('i.activity'=>$activity,'b.status'=>'3'),'-1'),
				'partyObj'   	    => $this->getDefinedTable(Accounts\PartyTable::class),
				'batchObj'    	    => $this->getDefinedTable(Stock\BatchTable::class),
				'batch_dtlsObj'     => $this->getDefinedTable(Stock\BatchDetailsTable::class),
				'salesObj'          => $this->getDefinedTable(Sales\SalesTable::class),
				'movingitemspObj'   => $this->getDefinedTable(Stock\MovingItemSpTable::class),
		));
	}
	/**
	 * Sales End of Session Reconcilation
	 */
	public function eosreconcileAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
		else:
			$location_type = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($this->_user_loc,'location_type');
			$loc = (in_array($location_type,array(1,2,7,8)))?$this->_user_loc:'-1';
			$location = $loc;
			$start_date = date('Y-m-d');
			$end_date = date('Y-m-d');
		endif;
		$data = array(
			'location' => $location,
			'start_date' => $start_date,
			'end_date' => $end_date,
		);
		return new ViewModel(array(
				'title'      	    => "Sales Reconcilation",
				'data'        	    => $data,
				'regions'     	    => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' 	    => $this->getDefinedTable(Administration\LocationTable::class),
				'bookingObj'        => $this->getDefinedTable(Sales\BookingTable::class),
				'transactionObj'    => $this->getDefinedTable(Accounts\TransactionTable::class),
				'salesObj'          => $this->getDefinedTable(Sales\SalesTable::class),
		));
	}
}

<?php
namespace Reports\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Laminas\EventManager\EventManagerInterface;
use Interop\Container\ContainerInterface;
use Administration\Model as Administration;
use Acl\Model as Acl;
use Stock\Model as Stock;
use Accounts\Model as Accounts;
use Purchase\Model as Purchase;
use Sales\Model as Sales;
class StockreportController extends AbstractActionController
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
	 * Fetch Item via Activity - stock movement 4 - developed on 2017-11-14
	 */
	public function getitemactivityAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		
		$activity_id = $form['activity'];
		$stock_items = $this->getDefinedTable(Stock\BatchTable::class)->fetchSMDistinctItems(array('i.activity'=>$activity_id,'b.status'=>'3'),'-1');
		$items .="<option value='-1'>All</option>";
		foreach($stock_items as $stock_item):
			$items .="<option value='".$stock_item['item_id']."'>".$stock_item['name']."</option>";
		endforeach;
		
		echo json_encode(array(
				'item' => $items,
				'selected_item' => -1,
		));
		exit;
	}
	
	/**
	 * Stock Movement 4 - developed on 2017-11-14
	 */
	public function stockmovementAction()
	{
		$this->init();

	if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
			$activity = $form['activity'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
			$item = $form['item'];
			$data = array(
				'location' => $location,
				'activity' => $activity,
				'start_date' => $start_date,
				'end_date' => $end_date,
				'item' => $item,
			);
			if($data['activity'] == 1 || $data['activity'] == 7):
				$lists = $this->getDefinedTable(Stock\BatchTable::class)->filterSMBatch($data['location'],array('i.activity'=>$data['activity'], 'i.valuation'=>0, 'b.status' => 3),'d.location',$data['item']);
				//echo "From batch=".sizeof($lists)."<br>";
				$array = array();
				foreach($lists as $list):
					array_push($array, $list['id']);
				endforeach;
				$opening_list = $this->getDefinedTable(Stock\OpeningStockTable::class)->filterSMBatch($data['start_date'],$data['location'],array('i.activity'=>$data['activity']),'o.opening_date','od.location',$array,$data['item']);
				//echo "Opening=".sizeof($opening_list)."<br>";
				$pur_list = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->filterSMBatch($data['end_date'],$data['location'],array('p.status'=>3,'p.activity'=>$data['activity']),'p.prn_date','p.location',$array,$data['item']);
				//echo "Purchase=".sizeof($pur_list)."<br>";
				$disp_list = $this->getDefinedTable(Stock\DispatchTable::class)->filterSMBatch($data['start_date'],$data['end_date'],$data['location'],array('d.status'=>array(2,10,3),'d.activity'=>$data['activity']),'d.dispatch_date','d.from_location',$array,$data['item']);
				//echo "dispatch=".sizeof($disp_list)."<br>";
				$rept_list = $this->getDefinedTable(Stock\DispatchTable::class)->filterSMReceiptBatch($data['end_date'],$data['location'],array('d.status'=>array(3),'d.activity'=>$data['activity']),'d.received_on','d.to_location',$array,$data['item']);
				//echo "receipt=".sizeof($rept_list)."<br>";
				$tras_list_date = $this->getDefinedTable(Stock\DispatchTable::class)->filterSMBatchTransitDate($data['end_date'],$data['location'],array('d.activity'=>$data['activity'],'d.status'=>array(2,3)),'d.dispatch_date','d.received_on','d.to_location',$array,$data['item']);
				//echo "In-Transit-Date=".sizeof($tras_list_date)."<br>";
				$tras_list_status = $this->getDefinedTable(Stock\DispatchTable::class)->filterSMBatchTransitStatus($data['end_date'],$data['location'],array('d.activity'=>$data['activity'],'d.status'=>array(2,10)),'d.dispatch_date','d.to_location',$array,$data['item']);
				//echo "In-Transit-Status=".sizeof($tras_list_status)."<br>";
				$converted_to_list = $this->getDefinedTable(Stock\ItemConversionTable::class)->filterSMBatchto($data['start_date'],$data['end_date'],$data['location'],array('c.status'=>array(3),'i.activity'=>$data['activity']),'c.conversion_date','c.location',$array,$data['item']);
				//echo "converted to list =".sizeof($converted_to_list)."<br>";
				$converted_from_list = $this->getDefinedTable(Stock\ItemConversionTable::class)->filterSMBatchfrom($data['start_date'],$data['end_date'],$data['location'],array('c.status'=>array(3),'i.activity'=>$data['activity']),'c.conversion_date','c.location',$array,$data['item']);
				//echo "converted from list=".sizeof($converted_from_list)."<br>";	
				$sale_list = $this->getDefinedTable(Sales\SalesTable::class)->filterSMBatch($data['start_date'],$data['end_date'],$data['location'],array('i.activity'=>$data['activity'],'s.status'=>3),'s.sales_date','s.location',$array,$data['item']);
				//echo "Sales=".sizeof($sale_list)."<br>";
				$sam_list = $this->getDefinedTable(Stock\SamTable::class)->filterSMBatch($data['start_date'],$data['end_date'],$data['location'],array('s.activity'=>$data['activity'],'s.status'=>3),'s.sam_date','s.location',$array,$data['item']);
				//echo "Sam=".sizeof($sam_list)."<br>";
				$row_list = array_merge($lists,$opening_list,$pur_list,$disp_list,$rept_list,$tras_list_date,$tras_list_status,$converted_to_list,$converted_from_list,$sale_list,$sam_list);
				//echo "Merge = ".sizeof($row_list)."<br>";
				$postTable = array_unique($row_list, SORT_REGULAR);
			else:
				$postTable = $this->getDefinedTable(Stock\BatchTable::class)->fetchSMDistinctItems(array('i.activity'=>$data['activity'], 'i.valuation'=>1, 'b.status' => 3),$data['item']);
			endif;
			//echo "<pre>";print_r($postTable);exit;
		else:
			$param = explode("_",$this->_id);
			$location = $param['0'];
			$activity = $param['1'];
			$start_date = $param['2'];
			$end_date = $param['3'];
			$item = $param['4'];
			$data = array(
				'location' => $location,
				'activity' => $activity,
				'start_date' => $start_date,
				'end_date' => $end_date,
				'item' => $item,
			);
			
			if($data['activity'] == 1 || $data['activity'] == 7):
				$lists = $this->getDefinedTable(Stock\BatchTable::class)->filterSMBatch($data['location'],array('i.activity'=>$data['activity'], 'i.valuation'=>0, 'b.status' => 3),'d.location',$data['item']);
				//echo "From batch=".sizeof($lists)."<br>";
				$array = array();
				foreach($lists as $list):
					array_push($array, $list['id']);
				endforeach;
				$opening_list = $this->getDefinedTable(Stock\OpeningStockTable::class)->filterSMBatch($data['start_date'],$data['location'],array('i.activity'=>$data['activity']),'o.opening_date','od.location',$array,$data['item']);
				//echo "Opening=".sizeof($opening_list)."<br>";
				$pur_list = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->filterSMBatch($data['end_date'],$data['location'],array('p.status'=>3,'p.activity'=>$data['activity']),'p.prn_date','p.location',$array,$data['item']);
				//echo "Purchase=".sizeof($pur_list)."<br>";
				$disp_list = $this->getDefinedTable(Stock\DispatchTable::class)->filterSMBatch($data['start_date'],$data['end_date'],$data['location'],array('d.status'=>array(2,10,3),'d.activity'=>$data['activity']),'d.dispatch_date','d.from_location',$array,$data['item']);
				//echo "dispatch=".sizeof($disp_list)."<br>";
				$rept_list = $this->getDefinedTable(Stock\DispatchTable::class)->filterSMReceiptBatch($data['end_date'],$data['location'],array('d.status'=>array(3),'d.activity'=>$data['activity']),'d.received_on','d.to_location',$array,$data['item']);
				//echo "receipt=".sizeof($rept_list)."<br>";
				$tras_list_date = $this->getDefinedTable(Stock\DispatchTable::class)->filterSMBatchTransitDate($data['end_date'],$data['location'],array('d.activity'=>$data['activity'],'d.status'=>array(2,3)),'d.dispatch_date','d.received_on','d.to_location',$array,$data['item']);
				//echo "In-Transit-Date=".sizeof($tras_list_date)."<br>";
				$tras_list_status = $this->getDefinedTable(Stock\DispatchTable::class)->filterSMBatchTransitStatus($data['end_date'],$data['location'],array('d.activity'=>$data['activity'],'d.status'=>array(2,10)),'d.dispatch_date','d.to_location',$array,$data['item']);
				//echo "In-Transit-Status=".sizeof($tras_list_status)."<br>";
				$converted_to_list = $this->getDefinedTable(Stock\ItemConversionTable::class)->filterSMBatchto($data['start_date'],$data['end_date'],$data['location'],array('c.status'=>array(3),'i.activity'=>$data['activity']),'c.conversion_date','c.location',$array,$data['item']);
				//echo "converted to list =".sizeof($converted_to_list)."<br>";
				$converted_from_list = $this->getDefinedTable(Stock\ItemConversionTable::class)->filterSMBatchfrom($data['start_date'],$data['end_date'],$data['location'],array('c.status'=>array(3),'i.activity'=>$data['activity']),'c.conversion_date','c.location',$array,$data['item']);
				//echo "converted from list=".sizeof($converted_from_list)."<br>";	
				$sale_list = $this->getDefinedTable(Sales\SalesTable::class)->filterSMBatch($data['start_date'],$data['end_date'],$data['location'],array('i.activity'=>$data['activity'],'s.status'=>3),'s.sales_date','s.location',$array,$data['item']);
				//echo "Sales=".sizeof($sale_list)."<br>";
				$sam_list = $this->getDefinedTable(Stock\SamTable::class)->filterSMBatch($data['start_date'],$data['end_date'],$data['location'],array('s.activity'=>$data['activity'],'s.status'=>3),'s.sam_date','s.location',$array,$data['item']);
				//echo "Sam=".sizeof($sam_list)."<br>";
				$row_list = array_merge($lists,$opening_list,$pur_list,$disp_list,$rept_list,$tras_list_date,$tras_list_status,$converted_to_list,$converted_from_list,$sale_list,$sam_list);
				//echo "Merge = ".sizeof($row_list)."<br>";
				$postTable = array_unique($row_list, SORT_REGULAR);
			else:
				$postTable = $this->getDefinedTable(Stock\BatchTable::class)->fetchSMDistinctItems(array('i.activity'=>$data['activity'], 'i.valuation'=>1, 'b.status' => 3),$data['item']);
			endif;
			//echo "<pre>";print_r($postTable);exit;
		endif;
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($postTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10);
		
		return new ViewModel(array(
				'title'      	    => "Stock Movement",
				'data'        	    => $data,
				'paginator'         => $paginator,
				'regions'     	    => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' 	    => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' 	    => $this->getDefinedTable(Administration\ActivityTable::class),
				'stock_items' 	    => $this->getDefinedTable(Stock\BatchTable::class)->fetchSMDistinctItems(array('i.activity'=>$activity,'b.status'=>'3'),'-1'),
				'partyObj'   	    => $this->getDefinedTable(Accounts\PartyTable::class),
				'itemObj'     	    => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'      	    => $this->getDefinedTable(Stock\UomTable::class),
				'batchObj'    	    => $this->getDefinedTable(Stock\BatchTable::class),
				'batch_dtlsObj'     => $this->getDefinedTable(Stock\BatchDetailsTable::class),
				'movingObj'         => $this->getDefinedTable(Stock\MovingItemTable::class),
				'openingObj'        => $this->getDefinedTable(Stock\OpeningStockTable::class),
				'pur_receiptObj'    => $this->getDefinedTable(Purchase\PurchaseReceiptTable::class),
				'dispatchObj'       => $this->getDefinedTable(Stock\DispatchTable::class),
				'salesObj'          => $this->getDefinedTable(Sales\SalesTable::class),
				'samObj'            => $this->getDefinedTable(Stock\SamTable::class),
				'itemconversionObj' => $this->getDefinedTable(Stock\ItemConversionTable::class),
				'movingitemspObj'   => $this->getDefinedTable(Stock\MovingItemSpTable::class),
		));	
	}
		/**
	 * Stock Movement 4 - developed on 2017-11-14
	 */
	public function stockmovement1Action()
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
				'title'      	    => "Stock Movement",
				'data'        	    => $data,
				'regions'     	    => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' 	    => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' 	    => $this->getDefinedTable(Administration\ActivityTable::class),
				'stock_items' 	    => $this->getDefinedTable(Stock\BatchTable::class)->fetchSMDistinctItems(array('i.activity'=>$activity,'b.status'=>'3'),'-1'),
				'partyObj'   	    => $this->getDefinedTable(Accounts\PartyTable::class),
				'itemObj'     	    => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'      	    => $this->getDefinedTable(Stock\UomTable::class),
				'batchObj'    	    => $this->getDefinedTable(Stock\BatchTable::class),
				'batch_dtlsObj'     => $this->getDefinedTable(Stock\BatchDetailsTable::class),
				'movingObj'         => $this->getDefinedTable(Stock\MovingItemTable::class),
				'openingObj'        => $this->getDefinedTable(Stock\OpeningStockTable::class),
				'pur_receiptObj'    => $this->getDefinedTable(Purchase\PurchaseReceiptTable::class),
				'dispatchObj'       => $this->getDefinedTable(Stock\DispatchTable::class),
				'salesObj'          => $this->getDefinedTable(Sales\SalesTable::class),
				'samObj'            => $this->getDefinedTable(Stock\SamTable::class),
				'itemconversionObj' => $this->getDefinedTable(Stock\ItemConversionTable::class),
				'movingitemspObj'   => $this->getDefinedTable(Stock\MovingItemSpTable::class),
                                'prdtlsObj'    => $this->getDefinedTable(Purchase\PRDetailsTable::class),
			'invoicedtlsObj'    => $this->getDefinedTable(Purchase\SupInvDetailsTable::class),
		));
	}
	/**
	 * Location Stock Movement 4 - developed on 2018-01=12
	 */
	public function locationstockmovementAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$region   = $form['region'];
			$location = $form['location'];
			$location_type = $form['location_type'];
			$activity = $form['activity'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
			$item = $form['item'];
		else:
			$region   = '';
			$location = '-1';
			$location_type = '-1';
			$activity = '-1';
			$start_date = date('Y-m-d');
			$end_date = date('Y-m-d');
			$item = '-1';
		endif;
		$data = array(
		    'region' => $region,
			'location' => $location,
			'location_type' => $location_type,
			'activity' => $activity,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'item' => $item,
		);
		return new ViewModel(array(
				'title'      	    => "Stock Movement",
				'data'        	    => $data,
				'regionObj'     	=> $this->getDefinedTable(Administration\RegionTable::class),
				'locationObj' 	    => $this->getDefinedTable(Administration\LocationTable::class),
				'locationtypeObj'   => $this->getDefinedTable(Administration\LocationTypeTable::class),
				'activityObj' 	    => $this->getDefinedTable(Administration\ActivityTable::class),
				'stock_items' 	    => $this->getDefinedTable(Stock\BatchTable::class)->fetchSMDistinctItems(array('i.activity'=>$activity,'b.status'=>'3'),'-1'),
				'partyObj'   	    => $this->getDefinedTable(Accounts\PartyTable::class),
				'itemObj'     	    => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'      	    => $this->getDefinedTable(Stock\UomTable::class),
				'batchObj'    	    => $this->getDefinedTable(Stock\BatchTable::class),
				'batch_dtlsObj'     => $this->getDefinedTable(Stock\BatchDetailsTable::class),
				'movingObj'         => $this->getDefinedTable(Stock\MovingItemTable::class),
				'openingObj'        => $this->getDefinedTable(Stock\OpeningStockTable::class),
				'pur_receiptObj'    => $this->getDefinedTable(Purchase\PurchaseReceiptTable::class),
				'dispatchObj'       => $this->getDefinedTable(Stock\DispatchTable::class),
				'salesObj'          => $this->getDefinedTable(Sales\SalesTable::class),
				'samObj'            => $this->getDefinedTable(Stock\SamTable::class),
				'itemconversionObj' => $this->getDefinedTable(Stock\ItemConversionTable::class),
				'movingitemspObj'   => $this->getDefinedTable(Stock\MovingItemSpTable::class),
		));
	}
	
}

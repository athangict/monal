<?php
namespace Store\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Stdlib\ArrayObject;
use Laminas\Validator\File\Size;
use Laminas\Validator\File\Extension;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Accounts\Model As Accounts;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Hr\Model As Hr;
use Store\Model As Store;
use Stock\Model As Stock;
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
		
		$this->_id = $this->params()->fromRoute('id');
		
	    $this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		
		// /$this->_dir =realpath($fileManagerDir);

		//$this->_safedataObj =  $this->SafeDataPlugin();
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
	
	/**
	 * report index action
	 */
	public function indexAction()
	{
		$this->init();		
			
		return new ViewModel( array(
				'title' => "Fleet Report Setup",
		));
	}

	/**
	 * Stock 
	 */
	public function stockreportAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
			$item_group = $form['item_group'];
			$item_sub_group = $form['item_sub_group'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
			$item = $form['item'];
		else:
			$location_type = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($this->_user_loc,'location_type');
			$loc = (in_array($location_type,array(1,2,4,5,7,8)))?$this->_user_loc:'-1';
			$location = $loc;
			$item_group = '-1';
			$item_sub_group = '-1';
			$start_date = date('Y-m-d');
			$end_date = date('Y-m-d');
			$item = '-1';
		endif;
		$data = array(
			'location' => $location,
			'item_group' => $item_group,
			'item_sub_group' => $item_sub_group,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'item' => $item,
		);
		if($item_group == 1):
		$item_details = $this->getDefinedTable(Store\AssetTable::class)->fetchDistinctItems(array('i.item_group'=>$item_group,'a.status'=>'3'),'-1');
		else:
		$item_details = $this->getDefinedTable(Store\StoreSpareTable::class)->fetchDistinctItems(array('i.item_group'=>$item_group,'ssp.status'=>'3'),'-1');
		endif;
		$subgroupdtls = $this->getDefinedTable(Store\SubGroupTable::class)->get(array('item_group'=>$item_group));
		$ViewModel = new ViewModel(array(
			'title'      	  => "Stock Report",
			'data'        	  => $data,
			'item_details'    =>$item_details,
			'subgroupdtls'    =>$subgroupdtls,
			'regions'     	  => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'locationObj' 	  => $this->getDefinedTable(Administration\LocationTable::class),
			'groupObj'   	  => $this->getDefinedTable(Store\GroupTable::class),
			'subgroupObj'     => $this->getDefinedTable(Store\SubGroupTable::class),
			'partyObj'   	  => $this->getDefinedTable(Accounts\PartyTable::class),
			'itemObj'     	  => $this->getDefinedTable(Store\ItemTable::class),
			'uomObj'      	  => $this->getDefinedTable(Stock\UomTable::class),
			'pur_receiptObj'  => $this->getDefinedTable(Store\PurchaseReceiptTable::class),
			'issueObj'        => $this->getDefinedTable(Store\IssueTable::class),
			'assetObj'        => $this->getDefinedTable(Store\AssetTable::class),
			'sspObj'          => $this->getDefinedTable(Store\StoreSpareTable::class),
			'openingassetObj' => $this->getDefinedTable(Store\AssetOpeningTable::class),
			'openingsspObj'   => $this->getDefinedTable(Store\SspOpeningTable::class),
			'sspdtlsObj'      => $this->getDefinedTable(Store\StoreSpareDetailsTable::class),
			'assetdtlsObj'      => $this->getDefinedTable(Store\AssetDetailsTable::class),
                        'openingsspdtlsObj'   => $this->getDefinedTable(Store\SspOpeningDetailsTable::class),
			'issuedtlsObj'        => $this->getDefinedTable(Store\IssueDetailsTable::class),
			'pur_receiptdtlObj'  => $this->getDefinedTable(Store\PRDetailsTable::class),

		));
		$this->layout('layout/reportlayout');
		return $ViewModel; 
	}
       /**
	 * Fetch Sub Group via group - Store Report - developed on 2018-09-14
	 */
	public function getsubgroupgroupAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		
		$item_group = $form['item_group'];
		if($item_group  == '1'):
		$item_details = $this->getDefinedTable(Store\AssetTable::class)->fetchDistinctItems(array('i.item_group'=>$item_group,'a.status'=>'3'),'-1');
		else:
		$item_details = $this->getDefinedTable(Store\StoreSpareTable::class)->fetchDistinctItems(array('i.item_group'=>$item_group,'ssp.status'=>'3'),'-1');
		endif;
		$items .="<option value='-1'>All</option>";
		foreach($item_details as $item_detail):
			$items .="<option value='".$item_detail['item_id']."'>".$item_detail['name']."</option>";
		endforeach;
		
		$item_subgroups = $this->getDefinedTable(Store\SubGroupTable::class)->get(array('item_group'=>$item_group));
		$subgroups .="<option value='-1'>All</option>";
		foreach($item_subgroups as $item_subgroup):
			$subgroups .="<option value='".$item_subgroup['id']."'>".$item_subgroup['name']."</option>";
		endforeach;
		echo json_encode(array(
				'subgroups' => $subgroups,
				'items' => $items,
				'selected_sub_group' => -1,
				'selected_item' => -1,
		));
		exit;
	}
}

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
class StoreController extends AbstractActionController
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
		
		//$this->_dir =realpath($fileManagerDir);

		//$this->_safedataObj =  $this->SafeDataPlugin();
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
	
	/**
	 * index action
	 */
	 
	public function indexAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$group = $form['item_group'];
			$subgroup = $form['item_subgroup'];
		}else{
			$group = 1; 
			$subgroup = -1; 
		}
		$items = $this->getDefinedTable(Store\ItemTable::class)->getItemBy($group,$subgroup);	
		return new ViewModel( array(
				'title' => "Item",
				'group_id' => $group,
				'subgroup_id' => $subgroup,
				'items'   =>$items,
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
				'itemgroupObj' => $this->getDefinedTable(Store\GroupTable::class),
				'itemsubgroupObj' => $this->getDefinedTable(Store\SubGroupTable::class),
		) );
	}
	/**
	 * add item action
	 */
	public function additemAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
		
			$data = array(
					'name'                     => strtoupper($form['item_name']),
					'code'                     => strtoupper($form['item_code']),
					'item_group'               => $form['item_group'],
					'item_sub_group'           => $form['item_subgroup'],
					'uom'                      => $form['uom'],
					'item_type_code'           => $form['item_type_code'],
					'item_specification_code'  => $form['item_Specfication_code'], 
					'expiry_period'            => $form['expiry_period'],
					'barcode'                  => $form['barcode'],
					'valuation'                => $form['valuation'],
					'status'                   => 1,
					'author'                   => $this->_author,
					'created'                  => $this->_created,
					'modified'                 => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Store\ItemTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add");
			endif;
			return $this->redirect()->toRoute('store',array('action' => 'index'));
		}
		return new ViewModel( array(
				'groupObj' => $this->getDefinedTable(Store\GroupTable::class),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
			));
	}
	/**
	 * edit item Action
	 **/
	public function edititemAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest();
			
			$data=array(
					'id' => $form->getPost('item_id'),
					'name' => strtoupper($form->getPost('item_name')),
					'code' => strtoupper($form->getPost('item_code')),
					'item_group' => $form->getPost('item_group'),
					'item_sub_group' => $form->getPost('item_subgroup'),
					'uom' => $form->getPost('uom'),
					'item_type_code' => $form->getPost('item_type_code'),
					'item_specification_code' => $form->getPost('item_Specfication_code'),
					'expiry_period' => $form->getPost('expiry_period'),
					'barcode' => $form->getPost('barcode'),
					'valuation' => $form->getPost('valuation'),
					'status' => 1,
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Store\ItemTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update");
			endif;
			return $this->redirect()->toRoute('store',array('action' => 'index'));
		}
		return new ViewModel(array(
				'items' => $this->getDefinedTable(Store\ItemTable::class)->get($this->_id),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
				'itemgroupObj' => $this->getDefinedTable(Store\GroupTable::class),
				'itemsubgroupObj' => $this->getDefinedTable(Store\SubGroupTable::class),
			));
	}
	/**
	 * Action for getting sub group
	 */
	public function getitemsubgroupAction()
	{
		
		$this->init();
		
		$form = $this->getRequest()->getPost();
		
		$item_group = $form['item_group'];
		$subgroupObj = $this->getDefinedTable(Store\SubGroupTable::class);
		$itemgroups = $subgroupObj->get(array('item_group'=>$item_group));
		$itemp .="<option value=''></option>";
		foreach($itemgroups as $itemgroup):
			$itemp .="<option value='".$itemgroup['id']."'>".$itemgroup['name']."</option>";
		endforeach;
		echo json_encode(array(
				'itemp' => $itemp,
				'item_group'=>$item_group,
		));
		exit;
	}
	/**
	* Action for viewitem
	*/
	public function viewitemAction()
	{
		$this->init();
		return new ViewModel( array(
				'title' => "Item",
				'items' => $this->getDefinedTable(Store\ItemTable::class)->get($this->_id),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
				'itemgroupObj' => $this->getDefinedTable(Store\GroupTable::class),
				'itemsubgroupObj' => $this->getDefinedTable(Store\SubGroupTable::class),
		) );
	}
	/**
	 * checkavailability Action
	**/
	public function checkavailabilityAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		switch ($form['type']) {
			case 'item_code':
				$item_code = $form['item_code'];
				// Check the item code existence ...
				$result = $this->getDefinedTable(Store\ItemTable::class)->isPresent('code', $item_code);
				break;

			case 'item_name':
			//default:
				$item_name = $form['item_name'];
				// Check the item name existence ...
				$result = $this->getDefinedTable(Store\ItemTable::class)->isPresent('name', $item_name);
				break;
		}
		
		echo json_encode(array(
					'valid' => $result,
		));
		exit;
	}
}

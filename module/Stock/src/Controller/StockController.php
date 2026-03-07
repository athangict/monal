<?php
namespace Stock\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Stdlib\ArrayObject;
use Laminas\Validator\File\Size;
use Laminas\Validator\File\Extension;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Stock\Model As Stock;
use Administration\Model As Administration;
use Accounts\Model As Accounts;
use Purchase\Model As Purchase;
class StockController extends AbstractActionController
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
		
		if(!isset($this->_config)) {
			$this->_config = $this->_container->get('Config');
		}
		
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
	 * Item Class Action
	 */
	public function itemclassAction()
	{
		$this->init();	
		return new ViewModel( array(
				'title' => "Item Class",
				'itemclasses' => $this->getDefinedTable(Stock\ItemClassTable::class) -> getAll(),
		) );
	}
	/**
	 * add itemgroup action
	 */
	public function additemclassAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'item_class' => $form['itemclass'],
					'description' => $form['description'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\ItemClassTable::class)->save($data);
	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully added new item class");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new item class");
			endif;
			return $this->redirect()->toRoute('stock',array('action' => 'itemclass'));
		}
		$ViewModel = new ViewModel();
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * edit item group Action
	 **/
	public function edititemclassAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$data=array(
					'id' => $form['itemclass_id'],
					'item_class' => $form['itemclass'],
					'description' => $form['description'],
					'author' => $this->_author,
					'modified' => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\ItemClassTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully updated item class");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to update item class");
			endif;
			return $this->redirect()->toRoute('stock',array('action' => 'itemclass'));
		}
		$ViewModel = new ViewModel(array(
				'itemclasses' => $this->getDefinedTable(Stock\ItemClassTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}	
	/**
	 * item group action
	 */
	public function itemgroupAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$class_id = $form['item_class'];
			
		}else{
			$class_id = '-1';
		}
		$itemgroups = $this->getDefinedTable(Stock\ItemGroupTable::class) -> getByClass($class_id);
		
		return new ViewModel( array(
				'title' => "Item Group",
				'class_id' => $class_id,
				'itemgroups' => $itemgroups,
				'itemclassObj' => $this->getDefinedTable(Stock\ItemClassTable::class),
		) );
	}
	/**
	 * add itemgroup action
	 */
	public function additemgroupAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'item_group' => $form['itemgroup'],
					'description' => $form['description'],
					'item_class' => $form['itemclass'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\ItemGroupTable::class)->save($data);
	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully added new item group");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new item group");
			endif;
			return $this->redirect()->toRoute('stock',array('action' => 'itemgroup'));
		}
		$ViewModel = new ViewModel(array(
				'itemclasses' => $this->getDefinedTable(Stock\ItemClassTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * edit item group Action
	 **/
	public function edititemgroupAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$data=array(
					'id' => $this->_id,
					'item_group' => $form['itemgroup'],
					'description' => $form['description'],
					'item_class' => $form['itemclass'],
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\ItemGroupTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully updated item group");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to edit item group");
			endif;
			return $this->redirect()->toRoute('stock',array('action' => 'itemgroup'));
		}
		$ViewModel = new ViewModel(array(
				'itemgroups' => $this->getDefinedTable(Stock\ItemGroupTable::class)->get($this->_id),
				'itemclasses' => $this->getDefinedTable(Stock\ItemClassTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}	
	/**
	 * item group action
	 */
	public function itemsubgroupAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$group_id = $form['item_group'];
			
		}else{
			$group_id = '-1';
		}
		$itemsubgroups = $this->getDefinedTable(Stock\ItemSubGroupTable::class) -> getByGroup($group_id);
		
		return new ViewModel( array(
				'title' => "Item Sub-Group",
				'group_id' => $group_id,
				'itemsubgroups' => $itemsubgroups,
				'itemgroupObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
				'itemclassObj' => $this->getDefinedTable(Stock\ItemClassTable::class),
		) );
	}
	/**
	 * add itemgroup action
	 */
	public function additemsubgroupAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'item_subgroup' => $form['itemsubgroup'],
					'description' => $form['description'],
					'item_group' => $form['itemgroup'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\ItemSubGroupTable::class)->save($data);
	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully added new item sub-group");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new item sub-group");
			endif;
			return $this->redirect()->toRoute('stock',array('action' => 'itemsubgroup'));
		}
		$ViewModel = new ViewModel(array(
				'itemgroups' => $this->getDefinedTable(Stock\ItemGroupTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * edit item group Action
	 **/
	public function edititemsubgroupAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$data=array(
					'id' => $this->_id,
					'item_subgroup' => $form['itemsubgroup'],
					'description' => $form['description'],
					'item_group' => $form['itemgroup'],
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\ItemSubGroupTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully updated item sub-group");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to edit item sub-group");
			endif;
			return $this->redirect()->toRoute('stock',array('action' => 'itemsubgroup'));
		}
		$ViewModel = new ViewModel(array(
				'itemsubgroups' => $this->getDefinedTable(Stock\ItemSubGroupTable::class)->get($this->_id),
				'itemgroups' => $this->getDefinedTable(Stock\ItemGroupTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}	
	/**
	 * stock item action
	 */
	public function indexAction()
	{
		$this->init();		
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$subgroup = $form['item_subgroup'];
		}else{
			$subgroup = -1; //default All
		}
		$items = $this->getDefinedTable(Stock\ItemTable::class)->getItemBy($subgroup);		
		return new ViewModel( array(
				'title' => "Stock Item",
				'subgroup_id' => $subgroup,
				'items' => $items,
				'itemgroups' => $this->getDefinedTable(Stock\ItemGroupTable::class)->getAll(),
				'itemclasses' => $this->getDefinedTable(Stock\ItemClassTable::class)->getAll(),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'costingFormulaObj' => $this->getDefinedTable(Stock\CostingFormulaTable::class),
				'itemclassObj' => $this->getDefinedTable(Stock\ItemClassTable::class),
				'itemgroupObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
				'itemsubgroupObj' => $this->getDefinedTable(Stock\ItemSubGroupTable::class),
		) );
	}
	/**
	 * add item action
	 */	
	public function additemAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$results = $this->getDefinedTable(Stock\ItemTable::class)->getAll();		   	  
			$item_code_list = array();
			foreach($results as $result):			   	
			  array_push($item_code_list, substr($result['item_code'], 0));
			endforeach;
			//print_r($result);exit;
			if(sizeof($item_code_list)>0){
				$next_serial = max($item_code_list) + 1;
			}else{
				 $next_serial = 1;
			}
			
			switch(strlen($next_serial)){
				case 1: $next_code_serial = "000".$next_serial; break;
				case 2: $next_code_serial = "00".$next_serial;  break;
				case 3: $next_code_serial = "0".$next_serial;   break;
				default: $next_code_serial = $next_serial;      break;
			}
			$itemCode = $item_code_list; 
			$form = $this->getRequest()->getPost();
			$group = $this->getDefinedTable(Stock\ItemSubGroupTable::class)->getColumn($form['item_subgroup'],'item_group');		
				$data1 = array(
						'code'          	=> $form['item_code'],
						'name'          	=> $form['item_name'],
						'item_subgroup'    	=> $form['item_subgroup'],
						'uom'           	=> $form['unit'],
					    'scalar_uom'   		=> '1',
						'elc_formula'       => '1',
						'location_formula' 	=> '1',
						'net_weight'    	=> '1',
						'status'        	=> '1',
						'activity'      	=> '1',
						'valuation'      	=> '1',
						'supplier'      	=> '1',
						'expiry_period' 	=> '0',
						'bst'           	=> '0',
						'transportation_charge'	=>'0',
						'item_group'    	=> $group,
						'barcode'			=> $form['barcode'],						
						'item_code'			=> $itemCode,
						'author'        	=> $this->_author,
						'created'       	=> $this->_created,
						'modified'      	=> $this->_modified,
				);
				//echo'<pre>';print_r($data1); exit;
				$data1 = $this->_safedataObj->rteSafe($data1);
				$this->_connection->beginTransaction(); //***Transaction begins here***//
				$result1 = $this->getDefinedTable(Stock\ItemTable::class)->save($data1);
				
			/*	$location_type = $form['location_type'];
				$margin = $form['margin'];
				
				for($l=0; $l<sizeof($location_type);$l++):
					if(isset($location_type[$l]) && is_numeric($location_type[$l])):
						$margindata = array(
								'item'			=>	$result1,
								'location_type' =>	$location_type[$l],
								'margin' 		=>	$margin[$l],
								'author'		=>	$this->_author, 
								'created'		=>	$this->_created,
								'modified'		=>	$this->_modified,
						);
						$margindata = $this->_safedataObj->rteSafe($margindata);
						$result5 = $this->getDefinedTable(Stock\MarginTable::class)->save($margindata);
					endif; 
				endfor;

				$min_stock_qty = $form['min_stock_qty'];
				$reorder_level = $form['re_order_level'];
				$reorder_loc_type = $form['reorder_loc_type'];
				//$request_type = $form['request_type'];
				
				for($i=0; $i<sizeof($reorder_loc_type);$i++):
					if(isset($reorder_loc_type[$i]) && is_numeric($reorder_loc_type[$i])):
						$reorderdata = array(
								'item'			=>	$result1,
								'location_type' =>  $reorder_loc_type[$i],
								'min_stock_qty' =>	$min_stock_qty[$i],
								'reorder_level' =>	$reorder_level[$i],
								'author'		=>	$this->_author, 
								'created'		=>	$this->_created,
								'modified'		=>	$this->_modified,
						);
						$reorderdata = $this->_safedataObj->rteSafe($reorderdata);
						$result2 = $this->getDefinedTable(Stock\ReOrderTable::class)->save($reorderdata);
					endif; 
				endfor;
				
				$uom = $form['uom'];
				$conversion = $form['conversion'];
				for($j=0; $j<sizeof($uom);$j++):
					if(isset($uom[$j]) && is_numeric($uom[$j])):
						$standarduom = ($uom[$j] == $form['st_uom'])?'1':'0';
						$uomdata = array(
								'item'			=>	$result1,
								'uom'			=>	$uom[$j],
								'conversion'	=>	$conversion[$j],
								'costing_uom'   =>  $standarduom,
								'author'		=>	$this->_author,
								'created'		=>	$this->_created,
								'modified'		=>	$this->_modified,	
						);
						$uomdata = $this->_safedataObj->rteSafe($uomdata);
						$result3 = $this->getDefinedTable(Stock\ItemUomTable::class)->save($uomdata);
					endif;
				endfor;*/
				
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
				 	$this->flashMessenger()->addMessage("success^ Successfully added new item ".$form['item_code']);
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to add new item");
				endif;
				return $this->redirect()->toRoute('stock', array('action' => 'viewitem', 'id' => $result1));
		}
		$ViewModel = new ViewModel(array(
			'title'      => "Item",
			'itemclasses' => $this->getDefinedTable(Stock\ItemClassTable::class) -> getAll(),
			'itemgroupsObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
			'itemsubgroupsObj' => $this->getDefinedTable(Stock\ItemSubGroupTable::class),
			'uomtypeObj' => $this->getDefinedTable(Stock\UomTypeTable::class),
			'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'parties'    => $this->getDefinedTable(Accounts\PartyTable::class)->getAll(),
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'locationtypes'=> $this->getDefinedTable(Administration\LocationTypeTable::class)->get(array('sales' => '1')),
			'elcformula' => $this->getDefinedTable(Stock\CostingFormulaTable::class)->get(array('costing_type'=>1)),
			'locationformula' => $this->getDefinedTable(Stock\CostingFormulaTable::class)->get(array('costing_type'=>2)),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * get uom code
	 * Standard Uom
	**/
	public function getuomcodeAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$uom_code = $this->getDefinedTable(Stock\UomTable::class)->getColumn($form['uom_id'],'code');
		$uom = "<option value='".$form['uom_id']."'>".$uom_code."</option>";
		echo json_encode(array(
				'uom' => $uom,
		));
		exit;
	}
	/**
	 * get the elc and location formula from st_costing_formula
	 * according to activity and costing type
	 */
	public function getcostingformulaAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		
		$activity = $form['activity'];
		$costingFormulaObj = $this->getDefinedTable(Stock\CostingFormulaTable::class);
		
		$elc_formulas = $costingFormulaObj->get(array('costing_type'=>'1', 'activity'=>$activity));
		$elc_for .="<option value=''></option>";
		foreach($elc_formulas as $elc):
			$elc_for .="<option value='".$elc['id']."'>".$elc['formula_name']."</option>";
		endforeach;
		$location_formulas = $costingFormulaObj->get(array('costing_type'=>'2', 'activity'=>$activity));
		$loc_for .="<option value=''></option>";
		foreach($location_formulas as $loc):
			$loc_for .="<option value='".$loc['id']."'>".$loc['formula_name']."</option>";
		endforeach;
		echo json_encode(array(
				'elc_formula' => $elc_for,
				'location_formula' => $loc_for,
		));
		exit;
	}
	/**
	 * get uom type details
	**/
	public function getuomtypeAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$uom_id = 141006;//$form['uom_id'];
		
		$uomObj = $this->getDefinedTable(Stock\UomTable::class);
		$uom_type = $uomObj->getColumn($uom_id,'uom_type');
		$uomtypeObj = $this->getDefinedTable(Stock\UomTypeTable::class);
		
		if($uom_type == 1): //Uom Type is Package
			$uoms.="<option value=''></option>";
			$uomtypes = $uomtypeObj->get(array('id'=>array(2,3)));
			foreach($uomtypes as $uomtype):
				$uoms.="<optgroup label='".$uomtype['uom_type']."'>";
				$WtVol_uoms = $uomObj->get(array('uom_type'=>$uomtype['id']));
				foreach($WtVol_uoms as $WtVol_uom):
					$uoms.="<option value='".$WtVol_uom['id']."'>".$WtVol_uom['code']."</option>";
				endforeach;
				$uoms.="</optgroup>";
			endforeach;
			$wt = '';
		else:
			$WtVol_uoms = $uomObj->get($uom_id);
			$uomtypeCode = $uomtypeObj->getColumn($uom_type,'uom_type');
			$uoms.="<optgroup label='".$uomtypeCode."'>";
				foreach($WtVol_uoms as $WtVol_uom):
					$uoms.="<option value='".$WtVol_uom['id']."'>".$WtVol_uom['code']."</option>";
				endforeach;
			$uoms.="</optgroup>";
			$wt = '1';
			$conversion = $this->getDefinedTable(Stock\ScalarConversionTable::class)->getColumn(array('from_uom'=>$uom_id,'to_uom'=>'59001'),'conversion');
		endif;
		//print_r($conversion);exit;
		$other_uomtypes = $uomtypeObj->getAll();
		$units.="<option value=''></option>";
		foreach($other_uomtypes as $other_uomtype):
			$units.="<optgroup label='".$other_uomtype['uom_type']."'>";
			$other_uoms = $uomObj->get(array('uom_type'=>$other_uomtype['id']));
			foreach($other_uoms as $other_uom):
				$units.="<option value='".$other_uom['id']."' data_uom_type='".$other_uomtype['id']."'>".$other_uom['code']."</option>";
			endforeach;
			$units.="</optgroup>";
		endforeach;
		//echo "<pre>"; print_r($other_uoms);
		
		echo json_encode(array(
				'uom_type' => $uom_type,
				'uoms' => $uoms,
				'wt' => $wt,
				'other_uom' => $units,
				'conversion' => $conversion,
		));
		exit;
	}
	
	/**
	 * edit item action
	 *
	 */
	public function edititemAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			if($form['item_status']==""){
				$item_status = '0';
			}else{
				$item_status = '1';
			}
			$group = $this->getDefinedTable(Stock\ItemSubGroupTable::class)->getColumn($form['item_subgroup'],'item_group');
			$data1 = array(
					'id'				=> $form['item_id'],
					'code'          	=> $form['item_code'],
					'name'          	=> $form['item_name'],
					'item_subgroup'    	=> $form['item_subgroup'],
					'item_group'    	=> $group,
					'uom'           	=> $form['unit'],
				    'scalar_uom'   		=> '1',
					'elc_formula'       => '1',
					'location_formula' 	=> '1',
					'net_weight'    	=> '1',
					'status'        	=> '1',
					'activity'      	=> '1',
					'valuation'      	=> '1',
					'supplier'      	=> '1',
					'expiry_period' 	=> '0',
					'bst'           	=> '0',
					'transportation_charge'	=>'0',
					'barcode'			=> $form['barcode'],						
					'item_code'			=> 0,
					'author'        	=> $this->_author,
					'modified'      	=> $this->_modified,
			);
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result1 = $this->getDefinedTable(Stock\ItemTable::class)->save($data1);
	
		/*	$margin_id = $form['margin_id'];
			$location_type = $form['location_type'];
			$margin = $form['margin'];
			for($l=0; $l<sizeof($location_type);$l++):
				if(isset($location_type[$l]) && is_numeric($location_type[$l]) && $margin_id[$l] > 0):
					$margindata = array(
							'id'			=>  $margin_id[$l],
							'location_type' =>	$location_type[$l],
							'margin' 		=>	$margin[$l],
							'author'		=>	$this->_author, 
							'modified'		=>	$this->_modified,
					);
					//echo "<pre>";print_r($margindata);exit;
					$margindata = $this->_safedataObj->rteSafe($margindata);
					$result5 = $this->getDefinedTable(Stock\MarginTable::class)->save($margindata);
				else:
					$margindata = array(
							'item'			=>	$result1,
							'location_type' =>	$location_type[$l],
							'margin' 		=>	$margin[$l],
							'author'		=>	$this->_author,
							'created'		=>	$this->_created, 
							'modified'		=>	$this->_modified,
					);
					//echo "<pre>";print_r($margindata);exit;
					$margindata = $this->_safedataObj->rteSafe($margindata);
					$result5 = $this->getDefinedTable(Stock\MarginTable::class)->save($margindata);
				endif; 
			endfor;
			
			$reorder_id = $form['reorder_id'];
			$min_stock_qty = $form['min_stock_qty'];
			$reorder_level = $form['re_order_level'];
			$reorder_loc_type = $form['reorder_loc_type'];
			
			for($i=0; $i<sizeof($reorder_loc_type);$i++):
				if(isset($reorder_loc_type[$i]) && is_numeric($reorder_loc_type[$i]) && $reorder_id[$i] > 0):
					$reorderdata = array(
							'id'			=>  $reorder_id[$i],
							'item'			=>	$result1,
							'location_type' => $reorder_loc_type[$i],
							'min_stock_qty' =>	$min_stock_qty[$i],
							'reorder_level' =>	$reorder_level[$i],
							'author'		=>	$this->_author,
							'modified'		=>	$this->_modified,
					);
					$reorderdata = $this->_safedataObj->rteSafe($reorderdata);
					$result2 = $this->getDefinedTable(Stock\ReOrderTable::class)->save($reorderdata);
				else:
					$reorderdata = array(
							'item'			=>	$result1,
							'location_type' => $reorder_loc_type[$i],
							'min_stock_qty' =>	$min_stock_qty[$i],
							'reorder_level' =>	$reorder_level[$i],
							'author'		=>	$this->_author,
							'modified'		=>	$this->_modified,
					);
					$reorderdata = $this->_safedataObj->rteSafe($reorderdata);
					$result2 = $this->getDefinedTable(Stock\ReOrderTable::class)->save($reorderdata);
				endif;
			endfor;
			
			$uom_item_id = $form['uom_item_id'];
			//print_r($uom_item_id); 
			$uom = $form['uom'];
			$conversion = $form['conversion'];
			$delete_rows = $this->getDefinedTable(Stock\ItemUomTable::class)->getNotIn($uom_item_id, array('item' => $result1));
	
			for($j=0; $j<sizeof($uom);$j++):
				//echo $uom_item_id[$j];
				if(isset($uom_item_id[$j]) && is_numeric($uom_item_id[$j]) && $uom_item_id[$j] > 0):
					$standarduom = ($uom[$j] == $form['st_uom'])?'1':'0';
					$uomdata = array(
							'id'			=>  $uom_item_id[$j],
							'item'			=>	$result1,
							'uom'			=>	$uom[$j],
							'conversion'	=>	$conversion[$j],
							'costing_uom'   =>  $standarduom,
							'author'		=>	$this->_author,
							'modified'		=>	$this->_modified,
					);
				else:
					$standarduom = ($uom[$j] == $form['st_uom'])?'1':'0';
					$uomdata = array(
							'item'			=>	$result1,
							'uom'			=>	$uom[$j],
							'conversion'	=>	$conversion[$j],
							'costing_uom'   =>  $standarduom,
							'author'		=>	$this->_author,
							'modified'		=>	$this->_modified,
							'created'       =>  $this->_created,
					);
				endif;
				$uomdata = $this->_safedataObj->rteSafe($uomdata);
				$result3 = $this->getDefinedTable(Stock\ItemUomTable::class)->save($uomdata);
			endfor;
			//echo "<pre>";print_r($uomdata);exit;
			foreach($delete_rows as $delete_row):
				$this->getDefinedTable(Stock\ItemUomTable::class)->remove($delete_row['id']);
			endforeach;*/
			
			if($result1 > 0):
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Successfully updated item ".$form['item_code']);
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to update item");
			endif;
			return $this->redirect()->toRoute('stock', array('action' => 'viewitem', 'id' => $result1));
		}
		$ViewModel = new ViewModel(array(
				'title'      => "Edit Item",
				'itemclasses' => $this->getDefinedTable(Stock\ItemClassTable::class) -> getAll(),
				'itemgroupsObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
				'itemsubgroupsObj' => $this->getDefinedTable(Stock\ItemSubGroupTable::class),
				'uomtypeObj'   => $this->getDefinedTable(Stock\UomTypeTable::class),
				'uomObj'     => $this->getDefinedTable(Stock\UomTable::class),
				'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'parties'    => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role' => '1')),
				'regions'    => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj'=> $this->getDefinedTable(Administration\LocationTable::class),
				'items'	     =>	$this->getDefinedTable(Stock\ItemTable::class)-> get(array('i.id' => $this->_id)),
				'reordersObj'=> $this->getDefinedTable(Stock\ReOrderTable::class),
				'itemuomObj' => $this->getDefinedTable(Stock\ItemUomTable::class),
				'costingFormulaObj' => $this->getDefinedTable(Stock\CostingFormulaTable::class),
				'locationtypes'=> $this->getDefinedTable(Administration\LocationTypeTable::class)->get(array('sales' => '1')),
				'marginsObj'	 => $this->getDefinedTable(Stock\MarginTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * View particular Item  
	 */	
	public function viewitemAction()
	{
		$this->init();
		return new ViewModel(array(
				'title'=>'View Item',
				'items'=> $this->getDefinedTable(Stock\ItemTable::class)->get($this->_id),
				'item_uoms'=> $this->getDefinedTable(Stock\ItemUomTable::class)->get(array('ui.item'=>$this->_id)),
				'reordersObj'=> $this->getDefinedTable(Stock\ReOrderTable::class),
				'marginsObj'=> $this->getDefinedTable(Stock\MarginTable::class),
				'locationtypeObj'=> $this->getDefinedTable(Administration\LocationTypeTable::class),
				'itemclassObj' => $this->getDefinedTable(Stock\ItemClassTable::class),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'costingFormulaObj' => $this->getDefinedTable(Stock\CostingFormulaTable::class),
				'itemgroupObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
				'itemsubgroupObj' => $this->getDefinedTable(Stock\ItemSubGroupTable::class),
				'partyObj' => $this->getDefinedTable(Accounts\PartyTable::class),
				'materials'=> $this->getDefinedTable(Stock\ItemMaterialTable::class)->get(array('item'=>$this->_id)),
				'materialsObj'=> $this->getDefinedTable(Stock\ItemMaterialTable::class),
		));
	}
	/**
	 * add item action
	 */	
	public function additemmaterialAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$uom = $this->getDefinedTable(Stock\ItemTable::class)->get(array('i.id'=>$form['item_name']));
			foreach($uom as $uoms);
			$unit = $this->getDefinedTable(Stock\UomTable::class)->getColumn(array('id'=>$uoms['uom']),'id');
				$data1 = array(
						'item'          	=> $form['item_id'],
						'location'          => $form['location'],
						'material_subgroup' => $form['item_subgroup'],
						'material'    		=> $form['item_name'],
						'quantity'        	=> $form['quantity'],
						'uom'          		=> $unit,	
					    'rate'				=> $form['rate'],
						'value'          	=> $form['value'],						
						'author'        	=> $this->_author,
						'created'       	=> $this->_created,
						'modified'      	=> $this->_modified,
				);
				$data1 = $this->_safedataObj->rteSafe($data1);
				$this->_connection->beginTransaction(); //***Transaction begins here***//
				$result1 = $this->getDefinedTable(Stock\ItemMaterialTable::class)->save($data1);
			
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
				 	$this->flashMessenger()->addMessage("success^ Successfully added new data ");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to add new data");
				endif;
				return $this->redirect()->toRoute('stock', array('action' => 'viewitem', 'id' => $form['item_id']));
		}
		$ViewModel = new ViewModel(array(
			'title'      => "Item Materials",
			'userid'     => $this->getDefinedTable(Administration\UsersTable::class)->get(array('id'=>$this->_author)),
			'itemclasses' => $this->getDefinedTable(Stock\ItemClassTable::class) -> getAll(),
			'itemgroupsObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
			'itemsubgroupsObj' => $this->getDefinedTable(Stock\ItemSubGroupTable::class),
			'item' => $this->getDefinedTable(Stock\ItemTable::class)->get($this->_id),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'locationtypes'=> $this->getDefinedTable(Administration\LocationTypeTable::class)->get(array('sales' => '1')),
			'elcformula' => $this->getDefinedTable(Stock\CostingFormulaTable::class)->get(array('costing_type'=>1)),
			'locationformula' => $this->getDefinedTable(Stock\CostingFormulaTable::class)->get(array('costing_type'=>2)),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * add item action
	 */	
	public function edititemmaterialAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$uom = $this->getDefinedTable(Stock\ItemTable::class)->get(array('i.id'=>$form['item_name']));
			foreach($uom as $uoms);
			$unit = $this->getDefinedTable(Stock\UomTable::class)->getColumn(array('id'=>$uoms['uom']),'id');
				$data1 = array(
						'id'          		=> $form[''],
						'item'          	=> $form['item_id'],
						'location'          => $form['location'],
						'material_subgroup' => $form['item_subgroup'],
						'material'    		=> $form['item_name'],
						'quantity'        	=> $form['quantity'],
						'uom'          		=> $unit,	
					    'rate'				=> $form['rate'],
						'value'          	=> $form['value'],						
						'author'        	=> $this->_author,
						'created'       	=> $this->_created,
						'modified'      	=> $this->_modified,
				);
				$data1 = $this->_safedataObj->rteSafe($data1);
				$this->_connection->beginTransaction(); //***Transaction begins here***//
				$result1 = $this->getDefinedTable(Stock\ItemMaterialTable::class)->save($data1);
			
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
				 	$this->flashMessenger()->addMessage("success^ Successfully added new data ");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to add new data");
				endif;
				return $this->redirect()->toRoute('stock', array('action' => 'viewitem', 'id' => $form['item_id']));
		}
		$ViewModel = new ViewModel(array(
			'title'      => "Item Materials",
			'userid'     => $this->getDefinedTable(Administration\UsersTable::class)->get(array('id'=>$this->_author)),
			'itemclasses' => $this->getDefinedTable(Stock\ItemClassTable::class) -> getAll(),
			'itemgroupsObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
			'itemsubgroupsObj' => $this->getDefinedTable(Stock\ItemSubGroupTable::class),
			'item' => $this->getDefinedTable(Stock\ItemTable::class)->get($this->_id),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'item_material' => $this->getDefinedTable(Stock\ItemMaterialTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * delete item material
	 */
	public function delitemmaterialAction(){
		$this->init();
		$material=$this->_id;
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		$result = $this->getDefinedTable(Stock\ItemMaterialTable::class)->remove($material);
		if($result > 0):
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Paydetail deleted successfully");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to delete Paydetail");
			endif;
			
		$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
		return $this->redirect()->toUrl($redirectUrl);
	}
	/**
	 * Delete the Particular items and its details 
	**/
	public function deleteitemAction()
	{
		$this->init();
		$item_id = $this->_id;
		//remove from margion table
		$margins = $this->getDefinedTable(Stock\MarginTable::class)->get(array('item'=>$item_id));
		foreach($margins as $margin):
			//echo "<pre>"; print_r($margin);
			$this->getDefinedTable(Stock\MarginTable::class)->remove($margin['id']);
		endforeach;
		
		//remove from reorder table
		$reorders = $this->getDefinedTable(Stock\ReOrderTable::class)->get(array('item'=>$item_id));
		foreach($reorders as $reorder):
			$this->getDefinedTable(Stock\ReOrderTable::class)->remove($reorder['id']);
		endforeach;
		
		//remove from item uom table
		$item_uoms = $this->getDefinedTable(Stock\ItemUomTable::class)->get(array('item'=>$item_id));
		foreach($item_uoms as $item_uom):
			//echo "<pre>"; print_r($item_uom);
			$this->getDefinedTable(Stock\ItemUomTable::class)->remove($item_uom['id']);
		endforeach;
		
		//remove from item table
		$stock_items = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
		$result = $this->getDefinedTable(Stock\ItemTable::class)->remove($item_id);
		
		if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully removed this particular item from this system");
		else:
			$this->flashMessenger()->addMessage("error^ Failed to delete this particular item");
		endif;
		return $this->redirect()->toRoute('stock', array('action' => 'index'));
		exit;
	}
	/**
	 * View Stock Items
	**/
	public function stockAction()
	{
		$this->init();
		
		$t_classes = $this->getDefinedTable(Stock\ItemClassTable::class)->getCount(0);
		$t_groups = $this->getDefinedTable(Stock\ItemGroupTable::class)->getCount(0);
		$t_subgroups = $this->getDefinedTable(Stock\ItemSubGroupTable::class)->getCount(0);
		$t_items = $this->getDefinedTable(Stock\ItemTable::class)->getCount(0);
		
		$t_agency = $this->getDefinedTable(Stock\ItemTable::class)->getCount(array('activity' => '1'));
		$t_foodgrain = $this->getDefinedTable(Stock\ItemTable::class)->getCount(array('activity' => '2'));
		
		$data = array(
				'class' => $t_classes,
				'group' => $t_groups,
				'subgroup' => $t_subgroups,
				'item' => $t_items,
		);
		return new ViewModel(array(
				'title' => 'Stock Item Details',
				'classes' => $this->getDefinedTable(Stock\ItemClassTable::class)->getAll(),
				'groupObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
				'subgroupObj' => $this->getDefinedTable(Stock\ItemSubGroupTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'data' => $data,
				'activitites' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
		));
	}
	/**
	 * checkavailability Action
	**/
	public function getcheckavailabilityAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		switch ($form['type']) {
			case 'item_code':
				$item_code =$form['item_code'];
				// Check the item code existence ...
				$result = $this->getDefinedTable(Stock\ItemTable::class)->isPresent('code', $item_code);
				break;

			case 'item_name':
			//default:
				$item_name = $form['item_name'];
				// Check the item name existence ...
				$result = $this->getDefinedTable(Stock\ItemTable::class)->isPresent('name', $item_name);
				break;
		}
		
		echo json_encode(array(
					'valid' => $result,
		));
		exit;
	}
	
	/**
	 * Selling price of  item action
	 */
	public function sellingpriceAction()
	{
		$this->init();		
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$subgroup = $form['item_subgroup'];
		}else{
			$act = 2; //default FG
			$subgroup = -1; //default All
		}
		$pricing = $this->getDefinedTable(Stock\SellingPriceTable::class)->getAll($subgroup);		
		return new ViewModel( array(
				'title' => "Selling price of items",
				'activity_id' => $act,
				'subgroup_id' => $subgroup,
				'pricing' => $pricing,
				'itemgroups' => $this->getDefinedTable(Stock\ItemGroupTable::class)->getAll(),
				'itemclasses' => $this->getDefinedTable(Stock\ItemClassTable::class)->getAll(),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
				'itemclassObj' => $this->getDefinedTable(Stock\ItemClassTable::class),
				'itemgroupObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
				'itemsubgroupObj' => $this->getDefinedTable(Stock\ItemSubGroupTable::class),
		) );
	}
	/**
	 * add selling price action
	 */
	public function addsellingpriceAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$uom = $this->getDefinedTable(Stock\ItemTable::class)->getColumn(array('id'=>$form['item']),'uom');
			$data = array(
					'batch' => 1,
					'supplier' => $form['supplier'],
					'item' => $form['item'],
					'uom' => $uom,
					'quantity' => $form['quantity'],
					'rate_nu' => $form['rate_nu'],
					'sale_tax' => $form['sale_tax'],
					'freight_charge' => $form['freight_charge'],
					'total_charge' => $form['total_charge'],
					'cost_price' => $form['cost_price'],
					'selling_price' => $form['selling_price'],
					'specification' => $form['specification'],
					'remarks' => $form['remarks'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($data);exit;
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\SellingPriceTable::class)->save($data);
	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully generated selling price");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to generate selling price");
			endif;
			return $this->redirect()->toRoute('stock',array('action' => 'sellingprice'));
		}
		return new ViewModel( array(
			'title' => "Selling Price",
			'supplier' => $this->getDefinedTable(Accounts\PartyTable::class)->getAll(),
			'item' => $this->getDefinedTable(Stock\ItemTable::class)->getAll(),
			) );
	}
	/**
	 * get cost details
	 */
	public function getcostdetailsAction()
	{		
		$form = $this->getRequest()->getPost();
		$itemId =$form['itemId'];
		$supplierId =$form['supplierId'];
		$charges = $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->getCharges(array('item'=>$itemId,'supplier'=>$supplierId));
		foreach($charges as $charge):
			$quantity=$charge['quantity'];
			$freight_charge=$charge['freight_charge'];
			$paid_amount=$charge['paid_amount'];
		endforeach;
		echo json_encode(array(
				'quantity' => $quantity,
				'freight_charge' => $freight_charge,
				'paid_amount' => $paid_amount,
		));
		exit;
	}
	/**
	 * Get item
	 */
	public function getitemAction()
	{		
		$form = $this->getRequest()->getPost();
		$supplierId = $form['supplierId'];
		$itm_list = $this->getDefinedTable(Stock\ItemTable::class)->get(array('supplier'=>$supplierId));
		
		$item = "<option value=''></option>";
		foreach($itm_list as $itm_lists):
			$item.="<option value='".$itm_lists['id']."'>".$itm_lists['name']."</option>";
		endforeach;
		echo json_encode(array(
				'item' => $item,
		));
		exit;
	}
	/**
	 * Get item by Subgroup
	 */
	public function getitembysubgroupAction()
	{		
		$form = $this->getRequest()->getPost();
		$subgroupId = $form['subgroupId'];
		$itm_list = $this->getDefinedTable(Stock\ItemTable::class)->get(array('item_subgroup'=>$subgroupId));
		$item = "<option value=''></option>";
		foreach($itm_list as $itm_lists):
			$item.="<option value='".$itm_lists['id']."'>".$itm_lists['name']."</option>";
		endforeach;
		echo json_encode(array(
				'item' => $item,
		));
		exit;
	}
	/**
	 * Get uom,rate,stock quantity
	 */
	public function getuomquantityrateAction()
	{		
		$form = $this->getRequest()->getPost();
		$itemId = $form['itemId'];
		$locationId=$form['locationId'];
		$uom = $this->getDefinedTable(Stock\ItemTable::class)->get(array('i.id'=>$itemId));
		foreach($uom as $uoms);
		$unit = $this->getDefinedTable(Stock\UomTable::class)->getColumn(array('id'=>$uoms['uom']),'code');
		$itm_list = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('item'=>$itemId,'location'=>$locationId));
		foreach($itm_list as $itm_lists);
		//print_r($itm_lists);exit;
		$st_quantity=$itm_lists['quantity'];
		$rate=$itm_lists['selling_price'];
		echo json_encode(array(
				'uom' => $unit,
				'st_quantity' => $st_quantity,
				'rate' => $rate,
		));
		exit;
	}
	/**
	 * confirm dispatch goods received
	 */
	public function commititemmaterialAction()
	{
		$this->init();
		
		$item_id = $this->_id;
		$materials_used = $this->getDefinedTable(Stock\ItemMaterialTable::class)->get(array('item'=>$this->_id,'status'=>3));
		foreach($materials_used as $materials):
		$opening_stock_details = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('item'=>$materials['material'],'location'=>$materials['location']));
			foreach($opening_stock_details as $opn_stk_detls);
				$remaining_qty=$opn_stk_detls['quantity']-$materials['quantity'];
				$data1 = array(
					'id'	=> $opn_stk_detls['id'],
					'quantity'	=>$remaining_qty,
					'author' => $this->_author,
					'created' => $this->_modified,
				);
				$data1   = $this->_safedataObj->rteSafe($data1);
				$result1 = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($data1);
				$opening_st_id=$opn_stk_detls['opening_stock'];
				$opening_stock_qty = $this->getDefinedTable(Stock\OpeningStockTable::class)->getColumn(array('id'=>$opn_stk_detls['opening_stock']),'quantity');
				$deducted_qty = $opening_stock_qty - $materials['quantity'];
				$data2 = array(
					'id'	=> $opn_stk_detls['opening_stock'],
					'quantity'	=>$deducted_qty,
					'author' => $this->_author,
					'created' => $this->_modified,
				);
				$data2   = $this->_safedataObj->rteSafe($data2);
				$result2 = $this->getDefinedTable(Stock\OpeningStockTable::class)->save($data2);
				$data4 = array(
				'id'	=> $materials['id'],
				'status'	=> 4,
				'author' => $this->_author,
				'created' => $this->_modified,
				);
				$data4   = $this->_safedataObj->rteSafe($data4);
				$result4 = $this->getDefinedTable(Stock\ItemMaterialTable::class)->save($data4);
			endforeach; 
			$data3 = array(
				'id'	=> $item_id,
				'author' => $this->_author,
				'created' => $this->_modified,
			);
			$data3   = $this->_safedataObj->rteSafe($data3);
			$result3 = $this->getDefinedTable(Stock\ItemTable::class)->save($data3);
			
		if($result3){
			$this->flashMessenger()->addMessage("success^ Successfully Committed and stock deducted from current stock");
		}else{
			$this->flashMessenger()->addMessage("error^ Failed to Commit");
		}
		return $this->redirect()->toRoute('stock',array('action'=>'viewitem','id'=>$item_id));
	}
	

}

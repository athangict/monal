<?php
namespace Sales\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl; 
use Administration\Model As Administration;
use Hr\Model As Hr;
use Sales\Model As Sales;
use Stock\Model As Stock;
use Accounts\Model As Accounts;

class SchemeController extends AbstractActionController
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
	
    //echo 'hellow';
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
		
		//$this->_safedataObj = $this->SafeDataPlugin();

		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
		
	/**
	 * scheme Action
	 */
	public function schemeAction()
	{
		//echo 'hellow';//exit;
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
		}else{
			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;
		}
		$data = array(
				'year' => $year,
				'month' => $month,
			);
		return new ViewModel(array(
				'title' 	  => 'Schemes',
				'schemes' 	  => $this->getDefinedTable(Sales\SchemeTable::class)->getDateWise('scheme_date',$year,$month),
				'minYear' => $this->getDefinedTable(Sales\SchemeTable::class)->getMin('scheme_date'),
				'data' => $data,
				'subheadObj'  => $this->getDefinedTable(Accounts\SubHeadTable::class),
				'partyObj' => $this->getDefinedTable(Accounts\PartyTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'schemetypeObj' => $this->getDefinedTable(Sales\SchemeTypeTable::class),
		));
	}
	/**
	 * Add scheme detail Action
	 */
	public function addsdetailsAction()
	{
		$this->init();

	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$item_id = $form['item'];
            $cur_start_date = strtotime($form['from_date']);

			$item_name = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id, 'code');
			$pre_end_dates = $this->getDefinedTable(Sales\SchemeTable::class)->get(array('item'=>$item_id));
			foreach($pre_end_dates as $pre_end_date):
				extract($pre_end_date);
				$p_end_date = $to_date;
				$sc_status = $status;
				$p_end_date = strtotime($p_end_date);
			endforeach;

			//if($cur_start_date > $p_end_date || $sc_status == 4){			
				$date = date('ym',strtotime($form['scheme_date']));					
				$tmp_SCNo = "SC".$date; 			
				$results = $this->getDefinedTable(Sales\SchemeTable::class)->getMonthlySC($tmp_SCNo);
				
			    $sc_no_list = array();
	            foreach($results as $result):
			        array_push($sc_no_list, substr($result['scheme_no'], 6)); 
			    endforeach;
	            $next_serial = max($sc_no_list) + 1;
				
				switch(strlen($next_serial)){
				    case 1: $next_sc_serial = "00".$next_serial;  break;
				    case 2: $next_sc_serial = "0".$next_serial;   break;
				   default: $next_sc_serial = $next_serial;       break; 
				}					   
				
				$scheme_no = $tmp_SCNo.$next_sc_serial;
				
				$data = array(
						 'scheme_no'    => $scheme_no, 
						 'scheme_date'  => $form['scheme_date'],
						 'activity'     => $form['activity'],
						 'supplier'     => $form['supplier'],
						 'sub_head'     => $form['sub_head'],
						 'item'    		=> $form['item'],
						 'scheme_type'  => $form['scheme_type'],
						 'from_date'    => $form['from_date'],
						 'to_date'   	=> $form['to_date'],
						 'note'         => $form['note'],
						 'claim'		=> $form['claim'],
						 'status' 		=> '1',
						 'author'       => $this->_author,
						 'created'      => $this->_created,
						 'modified'     => $this->_modified
				);
				$data   = $this->_safedataObj->rteSafe($data);
				$result = $this->getDefinedTable(Sales\SchemeTable::class)->save($data);
				if($result > 0){ 
					 $uom            = $form['uom']; 
					 $qty_slab1      = $form['qty_slab1'];
					 $qty_slab2      = $form['qty_slab2'];
					 $unit_quantity  = $form['unit_quantity'];
					 $free_item      = $form['free_item'];
					 $free_item_uom  = $form['free_item_uom'];
					 $discount_qty   = $form['discount_qty'];
					 
					 for($i=0; $i < sizeof($uom); $i++):
					   if(isset($uom[$i]) && $uom[$i] > 0):				      
					      $sc_details = array(
					      		'scheme' 		=> $result,
					      		'uom' 			=> $uom[$i], 
					      		'qty_slab1' 	=> $qty_slab1[$i], 
					      		'qty_slab2' 	=> $qty_slab2[$i], 
					      		'unit_quantity' => $unit_quantity[$i],
					      		'free_item' 	=> $free_item[$i],
					      		'free_item_uom' => $free_item_uom[$i],
					      	    'discount_qty' 	=> $discount_qty[$i],
					      	    'author'    	=> $this->_author,
					      		'created'   	=> $this->_created,
					      		'modified'  	=> $this->_modified
					      );	
					     $sc_details   = $this->_safedataObj->rteSafe($sc_details);
					     $this->getDefinedTable(Sales\SchemeDetailsTable::class)->save($sc_details);		
					   endif; 		     
					 endfor;
					 $this->flashMessenger()->addMessage("success^ Successfully added new scheme ". $scheme_no);
					 return $this->redirect()->toRoute('schemes', array('action' =>'viewsdetails', 'id' => $result));
				}
				else{
					$this->flashMessenger()->addMessage("error^ Failed to add new Scheme Details");
					return $this->redirect()->toRoute('schemes', array('action'=>'addsdetails'));
				}
			//}
			//else{
				//$this->flashMessenger()->addMessage("error^ Failed to Add scheme, You need to cancel the existing scheme to add new scheme with -".$item_name.".");
					//return $this->redirect()->toRoute('schemes', array('action'=>'scheme'));
			//}			
		}	
				
		return new ViewModel( array(
				'suppliers'   => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'1')),
				'schemetypes' => $this->getDefinedTable(Sales\SchemeTypeTable::class),
				'subheadObj' => $this->getDefinedTable(Accounts\SubHeadTable::class),
				'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				//'scheme' => $this->getDefinedTable(Sales\SchemeTable::class)->getAll(),
				'schemedetails' => $this->getDefinedTable(Sales\SchemeDetailsTable::class)->getAll(),
				'activities'  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
		) );	
	}
	/**
	 * Get Item By Activity
	 **/
	public function getitemactivityAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$activity_id = $form['activity'];
		
		$itembyactivity = $this->getDefinedTable(Stock\ItemTable::class)->get(array('i.activity'=>$activity_id));
		
		$items .="<option value=''></option>";
		foreach($itembyactivity as $item):
			$items .="<option value='".$item['id']."'>".$item['code']."</option>";
		endforeach;
		
		echo json_encode(array(
					'items' => $items,
		));
		exit;
	}
	
	/**
	 * Get Item Uom
	 **/
	public function getitemuomAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$item_id = $form['item'];
		
		/***** UOM Options *****/
		$basicuoms = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
		$itemuoms = $this->getDefinedTable(Stock\ItemUomTable::class)->get(array('ui.item'=>$item_id));
		$uoms .="<option value=''></option>";
		foreach($basicuoms as $basicuom):
			$uoms .="<option value='".$basicuom['st_uom_id']."'>".$basicuom['st_uom_code']."</option>";
		endforeach;
		foreach($itemuoms as $itemuom):
			$uoms .="<option value='".$itemuom['uom_id']."'>".$itemuom['uom_code']."</option>";
		endforeach;
		echo json_encode(array(
					'uoms' => $uoms,
		));
		exit;
	}

	/**
	 * Edit Scheme Details Action
	 */
	public function viewsdetailsAction()
	{
		$this->init();
		return new ViewModel( array(
				'schemes' 		 => $this->getDefinedTable(Sales\SchemeTable::class)->get($this->_id),
				'schemedtls'     => $this->getDefinedTable(Sales\SchemeDetailsTable::class), 
				'userTable'      => $this->getDefinedTable(Acl\UsersTable::class),
				'schemetypeObj'     => $this->getDefinedTable(Sales\SchemeTypeTable::class),
				'activityObj'  => $this->getDefinedTable(Administration\ActivityTable::class),
				'supplierObj'   => $this->getDefinedTable(Accounts\PartyTable::class),
				'itemObj'     => $this->getDefinedTable(Stock\ItemTable::class),
				'subheadObj' => $this->getDefinedTable(Accounts\SubHeadTable::class),
				'uomObj'=> $this->getDefinedTable(Stock\UomTable::class),
				'schemebatchObj'=> $this->getDefinedTable(Sales\SchemeBatchTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'batchObj' => $this->getDefinedTable(Stock\BatchTable::class),
				'statusObj'      => $this->getDefinedTable(Acl\StatusTable::class),
		) );
	}

	/**
	 * Edit Scheme detail Action
	 */
	public function editsdetailsAction()
	{
		$this->init();

		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();

			$data = array(
					 'id'           => $form['sc_id'],
					 'scheme_no'    => $form['scheme_no'], 
					 'scheme_date'  => $form['scheme_date'],
					 'activity'     => $form['activity'],
					 'supplier'     => $form['supplier'],
					 'sub_head'     => $form['sub_head'],
					 'item'    		=> $form['item'],
					 'scheme_type'  => $form['scheme_type'],
					 'from_date'    => $form['from_date'],
					 'to_date'   	=> $form['to_date'],
					 'note'         => $form['note'],
					 'claim'		=> $form['claim'],
					 'status' 		=> '1',
					 'author'       => $this->_author,
					 'modified'     => $this->_modified
			);		 

			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\SchemeTable::class)->save($data);
			if($result > 0){ 
				 $details_id	 = $form['details_id'];
				 $uom            = $form['uom']; 
				 $qty_slab1      = $form['qty_slab1'];
				 $qty_slab2      = $form['qty_slab2'];
				 $unit_quantity  = $form['unit_quantity'];
				 $free_item      = $form['free_item'];
				 $free_item_uom  = $form['free_item_uom'];
				 $discount_qty   = $form['discount_qty'];
				 $deleted_rows = $this->getDefinedTable(Sales\SchemeDetailsTable::class)->getNotIn($details_id, array('scheme' => $result));
				 for($i=0; $i < sizeof($uom); $i++):
				   if(isset($uom[$i]) && $uom[$i] > 0):				      
				      $sc_details = array(
				      		'id'			=> $details_id[$i],
				      		'scheme' 		=> $result,
				      		'uom' 			=> $uom[$i], 
				      		'qty_slab1' 	=> $qty_slab1[$i], 
				      		'qty_slab2' 	=> $qty_slab2[$i], 
				      		'unit_quantity' => $unit_quantity[$i],
				      		'free_item' 	=> $free_item[$i],
				      		'free_item_uom' => $free_item_uom[$i],
				      	    'discount_qty' 	=> $discount_qty[$i],
				      	    'author'    	=> $this->_author,
				      		'modified'  	=> $this->_modified
				      );
				      $sc_details   = $this->_safedataObj->rteSafe($sc_details);
				     $this->getDefinedTable(Sales\SchemeDetailsTable::class)->save($sc_details);		
				   endif; 		     
				 endfor;
				 foreach($deleted_rows as $delete_row):
				 	$this->getDefinedTable(Sales\SchemeDetailsTable::class)->remove($delete_row['id']);
				 endforeach;

				 $this->flashMessenger()->addMessage("success^ Successfully updated scheme no ".$form['scheme_no']);
				 return $this->redirect()->toRoute('schemes', array('action' =>'viewsdetails', 'id' => $result));
			}
			else{
				$this->flashMessenger()->addMessage("error^ Failed to update Scheme Details");
				return $this->redirect()->toRoute('schemes', array('action' => 'editsdetails', 'id' => $this->_id));
			}		
		}
		return new ViewModel(array(
				'suppliers'   		=> $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'1')),
				'schemetype' 		=> $this->getDefinedTable(Sales\SchemeTypeTable::class)->getAll(),
				'subheadObj' 		=> $this->getDefinedTable(Accounts\SubHeadTable::class),
				'headObj' 			=> $this->getDefinedTable(Accounts\HeadTable::class),
				'itemObj' 			=> $this->getDefinedTable(Stock\ItemTable::class),
				'scheme' 			=> $this->getDefinedTable(Sales\SchemeTable::class)->get($this->_id),
				'schemedetails'	 	=> $this->getDefinedTable(Sales\SchemeDetailsTable::class)->get(array('scheme'=>$this->_id)),
				'activityObj'  		=> $this->getDefinedTable(Administration\ActivityTable::class),
				'statusObj'      	=> $this->getDefinedTable(Acl\StatusTable::class),
				'uomObj'            => $this->getDefinedTable(Stock\UomTable::class),
				'itemuomObj'        => $this->getDefinedTable(Stock\ItemUomTable::class),
			)
		);
	}

	/**
	 * cancel scheme Detail Action
	 * 
	 */
	public function cancelschemesAction()
	{
		$this->init();
		
		$scheme = $this->getDefinedTable(Sales\SchemeTable::class)->get($this->_id);
		
		foreach ($scheme as $schemes):
			$data = array(
					'id'			=>$this->_id,
					'status' 		=> 4,
					'author'	    => $this->_author,
					'modified'      => $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\SchemeTable::class)->save($data);
			if($result):
			$this->flashMessenger()->addMessage("success^ Successfully cancelled Scheme no ".$schemes['scheme_no']);
			return $this->redirect()->toRoute('schemes');
			endif;
		endforeach;
	}
	
	/**
	 * commit scheme Detail Action
	 * 
	 */
	public function commitschemesAction()
	{
		$this->init();
		
		$scheme = $this->getDefinedTable(Sales\SchemeTable::class)->get($this->_id);
		
		foreach ($scheme as $schemes):
		$data = array(
				'id'			=>$this->_id,
				'status' 		=> 3,
				'author'	    => $this->_author,
				'modified'      => $this->_modified,
		);
		$data   = $this->_safedataObj->rteSafe($data);
		$result = $this->getDefinedTable(Sales\SchemeTable::class)->save($data);
		if($result):
			$this->flashMessenger()->addMessage("success^ Successfully commited Scheme no ".$schemes['scheme_no']);
			return $this->redirect()->toRoute('schemes',array('action'=>'viewsdetails','id'=>$this->_id));
		endif;
		endforeach;
	}
	
	/**
	* Add scheme batches Action
	*/
	public function addscbatchAction()
	{
		$this->init();
		$scheme_id = $this->_id;
		$item_id = $this->getDefinedTable(Sales\SchemeTable::class)->getColumn($scheme_id,'item');

		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
            $sc_id = $form['scheme_id'];
			
			$valuation = $form['item_valuation'];
			$batch = ($valuation == 0)?$form['batches']:0;
			//echo $valuation; exit;
			if($valuation == 0){ //FIFO
				$schemeAll = $this->getDefinedTable(Sales\SchemeBatchTable::class)->get(array('scheme'=>$sc_id,'batch'=>'-1','location'=>'-1'));
			}
			else{ // Wt moving avg
				$schemeAll = $this->getDefinedTable(Sales\SchemeBatchTable::class)->get(array('scheme'=>$sc_id,'location'=>'-1'));
			}
			
			if(sizeof($schemeAll)>0):
				$this->flashMessenger()->addMessage("notice^ Scheme is already Mapped to the Item/Item-Batch and Location");
			else:
				$data = array(
						'scheme' => $sc_id,
						'batch' => $batch,
						'location' => $form['locations'],
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
				);
				$data =  $this->_safedataObj->rteSafe($data);
				$result = $this->getDefinedTable(Sales\SchemeBatchTable::class)->save($data);
				if($result > 0):
					$this->flashMessenger()->addMessage("success^ Successfully Mapped Scheme to Item/Item-Batch and Location");
				else:
					$this->flashMessenger()->addMessage("notice^ Failed to map Scheme to Item/Item-Batch and Location");
				endif;
			endif;
			return $this->redirect()->toRoute('schemes', array('action'=>'viewsdetails', 'id'=> $sc_id));
			
		}
		$ViewModel = new ViewModel(array(
			'scheme_id' => $scheme_id,
			'item_id' => $item_id,
			'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
			'batchObj'  =>$this->getDefinedTable(Stock\BatchTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
		
	
	/**
	 *Delete Batches mapped to Scheme
	 **/
	public function deletescbatchAction()
	{
		$this->init();

		$scheme_batch_id = $this->_id;
		$scheme_id = $this->getDefinedTable(Sales\SchemeBatchTable::class)->getColumn($scheme_batch_id,'scheme');
        $result = $this->getDefinedTable(Sales\SchemeBatchTable::class)->remove($scheme_batch_id);
    	if($result > 0):
		    $this->flashMessenger()->addMessage("success^ Successfully deleted the Mapping of Scheme to Item/Item-Bactch and Location");
		else:
		    $this->flashMessenger()->addMessage("notice^ Failed to delete the Mapping of Scheme to Item/Item-Bactch and Location");
		endif;
        return $this->redirect()->toRoute('schemes', array('action' => 'viewsdetails', 'id' => $scheme_id));
	}
	 
	/*
	 * Edit scheme batch 
	 **/
	public function editscbatchAction()
	{
		$this->init();
	 	$scheme_id = $this->_id;
	 	$item_id = $this->getDefinedTable(Sales\SchemeTable::class)->getColumn($scheme_id, 'item');
	 	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$valuation = $form['item_valuation'];
			$batch = ($valuation == 0)?$form['batches']:0;
			
			$data=array(
					'id'          => $form['sc_id'],
					'scheme'	  => $form['scheme_id'],
					'batch'		  => $batch,
					'location'	  => $form['location'],
					'author'      => $this->_author,
					'modified'    => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data); 
			$result = $this->getDefinedTable(Sales\SchemeBatchTable::class)->save($data);
			
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully updated the Scheme Mapping");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to update Scheme Mapping");
			endif;
			return $this->redirect()->toRoute('schemes', array('action' => 'viewsdetails', 'id' => $form['scheme_id']));
			
		}
	 	$ViewModel = new ViewModel(array(
	 		'scheme_id' => $scheme_id,
	 		'item_id' => $item_id,
	 		'itemObj'	=> $this->getDefinedTable(Stock\ItemTable::class),
	 		'batchObj'  =>$this->getDefinedTable(Stock\BatchTable::class),
	 		'schemebatches' => $this->getDefinedTable(Sales\SchemeBatchTable::class)->get(array('scheme'=>$scheme_id)),
	 		'locationObj'=> $this->getDefinedTable(Administration\LocationTable::class),
	 	));
	 	$ViewModel->setTerminal(True);
	 	return $ViewModel;
	}
	/**
	 * Adjust Scheme End Date Action
	**/
	public function adjustenddateAction()
	{
		$this->init();
		$scheme_id = $this->_id;
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'id' => $form['scheme_id'],
				'to_date' => $form['to_date'],
				'author'      => $this->_author,
				'modified'    => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data); 
			$result = $this->getDefinedTable(Sales\SchemeTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully updated the Scheme End Date");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to update Scheme End Date");
			endif;
			return $this->redirect()->toRoute('schemes', array('action' => 'viewsdetails', 'id' => $form['scheme_id']));
		}
		$ViewModel = new ViewModel(array(
				'title' => 'Adjust Scheme End Date',
				'schemes' => $this->getDefinedTable(Sales\SchemeTable::class)->get($scheme_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
}

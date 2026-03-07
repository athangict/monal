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
use Acl\Model As Acl;
use Stock\Model As Stock;
use Administration\Model As Administration;
use Purchase\Model As Purchase;
use Accounts\Model As Accounts;
class CostingController extends AbstractActionController
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
    protected $_id; 		 // route parameter id, usally used by crude
    protected $_auth; 		 // checking authentication
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
		$view = new ViewModel( array(
				'controller' => "Costing",
		) );
	}
	
	public function resetelcAction()
	{
	   $this->init();
	   $param = explode("-",$this->_id);
	   $cost_sheet_id = $param['0'];
	   $cost_item_id = $param['1'];
	   
	    $this->_connection->beginTransaction(); //***Transaction begins here***//
		if($cost_sheet_id > 0){
			$costingItemDtls = $this->getDefinedTable(Stock\CostingItemsTable::class)->get(array('costing_sheet'=>$cost_sheet_id));
			foreach( $costingItemDtls as $row):
			    if($row['status']=='1'):
				$dtls = $this->getDefinedTable(Stock\CostSheetDtlsTable::class)->get(array('costing_item'=>$row['id']));
			    if(sizeof($dtls)>0){
				  $result = $this->getDefinedTable(Stock\CostSheetDtlsTable::class)->remove(array('costing_item'=>$row['id']));
				  if($result) { $deleteAction = $this->getDefinedTable(Stock\CostingItemsTable::class)->remove($row['id']);  }
				}else{
				  $deleteAction = $this->getDefinedTable(Stock\CostingItemsTable::class)->remove($row['id']); 
				}
			    endif; 
			endforeach;
		}		   
		elseif($cost_item_id > 0){	        
			$costingItemStatus = $this->getDefinedTable(Stock\CostingItemsTable::class)->getColumn($cost_item_id, 'status');
			if($costingItemStatus == '1'):
			   $cost_sheet_id = $this->getDefinedTable(Stock\CostingItemsTable::class)->getColumn($cost_item_id, 'costing_sheet');
			   $dtls = $this->getDefinedTable(Stock\CostSheetDtlsTable::class)->get(array('costing_item'=>$cost_item_id));
			   if(sizeof($dtls)>0){
				   $result = $this->getDefinedTable(Stock\CostSheetDtlsTable::class)->remove(array('costing_item'=>$cost_item_id));
				   if($result) { $deleteAction = $this->getDefinedTable(Stock\CostingItemsTable::class)->remove($cost_item_id);  }
			   }
			   else{
				   $deleteAction = $this->getDefinedTable(Stock\CostingItemsTable::class)->remove($cost_item_id); 
			   }
			endif; 
		}
	   if($deleteAction){
		   $this->_connection->commit(); // commit transaction over success
	   }
	   else{
		  $this->_connection->rollback(); // rollback transaction over failure
	   }
	   return $this->redirect()->toRoute('cost', array('action' => 'elccosting', 'id' => $cost_sheet_id));
	}
	
	/*
	 * ELC costing
	 **/
	public function elccostingAction()
	{
	   $this->init();	   
	   if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();		 
			$costItems = $form['view_dtls'];
			$task = $form['btn'];
			$costing_sheet_id = $form['costing_sheet_id'];
			$costing_item_id = $form['costing_item_id'];
			$invoice_no = $form['supplier_invoice'];
			$po_invoice = $form['elc_source'];
			//echo $po_invoice; exit;
			$this->_connection->beginTransaction(); //***Transaction begins here***//	
			if($task == "commit"):
		        if(sizeof($costItems) == 0): 
				     $this->flashMessenger()->addMessage("notice^ Please select the checkbox to commit.");
				     return $this->redirect()->toRoute('cost', array('action' => 'elccosting', 'id' => $costing_sheet_id));
				endif; 
				$po_invoice_no = $this->getDefinedTable(Stock\CostingSheetTable::class)->getColumn($costing_sheet_id, 'po_invoice_no');
				$costing_location = $this->getDefinedTable(Stock\CostingSheetTable::class)->getColumn($costing_sheet_id, 'location');
               
				for($i = 0; $i < sizeof($costItems); $i++):			   
					//Batching of item 
					$costing_item_id = $costItems[$i];
					$itemID = $this->getDefinedTable(Stock\CostingItemsTable::class)->getColumn($costing_item_id,'item');
					$itemCode = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($itemID,'item_code');	
					$activity_id = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($itemID,'activity');
					$itemValuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($itemID,'valuation');	
					$location_costing = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($itemID,'transportation_charge');	
					$location_formula = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($itemID,'location_formula');	
					
					$costItemDtls = $this->getDefinedTable(Stock\CostingItemsTable::class)->get($costing_item_id);			   	    
			   	    foreach ($costItemDtls  as $itemdtl):
						//echo"<pre>"; print_r($itemdtl ); exit; 
						$costing_sheet_no = $itemdtl['costing_sheet'];
						if($po_invoice == '1'){ $inv_qty = '0'; }else{ $inv_qty = $itemdtl['qty']; }			   	          
						$item_id = $itemdtl['item'];
						$sinv_detailID = $itemdtl['po_sinv_dtl'];
						$unit_uom = $itemdtl['unit_uom'];
			   	    endforeach;	
					
					$landed_cost = $this->getDefinedTable(Stock\CostSheetDtlsTable::class)->getColumn(array('costing_item'=>$costing_item_id,'elc'=>1),'value');
					
					/* Check for existing batch */
					$trip_id = $this->getDefinedTable(Stock\TripTable::class)->getColumn(array('status'=>1),'id');
					if($location_costing == 1):
					    if($activity_id == 7):
					       $charge_sum = '5';
					   else:
						    $charge_sum = '100';
						endif;
					endif;
					$item_margin = $this->getDefinedTable(Stock\MarginTable::class)->getSUM(array('item'=>$itemID),'margin');
					if($location_costing == 1):
						$existing_Batches =  $this->getDefinedTable(Stock\BatchTable::class)->getExistingBatch(array('location'=>$costing_location,'item'=>$itemID, 'landed_cost'=>$landed_cost,'trip'=>$trip_id,'location_formula'=>$location_formula,'charge_sum'=>$charge_sum,'margin_sum'=>$item_margin,'status'=>'3'));
					else:
						$existing_Batches =  $this->getDefinedTable(Stock\BatchTable::class)->getExistingBatch(array('location'=>$costing_location,'item'=>$itemID, 'landed_cost'=>$landed_cost,'margin_sum'=>$item_margin,'status'=>'3'));
					endif;
					$multiple_array = array();
					foreach($existing_Batches as $existings):
						array_push($multiple_array,$existings['id']);
					endforeach;
					$max_existing_batch = max($multiple_array);
					$existing_Batch =  $this->getDefinedTable(Stock\BatchTable::class)->getExistingBatch($max_existing_batch);
					//echo sizeof($existing_Batch); exit;
					if(sizeof($existing_Batch)>0){
						foreach($existing_Batch as $dtl):				 
						    $existing_batch_id = $dtl['id'];
						    $batch_qty = $dtl['quantity'];
						    //update the batch table's qty
					        $new_actual_qty = $existing_actual_qty + $inv_qty;
						 	$new_qty = $existing_qty + $inv_qty; 
							$new_batch_qty = $batch_qty + $inv_qty;  
							/*Update the batch and batch details with new quantity */
							$batch_data = array(
								 'id'          => $existing_batch_id,
								 'quantity'    => $new_batch_qty,                									   	    		
								 'modified'    => $this->_modified
			   	            );
							//echo"<pre>"; print_r($batch_data); exit; 
			   	            $batchResult = $this->getDefinedTable(Stock\BatchTable::class)->save($batch_data);
							if($batchResult){	
								$existing_batchDtls = $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('location'=>$dtl['location'],'batch'=>$dtl['id']));
							    //echo"<pre>"; print_r($existing_batchDtls); exit; 
								if(sizeof($existing_batchDtls)>0){
									foreach($existing_batchDtls as $batchdtl_row):
										$existing_batchdtl_id = $batchdtl_row['id'];
										$existing_actual_qty1 = $batchdtl_row['actual_quantity'];
										$existing_qty1 = $batchdtl_row['quantity'];
									endforeach;	
									$new_actual_qty1 = $existing_actual_qty1 + $inv_qty;
									$new_qty1 = $existing_qty1 + $inv_qty; 
								}
								$batch_dtl_data = array(
									'id'              => $existing_batchdtl_id,
									'actual_quantity' => $new_actual_qty1,
									'quantity'        => $new_qty1,                									   	    		
									'modified'        => $this->_modified
			   	                );	
			   	                //echo"<pre>"; print_r($batch_dtl_data); exit; 
			   	                $batchDtlResult = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($batch_dtl_data);
								if($batchDtlResult){
									if($itemValuation == "1"){
										$movingItemDtl = $this->getDefinedTable(Stock\MovingItemTable::class)->get(array('location'=>$dtl['location'], 'item'=>$item_id));
										if(sizeof($movingItemDtl) > 0){
											foreach($movingItemDtl as $mvdtl):
												$moving_item_id = $mvdtl['id'];
												$exist_qty = $mvdtl['quantity'];
											endforeach;
											$new_mv_qty =  $exist_qty + $inv_qty;
											$mv_data = array(
												   'id'       => $moving_item_id,
												   'batch'    =>$existing_batch_id,
												   'quantity' => $new_mv_qty,
												   'modified' => $this->_modified
											);
											$mvResult = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);	
										}else{
											$cur_location = $dtl['location'];
											$uom = $this->getDefinedTable(Stock\BatchTable::class)->getColumn($existing_batch_id,'uom');
											$location_type = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($dtl['location'], 'location_type');			   	    
											$marginDtls = $this->getDefinedTable(Stock\MarginTable::class)->get(array('item'=>$itemID, 'location_type'=>$location_type));
											if(sizeof($marginDtls)>0){
												foreach ($marginDtls as $dtl):
													$margin_value = $dtl['margin'];
												endforeach;	
												$selling_price = ($landed_cost + $landed_cost * ($margin_value/100))/$unit_uom;
												$mv_data = array(
														'item'   		=> $itemID,
														'uom'    		=> $uom,
														'batch'  		=> $existing_batch_id,
														'location'  	=> $cur_location,
														'quantity'  	=> $inv_qty,
														'selling_price' => $selling_price,
														'author'        => $this->_author,
														'created'       => $this->_created,
														'modified'      => $this->_modified							
												);
												$mvResult = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);												
											}
											else{			   	     
												$this->_connection->rollback(); // rollback transaction over failure
												$this->flashMessenger()->addMessage("error^ Margin for this item is missing. Please Check");
												return $this->redirect()->toRoute('cost', array('action' => 'elccosting', 'id' => $costing_sheet_id));
											}
										}
										if(!$mvResult){  
											$this->_connection->rollback(); // rollback transaction over failure
											$this->flashMessenger()->addMessage("error^ Cannot Save the qty in Moving Item table. Please Check");
											return $this->redirect()->toRoute('cost', array('action' => 'elccosting', 'id' => $costing_sheet_id));
										}									  
									}
									
									if($itemValuation == '1'){ 
										$date = strtotime("+2 day");
										$sp_effect_date = date('Y-m-d', $date);
										
										$moving_item_sp = $this->getDefinedTable(Stock\MovingItemSpTable::class)->getMaxRow('id',array('item' => $itemID));
										$uom = $this->getDefinedTable(Stock\BatchTable::class)->getColumn($existing_batch_id,'uom');
										foreach($moving_item_sp as $misp);

										$datefrom = strtotime($misp['sp_effect_date'], 0);
										$dateto = strtotime($sp_effect_date, 0);
										$difference = $dateto - $datefrom; // Difference in seconds
										$datediff = floor($difference / 86400);
										
										if($datediff > 7){
											if($misp['batch'] != $existing_batch_id){
												$misp_data = array(
														'item'         => $itemID,
														'uom'          => $uom,
														'batch'        => $existing_batch_id,
														'costing_item'  => $costing_item_id,
														'sp_effect_date'  => $sp_effect_date,
														'author'        => $this->_author,
														'created'       => $this->_created,
														'modified'      => $this->_modified	
												);
												//echo"<pre>";print_r($misp_data);
												$this->getDefinedTable(Stock\MovingItemSpTable::class)->save($misp_data);
											}else{
												$sp_effect_date = ''; 
											}
										}else{
											$sp_effect_date = ''; 
										}
									} else { 
										$sp_effect_date = date("Y-m-d", strtotime("NOW")); 
									}
									$item_details = array(
										'id'       => $costing_item_id,
										'batch'	   => $existing_batch_id, 
										'sp_effect_date' => $sp_effect_date, 
										'top_up'         => '1',
										'status'   => '3',
										'modified' => $this->_modified
									);			 
								    $updatecostitem = $this->getDefinedTable(Stock\CostingItemsTable::class)->save($item_details);
									
									/* Update pr_detail table with batchID */
									if($po_invoice == '2')  /* differentiate between PO and Inv */
									{   $prDtl_id = $this->getDefinedTable(Purchase\SupInvDetailsTable::class)->getColumn($sinv_detailID, 'prn_details_id');	
									    if($prDtl_id < 1 || $prDtl_id == ""){
											$pr_id = $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->getColumn($po_invoice_no, 'purchase_receipt');
											$item_id = $this->getDefinedTable(Stock\CostingItemsTable::class)->getColumn($costing_item_id, 'item');
											$prDtl_id = $this->getDefinedTable(Purchase\PRDetailsTable::class)->getColumn(array('purchase_receipt'=>$pr_id, 'item'=>$item_id),'id');
										}
										if($prDtl_id > 0):
										    $pr_data = array(
													'id'      => $prDtl_id,
													'batch'   => $existing_batch_id,
													'author'  => $this->_author,
													'modified'=> $this->_modified
											);
											$pr_update = $this->getDefinedTable(Purchase\PRDetailsTable::class)->save($pr_data);
										endif; 
									}
								}								
							}
						endforeach;
					}else{
						if(strlen($itemCode) == 0){ 
							$this->_connection->rollback(); // rollback transaction over failure
							$this->flashMessenger()->addMessage("notice^ Item Code is missing. Please Check");
							return $this->redirect()->toRoute('cost', array('action' => 'elccosting', 'id' => $costing_sheet_id));
						}	
						$date     = date('y', strtotime($form['sheet_date']));
						$tmp_PONo = $itemCode.$date; 
						$results  = $this->getDefinedTable(Stock\BatchTable::class)->getMonthlyBatch($tmp_PONo);	
			   	 
						$sheet_no_list = array();
						foreach($results as $result):			   	
							array_push($sheet_no_list, substr($result['batch'], 6));
						endforeach;
						if(sizeof($sheet_no_list)>0){
							$next_serial = max($sheet_no_list) + 1;
						}else{
							$next_serial = 1;
						}			   	  
						switch(strlen($next_serial)){
							case 1: $next_b_serial  = "00".$next_serial;  break;
							case 2: $next_b_serial  = "0".$next_serial;   break;
							default: $next_b_serial = $next_serial;      break;
						}			   	  
						$batch = $itemCode.$date.$next_b_serial; 		   	 
						$costSheetDtls = $this->getDefinedTable(Stock\CostingSheetTable::class)->get($costing_sheet_id);					
						foreach ($costSheetDtls as $sheetDtl):
							$po_invoice = $sheetDtl['po_invoice'];  //differentiate between PO and Inv
							$po_invoice_no = $sheetDtl['po_invoice_no']; // Get the PO_ID or Invoice_Id
							$sht_location = $sheetDtl['location'];
						endforeach;

						if($sht_location != $form['location']){
							$sheet_data = array(
								  'id' 	      => $costing_sheet_id,		   	  		      
								  'location'  => $form['location'],				   	  	
								  'modified'  => $this->_modified
							);  		   	 		    
							$this->_safedataObj->rteSafe($sheet_data); 
							$insertSheet = $this->getDefinedTable(Stock\CostingSheetTable::class)->save($sheet_data);
						}			   	    
						$item_uom = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id,'uom');
						$location_id = $this->getDefinedTable(Stock\CostingSheetTable::class)->getColumn($costing_sheet_id, 'location');
						$location_type = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($location_id, 'location_type');			   	    
						$marginDtls = $this->getDefinedTable(Stock\MarginTable::class)->get(array('item'=>$item_id, 'location_type'=>$location_type));
						if(sizeof($marginDtls)>0){
							foreach ($marginDtls as $dtl):
								$margin_value = $dtl['margin'];
							endforeach;			   	    	
						}
						else{			   	     
							$this->_connection->rollback(); // rollback transaction over failure
							$this->flashMessenger()->addMessage("error^ Margin for this item is missing. Please Check");
							return $this->redirect()->toRoute('cost', array('action' => 'elccosting', 'id' => $costing_sheet_id));
						}			   	   
						
						$selling_price = ($landed_cost + $landed_cost * ($margin_value/100))/$unit_uom;
						$barcode =  $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id, 'barcode');
						 
						$batch_details = array(
										 'batch'       => $batch,
										 'item'        => $item_id,
										 'uom'         => $item_uom,
										 'location'    => $form['location'],
										 'quantity'    => $inv_qty,
										 'unit_uom'    => $unit_uom,
										 'barcode'     => $barcode,
										 'landed_cost' => $landed_cost,
										 'batch_date'  => date('Y-m-d'),
										 'expiry_date' => '',
										 'end_date'    => '',
										 'costing'     => $costing_item_id,
										 'trip'        => $trip_id, 
										 'location_formula' => $location_formula,
										 'charge_sum'  => $charge_sum,
										 'margin_sum'  => $item_margin,
										 'status'  => '1',
										 'author'      => $this->_author,
										 'created'     => $this->_created,
										 'modified'    => $this->_modified
						);
						//echo"<pre>";print_r($batch_details);exit;
						$batchID = $this->getDefinedTable(Stock\BatchTable::class)->save($batch_details); 			   	    
						//change the status of Costing Sheet 
						if($batchID > 0 ){			   	    	
							$batchDtls = array(
									'batch' => $batchID,
									'location' => $form['location'],
									'actual_quantity' => $inv_qty,
									'quantity' =>  $inv_qty,
									'landed_cost' => $landed_cost,
									'margin'       => $margin_value,
									'selling_price' => $selling_price,			   	    			
									'author'          => $this->_author,
									'created'        => $this->_created,
									'modified'       => $this->_modified
							);			   	    	
							$batchDtlsId = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($batchDtls);			   	    	
							if($batchDtlsId > 0) {
								$item_details = array(
										'id'       => $costing_item_id,
										'batch'	   => $batchID, 
										'top_up'   => '0',
										'status'   => '2',
										'modified'   => $this->_modified,
								);
							}
						}	
						$updatecostitem = $this->getDefinedTable(Stock\CostingItemsTable::class)->save($item_details);
						/* Update pr_detail table with batchID */
						if($po_invoice == '2')  /* differentiate between PO and Inv */
						{
							$prDtl_id = $this->getDefinedTable(Purchase\SupInvDetailsTable::class)->getColumn($sinv_detailID, 'prn_details_id');	
							if($prDtl_id < 1 || $prDtl_id ==""){
								$pr_id = $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->getColumn($po_invoice_no, 'purchase_receipt');
								$item_id = $this->getDefinedTable(Stock\CostingItemsTable::class)->getColumn($costing_item_id, 'item');
								$prDtl_id = $this->getDefinedTable(Purchase\PRDetailsTable::class)->getColumn(array('purchase_receipt'=>$pr_id, 'item'=>$item_id),'id');
							}
							if($prDtl_id > 0):
								$pr_data = array(
										'id'      => $prDtl_id,
										'batch'   => $batchID,
										'author'  => $this->_author,
										'modified'=> $this->_modified
								);
								$this->getDefinedTable(Purchase\PRDetailsTable::class)->save($pr_data);
							endif; 
						}
					}
				endfor;
				if($updatecostitem):
				    $this->_connection->commit(); // commit transaction over success
					$this->flashMessenger()->addMessage("success^ Batching successfully completed with Batch No");			
					return $this->redirect()->toRoute('cost', array('action' => 'elccosting', 'id' => $costing_sheet_id));
				else:
				   $this->_connection->rollback(); // rollback transaction over failure
				   $this->flashMessenger()->addMessage("error^ Failed batching. Please check again");			
				   return $this->redirect()->toRoute('cost', array('action' => 'elccosting', 'id' => $costing_sheet_id));
				endif;	//end of commit
			elseif($task == "save_costing"):		   
				if($form['elc_source'] == '2') {
					$invdefID = $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->getColumn($form['supplier_invoice'],'inv_defn'); 
				} 
				if($costing_sheet_id > 0 ):			      
			    	for($x = 0; $x < sizeof($form['valuation']); $x++){
						$flag = '0';
						$sinvdtl_id = $form['valuation'][$x];
						switch($form['elc_source']):
						    case 1:$sinvdtls = $this->getDefinedTable(Purchase\PODetailsTable::class)->get($sinvdtl_id);
						            foreach ($sinvdtls as $row):			                                   		
										extract($row);
										$given_uom = $uom_id;
										$sinv_detail = $id;
										$basic_uom = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id, "uom");
										if($basic_uom == $given_uom){  $c_rate = $rate; $basicQty = $quantity; }
										else{ 
										  $conversion = $this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$item_id, 'uom'=>$given_uom), "conversion");
										  $conversion = ($conversion > 0)?$conversion:1;
										  $c_rate = $rate / $conversion;  
										  $basicQty = $quantity * $conversion;							
										}											
										$c_qty = $quantity;							
										$challan_unit = $uom;							
									endforeach;
									$result = $this->getDefinedTable(Stock\ItemUomTable::class)->get(array('item'=>$item_id, 'costing_uom'=>'1'));
									if(sizeof($result)>0) { 
										foreach($result as $dtl); $c_units = $dtl['conversion'];
									}else{
										$uomDtls = $this->getDefinedTable(Stock\UomTable::class)->get($basic_uom);
										foreach($uomDtls as $uomdtl): $uom_type = $uomdtl['uom_type']; endforeach; 
										if($uom_type == '2' ||  $uom_type == '3'){ $c_units = '1000'; }
										else{ $flag = '1'; /*Standard UOM not assigned for costing  */ }					
									}
						            break;
						    case 2: $sinvdtls = $this->getDefinedTable(Purchase\SupInvDetailsTable::class)->get($sinvdtl_id);
									foreach ($sinvdtls as $row):									
										extract($row);
										$given_uom = $uom_id;
										$sinv_detail = $id;
										$basic_uom = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id, "uom");
										if($basic_uom == $given_uom)
										{ $c_rate = $rate;  $basicQty = $quantity;  }
										else
										{
											$conversion = $this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$item_id, 'uom'=>$given_uom), "conversion"); 
											$conversion = ($conversion > 0)?$conversion:1;
											$c_rate = $rate / $conversion;  
											$basicQty = $quantity * $conversion;							  
										}												
										$c_qty = $quantity;
										$challan_unit = $uom; 
									endforeach;
									$result = $this->getDefinedTable(Stock\ItemUomTable::class)->get(array('item'=>$item_id, 'costing_uom'=>'1'));
									if(sizeof($result)>0) { 
										foreach($result as $dtl); $c_units = $dtl['conversion'];
									}else{
										$uomDtls = $this->getDefinedTable(Stock\UomTable::class)->get($basic_uom);
										foreach($uomDtls as $uomdtl): $uom_type = $uomdtl['uom_type']; endforeach; 
										if($uom_type == '2' ||  $uom_type == '3'){ $c_units = '1000'; }
										else{ $flag = '1'; /*Standard UOM not assigned for costing  */ }	
									}										
									break;
						endswitch;	
						if($flag == "1"){ 
							$this->flashMessenger()->addMessage("error^ Standard UOM not assigned for costing");
							return $this->redirect()->toRoute('cost', array('action' => 'elccosting', 'id' => $costing_sheet_id));
						}
						$item_valuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id, "valuation");	
                        if($item_valuation == "1")	{
							$prevDtls = $this->getDefinedTable(Stock\CostingItemsTable::class)->getLatestCostingDtls($item_id);
							if(sizeof($prevDtls) > 0 ){
								foreach ($prevDtls as $dtl): 
									$prev_costing_item = $dtl['id'];
									$ref_rate = $dtl['rate'];
									$batch_id = $dtl['batch'];
									$po_sinv_dtl_id = $dtl['po_sinv_dtl'];
									$costing_sheet = $dtl['costing_sheet'];
									$rate_taken = $dtl['rate_taken'];
								endforeach;
								$actual_rate_taken = $dtl['rate_taken_value'];
								if($actual_rate_taken == ''):
									if($rate_taken == 1){
										$actual_rate_taken = $dtl['ref_rate'];
									}elseif($rate_taken == 2){
										$actual_rate_taken = $dtl['rate'];
									}elseif($rate_taken == 3){
										$actual_rate_taken = $dtl['avg_rate'];
									}else{
										$actual_rate_taken = $dtl['rate'];
									}
								else:
									$actual_rate_taken = $actual_rate_taken;
								endif;
								foreach($this->getDefinedTable(Stock\CostingSheetTable::class)->get($costing_sheet) as $sheet):
									  $ref_po_sinv = $sheet['po_invoice'];
									  $po_sinv_no = $sheet['po_invoice_no'];
								endforeach;
							}
							$stock_balance = $this->getDefinedTable(Stock\MovingItemTable::class)->getStockBalance($column='quantity', $where=array('item'=>$item_id));
							$X1 = $c_rate ; $X2 = $actual_rate_taken; $Y1 = $basicQty; $Y2 = $stock_balance;
							$X = round(($X1 * $Y1 + $X2 * $Y2) / ( $Y1 + $Y2 ),3);
							/*
							echo "current rate =".$X1."<br>";
							echo "previous rate =".$X2."<br>";
							echo "current stock =".$Y1."<br>";
							echo "previous stock =".$Y2."<br>";
							echo "Average = ".$X."<br>";exit;*/
							if($form["rate-".$sinvdtl_id] == $X2):
								$rate_taken = '1';
								$rate_taken_value = $X2;
							elseif($form["rate-".$sinvdtl_id] == $X1):
								$rate_taken = '2';
								$rate_taken_value = $X1;
							else:
								$rate_taken = '3';
								$rate_taken_value = $X;
							endif;
						}		
						$sitem_data = array(
								'costing_sheet'  => $costing_sheet_id,
								'batch'          => '',
								'item'           => $item_id,
								'valuation'      => $item_valuation,
								'uom'            => $basic_uom,
								'rate'           => $c_rate,
								'unit_uom'       => $c_units,
								'qty'            => $basicQty,
								'po_sinv_dtl'    => $sinvdtl_id,
								'avg_rate'       => $X,
								'rate_taken'     => $rate_taken,
								'rate_taken_value' => $rate_taken_value,
								'prev_costing_item' => $prev_costing_item,
								'ref_rate'       => $ref_rate,
								'ref_qty'        => $stock_balance,
								'ref_po_sinv'    => $ref_po_sinv,
								'ref_po_sinv_no' => $po_sinv_no,
								'ref_po_sinv_dtl'=> $po_sinv_dtl_id,
								'status'  		 => '1',
								'author'  		 => $this->_author, 
								'created' 		 => $this->_created,
								'modified' 		 => $this->_modified
						);
						$this->_safedataObj->rteSafe($sheet_data);
						$insertitem = $this->getDefinedTable(Stock\CostingItemsTable::class)->save($sitem_data);
						if($insertitem > 0){	// only after succesfully inserting the costing item	
							$sheetValue = array();	
							$costformula_id = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id, "elc_formula");
							if($costformula_id == "0"  || $costformula_id == "" ){
								$item_code = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id, "code");
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("notice^ ELC Formula not mapped for item : ".$item_code);
								return $this->redirect()->toRoute('cost', array('action' => 'elccosting', 'id' => $costing_sheet_id));	
							}
							$costformuladtls = $this->getDefinedTable(Stock\CostFormulaDtlsTable::class)->get(array('costing_formula'=>$costformula_id));
							/*Start of $formuladtl foreach*/
							foreach($costformuladtls as $formuladtl):
								$string = $formuladtl['formula'];
								$needle1 = "[";	$needle2 = "]";	$lastPos = 0;$Pos = 0;
								$start_pos = array(); $end_pos = array();						   
								while (($lastPos = strpos($string, $needle1, $lastPos))!== false) {
									$start_pos[] = $lastPos;						
									$lastPos = $lastPos + strlen($needle1);						
								}							
								while (($Pos = strpos($string, $needle2, $Pos))!== false) {
									$end_pos[] = $Pos;
									$Pos = $Pos + strlen($needle2);
								} 							
								$variables = array();					
								if($start_pos[0] > 0 ){
										$opt = substr($string, 0, 1); 
										array_push($variables, $opt);
								}								
								for($i = 0; $i < sizeof($start_pos); $i++){                          		
									$len = $end_pos[$i] - $start_pos[$i]; 
									$var = substr($string, $start_pos[$i] + 1, $len - 1); 
									if(strlen($var) > 0) { array_push($variables, $var ); }								
									$j = $i + 1;							
									$diff = $start_pos[$j] - $end_pos[$i]; 
									if($diff > 2){
										 for($k = $len+1; $k < ($len+$diff); $k++){
											  $opt = substr($string, $start_pos[$i]+$k, 1);  
											  array_push($variables, $opt);
											}		
									}else{
									  $operPos = $start_pos[$i] + $len + 1 ;
									  $operator = substr($string, $operPos, 1);
									  if(strlen($operator) > 0 ) { array_push($variables, $operator); }
									}
								}											  
								$formula = ""; 
								foreach($variables as $var):
								$stored_operators = array("/","*","-","+", "(", ")"); 
								//Check whether the variable is operator or not
								if( in_array($var, $stored_operators)){
									$formula =  $formula.$var;
								}else{   
									$table = substr($var, 0, 1); // get the table name to fetch data
									switch($table) {
										case "I":// get the supplier Invoice columns value
												  $field = substr($var, 2);
												 if($field == "rate"):
													if($item_valuation == "1" && sizeof($prevDtls) > 0){ 
														$value = $form["rate-".$sinvdtl_id];	
													}else{ 
														$value = $c_rate;	
													}
												  elseif($field == "unit_uom"):  $value = $c_units;
												  elseif($field=="quantity"): $value = $basicQty; endif;
												  $formula = $formula.$value;
												  break; 
										case "S": //get values from formula sheet 		
												  $field = substr($var, 2);
												  $formula = $formula.$sheetValue[$field];
												  break;
										case "C": // get values from charge tax
												  $field = substr($var, 2);
												  $chargeDtls = $this->getDefinedTable(Stock\ChargesTaxTable::class)->get(array('charge_tax'=>$field));
												  foreach ($chargeDtls as $charge):
													if($charge['percentage'] == "1"){$chargeValue = $charge['value']/100; }
													else { $chargeValue = $charge['value'];  } 									   
													$formula = $formula . $chargeValue; 
												  endforeach;
												//  echo  $chargeValue; 
												  break;
										case "B": // get values from Item table
												  $field = substr($var, 2);
												  $field_name = ($field == "BST")?"bst":"net_weight";
												  $b_value = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id, $field_name);
												  if($field_name == "net_weight"){
														$net_uom = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id, "scalar_uom"); 
														if( $net_uom == "13001"){ $wt_value = $b_value / 10000;  }elseif($net_uom == "15001"){ $wt_value = $b_value / 1000; }											   
														$formula = $formula.$wt_value;
												  }elseif($field_name == "bst"){
													  $bstValue = $b_value/100;	  $formula = $formula.$bstValue;
												  } 	
												  break;
										case "D": $defValue = 0;
												  $field = substr($var, 2); 
												  $invDefFieldID = $this->getDefinedTable(Purchase\InvDefFieldsTable::class)->getColumn(array('def_field'=>$field),'id');
												  if($invDefFieldID > 0){ 
													$invDefDtlID = $this->getDefinedTable(Purchase\InvDefDetailsTable::class)->getColumn(array('inv_def'=>$invdefID,'inv_def_field'=>$invDefFieldID), 'id'); 
													if($invDefDtlID > 0){  $defValue = $this->getDefinedTable(Purchase\SupInvDefDataTable::class)->getColumn(array('sinv_details'=> $sinvdtl_id, 'def_details'=> $invDefDtlID),'data'); }
												  }
												  $formula = $formula.$defValue; 
										}  /* End of Switch Case*/
									}   
								endforeach;  /*End of $variables foreach*/
								$sheetValue[$formuladtl['description']] = eval('return '.$formula.";");
								$costing_head = $formuladtl['costing_head'];
								$elc = $formuladtl['elc'];
								$costingValues = array(
										'costing_item' => $insertitem,
										'costing_head' =>$costing_head,
										'value' => $sheetValue[$formuladtl['description']],
										'elc'  => $elc,
										'author'  => $this->_author,
										'created' => $this->_created,
										'modified' => $this->_modified
								); 								
								$this->_safedataObj->rteSafe($costingValues);
								$valuesResult = $this->getDefinedTable(Stock\CostSheetDtlsTable::class)->save($costingValues); 
							endforeach;  /*End of $formuladtl foreach*/
						}
						else{
							$this->_connection->rollback(); // rollback transaction over failure
							$this->flashMessenger()->addMessage("error^ Costing sheet information not saved");
							return $this->redirect()->toRoute('cost', array('action' => 'elccosting', 'id' => $costing_sheet_id));						   
						}
					} /*end of forloop */	
					if($valuesResult > 0){  
						$this->_connection->commit(); // commit transaction over success
						$this->flashMessenger()->addMessage("success^ Costing sheet information successfully saved");
					}
					else{
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Costing sheet information not saved");
					}
					return $this->redirect()->toRoute('cost', array('action' => 'elccosting', 'id' => $costing_sheet_id));
				else:
					$prefix = $this->getDefinedTable(Administration\ActivityTable::class)->getColumn($form['activity'],'prefix');
					$date = date('ym',strtotime($form['sheet_date']));
					$tmp_PONo = "CS".$prefix.$date;
					$results = $this->getDefinedTable(Stock\CostingSheetTable::class)->getMonthlySheet($tmp_PONo);
					
					if(sizeof($results)>0){
						$sheet_no_list = array();
						foreach($results as $result):
							array_push($sheet_no_list, substr($result['costing_no'], 8));
						endforeach;
						$next_serial = max($sheet_no_list) + 1;
					}else{ $next_serial = 1;}
					
					switch(strlen($next_serial)){
						case 1: $next_cs_serial = "000".$next_serial; break;
						case 2: $next_cs_serial = "00".$next_serial;  break;
						case 3: $next_cs_serial = "0".$next_serial;   break;
						default: $next_cs_serial = $next_serial;      break;
					}					
					$costing_no = $tmp_PONo.$next_cs_serial;
					//$invoice_no = $form['supplier_invoice'];				
					$sheet_data = array(
								'costing_no' 	   => $costing_no,
								'sheet_date' 	   => $form['sheet_date'],
								'supplier' 		   => $form['supplier'],
								'po_invoice'       => $form['elc_source'],
								'po_invoice_no'    => $form['supplier_invoice'],
								'location' 		   => $form['location'],
								'activity'         => $form['activity'],
								'status' 		   => '2',
								'author'           => $this->_author, 
								'created'          => $this->_created,
								'modified'         => $this->_modified
					);  							
					$this->_safedataObj->rteSafe($sheet_data); 
					$insertSheet = $this->getDefinedTable(Stock\CostingSheetTable::class)->save($sheet_data);
					if($insertSheet > 0 ){
						$po_sinv_Data = array(
								'id'   => $form['supplier_invoice'],
								'costing' => 1
						);
						if($form['elc_source'] == "1"){
							 $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->save($po_sinv_Data);
						}else{
							$this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->save($po_sinv_Data);	
						}   
						for($x = 0; $x < sizeof($form['valuation']); $x++){
							$flag = '0';
							$sinvdtl_id = $form['valuation'][$x];
							switch($form['elc_source']):
								case 1:$sinvdtls = $this->getDefinedTable(Purchase\PODetailsTable::class)->get($sinvdtl_id);
										foreach ($sinvdtls as $row):					  
											extract($row);
											$given_uom = $uom_id;
											$sinv_detail = $id;
											$basic_uom = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id, "uom");
											if($basic_uom == $given_uom){  $c_rate = $rate; $basicQty = $quantity; }
											else{ 
											  $conversion = $this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$item_id, 'uom'=>$given_uom), "conversion");
											  $conversion = ($conversion > 0)?$conversion:1;
											  $c_rate = $rate / $conversion;  
											  $basicQty = $quantity * $conversion;							
											}											
											$c_qty = $quantity;							
											$challan_unit = $uom;							
										endforeach;
										$result = $this->getDefinedTable(Stock\ItemUomTable::class)->get(array('item'=>$item_id, 'costing_uom'=>'1'));
										if(sizeof($result)>0) { 
											foreach($result as $dtl); $c_units = $dtl['conversion'];
										}else{
											$uomDtls = $this->getDefinedTable(Stock\UomTable::class)->get($basic_uom);
											foreach($uomDtls as $uomdtl): $uom_type = $uomdtl['uom_type']; endforeach; 
											if($uom_type == '2' ||  $uom_type == '3'){ $c_units = '1000'; }
											else{ $flag = '1'; /*Standard UOM not assigned for costing  */ }					
										}
										break;
								case 2: $sinvdtls = $this->getDefinedTable(Purchase\SupInvDetailsTable::class)->get($sinvdtl_id);
										foreach ($sinvdtls as $row):	
											extract($row);
											$given_uom = $uom_id;
											$sinv_detail = $id;
											$basic_uom = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id, "uom");
											if($basic_uom == $given_uom)
											{ $c_rate = $rate;  $basicQty = $quantity;  }
											else
											{
												$conversion = $this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$item_id, 'uom'=>$given_uom), "conversion"); 
												$conversion = ($conversion > 0)?$conversion:1;
												$c_rate = $rate / $conversion;  
												$basicQty = $quantity * $conversion;							  
											}												
											$c_qty = $quantity;
											$challan_unit = $uom; 
										endforeach;
										$result = $this->getDefinedTable(Stock\ItemUomTable::class)->get(array('item'=>$item_id, 'costing_uom'=>'1'));
										if(sizeof($result)>0) { 
											foreach($result as $dtl); $c_units = $dtl['conversion'];
										}else{
											$uomDtls = $this->getDefinedTable(Stock\UomTable::class)->get($basic_uom);
											foreach($uomDtls as $uomdtl): $uom_type = $uomdtl['uom_type']; endforeach; 
											if($uom_type == '2' ||  $uom_type == '3'){ $c_units = '1000'; }
											else{ $flag = '1'; /*Standard UOM not assigned for costing  */ }	
										}										
										break;
							endswitch;	
							if($flag == "1"){ 
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("error^ Standard UOM not assigned for costing");
								return $this->redirect()->toRoute('cost', array('action' => 'elccosting', 'id' => $insertSheet));
							}
							$item_valuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id, "valuation");	
							if($item_valuation == "1")	{
								$prevDtls = $this->getDefinedTable(Stock\CostingItemsTable::class)->getLatestCostingDtls($item_id);
								if(sizeof($prevDtls) > 0 ){
									foreach ($prevDtls as $dtl):
										$prev_costing_item = $dtl['id'];
										$ref_rate = $dtl['rate'];
										$batch_id = $dtl['batch'];
										$po_sinv_dtl_id = $dtl['po_sinv_dtl'];
										$costing_sheet_id = $dtl['costing_sheet'];
										$rate_taken = $dtl['rate_taken'];
									endforeach;
									$actual_rate_taken = $dtl['rate_taken_value'];
									if($actual_rate_taken == ''):
										if($rate_taken == 1){
											$actual_rate_taken = $dtl['ref_rate'];
										}elseif($rate_taken == 2){
											$actual_rate_taken = $dtl['rate'];
										}elseif($rate_taken == 3){
											$actual_rate_taken = $dtl['avg_rate'];
										}else{
											$actual_rate_taken = $dtl['rate'];
										}
									else:
										$actual_rate_taken = $actual_rate_taken;
									endif;
								   
									foreach($this->getDefinedTable(Stock\CostingSheetTable::class)->get($costing_sheet_id) as $sheet):
										$ref_po_sinv = $sheet['po_invoice'];
										$po_sinv_no = $sheet['po_invoice_no'];
									endforeach;
								}
								$stock_balance = $this->getDefinedTable(Stock\MovingItemTable::class)->getStockBalance($column='quantity', $where=array('item'=>$item_id));
								$X1 = $c_rate ; $X2 = $actual_rate_taken; $Y1 = $basicQty; $Y2 = $stock_balance;
								$X = round(($X1 * $Y1 + $X2 * $Y2) / ( $Y1 + $Y2 ),3);	
								/*
								echo "current rate =".$X1."<br>";
								echo "previous rate =".$X2."<br>";
								echo "current stock =".$Y1."<br>";
								echo "previous stock =".$Y2."<br>";
								echo "Average = ".$X."<br>";exit;*/
								if($form["rate-".$sinvdtl_id] == $X2):
									$rate_taken = '1';
									$rate_taken_value = $X2;
								elseif($form["rate-".$sinvdtl_id] == $X1):
									$rate_taken = '2';
									$rate_taken_value = $X1;
								else:
									$rate_taken = '3';
									$rate_taken_value = $X;
								endif;
							}		
							$sitem_data = array(
									'costing_sheet'  => $insertSheet,
									'batch'          => '',
									'item'           => $item_id,
									'valuation'      => $item_valuation,
									'uom'            => $basic_uom,
									'rate'           => $c_rate,
									'unit_uom'       => $c_units,
									'qty'            => $basicQty,
									'po_sinv_dtl'    => $sinvdtl_id,
									'avg_rate'       => $X,
									'rate_taken'     => $rate_taken,
									'rate_taken_value'  => $rate_taken_value,
									'prev_costing_item' => $prev_costing_item,
									'ref_rate'       => $ref_rate,
									'ref_qty'        => $stock_balance,
									'ref_po_sinv'    => $ref_po_sinv,
									'ref_po_sinv_no' => $po_sinv_no,
									'ref_po_sinv_dtl'=> $po_sinv_dtl_id,
									'status'  		 => '1',
									'author'  		 => $this->_author, 
									'created' 		 => $this->_created,
									'modified' 		 => $this->_modified
							);
							
							$this->_safedataObj->rteSafe($sheet_data);
							$insertitem = $this->getDefinedTable(Stock\CostingItemsTable::class)->save($sitem_data);
							if($insertitem > 0){	// only after succesfully inserting the costing item	
								$sheetValue = array();	
								$costformula_id = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id, "elc_formula");
								$costformuladtls = $this->getDefinedTable(Stock\CostFormulaDtlsTable::class)->get(array('costing_formula'=>$costformula_id));
								/*Start of $formuladtl foreach*/
								foreach($costformuladtls as $formuladtl):
									$string = $formuladtl['formula'];
									$needle1 = "[";	$needle2 = "]";	$lastPos = 0;$Pos = 0;
									$start_pos = array(); $end_pos = array();						   
									while (($lastPos = strpos($string, $needle1, $lastPos))!== false) {
										$start_pos[] = $lastPos;						
										$lastPos = $lastPos + strlen($needle1);						
									}							
									while (($Pos = strpos($string, $needle2, $Pos))!== false) {
										$end_pos[] = $Pos;
										$Pos = $Pos + strlen($needle2);
									} 							
									$variables = array();					
									if($start_pos[0] > 0 ){
											$opt = substr($string, 0, 1); 
											array_push($variables, $opt);
									}								
									for($i = 0; $i < sizeof($start_pos); $i++){                          		
										$len = $end_pos[$i] - $start_pos[$i]; 
										$var = substr($string, $start_pos[$i] + 1, $len - 1); 
										if(strlen($var) > 0) { array_push($variables, $var ); }								
										$j = $i + 1;							
										$diff = $start_pos[$j] - $end_pos[$i]; 
										if($diff > 2){
											 for($k = $len+1; $k < ($len+$diff); $k++){
												$opt = substr($string, $start_pos[$i]+$k, 1);  
												array_push($variables, $opt);
											}		
										}else{
											$operPos = $start_pos[$i] + $len + 1 ;
											$operator = substr($string, $operPos, 1);
											if(strlen($operator) > 0 ) { array_push($variables, $operator); }
										}
									}											  
									$formula = ""; 
									foreach($variables as $var):
										$stored_operators = array("/","*","-","+", "(", ")"); 
										//Check whether the variable is operator or not
										if( in_array($var, $stored_operators)){
												$formula =  $formula.$var;
										}else{   
											$table = substr($var, 0, 1); // get the table name to fetch data
											switch($table) {
												case "I":// get the supplier Invoice columns value
														 $field = substr($var, 2);
														  if($field == "rate"):
															if($item_valuation == "1" && sizeof($prevDtls) > 0){ 
																$value = $form["rate-".$sinvdtl_id];	
															}else{ 
																$value = $c_rate;	
															}
														  elseif($field == "unit_uom"):  $value = $c_units;
														  elseif($field=="quantity"): $value = $basicQty; endif;
														  $formula = $formula.$value;
														  break; 
												case "S": //get values from formula sheet 		
														  $field = substr($var, 2);
														  $formula = $formula.$sheetValue[$field];
														  break;
												case "C": // get values from charge tax
														  $field = substr($var, 2);
														  $chargeDtls = $this->getDefinedTable(Stock\ChargesTaxTable::class)->get(array('charge_tax'=>$field));
														  foreach ($chargeDtls as $charge):
															if($charge['percentage'] == "1"){$chargeValue = $charge['value']/100; }
															else { $chargeValue = $charge['value'];  } 									   
															$formula = $formula . $chargeValue; 
														  endforeach;
														//  echo  $chargeValue; 
														  break;
												case "B": // get values from Item table
														  $field = substr($var, 2);
														  $field_name = ($field == "BST")?"bst":"net_weight";
														  $b_value = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id, $field_name);
														  if($field_name == "net_weight"){
																$net_uom = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id, "scalar_uom"); 
																if( $net_uom == "13"){ $wt_value = $b_value / 10000;  }elseif($net_uom == "17"){ $wt_value = $b_value / 1000; }											   
																$formula = $formula.$wt_value;
														  }elseif($field_name == "bst"){
															  $bstValue = $b_value/100;	  $formula = $formula.$bstValue;
														  } 	
														  break;
												case "D": $defValue = 0;
														  $field = substr($var, 2); 
														  $invDefFieldID = $this->getDefinedTable(Purchase\InvDefFieldsTable::class)->getColumn(array('def_field'=>$field),'id');
														  if($invDefFieldID > 0){ 
															$invDefDtlID = $this->getDefinedTable(Purchase\InvDefDetailsTable::class)->getColumn(array('inv_def'=>$invdefID,'inv_def_field'=>$invDefFieldID), 'id'); 
															if($invDefDtlID > 0){  $defValue = $this->getDefinedTable(Purchase\SupInvDefDataTable::class)->getColumn(array('sinv_details'=> $sinvdtl_id, 'def_details'=> $invDefDtlID),'data'); }
														  }
														  $formula = $formula.$defValue; 
											}  /* End of Switch Case*/
										}   
									endforeach;  /*End of $variables foreach*/
									$sheetValue[$formuladtl['description']] = eval('return '.$formula.";");
									$costing_head = $formuladtl['costing_head'];
									$elc = $formuladtl['elc'];
									$costingValues = array(
											'costing_item' => $insertitem,
											'costing_head' =>$costing_head,
											'value' => $sheetValue[$formuladtl['description']],
											'elc'  => $elc,
											'author'  => $this->_author,
											'created' => $this->_created,
											'modified' => $this->_modified
									); 								
									$this->_safedataObj->rteSafe($costingValues);
									$valuesResult = $this->getDefinedTable(Stock\CostSheetDtlsTable::class)->save($costingValues); 
								endforeach;  /*End of $formuladtl foreach*/		
							}
							else{
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("error^ Costing sheet information not saved");
								return $this->redirect()->toRoute('cost', array('action' => 'elccosting', 'id' => $insertSheet));						   
							}
						} /*end of forloop */	
						if($valuesResult > 0){ 
							$this->_connection->commit(); // commit transaction over success
							$this->flashMessenger()->addMessage("success^ Costing sheet information successfully saved"); 
						}
						return $this->redirect()->toRoute('cost', array('action' => 'elccosting', 'id' => $insertSheet));
					}  
				endif;  /* end of else */
			endif; 		  
		}
		$costingSheetRecords = ($this->_id > 0 && isset($this->_id))?$this->getDefinedTable(Stock\CostingSheetTable::class)->get($this->_id):"";
		$ViewModel =  new ViewModel(array(
				'title'              => "ELC Costing",	
				'id'                 => $this->_id,
				'invoiceObj'         => $this->getDefinedTable(Purchase\SupplierInvoiceTable::class),
				'regionObj'          => $this->getDefinedTable(Administration\RegionTable::class),
				'locationObj'        => $this->getDefinedTable(Administration\LocationTable::class),	   		
				'sheetRecords'       => $costingSheetRecords,
				'activityObj'        => $this->getDefinedTable(Administration\ActivityTable::class),
				'partyObj'           => $this->getDefinedTable(Accounts\PartyTable::class),
				'invDtlsObj'         => $this->getDefinedTable(Purchase\SupInvDetailsTable::class),
				'itemObj'            => $this->getDefinedTable(Stock\ItemTable::class),
				'statusObj'          => $this->getDefinedTable(Acl\StatusTable::class),
				'costItemObj'        => $this->getDefinedTable(Stock\CostingItemsTable::class),
				'batchObj'           => $this->getDefinedTable(Stock\BatchTable::class),
				'porderObj'          => $this->getDefinedTable(Purchase\PurchaseOrderTable::class),
				'podtlsObj'          => $this->getDefinedTable(Purchase\PODetailsTable::class),
				'movingItemTableObj' => $this->getDefinedTable(Stock\MovingItemTable::class),
				'uomitemObj'         => $this->getDefinedTable(Stock\ItemUomTable::class),
                                'userLocation'       => $this->_userloc,
		));
	   
		$costingSheetRecords = $this->getDefinedTable(Stock\CostingSheetTable::class)->get($this->_id);
		if(sizeof($costingSheetRecords) > 0){
			$ViewModel->setTemplate('stock/costing/editelccosting.phtml');
		}
		else{
			$ViewModel->setTemplate('stock/costing/elccosting.phtml');
		}
		return $ViewModel;
	}
	/**
	 * Generate Batch 
	 */
	public function generatebatchAction()
	{
		$this->init();
		return new ViewModel( array(
				'title'               => "Generate Batch",
				'costing_item_id'     => $this->_id,
				'user_location'       => $this->_userloc,
				'batchObj'            => $this->getDefinedTable(Stock\BatchTable::class),
				'batchdtlsObj'        => $this->getDefinedTable(Stock\BatchDetailsTable::class),
				'locationTypeObj'     => $this->getDefinedTable(Administration\LocationTypeTable::class),
				'locationObj'         => $this->getDefinedTable(Administration\LocationTable::class),
				'itemObj'             => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'              => $this->getDefinedTable(Stock\UomTable::class),
				'itemUomObj'          => $this->getDefinedTable(Stock\ItemUomTable::class),
				'marginObj'           => $this->getDefinedTable(Stock\MarginTable::class),
				'scalarConversionObj' => $this->getDefinedTable(Stock\ScalarConversionTable::class),
				'costItemObj'         => $this->getDefinedTable(Stock\CostingItemsTable::class),
				'costingformulaObj'   => $this->getDefinedTable(Stock\CostingFormulaTable::class),
				'costingformuladtlsObj'   => $this->getDefinedTable(Stock\CostFormulaDtlsTable::class),
				'chargeObj'           => $this->getDefinedTable(Stock\ChargesTaxTable::class),
				'tripObj'             => $this->getDefinedTable(Stock\TripTable::class),
				'tripdtlsObj'         => $this->getDefinedTable(Stock\TripDtlsTable::class),
				'costSheetObj'        => $this->getDefinedTable(Stock\CostingSheetTable::class),
				'costSheetdtlsObj'    => $this->getDefinedTable(Stock\CostSheetDtlsTable::class),
				'sinvdtlObj'         => $this->getDefinedTable(Purchase\SupInvDetailsTable::class),
		)); 
	}
	/**
	 *  Action to retrive activity
	 **/
	public function getactivityAction()
	{
		$this->init();
		$param = $this->params()->fromRoute('param', '');

		$params = explode("-", $param);
		
		$po_inv_no =123; $params[0];
   		$po_inv =1; $params[1];
		
		if($po_inv == "1"):
		   $activity_id = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->getColumn(array('po_no'=>$po_inv_no), 'activity');
		else:
		   $activity_id = $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->getColumn(array('invoice_no'=>$po_inv_no), 'activity');
		endif;
		$viewModel =  new ViewModel(array(
				'activity' => $this->getDefaultTable('adm_activity')->select(array('id'=>$activity_id)),
		));
		$viewModel->setTerminal(true);
		return  $viewModel;
	}
	
	/**
	 *  Action to retrive activity
	 **/
	public function getelcsourceAction()
	{
		$this->init();
		$elc_source = $this->_id;	
		if($elc_source == 1):
		   // source from PO 
		  $elcSourceDtls = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->get(array('po.status'=>'2', 'costing'=>'0'));
		elseif($elc_source == 2):
		   //source from Supplier Inv
		   $elcSourceDtls = $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->get(array('costing'=>'0', 'status'=>'3'));
		endif; 
		$viewModel =  new ViewModel(array(
				'elcSourceDtls' => $elcSourceDtls,
				'elc_source' => $elc_source,
		));		
		$viewModel->setTerminal(true);
		return  $viewModel;
	}
	
	/**
	 *  Action to retrive supplier
	 **/
	public function getpartyAction()
	{
		$this->init();
		$params = explode("-", $this->_id);
		$po_inv_no =123; $params['0'];
		$po_inv = 1;$params['1'];
		
		if($po_inv == "1"):
		    $party_id = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->getColumn(array('po_no'=>$po_inv_no), 'supplier');
		else:
		    $party_id = $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->getColumn(array('invoice_no'=>$po_inv_no), 'supplier');
		endif;
		
		$viewModel =  new ViewModel(array(
				'party' => $this->getDefaultTable('fa_party')->select(array('id'=>$party_id)),
		));
		
		$viewModel->setTerminal(true);
		return  $viewModel;
	}
	
	public function calculatecostAction()
	{
		$this->init();	
		
        $form = $this->getRequest()->getPost();			
		$valuation = $form['itemvaluation'];  // 0-FIFO , 1-Average Moving			
	    $sinvdtl_id = $form['sinvid']; 
		$po_sinv = $form['elc_source'];
		$selling_price = $form['selling_price'];
		
		if($po_sinv == "1"):
		    //when elc_source is Purchase Order
		    $po_sinvdtls = $this->getDefinedTable(Purchase\PODetailsTable::class)->get($sinvdtl_id);
		    $item_id = $this->getDefinedTable(Purchase\PODetailsTable::class)->getColumn($sinvdtl_id, 'item');
		elseif($po_sinv == "2"):
		    //when elc_source is Supplier Invoice
			$po_sinvdtls = $this->getDefinedTable(Purchase\SupInvDetailsTable::class)->get($sinvdtl_id);
			foreach($po_sinvdtls as $sindtl):
			    $supplier_invoice_id= $sindtl['supplier_invoice'];			
			endforeach;
		    $item_id = $this->getDefinedTable(Purchase\SupInvDetailsTable::class)->getColumn($sinvdtl_id, 'item');
			if($supplier_invoice_id > 0):
				$inv_def_id = $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->getColumn($supplier_invoice_id,'inv_defn');
			endif;
		endif;		
		
		$itemObj = $this->getDefinedTable(Stock\ItemTable::class);
		$formula_id = $itemObj->getColumn($item_id, 'elc_formula');	
		$costfordtls = $this->getDefinedTable(Stock\CostFormulaDtlsTable::class)->get(array('costing_formula'=>$formula_id));
		$viewModel =  new ViewModel(array(
			    'po_sinvdtls'     => $po_sinvdtls,
				'invdefID'        => $inv_def_id, 
				'costformuladtls' => $costfordtls,	
				'sinvdtlsObj'     => $this->getDefinedTable(Purchase\SupInvDetailsTable::class),
				'sinvObj'         => $this->getDefinedTable(Purchase\SupplierInvoiceTable::class),
				'chargeObj'       => $this->getDefinedTable(Stock\ChargesTaxTable::class),
				'itemObj'         => $this->getDefinedTable(Stock\ItemTable::class),
				'sinvdtl_id'      => $sinvdtl_id,
				'porderObj'       => $this->getDefinedTable(Purchase\PurchaseOrderTable::class),
				'podtlsObj'       => $this->getDefinedTable(Purchase\PODetailsTable::class),
				'po_sinv'         => $po_sinv,
				'valuation'       => $valuation,
				'item_id'         => $item_id,
				'costItemDtlsObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
				'costSheetObj'    => $this->getDefinedTable(Stock\CostingSheetTable::class),
				'batchDtlsObj'    => $this->getDefinedTable(Stock\BatchDetailsTable::class),
				'movingItemTableObj' => $this->getDefinedTable(Stock\MovingItemTable::class),
		        'uomitemObj'      => $this->getDefinedTable(Stock\ItemUomTable::class),
		        'uomObj'          => $this->getDefinedTable(Stock\UomTable::class),
				'InvDefDataObj'   =>$this->getDefinedTable(Purchase\SupInvDefDataTable::class),
				'InvDefFieldObj'  =>$this->getDefinedTable(Purchase\InvDefFieldsTable::class), 
				'InvDefinationObj' =>$this->getDefinedTable(Purchase\InvDefinationTable::class),
				'InvDefDtlsObj'   =>$this->getDefinedTable(Purchase\InvDefDetailsTable::class),
				'selling_price'   => $selling_price,
		));
		
		$viewModel->setTerminal(true);
		return  $viewModel;
	}	

	/**
	 * Supplier Invoice List
	 */
	public function viewcostdtlsAction()
	{
		$this->init();
		$costItemId = $this->_id;
		$costItemDtls = $this->getDefinedTable(Stock\CostingItemsTable::class)->get($costItemId);
		
		$viewModel = new ViewModel( array(
				'title'       => "Costing sheet",
				'costItemDtls' => $costItemDtls,
				'sheets'     => $this->getDefinedTable(Stock\CostingSheetTable::class) -> getAll(),
				'invoiceObj' => $this->getDefinedTable(Purchase\SupplierInvoiceTable::class),
                'batchObj'   => $this->getDefinedTable(Stock\BatchTable::class),
				'sheetDtls'  => $this->getDefinedTable(Stock\CostSheetDtlsTable::class),
				'itemObj'    => $this->getDefinedTable(Stock\ItemTable::class),
				'porderObj' => $this->getDefinedTable(Purchase\PurchaseOrderTable::class),
		        'uomObj'  => $this->getDefinedTable(Stock\UomTable::class),
				'costingitemsObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
		)); 
		
		$viewModel->setTerminal(true);
		return  $viewModel;
	}
	
	/**
	 * Supplier Invoice List
	 */
	public function suppinvlistAction()
	{
		$this->init();		
		$params = explode("-", $this->_id);
		$po_inv_no = 123;//$params['0'];  
		$po_inv = 1;//$params['1'];		
		if($po_inv == "1"):
  		    $dtls = $this->getDefinedTable(Purchase\PODetailsTable::class)->get(array('purchase_order' =>$po_inv_no));
		elseif($po_inv == "2"):
		    $dtls = $this->getDefinedTable(Purchase\SupInvDetailsTable::class)->get(array('supplier_invoice' =>$po_inv_no));
		endif;
		print_r($dtls);	exit;
		$viewModel =  new ViewModel( array(
		        'po_sinv' => $po_inv,
				'invDtls' => $dtls,
				'po_sinv_id'  => $po_inv_no,
				'porderObj' => $this->getDefinedTable(Purchase\PurchaseOrderTable::class),
				'invoiceObj' => $this->getDefinedTable(Purchase\SupplierInvoiceTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'podetailsObj' => $this->getDefinedTable(Purchase\PODetailsTable::class),
				'sinvdetailsObj' => $this->getDefinedTable(Purchase\SupInvDetailsTable::class),
				'costItemDtlsObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
				'costSheetObj'    => $this->getDefinedTable(Stock\CostingSheetTable::class),
				'movingItemTableObj' => $this->getDefinedTable(Stock\MovingItemTable::class),
				'uomitemObj'      => $this->getDefinedTable(Stock\ItemUomTable::class),
	    ) );		
		$viewModel->setTerminal(true);
		return  $viewModel;
	}
	
	/**
	 * Costing sheet list
	 */
	public function costingsheetAction()
	{
		$this->init();	
		$month = 0;
		$year = 0;
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			
			$year = $form['year'];
			$month = $form['month'];
			$activity = $form['activity'];
			
			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;
			$data = array(
					'year' => $year,
					'month' => $month,
					'activity' => $activity,
			);
		}else{
			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;
			$activity = '-1';
			
			$data = array(
					'year' => $year,
					'month' => $month,
					'activity' => $activity,
			);
		}
		$sheets = $this->getDefinedTable(Stock\CostingSheetTable::class)->getDateWise('sheet_date',$year,$month,$activity);
		return new ViewModel( array(
				'title' => "Costing sheet",
				'sheets' => $sheets,
				'costingItemsObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
				'invoiceObj' => $this->getDefinedTable(Purchase\SupplierInvoiceTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'POObj' => $this->getDefinedTable(Purchase\PurchaseOrderTable::class),
                                'minYear' => $this->getDefinedTable(Stock\CostingSheetTable::class)->getMin('sheet_date'),
				'data'  => $data,
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'partyObj'    => $this->getDefinedTable(Accounts\PartyTable::class),
		) );
	}
	
	/**
	 * Costing sheet details
	 */
	public function sheetdetailAction()
	{
		$this->init();
		$costing_id = $this->_id;
		$sheet      = $this->getDefinedTable(Stock\CostingSheetTable::class)->get($costing_id);
		return new ViewModel( array(
				'title'     => "Costing Sheet Details",
				'sheet'     => $this->getDefinedTable(Stock\CostingSheetTable::class)->get($costing_id),
				'sheetItemObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
				'sheetdtlsObj'   => $this->getDefinedTable(Stock\CostSheetDtlsTable::class),
				'userTable' => $this->getDefinedTable(Acl\UsersTable::class),
				'invoiceObj' => $this->getDefinedTable(Purchase\SupplierInvoiceTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'formulaObj'    => $this->getDefinedTable(Stock\CostingFormulaTable::class),
				'formulaDtlObj' => $this->getDefinedTable(Stock\CostFormulaDtlsTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'batchObj'  => $this->getDefinedTable(Stock\BatchTable::class),
				'uomObj'  => $this->getDefinedTable(Stock\UomTable::class),
				'statusObj'  => $this->getDefinedTable(Acl\StatusTable::class),
				'costingHeadObj' => $this->getDefinedTable(Stock\CostingHeadTable::class),
				'POObj' => $this->getDefinedTable(Purchase\PurchaseOrderTable::class)
			) );
	}
	/**
	 * set selling price effect date 
	 */
	public function speffectdateAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$costingitems = $this->getDefinedTable(Stock\CostingItemsTable::class)->get(array('costing_sheet'=>$form['costing_sheet_id']));
			foreach($costingitems as $costingitem):
				$data = array(
					'id'             => $costingitem['id'],
					'sp_effect_date' => $form['sp_effect_date'],
					'modified'       => $this->_modified,
				);
				$this->_safedataObj->rteSafe($data);
				$result = $this->getDefinedTable(Stock\CostingItemsTable::class)->save($data);
				$mv_sp_id = $this->getDefinedTable(Stock\MovingItemSpTable::class)->getColumn(array('costing_item' => $costingitem['id']),'id');
				if(isset($mv_sp_id)){
					$misp_data = array(
						'id'              => $mv_sp_id,
						'sp_effect_date'  => $form['sp_effect_date'],
						'modified'        => $this->_modified,	
					);
					$this->getDefinedTable(Stock\MovingItemSpTable::class)->save($misp_data);
				}
			endforeach;
			if($result){
				$this->flashMessenger()->addMessage("success^ Selling price date successfully updated");
			}
			else{
				 $this->flashMessenger()->addMessage("error^ Unsuccessful to update selling price date");
			}
			return $this->redirect()->toRoute('cost', array('action' =>'elccosting', 'id' => $form['costing_sheet_id']));
		endif;
		$viewModel = new ViewModel( array(
				'title'          => "Set Selling Price Effect Date",
				'costingitems'   => $this->getDefinedTable(Stock\CostingItemsTable::class)->get(array('costing_sheet'=>$this->_id,'status' => '3')),
                'batchObj'       => $this->getDefinedTable(Stock\BatchTable::class),
				'itemObj'        => $this->getDefinedTable(Stock\ItemTable::class),
				'costing_sheet_id' => $this->_id,
		)); 
		
		$viewModel->setTerminal(true);
		return  $viewModel;
	}
    /**
	 * commit costingsheetAction
	 */
	public function commitcsAction()
	{
		$this->init();
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		$data = array(
				'id'       => $this->_id,
				'status'   => '3',
				'modified' => $this->_modified,
		);
		$result = $this->getDefinedTable(Stock\CostingSheetTable::class)->save($data);
		if($result > 0){
		$costing_sheet_no = $this->getDefinedTable(Stock\CostingSheetTable::class)->getColumn($this->_id,'costing_no');
        $this->_connection->commit(); // commit transaction on success
	    $this->flashMessenger()->addMessage("success^ Successfully committed costing sheet no. ".$costing_sheet_no);
		}
		else{
			 $this->flashMessenger()->addMessage("error^ Unsuccessful to commit costing sheet no. ".$costing_sheet_no);
		}
		return $this->redirect()->toRoute('cost', array('action' =>'elccosting', 'id' => $this->_id));
	}
	/**
	 * Rectify Costing items
	 */
	public function rectifycostingAction()
	{
		$this->init();
		$costingitemID = $this->_id;
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$costingitem_id = $form['costingitem_id'];
			
			$costingitems = $this->getDefinedTable(Stock\CostingItemsTable::class)->get($costingitem_id);
			foreach($costingitems as $costingitem);
			
			$costingsheets = $this->getDefinedTable(Stock\CostingSheetTable::class)->get($costingitem['costing_sheet']);
			foreach($costingsheets as $sheet);
			$po_invoice = $sheet['po_invoice'];
			$itemValuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($costingitem['item'],'valuation');
			
			if($sheet['status'] !=3):
				$this->_connection->beginTransaction(); //***Transaction begins here***//	
				if($costingitem['top_up'] == 1): 
					if($itemValuation == "1"){
						$batches = $this->getDefinedTable(Stock\BatchTable::class)->get($costingitem['batch']);
						$batchDtls = $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('location'=>$sheet['location'],'batch'=>$costingitem['batch']));
						foreach($batchDtls as $batchdtl);
						//echo "<pre>";print_r($batchdtl);exit;
						$actual_qty = $batchdtl['actual_quantity'];
						$qty = $batchdtl['quantity'];
						if($po_invoice ==1){
							$inv_qty = '0';
						}else{
							$inv_qty = $costingitem['qty'];
						}
						$new_actual_qty = $actual_qty - $inv_qty;
						$new_qty = $qty - $inv_qty;
						
						$movingItemDtl = $this->getDefinedTable(Stock\MovingItemTable::class)->get(array('location'=>$sheet['location'], 'item'=>$costingitem['item']));
						foreach($movingItemDtl as $mvdtl):
							$moving_item_id = $mvdtl['id'];
							$exist_qty = $mvdtl['quantity'];
						endforeach;
						$new_mv_qty = $exist_qty - $inv_qty;
						
						if($new_mv_qty < 0): 
							$this->flashMessenger()->addMessage("notice^ Transactions has already happened from this batch. You cannot change the batch quantity now.");
						else:
							foreach($batches as $batch);
							$existing_actual_qty = $batch['quantity'];
							$new_batch_qty = $existing_actual_qty - $inv_qty;
							/*Update the batch and batch details with new quantity */
							$batch_data = array(
								 'id'          => $batch['id'],
								 'quantity'    => $new_batch_qty,                									   	    		
								 'modified'    => $this->_modified
							);
							//echo"<pre>"; print_r($batch_data); exit; 
							$batchResult = $this->getDefinedTable(Stock\BatchTable::class)->save($batch_data);
							if($batchResult){
								$batch_dtl_data = array(
									'id'              => $batchdtl['id'],
									'actual_quantity' => $new_actual_qty,
									'quantity'        => $new_qty,                									   	    		
									'modified'        => $this->_modified
								);
								$batchDtlResult = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($batch_dtl_data);
								if($batchDtlResult){
									if($itemValuation == "1" && $batch['status'] == '3'){
										
										$mv_data = array(
											   'id'       => $moving_item_id,
											   'quantity' => $new_mv_qty,
											   'modified' => $this->_modified
										);
										$mvResult = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);
										
										$effectdate_id = $this->getDefinedTable(Stock\MovingItemSpTable::class)->getColumn(array('costing_item' => $costingitem['id']),'id');
										$removeSpED = $this->getDefinedTable(Stock\MovingItemSpTable::class)->remove($effectdate_id);
									}
									
								}else{
									$this->_connection->rollback(); // rollback transaction over failure
									$this->flashMessenger()->addMessage("notice^ Could not update Batch Details.");
								}
							}else{
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("notice^ Couldn not update Batch Total Quantity.");
							}
						endif;
					}else{
						$batches = $this->getDefinedTable(Stock\BatchTable::class)->get($costingitem['batch']);
						$batchDtls = $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('location'=>$sheet['location'],'batch'=>$costingitem['batch']));
						foreach($batchDtls as $batchdtl);
						//echo "<pre>";print_r($batchdtl);exit;
						$actual_qty = $batchdtl['actual_quantity'];
						$qty = $batchdtl['quantity'];
						if($po_invoice ==1){
							$inv_qty = '0';
						}else{
							$inv_qty = $costingitem['qty'];
						}
						$new_actual_qty = $actual_qty - $inv_qty;
						$new_qty = $qty - $inv_qty;
						if($new_actual_qty < 0 || $new_qty < 0): 
							$this->flashMessenger()->addMessage("notice^ Transactions has already happened from this batch. You cannot change the batch quantity now.");
						else:
							foreach($batches as $batch);
							$existing_actual_qty = $batch['quantity'];
							$new_batch_qty = $existing_actual_qty - $inv_qty;
							/*Update the batch and batch details with new quantity */
							$batch_data = array(
								 'id'          => $batch['id'],
								 'quantity'    => $new_batch_qty,                									   	    		
								 'modified'    => $this->_modified
							);
							//echo"<pre>"; print_r($batch_data); exit; 
							$batchResult = $this->getDefinedTable(Stock\BatchTable::class)->save($batch_data);
							if($batchResult){
								$batch_dtl_data = array(
									'id'              => $batchdtl['id'],
									'actual_quantity' => $new_actual_qty,
									'quantity'        => $new_qty,                									   	    		
									'modified'        => $this->_modified
								);
								$batchDtlResult = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($batch_dtl_data);
							}else{
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("notice^ Couldn not update Batch Total Quantity.");
							}
						endif;
					}
				else: //new batch
					if($itemValuation == "1"){
						$batches = $this->getDefinedTable(Stock\BatchTable::class)->get($costingitem['batch']);
						foreach($batches as $batch);
						if($batch['status'] ==3):
							$batchDtls = $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('location'=>$sheet['location'],'batch'=>$costingitem['batch']));
							foreach($batchDtls as $batchdtl);
							//echo "<pre>";print_r($batchdtl);exit;
							$actual_qty = $batchdtl['actual_quantity'];
							$qty = $batchdtl['quantity'];
							if($po_invoice ==1){
								$inv_qty = '0';
							}else{
								$inv_qty = $costingitem['qty'];
							}
							$new_actual_qty = $actual_qty - $inv_qty;
							$new_qty = $qty - $inv_qty;
							
							$movingItemDtl = $this->getDefinedTable(Stock\MovingItemTable::class)->get(array('location'=>$sheet['location'], 'item'=>$costingitem['item']));
							foreach($movingItemDtl as $mvdtl):
								$moving_item_id = $mvdtl['id'];
								$exist_qty = $mvdtl['quantity'];
							endforeach;
							$new_mv_qty = $exist_qty - $inv_qty;
							
							if($new_mv_qty < 0): 
								$this->flashMessenger()->addMessage("notice^ Transactions has already happened from this batch. You cannot change the batch quantity now.");
							else:
								$batches = $this->getDefinedTable(Stock\BatchTable::class)->get($costingitem['batch']);
								foreach($batches as $batch);
								$batchDtls = $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('batch'=>$costingitem['batch']));
								foreach($batchDtls as $dtl):
									$this->getDefinedTable(Stock\BatchDetailsTable::class)->remove($dtl['id']);
								endforeach;
								$batchDtlResult = $this->getDefinedTable(Stock\BatchTable::class)->remove($costingitem['batch']);
								if($batchDtlResult){
									if($itemValuation == "1" && $batch['status'] == '3'){
										$movingItemDtl = $this->getDefinedTable(Stock\MovingItemTable::class)->get(array('location'=>$sheet['location'], 'item'=>$costingitem['item']));
										foreach($movingItemDtl as $mvdtl):
											$moving_item_id = $mvdtl['id'];
											$exist_qty = $mvdtl['quantity'];
										endforeach;
										$new_mv_qty = $exist_qty - $inv_qty;
										$mv_data = array(
											   'id'       => $moving_item_id,
											   'quantity' => $new_mv_qty,
											   'modified' => $this->_modified
										);
										$mvResult = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);
										
										$effectdate_id = $this->getDefinedTable(Stock\MovingItemSpTable::class)->getColumn(array('costing_item' => $costingitem['id']),'id');
										$removeSpED = $this->getDefinedTable(Stock\MovingItemSpTable::class)->remove($effectdate_id);
									}
									
								}else{
									$this->_connection->rollback(); // rollback transaction over failure
									$this->flashMessenger()->addMessage("notice^ Could not update Batch Details.");
								}
							endif;
						else:
							$batches = $this->getDefinedTable(Stock\BatchTable::class)->get($costingitem['batch']);
							foreach($batches as $batch);
							$batchDtls = $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('batch'=>$costingitem['batch']));
							foreach($batchDtls as $dtl):
								$this->getDefinedTable(Stock\BatchDetailsTable::class)->remove($dtl['id']);
							endforeach;
							$batchDtlResult = $this->getDefinedTable(Stock\BatchTable::class)->remove($costingitem['batch']);
						endif;
					}else{
						$batches = $this->getDefinedTable(Stock\BatchTable::class)->get($costingitem['batch']);
						$batchDtls = $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('location'=>$sheet['location'],'batch'=>$costingitem['batch']));
						foreach($batchDtls as $batchdtl);
						//echo "<pre>";print_r($batchdtl);exit;
						$actual_qty = $batchdtl['actual_quantity'];
						$qty = $batchdtl['quantity'];
						if($po_invoice ==1){
							$inv_qty = '0';
						}else{
							$inv_qty = $costingitem['qty'];
						}
						$new_actual_qty = $actual_qty - $inv_qty;
						$new_qty = $qty - $inv_qty;
						if($new_actual_qty < 0 || $new_qty < 0): 
							$this->flashMessenger()->addMessage("notice^ Transactions has already happened from this batch. You cannot change the batch quantity now.");
						else:
							$batches = $this->getDefinedTable(Stock\BatchTable::class)->get($costingitem['batch']);
							foreach($batches as $batch);
							$batchDtls = $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('batch'=>$costingitem['batch']));
							foreach($batchDtls as $dtl):
								$this->getDefinedTable(Stock\BatchDetailsTable::class)->remove($dtl['id']);
							endforeach;
							$batchDtlResult = $this->getDefinedTable(Stock\BatchTable::class)->remove($costingitem['batch']);
							if($batchDtlResult){
								if($itemValuation == "1" && $batch['status'] == '3'){
									$movingItemDtl = $this->getDefinedTable(Stock\MovingItemTable::class)->get(array('location'=>$sheet['location'], 'item'=>$costingitem['item']));
									foreach($movingItemDtl as $mvdtl):
										$moving_item_id = $mvdtl['id'];
										$exist_qty = $mvdtl['quantity'];
									endforeach;
									$new_mv_qty = $exist_qty - $inv_qty;
									$mv_data = array(
										   'id'       => $moving_item_id,
										   'quantity' => $new_mv_qty,
										   'modified' => $this->_modified
									);
									$mvResult = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);
									
									$effectdate_id = $this->getDefinedTable(Stock\MovingItemSpTable::class)->getColumn(array('costing_item' => $costingitem['id']),'id');
									$removeSpED = $this->getDefinedTable(Stock\MovingItemSpTable::class)->remove($effectdate_id);
								}
							}else{
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("notice^ Could not update Batch Details.");
							}
						endif;
					}
					$Act_deletes = $this->getDefinedTable(Administration\ActivityLogTable::class)->get(array('process'=>13,'process_id'=>$costingitem['batch']));
					//echo "<pre>";print_r($Act_deletes); exit;
					foreach($Act_deletes as $Act):
						$this->getDefinedTable(Administration\ActivityLogTable::class)->remove($Act['id']);
					endforeach;
				endif;
				if($sheet['po_invoice'] == '2')  
				{   
					$prDtl_id = $this->getDefinedTable(Purchase\SupInvDetailsTable::class)->getColumn($costingitem['po_sinv_dtl'], 'prn_details_id');	
					if($prDtl_id < 1 || $prDtl_id == ""){
						$pr_id = $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->getColumn($sheet['po_invoice_no'], 'purchase_receipt');
						$item_id = $this->getDefinedTable(Stock\CostingItemsTable::class)->getColumn($costingitem['item'], 'item');
						$prDtl_id = $this->getDefinedTable(Purchase\PRDetailsTable::class)->getColumn(array('purchase_receipt'=>$pr_id, 'item'=>$item_id),'id');
					}
					if($prDtl_id > 0):
						$pr_data = array(
								'id'      => $prDtl_id,
								'batch'   => '0',
								'modified'=> $this->_modified
						);
						$pr_update = $this->getDefinedTable(Purchase\PRDetailsTable::class)->save($pr_data);
					endif; 
				}
				
				$activity_data = array(
						'process'    => '9',
						'process_id' => $costingitem['costing_sheet'],
						'status'     => '11',
						'remarks'    => $form['remarks'],
						'author'     => $this->_author,
						'created'    => $this->_created,
						'modified'   => $this->_modified,
				);
				//print_r($activity_data); exit;
				$activityLogResult = $this->getDefinedTable(Administration\ActivityLogTable::class)->save($activity_data);
				
				$ALL_deletes = $this->getDefinedTable(Administration\ActivityLogTable::class)->get(array('process'=>10,'process_id'=>$costingitem['id']));
				//echo "<pre>";print_r($ALL_deletes); exit;
				foreach($AL_deletes as $ALL):
					$this->getDefinedTable(Administration\ActivityLogTable::class)->remove($ALL['id']);
				endforeach;
				
				$removeCostingItem = $this->getDefinedTable(Stock\CostingItemsTable::class)->remove($costingitem['id']);
				if($removeCostingItem){
					$this->_connection->commit(); // commit transaction over success
					$this->flashMessenger()->addMessage("success^ Successfully rectified costing item.");
				}else{
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Could not rectify Costing Item.");
				}
			else:
				$this->flashMessenger()->addMessage("notice^ Costing sheet is already committed.");
			endif;
			return $this->redirect()->toRoute('cost', array('action' =>'elccosting', 'id' => $costingitem['costing_sheet']));
		endif;
		$viewModel = new ViewModel( array(
				'title'          => "Rectify Costing Sheet",
				'costingitems'   => $this->getDefinedTable(Stock\CostingItemsTable::class)->get($costingitemID),
				'costingsheetObj'=> $this->getDefinedTable(Stock\CostingSheetTable::class),
                'batchObj'       => $this->getDefinedTable(Stock\BatchTable::class),
				'itemObj'        => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'         => $this->getDefinedTable(Stock\UomTable::class),
		)); 
		
		$viewModel->setTerminal(true);
		return  $viewModel;
	}
}
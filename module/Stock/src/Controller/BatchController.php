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
class BatchController extends AbstractActionController
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
		$this->_userloc = $this->_user->location;  
		$this->_id = $this->params()->fromRoute('id');		
	    $this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');		
		//$this->_dir =realpath($fileManagerDir);
		//$this->_safedataObj =  $this->SafeDataPlugin();
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();


	}
	/**
	 * index of the Batch Controller
	 */
	public function indexAction()
	{
		$this->init();
		$month = '';
		$year = '';
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
				
			$year = $form['year'];
			$month = $form['month'];
				
			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;
			$data = array(
					'year' => $year,
					'month' => $month,
			);
		}
		$month = ($month == 0)? date('m'):$month;
		$year = ($year == 0)? date('Y'):$year;
		
		$data = array(
				'year' => $year,
				'month' => $month,
		);
		
		$batches = $this->getDefinedTable(Stock\BatchTable::class)->getDateWise('batch_date',$year,$month);	
		return new ViewModel( array(
				'title'   => "Batch List",
				'batches' => $batches,
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'  => $this->getDefinedTable(Stock\UomTable::class),
				'statusObj' => $this->getDefinedTable(Acl\StatusTable::class),
				'data'  => $data,
		) );
	}
	
	/**
	 * View Batch Details Action
	 */
	public function viewbatchAction()
	{
		$this->init();		
		if($this->getRequest()->isPost()):
		   $form = $this->getRequest()->getpost();		 
		   $count = sizeof($form['location']);
		   $item_id = $form['item'];
		   $valuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id,'valuation'); 
           $this->_connection->beginTransaction(); //***Transaction begins here***//		   
		   $task = $form['btn1'];
		   if($task == "1"){
			   //Redo Costing
				$batch_data = array(
					 'id'          => $form['batch'],
					 'status'      => '1',                									   	    		
					 'modified'    => $this->_modified
				);
				$batchResult = $this->getDefinedTable(Stock\BatchTable::class)->save($batch_data);
				$result = $this->getDefinedTable(Stock\BatchDetailsTable::class)->remove(array('batch'=>$form['batch']));
				if($result > 0){
					$this->_connection->commit(); // commit transaction over success
					$this->flashMessenger()->addMessage("notice^ Selling Price to Re-calculate");
					return $this->redirect()->toRoute('batch', array('action' =>'costingsp', 'id' => $form['batch']));
				}
				else{
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Cannot redo Selling Price costing");
				}
		   }
		   elseif($task == "2"){
			    //Commit the Selling Price
				$itemID = $this->getDefinedTable(Stock\BatchTable::class)->getColumn($form['batch'],'item');
				$uom = $this->getDefinedTable(Stock\BatchTable::class)->getColumn($form['batch'],'uom');
				$itemValuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($itemID,'valuation');
				$costing_item_id = $this->getDefinedTable(Stock\BatchTable::class)->getColumn($form['batch'],'costing');
				if($itemValuation == '1'){ 
					$movingItemDtl = $this->getDefinedTable(Stock\MovingItemTable::class)->get(array('item'=>$itemID));
					if(sizeof($movingItemDtl) == 0){
					   /* insert into Moving Item table directly */
					   $batchDtls = $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('batch'=>$form['batch']));
					   foreach($batchDtls as $row):
					        $mv_data = array(
						            'item'   		=> $itemID,
									'uom'    		=> $uom,
									'batch'  		=> $row['batch'],
									'location'  	=> $row['location'],
									'quantity'  	=> $row['quantity'],
									'selling_price' => $row['selling_price'],
									'author'        => $this->_author,
							        'created'       => $this->_created,
							        'modified'      => $this->_modified							
						    );
						    $st_mv_result = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);
					   endforeach;
					}else{
					    $batchDtls = $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('batch'=>$form['batch']));
					    foreach($batchDtls as $row):
						    $movingItemDtl = $this->getDefinedTable(Stock\MovingItemTable::class)->get(array('location'=>$row['location'],'item'=>$itemID));
							if(sizeof($movingItemDtl) > 0){ 
							    foreach($movingItemDtl as $mvRow):
								    $mv_id        = $mvRow['id'];
									$existing_qty = $mvRow['quantity'] + $row['quantity'];
								endforeach;
								if( $mv_id > 0){
									$mv_data = array(
									        'id'            => $mv_id, 											
											'batch'  		=> $row['batch'],											
											'quantity'  	=> $existing_qty,
											/*'selling_price' => $row['selling_price'],*/																					
											'modified'      => $this->_modified							
									);
									$st_mv_result = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);
								}								
							}else{
								$mv_data = array(
										'item'   		=> $itemID,
										'uom'    		=> $uom,
										'batch'  		=> $row['batch'],
										'location'  	=> $row['location'],
										'quantity'  	=> $row['quantity'],
										/*'selling_price' => $row['selling_price'],*/
										'author'        => $this->_author,
										'created'       => $this->_created,
										'modified'      => $this->_modified							
								);
								$st_mv_result = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);
							}
					    endforeach;
					}
			
					$date = strtotime("+2 day");
					$sp_effect_date = date('Y-m-d', $date); 
					
					$moving_item_sp = $this->getDefinedTable(Stock\MovingItemSpTable::class)->getMaxRow('id',array('item' => $itemID));
					foreach($moving_item_sp as $misp);
					
					$datefrom = strtotime($misp['sp_effect_date'], 0);
					$dateto = strtotime($sp_effect_date, 0);
					$difference = $dateto - $datefrom; // Difference in seconds
					$datediff = floor($difference / 86400);
					if($datediff > 7){
						if(sizeof($moving_item_sp)>0){
							if($misp['batch'] != $form['batch']){
								$misp_data = array(
										'item'         => $itemID,
										'uom'          => $uom,
										'batch'        => $form['batch'],
										'costing_item' => $costing_item_id,
										'sp_effect_date'  => $sp_effect_date,
										'author'        => $this->_author,
										'created'       => $this->_created,
										'modified'      => $this->_modified	
								);
								$this->getDefinedTable(Stock\MovingItemSpTable::class)->save($misp_data);
							}
						}else{
							$misp_data = array(
									'item'         => $itemID,
									'uom'          => $uom,
									'batch'        => $form['batch'],
									'costing_item' => $costing_item_id,
									'sp_effect_date'  => $sp_effect_date,
									'author'        => $this->_author,
									'created'       => $this->_created,
									'modified'      => $this->_modified	
							);
							$this->getDefinedTable(Stock\MovingItemSpTable::class)->save($misp_data);
						}
					}else{
						$sp_effect_date = ''; 
					}
				} else { 				
				    $sp_effect_date = date("Y-m-d", strtotime("NOW")); 			
				}
				if($costing_item_id > 0){
					$item_details = array(
						'id'       => $costing_item_id,
						'batch'	   => $form['batch'], 
						'sp_effect_date' => $sp_effect_date, 
						'top_up' => '0', 
						'status'   => '3',
						'author'   => $this->_author,
						'modified' => $this->_modified
					);
					//echo"<pre>";print_r($item_details);exit;
					$updatecostitem = $this->getDefinedTable(Stock\CostingItemsTable::class)->save($item_details);
				}
				$batch_data = array(
						 'id' 			   => $form['batch'],
						 'status'          => 3,		
						 'author'          => $this->_author,							
						 'modified'        => $this->_modified
				);
				$result = $this->getDefinedTable(Stock\BatchTable::class)->save($batch_data);
				if($result > 0){
					$this->_connection->commit(); // commit transaction over success
					$this->flashMessenger()->addMessage("success^ Successfully committed selling price.");
					return $this->redirect()->toRoute('batch', array('action' =>'viewbatch', 'id' =>$form['batch']));
				}
				else{
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Unsuccessful to commit Selling Price.");
				}
		   }  
		endif;
		return new ViewModel(array(
				'title'  => "View Batch",
			    'batch' => $this->getDefinedTable(Stock\BatchTable::class)->get(array('b.id'=>$this->_id)),
				'batchdtls' => $this->getDefinedTable(Stock\BatchDetailsTable::class)->getSortByLocationName(array('batch'=>$this->_id)),
				'userTable' => $this->getDefinedTable(Administration\UsersTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'locationTypeObj' => $this->getDefinedTable(Administration\LocationTypeTable::class),
				'statusObj'  => $this->getDefinedTable(Acl\StatusTable::class),
				'marginAdjObj'  => $this->getDefinedTable(Stock\MarginAdjustTable::class),
				'marginAdjDtlObj'  => $this->getDefinedTable(Stock\MarginAdjustDtlsTable::class),
				'itemUomObj'  => $this->getDefinedTable(Stock\ItemUomTable::class),
				'itemObj'  => $this->getDefinedTable(Stock\ItemTable::class),
				'costItemObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
				'costSheetObj'  => $this->getDefinedTable(Stock\CostingSheetTable::class),
				'sinvdtlObj'  => $this->getDefinedTable(Purchase\SupInvDetailsTable::class),
                'costingformulaObj'   => $this->getDefinedTable(Stock\CostingFormulaTable::class),
		));
	}
	/**
	 * Batch -> pending
	 */
	public function costingspAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();			
			$source_location = $form['source_location'];
			for ($i=0;$i < sizeof($form['landed_cost']); $i++){	 					
				if($form['batch'] > 0):
					if($source_location != $form['location'][$i]):
					  $batchDtls = array(
							'batch'          => $form['batch'],
							'location'       => $form['location'][$i],
							'actual_quantity'=> 0,
							'quantity'       => 0,
							'landed_cost'    => $form['landed_cost'][$i],
							'margin'         => $form['margin'][$i],
							'selling_price'  => $form['selling_price'][$i],
							'author'         => $this->_author,
							'created'        => $this->_created,
							'modified'       => $this->_modified
					 );
					 $batchDtls   = $this->_safedataObj->rteSafe($batchDtls);
					 $batchDtlsId = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($batchDtls);
				   else:
					 $dtl_id = $this->getDefinedTable(Stock\BatchDetailsTable::class)->getColumn(array('batch'=>$form['batch'],'location'=>$form['location'][$i]),'id');
				     if($dtl_id > 0){
						 $batchDtls = array(
								'id'             => $dtl_id,	
								'margin'         => $form['margin'][$i],
								'selling_price'  => $form['selling_price'][$i],						
								'modified'       => $this->_modified
						 );
					 }else{
						 $batchResults = $this->getDefinedTable(Stock\BatchTable::class)->get($form['batch']);
						 foreach($batchResults as $row);
						 $batchDtls = array(
							'batch'          => $form['batch'],
							'location'       => $form['location'][$i],
							'actual_quantity'=> $row['quantity'],
							'quantity'       => $row['quantity'],
							'landed_cost'    => $form['landed_cost'][$i],
							'margin'         => $form['margin'][$i],
							'selling_price'  => $form['selling_price'][$i],
							'author'         => $this->_author,
							'created'        => $this->_created,
							'modified'       => $this->_modified
					     );
					 }
					 $batchDtls   = $this->_safedataObj->rteSafe($batchDtls);
					 $batchDtlsId = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($batchDtls);
				  endif;
			   else:
                  $this->flashMessenger()->addMessage("error^ Cannot generate Selling Prices");				
			   endif;                 
			} 
			if($batchDtlsId > 0 ){
				$batch_data = array(
					   'id' => $form['batch'],
					   'status' => 2,
					   'modified'       => $this->_modified
				);				
				$this->getDefinedTable(Stock\BatchTable::class)->save($batch_data);
				$this->flashMessenger()->addMessage("success^ Selling Price successfully generated and updated");
				return $this->redirect()->toRoute('batch', array('action' =>'viewbatch', 'id' => $form['batch']));
			}
		}
		$batchDtls = $this->getDefinedTable(Stock\BatchTable::class)->get(array('b.id'=>$this->_id));		
		if(sizeof($batchDtls) == 0){
		    return $this->redirect()->toRoute('batch');
		}		
		$batchDtlRecords = $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('batch'=>$this->_id));
		if(sizeof($batchDtlRecords) > 1){
            return $this->redirect()->toRoute('batch',array('action' =>'viewbatch', 'id' => $this->_id));
		}
		
		$item_id = $this->getDefinedTable(Stock\BatchTable::class)->getColumn($this->_id,'item');
		$itemObj = $this->getDefinedTable(Stock\ItemTable::class);
	    $activity = $itemObj->getColumn($item_id,'activity');
	    $loc_formula_id = $itemObj->getColumn($item_id, 'location_formula');				
		$locationCostformula = $this->getDefinedTable(Stock\CostFormulaDtlsTable::class)->get(array('costing_formula'=>$loc_formula_id));
		
		return new ViewModel(array(
                'title'       => "Calculate SP",
				'batch'       => $batchDtls,
				'batchdtls'   => $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('batch'=>$item_id)),
				'userTable'   => $this->getDefinedTable(Administration\UsersTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'itemObj'     => $itemObj,
				'costItemObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
				'sinvdtlObj'  => $this->getDefinedTable(Purchase\SupInvDetailsTable::class),
				'locationFormula' => $locationCostformula,
				'chargeObj'   => $this->getDefinedTable(Stock\ChargesTaxTable::class),
				'tripObj'     => $this->getDefinedTable(Stock\TripTable::class),
				'tripdtlsObj' => $this->getDefinedTable(Stock\TripDtlsTable::class),
				'batchdtlObj' => $this->getDefinedTable(Stock\BatchDetailsTable::class),
			    'uomObj'  => $this->getDefinedTable(Stock\UomTable::class),
				'locationTypeObj' => $this->getDefinedTable(Administration\LocationTypeTable::class),
				'costSheetObj' => $this->getDefinedTable(Stock\CostingSheetTable::class),
				'podtlsObj'    => $this->getDefinedTable(Purchase\PODetailsTable::class),
		        'marginObj'  => $this->getDefinedTable(Stock\MarginTable::class),
		        'scalarConversionObj'  => $this->getDefinedTable(Stock\ScalarConversionTable::class),
				'itemUomObj'  => $this->getDefinedTable(Stock\ItemUomTable::class)
		));				
	}
		
	/**
	 * Edit Batch Details Action
	 */
	public function editbatchAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();
		     // echo"<pre>"; print_r($form); exit;
              $batchDtls = $form['batchdtlID'];
			  if(sizeof($batchDtls) > 0 ){
			      $data1 = array(	
					     'id'	       => $form['batch_id'],
						 'quantity'    => $form['quantity'],
			             'unit_uom'    => $form['unit_uom'],
						 'barcode'     => $form['barcode'],
						 'landed_cost' => $form['landed_cost'],
						 'batch_date'  => $form['batch_date'],
						 'expiry_date' => $form['expiry_date'],
						 'sp_effect_date' => $form['sp_effect_date'],
						 'modified'    => $this->_modified
			   	    );
			  }else{
			    $data1 = array(
						'id'	      => $form['batch_id'],
					    'barcode'     => $form['barcode'],
						'expiry_date' => $form['expiry_date'],
						'sp_effect_date' => $form['sp_effect_date'],
						'modified'    => $this->_modified,
				);
			  }
            $this->_connection->beginTransaction(); //***Transaction begins here***//
			$data1   = $this->_safedataObj->rteSafe($data1);
			$result1 = $this->getDefinedTable(Stock\BatchTable::class)->save($data1);
			if($result1 > 0){
			   if(sizeof($batchDtls) > 0) {
                for ($i=0;$i < sizeof($batchDtls); $i++){		
				      $landed_cost_basic_uom = ($form['est_landed_cost'][$i] / $form['unit_uom']);
                      $diff = $form['selling_price'][$i] - $landed_cost_basic_uom;
					  $margin = ($diff / $landed_cost_basic_uom) * 100;
					  $batchDtls_data = array(
							'id'             => $batchDtls[$i],
							'actual_quantity'=> $form['qty'][$i],
							'quantity'       => $form['qty'][$i],
							'landed_cost'    => $form['est_landed_cost'][$i],
							'margin'         => $margin,
							'selling_price'  => $form['selling_price'][$i],
							'author'         => $this->_author,
							'created'        => $this->_created,
							'modified'       => $this->_modified
					 );
					 $batchDtlsId = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($batchDtls_data);
				 }
				 if( $batchDtlsId > 0){
					//$this->_connection->commit(); 
				    $this->flashMessenger()->addMessage("success^ Batch successfully updated");
				    return $this->redirect()->toRoute('batch', array('action' =>'viewbatch', 'id' => $result1));
				 }
				 else{
					// $this->_connection->rollback(); 
				     $this->flashMessenger()->addMessage("error^ Batch successfully updated");
				     return $this->redirect()->toRoute('batch', array('action' =>'viewbatch', 'id' => $result1));
				 }
			   }
			   else{
				$this->_connection->commit(); 
				$this->flashMessenger()->addMessage("success^ Batch successfully updated");
				return $this->redirect()->toRoute('batch', array('action' =>'viewbatch', 'id' => $result1));
			   }
			}
			else{
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to update Batch");
				return $this->redirect()->toRoute('batch');
			}
		endif;
		return new ViewModel(array(
				'title'	=> 'Edit Batch',
				'batch' => $this->getDefinedTable(Stock\BatchTable::class)->get(array('b.id'=>$this->_id)),
				//'batch_details' => $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('batch'=>1)),
				'regions'     	=> $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
				'batchDetailsObj' => $this->getDefinedTable(Stock\BatchDetailsTable::class),
				'itemObj'  => $this->getDefinedTable(Stock\ItemTable::class),
				
		));
	}
	
	/**
	 * Generate new SP by Adjusting Margin Action
	 */
	public function marginadjustAction()
	{
            $this->init();
			$results = $this->getDefinedTable(Stock\MarginAdjustTable::class)->getAll();
            
			return new ViewModel(array(
				'title'            => 'Margin Adjustment for SP',
				'itemObj'          => $this->getDefinedTable(Stock\ItemTable::class),
				'batchObj'         => $this->getDefinedTable(Stock\BatchTable::class),
				'marginAdjustDtls' => $results,
		  ));
	}
       /**
	 * View Adjusting Margin Action
	 */
	public function viewadjustmarginAction()
	{
		$this->init();		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();	
			$itemID = $form['itemID'];  
			$batchID = $form['batchID'];
			$marginID = $form['marginID'];
			$batchDtlID = $form['batchdtlID'];
			$selling_price = $form['selling_price'];
            $margin = $form['margin'];
			$location = $form['locationID'];
            $task = $form['button'];
          	if($task == "1"){ //task to commit
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$sp_effect_date = $this->getDefinedTable(Stock\MarginAdjustTable::class)->getColumn($marginID,'sp_effect_date'); 
			$itemValuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($itemID,'valuation');
			if($itemValuation == '1'):
			if($itemValuation == '1'){ $sp_effect_date = $sp_effect_date; } else { $sp_effect_date = date("Y-m-d", strtotime("NOW")); }
			
			for($i=0; $i < sizeof($batchDtlID); $i++){
				$data = array(
					'id'             => $batchDtlID[$i],
					'margin'         => $margin[$i],
					'selling_price'  => $selling_price[$i],
					'modified'       => $this->_modified
				);				
			   $result = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($data);
			   if($result){
				   if($itemValuation == '1' && $sp_effect_date == date("Y-m-d", strtotime("NOW"))){
					   $st_mv_id = $this->getDefinedTable(Stock\MovingItemTable::class)->getColumn(array('location'=>$location[$i], 'item'=>$itemID),'id');
					   if($st_mv_id > 0 ){
						   $mv_data = array(
					         'id'            => $st_mv_id,
							 'batch'         => $batchID,
							 'selling_price' => $selling_price[$i]
					       );
						   $resultMv = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);
					   }else{
						    $uom  = $this->getDefinedTable(Stock\BatchTable::class)->getColumn($batchID, 'uom');
							$qty = $this->getDefinedTable(Stock\BatchDetailsTable::class)->getColumn($batchDtlID[$i], 'quantity');
						    $mv_data = array(			
							 'item'         => $itemID,
							 'uom'          => $uom,							 
                             'batch'        => $batchID,
							 'location'     => $location[$i],
							 'quantity'     => $qty,
							 'selling_price'=> $selling_price[$i],
							 'author'       => $this->_author,
							 'created'      => $this->_created,
							 'modified'     => $this->_modified
					       );
						   $resultMv = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);
					   }
				   }
			   }
			} 
            endif; 			
			if($result > 0 || $itemValuation == '0'){				
			     $batch_data = array(
						 'id'              => $batchID,
						 'sp_effect_date'  => $sp_effect_date,
						 'modified'        => $this->_modified	
			     );
			   // $result = $this->getDefinedTable(Stock\BatchTable::class)->save($batch_data);	
			    $data1 = array(
				    'id'             => $marginID,
					'adjust_date'    => date('Y-m-d'),
					'sp_effect_date'  => $sp_effect_date,
					'status'         => '3',
					'modified'       => $this->_modified
				);
				$result1 = $this->getDefinedTable(Stock\MarginAdjustTable::class)->save($data1);
				if($result1){					
					 
						  $this->_connection->commit(); // rollback transaction over failure
						  $this->flashMessenger()->addMessage("success^ New selling price successfully updated");
						  return $this->redirect()->toRoute('batch', array('action' =>'viewadjustmargin', 'id' => $marginID));
					  }
					  else{
					  $this->_connection->rollback(); // rollback transaction over failure
				      $this->flashMessenger()->addMessage("error^ failed to update new selling price");
     				  return $this->redirect()->toRoute('batch', array('action' =>'viewadjustmargin', 'id' => $marginID));
					  }
				
			  }
			}
			else{ // task to cancel
			      $result1 = $this->getDefinedTable(Stock\MarginAdjustTable::class)->remove($marginID);
				  $result2 = $this->getDefinedTable(Stock\MarginAdjustDtlsTable::class)->remove(array('margin_adjust'=>$marginID));
				  if($result1){
						  $this->flashMessenger()->addMessage("success^ Margin Adjustment Cancellation Successfull");
						  return $this->redirect()->toRoute('batch', array('action' =>'marginadjust'));
				  }
				  else{
						  $this->flashMessenger()->addMessage("error^ Margin Adjustment Cancellation failed");
						  return $this->redirect()->toRoute('batch', array('action' =>'viewadjustmargin', 'id' => $marginID));
				   }
			}
		endif; 
		$results = $this->getDefinedTable(Stock\MarginAdjustTable::class)->get($this->_id);
		$marginDtls = $this->getDefinedTable(Stock\MarginAdjustDtlsTable::class)->get(array('margin_adjust'=>$this->_id));
		return new ViewModel(array(
				'title'           => 'View Margin Adjustment',
			    'locationTypeObj' => $this->getDefinedTable(Administration\LocationTypeTable::class),
				'itemObj'         => $this->getDefinedTable(Stock\ItemTable::class),
			    'batchObj'        => $this->getDefinedTable(Stock\BatchTable::class),
			    'batchDtlsObj'    => $this->getDefinedTable(Stock\BatchDetailsTable::class),
			    'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
			    'CostingItemsObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
			    'uomObj'          => $this->getDefinedTable(Stock\UomTable::class),
			    'results'         => $results,
			    'marginDtls'      => $marginDtls, 
                'marginDtlObj'    => $this->getDefinedTable(Stock\MarginAdjustDtlsTable::class),				
			    'CostingItemsObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
			    'itemUomObj'      => $this->getDefinedTable(Stock\ItemUomTable::class),
                'marginBatchObj'  => $this->getDefinedTable(Stock\MarginBatchDtlTable::class),				
		));
	}
        /**
	 * View Adjusting Margin Action
	 */
	public function viewadjustmarginold1Action()
	{
		$this->init();		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();	
			$itemID = $form['itemID'];  
			$batchID = $form['batchID'];
			$marginID = $form['marginID'];
			$batchDtlID = $form['batchdtlID'];
			$selling_price = $form['selling_price'];
            $margin = $form['margin'];
			$location = $form['locationID'];
            $task = $form['button'];
          	if($task == "1"){ //task to commit
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$sp_effect_date = $this->getDefinedTable(Stock\MarginAdjustTable::class)->getColumn($marginID,'sp_effect_date'); 
			$itemValuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($itemID,'valuation');
			if($itemValuation == '1'){ $sp_effect_date = $sp_effect_date; } else { $sp_effect_date = date("Y-m-d", strtotime("NOW")); }
			
			for($i=0; $i < sizeof($batchDtlID); $i++){
				$data = array(
					'id'             => $batchDtlID[$i],
					'margin'         => $margin[$i],
					'selling_price'  => $selling_price[$i],
					'modified'       => $this->_modified
				);				
			   $result = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($data);
			   if($result){
				   if($itemValuation == '1' && $sp_effect_date == date("Y-m-d", strtotime("NOW"))){
					   $st_mv_id = $this->getDefinedTable(Stock\MovingItemTable::class)->getColumn(array('location'=>$location[$i], 'item'=>$itemID),'id');
					   if($st_mv_id > 0 ){
						   $mv_data = array(
					         'id'            => $st_mv_id,
							 'batch'         => $batchID,
							 'selling_price' => $selling_price[$i]
					       );
						   $resultMv = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);
					   }else{
						    $uom  = $this->getDefinedTable(Stock\BatchTable::class)->getColumn($batchID, 'uom');
							$qty = $this->getDefinedTable(Stock\BatchDetailsTable::class)->getColumn($batchDtlID[$i], 'quantity');
						    $mv_data = array(			
							 'item'         => $itemID,
							 'uom'          => $uom,							 
                             'batch'        => $batchID,
							 'location'     => $location[$i],
							 'quantity'     => $qty,
							 'selling_price'=> $selling_price[$i],
							 'author'       => $this->_author,
							 'created'      => $this->_created,
							 'modified'     => $this->_modified
					       );
						   $resultMv = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);
					   }
				   }
			   }
			}  			
			if($result > 0){				
			     $batch_data = array(
						 'id'              => $batchID,
						 'sp_effect_date'  => $sp_effect_date,
						 'modified'        => $this->_modified	
			     );
			   // $result = $this->getDefinedTable(Stock\BatchTable::class)->save($batch_data);	
			    $data1 = array(
				    'id'             => $marginID,
					'adjust_date'    => date('Y-m-d'),
					'sp_effect_date'  => $sp_effect_date,
					'status'         => '3',
					'modified'       => $this->_modified
				);
				$result1 = $this->getDefinedTable(Stock\MarginAdjustTable::class)->save($data1);
				if($result1){					
					 
						  $this->_connection->commit(); // rollback transaction over failure
						  $this->flashMessenger()->addMessage("success^ New selling price successfully updated");
						  return $this->redirect()->toRoute('batch', array('action' =>'viewadjustmargin', 'id' => $marginID));
					  }
					  else{
					  $this->_connection->rollback(); // rollback transaction over failure
				      $this->flashMessenger()->addMessage("error^ failed to update new selling price");
     				  return $this->redirect()->toRoute('batch', array('action' =>'viewadjustmargin', 'id' => $marginID));
					  }
				
			  }
			}
			else{ // task to cancel
			      $result1 = $this->getDefinedTable(Stock\MarginAdjustTable::class)->remove($marginID);
				  $result2 = $this->getDefinedTable(Stock\MarginAdjustDtlsTable::class)->remove(array('margin_adjust'=>$marginID));
				  if($result1){
						  $this->flashMessenger()->addMessage("success^ Margin Adjustment Cancellation Successfull");
						  return $this->redirect()->toRoute('batch', array('action' =>'marginadjust'));
				  }
				  else{
						  $this->flashMessenger()->addMessage("error^ Margin Adjustment Cancellation failed");
						  return $this->redirect()->toRoute('batch', array('action' =>'viewadjustmargin', 'id' => $marginID));
				   }
			}
		endif; 
		$results = $this->getDefinedTable(Stock\MarginAdjustTable::class)->get($this->_id);
		$marginDtls = $this->getDefinedTable(Stock\MarginAdjustDtlsTable::class)->get(array('margin_adjust'=>$this->_id));
		return new ViewModel(array(
				'title'           => 'View Margin Adjustment',
			    'locationTypeObj' => $this->getDefinedTable(Administration\LocationTypeTable::class),
				'itemObj'         => $this->getDefinedTable(Stock\ItemTable::class),
			    'batchObj'        => $this->getDefinedTable(Stock\BatchTable::class),
			    'batchDtlsObj'    => $this->getDefinedTable(Stock\BatchDetailsTable::class),
			    'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
			    'CostingItemsObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
			    'uomObj'          => $this->getDefinedTable(Stock\UomTable::class),
			    'results'         => $results,
			    'marginDtls'      => $marginDtls, 
                'marginDtlObj'    => $this->getDefinedTable(Stock\MarginAdjustDtlsTable::class),				
			    'CostingItemsObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
			    'itemUomObj'      => $this->getDefinedTable(Stock\ItemUomTable::class),
                'marginBatchObj'  => $this->getDefinedTable(Stock\MarginBatchDtlTable::class),				
		));
	}
        /**
	 * View Adjusting Margin Action
	 */
	public function viewadjustmargin1Action()
	{
		$this->init();		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();	
			$itemID = $form['itemID'];  
			$batchID = $form['batchID'];
			$marginID = $form['marginID'];
			$batchDtlID = $form['batchdtlID'];
			$selling_price = $form['selling_price'];
            $margin = $form['margin'];
			$location = $form['locationID'];
            $task = $form['button'];
          	if($task == "1"){ //task to commit
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$sp_effect_date = $this->getDefinedTable(Stock\MarginAdjustTable::class)->getColumn($marginID,'sp_effect_date'); 
			$itemValuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($itemID,'valuation');
			if($itemValuation == '1'){ $sp_effect_date = $sp_effect_date; } else { $sp_effect_date = date("Y-m-d", strtotime("NOW")); }
			
			for($i=0; $i < sizeof($batchDtlID); $i++){
				$data = array(
					'id'             => $batchDtlID[$i],
					'margin'         => $margin[$i],
					'selling_price'  => $selling_price[$i],
					'modified'       => $this->_modified
				);				
			   $result = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($data);
			   if($result){
				   if($itemValuation == '1' && $sp_effect_date == date("Y-m-d", strtotime("NOW"))){
					   $st_mv_id = $this->getDefinedTable(Stock\MovingItemTable::class)->getColumn(array('location'=>$location[$i], 'item'=>$itemID),'id');
					   if($st_mv_id > 0 ){
						   $mv_data = array(
					         'id'            => $st_mv_id,
							 'batch'         => $batchID,
							 'selling_price' => $selling_price[$i]
					       );
						   $resultMv = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);
					   }else{
						    $uom  = $this->getDefinedTable(Stock\BatchTable::class)->getColumn($batchID, 'uom');
							$qty = $this->getDefinedTable(Stock\BatchDetailsTable::class)->getColumn($batchDtlID[$i], 'quantity');
						    $mv_data = array(			
							 'item'         => $itemID,
							 'uom'          => $uom,							 
                             'batch'        => $batchID,
							 'location'     => $location[$i],
							 'quantity'     => $qty,
							 'selling_price'=> $selling_price[$i],
							 'author'       => $this->_author,
							 'created'      => $this->_created,
							 'modified'     => $this->_modified
					       );
						   $resultMv = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);
					   }
				   }
				   else{
					    $batch_id = $this->getDefinedTable(Stock\BatchDetailsTable::class)->getColumn(array('location'=>$location[$i],'batch'=>$batchID),'id');
					   if($batch_id > 0 ){
						   $b_data = array(
					         'id'            => $batch_id,
							 'batch'         => $batchID,
							 'selling_price' => $selling_price[$i]
					       );
						   $resultBt = $this->getDefinedTable(Stock\MovingItemTable::class)->save($b_data);
					   }else{
						    $uom  = $this->getDefinedTable(Stock\BatchTable::class)->getColumn($batchID, 'uom');
							$qty = $this->getDefinedTable(Stock\BatchDetailsTable::class)->getColumn($batchDtlID[$i], 'quantity');
						    $b_data = array(			
                             'batch'        => $batchID,
							 'location'     => $location[$i],
							 'quantity'     => $qty,
							 'selling_price'=> $selling_price[$i],
							 'author'       => $this->_author,
							 'created'      => $this->_created,
							 'modified'     => $this->_modified
					       );
						   $resultMv = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($b_data);
					   } 
				   }
			   }
			}  			
			if($result > 0){				
			     $batch_data = array(
						 'id'              => $batchID,
						 'sp_effect_date'  => $sp_effect_date,
						 'modified'        => $this->_modified	
			     );
			   // $result = $this->getDefinedTable(Stock\BatchTable::class)->save($batch_data);	
			    $data1 = array(
				    'id'             => $marginID,
					'adjust_date'    => date('Y-m-d'),
					'sp_effect_date'  => $sp_effect_date,
					'status'         => '3',
					'modified'       => $this->_modified
				);
				$result1 = $this->getDefinedTable(Stock\MarginAdjustTable::class)->save($data1);
				if($result1){					
					 
						  $this->_connection->commit(); // rollback transaction over failure
						  $this->flashMessenger()->addMessage("success^ New selling price successfully updated");
						  return $this->redirect()->toRoute('batch', array('action' =>'viewadjustmargin', 'id' => $marginID));
					  }
					  else{
					  $this->_connection->rollback(); // rollback transaction over failure
				      $this->flashMessenger()->addMessage("error^ failed to update new selling price");
     				  return $this->redirect()->toRoute('batch', array('action' =>'viewadjustmargin', 'id' => $marginID));
					  }
				
			  }
			}
			else{ // task to cancel
			      $result1 = $this->getDefinedTable(Stock\MarginAdjustTable::class)->remove($marginID);
				  $result2 = $this->getDefinedTable(Stock\MarginAdjustDtlsTable::class)->remove(array('margin_adjust'=>$marginID));
				  if($result1){
						  $this->flashMessenger()->addMessage("success^ Margin Adjustment Cancellation Successfull");
						  return $this->redirect()->toRoute('batch', array('action' =>'marginadjust'));
				  }
				  else{
						  $this->flashMessenger()->addMessage("error^ Margin Adjustment Cancellation failed");
						  return $this->redirect()->toRoute('batch', array('action' =>'viewadjustmargin', 'id' => $marginID));
				   }
			}
		endif; 
		$results = $this->getDefinedTable(Stock\MarginAdjustTable::class)->get($this->_id);
		$marginDtls = $this->getDefinedTable(Stock\MarginAdjustDtlsTable::class)->get(array('margin_adjust'=>$this->_id));
		return new ViewModel(array(
				'title'           => 'View Margin Adjustment',
			    'locationTypeObj' => $this->getDefinedTable(Administration\LocationTypeTable::class),
				'itemObj'         => $this->getDefinedTable(Stock\ItemTable::class),
			    'batchObj'        => $this->getDefinedTable(Stock\BatchTable::class),
			    'batchDtlsObj'    => $this->getDefinedTable(Stock\BatchDetailsTable::class),
			    'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
			    'CostingItemsObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
			    'uomObj'          => $this->getDefinedTable(Stock\UomTable::class),
			    'results'         => $results,
			    'marginDtls'      => $marginDtls, 
                'marginDtlObj'    => $this->getDefinedTable(Stock\MarginAdjustDtlsTable::class),				
			    'CostingItemsObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
			    'itemUomObj'      => $this->getDefinedTable(Stock\ItemUomTable::class),
                'marginBatchObj'  => $this->getDefinedTable(Stock\MarginBatchDtlTable::class),				
		));
	}
	/**
	 * View Adjusting Margin Action
	 */
	public function viewadjustmarginoldAction()
	{
		$this->init();		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();	
			$itemID = $form['itemID'];  
			$batchID = $form['batchID'];
			$marginID = $form['marginID'];
			$batchDtlID = $form['batchdtlID'];
			$selling_price = $form['selling_price'];
            $margin = $form['margin'];
			$location = $form['locationID'];
            $task = $form['button'];
          	if($task == "1"){ //task to commit
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$sp_effect_date = $this->getDefinedTable(Stock\MarginAdjustTable::class)->getColumn($marginID,'sp_effect_date'); 
			$itemValuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($itemID,'valuation');
			if($itemValuation == '1'){ $sp_effect_date = $sp_effect_date; } else { $sp_effect_date = date("Y-m-d", strtotime("NOW")); }
			
			for($i=0; $i < sizeof($batchDtlID); $i++){
				$data = array(
					'id'             => $batchDtlID[$i],
					'margin'         => $margin[$i],
					'selling_price'  => $selling_price[$i],
					'modified'       => $this->_modified
				);				
			   $result = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($data);
			   if($result){
				   if($itemValuation == '1' && $sp_effect_date == date("Y-m-d", strtotime("NOW"))){
					   $st_mv_id = $this->getDefinedTable(Stock\MovingItemTable::class)->getColumn(array('location'=>$location[$i], 'item'=>$itemID),'id');
					   if($st_mv_id > 0 ){
						   $mv_data = array(
					         'id'            => $st_mv_id,
							 'batch'         => $batchID,
							 'selling_price' => $selling_price[$i]
					       );
						   $resultMv = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);
					   }else{
						    $uom  = $this->getDefinedTable(Stock\BatchTable::class)->getColumn($batchID, 'uom');
							$qty = $this->getDefinedTable(Stock\BatchDetailsTable::class)->getColumn($batchDtlID[$i], 'quantity');
						    $mv_data = array(			
							 'item'         => $itemID,
							 'uom'          => $uom,							 
                             'batch'        => $batchID,
							 'location'     => $location[$i],
							 'quantity'     => $qty,
							 'selling_price'=> $selling_price[$i],
							 'author'       => $this->_author,
							 'created'      => $this->_created,
							 'modified'     => $this->_modified
					       );
						   $resultMv = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);
					   }
				   }
			   }
			}  			
			if($result > 0){				
			     $batch_data = array(
						 'id'              => $batchID,
						 'sp_effect_date'  => $sp_effect_date,
						 'modified'        => $this->_modified	
			     );
			   // $result = $this->getDefinedTable(Stock\BatchTable::class)->save($batch_data);	
			    $data1 = array(
				    'id'             => $marginID,
					'adjust_date'    => date('Y-m-d'),
					'sp_effect_date'  => $sp_effect_date,
					'status'         => '3',
					'modified'       => $this->_modified
				);
				$result1 = $this->getDefinedTable(Stock\MarginAdjustTable::class)->save($data1);
				if($result1){					
					 
						  $this->_connection->commit(); // rollback transaction over failure
						  $this->flashMessenger()->addMessage("success^ New selling price successfully updated");
						  return $this->redirect()->toRoute('batch', array('action' =>'viewadjustmargin', 'id' => $marginID));
					  }
					  else{
					  $this->_connection->rollback(); // rollback transaction over failure
				      $this->flashMessenger()->addMessage("error^ failed to update new selling price");
     				  return $this->redirect()->toRoute('batch', array('action' =>'viewadjustmargin', 'id' => $marginID));
					  }
				
			  }
			}
			else{ // task to cancel
			      $result1 = $this->getDefinedTable(Stock\MarginAdjustTable::class)->remove($marginID);
				  $result2 = $this->getDefinedTable(Stock\MarginAdjustDtlsTable::class)->remove(array('margin_adjust'=>$marginID));
				  if($result1){
						  $this->flashMessenger()->addMessage("success^ Margin Adjustment Cancellation Successfull");
						  return $this->redirect()->toRoute('batch', array('action' =>'marginadjust'));
				  }
				  else{
						  $this->flashMessenger()->addMessage("error^ Margin Adjustment Cancellation failed");
						  return $this->redirect()->toRoute('batch', array('action' =>'viewadjustmargin', 'id' => $marginID));
				   }
			}
		endif; 
		$results = $this->getDefinedTable(Stock\MarginAdjustTable::class)->get($this->_id);
		$marginDtls = $this->getDefinedTable(Stock\MarginAdjustDtlsTable::class)->get(array('margin_adjust'=>$this->_id));
		return new ViewModel(array(
				'title'           => 'View Margin Adjustment',
			    'locationTypeObj' => $this->getDefinedTable(Administration\LocationTypeTable::class),
				'itemObj'         => $this->getDefinedTable(Stock\ItemTable::class),
			    'batchObj'        => $this->getDefinedTable(Stock\BatchTable::class),
			    'batchDtlsObj'    => $this->getDefinedTable(Stock\BatchDetailsTable::class),
			    'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
			    'CostingItemsObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
			    'uomObj'          => $this->getDefinedTable(Stock\UomTable::class),
			    'results'         => $results,
			    'marginDtls'      => $marginDtls, 
                'marginDtlObj'    => $this->getDefinedTable(Stock\MarginAdjustDtlsTable::class),				
			    'CostingItemsObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
			    'itemUomObj'      => $this->getDefinedTable(Stock\ItemUomTable::class),
                'marginBatchObj'  => $this->getDefinedTable(Stock\MarginBatchDtlTable::class),				
		));
	}
        /**
	 * Generate new SP by Adjusting Margin Action
	 */
	public function addmarginadjustAction()
	{
		$this->init();		
        $activities = $this->getDefinedTable(Administration\ActivityTable::class)->getAll();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();
		    //echo"<pre>"; print_r($form); exit; 
		    $sp_effect_date = $form['sp_effect_date'];
			$activity = $form['activity'];
			$item = $form['item'];
			$batch = $form['batch'];
			$location_type = $form['location_type'];
			$region = $form['region'];
			$prev_margin = $form['prev_margin'];
			$new_margin = $form['new_margin'];
            $button = $form['button'];
			$sl = $form['sl'];
			$dtl = $form['dtl']; $location = $form['location']; $landed_cost = $form['landed_cost']; $margin = $form['margin']; $sp = $form['sp'];
			//$this->_connection->beginTransaction(); //***Transaction begins here***//
			echo"<pre>"; print_r($dtl); exit; 
			if($button == "2"){
				$data = array(
					     'adjust_date'    => date('Y-m-d'),
						 'sp_effect_date' => $sp_effect_date,
						 'item'           => $item,
						 'batch'          => $batch,
						 'status'         => '1',
						 'author'         => $this->_author,
						 'created'        => $this->_created,
						 'modified'       => $this->_modified
					 ); 
					echo"<pre>"; print_r($form); exit; 
				$data  = $this->_safedataObj->rteSafe($data);
			    $result = $this->getDefinedTable(Stock\MarginAdjustTable::class)->save($data);
                if($result > 0 && sizeof($sl)){
					for($i=0;$i<sizeof($sl);$i++){ 
						  $data1 = array(
							 'margin_adjust' => $result,
							 'location_type' => $location[$sl[$i]],
							 'old_margin'    => $prev_margin[$sl[$i]],
							 'new_margin'    => $margin[$sl[$i]],
							 'author'        => $this->_author,
							 'created'       => $this->_created,
							 'modified'      => $this->_modified
						 ); 
						 $data1  = $this->_safedataObj->rteSafe($data1);
						 $result1 = $this->getDefinedTable(Stock\MarginAdjustDtlsTable::class)->save($data1);
					}
					if($result1 > 0){						
						for($i=0;$i<sizeof($sl);$i++){ 
						  $data2 = array(
							 'margin_adjust' => $result,
							 'batch_detail'  => $dtl[$sl[$i]],
							 'location'      => $location[$sl[$i]],
 						     'landed_cost'   => $landed_cost[$sl[$i]],
							 'margin'        => $margin[$sl[$i]],
							 'selling_price' => $sp[$sl[$i]],
							 'author'        => $this->_author,
							 'created'       => $this->_created,
							 'modified'      => $this->_modified
						 ); 
						 //print_r($data2); exit; 
						 $data1  = $this->_safedataObj->rteSafe($data1);
						 $result1 = $this->getDefinedTable(Stock\MarginBatchDtlTable::class)->save($data2);
					    }						
						$this->_connection->commit(); // rollback transaction over failure
					  $this->flashMessenger()->addMessage("success^ New Margin set. Commit to change the selling price");
     				  return $this->redirect()->toRoute('batch', array('action' =>'viewadjustmargin', 'id' => $result));
					}else{ $this->_connection->rollback();  }
				}else{ $this->_connection->rollback();  }
			}
		endif;

		$ViewModel = new ViewModel(array(
				'title'             => 'Generate New SP',
			    'activityObj'       => $this->getDefinedTable(Administration\ActivityTable::class),
			    //'activityID'        => $activity,
			    'locationTypeObj'   => $this->getDefinedTable(Administration\LocationTypeTable::class),
			    'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
				'itemObj'           => $this->getDefinedTable(Stock\ItemTable::class),
			   // 'itemID'            => $item,
				//'sp_effect_date'   => $sp_effect_date,
			    'batchObj'          => $this->getDefinedTable(Stock\BatchTable::class),
			    //'batchID'           => $batch,
			    //'locationID'        => $location,
			   // 'new_margins'       => $new_margin,
			   // 'region'            => $region,
			   // 'prev_margins'      => $prev_margin,
			    'batchDtlsObj'    => $this->getDefinedTable(Stock\BatchDetailsTable::class),
			    'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
			    'CostingItemsObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
			    'uomObj'          => $this->getDefinedTable(Stock\UomTable::class),
				'itemUomObj'  => $this->getDefinedTable(Stock\ItemUomTable::class),
				'regions'  => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
		));

        //if($button == '1'){
		 //  $ViewModel->setTemplate('stock/batch/viewsp.phtml');
		//}else{
		   $ViewModel->setTemplate('stock/batch/generatesp.phtml');
		//}
		return $ViewModel;
	}
	/**
	 * Generate new SP by Adjusting Margin Action
	 */
	public function addmarginadjustOldAction()
	{
		$this->init();		
        $activities = $this->getDefinedTable(Administration\ActivityTable::class)->getAll();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();
		    //echo"<pre>"; print_r($form); exit; 
		    $sp_effect_date = $form['sp_effect_date'];
			$activity = $form['activity'];
			$item = $form['item'];
			$batch = $form['batch'];
			$location_type = $form['location_type'];
			$prev_margin = $form['prev_margin'];
			$new_margin = $form['new_margin'];
            $button = $form['button'];
			$sl = $form['sl'];
			$dtl = $form['dtl']; $location = $form['location']; $landed_cost = $form['landed_cost']; $margin = $form['margin']; $sp = $form['sp'];
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			if($button == "2"){
				$data = array(
					     'adjust_date'    => date('Y-m-d'),
						 'sp_effect_date' => $sp_effect_date,
						 'item'           => $item,
						 'batch'          => $batch,
						 'status'         => '1',
						 'author'         => $this->_author,
						 'created'        => $this->_created,
						 'modified'       => $this->_modified
					 ); 
				$data  = $this->_safedataObj->rteSafe($data);
			    $result = $this->getDefinedTable(Stock\MarginAdjustTable::class)->save($data);
                if($result > 0){
					for($i=0;$i<sizeof($location_type);$i++){ 
						  $data1 = array(
							 'margin_adjust' => $result,
							 'location_type' => $location_type[$i],
							 'old_margin'    => $prev_margin[$i],
							 'new_margin'    => $new_margin[$i],
							 'author'        => $this->_author,
							 'created'       => $this->_created,
							 'modified'      => $this->_modified
						 ); 
						 $data1  = $this->_safedataObj->rteSafe($data1);
						 $result1 = $this->getDefinedTable(Stock\MarginAdjustDtlsTable::class)->save($data1);
					}
					if($result1 > 0){						
						for($i=0;$i<sizeof($sl);$i++){ 
						  $data2 = array(
							 'margin_adjust' => $result,
							 'batch_detail'  => $dtl[$sl[$i]],
							 'location'      => $location[$sl[$i]],
 						     'landed_cost'   => $landed_cost[$sl[$i]],
							 'margin'        => $margin[$sl[$i]],
							 'selling_price' => $sp[$sl[$i]],
							 'author'        => $this->_author,
							 'created'       => $this->_created,
							 'modified'      => $this->_modified
						 ); 
						 //print_r($data2); exit; 
						 $data1  = $this->_safedataObj->rteSafe($data1);
						 $result1 = $this->getDefinedTable(Stock\MarginBatchDtlTable::class)->save($data2);
					    }						
						$this->_connection->commit(); // rollback transaction over failure
					  $this->flashMessenger()->addMessage("success^ New Margin set. Commit to change the selling price");
     				  return $this->redirect()->toRoute('batch', array('action' =>'viewadjustmargin', 'id' => $result));
					}else{ $this->_connection->rollback();  }
				}else{ $this->_connection->rollback();  }
			}
		endif;

		$ViewModel = new ViewModel(array(
				'title'             => 'Generate New SP',
			    'activityObj'       => $this->getDefinedTable(Administration\ActivityTable::class),
			    'activityID'        => $activity,
			    'locationTypeObj'   => $this->getDefinedTable(Administration\LocationTypeTable::class),
				'itemObj'           => $this->getDefinedTable(Stock\ItemTable::class),
			    'itemID'            => $item,
				'sp_effect_date'   => $sp_effect_date,
			    'batchObj'          => $this->getDefinedTable(Stock\BatchTable::class),
			    'batchID'           => $batch,
			    'location_types'    => $location_type,
			    'new_margins'       => $new_margin,
			    'prev_margins'      => $prev_margin,
			    'batchDtlsObj'    => $this->getDefinedTable(Stock\BatchDetailsTable::class),
			    'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
			    'CostingItemsObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
			    'uomObj'          => $this->getDefinedTable(Stock\UomTable::class),
				'itemUomObj'  => $this->getDefinedTable(Stock\ItemUomTable::class)
		));

        if($button == '1'){
		   $ViewModel->setTemplate('stock/batch/viewsp.phtml');
		}else{
		   $ViewModel->setTemplate('stock/batch/generatesp.phtml');
		}
		return $ViewModel;
	}

	public function editmarginadjustAction()
	{
	    $this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();
		    //echo"<pre>"; print_r($form); exit; 
			$sp_effect_date = $form['sp_effect_date'];
		    $adjustMarginID = $form['id'];
		    $marginID = $form['marginID'];
			$new_margin = $form['new_margin'];
			$button = $form['button'];
		    for($i=0;$i<sizeof($marginID);$i++){
				  $data1 = array(
					 'id'        => $marginID[$i],
					 'new_margin'    => $new_margin[$i],
					 'author'      => $this->_author,
					 'modified'    => $this->_modified
				 ); 
				 $data1   = $this->_safedataObj->rteSafe($data1);
				 $result1 = $this->getDefinedTable(Stock\MarginAdjustDtlsTable::class)->save($data1);
			}
			if($result1){
				$data2 = array(
				    'id'             => $adjustMarginID,
					'sp_effect_date' => $sp_effect_date,				
					'modified'       => $this->_modified
				);				
				$result = $this->getDefinedTable(Stock\MarginAdjustTable::class)->save($data2);				
                if($button == "1"):
					$this->flashMessenger()->addMessage("success^ New Margin set. Click Save to store the margins");
					return $this->redirect()->toRoute('batch', array('action' =>'editmarginadjust', 'id' => $adjustMarginID));
				else:
                    $this->flashMessenger()->addMessage("success^ New Margin set. Commit to change the selling price");
					return $this->redirect()->toRoute('batch', array('action' =>'viewadjustmargin', 'id' => $adjustMarginID));
				endif;
			}
		endif; 

		$results = $this->getDefinedTable(Stock\MarginAdjustTable::class)->get($this->_id);
		$marginDtls = $this->getDefinedTable(Stock\MarginAdjustDtlsTable::class)->get(array('margin_adjust'=>$this->_id));
		return new ViewModel(array(
				'title'           => 'Edit Margin Adjustment',
			    'locationTypeObj' => $this->getDefinedTable(Administration\LocationTypeTable::class),
				'itemObj'         => $this->getDefinedTable(Stock\ItemTable::class),
			    'batchObj'        => $this->getDefinedTable(Stock\BatchTable::class),
			    'batchDtlsObj'    => $this->getDefinedTable(Stock\BatchDetailsTable::class),
			    'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
			    'CostingItemsObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
			    'uomObj'          => $this->getDefinedTable(Stock\UomTable::class),
			    'results'         => $results,
			    'marginDtls'      => $marginDtls,    		   
			    'CostingItemsObj' => $this->getDefinedTable(Stock\CostingItemsTable::class),
                'itemUomObj'  => $this->getDefinedTable(Stock\ItemUomTable::class)					
		));	

	}
        /**
	 * Get Batches by Item Action
	*/
	public function getitembatchAction()
	{
		$this->init();			
        $itemDtls = $this->getDefinedTable(Stock\ItemTable::class)->get($this->_id);		
		foreach($itemDtls as $row);  
		
		if($row['valuation'] == '0'){
		    //get all the batches
			$batches = $this->getDefinedTable(Stock\BatchTable::class)->get(array('b.item'=>$this->_id, 'b.status'=>3));
			 
		}
		elseif($row['valuation'] == '1'){
            // $MBID = $this->getDefinedTable(Stock\BatchTable::class)->getMaxbatch('b.id',array('b.item'=>$this->_id, 'b.status'=>3));
		//	 $batches = $this->getDefinedTable(Stock\BatchTable::class)->get(array('b.id'=>$MBID, 'b.status'=>3));
                       			$batches = $this->getDefinedTable(Stock\BatchTable::class)->get(array('b.item'=>$this->_id, 'b.status'=>3));

				}
		elseif($row['valuation'] == '1'){
			$newlocation_date = date('Y-m-d');
			$batchID = $this->getDefinedTable(Stock\MovingItemSpTable::class)->getBatchColumn(array('item'=>$this->_id),$newlocation_date);
			$batches = $this->getDefinedTable(Stock\BatchTable::class)->get($batchID);	
		}
		$ViewModel =  new ViewModel(array(				   
			    'batches'    => $batches
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}

        /**
	 * Get Batches by Item Action
	*/
	public function getmarginAction()
	{
		$this->init();	
		$ViewModel=  new ViewModel(array(
				'locationTypes' => $this->getDefinedTable(Administration\LocationTypeTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'marginObj'     => $this->getDefinedTable(Stock\MarginTable::class),
				'itemID'   => $this->_id,
			 ));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}/**
	 * Get Batches by Item Action
	*/
	public function getmarginoldAction()
	{
		$this->init();	
		$ViewModel=  new ViewModel(array(
				'locationTypes' => $this->getDefinedTable(Administration\LocationTypeTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'marginObj'     => $this->getDefinedTable(Stock\MarginTable::class),
				'itemID'   => $this->_id,
			 ));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}

	/**
	 * Generate SP for new locations
	*/
	public function locationspAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();		
            $batchs = $this->getDefinedTable(Stock\BatchTable::class)->get($form['batch']);
			foreach($batchs as $batchRows){
			    $batch_uom = $batchRows['uom'];
				$item_id = $batchRows['item'];
			}
			$valuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id,'valuation'); 
			$locationsp_check = $form['locationsp_check'];
			//echo "<pre>";print_r($locationsp_check);exit;
			if($form['batch'] > 0):
				foreach($locationsp_check as $row):
					$location = $form['location_'.$row];
					$landed_cost = $form['landed_cost_'.$row];
					$margin = $form['margin_'.$row];
					$selling_price = $form['selling_price_'.$row];
				
					$batchDtls = array(
							'batch'          => $form['batch'],
							'location'       => $location,
							'actual_quantity'=> 0,
							'quantity'       => 0,
							'landed_cost'    => $landed_cost,
							'margin'         => $margin,
							'selling_price'  => $selling_price,
							'author'         => $this->_author,
							'created'        => $this->_created,
							'modified'       => $this->_modified
					);
					$batchDtlsId = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($batchDtls);
					if($batchDtlsId>0 && $valuation == "1"):
                                            $mv_id = $this->getDefinedTable(Stock\MovingItemTable::class)->getColumn(array('item'=>$item_id,'location'=>$location),'id');
				        if(!isset($mv_id)){						 
						$insert_mi_data = array(
								  'item'           => $item_id,
								  'uom'            => $batch_uom,
								  'batch'          => $form['batch'],
								  'location'       => $location,
								  'quantity'       => 0,
								  'selling_price'  => $selling_price,
								  'author'         => $this->_author,
								  'created'        => $this->_created,
								  'modified'       => $this->_modified
						);	
						$result = $this->getDefinedTable(Stock\MovingItemTable::class)->save($insert_mi_data);
                                         }
					endif;
				endforeach;
			else:
				$this->flashMessenger()->addMessage("error^ Cannot Save New SP");				
			endif;  
			
			if($batchDtlsId > 0){
			    $this->flashMessenger()->addMessage("success^ New SP successfully saved");	
			}
			return $this->redirect()->toRoute('batch', array('action' =>'viewbatch', 'id' => $form['batch']));
		}		
		return new ViewModel(array(
				'title'             => 'Generate SP for New Locations',
			    'activityObj'       => $this->getDefinedTable(Administration\ActivityTable::class),		
			    'locationTypeObj'   => $this->getDefinedTable(Administration\LocationTypeTable::class),
				'itemObj'           => $this->getDefinedTable(Stock\ItemTable::class),		
			    'batchObj'          => $this->getDefinedTable(Stock\BatchTable::class),	
			    'batchDtlsObj'    	=> $this->getDefinedTable(Stock\BatchDetailsTable::class),
			    'locationObj'    	=> $this->getDefinedTable(Administration\LocationTable::class),			  
			    'uomObj'          	=> $this->getDefinedTable(Stock\UomTable::class),
		  ));		
	}

	public function checklocationAction()
	{
		$this->init();
		$batchID = $this->_id;		
		$itemID = $this->getDefinedTable(Stock\BatchTable::class)->getColumn($batchID, "item");
		$ViewModel=  new ViewModel(array(
						'locationTypes'		 => $this->getDefinedTable(Administration\LocationTypeTable::class)->get(array('sales'=>'1')),
			            'locationObj'		 => $this->getDefinedTable(Administration\LocationTable::class),
						'marginObj'			 => $this->getDefinedTable(Stock\MarginTable::class),
			            'batchObj'			 => $this->getDefinedTable(Stock\BatchTable::class),
			            'batchDtlObj'		 => $this->getDefinedTable(Stock\BatchDetailsTable::class),
			            'batchID'			 => $batchID,
			            'itemID'             => $itemID,
			            'itemObj'            => $this->getDefinedTable(Stock\ItemTable::class),
			            'CostFormulaDtlsObj' => $this->getDefinedTable(Stock\CostFormulaDtlsTable::class),
			            'scalarConversionObj'=> $this->getDefinedTable(Stock\ScalarConversionTable::class),
			           'tripObj'            => $this->getDefinedTable(Stock\TripDtlsTable::class),
		                'uomObj'             => $this->getDefinedTable(Stock\UomTable::class),
			            'chargeObj'          => $this->getDefinedTable(Stock\ChargesTaxTable::class),
		             ));
		$ViewModel->setTerminal(True);
		return $ViewModel;	
	}

	
	/**
	 * view sellingprice action
	 */
	public function sellingpriceAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$region = $form['region'];
			$location = $form['location'];
			$item = $form['item'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
		}else{
			$region = -1;
			$location = -1;
			$item = '';
			$start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
		}
		$data = array(
				'region'     => $region,
				'location'   => $location,
				'item'       => $item,
				'start_date' => $start_date,
				'end_date'   => $end_date,
		);
		$ViewModel = new ViewModel(array(
			'title'   	  => 'View Selling Price',
			'data'        => $data,
			'regionObj'   => $this->getDefinedTable(Administration\RegionTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'batchObj'    => $this->getDefinedTable(Stock\BatchTable::class),
			'itemObj' 	  => $this->getDefinedTable(Stock\ItemTable::class),
			'uomObj'	  => $this->getDefinedTable(Stock\UomTable::class),
			'batchdtlObj' => $this->getDefinedTable(Stock\BatchDetailsTable::class),
			'movingitemObj'   => $this->getDefinedTable(Stock\MovingItemTable::class),
			'movingitemspObj' => $this->getDefinedTable(Stock\MovingItemSpTable::class),
			
		));
		//$this->layout('layout/reportlayout');
		return $ViewModel;
	}
	/**
	 * get location via region
	 */
	public function getreglocationAction()
	{
		$this->init();
		$form = $this->getRequest()->getpost();			
		$regionID = $form['region_id'];
		$location.="<option value=''></option>";		
		$location.="<option value='-1' selected>All</option>";
		$locations = $this->getDefinedTable(Administration\LocationTable::class)->get(array('l.region'=>$regionID,'l.location_type' => array(1,2,7,8)));
		foreach($locations as $loc):
			$location .="<option value='".$loc['id']."'>".$loc['location']."</option>";
		endforeach;
		echo json_encode(array(
				'location' => $location,
		));
		exit;
	}
	/**
	 * View Moving Item Action
	 */
	public function movingitemAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$region = $form['region'];
			$location = $form['location'];
			$location_type = $form['location_type'];
			$activity =$form['activity'];
			$item = $form['item'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
		}else{
			$region = '';
			$location = -1;
			$location_type = -1;
			$activity = '';
			$item = -1;
			$start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
		}
		$data = array(
				'region'        => $region,
				'location'      => $location,
				'location_type' => $location_type,
				'activity'      => $activity,
				'item'          => $item,
				'start_date'    => $start_date,
				'end_date'      => $end_date,
		);
		$ViewModel = new ViewModel(array(
			'title'   	  => 'View Selling Price',
			'data'        => $data,
			'regionObj'   => $this->getDefinedTable(Administration\RegionTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'locationtypeObj' => $this->getDefinedTable(Administration\LocationTypeTable::class),
			'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
			'batchObj'    => $this->getDefinedTable(Stock\BatchTable::class),
			'itemObj' 	  => $this->getDefinedTable(Stock\ItemTable::class),
			'uomObj'	  => $this->getDefinedTable(Stock\UomTable::class),
			'batchdtlObj' => $this->getDefinedTable(Stock\BatchDetailsTable::class),
			'movingitemObj'   => $this->getDefinedTable(Stock\MovingItemTable::class),
			'movingitemspObj' => $this->getDefinedTable(Stock\MovingItemSpTable::class),
			
		));
		//$this->layout('layout/reportlayout');
		return $ViewModel;
	}
	/**
	 * get item via activity
	 */
	public function getitemactivityAction()
	{
		$this->init();
		$form = $this->getRequest()->getpost();			
		$activityID = $form['activity_id'];
		$item.="<option value=''></option>";		
		$item.="<option value='-1' selected>All</option>";
		$items = $this->getDefinedTable(Stock\BatchTable::class)->getDis(array('i.activity'=>$activityID));
		foreach($items as $row):
			$item .="<option value='".$row['item_id']."'>".$row['code']."</option>";
		endforeach;	
		echo json_encode(array(
				'item' => $item,
		));
		exit;
	}
	/**
	 * View selling price changes
	 */
	public function spupdateAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'location'   => $form['location'],
				'start_date' => $form['start_date'],
				'end_date'   => $form['end_date'],
			);
		}else{
			$start_date = date('Y-m-d', strtotime(date('Y-m-d').'-7 days'));
			$end_date = date('Y-m-d', strtotime(date('Y-m-d').'+7 days'));
			$userLocation = $this->_userloc;
			$userLocation_Type = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($userLocation,'location_type');
			if(in_array($userLocation_Type,array(1,2,7,8))){
				$userLocation = $this->_userloc;
			}else{
				$userLocation = 22;
			}
			$data = array(
				'location'   => $userLocation,
				'start_date' => $start_date,
				'end_date'   => $end_date,
			);
		}
		$ViewModel = new ViewModel(array(
				'title'           => 'Selling Price Update',
				'data'            => $data,
				'movingitemspObj' => $this->getDefinedTable(Stock\MovingItemSpTable::class),
				'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
				'itemObj'         => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'          => $this->getDefinedTable(Stock\UomTable::class),
				'batchdtlObj'     => $this->getDefinedTable(Stock\BatchDetailsTable::class),
		));
		//$this->layout('layout/reportlayout');
		return $ViewModel;
	}
}

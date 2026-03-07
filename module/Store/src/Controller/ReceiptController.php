<?php
namespace Store\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Accounts\Model As Accounts;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Hr\Model As Hr;
use Store\Model As Store;

class ReceiptController extends AbstractActionController
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
		//$this->_safedataObj = $this->SafeDataPlugin();
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();


	}
    /**
     * index in action
     */
	public function indexAction()
	{
		$this->init();
		$month='';
		$year='';
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();

			$year = $form['year'];
			$month = $form['month'];
			$item_group = $form['item_group'];

			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;
			$data = array(
					'year' => $year,
					'month' => $month,
					'item_group' => $item_group,
			);
		}else{
			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;
			$item_group = '-1';

			$data = array(
					'year' => $year,
					'month' => $month,
					'item_group' => $item_group,
			);
		}
		$receipts_results = $this->getDefinedTable(Store\PurchaseReceiptTable::class)->getDateWise('purchase_receipt_date',$year,$month,$item_group);
		
		return new ViewModel( array(
               'receipts_results' => $receipts_results,
			   'minYear' 		  => $this->getDefinedTable(Store\PurchaseReceiptTable::class)->getMin('purchase_receipt_date'),
			   'data'             => $data,
			   'groupObj'         => $this->getDefinedTable(Store\GroupTable::class),
			   'poObj'            => $this->getDefinedTable(Store\PurchaseOrderTable::class),
			   'activityObj'      => $this->getDefinedTable(Administration\ActivityTable::class),
		));
	}

	/**
	 * Add Receipt Action
	 */
	public function addreceiptAction()
	{
		$this->init();
		$invoice = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->get($invoice_id);
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$porder_id = $form['porder'];
			$item_id  = $form['item'];
			if($porder_id == "" || $porder_id == 0 ){
				$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'],'prefix');
				$date = date('ym',strtotime($form['purchase_receipt_date']));
				$tmp_PRNo = $location_prefix."PR".$date;
				$results = $this->getDefinedTable(Store\PurchaseReceiptTable::class)->getMonthlyPR($tmp_PRNo);
				$prn_no_list = array();
				foreach($results as $result):
					array_push($prn_no_list, substr($result['purchase_receipt_no'], 8));
				endforeach;
				$next_serial = max($prn_no_list) + 1;

				switch(strlen($next_serial)){
					case 1: $next_prn_serial = "000".$next_serial; break;
					case 2: $next_prn_serial = "00".$next_serial;  break;
					case 3: $next_prn_serial = "0".$next_serial;   break;
					default: $next_prn_serial = $next_serial;       break;
				}

				$prn_no = $tmp_PRNo.$next_prn_serial;
			 
				$data = array(
					'purchase_receipt_no'   => $prn_no,
					'purchase_order'        => $form['purchase_order_no'],
					'purchase_receipt_date' => $form['purchase_receipt_date'],
					'location'              => $form['location'],
					'item_group'            => $form['item_group'],
					'cost_center'           => $form['cost_center'],
					'invoice_no'            => $form['invoice_no'],
					'supplier'              => $form['supplier'],
					'custom_declaration'    => $form['custom_declaration'],
					'challan_no'            => $form['challan_no'],
					'challan_date'          => $form['challan_date'],
					'payment_amt'           => $form['payable_amount'],
					'note'                  => $form['note'],
					'status'                => 1,
					'author'                => $this->_author,
					'created'               => $this->_created,
					'modified'              => $this->_modified
				);
				$data   = $this->_safedataObj->rteSafe($data);
				//print_r($data); exit;
				$result = $this->getDefinedTable(Store\PurchaseReceiptTable::class)->save($data);
				if($result > 0){
					 $item            = $form['item'];
					 $uom		      = $form['uom'];
					 $rate		      = $form['rate'];
					 $challan_qty     = $form['challan_qty'];
					 $damage_qty      = $form['damage_qty'];
					 $shortage_qty    = $form['shortage_qty'];
					 $accept_qty      = $form['accept_qty'];
					 $amount		  = $form['amount'];
					 $sound_qty		  = $form['sound_qty'];
					 $po_details_id   = $form['po_details_id'];
					 for($i=0; $i < sizeof($item); $i++):
					    if(isset($item[$i]) && $item[$i] > 0):
							$pr_details = array(
								'purchase_receipt'	=> $result,
								'item'           	=> $item[$i],
								'rate'            	=> $rate[$i],
								'uom'            	=> $uom[$i],
								'challan_qauntity'  => $challan_qty[$i],
								'demage_quantity'   => $damage_qty[$i],
								'shortage_quantity'	=> $shortage_qty[$i],
								'accepted_quantity' => $accept_qty[$i],
								'sound_quantity' 	=> $sound_qty[$i],
								'amount' 	        => $amount[$i],
								'po_details_id'     => $po_details_id[$i],
								'author'    	 	=> $this->_author,
								'created'   	 	=> $this->_created,
								'modified'  	 	=> $this->_modified
							 );
							$pr_details   = $this->_safedataObj->rteSafe($pr_details);
							$this->getDefinedTable(Store\PRDetailsTable::class)->save($pr_details);
						   endif;
					endfor;
					$this->flashMessenger()->addMessage("success^ Successfully added new Purchase Receipt :". $prn_no);
					return $this->redirect()->toRoute('inpurreceipt', array('action'=> 'viewreceipt', 'id'=>$result));
				}
				else{
					$this->flashMessenger()->addMessage("Failed^ Failed to add new Purchase Receipt");
					return $this->redirect()->toRoute('inpurreceipt', array('action' => 'addreceipt'));
				}
			}
			else{
				$pur_orders = $this->getDefinedTable(Store\PurchaseOrderTable::class)->get($form['porder']);
				$pur_details = $this->getDefinedTable(Store\PODetailsTable::class)->get(array('purchase_order' => $form['porder']));
			}
		  }

		return new ViewModel( array(
				'pur_orders'  => $pur_orders,
				'pur_details' => $pur_details,
				'regions'     => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'items'  	  => $this->getDefinedTable(Store\ItemTable::class)->getItem($porder_id),
				'itemObj'     => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'	  => $this->getDefinedTable(Stock\UomTable::class),
				'pur_ordersObj' => $this->getDefinedTable(Store\PurchaseOrderTable::class),
				'activities'  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'groupObj'    => $this->getDefinedTable(Store\GroupTable::class),
		));
	}

	/**
	 * Get Purchase Order for Purchase Receipt
	 **/
	public function getporderAction()
	{
		$this->init();
		$ViewModel = new ViewModel(array(
				'purchaseorder' => $this->getDefinedTable(Store\PurchaseOrderTable::class)->get(array('status' => 2)),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * Get Item Uoms, Po_uom and Po_qty by item_id and po_id
	 */
	public function getpodetailAction()
	{
		$this->init();

		$form = $this->getRequest()->getPost();
		$item_id = $form['item_id'];
		$po_id = $form['po_id'];
		$itemDtls = $this->getDefinedTable(Store\ItemTable::class)->get($item_id);
		$uom_for .="<option value=''></option>";
		foreach($itemDtls as $dtl):
			$uom_for .="<option value='".$dtl['st_uom_id']."' selected>".$dtl['uom_code']."</option>";		
		endforeach;	
		$po_details = $this->getDefinedTable(Store\PODetailsTable::class)->get(array('purchase_order'=>$po_id,'item'=>$item_id));
		foreach($po_details as $po_detail);
		$po_qty = number_format($po_detail['quantity'], 2, '.', '');
		$rate = number_format($po_detail['rate'], 2, '.', '');
		echo json_encode(array(
			'uom' => $uom_for,
			'po_qty' =>$po_qty,
			'rate'   =>$rate,
			'po_details_id' => $po_detail['id'],
		));
		exit;
	}

	/**
	 * View Purchase Receipt
	 *
	 **/
	public function viewreceiptAction()
	{
		$this->init();
		return new ViewModel( array(
				'purchase_receipt' => $this->getDefinedTable(Store\PurchaseReceiptTable::class)->get($this->_id),
				'PRDetailsObj'     => $this->getDefinedTable(Store\PRDetailsTable::class),
				'purchase_orderObj'=> $this->getDefinedTable(Store\PurchaseOrderTable::class),
				'userTable'        => $this->getDefinedTable(Acl\UsersTable::class),
				'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
				'statusObj'        => $this->getDefinedTable(Acl\StatusTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'itemuomObj'  => $this->getDefinedTable(Stock\ItemUomTable::class),
				'po_detailsObj'   => $this->getDefinedTable(Store\PODetailsTable::class),
				'uomObj'=> $this->getDefinedTable(Stock\UomTable::class),
		        'userObj'   => $this->getDefinedTable(Acl\UsersTable::class),'transactionObj'   => $this->getDefinedTable(Accounts\TransactionTable::class),
                         
		) );
	}
	/**
	 * Edit Purchase Receipt Action
	**/
	public function editreceiptAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$data = array(
				'id'			        => $form['id'],
				'purchase_receipt_no'   => $form['purchase_receipt_no'],
				'purchase_order'   	    => $form['purchase_order_no'],
				'purchase_receipt_date' => $form['purchase_receipt_date'],
				'location'              => $form['location'],
				'cost_center'           => $form['cost_center'],
				'supplier'              => $form['supplier'],
				'custom_declaration'    => $form['custom_declaration'],
				'challan_no'            => $form['challan_no'],
				'challan_date'          => $form['challan_date'],
				'note'                  => $form['note'],
				'status'                => 1,
				'author'                => $this->_author,
				'created'               => $this->_created,
				'modified'              => $this->_modified
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Store\PurchaseReceiptTable::class)->save($data);
			if($result > 0){
				$details_id      = $form['details_id'];
				$item            = $form['item'];
				$uom		     = $form['uom'];
				$rate		     = $form['rate'];
				$challan_qty     = $form['challan_qty'];
				$damage_qty      = $form['damage_qty'];
				$shortage_qty    = $form['shortage_qty'];
				$accept_qty      = $form['accept_qty'];
				$sound_qty       = $form['sound_qty'];
				$po_details_id   = $form['po_details_id'];
				$delete_rows = $this->getDefinedTable(Store\PRDetailsTable::class)->getNotIn($details_id, array('purchase_receipt' => $result));
				 
				for($i=0; $i < sizeof($item); $i++):
					if(isset($item[$i]) && $item[$i] > 0):
					$pr_details = array(
						'id'                => $details_id[$i],
						'purchase_receipt'  => $result,
						'item'              => $item[$i],
						'uom'               => $uom[$i],
						'rate'              => $rate[$i],
						'challan_qauntity'  => $challan_qty[$i],
						'demage_quantity'   => $damage_qty[$i],
						'shortage_quantity' => $shortage_qty[$i],
						'accepted_quantity' => $accept_qty[$i],
						'sound_quantity'    => $sound_qty[$i],
						'author'    	    => $this->_author,
						'modified'  	    => $this->_modified
					);
					$pr_details   = $this->_safedataObj->rteSafe($pr_details);
					$this->getDefinedTable(Store\PRDetailsTable::class)->save($pr_details);
					endif;
				endfor;
				//deleting deleted table rows form database table
				foreach($delete_rows as $delete_row):
					$this->getDefinedTable(Store\PRDetailsTable::class)->remove($delete_row['id']);
				endforeach;
				$this->flashMessenger()->addMessage("success^ Successfully updated Purchase Receipt no. ". $form['purchase_receipt_no']);
				return $this->redirect()->toRoute('inpurreceipt', array('action' =>'viewreceipt', 'id' => $result));
			}
			else{
				$this->flashMessenger()->addMessage("error^ Failed to add new Purchase Receipt");
				return $this->redirect()->toRoute('inpurreceipt');
			}
		}
		$pur_receipt = $this->getDefinedTable(Store\PurchaseReceiptTable::class)->get($this->_id);
		foreach ($pur_receipt as $preceipt):
			if($preceipt['status'] == "3"){
				$this->flashMessenger()->addMessage("notice^ Cannot edit a canceled/closed Purchase Receipt");
				return $this->redirect()->toRoute('inpurreceipt');
			}
            $pur_details = $this->getDefinedTable(Store\PODetailsTable::class)->get(array('purchase_order' => $preceipt['purchase_order']));
			$items = $this->getDefinedTable(Store\ItemTable::class)->getItem($preceipt['purchase_order']);
			$pr_details = $this->getDefinedTable(Store\PRDetailsTable::class)->get(array('purchase_receipt' => $preceipt['id']));
		endforeach;

		return new ViewModel( array(
				'purchase_receipt' => $pur_receipt,
				'pr_details' 	   => $pr_details,
                'pur_details' 	   => $pur_details,
				'items'  	       => $items,
				'statusObj'        => $this->getDefinedTable(Acl\StatusTable::class),
				'regions'          => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
				'itemObj'          => $this->getDefinedTable(Store\ItemTable::class),
				'uomObj'	       => $this->getDefinedTable(Stock\UomTable::class),
				'po_detailsObj'    => $this->getDefinedTable(Store\PODetailsTable::class),
				'pur_ordersObj'    => $this->getDefinedTable(Store\PurchaseOrderTable::class),
				'activities'       => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'groupObj'         => $this->getDefinedTable(Store\GroupTable::class),
		) );
	}
	/**
	 * commit purchase receipt Action
	 * 
	 */
	public function generatecodeAction()
	{
		
		$this->init();

		$pur_receipts = $this->getDefinedTable(Store\PurchaseReceiptTable::class)->get(array('pr.id'=>$this->_id));
		foreach($pur_receipts as $pur_receipt);
		//print_r($pur_receipts); exit;
		$purchase_receipt_no = $pur_receipt['purchase_receipt_no']; 
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		
		if($pur_receipt['item_group_id'] == 1): //Assets
		
		    $pur_receipt_details = $this->getDefinedTable(Store\PRDetailsTable::class)->get(array('purchase_receipt'=>$this->_id));
		    foreach($pur_receipt_details as $pur_receipt_detail):	
			
				for($x=0; $x < $pur_receipt_detail['accepted_quantity']; $x++)
				{
					$itemTypeCode = $this->getDefinedTable(Store\ItemTable::class)->getColumn($pur_receipt_detail['item_id'],'item_type_code');
					$itemSpecificationCode = $this->getDefinedTable(Store\ItemTable::class)->getColumn($pur_receipt_detail['item_id'],'item_specification_code');	
					$itemsg_id = $this->getDefinedTable(Store\ItemTable::class)->getColumn($pur_receipt_detail['item_id'],'item_sub_group');
					$prefix = $this->getDefinedTable(Store\SubGroupTable::class)->getColumn($itemsg_id,'prefix');		
					$activity_prefix = $this->getDefinedTable(Administration\ActivityTable::class)->getColumn($pur_receipt['activity_id'],'prefix');		
					$year = date('Y',strtotime($pur_receipt['purchase_receipt_date']));
					$tmp_ACNo = "FCBL"."/".$activity_prefix."/".$year."/".$prefix."/".$itemTypeCode."/".$itemSpecificationCode."/";
					$results  = $this->getDefinedTable(Store\AssetTable::class)->getMonthlyAssetCode($tmp_ACNo);							
					
					$sheet_no_list = array();
					foreach($results as $result):			   	
						array_push($sheet_no_list, substr($result['asset'],21));
					endforeach;
					if(sizeof($sheet_no_list)>0){
						$next_serial = max($sheet_no_list) + 1;
					}else{
						$next_serial = 1;
					}			   	  
					switch(strlen($next_serial)){
						case 1: $next_sc_serial  = "000".$next_serial;  break;
						case 2: $next_sc_serial  = "00".$next_serial;   break;
						case 3: $next_sc_serial  = "0".$next_serial;   break;
						default: $next_sc_serial = $next_serial;      break;
					}
					$asset_code = $tmp_ACNo.$next_sc_serial;
					$asset_data = array(
						'asset'         =>$asset_code,
						'item'          =>$pur_receipt_detail['item_id'],
						'uom'           =>$pur_receipt_detail['uom'],
						'quantity'      =>1,
						'asset_date'    =>$pur_receipt['purchase_receipt_date'],
						'expiry_date'   =>'',
						'barcode'       =>'',
						'status' 		=>3,
						'author'	    =>$this->_author,
						'created'       =>$this->_created,
						'modified'      =>$this->_modified,
					);
					$asset_data   = $this->_safedataObj->rteSafe($asset_data);
					$assetID = $this->getDefinedTable(Store\AssetTable::class)->save($asset_data);
					$existing_amount = $this->getDefinedTable(Store\AssetDetailsTable::class)->getSumAmount($pur_receipt_detail['item_id'],$pur_receipt['location_id']);
					$existing_qty = $this->getDefinedTable(Store\AssetDetailsTable::class)->getSumQty($pur_receipt_detail['item_id'],$pur_receipt['location_id']);
					$new_amount = ($pur_receipt_detail['amount'] + $existing_amount) /($pur_receipt_detail['accepted_quantity'] + $existing_qty);
					if($assetID > 0){
						$assetDtls = array(
							'asset'          => $assetID,
							'location'       => $pur_receipt['location_id'],
							'quantity'       => 1,
							'rate'           => $pur_receipt_detail['rate'],
							'amount'         => $new_amount,
							'author'         => $this->_author,
							'created'        => $this->_created,
							'modified'       => $this->_modified
						);	
						$assetDtls   = $this->_safedataObj->rteSafe($assetDtls);		   	    	
						$assetDtlsId = $this->getDefinedTable(Store\AssetDetailsTable::class)->save($assetDtls);	
						$pr_details = array(
							'purchase_receipt' =>$pur_receipt_detail['purchase_receipt'],
							'assetssp'         =>$assetID,
							'item'             =>$pur_receipt_detail['item_id'],
							'uom'              =>$pur_receipt_detail['uom'],
							'challan_qauntity' =>1,
							'accepted_quantity'=>1,
							'sound_quantity'   =>1,
							'rate'             =>$pur_receipt_detail['rate'],
                                                        'amount'           =>$pur_receipt_detail['rate'],
							'po_details_id'    =>$pur_receipt_detail['po_details_id'],
							'author'           => $this->_author,
							'modified'         => $this->_modified
						);
					    $pr_details   = $this->_safedataObj->rteSafe($pr_details);
						$pr_detailsId  = $this->getDefinedTable(Store\PARDetailsTable::class)->save($pr_details);		
						if($assetDtlsId < 0 && $pr_detailsId < 0):
						   $this->_connection->rollback(); // rollback transaction over failure
						   $this->flashMessenger()->addMessage("error^ Failed to save the data in assets details table. Please check ");			
						   return $this->redirect()->toRoute('inpurreceipt', array('action' => 'viewreceipt', 'id' => $this->_id));
						   
						endif;
					}
					else{			   	     
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Failed to save the data in assets table. Please Check");
						return $this->redirect()->toRoute('inpurreceipt', array('action' => 'viewreceipt', 'id' =>  $this->_id));
					}	
				}
				$asset_Details  =  $this->getDefinedTable(Store\AssetDetailsTable::class)->getAssetDetails($pur_receipt_detail['item_id'],$pur_receipt['location_id']);
				if(sizeof($asset_Details) > 0):
				foreach($asset_Details as $ad_row):
				    $update_ad = (array(
					    'id' =>$ad_row['id'],
						'amount'=>$new_amount,
						'rate' =>$new_amount,
					));
					$update_ads  = $this->_safedataObj->rteSafe($update_ad);		   	    	
					$this->getDefinedTable(Store\AssetDetailsTable::class)->save($update_ads);	
				endforeach;
				endif;
            endforeach;
		
		else: // Store and spare
				
		    $pur_receipt_details = $this->getDefinedTable(Store\PRDetailsTable::class)->get(array('purchase_receipt'=>$this->_id));
			
		    foreach($pur_receipt_details as $pur_receipt_detail):
				$existing_SSP_codes =  $this->getDefinedTable(Store\StoreSpareTable::class)->getExistingSSPCode(array('item'=>$pur_receipt_detail['item_id'],'status'=>'3'));
				$multiple_array = array();
				foreach($existing_SSP_codes as $existing_SSP_code):
					array_push($multiple_array,$existing_SSP_code['id']);
				endforeach;
				$max_existing_SSP_code = max($multiple_array);
				
				$existing_SSPCode =  $this->getDefinedTable(Store\StoreSpareTable::class)->getExistingSSPCode($max_existing_SSP_code);
				if(sizeof($existing_SSPCode) > 0):
					foreach($existing_SSPCode as $existing_SSPC);
					$existing_SSPCode_id = $existing_SSPC['id'];
					$existing_SSPCode_qty = $existing_SSPC['quantity'];
					$new_SSP_qty = $existing_SSPCode_qty + $pur_receipt_detail['sound_quantity'];  
					/*Update the Store and Spare details with new quantity */
					$sspcode_data = array(
						'id'          => $existing_SSPCode_id,
						'quantity'    => $new_SSP_qty,                									   	    		
						'modified'    => $this->_modified
					);
					$sspcode_data   = $this->_safedataObj->rteSafe($sspcode_data);
					$storesparecodeResult = $this->getDefinedTable(Store\StoreSpareTable::class)->save($sspcode_data);
					if($storesparecodeResult > 0 ){
						$existing_SSPDtls = $this->getDefinedTable(Store\StoreSpareDetailsTable::class)->get(array('storespare'=>$existing_SSPC['id'],'location'=>$pur_receipt['location_id']));
						if(sizeof($existing_SSPDtls)>0):
							foreach($existing_SSPDtls as $sspdtl_row):
								$existing_sspdtl_id = $sspdtl_row['id'];
								$existing_qty1 = $sspdtl_row['quantity'];
								$existing_amount = $sspdtl_row['amount'];
							endforeach;	
							$new_qty1 = $existing_qty1 + $pur_receipt_detail['sound_quantity']; 
							$new_amount1 = $existing_amount  + $pur_receipt_detail['amount']; 
							$new_rate1 = $new_amount1 / $new_qty1; 
						endif;
						$storespare_dtl_data = array(
							'id'        => $existing_sspdtl_id,
							'quantity'  => $new_qty1,                									   	    		
							'rate'      => $new_rate1,                									   	    		
							'amount'    => $new_amount1,                									   	    		
							'modified'  => $this->_modified
						);
						$storespare_dtl_data   = $this->_safedataObj->rteSafe($storespare_dtl_data);
						$storespDtls = $this->getDefinedTable(Store\StoreSpareDetailsTable::class)->save($storespare_dtl_data);
						$prDtl_id = $this->getDefinedTable(Store\PRDetailsTable::class)->getColumn(array('purchase_receipt'=>$this->_id, 'item'=>$pur_receipt_detail['item_id']),'id');
						$pr_details = array(
						    'id'                => $prDtl_id,
							'assetssp'          => $existing_sspdtl_id,
							'author'    	    => $this->_author,
							'modified'  	    => $this->_modified
						);
						$pr_details   = $this->_safedataObj->rteSafe($pr_details);
						//echo "<pre>"; print_r($pr_details); exit;
						$this->getDefinedTable(Store\PRDetailsTable::class)->save($pr_details);	
						if($assetDtlResult < 0):
						   $this->_connection->rollback(); // rollback transaction over failure
						   $this->flashMessenger()->addMessage("error^ Failed to save the data in store and spare details table. Please check ");			
						   return $this->redirect()->toRoute('inpurreceipt', array('action' => 'viewreceipt', 'id' => $this->_id));
						endif;
					}
					else{			   	     
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Failed to save the data in store and spare table. Please Check");
						return $this->redirect()->toRoute('inpurreceipt', array('action' => 'viewreceipt', 'id' =>  $this->_id));
					}	
					
				else:
					
					$itemsg_id = $this->getDefinedTable(Store\ItemTable::class)->getColumn($pur_receipt_detail['item_id'],'item_sub_group');
					$prefix = $this->getDefinedTable(Store\SubGroupTable::class)->getColumn($itemsg_id,'prefix');		
					$activity_prefix = $this->getDefinedTable(Administration\ActivityTable::class)->getColumn($pur_receipt['activity_id'],'prefix');		
					$year = date('Y', strtotime($pur_receipt['purchase_receipt_date']));
					$tmp_SSPCNo = "FCBL"."/".$activity_prefix."/".$year."/".$prefix."/";
					
					$results  = $this->getDefinedTable(Store\StoreSpareTable::class)->getMonthlySSPCode($tmp_SSPCNo);		 
					$sheet_no_list = array();
					foreach($results as $result):			   	
						array_push($sheet_no_list, substr($result['storespare'], 15));
					endforeach;
					if(sizeof($sheet_no_list)>0){
						$next_serial = max($sheet_no_list) + 1;
					}else{
						$next_serial = 1;
					}			   	  
					switch(strlen($next_serial)){
						case 1: $next_sc_serial  = "000".$next_serial;  break;
						case 2: $next_sc_serial  = "00".$next_serial;   break;
						case 3: $next_sc_serial  = "0".$next_serial;   break;
						default: $next_sc_serial = $next_serial;      break;
					}
					$ssp_code = $tmp_SSPCNo.$next_sc_serial;
					$ssp_data = array(
						'storespare'     =>$ssp_code,
						'item'           =>$pur_receipt_detail['item_id'],
						'uom'            =>$pur_receipt_detail['uom'],
						'quantity'       =>$pur_receipt_detail['sound_quantity'],
						'storespare_date'=>$pur_receipt['purchase_receipt_date'],
						'expiry_date'    =>'',
						'barcode'        =>'',
						'status' 		 => 3,
						'author'	     => $this->_author,
						'created'        => $this->_created,
						'modified'       => $this->_modified,
					   );
					$ssp_data   = $this->_safedataObj->rteSafe($ssp_data);
					$storespID = $this->getDefinedTable(Store\StoreSpareTable::class)->save($ssp_data);
					if($storespID > 0 ){			   	    	
						$storespDtls = array(
							'storespare'     => $storespID,
							'location'       => $pur_receipt['location_id'],
							'quantity'       => $pur_receipt_detail['sound_quantity'],
							'rate'           => $pur_receipt_detail['rate'],
						    'amount'         =>$pur_receipt_detail['amount'],
							'author'         => $this->_author,
							'created'        => $this->_created,
							'modified'       => $this->_modified
						);	
						$storespDtls   = $this->_safedataObj->rteSafe($storespDtls);		   	    	
						$storespDtlsId = $this->getDefinedTable(Store\StoreSpareDetailsTable::class)->save($storespDtls);
						$prDtl_id = $this->getDefinedTable(Store\PRDetailsTable::class)->getColumn(array('purchase_receipt'=>$this->_id, 'item'=>$pur_receipt_detail['item_id']),'id');
                        $pr_details = array(
							'id'         => $prDtl_id,
							'assetssp'   => $storespID,
							'author'     => $this->_author,
							'modified'   => $this->_modified
						);
						$pr_details   = $this->_safedataObj->rteSafe($pr_details);
						$this->getDefinedTable(Store\PRDetailsTable::class)->save($pr_details);						
						if($storespDtlsId < 0):
							   $this->_connection->rollback(); // rollback transaction over failure
						   $this->flashMessenger()->addMessage("error^ Failed to save the data in store and spare details table. Please check ");			
						   return $this->redirect()->toRoute('inpurreceipt', array('action' => 'viewreceipt', 'id' => $this->_id));
						endif;
					}
					else{			   	     
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Failed to save the data in store and spare table. Please Check");
						return $this->redirect()->toRoute('inpurreceipt', array('action' => 'viewreceipt', 'id' =>  $this->_id));
					}	
				endif;
			endforeach;
		endif;
		
		// updating the status of purchase receipt
		$update_PR_data = array(
			'id'			=>$this->_id,
			'status' 		=> 10,
			'author'	    => $this->_author,
			'modified'      => $this->_modified,
		);
		$update_PR_data   = $this->_safedataObj->rteSafe($update_PR_data);
		$result = $this->getDefinedTable(Store\PurchaseReceiptTable::class)->save($update_PR_data);
		
		// Updating the status of purchase order
		$pur_orders = $this->getDefinedTable(Store\PurchaseOrderTable::class)->get(array('po.id'=>$pur_receipt['purchase_order']));
		//print_r($pur_orders); exit;
		foreach($pur_orders as $pur_order);
		$update_PO_data = array(
			'id'		=>$pur_order['id'],
			'status' 	=> 3,
			'author'	=> $this->_author,
			'modified'  => $this->_modified,
		);
		$update_PO_data   = $this->_safedataObj->rteSafe($update_PO_data);
		$result = $this->getDefinedTable(Store\PurchaseOrderTable::class)->save($update_PO_data);
		$this->_connection->commit(); // commit transaction over success
		$this->flashMessenger()->addMessage("success^ successfully save the data with for receipt no.".$purchase_receipt_no);			
		return $this->redirect()->toRoute('inpurreceipt', array('action' => 'viewreceipt', 'id' => $this->_id));	
    }	
   /**
	 *
	 *Commit purchase receipt
	 */
	public function commitpurreceiptAction()
	{
		$this->init();
		
		$pur_receipts = $this->getDefinedTable(Store\PurchaseReceiptTable::class)->get(array('pr.id'=>$this->_id));
		foreach($pur_receipts as $pur_receipt);
		
		$voucher_amount = $pur_receipt['payment_amt'];
		$receipt_no = $pur_receipt['purchase_receipt_no'];
			
		$this->_connection->beginTransaction(); //***Transaction begins here***//	
		 
		$location = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($pur_receipt['location_id'], 'prefix');
		$voucherType = '4'; //Journal
		$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($voucherType,'prefix');	 
		$date = date('ym',strtotime(date('Y-m-d')));
		$tmp_VCNo = $location.$prefix.$date;
		$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
		$pltp_no_list = array();
		foreach($results as $result):
			array_push($pltp_no_list, substr($result['voucher_no'], 8));
		endforeach;
		$next_serial = max($pltp_no_list) + 1;
			
		switch(strlen($next_serial)){
		case 1: $next_vc_serial = "000".$next_serial; break;
		case 2: $next_vc_serial = "00".$next_serial;  break;
		case 3: $next_vc_serial = "0".$next_serial;   break;
		default: $next_vc_serial = $next_serial;       break;
		}	
		$voucher_no = $tmp_VCNo.$next_vc_serial;
		$insert_data_transaction = array(
			'voucher_date' => date('Y-m-d'),
			'voucher_type' => $voucherType,
			'doc_id' => $receipt_no,
			'doc_type' => '',
			'voucher_no' => $voucher_no,
			'voucher_amount' => $voucher_amount,
			'remark' => 'Store Adjustment',
			'status' => 3, // status commited
			'author' =>$this->_author,
			'created' =>$this->_created,
			'modified' =>$this->_modified,
		); 
		//echo "<pre>";print_r($insert_data_transaction); exit;						
		$insert_data_transaction = $this->_safedataObj->rteSafe($insert_data_transaction);
		$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($insert_data_transaction);
		
		if($result > 0 && $pur_receipt['item_group_id'] == 1 )// Assets
		{ 
			$tdetailsdata = array(
				'transaction'  => $result,
				'location'     => $pur_receipt['location_id'],
				'activity'     => '5',
				'head'         => '16',
				'sub_head'     => '1237',
				'bank_ref_type'=> $bank_ref_type,
				'cheque_no'    => '',
				'debit'        => $voucher_amount,
				'credit'       => 0.00,
				'ref_no'       => '',
				'type'         => '2', //System Generated
				'author'       => $this->_author,
				'created'      => $this->_created,
				'modified'     => $this->_modified,
			);
			//echo "<pre>";print_r($tdetailsdata); exit;			
			$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
			$resultTdetailsdata = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
			if($resultTdetailsdata > 0)
			{	
				$tdetailsdata1 = array(
					'transaction' 	=> $result,
					'location' 		=> $pur_receipt['location_id'],
					'activity' 		=> '5',
					'head'         => '102',// expense payable
					'sub_head'     => '22004',// Store and spare bills
					'bank_ref_type' => $bank_ref_type,
					'cheque_no' 	=> $cheque_no,
					'debit' 		=> 0.00,
					'credit' 		=> $voucher_amount,
					'ref_no'		=> '',
					'type' 			=> '2', //System Generated
					'author' 		=>$this->_author,
					'created' 		=>$this->_created,
					'modified' 		=>$this->_modified,
				);
				$tdetailsdata1 = $this->_safedataObj->rteSafe($tdetailsdata1);
				$resultTdetailsdata1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata1);
				if($resultTdetailsdata1 < 0){
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to save the data in transaction details table. Please check ");			
					return $this->redirect()->toRoute('inpurreceipt', array('action' => 'viewreceipt', 'id' => $this->_id));	
				}
			}else{	
			$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessenger()->addMessage("error^ Failed to save the data in assets details table. Please check ");			
			return $this->redirect()->toRoute('inpurreceipt', array('action' => 'viewreceipt', 'id' => $this->_id));
			}				
		}
		
		else //Store and Spare
		
		{ 
		$tdetailsdata = array(
			'transaction'  => $result,
			'location'     => $pur_receipt['location_id'],
			'activity'     => '5',
			'head'         => '102',// expense payable
			'sub_head'     => '22004',// Store and spare bills
			'bank_ref_type'=> $bank_ref_type,
			'cheque_no'    => '',
			'debit'        => '0.00',
			'credit'       => $pur_receipt['payment_amt'],
			'ref_no'       => '',
			'type'         => '2', //System Generated
			'author'       => $this->_author,
			'created'      => $this->_created,
			'modified'     => $this->_modified,
			);
			$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
			$resultTdetailsdata = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
			
			$item_groups = $this->getDefinedTable(Store\PRDetailsTable::class)->getDistinctISGID(array('purchase_receipt'=>$this->_id));
                       // print_r($item_groups); exit;
		    foreach($item_groups  as $item_group):
                if($item_group['item_sub_group_id'] == 1){$head = 203;}elseif($item_group['item_sub_group_id'] == 4){$head = 183;}elseif($item_group['item_sub_group_id'] == 9){$head = 203;}
				elseif($item_group['item_sub_group_id'] == 8){$head = 203;}elseif($item_group['item_sub_group_id'] == 3){$head = 156;}elseif($item_group['item_sub_group_id'] == 7){$head = 259;}elseif($item_group['item_sub_group_id'] == 2){$head = 167;}					$amount = $this->getDefinedTable(Store\PRDetailsTable::class)->getAmount(array('purchase_receipt'=>$this->_id),$item_group['item_sub_group_id']);
				$sub_headResults = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.type'=>'9', 'sh.head'=>$head,'sh.ref_id'=>$item_group['item_sub_group_id']));
				if(sizeof($sub_headResults) > 0 )
				{
					foreach($sub_headResults as $shrow);
					$tdetailsdata1 = array(
						'transaction' 	=> $result,
						'location' 		=> $pur_receipt['location_id'],
						'activity' 		=> '5',
						'head' 			=> $shrow['head_id'],
						'sub_head' 		=> $shrow['id'],
						'bank_ref_type' => $bank_ref_type,
						'cheque_no' 	=> $cheque_no,
						'debit' 		=> $amount,
						'credit' 		=> '0.00',
						'ref_no'		=> '',
						'type' 			=> '2', //System Generated
						'author' 		=>$this->_author,
						'created' 		=>$this->_created,
						'modified' 		=>$this->_modified,
					);
					$tdetailsdata1 = $this->_safedataObj->rteSafe($tdetailsdata1);
					//echo "<pre>";print_r($tdetailsdata1); exit;			
					$resultTdetailsdata1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata1);
					if($resultTdetailsdata1 < 0){
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Failed to save the data in transaction details table. Please check ");			
						return $this->redirect()->toRoute('inpurreceipt', array('action' => 'viewreceipt', 'id' => $this->_id));	
					}									
				}else{
					
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to save the data in store and spare details table and sub head mapping is not done. Please check ");			
				return $this->redirect()->toRoute('inpurreceipt', array('action' => 'viewreceipt', 'id' => $this->_id));
				}
		    endforeach;
		}
		// Updating the status and transaction id in the issue table
		$data = array(
			'id'			=> $this->_id,
			'transaction_id'=> $result,
			'status' 		=> 3,
			'author'	    => $this->_author,
			'modified'      => $this->_modified,
		);
		$data   = $this->_safedataObj->rteSafe($data);				
		$result = $this->getDefinedTable(Store\PurchaseReceiptTable::class)->save($data);
		$this->_connection->commit(); // commit transaction over success
		$this->flashMessenger()->addMessage("success^ successfully save the data for receipt no." .$receipt_no);
		return $this->redirect()->toRoute('inpurreceipt', array('action' => 'viewreceipt', 'id' => $this->_id));
	}	
}


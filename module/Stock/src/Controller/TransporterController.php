<?php
namespace Stock\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl;
use Stock\Model As Stock;
use Administration\Model As Administration;
use Purchase\Model As Purchase;
use Accounts\Model As Accounts;
class TransporterController extends AbstractActionController
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
	 * index Action of TransporterInvoice
	 */
	public function indexAction()
	{
		$this->init();
		
		return new ViewModel( array(
                'title'   => 'Transporter Invoice',
		) );
	}
	
	/**
	 * transport charge Action
	 */
	public function transportchargeAction()
	{
		$this->init();
		$month = 0;
		$year = 0;
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
		$transport_charges = $this->getDefinedTable(Stock\TransportChargeTable::class)->getDateWise('transport_date',$year,$month);
		return new ViewModel(array(
				'title' => 'Transport Charge',
				'transport_charges' => $transport_charges,
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'transporterObj' => $this->getDefinedTable(Accounts\PartyTable::class),
				'minYear' => $this->getDefinedTable(Stock\TransportChargeTable::class)->getMin('transport_date'),
				'data' => $data,
                                'dispatchObj' => $this->getDefinedTable(Stock\DispatchTable::class),
		));
	}
	
	/**
	 * View Transport Charge
	 */
	public function viewtranspchargeAction()
	{
		$this->init();
		
		return new ViewModel(array(
				'title' => 'View Transport Charge',
				'transport_charges' => $this->getDefinedTable(Stock\TransportChargeTable::class)->get($this->_id),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'partyObj' => $this->getDefinedTable(Accounts\PartyTable::class),
				'dispatchObj' => $this->getDefinedTable(Stock\DispatchTable::class),
				'transit_lossObj' => $this->getDefinedTable(Stock\TransitLossTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class), 
				'batchObj' => $this->getDefinedTable("Stock\BatchTable"),
				'uomObj' => $this->getDefinedTable("Stock\UomTable"),
		));
	}
	
	/**
	 * Edit Transport charge
	 */
	public function edittranspchargeAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			//echo 'hi there';
			$form = $this->getRequest()->getpost();
			$tc_data = array(
					'id' => $form['tc_id'],
					'hill_distance' => $form['hill_distance'],
					'hill_rate' => $form['hill_rate'],
					'plain_distance' => $form['plain_distance'],
					'plain_rate' => $form['plain_rate'],
					'qty' => $form['qty'],
					'transportation_charge' => $form['transp_charge'],
					'author' => $this->_author,
					'modified' => $this->_modified
			);
			//echo "<pre>";print_r($tc_data);exit;
			$tc_data = $this->_safedataObj->rteSafe($tc_data);
			$tc_result = $this->getDefinedTable(Stock\TransportChargeTable::class)->save($tc_data);
			
			if($tc_result > 0):
				$tl_id = $form['tl_id'];
				$dispatch = $form['tl_dispatch'];
				$item = $form['item'];
				$batch = $form['batch'];
				$uom = $form['uom'];
				$rate = $form['rate'];
				$qty_loss = $form['qty_loss'];
				$recovery_qty = $form['recovery_qty'];
				$amount = $form['amount'];
				$delete_rows = $this->getDefinedTable(Stock\TransitLossTable::class)->getNotIn($tl_id, array('dispatch' => $form['tc_dispatch']));
				
				for($i = 0; $i < sizeof($item); $i++):
					if(isset($item[$i]) && is_numeric($qty_loss[$i]) && $item[$i] > 0):
						$tl_data = array(
								'id' => $tl_id[$i],
								'dispatch' => $form['tc_dispatch'],
								'item' => $item[$i],
								'batch' => $batch[$i],
								'uom' => $uom[$i],
								'rate' => $rate[$i],
								'qty_loss' => $qty_loss[$i],
								'recovery_qty' => $recovery_qty[$i],
								'amount' => $amount[$i],
								'author' => $this->_author,
								'modified' => $this->_modified,
						);
						$tl_data = $this->_safedataObj->rteSafe($tl_data);
						//echo "<pre>"; print_r($tl_data);exit;
						$tl_result = $this->getDefinedTable(Stock\TransitLossTable::class)->save($tl_data);
					endif;
				endfor;
				
				//deleting deleted table rows form database table;
				foreach($delete_rows as $delete_row):
					$this->getDefinedTable(Stock\TransitLossTable::class)->remove($delete_row['id']);
				endforeach;
				$tc_no = $this->getDefinedTable(Stock\TransportChargeTable::class)->getColumn($form['tc_id'],'transport_no');
				$this->flashMessenger()->addMessage("success^ Successfully updated Transportation Charge ".$tc_no);
				return $this->redirect()->toRoute('transporter',array('action'=>'viewtranspcharge','id'=>$tc_result));
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to Update Transportation Charge");
				return $this->redirect()->toRoute('transporter',array('action'=>'edittranspcharge','id'=>$tc_result));
			endif;
		endif;
		
		return new ViewModel(array(
				'title' => 'Edit Transport Charge',
				'transport_charges' => $this->getDefinedTable(Stock\TransportChargeTable::class)->get($this->_id),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'partyObj' => $this->getDefinedTable(Accounts\PartyTable::class),
				'dispatchObj' => $this->getDefinedTable(Stock\DispatchTable::class),
				'transit_lossObj' => $this->getDefinedTable(Stock\TransitLossTable::class),
				'itemsObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'batchObj' => $this->getDefinedTable("Stock\BatchTable"),
				'movingitemObj' => $this->getDefinedTable(Stock\MovingItemTable::class),
				'uomObj' => $this->getDefinedTable("Stock\UomTable"),
				'itemuomObj' => $this->getDefinedTable("Stock\ItemuomTable"),
		));
	}
	
	/**
	 * get details of dispatch
	 * get batch N/A --> if Wt Moving Avg, Batches --> if FIFO 
	 * get UOM, selected uom if Wt Moving Avg
	 * get Selling price if Wt Moving Avg
	 */
	public function getdetailsAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getpost();
		
		$item_id = $form['item_id'];
		$location = $form['location'];
		
		$selected_item = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
		foreach($selected_item as $item);
		
		$item_valuation = $item['valuation'];
		
		$batchObj = $this->getDefinedTable(Stock\BatchTable::class);
		/***** Select UOM Options *****/
		$itemuoms = $this->getDefinedTable(Stock\ItemUomTable::class)->get(array('ui.item'=>$item_id));
		$select_uom .="<option value=''></option>";
		foreach($selected_item as $item):
			$select_uom .="<option value='".$item['st_uom_id']."'>".$item['st_uom_code']."</option>";
		endforeach;
		foreach($itemuoms as $itemuom):
			$select_uom .="<option value='".$itemuom['uom_id']."'>".$itemuom['uom_code']."</option>";
		endforeach;
		
		if($item_valuation == 1)://Wt Movining Average/Food Grain
			$batch = "<option value=''>N/A</option>";
			$movingitemObj = $this->getDefinedTable(Stock\MovingItemTable::class);
			
			$batch_id = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$location),'batch');
			$batch_uom = $batchObj->getColumn(array('id'=>$batch_id,'item'=>$item_id),'uom'); 
			$selling_price = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$location),'selling_price');
			$selling_price = ($selling_price >0)?$selling_price:"0.00";
			echo json_encode(array(
					'batch' => $batch,
					'uom' => $select_uom,
					'batch_uom' => $batch_uom,
					'selling_price' => $selling_price,
			));
		else: //$item_valuation == 0/FIFO/Agency
			$batchs = $batchObj->get(array('b.item'=>$item_id));
			$select_batch .="<option value=''></option>";
			foreach($batchs as $batch):
				if($batch['end_date'] == "0000-00-00" || $batch['end_date'] == ""):
					$select_batch .="<option value='".$batch['id']."'>".$batch['batch']."</option>";
				endif;
			endforeach;
			$max_batch_id = $batchObj->getMax('id',array('item'=>$item_id));
			$batch_uom = $batchObj->getColumn(array('id'=>$max_batch_id,'item'=>$item_id),'uom');
			$batchdltObj = $this->getDefinedTable(Stock\BatchDetailsTable::class);
			$selling_price = $batchdltObj->getColumn(array('batch'=>$max_batch_id,'location'=>$location),'selling_price');
			$selling_price = ($selling_price >0)?$selling_price:"0.00";
			echo json_encode(array(
					'batch' => $select_batch,
					'latest_batch' => $max_batch_id,
					'uom' => $select_uom,
					'batch_uom' => $batch_uom,
					'selling_price' => $selling_price,
			));
		endif;
		exit;
	}
	
	/**
	 * getsellingprice Action
	 * Selling Price and Selected UOM
	 */
	public function getsellingpriceAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		
		$batch_id = $form['batch_id'];
		$item_id = $form['item_id'];
		$location = $form['location'];
		
		$batchdltObj = $this->getDefinedTable(Stock\BatchDetailsTable::class);
		$selling_price = $batchdltObj->getColumn(array('batch'=>$batch_id,'location'=>$location),'selling_price');
		
		$batch_uom = $this->getDefinedTable(Stock\BatchTable::class)->getColumn(array('id'=>$batch_id,'item'=>$item_id),'uom');
		
		echo json_encode(array(
				'selling_price' => $selling_price,
				'batch_uom' => $batch_uom,
		));
		exit;
	}
	
	/**
	 * get converted qty Action
	 */
	public function getconvertedrateAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$item_id = $form['item_id'];
		$batch_id = $form['batch_id'];
		$selected_uom = $form['uom_id'];
		$location = $form['location'];
		
		$selected_item = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
		foreach($selected_item as $item);
		
		$item_valuation = $item['valuation'];
		$basic_uom = $item['uom'];
		
		$batchObj = $this->getDefinedTable(Stock\BatchTable::class);
		$itemuomObj = $this->getDefinedTable(Stock\ItemUomTable::class);
		
		if($item_valuation == 1)://Wt Movining Average/Food Grain
		
			$movingitemObj = $this->getDefinedTable(Stock\MovingItemTable::class);
		
			$costing_rate = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$location),'selling_price');
				
			$batch_id = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$location),'batch');
			$batch_uom = $batchObj->getColumn(array('id'=>$batch_id,'item'=>$item_id),'uom');
			
		else: //$item_valuation == 0/FIFO/Agency
			$batchdltObj = $this->getDefinedTable(Stock\BatchDetailsTable::class);
			$costing_rate = $batchdltObj->getColumn(array('batch'=>$batch_id,'location'=>$location),'selling_price');
		
			$batch_uom = $batchObj->getColumn(array('id'=>$batch_id,'item'=>$item_id),'uom');
			
		endif;
		
		$batch_uom_conversion = $itemuomObj->getColumn(array('item'=>$item_id,'uom'=>$batch_uom),'conversion');
		$selected_uom_conversion = $itemuomObj->getColumn(array('item'=>$item_id,'uom'=>$selected_uom),'conversion');
			
		$costing_rate = ($batch_uom == $basic_uom)?$costing_rate: $costing_rate / $batch_uom_conversion;
		$costing_converted_rate = ($basic_uom == $selected_uom)?$costing_rate: $costing_rate * $selected_uom_conversion;
		
		echo json_encode(array(
				'rate' => $costing_converted_rate,
		));
		exit;
	}
	
	/**
	 * commit purchase order Action
	 *
	 */
	public function committranspchargeAction()
	{
		$this->init();
		
		$data = array(
				'id' =>$this->_id,
				'status' => 3,
				'author' => $this->_author,
				'modified' => $this->_modified,
		);
		$data   = $this->_safedataObj->rteSafe($data);
		$result = $this->getDefinedTable(Stock\TransportChargeTable::class)->save($data);
		if($result):
			$tc_no = $this->getDefinedTable(Stock\TransportChargeTable::class)->getColumn($this->_id,'transport_no');
			$this->flashMessenger()->addMessage("success^ Successfully commited Transportation Charge ".$tc_no);
			return $this->redirect()->toRoute('transporter', array('action'=>'viewtranspcharge','id'=>$this->_id));
		endif;
	}
	
	/**
	 * transporter invoice
	 */
	public function transporterinvAction()
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
		$transinvs = $this->getDefinedTable(Stock\TransporterInvoiceTable::class)->getDateWise('transport_inv_date',$year,$month);
		return new ViewModel(array(
			'title' 		 => 'Transport Invoice',
			'transinvs'		 => $transinvs,
			'locationObj'    => $this->getDefinedTable(Administration\LocationTable::class),
			//'activityObj'	 => $this->getDefinedTable(Administration\ActivityTable::class),
			'transporterObj' => $this->getDefinedTable(Accounts\PartyTable::class),
			'minYear' 		 => $this->getDefinedTable(Stock\TransporterInvoiceTable::class)->getMin('transport_inv_date'),
			'data'           => $data,
		));
	}
	
	/**
	 * addtransporterinv Action
	 */
	public function addtransporterinvAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();
			
			$data = array(
					'transport_inv_no' => $form['transport_inv_no'],
					'transport_inv_date' => $form['transport_inv_date'],
					'location' => $form['location'],
					//'activity' => $form['activity'],
					'transporter' => $form['transporter'],
					'from_date' => $form['from_date'],
					'to_date' => $form['to_date'],
					'total_amount' => $form['total_amount'],
					'deduction' => $form['total_deduction'],
					'payable_amount' => $form['total_payable_amount'],
					'status' => 1,
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			//echo "<pre>"; print_r($data); exit;
			$result = $this->getDefinedTable(Stock\TransporterInvoiceTable::class)->save($data);
			if($result > 0):
				$transp_status = $form['transp_status'];
				//$deduction = $form['deduction'];
				
				//echo "<pre>"; print_r($deduction);
				foreach($transp_status as $row):
					$deduction = $form['deduction_'.$row];
					
					$transport_charges = $this->getDefinedTable(Stock\TransportChargeTable::class)->get($row);
					foreach($transport_charges as $transport_charge):
						$hill_transp_charge = $transport_charge['hill_distance'] * $transport_charge['hill_rate'] * $transport_charge['qty'];
						$plain_transp_charge = $transport_charge['plain_distance'] * $transport_charge['plain_rate'] * $transport_charge['qty'];
						$total_charge = $hill_transp_charge + $plain_transp_charge;
						
						$payable_amt = $total_charge-$deduction;
						$ti_details = array(
								'transporter_invoice' => $result,
								'transport_charge' 	=> $transport_charge['id'],
								'transport_date' 	=> $transport_charge['transport_date'],
								'activity'          => $transport_charge['activity'],
								'amount' 			=> $total_charge,
								'deduction' 		=> $deduction,
								'payable_amount' 	=> $payable_amt,
								'author'    		=> $this->_author,
								'created'   		=> $this->_created,
								'modified'  		=> $this->_modified
						);
						$ti_details   = $this->_safedataObj->rteSafe($ti_details);
						//echo "<pre>";print_r($ti_details);
						$this->getDefinedTable(Stock\TranspInvDetailsTable::class)->save($ti_details);
					endforeach;
				endforeach;
				
				$this->flashMessenger()->addMessage("success^ successfully added new Transporter Invoice :". $form['transport_inv_no']);
				return $this->redirect()->toRoute('transporter', array('action'=> 'viewtransporterinv', 'id'=>$result));
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new Transporter Invoice");
				return $this->redirect()->toRoute('transporter',array('action'=>'addtransporterinv'));
			endif;
		endif;//end of post
		return new ViewModel(array(
				'title' => 'Add Transport Invoice',
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'transporters' => $this->getDefinedTable(Accounts\PartyTable::class)->getAll(),
		));
	}
	/**
	 * Get Transportation Charge 
	 * for Transporter Invoice
	 */
	public function gettransinvdetailsAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getpost();
	
		$from_date = $form['from_date'];
		$to_date   = $form['to_date'];
		$location  = $form['location'];
		//$activity  = $form['activity'];
		$transporter = $form['transporter'];
	
		$data = array(
				'from_date' => $from_date,
				'to_date'   => $to_date,
				'location'  => $location,
				//'activity'  => $activity,
				'transporter' => $transporter,
		);
		//echo "<pre>";print_r($trans_inv_data); exit;
		$ViewModel = new ViewModel(array(
				'data' => $data,
				'transchargeObj' => $this->getDefinedTable(Stock\TransportChargeTable::class),
				'dispatchObj' => $this->getDefinedTable(Stock\DispatchTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'partyObj' => $this->getDefinedTable(Accounts\PartyTable::class),
				'activityObj'=> $this->getDefinedTable(Administration\ActivityTable::class),
				'transit_lossObj' => $this->getDefinedTable(Stock\TransitLossTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * view transporter invoice action
	 */
	public function viewtransporterinvAction(){
		$this->init();
	
		return new ViewModel(array(
				'title' => 'View Transporter Invoice',
				'transinvs' => $this->getDefinedTable(Stock\TransporterInvoiceTable::class)->get($this->_id),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj'=> $this->getDefinedTable(Administration\ActivityTable::class),
				'partyObj' => $this->getDefinedTable(Accounts\PartyTable::class),
				'transinvdtlObj' => $this->getDefinedTable(Stock\TranspInvDetailsTable::class),
				'transchargeObj' => $this->getDefinedTable(Stock\TransportChargeTable::class),
				'usersObj' => $this->getDefinedTable(Administration\UsersTable::class),
				'dispatchObj' => $this->getDefinedTable(Stock\DispatchTable::class),
		));
	}
	
	/**
	 * edit transporter invoice action
	 */
	public function edittransporterinvAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();
			$data = array(
					'id' => $form['transinv_id'],
					'transport_inv_no' => $form['transport_inv_no'],
					'transport_inv_date' => $form['transport_inv_date'],
					'location' => $form['location'],
					//'activity' => $form['activity'],
					'transporter' => $form['transporter'],
					'from_date' => $form['from_date'],
					'to_date' => $form['to_date'],
					'total_amount' => $form['total_amount'],
					'deduction' => $form['total_deduction'],
					'payable_amount' => $form['total_payable_amount'],
					'status' => 1,
					'author' => $this->_author,
					'modified' => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			//echo "<pre>"; print_r($data); exit;
			$result = $this->getDefinedTable(Stock\TransporterInvoiceTable::class)->save($data);
			if($result > 0):
				/***** Transporter Invoice Details *****/
				$clear_details = $this->getDefinedTable(Stock\TranspInvDetailsTable::class)->get(array('transporter_invoice'=>$form['transinv_id']));
				foreach($clear_details as $clear):
					$this->getDefinedTable(Stock\TranspInvDetailsTable::class)->remove($clear['id']);
				endforeach;
				$transp_status = $form['transp_status'];
				
				//echo "<pre>"; print_r($deduction);
				foreach($transp_status as $row):
					$deduction = $form['deduction_'.$row];
					//echo "<pre>"; print_r($deduction);
					$transport_charges = $this->getDefinedTable(Stock\TransportChargeTable::class)->get($row);
					foreach($transport_charges as $transport_charge):
						$hill_transp_charge = $transport_charge['hill_distance'] * $transport_charge['hill_rate'] * $transport_charge['qty'];
						$plain_transp_charge = $transport_charge['plain_distance'] * $transport_charge['plain_rate'] * $transport_charge['qty'];
						$total_charge = $hill_transp_charge + $plain_transp_charge;
						
						$payable_amt = $total_charge-$deduction;
						$ti_details = array(
								'transporter_invoice' => $result,
								'transport_charge' 	=> $transport_charge['id'],
								'transport_date' 	=> $transport_charge['transport_date'],
								'activity'          => $transport_charge['activity'],
								'amount' 			=> $total_charge,
								'deduction' 		=> $deduction,
								'payable_amount' 	=> $payable_amt,
								'author'    		=> $this->_author,
								'created'   		=> $this->_created,
								'modified'  		=> $this->_modified
						);
						$ti_details   = $this->_safedataObj->rteSafe($ti_details);
						//echo "<pre>";print_r($ti_details);
						$this->getDefinedTable(Stock\TranspInvDetailsTable::class)->save($ti_details);
					endforeach;
				endforeach;
				//exit;
				$this->flashMessenger()->addMessage("success^ Successfully updated Transporter Invoice ". $form['transport_inv_no']);
				return $this->redirect()->toRoute('transporter', array('action'=> 'viewtransporterinv', 'id'=>$result));
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to update Transporter Invoice");
				return $this->redirect()->toRoute('transporter',array('action'=>'edittransporterinv'));
			endif;
		endif;//end of post
		return new ViewModel(array(
				'title' => 'Edit Transport Invoice',
				'transinvs' => $this->getDefinedTable(Stock\TransporterInvoiceTable::class)->get($this->_id),
				'transinvdtls' => $this->getDefinedTable(Stock\TranspInvDetailsTable::class)->get(array('transporter_invoice'=>$this->_id)),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'transporters' => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'4')),
				'transchargeObj' => $this->getDefinedTable(Stock\TransportChargeTable::class),
				'dispatchObj' => $this->getDefinedTable(Stock\DispatchTable::class),
				'transinvdtlsObj' => $this->getDefinedTable(Stock\TranspInvDetailsTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'partyObj' => $this->getDefinedTable(Accounts\PartyTable::class),
		));
	}
	
	/*
	 * commit Transport Invoice Action
	 
	  *public function committransinvAction(){
	  	$this->init();
		
		$transinv_dtls = $this->getDefinedTable(Stock\TranspInvDetailsTable::class)->get(array('transporter_invoice' => $this->_id));
		foreach($transinv_dtls as $transinv_dtl):
			$inv_data = array(
					'id' => $transinv_dtl['transport_charge'],	
					'invoiced' => '1',
					'modified' => $this->_modified,
			);
			$inv_data   = $this->_safedataObj->rteSafe($inv_data);
			$result1 = $this->getDefinedTable(Stock\TransportChargeTable::class)->save($inv_data);
		endforeach;
		
		$transinvs = $this->getDefinedTable(Stock\TransporterInvoiceTable::class)->get($this->_id);
		foreach ($transinvs as $transinv):
			$data = array(
					'id'			=>$this->_id,
					'status' 		=> 3,
					'author'	    => $this->_author,
					'modified'      => $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\TransporterInvoiceTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully commited Transporter Invoice ".$transinv['transport_inv_no']);
				return $this->redirect()->toRoute('transporter',array('action'=>'viewtransporterinv','id'=>$result));
			endif;
		endforeach;
	*}
        *
	*/
         public function committransinvAction(){
		$this->init();
		$flag = 1;
		$transinv_dtls = $this->getDefinedTable(Stock\TranspInvDetailsTable::class)->get(array('transporter_invoice' => $this->_id));
		$total_amt = 0;
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		foreach($transinv_dtls as $transinv_dtl):
			$inv_data = array(
					'id' => $transinv_dtl['transport_charge'],	
					'invoiced' => '1',
					'modified' => $this->_modified,
			);
			$inv_data   = $this->_safedataObj->rteSafe($inv_data);
			$this->getDefinedTable(Stock\TransportChargeTable::class)->save($inv_data);
			
			$total_amt += $transinv_dtl['amount'];
		endforeach;
		
		$transinvs = $this->getDefinedTable(Stock\TransporterInvoiceTable::class)->get($this->_id);
		foreach ($transinvs as $transinv):
			$data = array(
					'id'			=>$this->_id,
					'status' 		=> 3,
					'author'	    => $this->_author,
					'modified'      => $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result1 = $this->getDefinedTable(Stock\TransporterInvoiceTable::class)->save($data);
			if($result1):
				//for booking of transport invoice voucher 
				$location = $transinv['location'];
				$voucherType = 4;
				
				$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($voucherType,'prefix');
				
				$date = date('ym',strtotime($transinv['transport_inv_date']));
				
				$tmp_VCNo = $loc.$prefix.$date; 
				$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
			
				$pltp_no_list = array();
				foreach($results as $result):
					array_push($pltp_no_list, substr($result['voucher_no'], 8));
				endforeach;
				$next_serial = max($pltp_no_list) + 1;
					
				switch(strlen($next_serial)){
					case 1: $next_dc_serial = "000".$next_serial; break;
					case 2: $next_dc_serial = "00".$next_serial;  break;
					case 3: $next_dc_serial = "0".$next_serial;   break;
					default: $next_dc_serial = $next_serial;       break;
				}	
				$voucher_no = $tmp_VCNo.$next_dc_serial;
					
				$fa_data = array(
							'voucher_date' => date('Y-m-d'),
							'voucher_type' => $voucherType,
							'doc_id' => $transinv['transport_inv_no'],
							'doc_type' => 'Transporter',
							'voucher_no' => $voucher_no,
							'voucher_amount' => str_replace( ",","",$total_amt),
							'remark' => $sup_inv['note'],
							'status' => 3, // status initiated
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,	
				);
				$fa_data = $this->_safedataObj->rteSafe($fa_data);
				$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($fa_data);
				
				if($result):
					//safe transaction id in transporter inv table
					$txn = array(
							'id' => $this->_id,
							'transaction_id' => $result,
					);
					$this->_safedataObj->rteSafe($txn);
					$this->getDefinedTable(Stock\TransporterInvoiceTable::class)->save($txn);
				
					//for head selection according to the activity, and as debit booking 
					foreach($transinv_dtls as $transinv_dtl):
						if($transinv_dtl['activity'] == 1 || $transinv_dtl['activity'] == 2){
							$subheadDtls = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>'81','sh.ref_id'=>$transinv['transporter']));
							if($subheadDtls == 0): $flag = 0; endif;
						}else{
							$subheadDtls = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>'177','sh.ref_id'=>$transinv['transporter']));
							if($subheadDtls == 0): $flag = 0; endif;
						}
						
						foreach($subheadDtls as $subheadD);
						$debit = $transinv_dtl['amount'];
						$credit = 0;
						
						$fa_data1 = array(
									'transaction' => $result,
									'location' => $location,
									'activity' => $transinv_dtl['activity'],
									'head' => $subheadD['head_id'],
									'sub_head' => $subheadD['id'],
									'bank_ref_type' => '',
									'cheque_no' => '',
									'debit' => (isset($debit))? $debit:'0.000',
									'credit' => (isset($credit))? $credit:'0.000',
									'ref_no'=> '',
									'type' => '2', //System Generated
									'author' =>$this->_author,
									'created' =>$this->_created,
									'modified' =>$this->_modified,
						);
						$fa_data1 = $this->_safedataObj->rteSafe($fa_data1);
						$data = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($fa_data1);
					endforeach;
					
					//for head = 98, 80, and as credit booking according to the activity
					$credit2 = 0;
					$credit3 = 0;
					$credit22 = 0;
					$debit2 = 0;
					$deduction = 0;
					$agDeduct = 0;
					$fgDeduct = 0;
					$generlDeduct = 0;
					foreach($transinv_dtls as $transinv_dtl):
						$deduction += $transinv_dtl['deduction'];
						$activity = $transinv_dtl['activity'];
						switch($activity){
							case 1: $credit2 += $transinv_dtl['payable_amount'];
									$agDeduct += $transinv_dtl['deduction'];
									break;
									//for agency 
							case 2: $credit3 += $transinv_dtl['payable_amount'];
									$fgDeduct += $transinv_dtl['deduction'];
									break;
									//for fg
							default: $credit22 += $transinv_dtl['payable_amount'];
									$generlDeduct += $transinv_dtl['deduction'];
									$activity22 = $activity;
									break;
									//for activity other then 1 and 2
						}
					endforeach;
					
						if($deduction > 0){
							$subheadDtls3 = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>'80','sh.ref_id'=>$transinv['transporter']));
							if($subheadDtls3 == 0){$flag = 0;}
							foreach($subheadDtls3 as $subheadD3);
							if($agDeduct > 0):
							$fa_data2 = array(
										'transaction' => $result,
										'location' => $location,
										'activity' => 1,
										'head' => 80,
										'sub_head' =>  $subheadD3['id'],
										'bank_ref_type' => '',
										'cheque_no' => '',
										'debit' => (isset($debit2))? $debit2:'0.000',
										'credit' => (isset($agDeduct))? $agDeduct:'0.000',
										'ref_no'=> '',
										'type' => '2', //System Generated
										'author' =>$this->_author,
										'created' =>$this->_created,
										'modified' =>$this->_modified,
							);
							$fa_data2 = $this->_safedataObj->rteSafe($fa_data2);
							$this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($fa_data2);
							endif;
							
							if($fgDeduct > 0):
							$fa_data3 = array(
										'transaction' => $result,
										'location' => $location,
										'activity' => 2,
										'head' => 80,
										'sub_head' =>  $subheadD3['id'],
										'bank_ref_type' => '',
										'cheque_no' => '',
										'debit' => (isset($debit2))? $debit2:'0.000',
										'credit' => (isset($fgDeduct))? $fgDeduct:'0.000',
										'ref_no'=> '',
										'type' => '2', //System Generated
										'author' =>$this->_author,
										'created' =>$this->_created,
										'modified' =>$this->_modified,
							);
							$fa_data3 = $this->_safedataObj->rteSafe($fa_data3);
							$this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($fa_data3);
							endif;
							
							if($generlDeduct > 0):
							$fa_data4 = array(
										'transaction' => $result,
										'location' => $location,
										'activity' => $activity22,
										'head' => 80,
										'sub_head' =>  $subheadD3['id'],
										'bank_ref_type' => '',
										'cheque_no' => '',
										'debit' => (isset($debit2))? $debit2:'0.000',
										'credit' => (isset($generlDeduct))? $generlDeduct:'0.000',
										'ref_no'=> '',
										'type' => '2', //System Generated
										'author' =>$this->_author,
										'created' =>$this->_created,
										'modified' =>$this->_modified,
							);
							$fa_data4 = $this->_safedataObj->rteSafe($fa_data4);
							$this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($fa_data4);
							endif;
						}
						
						$subheadDtls2 = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>'98','sh.ref_id'=>$transinv['transporter']));
						if($subheadDtls2 == 0){$flag = 0;}
						foreach($subheadDtls2 as $subheadD2);
							if($credit2 > 0):
							$fa_data2 = array(
										'transaction' => $result,
										'location' => $location,
										'activity' => 1,
										'head' => 98,
										'sub_head' =>  $subheadD2['id'],
										'bank_ref_type' => '',
										'cheque_no' => '',
										'debit' => (isset($debit2))? $debit2:'0.000',
										'credit' => (isset($credit2))? $credit2:'0.000',
										'ref_no'=> '',
										'type' => '2', //System Generated
										'author' =>$this->_author,
										'created' =>$this->_created,
										'modified' =>$this->_modified,
							);
							$fa_data2 = $this->_safedataObj->rteSafe($fa_data2);
							$this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($fa_data2);
							endif;
							
							if($credit3 > 0):
							$fa_data3 = array(
										'transaction' => $result,
										'location' => $location,
										'activity' => 2,
										'head' => 98,
										'sub_head' =>  $subheadD2['id'],
										'bank_ref_type' => '',
										'cheque_no' => '',
										'debit' => (isset($debit2))? $debit2:'0.000',
										'credit' => (isset($credit3))? $credit3:'0.000',
										'ref_no'=> '',
										'type' => '2', //System Generated
										'author' =>$this->_author,
										'created' =>$this->_created,
										'modified' =>$this->_modified,
							);
							$fa_data3 = $this->_safedataObj->rteSafe($fa_data3);
							$this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($fa_data3);
							endif;
							
							if($credit22 > 0):
							$fa_data4 = array(
										'transaction' => $result,
										'location' => $location,
										'activity' => $activity22,
										'head' => 98,
										'sub_head' =>  $subheadD2['id'],
										'bank_ref_type' => '',
										'cheque_no' => '',
										'debit' => (isset($debit2))? $debit2:'0.000',
										'credit' => (isset($credit22))? $credit22:'0.000',
										'ref_no'=> '',
										'type' => '2', //System Generated
										'author' =>$this->_author,
										'created' =>$this->_created,
										'modified' =>$this->_modified,
							);
							$fa_data4 = $this->_safedataObj->rteSafe($fa_data4);
							$this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($fa_data4);
							endif;
							
						if($flag = 1):
							$this->_connection->commit(); // commit transaction on success
							$this->flashMessenger()->addMessage("success^ Successfully commited Transporter Invoice ".$transinv['transport_inv_no']." and Booking of Voucher ".$voucher_no );
						else:
							$this->_connection->rollback(); // rollback transaction over failure
							$this->flashMessenger()->addMessage("error^ Unsuccessfull, Subhead Missing");
						endif;
				endif;
				return $this->redirect()->toRoute('transporter',array('action'=>'viewtransporterinv','id'=>$result1));
			endif;
		endforeach;
	}
}



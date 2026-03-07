<?php
namespace Asset\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Laminas\Stdlib\ArrayObject;
use Laminas\Validator\File\Size;
use Laminas\Validator\File\Extension;
use Interop\Container\ContainerInterface;
use Asset\Model As Asset;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Hr\Model As Hr;
use Accounts\Model As Accounts; 

class AssetController extends AbstractActionController
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
		if(!isset($this->_highest_role)){
			$this->_highest_role = $this->getDefinedTable(Acl\RolesTable::class)->getMax($column='id');  
		}
		if(!isset($this->_lowest_role)){
			$this->_lowest_role = $this->getDefinedTable(Acl\RolesTable::class)->getMin($column='id'); 
		}
		if(!isset($this->_author)){
			$this->_author = $this->_user->id;  
		}
		
		$this->_id = $this->params()->fromRoute('id');
		
		$this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		
		
		$fileManagerDir = $this->_config['file_manager']['dir'];
	
		if(!is_dir($fileManagerDir)) {
			mkdir($fileManagerDir, 0777);
		}			
	
		$this->_dir =realpath($fileManagerDir);
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
	/** 
	 *ASSET LIST [asset action]
	 *Used to fetch assets based on the given parameters
	 */
	public function assetAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$typeid = $form['assettype'];
			$location = $form['location'];
			$region = $form['region'];
			$status = $form['status'];
		}else{
			$typeid = '-1';
			$location='-1';
			$region='-1';
			$status='-1';
		}	
		$data = array(
		    'typeid'=>$typeid,
			'region' => $region,
			'location' => $location,
            'status' => $status,			
		);
		$assetmgtTable = $this->getDefinedTable(Asset\AssetmanagementTable::class)->getbyassettype($data['typeid'],$data['location'],$data['status']);
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($assetmgtTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10000);
		$paginator->setPageRange(8);
		return new ViewModel(array(
			'title'        => 'Assests Management',
			'paginator'    => $paginator,
			'page'         => $page,
			'data'         => $data,
			'assettypeObj' => $this->getDefinedTable(Asset\AssettypeTable::class),
			'regionObj'    => $this->getDefinedTable(Administration\RegionTable::class),
			'locationObj'  => $this->getDefinedTable(Administration\LocationTable::class),
			'statusObj'    => $this->getDefinedTable(Acl\StatusTable::class),
		));
	} 
	/**
	 * get location by class
	**/
	public function getlocationAction()
	{
		$this->init();
		$lc='';
		$form = $this->getRequest()->getPost();
		
		$region_id = $form['region'];
		//$region_id =1;
		$locations = $this->getDefinedTable(Administration\LocationTable::class)->get(array('region' => $region_id));
		
		$lc.="<option value=''></option>";
		foreach($locations as $loc):
			$lc.= "<option value='".$loc['id']."'>".$loc['location']."</option>";
		endforeach;
		echo json_encode(array(
			'location' => $lc,
		));
		exit;
	}
	public function getCustodianAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$loc_id = $form['loc_id'];
		
		$employee = $this->getDefinedTable(Hr\EmployeeTable::class)->get(array('e.location' => $loc_id));
		//echo '<pre>';print_r($employee);
		$emp="<option value=''></option>";
		foreach($employee as $employees):
			$emp.= "<option value='".$employees['id']."'>".$employees['full_name'].'-'.$employees['emp_id']."</option>";
		endforeach;
		echo json_encode(array(
			'emp' => $emp,
		));
		exit;
	}
	/** FOR ITEM RECEIPT WITHOUT PO / PURCHASE REQUISITION
	 * Get Item Uoms, Po_uom, Po_qty and balance_qty by item_id and po_id
	 */
	public function getheadAction()
	{
		$this->init();

		$form = $this->getRequest()->getPost();
		$item_id = $form['head_type'];

		$heads = $this->getDefinedTable(Accounts\HeadTable::class)->get(array('group' => $item_id));

		$hd ="<option value=''></option>";
		foreach($heads as $head):
			$hd .="<option value='".$head['id']."'>".$head['code']."</option>";
		endforeach;
		echo json_encode(array(
			'head' => $hd,
		));
		exit;
	}
	/**
	 *  addasset action
	 */
	public function addassetAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			/*CHECK IF THE YEAR IS LEAP AND COUNT THE LEAP IN BETWEEN*/
			$current_year=date('Y');
			$put_in_use_year = date('Y', strtotime($form['putin_date']));
			$usefullife = $form['usefullife'];
			$end_of_life = $put_in_use_year + $usefullife;
			
			$year_of_use= $current_year - $put_in_use_year;
			$leap = 0;
			for ($year = $put_in_use_year; $year <= $end_of_life; $year++) {
				if (($year % 4 == 0 && $year % 100 != 0) || ($year % 400 == 0)) {
					$leap++;
				}
			}
			/*TOTAL USEFULLIFE IN DAYS*/
			$total_days_with_leap = $leap * 366;
			$regular_year = $usefullife - $leap;
			$total_days_with_regular_year = $regular_year * 365;
			$total_useful_days = $total_days_with_leap + $total_days_with_regular_year;
			/*TOTAL USEFULLIFE IN COMPLETED*/
			$startDate = $form['putin_date'];
			$currentDate = date("Y-m-d");
			
			/*DAY FROM USE UNTIL TODAY*/
			$timestampStartDate = strtotime($startDate);
			$timestampEndDate = strtotime($currentDate);
			if ($timestampStartDate === false || $timestampEndDate === false) {
			} else {
				$timeDifference = $timestampEndDate - $timestampStartDate;
				$daysCompletedtilltoday = floor($timeDifference / (60 * 60 * 24));
			}
			
			//echo '<pre>';print_r($timeDifference);
			//echo '<pre>';print_r($daysCompletedtilltoday);exit;
			/*DEPRECIATION AMOUNT PER DAY*/
			$asset_value = $form['asset_value'];
			$actual_asset_value = (float)str_replace(',', '', $asset_value);
			$salvage_value = $form['salvage'];
			$depreciation = $form['depreciation'];
			if($depreciation > 0){
				$depreciation_per_day = ($actual_asset_value - $salvage_value) / $total_useful_days;
				$dep_per_day = round($depreciation_per_day,2);
			}else{
				$dep_per_day = 0.00;
				$salvage_value = 0.00;
				$usefullife = 0.00;
			}
			$depreciation_amount = $form['dep_amount'];
			//echo '<pre>';print_r($dep_per_day);exit;
            $data = array(  
			    'assetid' => $form['assetid'],
				'asset_type' => $form['asset_type'],
				'code' => $form['code'],
				'name' => $form['name'],
				'purchase_date' => $form['purchase_date'],
				'putin_date' => $form['putin_date'],
				'asset_value' => str_replace(',', '', $form['asset_value']),
				'depreciation' => $form['depreciation'],
				'dep_per_day' => (isset($dep_per_day))? $dep_per_day:'0.00',
				'salvage' =>(isset($salvage_value))? $salvage_value:'0.00',
				'usefullife' =>(isset($usefullife))? $usefullife:'0.000',
				'region' => $form['region'],
				'location' => $form['location'],
				'custodian' => $form['custodian'],
				'previous_depreciated_amount' => $form['previous_depreciated_amount'],
				'depreciable_amount' => $depreciation_amount,
				//'depreciation_date' => $this->_modified,
				'status' =>1,
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
            );
			//echo '<pre>';print_r($data);exit;
            $data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
            $result = $this->getDefinedTable(Asset\AssetmanagementTable::class)->save($data);
              
            if($result > 0):
				/*$head= $form['head'];
				$des= $form['des'];
				for($i=0; $i < sizeof($head); $i++):
					if(isset($head[$i]) && is_numeric($head[$i])):
						$subheaddata = array(
							'head' => $head[$i],
							'type' => 1,
							'ref_id' => $result,
							'code' => $form['code'],
							'name' => $form['name'],
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$subheaddata = $this->_safedataObj->rteSafe($subheaddata);
						$this->getDefinedTable(Accounts\SubheadTable::class)->save($subheaddata);
					endif;
				endfor;*/
				$this->_connection->commit(); // commit transaction on success
                $this->flashMessenger()->addMessage("success^ New asset successfully added");
            else:
				$this->_connection->rollback(); // rollback transaction over failure
                $this->flashMessenger()->addMessage("Failed^ Failed to add new asset");
            endif;
            return $this->redirect()->toRoute('assets', array('action'=>'asset'));
        }
		return new ViewModel(array(
			'title'  => 'Add assest',
			'rowset' => $this->getDefinedTable(Asset\AssetmanagementTable::class)->getAll(),
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'employees' => $this->getDefinedTable(HR\EmployeeTable::class)->getAllEmp(),
			'assettypes' => $this->getDefinedTable(Asset\AssettypeTable::class)->getAll(),
		));
		
	}
	
	/**
	 *  function/action to edit asset
	 */
     public function editassetAction()
    {
        $this->init();
        if($this->getRequest()->isPost())
        {
			$form=$this->getRequest()->getPost();
			/*CHECK IF THE YEAR IS LEAP AND COUNT THE LEAP IN BETWEEN*/
			// $current_year=date('Y');
			// $put_in_use_year = date('Y', strtotime($form['putin_date']));
			// $usefullife = $form['usefullife'];
			// $end_of_life = $put_in_use_year + $usefullife;
			
			// $year_of_use= $current_year - $put_in_use_year;
			// $leap = 0;
			// for ($year = $put_in_use_year; $year <= $end_of_life; $year++) {
			// 	if (($year % 4 == 0 && $year % 100 != 0) || ($year % 400 == 0)) {
			// 		$leap++;
			// 	}
			// }
			// /*TOTAL USEFULLIFE IN DAYS*/
			// $total_days_with_leap = $leap * 366;
			// $regular_year = $usefullife - $leap;
			// $total_days_with_regular_year = $regular_year * 365;
			// $total_useful_days = $total_days_with_leap + $total_days_with_regular_year;
			// /*TOTAL USEFULLIFE IN COMPLETED*/
			// $startDate = $form['putin_date'];
			// $currentDate = date("Y-m-d");
			
			// /*DAY FROM USE UNTIL TODAY*/
			// $timestampStartDate = strtotime($startDate);
			// $timestampEndDate = strtotime($currentDate);
			// if ($timestampStartDate === false || $timestampEndDate === false) {
			// } else {
			// 	$timeDifference = $timestampEndDate - $timestampStartDate;
			// 	$daysCompletedtilltoday = floor($timeDifference / (60 * 60 * 24));
			// }
			
			//echo '<pre>';print_r($timeDifference);
			//echo '<pre>';print_r($daysCompletedtilltoday);exit;
			/*DEPRECIATION AMOUNT PER DAY*/
			$total_useful_days=$form['usefullife']*365;
			$asset_value = $form['asset_value'];
			$actual_asset_value = (float)str_replace(',', '', $asset_value);
			$salvage_value = $form['salvage'];
			$depreciation = $form['depreciation'];
			if($depreciation > 0){
				$depreciation_per_day = $form['dep_amount'] / $total_useful_days;
				$dep_per_day = round($depreciation_per_day,2);
			}else{
				$dep_per_day = 0.00;
				$salvage_value = 0.00;
				$usefullife = 0.00;
			}
			//print_r($dep_per_day);exit;
			$depreciation_amount = $form['dep_amount'];
            $data=array(
				'id' => $this->_id,
				'assetid' => $form['assetid'],
				'asset_type' => $form['asset_type'],
				'code' => $form['code'],
				'name' => $form['name'],
				'purchase_date' => $form['purchase_date'],
				'putin_date' => $form['putin_date'],
				'asset_value' => str_replace(',', '', $form['asset_value']),
				'depreciation' => $form['depreciation'],
				'dep_per_day' => (isset($dep_per_day))? $dep_per_day:'0.00',
				'salvage' =>(isset($form['salvage']))? $form['salvage']:'0.00',
				'usefullife' =>(isset($form['usefullife']))? $form['usefullife']:'0.000',
				'region' => $form['region'],
				'location' => $form['location'],
				'custodian' => $form['custodian'],
				'depreciable_amount' => $form['dep_amount'],
				'net'				=>$form['dep_amount']-$form['previous_depreciated_amount'],
				'previous_depreciated_amount' => $form['previous_depreciated_amount'],
				'status' =>1,
				'author' =>$this->_author,
				'modified' =>$this->_modified,
            );
			//echo '<pre>';print_r($data);exit;
            $data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
            $result = $this->getDefinedTable(Asset\AssetmanagementTable::class)->save($data);
            if($result > 0):
				$this->_connection->commit(); // commit transaction on success
                $this->flashMessenger()->addMessage("success^ Asset successfully updated");
            else:
				$this->_connection->rollback(); // rollback transaction over failure
                $this->flashMessenger()->addMessage("Failed^ Failed to update asset");
            endif;
            return $this->redirect()->toRoute('assets', array('action'=>'asset'));
        }   
        $ViewModel = new ViewModel(array(
			'title' => 'Edit Asset',
			'asset' => $this->getDefinedTable(Asset\AssetmanagementTable::class)->get($this->_id),
			'locationObj'=>$this->getdefinedTable(Administration\LocationTable::class),
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'employees' => $this->getDefinedTable(HR\EmployeeTable::class)->getAllEmp(),
			'empObj' => $this->getDefinedTable(HR\EmployeeTable::class),
			'assettypes' => $this->getDefinedTable(Asset\AssettypeTable::class)->getAll(),
	    ));
	
	$ViewModel->setTerminal(False);
	return $ViewModel;
       
    }
	/**
	 *  VIEW ASSET action
	 */
	public function viewassetAction()
	{
		$this->init();
		$asset_id = $this->_id;
		return new ViewModel(array(
			'title'   => 'View Asset',
			'assets' => $this->getDefinedTable(Asset\AssetmanagementTable::class)->get($asset_id),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),   
			'employeeObj' =>$this->getDefinedTable(HR\EmployeeTable::class),
			'assettypeObj' =>$this->getDefinedTable(Asset\AssettypeTable::class),
			'userObj'        =>$this->getDefinedTable(Administration\UsersTable::class),
			'assethistory' => $this->getDefinedTable(Asset\AssethistoryTable::class)->get(array('asset_id'=>$asset_id)),
		));
	}
	/**
	 *  ASSET DISPOSE action
	 */
	public function disposalAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			//$disposal_amount = sprintf("%.2f", $form['disposal_amount']);
			 $data=array(
				'id' 				=> $this->_id,
				'disposal_amount' 	=>$form['disposal_amount'],
				'disposal_year'		=> date('y',strtotime($form['disposal_date'])),
				'disposal_date'		=> $form['disposal_date'],
				'status' 			=>19,
				'modified'			=>$this->_modified,
            );
			//echo '<pre>';print_r($data);exit;
			$asset_type =$this->getDefinedTable(Asset\AssetmanagementTable::class)->getColumn($this->_id,'asset_type');
			$asset_subhead = $this->getDefinedTable(Asset\AssettypeTable::class)->getColumn($asset_type,'subhead');
			$asset_head = $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($asset_subhead,'head');
			//echo '<pre>';print_r($asset_head);
			//echo '<pre>';print_r($asset_subhead);
			//echo '<pre>';print_r($asset_type);exit;
            $data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
            $result = $this->getDefinedTable(Asset\AssetmanagementTable::class)->save($data);
			if($result > 0):
			    //generate voucher no
				$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(24,'prefix');
				$date = date('ym',strtotime($form['disposal_date']));
				$tmp_VCNo = $loc.'-'.$prefix.$date;
				
				 $results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
				
				$pltp_no_list = array();
				foreach($results as $result):
					array_push($pltp_no_list, substr($result['voucher_no'], -4));
				endforeach;
				$next_serial = max($pltp_no_list) + 1; 
					
				switch(strlen($next_serial)){
					case 1: $next_dc_serial = "0000".$next_serial; break;
					case 2: $next_dc_serial = "000".$next_serial;  break;
					case 3: $next_dc_serial = "00".$next_serial;   break;
					case 4: $next_dc_serial = "0".$next_serial;    break;
					default: $next_dc_serial = $next_serial;       break;
				}	
				$voucher_no = $tmp_VCNo.$next_dc_serial;
				$region =$this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'region');
				$data1 = array(
					'voucher_date' =>$form['disposal_date'],
					'voucher_type' => 24,
					'region'   =>$region,
					'doc_id' => 'Asset Disposal',
					'doc_type' => '',
					'voucher_no' => $voucher_no,
					'cheque_no' => '',
					'voucher_amount' => str_replace( ",", "",$form['disposal_amount']),
					'remark' => 'Disposal of Asset',
					'status' => 4, // status initiated 
					'author' =>$this->_author,
					'created' =>$this->_created,  
					'modified' =>$this->_modified,
				);
				$data = $this->_safedataObj->rteSafe($data);
                $result1 = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
				$location =$this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'id');
				/*EXPENSE*/
				$head = 212;
				$sub_head =2927;
				if($result1 > 0):
				    /*EXPENSE HIT ON DISPOSE*/
				    $curent_asset_net_value = array(
						'transaction' => $result1,
						'voucher_dates' => $form['disposal_date'],
						'voucher_types' =>24,
						'location' => $location,
						'activity' => $location,
						'head' => $head,//Other General and admin expense
						'sub_head' => $sub_head,//loss on the sale of asset
						'bank_ref_type' => '',
						'cheque_no' => "",
						'debit' => $form['disposal_amount'],//current Net value of the asset
						'credit' =>'0.000',
						'ref_no'=> '', 
						'type' => '1',//user inputted  data
						'status' => 4, // status initiated
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$inventories_out_asset = array(
						'transaction' => $result1,
						'voucher_dates' =>$form['disposal_date'],
						'voucher_types' =>24,
						'location' => $location,
						'activity' => $location,
						'head' => $asset_head,//Subtract on the inventory
						'sub_head' => $asset_subhead,//Subtract on the inventory
						'bank_ref_type' => '',
						'cheque_no' => '',
						'debit' =>'0.000',
						'credit' => $form['disposal_amount'],//asset value of the purchase
						'ref_no'=> '', 
						'type' => '1',//user inputted  data
						'status' => 4, // status initiated
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					//echo '<pre>';print_r($expensedata);
					//echo '<pre>';print_r($inventoriesdata);exit;
					$curent_asset_net_value = $this->_safedataObj->rteSafe($curent_asset_net_value);
					$inventories_out_asset = $this->_safedataObj->rteSafe($inventories_out_asset);
					$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($curent_asset_net_value);
					$result4 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($inventories_out_asset); 
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ Asset successfully Disposed");
				 endif;
            else:
				$this->_connection->rollback(); // rollback transaction over failure
                $this->flashMessenger()->addMessage("Failed^ Failed Dispose");
            endif;
            return $this->redirect()->toRoute('assets', array('action'=>'asset'));
        }   
		$ViewModel = new ViewModel(array(
			'title'       => 'Assests',
			'assets'      =>$this->getDefinedTable(Asset\AssetmanagementTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	} 
	/**
	 *  ASSET TRANSFER 
	 */
	public function transferAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$currentdata=$this->getDefinedTable(Asset\AssetmanagementTable::class)->get($this->_id);
			if($form['transfer']==0){
				$status=22;
			}
			else{
				$status=1;
			}
			$data = array(
				'id' 			=> $this->_id,
				'region' 		=> $form['region'],
				'location' 		=> $form['location'],
				'custodian' 	=> $form['custodian'],
				'status'		=> $status,
				'modified' 		=>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Asset\AssetmanagementTable::class)->save($data);
			if($result > 0):
				foreach ($currentdata as $asthistory);
				$assethistory = array(
					'asset_id' => $asthistory['id'],
					'his_region' => $asthistory['region_id'],
					'his_location' => $asthistory['location_id'],
					'his_custodian' => $asthistory['custodian'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);
				$assethistory = $this->_safedataObj->rteSafe($assethistory);
				$result = $this->getDefinedTable(Asset\AssethistoryTable::class)->save($assethistory);
			    $this->flashMessenger()->addMessage("success^ Asset Transfered");
			else:
			    $this->flashMessenger()->addMessage("Failed^ Failed to Transfered");
			endif;
			return $this->redirect()->toRoute('assets', array('action'=>'viewasset','id'=>$this->_id));
		}
		$ViewModel = new ViewModel(array(
			'title'          => 'Assests Transfer',
			'assets'         =>$this->getDefinedTable(Asset\AssetmanagementTable::class)->get($this->_id),
			'regionObj'      =>$this->getDefinedTable(Administration\RegionTable::class),
			'locationObj'      =>$this->getDefinedTable(Administration\LocationTable::class),
			'employeeObj'    =>$this->getDefinedTable(Hr\EmployeeTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *  ---------------------ASSET SCHEDULE ACTION------------------------------------------------
	 */
	public function scheduleAction()
	{
		$this->init();
		
		return new ViewModel(array(
			'title'       => 'Assets Schedule',
			'assetObj' =>$this->getDefinedTable(Asset\AssetTable::class),
		));
	} 
	/** 
	 *Depreciate Asset action
	 */
	public function depreciateAction()
	{
		 $this->init();
        if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
		}else{
			$year = date('Y');
			$month = (int)date('m');
        }
        return new ViewModel(array( 
            'title' => 'Depreciate Asset',
            'year' =>$year,
            'month' =>$month,
            'role'=>$this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'role'),
            'depreciation' => $this->getDefinedTable(Asset\DepreciationTable::class)->getDep($year),
			'depObj' => $this->getDefinedTable(Asset\DepreciationTable::class),
    
        ));
	}
	 public function generatedepAction()
    {
        $this->init();
        $my = explode('-', $this->_id);
		$last_day = date('t', strtotime("$my[0]-$my[1]"));
        $asset = $this->getDefinedTable(Asset\AssetmanagementTable::class)->get(array('a.depreciation'=>1,'a.status'=>[1,22]));
		
		//echo '<pre>';print_r($asset);exit;
		//$count=1;	
		foreach($asset as $row):
			$year = date('Y',strtotime($row['putin_date']));
			$month = date('m',strtotime($row['putin_date']));
			if($year==$my[0] && $my[1]==$month){
				$day=date('d',strtotime($row['putin_date']));
				$dep_amount=$row['dep_per_day']*($last_day-($day-1));
			}
			else{$dep_amount=$row['dep_per_day']*$last_day;}
			
			$net_after_dep=$row['net']-$dep_amount;
			if($net_after_dep<1){
				$dep_amount=$row['net']-1;
			}
			
			if($row['net']>1 && $row['asset_type']!=16){
				if(($year==$my[0] && $my[1]>=$month) || $year<$my[0]){
					$data = array(
						'asset' 		=> $row['id'],
						'location' 		=> $row['location_id'],
						'type' 			=> $row['asset_type'],
						'year' 			=> $my[0],
						'month' 		=> $my[1],
						'ref_no' 		=> $my[1].",".$my[0],
						'dep_amount' 	=> $dep_amount,
						'net'			=> $row['net'],
						'status' 		=> 2,
						'author' 		=> $this->_author,
						'created' 		=> $this->_created,
						'modified' 		=> $this->_modified,
					);
					//echo '<pre>';print_r($data);
					//$count++;
					$result = $this->getDefinedTable(Asset\DepreciationTable::class)->save($data);
				}
			}

			endforeach;
	//	print_r($count);exit;
            if ($result) {
                $this->flashMessenger()->addMessage("success^ Action Successful");
            } else {

                $this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
            }
            return $this->redirect()->toRoute('assets',array('action' => 'depreciate'));
    }
	 //edit building
	 public function viewdepreciateAction()
	 {
		 $this->init();
		 if(isset($this->_id) & $this->_id!=0):
			 $my = explode('-', $this->_id);
		 endif;
		 if(sizeof($my)==0):
			 $my = array('1'); //default selection
		 endif;
			 if($my[2]=="" ){
				 $my[2]='-1';
			 }else{
				 $my[2] = $my[2];
			 }
			//  if($my[3]=="" ){
			// 	$my[3]='-1';
			// }else{
			// 	$my[3] = $my[3];
			// }

		 
		//print_r($locationlist);exit;
		 return new ViewModel(array(
			 'title' 				=> 'Details',
			 'asset' 				=> $this->getDefinedTable(Asset\DepreciationTable::class)->getDepByMonth($my[0],$my[1],$my[2]),
			 'month'				=> $my[1],
			 'year'					=> $my[0],
			 //'location'          	=> $my[2],
			 'asttype'          	=> $my[2],
			 'locationObj' 			=> $this->getDefinedTable(Administration\LocationTable::class),
			 'typeObj' 				=> $this->getDefinedTable(Asset\AssettypeTable::class),
			 'assetObj' 			=> $this->getDefinedTable(Asset\AssetmanagementTable::class),
			 'assettype' 			=> $this->getDefinedTable(Asset\AssettypeTable::class)->getAll(),
			 'party' 				=> $this->getDefinedTable(Accounts\PartyTable::class),
	   
		 ));
	 }
	 /**
	 * Submit Depreciation action
	 */
	public function submitAction()
	{
		$this->init(); 
		$my = explode('-', $this->_id);
		$assets=$this->getDefinedTable(Asset\DepreciationTable::Class)->getDepByMonth($my[0],$my[1],'-1');
		$this->_connection->beginTransaction();
		foreach($assets as $ast){
			$data = array(
				'id'		=> $ast['id'],
				'status' 	=> 4,
				'author' 	=> $this->_author,
				'created' 	=> $this->_created,
				'modified' 	=> $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Asset\DepreciationTable::class)->save($data);
			$assetdet= $this->getDefinedTable(Asset\AssetmanagementTable::class)->get($ast['asset']);
			foreach($assetdet as $ad);
			$data1 = array(
				'id'				=> $ast['asset'],
				'net'				=> $ad['net']-$ast['dep_amount'],
				'acc_depreciation'	=> $ad['acc_depreciation']+$ast['dep_amount'],
				'author' 			=> $this->_author,
				'modified' 			=> $this->_modified,
			);
		
			$result1 = $this->getDefinedTable(Asset\AssetmanagementTable::class)->save($data1);
		}
		$types=$this->getDefinedTable(Asset\DepreciationTable::Class)->getDistinct('type',array('year'=>$my[0],'month'=>$my[1]));
		//echo '<pre>';print_r($types);exit;	
		$day=date('d');
		$dep_date= $my[0].'-'.$my[1].'-'.$day;
		foreach($types as $type){
			$location=286;
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($location, 'prefix');
			$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(19,'prefix');
			$date = date('ym',strtotime(date('Y-m-d')));
			$tmp_VCNo = $loc.'-'.$prefix.$date;
			
			$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
			
			$pltp_no_list = array();
			foreach($results as $result):
				array_push($pltp_no_list, substr($result['voucher_no'], -3));
			endforeach;
			$next_serial = $pltp_no_list ? max($pltp_no_list) + 1 : 1;
				
			switch(strlen($next_serial)){
				case 1: $next_dc_serial = "0000".$next_serial; break;
				case 2: $next_dc_serial = "000".$next_serial;  break;
				case 3: $next_dc_serial = "00".$next_serial;   break;
				case 4: $next_dc_serial = "0".$next_serial;    break;
				default: $next_dc_serial = $next_serial;       break;
			}	
			$voucher_no = $tmp_VCNo.$next_dc_serial;
			$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($location,'region');

			$asset_subhead=$this->getDefinedTable(Asset\AssettypeTable::class)->getColumn($type['column'],'subhead');
			$exp_subhead=$this->getDefinedTable(Asset\AssettypeTable::class)->getColumn($type['column'],'dep_subhead');
			$amount=$this->getDefinedTable(Asset\DepreciationTable::Class)->getSum(array('year'=>$my[0],'month'=>$my[1],'type'=>$type['column']),'dep_amount');
			$trans = array(
					'voucher_date' =>$dep_date,
					'voucher_type' => 19,
					'region'   =>$region,
					'doc_id'   =>"Depreciation",
					'voucher_no' => $voucher_no,
					'voucher_amount' => str_replace( ",", "",$amount),
					'status' => 4, // status initiated 
					'author' =>$this->_author,
					'created' =>$this->_created,  
					'modified' =>$this->_modified,
				);
				$resultt = $this->getDefinedTable(Accounts\TransactionTable::class)->save($trans);
				
				if($resultt):
				$tdetailsdata = array(
					'transaction' => $resultt,
					'voucher_dates' =>$dep_date,
					'voucher_types' => 19,
					'location' => $location,
					'head' =>$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($asset_subhead,'head'),
					'sub_head' =>$asset_subhead,
					'bank_ref_type' => '',
					'debit' =>'0.000',
					'credit' =>$amount,
					'ref_no'=> '', 
					'type' => '1',//user inputted  data
					'status' => 4, // status initiated
					'activity'=>$location,
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);
				$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
			
				$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
				$tdetailsdata = array(
					'transaction' => $resultt,
					'voucher_dates' => $dep_date,
					'voucher_types' => 19,
					'location' => $location,
					'head' =>$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($exp_subhead,'head'),
					'sub_head' =>$exp_subhead,
					'bank_ref_type' => '',
					'debit' =>$amount,
					'credit' =>'0.000',
					'against' =>'0',
					'ref_no'=> '', 
					'type' => '1',//user inputted  data
					'status' => 4, // status initiated
					'activity'=>$location,
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);
				$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
				$result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
				endif;
			
		}
			//echo '<pre>';print_r($trans);exit;	
		
		if($result):
				$this->_connection->commit();
				$this->flashMessenger()->addMessage("success^ Depreciation submition successful");
			else:
			$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to Submit Depreciation");
			endif;
			//end			
		
			return $this->redirect()->toRoute('assets',array('action' => 'viewdepreciate','id'=>$this->_id.'-1'));	
	}

	//edit depreciated amount for Grants
	public function editdepreciateAction()
    {
		$this->init(); 
		if ($this->getRequest()->isPost()) {
            $form = $this->getRequest()->getPost();
            $data = array(
				'id'		=> $this->_id,
                'dep_amount' => $form['dep_amount'],
                'author' 	=> $this->_author,
                'created' 	=> $this->_created,
                'modified' 	=> $this->_modified,
            );
            $data = $this->_safedataObj->rteSafe($data);
            $result = $this->getDefinedTable(Asset\DepreciationTable::class)->save($data);
            if ($result) {
                $this->flashMessenger()->addMessage("success^ Action Successful");
            } else {

                $this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
            }
           
            return $this->redirect()->toRoute('assets',array('action' => 'viewdepreciate','id' => $form['year']."-".$form['month'].'--1' ));
            
        }
      
       //$bid=$this->getDefinedTable(Realestate\BuildingTable::class)->getColumn($this->_id,'building_id');
        $ViewModel = new ViewModel([
            'title' => 'Edit Depreciated Amount Details.',
            'depreciate' => $this->getDefinedTable(Asset\DepreciationTable::class)->get($this->_id),
        
        
        ]);
		$ViewModel->setTerminal(True);
		return $ViewModel;
    }
	/**
	 *  ASSET Write Off action
	 */
	public function writeoffAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			//$disposal_amount = sprintf("%.2f", $form['disposal_amount']);
			 $data=array(
				'id' 				=> $this->_id,
				'writeoff_amount' 	=>$form['writeoff_amount'],
				'writeoff_year'		=> date('y',strtotime($form['write_off_date'])),
				'writeoff_date'		=> $form['write_off_date'],
				'status' 			=>23,
				'modified'			=>$this->_modified,
            );
			//echo '<pre>';print_r($data);exit;
			$asset_type =$this->getDefinedTable(Asset\AssetmanagementTable::class)->getColumn($this->_id,'asset_type');
			$asset_subhead = $this->getDefinedTable(Asset\AssettypeTable::class)->getColumn($asset_type,'subhead');
			$asset_head = $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($asset_subhead,'head');
			//echo '<pre>';print_r($asset_head);
			//echo '<pre>';print_r($asset_subhead);
			//echo '<pre>';print_r($asset_type);exit;
            $data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
            $result = $this->getDefinedTable(Asset\AssetmanagementTable::class)->save($data);
			if($result > 0):
			    //generate voucher no
				$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(26,'prefix');
				$date = date('ym',strtotime($form['write_off_date']));
				$tmp_VCNo = $loc.'-'.$prefix.$date;
				
				 $results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
				
				$pltp_no_list = array();
				foreach($results as $result):
					array_push($pltp_no_list, substr($result['voucher_no'], -4));
				endforeach;
				$next_serial = max($pltp_no_list) + 1; 
					
				switch(strlen($next_serial)){
					case 1: $next_dc_serial = "0000".$next_serial; break;
					case 2: $next_dc_serial = "000".$next_serial;  break;
					case 3: $next_dc_serial = "00".$next_serial;   break;
					case 4: $next_dc_serial = "0".$next_serial;    break;
					default: $next_dc_serial = $next_serial;       break;
				}	
				$voucher_no = $tmp_VCNo.$next_dc_serial;
				$region =$this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'region');
				$data1 = array(
					'voucher_date' =>$form['write_off_date'],
					'voucher_type' => 24,
					'region'   =>$region,
					'doc_id' => 'Asset Write Off',
					'doc_type' => '',
					'voucher_no' => $voucher_no,
					'cheque_no' => '',
					'voucher_amount' => str_replace( ",", "",$form['writeoff_amount']),
					'remark' => 'Write Off of Asset',
					'status' => 4, // status initiated 
					'author' =>$this->_author,
					'created' =>$this->_created,  
					'modified' =>$this->_modified,
				);
				$data = $this->_safedataObj->rteSafe($data);
                $result1 = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
				$location =$this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'id');
				/*EXPENSE*/
				$head = 212;
				$sub_head =1713;
				if($result1 > 0):
				    /*EXPENSE HIT ON DISPOSE*/
				    $curent_asset_net_value = array(
						'transaction' => $result1,
						'voucher_dates' => $form['write_off_date'],
						'voucher_types' =>24,
						'location' => $location,
						'activity' => $location,
						'head' => $head,//Other General and admin expense
						'sub_head' => $sub_head,//loss on the sale of asset
						'bank_ref_type' => '',
						'cheque_no' => "",
						'debit' => $form['writeoff_amount'],//current Net value of the asset
						'credit' =>'0.000',
						'ref_no'=> '', 
						'type' => '1',//user inputted  data
						'status' => 4, // status initiated
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$inventories_out_asset = array(
						'transaction' => $result1,
						'voucher_dates' =>$form['write_off_date'],
						'voucher_types' =>24,
						'location' => $location,
						'activity' => $location,
						'head' => $asset_head,//Subtract on the inventory
						'sub_head' => $asset_subhead,//Subtract on the inventory
						'bank_ref_type' => '',
						'cheque_no' => '',
						'debit' =>'0.000',
						'credit' => $form['writeoff_amount'],//asset value of the purchase
						'ref_no'=> '', 
						'type' => '1',//user inputted  data
						'status' => 4, // status initiated
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					//echo '<pre>';print_r($expensedata);
					//echo '<pre>';print_r($inventoriesdata);exit;
					$curent_asset_net_value = $this->_safedataObj->rteSafe($curent_asset_net_value);
					$inventories_out_asset = $this->_safedataObj->rteSafe($inventories_out_asset);
					$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($curent_asset_net_value);
					$result4 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($inventories_out_asset); 
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ Asset successfully Disposed");
				 endif;
            else:
				$this->_connection->rollback(); // rollback transaction over failure
                $this->flashMessenger()->addMessage("Failed^ Failed Dispose");
            endif;
            return $this->redirect()->toRoute('assets', array('action'=>'asset'));
        }   
		$ViewModel = new ViewModel(array(
			'title'       => 'Assests',
			'assets'      =>$this->getDefinedTable(Asset\AssetmanagementTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	} 
	public function getemployeeAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$loc = $form['loc'];
		$employee = $this->getDefinedTable(Hr\EmployeeTable::class)->get(array('e.location' => $loc));
		
		$emplist="<option value=''></option>";
		foreach($employee as $emp):
			$emplist.= "<option value='".$emp['id']."'>".$emp['full_name'].'-'.$emp['emp_id']."</option>";
		endforeach;
		echo json_encode(array(
			'emp' => $emplist,
		));
		exit;
	}
	
}

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
class IssueController extends AbstractActionController
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
		$this->_id = $this->params()->fromRoute('id');		
	    $this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');		
		//$this->_dir =realpath($fileManagerDir);
		//$this->_safedataObj =  $this->SafeDataPlugin();
		$this->_userloc = $this->_user->location;  
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
	/**
	 * index of the Batch Controller
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
	    $subRoles = $this->getDefinedTable(Acl\UserroleTable::class)->get(array('user'=>$this->_author, 'subrole'=>array('1','3','15')));  
		if(sizeof($subRoles) > 0){ $role_flag = true; } else{ $role_flag = false; }
		$admin_locs = $this->getDefinedTable(Acl\UsersTable::class)->getColumn($this->_author,'location');
		$admin_loc_array = explode(',',$admin_locs);
		$issues = $this->getDefinedTable(Store\IssueTable::class)->getDateWise('issue_date',$year,$month,$this->_userloc, $admin_loc_array, $role_flag);
		return new ViewModel( array(
				'title' => "Goods Issue",
				'issues' => $issues,
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'minYear' => $this->getDefinedTable(Store\IssueTable::class)->getMin('issue_date'),
				'data' => $data,
				'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
				'groupObj' => $this->getDefinedTable(Store\GroupTable::class),
		) );
	}
	
	/**
	 * Add Issue
	 */
	public function addissueAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$request= $this->getRequest();
			$form = $request->getPost();
             
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['from_location'],'prefix');
			$date = date('ym',strtotime($form['issue_date']));
			$tmp_INo = $location_prefix."DC".$date;
			$results = $this->getDefinedTable(Store\IssueTable::class)->getMonthlyINO($tmp_DCNo);
			$dc_no_list = array();
			foreach($results as $result):
				array_push($dc_no_list, substr($result['challan_no'], 8));
			endforeach;
			$next_serial = max($dc_no_list) + 1;
				
			switch(strlen($next_serial)){
				case 1: $next_dc_serial = "000".$next_serial; break;
				case 2: $next_dc_serial = "00".$next_serial;  break;
				case 3: $next_dc_serial = "0".$next_serial;   break;
				default: $next_dc_serial = $next_serial;       break;
			}	
			$challan_no = $tmp_INo.$next_dc_serial;
			$transporter = ($form['fcb_transport'] == 1)?$form['transporter_fcb']:$form['transporter_nonfcb'];
			$data =array(
					'issue_date'    => $form['issue_date'],
					'challan_no'    => $challan_no,
					'from_location' => $form['from_location'],
					'to_location'   => $form['to_location'],
					'cost_center'   => $form['activity'],
					'item_group'    => $form['item_group'],
					'fcb_transport' => $form['fcb_transport'],
					'transporter' => $transporter,
					'vehicle_no' => $form['vehicle_no'],
					'party' => $form['party'],
					'total_amount' => $form['payable_amount'],
					'note' => $form['note'],
					'status' => 1,
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified
			);
			$data = $this->_safedataObj->rteSafe($data);
			//echo "<pre>";print_r($data); exit;
			$result = $this->getDefinedTable(Store\IssueTable::class)->save($data);
			
			if($result>0):
				$item= $form['item'];
				$assetssp= $form['assetssp'];
				$from_balance = $form['from_balance'];
				$rate= $form['rate'];
				$quantity= $form['quantity'];
				$uom= $form['uom'];
				$amount= $form['amount'];
				$remarks = $form['remarks'];
				for($i=0; $i < sizeof($item); $i++):
					if(isset($item[$i]) && is_numeric($quantity[$i])):
						$issue_detail_data = array(
							'issue' => $result,
							'item' => $item[$i],
							'assetssp' => $assetssp[$i],
							'uom' => $uom[$i],
							'quantity' => $quantity[$i],
							'rate' => $rate[$i],
							'amount' => $amount[$i],
							'remarks' => $remarks[$i],
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						//echo "<pre>";print_r($issue_detail_data); exit;
						$issue_detail_data = $this->_safedataObj->rteSafe($issue_detail_data);
						$this->getDefinedTable(Store\IssueDetailsTable::class)->save($issue_detail_data);
					endif;
				endfor;
				$this->flashMessenger()->addMessage("success^ Successfully added new issue :".$challan_no);
				return $this->redirect()->toRoute('inissue',array('action'=>'viewissue','id'=>$result));
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to add new issue");
				return $this->redirect()->toRoute('inissue');
			endif;
		endif;
		$admin_locs = $this->getDefinedTable(Acl\UsersTable::class)->getColumn($this->_author,'admin_location');
        $admin_loc_array = explode(',',$admin_locs);
		return new ViewModel( array(
				'title' 		=> "Add Issue",
				'user_location' => $this->_userloc,
				'regionObj'     => $this->getDefinedTable(Administration\RegionTable::class),
				'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
				'admin_locs' 	=> $this->getDefinedTable(Administration\LocationTable::class)->get(array('l.id'=>$admin_loc_array)),
				'activities'  	=> $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'itemgroupObj'  	=> $this->getDefinedTable(Store\GroupTable::class),
				'parties'   	=> $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>4)),
				'employee_driver' => $this->getDefinedTable(Hr\EmployeeTable::class)->getEmployee(array('e.position_title'=>array(26,30,34,38,39,25))),
				'user_location' => $this->_userloc,
				'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
				'userID' => $this->_author,
		));
	}
	
	/**
	 * get items according to item group
	 */
	public function getitemgroupAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$source_loc = $form['source_loc'];
		$item_group_id = $form['item_group'];
		
		$items .="<option value=''></option>";
		if($item_group_id == 1):
			$assetitems = $this->getDefinedTable(Store\AssetTable::class)->getIssueItems(array('a.status'=>'3','ad.location'=>$source_loc,'i.item_group'=>$item_group_id));
		    foreach($assetitems as $assetitem):
			    $items .="<option value='".$assetitem['item_id']."'>".$assetitem['name']."</option>";
		    endforeach;
			echo json_encode(array(
			'items' => $items,
		));
		else:	
			$sspitems = $this->getDefinedTable(Store\StoreSpareTable::class)->getIssueItems(array('ssp.status'=>'3','sspd.location'=>$source_loc,'i.item_group'=>$item_group_id));
		    foreach($sspitems as $sspitem):
				$items .="<option value='".$sspitem['item_id']."'>".$sspitem['name']."</option>";
			endforeach;
		    echo json_encode(array(
			'items' => $items,
		));
		endif;
		exit;
	}
	
	/**
	 * get details of issue
	 */
	public function getdetailsAction()
	{
		$this->init();		
		$form = $this->getRequest()->getpost();		
		$item_id = $form['item_id'];
		$source_loc = $form['source_loc'];
		
		$selected_items = $this->getDefinedTable(Store\ItemTable::class)->get($item_id);
		foreach($selected_items as $item);	
			
		$item_group = $item['item_group_id'];
		if($item_group == 1): //Assets 
		
		    $assetObj = $this->getDefinedTable(Store\AssetTable::class);		
		    $assets = $assetObj->getAsset(array('a.item'=>$item_id,'ad.location'=>$source_loc));
			$select_assets .="<option value=''></option>";
			$uom .="<option value=''></option>";
			foreach($assets as $asset):
				$select_assets .="<option value='".$asset['id']."'>".$asset['asset']."</option>";
			endforeach;
			echo json_encode(array(
				'assetssp' => $select_assets,
			));
			
		else:// Store and Spare
		    
			$sspObj = $this->getDefinedTable(Store\StoreSpareTable::class);		
		    $storespares = $sspObj->getStoreSpare(array('ssp.item'=>$item_id,'sspd.location'=>$source_loc));
			$select_storespares .="<option value=''></option>";
			$uom .="<option value=''></option>";
			foreach($storespares as $storespare):
				$select_storespares .="<option value='".$storespare['id']."'>".$storespare['storespare']."</option>";
			endforeach;
			echo json_encode(array(
				'assetssp' => $select_storespares,
			));
		
		endif;
		exit;
	}
	
	/**
	 * get Stock Balance from batch and moving_items
	 */
	public function getstockbalanceAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$assetssp_id = $form['assetssp_id'];
		$source_loc = $form['source_loc'];
		$destination_loc = $form['destination_loc'];
		$item_id = $form['item_id'];
		
		$selected_item = $this->getDefinedTable(Store\ItemTable::class)->get($item_id);
		foreach($selected_item as $item);
		
		$item_uoms = $this->getDefinedTable(Stock\UomTable::class)->get($item['st_uom_id']);	
        $uom .="<option value=''></option>";		
        foreach($item_uoms as $item_uom):
			$selected_uom .="<option value='".$item_uom['id']."'>".$item_uom['code']."</option>";
		endforeach;
		
		$item_group = $item['item_group_id'];
		
		if($item_group == 1)://Assets
		
			$assetdltObj = $this->getDefinedTable(Store\AssetDetailsTable::class);
			$uomObj = $this->getDefinedTable(Stock\UomTable::class);
			$source_qty = $assetdltObj->getColumn(array('asset'=>$assetssp_id,'location'=>$source_loc),'quantity');
			$destination_qty = $assetdltObj->getColumn(array('asset'=>$assetssp_id,'location'=>$destination_loc),'quantity');
			$rate = $assetdltObj->getColumn(array('asset'=>$assetssp_id,'location'=>$source_loc),'rate');
		
		else: //Store and Spare
		
			$sspdltObj = $this->getDefinedTable(Store\StoreSpareDetailsTable::class);
			$source_qty = $sspdltObj->getColumn(array('storespare'=>$assetssp_id,'location'=>$source_loc),'quantity');
			$destination_qty = $sspdltObj->getColumn(array('storespare'=>$assetssp_id,'location'=>$destination_loc),'quantity');
			$rate = $sspdltObj->getColumn(array('storespare'=>$assetssp_id,'location'=>$source_loc),'rate');

	    endif;
		
		$source_qty = (is_numeric($source_qty))?$source_qty:"0.00";
		$destination_qty = (is_numeric($destination_qty))?$destination_qty:"0.00";
		$rate = (is_numeric($rate))?$rate:"0.00";
		
		echo json_encode(array(
				'source_qty' => $source_qty,
				'destination_qty' => $destination_qty,
				'uom' => $selected_uom,
				'rate' => $rate,
		));
		exit;
	}
	/**
	 * View Goods Dispatch
	 */
	public function viewissueAction()
	{
		$this->init();		
		$params = explode("-", $this->_id);
		//echo "<pre>"; print_r($params); exit;
		if($params['1'] == '1' && $params['2'] > 0){
			$flag = $this->getDefinedTable(Acl\NotifyTable::class)->getColumn($params['2'], 'flag'); 
			if($flag == "0") {
				$notify = array('id' => $params['2'], 'flag'=>'1');
               	$this->getDefinedTable(Acl\NotifyTable::class)->save($notify); 	
			}				
		}		
		$issue_ID = $params['0'];
        //echo "<pre>"; print_r($issue_ID); exit;		
		return new ViewModel(array(
				'title' => 'View Goods Dispatch',
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
				'usersObj' => $this->getDefinedTable(Acl\UsersTable::class),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
				'assetObj' => $this->getDefinedTable(Store\AssetTable::class),
				'asset_detailsObj' => $this->getDefinedTable(Store\AssetDetailsTable::class),
				'issues' => $this->getDefinedTable(Store\IssueTable::class)->get($issue_ID),
				'issue_detailsObj' => $this->getDefinedTable(Store\IssueDetailsTable::class),	
				'ssp_detailsObj' => $this->getDefinedTable(Store\StoreSpareDetailsTable::class),				
				'itemObj' => $this->getDefinedTable(Store\ItemTable::class),
				'sspObj' => $this->getDefinedTable(Store\StoreSpareTable::class),
				'user_location' => $this->_userloc,
				'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
				'transactionObj'  => $this->getDefinedTable(Accounts\TransactionTable::class),
				'groupObj'  => $this->getDefinedTable(Store\GroupTable::class),
				'userID' => $this->_author,
		));
	}
	
	/**
	 * edit goods dispatch action
	 */
	public function editissueAction()
	{
		$this->init();	
		$issue_ID = $this->_id;
		if($this->getRequest()->isPost()):
			$request= $this->getRequest();
			$form = $request->getPost();
			$transporter = ($form['fcb_transport'] == 1)?$form['transporter_fcb']:$form['transporter_nonfcb'];
			$data =array(
					'id' => $form['issue_id'],
					'issue_date' => $form['issue_date'],
					'from_location' => $form['from_location'],
					'to_location' => $form['to_location'],
					'cost_center' => $form['activity'],
					'item_group' => $form['item_group'],
					'fcb_transport' => $form['fcb_transport'],
					'transporter' => $transporter,
					'party' => $form['party'],
					'vehicle_no' => $form['vehicle_no'],
					'total_amount' => $form['payable_amount'],
					'note' => $form['note'],
					'status' => 1,
					'author' =>$this->_author,
					'modified' =>$this->_modified
			);
			$data = $this->_safedataObj->rteSafe($data);
			//echo "<pre>"; print_r($data);exit;
			$result = $this->getDefinedTable(Store\IssueTable::class)->save($data);
			if($result>0):
				
				$details_id   = $form['details_id'];
				$item= $form['item'];
				$assetssp = $form['assetssp'];
				$from_balance = $form['from_balance'];
				$quantity= $form['quantity'];
				$uom= $form['uom'];
				$rate= $form['rate'];
				$amount= $form['amount'];
				$remarks = $form['remarks'];
				$delete_rows = $this->getDefinedTable(Store\IssueDetailsTable::class)->getNotIn($details_id, array('issue' => $result));
				
				for($i=0; $i < sizeof($item); $i++):
					if(isset($item[$i]) && is_numeric($quantity[$i])):
						$issue_detail_data = array(
							'id' => $details_id[$i],
							'issue' => $result,
							'item' => $item[$i],
							'assetssp' => $assetssp[$i],
							'uom' => $uom[$i],
							'quantity' => $quantity[$i],
							'rate' => $rate[$i],
							'amount' => $amount[$i],
							'remarks' => $remarks[$i],
							'author' =>$this->_author,
							'modified' =>$this->_modified,
						);
						$issue_detail_data = $this->_safedataObj->rteSafe($issue_detail_data);
						//echo "<pre>"; print_r($issue_detail_data);exit;
						$this->getDefinedTable(Store\IssueDetailsTable::class)->save($issue_detail_data);
					endif;
				endfor;
				
				//deleting deleted table rows form database table;
				//print_r($delete_rows);exit;
				foreach($delete_rows as $delete_row):
				//echo $delete_row['id'];
					$this->getDefinedTable(Store\IssueDetailsTable::class)->remove($delete_row['id']);
				endforeach;
				
				$challan_no = $this->getDefinedTable(Store\IssueTable::class)->getColumn($form['issue_id'],'challan_no');
				$this->flashMessenger()->addMessage("success^ Successfully updated challan no. ".$challan_no);
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to update Issue details");
			endif;
			return $this->redirect()->toRoute('inissue',array('action'=>'viewissue','id'=>$form['issue_id']));
		endif;
		$admin_locs = $this->getDefinedTable(Acl\UsersTable::class)->getColumn($this->_author,'admin_location');
        $admin_loc_array = explode(',',$admin_locs);
		return new ViewModel(array(
				'title' => 'Edit Issue',
				'regionObj'     	=> $this->getDefinedTable(Administration\RegionTable::class),
				'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
			    'admin_locs' 	=> $this->getDefinedTable(Administration\LocationTable::class)->get(array('l.id'=>$admin_loc_array)),
				'activityObj'  	=> $this->getDefinedTable(Administration\ActivityTable::class),
				'parties'   	=> $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>4)),
				'assetObj' => $this->getDefinedTable(Store\AssetTable::class),
				'asset_detailsObj' => $this->getDefinedTable(Store\AssetDetailsTable::class),
				'issues' => $this->getDefinedTable(Store\IssueTable::class)->get($issue_ID),
				'issue_detailsObj' => $this->getDefinedTable(Store\IssueDetailsTable::class),	
				'ssp_detailsObj' => $this->getDefinedTable(Store\StoreSpareDetailsTable::class),				
				'itemObj' => $this->getDefinedTable(Store\ItemTable::class),
				'sspObj' => $this->getDefinedTable(Store\StoreSpareTable::class),
				'employee_driver' => $this->getDefinedTable(Hr\EmployeeTable::class)->getEmployee(array('e.position_title'=>array(25,29,26,30,34,38,39))),
				'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
				'userID' => $this->_author,
				'user_location' => $this->_userloc,
				'groupObj'  => $this->getDefinedTable(Store\GroupTable::class),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
		));
	}
	
	/**
	 * receive goods Action
	 */
	public function receiveissueAction()
	{
		$this->init();	
		
		if($this->getRequest()->isPost()):
			$request= $this->getRequest();
			$form = $request->getPost();
			$data =array(
					'id' => $form['issue_id'],
					'status' => 10,
					'note' => $form['note'],
					'received_by' => $form['received_by'],
					'received_on' => $form['received_date'],
					'author' 	  => $this->_author,					
					'modified'    => $this->_modified
			);
			$data = $this->_safedataObj->rteSafe($data);
			echo "<pre>"; print_r($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Store\IssueTable::class)->save($data);
			$challan_no = $this->getDefinedTable(Store\IssueTable::class)->getColumn($form['issue_id'],'challan_no');
			if($result>0):
			/*** Receiving Goods Dispatch ***/
				$item_id         = $form['item'];
				$uom_id          = $form['uom'];
				$details_id  	 = $form['issue_detail_id'];
				$accept_qty      = $form['accept_qty'];
				$sound_qty		 = $form['sound_qty'];
				$demage_qty      = $form['damage_qty'];
				$shortage_qty    = $form['shortage_qty'];
				
				for($i=0; $i < sizeof($details_id); $i++):
				if(isset($details_id[$i])):
					$issue_detail_data = array(
						'id' 			     => $details_id[$i],
						'issue' 		     => $result,
						'accepted_quantity'  => $accept_qty[$i],
						'sound_quantity'	 => $sound_qty[$i],
						'demage_quantity'    => $demage_qty[$i],
						'shortage_quantity'  => $shortage_qty[$i],
						'author' 		     => $this->_author,
						'modified' 		     => $this->_modified,
					);
					$issue_detail_data = $this->_safedataObj->rteSafe($issue_detail_data);
					//echo "<pre>"; print_r($issue_detail_data);
					$this->getDefinedTable(Store\IssueDetailsTable::class)->save($issue_detail_data);
				endif;
				endfor;
				$issue_destination = $this->getDefinedTable(Store\IssueTable::class)->getColumn($form['issue_id'],'to_location');
				$source_location = $this->getDefinedTable(Store\IssueTable::class)->getColumn($form['issue_id'],'from_location');
				$issue_date = $this->getDefinedTable(Store\IssueTable::class)->getColumn($form['issue_id'],'issue_date');
				$issue_dtls = $this->getDefinedTable(Store\IssueDetailsTable::class)->get(array('issue' => $form['issue_id']));
				foreach($issue_dtls as $issue_dtl):
					//echo "<pre>";print_r($issue_dtl); exit;
					$item_id = $issue_dtl['item_id'];	
					foreach($this->getDefinedTable(Stock\ItemTable::class)->get($item_id) as $itemdtl);
					//echo "<pre>";print_r($itemdtl);
					$item_group = $itemdtl['item_group_id'];
					$item_id    = $itemdtl['item_id'];
					$asssetssp_id    = $itemdtl['asssetssp'];
					if($item_group == 1):
					    $asset_check = $this->getDefinedTable(Store\AssetDetailsTable::class)->get(array('asset'=>$issue_dtl['assetssp'],'location'=>$issue_destination));
						    //echo "<pre>";print_r($asset_check); exit;
							if($asset_check > 0){
								$insert_asset_data = array(
									'id'             => $asset_id,	
									'item'           => $item_id,
									'assetssp'       => $asssetssp_id,
									'location'       => $dispatch_destination,
									'quantity'       => 0,
									'selling_price'  => $selling_price,
									'author'         => $this->_author,
									'created'        => $this->_created,
									'modified'       => $this->_modified
								);
							}else{
								$insert_asset_data = array(
									'item'           => $item_id,
									'uom'            => $batch_uom,
									'assetssp'       => $asssetssp_id,
									'location'       => $dispatch_destination,
									'quantity'       => 0,
									'selling_price'  => $selling_price,
									'author'         => $this->_author,
									'created'        => $this->_created,
									'modified'       => $this->_modified
								);
							}
							//echo "<pre>";print_r($insert_mi_data);exit;
							$insert_asset_Result = $this->getDefinedTable(Store\AssetDetailsTable::class)->save($insert_asset_data);
					else:
					    $check_assetssp = $this->getDefinedTable(Store\StoreSpareDetailsTable::class)->get(array('storespare'=>$issue_dtl['assetssp'],'location'=>$issue_destination));
						if($asset_check > 0){
								$insert_asset_data = array(
									'id'             => $asset_id,	
									'item'           => $item_id,
									'assetssp'       => $asssetssp_id,
									'location'       => $dispatch_destination,
									'quantity'       => 0,
									'selling_price'  => $selling_price,
									'author'         => $this->_author,
									'created'        => $this->_created,
									'modified'       => $this->_modified
								);
							}else{
								$insert_asset_data = array(
									'item'           => $item_id,
									'uom'            => $batch_uom,
									'assetssp'       => $asssetssp_id,
									'location'       => $dispatch_destination,
									'quantity'       => 0,
									'selling_price'  => $selling_price,
									'author'         => $this->_author,
									'created'        => $this->_created,
									'modified'       => $this->_modified
								);
							}
					endif;
				endforeach;
				$this->_connection->commit(); 
				if($insert_asset_Result){
					$this->flashMessenger()->addMessage("success^ Successfully received challan no. ".$challan_no." . And Location Costing added for the Destination Location.");
				}else{
					$this->flashMessenger()->addMessage("success^ Successfully received challan no. ".$challan_no);
				}
			else:
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("Failed^ Failed to receive issue");
			endif;
			return $this->redirect()->toRoute('inissue',array('action'=>'viewissue','id'=>$form['issue_id']));
		endif;
		
		return new ViewModel(array(
				'title' => 'Receive the Issued Goods',
				'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj'  	=> $this->getDefinedTable(Administration\ActivityTable::class),
				'issues'		=> $this->getDefinedTable(Store\IssueTable::class)->get($this->_id),
				'issue_detailsObj' => $this->getDefinedTable(Store\IssueDetailsTable::class),
				'assetObj' => $this->getDefinedTable(Store\AssetTable::class),
				'sspObj' => $this->getDefinedTable(Store\StoreSpareTable::class),
				'itemObj'  => $this->getDefinedTable(Store\ItemTable::class), 
				'uomObj'    => $this->getDefinedTable(Stock\UomTable::class),
				'assetdetailsObj' => $this->getDefinedTable(Store\AssetDetailsTable::class),
				'sspdetailsObj' => $this->getDefinedTable(Store\StoreSpareDetailsTable::class),
				'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
				'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
				'userID' => $this->_author,
				'user_location' => $this->_userloc,
				'usersObj' => $this->getDefinedTable(Acl\UsersTable::class),
				'groupObj' => $this->getDefinedTable(Store\GroupTable::class),
		));
	}
	
	/**
	 * pending goods issue action {Issue}
	 */
	public function pendingissueAction()
	{
		$this->init();	
		$issues = $this->getDefinedTable(Store\IssueTable::class)->get($this->_id);
		$isuue_details = $this->getDefinedTable(Store\IssueDetailsTable::class)->get(array('issue'=>$this->_id));
		//echo "<pre>"; print_r($isuue_details); exit;
		foreach($issues as $issue);
		$lessThanQty = array();
		foreach($isuue_details as $isuue_detail):
			$item_group = $this->getDefinedTable(Store\ItemTable::class)->getColumn($isuue_detail['item_id'],'item_group');
			if($item_group == 1):
				$qty = $this->getDefinedTable(Store\AssetDetailsTable::class)->getColumn(array('asset' => $isuue_detail['assetssp'],'location' => $issue['from_location']),'quantity');
			else:
				$qty = $this->getDefinedTable(Store\StoreSpareDetailsTable::class)->getColumn(array('storespare' => $isuue_detail['assetssp'],'location'=> $issue['from_location']),'quantity');
			endif;
			if($qty < $isuue_detail['quantity']):
				array_push($lessThanQty,$isuue_detail['id']);
			endif;
		endforeach;
		$ViewModel = new ViewModel(array(
				'title' => 'Check Issue Quantity',
				'id' => $this->_id,
				'isuuedtls' => $lessThanQty,
				'assetsspdtlsObj' => $this->getDefinedTable(Store\StoreSpareDetailsTable::class),
				'issuedtlsObj' => $this->getDefinedTable(Store\IssueDetailsTable::class),
				'assetdtlsObj' => $this->getDefinedTable(Store\AssetDetailsTable::class),
				'isuueObj' => $this->getDefinedTable(Store\IssueTable::class),
				'itemObj' => $this->getDefinedTable(Store\ItemTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *
	 *do dispatch and do store adjustment
	 */
	public function doissueAction()
	{
		$this->init();
		
		$center_location = $this->getDefinedTable(Store\IssueTable::class)->getColumn($this->_id,"from_location");
		$issues = $this->getDefinedTable(Store\IssueTable::class)->get($this->_id);
		foreach($issues as $issue):
		
		$voucher_amount = $issue['total_amount'];
		$issue_no = $issue['challan_no'];
			
		$this->_connection->beginTransaction(); //***Transaction begins here***//	
		if($center_location == 241)
		{ 
			
			
			$location = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($center_location, 'prefix');
			$voucherType = '4'; //Journal
			$prefix = $this->getDefinedTable("Accounts\JournalTable")->getcolumn($voucherType,'prefix');	 
			$date = date('ym',strtotime($issue['issue_date']));
			$tmp_VCNo = $location.$prefix.$date;
			$results = $this->getDefinedTable("Accounts\TransactionTable")->getSerial($tmp_VCNo);
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
				'voucher_date' => $issue['issue_date'],
				'voucher_type' => $voucherType,
				'doc_id' => $issue_no,
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
			//echo $result; echo 
			if($result > 0 && $issue['item_group'] == 1 )// Assets
			{ 
				$tdetailsdata = array(
					'transaction'  => $result,
					'location'     => $issue['from_location'],
					'activity'     => '5',
					'head'         => '16',
					'sub_head'     => '1237',
					'bank_ref_type'=> $bank_ref_type,
					'cheque_no'    => '',
					'debit'        => 0.00,
					'credit'       => $voucher_amount,
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
					$issueDtls = $this->getDefinedTable(Store\IssueDetailsTable::class)->get(array('issue'=>$this->_id));
					//echo "<pre>";print_r($issueDtls); exit;			
					foreach($issueDtls as $issueDtl):
					    // Updatting the Assets quantity 
						$assetdtls = $this->getDefinedTable(Store\AssetDetailsTable::class)->get(array('asset'=>$issueDtl['assetssp'],'location'=>$issue['from_location']));
						//echo "<pre>";print_r($assetdtls); exit;			
						foreach($assetdtls as $assetdtl);
						$items = $this->getDefinedTable(Store\ItemTable::class)->get($issueDtl['item_id']);
						foreach($items  as $item);
						if($item['item_sub_group_id'] == 5){$head = 3;}elseif($item['item_sub_group_id'] == 6){$head = 5;}
						//echo "<pre>";print_r($item); exit;			
						$assets = $this->getDefinedTable(Store\AssetTable::class)->get(array('a.id'=>$assetdtl['asset']));
						foreach($assets  as $asset);
						//echo "<pre>";print_r($asset); exit;
						$remaining_qty = $assetdtl['quantity'] - $issueDtl['quantity'];				
						$update_assetdtls_data = array(
							'id'	   => $assetdtl['id'],
							'quantity' => $remaining_qty,
							'author'   => $this->_author,
							'created'  => $this->_modified,
						);
						$update_assetdtls_data   = $this->_safedataObj->rteSafe($update_assetdtls_data);
						//echo "<pre>";print_r($update_assetdtls_data); exit;			
						$resultAssetdtls = $this->getDefinedTable(Store\AssetDetailsTable::class)->save($update_assetdtls_data);						
						if($resultAssetdtls > 0 )
						{	
							$insert_asset_data = array(
								'name' => $item['name'],
								'code' => $asset['asset'],
								'fund' => '13',
								'purchase_date' =>$asset['asset_date'],
								'asset_value' => $assetdtl['rate'],
								'depreciation' =>'1',
								'rate' => $assetdtl['rate'],
								'method' => '1', 
								'cumulative' =>'',
								'activity' =>5, 
								'location' =>$issue['to_location'],
								'depreciation_date' => date('Y-m-d'), 
								'author' =>$this->_author,
								'created' =>$this->_created,
								'modified' =>$this->_modified,
							); 
							//echo "<pre>";print_r($insert_asset_data); exit;						
							$insert_asset_data = $this->_safedataObj->rteSafe($insert_asset_data);
							$resultAsset = $this->getDefinedTable(Accounts\AssetsTable::class)->save($insert_asset_data);
							if($resultAsset > 0)
							{
								$insert_subhead_data = array(
									'head'   => $head,
									'type'   => 1,
									'ref_id' =>$item['item_sub_group_id'],
									'code'   =>$item['name']." - ".$asset['asset'],
									'name'   => $item['name']." - ".$asset['asset'],
									'author' =>$this->_author,
									'created' =>$this->_created,
									'modified' =>$this->_modified,
								); 
								$insert_subhead_data = $this->_safedataObj->rteSafe($insert_subhead_data);
								//echo "<pre>";print_r($insert_subhead_data); exit;						
								$resultSubhead = $this->getDefinedTable(Accounts\SubheadTable::class)->save($insert_subhead_data);
								if($resultSubhead > 0)
								{
									$tdetailsdata1 = array(
										'transaction' 	=> $result,
										'location' 		=> $issue['to_location'],
										'activity' 		=> '5',
										'head' 			=> $head,
										'sub_head' 		=> $resultSubhead,
										'bank_ref_type' => $bank_ref_type,
										'cheque_no' 	=> $cheque_no,
										'debit' 		=> $issueDtl['amount'],
										'credit' 		=> 0.00,
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
										return $this->redirect()->toRoute('inissue', array('action' => 'viewissue', 'id' => $this->_id));	
									}
								}else{
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("error^ Failed to save the data in transaction details table and mapping is not done . Please check ");			
								return $this->redirect()->toRoute('inissue', array('action' => 'viewissue', 'id' => $this->_id));	
								}
							}else{
							$this->_connection->rollback(); // rollback transaction over failure
							$this->flashMessenger()->addMessage("error^ Failed to save the data in transaction details table and mapping is not done . Please check ");			
							return $this->redirect()->toRoute('inissue', array('action' => 'viewissue', 'id' => $this->_id));	
							}
						}else{
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Failed to save the data in assets details table. Please check ");			
						return $this->redirect()->toRoute('inissue', array('action' => 'viewissue', 'id' => $this->_id));
						}
				    endforeach; 									

				}else{	
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to save the data in assets details table. Please check ");			
				return $this->redirect()->toRoute('inissue', array('action' => 'viewissue', 'id' => $this->_id));
				}				
			}
			
			else //Store and Spare
			
			{ 
				$issueDtls = $this->getDefinedTable(Store\IssueDetailsTable::class)->get(array('issue'=>$this->_id));
				foreach($issueDtls as $issueDtl):
				//Updatting the Assets quantity 
				$sspdtls = $this->getDefinedTable(Store/StoreSpareDetailsTable::class)->get(array('storespare'=>$issueDtl['assetssp'],'location'=>$issue['from_location']));
					foreach($sspdtls as $sspdtl);
					$ssps = $this->getDefinedTable(Store\StoreSpareTable::class)->get(array('sp.id'=>$sspdtl['storespare']));
					$remaining_qty = $sspdtl['quantity'] - $issueDtl['quantity'];				
					$remaining_amount = $sspdtl['amount'] - $issueDtl['amount'];				
					if($remaining_qty == 0):					
					$update_sspdtls_data = array(
						'id'	   => $sspdtl['id'],
						'quantity' => $remaining_qty,
						'amount'   => 0,
						'rate'     => 0,
						'author'   => $this->_author,
						'created'  => $this->_modified,
					);
					else:
					$update_sspdtls_data = array(
						'id'	   => $sspdtl['id'],
						'quantity' => $remaining_qty,
						'amount'   => $remaining_amount,
						'author'   => $this->_author,
						'created'  => $this->_modified,
					);
					endif;
					$update_sspdtls_data   = $this->_safedataObj->rteSafe($update_sspdtls_data);
					$resultSspdtls = $this->getDefinedTable(Store\StoreSpareDetailsTable::class)->save($update_sspdtls_data);
				endforeach; 
				if(sizeof($resultSspdtls) > 0 )
				{
                    $item_groups = $this->getDefinedTable(Store\IssueDetailsTable::class)->getDistinctISGID(array('issue'=>$this->_id));
					foreach($item_groups  as $item_group):
	                if($item_group['item_sub_group_id'] == 1){$head = 159;}elseif($item_group['item_sub_group_id'] == 4){$head = 183;}
				    elseif($item_group['item_sub_group_id'] == 3){$head = 156;}elseif($item_group['item_sub_group_id'] == 7){$head = 259;}
					elseif($item_group['item_sub_group_id'] == 2){$head = 167;}elseif($item_group['item_sub_group_id'] == 9){$head = 168;}
			    	$amount = $this->getDefinedTable(Store\IssueDetailsTable::class)->getAmount(array('issue'=>$this->_id),$item_group['item_sub_group_id']);
					$sub_headResults = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.type'=>'9', 'sh.head'=>$head,'sh.ref_id'=>$item_group['item_sub_group_id']));
					foreach($sub_headResults as $sub_hR);
					$tdetailsdata = array(
						'transaction'  => $result,
						'location'     => $issue['to_location'],
						'activity'     => '5',
						'head'         => $sub_hR['head_id'],
						'sub_head'     => $sub_hR['id'],
						'bank_ref_type'=> $bank_ref_type,
						'cheque_no'    => '',
						'debit'        => $amount,
						'credit'       => '0.00',
						'ref_no'       => '',
						'type'         => '2', //System Generated
						'author'       => $this->_author,
						'created'      => $this->_created,
						'modified'     => $this->_modified,
					);
					$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
					$resultTdetailsdata = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
					$sub_headResults = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.type'=>'9', 'sh.head'=>'203','sh.ref_id'=>$item_group['item_sub_group_id']));	if(sizeof($sub_headResults) > 0 )
					{
						foreach($sub_headResults as $shrow);
						$tdetailsdata1 = array(
							'transaction' 	=> $result,
							'location' 		=> $issue['to_location'],
							'activity' 		=> '5',
							'head' 			=> '203',
							'sub_head' 		=> $shrow['id'],
							'bank_ref_type' => $bank_ref_type,
							'cheque_no' 	=> $cheque_no,
							'debit' 		=> '0.00',
							'credit' 		=> $amount,
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
							return $this->redirect()->toRoute('inissue', array('action' => 'viewissue', 'id' => $this->_id));	
						}								
					}else{	
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to save the data in store and spare details table and sub head mapping is not done. Please check ");			
					return $this->redirect()->toRoute('inissue', array('action' => 'viewissue', 'id' => $this->_id));
					}
				    endforeach;
                }else{		
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to save the data in store and spare details table.");			
				return $this->redirect()->toRoute('inissue', array('action' => 'viewissue', 'id' => $this->_id));
				}				
			}
	    }
		
		else // location is not equal to 7
		
		{
			$issueDtls = $this->getDefinedTable(Store\IssueDetailsTable::class)->get(array('issue'=>$this->_id));
		    foreach($issueDtls as $issueDtl):
			
			if($item['item_group'] == 1)//Asset
			{ 
				// Updatting the Assets quantity 
				$assetdtls = $this->getDefinedTable(Store\AssetDetailsTable::class)->get(array('asset'=>$issueDtl['assetssp'],'location'=>$issue['from_location']));
				foreach($assetdtls as $assetdtl);
				$remaining_qty = $assetdtl['quantity'] - $issueDtl['quantity'];				
				$update_assetdtls_data = array(
					'id'	=> $assetdtl['id'],
					'quantity' => $remaining_qty,
					'author' => $this->_author,
					'created' => $this->_modified,
				);
				$update_assetdtls_data   = $this->_safedataObj->rteSafe($update_assetdtls_data);
				$result1 = $this->getDefinedTable(Store\AssetDetailsTable::class)->save($update_assetdtls_data);		
		    }
			else //Store and Spare
			{ 
				// Updatting the Store and Spare quantity 
				$ssspdtls = $this->getDefinedTable(Store/StoreSpareDetailsTable::class)->get(array('storespare' => $issueDtl['assetssp'],'location'=>$issue['from_location']));
				foreach($ssspdtls as $ssspdtl);
				$remaining_qty = $ssspdtl['quantity'] - $issueDtl['quantity'];
				$upate_storespare = array(
					'id'	=> $ssspdtl['id'],
					'quantity' => $remaining_qty,
					'author' => $this->_author,
					'created' => $this->_created,
				);
				$upate_storespare   = $this->_safedataObj->rteSafe($upate_storespare);
				$result1 = $this->getDefinedTable(Store\StoreSpareDetailsTable::class)->save($data1);
			}
			endforeach;
		}
		// Updating the status and transaction id in the issue table
		$data = array(
				'id'			=> $this->_id,
				'transaction_id'=> $result,
				'status' 		=> 2,
				'author'	    => $this->_author,
				'modified'      => $this->_modified,
		);
		$data   = $this->_safedataObj->rteSafe($data);				
		$result = $this->getDefinedTable(Store\IssueTable::class)->save($data);
		$notification_data = array(
			'route'         => 'inissue',
			'action'        => 'viewissue',
			'key' 		    => $this->_id,
			'description'   => 'dispatch of Goods',
			'author'	    => $this->_author,
			'created'       => $this->_created,
			'modified'      => $this->_modified,   
		);
		$notificationResult = $this->getDefinedTable(Acl\NotificationTable::class)->save($notification_data);
		//echo "<pre>";print_r($notification_data); exit;			
		if($notificationResult > 0 ){
			/*Get users under destination location with sub role Depoy Manager*/
			$sourceLocation = $this->getDefinedTable(Store\IssueTable::class)->getColumn($this->_id, 'to_location');
			$depoyManagers = $this->getDefinedTable(Acl\UserroleTable::class)->get(array('subrole'=>'9','21'));
			foreach($depoyManagers as $row):
				$user_location_id = $this->getDefinedTable(Acl\UsersTable::class)->getColumn($row['user'], 'location');
				if($user_location_id == $sourceLocation ):
					$notify_data = array(
						'notification' => $notificationResult,
						'user'    	   => $row['user'],
						'flag'    	 => '0',
						'desc'    	 => 'Goods have been dispatched to your location',
						'author'	 => $this->_author,
						'created'    => $this->_created,
						'modified'   => $this->_modified,  
					);
					$notifyResult = $this->getDefinedTable(Acl\NotifyTable::class)->save($notify_data);
				//echo "<pre>";print_r($notify_data); exit;			
				endif;
			endforeach;
		}
		endforeach;
		$this->_connection->commit(); // commit transaction over success
		$this->flashMessenger()->addMessage("success^ successfully save the data with Challan or issue no." .$issue_no);
		return $this->redirect()->toRoute('inissue', array('action' => 'viewissue', 'id' => $this->_id));
	}
	
	/**
	 * confirm dispatch goods received
	 */
	public function confirmreceiveAction()
	{
		$this->init();
		
		$issue_id = $this->_id;
		
		$issues = $this->getDefinedTable(Store\IssueTable::class)->get($issue_id);
		foreach($issues as $issue):
		//echo "<pre>";print_r($issue); exit;
		if($issue['party'] != '323'):
			/*** Generate Transport No ***/
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($issue['to_location'],'prefix');
			$date = date('ym',strtotime($this->_created));
			$tmp_SerialNo = $location_prefix."TC".$date;
			$results = $this->getDefinedTable(Store\TransportChargeTable::class)->getMonthlySerialNo($tmp_SerialNo);
			
			if(sizeof($results) <= 0):
				$next_serial = 1;
			else:
				$serial_no_list = array();
				foreach($results as $result):
					array_push($serial_no_list, substr($result['transport_no'], 8));
				endforeach;
				$next_serial = max($serial_no_list) + 1;
			endif;
			switch(strlen($next_serial)){
				case 1: $next_serial_no = "000".$next_serial; break;
				case 2: $next_serial_no = "00".$next_serial;  break;
				case 3: $next_serial_no = "0".$next_serial;   break;
				default: $next_serial_no = $next_serial;      break;
			}
			
			$transport_no = $tmp_SerialNo.$next_serial_no;
			/****** Transit Loss Details *******/
			$issue_details = $this->getDefinedTable(Store\IssueDetailsTable::class)->get(array('d.issue'=>$issue_id));
			//echo "<pre>";print_r($issue_details); exit;
			$issue_date = $this->getDefinedTable(Store\IssueTable::class)->getColumn($issue_id,'issue_date');
			//Sum of Dispatch Quantities
			foreach($issue_details as $issue_detail):
			    $item_group = $this->getDefinedTable(Store\ItemTable::class)->getColumn($issue_detail['item_id'],'item_group');
			    $total_quantity = 0;
				if($issue_detail['shortage_quantity'] > 0):
					$amount = $issue_detail['shortage_quantity'] * $issue_detail['rate'];
					$transit_loss = array(
							'issue' => $issue_id,
							'item_group' => $item_group,
							'item' => $issue_detail['item_id'],
							'assetssp' => $issue_detail['assetssp'],
							'uom' => $issue_detail['uom'],
							'qty_loss' => $issue_detail['shortage_quantity'],
							'rate' => $issue_detail['rate'],
							'amount' => $amount,
							'author' => $this->_author,
							'created' => $this->_created,
							'modified' => $this->_modified					
					);
					$total_amount += $amount;
					$transit_loss = $this->_safedataObj->rteSafe($transit_loss);
					//echo "<pre>"; print_r($transit_loss); exit;
					$transit_result = $this->getDefinedTable(Store\TransitLossTable::class)->save($transit_loss); 
				endif;
				$total_quantity += $issue_detail['shortage_quantity'] ;
			endforeach;
			
			/****** Transport Charge Details *******/
			
            $trip_id = $this->getDefinedTable(Stock\TripTable::class)->getColumn(array('status' => 1),'id');
			$tripsdtls = $this->getDefinedTable(Stock\TripDtlsTable::class)->get(array('trip' => $trip_id,'source_location'=>$issue['from_location'],'destination_location'=>$issue['to_location']));
			foreach($tripsdtls as $trip);
			//echo"<pre>"; print_r($trip);  exit;
			
			$check = $trip['hill_distance'] + $trip['plain_distance'];
			if($check > 0):
				$hill_transp_charge = $trip['hill_distance'] * $trip['hill_rate'] * $total_quantity;
				$plain_transp_charge = $trip['plain_distance'] * $trip['plain_rate'] * $total_quantity;
				$total_charge = $hill_transp_charge + $plain_transp_charge;
			else:
				$tripsrev = $this->getDefinedTable(Stock\TripDtlsTable::class)->get(array('trip' => $trip_id,'source_location'=>$issue['to_location'],'destination_location'=>$issue['from_location']));
				foreach($tripsrev as $trip);
				
				$hill_transp_charge = $trip['hill_distance'] * $trip['hill_rate'] * $total_quantity;
				$plain_transp_charge = $trip['plain_distance'] * $trip['plain_rate'] * $total_quantity;
				$total_charge = $hill_transp_charge + $plain_transp_charge;
			endif;
			
			$transp_charge = $total_charge - $total_amount;
			
			$data = array(
					'transport_no' => $transport_no,
					'transport_date' => date('Y-m-d'),
					'issue' => $issue_id,
					'location' => $issue['to_location'],
					'item_group' => $issue['item_group'],
					'activity' => $issue['cost_center'],
					'transporter' => $issue['party'],
					'source_location' => $issue['from_location'],
					'destination_location' => $issue['to_location'],
					'hill_distance' => $trip['hill_distance'],
					'hill_rate' => $trip['hill_rate'],
					'plain_distance' => $trip['plain_distance'],
					'plain_rate' => $trip['plain_rate'],
					'qty' => $total_quantity,
					'transportation_charge' => $transp_charge,
					'status' => 1,
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified
			);
			
			$data = $this->_safedataObj->rteSafe($data);
			//echo "<pre>"; print_r($data); exit;
			$result = $this->getDefinedTable('Store\TransportChargeTable')->save($data);
		endif;//endof fcb_transport == 0
		
		/*** Change in Asset Details Table/Store Spare Details Table and Change the Status in Issue Table ***/
		$issue_details = $this->getDefinedTable(Store\IssueDetailsTable::class)->get(array('issue'=>$this->_id));
		//echo "<pre>";print_r($issue_details); exit;
		
		if($issue['received_on'] > 0):
			foreach($issue_details as $detail):
				$item_group = $this->getDefinedTable(Store\ItemTable::class)->getColumn($detail['item_id'],'item_group');
				if($item_group == 1){ //Asset
					$assetdtls = $this->getDefinedTable(Store\AssetDetailsTable::class)->get(array('asset'=>$detail['assetssp'],'location'=>$issue['to_location']));
					//echo "<pre>"; print_r($assetdtls); exit;
					foreach($assetdtls as $assetdtl);
					if(sizeof($assetdtl) > 0 )
					{
						$remaining_qty = $assetdtl['quantity'] + $detail['accepted_quantity'];
						$data1 = array(
							'id'	=> $assetdtl['id'],
							'quantity' => $remaining_qty,
							'author' => $this->_author,
							'created' => $this->_modified,
						);
					}
					else
					{
						$data1 = array(
							'quantity' => $detail['accepted_quantity'],
							'location' => $issue['to_location'],
							'asset'    => $detail['assetssp'],
							'rate'     => $detail['rate'],
                                                        'amount'   =>$detail['amount'],
							'author'   => $this->_author,
							'created'  => $this->created,
							'modified'  => $this->_modified,
						);	
					}
					$data1   = $this->_safedataObj->rteSafe($data1);
					//echo "<pre>"; print_r($data1); exit;
					$result1 = $this->getDefinedTable(Store\AssetDetailsTable::class)->save($data1);
				}else{ //Store and Spare
					$sspdtls = $this->getDefinedTable(Store/StoreSpareDetailsTable::class)->get(array('storespare' => $detail['assetssp'],'location'=>$issue['to_location']));
					foreach($sspdtls as $sspdtl);
					if(sizeof($sspdtl) > 0 )
					{	
						$remaining_qty = $sspdtl['quantity'] + $detail['accepted_quantity'];					
						$data1 = array(
							'id'	=> $sspdtl['id'],
							'quantity' => $remaining_qty,
                                                        'rate'     =>$sspdtl['rate'],
                                                        'amount'   =>$sspdtl['amount'],
							'author' => $this->_author,
							'created' => $this->_modified,
						);
					}
					else
					{
						$data1 = array(
							'quantity'   => $detail['accepted_quantity'],
							'location'   => $issue['to_location'],
							'storespare' => $detail['assetssp'],
							'rate'       => $detail['rate'],
                                                        'amount'     =>$detail['amount'],
							'author'     => $this->_author,
							'created'    => $this->created,
							'modified'   => $this->_modified,
						);	
					}
					$data1   = $this->_safedataObj->rteSafe($data1);
					//echo "<pre>";print_r($data1); exit;
					$result1 = $this->getDefinedTable(Store\StoreSpareDetailsTable::class)->save($data1);
				}
			endforeach;
		endif;
		//exit;
		
		$issue_data = array(
			'id' => $issue_id,
			'status' => 3,
			'author' => $this->_author,
			'modified' => $this->_modified,		
		);
		$issue_data   = $this->_safedataObj->rteSafe($issue_data);
		$result2 = $this->getDefinedTable(Store\IssueTable::class)->save($issue_data);
		
		$challan_no = $this->getDefinedTable(Store\IssueTable::class)->getColumn($issue_id,'challan_no');
		if($result2){
			if($result)
				$this->flashMessenger()->addMessage("success^ Successfully Committed Issue No. ".$challan_no.". And New Transport Charge successfully added with TC No. ".$transport_no);
			else{
				$this->flashMessenger()->addMessage("success^ Successfully Committed Issue Received");
			}
		}else{
			$this->flashMessenger()->addMessage("error^ Failed to Commit the Received Issue");
		}
		return $this->redirect()->toRoute('inissue',array('action'=>'viewissue','id'=>$issue_id));
	endforeach;
	}
	
	/**
	 * Revice Issue Destination Location
	**/
	public function reviceissueAction()
	{
		$this->init();
		$issue_id = $this->_id;
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'id' => $form['issue_id'],
				'to_location' => $form['to_location'],
				'note' => $form['note'],
				'author'      => $this->_author,
				'modified'    => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data); 
			$result = $this->getDefinedTable(Store\IssueTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully updated the Issue Destination Location");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to update Issue Destination Location");
			endif;
			return $this->redirect()->toRoute('inissue', array('action' => 'viewissue', 'id' => $form['issue_id']));
		}
		$ViewModel = new ViewModel(array(
				'title' => 'Revice Issue',
				'issues' => $this->getDefinedTable(Store\IssueTable::class)->get($issue_id),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Dispatch Report
	**/
	public function issuereportAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'item_group'           => $form['item_group'],
				'source_location'      => $form['source_location'],
				'destination_location' => $form['destination_location'],
				'start_date'           => $form['start_date'],
				'end_date'             => $form['end_date'],
			);
		}else{
			$data = array(
				'item_group'           => '-1',
				'source_location'      => '-1',
				'destination_location' => '-1',
				'start_date'           => '',
				'end_date'             => '',
			);
		}
		
		return new ViewModel(array(
			'title'       => 'Issue Report',
			'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
			'regionObj'   => $this->getDefinedTable(Administration\RegionTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'data'        => $data,
			'issueObj'    => $this->getDefinedTable(Store\IssueTable::class),
			'itemObj'     => $this->getDefinedTable(Store\ItemTable::class),
			'uomObj'      => $this->getDefinedTable(Stock\UomTable::class),
			'groupObj'    => $this->getDefinedTable(Store\GroupTable::class),
			'assetObj'    => $this->getDefinedTable(Store\AssetTable::class),
			'assetsspObj' => $this->getDefinedTable(Store\StoreSpareTable::class),
		));
	}
	/**
	 * asset opening
	**/
	public function assetopeningAction()
	{
		$this->init();
		$year='';
		$month='';
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
		}else{
			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;

			$data = array(
				'year' => $year,
				'month' => $month,
			);
		}
		$openingstores = $this->getDefinedTable(Store\AssetOpeningTable::class)->getDateWise('opening_date',$year,$month);
		
		return new ViewModel(array(
		
			'title'         => 'Store Asset Opening',
			'openingstores' => $openingstores,
			'minYear' 		=> $this->getDefinedTable(Store\AssetOpeningTable::class)->getMin('opening_date'),
			'groupObj'      => $this->getDefinedTable(Store\GroupTable::class),
			'itemObj'       => $this->getDefinedTable(Store\ItemTable::class),
			'uomObj'        => $this->getDefinedTable(Stock\UomTable::class),
			'data'          => $data,
		));
	}
	/**
	 * store and spare opening
	**/
	public function sspopeningAction()
	{
		$this->init();
		$year='';
		$month='';
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
		}else{
			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;
			$item_group = '-1';

			$data = array(
					'year' => $year,
					'month' => $month,
			);
		}
		$openingstores = $this->getDefinedTable(Store\SspOpeningTable::class)->getDateWise('opening_date',$year,$month);
		return new ViewModel(array(
			'title'         => 'Store and Spare Opening',
			'openingstores' => $openingstores,
			 'minYear' 		=> $this->getDefinedTable(Store\SspOpeningTable::class)->getMin('opening_date'),
			'groupObj'      => $this->getDefinedTable(Store\GroupTable::class),
			'itemObj'       => $this->getDefinedTable(Store\ItemTable::class),
			'uomObj'        => $this->getDefinedTable(Stock\UomTable::class),
			'data'          => $data,
		));
	}
	/**
	 * OPENING STOCK ENTRY
	 * Add Stock Opening Action
	 * Initiate Status
	**/
	public function addsspopeningAction() 
	{
		$this->init();
        if($this->getRequest()->isPost()){
			
			$form = $this->getRequest()->getPost();
			$item_detls = $this->getDefinedTable(Store\ItemTable::class)->get(array('i.id'=>$form['item']));
			foreach($item_detls as $item_detl):
			
			if($item_detl['item_group_id'] == 2): //Assets
			
				$check_ssp = $this->getDefinedTable(Store\SspOpeningTable::class)->get(array('storespare'=>$form['assetssp']));
				if(sizeof($check_ssp) > 0 ):
				    $this->flashMessenger()->addMessage("error^ You cannot save the same store and spare no.,but you can edit it.");			
					return $this->redirect()->toRoute('inissue', array('action' => 'addsspopening'));
			    else:
					$ssp_data = array(
						'storespare'     => $form['assetssp'],
						'item'           => $item_detl['id'],
						'uom'            => $item_detl['uom'],
						'quantity'       => $form['qty'],
						'opening_date'   => '2018-01-01',
						'status' 		 => 2,
						'author'	     => $this->_author,
						'created'        => $this->_created,
						'modified'       => $this->_modified,
					   );
					$ssp_data   = $this->_safedataObj->rteSafe($ssp_data);
					$storespID = $this->getDefinedTable(Store\SspOpeningTable::class)->save($ssp_data);
					if($storespID > 0 ){
						
					 $item		  = $form['item'];
					 $amount	  = $form['amount'];
					 $quantity	  = $form['qty'];
					 $location	  = $form['location'];
					 for($i=0; $i < sizeof($item); $i++):
						if(isset($item[$i]) && $item[$i] > 0):	
						    $rate = $amount[$i] / $quantity[$i];
							$storespDtls = array(
								'storespare'     => $storespID,
								'location'       => $location[$i],
								'quantity'       => $quantity[$i],
								'amount'         => $amount[$i],
								'rate'         => $rate,
								'status'         => 2,
								'author'         => $this->_author,
								'created'        => $this->_created,
								'modified'       => $this->_modified
							);	
							$storespDtls   = $this->_safedataObj->rteSafe($storespDtls);
							$storespDtlsId = $this->getDefinedTable(Store\SspOpeningDetailsTable::class)->save($storespDtls);
							if($storespDtlsId){
							$this->getDefinedTable(Store\SspOpeningTable::class)->save(array('id'=>$storespID,'quantity'=>$quantity[$i]));
							}else{
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("error^ Unsuccessful to update Store and spares details.");
							}
						endif;
					endfor;	
						if($storespDtlsId < 0):
						   $this->_connection->rollback(); // rollback transaction over failure
						   $this->flashMessenger()->addMessage("error^ Failed to save the data in store and spare details table. Please check ");			
						   return $this->redirect()->toRoute('inissue', array('action' => 'addsspopening'));
						endif;
					}
					else{			   	     
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Failed to save the data in store and spare table. Please Check");
						return $this->redirect()->toRoute('inissue', array('action' => 'addsspopening'));
					}
					$this->flashMessenger()->addMessage("success^ Successfully save the data in store and spare table. Please Check");
					return $this->redirect()->toRoute('inissue', array('action' => 'viewsspopening','id'=>$storespID));
				endif;
		    endif;
		endforeach;
		}
					
		return new ViewModel(array(
				'title'           => 'Add Store and Spare Opening',
				'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
				'regionObj'     => $this->getDefinedTable(Administration\RegionTable::class),
				'activityObj'     => $this->getDefinedTable(Administration\ActivityTable::class),
				'itemObj'         => $this->getDefinedTable(Store\ItemTable::class),
				'groupObj'         => $this->getDefinedTable(Store\GroupTable::class),
		));
	}
	/**
	 * OPENING STOCK ENTRY
	 * Add Stock Opening Action
	 * Initiate Status
	**/
	public function addassetopeningAction() 
	{
		$this->init();
        if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();	
			$counts = (int)$form['qty'];
			$this->_connection->beginTransaction();
			for($x=0; $x < $counts ; $x++)
			{
				$itemTypeCode = $this->getDefinedTable(Store\ItemTable::class)->getColumn($form['item'],'item_type_code');
				$itemSpecificationCode = $this->getDefinedTable(Store\ItemTable::class)->getColumn($form['item'],'item_specification_code');	
				$itemsg_id = $this->getDefinedTable(Store\ItemTable::class)->getColumn($form['item'],'item_sub_group');
				$prefix = $this->getDefinedTable(Store\SubGroupTable::class)->getColumn($itemsg_id,'prefix');		
				$activity_prefix = $this->getDefinedTable(Administration\ActivityTable::class)->getColumn(5,'prefix');		
				$year = date('Y',strtotime($form['opening_date']));
				$tmp_ACNo = "FCBL"."/".$activity_prefix."/".$year."/".$prefix."/".$itemTypeCode."/".$itemSpecificationCode."/";
				$results  = $this->getDefinedTable(Store\AssetTable::class)->getMonthlyAssetCode($tmp_ACNo);							
				$sheet_no_list = array();
				foreach($results as $result):			   	
					array_push($sheet_no_list, substr($result['asset'],21));
				endforeach;
				if(sizeof($sheet_no_list)>0){
					$next_serial = max($sheet_no_list) + 1 + $x;
				}else{
					$next_serial = 1 + $x;
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
					'item'          =>$form['item'],
					'uom'           =>$form['basic_uom'],
					'quantity'      =>1,
					'opening_date'  =>$form['opening_date'],
					'status' 		=>2,
					'author'	    =>$this->_author,
					'created'       =>$this->_created,
					'modified'      =>$this->_modified,
				);
				$asset_data   = $this->_safedataObj->rteSafe($asset_data);
				$assetID = $this->getDefinedTable(Store\AssetOpeningTable::class)->save($asset_data);
				$amount = (int)$form['amount']/(int)$form['qty'];
		        $location = $form['location'];
				$assetDtls = array(
					'asset'          => $assetID,
					'location'       => $location,
					'quantity'       => 1,
					'rate'           => $amount,
					'amount'         => $amount,
					'status'         => 2,
					'author'         => $this->_author,
					'created'        => $this->_created,
					'modified'       => $this->_modified
				);	
				$assetDtls   = $this->_safedataObj->rteSafe($assetDtls);
				$assetDtlsId = $this->getDefinedTable(Store\AssetOpeningDetailsTable::class)->save($assetDtls);			
			}
			if($assetDtlsId > 0){
                $this->_connection->commit(); // commit transaction over success
				$this->flashMessenger()->addMessage("success^ Successfully updated new asset No");			
				}
			else{			   	     
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to save the data in assets table. Please Check");
				return $this->redirect()->toRoute('inissue', array('action' =>'addassetopening'));
			}
			return $this->redirect()->toRoute('inissue', array('action' =>'assetopening'));
		}
					
		return new ViewModel(array(
				'title'           => 'Add Asset Opening',
				'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
				'regionObj'       => $this->getDefinedTable(Administration\RegionTable::class),
				'activityObj'     => $this->getDefinedTable(Administration\ActivityTable::class),
				'itemObj'         => $this->getDefinedTable(Store\ItemTable::class),
				'groupObj'         => $this->getDefinedTable(Store\GroupTable::class),
		));
	}
    /**
	 * Get Basic Uom
	**/
	public function getitemuomAction()
	{
		$this->init();		
		$form = $this->getRequest()->getPost();		
		$itemID = $form['item'];
		$opening_date = $form['opening_date'];
		
		$itemDtls = $this->getDefinedTable(Store\ItemTable::class)->get($itemID);
		$uom_for .="<option value=''></option>";
		foreach($itemDtls as $dtl):
			$uom_for .="<option value='".$dtl['st_uom_id']."' selected>".$dtl['uom_code']."</option>";		
		endforeach;		
		
		$item_group_id = $this->getDefinedTable(Store\ItemTable::class)->getColumn($itemID,'item_group');	
		
		if($item_group_id == 1):
		 
		    $itemTypeCode = $this->getDefinedTable(Store\ItemTable::class)->getColumn($form['item'],'item_type_code');
			$itemSpecificationCode = $this->getDefinedTable(Store\ItemTable::class)->getColumn($form['item'],'item_specification_code');	
			$itemsg_id = $this->getDefinedTable(Store\ItemTable::class)->getColumn($form['item'],'item_sub_group');
			$prefix = $this->getDefinedTable(Store\SubGroupTable::class)->getColumn($itemsg_id,'prefix');		
			$activity_prefix = $this->getDefinedTable(Administration\ActivityTable::class)->getColumn(5,'prefix');		
			$year = date('Y',strtotime($opening_date));
			$tmp_ACNo = "FCBL"."/".$activity_prefix."/".$year."/".$prefix."/".$itemTypeCode."/".$itemSpecificationCode."/";
			$results  = $this->getDefinedTable(Store\AssetTable::class)->getMonthlyAssetCode($tmp_ACNo);		   	  
					 
			$sheet_no_list = array();
			foreach($results as $result):			   	
				array_push($sheet_no_list, substr($result['asset'], 21));
			endforeach;
			if(sizeof($sheet_no_list)>0){
				$next_serial = max($sheet_no_list) + 1;
			}else{
				$next_serial = 1;
			}			   	  
			switch(strlen($next_serial)){
				case 1: $next_b_serial  = "000".$next_serial;  break;
				case 2: $next_b_serial  = "00".$next_serial;  break;
				case 3: $next_b_serial  = "0".$next_serial;   break;
				default: $next_b_serial = $next_serial;      break;
			}			   	  
			$assetssp =$tmp_ACNo.$next_b_serial; 
			
		else:
		
		    $existing_SSP_codes =  $this->getDefinedTable(Store\StoreSpareTable::class)->getExistingSSPCode(array('item'=>$itemID,'status'=>'3','storespare_date'=>'2018-01-01'));
			
			$multiple_array = array();
			foreach($existing_SSP_codes as $existing_SSP_code):
				array_push($multiple_array,$existing_SSP_code['id']);
			endforeach;
			$max_existing_SSP_code = max($multiple_array);
			
			$existing_SSPCode =  $this->getDefinedTable(Store\StoreSpareTable::class)->getExistingSSPCode($max_existing_SSP_code);
			if(sizeof($existing_SSPCode) > 0):
				foreach($existing_SSPCode as $existing_SSPC);
				$assetssp = $existing_SSPC['storespare'];
				
			else:
				
				$itemsg_id = $this->getDefinedTable(Store\ItemTable::class)->getColumn($form['item'],'item_sub_group');
				$prefix = $this->getDefinedTable(Store\SubGroupTable::class)->getColumn($itemsg_id,'prefix');		
				$activity_prefix = $this->getDefinedTable(Administration\ActivityTable::class)->getColumn(5,'prefix');		
				$year = date('Y', strtotime($opening_date));
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
				$assetssp = $tmp_SSPCNo.$next_sc_serial;
		    endif;
		endif;
		
		echo json_encode(array(
				'uom' =>$uom_for,
				'assetssp' =>$assetssp,
		));
		exit;
	}
	
	/**
	 * store opening
	**/
	public function viewassetopeningAction()
	{
		$this->init();
		
		return new ViewModel( array(
			'assetOpening' => $this->getDefinedTable(Store\AssetOpeningTable::class)->get($this->_id),
			'assetOpeningdtlsObj' => $this->getDefinedTable(Store\AssetOpeningDetailsTable::class),
			'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
			'locationtypeObj'      => $this->getDefinedTable(Administration\LocationTypeTable::class),
			'statusObj'        => $this->getDefinedTable(Acl\StatusTable::class),
			'itemObj' => $this->getDefinedTable(Store\ItemTable::class),
			'itemuomObj'  => $this->getDefinedTable(Stock\ItemUomTable::class),
			'uomObj'=> $this->getDefinedTable(Stock\UomTable::class),
			'usersObj'=> $this->getDefinedTable(Acl\UsersTable::class),
			'author'=> $this->_author,
		) );
	}
	/**
	 * store opening
	**/
	public function viewsspopeningAction()
	{
		$this->init();
		
		return new ViewModel( array(
			'sspOpening' => $this->getDefinedTable(Store\SspOpeningTable::class)->get($this->_id),
			'sspOpeningdtlsObj' => $this->getDefinedTable(Store\SspOpeningDetailsTable::class),
			'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
			'locationtypeObj'      => $this->getDefinedTable(Administration\LocationTypeTable::class),
			'statusObj'        => $this->getDefinedTable(Acl\StatusTable::class),
			'itemObj' => $this->getDefinedTable(Store\ItemTable::class),
			'itemuomObj'  => $this->getDefinedTable(Stock\ItemUomTable::class),
			'uomObj'=> $this->getDefinedTable(Stock\UomTable::class),
			'usersObj'=> $this->getDefinedTable(Acl\UsersTable::class),
			'author'=> $this->_author,
		) );
	}
	/**
	 * Update to batch_table
	**/
	public function updateassetAction()
	{
		$this->init();

		$opening_stores = $this->getDefinedTable(Store\AssetOpeningTable::class)->get($this->_id);
		foreach($opening_stores as $opening);
		foreach($this->getDefinedTable(Store\ItemTable::class)->get($opening['item']) as $itemdtls);
		
		if($itemdtls['item_group_id'] == 1):
		
			if($opening['status']==2): //opening stock status = 2 pending.
				$data = array(
						'asset' => $opening['asset'],
						'item'  => $opening['item'],
						'uom'   => $opening['uom'],	
						'quantity' => $opening['quantity'],
						'barcode' => '',
						'asset_date' => $opening['opening_date'],
						'expiry_date' => '',
						'status' => '3',
						'author' => $this->_author,
						'created' => $this->_created,
						'modified' => $this->_modified,					
				);
				$data = $this->_safedataObj->rteSafe($data);
				$this->_connection->beginTransaction(); //***Transaction begins here***//
				$assetID = $this->getDefinedTable(Store\AssetTable::class)->save($data);
				if($assetID > 0){
					$opening_dtls = $this->getDefinedTable(Store\AssetOpeningDetailsTable::class)->get(array('asset' =>$opening['id'],'status'=>'2','author'=>$this->_author));
					foreach($opening_dtls as $detail):
						$assetDtls = array(
							'asset'          => $assetID,
							'location'       => $detail['location'],
							'quantity'       => $detail['quantity'],
							'rate'           => $detail['rate'],
                                                        'amount'         => $detail['amount'],
							'author'         => $this->_author,
							'created'        => $this->_created,
							'modified'       => $this->_modified,
						);
						$assetDtls = $this->_safedataObj->rteSafe($assetDtls);
						$assetDtlsId = $this->getDefinedTable(Store\AssetDetailsTable::class)->save($assetDtls);
						if($assetDtlsId){
						$this->getDefinedTable(Store\AssetOpeningDetailsTable::class)->save(array('id'=>$detail['id'],'status'=>'3'));
						}else{
							$this->_connection->rollback(); // rollback transaction over failure
							$this->flashMessenger()->addMessage("error^ Unsuccessful to update batch details.");
						}
					endforeach;
					if($assetDtlsId){
						$result1 = $this->getDefinedTable(Store\AssetOpeningTable::class)->save(array('id'=>$this->_id,'status'=>'3','modified'=>_modified));
						if($result1):
							$this->_connection->commit(); // commit transaction over success
							$this->flashMessenger()->addMessage("success^ Successfully updated new asset No : ". $opening['asset']);
						else:
							$this->_connection->rollback(); // rollback transaction over failure
							$this->flashMessenger()->addMessage("error^ Failed to update asset.");
						endif;
					}else{
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Unsuccessful to new update asset details");
					}
				}else{
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Unsuccessful to update new asset");
				}
			return $this->redirect()->toRoute('inissue', array('action' =>'viewassetopening','id'=>$this->_id));
			endif;
		endif;
	}
	/**
	 * Update to store spare
	**/
	public function updatesspAction()
	{
		$this->init();
		
		$opening_stores = $this->getDefinedTable(Store\SspOpeningTable::class)->get($this->_id);
		
		foreach($opening_stores as $opening);
		foreach($this->getDefinedTable(Store\ItemTable::class)->get($opening['item']) as $itemdtls);

		if($itemdtls['item_group_id'] == 2):// asset or spare
		
       		if($opening['status']==2): // opening stock status = 2 pending.
			
			    $check_ssp =$this->getDefinedTable(Store\StoreSpareTable::class)->get(array('storespare' =>$opening['storespare'])); 
			    if(sizeof($check_ssp) > 0 ):
				    foreach($check_ssp as $row);
					    $new_qty = $row['quantity'] + $opening['quantity'];
						
					    $data = array(
						'id'         => $row['id'],
						'quantity'   => $new_qty,
						'author' => $this->_author,
						'modified' => $this->_modified,					
					);
					$data = $this->_safedataObj->rteSafe($data);
					$this->_connection->beginTransaction(); //***Transaction begins here***//
					$sspID = $this->getDefinedTable(Store\StoreSpareTable::class)->save($data); 
					if($sspID > 0){
						$opening_dtls = $this->getDefinedTable(Store\SspOpeningDetailsTable::class)->get(array('storespare' =>$opening['id'],'status'=>'2','author'=>$this->_author));
						$ssp_dtls = $this->getDefinedTable(Store\StoreSpareDetailsTable::class)->get(array('storespare' =>$opening['id']));
						foreach($ssp_dtls as $row);
						foreach($opening_dtls as $detail):
							$sspDtls = array(
								'id'             => $row['id'],
								'quantity'       => $detail['quantity'] + $row['quantity'],
								'rate'           => $detail['rate'],
								'amount'         => $detail['amount'],
								'author'         => $this->_author,
								'created'        => $this->_created,
								'modified'       => $this->_modified,
							);
							$sspDtls = $this->_safedataObj->rteSafe($sspDtls);
							$sspDtlsId = $this->getDefinedTable(Store\StoreSpareDetailsTable::class)->save($sspDtls);
							if($sspDtlsId){
							$this->getDefinedTable(Store\SspOpeningDetailsTable::class)->save(array('id'=>$detail['id'],'status'=>'3'));
							}else{
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("error^ Unsuccessful to update store and spare details.");
							}
						endforeach;
						if($sspDtlsId){
							$result1 = $this->getDefinedTable(Store\SspOpeningTable::class)->save(array('id'=>$this->_id,'status'=>'3','modified'=>_modified));
							if($result1):
								$this->_connection->commit(); // commit transaction over success
								$this->flashMessenger()->addMessage("success^ Successfully updated new store and spare,Store and Spare No : ". $opening['storespare']);
							else:
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("error^ Failed to update in opening stock.");
							endif;
						}else{
							echo "This is my testing"; exit;
							$this->_connection->rollback(); // rollback transaction over failure
							$this->flashMessenger()->addMessage("error^ Unsuccessful to new update store and spare details");
						}
					}else{
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Unsuccessful to update new store and spare");
					}
					return $this->redirect()->toRoute('inissue', array('action' =>'viewsspopening','id'=>$this->_id));
					
				else: 
				
					$data = array(
							'storespare' => $opening['storespare'],
							'item'       => $opening['item'],
							'uom'        => $opening['uom'],	
							'quantity'   => $opening['quantity'],
							'barcode'    => '',
							'storespare_date' => $opening['opening_date'],
							'expiry_date' => '',
							'status' => '3',
							'author' => $this->_author,
							'created' => $this->_created,
							'modified' => $this->_modified,					
					);
					$data = $this->_safedataObj->rteSafe($data);
					//
					$this->_connection->beginTransaction(); //***Transaction begins here***//
					$sspID = $this->getDefinedTable(Store\StoreSpareTable::class)->save($data); 
					if($sspID > 0){
						$opening_dtls = $this->getDefinedTable(Store\SspOpeningDetailsTable::class)->get(array('storespare' =>$opening['id'],'status'=>'2','author'=>$this->_author));
						foreach($opening_dtls as $detail):
							$sspDtls = array(
								'storespare'     => $sspID,
								'location'       => $detail['location'],
								'quantity'       => $detail['quantity'],
								'rate'           => $detail['rate'],
								'amount'           => $detail['amount'],
								'author'         => $this->_author,
								'created'        => $this->_created,
								'modified'       => $this->_modified,
							);
							$sspDtls = $this->_safedataObj->rteSafe($sspDtls);
							$sspDtlsId = $this->getDefinedTable(Store\StoreSpareDetailsTable::class)->save($sspDtls);
							//print_r($sspDtls); exit;
							if($sspDtlsId){
								$this->getDefinedTable(Store\SspOpeningDetailsTable::class)->save(array('id'=>$detail['id'],'status'=>'3'));
							}else{
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("error^ Unsuccessful to update store and spare details.");
							}
						endforeach;
						if($sspDtlsId){
							$result1 = $this->getDefinedTable(Store\SspOpeningTable::class)->save(array('id'=>$this->_id,'status'=>'3','modified'=>_modified));
							if($result1):
								$this->_connection->commit(); // commit transaction over success
								$this->flashMessenger()->addMessage("success^ Successfully updated new store and spare,Store and Spare No : ". $opening['storespare']);
							else:
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("error^ Failed to update in opening stock.");
							endif;
						}else{
							$this->_connection->rollback(); // rollback transaction over failure
							$this->flashMessenger()->addMessage("error^ Unsuccessful to new update store and spare details");
						}
					}else{
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Unsuccessful to update new store and spare");
					}
			        return $this->redirect()->toRoute('inissue', array('action' =>'viewsspopening','id'=>$this->_id));
			    endif;
		    endif;
		endif;
		return new ViewModel(array());
	}
	/**
	 * editsspopeningdetail Action
	 */
	public function editsspopeningdetailAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$openingdtl_id = $form['openingdtl_id'];
			$openingdtls = $this->getDefinedTable(Store\SspOpeningDetailsTable::class)->get($openingdtl_id);
			foreach($openingdtls as $openingdtl);
			$openings = $this->getDefinedTable(Store\SspOpeningTable::class)->get($openingdtl['storespare']);
			foreach($openings as $opening);
			
			if($openingdtl['status']==3):
			   
				$sspID = $this->getDefinedTable(Store\StoreSpareTable::class)->getColumn(array('item' => $opening['item'],'storespare'=>$opening['storespare']),'id');
				if($sspID > 0):
				
					$sspdtls = $this->getDefinedTable(Store\StoreSpareDetailsTable::class)->get(array('storespare'=>$sspID,'location'=>$openingdtl['location']));
					if(sizeof($sspdtls)>0):
						foreach($sspdtls as $row);
						$nullify_qty = $row['quantity'] - $openingdtl['quantity'];
						$qty = $nullify_qty + $form['quantity'];
						$rate = $form['amount'] / $qty;
						$ssp_dtl_data = array(
							'id'             => $row['id'],
							'quantity'       => $qty,
							'rate'           => $rate,
							'amount'         => $form['amount'],
							'author'         => $this->_author,
							'modified'       => $this->_modified,
						);
						$ssp_dtl_data = $this->_safedataObj->rteSafe($ssp_dtl_data);
						$this->_connection->beginTransaction(); //***Transaction begins here***//
						$result = $this->getDefinedTable(Store\StoreSpareDetailsTable::class)->save($ssp_dtl_data); 
						if($result):
							$qty = $this->getDefinedTable(Store\StoreSpareDetailsTable::class)->getSum(array('storespare'=>$sspID),'quantity');
							$result1 = $this->getDefinedTable(Store\StoreSpareTable::class)->save(array('id'=>$sspID,'quantity'=>$qty,'modified'=>$this->_modified));
							if($result1):
								$data = array(
									'id'            => $openingdtl['id'],
									'quantity'      => $form['quantity'],
									'rate'           => $rate,
									'amount'         => $form['amount'],
									'status'        => '3',
									'author'        => $this->_author,
									'modified'      => $this->_modified,
								);
								$data = $this->_safedataObj->rteSafe($data);
								$result3 = $this->getDefinedTable(Store\SspOpeningDetailsTable::class)->save($data);
								if($result3):
									$qty = $this->getDefinedTable(Store\SspOpeningDetailsTable::class)->getSUM(array('sd.storespare'=>$opening['id']),'sd.quantity');
									$result4 = $this->getDefinedTable(Store\SspOpeningTable::class)->save(array('id'=>$opening['id'],'quantity'=>$qty,'modified'=>$this->_modified));
									if($result4):
										$this->_connection->commit(); // commit transaction over success
										$this->flashMessenger()->addMessage("success^ Successfully updated opening store and spare details.");
									else:
										$this->_connection->rollback(); // rollback transaction over failure
										$this->flashMessenger()->addMessage("error^ Failed to update sum opening store and spare table.");
									endif;
								else:
									$this->_connection->rollback(); // rollback transaction over failure
									$this->flashMessenger()->addMessage("error^ Failed to update opening store and spare details.");
								endif;
							else:
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("error^ Failed to update the sum of quantity in opening store and store table.");
							endif;
						else:
							$this->_connection->rollback(); // rollback transaction over failure
							$this->flashMessenger()->addMessage("error^ Failed to update ssp details.");
						endif;
					else:
						$this->flashMessenger()->addMessage("notice^ Store and Spare not found. Please check.");
					endif;
				else:
					$this->flashMessenger()->addMessage("notice^ Store and Spare  not found in store and spare table. Please check.");
				endif;
			else:
			    $rate = $form['amount']/$form['quantity'];
				$data = array(
					'id'            => $openingdtl['id'],
					'quantity'      => $form['quantity'],
					'rate'          => $rate,
					'amount'        => $form['amount'],
					'status'        => '2',
					'author'        => $this->_author,
					'modified'      => $this->_modified,
				);
				$data = $this->_safedataObj->rteSafe($data);
				$this->_connection->beginTransaction(); //***Transaction begins here***//
				$result = $this->getDefinedTable(Store\SspOpeningDetailsTable::class)->save($data);
				if($result):
					$qty = $this->getDefinedTable(Store\SspOpeningDetailsTable::class)->getSMSUM(array('storespare'=>$opening['id']),'quantity');
					$result1 = $this->getDefinedTable(Store\SspOpeningTable::class)->save(array('id'=>$opening['id'],'quantity'=>$qty,'modified'=>$this->_modified));
					if($result1):
						$this->_connection->commit(); // commit transaction over success
						$this->flashMessenger()->addMessage("success^ Successfully updated opening store and spare details. Please update to the main store and store table.");
					else:
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Failed to update sum in opening store and spare.");
					endif;
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to update opening store and spare details.");
				endif;
			endif;
			return $this->redirect()->toRoute('inissue',array('action' => 'viewsspopening','id'=>$opening['id']));
		}
		$ViewModel = new ViewModel(array(
				'title'       => 'Edit Opening Details',
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'openingdtls' => $this->getDefinedTable(Store\SspOpeningDetailsTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
							
	/**
	 * deletestockopeningdetail Action
	 */
	public function editassetopeningdetailAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$openingdtl_id = $form['openingdtl_id'];
			$openingdtls = $this->getDefinedTable(Store\AssetOpeningDetailsTable::class)->get($openingdtl_id);
			foreach($openingdtls as $openingdtl);
			//echo "<pre>"; print_r($openingdtls); exit;
			$openings = $this->getDefinedTable(Store\AssetOpeningTable::class)->get($openingdtl['asset']);
			foreach($openings as $opening);
			
			if($openingdtl['status']==3):
			
				$assetID = $this->getDefinedTable(Store\AssetTable::class)->getColumn(array('item' => $opening['item'],'asset'=>$opening['asset']),'id');
				//echo $assetID; exit;
				if($assetID > 0):
				
					$assetdtls = $this->getDefinedTable(Store\AssetDetailsTable::class)->get(array('asset'=>$assetID,'location'=>$openingdtl['location']));
					if(sizeof($assetdtls)>0):
						foreach($assetdtls as $row);
						$nullify_qty = $row['quantity'] - $openingdtl['quantity'];
						$qty = $nullify_qty + $form['quantity'];
						$asset_dtl_data = array(
								'id'             => $row['id'],
								'quantity'       => $qty,
								'rate'           => $form['rate'],
								'author'         => $this->_author,
								'modified'       => $this->_modified,
						);
						$asset_dtl_data = $this->_safedataObj->rteSafe($asset_dtl_data);
						$this->_connection->beginTransaction(); //***Transaction begins here***//
						$result = $this->getDefinedTable(Store\AssetDetailsTable::class)->save($asset_dtl_data); 
						if($result):
							$qty = $this->getDefinedTable(Store\AssetDetailsTable::class)->getSum(array('asset'=>$assetID),'quantity');
							$result1 = $this->getDefinedTable(Store\AssetTable::class)->save(array('id'=>$assetID,'quantity'=>$qty,'modified'=>$this->_modified));
							if($result1):
								$data = array(
										'id'            => $openingdtl['id'],
										'quantity'      => $form['quantity'],
										'rate'          => $form['rate'],
										'status'        => '3',
										'author'        => $this->_author,
										'modified'      => $this->_modified,
								);
								$data = $this->_safedataObj->rteSafe($data);
								$result3 = $this->getDefinedTable(Store\AssetOpeningDetailsTable::class)->save($data);
								if($result3):
									$qty = $this->getDefinedTable(Store\AssetOpeningDetailsTable::class)->getSMSUM(array('asset'=>$opening['id']),'quantity');
									$result4 = $this->getDefinedTable(Store\AssetOpeningTable::class)->save(array('id'=>$opening['id'],'quantity'=>$qty,'modified'=>$this->_modified));
									if($result4):
										$this->_connection->commit(); // commit transaction over success
										$this->flashMessenger()->addMessage("success^ Successfully updated opening assets details.");
									else:
										$this->_connection->rollback(); // rollback transaction over failure
										$this->flashMessenger()->addMessage("error^ Failed to update sum opening asset table.");
									endif;
								else:
									$this->_connection->rollback(); // rollback transaction over failure
									$this->flashMessenger()->addMessage("error^ Failed to update opening assets details.");
								endif;
							else:
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("error^ Failed to update the sum of quantity in opening asset table.");
							endif;
						else:
							$this->_connection->rollback(); // rollback transaction over failure
							$this->flashMessenger()->addMessage("error^ Failed to update asset details.");
						endif;
					else:
						$this->flashMessenger()->addMessage("notice^ Asset not found. Please check.");
					endif;
				else:
					$this->flashMessenger()->addMessage("notice^ Store and Spare  not found in asset table. Please check.");
				endif;
			else:
				$data = array(
						'id'            => $openingdtl['id'],
						'quantity'      => $form['quantity'],
						'rate'          => $form['rate'],
						'status'        => '2',
						'author'        => $this->_author,
						'modified'      => $this->_modified,
				);
				$data = $this->_safedataObj->rteSafe($data);
				$this->_connection->beginTransaction(); //***Transaction begins here***//
				$result = $this->getDefinedTable(Store\AssetOpeningDetailsTable::class)->save($data);
				if($result):
					$qty = $this->getDefinedTable(Store\AssetOpeningDetailsTable::class)->getSMSUM(array('asset'=>$opening['id']),'quantity');
					$result1 = $this->getDefinedTable(Store\SspOpeningTable::class)->save(array('id'=>$opening['id'],'quantity'=>$qty,'modified'=>$this->_modified));
					if($result1):
						$this->_connection->commit(); // commit transaction over success
						$this->flashMessenger()->addMessage("success^ Successfully updated opening asset details. Please update to the main asset table.");
					else:
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Failed to update sum in opening asset.");
					endif;
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to update opening asset details.");
				endif;
			endif;
			return $this->redirect()->toRoute('inissue',array('action' => 'viewassetopening','id'=>$opening['id']));
		}
		$ViewModel = new ViewModel(array(
				'title'       => 'Edit Opening Details',
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'openingdtls' => $this->getDefinedTable(Store\AssetDetailsTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	 /* OPENING STOCK ENTRY
	 * Fetch items via item group ID
	 */
	public function getitemsAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		$group_id = $form['item_group'];
		
		if($group_id == '1'):
			$items = $this->getDefinedTable(Store\ItemTable::class)->get(array('i.item_group' => $group_id));
		else:
			$items = $this->getDefinedTable(Store\ItemTable::class)->get(array('i.item_group' => $group_id));
		endif;
		$stock_items.="<option value=''></option>";
		foreach($items as $item):
		    $item_id =  $item['id'];
			if($item_id):
				$stock_items .="<option value='".$item_id."'>".$item['name']."</option>";
			endif;
		endforeach;
		echo json_encode(array(
				'stock_items' => $stock_items,
		));
		exit;
	}
}




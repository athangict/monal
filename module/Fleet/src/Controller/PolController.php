<?php
namespace Fleet\Controller;

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
use Fleet\Model As Fleet;
class PolController extends AbstractActionController
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
	 * pol index action
	 */
	public function indexAction()
	{
		$this->init();		
			
		return new ViewModel( array(
				'title' => "POL Mangement Setup",
		));
	}
	/*
	 *pol Action
	*/
	public function polAction()
     {
       $this->init();
	   $year = '';
	   $month = '';
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			
			$year = $form['year'];
			$month = $form['month'];
			if(strlen($month)==1){
				$month = '0'.$month;
			}
		}else{
			$month = ($month == '')?date('m'):$month;
			$year = ($year == '')? date('Y'):$year;
		}
		$month = ($month == '')?date('m'):$month;
		$year = ($year == '')? date('Y'):$year;
		
		$minYear = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->getMin('sanction_order_date');
		$minYear = ($minYear == "")?date('Y-m-d'):$minYear;
		$minYear = date('Y', strtotime($minYear));
		$data = array(
			'year' => $year,
			'month' => $month,
			'minYear' => $minYear,
		);
		$results = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->getLocDateWise('sanction_order_date',$year,$month,array('status'=>array(1,2,3,4,6)));
		//print_r($results); exit;
		return new ViewModel(array(
			'title' 	  => 'POL Management',
			'data'        => $data,
			'tr_sanctions' => $results,
			'subheadObj'   	  => $this->getDefinedTable(Accounts\SubheadTable::class),
			'employeeObj'   	  => $this->getDefinedTable(Hr\EmployeeTable::class),
			'locationObj' 	  => $this->getDefinedTable(Administration\LocationTable::class),
		));                
     }
    /**
	 * Get gettransportDetails Action
	**/
	public function gettransportDetailsAction()
	{
		$this->init();		
		$form = $this->getRequest()->getPost();		
		$assets_id = $form['assets_id'];
		
		$getso_max_id  = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->getMaxSID('so.id',array('so.transport' =>$assets_id));
		$getvl_max_id  = $this->getDefinedTable(Fleet\TransportVehicleLogTable::class)->getMaxVLID('vl.id',array('vl.sanction_order' =>$getso_max_id));
        $veg_logs = $this->getDefinedTable(Fleet\TransportVehicleLogTable::class)->get(array('vl.id'=>$getvl_max_id));
		foreach($veg_logs as $veg_log);
			$final_reading = $veg_log['final_reading'];
			$closing_tank_balance = $veg_log['closing_tank_balance'];
			$f_reading = (is_numeric($final_reading))?$final_reading:"0.00";
			$c_tank_balance = (is_numeric($closing_tank_balance))?$closing_tank_balance:"0.00";
			$booloan = ($f_reading > 0 && $c_tank_balance > 0)?'1':'0';
		echo json_encode(array(
			//'locs' => $locs,
			//'driver' =>$driver,
			'final_reading' =>$f_reading,
			'closing_tank_balance' =>$c_tank_balance,
			'booloan' =>$booloan,
		));
		exit;
	}
	
	/**
	 *addpol action
	 */
	public function addpolAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()){
			
			$form = $this->getRequest()->getpost();					
			
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_userloc,'prefix');	
			$date = date('ym',strtotime($form['sanction_order_date']));			
			$tmp_SONo = "PL"."SO".$date; 			
			$results = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->getMonthlySO($tmp_SONo);
			
			$so_no_list = array();
			foreach($results as $result):
				array_push($so_no_list, substr($result['sanction_order_no'], 8));
			endforeach;
			$next_serial = max($so_no_list) + 1;	
			switch(strlen($next_serial)){
				case 1: $next_so_serial = "000".$next_serial; break;
				case 2: $next_so_serial = "00".$next_serial;  break;
				case 3: $next_so_serial = "0".$next_serial;   break;
				default: $next_so_serial = $next_serial;       break;
			}	
			$so_no = $tmp_SONo.$next_so_serial;
			
			$data = array(
				'sanction_order_no'   => $so_no,
				'sanction_order_date' => $form['sanction_order_date'], 
				'head'           	  => $form['head'],				
				'subhead'             => $form['subhead'],
				'driver'              => $form['transporter_fcb'],
				'location'            => $form['location'],
				'start_date'          => $form['start_date'],
				'end_date'            => $form['end_date'],
				'opening_tank'        => $form['tank_balance'],
				'closing_tank'        => $form['tank_closing'],
				'last_reading'        => $form['last_km_reading'],
				'present_reading'     => $form['current_km_reading'],
				'total_fuel'          => $form['total_refuel'],
				'total_fuel_consumed' => $form['total_fuel_consumed'],
				'total_km'            => $form['total_kilometer'],
				//'rate'            	  => $form['rate'],
				'amount'              => $form['amount'],
				'remark'              => $form['note'],
				'party'               => $form['party'],
				'ref_no'              => $form['ref_no'],
				'license_plate'              => $form['license_plate'],
				'status' 		      => '1', 
				'author'              => $this->_author,
				'created'             => $this->_created,
				'modified'            => $this->_modified
			);
			//echo'<pre>';print_r($data); exit;
			$data   = $this->_safedataObj->rteSafe($data);
            $this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->save($data);
			if($result > 0){ 
				$location              = $form['location'];
				$cost_center           = $form['cost_center'];
				$initial_reading       = $form['initial_reading'];
				$final_reading         = $form['final_reading'];
				$km_covered            = $form['km_covered'];
				$opening_tank_balance  = $form['opening_tank_balance'];
				$refuelling            = $form['refuelling'];
				$total_fuel            = $form['total_fuelling'];
				$milage                = $form['millage'];
				$fuel_consumed         = $form['fuel_consumed'];
				$closing_tank_balance  = $form['closing_tank_balance'];
				$rate  					= $form['r_rate'];
				$r_amount  					= $form['r_amount'];
				for($i=0; $i < sizeof($initial_reading); $i++):
				
				$date = date('Y-m-d');
				$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($location_id,'prefix');	
				$date = date('ym',strtotime($date));			
				$tmp_CMNo = $location_prefix."CM".$date; 			
				$answers = $this->getDefinedTable(Fleet\TransportCashMemoTable::class)->getMonthlyCM($tmp_CMNo);
				
				$cm_no_list = array();
				foreach($answers as $answer):
					array_push($cm_no_list, substr($answer['cash_memo_no'], 8));
				endforeach;
				$next_serial = max($cm_no_list) + 1 + $i;	
				switch(strlen($next_serial)){
					case 1: $next_cm_serial = "000".$next_serial; break;
					case 2: $next_cm_serial = "00".$next_serial;  break;
					case 3: $next_cm_serial = "0".$next_serial;   break;
					default: $next_cm_serial = $next_serial;       break;
				}	
			    $cm_no = $tmp_CMNo.$next_cm_serial;
				if(isset($initial_reading[$i]) && is_numeric($initial_reading[$i])):
					$so_details = array(
						'sanction_order'       => $result,
						'cash_memo_no'         => $cm_no,
						'initial_reading'      => $initial_reading[$i],
						'final_reading'        => $final_reading[$i],
						'km_covered'           => $km_covered[$i],
						'opening_tank_balance' => $opening_tank_balance[$i],
						'refuelling' 	       => $refuelling[$i],
						'total_fuel' 	       => $total_fuel[$i],
						'milage' 	           => $milage[$i],
						'fuel_consumed' 	   => $fuel_consumed[$i],
						'closing_tank_balance' => $closing_tank_balance[$i],
						'location'             => $location,
						'rate'          		=> $rate[$i],
						'amount'          		=> $r_amount[$i],
						'closing_tank_balance' => $closing_tank_balance[$i],
						'author'               => $this->_author,
						'created'              => $this->_created,
						'modified'             => $this->_modified
					);
					//echo'<pre>';print_r($so_details); exit;
		     		$so_details   = $this->_safedataObj->rteSafe($so_details);
			     	$this->getDefinedTable(Fleet\TransportVehicleLogTable::class)->save($so_details);		
				   	endif; 		     
				endfor;
                                $this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Successfully added new POL Management :". $so_no);
				return $this->redirect()->toRoute('pol', array('action' =>'viewpol', 'id' => $result));
			}
			else{
                                $this->_connection->rollback(); // rollback transaction on failure
				$this->flashMessenger()->addMessage("error^ Failed to add new POL Management");
				return $this->redirect()->toRoute('pol');
			}		
		}	
		return new ViewModel( array(
			'title'           => "Add POL ",
			'id'             =>$this->_id,
			'locationObj' 	  => $this->getDefinedTable(Administration\LocationTable::class),
			//'party' => $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('head'=>[76,79,90,91,92,93,94,95,96,97,112,118])),
			'party' 		  => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>array(5,7))),
			'head' 			  => $this->getDefinedTable(Accounts\HeadTable::class)->get(array('h.id'=>array(259,252,256))),
			'head1' 		  => $this->getDefinedTable(Accounts\HeadTable::class)->get(array('h.id'=>array(142,144,233,234,235,236,65))),
			'rowsets'         => $this->getDefinedTable(Accounts\AssetsTable::class)->getAll(),
			'veg_logs'        => $this->getDefinedTable(Fleet\TransportVehicleLogTable::class)->getAll(),
			'rowsets'         => $this->getDefinedTable(Accounts\AssetsTable::class)->getAll(),
			'activities'  	  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'driver'  	  => $this->getDefinedTable(Hr\EmployeeTable::class)->getAllEmp(),
		));
	}
	
	/**
	 *editpol action
	 */
	public function editpolAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			
			$form = $this->getRequest()->getpost();
			$data = array(
			    'id'                  => $form['sanction_order_id'],
				'sanction_order_date' => $form['sanction_order_date'], 
				'head'           	  => $form['head'],				
				'subhead'             => $form['subhead'],
				'driver'              => $form['transporter_fcb'],
				'location'            => $form['location'],
				'start_date'          => $form['start_date'],
				'end_date'            => $form['end_date'],
				'opening_tank'        => $form['tank_balance'],
				'closing_tank'        => $form['tank_closing'],
				'last_reading'        => $form['last_km_reading'],
				'present_reading'     => $form['current_km_reading'],
				'total_fuel'          => $form['total_refuel'],
				'total_fuel_consumed' => $form['total_fuel_consumed'],
				'total_km'            => $form['total_kilometer'],
				//'rate'            	  => $form['rate'],
				'amount'              => $form['amount'],
				'remark'              => $form['note'],
				'party'               => $form['party'],
				'ref_no'              => $form['ref_no'],
				//'license_plate'              => $form['license_plate'],
				'status' 		      => '1', 
				'author'              => $this->_author,
				'created'             => $this->_created,
				'modified'            => $this->_modified
			);
			//print_r($data); exit;
			$data   = $this->_safedataObj->rteSafe($data);
            $this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->save($data);
			if($result > 0){ 
				$vel_log_id            = $form['vel_log_id'];
				$location              = $form['location'];
				//$cost_center           = $form['cost_center'];
				$initial_reading       = $form['initial_reading'];
				$final_reading         = $form['final_reading'];
				$km_covered            = $form['km_covered'];
				$opening_tank_balance  = $form['opening_tank_balance'];
				$refuelling            = $form['refuelling'];
				$total_fuel            = $form['total_fuelling'];
				$rate                = $form['r_rate'];
				$amount                = $form['r_amount'];
				$milage                = $form['millage'];
				$fuel_consumed         = $form['fuel_consumed'];
				$closing_tank_balance  = $form['closing_tank_balance'];
				$delete_rows = $this->getDefinedTable(Fleet\TransportVehicleLogTable::class)->getNotIn($vel_log_id, array('sanction_order' => $result));
				$date = date('Y-m-d');
				for($i=0; $i < sizeof($initial_reading); $i++):
				$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_userloc,'prefix');	
				$date = date('ym',strtotime($date));			
				$tmp_CMNo = $location_prefix."CM".$date; 			
				$answers = $this->getDefinedTable(Fleet\TransportCashMemoTable::class)->getMonthlyCM($tmp_CMNo);
				
				$cm_no_list = array();
				foreach($answers as $answer):
					array_push($cm_no_list, substr($answer['cash_memo_no'], 8));
				endforeach;
				$next_serial = max($cm_no_list) + 1 + $i;	
				switch(strlen($next_serial)){
					case 1: $next_cm_serial = "000".$next_serial; break;
					case 2: $next_cm_serial = "00".$next_serial;  break;
					case 3: $next_cm_serial = "0".$next_serial;   break;
					default: $next_cm_serial = $next_serial;       break;
				}	
			    $cm_no = $tmp_CMNo.$next_cm_serial;
				if(isset($initial_reading[$i]) && is_numeric($initial_reading[$i])):
				if($vel_log_id[$i]>0):
					$so_details = array(
					    'id'                   => $vel_log_id[$i],
						'sanction_order'       => $result,
						'cash_memo_no'         => $cm_no,
						'initial_reading'      => $initial_reading[$i],
						'final_reading'        => $final_reading[$i],
						'km_covered'           => $km_covered[$i],
						'opening_tank_balance' => $opening_tank_balance[$i],
						'refuelling' 	       => $refuelling[$i],
						'total_fuel' 	       => $total_fuel[$i],
						'milage' 	           => $milage[$i],
						'fuel_consumed' 	   => $fuel_consumed[$i],
						'closing_tank_balance' => $closing_tank_balance[$i],
						'rate' 					=> $rate[$i],
						'amount' 				=> $amount[$i],
						'location'             => $form['location'],
						//'cost_center'          => $cost_center[$i],
						'closing_tank_balance' => $closing_tank_balance[$i],
						'author'               => $this->_author,
						'modified'             => $this->_modified
					);else:
					$so_details = array(
					   'sanction_order'       => $result,
						'cash_memo_no'         => $cm_no,
						'initial_reading'      => $initial_reading[$i],
						'final_reading'        => $final_reading[$i],
						'km_covered'           => $km_covered[$i],
						'opening_tank_balance' => $opening_tank_balance[$i],
						'refuelling' 	       => $refuelling[$i],
						'total_fuel' 	       => $total_fuel[$i],
						'milage' 	           => $milage[$i],
						'fuel_consumed' 	   => $fuel_consumed[$i],
						'closing_tank_balance' => $closing_tank_balance[$i],
						'rate' 					=> $rate[$i],
						'amount' 				=> $amount[$i],
						'location'             => $form['location'],
						//'cost_center'          => $cost_center[$i],
						'closing_tank_balance' => $closing_tank_balance[$i],
						'author'               => $this->_author,
						'modified'             => $this->_modified
					);
					endif;
					//echo '<pre>';print_r($so_details); 
		     		$so_details   = $this->_safedataObj->rteSafe($so_details);
			     	$this->getDefinedTable(Fleet\TransportVehicleLogTable::class)->save($so_details);		
				   	endif; 		     
				endfor;
				foreach($delete_rows as $delete_row):
					$this->getDefinedTable(Fleet\TransportVehicleLogTable::class)->remove($delete_row['id']);
				endforeach;
				$this->_connection->commit(); // commit transaction on success
				$so_no = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->getColumn($form['sanction_order_id'],'sanction_order_no');
				$this->flashMessenger()->addMessage("success^ Successfully edited new POL Management :".$so_no);
				return $this->redirect()->toRoute('pol', array('action' =>'viewpol', 'id' => $form['sanction_order_id']));
			}
			else{
                $this->_connection->rollback(); // rollback transaction on failure
				$this->flashMessenger()->addMessage("error^ Failed to new POL Management");
				return $this->redirect()->toRoute('pol');
			}		
		}
		$cash_memos = $this->getDefinedTable(Fleet\TransportCashMemoTable::class)->get(array('sanction_order'=>$this->_id));	
            if(sizeof($cash_memos) > 0):
			    foreach($cash_memos as $cash_memos):
					$this->getDefinedTable(Fleet\TransportCashMemoTable::class)->remove($cash_memos['id']);
				endforeach;
            endif;		
		return new ViewModel( array(
			'title'           => "Edit POL ",
			'id'              =>$this->_id,
			'locationObj' 	  => $this->getDefinedTable(Administration\LocationTable::class),
			'assetObj'   	  => $this->getDefinedTable(Accounts\AssetsTable::class),
		    'tr_sanctions'    => $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->get($this->_id),
			'employee_driver' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAll(),
			'rowsets'         => $this->getDefinedTable(Accounts\AssetsTable::class)->getAll(),
			'vel_logObj'        => $this->getDefinedTable(Fleet\TransportVehicleLogTable::class),
			'rowsets'         => $this->getDefinedTable(Fleet\VehicleRegisterTable::class)->getAll(),
			'activities'  	  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'heads'				=> $this->getDefinedTable(Accounts\HeadTable::class)->get(array('h.id'=>array(176,179,198))),
			'sh' 				=> $this->getDefinedTable(Accounts\SubheadTable::class)->getAll(),
			'subheadObj' 		  => $this->getDefinedTable(Accounts\SubheadTable::class),
			'head1' 		  => $this->getDefinedTable(Accounts\HeadTable::class)->get(array('h.id'=>array(142,144,233,234,235,236,65))),
		));
	}
	/**
	 * Commit pol
	*/
    public function commitpolAction()
      { 
      	$this->init();
		//if($this->getRequest()->isPost()){
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
        $admin_loc_array = explode(',',$admin_locs);
		//if($this->getRequest()->isPost()){
		//	$form = $this->getRequest()->getPost();
			$polresult = $this->_id;
			$pol = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->get($this->_id);
		
		/*	foreach($postage as $postages):
				echo '<pre>';print_r($postages);
			endforeach;exit;*/
			$location = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('id'=>$this->_author),'location');
			$region = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('id'=>$this->_author),'region');
			
				$statusUpdate = array(
				'id' 	=> $polresult,
				'status' => 4,
				);
		
			$statusUpdate =  $this->_safedataObj->rteSafe($statusUpdate);
			$status= $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->save($statusUpdate);
			foreach($pol as $pols):
			//	echo '<pre>';print_r($pols);	endforeach;exit;
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(11,'prefix');
				$date = date('ym',strtotime(date('Y-m-d')));
				$tmp_VCNo = $loc.'-'.$prefix.$date;
				
				$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
				
				$pltp_no_list = array();
				foreach($results as $result):
					array_push($pltp_no_list, substr($result['voucher_no'], 14));
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
				
			$data2 = array(
				'voucher_date' 	  	=> date('Y-m-d'),
				'voucher_type' 	  	=> 11,
				'region' 	  		=> $region,
				'voucher_no' 	  	=> $voucher_no,
				'voucher_amount' 	=> $pols['amount'],
				'status' 	  		=> 4,
				'doc_id' 	  		=> "pol",
				'against' 	  	=> 0,
				'doc_type' 	  		=> " ",
				'remark' 	  		=> $pols['sanction_order_no'],
				'author' 			 =>$this->_author,
				'created' 			 =>$this->_created,
				'modified' 			 =>$this->_modified,
			);
			$data2 =  $this->_safedataObj->rteSafe($data2);
			$result2 = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data2);
			if($result2 > 0){
			$data3 = array(
				'transaction' 	=> $result2,
				'voucher_dates' => $data2['voucher_date'],
				'voucher_types' 	=> 11,
				'location' 	  	=> $location,
				'head' 	  		=> $pols['head'],
				'sub_head' 	  	=> $pols['subhead'],
				'activity'		=>$location,
				'debit' 	  	=> $data2['voucher_amount'],
				'credit' 	  	=> 0,
				'against' 	  	=> 0,
				'ref_no' 	  	=> $pols['ref_no'],
				'status' 	  	=> 4,
				'author' 		=>$this->_author,
				'created' 		=>$this->_created,
				'modified' 		=>$this->_modified,
			);
			$data3 =  $this->_safedataObj->rteSafe($data3);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($data3);
			$ref=$pols['party'];
				$subhead = $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('ref_id'=>$ref,'type'=>2),'id');
				$head = $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('id'=>$subhead),'head');
				$data4 = array(
					'transaction' 	=> $result2,
					'voucher_dates' => $data2['voucher_date'],
					'voucher_types' => 11,
					'location' 	  	=> $location,
					'head' 	  		=> $head,
					'sub_head' 	  	=> $subhead,
					'activity'      =>$location,
					'debit' 	  	=>0,
					'credit' 	  	=> $data2['voucher_amount'],
					'status' 	  	=> 4,
					'ref_no' 	  	=> 0,
					'activity'		=>$location,
					'author' 		=>$this->_author,
					'created' 		=>$this->_created,
					'modified' 		=>$this->_modified,
				);
			$data4 =  $this->_safedataObj->rteSafe($data4);
			$result4 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($data4);
			$data5 = array(
				'id' 	=> $pols['id'],
				'transaction' => $result2,
				);
			$data5 =  $this->_safedataObj->rteSafe($data5);
			$result5 = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->save($data5);
			}
			if($result5 > 0):
				$this->_connection->commit(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("success^ successfully committed the data");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("notice^ Failed to commit  data");
			endif;
		endforeach;
			return $this->redirect()->toRoute('pol', array('action'=>'viewpol','id'=>$polresult));
			
		
      	return new ViewModel(array());   	
      }
	/**
	 * Commit pol
	*/
    public function commitpolAAction()
      { 
      	$this->init();
        $location_id = $this->_userloc;
      	$vel_Logs = $this->getDefinedTable(Fleet\TransportVehicleLogTable::class)->get(array('sanction_order'=>$this->_id));
      	//$cash_memos = $this->getDefinedTable(Fleet\TransportCashMemoTable::class)->get(array('sanction_order'=>$this->_id));
      	if(sizeof($vel_Logs) > 0){
            //foreach($vel_Logs as $vel_Log):	
            //if($vel_Log['amount'] > 0):			
      	    $rmResults = $this->getDefaultTable("tp_sanction_order")->select(array('id'=>$this->_id));
            foreach ($rmResults as $row):
               $sanction_order_id = $row->id;
               $sanction_order_no = $row->sanction_order_no;
               $transport = $row->transport;
			   $voucher_amount = $row->payment_amt;
            endforeach;
      	    $loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($location_id, 'prefix');
      	    $locs = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($location_id, 'id');
      	    $voucherType = '4'; //Journal
      	    $prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($voucherType,'prefix');
      	     
      	    $date = date('ym',strtotime(date('Y-m-d')));
			$tmp_VCNo = $loc.$prefix.$date;
      	    $results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
			$pltp_no_list = array();
			 foreach($results as $result):
				  array_push($pltp_no_list, substr($result['voucher_no'], 8));
			 endforeach;
			$next_serial = max($pltp_no_list) + 1;
				
			switch(strlen($next_serial)){
			case 1: $next_pol_serial = "000".$next_serial; break;
			case 2: $next_pol_serial = "00".$next_serial;  break;
			case 3: $next_pol_serial = "0".$next_serial;   break;
			default: $next_pol_serial = $next_serial;       break;
			}	
			$voucher_no = $tmp_VCNo.$next_pol_serial;
      	    $data1 = array(
				'voucher_date'   => date('Y-m-d'),
				'voucher_type'   => $voucherType,
				'doc_id'         => $sanction_order_no,
				'doc_type'       => 'POL Management',
				'voucher_no'     =>$voucher_no,
				'voucher_amount' => $voucher_amount,
				'against' 	  	=> 0,
				'remark'         => 'Being the fuel adjustment',
				'status'         => 3, // status commited
				'author'         =>$this->_author,
				'created'        =>$this->_created,
				'modified'       =>$this->_modified,
      	    );      	 
      	    $data1 = $this->_safedataObj->rteSafe($data1);
      	    $this->_connection->beginTransaction(); //***Transaction begins here***//
      	    $result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
      	    $sub_headResults = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.type'=>'1', 'sh.head'=>array('135','140'),'sh.ref_id'=>$transport));
      	    $sub_heads = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.type'=>'1', 'sh.head'=>array('230'),'sh.ref_id'=>$transport));

			if($result > 0 && sizeof($sub_headResults) > 0){
					foreach($sub_headResults as $row);
                    foreach($vel_Logs as $vel_Log):				
					$tdetailsdata = array(
						'transaction'  => $result,
						'location'     => $vel_Log['location_id'],
						'activity'     => $vel_Log['cost_center_id'],
						'head'         => $row['head_id'],
						'sub_head'     => $row['id'],
						'debit'        => $vel_Log['amount'],
						'against' 	  	=> 0,
						'credit'       => '0.00',
						'ref_no'       => '',
						'type'         => '2', //System Generated
						'author'       => $this->_author,
						'created'      => $this->_created,
						'modified'     => $this->_modified,
					);   				
					$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
					$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
					endforeach;
				if($result1 > 0){
                    foreach($sub_heads as $sub_head);
				    $tdetailsdata1 = array(
						'transaction' 	=> $result,
						'location' 		=> $locs,
						'activity' 		=> '5',
						'head' 			=> 230, // Advance POL
						'sub_head' 		=> $sub_head['id'],// POL Heavy Vehicle or POL - Light Vehicle
						'debit' 		=> '0.00',
						'credit' 		=> $voucher_amount,
						'ref_no'		=> '',
						'type' 			=> '2', //System Generated
						'author' 		=>$this->_author,
						'created' 		=>$this->_created,
						'modified' 		=>$this->_modified,
					);
				   $tdetailsdata1 = $this->_safedataObj->rteSafe($tdetailsdata1);
				   $result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata1);
          	        if($result2 > 0){					
          	        //update the receipt status to commit
          	        $sanctionorderData = array(
          	        	'id'          =>$this->_id,
          	            'transaction' =>$result,
          	            'status'      =>'3',
          	            'modified'    =>$this->_modified,
          	        );
          	        $result3 = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->save($sanctionorderData);				
          	        $this->_connection->commit();
          	        $this->flashMessenger()->addMessage("success^ POL Management voucher Booked with transaction No. : ".$voucher_no);
          	        return $this->redirect()->toRoute('pol', array('action' =>'viewpol', 'id'=>$this->_id));
					}
					 $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->updateRecords($sanction_order_id,$result);			
				}		
				else{
					$this->flashMessenger()->addMessage("error^ Not able to commit and book the POL Management");
					return $this->redirect()->toRoute('pol', array('action' =>'viewrepol', 'id'=>$this->_id));
				}
            }  
      	    else{
      	        $this->_connection->rollback();
      	        $this->flashMessenger()->addMessage("error^ POL Management voucher cannot be booked. Please contact Administrator to map the heads and sub heads");
      	        return $this->redirect()->toRoute('pol', array('action' =>'viewpol', 'id'=>$this->_id));
      	    }	
       // endif;
        //endforeach;			
        }		
  	    else{
        	$this->flashMessenger()->addMessage("error^ Not able to commit and book the POL Management Or Add Cash Memo before you commit the POL Management");
        	return $this->redirect()->toRoute('pol', array('action' =>'viewpol', 'id'=>$this->_id));
        }      	
      	return new ViewModel(array());   	
      }
       /**
	 * pol action
	 */
	public function viewpolAction()
	{
		$this->init();	
		$params = explode("-", $this->_id);
		if (isset($params['1']) && $params['1'] == '1' && isset($params['2']) && $params['2'] > 0) {
			$flag = $this->getDefinedTable(Acl\NotifyTable::class)->getColumn($params['2'], 'flag'); 
				if($flag == "0") {
					$notify = array('id' => $params['2'], 'flag'=>'1');
					$this->getDefinedTable(Acl\NotifyTable::class)->save($notify); 	
				}				
		}
        $dispatch_ID = $params['0'];		
			
		return new ViewModel( array(
				'title' => "View POL Mangement",
				'id'             =>$this->_id,
                'tr_sanctions'   => $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->get($this->_id),
                'veg_logObj'     => $this->getDefinedTable(Fleet\TransportVehicleLogTable::class),
				'CMemoObj'        => $this->getDefinedTable(Fleet\TransportCashMemoTable::class),
				'userObj'   => $this->getDefinedTable(Administration\UsersTable::class),
				'transObj'   => $this->getDefinedTable(Accounts\TransactionTable::class),
				'transportObj'   => $this->getDefinedTable(Fleet\TransportTable::class),
				'headObj'   => $this->getDefinedTable(Accounts\HeadTable::class),
				'subheadObj'   => $this->getDefinedTable(Accounts\SubheadTable::class),
				'user_location'   => $this->_userloc,
				'userID'          => $this->_author,

		));
	}
	
	/**
	 *addcashmemo action
	 */
	public function addcashmemoAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();	
            if($form['total_amount'] > 0 && $form['total_payment_amount'] > 0 ){		
				$cash_memo_date         = $form['cash_memo_date'];
				$cash_memo_id           = $form['cash_memo_id'];
				$cash_memo_no           = $form['cash_memo_no'];
				$fuel_litter            = $form['fuel_litter'];
				$fuel_rate              = $form['fuel_rate'];
				$engine_oil_amt         = $form['engine_oil_amt'];
				$brake_oil_amt          = $form['brake_oil_amt'];
				$gear_oil_amt           = $form['gear_oil_amt'];
				$distilled_water_amt    = $form['distilled_water_amt'];
				$total_amount           = $form['total_amount'];
				$fuelling_location      = $form['fuelling_location'];
				for($i=0; $i < sizeof($total_amount); $i++):
				if(isset($fuel_litter[$i]) && is_numeric($total_amount[$i])):
					$cm_details = array(
						'sanction_order'       => $this->_id,
						'cash_memo_no'         => $cash_memo_no[$i],
						'cash_memo_date'       => $cash_memo_date[$i],
						'fuel_litter'          => $fuel_litter[$i],
						'fuel_rate'            => $fuel_rate[$i],
						'engine_oil_amt'       => $engine_oil_amt[$i],
						'brake_oil_amt' 	   => $brake_oil_amt[$i],
						'gear_oil_amt' 	       => $gear_oil_amt[$i],
						'distilled_water_amt'  => $distilled_water_amt[$i],
						'total_amount' 	       => $total_amount[$i],
						'fuelling_location'    => $fuelling_location[$i],
						'author'               => $this->_author,
						'created'              => $this->_created,
						'modified'             => $this->_modified
					);
					//print_r($cm_details); exit;
					$cm_details   = $this->_safedataObj->rteSafe($cm_details);
					$this->_connection->beginTransaction(); //***Transaction begins here***//
					$this->getDefinedTable(Fleet\TransportCashMemoTable::class)->save($cm_details);	
                    $vel_Log = array(
					    'id'             => $cash_memo_id[$i], 
						'sanction_order' => $this->_id,
						'amount'         => $total_amount[$i],
					);
				   $vel_Log   = $this->_safedataObj->rteSafe($vel_Log);
				   $this->_connection->commit(); // commit transaction on success
				   $this->getDefinedTable(Fleet\TransportVehicleLogTable::class)->save($vel_Log);					
				endif;									
				endfor;
				
				if($form['total_payment_amount'] > 0){
				$total_amount = array(
				    'id'          => $this->_id,
					'payment_amt' => $form['total_payment_amount'],
				
				);
			    $total_amount   = $this->_safedataObj->rteSafe($total_amount);
			    $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->save($total_amount);	
			    $this->flashMessenger()->addMessage("success^ Successfully added Cash Memo!!!");
			    return $this->redirect()->toRoute('pol', array('action' =>'viewpol', 'id' =>$this->_id));
			}
			}
			else{
				$this->_connection->rollback(); // rollback transaction on failure
				$this->flashMessenger()->addMessage("error^ Failed to add new Cash Memo");
				return $this->redirect()->toRoute('pol');
			}
        endif;			
		return new ViewModel( array(
			'title'           => "Add Cash Memo ",
			'id'               =>$this->_id,
			'locationObj' 	  => $this->getDefinedTable(Administration\LocationTable::class),
			'assetObj'   	  => $this->getDefinedTable(Accounts\AssetsTable::class),
			'employeeObj'     => $this->getDefinedTable(Hr\EmployeeTable::class),
			'rowsets'         => $this->getDefinedTable(Accounts\AssetsTable::class)->getAll(),
			'veh_logs'        => $this->getDefinedTable(Fleet\TransportVehicleLogTable::class)->get(array('sanction_order'=>$this->_id)),
			'rowsets'         => $this->getDefinedTable(Accounts\AssetsTable::class)->getAll(),
			'activities'  	  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
		));
	}
	/**
	 *addcashmemo action
	 */
	public function editcashmemoAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();	
            if($form['total_amount'] > 0 && $form['total_payment_amount'] > 0 ){				
				$cash_memo_id           = $form['cashmemo_id'];
				$cash_memo_date         = $form['cash_memo_date'];
				$cash_memo_no           = $form['cash_memo_no'];
				$fuel_litter            = $form['fuel_litter'];
				$fuel_rate              = $form['fuel_rate'];
				$engine_oil_amt         = $form['engine_oil_amt'];
				$brake_oil_amt          = $form['brake_oil_amt'];
				$gear_oil_amt           = $form['gear_oil_amt'];
				$distilled_water_amt    = $form['distilled_water_amt'];
				$total_amount           = $form['total_amount'];
				$fuelling_location      = $form['fuelling_location'];
				for($i=0; $i < sizeof($total_amount); $i++):
				if(isset($fuel_litter[$i]) && is_numeric($total_amount[$i])):
					$cm_details = array(
					    'id'                   => $this->_id,
						'sanction_order'       => $cash_memo_id[$i],
						'cash_memo_no'         => $cash_memo_no[$i],
						'cash_memo_date'       => $cash_memo_date[$i],
						'fuel_litter'          => $fuel_litter[$i],
						'fuel_rate'            => $fuel_rate[$i],
						'engine_oil_amt'       => $engine_oil_amt[$i],
						'brake_oil_amt' 	   => $brake_oil_amt[$i],
						'gear_oil_amt' 	       => $gear_oil_amt[$i],
						'distilled_water_amt'  => $distilled_water_amt[$i],
						'total_amount' 	       => $total_amount[$i],
						'fuelling_location'    => $fuelling_location[$i],
						'author'               => $this->_author,
						'created'              => $this->_created,
						'modified'             => $this->_modified
					);
					$cm_details   = $this->_safedataObj->rteSafe($cm_details);
					$this->_connection->beginTransaction(); //***Transaction begins here***//
					$this->getDefinedTable(Fleet\TransportCashMemoTable::class)->save($cm_details);	
                    $vel_Log = array(
						'id'             =>$cash_memo_id[$i],
						'sanction_order' =>$this->_id,
						'amount'         =>$total_amount[$i],
					);
				   $vel_Log   = $this->_safedataObj->rteSafe($vel_Log);
				    $this->_connection->commit(); // commit transaction on success
				   $this->getDefinedTable(Fleet\TransportVehicleLogTable::class)->save($vel_Log);		   					
				endif;				
				endfor;
				if($form['total_payment_amount'] > 0){
				$total_amount = array(
				    'id'          => $this->_id,
					'payment_amt' => $form['total_payment_amount'],
				
				);
			    $total_amount   = $this->_safedataObj->rteSafe($total_amount);
			    $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->save($total_amount);	
				$so_no = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->getColumn($form['sanction_order_id'],'sanction_order_no');
			    $this->flashMessenger()->addMessage("success^ Successfully edited Cash Memo!!!" .$so_no);
			    return $this->redirect()->toRoute('pol', array('action' =>'viewpol', 'id' =>$form['sanction_order_id']));
			}
			}
			else{
				$this->_connection->rollback(); // roll back transaction on failuer
				$this->flashMessenger()->addMessage("error^ Failed to edit new Cash Memo");
				return $this->redirect()->toRoute('pol');
			}
        endif;			
		return new ViewModel( array(
			'title'           => "Edit Cash Memo ",
			'id'              => $this->_id,
			'locationObj' 	  => $this->getDefinedTable(Administration\LocationTable::class),
			'assetObj'   	  => $this->getDefinedTable(Accounts\AssetsTable::class),
			'employeeObj'     => $this->getDefinedTable(Hr\EmployeeTable::class),
			'rowsets'         => $this->getDefinedTable(Accounts\AssetsTable::class)->getAll(),
		    'tr_sanctions'    => $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->get($this->_id),
			'cash_dtlObj'     => $this->getDefinedTable(Fleet\TransportCashMemoTable::class),
			'rowsets'         => $this->getDefinedTable(Accounts\AssetsTable::class)->getAll(),
			'activities'  	  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
		));
	}
	/**
	 * forward transport Action
	 *
	 */
	public function processpolAAAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()):		
			$form = $this->getRequest()->getPost()->toArray();		
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			if($form['action'] == "1"){ 
			        /* Send Request */
					$data = array(
						'id'			=> $form['tp_id'],
						'status' 		=> 6,
						'remark'        => $form['remarks'],
						'author'	    => $this->_author,
						'modified'      => $this->_modified,
				    );
					$message = "Successfully Applied";
					$desc = "New Transport expense Applied";
					/*Get users under destination location with sub role Depoy Manager*/
					$sourceLocation = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->getColumn($form['tp_id'], 'location');			
			}elseif($form['action'] == "2"){	 /* Received Request */			
				    $data = array(
						'id'			=> $form['tp_id'],
						'status' 		=> 5,
						'remark'        => $form['remarks'],
						'author'	    => $this->_author,
						'modified'      => $this->_modified,
		            );
					$message = "Successfully cancelled";
					$desc = "Transport expense cancelled";
					/*Get users under request location with sub role Depoy Manager*/
					$sourceLocation = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->getColumn($form['tp_id'], 'location');
			}	
			elseif($form['action'] == "3"){	 /* Received Request */			
				$data = array(
					'id'			=> $form['tp_id'],
					'status' 		=> 3,
					'remark'        => $form['remarks'],
					'author'	    => $this->_author,
					'modified'      => $this->_modified,
				);
				$message = "Successfully forwarded";
				$desc = "Transport expense forwarded";
				/*Get users under request location with sub role Depoy Manager*/
				$sourceLocation = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->getColumn($form['tp_id'], 'location');
		}	
			//print_r($data);exit;	
			$result = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->save($data);		
			if($result):
			    	$notification_data = array(
					    'route'         => 'pol',
						'action'        => 'viewpol',
						'key' 		    => $form['tp_id'],
						'description'   => $desc,
						'author'	    => $this->_author,
						'created'       => $this->_created,
						'modified'      => $this->_modified,   
					);
					//print_r($notification_data);exit;
					$notificationResult = $this->getDefinedTable(Acl\NotificationTable::class)->save($notification_data);
					//echo $notificationResult; exit;
					if($notificationResult > 0 ){	
						if($form['action'] == "1"):
                     		$depoyManagers = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>array('3')));
						elseif($form['action'] == "3"):
							$depoyManagers = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>array('5')));
						else:
							$depoyManagers = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>array('2')));
						endif;
						foreach($depoyManagers as $row):						    
						    $user_location_id = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($row['id'], 'location');
						    if($user_location_id == $sourceLocation ):						
							    $notify_data = array(
								    'notification' => $notificationResult,
									'user'    	   => $row['id'],
									'flag'    	 => '0',
									'desc'    	 => $desc,
									'author'	 => $this->_author,
									'created'    => $this->_created,
									'modified'   => $this->_modified,  
 								);
								//print_r($notify_data);exit;
								$notifyResult = $this->getDefinedTable(Acl\NotifyTable::class)->save($notify_data);
							endif;
						endforeach;
					}
				$this->_connection->commit(); // commit transaction over success
				$this->flashMessenger()->addMessage("success^".$message);
			else:
			    $this->_connection->rollback(); // rollback transaction over failure
			    $this->flashMessenger()->addMessage("error^ Cannot send good request");			  
			endif;
			return $this->redirect()->toRoute('pol',array('action'=>'viewpol','id'=>$form['tp_id']));
		endif; 		
		$viewModel =  new ViewModel(array(
			'title' => 'GRN',
			'id'  => $this->_id,
			'userID' => $this->_author,
		));	
		$viewModel->setTerminal('false');
        return $viewModel;	
	}
	/**
	 * forward transport Action
	 *
	 */
	public function processpolAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()):		
			$form = $this->getRequest()->getPost()->toArray();	
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			   /* Send Request */
					$data = array(
						'id'			=> $form['tp_id'],
						'status' 		=> 6,
						'remark'        => $form['remarks'],
						'author'	    => $this->_author,
						'modified'      => $this->_modified,
				    );
					$message = "Successfully Applied";
					$desc = "New POL expense Applied";
					/*Get users under destination location with sub role Depoy Manager*/
					$sourceLocation = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->getColumn($form['tp_id'], 'location');			
			$result = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->save($data);		
			if($result):
				$expenseresult =$form['tp_id'];
				$pol = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->get($result);
				foreach($pol as $pols):
					$expensedate = $pols['sanction_order_date'];
					$location = $pols['location'];
					$region = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('id'=>$this->_author),'region');
					$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
					$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(11,'prefix');
					$date = date('ym',strtotime(date('Y-m-d')));
					$tmp_VCNo = $loc.'-'.$prefix.$date;
					
					$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
					
					$pltp_no_list = array();
					foreach($results as $result):
						array_push($pltp_no_list, substr($result['voucher_no'], 14));
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
					
							$data = array(
								'voucher_date' 		=> $expensedate,
								'voucher_type' 		=> 11,
								'region'   			=>$region,
								'doc_id'   			=>"POL Expense",
								'voucher_no' 		=> $voucher_no,
								'voucher_amount' 	=> $pols['amount'],
								'status' 			=> 6, // status initiated 
								'remark'			=>$form['tp_id'],
								'against' 	  	=> 0,
								'author' 			=>$this->_author,
								'created' 			=>$this->_created,  
								'modified' 			=>$this->_modified,
							);
							$resultTrans = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data);
							//if($resultTrans >0){
								$flow=array(
									'flow' 				=> 2,
									'application' 		=> $resultTrans,
									'activity'			=>$sourceLocation,
									//'role_id'   		=>' ',
									'actor'   			=>3,
									'action' 			=> "2|4",
									'routing' 			=> 6,
									'status' 			=> 6, // status applied 
									'routing_status'	=>2,
									'action_performed'	=>1,
									'description'		=>"POL Expense",
									'author' 			=>$this->_author,
									'created' 			=>$this->_created,  
									'modified' 			=>$this->_modified,
								);
								$flow=$this->getDefinedTable(Administration\FlowTransactionTable::class)->save($flow);
								$transactionDtls1 = array(
									'transaction' => $resultTrans,
									'voucher_dates' => $data['voucher_date'],
									'voucher_types' => 11,
									'location' => $sourceLocation,
									'head' 	  		=> $pols['head'],
									'sub_head' 	  	=> $pols['subhead'],
									'bank_ref_type' => '',
									'debit' =>$pols['amount'],
									'credit' =>'0.00',
									'ref_no'=> '', 
									'against' 	  	=> 0,
									'type' => '1',//user inputted  data  
									'status' => 6, // status appied
									'activity'=>$sourceLocation,
									'author' =>$this->_author,
									'created' =>$this->_created,
									'modified' =>$this->_modified,
								);
							$transactionDtls1 = $this->_safedataObj->rteSafe($transactionDtls1);
							$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($transactionDtls1);
							$transactionDtls2 = array(
									'transaction' => $resultTrans,
									'voucher_dates' => $data['voucher_date'],
									'voucher_types' => 11,
									'location' => $sourceLocation,
									'head' =>$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('id'=>$pols['party']),'head'),
									'sub_head' =>$pols['party'],
									'bank_ref_type' => '',
									'debit' =>'0.00',
									'credit' =>$pols['amount'],
									'ref_no'=> $pols['ref_no'], 
									'type' => '1',//user inputted  data
									'status' => 6, // status applied
									'against' 	  	=> 0,
									'activity'=>$data['voucher_amount'],
									'author' =>$this->_author,
									'created' =>$this->_created,
									'modified' =>$this->_modified,
								);
								$transactionDtls2 = $this->_safedataObj->rteSafe($transactionDtls2);
								$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($transactionDtls2);
								$data2 = array(
									'id' 	=> $pols['id'],
									'transaction' => $resultTrans,
									);
								$data2 =  $this->_safedataObj->rteSafe($data2);
								$result5 = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->save($data2);
						endforeach;
						if($result4):
							$notification_data = array(
								'route'         => 'transaction',
								'action'        => 'viewcredit',
								'key' 		    => $resultTrans,
								'description'   => 'POL Expense Applied',
								'author'	    => $this->_author,
								'created'       => $this->_created,
								'modified'      => $this->_modified,   
							);
							//print_r($notification_data);exit;
							$notificationResult = $this->getDefinedTable(Acl\NotificationTable::class)->save($notification_data);
							if($notificationResult > 0 ){	
								$user = $this->getDefinedTable(Administration\UsersTable::class)->get(array('region'=>$region,'role'=>array('3')));
								foreach($user as $row):						    
									$user_location_id = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($row['id'], 'location');
									//if($user_location_id == $location ):						
										$notify_data = array(
											'notification' => $notificationResult,
											'user'    	   => $row['id'],
											'flag'    	 => '0',
											'desc'    	 => 'Transport Expense Applied',
											'author'	 => $this->_author,
											'created'    => $this->_created,
											'modified'   => $this->_modified,  
										);
										$notifyResult = $this->getDefinedTable(Acl\NotifyTable::class)->save($notify_data);
									//endif;
								endforeach;
							}
						endif;
				$this->_connection->commit(); // commit transaction over success
				$this->flashMessenger()->addMessage("success^".$message);
			else:
			    $this->_connection->rollback(); // rollback transaction over failure
			    $this->flashMessenger()->addMessage("error^ Cannot send good request");			  
			endif;
			return $this->redirect()->toRoute('pol',array('action'=>'viewpol','id'=>$form['tp_id']));
		endif; 		
		$viewModel =  new ViewModel(array(
			'title' => 'GRN',
			'id'  => $this->_id,
			'userID' => $this->_author,
		));	
		$viewModel->setTerminal('false');
        return $viewModel;	
	}
	/***********************************************Get Finction**********************************************/
	/**
	 * Get gettransport Action
	**/
	public function gettransportAction()

	{		
		$form = $this->getRequest()->getPost();
		$locationId =$form['locationId'];
		$headId =$form['headId'];
		$item_list = $this->getDefinedTable(Fleet\VehicleRegisterTable::class)->get(array('location'=>$locationId,'head'=>$headId,'pol'=>1));
		
			$transport = "<option value=''></option>";
		foreach($item_list as $subhead_lists):
			$transport.="<option value='".$subhead_lists['subhead']."'>".$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('id'=>$subhead_lists['subhead']),'name')."</option>";
		endforeach;
		echo json_encode(array(
				'transport' => $transport,
		));
		exit;
	}
	/**
	 * Get Subhead based on license plate
	**/
	public function getplateAction()
	{		
		$form = $this->getRequest()->getPost();
		$subheadId = $form['subheadId'];
		$headId = $form['headId'];
		$subh_list = $this->getDefinedTable(Fleet\VehicleRegisterTable::class)->get(array('head'=>$headId,'subhead'=>$subheadId));
		foreach($subh_list as $subh_lists);
			$license_plate=$subh_lists['license_plate'];
			echo json_encode(array(
				'license_plate' => $license_plate,
		));
		exit;
	}
	/**
	 * Get Subhead based on head
	**/
	public function getsubheadAction()
	{
		$form = $this->getRequest()->getPost();
		$headid =$form['headId'];
		$sub_head = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>$headid));
		
		$subheads ="<option value='-1'></option>";
			foreach($sub_head as $row):
			
				$subheads .="<option value='".$row['id']."'>".$row['name']."</option>";
			endforeach;
		
		echo json_encode(array(
			'subheads'=>$subheads ,
		));
		exit;
	}
}

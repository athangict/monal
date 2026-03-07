<?php
namespace Fleet\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Accounts\Model As Accounts;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Hr\Model As Hr;
use Fleet\Model As Fleet;
class ReportController extends AbstractActionController
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
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();


	}
	
	/**
	 * report index action
	 */
	public function indexAction()
	{
		$this->init();		
			
		return new ViewModel( array(
				'title' => "Fleet Report Setup",
		));
	}
	/**
	 * vehicle log report action
	 */
	public function vehiclelogreportAction()
	{
		$this->init();
		$transport  = '';
		$start_date = '';
		$end_date   = '';		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
            $transport  = $form['transport'];
			$start_date = $form['start_date'];
			$end_date   = $form['end_date'];
		endif;
		$data = array(
			'transport'  => $transport,
			'start_date' => $start_date,
			'end_date'   => $end_date,
		);
		//print_r($data); exit;
		$transport_so = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->getAll();
	
		//$transport_so = $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class)->getDistinctTransport($transport,$start_date,$end_date);
		$ViewModel = new ViewModel(array(
				'title'              => "POL Report",
				'data'               => $data,
				'transport_so'       => $transport_so,
				'TObject'            => $this->getDefinedTable(Accounts\AssetsTable::class),
			    'sanctionorderObj'   => $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class),
			    'vel_logObj'         => $this->getDefinedTable(Fleet\TransportVehicleLogTable::class),
                'cashmemoObj'        => $this->getDefinedTable(Fleet\TransportCashMemoTable::class),



		));
		return $ViewModel; 
	}
	
	/**
	 * vehicle log report action
	 */
	public function rmreportAction()
	{
		$this->init();	
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			 $location  = $form['location'];
            $license_plate  = $form['license_plate'];
			$start_date = $form['start_date'];
			$end_date   = $form['end_date'];
		else:
			$location  ='-1';
			$license_plate  ='-1';
			$start_date =date('Y-m-d');
			$end_date   = date('Y-m-d');	
		endif;
		$data = array(
			'location'  => $location,
			'license_plate'  => $license_plate,
			'start_date' => $start_date,
			'end_date'   => $end_date,
		);
		//print_r($data); exit;
		//$repairs = $this->getDefinedTable(Fleet\VehicleRepairMaintaneseDtlsTable::class)->getAll();
		$repairs = $this->getDefinedTable(Fleet\VehicleRepairMaintaneseDtlsTable::class)->getDistinctTransport($location,$license_plate,$start_date,$end_date);
		$ViewModel = new ViewModel(array(
				'title'              => "RM Report",
				'data'               => $data,
				'repairs'            => $repairs,
				'TObject'            => $this->getDefinedTable(Fleet\TransportTable::class),
				'rmObject'           => $this->getDefinedTable(Fleet\VehicleRepairMaintaneseTable::class),
			    'RMDObject'          => $this->getDefinedTable(Fleet\VehicleRepairMaintaneseDtlsTable::class),
			    'TSOObject'          => $this->getDefinedTable(Fleet\TransportSanctionOrderTable::class),
				'vehiclepartObj'     => $this->getDefinedTable(Fleet\VehiclePartTable::class),
				'licenseObj'     => $this->getDefinedTable(Fleet\TransportTable::class),
				'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
				'headObj'     => $this->getDefinedTable(Accounts\HeadTable::class),
				'subheadObj'     => $this->getDefinedTable(Accounts\SubheadTable::class),
				'partyObj'     => $this->getDefinedTable(Accounts\PartyTable::class),
				'license'     => $this->getDefinedTable(Fleet\TransportTable::class)->getAll(),

		));
		return $ViewModel; 
	}
	/**
	 * vehicle log report action
	 */
	public function vrreportAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$location  = $form['location'];
            $license_plate  = $form['license_plate'];
			$start_date = $form['start_date'];
			$end_date   = $form['end_date'];
			
		else:
			$location  = '-1';		
			$license_plate  = '-1';
			$start_date =date('Y-m-d');
			$end_date   = date('Y-m-d');
		endif;
		$data = array(
			'location'  => $location,
			'license_plate'  => $license_plate,
			'start_date' => $start_date,
			'end_date'   => $end_date,
		);
		//print_r($data); exit;
		$transport_vr = $this->getDefinedTable(Fleet\TransportVehicleLogTable::class)->getDistinctTransport($location,$license_plate,$start_date,$end_date);
		$ViewModel = new ViewModel(array(
				'title'              => "Vehicle POL Report",
				'data'               => $data,
				'transport_vr'       => $transport_vr,
				'TObject'            => $this->getDefinedTable(Fleet\TransportTable::class),
			    'VRObject'          => $this->getDefinedTable(Fleet\VehicleRequisitionTable::class),
			    'VRDObject'          => $this->getDefinedTable(Fleet\VehicleRequisitionDtlsTable::class),
				'vehiclepartObj'     => $this->getDefinedTable(Fleet\VehiclePartTable::class),
				'licenseObj'     => $this->getDefinedTable(Fleet\TransportTable::class),
				'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
				'partyObj'     => $this->getDefinedTable(Accounts\SubheadTable::class),
		));
		return $ViewModel; 
	}
	/**
	 * vehicle report action
	 */
	public function transportreportAction()
	{
		$this->init();	
		$region='';	
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$region = $form['region'];
		endif;
		$data = array(
			'region' => $region,
		);
		$transports  = $this->getDefinedTable(Fleet\TransportTable::class)->getDistinctRegion($region);	
		$ViewModel = new ViewModel(array(
				'title' => "Transport Report",
				'data'               => $data,
				'transports'         => $transports,
			    'transportObj'       => $this->getDefinedTable(Fleet\TransportTable::class),
			    'TDObject'           => $this->getDefinedTable(Fleet\TransportDetailsTable::class),
			    'THObject'           => $this->getDefinedTable(Fleet\TransportHistoryTable::class),
			    'regionObj'          => $this->getDefinedTable(Administration\RegionTable::class),
			    'locationObj'        => $this->getDefinedTable(Administration\LocationTable::class),
			    'TCObject'           => $this->getDefinedTable(Fleet\VehicleClassTable::Class),
			    'MObject'            => $this->getDefinedTable(Fleet\MakeTable::class),
			    'FObject'            => $this->getDefinedTable(Fleet\FuelTable::Class),
				'EDObject'           => $this->getDefinedTable(Hr\EmployeeTable::class),
				'PDObject'           => $this->getDefinedTable(Fleet\TransportPDTable::class),
				'typeObj'         => $this->getDefinedTable(Fleet\TransportTypeTable::class),
		));
		$this->layout('layout/reportlayout');
		return $ViewModel; 
	}
	/**
	 * Get transport
	**/
	public function gettransportAction()
	{		
		$form = $this->getRequest()->getPost();
		$locationId =$form['locationId'];
		$veh_list = $this->getDefinedTable(Fleet\TransportTable::class)->get(array('location'=>$locationId));
		
			$license_plate = "<option value='-1'>All</option>";
		foreach($veh_list as $veh_lists):
			$license_plate.="<option value='".$veh_lists['id']."'>".$veh_lists['license_plate']."</option>";
		endforeach;
		echo json_encode(array(
				'license_plate' => $license_plate,
		));
		exit;
	}
}


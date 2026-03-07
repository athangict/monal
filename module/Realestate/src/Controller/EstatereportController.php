<?php
namespace Realestate\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Stdlib\ArrayObject;
use Laminas\Validator\File\Size;
use Laminas\Validator\File\Extension;
use Laminas\Mail\Message;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Part as MimePart;
use Laminas\Mail\Transport\Sendmail;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl;
use Stock\Model As Stock;
use Purchase\Model As Purchase;
use Accounts\Model As Accounts;
use Administration\Model As Administration;
use Realestate\Model As Realestate;
use Asset\Model As Asset;
use Hr\Model As Hr;
class EstatereportController extends AbstractActionController
{   
	private $_container;
	protected $_table; 		// database table 
    protected $_user; 		// user detail
    protected $_highest_role; 	// highest_role
    protected $_login_role; // logined user role
    protected $_author; 	// logined user id
    protected $_created; 	// current date to be used as created dated
    protected $_modified; 	// current date to be used as modified date
    protected $_config; 	// configuration details
    protected $_dir; 		// default file directory
    protected $_id; 		// route parameter id, usally used by crude
    protected $_auth; 		// checking authentication
    protected $_safedataObj; //safedata controller plugin
    protected $_permissionObj; //permission controller plugin
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
		
		//$this->_dir =realpath($fileManagerDir);
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();
	
	}
	//Real Estate Details view page
	public function indexAction()
	{
		$this->init();
		
		return new ViewModel(array(
			'title' => 'Estatereport',
			'estatereports' => $this->getDefinedTable(Realestate\LandReportTable::class)->getAll(),
			'buildingreports' => $this->getDefinedTable(Realestate\LandReportTable::class)->getAll(),
			'flatreports' => $this->getDefinedTable(Realestate\LeasedRentTable::class)->getAll(),
		));
	}
	public function estatereportAction()
	{	
		$this->init();
		$array_id = explode("_", $this->_id);
		$region = (sizeof($array_id)>1)?$array_id[0]:'-1';
		$location = (sizeof($array_id)>1)?$array_id[1]:'-1';
		if($this->getRequest()->isPost())
		{
			$form      		 = $this->getRequest()->getPost();
			$region          = $form['region'];
			$location   = $form['location'];
			$land_type  =   $form['land_type'];
			
		}else{
			$region = "-1";
			$location = '-1';
			$land_type = '-1';
		
		}
		
		$data = array(
			'region'   => $region,
			'location' => $location,
			'land_type'     =>$land_type,
		);
			//echo '<pre>';print_r($data);exit;
		$locationTable = $this->getDefinedTable(Realestate\LandTable::class)->getReport($data);
		 $paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($locationTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		 $paginator->setCurrentPageNumber((int)$page);
		 $paginator->setItemCountPerPage(20);
		 $paginator->setPageRange(8);
        return new ViewModel(array(
			'title'           => 'Land Report',
			'paginator'       => $paginator,
			'data'            => $data,
			'page'            => $page,
			'landtypeObj'     => $this->getDefinedTable(Realestate\LandTable::class),
			'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			
		)); 
	} 

	//Building Report
	public function buildingreportAction()
	{	
		$this->init();
		$array_id = explode("_", $this->_id);
		$region = (sizeof($array_id)>1)?$array_id[0]:'-1';
		$location = (sizeof($array_id)>1)?$array_id[1]:'-1';
		$block = (sizeof($array_id)>1)?$array_id[1]:'-1';
	
		if($this->getRequest()->isPost())
		{
			$form      		 = $this->getRequest()->getPost();
			$region          = $form['region'];
			$location   = $form['location'];
			$block  = $form['block'];
			
			
		}else{
			$region = '-1';
			$location = '-1';
			$block = '-1';
			
		
		}
		
		$data = array(
			'region'   => $region,
			'location' => $location,
			'block'     =>$block,
		
		);
			//echo '<pre>';print_r($data);exit;
		$building = $this->getDefinedTable(Realestate\BuildingTable::class)->getReportB($data);
        return new ViewModel(array(
			'title'           	=> 'Building Report',
			'building'       	=> $building,
			'data'            	=> $data,
			'blockObj'        	=> $this->getDefinedTable(Realestate\BuildingTable::class),
			'regionObj'			=> $this->getDefinedTable(Administration\RegionTable::class),
			'locationObj' 		=> $this->getDefinedTable(Administration\LocationTable::class),
			'blockObj' 			=> $this->getDefinedTable(Asset\AssettypeTable::class),
			'empObj' 			=> $this->getDefinedTable(Hr\EmployeeTable::class),
		)); 
	} 

	//flatreport
	public function flatreportAction()
	{
		$this->init();
			$id = $this->_id;
			$array_id = explode("_", $id);
			$region = (sizeof($array_id)>1)?$array_id[1]:'-1';
			$location = (sizeof($array_id)>1)?$array_id[1]:'-1';
			$block = (sizeof($array_id)>1)?$array_id[1]:'-1';
			$status = (sizeof($array_id)>1)?$array_id[1]:'-1';
			$building = $array_id[0];
			
			if($this->getRequest()->isPost())
			{
				$form      			= $this->getRequest()->getPost();
				$block  	 = $form['block'];
				$region  	 = $form['region'];
				$location  	 = $form['location'];
				$building  	 = $form['building'];
				$status  	 = $form['status'];
			}else{
				$block       = '-1';
				$building       = '-1';
				$region       = '-1';
				$location       = '-1';
				$status       = '-1';
				

			}
			$data = array(
				'block'  	=> $block,
				'region'  	=> $region,
				'location'  => $location,
				'building'  	=> $building,
				'status'  	=> $status,
				
			);
			//echo '<pre>';print_r($data);exit;
			$flat = $this->getDefinedTable(Realestate\FlatTable::class)->getReportflat($data);
        return new ViewModel(array(
			'title'           => 'Flat Report',
			'data'            => $data,
			'blockObj'        => $this->getDefinedTable(Asset\AssettypeTable::class),
			'buildingnasterObj' => $this->getDefinedTable(Realestate\BuildingMasterTable::class),
			'statusObj' => $this->getDefinedTable(Realestate\FlatstatusTable::class),
			'floorObj' => $this->getDefinedTable(Realestate\FloorMasterTable::class),
			'buildingObj' => $this->getDefinedTable(Realestate\BuildingTable::class),
			'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'partyObj' => $this->getDefinedTable(Accounts\PartyTable::class),
			'flat' => $flat,
		
		)); 
	} 
	 
	public function getlocationAction()
	{		
		$form = $this->getRequest()->getPost();
		$regId = $form['regId'];
		$region = $this->getDefinedTable(Administration\LocationTable::class)->get(array('region'=>$regId));

       
		
		$location = "<option value=''></option>";
		foreach($region as $regions):
			$location.="<option value='".$regions['id']."'>".$regions['location']."</option>";
		endforeach;
		
		echo json_encode(array(
				'location' => $location,
		));
		exit;
	}


// tenants cation
public function tenantsAction()
{
	$this->init();	
		$year = ($this->_id == 0)? date('Y'):$this->_id;	
		return new ViewModel(array(
				'title'  => 'Generate',
				'flat' => $this->getDefinedTable(Realestate\LeasedRentTable::class)->getAll(),
				'year' => $year,
				'tenantsdtls' => $this->getDefinedTable(Realestate\FlatTable::class)->getAll(),
				'datet'  => $this->getDefinedTable(Realestate\FlatTable::class)->getAll(),
				'rent' => $this->getDefinedTable(Realestate\RentTable::class)->getRent($year),

		));
}

public function tenantdtlsAction()
	{
		$this->init();
		$this->_id = isset($this->_id)?$this->_id:date('Y-m-d');
		list($year, $month) = explode('-', $this->_id);	  
	
		if($year == 0):
			$max_year = $this->getDefinedTable(Realestate\DateTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Realestate\DateTable::class)->getMax('month', array('year' => $max_year));
			$year = ($max_month == 12)? $max_year+1 : $max_year;
		endif;
		if($month == 0):
			$max_year = $this->getDefinedTable(Realestate\DateTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Realestate\DateTable::class)->getMax('month', array('year' => $max_year));
			$month = ($max_month == 12)? 1 : $max_month+1;
		endif;
		if ($this->getDefinedTable(Realestate\DateTable::class)->isPresent($month, $year)):
			$this->redirect()->toRoute('estatereport',array('id'=>$year.'-'.$month));
		endif;
		$data=array(
			'month'=> $month,
			'year' => $year,
			'author'=> $this->_author,
			'created'=>$this->_created,
			'modified' => $this->_modified
		);
		//prepare temporary payroll
		$this->getDefinedTable(Realestate\DateTimeTable::class)->prepareDateTime($data);
		foreach($this->getDefinedTable(Realestate\DateTimeTable::class)->get(array('pr.status'=>'0')) as $rs_rent):
			$rent = $rs_rent['rent'];
			$total_rent = 0;					
			$data1 = array(
					'id'	=> $rs_rent['id'],
					// 'year' => $year,
					// 'month' => $month,
					'total_rent' => $total_rent,
					'status' => '1', // initiated
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);			
			$data1 = $this->_safedataObj->rteSafe($data1);			
			
			$result1 = $this->getDefinedTable(Realestate\DateTimeTable::class)->save($data1);
		endforeach;
		
		return new ViewModel(array(
				'title' => 'Add Pay roll',
				'month' => $month,
				'year' => $year,
				'tenantsdtls' => $this->getDefinedTable(Realestate\FlatTable::class)->getAll(),

		));
	}
}

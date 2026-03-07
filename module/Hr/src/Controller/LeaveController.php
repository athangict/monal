<?php
namespace Hr\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Hr\Model As Hr;
use Accounts\Model As Accounts;


class LeaveController extends AbstractActionController
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
	protected $_permission; // permission plugin
    
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
	 * indexAction
	**/
	public function indexAction()
	{
        $this->init();	
			
		return new ViewModel(array(
			'title'        => 'Leave Application',
			'userID'       => $this->_login_id,
			'leaveObj'     => $this->getDefinedTable(Hr\LeaveTable::class),
			'employeeObj'  => $this->getDefinedTable(Hr\EmployeeTable::class),
			'userObj'      => $this->getDefinedTable(Administration\UsersTable::class),
			'roleObj'      => $this->getDefinedTable(Acl\RolesTable::class),
		));
	} 
	/**
	 * leave that are pending with user Action
	**/
	public function leavependingAction()
	{
        $this->init();	
		$leave=$this->getDefinedTable(Hr\LeaveTable::class)->getPendingLeave('a.id',array('a.process'=>508,'a.send_to'=>$this->_user->id),$this->_user->id);
		return new ViewModel(array(
			'title'        => 'Leave Pending',
			'userID'       => $this->_login_id,
			'leaves'=>$leave,
			'leaveObj'     => $this->getDefinedTable(Hr\LeaveTable::class),
			'employeeObj'  => $this->getDefinedTable(Hr\EmployeeTable::class),
			'leavetypeObj'     => $this->getDefinedTable(Hr\LeaveTypeTable::class),
			'userObj'      => $this->getDefinedTable(Administration\UsersTable::class),
			'roleObj'      => $this->getDefinedTable(Acl\RolesTable::class),
		));
	} 
	/**
	 * indexAction
	**/
	public function leaveactionbymeAction()
	{
        $this->init();	
			$leave=$this->getDefinedTable(Hr\LeaveTable::class)->getActionByMe(array('a.process'=>508,'a.send_to'=>$this->_user->id),$this->_user->id);
		return new ViewModel(array(
			'title'        => 'Leave Action',
			'userID'       => $this->_login_id,
			'leaves'		=> $leave,
			'leaveObj'     => $this->getDefinedTable(Hr\LeaveTable::class),
			'leavetypeObj'     => $this->getDefinedTable(Hr\LeaveTypeTable::class),
			'employeeObj'  => $this->getDefinedTable(Hr\EmployeeTable::class),
			'userObj'      => $this->getDefinedTable(Administration\UsersTable::class),
			'roleObj'      => $this->getDefinedTable(Acl\RolesTable::class),
		));
	} 
	
	/**  
	 *  myleave action
	 */
	public function employeeleaveAction()
	{
		$this->init();		
		return new ViewModel(array(
			'title' => 'Leave Application',
			'leave' => $this->getDefinedTable(Hr\LeaveTable::class)->get(array('author'=>$this->_user->id)),
			'leavedetailObj' => $this->getDefinedTable(Hr\LeaveDetailTable::class),
		));
	} 
	
	/**
	 *  apply action
	 */
	public function applyAction()
	{
		$this->init();	
		
        if($this->getRequest()->isPost()):		
			$form = $this->getRequest()->getPost()->toArray();	
			$pendingleaves = $this->getDefinedTable(Hr\LeaveTable::class)->get(array('ld.employee'=>$form['applicant'], 'ld.status'=>array(2,3,4,5,6)));
			 if(sizeof($pendingleaves)>0){
				  $this->flashMessenger()->addMessage("error^ Failed to apply leave because your previous leave is not approved");
				  return $this->redirect()->toRoute('leave');				
			}
			$empolyeeMax = $this->getDefinedTable(Hr\EmpHistoryTable::class)->getMax('id',array('employee'=>$form['applicant']));
			$empolyeeProbation = $this->getDefinedTable(Hr\EmpHistoryTable::class)->getColumn(array('id'=>$empolyeeMax),'type_of_appointment');
			if ($empolyeeProbation == 14 && !in_array($form['leave_type'], [1, 2, 5, 6, 7])){
				$this->flashMessenger()->addMessage("warning^ Only eligible for Casual Leave, Try again");
				return $this->redirect()->toRoute('leave', array('action' => 'apply'));
			}
			if(empty($form['delegation'])):$delegation=0;else:$delegation=$form['delegation'];endif;
			if($form['half']==1):$no_of_days=0.5;else:$no_of_days=$form['no_of_days'];endif;
			$authorEmpID = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author, 'employee');	
			$leaveOfficialResult = $this->getDefinedTable(Administration\UsersTable::class)->get(array('id'=>$this->_author,'role'=>'4'))||$this->getDefinedTable(Administration\UsersTable::class)->get(array('id'=>$this->_author,'role'=>'100'));
			if($form['applicant'] != $authorEmpID ){$leave_officier = $this->_author; }else{ $leave_officier = '0'; }		
			if($form['declaration'] == '1' && $form['applicant'] > 0 ):
			     $data = array(					
					'employee'     => $form['applicant'],
					'leave_type'   => $form['leave_type'],
					'start_date'   => $form['start_date'],
					'end_date'     => $form['end_date'],
					'no_of_days'   => $no_of_days,
					'contact'      => $form['contact'],
					'delegation'   => $delegation,
					'sanction_order_no'  => $form['sanction_order_no'],
					'actual_leave_taken'  => $no_of_days,
					'remarks'  			 => $form['remarks'],
					'leave_official'  => $leave_officier,
					'remark_log'   => "",
					'status'       => '2',
					'author'        => $this->_author,
					'created'       => $this->_created,
					'modified'      => $this->_modified					
				);
				//print_r($data);exit;	
	            $data = $this->_safedataObj->rteSafe($data);
			    $result = $this->getDefinedTable(Hr\LeaveTable::class)->save($data);	
				$flow_result = $this->flowinitiation('508', $result);
			endif;
			if($result){
				$this->flashMessenger()->addMessage("success^ Leave application successfully initiated");
				return $this->redirect()->toRoute('leave', array('action' => 'leavedetail', 'id'=>$result));
			}			
			else{
				$this->flashMessenger()->addMessage("error^ Failed to initiate leave application, Try again");
				return $this->redirect()->toRoute('leave', array('action' => 'leavedetail', 'id'=>$result));
			}		
		endif;
		$viewModel = new ViewModel(array(
				'title'        => 'Leave Application',
				'userID'       => $this->_login_id,
				'login_emp_ID' => $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'employee'),
				'employeeObj'  => $this->getDefinedTable(Hr\EmployeeTable::class),
				'emphisObj'    => $this->getDefinedTable(Hr\EmpHistoryTable::class),
				'leaveTypes'   => $this->getDefinedTable(Hr\LeaveTypeTable::class)->getAll(),
				'roleObj'      => $this->getDefinedTable(Acl\RolesTable::class),
				'userObj'      => $this->getDefinedTable(Administration\UsersTable::class),
				'leaveObj' 	   => $this->getDefinedTable(Hr\LeaveTable::class),
			));			
		return $viewModel;
	}
	
	/**
	 * get leave details
	 */
	 public function getleavedtlsAction(){
		//$this->init();	
		//echo "g";exit;
		//if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();
			
			$employeeID =$form['emp_id'];
			$leaveencashMax = $this->getDefinedTable(Hr\LeaveEncashTable::class)->getMax('id',array('employee'=>$employeeID));
			$leaveencash = $this->getDefinedTable(Hr\LeaveEncashTable::class)->getColumn(array('id'=>$leaveencashMax,'status'=>4),'encash_date');
			$encashyear = date('Y',strtotime($leaveencash));
			//print_r($encashyear);
			$leaveDate = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($employeeID, 'leave_balance_date');
			$leaveBal = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($employeeID, 'leave_balance');
			$empLocation = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($employeeID, 'location');
			$empDivision = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($employeeID, 'activity');
			$empRegion = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($empLocation, 'region');
			$empolyeeMax = $this->getDefinedTable(Hr\EmpHistoryTable::class)->getMax('id',array('employee'=>$employeeID));
			$category=$this->getDefinedTable(Hr\EmpHistoryTable::class)->getColumn($empolyeeMax,'employee_type');
			$appointment=$this->getDefinedTable(Hr\EmpHistoryTable::class)->getColumn($empolyeeMax,'type_of_appointment');
			
			 if(in_array($appointment, array(1, 15, 14))) {
				$empolyeeMax=$empolyeeMax;
			}
			else{
				$empolyeeMax=$this->getDefinedTable(Hr\EmpHistoryTable::class)->getMin('id',array('employee'=>$employeeID,'type_of_appointment'=>15));
				}
			if($leaveDate > 0 && $leaveBal > 0):
				$startDate = $leaveDate;
				$Balance = $leaveBal;
			else:
				$startDate = $this->getDefinedTable(Hr\EmpHistoryTable::class)->getColumn(array('id'=>$empolyeeMax), 'start_date');
				//print_r($startDate);
				$Balance = 0;
			endif; 
			
			$leaveObj = $this->getDefinedTable(Hr\LeaveTable::class);
			$minYr = date('Y',strtotime($startDate));
			$minMonth = date('m',strtotime($startDate));
			$curYr = date('Y');
			$curMonth = date('m');
			$presentCasual =0;
			$presentEarned  =0;
			$empolyeeProbation = $this->getDefinedTable(Hr\EmpHistoryTable::class)->getColumn(array('id'=>$empolyeeMax),'type_of_appointment');
			$casual = 0;  $earned = 0; $beavement = 0; $extraordinary = 0; $maternity = 0;	$medical = 0;$paternity = 0;$escort = 0;$study = 0;$prep = 0;
			 $i = 1;	$j = 1;	$k = 1;$l = 1;$m = 1;	$n = 1;	$o = 1;	$p = 1;	$q = 1;	$r = 1;	
			 $casual_days = $this->getDefinedTable(Hr\LeaveTypeTable::class)->getColumn(2, 'total_days'); 
			 $earned_days = $this->getDefinedTable(Hr\LeaveTypeTable::class)->getColumn(3, 'total_days');
			 $beavement_days = $this->getDefinedTable(Hr\LeaveTypeTable::class)->getColumn(1, 'total_days');
			 $extraordinary_days =$this->getDefinedTable(Hr\LeaveTypeTable::class)->getColumn(4, 'total_days');
			 $maternity_days = $this->getDefinedTable(Hr\LeaveTypeTable::class)->getColumn(5, 'total_days');
			 $medical_days = $this->getDefinedTable(Hr\LeaveTypeTable::class)->getColumn(6, 'total_days');
			 $paternity_days =$this->getDefinedTable(Hr\LeaveTypeTable::class)->getColumn(7, 'total_days');
			 $escort_days = $this->getDefinedTable(Hr\LeaveTypeTable::class)->getColumn(11, 'total_days');
			 $study_days = $this->getDefinedTable(Hr\LeaveTypeTable::class)->getColumn(9, 'total_days');
			 $prep_days = $this->getDefinedTable(Hr\LeaveTypeTable::class)->getColumn(8, 'total_days');
			 $pre_maternity_leave = $this->getDefinedTable(Hr\LeaveTypeTable::class)->getColumn(10, 'total_days');
			for($curYear = $curYr; $curYear >= $minYr; $curYear--):	
				foreach($leaveObj->get(array('employee'=>$employeeID, 'ld.status'=>array(8))) as $leave):
					$startD = $leave['start_date'];
					$startYr = date('Y',strtotime($startD));
					$startMonth= date('m',strtotime($startD));							
					if($startYr == $curYear):
						if($minMonth<=$startMonth):
						if($leave['leave_type']==2):
							$casual += $leave['actual_leave_taken'];
							if($i == 1):
								 $presentCasual = $casual;
							endif;// total casual leave used for till current year from given date															
							$i++;
						
							
						elseif($leave['leave_type']==3):
							$earned += $leave['actual_leave_taken'];//total earned leave used
							if($j == 1):
								$presentEarned = $earned;
							endif;// total casual leave used for till current year from given date															
							$j++;
						elseif($leave['leave_type']==1):
							$beavement += $leave['actual_leave_taken'];//total Breavement Leave  used
							if($k == 1):
								$presentBreavement = $beavement;
							endif;// total Breavement leave used for till current year from given date															
							$k++;
						elseif($leave['leave_type']==4):
							$extraordinary += $leave['actual_leave_taken'];//total Extraordinary Leave used
							if($l == 1):
								$presentExtraordinary = $extraordinary;
							endif;// total Extraordinary Leave used for till current year from given date															
							$l++;
						elseif($leave['leave_type']==5):
							$maternity += $leave['actual_leave_taken'];//total Maternity Leave used
							if($m == 1):
								$presentMaternity = $maternity;
							endif;// total Maternity Leave used for till current year from given date															
							$m++;
						elseif($leave['leave_type']==6):
							$medical += $leave['actual_leave_taken'];//total Medical Leave used
							if($n == 1):
								$presentMedical= $medical;
							endif;// total Medical Leave used for till current year from given date															
							$n++;
						elseif($leave['leave_type']==7):
							$paternity += $leave['actual_leave_taken'];//total Paternity Leave used
							if($o == 1):
								$presentPaternity = $paternity;
							endif;// total Paternity Leave used for till current year from given date															
							$o++;
						elseif($leave['leave_type']==11):
							$escort += $leave['actual_leave_taken'];//total Escort Leave used
							if($p == 1):
								$presentEscort = $escort;
							endif;// total Escort Leave used for till current year from given date															
							$p++;
						elseif($leave['leave_type']==9):
							$study += $leave['actual_leave_taken'];//total Study Leave used
							if($q == 1):
								$presentStudy = $study;
							endif;// total Study Leave used for till current year from given date															
							$q++;
						elseif($leave['leave_type']==8):
							$prep += $leave['actual_leave_taken'];//total Study Leave used
							if($q == 1):
								$presentPrep = $prep;
							endif;// total Study Leave used for till current year from given date															
							$r++;
						endif;endif;
					endif;
				endforeach;											 						 
			endfor;	
				$total_years = $curYr - $minYr;
				$sub_total = 0;
				if($empolyeeProbation==14):$monthCasual = 5;else:$monthCasual = 10;endif;
				//print_r($total_years);exit;
				
				if($total_years > 0){
					$Bmonth = (12 - $minMonth);  //addition of 1 is to include the present month 
					$Amonth = $curMonth;
					$sub_total = $Bmonth + $Amonth;
					$Bcasual = round($Bmonth * $monthCasual);
					
					if($total_years == 1):
						$total_months = $sub_total;	
						if($empolyeeProbation==14):	$totCasual = $casual_days/2;else:$totCasual = $casual_days;endif;			
						$usedcasual = $casual - $presentCasual;
						$CasualLeft = $Bcasual - $usedcasual;					
						$totcasualBal = $totCasual - $casual;
						if($curYr==$encashyear):$totearnedBal = ($Balance + ($total_months * 2.5) - $earned)-30;else:$totearnedBal = $Balance + ($total_months * 2.5) - $earned;endif;
						/*Breavemnt */
						$totbreavementBal = $beavement_days;
						/*extraordinary */
						$totextraordinaryBal =  $extraordinary_days- $extraordinary;
						/*Maternity */
						$totmaternityBal = $maternity_days - $maternity;
						/*Medical */
						$totmedicalBal = $medical_days - $medical;
						/*Paternity */
						$totpaternityBal = $paternity_days - $paternity;
						
						/*Escort */
						$totescortBal = $escort_days - $escort;
						/*Study */
						$totstudyBal = $study_days - $study;
						/**preparotory */
						$totprepBal = $prep_days - $prep;
					else:
					
						$YEAR = $curYr - $minYr - 1;
						$total_months = ($YEAR * 12) + $sub_total;							
						$totCasual = $Bcasual + 10 + ($YEAR * 10);
						$casualBefore = $casual - $presentCasual;
						$CasualLeft = $Bcasual + ($YEAR * 10) - $casualBefore;						
						$totcasualBal = $totCasual - $casual;
						if($curYr==$encashyear):$totearnedBal = ($Balance + ($total_months * 2.5) - $earned)-30;	else:$totearnedBal = $Balance + ($total_months * 2.5) - $earned;endif;	
						/*Breavemnt */
						$totbreavementBal = $beavement_days;
						/*extraordinary */
						$totextraordinaryBal = $extraordinary_days - $extraordinary;
						/*Maternity */
						$totmaternityBal = $maternity_days - $maternity;
						/*Medical */
						$totmedicalBal = $medical_days  - $medical;
						/*Paternity */
						$totpaternityBal = $paternity_days - $paternity;
						/*Escort */
						$totescortBal = $escort_days - $escort;
						/*Study */
						$totstudyBal = $study_days - $study;
						/*Prep */
						$totprepBal = $prep_days - $prep;							
					endif;
				}else{
					$total_months = $curMonth - $minMonth;
					$actCasual = $monthCasual;//(12 - $minMonth + 1) * $monthCasual;
					$CasualLeft = 0;
					$actualCasual = round($actCasual);
					$totcasualBal = $actualCasual - $casual;
					if($curYr==$encashyear):$totearnedBal = ($Balance + ($total_months * 2.5) - $earned)-30;else:$totearnedBal = $Balance + ($total_months * 2.5) - $earned;endif;
					/*Breavemnt */
					$actBreavement = $beavement_days;//(12 - $minMonth + 1) * 21/12;
					$BreavementlLeft = 0;
					$actualBreavement = round($actBreavement);
					$totbreavementBal = $beavement_days;
					/*extraordinary */
					$actExtraordinary = 365*2;//(12 - $minMonth + 1) * 730/12;
					$ExtraordinarylLeft = 0;
					$actualExtraordinary = round($actExtraordinary);
					$totextraordinaryBal = $actualExtraordinary - $extraordinary;
					/*Maternity */
					$actMaternity = $maternity_days;//(12 - $minMonth + 1) * 180/12;
					$MaternityLeft = 0;
					$actualMaternity = round($actMaternity);
					$totmaternityBal = $actualMaternity - $maternity;
					/*Medical */
					$actMedical = $medical_days;//(12 - $minMonth + 1) * 180/12;
					$MedicalLeft = 0;
					$actualMedical = round($actMedical);
					$totmedicalBal = $actualMedical - $medical;
					/*Paternity */
					$actPaternity = $paternity_days;//(12 - $minMonth + 1) * 10/12;
					$PaternityLeft = 0;
					$actualPaternity = round($actPaternity);
					$totpaternityBal = $actualPaternity - $paternity;
					/*Escort */
					$actEscort = $escort_days;//(12 - $minMonth + 1) * 30/12;
					$EscortLeft = 0;
					$actualEscort = round($actEscort);
					$totescortBal = $actualEscort - $escort;
					/*Study */
					$actStudy = $study_days;//(12 - $minMonth + 1) * 1095/12;
					$StudyLeft = 0;
					$actualStudy = round($actStudy);
					$totstudyBal = $actualStudy - $study;
					/*PREP */
					$actPrep = $prep_days;//(12 - $minMonth + 1) * 7/12;
					$PrepLeft = 0;
					$actualPrep = round($actPrep);
					$totprepBal = $actualPrep - $prep;

				}
			$casual_bal = $totcasualBal; 
			$earned_bal = $totearnedBal + $CasualLeft;
			$casual_leave_used = $casual;
			$earned_leave_used = $earned;
			$leave_taken = $casual + $earned;
			$total_bal = $earned_bal + $casual_bal;
			/*Breavemnt */
			$breavement_bal = $totbreavementBal;
			$beavement_leave_used = $beavement;
			/*Extraordinary */
			$extraordinary_bal = $totextraordinaryBal;
			$extraordinary_leave_used = $extraordinary;
			/*Maternity */
			$maternity_bal = $totmaternityBal;
			$maternity_leave_used = $maternity;
			/*Medical */
			$medical_bal = $totmedicalBal;
			$medical_leave_used = $medical;
			/*Paternity */
			$paternity_bal = $totpaternityBal;
			$paternity_leave_used = $paternity;
			/*Escort */
			$escort_bal = $totescortBal;
			$escort_leave_used = $escort;
			/*Study*/
			$study_bal = $totstudyBal;
			$study_leave_used = $study;
			/*Prep*/
			$prep_bal = $totprepBal;
			$prep_leave_used = $prep;
			
			//print_r($beavement_leave_used);exit;
			if($total_bal > 90){
					$total_balance = 90;
			}else{
					$total_balance = $total_bal;
			}
			if($earned_bal > 90){
					$earned_balance = 90;
			}else{
					$earned_balance = $earned_bal;
			}
			$no_of_days = '';
			if($empolyeeProbation==14):
				echo json_encode(array(
					'casual_bal' => $casual_bal,
					'total_leave'=> $leave_taken,
					'total_bal'	 => $total_balance,					
					'casual_leave_used' => $casual_leave_used,
					'empLocation' => $empLocation,
					'breavement_bal' => $breavement_bal,
					'beavement_leave_used' => $beavement_leave_used,
					'maternity_bal' => $maternity_bal,
					'maternity_leave_used' => $maternity_leave_used,
					'medical_bal' => $medical_bal,
					'medical_leave_used' => $medical_leave_used,
					'paternity_bal' => $paternity_bal,
					'paternity_leave_used' => $paternity_leave_used,

			));else:
			echo json_encode(array(
					'casual_bal' => $casual_bal,
					'earned_bal' => $earned_balance,
					'total_leave'=> $leave_taken,
					'total_bal'	 => $total_balance,					
					'no_of_days' => $no_of_days,
					'casual_leave_used' => $casual_leave_used,
					'earned_leave_used' => $earned_leave_used,
					'breavement_bal' => $breavement_bal,
					'beavement_leave_used' => $beavement_leave_used,
					'paternity_bal' => $paternity_bal,
					'paternity_leave_used' => $paternity_leave_used,
					'extraordinary_bal' => $extraordinary_bal,
					'extraordinary_leave_used' => $extraordinary_leave_used,
					'maternity_bal' => $maternity_bal,
					'maternity_leave_used' => $maternity_leave_used,
					'medical_bal' => $medical_bal,
					'medical_leave_used' => $medical_leave_used,
					'escort_bal' => $escort_bal,
					'escort_leave_used' => $escort_leave_used,
					'study_bal' => $study_bal,
					'study_leave_used' => $study_leave_used,
					'prep_bal' => $prep_bal,
					'prep_leave_used' => $prep_leave_used,
					'empLocation' => $empLocation,
					'empDivision' => $empDivision,
					'empRegion' => $empRegion,
					'prematernity' => $pre_maternity_leave,
			));endif;
		exit;
	  }
	 
		/*leave application Detail*/
	public function leavedetailAction(){
		$this->init();
		$params = explode("-", $this->_id);
		if (isset($params['1']) && $params['1'] == '1' && isset($params['2']) && $params['2'] > 0) {
			$flag = $this->getDefinedTable(Acl\NotifyTable::class)->getColumn($params['2'], 'flag'); 
				if($flag == "0") {
					$notify = array('id' => $params['2'], 'flag'=>'1');
					$this->getDefinedTable(Acl\NotifyTable::class)->save($notify); 	
				}				
		}
		
		$leaveID = $params['0'];
		$employeeID = $this->getDefinedTable(Hr\LeaveTable::class)->getColumn($leaveID, 'employee');
		$leaveDate = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($employeeID, 'leave_balance_date');
		$leaveBal = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($employeeID, 'leave_balance');
		
		if($leaveDate > 0 || $leaveBal > 0):
			$startDate = $leaveDate;
			$Balance = $leaveBal;
		else:
			$startDate = $this->getDefinedTable(Hr\EmpHistoryTable::class)->getColumn(array('employee'=>$employeeID), 'start_date');
			$Balance = 0;
		endif; 
		$empolyeeMax = $this->getDefinedTable(Hr\EmpHistoryTable::class)->getMax('id',array('employee'=>$employeeID));
		$empolyeeProbation = $this->getDefinedTable(Hr\EmpHistoryTable::class)->getColumn(array('id'=>$empolyeeMax),'type_of_appointment');
		$leaveObj = $this->getDefinedTable(Hr\LeaveTable::class);
		$minYr = date('Y',strtotime($startDate));
		$minMonth = date('m',strtotime($startDate));
		$curYr = date('Y');
		$curMonth = date('m');
		$presentCasual =0;
		$presentEarned  =0;
		
		$casual = 0;  $earned = 0; $beavement = 0; $extraordinary = 0; $maternity = 0;	$medical = 0;$paternity = 0;$escort = 0;$study = 0;
		$i = 1;	$j = 1;	$k = 1;$l = 1;$m = 1;	$n = 1;	$o = 1;	$p = 1;	$q = 1;					
	   for($curYear = $curYr; $curYear >= $minYr; $curYear--):			
		   foreach($leaveObj->get(array('employee'=>$employeeID, 'ld.status'=>array(8))) as $leave):
			   $startD = $leave['start_date'];
			   $startYr = date('Y',strtotime($startD));
			   $startMonth= date('m',strtotime($startD));							
			   if($startYr == $curYear):
				   if($leave['leave_type']==2):
					   $casual += $leave['actual_leave_taken'];
					   if($i == 1):
							$presentCasual = $casual;
					   endif;// total casual leave used for till current year from given date															
					   $i++;
				   elseif($leave['leave_type']==3):
					   $earned += $leave['actual_leave_taken'];//total earned leave used
					   if($j == 1):
						   $presentEarned = $earned;
					   endif;// total casual leave used for till current year from given date															
					   $j++;
				   elseif($leave['leave_type']==1):
					   $beavement += $leave['actual_leave_taken'];//total Breavement Leave  used
					   if($k == 1):
						   $presentBreavement = $beavement;
					   endif;// total Breavement leave used for till current year from given date															
					   $k++;
				   elseif($leave['leave_type']==4):
					   $extraordinary += $leave['actual_leave_taken'];//total Extraordinary Leave used
					   if($l == 1):
						   $presentExtraordinary = $extraordinary;
					   endif;// total Extraordinary Leave used for till current year from given date															
					   $l++;
				   elseif($leave['leave_type']==5):
					   $maternity += $leave['actual_leave_taken'];//total Maternity Leave used
					   if($m == 1):
						   $presentMaternity = $maternity;
					   endif;// total Maternity Leave used for till current year from given date															
					   $m++;
				   elseif($leave['leave_type']==6):
					   $medical += $leave['actual_leave_taken'];//total Medical Leave used
					   if($n == 1):
						   $presentMedical= $medical;
					   endif;// total Medical Leave used for till current year from given date															
					   $n++;
				   elseif($leave['leave_type']==7):
					   $paternity += $leave['actual_leave_taken'];//total Paternity Leave used
					   if($o == 1):
						   $presentPaternity = $paternity;
					   endif;// total Paternity Leave used for till current year from given date															
					   $o++;
				   elseif($leave['leave_type']==8):
					   $escort += $leave['actual_leave_taken'];//total Escort Leave used
					   if($p == 1):
						   $presentEscort = $escort;
					   endif;// total Escort Leave used for till current year from given date															
					   $p++;
				   elseif($leave['leave_type']==9):
					   $study += $leave['actual_leave_taken'];//total Study Leave used
					   if($q == 1):
						   $presentStudy = $study;
					   endif;// total Study Leave used for till current year from given date															
					   $q++;
				   endif;	
			   endif;
		   endforeach;											 						 
	   endfor;	
		   $total_years = $curYr - $minYr;
		   $sub_total = 0;
		   if($empolyeeProbation==14): $monthCasual = 5/12;else: $monthCasual = 10/12;endif;
		  //print_r($total_years);exit;
		   if($total_years > 0){
			   $Bmonth = (12 - $minMonth);  //addition of 1 is to include the present month 
			   $Amonth = $curMonth;
			   $sub_total = $Bmonth + $Amonth;
			   $Bcasual = round($Bmonth * $monthCasual);
			   
			   if($total_years == 1):
				   $total_months = $sub_total;							
				   if($empolyeeProbation==14):$totCasual = 5;else:$totCasual = 10;endif;
				   $usedcasual = $casual - $presentCasual;
				   $CasualLeft = $Bcasual - $usedcasual;					
				   $totcasualBal = $totCasual - $casual;
				   $totearnedBal = $Balance + ($total_months * 2.5) - $earned;
				   /*Breavemnt */
				   $totbreavementBal = 21;
				   /*extraordinary */
				   $totextraordinaryBal = 730 - $extraordinary;
				   /*Maternity */
				   $totmaternityBal = 180 - $maternity;
				   /*Medical */
				   $totmedicalBal = 180 - $medical;
				   /*Paternity */
				   $totpaternityBal = 10 - $paternity;
				   /*Paternity */
				   $totpaternityBal = 10 - $paternity;
				   /*Escort */
				   $totescortBal = 50 - $escort;
				   /*Study */
				   $totstudyBal = 1095 - $study;
			   else:
				   $YEAR = $curYr - $minYr - 1;
				   $total_months = ($YEAR * 12) + $sub_total;							
				   $totCasual = $Bcasual + 10 + ($YEAR * 10);
				   $casualBefore = $casual - $presentCasual;
				   $CasualLeft = $Bcasual + ($YEAR * 10) - $casualBefore;						
				   $totcasualBal = $totCasual - $casual;
				   $totearnedBal = $Balance + ($total_months * 2.5) - $earned;		
				   /*Breavemnt */
				   $totbreavementBal = 21;
				   /*extraordinary */
				   $totextraordinaryBal = 730 - $extraordinary;
				   /*Maternity */
				   $totmaternityBal = 180 - $maternity;
				   /*Medical */
				   $totmedicalBal = 180 - $medical;
				   /*Paternity */
				   $totpaternityBal = 10 - $paternity;
				   /*Paternity */
				   $totpaternityBal = 10 - $paternity;
				   /*Escort */
				   $totescortBal = 50 - $escort;
				   /*Study */
				   $totstudyBal = 1095 - $study;							
			   endif;
		   }else{
			   $total_months = $curMonth - $minMonth;
			   $actCasual = (12 - $minMonth + 1) * $monthCasual;
			   $CasualLeft = 0;
			   $actualCasual = round($actCasual);
			   $totcasualBal = $actualCasual - $casual;
			   $totearnedBal = $Balance + ($total_months * 2.5) - $earned;
			   /*Breavemnt */
			   $actBreavement = (12 - $minMonth + 1) * 21/12;
			   $BreavementlLeft = 0;
			   $actualBreavement = round($actBreavement);
			   $totbreavementBal = 21;
			   /*extraordinary */
			   $actExtraordinary = (12 - $minMonth + 1) * 730/12;
			   $ExtraordinarylLeft = 0;
			   $actualExtraordinary = round($actExtraordinary);
			   $totextraordinaryBal = $actualExtraordinary - $extraordinary;
			   /*Maternity */
			   $actMaternity = (12 - $minMonth + 1) * 180/12;
			   $MaternityLeft = 0;
			   $actualMaternity = round($actExtraordinary);
			   $totmaternityBal = $actualMaternity - $maternity;
			   /*Medical */
			   $actMedical = (12 - $minMonth + 1) * 180/12;
			   $MedicalLeft = 0;
			   $actualMedical = round($actMedical);
			   $totmedicalBal = $actualMedical - $medical;
			   /*Paternity */
			   $actPaternity = (12 - $minMonth + 1) * 10/12;
			   $PaternityLeft = 0;
			   $actualPaternity = round($actPaternity);
			   $totpaternityBal = $actualPaternity - $paternity;
			   /*Escort */
			   $actEscort = (12 - $minMonth + 1) * 50/12;
			   $EscortLeft = 0;
			   $actualEscort = round($actEscort);
			   $totescortBal = $actualEscort - $escort;
			   /*Study */
			   $actStudy = (12 - $minMonth + 1) * 1095/12;
			   $StudyLeft = 0;
			   $actualStudy = round($actStudy);
			   $totstudyBal = $actualStudy - $study;

		   }
			$casual_bal = $totcasualBal; 
			if($empolyeeProbation==14):$earned_bal = 0;else:$earned_bal = $totearnedBal + $CasualLeft;endif;
			$casual_leave_used = $casual;
			$earned_leave_used = $earned;
			$leave_taken = $casual + $earned;
			$total_bal = $earned_bal + $casual_bal;
			/*Breavemnt */
			$breavement_bal = $totbreavementBal;
			$beavement_leave_used = $beavement;
			/*Extraordinary */
			$extraordinary_bal = $totextraordinaryBal;
			$extraordinary_leave_used = $extraordinary;
			/*Maternity */
			$maternity_bal = $totmaternityBal;
			$maternity_leave_used = $maternity;
			/*Medical */
			$medical_bal = $totmedicalBal;
			$medical_leave_used = $medical;
			/*Paternity */
			$paternity_bal = $totpaternityBal;
			$paternity_leave_used = $paternity;
			/*Escort */
			$escort_bal = $totescortBal;
			$escort_leave_used = $escort;
			/*Study*/
			$study_bal = $totstudyBal;
			$study_leave_used = $study;
	   
		if($total_bal > 90){
				$total_balance = 90;
		}else{
				$total_balance = $total_bal;
		}
		if($this->getRequest()->isPost()):		
			$form = $this->getRequest()->getPost()->toArray();
			if($form['cancel'] == "1"){
				$data = array(		
					 'id'          => $form['application_id'],
					 'status'      => '5',
					 'remark_log'  => "Leave Application Cancelled",
					 'author'      => $this->_author,
					 'modified'	   => $this->_modified								 
					 );
			}
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\LeaveTable::class)->save($data);	
			if($result){
				$this->flashMessenger()->addMessage("success^ Leave application successfully applied");
				return $this->redirect()->toRoute('leave', array('action' => 'leavedetail', 'id'=>$form['application_id']));
			}			
			else{
				$this->flashMessenger()->addMessage("error^ Failed to apply leave application, Try again");
				return $this->redirect()->toRoute('leave', array('action' => 'leavedetail', 'id'=>$form['application_id']));
			}		
		endif;
		return new ViewModel(array(
			'title'           => 'Leave Detail',
			'leavedetail'     => $this->getDefinedTable(Hr\LeaveTable::class)->get($this->_id),
			'leaveObj'        => $this->getDefinedTable(Hr\LeaveTable::class),
			'employeeObj'     => $this->getDefinedTable(Hr\EmployeeTable::class),
			'usersObj'        => $this->getDefinedTable(Administration\UsersTable::class),
			'leaveFlowObj'    => $this->getDefinedTable(Hr\LeaveFlowTable::class),
			'userID'          => $this->_login_id,
			'leaveActionObj'  => $this->getDefinedTable(Hr\LeaveActionTable::class),
			'userObj'         => $this->getDefinedTable(Administration\UsersTable::class),
			'ActivityLogObj'  => $this->getDefinedTable(Acl\ActivityLogTable::class),
			'casual_bal' 	  => $casual_bal,
			'earned_bal' 	  => $earned_bal,
			'total_leave'	  => $leave_taken,
			'total_bal'	 	  => $total_balance,
			'flowtransactionObj'  => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'       => $this->getDefinedTable(Administration\FlowActionTable::class), 
			'login_role'          => $this->_login_role,
			'activityObj'      => $this->getDefinedTable(Acl\ActivityLogTable::class),
		));	
	}
	
	/**
	 *  process leave action
	 */
	public function processAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){	
			$form = $this->getRequest()->getpost();
			$process_id	= '508';
			$flow_id = $form['flow'];
			if(empty($form['action'])):$action_id=0; else:$action_id = $form['action'];endif;
			$leave_id = $form['leave'];
			$remark = $form['remarks'];
			$application_focal=$form['focal'];
			$current_flow = $this->getDefinedTable(Administration\FlowTransactionTable::class)->get($flow_id);
			foreach($current_flow as $flow);
			$next_activity_no = $flow['activity'] + 1;
			$action_performed = $this->getDefinedTable(Administration\FlowActionTable::class)->getColumn($action_id, 'action');
			$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get(array('flow'=>$flow['flow'],'action_performed'=>$action_id));
			foreach($privileges as $privilege);
			$app_data = array(
				'id'		=> $leave_id,				
				'status' 	=> $privilege['status_changed_to'],			
				'modified'  => $this->_modified
			);
			$app_data = $this->_safedataObj->rteSafe($app_data);
			$this->_connection->beginTransaction();
			$app_result = $this->getDefinedTable(Hr\LeaveTable::class)->save($app_data);
			if($app_result):
				$activity_data = array(
						'process'      => $process_id,
						'process_id'   => $leave_id,
						'status'       => $privilege['status_changed_to'],
						'remarks'      => $remark,
						//'role'         => $flow['actor'],
						'send_to'      => $application_focal,
						'author'	   => $this->_author,
						'created'      => $this->_created,
						'modified'     => $this->_modified,  
				);
				$activity_data = $this->_safedataObj->rteSafe($activity_data);
				$activity_result = $this->getDefinedTable(Acl\ActivityLogTable::class)->save($activity_data);
				if($activity_result):
					//if($privilege['route_to_role']):
						$flow_data = array(
							'flow'          => $flow['flow'],
							'role_id'       =>$application_focal,
							'application'   => $leave_id,
							'activity'      => $next_activity_no,
							'actor'         => $privilege['route_to_role'],
							'status'        => $privilege['status_changed_to'],
							'action'        => $privilege['action'],
							'routing'       => $flow['actor'],
							'routing_status'=> $flow['status'],
							'description'   => $remark,
							'process'      =>  $process_id,
							'author'        => $this->_author,
							'created'       => $this->_created,
							'modified'      => $this->_modified
						);
						$flow_data = $this->_safedataObj->rteSafe($flow_data);
						$flow_result = $this->getDefinedTable(Administration\FlowTransactionTable::class)->save($flow_data);
						if($flow_result > 0):
							$this->notify($leave_id,$privilege['id'],$remark,$flow_result);
							$this->getDefinedTable(Administration\FlowTransactionTable::class)->performed($flow_id);
							$this->_connection->commit();
							$this->flashMessenger()->addMessage("success^ Successfully performed application action <strong>".$action_performed."</strong>!");
						else:
							$this->_connection->rollback();
							$this->flashMessenger()->addMessage("error^ Failed to update application work flow for <strong>".$action_performed."</strong> action.");
						endif;
					/*else:
						$remove_transaction_flows = $this->getDefinedTable(Administration\FlowTransactionTable::class)->remove($application_id);
						$this->_connection->commit();
						$this->flashMessenger()->addMessage("success^ Successfully Removed and approved or rejected or aborted the Work Plan.");
					endif;*/
				else:
					$this->_connection->rollback(); 
					$this->flashMessenger()->addMessage("error^ Failed to register the forward action in activity log.");
				endif;
			else:
				$this->_connection->rollback(); 
				$this->flashMessenger()->addMessage("error^ Failed to update Work Plan status for forward action.");
			endif;
			return $this->redirect()->toRoute('leave', array('action'=>'leavedetail', 'id' => $leave_id));
		}

		$login = array(
			'login_id'      => $this->_login_id,
			'login_role'    => $this->_login_role,
		); 
		//$focal=$this->getDefinedTable(Administration\UsersTable::class)->get(array('role',3));
		//
		$viewModel =  new ViewModel(array(
			'title'              => 'Protection Works Application Actions',
			'flow_id'            => $this->_id,
			'login'              => $login,
			'role'               =>$this->_login_role,
			'leaveObj'      	 => $this->getDefinedTable(Hr\LeaveTable::class),
			'flowprivilegeObj'   => $this->getDefinedTable(Administration\FlowPrivilegeTable::class),
			'flowtransactionObj' => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'      => $this->getDefinedTable(Administration\FlowActionTable::class),
			'roleObj'            => $this->getDefinedTable(Acl\RolesTable::class),
			'focals' 			 => $this->getDefinedTable(Administration\UsersTable::class)->getNotIn('0','employee'),
			'focalsObj' 		 => $this->getDefinedTable(Administration\UsersTable::class),
			'employeeObj'        => $this->getDefinedTable(Hr\EmployeeTable::class), 
			'departmentObj'      => $this->getDefinedTable(Administration\ActivityTable::class),
		));
		$viewModel->setTerminal(true);
        return $viewModel;		
	}
	/**
	 * Notification Action
	 */
	public function notify($leave_id,$privilege_id,$remarks = NULL,$flow_result)
	{
		$userlists='';
		$applications = $this->getDefinedTable(Hr\LeaveTable::class)->get($leave_id);
		foreach($applications as $app);
		$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get($privilege_id);
		foreach ($privileges as $flow) {
			$notify_msg = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($app['employee'],'full_name')." - ".$flow['description']."<br>[".$remarks."]";
			$notification_data = array(
				'route'         => 'leave',
				'action'        => 'leavedetail',
				'key' 		    => $leave_id,
				'description'   => $notify_msg,
				'author'	    => $this->_author,
				'created'       => $this->_created,
				'modified'      => $this->_modified,   
			);
			$notificationResult = $this->getDefinedTable(Acl\NotificationTable::class)->save($notification_data);
			if($notificationResult > 0 ){
				$notification_array = explode("|", $flow['route_notification_to']);
				if(sizeof($notification_array)>0){
					for($k=0;$k<sizeof($notification_array);$k++){
						$focalusers=$this->getDefinedTable(Administration\FlowTransactionTable::class)->get(array('id'=>$flow_result));
						foreach($focalusers as $applicationfocal):
						$focal_id = $applicationfocal['role_id'];
						//if($notification_array[$k]=='2'){
							if(!empty($focal_id)):
							   $userlists = $this->getDefinedTable(Administration\UsersTable::class)->get(array('id'=>$focal_id,'status'=>'1'));
							else:
								 $userlists = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>$notification_array[$k],'status'=>'1'));
							endif;
						//}
						endforeach;
					}
				}
				$email_array = [];
				$loop_count = 1;
				foreach($userlists as $userlist):
					$notify_data = array(
						'notification' => $notificationResult,
						'user'    	   => $userlist['id'],
						'flag'    	   => '0',
						'desc'    	   => $notify_msg,
						'author'	   => $this->_author,
						'created'      => $this->_created,
						'modified'     => $this->_modified,  
					);
					if($flow['notification'] == 1){
						$notifyResult = $this->getDefinedTable(Acl\NotifyTable::class)->save($notify_data);
					}
					if($loop_count == 1){
						$recipient_email = $userlist['email'];
						$recipient_name = $userlist['name'];
					}else{
						array_push($email_array, ['email'=>$userlist['email'],'name'=>$userlist['name']]);
					}
					$loop_count += 1;
				endforeach;
				
			}               	
		}
	}
	/**
	 * Notification Action
	 */
	public function notifyforencash($leave_id,$privilege_id,$remarks = NULL,$flow_result)
	{
		$userlists='';
		$applications = $this->getDefinedTable(Hr\LeaveEncashTable::class)->get($leave_id);
		foreach($applications as $app);
		$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get($privilege_id);
		foreach ($privileges as $flow) {
			$notify_msg = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($app['employee'],'full_name')." - ".$flow['description']."<br>[".$remarks."]";
			$notification_data = array(
				'route'         => 'leave',
				'action'        => 'encashmentdtl',
				'key' 		    => $leave_id,
				'description'   => $notify_msg,
				'author'	    => $this->_author,
				'created'       => $this->_created,
				'modified'      => $this->_modified,   
			);
			//echo '<pre>';print_r($notification_data);exit;
			$notificationResult = $this->getDefinedTable(Acl\NotificationTable::class)->save($notification_data);
			if($notificationResult > 0 ){
				$notification_array = explode("|", $flow['route_notification_to']);
				if(sizeof($notification_array)>0){
					for($k=0;$k<sizeof($notification_array);$k++){
						$focalusers=$this->getDefinedTable(Administration\FlowTransactionTable::class)->get(array('id'=>$flow_result));
						foreach($focalusers as $applicationfocal):
						$focal_id = $applicationfocal['role_id'];
						//if($notification_array[$k]=='2'){
							if(!empty($focal_id)):
							   $userlists = $this->getDefinedTable(Administration\UsersTable::class)->get(array('id'=>$focal_id,'status'=>'1'));
							else:
								 $userlists = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>$notification_array[$k],'status'=>'1'));
							endif;
						//}
						endforeach;
					}
				}
				//echo '<pre>';print_r($userlists);exit;
				$email_array = [];
				$loop_count = 1;
				foreach($userlists as $userlist):
					$notify_data = array(
						'notification' => $notificationResult,
						'user'    	   => $userlist['id'],
						'flag'    	   => '0',
						'desc'    	   => $notify_msg,
						'author'	   => $this->_author,
						'created'      => $this->_created,
						'modified'     => $this->_modified,  
					);

					if($flow['notification'] == 1){
						$notifyResult = $this->getDefinedTable(Acl\NotifyTable::class)->save($notify_data);
					}
					if($loop_count == 1){
						$recipient_email = $userlist['email'];
						$recipient_name = $userlist['name'];
					}else{
						array_push($email_array, ['email'=>$userlist['email'],'name'=>$userlist['name']]);
					}
					$loop_count += 1;
				endforeach;
				
			}               	
		}
	}
	public function encashprocessAction(){
	    $this->init();
		if($this->getRequest()->isPost()){	
			$form = $this->getRequest()->getpost();
			$process_id	= '520';
			$flow_id = $form['flow'];
			$action_id = $form['action'];
			$leave_id = $form['leave'];
			$remark = $form['remarks'];
			$application_focal=$form['focal'];
			$current_flow = $this->getDefinedTable(Administration\FlowTransactionTable::class)->get($flow_id);
			foreach($current_flow as $flow);
			$next_activity_no = $flow['activity'] + 1;
			$action_performed = $this->getDefinedTable(Administration\FlowActionTable::class)->getColumn($action_id, 'action');
			$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get(array('flow'=>$flow['flow'],'action_performed'=>$action_id));
			foreach($privileges as $privilege);
			$applicant = $this->getDefinedTable(Hr\LeaveEncashTable::class)->getColumn(array('id'=>$leave_id),'employee');
			$user = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('employee'=>$applicant),'id');
			$location = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('employee'=>$applicant),'location');
			//print_r($user);exit;
			//$this->_connection->beginTransaction();
			$encash_update = array(
				'id'		=> $leave_id,				
				'status' 	=> $privilege['status_changed_to'],			
				'modified'  => $this->_modified
			   );
			$encash_update = $this->_safedataObj->rteSafe($encash_update);
			$this->_connection->beginTransaction();
			$encash_result = $this->getDefinedTable(Hr\LeaveEncashTable::class)->save($encash_update);
			
			if($encash_result):
				//$this->_connection->beginTransaction(); //***Transaction begins here***//
				if($action_id==3):
					$activity_data = array(
						'process'      => $process_id,
						'process_id'   => $leave_id,
						'status'       => $privilege['status_changed_to'],
						'remarks'      => $remark,
						//'role'         => $flow['actor'],
						'send_to'      => 146,
						'author'	   => $this->_author,
						'created'      => $this->_created,
						'modified'     => $this->_modified,  
				);
			elseif($action_id==4):
				$activity_data = array(
						'process'      => $process_id,
						'process_id'   => $leave_id,
						'status'       => $privilege['status_changed_to'],
						'remarks'      => $remark,
						//'role'         => $flow['actor'],
						'send_to'      => $user,
						'author'	   => $this->_author,
						'created'      => $this->_created,
						'modified'     => $this->_modified,  
				);
			else:
				$activity_data = array(
						'process'      => $process_id,
						'process_id'   => $leave_id,
						'status'       => $privilege['status_changed_to'],
						'remarks'      => $remark,
						//'role'         => $flow['actor'],
						'send_to'      => $application_focal,
						'author'	   => $this->_author,
						'created'      => $this->_created,
						'modified'     => $this->_modified,  
				);
			endif;
				$activity_data = $this->_safedataObj->rteSafe($activity_data);
				$activity_result = $this->getDefinedTable(Acl\ActivityLogTable::class)->save($activity_data);
				if($activity_result):
					//if($privilege['route_to_role']):

					/**When Approved, it hits the transaction */	
					if($action_id==3):
						/** */
						$region = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('id'=>$this->_author),'region');
						$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($location, 'prefix');
						$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(12,'prefix');
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
					//$location= $this->_user->location;
					$encashAmount = $this->getDefinedTable(Hr\LeaveEncashTable::class)->getColumn(array('id'=>$leave_id),'payment_amount');
					$basicAmount = $this->getDefinedTable(Hr\LeaveEncashTable::class)->getColumn(array('id'=>$leave_id),'basic');
					$deduction = $this->getDefinedTable(Hr\LeaveEncashTable::class)->getColumn(array('id'=>$leave_id),'deduction');
					$encashdate = $this->getDefinedTable(Hr\LeaveEncashTable::class)->getColumn(array('id'=>$leave_id),'encash_date');
							$data = array(
								'voucher_date' 		=> $encashdate,
								'voucher_type' 		=> 12,
								'region'   			=>$region,
								'doc_id'   			=>"Encashment",
								'voucher_no' 		=> $voucher_no,
								'voucher_amount' 	=> str_replace( ",", "",$encashAmount),
								'status' 			=> 3, // status initiated 
								'remark'			=>'',
								'author' 			=>$user,
								'created' 			=>$this->_created,  
								'modified' 			=>$this->_modified,
							);
								$resultTrans = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data);
							//if($resultTrans >0){
								$flow=array(
									'flow' 				=> 4,
									'application' 		=> $resultTrans,
									'activity'			=>$location,
									//'role_id'   		=>130,
									'actor'   			=>6,
									'action' 			=> "3|4",
									'routing' 			=> 2,
									'status' 			=> 3, // status initiated 
									'routing_status'	=> 2,
									'action_performed'	=> 1,
									'description'		=>"Encash Expense",
									'author' 			=>$this->_author,
									'created' 			=>$this->_created,  
									'modified' 			=>$this->_modified,
								);
								$flow=$this->getDefinedTable(Administration\FlowTransactionTable::class)->save($flow);
								$transactionDtls1 = array(
									'transaction' => $resultTrans,
									'voucher_dates' => $data['voucher_date'],
									'voucher_types' => 12,
									'location' => $location,
									'head' =>'170',
									'sub_head' =>'2594',
									'bank_ref_type' => '',
									'debit' =>$basicAmount,
									'credit' =>'0.00',
									'ref_no'=> 'Encashment', 
									'type' => '1',//user inputted  data  
									'status' => 3, // status appied
									'activity'=>$location,
									'author' =>$user,
									'created' =>$this->_created,
									'modified' =>$this->_modified,
								);
								//print_r();exit;
							$transactionDtls1 = $this->_safedataObj->rteSafe($transactionDtls1);
							$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($transactionDtls1);
							/** Credit to HQ bank Account */
							$transactionDtls2 = array(
									'transaction' => $resultTrans,
									'voucher_dates' => $data['voucher_date'],
									'voucher_types' => 12,
									'location' => $location,
									'head' =>'150',
									'sub_head' =>'2553',
									'bank_ref_type' => '',
									'debit' =>'0.00',
									'credit' =>$deduction,
									'ref_no'=> 'Encashment', 
									'type' => '1',//user inputted  data
									'status' => 3, // status applied
									'activity'=>$data['voucher_amount'],
									'author' =>$user,
									'created' =>$this->_created,
									'modified' =>$this->_modified,
								);
								//echo'<pre>';print_r($transactionDtls2);
								$transactionDtls2 = $this->_safedataObj->rteSafe($transactionDtls2);
								$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($transactionDtls2);
								$transactionDtls3 = array(
									'transaction' => $resultTrans,
									'voucher_dates' => $data['voucher_date'],
									'voucher_types' => 12,
									'location' => $location,
									'head' =>'36',
									'sub_head' =>'172',
									'bank_ref_type' => '',
									'debit' =>'0.00',
									'credit' =>$encashAmount,
									'ref_no'=> 'Encashment', 
									'type' => '1',//user inputted  data
									'status' => 3, // status applied
									'activity'=>$data['voucher_amount'],
									'author' =>$user,
									'created' =>$this->_created,
									'modified' =>$this->_modified,
								);
								$transactionDtls3 = $this->_safedataObj->rteSafe($transactionDtls3);
								$result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($transactionDtls3);
						//	endforeach;
							//}
							
								$updateEncash = array(
									'id' => $leave_id,
									'transaction_id' => $resultTrans,
									'author' =>$this->_author,
									'created' =>$this->_created,
									'modified' =>$this->_modified,
								);
								$updateEncash = $this->_safedataObj->rteSafe($updateEncash);
								$result4 = $this->getDefinedTable(Hr\LeaveEncashTable::class)->save($updateEncash);	
							/*	$employee = $this->getDefinedTable(Hr\LeaveEncashTable::class)->get(array('id'=>$leave_id));
								foreach($employee as $employees);
								$leavebalance = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn(array('id'=>$employees['employee']),'leave_balance');
								$leave=$leavebalance-$employees['no_of_encashed_days'];
								$updateLeaveBalance = array(
									'id' => $employees['employee'],
									'leave_balance' => $leave,
									'author' =>$this->_author,
									'created' =>$this->_created,
									'modified' =>$this->_modified,
								);
								$updateLeaveBalance = $this->_safedataObj->rteSafe($updateLeaveBalance);
								$result4 = $this->getDefinedTable(Hr\EmployeeTable::class)->save($updateLeaveBalance);	*/
						if($result4):
							$notification_data = array(
								'route'         => 'transaction',
								'action'        => 'againstdebit',
								'key' 		    => $resultTrans,
								'description'   => 'Encashment Sent',
								'author'	    => $this->_author,
								'created'       => $this->_created,
								'modified'      => $this->_modified,   
							);
							//print_r($notification_data);exit;
							$notificationResult = $this->getDefinedTable(Acl\NotificationTable::class)->save($notification_data);
							if($notificationResult > 0 ){	
								$user = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>array('5')));
								foreach($user as $row):						    
									$user_location_id = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($row['id'], 'location');
									//if($user_location_id == $location ):						
										$notify_data = array(
											'notification' => $notificationResult,
											'user'    	   => $row['id'],
											'flag'    	 => '0',
											'desc'    	 => 'New Encashment sent',
											'author'	 => $this->_author,
											'created'    => $this->_created,
											'modified'   => $this->_modified,  
										);
										$notifyResult = $this->getDefinedTable(Acl\NotifyTable::class)->save($notify_data);
									//endif;
								endforeach;
							}
						endif;
					elseif($action_id==4):
						$flow_data = array(
							'flow'          => $flow['flow'],
							'role_id'       =>$user,
							'application'   => $leave_id,
							'activity'      => $next_activity_no,
							'actor'         => $privilege['route_to_role'],
							'status'        => $privilege['status_changed_to'],
							'action'        => $privilege['action'],
							'routing'       => $flow['actor'],
							'routing_status'=> $flow['status'],
							'description'   => $remark,
							'process'      	=> $process_id,
							'author'        => $this->_author,
							'created'       => $this->_created,
							'modified'      => $this->_modified
						);
						$flow_data = $this->_safedataObj->rteSafe($flow_data);
						$flow_result = $this->getDefinedTable(Administration\FlowTransactionTable::class)->save($flow_data);
						$this->notifyforencash($leave_id,$privilege['id'],$remark,$flow_result);
					else:
						$flow_data = array(
							'flow'          => $flow['flow'],
							'role_id'       =>$application_focal,
							'application'   => $leave_id,
							'activity'      => $next_activity_no,
							'actor'         => $privilege['route_to_role'],
							'status'        => $privilege['status_changed_to'],
							'action'        => $privilege['action'],
							'routing'       => $flow['actor'],
							'routing_status'=> $flow['status'],
							'description'   => $remark,
							'process'      	=> $process_id,
							'author'        => $this->_author,
							'created'       => $this->_created,
							'modified'      => $this->_modified
						);
						$flow_data = $this->_safedataObj->rteSafe($flow_data);
						$flow_result = $this->getDefinedTable(Administration\FlowTransactionTable::class)->save($flow_data);
						$this->notifyforencash($leave_id,$privilege['id'],$remark,$flow_result);
					endif;
						
					
				
						if($encash_result > 0):
							
							$this->getDefinedTable(Administration\FlowTransactionTable::class)->performed($flow_id);
							$this->_connection->commit();
							$this->flashMessenger()->addMessage("success^ Successfully performed application action <strong>".$action_performed."</strong>!");
						else:
							$this->_connection->rollback();
							$this->flashMessenger()->addMessage("error^ Failed to update application work flow for <strong>".$action_performed."</strong> action.");
						endif;
					/*else:
						$remove_transaction_flows = $this->getDefinedTable(Administration\FlowTransactionTable::class)->remove($application_id);
						$this->_connection->commit();
						$this->flashMessenger()->addMessage("success^ Successfully Removed and approved or rejected or aborted the Work Plan.");
					endif;*/
				else:
					$this->_connection->rollback(); 
					$this->flashMessenger()->addMessage("error^ Failed to register the forward action in activity log.");
				endif;
			else:
				$this->_connection->rollback(); 
				$this->flashMessenger()->addMessage("error^ Failed to update Work Plan status for forward action.");
			endif;
			return $this->redirect()->toRoute('leave', array('action'=>'encashmentdtl', 'id' => $leave_id));
		}

		$login = array(
			'login_id'      => $this->_login_id,
			'login_role'    => $this->_login_role,
		); 		
		$viewModel = new ViewModel(array(			
			'title' 		=> 'Leave Encashment Application',
			'flow_id'            => $this->_id,
			'login'              => $login,
			'role'               =>$this->_login_role,
			'leaveObj'      	 => $this->getDefinedTable(Hr\LeaveEncashTable::class),
			'flowprivilegeObj'   => $this->getDefinedTable(Administration\FlowPrivilegeTable::class),
			'flowtransactionObj' => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'      => $this->getDefinedTable(Administration\FlowActionTable::class),
			'roleObj'            => $this->getDefinedTable(Acl\RolesTable::class),
			'focals' => $this->getDefinedTable(Administration\UsersTable::class)->getNotIn('0','employee'),
			'focalsObj' 		 => $this->getDefinedTable(Administration\UsersTable::class),
			'employeeObj'        => $this->getDefinedTable(Hr\EmployeeTable::class), 
			'departmentObj'        => $this->getDefinedTable(Administration\ActivityTable::class),    
		));
		$viewModel->setTerminal(True);
		return $viewModel;
	}
	
	/**
	 *edit leave detail
	 */
	public function editleaveAction(){
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost()->toArray();
			$authorEmpID = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author, 'employee');	
			$leaveOfficialResult = $this->getDefinedTable(Administration\UsersTable::class)->get(array('id'=>$this->_author,'role'=>'4'));
			if($form['applicant'] != $authorEmpID && sizeof($leaveOfficialResult) > 0 ){	$leave_officier = $this->_author; }else{ $leave_officier = '0'; }		
			if(empty($form['delegation'])):$delegation=0;else:$delegation=$form['delegation'];endif;
			if($form['half']==1):$no_of_days=0.5;else:$no_of_days=$form['no_of_days'];endif;
			if($form['declaration'] == '1' && $form['applicant'] > 0 ):
			     $data = array(		
					'id'		   => $form['leaveID'],
					'employee'     => $form['applicant'],
					//'leave_type'   => $form['leave_type'],
					'start_date'   => $form['start_date'],
					'end_date'     => $form['end_date'],
					'no_of_days'   => $no_of_days,
					//'contact'      => $form['contact'],
					'actual_leave_taken'  => $no_of_days,
					'delegation'   => $delegation,
					//'sanction_order_no'  => $form['sanction_order_no'],
					'remarks'  			 => $form['remarks'],
					'leave_official'   => $leave_officier,
					'author'       => $this->_author,
					'modified'     => $this->_modified					
				);	
                $data = $this->_safedataObj->rteSafe($data);
			    $result = $this->getDefinedTable(Hr\LeaveTable::class)->save($data);	
				if($result > 0 ){
				   $this->flashMessenger()->addMessage("success^ Successfully Edited Leave application");
				   return $this->redirect()->toRoute('leave', array('action' => 'leavedetail', 'id'=>$form['leaveID']));
				}			
				else{
				  $this->flashMessenger()->addMessage("error^ Failed to Edit leave application, Try again");
				  return $this->redirect()->toRoute('leave', array('action' => 'editleave', 'id'=>$form['leaveID']));
				}				
			endif; 
		endif;
		$viewModel = new ViewModel(array(
			'title'=> 'Edit Leave Application',
		    'leavedetails' => $this->getDefinedTable(Hr\LeaveTable::class)->get($this->_id),
			'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
			'leavetypes' => $this->getDefinedTable(Hr\LeaveTypeTable::class),
			'userObj'  => $this->getDefinedTable(Administration\UsersTable::class),
			'userID'       => $this->_login_id,
			'login_emp_ID' => $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'employee'),
		));
		return $viewModel;
	}
	
	/**
	 * leave encashment
	*/
	public function encashmentAction(){
		$this->init();
		
		return new ViewModel(array(
			'title'			=> 'Leave Encashment',
			'userID'  		=> $this->_login_id,
			'encashObj' 	=> $this->getDefinedTable(Hr\LeaveEncashTable::class),
			'employeeObj'   => $this->getDefinedTable(Hr\EmployeeTable::class),
			'userObj'       => $this->getDefinedTable(Administration\UsersTable::class),
			'flowtransObj'	=>$this->getDefinedTable(Administration\FlowTransactionTable::class),
		));
	}
	
	/**
	 *leave encashment
	*/
	public function getsalarydtlAction(){
		$this->init();
		$form = $this->getRequest()->getPost();		
		$employeeID = $form['employee_id'];
		$salaryDtls = $this->getDefinedTable(Hr\PaystructureTable::class)->get(array('sd.employee' => $employeeID, 'pht.deduction' => 0, 'sd.pay_head' => 1));  
		$total_earning = 0;
		
		foreach($salaryDtls as $dtl):
		     $total_earning += $dtl['amount'];
		endforeach;
	
		/*Slab*/
		$encashSlabs = $this->getDefinedTable(Hr\PaySlabTable::class)->getPaySlabForTotalEarning($total_earning);
		//print_r($encashSlabs);exit;
		foreach($encashSlabs as $encashSlab);
			$PIT_deduct  = $encashSlab['id'];
			
			if($encashSlab['rate'] > 0 OR empty($encashSlab['rate'])){
				if($encashSlab['rate'] != 0){		            
				/*	if($total_earning > 150200){
						$PIT_deduct = (((($total_earning- 83333) / 100) * $encashSlab['rate']) + $encashSlab['base']);
					}else{	 */           
						//$PIT_deduct = (((($total_earning -  $encashSlab['from_range']) / 100) * $encashSlab['rate']) + ($encashSlab['base']));
					$from= $encashSlab['from_range'];
					$count=0;
					while($total_earning >= $from){
						$count++;
						if($count==1){
							$PIT_deduct= $encashSlab['base'];
						}else{
							$PIT_deduct= ($encashSlab['rate'] * ($count-1))+ $encashSlab['base'];

						}
						$from+=100; 
					}
				//}
				
				}
				else{
					$PIT_deduct = $encashSlab['value']; 
				}
			}
	/*	$deductions = $this->getDefinedTable(Hr\PaystructureTable::class)->get(array('sd.employee' => $employeeID, 'sd.pay_head' => '44'));		
		
		foreach($deductions as $deduct):
		     $PIT_deduct  = $deduct['amount'];
		endforeach;*/
		
		echo json_encode(array(
			'gross_salary' => $total_earning,
			'PIT_deduct'   => ceil($PIT_deduct)
		));
		exit;
	}
	
	/**
	 *Employee Details
	*/
	public function displayempdtlAction(){
	   $this->init();
	   $employeeID = $this->_id;
	   $viewModel =  new ViewModel(array(
	        'empDtls' => $this->getDefinedTable(Hr\EmployeeTable::class)->get($employeeID),
	   ));
	   $viewModel->setTerminal('false');
       return $viewModel;		
	}
	/**
	 *Add leave encashment
	*/
	public function addencashmentAction(){
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost()->toArray();		
		    $encashedDate = $this->getDefinedTable(Hr\LeaveEncashTable::class)->getMax($column='encash_date', $where=array('employee'=>$form['applicant'],'status'=>array(2,8)));
			if(strlen($encashedDate)>0){
				$today = date('Y-m-d');
				$timestamp_start = strtotime($encashedDate);
                $timestamp_end = strtotime($today);
				$difference = abs($timestamp_end - $timestamp_start);
				$years = floor($difference/(60*60*24*365));
				//print_r($years );exit;
				if($years == 0){
					$this->flashMessenger()->addMessage("notice^ Leave Encashment failed as you have applied on ".$encashedDate ." and you can only apply after one year");
				    return $this->redirect()->toRoute('leave', array('action' => 'encashment'));
				}
			}
			
				$data = array(							
				'employee'           => $form['applicant'],
				'encash_date'        => $form['encash_date'],
				'no_of_encashed_days'=> $form['no_of_encashed_days'],
				'leave_balance'      => $form['leave_balance'],
				'leave_balance_date' => date('Y-m-d'),
				'payment_amount'     => $form['payment_amount'],
				//'encash_sub_head'    => '',
				'deduction'   		 => $form['deduction'],
				'basic'   		 => $form['gross_salary'],
				//'deduction_sub_head' => '',
				'status'             => '1',
				'remarks'  		     => $form['remarks'],
				'author'             => $this->_author,
				'created'            => $this->_created,
				'modified'           => $this->_modified					
				);
				$data = $this->_safedataObj->rteSafe($data);
			    $result = $this->getDefinedTable(Hr\LeaveEncashTable::class)->save($data);	
				$flow_result = $this->flowinitiation('520', $result);
				if($result > 0 ){
				   $this->flashMessenger()->addMessage("success^ Successfully saved the Leave Encashment");
				   return $this->redirect()->toRoute('leave', array('action' => 'encashmentdtl', 'id'=>$result));
				}			
				else{
				  $this->flashMessenger()->addMessage("error^ Failed to apply leave Encashment, Try again");
				  return $this->redirect()->toRoute('leave', array('action' => 'addencashment'));
				}	
		endif;
		return new ViewModel(array(
			'title'=> 'Add Leave Encashment',
			'userID'       => $this->_login_id,
			'login_emp_ID' => $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'employee'),
			'employeeObj'  => $this->getDefinedTable(Hr\EmployeeTable::class),
			'leaveTypes'   => $this->getDefinedTable(Hr\LeaveTypeTable::class)->getAll(),
			'userObj'      => $this->getDefinedTable(Administration\UsersTable::class),
		));
	}
	
	public function encashmentdtlAction(){
		$this->init();
		$params = explode("-", $this->_id);
		if (isset($params['1']) && $params['1'] == '1' && isset($params['2']) && $params['2'] > 0) {
			$flag = $this->getDefinedTable(Acl\NotifyTable::class)->getColumn($params['2'], 'flag'); 
				if($flag == "0") {
					$notify = array('id' => $params['2'], 'flag'=>'1');
					$this->getDefinedTable(Acl\NotifyTable::class)->save($notify); 	
				}				
		}
		
     	return new ViewModel(array(
			'title'        => 'Leave Encashment Details',
			'encash_id'    => $this->_id,
			'userID'       => $this->_login_id,	
			'encashDtls'   => $this->getDefinedTable(Hr\LeaveEncashTable::class)->get($this->_id),		
			'flowtransactionObj'  => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'usersObj'     => $this->getDefinedTable(Administration\UsersTable::class),
			'employeeObj'  => $this->getDefinedTable(Hr\EmployeeTable::class),
			//'leaveFlowObj' => $this->getDefinedTable(Hr\LeaveFlowTable::class),
			'leaveActionObj'  => $this->getDefinedTable(Hr\LeaveActionTable::class),
			'flowactionObj'       => $this->getDefinedTable(Administration\FlowActionTable::class), 
			'activityObj'      => $this->getDefinedTable(Acl\ActivityLogTable::class),
		));
	}
	/**
	 * FLOW Function -- Initiation
	 */
	public function flowinitiation($process_id, $application_id)
	{
		$flow_id = $this->getDefinedTable(Administration\FlowTable::class)->getColumn(array('process'=>$process_id),'id');
		if($flow_id):
			$flow_role = $this->getDefinedTable(Administration\FlowTable::class)->getColumn($flow_id,'role');
			$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get(array('flow'=>$flow_id,'action_performed'=>'0'));
			foreach($privileges as $privilege);
			$data = array(
				'flow'             => $flow_id,
				'application'      => $application_id,
				'process'          => $process_id,
				'activity'         => 1,
				'actor'            => $privilege['route_to_role'],
				'status'           => $privilege['status_changed_to'],
				'action'           => $privilege['action'],
				'routing'          => $flow_role,
				'routing_status'   => $privilege['status_changed_to'],
				'action_performed' => 0,
				'description'      => $privilege['description'],
				'author'           => $this->_author,
				'created'          => $this->_created,
				'modified'         => $this->_modified
			);
			$data = $this->_safedataObj->rteSafe($data);
			$flow_result = $this->getDefinedTable(Administration\FlowTransactionTable::class)->save($data);
			return $flow_result;
		else:
			return '0';
		endif;
	}
	/**
	 *  holidays 
	 */
	public function holidaysAction()
	{
		$this->init();		
		return new ViewModel(array(
			'title' => 'Holidays',
			'holidays' => $this->getDefinedTable(Hr\HolidayTable::class)->getAll(),
		));
		
	} 
	
	/**
	 * add holidays action
	 */
	public function addholidaysAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(	
					'holi_date' => $form['holi_date'],
					'description' => $form['description'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\HolidayTable::class)->save($data);	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Holiday successfully added");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new holiday");
			endif;
			return $this->redirect()->toRoute('leave',array('action' => 'holidays'));			 
		}
		$ViewModel = new ViewModel();		 
		$ViewModel->setTerminal(True);
		return $ViewModel;	
	}
	/**
	 * edit holidays action
	 */
	public function editholidaysAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(	
					'id' => $form['id'],
					'holi_date' => $form['holi_date'],
					'description' => $form['description'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Hr\HolidayTable::class)->save($data);	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Holiday successfully added");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new holiday");
			endif;
			return $this->redirect()->toRoute('leave',array('action' => 'holidays'));			 
		}
	 $viewModel =  new ViewModel(array(
	        'holidays' => $this->getDefinedTable(Hr\HolidayTable::class)->get($this->_id),
	   ));
	   $viewModel->setTerminal('false');
       return $viewModel;	
	}
	/**
	* Leave Reports 
	*/
	public function leavedailyreportAction()
	{
		{	
			$this->init();
			if($this->getRequest()->isPost())
			{
				$form = $this->getRequest()->getPost();
				$date = $form['start_date'];
			}else{
				
				$date =date('Y-m-d');
				}

			$data = array(
				'start_date' => $date,
			);
			
			$serviceTarriffTable = $this->getDefinedTable(Hr\LeaveTable::class)->getDailyLeave($data,$date, array('status'=>8));
			$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($serviceTarriffTable));
			$page = 1;
			if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
			$paginator->setCurrentPageNumber((int)$page);
			$paginator->setItemCountPerPage(1000);
			$paginator->setPageRange(8);
			
			return new ViewModel(array(
				'title' => 'Service Tarriff',
				'paginator'       => $paginator,
				'data'            => $data,
				'page'            => $page,
				'employeeObj'      => $this->getDefinedTable(Hr\EmployeeTable::class),
				'divisionObj'      => $this->getDefinedTable(Administration\ActivityTable::class),
				'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
				'leaveObj'      => $this->getDefinedTable(Hr\LeaveTypeTable::class),
			)); 
		} 

		
	}
		/**
	* Leave Reports 
	*/
	public function leaveyearlyreportAction()
	{
		{	
			$this->init();
			$array_id = explode("_", $this->_id);
		
			$region = (sizeof($array_id)>1)?$array_id[1]:'-1';
			$location = (sizeof($array_id)>1)?$array_id[2]:'-1';
			$leave = (sizeof($array_id)>1)?$array_id[3]:'-1';
			//$userlocreport = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
			if($this->getRequest()->isPost())
			{
				$form      	 = $this->getRequest()->getPost();
				
				$region    = $form['region'];
				$location    = $form['location'];
				$leave    	 = $form['leave'];
				$start_date  = $form['start_date'];
				$end_date    = $form['end_date'];
			}else{
				
				$region = '-1';
				$location = '-1';
				$leave = '-1';
				$start_date =date('Y-m-d');
				$end_date   = date('Y-m-d');
			}

			$data = array(
				
				'region'  => $region,
				'location'  => $location,
				'leave'  => $leave,
				'start_date' => $start_date,
				'end_date'   => $end_date,
			);
			
			//$serviceTarriffTable = $this->getDefinedTable(Hr\LeaveTable::class)->getYearlyLeave($data,$start_date,$end_date);
			$paginator = $this->getDefinedTable(Hr\LeaveTable::class)->getYearlyLeave($data,$start_date,$end_date,'ld.leave_type',array('ld.status'=>8));
			
			return new ViewModel(array(
				'title' => 'Leave Yearly Report',
				'paginator'       => $paginator,
				'data'            => $data,
				'employeeObj'      => $this->getDefinedTable(Hr\EmployeeTable::class),
				'region'      => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'depObj'      => $this->getDefinedTable(Administration\DepartmentTable::class),
				'leaveObj'      => $this->getDefinedTable(Hr\LeaveTypeTable::class),	
				'locations'      => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),	
				'statusObj'      => $this->getDefinedTable(Acl\StatusTable::class),
				'regionObj'      => $this->getDefinedTable(Administration\RegionTable::class),
				'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
			)); 
		} 

		
	}
	/**
	* Leave Balance Reports 
	*/
	public function leavebalancereportAction()
	{
		{	
			$this->init();
			$array_id = explode("_", $this->_id);
			$department = (sizeof($array_id)>1)?$array_id[0]:'-1';
			$region = (sizeof($array_id)>1)?$array_id[1]:'-1';
			$location = (sizeof($array_id)>1)?$array_id[2]:'-1';
			//$userlocreport = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
			$year=date('Y');
			if($this->getRequest()->isPost())
			{
				$form      	 = $this->getRequest()->getPost();
				$department      = $form['department'];
				$region      = $form['region'];
				$location    = $form['location'];
				$start_date  = $form['start_date'];
				$end_date    = $form['end_date'];
			}else{
				$department = '-1';
				$region = '-1';
				$location = '-1';
			$start_date =$year.'01-01';
				$end_date   = date('Y-m-d');
			}

			$data = array(
				'department'  => $department,
				'region'  => $region,
				'location'  => $location,
				'start_date' => $start_date,
				'end_date'   => $end_date,
			);
			$employee = $this->getDefinedTable(Hr\EmployeeTable::class)->getEmployeeByLoc($data);
		//echo '<pre>';print_r($employee);exit;
		
			return new ViewModel(array(
				'title' => 'Leave Balance report',
				//'paginator'       => $paginator,
				'data'            => $data,
				'employeeObj'      => $this->getDefinedTable(Hr\EmployeeTable::class),
				'employee'      => $employee,
				'region'      => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'depObj'      => $this->getDefinedTable(Administration\DepartmentTable::class),
				'leaveObj'      => $this->getDefinedTable(Hr\LeaveTypeTable::class),	
				'leavesObj'      => $this->getDefinedTable(Hr\LeaveTable::class),	
				'locations'      => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),	
				'statusObj'      => $this->getDefinedTable(Acl\StatusTable::class),
				'divisionObj'      => $this->getDefinedTable(Administration\ActivityTable::class),
				'leaveenObj' =>$this->getDefinedTable(Hr\LeaveEncashTable::class),
				'empObj'=> $this->getDefinedTable(Hr\EmployeeTable::class),
				'emphisObj'=>$this->getDefinedTable(Hr\EmpHistoryTable::class),
				'leavesObj'=> $this->getDefinedTable(Hr\LeaveTable::class),
				'regionObj'      => $this->getDefinedTable(Administration\RegionTable::class),
				'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
				
			)); 
		} 

		
	}
	/**
	* Leave Encash Reports 
	*/
	public function leaveencashreportAction()
	{
		{	
			$this->init();
			$array_id = explode("_", $this->_id);
			$department = (sizeof($array_id)>1)?$array_id[0]:'-1';
			$region = (sizeof($array_id)>1)?$array_id[1]:'-1';
			$location = (sizeof($array_id)>1)?$array_id[2]:'-1';
			$leave = (sizeof($array_id)>1)?$array_id[3]:'-1';
			//$userlocreport = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
			if($this->getRequest()->isPost())
			{
				$form      	 = $this->getRequest()->getPost();
				$department      = $form['department'];
				$region      = $form['region'];
				$location    = $form['location'];
				$start_date  = $form['start_date'];
				$end_date    = $form['end_date'];
			}else{
				$department = '-1';
				$region = '-1';
				$location = '-1';
			$start_date =date('Y-m-d');
				$end_date   = date('Y-m-d');
			}

			$data = array(
				'department'  => $department,
				'region'  => $region,
				'location'  => $location,
				'start_date' => $start_date,
				'end_date'   => $end_date,
			);
			$paginator = $this->getDefinedTable(Hr\LeaveEncashTable::class)->getEncashReport($data,$start_date,$end_date,array('ld.status'=>4));
		
			return new ViewModel(array(
				'title' => 'Leave Encash report',
				'paginator'       => $paginator,
				'data'            => $data,
				'employeeObj'      => $this->getDefinedTable(Hr\EmployeeTable::class),
				'region'      => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'depObj'      => $this->getDefinedTable(Administration\DepartmentTable::class),
				'leaveObj'      => $this->getDefinedTable(Hr\LeaveTypeTable::class),	
				'locations'      => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),	
				'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),	
				'statusObj'      => $this->getDefinedTable(Acl\StatusTable::class),
				'regionObj'      => $this->getDefinedTable(Administration\RegionTable::class),
				'leaveenObj' =>$this->getDefinedTable(Hr\LeaveEncashTable::class),
				'empObj'=> $this->getDefinedTable(Hr\EmployeeTable::class),
				'emphisObj'=>$this->getDefinedTable(Hr\EmpHistoryTable::class),
				'leavesObj'=> $this->getDefinedTable(Hr\LeaveTable::class),
				
			)); 
		} 

		
	}
	/***********************************************************************ALL GET FUNCTIONS FOR LEAVE CONTROLLER****************************************************************/
	/**
	 * Include weekends and holidays 
	 */
	public function getweekendsaaAction()
	{		
		$form = $this->getRequest()->getPost();
		$startDate = strtotime($form['start_date']); // Convert string to Unix timestamp
		$endDate = strtotime($form['end_date']); //$form['start_date'];

		// Initialize an array to store the dates
		$dateArray = array();

		// Loop through each date and store them in the array
		$currentDate = $startDate;
		while ($currentDate <= $endDate) {
			$dateArray[] = date('Y-m-d', $currentDate); // Format and store the current date
			$currentDate = strtotime('+1 day', $currentDate); // Move to the next day
		}

		// Get holidays
		$holidays = $this->getDefinedTable(Hr\HolidayTable::class)->getAll();
		// Count the number of days where dates are not holidays
		$nonHolidayDays = 0;
		foreach ($dateArray as $date) {
			$isHoliday = false;
			foreach ($holidays as $holiday) {
				if ($date == $holiday['holi_date']) {
					$isHoliday = true;
					break;
				}
			}
			if (!$isHoliday) {
				$nonHolidayDays++;
			}
		}

		echo json_encode(array(
			'non_holiday_days' => $nonHolidayDays,
		));

		exit;
	}
	/**
	 * Include weekends and holidays 
	 */
	public function getweekendsAction()
		{       
			$form = $this->getRequest()->getPost();
			$startDate = strtotime($form['start_date']); // Convert start date string to Unix timestamp
			$endDate = strtotime($form['end_date']); // Convert end date string to Unix timestamp

			// Calculate the difference in days between start date and end date
			$nonHolidayDays = floor(($endDate - $startDate) / (60 * 60 * 24)) + 1; // Adding 1 to include both start and end dates

			echo json_encode(array(
				'non_holiday_days' => $nonHolidayDays,
			));

			exit;
		}
	
	/**
	 * Exclude weekends and holidays 
	 */
	public function getholidaysAction()
	{       
		$form = $this->getRequest()->getPost();
		$startDate = strtotime($form['start_date']); // Convert string to Unix timestamp
		$endDate = strtotime($form['end_date']); //$form['start_date'];

		// Initialize an array to store the dates
		$dateArray = array();

		// Loop through each date and store them in the array, excluding weekends
		$currentDate = $startDate;
		while ($currentDate <= $endDate) {
			// Check if the current day is not a Saturday or Sunday
			if (date('N', $currentDate) < 6) {
				$dateArray[] = date('Y-m-d', $currentDate); // Format and store the current date
			}
			$currentDate = strtotime('+1 day', $currentDate); // Move to the next day
		}

		// Get holidays
		$holidays = $this->getDefinedTable(Hr\HolidayTable::class)->getAll();
		// Count the number of days where dates are not holidays
		$nonHolidayDays = 0;
		foreach ($dateArray as $date) {
			$isHoliday = false;
			foreach ($holidays as $holiday) {
				if ($date == $holiday['holi_date']) {
					$isHoliday = true;
					break;
				}
			}
			if (!$isHoliday) {
				$nonHolidayDays++;
			}
		}

		echo json_encode(array(
			'non_holiday_days' => $nonHolidayDays,
		));

		exit;
	}
	
	
	/*Saturdays Included for GPOS**/
	
	/**
 * Include weekends and holidays, treating Saturdays as 0.5 days
 */
	public function getweekendsGpoAction()
	{       
		$form = $this->getRequest()->getPost();
		$startDate = strtotime($form['start_date']); // Convert start date string to Unix timestamp
		$endDate = strtotime($form['end_date']); // Convert end date string to Unix timestamp

		// Initialize total days counter
		$totalDays = 0.0;

		// Loop through each day in the date range
		$currentDate = $startDate;
		while ($currentDate <= $endDate) {
			$dayOfWeek = date('N', $currentDate);

			// Check if the current day is a Saturday
			if ($dayOfWeek == 6) {
				$totalDays += 0.5; // Count Saturday as 0.5 days
			} else {
				$totalDays += 1.0; // Count other days as full days
			}

			// Move to the next day
			$currentDate = strtotime('+1 day', $currentDate);
	}

    echo json_encode(array(
        'non_holiday_days' => $totalDays,
    ));

    exit;
}

	public function getholidaysGpoAction()
{       
    $form = $this->getRequest()->getPost();
    $startDate = strtotime($form['start_date']); // Convert string to Unix timestamp
    $endDate = strtotime($form['end_date']); // Convert string to Unix timestamp
    // Initialize an array to store the dates
    $dateArray = array();

    // Loop through each date and store them in the array, excluding Sundays
    $currentDate = $startDate;
    while ($currentDate <= $endDate) {
        // Check if the current day is not a Sunday
        if (date('N', $currentDate) < 7) {
            $dateArray[] = array(
                'date' => date('Y-m-d', $currentDate),
                'dayOfWeek' => date('N', $currentDate)
            );
        }
        $currentDate = strtotime('+1 day', $currentDate); // Move to the next day
    }

    // Get holidays
    $holidays = $this->getDefinedTable(Hr\HolidayTable::class)->getAll();

    // Count the number of days where dates are not holidays
    $nonHolidayDays = 0.0;
    foreach ($dateArray as $dateEntry) {
        $date = $dateEntry['date'];
        $dayOfWeek = $dateEntry['dayOfWeek'];

        $isHoliday = false;
        foreach ($holidays as $holiday) {
            if ($date == $holiday['holi_date']) {
                $isHoliday = true;
                break;
            }
        }
        if (!$isHoliday) {
            if ($dayOfWeek == 6) { // Saturday
                $nonHolidayDays += 0.5; // Saturday as 0.5 days
            } elseif ($dayOfWeek < 6) { // Weekdays
                $nonHolidayDays += 1.0; // Weekdays as full days
            }
        }
    }

    echo json_encode(array(
        'non_holiday_days' => $nonHolidayDays,
		'st' => $startDate,
		'et' => $endDate,
    ));

    exit;
}

}

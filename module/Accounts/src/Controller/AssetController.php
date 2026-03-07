<?php
namespace Accounts\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Laminas\Stdlib\ArrayObject;
use Laminas\Validator\File\Size;
use Laminas\Validator\File\Extension;
use Interop\Container\ContainerInterface;
use Accounts\Model As Accounts;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Hr\Model As Hr;

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
	 *  assets action
	 */
	public function assetAction()
	{
		$this->init();
		$assetTable = $this->getDefinedTable(Accounts\AssetsTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($assetTable));
		//echo '<>pre';print_r($paginator);	exit;
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
		return new ViewModel(array(
			'title'       => 'Assests',
			'paginator'   => $paginator,
			'page'        => $page,
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
		
		$lc.="<option value='-1'>All</option>";
		foreach($locations as $loc):
			$lc.= "<option value='".$loc['id']."'>".$loc['location']."</option>";
		endforeach;
		echo json_encode(array(
			'location' => $lc,
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
            $data = array(  
				'code' => $form['code'],
				'name' => $form['name'],
				'fund' => $form['fund'],
				'purchase_date' => $form['purchase_date'],
				'asset_value' => str_replace(',', '', $form['asset_value']),
				'depreciation' => $form['depreciation'],
				'salvage' => $form['salvage'],
				'rate' => (isset($form['rate']))? $form['rate']:'0',
				'method' => (isset($form['method']))? $form['method']:'0',
				'cumulative' => 0,
				'activity' => 0,
				'location' => $form['location'],
				'depreciation_date' => $this->_modified,
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
            );
            $data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
            $result = $this->getDefinedTable(Accounts\AssetsTable::class)->save($data);
              
            if($result > 0):
				$head= $form['head'];
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
				endfor;
				$this->_connection->commit(); // commit transaction on success
                $this->flashMessenger()->addMessage("success^ New asset successfully added");
            else:
				$this->_connection->rollback(); // rollback transaction over failure
                $this->flashMessenger()->addMessage("Failed^ Failed to add new asset");
            endif;
            return $this->redirect()->toRoute('asset', array('action'=>'asset'));
        }
		return new ViewModel(array(
			'title'  => 'Add assest',
			'rowset' => $this->getDefinedTable(Accounts\AssetsTable::class)->getAll(),
			'funds' => $this->getDefinedTable(Accounts\FundTable::class)->getAll(),
			'activity' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'headtypes' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
		));
		
	}
	
	/**
	 *  function/action to edit asset
	 */
     public function editassetAction()
    {
        $this->init();
		$params = explode("_", $this->_id);
		$asset_id =  $params['0'];
		//echo '<pre>';print_r($asset_id);exit;
        if($this->getRequest()->isPost())
        {
            $form=$this->getRequest()->getPost();
            $data=array(
				'id' => $this->_id,
				'code' => $form['code'],
				'name' => $form['name'],
				'fund' => $form['fund'],
				'purchase_date' => $form['purchase_date'],
				'asset_value' => str_replace(',', '', $form['asset_value']),
				'depreciation' => $form['depreciation'],
				'salvage' => $form['salvage'],
				'rate' => (isset($form['rate']))? $form['rate']:'0',
				'method' => (isset($form['method']))? $form['method']:'0',
				'cumulative' => 0,
				'activity' => 0,//$form['activity'],
				'location' => $form['location'],
				'depreciation_date' => $this->_modified,
				'author' => $this->_author,
				'modified' =>$this->_modified,
            );
            $data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
            $result = $this->getDefinedTable(Accounts\AssetsTable::class)->save($data);
            if($result > 0):
				$subhead_id= $form['subhead_id'];
				$head= $form['head'];
				$delete_rows = $this->getDefinedTable(Accounts\SubheadTable::class)->getNotIn($subhead_id, array('ref_id' => $this->_id, 'type' => '1'));
				
				for($i=0; $i < sizeof($head); $i++):
					if(isset($head[$i]) && $head[$i] > 0):
						if($subhead_id[$i] > 0){
							$subheaddata = array(
								'id'   => $subhead_id[$i],
								'head' => $head[$i],
								'type' => 1,
								'ref_id' => $result,
								'code' => $form['code'],
								'name' => $form['name'],
								'author' => $this->_author,
								'modified' =>$this->_modified,
							);
						}else{
							$subheaddata = array(
								'head' => $head[$i],
								'type' => 1,
								'ref_id' => $result,
								'code' => $form['code'],
								'name' => $form['name'],
								'author' => $this->_author,
								'created' => $this->_created,
								'modified' =>$this->_modified,
							);
						}
						$subheaddata = $this->_safedataObj->rteSafe($subheaddata);
						$this->getDefinedTable(Accounts\SubheadTable::class)->save($subheaddata);
					endif;
				endfor;	
				foreach($delete_rows as $delete_row):
				 	$this->getDefinedTable(Accounts\SubheadTable::class)->remove($delete_row['id']);
				endforeach;
				$this->_connection->commit(); // commit transaction on success
                $this->flashMessenger()->addMessage("success^ Asset successfully updated");
            else:
				$this->_connection->rollback(); // rollback transaction over failure
                $this->flashMessenger()->addMessage("Failed^ Failed to update asset");
            endif;
            return $this->redirect()->toRoute('asset', array('action'=>'asset'));
        }  
        $ViewModel = new ViewModel(array(
			'title' => 'Edit Asset',
			'assets' => $this->getDefinedTable(Accounts\AssetsTable::class)->get($asset_id),
			'subheads' => $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('ref_id' => $this->_id, 'sh.type' => '1')),
			'funds' => $this->getDefinedTable(Accounts\FundTable::class)->getAll(),
			'locationObj'=>$this->getdefinedTable(Administration\LocationTable::class),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'headtypes' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),		
	    ));
	
	$ViewModel->setTerminal(False);
	return $ViewModel;
       
    }
	/**
	 *  ASSET DISPOSE action
	 */
	public function disposalAction()
	{
		$this->init();
		$id = $this->_id;
		echo '<pre>';print_r($id);exit;
		return new ViewModel(array(
			'title'       => 'Assests',
		));
	} 
	
	/**
	 *  party action
	 */
	public function partyAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$partyrole = $form['party'];			
		}else{
			$partyrole ='-1';
		}
		//echo'<pre>';print_r($partyrole);exit;;
		$partyTable = $this->getDefinedTable(Accounts\PartyTable::class)->getforparty(array($partyrole));
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($partyTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(1000);
		$paginator->setPageRange(8);
		
		
	    $rowset = $this->getDefinedTable(Accounts\PartyTable::class)->getbypartyrole($partyrole);
		return new ViewModel(array(
			'title'        => 'Party',
			'rowset'       => $rowset,
			'partyroleID'  => $partyrole,
			'paginator'    =>$paginator,
			'page'         => $page,
			'partyroleObj' => $this->getDefinedTable(Accounts\PartyroleTable::class),
		));
	} 
	
	/**
	 *  viewparty action
	 */
	public function viewpartyAction()
	{
		$this->init();
		$party_id = $this->_id;
		//echo '<pre>';print_r($party_id);exit;
		$photo = $this->getDefinedTable(Accounts\PartyTable::class)->getColumn($party_id, 'photo');
		if($photo !=""):
			$filename = $this->_dir."/party/". $photo;
			$img = null;
			if (file_exists($filename)) {
				$handle = fopen($filename, "rb");
				$img = fread($handle, filesize($filename));
				fclose($handle);
			}
		endif;
		return new ViewModel(array(
			'title'   => 'View Party',
			'parties' => $this->getDefinedTable(Accounts\PartyTable::class)->get($party_id),
			'img' 	  => $img,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'subheads' => $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.ref_id' => $party_id, 'sh.type' => '2')),   
		));
	}
	
	/**
	 * addparty action
	 **/
	public function addpartyAction()
	{
	    $this->init();
		
		if($this->getRequest()->isPost()){
			$data = array_merge_recursive(
				$this->getRequest()->getPost()->toArray(),
				$this->getRequest()->getFiles()->toArray()
			);
			$fileName = 'avatar.jpg';
			if(!$this->flashMessenger()->hasCurrentMessages()):				
    			$size = new Size(array('max'=>2000000));
    			$ext = new Extension('jpg, png, gif');    			
    			$adapter = new \Laminas\File\Transfer\Adapter\Http();
    			$adapter->setValidators(array($size, $ext), $data['imageupload']);    			
    			foreach ($adapter->getFileInfo() as $file => $info);
				$path = pathinfo($info['name']);
				if($path['filename']):				
					$a= rand(0,10);
					$b=chr(rand(97,122));
					$c=chr(rand(97,122));
					$d= rand(0,11000);					
					$ext = strtolower($path['extension']);
					$fileName =  md5($File['name'].$a.$b.$c.$d). '.' .$ext; //file path of the main picture
					$directory = $this->_dir."/party/";
					$img = $info['tmp_name'];
					//resize image and upload				
					$imgWidth = 192;
					$imgHeight = 192;
					$im = imageCreateTrueColor($imgWidth, $imgHeight);					
					switch($ext):
					case 'jpg':
					case 'jpeg': $im_org = imagecreatefromjpeg($img);
						imageCopyResampled($im, $im_org, 0, 0, 0, 0, $imgWidth, $imgHeight, imageSX($im_org), imageSY($im_org));
						imageJpeg($im, $directory . $fileName, 100);
					break;					
					case 'png': $im_org = imagecreatefrompng($img);
						imageCopyResampled($im, $im_org, 0, 0, 0, 0, $imgWidth, $imgHeight, imageSX($im_org), imageSY($im_org));
						imagepng($im, $directory . $fileName, 100);
					break;					
					case 'gif': $im_org = imagecreatefromgif($img);
						imageCopyResampled($im, $im_org, 0, 0, 0, 0, $imgWidth, $imgHeight, imageSX($im_org), imageSY($im_org));
						imagegif($im, $directory . $fileName, 100);
					break;					
					default : 	$fileName = 'avatar.jpg';
					break;
					endswitch;
					//---------------------------------------endof image upload------------------------
					//change uploaded user photo permission
					if ( $handle = @opendir($directory) ):
						if( !@is_dir($directory . $fileName) ):
						chmod($directory . $fileName, 0777);
						endif;
					endif;					
					@closedir($handle);
				endif;
			endif;
			$data1 = array(
				'code' => $data['code'],
				'name' => $data['name'],
				'role' => $data['role'],
				'address1' => $data['address1'],
				'city' => $data['city'],
				'location' => $data['location'],
				'region' => $data['region'],
				'country' => $data['country'],
				'postal_code' => $data['postal_code'],
				'telephone' => $data['telephone'],
				'tpn' => $data['tpn'],
				'email' => $data['email'],
				'contact_person' => $data['contact_person'],
				'photo' => $fileName,
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);	
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Accounts\PartyTable::class)->save($data1);	
			if($result > 0):
				$head= $data['head'];
				$des= $data['des'];
				for($i=0; $i < sizeof($head); $i++):
					if(isset($head[$i]) && is_numeric($head[$i])):
						$subheaddata = array(
							'head' => $head[$i],
							'type' => 2,
							'ref_id' => $result,
							'code' => $data['code'],
							'name' => $data['name'],
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$subheaddata = $this->_safedataObj->rteSafe($subheaddata);
						$this->getDefinedTable(Accounts\SubheadTable::class)->save($subheaddata);
					endif;
				endfor;
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ New party successfully added");
				return $this->redirect()->toRoute('asset', array('action'=>'viewparty','id'=>$result));
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				//remove uploaded user photo 
				if ( $handle = @opendir($directory) ):
					if( !@is_dir($directory . $fileName) ):
					@unlink($directory . $fileName);
					endif;
				endif;					
				@closedir($handle);
				$this->flashMessenger()->addMessage("Failed^ Failed to add new party");
				return $this->redirect()->toRoute('asset', array('action'=>'party'));
			endif;
		}
		$ViewModel = new ViewModel(array(
			'role' => $this->getDefinedTable(Accounts\PartyroleTable::class)->getAll(),
			'headtypes' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
			'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
		));
		$ViewModel->setTerminal(False);
		return $ViewModel;			
	}
	
	/*
	 * party editAction
	 * */
	public function editpartyAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();			
			$data = array(
				'id' => $this->_id,
				'code' => $form['code'],
				'name' => $form['name'],
				'role' => $form['role'],
				'address1' => $form['address1'],
				'city' => $form['city'],
				'location' => $form['location'],
				'region' => $form['region'],
				'country' => $form['country'],
				'postal_code' => $form['postal_code'],
				'telephone' => $form['telephone'],
				'tpn' => $form['tpn'],
				'email' => $form['email'],
				'contact_person' => $form['contact_person'],
				'author' => $this->_author,
				'modified' => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Accounts\PartyTable::class)->save($data);
			if($result > 0){
				$subhead_id= $form['subhead_id'];
				$head= $form['head'];
				$delete_rows = $this->getDefinedTable(Accounts\SubheadTable::class)->getNotIn($subhead_id, array('ref_id' => $this->_id, 'type' => '2'));
				
				for($i=0; $i < sizeof($head); $i++):
					if(isset($head[$i]) && $head[$i] > 0):
						if($subhead_id[$i] > 0){
							$subheaddata = array(
								'id'   => $subhead_id[$i],
								'head' => $head[$i],
								'type' => 2,
								'ref_id' => $result,
								'code' => $form['code'],
								'name' => $form['name'],
								'author' => $this->_author,
								'modified' =>$this->_modified,
							);
						}else{
							$subheaddata = array(
								'head' => $head[$i],
								'type' => 2,
								'ref_id' => $result,
								'code' => $form['code'],
								'name' => $form['name'],
								'author' => $this->_author,
								'created' => $this->_created,
								'modified' =>$this->_modified,
							);
						}
						$subheaddata = $this->_safedataObj->rteSafe($subheaddata);
						$this->getDefinedTable(Accounts\SubheadTable::class)->save($subheaddata);
					endif;
				endfor;	
				foreach($delete_rows as $delete_row):
				 	$this->getDefinedTable(Accounts\SubheadTable::class)->remove($delete_row['id']);
				endforeach;
				$this->_connection->commit(); // commit transaction on success
				$this->flashmessenger()->addMessage('success^ Successfully updated party '.$form['name']);
			}
			else {
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashmessenger()->addMessage('error^ Failed to update party');
			}
			return $this->redirect()->toRoute('asset', array('action'=>'viewparty','id'=>$this->_id));
		}
			
		$ViewModel = new ViewModel(array(
			'title' => 'Edit Party',
			'id' => $this->_id,
			'party' => $this->getDefinedTable(Accounts\PartyTable::class)->get($this->_id),
			'subheads' => $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('ref_id' => $this->_id, 'sh.type' => '2')),
			'roles' => $this->getDefinedTable(Accounts\PartyroleTable::class)->getAll(),
			'headtypes' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
			'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
		));
		return $ViewModel;
	}
	
	/**
	 *  changephoto Action --to Change Party Profile photo 
	 **/
	public function changephotoAction()
	{
	    $this->init();
	    if($this->_login_role != $this->_highest_role ):
    	    $this->flashMessenger()->addMessage("notice^ You are not allowed to change this photo");
    	    return $this->redirect()->toRoute('asset', array('action' => 'viewparty', 'id' => $this->_id));
	    endif;	    
	    if (!isset($this->_id)):
		    return $this->redirect()->toRoute('asset', array('action'=>'party'));
		endif;
		$request=$this->getRequest();
		if ($request->isPost()):
			$data = array_merge_recursive(
				$request->getPost()->toArray(),
				$request->getFiles()->toArray()
			);  
		  
			if(!$this->flashMessenger()->hasCurrentMessages()):
				$size = new Size(array('max'=>2000000));
				$ext = new Extension('jpg, png, gif');
				
				$adapter = new \Laminas\File\Transfer\Adapter\Http();
				$adapter->setValidators(array($size, $ext), $data['fileupload']);
				
				foreach ($adapter->getFileInfo() as $file => $info):
					$path = pathinfo($info['name']);
					if($path['filename']):
					
						$a= rand(0,10);
						$b=chr(rand(97,122));
						$c=chr(rand(97,122));
						$d= rand(0,11000);
						
						$ext = strtolower($path['extension']);
						$fileName =  md5($File['name'].$a.$b.$c.$d). '.' .$ext; //file path of the main picture
						
						$directory = $this->_dir."/party/";
						//for thumb image
						$img = $info['tmp_name'];
						
						//----------------------------------- ACTUAL IMAGE-----------------------------
						$imgWidth = 180;
						$imgHeight = 200;
						$im = imageCreateTrueColor($imgWidth, $imgHeight);
						
						switch($ext):
						case 'jpg':
						case 'jpeg': $im_org = imagecreatefromjpeg($img);
							imageCopyResampled($im, $im_org, 0, 0, 0, 0, $imgWidth, $imgHeight, imageSX($im_org), imageSY($im_org));
							imageJpeg($im, $directory . $fileName, 100);
						break;
						
						case 'png': $im_org = imagecreatefrompng($img);
							imageCopyResampled($im, $im_org, 0, 0, 0, 0, $imgWidth, $imgHeight, imageSX($im_org), imageSY($im_org));
							imagepng($im, $directory . $fileName, 100);
						break;
						
						case 'gif': $im_org = imagecreatefromgif($img);
							imageCopyResampled($im, $im_org, 0, 0, 0, 0, $imgWidth, $imgHeight, imageSX($im_org), imageSY($im_org));
							imagegif($im, $directory . $fileName, 100);
						break;
						
						default : 	$fileName = 'avatar.jpg';
						break;
						endswitch;
						//---------------------------------------END OF ACTUAL IMAGE------------------------
						//change uploaded user photo permission
						if ( $handle = @opendir($directory) ):
							if( !@is_dir($directory . $fileName) ):
							chmod($directory . $fileName, 0777);
							endif;
							if( !@is_dir($directory."/thumb/". $fileName) ):
							chmod($directory."/thumb/". $fileName, 0777);
							endif;
						endif;
						
						@closedir($handle);
					endif;
				endforeach;
				$prev_photo = $this->getDefinedTable(Accounts\PartyTable::class)->getColumn($this->_id, $column="photo");
				$data = array(
					'id'  		 => $this->_id, 
					'photo'      => $fileName,
					'created'    => $this->_created,
					'modified'   => $this->_modified
				);
				if($adapter->isValid()):
					$data = $this->_safedataObj->rteSafe($data);
					$result = $this->getDefinedTable(Accounts\PartyTable::class)->save($data);
					
					if($result > 0):
						$this->flashMessenger()->addMessage("success^ User photo successfully changed");
						//change uploade user photo permission
						if ( $handle = @opendir($directory) ):
							if( !@is_dir($directory . $prev_photo) ):
							   @unlink($directory . $prev_photo);
							endif;
						endif;
						@closedir($handle);		 	             
						return $this->redirect()->toRoute('asset', array('action' => 'viewparty', 'id'=>$result));
					else:
						// when user couldnot be added into database
						$this->flashMessenger()->addMessage("error^ Someting went wrong and couldnot change photo");
						
						//deleted uploaded photo
						if ( $handle = @opendir($directory) ):
							if( !@is_dir($directory . $fileName) ):
							   @unlink($directory . $fileName);
							endif;
						endif;
						@closedir($handle);	 	             
					endif;
				else:
					// when user photo couldnot be added/uploaded
					foreach($adapter->getMessages() as $sms):
						$fmessage ='error^'.$sms;
					endforeach;
					$this->flashMessenger()->addMessage($fmessage); 
				endif;
			endif;
			return $this->redirect()->toRoute('asset', array('action'=>'viewparty', 'id'=>$this->_id));
		else:
			$photo = $this->getDefinedTable(Accounts\PartyTable::class)->getColumn($this->_id,'photo'); 
								 
			if($photo !=""):  
				$filename = $this->_dir."/user/". $photo; 
				$userimg = null;
				if (file_exists($filename)) {
					$handle = fopen($filename, "rb");
					$userimg = fread($handle, filesize($filename));
					fclose($handle);
				}
			endif; 		 
		endif;
		
		$viewModel = new ViewModel(array(
			'title'    	   => 'Change Photo',
			'id'           => $this->_id,
			'userimg'  	   => $userimg,
			'login_role'   => $this->_login_role,
			'highest_role' => $this->_highest_role
		)); 
		$viewModel->setTerminal(true);
		return $viewModel;
	}
	
	/**
	 *  bankaccount action
	 */
	public function bankaccountAction()
	{
		$this->init();
		$bankaccountTable = $this->getDefinedTable(Accounts\BankaccountTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($bankaccountTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10000);
		$paginator->setPageRange(8);
		return new ViewModel(array(
			'title'            => 'Bank Account',
			'paginator'        => $this->getDefinedTable(Accounts\BankaccountTable::class)->getAll(),
			'page'             => $page,
			'bankObj'          => $this->getDefinedTable(Administration\BankTable::class),
		));
	} 
	
	/**
	 * addbankaccount Action
	 **/
	public function addbankaccountAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'account'  => $form['account'],
				'code'     => $form['code'],
				'bank'     => $form['bank'],
				'branch'   => $form['branch'],
				'location' => $form['location'],
				'author'   => $this->_author,
				'created'  => $this->_created,
				'modified' => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Accounts\BankaccountTable::class)->save($data);
			if($result > 0):
				$head= $form['head'];
				$des= $form['des'];
				for($i=0; $i < sizeof($head); $i++):
					if(isset($head[$i]) && is_numeric($head[$i])):
						$subheaddata = array(
							'head' => $head[$i],
							'type' => 3,
							'ref_id' => $result,
							'code' => $form['code'],
							'name' => $form['account'],
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$subheaddata = $this->_safedataObj->rteSafe($subheaddata);
						$this->getDefinedTable(Accounts\SubheadTable::class)->save($subheaddata);
					endif;
				endfor;
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ New bank account successfully added");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("Failed^ Failed to add new bank account");
			endif;
			return $this->redirect()->toRoute('asset', array('action'=>'bankaccount'));
		}
		return $ViewModel = new ViewModel(array(
			'title'     => 'Add Bank Account',
			'regions'   => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'banks'     => $this->getDefinedTable(Administration\BankTable::class)->get(array('status'=>'1')),
			'headtypes' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
		));	
	}
	
	/**
	 * bankaccount editAction
	 **/
	public function editbankaccountAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'id' => $this->_id,
				'account' => $form['account'],
				'code' => $form['code'],
				'bank'     => $form['bank'],
				'branch' => $form['branch'],
				'location' => $form['location'],
				'author' => $this->_author,
				'modified' => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Accounts\BankaccountTable::class)->save($data);
			if($result > 0){
				$subhead_id= $form['subhead_id'];
				$head= $form['head'];
				$delete_rows = $this->getDefinedTable(Accounts\SubheadTable::class)->getNotIn($subhead_id, array('ref_id' => $this->_id, 'type' => '3'));
				
				for($i=0; $i < sizeof($head); $i++):
					if(isset($head[$i]) && $head[$i] > 0):
						if($subhead_id[$i] > 0){
							$subheaddata = array(
								'id'   => $subhead_id[$i],
								'head' => $head[$i],
								'type' => 3,
								'ref_id' => $result,
								'code' => $form['code'],
								'name' => $form['account'],
								'author' => $this->_author,
								'modified' =>$this->_modified,
							);
						}else{
							$subheaddata = array(
								'head' => $head[$i],
								'type' => 3,
								'ref_id' => $result,
								'code' => $form['code'],
								'name' => $form['account'],
								'author' => $this->_author,
								'created' => $this->_created,
								'modified' =>$this->_modified,
							);
						}
						$subheaddata = $this->_safedataObj->rteSafe($subheaddata);
						$this->getDefinedTable(Accounts\SubheadTable::class)->save($subheaddata);
					endif;
				endfor;	
				foreach($delete_rows as $delete_row):
				 	$this->getDefinedTable(Accounts\SubheadTable::class)->remove($delete_row['id']);
				endforeach;
				$this->_connection->commit(); // commit transaction on success
				$this->flashmessenger()->addMessage('success^ Bank Account Successfully Updated');
				return $this->redirect()->toRoute('asset', array('action'=>'bankaccount'));			}
			else {
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashmessenger()->addMessage('error^ Failed to update bank account.');
				return $this->redirect()->toRoute('asset', array('action' => 'bankaccount'));
			}
		}			
		$ViewModel = new ViewModel(array(
			'title' => 'Edit Bank Account',
			'id' => $this->_id,
			'bankaccount' => $this->getDefinedTable(Accounts\BankaccountTable::class)->get($this->_id),
			'subheads' => $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('ref_id' => $this->_id, 'sh.type' => '3')),
			'locationObj'=>$this->getdefinedTable(Administration\LocationTable::class),
			'banks'     => $this->getDefinedTable(Administration\BankTable::class)->get(array('status'=>'1')),
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
			'headtypes' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
		));
		return $ViewModel;
	}	
	
	/**
	 *  fund action
	 */
	public function fundAction()
	{
		$this->init();
		$fundTable = $this->getDefinedTable(Accounts\FundTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($fundTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
		return new ViewModel(array(
			'title'         => 'Fund',
			'paginator'     => $paginator,
			'page'          => $page,
		));
	} 
	
	/**
	 *  function/action to add Fund
	 */
	public function addfundAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'code' => $form['code'],
				'description' =>$form['description'],
				'fund_date' => $form['fund_date'],
				'author' => $this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Accounts\FundTable::class)->save($data);
	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Fund successfully added");
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to add new Fund");
			endif;
			return $this->redirect()->toRoute('asset', array('action'=>'fund'));
		}
	$ViewModel = new ViewModel(array(
		'title'  => 'Add Fund',
	));
	$ViewModel->setTerminal(True);
	return $ViewModel;
	}
	
	/**
	 * edit fund action
	**/
	public function editfundAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$data=array(
				'id' => $this->_id,
				'code' => $form['code'],
				'description' => $form['description'],
				'fund_date' => $form['fund_date'],
				'author' => $this->_author,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Accounts\FundTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Fund successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update Fund");
			endif;
			return $this->redirect()->toRoute('asset', array('action'=>'fund'));
		}
	
		$viewModel =  new ViewModel(array(
			'funds' => $this->getDefinedTable(Accounts\FundTable::class)->get($this->_id),
		));
		$viewModel->setTerminal(True);
		return $viewModel;
		
	}
        
	/**
	 *  hr action
	 */
	public function hrAction()
	{
		$this->init();
		$hrTable = $this->getDefinedTable(Hr\EmployeeTable::class)->getEmployee(array('e.status' =>array(1,2)));
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($hrTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
		return new ViewModel(array(
			'title'         => 'Human Resource',
			'paginator'     => $paginator,
			'page'          => $page,
		));
	}   
	
	/**
	 * addhr action
	**/
	public function addhrAction()
	{
	    $this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			    $employees = $this->getDefinedTable(Hr\EmployeeTable::class)->get(array('e.id' =>$form['code']));
				foreach($employees as $employee);
				$employee_id = $employee['id'];
				$code = $employee['full_name'].' ( '.$employee['designation'].'/'.$employee['cid'].' ) '; 
				$head= $form['head'];
				for($i=0; $i < sizeof($head); $i++):
					if(isset($head[$i]) && is_numeric($head[$i])):
						$subheaddata = array(
							'head' => $head[$i],
							'type' => $form['role'],
							'ref_id' =>$employee_id,
							'code' => $code,
							'name' => $code,
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						//$this->_connection->beginTransaction(); //***Transaction begins here***//
						$subheaddata = $this->_safedataObj->rteSafe($subheaddata);
						$this->getDefinedTable(Accounts\SubheadTable::class)->save($subheaddata);
					endif;
				endfor;
				//$this->_connection->commit(); // commit transaction on success
				$this->flashmessenger()->addMessage('success^ Successfully Mapped' .$employee['full_name'].' and '.$employee['cid']);
			return $this->redirect()->toRoute('asset', array('action'=>'hr'));
		}
		$ViewModel = new ViewModel(array(
			'headtypes' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
			'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'employees' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAll(),
		));
		return $ViewModel;			
	}
		
	/**
	 * hr edit Action
	 **/
	public function edithrAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$employees = $this->getDefinedTable(Hr\EmployeeTable::class)->get(array('e.id' =>$form['code']));
			foreach($employees as $employee);
			$employee_id = $employee['id'];
			$code = $employee['full_name'].' ( '.$employee['designation'].'/'.$employee['cid'].' ) '; 
			//$this->_connection->beginTransaction(); //***Transaction begins here***//
			$subhead_id = $form['subhead_id'];
			$head= $form['head'];
			$delete_rows = $this->getDefinedTable(Accounts\SubheadTable::class)->getNotIn($subhead_id, array('ref_id' => $this->_id, 'type' => '8'));
			for($i=0; $i < sizeof($head); $i++):
				if(isset($head[$i]) && $head[$i] > 0):
					if($subhead_id[$i] > 0){
						$subheaddata = array(
							'id'   => $subhead_id[$i],
							'head' => $head[$i],
							'type' => 8,
							'ref_id' =>$employee_id,
							'code' => $code,
							'name' => $code,
							'author' => $this->_author,
							'modified' =>$this->_modified,
						);
					}else{
						$subheaddata = array(
							'head' => $head[$i],
							'type' => 8,
							'ref_id' =>$employee_id,
							'code' => $code,
							'name' => $code,
							'author' => $this->_author,
							'created' => $this->_created,
							'modified' =>$this->_modified,
						);
					}
					$subheaddata = $this->_safedataObj->rteSafe($subheaddata);
					$this->getDefinedTable(Accounts\SubheadTable::class)->save($subheaddata);
				endif;
			endfor;	
			foreach($delete_rows as $delete_row):
				$this->getDefinedTable(Accounts\SubheadTable::class)->remove($delete_row['id']);
			endforeach;
			//$this->_connection->commit(); // commit transaction on success
			$this->flashmessenger()->addMessage('success^ Successfully Updated Mapping' .$employee['full_name'].' and '.$employee['cid']);
			return $this->redirect()->toRoute('asset', array('action'=>'hr'));			
		}			
		$ViewModel = new ViewModel(array(
			'title' => 'Edit HR',
			'id' => $this->_id,
			'employees' => $this->getDefinedTable(Hr\EmployeeTable::class)->getEmployee($this->_id),
			'subheads' => $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.ref_id' => $this->_id, 'sh.type' => '8')),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
			'headtypes' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
		));
		return $ViewModel;
	}	
	/**
	 *  cash account action
	 */
	public function cashaccountAction()
	{
		$this->init();
		$cashTable = $this->getDefinedTable(Accounts\CashaccountTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($cashTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10000);
		$paginator->setPageRange(8);
		return new ViewModel(array(
			'title'         => 'Cash Account',
			'paginator'     => $paginator,
			'page'          => $page,
			'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
		));
	} 
	/**
	 * addcashaccount Action
	 **/
	public function addcashaccountAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'location' => $form['location'],
				'cash_account_code' => $form['cash_account_code'],
				'cash_account_name' => $form['cash_account_name'],
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Accounts\CashaccountTable::class)->save($data);
			if($result > 0):
				$head= $form['head'];
				$des= $form['des'];
				for($i=0; $i < sizeof($head); $i++):
					if(isset($head[$i]) && is_numeric($head[$i])):
						$subheaddata = array(
							'head' => $head[$i],
							'type' => 6,
							'ref_id' => $result,
							'code' => $form['cash_account_code'],
							'name' => $form['cash_account_name'],
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$subheaddata = $this->_safedataObj->rteSafe($subheaddata);
						$this->getDefinedTable(Accounts\SubheadTable::class)->save($subheaddata);
					endif;
				endfor;
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ New cash account successfully added");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to add new cash account");
			endif;
			return $this->redirect()->toRoute('asset', array('action'=>'cashaccount'));
		}
		return $ViewModel = new ViewModel(array(
			'title' => 'Add Cash Account',
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'headtypes' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
		));	
	}
	/**
	 * cashaccount editAction
	 **/
	public function editcashaccountAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'id' => $this->_id,
				'region' => $form['region'],
				'location' => $form['location'],
				'cash_account_code' => $form['cash_account_code'],
				'cash_account_name' => $form['cash_account_name'],
				'author' => $this->_author,
				'modified' => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Accounts\CashaccountTable::class)->save($data);
			if($result > 0){
				$subhead_id= $form['subhead_id'];
				$head= $form['head'];
				$delete_rows = $this->getDefinedTable(Accounts\SubheadTable::class)->getNotIn($subhead_id, array('ref_id' => $this->_id, 'type' => '6'));
				
				for($i=0; $i < sizeof($head); $i++):
					if(isset($head[$i]) && $head[$i] > 0):
						if($subhead_id[$i] > 0){
							$subheaddata = array(
								'id'   => $subhead_id[$i],
								'head' => $head[$i],
								'type' => 6,
								'ref_id' => $result,
								'code' => $form['cash_account_code'],
								'name' => $form['cash_account_name'],
								'author' => $this->_author,
								'modified' =>$this->_modified,
							);
						}else{
							$subheaddata = array(
								'head' => $head[$i],
								'type' => 6,
								'ref_id' => $result,
								'code' => $form['cash_account_code'],
								'name' => $form['cash_account_name'],
								'author' => $this->_author,
								'created' => $this->_created,
								'modified' =>$this->_modified,
							);
						}
						$subheaddata = $this->_safedataObj->rteSafe($subheaddata);
						$this->getDefinedTable(Accounts\SubheadTable::class)->save($subheaddata);
					endif;
				endfor;	
				foreach($delete_rows as $delete_row):
				 	$this->getDefinedTable(Accounts\SubheadTable::class)->remove($delete_row['id']);
				endforeach;
				$this->_connection->commit(); // commit transaction on success
				$this->flashmessenger()->addMessage('success^ Cash Account Successfully Updated');
				return $this->redirect()->toRoute('asset', array('action'=>'cashaccount'));			}
			else {
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashmessenger()->addMessage('error^ Failed to update cash account.');
				return $this->redirect()->toRoute('asset', array('action' => 'cashaccount'));
			}
		}			
		$ViewModel = new ViewModel(array(
			'title' => 'Edit Cash Account',
			'id' => $this->_id,
			'cashaccounts' => $this->getDefinedTable(Accounts\CashaccountTable::class)->get($this->_id),
			'subheads' => $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('ref_id' => $this->_id, 'sh.type' => '6')),
			'locationObj'=>$this->getdefinedTable(Administration\LocationTable::class),
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
			'headtypes' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
		));
		return $ViewModel;
	}	
	/**
	 *  cash account action
	 */
	public function incomeheadAction()
	{
		$this->init();
		$incomeheadTable = $this->getDefinedTable(Accounts\IncomeheadTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($incomeheadTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10000);
		$paginator->setPageRange(8);
		return new ViewModel(array(
			'title'         => 'Income Head',
			'paginator'     => $paginator,
			'page'          => $page,
			'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
		));
	}
	/**
	 * addincomehead Action
	 **/
	public function addincomeheadAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			if(!empty($form['countersale'])):
			    $counter=$form['countersale'];
			else:
			    $counter=0;
			endif;
			$data = array(
				'location' =>$form['location'],
				'income_head' => $form['income_head'],
				'counter_sale' => $counter,
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Accounts\IncomeheadTable::class)->save($data);
			if($result > 0):
				$head= $form['head'];
				$des= $form['des'];
				for($i=0; $i < sizeof($head); $i++):
					if(isset($head[$i]) && is_numeric($head[$i])):
						$subheaddata = array(
							'head' => $head[$i],
							'type' => 7,
							'ref_id' => $result,
							'code' => $form['income_head'],
							'name' => $form['income_head'],
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$subheaddata = $this->_safedataObj->rteSafe($subheaddata);
						$this->getDefinedTable(Accounts\SubheadTable::class)->save($subheaddata);
					endif;
				endfor;
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ New income head successfully added");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to add new income head");
			endif;
			return $this->redirect()->toRoute('asset', array('action'=>'incomehead'));
		}
		return $ViewModel = new ViewModel(array(
			'title' => 'Add Income Head',
			'headtypes' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
			'locations'=> $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
		));	
	}
	/**
	 * editincomehead Action
	 **/
	public function editincomeheadAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'id'	=> $this->_id,
				'location' => $form['location'],
				'counter_sale' => $form['countersale'],
				'income_head' => $form['income_head'],
				'author' =>$this->_author,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Accounts\IncomeheadTable::class)->save($data);
			if($result > 0):
				$subhead_id= $form['subhead_id'];
				$head= $form['head'];
				$delete_rows = $this->getDefinedTable(Accounts\SubheadTable::class)->getNotIn($subhead_id, array('ref_id' => $this->_id, 'type' => '7'));
				
				for($i=0; $i < sizeof($head); $i++):
					if(isset($head[$i]) && $head[$i] > 0):
						if($subhead_id[$i] > 0){
							$subheaddata = array(
								'id'   => $subhead_id[$i],
								'head' => $head[$i],
								'type' => 7,
								'ref_id' => $result,
								'code' => $form['income_head'],
								'name' => $form['income_head'],
								'author' => $this->_author,
								'modified' =>$this->_modified,
							);
						}else{
							$subheaddata = array(
								'head' => $head[$i],
								'type' => 7,
								'ref_id' => $result,
								'code' => $form['income_head'],
								'name' => $form['income_head'],
								'author' => $this->_author,
								'created' => $this->_created,
								'modified' =>$this->_modified,
							);
						}
						$subheaddata = $this->_safedataObj->rteSafe($subheaddata);
						$this->getDefinedTable(Accounts\SubheadTable::class)->save($subheaddata);
					endif;
				endfor;	
				foreach($delete_rows as $delete_row):
				 	$this->getDefinedTable(Accounts\SubheadTable::class)->remove($delete_row['id']);
				endforeach;
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Income head successfully Updated");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to Update income head");
			endif;
			return $this->redirect()->toRoute('asset', array('action'=>'incomehead'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Edit Income Head',
			'id' => $this->_id,
			'incomeheads' => $this->getDefinedTable(Accounts\IncomeheadTable::class)->get($this->_id),
			'subheads' => $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('ref_id' => $this->_id, 'sh.type' => '7')),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
			'headtypes' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
			'activities'=> $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
		));
		return $ViewModel;	
	}
	/** -----------------------------------------------ADD EXPENSE LEDGER---------------------------------- */
	/**
	 * Expense Ledger
	 */
	public function expenseledgerAction()
	{
		$this->init();
		$expenseledgerTable = $this->getDefinedTable(Accounts\ExpenseledgerTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($expenseledgerTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10000);
		$paginator->setPageRange(8);
		return new ViewModel(array(
			'title'         => 'Expense Ledger',
			'paginator'     => $paginator,
			'page'          => $page,
			'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
		));
	}
	/**
	 * Add expense Ledger Action
	 **/
	public function addexpenseledgerAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'location' =>$form['location'],
				'expense_code' => $form['expense_code'],
				'expense_name' => $form['expense_name'],
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Accounts\ExpenseledgerTable::class)->save($data);
			if($result > 0):
				$head= $form['head'];
				$des= $form['des'];
				for($i=0; $i < sizeof($head); $i++):
					if(isset($head[$i]) && is_numeric($head[$i])):
						$subheaddata = array(
							'head' => $head[$i],
							'type' => 10,
							'ref_id' => $result,
							'code' => $form['expense_code'],
							'name' => $form['expense_name'],
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$subheaddata = $this->_safedataObj->rteSafe($subheaddata);
						$this->getDefinedTable(Accounts\SubheadTable::class)->save($subheaddata);
					endif;
				endfor;
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ New Expense Ledger successfully added");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to add new Expense Ledger");
			endif;
			return $this->redirect()->toRoute('asset', array('action'=>'expenseledger'));
		}
		return $ViewModel = new ViewModel(array(
			'title' => 'Add Expense Ledger',
			'headtypes' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
			'locations'=> $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
		));	
	}
	/**
	 * Edit Expense Ledger Action
	 **/
	public function editexpenseledgerAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'id'	=> $this->_id,
				'location' => $form['location'],
				'expense_code' => $form['expense_code'],
				'expense_name' => $form['expense_name'],
				'author' =>$this->_author,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Accounts\ExpenseledgerTable::class)->save($data);
			if($result > 0):
				$subhead_id= $form['subhead_id'];
				$head= $form['head'];
				$delete_rows = $this->getDefinedTable(Accounts\SubheadTable::class)->getNotIn($subhead_id, array('ref_id' => $this->_id, 'type' => '7'));
				
				for($i=0; $i < sizeof($head); $i++):
					if(isset($head[$i]) && $head[$i] > 0):
						if($subhead_id[$i] > 0){
							$subheaddata = array(
								'id'   => $subhead_id[$i],
								'head' => $head[$i],
								'type' => 7,
								'ref_id' => $result,
								'code' => $form['expense_code'],
								'name' => $form['expense_name'],
								'author' => $this->_author,
								'modified' =>$this->_modified,
							);
						}else{
							$subheaddata = array(
								'head' => $head[$i],
								'type' => 7,
								'ref_id' => $result,
								'code' => $form['expense_code'],
								'name' => $form['expense_name'],
								'author' => $this->_author,
								'created' => $this->_created,
								'modified' =>$this->_modified,
							);
						}
						$subheaddata = $this->_safedataObj->rteSafe($subheaddata);
						$this->getDefinedTable(Accounts\SubheadTable::class)->save($subheaddata);
					endif;
				endfor;	
				foreach($delete_rows as $delete_row):
				 	$this->getDefinedTable(Accounts\SubheadTable::class)->remove($delete_row['id']);
				endforeach;
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Expense Ledher successfully Updated");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to Update Expense Ledger");
			endif;
			return $this->redirect()->toRoute('asset', array('action'=>'expenseledger'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Edit Expense Ledger',
			'id' => $this->_id,
			'expenseledgers' => $this->getDefinedTable(Accounts\ExpenseledgerTable::class)->get($this->_id),
			'subheads' => $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('ref_id' => $this->_id, 'sh.type' => '7')),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
			'headtypes' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
			'activities'=> $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
		));
		return $ViewModel;	
	}
}

<?php
namespace Auth\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Interop\Container\ContainerInterface;
use Laminas\Authentication\AuthenticationService;
use Laminas\Db\Adapter\Adapter as DbAdapter;
use Laminas\Authentication\Adapter\DbTable\CredentialTreatmentAdapter as AuthAdapter;
use Laminas\Session\Container;
use Laminas\Authentication\Result;
use Laminas\Mvc\MvcEvent;
use Auth\Form\AuthForm;
use Administration\Model as Administration;

class AuthController extends AbstractActionController
{
	private $container;
    private $dbAdapter;
    protected $_password;// password plugin

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->dbAdapter = $this->container->get(DbAdapter::class);
    }

    public function getDefinedTable($table)
    {
        $definedTable = $this->container->get($table);
        return $definedTable;
    }

    public function indexAction()
    {   
        $auth = new AuthenticationService();
		if($auth->hasIdentity()):
			return $this->redirect()->toRoute('home');
		else:
			return $this->redirect()->toRoute('auth', array('action' =>'login'));
		endif;
		
        return new ViewModel([
        	'title' => 'Login'
        ]);
    }
    /** 
     * Authentication - Login 
     */
    public function loginAction()
    {
		$messages = null;
		$auth = new AuthenticationService();
        if($auth->hasIdentity() && $this->params()->fromRoute('id') != "NoKeepAlive"):
			 return $this->redirect()->toRoute('home');
        endif;
        if ($this->getRequest()->isPost()) 
		{
			$data = $this->getRequest()->getPost();
            $staticSalt = $this->password()->getStaticSalt();// Get Static Salt using Password Plugin
            if(filter_var($data['username'], FILTER_VALIDATE_EMAIL)):
                $identitycolumn = "email";
            else:
                $identitycolumn = "mobile";
            endif;
        
            $authAdapter = new AuthAdapter($this->dbAdapter,
                                           'sys_users', // there is a method setTableName to do the same
                                           $identitycolumn, // there is a method setIdentityColumn to do the same
                                           'password', // there is a method setCredentialColumn to do the same
                                           "SHA1(CONCAT('$staticSalt', ?, salt))" // setCredentialTreatment(parametrized string) 'MD5(?)'
                                          );            
            $authAdapter
                    ->setIdentity($data['username'])
                    ->setCredential($data['password'])
                ;
            $authService = new AuthenticationService();
            $result = $authService->authenticate($authAdapter);
            //echo"<pre>"; print_r($result); exit;
            switch ($result->getCode()) 
			{
                case Result::FAILURE_IDENTITY_NOT_FOUND:
                    // nonexistent identity
                    $this->flashMessenger()->addMessage("error^ A record with the supplied identity (username) could not be found.");
                    break;

                case Result::FAILURE_CREDENTIAL_INVALID:
                    // invalid credential
                    $this->flashMessenger()->addMessage("info^ Please check Caps Lock key is activated on your computer.");
                    $this->flashMessenger()->addMessage("error^ Supplied credential (password) is invalid, Please try again.");
                    break;

                case Result::SUCCESS:
                    $storage = $authService->getStorage();
                    $storage->write($authAdapter->getResultRowObject());
                    $role = $this->identity()->role;
                    $time = 1209600; // 14 days 1209600/3600 = 336 hours => 336/24 = 14 days
                    if ($data['rememberme']) {
                        $sessionManager = new \Laminas\Session\SessionManager();
                        $sessionManager->rememberMe($time);
                    }
                    $id = $this->identity()->id; 
                    $login = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($id, $column='logins');
                    
                    $data = array(
                            'id'         => $id,
                            'last_login' => date('Y-m-d H:i:s'),
                            'last_accessed_ip' => !empty($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : ( !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'] ),
                            'logins' => $login + 1
                    ); 
                    $this->getDefinedTable(Administration\UsersTable::class)->save($data);
					//check whether user is block
					$status = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($id, $column='status');
					if($status == "0"){
					   return $this->redirect()->toRoute('auth', array('action' => 'logout','id'=>'blocked'));	
					}
                    $this->flashMessenger()->addMessage("info^ Welcome,</br>You have successfully logged in!");
                    return $this->redirect()->toRoute('home');
                break;

                default:
                    //other failure--- currently silent
                break;  
            }
            return $this->redirect()->toRoute('auth', array('action' => 'login'));
            
			if ( $this->params()->fromRoute('id') == "NoKeepAlive" ):
				$auth = new AuthenticationService();
				$auth->clearIdentity();
				$sessionManager = new \Laminas\Session\SessionManager();
				$sessionManager->forgetMe();
				$this->flashMessenger()->addMessage('warning^Your session has expired, please login again.');
			endif;
        }
        $ViewModel = new ViewModel(array(
			'title' => 'Log into System',
		));
		$ViewModel->setTerminal(false);
		return $ViewModel;
    }
    /**
     * Logout
     */
    public function logoutAction()
	{
        if(!$this->identity()){
	    	  $this->flashMessenger()->addMessage("warning^ Your session has already expired. Login in to proceed.");
	    	  return $this->redirect()->toRoute('auth', array('action' => 'login'));
	    }
		$auth = new AuthenticationService();
		//$msg = $this->params()->fromRoute('id');
		$id = $this->identity()->id;   
		$data = array(
		    'id'          => $id,
			'last_logout' => date('Y-m-d H:i:s')	    
		); 
		
		$this->getDefinedTable(Administration\UsersTable::class)->save($data);

		if ($auth->hasIdentity()) {
			$identity = $auth->getIdentity();
		}			
		
		$auth->clearIdentity();
		$sessionManager = new \Laminas\Session\SessionManager();
		$sessionManager->forgetMe();
		
		if ( $this->params()->fromRoute('id') == "blocked" ):
		    $this->flashMessenger()->addMessage('warning^You cannot use the system as you are blocked. Contact the administrator.');
		else:
			$this->flashMessenger()->addMessage('info^You have successfully logged out!');
		endif;
		
		return $this->redirect()->toRoute('auth', array('action'=>'login'));
	}
    /**
	 * forgotpwd
	 */
    public function forgotpwdAction()
    {
        $captcha = new AuthForm();

        if ($this->getRequest()->isPost()) {
            $form = $this->getRequest()->getPost();
            $captcha->setData($form);
            if ($captcha->isValid()) {
                $userDtls = $this->getDefinedTable(Administration\UsersTable::class)->get(array('email' => $form['email']));
                if(sizeof($userDtls) == 0){
                    $this->flashMessenger()->addMessage('error^ This email is not registered with any of the users in the system.');
                    return $this->redirect()->toRoute('auth', array('action' => 'forgotpwd'));
                }else{
                    foreach ($userDtls as $row);
					$email = $row['email']; $name = $row['name'];
                    
					$expiry_time = date("Y-m-d H:i:s", strtotime('+12 hours'));
					$recovery_stamp = rtrim(strtr(base64_encode($row['email']."***".$expiry_time), '+/', '-_'), '=');
					
                    $recovery_link = "<div style='font-family: Arial, sans-serif; line-height: 19px; color: #444444; font-size: 13px; text-align: center;'>
						<a href='https://erp.bhutanpost.bt/public/auth/amendpwd/".$recovery_stamp."' style='color: #ffffff; text-decoration: none; margin: 0px; text-align: center; vertical-align: baseline; border: 4px solid #1e7e34; padding: 4px 9px; font-size: 15px; line-height: 21px; background-color: #218838;'>&nbsp; Reset Password &nbsp;</a>
					</div>";
					
                    $notify_msg = "You have requested for password recovery. Please click on password recovery link below to reset your password: <br><br>".$recovery_link.
									"<br>This link will expire in 12 hours and can be used only once.<br><br>If you do not want to change your password and did not request this, please ignore and delete this message.";
                    $mail = array(
                        'email'    => $row['email'],
                        'name'     => $row['name'],
                        'subject'  => 'BhutanPost-ERP: Password Recovery', 
                        'message'  => $notify_msg,
                        'cc_array' => [],
                    );
                    $this->EmailPlugin()->sendmail($mail);
					$this->flashMessenger()->addMessage("success^ Your password reset link will be sent to your registered email, i.e. ".$row['email'].". Please check in the spam folder if you can't find in the inbox. Thank You.");
					return $this->redirect()->toRoute('auth', array('action' => 'forgotpwd'));
                    
                }
            }else{
                $this->flashMessenger()->addMessage("warning^ Captcha is invalid. Try again.");
                return $this->redirect()->toRoute('auth', array('action' => 'forgotpwd'));
            }
        }
        $ViewModel = new ViewModel(array('title' => 'Forgot Password','captcha'=>$captcha));
        $ViewModel->setTerminal(false);
        return $ViewModel;
    }
    /**
     * amendpwd Action -- link from email
     */
    public function amendpwdAction()
    {	
		$recovery_dtl = $this->params()->fromRoute('id');
		$decoded_dtl = base64_decode(str_pad(strtr($recovery_dtl, '-_', '+/'), 4 - ((strlen($recovery_dtl) % 4) ?: 4), '=', STR_PAD_RIGHT));
		$array_dtl = explode("***", $decoded_dtl);
		$email = (sizeof($array_dtl)>1)?$array_dtl[0]:'0';
		$expiry_time = (sizeof($array_dtl)>1)?$array_dtl[1]:'0';
		$userDtls = $this->getDefinedTable(Administration\UsersTable::class)->get(array('email' => $email));
		
        if($this->getRequest()->isPost()) {
            $form = $this->getRequest()->getPost();
			$staticSalt = $this->password()->getStaticSalt();
			$user_dtls = $this->getDefinedTable(Administration\UsersTable::class)->get(array('email' => $form['recovery_id']));	
			if(sizeof($user_dtls) == 1):
				foreach($user_dtls as $user_dtl);
				if($user_dtl['email'] == $form['recovery_id']):
					if($form['new_password'] == $form['confirm_password']):
						$dynamicSalt = $this->password()->generateDynamicSalt();
						$password = $this->password()->encryptPassword(
								$staticSalt,
								$form['new_password'],
								$dynamicSalt
						);
						$data = array(
								'id'		=> $user_dtl['id'],
								'password'	=> $password,
								'salt'		=> $dynamicSalt,
						);
						$result = $this->getDefinedTable(Administration\UsersTable::class)->save($data);
						if($result > 0):	
							$this->flashMessenger()->addMessage("success^ Successfully updated user password.");
						else:
							$this->flashMessenger()->addMessage("error^ Failed to update user password.");
						endif;
					else:
						$this->flashMessenger()->addMessage("error^ New Password and Confirmed Password doesn't match.");
					endif;
				else:
					$this->flashMessenger()->addMessage("error^ The entered email and the recovery details doesn't match.");
				endif;
			else:
				$this->flashMessenger()->addMessage("error^ The user with following recovery details doesn't exist anymore in the system.");
			endif;
			return $this->redirect()->toRoute('auth', array('action' => 'login'));
        }
		if($expiry_time < date('Y-m-d H:i:s')){
			$this->flashMessenger()->addMessage('error^ This password recovery link has already expired.');
			return $this->redirect()->toRoute('auth', array('action' => 'login'));
		}else{
			if(sizeof($userDtls) == 0){
				$this->flashMessenger()->addMessage('error^ This email is no more associated with any of the users in the system.');
				return $this->redirect()->toRoute('auth', array('action' => 'login'));
			}else{
				foreach ($userDtls as $row);
				$email = $row['email'];
				$ViewModel = new ViewModel(array('title' => 'Amend Password','email' => $email,));
				$ViewModel->setTerminal(false);
				return $ViewModel;
			}
		}
		return $this->redirect()->toRoute('auth', array('action' => 'login'));
    }
}
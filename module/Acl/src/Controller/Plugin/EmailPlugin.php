<?php
namespace Acl\Controller\Plugin;

use Administration\Model\AppSettingTable;
use Interop\Container\ContainerInterface;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\View\Model\ViewModel;
use Laminas\Db\Adapter\Adapter;    
use Laminas\Authentication\AuthenticationService;
use Laminas\Mail;
use Laminas\Mime;
use Laminas\Mail\Transport\Smtp as SmtpTransport;
use Laminas\Mail\Transport\SmtpOptions;

class EmailPlugin extends AbstractPlugin{
	private $container;

	public function __construct(ContainerInterface $container)
	{
		$this->container = $container;
	}

	public function sendmail($mail)
	{	
		$this->sendEmail($mail); 
	}

	private function isUnsupportedAuthException(\Exception $e)
	{
		$message = strtolower((string) $e->getMessage());
		return (strpos($message, 'authentication mechanism is not supported') !== false)
			|| (strpos($message, 'unrecognized authentication type') !== false)
			|| (strpos($message, 'auth command is not supported') !== false);
	}

	private function sendWithOptions(array $optionsData, Mail\Message $message)
	{
		$transport = new SmtpTransport();
		$transport->setOptions(new SmtpOptions($optionsData));
		$transport->send($message);
	}

	private function env($name, $default = '')
	{
		$value = getenv($name);
		if ($value === false || $value === null) {
			return $default;
		}

		$value = trim((string) $value);
		return ($value === '') ? $default : $value;
	}

	private function getMailSettings()
	{
		$defaults = [
			'app_name' => 'Monal-ERP',
			'mail_from_email' => $this->env('MAIL_FROM_EMAIL', 'no-reply@localhost'),
			'mail_from_name' => $this->env('MAIL_FROM_NAME', 'Monal-ERP'),
			'support_email' => $this->env('SUPPORT_EMAIL', 'support@localhost'),
			'support_phone' => '+975 (02) 333849',
			'smtp_host' => $this->env('SMTP_HOST', ''),
			'smtp_port' => (int) $this->env('SMTP_PORT', '587'),
			'smtp_encryption' => strtolower($this->env('SMTP_ENCRYPTION', 'tls')),
			'smtp_username' => $this->env('SMTP_USERNAME', ''),
			'smtp_password' => $this->env('SMTP_PASSWORD', ''),
		];

		if (!$this->container->has(Adapter::class)) {
			return $defaults;
		}

		try {
			$settingTable = new AppSettingTable($this->container->get(Adapter::class));
			$dbSettings = $settingTable->getSettings();

			$settings = [
				'app_name' => trim((string) ($dbSettings['app_name'] ?? '')),
				'mail_from_email' => trim((string) ($dbSettings['mail_from_email'] ?? '')),
				'mail_from_name' => trim((string) ($dbSettings['mail_from_name'] ?? '')),
				'support_email' => trim((string) ($dbSettings['support_email'] ?? '')),
				'support_phone' => trim((string) ($dbSettings['support_phone'] ?? '')),
				'smtp_host' => trim((string) ($dbSettings['smtp_host'] ?? '')),
				'smtp_port' => (int) ($dbSettings['smtp_port'] ?? 0),
				'smtp_encryption' => strtolower(trim((string) ($dbSettings['smtp_encryption'] ?? ''))),
				'smtp_username' => trim((string) ($dbSettings['smtp_username'] ?? '')),
				'smtp_password' => (string) ($dbSettings['smtp_password'] ?? ''),
			];

			$validEncryption = ['none', 'ssl', 'tls'];
			if (!in_array($settings['smtp_encryption'], $validEncryption, true)) {
				$settings['smtp_encryption'] = $defaults['smtp_encryption'];
			}

			if ($settings['smtp_port'] <= 0 || $settings['smtp_port'] > 65535) {
				$settings['smtp_port'] = (int) $defaults['smtp_port'];
			}

			$hasCustomSmtpHost = $settings['smtp_host'] !== '';
			if (!$hasCustomSmtpHost) {
				return $defaults;
			}

			if ($settings['mail_from_email'] === '') {
				$settings['mail_from_email'] = $defaults['mail_from_email'];
			}
			if ($settings['mail_from_name'] === '') {
				$settings['mail_from_name'] = $defaults['mail_from_name'];
			}
			if ($settings['app_name'] === '') {
				$settings['app_name'] = $defaults['app_name'];
			}
			if ($settings['support_email'] === '') {
				$settings['support_email'] = ($settings['mail_from_email'] !== '')
					? $settings['mail_from_email']
					: $defaults['support_email'];
			}
			if ($settings['support_phone'] === '') {
				$settings['support_phone'] = $defaults['support_phone'];
			}

			return $settings;
		} catch (\Exception $e) {
			return $defaults;
		}
	}

	private function getApplicationUrl()
	{
		$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		$host = $_SERVER['HTTP_HOST'] ?? '';
		if ($host !== '') {
			return $scheme . '://' . $host . '/';
		}

		return '';
	}
	
	private function sendEmail($mail)
	{
		$mailSettings = $this->getMailSettings();

		if (trim((string) ($mailSettings['smtp_host'] ?? '')) === '') {
			// No SMTP configured: skip send instead of throwing transport errors.
			return;
		}

		$view       = new \Laminas\View\Renderer\PhpRenderer();
		$resolver   = new \Laminas\View\Resolver\TemplateMapResolver();
		
		$resolver->setMap(array(
			'mailTemplate' => __DIR__ . '/../../../view/acl/index/mailtemplate.phtml'
		));

		$view->setResolver($resolver);
	 
		$viewModel  = new ViewModel();
		$viewModel->setTemplate('mailTemplate')->setVariables(array(
			'mail'          => $mail,
			'app'           => [
				'name' => $mailSettings['app_name'],
				'url' => $this->getApplicationUrl(),
				'support_email' => $mailSettings['support_email'],
				'support_phone' => $mailSettings['support_phone'],
			],
		));
		
		$bodyPart = new Mime\Message();
		$bodyMessage    = new Mime\Part($view->render($viewModel));
		$bodyMessage->type = 'text/html';
		$bodyPart->setParts(array($bodyMessage));
	 
		$message = new Mail\Message();
		$message->addTo($mail['email'],$mail['name'])
				->setSubject($mail['subject'])
				->setBody($bodyPart)
				->setFrom($mailSettings['mail_from_email'], $mailSettings['mail_from_name'])
				->setEncoding('UTF-8');
		if(sizeof($mail['cc_array'])>0){
			foreach($mail['cc_array'] as $cc_recipient):
				$message->addCc($cc_recipient['email'],$cc_recipient['name']);
			endforeach;
		}

		$baseOptions = [
			'host' => $mailSettings['smtp_host'],
			'port' => (int) $mailSettings['smtp_port'],
		];

		$sslConfig = [];
		if ($mailSettings['smtp_encryption'] !== 'none') {
			$sslConfig['ssl'] = $mailSettings['smtp_encryption'];
		}

		$username = trim((string) ($mailSettings['smtp_username'] ?? ''));
		$password = (string) ($mailSettings['smtp_password'] ?? '');
		$hasCredentials = ($username !== '' && $password !== '');

		if (!$hasCredentials) {
			$optionsData = $baseOptions;
			if (!empty($sslConfig)) {
				$optionsData['connection_config'] = $sslConfig;
			}

			$this->sendWithOptions($optionsData, $message);
			return;
		}

		$authConfig = array_merge([
			'username' => $username,
			'password' => $password,
		], $sslConfig);
		$authMethods = ['login', 'plain', 'crammd5'];

		foreach ($authMethods as $authMethod) {
			$options = $baseOptions;
			$options['connection_class'] = $authMethod;
			$options['connection_config'] = $authConfig;

			try {
				$this->sendWithOptions($options, $message);
				return;
			} catch (\Exception $e) {
				if (!$this->isUnsupportedAuthException($e)) {
					throw $e;
				}
			}
		}

		$noAuthOptions = $baseOptions;
		if (!empty($sslConfig)) {
			$noAuthOptions['connection_config'] = $sslConfig;
		}
		$this->sendWithOptions($noAuthOptions, $message);
	}
}
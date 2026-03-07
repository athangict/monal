<?php
namespace Pswf;

use Laminas\Router\Http\Segment;

return array(
    'router' => array(
        'routes' => array(
			'pswf' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/pswf[/:action[/:id]]',
					'constraints' => array(
								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
								'id'     	 => '[a-zA-Z0-9_-]*',
							),
					'defaults' => array(
						'controller' => Controller\IndexController::class,
						'action'        => 'index',
					),
				),
			),
			'pchartaccount' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/pc[/:action[/:id]]',
					'constraints' => array(
								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
								'id'     	 => '[a-zA-Z0-9_-]*',
							),
					'defaults' => array(
						'controller' => Controller\ChartaccountController::class,
						'action'        => 'index',
					),
				),
			),	
			
			'ptransaction' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/pt[/:action[/:id]]',
					'constraints' => array(
								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
								'id'     	 => '[a-zA-Z0-9_-]*',
							),
					'defaults' => array(
						'controller' => Controller\TransactionController::class,
						'action'        => 'index',
					),
				),
			),	
					
			'paccrep' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/paccrep[/:action[/:id]]',
					'constraints' => array(
								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
								'id'     	 => '[a-zA-Z0-9_-]*',
							),
					'defaults' => array(
						'controller' => Controller\ReportController::class,
						'action'        => 'cashbook',
					),
				),
			),	
        	'pclosing' => array(
        			'type'    => 'Segment',
        			'options' => array(
        					'route'    => '/paccclose[/:action[/:id]]',
        					'constraints' => array(
        							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
									'id'     	 => '[a-zA-Z0-9_-]*',
        					),
        					'defaults' => array(
        							'controller'    => Controller\ClosingController::class,
        							'action'        => 'index',
        					),
        			),
        	),
			'benefit' => array(
        			'type'    => 'Segment',
        			'options' => array(
        					'route'    => '/benefit[/:action[/:id]]',
        					'constraints' => array(
        							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
									'id'     	 => '[a-zA-Z0-9_-]*',
        					),
        					'defaults' => array(
        							'controller'    => Controller\BenefitController::class,
        							'action'        => 'index',
        					),
        			),
        	),
			'padvancesalary' => array(
        			'type'    => 'Segment',
        			'options' => array(
        					'route'    => '/advsalary[/:action[/:id]]',
        					'constraints' => array(
        							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
									'id'     	 => '[a-zA-Z0-9_-]*',
        					),
        					'defaults' => array(
        							'controller' => Controller\AdvancesalaryController::class,
        							'action'        => 'index',
        					),
        			),
        	),
            'ppartyadjustment' => array(
        			'type'    => 'Segment',
        			'options' => array(
        					'route'    => '/partyadjt[/:action[/:id]]',
        					'constraints' => array(
        							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
									'id'     	 => '[a-zA-Z0-9_-]*',
        					),
        					'defaults' => array(
        							'controller' => Controller\PartyAdjustmentController::class,
        							'action'        => 'partyadjustment',
        					),
        			),
        	),
			
		),
	),	
	'view_manager' => array(
        'template_path_stack' => array(
		    'pswf'=>__DIR__ . '/../view/',
        ),
		'display_exceptions' => true,
    ),
);
<?php
namespace Accounts;

use Laminas\Router\Http\Segment;

return array(
    'router' => array(
        'routes' => array(
			'account' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/acc[/:action[/:id]]',
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
	
			'accmaster' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/accmaster[/:action[/:id]]',
					'constraints' => array(
								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
								'id'     	 => '[a-zA-Z0-9_-]*',
							),
					'defaults' => array(
						'controller' => Controller\MasterController::class,
						'action'        => 'bankreftype',
					),
				),
			),
			
			'chartaccount' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/c[/:action[/:id]]',
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
			
			'asset' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/ast[/:action[/:id]]',
					'constraints' => array(
								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
								'id'     	 => '[a-zA-Z0-9_-]*',
							),
					'defaults' => array(
						'controller' => Controller\AssetController::class,
						'action'        => 'party',
					),
				),
			),	
			'ima' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/ima[/:action[/:id]]',
					'constraints' => array(
								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
								'id'     	 => '[a-zA-Z0-9_-]*',
							),
					'defaults' => array(
						'controller' => Controller\ImaController::class,
						'action'        => 'index',
					),
				),
			),		
			'transaction' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/t[/:action[/:id]]',
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
					
			'report' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/accrep[/:action[/:id]]',
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
        	'closing' => array(
        			'type'    => 'Segment',
        			'options' => array(
        					'route'    => '/accclose[/:action[/:id]]',
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
			'recolcilation' => array(
        			'type'    => 'Segment',
        			'options' => array(
        					'route'    => '/recolcile[/:action[/:id]]',
        					'constraints' => array(
        							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
									'id'     	 => '[a-zA-Z0-9_-]*',
        					),
        					'defaults' => array(
        							'controller'    => Controller\ReconcilationController::class,
        							'action'        => 'index',
        					),
        			),
        	),
			'advancesalary' => array(
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
            'partyadjustment' => array(
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
			 'budgetmanagement' => array(
        			'type'    => 'Segment',
        			'options' => array(
        					'route'    => '/budgetmgt[/:action[/:id]]',
        					'constraints' => array(
        							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
									'id'     	 => '[a-zA-Z0-9_-]*',
        					),
        					'defaults' => array(
        							'controller' => Controller\BudgetManagementController::class,
        							'action'        => 'forecasting',
        					),
        			),
        	),
		),
	),	
	'view_manager' => array(
        'template_path_stack' => array(
            'accounts'=>__DIR__ . '/../view/',
        ),
		'display_exceptions' => true,
    ),
);
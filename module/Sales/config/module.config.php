<?php
namespace Sales;

use Laminas\Router\Http\Segment;

return array(	
    'router' => array(
        'routes' => array( 
        	'sale' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'       => '/sale[/:action[/:id]]',
					'constraints' => array(
						'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
						'id'     	 => '[a-zA-Z0-9_-]*',
					),
					'defaults' => array(
						'controller' => Controller\IndexController::class,
						'action'     => 'index',
					),
				),
            ),

        	'slmaster' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'       => '/slmaster[/:action[/:id]]',
					'constraints' => array(
						'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
						'id'     	 => '[a-zA-Z0-9_-]*',
					),
					'defaults' => array(
						'controller' => Controller\MasterController::class,
						'action'     => 'schemetype',
					),
				),
            ),

            'schemes' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'       => '/schemes[/:action[/:id]]',
					'constraints' => array(
						'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
						'id'     	 => '[a-zA-Z0-9_-]*',
					),
					'defaults' => array(
						'controller' => Controller\SchemeController::class,
						'action'     => 'scheme',
					),
				),
            ),

            'sales' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'       => '/sales[/:action[/:id]]',
					'constraints' => array(
						'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
						'id'     	 => '[a-zA-Z0-9_-]*',
					),
					'defaults' => array(
						'controller' => Controller\SalesController::class,
						'action'     => 'receipt',
					),
				),
            ),
            
            'claim' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'       => '/claim[/:action[/:id]]',
					'constraints' => array(
						'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
						'id'     	 => '[a-zA-Z0-9_-]*',
					),
					'defaults' => array(
						'controller' => Controller\ClaimController::class,
						'action'     => 'claim',
					),
				),
            ),

            'sl_activity' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'       => '/sl_activity[/:action[/:id]]',
					'constraints' => array(
						'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
					),
					'defaults' => array(
						'controller' => Controller\SalesController::class,
						'action'     => 'slactivity',
					),
				),
            ),
			
			'fund' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'       => '/fund[/:action[/:id]]',
					'constraints' => array(
						'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
						'id'     	 => '[a-zA-Z0-9_-]*',
					),
					'defaults' => array(
						'controller' => Controller\FundController::class,
						'action'     => 'index',
					),
				),
            ),
			'pos' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'       => '/pos[/:action[/:id]]',
					'constraints' => array(
						'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
						'id'     	 => '[a-zA-Z0-9_-]*',
					),
					'defaults' => array(
						'controller' => Controller\PosController::class,
						'action'     => 'index',
					),
				),
            ),
		),
	),	
	'view_manager' => array(
        'template_path_stack' => array(
            'sales'=>__DIR__ . '/../view/',
        ),
		'display_exceptions' => true,
    ),
);

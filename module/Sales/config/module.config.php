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
			'museum' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'       => '/museum[/:action[/:id]]',
					'constraints' => array(
						'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
						'id'     	 => '[a-zA-Z0-9_-]*',
					),
					'defaults' => array(
						'controller' => Controller\MuseumController::class,
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
			'masterpos' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'       => '/masterpos[/:action[/:id]]',
					'constraints' => array(
						'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
						'id'     	 => '[a-zA-Z0-9_-]*',
					),
					'defaults' => array(
						'controller' => Controller\MasterposController::class,
						'action'     => 'scope',
					),
				),
            ),
			'postage' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'       => '/postage[/:action[/:id]]',
					'constraints' => array(
						'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
						'id'     	 => '[a-zA-Z0-9_-]*',
					),
					'defaults' => array(
						'controller' => Controller\PostageController::class,
						'action'     => 'index',
					),
				),
            ),
			'postbox' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/postbox[/:action[/:id]]',
					'constraints' => array(
							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
							'id'     	 => '[a-zA-Z0-9_-]*',
					),
					'defaults' => array(
							'controller' => Controller\PostController::class,
							'action'   => 'registration',
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

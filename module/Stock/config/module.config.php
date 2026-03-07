<?php
namespace Stock;

use Laminas\Router\Http\Segment;

return array(	
    'router' => array(
        'routes' => array(  
        		'stocks' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/s[/:action[/:id]]',
        						'constraints' => array(
        								'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'      => '[a-zA-Z0-9_-]*',
        						),
        		
        						'defaults' => array(
        								'controller' => Controller\IndexController::class,
        								'action'     => 'index',
        						),
        				),
        		),
        		
	            'stock' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'       => '/st[/:action[/:id]]',
						'constraints' => array(
							'action'   => '[a-zA-Z][a-zA-Z0-9_-]*',
							'id'       => '[a-zA-Z0-9_-]*',
						),
						
						'defaults' => array(
							'controller' => Controller\StockController::class,
							'action'     => 'stock',
						),
					),
	            ),            
	          
	            'stmaster' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'       => '/stmaster[/:action[/:id]]',
						'constraints' => array(
							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
							'id'     	 => '[a-zA-Z0-9_-]*',
						),
						'defaults' => array(
							'controller' => Controller\MasterController::class,
							'action'     => 'uomtype',
						),
					),
	            ),					

	        	'batch' => array(
					'type'    => 'Segment',
					'options' => array(
							'route'       => '/b[/:action[/:id]]',
							'constraints' => array(
								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
								'id'     	 => '[a-zA-Z0-9_-]*',
							),
							'defaults' => array(
								'controller' => Controller\BatchController::class,
								'action'     => 'index',
							),
					),
	        	),
	        	'moving' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/mv[/:action[/:id]]',
        						'constraints' => array(
        								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'     	 => '[a-zA-Z0-9_-]*',
        						),
        						'defaults' => array(
        								'controller' => Controller\BatchController::class,
        								'action'     => 'movingitem',
        						),
        				),
        		),
	        	'cost' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'       => '/cost[/:action[/:id]]',
						'constraints' => array(
							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
							'id'     	 => '[a-zA-Z0-9_-]*',
						),
						'defaults' => array(
							'controller' => Controller\CostingController::class,
							'action'     => 'costingsheet',
						),
					),
				),			
				'formula' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'       => '/formula[/:action[/:id]]',
						'constraints' => array(
							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
							'id'     	 => '[a-zA-Z0-9_-]*',
						),
						'defaults' => array(
							'controller' => Controller\FormulasheetController::class,
							'action'     => 'index',
						),
					),
				),
				'dispatch' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'       => '/dispatch[/:action[/:id]]',
						'constraints' => array(
							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
							'id'     	 => '[a-zA-Z0-9_-]*',
						),
						'defaults' => array(
							'controller' => Controller\DispatchController::class,
							'action'     => 'index',
						),
					),
				),
	        	'goodsrequest' => array(
	        			'type'    => 'Segment',
	       				'options' => array(
	       					'route'       => '/gr[/:action[/:id]]',
	        				'constraints' => array(
	        				'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
	        				'id'     	 => '[a-zA-Z0-9_-]*',
	        			),
	        			'defaults' => array(
	        				'controller' => Controller\GoodsrequestController::class,
	      					'action'     => 'index',
	       				),
	       			),
	       		),
	        	'transporter' => array(
	        			'type'    => 'Segment',
	        			'options' => array(
	        				'route'       => '/transp[/:action[/:id]]',
	        				'constraints' => array(
	        				'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
	       					'id'     	 => '[a-zA-Z0-9_-]*',
	        			),
	        			'defaults' => array(
	        				'controller' => Controller\TransporterController::class,
	        				'action'     => 'transportcharge',
	       				),
	       			),
	        	),

			  'sam' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/sam[/:action[/:id]]',
        						'constraints' => array(
        								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'     	 => '[a-zA-Z0-9_-]*',
        						),
        						'defaults' => array(
        								'controller' => Controller\SamController::class,
        								'action'     => 'openingstock',
        						),
        				),
        		),
				'price' => array(
					'type'    => 'Segment',
					'options' => array(
							'route'       => '/price[/:action[/:id]]',
							'constraints' => array(
									'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
									'id'     	 => '[a-zA-Z0-9_-]*',
							),
							'defaults' => array(
									'controller' => Controller\PriceController::class,
									'action'     => 'index',
							),
					),
			),
			),
		),	
	'view_manager' => array(
        'template_path_stack' => array(
            __DIR__ . '/../view/',
        ),
		'display_exceptions' => true,
    ),
);

<?php
return array(
    // Both the alias and invokable are required for ZnZend\Mvc\Controller\Plugin\Service\ZnZendIdentityFactory to work
    'service_manager' => array(
        'aliases' => array(
            'ZnZend\Authentication\AuthenticationService' => 'Zend\Authentication\AuthenticationService',
        ),

        'invokables' => array(
            'Zend\Authentication\AuthenticationService' => 'Zend\Authentication\AuthenticationService',
        ),
    ),

    'controller_plugins' => array(
        'factories'  => array(
            'znZendIdentity' => 'ZnZend\Mvc\Controller\Plugin\Service\ZnZendIdentityFactory',
        ),

        'invokables' => array(
            'znZendDataTables' => 'ZnZend\Mvc\Controller\Plugin\ZnZendDataTables',
            'znZendMvcParams'  => 'ZnZend\Mvc\Controller\Plugin\ZnZendMvcParams',
            'znZendPageStore'  => 'ZnZend\Mvc\Controller\Plugin\ZnZendPageStore',
            'znZendTimestamp'  => 'ZnZend\Mvc\Controller\Plugin\ZnZendTimestamp',
        ),
    ),

    'view_helpers' => array(
        'invokables' => array(
            'znZendColumnizeEntities' => 'ZnZend\View\Helper\ZnZendColumnizeEntities',
            'znZendExcerpt'           => 'ZnZend\View\Helper\ZnZendExcerpt',
            'znZendFormatBytes'       => 'ZnZend\View\Helper\ZnZendFormatBytes',
            'znZendFormatDateRange'   => 'ZnZend\View\Helper\ZnZendFormatDateRange',
            'znZendFormatTimeRange'   => 'ZnZend\View\Helper\ZnZendFormatTimeRange',
            'znZendResizeImage'       => 'ZnZend\View\Helper\ZnZendResizeImage',
            // Form view helpers
            'znZendFormElementValue'    => 'ZnZend\Form\View\Helper\ZnZendFormElementValue',
            'znZendFormCaptchaQuestion' => 'ZnZend\Form\View\Helper\Captcha\Question',
        ),
    ),

    'view_manager' => array(
        'strategies' => array(
            'ViewJsonStrategy', // required for returning of JsonModel to work
        ),
    ),
);

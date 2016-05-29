<?php
include 'vendor/autoload.php';
Zend\Loader\AutoloaderFactory::factory(array(
    'Zend\Loader\StandardAutoloader' => array(
        'namespaces' => array(
            'Solody\Dbinstall' => './src',
        ),
    ),
));
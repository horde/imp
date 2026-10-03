<?php

// Default routes file. Put custom routes into var/config/imp/routes.local.php and run composer horde:reconfigure


namespace Horde\Imp;

use Horde\Core\Middleware\DefaultStack;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;


// Test route for password credential system
$mapper->buildRoute(uri: '/mailboxes/authtest', name: 'ImpAuthTest')
    ->withController(\Horde\Imp\Mailboxes\AuthTestController::class)
    ->withDefaults(['HordeAuthType' => 'authenticate'])
    ->withMiddleware(DefaultStack::get())
    ->add();


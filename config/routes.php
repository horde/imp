<?php

// Empty default routes file. Put custom routes into var/config/imp/routes.local.php and run composer horde:reconfigure

// Test route for password credential system
$mapper->buildRoute(uri: '/imp/mailboxes/authtest/', name: 'ImpAuthTest')
    ->withController(\Horde\Imp\Mailboxes\AuthTestController::class)
    ->withDefaults(['HordeAuthType' => 'authenticate'])
    ->withMiddleware(\Horde\Core\Middleware\DefaultStack::get())
    ->add();


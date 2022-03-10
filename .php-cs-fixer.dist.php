<?php


$config = new PhpCsFixer\Config();
return $config->setRules([
    '@PHP74Migration' => true,
    '@PSR12' => true,
    'single_quote' => true,
]);

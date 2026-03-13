<?php

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/config',
        __DIR__.'/migrations',
    ])
    ->exclude(['var'])
    ->notName('reference.php')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        '@Symfony' => true,
        'yoda_style' => false,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(false)
;

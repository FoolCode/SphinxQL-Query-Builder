<?php

// PHPUnit Backwards Compatability Fix
if (!class_exists('\PHPUnit\Framework\TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', '\PHPUnit\Framework\TestCase');
}

// autoload.php generated by Composer

require_once __DIR__ . '/../vendor/autoload.php';

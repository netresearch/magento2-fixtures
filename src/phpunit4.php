<?php

/*
 * Forward compatibility for PHPUnit 4.x (Magento 2.1)
 */
if (!class_exists(\PHPUnit\Framework\TestCase::class)) {
    class_alias(\PHPUnit\Framework\TestCase::class, PHPUnit4_TestCase::class);
    abstract class PHPUnit4_TestCase extends \PHPUnit_Framework_TestCase
    {
        public function expectException($exceptionName)
        {
            $this->setExpectedException($exceptionName);
        }
    }
}

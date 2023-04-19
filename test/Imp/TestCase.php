<?php

class TestCase extends PHPUnit\Framework\TestCase
{
    protected function getLibSmimeClass(){
        $hordeCryptSmime = $this->createMock(Horde_Crypt_Smime::class); 
        $hordeDBAdapter =$this->createMock(Horde_Db_Adapter::class);
        $libSmime = $this->getMockBuilder(IMP_Smime::class)
            ->setConstructorArgs([$hordeCryptSmime, $hordeDBAdapter])
            ->getMock();

        return $libSmime;
    }

    protected function getBasicSmime(){
        $impBasicSmime = $this->createMock(IMP_Basic_Smime::class);
        return $impBasicSmime;
    }
    
    protected function getKeys( $user){
        $wd = getcwd();
        $path = $wd."/test/Imp/SmimeKeys/$user/cert.p12";
        return file_get_contents($path);
    }

}
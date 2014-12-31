<?php

namespace TableauWorldServer\Tests {

  use TableauWorldServer\MiddleWare;

  class MiddleWareTest extends \PHPUnit_Framework_TestCase {

    /**
     * @test
     * @expectedException \Exception
     * @expectedExceptionMessage The provided SFTP client must already be connected.
     */
    public function constructorExpectsConnection() {
      $mockClient = $this->getMockBuilder('Net_SFTP')
        ->disableOriginalConstructor()
        ->getMock();
      $mockWrapper = $this->getMockBuilder('EntityDrupalWrapper')
        ->disableOriginalConstructor()
        ->getMock();

      $middleware = new MiddleWare($mockClient, $mockWrapper);
    }

    /**
     * @test
     */
    public function getXliff() {
      $expectedLang = 'fr-fr';
      $mockClient = $this->getConnectedClientMock();
      $mockWrapper = $this->getMockBuilder('EntityDrupalWrapper')
        ->disableOriginalConstructor()
        ->setMethods(array('raw', 'getIdentifier', 'getPropertyInfo', 'type'))
        ->getMock();

      // @todo Remove/update once we're more entity-type agnostic.
      $mockWrapper->expects($this->any())
        ->method('getPropertyInfo')
        ->willReturn(array());
      $mockWrapper->language = $this->getMock('stdClass', array('value'));

      // Set up an observer on the serializer.
      $observerSerializer = $this->getMockBuilder('EggsCereal\Serializer')
        ->setMethods(array('serialize'))
        ->getMock();

      $observerSerializer->expects($this->once())
        ->method('serialize')
        ->with(
          $this->isInstanceOf('EggsCereal\Interfaces\TranslatableInterface'),
          $this->equalTo($expectedLang)
        );

      $middleware = new MiddleWare($mockClient, $mockWrapper, $observerSerializer);
      $middleware->getXliff($expectedLang);
    }

    /**
     * @test
     */
    public function getFilename() {
      $expectedType = 'entity_type';
      $expectedId = 1234;
      $mockClient = $this->getConnectedClientMock();

      // Set up an observer on the Entity wrapper.
      $observerWrapper = $this->getMockBuilder('EntityDrupalWrapper')
        ->disableOriginalConstructor()
        ->setMethods(array('type', 'getIdentifier'))
        ->getMock();

      // This method should call the entity wrapper's type method once.
      $observerWrapper->expects($this->once())
        ->method('type')
        ->willReturn($expectedType);

      // This method should call the entity wrapper's getIdentifier method once.
      $observerWrapper->expects($this->once())
        ->method('getIdentifier')
        ->willReturn($expectedId);

      // Instantiate MiddleWare and run MiddleWare::getFilename().
      $middleware = new MiddleWare($mockClient, $observerWrapper);
      $this->assertEquals($expectedType . '-' . $expectedId . '.xlf', $middleware->getFilename());
    }

    /**
     * Returns a mock SFTP client that is connected.
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getConnectedClientMock() {
      $mockClient = $this->getMockBuilder('Net_SFTP')
        ->disableOriginalConstructor()
        ->getMock();
      $mockClient->expects($this->any())
        ->method('isConnected')
        ->willReturn(TRUE);
      return $mockClient;
    }

  }
}

/**
 * Most of this need comes from EntityXliff weirdness... @todo Remove.
 */
namespace {
  $GLOBALS['user'] = (object) array();
  function entity_get_info() {}
  function drupal_static_reset() {}
  function node_load() {return (object) array('tnid' => 0);}
  function drupal_save_session() {}
  function user_load() {return $GLOBALS['user'];}
  function translation_node_get_translations() {}
}

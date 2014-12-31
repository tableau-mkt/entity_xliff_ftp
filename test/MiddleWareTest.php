<?php

namespace TableauWorldServer\Tests {

  use TableauWorldServer\MiddleWare;

  class MiddleWareTest extends \PHPUnit_Framework_TestCase {

    /**
     * Tests that the MiddleWare constructor will throw an exception in the case
     * that an SFTP client is passed in that is not connected / logged in.
     *
     * @test
     * @expectedException \Exception
     * @expectedExceptionMessage The provided SFTP client must already be connected.
     */
    public function constructorExpectsConnection() {
      $mockWrapper = $this->getWrapperMock();
      $mockClient = $this->getMockBuilder('Net_SFTP')
        ->disableOriginalConstructor()
        ->getMock();

      $middleware = new MiddleWare($mockClient, $mockWrapper);
    }

    /**
     * Tests that MiddleWare::putXliffs will attempt to pull default installed
     * languages from the DrupalHandler if no languages are provided.
     *
     * @test
     */
    public function putXliffsGetsDefaultInstalledLanguages() {
      $mockWrapper = $this->getWrapperMock(array('type', 'getIdentifier'));
      $mockClient = $this->getConnectedClientMock();

      // Create an observer double for the DrupalHandler.
      $observerDrupal = $this->getMock('TableauWorldServer\Utils\DrupalHandler', array('languageList'));

      // The DrupalHandler observer expects the variableGet method to be called.
      $observerDrupal->expects($this->once())
        ->method('languageList')
        ->with($this->equalTo('language'))
        ->willReturn(array('en' => (object) array()));

      // Instantiate MiddleWare and invoke MiddleWare::putXliffs()
      $middleWare = new MiddleWare($mockClient, $mockWrapper, NULL, $observerDrupal);
      $middleWare->putXliffs();
    }

    /**
     * Tests that MiddleWare::putXliffs will ignore English (not upload English-
     * to-English XLIFF files).
     *
     * @test
     */
    public function putXliffsIgnoresEnglish() {
      $mockWrapper = $this->getWrapperMock();
      $mockClient = $this->getConnectedClientMock();
      $mockDrupal = $this->getMock('TableauWorldServer\Utils\DrupalHandler');
      $mockMiddleWare = $this->getMockBuilder('TableauWorldServer\MiddleWare')
        ->setMethods(array('getXliff', 'putXliff', 'getFilename'))
        ->setConstructorArgs(array($mockClient, $mockWrapper, NULL, $mockDrupal))
        ->getMock();

      // The getXliff and putXliff methods should never be called.
      $mockMiddleWare->expects($this->never())
        ->method('getXliff');
      $mockMiddleWare->expects($this->never())
        ->method('putXliff');

      // Invoke MiddleWare::putXliffs on our test double.
      $mockMiddleWare->putXliffs(array('en' => (object) array()));
    }

    /**
     * Tests that MiddleWare::putXliffs will get XLIFF data and Net_SFTP::put
     * that data to the expected location.
     *
     * @test
     */
    public function putXliffsGetsAndPutsXliffData() {
      $expectedXlfData = '<xml></xml>';
      $expectedLangPathBase = 'en-US_to_';
      $expectedFilename = 'file.xlf';

      $mockWrapper = $this->getWrapperMock();
      $mockClient = $this->getConnectedClientMock();
      $mockDrupal = $this->getMock('TableauWorldServer\Utils\DrupalHandler');
      $mockMiddleWare = $this->getMockBuilder('TableauWorldServer\MiddleWare')
        ->setMethods(array('getXliff', 'putXliff', 'getFilename'))
        ->setConstructorArgs(array($mockClient, $mockWrapper, NULL, $mockDrupal))
        ->getMock();

      // Ensure getFilename just returns the filename.
      $mockMiddleWare->expects($this->once())
        ->method('getFilename')
        ->willReturn($expectedFilename);

      // The getXliff method should be called with 'fr' and 'de' in that order.
      $mockMiddleWare->expects($this->exactly(2))
        ->method('getXliff')
        ->withConsecutive(array($this->equalTo('fr')), array($this->equalTo('de')))
        ->willReturn($expectedXlfData);

      // The putXliff file should be called with expected data and params.
      $mockMiddleWare->expects($this->exactly(2))
        ->method('putXliff')
        ->withConsecutive(
          array($expectedXlfData, $expectedLangPathBase . 'fr-FR', $expectedFilename),
          array($expectedXlfData, $expectedLangPathBase . 'de-DE', $expectedFilename)
        )
        ->willReturn(FALSE);

      // Invoke MiddleWare::putXliffs on our test double.
      $mockMiddleWare->putXliffs($this->getValidLangObjects());
    }

    /**
     * Tests that MiddleWare::putXliffs will set success messages for each
     * valid FTP put command run.
     *
     * @test
     */
    public function putXliffsSetsDrupalMessages() {
      $expectedMessage = 'Success message.';

      $mockWrapper = $this->getWrapperMock(array('type', 'label'));
      $mockClient = $this->getConnectedClientMock();
      $observerDrupal = $this->getMock('TableauWorldServer\Utils\DrupalHandler');

      // The t method should be called twice with the expected values.
      $observerDrupal->expects($this->exactly(2))
        ->method('t')
        ->with($this->equalTo('Successfully uploaded @language XLIFF file for @type %label'))
        ->willReturn($expectedMessage);

      // The setMessage method should be called twice with the expected values.
      $observerDrupal->expects($this->exactly(2))
        ->method('setMessage')
        ->with($this->equalTo($expectedMessage), $this->equalTo('status'));

      // Build a mock double for MiddleWare (to inject observers on itself).
      $mockMiddleWare = $this->getMockBuilder('TableauWorldServer\MiddleWare')
        ->setMethods(array('getXliff', 'putXliff', 'getFilename'))
        ->setConstructorArgs(array($mockClient, $mockWrapper, NULL, $observerDrupal))
        ->getMock();

      // Force MiddleWare::putXliff to return TRUE so setMessage is called.
      $mockMiddleWare->expects($this->any())
        ->method('putXliff')
        ->willReturn(TRUE);

      // Invoke MiddleWare::putXliffs on our test double.
      $mockMiddleWare->putXliffs($this->getValidLangObjects());
    }

    /**
     * Tests that MiddleWare::putXliff DOES NOT call NetSFTP::put. Also ensures
     * that a Drupal message is set.
     *
     * @test
     */
    public function putXliffTargetDoesNotExist() {
      $translatedMessage = 'Translated no target directory message.';
      $expectedResponse = FALSE;
      $mockWrapper = $this->getWrapperMock();

      // Create an observer double for the SFTP client.
      $observerClient = $this->getConnectedClientMock();

      // The client's put method should never be called.
      $observerClient->expects($this->never())
        ->method('put');

      // Create an observer double for the DrupalHandler.
      $observerDrupal = $this->getMock('TableauWorldServer\Utils\DrupalHandler', array(
        'variableGet',
        'setMessage',
        't',
      ));

      // The DrupalHandler observer expects the variableGet method to be called.
      $observerDrupal->expects($this->once())
        ->method('variableGet')
        ->with($this->equalTo(MiddleWare::TARGETROOTVAR), $this->equalTo(FALSE))
        ->willReturn($expectedResponse);

      $observerDrupal->expects($this->once())
        ->method('t')
        ->with($this->equalTo('No target directory is configured.'))
        ->willReturn($translatedMessage);

      // The DrupalHandler observer expects the setMessage method to be called.
      $observerDrupal->expects($this->once())
        ->method('setMessage')
        ->with($this->equalTo($translatedMessage), $this->equalTo('error'));

      // Instantiate MiddleWare and invoke MiddleWare::putXliff().
      $middleware = new MiddleWare($observerClient, $mockWrapper, NULL, $observerDrupal);
      $this->assertSame($expectedResponse, $middleware->putXliff(NULL, NULL, NULL));
    }

    /**
     * Tests that MiddleWare::putXliff calls Net_SFTP::put with the values that
     * the SFTP client expects.
     *
     * @test
     */
    public function putXliffTargetExists() {
      $expectedXlfData = '<xml></xml>';
      $expectedTarget = '/path/to/target';
      $expectedLangPath = 'en-US_to_ja-JP';
      $expectedFilename = 'file.xlf';
      $expectedFullPath = $expectedTarget . '/' . $expectedLangPath . '/' . $expectedFilename;
      $expectedResponse = TRUE;

      $observerClient = $this->getConnectedClientMock();
      $mockWrapper = $this->getWrapperMock();

      // Create an observer double for the DrupalHandler.
      $observerDrupal = $this->getMock('TableauWorldServer\Utils\DrupalHandler', array('variableGet'));

      // The DrupalHandler observer expects the variableGet method to be called.
      $observerDrupal->expects($this->once())
        ->method('variableGet')
        ->with($this->equalTo(MiddleWare::TARGETROOTVAR), $this->equalTo(FALSE))
        ->willReturn($expectedTarget);

      // The client observer expects the put method to be called.
      $observerClient->expects($this->once())
        ->method('put')
        ->with($this->equalTo($expectedFullPath), $this->equalTo($expectedXlfData))
        ->willReturn($expectedResponse);

      // Instantiate MiddleWare and invoke MiddleWare::putXliff().
      $middleware = new MiddleWare($observerClient, $mockWrapper, NULL, $observerDrupal);
      $this->assertSame($expectedResponse, $middleware->putXliff($expectedXlfData, $expectedLangPath, $expectedFilename));
    }

    /**
     * Tests that MiddleWare::getXliff runs Serializer::serialize with a
     * Translatable based on the encapsulated Entity wrapper for the given
     * target language.
     *
     * @test
     */
    public function getXliff() {
      $expectedLang = 'fr-fr';
      $mockClient = $this->getConnectedClientMock();
      $mockWrapper = $this->getWrapperMock(array('raw', 'getIdentifier', 'getPropertyInfo', 'type'));

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
     * Tests that MiddleWare::getFilename returns a filename, based on the
     * encapsulated Entity wrapper, in the expected format of:
     * - [Entity Type]-[Entity ID].xlf
     *
     * @test
     */
    public function getFilename() {
      $expectedType = 'entity_type';
      $expectedId = 1234;
      $mockClient = $this->getConnectedClientMock();
      $observerWrapper = $this->getWrapperMock(array('type', 'getIdentifier'));

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

    /**
     * Returns a simple mock EntityDrupalWrapper.
     *
     * @param array $setMethods
     *   (optional) If provided, an array of method names that will be set by
     *   the caller.
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getWrapperMock(array $setMethods = NULL) {
      return $this->getMockBuilder('EntityDrupalWrapper')
        ->disableOriginalConstructor()
        ->setMethods($setMethods)
        ->getMock();
    }

    /**
     * Returns an array of valid Drupal language objects (or at least valid
     * insofar as MiddleWare needs it to be).
     *
     * @return object[]
     */
    protected function getValidLangObjects() {
      return array(
        'fr' => (object) array('prefix' => 'fr-fr', 'name' => 'French'),
        'de' => (object) array('prefix' => 'de-de', 'name' => 'German'),
      );
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

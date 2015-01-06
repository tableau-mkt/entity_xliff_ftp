<?php

/**
 * @file
 * Contains the MiddleWare class.
 */

namespace TableauWorldServer;

use EggsCereal\Serializer;
use TableauWorldServer\Utils\DrupalHandler;


class MiddleWare {

  /**
   * @var \Net_SFTP
   */
  protected $client;

  /**
   * @var \EntityDrupalWrapper
   */
  protected $wrapper;

  /**
   * @var Serializer
   */
  protected $serializer;

  /**
   * @var DrupalHandler
   */
  protected $drupal;

  /**
   * Describes the Drupal variable name representing the target root.
   */
  CONST TARGETROOTVAR = 'tableau_worldserver_integration_target_root';

  /**
   * Describes the Drupal variable name representing the source root.
   */
  CONST SOURCEROOTVAR = 'tableau_worldserver_integration_source_root';

  /**
   * @param \Net_SFTP $client
   *   An SFTP client, already logged in.
   *
   * @param \EntityDrupalWrapper $wrapper
   *   The Entity wrapper to be used.
   *
   * @param DrupalHandler $handler
   *   (optional) An instance of the DrupalHandler. If none is provided, one
   *   will be instantiated automatically.
   *
   * @throws \Exception
   */
  public function __construct(\Net_SFTP $client, \EntityDrupalWrapper $wrapper, Serializer $serializer = NULL, DrupalHandler $handler = NULL) {
    // Make sure the SFTP client is connected.
    if (!$client->isConnected()) {
      throw new \Exception('The provided SFTP client must already be connected.');
    }

    // If no DrupalHandler was provided, instantiate a new one.
    if ($handler === NULL) {
      $handler = new DrupalHandler();
    }

    // If no Serializer was provided, instantiate a new one.
    if ($serializer === NULL) {
      $serializer = new Serializer();
    }

    $this->client = $client;
    $this->wrapper = $wrapper;
    $this->serializer = $serializer;
    $this->drupal = $handler;
  }

  /**
   * Generates and writes XLIFF data to the configured target directory for all
   * provided languages. If no explicit list is provided, the list of all
   * installed Drupal languages will be used.
   *
   * @param object[] $langs
   *   An associative array of Drupal language objects, keyed by their language
   *   shortcode. Should match the output of language_list().
   */
  public function putXliffs(array $langs = array()) {
    // If no languages were provided, load them dynamically.
    if ($langs === array()) {
      $langs = $this->drupal->languageList('language');
    }

    // Unset English. @todo Don't be so English-centric.
    unset($langs['en']);

    // Set up invariants.
    $fileName = $this->getFilename();
    $langPathBase = 'en-US_to_';

    // Iterate through all languages, generate XLIFF data, and put those files.
    foreach ($langs as $targetLang => $lang) {
      // Calculate the language path.
      $langPathSuffix = substr($lang->prefix, 0, -2) . strtoupper(substr($lang->prefix, -2, 2 ));
      $langPath = $langPathBase . $langPathSuffix;

      $xlf = $this->getXliff($targetLang);
      if ($this->putXliff($xlf, $langPath, $fileName)) {
        $this->drupal->setMessage($this->drupal->t('Successfully uploaded @language XLIFF file for @type %label', array(
          '@language' => $lang->name,
          '@type' => $this->wrapper->type(),
          '%label' => $this->wrapper->label(),
        )), 'status');
      }
    }
  }

  /**
   * Writes XLIFF data to the configured target directory.
   *
   * @param string $xlfData
   *   The XLIFF data to write, represented as a string.
   *
   * @param string $languagePath
   *   The language path-part, used as a sub-directory underneath the configured
   *   target directory (e.g. en-US_to_de-DE).
   *
   * @param string $fileName
   *   The file name to use when writing the file.
   *
   * @return bool
   *   TRUE on success. FALSE on failure.
   */
  public function putXliff($xlfData, $languagePath, $fileName) {
    if ($targetDir = $this->drupal->variableGet(self::TARGETROOTVAR, FALSE)) {
      $targetFile = implode('/', array($targetDir, $languagePath, $fileName));
      $result = $this->client->put($targetFile, $xlfData);
    }
    else {
      $result = FALSE;
      $this->drupal->setMessage($this->drupal->t('No target directory is configured.'), 'error');
    }

    return $result;
  }

  /**
   * Reads translated/processed XLIFF data from Worldserver, unserializes the
   * data against the wrapped entity, and saves all relevant entities.
   *
   * @param object[] $langs
   *   An associative array of Drupal language objects, keyed by their language
   *   shortcode. Should match the output of language_list().
   */
  public function setXliffs(array $langs = array()) {
    // If no languages were provided, load them dynamically.
    if ($langs === array()) {
      $langs = $this->drupal->languageList('language');
    }

    // Unset English. @todo Don't be so English-centric.
    unset($langs['en']);

    // Iterate through all languages, generate XLIFF data, and put those files.
    foreach ($langs as $targetLang => $lang) {
      if ($this->setXliff($this->getProcessedXliff($targetLang), $targetLang)) {
        $this->drupal->setMessage($this->drupal->t('Successfully processed @language translation for @type %label from WorldServer.', array(
          '@language' => $lang->name,
          '@type' => $this->wrapper->type(),
          '%label' => $this->wrapper->label(),
        )), 'status');
      }
    }
  }

  /**
   * Sets and saves translated data on the wrapped entity given a target
   * language and xliff data (as a string).
   *
   * @param string $xlfData
   *   Translated/processed xliff contents as a string.
   *
   * @param string $targetLang
   *   The target language (Drupal language identifier).
   *
   * @return bool
   *   TRUE on success, false on failure.
   */
  public function setXliff($xlfData, $targetLang) {
    if ($translatable = $this->drupal->entityXliffGetTranslatable($this->wrapper)) {
      if ($this->serializer->unserialize($translatable, $targetLang, $xlfData)) {
        return $this->setProcessed($targetLang);
      }
    }
    return FALSE;
  }

  /**
   * @param string $language
   */
  public function getProcessedXliff($language) {
    if ($sourceDir = $this->drupal->variableGet(self::SOURCEROOTVAR, FALSE)) {
      $languagePath = $this->getLanguagePathPartSource($language);
      $targetFile = implode('/', array($sourceDir, $languagePath, $this->getFilename()));
      $result = $this->client->get($targetFile);
    }
    else {
      $result = FALSE;
      $this->drupal->setMessage($this->drupal->t('No source directory is configured.'), 'error');
    }

    return $result;
  }

  /**
   * Marks a given language as processed for this wrapped entity.
   *
   * @param string $language
   *   The Drupal language to mark as processed (e.g. language identifier).
   *
   * @return bool
   *   Returns TRUE on success, FALSE on failure.
   */
  public function setProcessed($language) {
    $root = $this->drupal->variableGet(self::SOURCEROOTVAR);
    $root .= '/' . $this->getLanguagePathPartSource($language);
    $fromFile = $root . '/' . $this->getFilename();
    $toFile = $root . '/processed/' . $this->getFilename();
    $this->client->touch($fromFile);
    return $this->client->rename($fromFile, $toFile);
  }

  /**
   * Returns XLIFF for a given Entity wrapper and target language.
   *
   * @param string $targetLang
   *   The desired target language.
   *
   * @return string
   *   XLIFF representing the given Entity wrapper.
   */
  public function getXliff($targetLang) {
    $translatable = $this->drupal->entityXliffGetTranslatable($this->wrapper);
    return $this->serializer->serialize($translatable, $targetLang);
  }

  /**
   * Returns the expected file name of this class' Entity wrapper.
   *
   * @return string
   *   Returns the file name for the given entity/wrapper.
   *
   * @see Querier::parseFilename()
   */
  public function getFilename() {
    return $this->wrapper->type() . '-' . $this->wrapper->getIdentifier() . '.xlf';
  }

  /**
   * @param string $language
   */
  public function getLanguagePathPartTarget($language) {
    $languages = $this->drupal->languageList('language');
    $language = $languages[$language];
    $prefix = $language->prefix;
    $langPathSuffix = substr($prefix, 0, -2) . strtoupper(substr($prefix, -2, 2 ));
    return 'en-US_to_' . $langPathSuffix;
  }

  /**
   * @param string $language
   */
  public function getLanguagePathPartSource($language) {
    $languages = $this->drupal->languageList('language');
    $language = $languages[$language];
    $prefix = $language->prefix;
    return substr($prefix, 0, -2) . strtoupper(substr($prefix, -2, 2 ));
  }

}

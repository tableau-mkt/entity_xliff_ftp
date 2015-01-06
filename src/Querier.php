<?php

/**
 * @file
 * Contains the Querier class for querying world server.
 */

namespace TableauWorldServer;


use TableauWorldServer\Utils\DrupalHandler;

class Querier {

  /**
   * Describes the Drupal variable name representing the source root.
   */
  CONST SOURCEROOTVAR = 'tableau_worldserver_integration_source_root';

  /**
   * @var \Net_SFTP
   */
  protected $client;

  /**
   * @var DrupalHandler
   */
  protected $handler;

  public function __construct(\Net_SFTP $client, DrupalHandler $handler = NULL) {
    // Make sure the SFTP client is connected.
    if (!$client->isConnected()) {
      throw new \Exception('The provided SFTP client must already be connected.');
    }

    // If no DrupalHandler was provided, instantiate a new one.
    if ($handler === NULL) {
      $handler = new DrupalHandler();
    }

    $this->client = $client;
    $this->drupal = $handler;
  }

  /**
   * Returns a list of content that has been translated in WorldServer that is
   * ready for processing in Drupal.
   *
   * @param object[] $langs
   *   An associative array of Drupal language objects, keyed by their language
   *   shortcode. Should match the output of language_list().
   *
   * @return array
   *   A multidimensional array of the following form:
   *   - [drupal_language]: The Drupal language identifier.
   *     - [entity_type]: An array of entity IDs to be processed, keyed by the
   *       type of entity.
   */
  public function getProcessable(array $langs = array()) {
    // If no languages were provided, load them dynamically.
    if ($langs === array()) {
      $langs = $this->drupal->languageList('language');
    }

    // Unset English. @todo Don't be so English-centric.
    unset($langs['en']);

    // Set up invariants.
    $baseDir = $this->drupal->variableGet(self::SOURCEROOTVAR);

    // Iterate through all languages, list files, and format response.
    $response = array();
    foreach ($langs as $targetLang => $lang) {
      $langPath = substr($lang->prefix, 0, -2) . strtoupper(substr($lang->prefix, -2, 2 ));

      foreach ($this->client->rawlist($baseDir . '/' . $langPath) as $file => $attrs) {
        // Check for files whose names match the expected format.
        if ($attrs['type'] === 1 && $entity = $this->parseFilename($file)) {
          $response[$targetLang][$entity['type']][$entity['identifier']] = $entity['identifier'];
        }
      }
    }

    return $response;
  }

  /**
   * Returns the same basic data as Querier::getProcessable(), but returns the
   * data keyed by entity_type[entity_id], as opposed to lang[entity_type].
   *
   * @param object[] $langs
   *   An associative array of Drupal language objects, keyed by their language
   *   shortcode. Should match the output of language_list().
   *
   * @return array
   *   A multidimensional array of the following form:
   *   - [entity_type]: The Drupal entity type.
   *     - [entity_id]: An array of languages that are processable, keyed by the
   *       entity ID.
   */
  public function getProcessableByEntity(array $langs = array()) {
    $response = array();
    foreach ($this->getProcessable($langs) as $lang => $langEntity) {
      foreach ($langEntity as $entityType => $entity) {
        $response[$entityType][key($entity)][$lang] = $lang;
      }
    }
    return $response;
  }

  /**
   * Returns a list of content that has already been processed by Drupal.
   *
   * @param object[] $langs
   *   An associative array of Drupal language objects, keyed by their language
   *   shortcode. Should match the output of language_list().
   *
   * @return array
   */
  public function getProcessed(array $langs = array()) {
    // If no languages were provided, load them dynamically.
    if ($langs === array()) {
      $langs = $this->drupal->languageList('language');
    }

    // Unset English. @todo Don't be so English-centric.
    unset($langs['en']);

    // Set up invariants.
    $baseDir = $this->drupal->variableGet(self::SOURCEROOTVAR);

    // Iterate through all languages, list files, and format response.
    $response = array();
    foreach ($langs as $targetLang => $lang) {
      $langPath = substr($lang->prefix, 0, -2) . strtoupper(substr($lang->prefix, -2, 2 ));

      foreach ($this->client->rawlist($baseDir . '/' . $langPath . '/processed') as $file => $attrs) {
        // Check that this file whose name matches the expected format.
        if ($attrs['type'] === 1 && $entity = $this->parseFilename($file)) {
          $response[$targetLang][$entity['type']][$entity['identifier']] = array(
            'filename' => $attrs['filename'],
            'size' => $attrs['size'],
            'created' => $attrs['atime'],
            'modified' => $attrs['mtime'],
          );
        }
      }
    }

    return $response;
  }

  public function getProcessedByEntity(array $langs = array()) {
    $response = array();
    foreach ($this->getProcessed($langs) as $lang => $langEntity) {
      foreach ($langEntity as $entityType => $fileData) {
        $entityId = key($fileData);
        $response[$entityType][$entityId][$lang] = $fileData[$entityId];
      }
    }
    return $response;
  }

  /**
   * Given a file name (created by MiddleWare::getFilename()), returns all
   * components that went into its creation.
   *
   * @param string $filename
   *   The file name to be parsed.
   *
   * @return array
   *   An associative array of file name parts, keyed by component name. If the
   *   file name does not match the expected format (e.g. it was not generated
   *   by MiddleWare::getFilename()), then an empty array is returned.
   *
   * @see MiddleWare::getFilename()
   */
  public function parseFilename($filename) {
    if (preg_match('/^([a-zA-Z_]+)-(\d+)\.xlf$/', $filename, $matches)) {
      return array(
        'type' => $matches[1],
        'identifier' => $matches[2],
      );
    }

    return array();
  }

}

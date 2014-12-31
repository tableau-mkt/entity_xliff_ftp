<?php

/**
 * @file
 * Contains the DrupalHandler class, which basically provides OO wrappers around
 * Drupal procedural functions for unit-testability.
 */

namespace TableauWorldServer\Utils;


class DrupalHandler {

  /**
   * Returns a persistent variable.
   *
   * @param string $name
   * @param mixed $default
   * @return mixed
   *
   * @see variable_get()
   */
  public function variableGet($name, $default = NULL) {
    return variable_get($name, $default);
  }

  /**
   * Returns a list of installed languages, indexed by the specified key.
   *
   * @param string $field
   * @return object[]
   *
   * @see language_list()
   */
  public function languageList($field = 'language') {
    return language_list($field);
  }

  /**
   * Sets a message to display to the user.
   *
   * @param string $message
   * @param string $type
   * @param bool $repeat
   * @return array|null
   *
   * @see drupal_set_message()
   */
  public function setMessage($message = NULL, $type = 'status', $repeat = TRUE) {
    drupal_set_message($message, $type, $repeat);
  }

  /**
   * Translates a string to the current language or to a given language.
   *
   * @param $string
   * @param array $args
   * @param array $options
   * @return string
   *
   * @see t()
   */
  public function t($string, array $args = array(), array $options = array()) {
    return t($string, $args, $options);
  }

}

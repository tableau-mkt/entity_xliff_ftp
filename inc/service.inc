<?php

/**
 * @file
 * Hooks and functions related to web services exposed by this module.
 */


/**
 * Page callback for a web service endpoint that can be used to trigger syncs
 * from the configured remote FTP server.
 */
function entity_xliff_ftp_sync_from_remote() {
  // Parse the POST request body. If none is provided, default to an array.
  $request = drupal_json_decode(file_get_contents('php://input'));
  $request = ($request ?: array());

  // Allow other modules to alter the remote sync payload.
  drupal_alter('entity_xliff_ftp_remote_sync', $request);

  // Always return JSON.
  $response = array();
  drupal_add_http_header('Content-Type', 'application/json');

  // Ensure we're dealing with a valid request.
  if (!_validate_sync_from_remote_request($request)) {
    print drupal_json_encode((object) $response);
    drupal_exit();
  }

  // Allow this process to run indefinitely.
  set_time_limit(0);

  // If valid, proceed as normal.
  if ($request === array()) {
    try {
      // Process all XLIFFs that can be.
      $response['processed'] = entity_xliff_ftp_process_all();

      // Return 200 on success.
      drupal_add_http_header('Status', '200 OK');
    }
    catch (Exception $e) {
      // Return a 500 on failure.
      drupal_add_http_header('Status', '500 Internal Server Error; ' . $e->getMessage());
    }
  }
  else {
    try {
      $processable = entity_xliff_ftp_parse_sync_from_remote_request_body($request);
      $response['processed'] = entity_xliff_ftp_process_given($processable);

      // Return 200 on success.
      drupal_add_http_header('Status', '200 OK');
    }
    catch (Exception $e) {
      // Return a 500 on failure.
      drupal_add_http_header('Status', '500 Internal Server Error; ' . $e->getMessage());
    }
  }

  print drupal_json_encode((object) $response);
  drupal_exit();
}

/**
 * Returns whether or not the given request conforms to our expectations.
 *
 * @param array $request
 *   The decoded request body.
 *
 * @return bool
 *   TRUE if the request appears valid. FALSE otherwise. If FALSE, HTTP status
 *   code will already be set indicating the form of failure.
 */
function _validate_sync_from_remote_request($request) {
  // If not POST, throw a 405 Method Not Allowed.
  if (strtolower($_SERVER['REQUEST_METHOD']) !== 'post') {
    drupal_add_http_header('Status', '405 Method Not Allowed');
    return FALSE;
  }

  // If cron key is not supplied, throw a 403 Forbidden.
  $expectedKey = variable_Get('cron_key', '');
  $params = drupal_get_query_parameters();
  if (!isset($params['key']) || $params['key'] !== $expectedKey) {
    drupal_add_http_header('Status', '403 Forbidden; missing or invalid key.');
    return FALSE;
  }

  // Check that the request body was formatted properly.
  if (!is_array($request)) {
    drupal_add_http_header('Status', '400 Bad Request; expecting a JSON array');
    return FALSE;
  }

  return TRUE;
}

/**
 * Returns an array suitable for use with entity_xliff_ftp_process() to process
 * the entities in a given payload.
 *
 * @param array $request
 *   The decoded request body.
 *
 * @return array
 *   An array suitable for use in entity_xliff_ftp_process().
 */
function entity_xliff_ftp_parse_sync_from_remote_request_body($request) {
  $parsed_request = array();

  foreach ($request as $sync_bundle) {
    $entity_type = $sync_bundle['entityType'];
    $entity_id = $sync_bundle['entityId'];
    $languages = $sync_bundle['languages'];

    // Add to the parsed request list if we have all the details.
    if ($entity_type && $entity_id && $languages) {
      // Format language array in the way expected downstream.
      $languages = array_combine($languages, $languages);

      // If this element already exists, merge in the languages.
      if (isset($parsed_request[$entity_type][$entity_id])) {
        $parsed_request[$entity_type][$entity_id] = array_merge($parsed_request[$entity_type][$entity_id], $languages);
      }
      // Otherwise, add the languages as new.
      else {
        $parsed_request[$entity_type][$entity_id] = $languages;
      }
    }
  }

  return $parsed_request;
}

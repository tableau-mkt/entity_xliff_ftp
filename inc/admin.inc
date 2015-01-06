<?php

/**
 * @file
 * Functions/callbacks used to administer the World Server Integration module.
 */


/**
 * Primary Drupal settings form callback.
 *
 * @see tableau_worldserver_integration_menu()
 */
function tableau_worldserver_integration_settings($form, &$form_state) {

  $form['sftp_details'] = array(
    '#title' => t('SFTP details'),
    '#type' => 'fieldset',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['sftp_details']['tableau_worldserver_integration_sftp_host'] = array(
    '#title' => t('SFTP hostname'),
    '#description' => t('Used to make an SFTP. Do not include a protocol.'),
    '#type' => 'textfield',
    '#required' => TRUE,
    '#default_value' => variable_get('tableau_worldserver_integration_sftp_host', ''),
  );

  $form['sftp_details']['tableau_worldserver_integration_sftp_un'] = array(
    '#title' => t('SFTP username'),
    '#description' => t('Used when connecting to WorldServer.'),
    '#type' => 'textfield',
    '#required' => TRUE,
    '#default_value' => variable_get('tableau_worldserver_integration_sftp_un', ''),
  );

  $form['sftp_details']['tableau_worldserver_integration_sftp_pw'] = array(
    '#title' => t('SFTP password'),
    '#description' => t('Used when connecting to WorldServer.'),
    '#type' => 'textfield',
    '#required' => TRUE,
    '#default_value' => variable_get('tableau_worldserver_integration_sftp_pw', ''),
  );

  $form['sftp_details']['tableau_worldserver_integration_target_root'] = array(
    '#title' => t('XLIFF target root path'),
    '#description' => t('Used as the root directory when pushing XLIFFs to WorldServer.'),
    '#type' => 'textfield',
    '#required' => TRUE,
    '#default_value' => variable_get('tableau_worldserver_integration_target_root', ''),
  );

  $form['sftp_details']['tableau_worldserver_integration_source_root'] = array(
    '#title' => t('XLIFF source root path'),
    '#description' => t('Used as the root directory when pulling XLIFFs from WorldServer.'),
    '#type' => 'textfield',
    '#required' => TRUE,
    '#default_value' => variable_get('tableau_worldserver_integration_source_root', ''),
  );

  return system_settings_form($form);
}

/**
 * Drupal settings form callback for WorldServer integration overview page.
 *
 * @see tableau_worldserver_integration_menu()
 */
function tableau_worldserver_integration_overview($form, &$form_state) {
  $languages = language_list();
  if (isset($form_state['storage']['confirm_process_action'])) {
    // Define which entities are to be processed (both in storage and in the UI)
    $toBeProceessed = array();
    foreach ($form_state['input']['processable'] as $entityType => $entities) {
      foreach ($entities as $entityId => $shouldBeProcessed) {
        if ($shouldBeProcessed) {
          $langs = $form_state['storage']['processable'][$entityType][$entityId];
          $langs = array_map(function($item) use ($languages) {
            return $languages[$item]->name;
          }, $langs);
          $wrapper = entity_metadata_wrapper($entityType, $entityId);
          $toBeProceessed[] = t('Updates: !count to %label (!langs).', array(
            '!count' => count($langs),
            '%label' => $wrapper->label(),
            '!langs' => implode(', ', $langs),
          ));

          // Store the entities to be processed.
          $targetLangs = $form_state['storage']['processable'][$entityType][$entityId];
          $form_state['storage']['to_be_processed'][$entityType][$entityId] = array_map(function($item) use ($languages) {
            return $languages[$item];
          }, $targetLangs);
        }
      }
    }

    // Print out the entities to be processed.
    $form['to_be_processed'] = array(
      '#theme' => 'item_list',
      '#items' => $toBeProceessed,
      '#title' => t('Projects to be processed'),
      '#type' => 'ul',
      '#attributes' => array(),
      '#suffix' => '<br />',
    );
    $form =  confirm_form($form, t('Are you sure you want to process the following WorldServer projects?'), current_path(), NULL, t('Process'));
    $form['actions']['submit']['#submit'] = array('tableau_worldserver_integration_overview_process_submit');
    return $form;
  }

  drupal_set_title(t('WorldServer overview'));

  $form['project_overview'] = array(
    '#type' => 'vertical_tabs',
    '#prefix' => t('You may also be looking to !configure_integration.', array(
      '!configure_integration' => l(t('configure WorldServer integration'), 'admin/config/services/world-server'),
    )),
  );

  // "Pending" projects.
  $form['pending_overview'] = array(
    '#type' => 'fieldset',
    '#title' => t('Pending projects'),
    '#description' => t('An overview of pending WorldServer/Drupal projects. Each row represents a piece of content that is awaiting Drupal import.'),
    '#group' => 'project_overview',
  );

  // Load in pending/processable projects.
  $form_state['storage']['processable'] = tableau_worldserver_integration_get_processable();

  // Prepare the table.
  $header = array('', t('Label'), t('Type'), t('Bundle'), t('Identifier'), t('Pending languages'));
  $rows = array();
  foreach ($form_state['storage']['processable'] as $entityType => $entityDetails) {
    foreach ($entityDetails as $entityId => $langs) {
      $entity = entity_load_single($entityType, (int) $entityId);
      $wrapper = entity_metadata_wrapper($entityType, $entity);
      $uri = entity_uri($entityType, $entity);
      $langs = array_map(function($item) use ($languages) {
        return $languages[$item]->name;
      }, $langs);

      $checkbox = array(
        '#type' => 'checkbox',
        '#name' => 'processable[' . implode('][', array($entityType, $entityId)) . ']',
      );

      $rows[] = array(
        'checkbox' => drupal_render($checkbox),
        'label' => l($wrapper->label(), $uri['path'], $uri['options']),
        'type' => $wrapper->type(),
        'bundle' => $wrapper->getBundle(),
        'identifier' => $entityId,
        'languages' => implode(', ', $langs),
      );
    }
  }

  // Add the table to be rendered.
  if (count($rows)) {
    $form['pending_overview']['table'] = array(
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    );
  }
  else {
    $form['pending_overview']['table'] = array(
      '#type' => 'markup',
      '#markup' => '<p><em>' . t('There are no pending projects at this time.') . '</em></p>',
    );
  }

  $form['pending_overview']['process'] = array(
    '#type' => 'submit',
    '#value' => t('Process selected projects'),
    '#submit' => array('tableau_worldserver_integration_overview_process_submit'),
    // Disabled by default. Enabled when any one processable item is checked.
    '#disabled' => count($rows) <= 0,
    '#states' => array(
      'enabled' => array(
        ':input[name^="processable"]' => array('checked' => TRUE),
      ),
      'disabled' => array(
        ':input[name^="processable"]' => array('checked' => FALSE),
      ),
    ),
  );

  // "Processed" projects.
  $form['processed_overview'] = array(
    '#type' => 'fieldset',
    '#title' => t('Processed projects'),
    '#description' => t('An overview of processed WorldServer/Drupal XLIFFs.'),
    '#group' => 'project_overview',
  );

  // Load in processed projects.
  $form_state['storage']['processed'] = tableau_worldserver_integration_get_processed();

  // Prepare the table.
  $header = array(t('Label'), t('Language'), t('Created'), t('Processed'));
  $rows = array();

  foreach ($form_state['storage']['processed'] as $entityType => $entityDetails) {
    foreach ($entityDetails as $entityId => $languageDetails) {
      foreach ($languageDetails as $language => $fileDetails) {
        $entity = entity_load_single($entityType, $entityId);
        $wrapper = entity_metadata_wrapper($entityType, $entity);
        $uri = entity_uri($entityType, $entity);
        $created = new DateObject($fileDetails['created']);
        $processed = new DateObject($fileDetails['modified']);

        $rows[$fileDetails['modified']] = array(
          'label' => l($wrapper->label(), $uri['path'], $uri['options']),
          'language' => $languages[$language]->name,
          'created' => date_format_date($created),
          'processed' => date_format_date($processed),
        );
      }
    }
  }

  // Latest ones first.
  krsort($rows);

  // Add the table to be rendered.
  if (count($rows)) {
    $form['processed_overview']['table'] = array(
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    );
  }
  else {
    $form['processed_overview']['table'] = array(
      '#type' => 'markup',
      '#markup' => '<p><em>' . t('There are no processed projects at this time.') . '</em></p>',
    );
  }

  return $form;
}

function tableau_worldserver_integration_overview_process_submit($form, &$form_state) {
  // If the form has not yet been confirmed, force a rebuild w/confirm form.
  if (!isset($form_state['storage']['confirm_process_action'])) {
    $form_state['storage']['confirm_process_action'] = TRUE;
    $form_state['rebuild'] = TRUE;
  }
  // Otherwise, process selected projects.
  else {
    $toBeProcessed = $form_state['storage']['to_be_processed'];
    foreach ($toBeProcessed as $entityType => $entities) {
      foreach ($entities as $entityId => $targetLangs) {
        tableau_worldserver_integration_set_entity_xliffs($entityType, $entityId, $targetLangs);
      }
    }
  }
}

/**
 * @see tableau_worldserver_integration_form_entity_xliff_actions_alter()
 */
function _tableau_worldserver_integration_form_entity_xliff_actions_alter(&$form, &$form_state) {
  // Do not show this on non-English pages for now. Currently, we only support
  // exporting XLIFFs for English. @todo
  $node = menu_get_object('node');
  if ($node->language !== 'en' && $node->language !== LANGUAGE_NONE) {
    return;
  }

  // Create a fieldset space for ourselves.
  $form['worldserver_integration'] = array(
    '#title' => t('WorldServer integration'),
    '#description' => '<p>' . t('Select languages into which you want to translate this page (defaults to all languages).') . '</p>' .
      '<p>' . t('Once selected, click %import. This will generate XLIFF files for the selected languages and push them to WorldServer.', array(
        '%import' => t('Push to WorldServer'),
      )) . '</p>',
    '#type' => 'fieldset',
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
    '#weight' => -5,
  );

  $languages = language_list('language');
  unset($languages[$GLOBALS['language']->language]);
  $form['worldserver_integration']['languages'] = array(
    '#title' => t('Target language(s)'),
    '#type' => 'select',
    '#multiple' => TRUE,
    '#options' => array_map(function($lang) {return $lang->name;}, $languages),
    '#default_value' => array_keys($languages),
  );

  $form['worldserver_integration']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Push to WorldServer'),
    '#submit' => array('tableau_worldserver_integration_push_xliffs'),
  );
}

/**
 * Submit handler for pushing XLIFFs to WorldServer through the UI.
 *
 * @see _tableau_worldserver_integration_form_entity_xliff_actions_alter()
 */
function tableau_worldserver_integration_push_xliffs($form, &$form_state) {
  if (isset($form_state['values']['languages'])) {
    // Filter down to just the selected languages.
    $selectedLangs = $form_state['values']['languages'];
    $langs = array_filter(language_list('language'), function ($lang) use ($selectedLangs) {
      return isset($selectedLangs[$lang->language]) && !empty($selectedLangs[$lang->language]);
    });

    // Get entity type and data.
    $type = $form_state['build_info']['args'][0];
    $entity = $form_state['build_info']['args'][1];

    // Push selected languages to WorldServer.
    tableau_worldserver_integration_put_entity_xliffs($type, $entity, $langs);
  }
}

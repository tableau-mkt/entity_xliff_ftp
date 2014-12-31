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

  return system_settings_form($form);
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

  #echo '<pre>'; print_r($form); die();
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

<?php

namespace Drupal\group_permissions\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class TogglePermissionsDebuggingForm.
 */
class TogglePermissionsDebuggingForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'group_permissions.toggle_permissions_debugging',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'toggle_permissions_debugging_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('group_permissions.toggle_permissions_debugging');

    $form['enable_message_debugging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable message debugging'),
      '#description' => $this->t('Enables permissions debugging via Drupal messages.'),
      '#default_value' => $config->get('enable_message_debugging'),
    ];
    $form['enable_log_debugging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable log debugging'),
      '#description' => $this->t('Enables permissions debugging via Drupal or system logs.'),
      '#default_value' => $config->get('enable_log_debugging'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('group_permissions.toggle_permissions_debugging')
      ->set('enable_message_debugging', $form_state->getValue('enable_message_debugging'))
      ->set('enable_log_debugging', $form_state->getValue('enable_log_debugging'))
      ->save();
  }

}

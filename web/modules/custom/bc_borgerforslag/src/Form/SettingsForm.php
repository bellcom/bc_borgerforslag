<?php

namespace Drupal\bc_borgerforslag\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

final class SettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'bc_borgerforslag_settings';
  }

  protected function getEditableConfigNames(): array {
    return ['bc_borgerforslag.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('bc_borgerforslag.settings');
    $form['municipality'] = ['#type' => 'textfield', '#title' => 'Kommunens navn', '#default_value' => $config->get('municipality'), '#required' => TRUE, '#maxlength' => 100];
    $form['municipality_code'] = ['#type' => 'textfield', '#title' => 'Kommunekode (fire cifre)', '#default_value' => $config->get('municipality_code'), '#required' => TRUE, '#maxlength' => 4, '#pattern' => '[0-9]{4}'];
    foreach (['minimum_age' => ['Minimumsalder', 0, 120], 'threshold' => ['Stemmemål', 1, 1000000], 'voting_days' => ['Afstemningsperiode (dage)', 1, 730]] as $key => [$title, $min, $max]) {
      $form[$key] = ['#type' => 'number', '#title' => $title, '#default_value' => $config->get($key), '#required' => TRUE, '#min' => $min, '#max' => $max, '#step' => 1];
    }
    foreach (['allow_organisations' => 'Tillad forslag fra virksomheder og foreninger', 'local_organisations' => 'Kræv CVR-adresse i kommunen'] as $key => $label) {
      $form[$key] = ['#type' => 'checkbox', '#title' => $label, '#default_value' => $config->get($key)];
    }
    $form['notifications_enabled'] = ['#type'=>'checkbox','#title'=>'Aktivér e-mailnotifikationer (ikke demo)','#default_value'=>$config->get('notifications_enabled')];
    $form['notification_admin_email'] = ['#type'=>'email','#title'=>'Administrativ modtager','#default_value'=>$config->get('notification_admin_email')];
    $form['demo_mode'] = ['#type' => 'checkbox', '#title' => 'Aktivér DEMO-deltagelse (kun isoleret testmiljø)', '#default_value' => $config->get('demo_mode'), '#description' => 'Lokale konti med demo-tilladelsen får en fiktiv identitet. Dette er ikke MitID. Slå aldrig til på produktion.'];
    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    if ($form_state->getValue('demo_mode') && \Drupal\Core\Site\Settings::get('bc_borgerforslag_live_enabled', FALSE)) { $form_state->setErrorByName('demo_mode', 'Demo kan ikke aktiveres i live-tilstand.'); }
    if (!preg_match('/^\d{4}$/D', (string) $form_state->getValue('municipality_code'))) {
      $form_state->setErrorByName('municipality_code', 'Indtast fire cifre.');
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('bc_borgerforslag.settings');
    foreach (['municipality', 'municipality_code'] as $key) {
      $config->set($key, trim((string) $form_state->getValue($key)));
    }
    foreach (['minimum_age', 'threshold', 'voting_days'] as $key) {
      $config->set($key, (int) $form_state->getValue($key));
    }
    foreach (['allow_organisations', 'local_organisations'] as $key) { $config->set($key, (bool) $form_state->getValue($key)); }
    $config->set('notifications_enabled',(bool)$form_state->getValue('notifications_enabled'))->set('notification_admin_email',trim((string)$form_state->getValue('notification_admin_email')));
    $config->set('demo_mode', (bool) $form_state->getValue('demo_mode'))->save();
    parent::submitForm($form, $form_state);
  }

}

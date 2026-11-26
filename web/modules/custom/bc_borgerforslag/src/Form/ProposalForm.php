<?php

namespace Drupal\bc_borgerforslag\Form;

use Drupal\bc_borgerforslag\Identity\Participation;
use Drupal\bc_borgerforslag\ProposalStore;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class ProposalForm extends FormBase {

  public function __construct(protected ProposalStore $store, protected Participation $participation) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('bc_borgerforslag.store'), $container->get('bc_borgerforslag.participation'));
  }

  public function getFormId(): string {
    return 'bc_borgerforslag_create';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $identity = $this->participation->proposer();
    if (!$identity) {
      $form['#cache']['max-age'] = 0;
      $form['notice'] = ['#plain_text' => 'Log ind som privatperson eller virksomhed for at oprette forslag.'];
      $form['login'] = ['#type' => 'link', '#title' => 'Log ind', '#url' => \Drupal\Core\Url::fromUserInput(\Drupal\bc_borgerforslag\Identity\Login::url(\Drupal\Core\Url::fromRoute('bc_borgerforslag.create')))];
      return $form;
    }
    $form['#cache']['max-age'] = 0;
    $form['bc_action_token'] = \Drupal\bc_borgerforslag\Identity\ActionToken::field('create');
    $form['intro'] = ['#plain_text' => 'Dit forslag vurderes af kommunen, før det bliver offentliggjort. Undlad CPR-numre og andre fortrolige personoplysninger.'];
    $form['title'] = ['#type' => 'textfield', '#title' => 'Overskrift', '#required' => TRUE, '#maxlength' => 160];
    $form['body'] = ['#type' => 'textarea', '#title' => 'Beskriv dit forslag', '#description' => 'Hvad ønsker du ændret, og hvorfor er det relevant for kommunen?', '#required' => TRUE, '#rows' => 8];
    $form['author'] = ['#type' => 'item', '#title' => 'Offentligt navn', '#plain_text' => $identity['name']];
    $form['email'] = ['#type'=>'email','#title'=>'Kontakt-e-mail (valgfri, ikke offentlig)','#maxlength'=>254,'#description'=>'Bruges kun til beskeder om dit forslag.'];
    $form['consent'] = ['#type' => 'checkbox', '#title' => 'Jeg har læst reglerne og er indforstået med, at mit navn og forslag offentliggøres efter godkendelse.', '#required' => TRUE];
    $form['actions'] = ['#type' => 'actions'];
    if ($form_state->get('preview')) {
      $form['preview'] = ['#type' => 'item', '#title' => 'Forhåndsvisning', '#plain_text' => (string) $form_state->getValue('title') . ' — ' . (string) $form_state->getValue('body')];
    }
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => 'Forhåndsvis eller opdater', '#submit' => ['::preview'], '#button_type' => 'secondary'];
    if ($form_state->get('preview')) { $form['actions']['send'] = ['#type' => 'submit', '#value' => 'Send til godkendelse', '#button_type' => 'primary']; }
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!\Drupal\bc_borgerforslag\Identity\ActionToken::valid('create', $form_state->getValue('bc_action_token'))) { $form_state->setErrorByName('bc_action_token', 'Sessionen er udløbet. Genindlæs siden.'); }
    if (!$this->participation->proposer()) {
      $form_state->setErrorByName('title', 'Din identitet kunne ikke verificeres.');
    }
    if (mb_strlen(trim((string) $form_state->getValue('title'))) < 5) {
      $form_state->setErrorByName('title', 'Overskriften skal indeholde mindst 5 tegn.');
    }
    $length = mb_strlen(trim((string) $form_state->getValue('body')));
    if ($length < 20 || $length > 10000) {
      $form_state->setErrorByName('body', 'Beskrivelsen skal indeholde 20–10.000 tegn.');
    }
  }

  public function preview(array &$form, FormStateInterface $form_state): void {
    $form_state->set('preview', TRUE)->set('preview_hash', hash('sha256', serialize([$form_state->getValue('title'), $form_state->getValue('body')])))->setRebuild();
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->get('preview_hash') !== hash('sha256', serialize([$form_state->getValue('title'), $form_state->getValue('body')]))) {
      $this->messenger()->addWarning('Forslaget er ændret. Forhåndsvis igen før indsendelse.');
      $form_state->set('preview', FALSE)->setRebuild();
      return;
    }
    $identity = $this->participation->proposer();
    if (!$identity) {
      throw new AccessDeniedHttpException();
    }
    $id = $this->store->create(trim((string) $form_state->getValue('title')), trim((string) $form_state->getValue('body')), $identity['name'], $identity['type'], $identity['subject'], trim((string) $form_state->getValue('email')));
    $this->messenger()->addStatus($this->t('Forslag nr. @id er sendt til godkendelse.', ['@id' => $id]));
    $form_state->setRedirect('bc_borgerforslag.overview');
  }

}

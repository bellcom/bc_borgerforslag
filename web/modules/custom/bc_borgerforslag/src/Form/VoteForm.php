<?php

namespace Drupal\bc_borgerforslag\Form;

use Drupal\bc_borgerforslag\Identity\Participation;
use Drupal\bc_borgerforslag\ProposalStore;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class VoteForm extends FormBase {

  public function __construct(private ProposalStore $store, private Participation $participation) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('bc_borgerforslag.store'), $container->get('bc_borgerforslag.participation'));
  }

  public function getFormId(): string {
    return 'bc_borgerforslag_vote';
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $id = 0): array {
    // The ID comes from the server-side form arguments, not a posted field.
    $form_state->set('proposal_id', $id);
    $form['#cache']['max-age'] = 0;
    $form['bc_action_token'] = \Drupal\bc_borgerforslag\Identity\ActionToken::field('vote:' . $id);
    $form['intro'] = ['#plain_text' => 'Din støtte vises ikke med navn. Du kan kun støtte én gang.'];
    $form['confirm'] = ['#type' => 'checkbox', '#title' => 'Jeg ønsker at støtte dette forslag.', '#required' => TRUE];
    $form['submit'] = ['#type' => 'submit', '#value' => 'Støt forslaget', '#button_type' => 'primary'];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!\Drupal\bc_borgerforslag\Identity\ActionToken::valid('vote:' . (int) $form_state->get('proposal_id'), $form_state->getValue('bc_action_token'))) { $form_state->setErrorByName('bc_action_token', 'Sessionen er udløbet. Genindlæs siden.'); }
    if (!$this->participation->citizen()) {
      $form_state->setErrorByName('confirm', 'Din identitet kunne ikke verificeres.');
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $identity = $this->participation->citizen();
    if (!$identity) {
      throw new AccessDeniedHttpException();
    }
    $id = (int) $form_state->get('proposal_id');
    $result = $this->store->vote($id);
    match ($result) {
      'ok' => $this->messenger()->addStatus('Tak for din støtte.'),
      'duplicate' => $this->messenger()->addWarning('Du har allerede støttet forslaget.'),
      'busy' => $this->messenger()->addWarning('Prøv igen om et øjeblik.'),
      default => $this->messenger()->addWarning('Forslaget er ikke længere åbent for støtte.'),
    };
    $form_state->setRedirect('bc_borgerforslag.detail', ['id' => $id]);
  }

}

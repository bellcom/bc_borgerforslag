<?php

namespace Drupal\bc_borgerforslag\Form;

use Drupal\bc_borgerforslag\ProposalStore;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ModerationForm extends FormBase {

  public function __construct(protected ProposalStore $store) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('bc_borgerforslag.store'));
  }

  public function getFormId(): string {
    return 'bc_borgerforslag_moderation';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#cache']['max-age'] = 0;
    $form['settings'] = Link::createFromRoute('Indstillinger', 'bc_borgerforslag.settings')->toRenderable();
    $form['export'] = Link::createFromRoute('Download oversigt (CSV)', 'bc_borgerforslag.export')->toRenderable();
    $form['intro'] = ['#plain_text' => 'Læs forslaget, kontrollér personoplysninger og kommunalt ansvarsområde før offentliggørelse. Offentlige beslutningsnoter må ikke indeholde fortrolige oplysninger.'];
    foreach ($this->store->all(FALSE) as $row) {
      $id = (int) $row['id'];
      $next = ProposalStore::TRANSITIONS[$row['status']] ?? [];
      $form['p_' . $id] = ['#type' => 'details', '#title' => sprintf('#%d – %s (%s, %d/%d)', $id, $row['title'], ProposalStore::LABELS[$row['status']], $row['votes'], $row['threshold']), '#open' => $row['status'] === 'pending'];
      $item = &$form['p_' . $id];
      $item['author'] = ['#plain_text' => 'Forslagsstiller: ' . $row['author_name']];
      $item['body'] = ['#type' => 'item', '#title' => 'Forslag', '#plain_text' => $row['body']];
      $history = [];
      foreach ($this->store->historyFor($id) as $entry) { $history[] = ['#plain_text' => date('d.m.Y H:i', (int) $entry['created']) . ' – ' . (ProposalStore::LABELS[$entry['from_status']] ?? $entry['from_status']) . ' → ' . (ProposalStore::LABELS[$entry['to_status']] ?? $entry['to_status']) . ' (UID ' . $entry['uid'] . ')']; }
      $item['history'] = ['#theme' => 'item_list', '#title' => 'Statushistorik', '#items' => $history];
      $item['decision'] = ['#type' => 'item', '#title' => 'Seneste beslutningsnote', '#plain_text' => $row['decision']];
      if ($next) {
        $item['#tree'] = TRUE;
        $item['next'] = ['#type' => 'select', '#title' => 'Ny status', '#options' => array_intersect_key(ProposalStore::LABELS, array_flip($next))];
        $item['note'] = ['#type' => 'textarea', '#title' => 'Beslutningsnote (offentlig ved publicerede forslag)', '#default_value' => $row['decision'], '#rows' => 3];
        $item['meeting'] = ['#type'=>'date','#title'=>'Dato for politisk behandling','#default_value'=>$row['meeting_date']];
        $item['agenda'] = ['#type'=>'url','#title'=>'Link til dagsorden eller referat (HTTPS)','#default_value'=>$row['agenda_url'],'#maxlength'=>1024];
        $item['save'] = ['#type' => 'submit', '#value' => 'Gem status for forslag #' . $id, '#name' => 'save_' . $id, '#proposal_id' => $id];
      }
      unset($item);
    }
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $id = (int) ($form_state->getTriggeringElement()['#proposal_id'] ?? 0);
    $values = $form_state->getValue('p_' . $id) ?? [];
    $url = trim((string) ($values['agenda'] ?? ''));
    if ($url !== '' && (!str_starts_with($url, 'https://') || !\Drupal\Component\Utility\UrlHelper::isValid($url, TRUE))) { $form_state->setErrorByName('p_' . $id . '][agenda', 'Anvend et gyldigt HTTPS-link.'); }
    if (($values['next'] ?? '') === 'rejected' && trim((string) ($values['note'] ?? '')) === '') { $form_state->setErrorByName('p_' . $id . '][note', 'Angiv afvisningsbegrundelsen.'); }
    if (!$id || mb_strlen((string) ($values['note'] ?? '')) > 5000) {
      $form_state->setErrorByName('p_' . $id . '][note', 'Beslutningsnoten må højst være 5.000 tegn.');
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $id = (int) ($form_state->getTriggeringElement()['#proposal_id'] ?? 0);
    $values = $form_state->getValue('p_' . $id) ?? [];
    if ($this->store->transition($id, (string) ($values['next'] ?? ''), trim((string) ($values['note'] ?? '')), (int) $this->currentUser()->id(), (string) ($values['meeting'] ?? ''), trim((string) ($values['agenda'] ?? '')))) {
      $this->messenger()->addStatus('Status er opdateret.');
    }
    else {
      $this->messenger()->addError('Status kunne ikke ændres. Genindlæs og prøv igen.');
    }
    $form_state->setRedirect('bc_borgerforslag.admin');
  }

}

<?php

namespace Drupal\bc_borgerforslag\Controller;

use Drupal\bc_borgerforslag\Form\VoteForm;
use Drupal\bc_borgerforslag\Identity\Participation;
use Drupal\bc_borgerforslag\ProposalStore;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProposalController extends ControllerBase {

  public function __construct(private ProposalStore $store, private Participation $participation, private DateFormatterInterface $dates) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('bc_borgerforslag.store'), $container->get('bc_borgerforslag.participation'), $container->get('date.formatter'));
  }

  private function presentation(array $row): array {
    unset($row['owner_key'], $row['contact_email']);
    $row['url'] = Url::fromRoute('bc_borgerforslag.detail', ['id' => $row['id']])->toString();
    $row['status_label'] = ProposalStore::LABELS[$row['status']];
    $row['progress'] = min(100, (int) floor(100 * $row['votes'] / max(1, $row['threshold'])));
    $row['deadline_label'] = $row['deadline'] ? $this->dates->format((int) $row['deadline'], 'custom', 'd.m.Y', 'Europe/Copenhagen') : '';
    return $row;
  }

  private function loginUrl(Url $destination): string {
    return \Drupal\bc_borgerforslag\Identity\Login::url($destination);
  }

  public function overview(): array {
    $config = $this->config('bc_borgerforslag.settings');
    $groups = ['active' => ['title' => 'Aktuelle forslag', 'items' => []], 'political' => ['title' => 'På vej til eller i politisk behandling', 'items' => []], 'closed' => ['title' => 'Tidligere forslag', 'items' => []]];
    foreach ($this->store->all() as $row) {
      $group = in_array($row['status'], ['reached', 'political'], TRUE) ? 'political' : (in_array($row['status'], ['adopted', 'declined'], TRUE) ? 'closed' : $row['status']);
      $groups[$group]['items'][] = $this->presentation($row);
    }
    $create = Url::fromRoute('bc_borgerforslag.create');
    return [
      '#theme' => 'bc_borgerforslag_overview',
      '#municipality' => $config->get('municipality'),
      '#threshold' => $config->get('threshold'),
      '#days' => $config->get('voting_days'),
      '#minimum_age' => $config->get('minimum_age'),
      '#demo' => $config->get('demo_mode'),
      '#mine_url' => Url::fromRoute('bc_borgerforslag.mine')->toString(),
      '#create_url' => $create->toString(),
      '#groups' => $groups,
      '#attached' => ['library' => ['bc_borgerforslag/public']],
      '#cache' => ['max-age' => 0],
    ];
  }

  public function detail(string $id): array {
    $row = $this->store->find((int) $id);
    if (!$row || !in_array($row['status'], ProposalStore::PUBLIC_STATUSES, TRUE)) {
      throw new NotFoundHttpException();
    }
    $form = [];
    $message = '';
    $login = '';
    $identity = $this->participation->citizen();
    if ($identity && $this->store->hasVoted((int) $id, $identity['subject'])) {
      $message = 'Du har allerede støttet dette forslag.';
    }
    elseif ($row['status'] !== 'active') {
      $message = 'Forslaget er ikke længere åbent for støtte.';
    }
    elseif ($identity) {
      $form = $this->formBuilder()->getForm(VoteForm::class, (int) $id);
    }
    elseif ($this->participation->isOrganisation()) {
      $message = 'Virksomheder kan oprette forslag, men kun privatpersoner kan støtte.';
    }
    elseif ($this->currentUser()->isAnonymous()) {
      $message = 'Log ind med en verificeret borgeridentitet for at støtte.';
      $login = $this->loginUrl(Url::fromRoute('bc_borgerforslag.detail', ['id' => $id]));
    }
    else {
      $message = 'Din identitet, alder eller bopæl er ikke verificeret.';
    }
    return [
      '#theme' => 'bc_borgerforslag_detail',
      '#proposal' => $this->presentation($row),
      '#vote_form' => $form,
      '#message' => $message,
      '#login_url' => $login,
      '#overview_url' => Url::fromRoute('bc_borgerforslag.overview')->toString(),
      '#demo' => $this->config('bc_borgerforslag.settings')->get('demo_mode'),
      '#attached' => ['library' => ['bc_borgerforslag/public']],
      '#cache' => ['max-age' => 0],
    ];
  }

}

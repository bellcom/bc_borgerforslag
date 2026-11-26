<?php

namespace Drupal\bc_borgerforslag;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;

/**
 * Database access and state transitions for proposals.
 */
final class ProposalStore {

  public const PUBLIC_STATUSES = ['active', 'reached', 'political', 'closed', 'adopted', 'declined'];
  public const LABELS = ['pending' => 'Afventer godkendelse', 'active' => 'Åben for støtte', 'reached' => 'Stemmemål nået', 'political' => 'Politisk behandling', 'closed' => 'Afsluttet', 'adopted' => 'Vedtaget', 'declined' => 'Politisk afvist', 'rejected' => 'Afvist'];
  public const TRANSITIONS = ['pending' => ['active', 'rejected'], 'active' => ['closed'], 'reached' => ['political', 'closed'], 'political' => ['adopted', 'declined', 'closed'], 'adopted' => [], 'declined' => [], 'closed' => [], 'rejected' => []];

  public function __construct(
    private Connection $db,
    private LockBackendInterface $lock,
    private TimeInterface $time,
    private ConfigFactoryInterface $configFactory,
    private LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  public function find(int $id): ?array {
    $row = $this->db->select('bc_borgerforslag_proposal', 'p')->fields('p')->condition('id', $id)->execute()->fetchAssoc();
    return $row ? $this->decorate($row) : NULL;
  }

  public function all(bool $public = TRUE): array {
    $query = $this->db->select('bc_borgerforslag_proposal', 'p')->fields('p')->orderBy('created', 'DESC')->orderBy('id', 'DESC');
    if ($public) {
      $query->condition('status', self::PUBLIC_STATUSES, 'IN');
    }
    $rows = [];
    $result = $query->execute();
    while ($row = $result->fetchAssoc()) {
      $rows[] = $row;
    }
    return array_map($this->decorate(...), $rows);
  }

  private function decorate(array $row): array {
    $row['votes'] = $this->count((int) $row['id']);
    if ($row['status'] === 'active' && (int) $row['deadline'] <= $this->time->getCurrentTime()) {
      $row['status'] = 'closed';
    }
    return $row;
  }

  private function count(int $id): int {
    return (int) $this->db->select('bc_borgerforslag_vote', 'v')->condition('proposal_id', $id)->countQuery()->execute()->fetchField();
  }

  public function create(string $title, string $body, string $name, string $type = 'person', string $subject = '', string $email = ''): int {
    $config = $this->configFactory->get('bc_borgerforslag.settings');
    $id = (int) $this->db->insert('bc_borgerforslag_proposal')->fields([
      'title' => $title, 'body' => $body, 'author_name' => mb_substr($name, 0, 200),
      'owner_key' => $subject !== '' ? $this->ownerKey($subject) : '', 'contact_email' => $email,
      'author_type' => $type, 'status' => 'pending', 'created' => $this->time->getCurrentTime(),
      'threshold' => max(1, (int) $config->get('threshold')),
      'voting_days' => max(1, (int) $config->get('voting_days')),
      'decision' => '',
    ])->execute();
    $this->notify($id, 'created');
    return $id;
  }

  private function ownerKey(string $subject): string {
    return hash_hmac('sha256', 'bc_borgerforslag:owner:v1:' . $subject, Settings::getHashSalt());
  }
  public function own(string $subject): array {
    $rows = $this->db->select('bc_borgerforslag_proposal','p')->fields('p')->condition('owner_key',$this->ownerKey($subject))->orderBy('created','DESC')->execute()->fetchAll(\Drupal\Core\Database\Statement\FetchAs::Associative);
    return array_map($this->decorate(...), $rows);
  }
  private function notify(int $id, string $event): void {
    // Queue only references; no message or raw identifier is written to queue.
    if (!$this->configFactory->get('bc_borgerforslag.settings')->get('notifications_enabled') || $this->configFactory->get('bc_borgerforslag.settings')->get('demo_mode') || \Drupal::state()->get('bc_borgerforslag.demo_used', FALSE)) { return; }
    try { \Drupal::queue('bc_borgerforslag_mail')->createItem(['id'=>$id,'event'=>$event]); } catch (\Throwable) { $this->loggerFactory->get('bc_borgerforslag')->warning('Kunne ikke sætte notifikation i kø for forslag @id.', ['@id'=>$id]); }
  }
  private function history(int $id, string $from, string $to, int $uid): void {
    $this->db->insert('bc_borgerforslag_history')->fields(['proposal_id'=>$id,'created'=>$this->time->getCurrentTime(),'uid'=>$uid,'from_status'=>$from,'to_status'=>$to])->execute();
  }
  public function historyFor(int $id): array {
    return $this->db->select('bc_borgerforslag_history','h')->fields('h')->condition('proposal_id',$id)->orderBy('id','DESC')->execute()->fetchAll(\Drupal\Core\Database\Statement\FetchAs::Associative);
  }
  private function voterKey(int $id, string $subject): string {
    // Proposal-specific HMAC avoids linking a person's votes across proposals.
    return hash_hmac('sha256', 'bc_borgerforslag:v1:' . $id . ':' . $subject, Settings::getHashSalt());
  }

  public function hasVoted(int $id, string $subject): bool {
    return (bool) $this->db->select('bc_borgerforslag_vote', 'v')->fields('v', ['proposal_id'])->condition('proposal_id', $id)->condition('voter_key', $this->voterKey($id, $subject))->execute()->fetchField();
  }

  public function vote(int $id): string {
    $identity = \Drupal::service('bc_borgerforslag.participation')->citizen();
    if (!$identity) { return 'denied'; }
    return $this->recordVote($id, $identity['subject']);
  }
  public function seedVote(int $id, string $subject): string {
    if (!$this->configFactory->get('bc_borgerforslag.settings')->get('demo_mode') || Settings::get('bc_borgerforslag_live_enabled', FALSE) || !\Drupal::state()->get('bc_borgerforslag.demo_used', FALSE) || !str_starts_with($subject, 'fixture:')) { return 'denied'; }
    return $this->recordVote($id, $subject);
  }
  private function recordVote(int $id, string $subject): string {
    $key = 'bc_borgerforslag:' . $id;
    if (!$this->lock->acquire($key, 30.0)) {
      return 'busy';
    }
    try {
      $row = $this->find($id);
      if (!$row || $row['status'] !== 'active') {
        return 'closed';
      }
      if ($this->hasVoted($id, $subject)) {
        return 'duplicate';
      }
      $transaction = $this->db->startTransaction();
      try {
        $now = $this->time->getCurrentTime();
        if ((int) $row['deadline'] <= $now) {
          return 'closed';
        }
        $this->db->insert('bc_borgerforslag_vote')->fields(['proposal_id' => $id, 'voter_key' => $this->voterKey($id, $subject), 'created' => $now])->execute();
        if ($this->count($id) >= (int) $row['threshold']) {
          $this->db->update('bc_borgerforslag_proposal')->fields(['status' => 'reached'])->condition('id', $id)->execute();
          $this->history($id, 'active', 'reached', 0);
        }
        unset($transaction);
        if ($this->count($id) >= (int) $row['threshold']) { $this->notify($id, 'reached'); }
        return 'ok';
      }
      catch (\Exception $e) {
        $transaction->rollBack();
        throw $e;
      }
    }
    finally {
      $this->lock->release($key);
    }
  }

  public function transition(int $id, string $next, string $decision, int $uid, string $meeting = '', string $agenda = ''): bool {
    $key = 'bc_borgerforslag:' . $id;
    if (!$this->lock->acquire($key, 30.0)) {
      return FALSE;
    }
    try {
      $row = $this->find($id);
      if (!$row || !in_array($next, self::TRANSITIONS[$row['status']] ?? [], TRUE)) {
        return FALSE;
      }
      $fields = ['status' => $next, 'decision' => $decision, 'meeting_date'=>$meeting, 'agenda_url'=>$agenda];
      if ($next === 'active') {
        $now = $this->time->getCurrentTime();
        $fields['published'] = $now;
        $fields['deadline'] = $now + 86400 * (int) $row['voting_days'];
      }
      $transaction = $this->db->startTransaction();
      try {
        $changed = $this->db->update('bc_borgerforslag_proposal')->fields($fields)->condition('id', $id)->condition('status', $row['status'])->execute();
        if (!$changed) { return FALSE; }
        $this->history($id, $row['status'], $next, $uid);
        unset($transaction);
      } catch (\Throwable $e) { $transaction->rollBack(); throw $e; }
      $this->notify($id, $next);
      $this->loggerFactory->get('bc_borgerforslag')->notice('Proposal @id: @from -> @to by user @uid.', ['@id' => $id, '@from' => $row['status'], '@to' => $next, '@uid' => $uid]);
      return TRUE;
    }
    finally {
      $this->lock->release($key);
    }
  }

  public function expire(): void {
    $ids = $this->db->select('bc_borgerforslag_proposal', 'p')->fields('p', ['id'])->condition('status', 'active')->condition('deadline', $this->time->getCurrentTime(), '<=')->range(0, 100)->execute()->fetchCol();
    foreach ($ids as $id) {
      $key = 'bc_borgerforslag:' . $id;
      if ($this->lock->acquire($key, 30.0)) {
        try {
          $transaction = $this->db->startTransaction();
          try {
            $changed = $this->db->update('bc_borgerforslag_proposal')->fields(['status' => 'closed'])->condition('id', $id)->condition('status', 'active')->condition('deadline', $this->time->getCurrentTime(), '<=')->execute();
            if ($changed) { $this->history((int) $id, 'active', 'closed', 0); }
            unset($transaction);
            if ($changed) { $this->notify((int) $id, 'expired'); }
          } catch (\Throwable $e) { $transaction->rollBack(); throw $e; }
        }
        finally {
          $this->lock->release($key);
        }
      }
    }
  }

}

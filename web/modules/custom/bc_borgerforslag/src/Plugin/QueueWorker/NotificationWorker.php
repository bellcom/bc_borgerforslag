<?php
namespace Drupal\bc_borgerforslag\Plugin\QueueWorker;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Queue\QueueWorkerBase;
/** Sends generic status notifications through the configured Drupal mail system. */
#[QueueWorker(id: 'bc_borgerforslag_mail', title: new TranslatableMarkup('Borgerforslag status notification'), cron: ['time'=>30])]
final class NotificationWorker extends QueueWorkerBase {
  public function processItem($data): void {
    $config=\Drupal::config('bc_borgerforslag.settings');
    if (!$config->get('notifications_enabled') || $config->get('demo_mode') || \Drupal::state()->get('bc_borgerforslag.demo_used',FALSE)) { return; }
    $row=\Drupal::service('bc_borgerforslag.store')->find((int) $data['id']);
    if (!$row) { return; }
    $event=(string)$data['event'];
    $recipients=[$row['contact_email']];
    if (in_array($event,['created','reached'],TRUE)) { $recipients[]=$config->get('notification_admin_email'); }
    foreach (array_unique($recipients) as $to) {
      if (!is_string($to) || !filter_var($to,FILTER_VALIDATE_EMAIL)) { continue; }
      $result=\Drupal::service('plugin.manager.mail')->mail('bc_borgerforslag','status',$to,'da',['id'=>(int)$row['id'],'event'=>$event],NULL,TRUE);
      if (empty($result['result'])) { \Drupal::logger('bc_borgerforslag')->warning('Notifikation fejlede for forslag @id. Kontrollér mailtransporten.', ['@id'=>$row['id']]); }
    }
  }
}

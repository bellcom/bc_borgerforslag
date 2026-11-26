<?php

namespace Drupal\bc_borgerforslag\Identity;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Local demo accounts only. No real identity verification is performed.
 */
final class DemoIdentity implements IdentityInterface {

  public function __construct(
    private AccountProxyInterface $account,
    private ConfigFactoryInterface $configFactory,
  ) {}

  public function verifiedIdentity(): ?array {
    $config = $this->configFactory->get('bc_borgerforslag.settings');
    if (\Drupal\Core\Site\Settings::get('bc_borgerforslag_live_enabled', FALSE)) {
      return (new Os2Identity())->verifiedIdentity();
    }
    if (!$config->get('demo_mode') || !$this->account->isAuthenticated() || !$this->account->hasPermission('participate in bc borgerforslag demo')) {
      return NULL;
    }
    \Drupal::state()->set('bc_borgerforslag.demo_used', TRUE);
    if ($this->account->hasPermission('participate as bc borgerforslag demo organisation') && !$this->account->hasPermission('participate in bc borgerforslag demo person')) {
      return ['type' => 'organisation', 'subject' => 'demo-org:' . $this->account->id(), 'name' => 'Fiktiv virksomhed ApS', 'municipality' => (string) $config->get('municipality_code')];
    }
    return [
      'type' => 'person',
      'subject' => 'demo:' . $this->account->id(),
      'name' => 'Demoborger ' . $this->account->id(),
      'municipality' => (string) $config->get('municipality_code'),
      'age' => 30,
    ];
  }

}

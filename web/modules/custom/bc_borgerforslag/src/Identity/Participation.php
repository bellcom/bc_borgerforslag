<?php

namespace Drupal\bc_borgerforslag\Identity;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Central verification of the currently authenticated citizen.
 */
final class Participation {

  public function __construct(private IdentityInterface $identity, private ConfigFactoryInterface $configFactory) {}

  private bool $loaded = FALSE;
  private ?array $cached = NULL;
  private function identity(): ?array {
    if (!$this->loaded) { $this->cached = $this->identity->verifiedIdentity(); $this->loaded = TRUE; }
    return $this->cached;
  }
  public function proposer(): ?array {
    $config = $this->configFactory->get('bc_borgerforslag.settings');
    $i = $this->identity();
    return Eligibility::canPropose($i, (string) $config->get('municipality_code'), (int) $config->get('minimum_age'), (bool) $config->get('allow_organisations'), (bool) $config->get('local_organisations')) ? $i : NULL;
  }
  public function isOrganisation(): bool { return ($this->identity()['type'] ?? NULL) === 'organisation'; }
  public function citizen(): ?array {
    $config = $this->configFactory->get('bc_borgerforslag.settings');
    $identity = $this->identity();
    return Eligibility::allows($identity, (string) $config->get('municipality_code'), (int) $config->get('minimum_age')) ? $identity : NULL;
  }

}

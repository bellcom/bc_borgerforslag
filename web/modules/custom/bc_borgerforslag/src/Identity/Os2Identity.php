<?php
namespace Drupal\bc_borgerforslag\Identity;

use Drupal\Core\Site\Settings;
use Drupal\os2web_datalookup\Plugin\os2web\DataLookup\ServiceplatformenCPRExtended;

/** Delegates authentication and register lookups to existing OS2web modules. */
final class Os2Identity implements IdentityInterface {
  public function verifiedIdentity(): ?array {
    if (!Settings::get('bc_borgerforslag_live_enabled', FALSE) || \Drupal::state()->get('bc_borgerforslag.demo_used', FALSE) || \Drupal::config('bc_borgerforslag.settings')->get('demo_mode')) {
      return NULL;
    }
    try {
      $provider = \Drupal::service('os2web_nemlogin.auth_provider')->getActivePlugin();
      if (!$provider->isInitialized() || !$provider->isAuthenticated() || ($provider->getConfiguration()['fetch_once'] ?? FALSE)) {
        return NULL;
      }
      // Require a fresh provider session. No Drupal user fields or POST claims.
      $age = time() - (int) $provider->getSessionInitialized();
      if ($age < 0 || $age > 900) {
        return NULL;
      }
      $manager = \Drupal::service('plugin.manager.os2web_datalookup');
      if ($provider->isAuthenticatedCompany()) {
        // Explicit deployment approval of the provider's organisation context.
        if (!Settings::get('bc_borgerforslag_company_context_verified', FALSE)) {
          return NULL;
        }
        $cvr = (string) $provider->fetchValue('cvr');
        if (!preg_match('/^[0-9]{8}$/D', $cvr)) { return NULL; }
        $result = $manager->createDefaultInstanceByGroup('cvr_lookup')->lookup($cvr);
        if (!$result->isSuccessful() || $result->getCvr() !== $cvr) { return NULL; }
        return ['type' => 'organisation', 'subject' => 'cvr:' . $cvr, 'name' => $result->getName(), 'municipality' => str_pad($result->getMunicipalityCode(), 4, '0', STR_PAD_LEFT), 'cvr' => $cvr];
      }
      if (!$provider->isAuthenticatedPerson()) { return NULL; }
      $cpr = str_replace('-', '', (string) $provider->fetchValue('cpr'));
      if (!preg_match('/^[0-9]{10}$/D', $cpr)) { return NULL; }
      $plugin = $manager->createDefaultInstanceByGroup('cpr_lookup');
      // Only the inspected extended plugin supports explicit no-test-replacement.
      if (!$plugin instanceof ServiceplatformenCPRExtended) { return NULL; }
      $result = $plugin->lookup($cpr, FALSE, FALSE);
      if (!$result->isSuccessful() || !$result->isAlive() || str_replace('-', '', $result->getCpr()) !== $cpr) { return NULL; }
      $birth = $result->getBirthDate();
      $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Copenhagen'));
      if ($birth > $now) { return NULL; }
      return ['type' => 'person', 'subject' => 'cpr:' . $cpr, 'name' => $result->isNameAddressProtected() ? 'Navnebeskyttet borger' : $result->getName(), 'municipality' => str_pad($result->getMunicipalityCode(), 4, '0', STR_PAD_LEFT), 'age' => $birth->diff($now)->y];
    }
    catch (\Throwable) {
      // Never log tokens, CPR or register responses.
      \Drupal::logger('bc_borgerforslag')->warning('Identitets- eller registerverifikation mislykkedes.');
      return NULL;
    }
  }
}

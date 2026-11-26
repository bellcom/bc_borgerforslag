<?php
namespace Drupal\bc_borgerforslag\Identity;
use Drupal\Core\Url;
use Drupal\Core\Site\Settings;
final class Login {
  public static function url(Url $destination): string {
    if (Settings::get('bc_borgerforslag_live_enabled', FALSE) && \Drupal::moduleHandler()->moduleExists('os2web_nemlogin')) {
      return Url::fromRoute('os2web_nemlogin.login', [], ['query' => ['destination' => $destination->toString()]])->toString();
    }
    return Url::fromRoute('user.login', [], ['query' => ['destination' => $destination->toString()]])->toString();
  }
}

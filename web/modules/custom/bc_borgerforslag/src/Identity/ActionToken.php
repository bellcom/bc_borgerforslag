<?php
namespace Drupal\bc_borgerforslag\Identity;
/** Session-bound form token for external logins without a Drupal account. */
final class ActionToken {
  public static function field(string $action): array {
    $session = \Drupal::request()->getSession();
    $token = $session->get('bc_borgerforslag.action_token');
    if (!is_string($token)) { $token = bin2hex(random_bytes(32)); $session->set('bc_borgerforslag.action_token', $token); }
    return ['#type'=>'hidden', '#default_value'=>hash_hmac('sha256', $action, $token)];
  }
  public static function valid(string $action, mixed $value): bool {
    $token = \Drupal::request()->getSession()->get('bc_borgerforslag.action_token');
    return is_string($token) && is_string($value) && hash_equals(hash_hmac('sha256', $action, $token), $value);
  }
}

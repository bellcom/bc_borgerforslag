<?php
namespace Drupal\bc_borgerforslag\Identity;
final class Eligibility {
  private static function valid(?array $i): bool {
    return $i !== NULL && is_string($i['subject'] ?? NULL) && $i['subject'] !== '' && is_string($i['name'] ?? NULL) && trim($i['name']) !== '';
  }
  public static function allows(?array $i, string $municipality, int $minimumAge): bool {
    return self::valid($i) && ($i['type'] ?? NULL) === 'person' && ($i['municipality'] ?? NULL) === $municipality && is_int($i['age'] ?? NULL) && $i['age'] >= $minimumAge;
  }
  public static function canPropose(?array $i, string $municipality, int $minimumAge, bool $organisations, bool $local): bool {
    if (self::allows($i, $municipality, $minimumAge)) { return TRUE; }
    return $organisations && self::valid($i) && ($i['type'] ?? NULL) === 'organisation' && (!$local || ($i['municipality'] ?? NULL) === $municipality);
  }
}

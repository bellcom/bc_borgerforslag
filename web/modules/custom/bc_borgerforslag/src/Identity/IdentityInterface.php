<?php

namespace Drupal\bc_borgerforslag\Identity;

/**
 * Trust boundary: only a server-side, authenticated identity provider may implement this.
 */
interface IdentityInterface {

  /**
   * Return verified identity and eligibility, or NULL (fail closed).
   *
   * @return array{type: string, subject: string, name: string, municipality: string, age?: int, cvr?: string}|null
   *   Stable canonical person identifier, public name, municipality and age.
   *   Never accept values from request parameters or unverified user fields.
   */
  public function verifiedIdentity(): ?array;

}

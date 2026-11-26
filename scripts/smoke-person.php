<?php
$store=\Drupal::service('bc_borgerforslag.store');
$matches=\Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name'=>'demo_borger1']);
$person=reset($matches);
if (!$person) { throw new RuntimeException('Person fixture missing'); }
$account=\Drupal::service('account_switcher');
$account->switchTo($person);
try {
  $id=$store->create('Integrationstest','Dette er en midlertidig test.','Fiktiv borger');
  if (!$store->transition($id,'active','',0) || $store->vote($id)!=='ok' || $store->vote($id)!=='duplicate' || $store->find($id)['votes']!==1) { throw new RuntimeException('Person vote or duplicate control failed'); }
} finally { $account->switchBack(); }
echo "Person voting and duplicate control OK\n";

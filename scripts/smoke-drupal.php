<?php
$store=\Drupal::service('bc_borgerforslag.store');
$rows=$store->all(FALSE);
if (count($rows)!==6 || count($store->all())!==5) { throw new RuntimeException('Fixtures missing'); }
$active=array_values(array_filter($rows,fn($r)=>$r['status']==='active'))[0];
$account=\Drupal::service('account_switcher');
$users=\Drupal::entityTypeManager()->getStorage('user');
$matches=$users->loadByProperties(['name'=>'demo_virksomhed']);
$org=reset($matches);
if (!$org) { throw new RuntimeException('Organisation fixture missing'); }
$account->switchTo($org);
try { if ($store->vote((int)$active['id'])!=='denied') { throw new RuntimeException('Organisation could vote'); } } finally { $account->switchBack(); }
echo "Drupal fixture and organisation vote rejection OK\n";

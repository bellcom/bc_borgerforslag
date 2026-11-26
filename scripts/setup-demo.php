<?php
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\Core\Site\Settings;
if (Settings::get('bc_borgerforslag_live_enabled', FALSE) || \Drupal::service('bc_borgerforslag.store')->all(FALSE)) { throw new RuntimeException('Kun en tom, isoleret demo.'); }
foreach (['bc_demo_person'=>['participate in bc borgerforslag demo person'],'bc_demo_organisation'=>['participate as bc borgerforslag demo organisation']] as $id=>$perms) {
  if (Role::load($id)) { throw new RuntimeException('Demo role already exists.'); }
  $role = Role::create(['id'=>$id,'label'=>$id]);
  foreach (array_merge(['access content','participate in bc borgerforslag demo'],$perms) as $perm) { $role->grantPermission($perm); }
  $role->save();
}
foreach (['demo_borger1'=>'bc_demo_person','demo_borger2'=>'bc_demo_person','demo_virksomhed'=>'bc_demo_organisation'] as $name=>$role) {
  $pass = bin2hex(random_bytes(16));
  $user = User::create(['name'=>$name,'mail'=>$name.'@example.invalid','status'=>1,'pass'=>$pass,'roles'=>[$role]]);
  $user->save();
  echo "$name: $pass\n";
}
\Drupal::configFactory()->getEditable('bc_borgerforslag.settings')->set('demo_mode',TRUE)->save();
require DRUPAL_ROOT.'/modules/custom/bc_borgerforslag/scripts/seed-demo.php';

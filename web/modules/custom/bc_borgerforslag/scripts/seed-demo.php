<?php

/**
 * Run only via Drush on a disposable demonstration site.
 */
$config = \Drupal::config('bc_borgerforslag.settings');
$store = \Drupal::service('bc_borgerforslag.store');
if (\Drupal\Core\Site\Settings::get('bc_borgerforslag_live_enabled', FALSE) || !$config->get('demo_mode') || $store->all(FALSE) || (int) $config->get('threshold') !== 250) {
  throw new \RuntimeException('Kræver tom demo med aktiveret demotilstand og stemmemål 250.');
}
$fixtures = [
  ['Flere sikre cykelstier til kommunens skoler', 'Vi foreslår bedre og mere sikre skoleveje for kommunens børn. Kommunen bør prioritere sammenhængende cykelstier ved skolerne.', 187, 'active'],
  ['Mere biodiversitet på kommunale grønne arealer', 'Lad flere grønne områder udvikle sig til naturlige levesteder. Forslaget kan bidrage til større variation i kommunens natur.', 96, 'active'],
  ['Bedre busforbindelser mellem landsbyerne', 'En bedre kollektiv trafik skal gøre det nemmere at komme rundt, især for unge og ældre uden bil.', 42, 'active'],
  ['Flere offentlige drikkevandsposter', 'Etabler drikkevandsposter i bymidten og ved de populære stier.', 250, 'political'],
  ['Udvidet åbningstid på biblioteket', 'Undersøg mulighederne for mere fleksible åbningstider på biblioteket.', 74, 'closed'],
  ['Flere bænke langs vandrestierne', 'Undersøg, hvor der er behov for flere bænke langs vandrestierne.', 0, 'pending'],
];
\Drupal::state()->set('bc_borgerforslag.demo_used', TRUE);
foreach ($fixtures as $i => [$title, $body, $votes, $status]) {
  $id = $store->create($title, $body, ($i === 2 ? 'Fiktiv virksomhed ApS' : 'Fiktiv demoborger ' . ($i + 1)), ($i === 2 ? 'organisation' : 'person'));
  if ($status === 'pending') {
    continue;
  }
  if (!$store->transition($id, 'active', '', 0)) {
    throw new \RuntimeException('Demo-godkendelse fejlede.');
  }
  for ($j = 0; $j < $votes; $j++) {
    if ($store->seedVote($id, 'fixture:' . $j) !== 'ok') {
      throw new \RuntimeException('Demo-stemme fejlede.');
    }
  }
  if ($status !== 'active' && !$store->transition($id, $status, 'Fiktiv beslutningsnote til demonstration.', 0)) {
    throw new \RuntimeException('Demo-statusændring fejlede.');
  }
}
print "6 fiktive forslag oprettet.\n";

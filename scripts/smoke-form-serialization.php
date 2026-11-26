<?php
// Regression: Drupal Form API serializes cached form callbacks across requests.
use Drupal\bc_borgerforslag\Form\ProposalForm;
use Drupal\bc_borgerforslag\Form\VoteForm;
use Drupal\bc_borgerforslag\Form\ModerationForm;

$container = \Drupal::getContainer();
foreach ([
  ProposalForm::class => ['store' => 'bc_borgerforslag.store', 'participation' => 'bc_borgerforslag.participation'],
  VoteForm::class => ['store' => 'bc_borgerforslag.store', 'participation' => 'bc_borgerforslag.participation'],
  ModerationForm::class => ['store' => 'bc_borgerforslag.store'],
] as $class => $services) {
  $original = $class::create($container);
  $restored = unserialize(serialize($original));
  foreach ($services as $name => $id) {
    $property = new ReflectionProperty($class, $name);
    if (!$property->isInitialized($restored) || $property->getValue($restored) !== $container->get($id)) {
      throw new RuntimeException("$class::$name was not restored from the container.");
    }
  }
  echo "$class: OK\n";
}
echo "All three form serialization checks passed.\n";

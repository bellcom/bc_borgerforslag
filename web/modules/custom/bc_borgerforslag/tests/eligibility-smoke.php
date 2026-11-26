<?php

require __DIR__ . '/../src/Identity/Eligibility.php';
use Drupal\bc_borgerforslag\Identity\Eligibility;
$identity = ['type' => 'person', 'subject' => 'example:123', 'name' => 'Testperson', 'municipality' => '0706', 'age' => 15];
$cases = [
  [$identity, TRUE],
  [NULL, FALSE],
  [[], FALSE],
  [array_replace($identity, ['subject' => '']), FALSE],
  [array_replace($identity, ['name' => ' ']), FALSE],
  [array_replace($identity, ['municipality' => '0751']), FALSE],
  [array_replace($identity, ['age' => 14]), FALSE],
  [array_replace($identity, ['age' => '18']), FALSE],
  [array_replace($identity, ['age' => 18]), TRUE],
];
foreach ($cases as $i => [$candidate, $expected]) {
  if (Eligibility::allows($candidate, '0706', 15) !== $expected) {
    throw new RuntimeException('Test ' . ($i + 1) . ' failed.');
  }
}
print count($cases) . " eligibility checks passed.\n";

$org = ['type'=>'organisation','subject'=>'demo:org','name'=>'Demo ApS','municipality'=>'0706','age'=>40];
if (Eligibility::allows($org, '0706', 15) || !Eligibility::canPropose($org, '0706', 15, TRUE, TRUE) || Eligibility::canPropose($org, '0706', 15, FALSE, TRUE) || Eligibility::canPropose($org, '0751', 15, TRUE, TRUE) || !Eligibility::canPropose($org, '0751', 15, TRUE, FALSE) || Eligibility::allows(array_diff_key($identity, ['type'=>TRUE]), '0706', 15)) { throw new RuntimeException('Organisation policy failed'); }
print "6 organisation checks passed.\n";

<?php
namespace Drupal\bc_borgerforslag\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\bc_borgerforslag\Identity\Login;
use Drupal\bc_borgerforslag\ProposalStore;
final class MyProposalsController extends ControllerBase {
  public function overview(): array {
    $identity=\Drupal::service('bc_borgerforslag.participation')->proposer();
    $build=['#cache'=>['max-age'=>0]];
    if (!$identity) {
      $build['login']=['#type'=>'link','#title'=>'Log ind for at se dine forslag','#url'=>Url::fromUserInput(Login::url(Url::fromRoute('bc_borgerforslag.mine')))];
      return $build;
    }
    $rows=[];
    foreach (\Drupal::service('bc_borgerforslag.store')->own($identity['subject']) as $row) {
      $title=['#plain_text'=>$row['title']];
      if (in_array($row['status'],ProposalStore::PUBLIC_STATUSES,TRUE)) { $title=['#type'=>'link','#title'=>$row['title'],'#url'=>Url::fromRoute('bc_borgerforslag.detail',['id'=>$row['id']])]; }
      $rows[]=[['data'=>$title], ProposalStore::LABELS[$row['status']], (string)$row['votes'], ['data'=>['#plain_text'=>$row['decision']]]];
    }
    $build['table']=['#type'=>'table','#header'=>['Forslag','Status','Støtter','Kommunens besked'],'#rows'=>$rows,'#empty'=>'Du har endnu ikke indsendt forslag.'];
    return $build;
  }
}

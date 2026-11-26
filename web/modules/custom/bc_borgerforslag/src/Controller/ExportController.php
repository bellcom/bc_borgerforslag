<?php
namespace Drupal\bc_borgerforslag\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
final class ExportController {
  public function csv(): StreamedResponse {
    return new StreamedResponse(static function (): void {
      $out = fopen('php://output', 'w');
      fputcsv($out, ['ID','Titel','Aktørtype','Status','Støtter','Stemmemål'], ';', '"', '');
      foreach (\Drupal::service('bc_borgerforslag.store')->all(FALSE) as $row) {
        $title = (string) $row['title'];
        if (preg_match('/^[\s]*[=+@-]/u', $title) || preg_match('/^[\x00-\x1f]/', $title)) { $title = "'" . $title; }
        fputcsv($out, [$row['id'],$title,$row['author_type'],$row['status'],$row['votes'],$row['threshold']], ';', '"', '');
      }
      fclose($out);
    }, 200, ['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="borgerforslag.csv"','Cache-Control'=>'private, no-store']);
  }
}

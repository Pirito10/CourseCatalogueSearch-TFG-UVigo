<?php

namespace Drupal\dacem_footer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an administrative listing for DACEM footer feedback.
 */
class DacemFooterMessagesController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs the controller.
   */
  public function __construct(Connection $database, DateFormatterInterface $date_formatter) {
    $this->database = $database;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('date.formatter')
    );
  }

  /**
   * Builds the feedback messages overview page.
   */
  public function overview() {
    $header = [
      $this->t('ID'),
      $this->t('Email'),
      $this->t('Message'),
      $this->t('Path'),
      $this->t('Date'),
    ];

    $query = $this->database->select('dacem_footer_feedback', 'f')
      ->fields('f', ['id', 'email', 'message', 'page_path', 'created'])
      ->orderBy('created', 'DESC')
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(50);

    $rows = [];
    foreach ($query->execute() as $record) {
      
      $rows[] = [
        'data' => [
          $record->id,
          $record->email,
          [
            'data' => [
              '#markup' => nl2br(Html::escape($record->message)),
            ],
          ],
          $record->page_path,
          $this->dateFormatter->format($record->created, 'custom', 'Y-m-d H:i:s'),
        ],
      ];
    }

    return [
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No footer feedback messages found.'),
      ],
      'pager' => [
        '#type' => 'pager',
      ],
    ];
  }

}

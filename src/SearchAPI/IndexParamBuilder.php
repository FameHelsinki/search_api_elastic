<?php

namespace Drupal\search_api_elastic\SearchAPI;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api_elastic\Event\IndexParamsEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a param builder for Items.
 */
class IndexParamBuilder {

  /**
   * The fields helper.
   *
   * @var \Drupal\search_api\Utility\FieldsHelperInterface
   */
  protected $fieldsHelper;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Creates a new IndexParamBuilder.
   *
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fieldsHelper
   *   The fields helper.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(FieldsHelperInterface $fieldsHelper, EventDispatcherInterface $eventDispatcher) {
    $this->fieldsHelper = $fieldsHelper;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Builds the params for an index operation.
   *
   * @param string $indexId
   *   The index ID.
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   * @param \Drupal\search_api\Item\ItemInterface[] $items
   *   The items.
   *
   * @return array
   *   The index operation params.
   */
  public function buildIndexParams(string $indexId, IndexInterface $index, array $items): array {
    $params = [];

    foreach ($items as $id => $item) {
      $this->addSpecialFields($index, $item);
      /** @var \Drupal\search_api\Item\FieldInterface $field */
      foreach ($item as $name => $field) {
        $field_type = $field->getType();
        if (!empty($field->getValues())) {
          $values = $this->buildFieldValues($field, $field_type);
          $data[$field->getFieldIdentifier()] = $values;
        }
      }
      $params['body'][] = ['index' => ['_id' => $id, '_index' => $indexId]];
      $params['body'][] = $data;
    }

    // Allow modification of search params.
    $event = new IndexParamsEvent($indexId, $params);
    $this->eventDispatcher->dispatch($event);
    $params = $event->getParams();

    return $params;
  }

  /**
   * Adds the "magic" field values on an item.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The item.
   */
  protected function addSpecialFields(IndexInterface $index, ItemInterface $item): void {
    $item->setField('search_api_id', $this->fieldsHelper
      ->createField($index, 'search_api_id', ['type' => 'string'])
      ->setValues([$item->getId()]));
    $item->setField('search_api_datasource', $this->fieldsHelper
      ->createField($index, 'search_api_datasource', ['type' => 'string'])
      ->setValues([$item->getDatasourceId()]));
    $item->setField('search_api_language', $this->fieldsHelper
      ->createField($index, 'search_api_language', ['type' => 'string'])
      ->setValues([$item->getLanguage()]));
  }

  /**
   * Builds field values.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field.
   * @param string $field_type
   *   The field type.
   *
   * @return array
   *   The fields params.
   */
  public function buildFieldValues(FieldInterface $field, string $field_type): array {
    $values = [];
    foreach ($field->getValues() as $value) {
      $values[] = match ($field_type) {
        'string' => (string) $value,
        'text' => $value->toText(),
        'boolean' => (boolean) $value,
        default => $value,
      };
    }
    return $values;
  }

}

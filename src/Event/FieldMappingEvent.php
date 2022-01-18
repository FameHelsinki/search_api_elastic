<?php

namespace Drupal\search_api_elastic\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\search_api\Item\FieldInterface;

/**
 * Event triggered when a field is mapped.
 */
class FieldMappingEvent extends Event {

  /**
   * The field.
   *
   * @var \Drupal\search_api\Item\FieldInterface
   */
  protected $field;

  /**
   * The mapping param.
   *
   * @var array
   */
  protected $param;

  /**
   * Creates a new event.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field.
   * @param array $param
   *   The mapping param.
   */
  public function __construct(FieldInterface $field, array $param) {
    $this->field = $field;
    $this->param = $param;
  }

  /**
   * Gets the field.
   *
   * @return \Drupal\search_api\Item\FieldInterface
   *   The field.
   */
  public function getField(): FieldInterface {
    return $this->field;
  }

  /**
   * Gets the param.
   *
   * @return array
   *   The param.
   */
  public function getParam(): array {
    return $this->param;
  }

  /**
   * Sets the param.
   *
   * @param array $param
   *   The param.
   *
   * @return $this
   *   The current object.
   */
  public function setParam(array $param): FieldMappingEvent {
    $this->param = $param;
    return $this;
  }

}

<?php

namespace Drupal\search_api_elastic\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a connector plugin annotation object.
 *
 * Condition plugins provide generalized conditions for use in other
 * operations, such as conditional block placement.
 *
 * Plugin Namespace: Plugin\ElasticSearch
 *
 * @see \Drupal\search_api_elastic\Connector\ConnectorPluginManager
 * @see \Drupal\search_api_elastic\Connector\ElasticSearchConnectorInterface
 *
 * @ingroup plugin_api
 *
 * @Annotation
 */
class ElasticSearchConnector extends Plugin {

  /**
   * The ElasticSearch connector plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the ElasticSearch connector.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The backend description.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}

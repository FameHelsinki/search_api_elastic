<?php

namespace Drupal\search_api_elastic\Connector;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;
// use Elastica\Client;
use Elasticsearch\Client;

/**
 * Defines and interface for ElasticSearch Connector plugins.
 */
interface ElasticSearchConnectorInterface extends PluginFormInterface, ConfigurableInterface, PluginInspectionInterface {

  /**
   * Gets the connection label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string;

  /**
   * Gets the connection description.
   *
   * @return string
   *   The description.
   */
  public function getDescription(): string;

  /**
   * Gets the ElasticSearch client.
   *
   * @return \Elasticsearch\Client
   *   The ElasticSearch client.
   */
  public function getClient(): Client;

  /**
   * Gets the URL to the ElasticSearch cluster.
   *
   * @return string
   *   The ElasticSearch cluster URL.
   */
  public function getUrl(): string;

}

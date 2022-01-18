<?php

namespace Drupal\search_api_elastic\SearchAPI;

use Drupal\search_api_elastic\SearchAPI\Query\QueryParamBuilder;
use Drupal\search_api_elastic\SearchAPI\Query\QueryResultParser;
use Drupal\search_api\Utility\FieldsHelperInterface;
use ElasticSearch\Client;
use Psr\Log\LoggerInterface;

/**
 * Provides a factory for creating a backend client.
 *
 * This is needed because the client is dynamically created based on the
 * connector plugin selected.
 */
class BackendClientFactory {

  /**
   * The item param builder.
   *
   * @var \Drupal\search_api_elastic\SearchAPI\IndexParamBuilder
   */
  protected $itemParamBuilder;

  /**
   * The query param builder.
   *
   * @var \Drupal\search_api_elastic\SearchAPI\Query\QueryParamBuilder
   */
  protected $queryParamBuilder;

  /**
   * The query result parser.
   *
   * @var \Drupal\search_api_elastic\SearchAPI\Query\QueryResultParser
   */
  protected $resultParser;

  /**
   * The Search API fields helper.
   *
   * @var \Drupal\search_api\Utility\FieldsHelperInterface
   */
  protected $fieldsHelper;

  /**
   * The field mapping param builder.
   *
   * @var \Drupal\search_api_elastic\SearchAPI\FieldMapper
   */
  protected $fieldParamsBuilder;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Creates a backend client factory.
   *
   * @param \Drupal\search_api_elastic\SearchAPI\Query\QueryParamBuilder $queryParamBuilder
   *   The query param builder.
   * @param \Drupal\search_api_elastic\SearchAPI\Query\QueryResultParser $resultParser
   *   The query result parser.
   * @param \Drupal\search_api_elastic\SearchAPI\IndexParamBuilder $itemParamBuilder
   *   The index param builder.
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fieldsHelper
   *   The fields helper.
   * @param \Drupal\search_api_elastic\SearchAPI\FieldMapper $fieldParamsBuilder
   *   The field mapper.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(QueryParamBuilder $queryParamBuilder, QueryResultParser $resultParser, IndexParamBuilder $itemParamBuilder, FieldsHelperInterface $fieldsHelper, FieldMapper $fieldParamsBuilder, LoggerInterface $logger) {
    $this->itemParamBuilder = $itemParamBuilder;
    $this->queryParamBuilder = $queryParamBuilder;
    $this->resultParser = $resultParser;
    $this->fieldsHelper = $fieldsHelper;
    $this->logger = $logger;
    $this->fieldParamsBuilder = $fieldParamsBuilder;
  }

  /**
   * Creates a new ElasticSearch Search API client.
   *
   * @param \ElasticSearch\Client $client
   *   The ElasticSearch client.
   * @param array $settings
   *   THe backend settings.
   *
   * @return \Drupal\search_api_elastic\SearchAPI\BackendClientInterface
   *   The backend client.
   */
  public function create(Client $client, array $settings): BackendClientInterface {
    return new BackendClient(
      $this->queryParamBuilder,
      $this->resultParser,
      $this->itemParamBuilder,
      $this->fieldsHelper,
      $this->fieldParamsBuilder,
      $this->logger,
      $client,
      $settings,
    );
  }

}

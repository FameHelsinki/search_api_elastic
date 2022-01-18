<?php

namespace Drupal\search_api_elastic\SearchAPI;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api_elastic\SearchAPI\Query\QueryParamBuilder;
use Drupal\search_api_elastic\SearchAPI\Query\QueryResultParser;
use ElasticSearch\Client;
use ElasticSearch\Common\Exceptions\ElasticSearchException;
use Psr\Log\LoggerInterface;

/**
 * Provides an ElasticSearch Search API client.
 */
class BackendClient implements BackendClientInterface {

  /**
   * The item param builder.
   *
   * @var \Drupal\search_api_elastic\SearchAPI\IndexParamBuilder
   */
  protected $indexParamBuilder;

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
   * The ElasticSearch client.
   *
   * @var \ElasticSearch\Client
   */
  protected $client;

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
   * Constructs a new BackendClient.
   *
   * @param \Drupal\search_api_elastic\SearchAPI\Query\QueryParamBuilder $queryParamBuilder
   *   The query param builder.
   * @param \Drupal\search_api_elastic\SearchAPI\Query\QueryResultParser $resultParser
   *   The query result parser.
   * @param \Drupal\search_api_elastic\SearchAPI\IndexParamBuilder $indexParamBuilder
   *   The index param builder.
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fieldsHelper
   *   The fields helper.
   * @param \Drupal\search_api_elastic\SearchAPI\FieldMapper $fieldParamsBuilder
   *   THe field mapper.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \ElasticSearch\Client $client
   *   The ElasticSearch client.
   * @param array $settings
   *   The settings.
   */
  public function __construct(QueryParamBuilder $queryParamBuilder, QueryResultParser $resultParser, IndexParamBuilder $indexParamBuilder, FieldsHelperInterface $fieldsHelper, FieldMapper $fieldParamsBuilder, LoggerInterface $logger, Client $client, array $settings) {
    $this->indexParamBuilder = $indexParamBuilder;
    $this->queryParamBuilder = $queryParamBuilder;
    $this->resultParser = $resultParser;
    $this->client = $client;
    $this->fieldsHelper = $fieldsHelper;
    $this->logger = $logger;
    $this->fieldParamsBuilder = $fieldParamsBuilder;
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable() {
    return $this->client->ping();
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(IndexInterface $index, array $items): array {
    if (empty($items)) {
      return [];
    }
    $indexId = $this->getIndexId($index);

    $params = $this->indexParamBuilder->buildIndexParams($indexId, $index, $items);

    try {
      $response = $this->client->bulk($params);
      // If there were any errors, log them and throw an exception.
      if (!empty($response['errors'])) {
        foreach ($response['items'] as $item) {
          if (!empty($item['index']['status']) && $item['index']['status'] == '400') {
            $this->logger->error('%reason. %caused_by for index: %id', [
              '%reason' => $item['index']['error']['reason'],
              '%caused_by' => $item['index']['error']['caused_by']['reason'],
              '%id' => $item['index']['_id'],
            ]);
          }
        }
        throw new SearchApiException('An error occurred indexing items.');
      }
    }
    catch (ElasticSearchException $e) {
      throw new SearchApiException(sprintf('An error occurred indexing items in index %s.', $indexId), 0, $e);
    }

    return array_keys($items);

  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index, array $item_ids): void {
    if (empty($item_ids)) {
      return;
    }

    $indexId = $this->getIndexId($index);
    $params = [
      'index' => $indexId,
    ];

    foreach ($item_ids as $id) {
      $params['body'][] = [
        'delete' => [
          '_index' => $params['index'],
          '_id' => $id,
        ],
      ];
    }
    try {
      $this->client->bulk($params);
    }
    catch (ElasticSearchException $e) {
      throw new SearchApiException(sprintf('An error occurred deleting items from the index %s.', $indexId), 0, $e);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query): ResultSetInterface {
    $resultSet = $query->getResults();
    $index = $query->getIndex();
    $indexId = $this->getIndexId($index);
    $params = [
      'index' => $indexId,
    ];

    // Check index exists.
    if (!$this->client->indices()->exists($params)) {
      $this->logger->warning('Index "%index" does not exist.', ["%index" => $indexId]);
      return $resultSet;
    }

    // Build ElasticSearch query.
    $params = $this->queryParamBuilder->buildQueryParams($indexId, $query, $this->settings);

    try {

      // When set to true the search response will always track the number of
      // hits that match the query accurately.
      $params['track_total_hits'] = TRUE;

      // Do search.
      $response = $this->client->search($params);
      $resultSet = $this->resultParser->parseResult($query, $response);

      return $resultSet;
    }
    catch (ElasticSearchException $e) {
      throw new SearchApiException(sprintf('Error querying index %s', $indexId), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index): void {
    $indexId = $this->getIndexId($index);
    try {
      $this->client->indices()->delete([
        'index' => [$indexId],
      ]);
    }
    catch (ElasticSearchException $e) {
      throw new SearchApiException(sprintf('An error occurred removing the index %s.', $indexId), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index): void {
    $indexId = $this->getIndexId($index);
    try {
      $this->client->indices()->create([
        'index' => $indexId,
      ]);
      $this->updateFieldMapping($index);
    }
    catch (ElasticSearchException $e) {
      throw new SearchApiException(sprintf('An error occurred creating the index %s.', $indexId), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index): void {
    $this->updateFieldMapping($index);
  }

  /**
   * Updates the field mappings for an index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown when an underlying ElasticSearch error occurs.
   */
  public function updateFieldMapping(IndexInterface $index): void {
    $indexId = $this->getIndexId($index);
    try {
      $params = $this->fieldParamsBuilder->mapFieldParams($indexId, $index);
      $this->client->indices()->putMapping($params);
    }
    catch (ElasticSearchException $e) {
      throw new SearchApiException(sprintf('An error occurred updating field mappings for index %s.', $indexId), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clearIndex(IndexInterface $index, string $datasource_id = NULL): void {
    $this->removeIndex($index);
    $this->addIndex($index);
  }

  /**
   * {@inheritdoc}
   */
  public function indexExists(IndexInterface $index): bool {
    $indexId = $this->getIndexId($index);
    try {
      return $this->client->indices()->exists([
        'index' => $indexId,
      ]);
    }
    catch (ElasticSearchException $e) {
      throw new SearchApiException(sprintf('An error occurred checking if the index %s exists.', $indexId), 0, $e);
    }
  }

  /**
   * Gets the index ID.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   *
   * @return string
   *   The index ID.
   */
  public function getIndexId(IndexInterface $index) {
    return $this->settings['prefix'] . $index->id();
  }

}

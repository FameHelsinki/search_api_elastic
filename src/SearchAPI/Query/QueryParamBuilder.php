<?php

namespace Drupal\search_api_elastic\SearchAPI\Query;

use Drupal\search_api_elastic\Event\QueryParamsEvent;
use Drupal\search_api_elastic\SearchAPI\MoreLikeThisParamBuilder;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a query param builder for search operations.
 */
class QueryParamBuilder {

  /**
   * The default query offset.
   */
  const DEFAULT_OFFSET = 0;

  /**
   * The default query limit.
   */
  const DEFAULT_LIMIT = 10;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

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
   * The MLT param builder.
   *
   * @var \Drupal\search_api_elastic\SearchAPI\MoreLikeThisParamBuilder
   */
  protected $mltParamBuilder;

  /**
   * The search param builder.
   *
   * @var \Drupal\search_api_elastic\SearchAPI\Query\SearchParamBuilder
   */
  protected $searchParamBuilder;

  /**
   * The sort builder.
   *
   * @var \Drupal\search_api_elastic\SearchAPI\Query\QuerySortBuilder
   */
  protected $sortBuilder;

  /**
   * The filter builder.
   *
   * @var \Drupal\search_api_elastic\SearchAPI\Query\FilterBuilder
   */
  protected $filterBuilder;

  /**
   * The facet param builder.
   *
   * @var \Drupal\search_api_elastic\SearchAPI\Query\FacetParamBuilder
   */
  protected $facetBuilder;

  /**
   * Creates a new QueryParamBuilder.
   *
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fieldsHelper
   *   The fields helper.
   * @param \Drupal\search_api_elastic\SearchAPI\Query\QuerySortBuilder $sortBuilder
   *   The sort builder.
   * @param \Drupal\search_api_elastic\SearchAPI\Query\FilterBuilder $filterBuilder
   *   The filter builder.
   * @param \Drupal\search_api_elastic\SearchAPI\Query\SearchParamBuilder $searchParamBuilder
   *   The search param builder.
   * @param \Drupal\search_api_elastic\SearchAPI\MoreLikeThisParamBuilder $mltParamBuilder
   *   The More Like This param builder.
   * @param \Drupal\search_api_elastic\SearchAPI\Query\FacetParamBuilder $facetBuilder
   *   The facet param builder.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(FieldsHelperInterface $fieldsHelper, QuerySortBuilder $sortBuilder, FilterBuilder $filterBuilder, SearchParamBuilder $searchParamBuilder, MoreLikeThisParamBuilder $mltParamBuilder, FacetParamBuilder $facetBuilder, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger) {
    $this->fieldsHelper = $fieldsHelper;
    $this->sortBuilder = $sortBuilder;
    $this->filterBuilder = $filterBuilder;
    $this->searchParamBuilder = $searchParamBuilder;
    $this->mltParamBuilder = $mltParamBuilder;
    $this->facetBuilder = $facetBuilder;
    $this->eventDispatcher = $eventDispatcher;
    $this->logger = $logger;
  }

  /**
   * Build up the body of the request to the ElasticSearch _search endpoint.
   *
   * @param string $indexId
   *   The query.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The index ID.
   * @param array $settings
   *   The query settings.
   *
   * @return array
   *   Array or parameters to send along to the ElasticSearch _search endpoint.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an error occurs building query params.
   */
  public function buildQueryParams(string $indexId, QueryInterface $query, array $settings): array {
    $index = $query->getIndex();
    $params = [
      'index' => $indexId,
    ];

    $body = [];

    // Set the size and from parameters.
    $body['from'] = $query->getOption('offset', self::DEFAULT_OFFSET);
    $body['size'] = $query->getOption('limit', self::DEFAULT_LIMIT);

    // Sort.
    $sort = $this->sortBuilder->getSortSearchQuery($query);
    if (!empty($sort)) {
      $body['sort'] = $sort;
    }

    $languages = $query->getLanguages();
    if ($languages !== NULL) {
      $query->getConditionGroup()->addCondition('search_api_language', $languages, 'IN');
    }

    $index_fields = $this->getIndexFields($index);

    // Filters.
    $filters = $this->filterBuilder->buildFilters($query->getConditionGroup(), $index_fields);

    // Build the query.
    $searchParams = $this->searchParamBuilder->buildSearchParams($query, $index_fields, $settings);
    if (!empty($searchParams) && !empty($filters)) {
      $body['query']['bool']['must'] = $searchParams;
      $body['query']['bool']['filter'] = $filters;
    }
    elseif (!empty($searchParams)) {
      if (empty($body['query'])) {
        $body['query'] = [];
      }
      $body['query'] += $searchParams;
    }
    elseif (!empty($filters)) {
      $body['query']['bool']['filter'] = $filters;
    }

    // @todo Handle fields on filter query.
    if (empty($fields)) {
      unset($body['fields']);
    }

    if (empty($body['post_filter'])) {
      unset($body['post_filter']);
    }

    // If the body is empty, match all.
    if (empty($body)) {
      $body['match_all'] = [];
    }

    $exclude_source_fields = $query->getOption('elasticsearch_exclude_source_fields', []);
    if (!empty($exclude_source_fields)) {
      $body['_source'] = [
        'excludes' => $exclude_source_fields,
      ];
    }

    // More Like This.
    if (!empty($query->getOption('search_api_mlt'))) {
      $body['query']['bool']['must'][] = $this->mltParamBuilder->buildMoreLikeThisQuery($query->getOption('search_api_mlt'));
    }

    if (!empty($query->getOption('search_api_facets'))) {
      $aggs = $this->facetBuilder->buildFacetParams($query, $index_fields);
      if (!empty($aggs)) {
        $body['aggs'] = $aggs;
      }
    }

    $params['body'] = $body;
    // Preserve the options for further manipulation if necessary.
    $query->setOption('ElasticSearchParams', $params);

    // Allow modification of search params via an event.
    $event = new QueryParamsEvent($indexId, $params);
    $this->eventDispatcher->dispatch($event);
    $params = $event->getParams();

    return $params;
  }

  /**
   * Gets the list of index fields.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   *
   * @return \Drupal\search_api\Item\FieldInterface[]
   *   The index fields, keyed by field identifier.
   */
  protected function getIndexFields(IndexInterface $index): array {
    $index_fields = $index->getFields();

    // Search API does not provide metadata for some special fields but might
    // try to query for them. Thus add the metadata so we allow for querying
    // them.
    if (empty($index_fields['search_api_datasource'])) {
      $index_fields['search_api_datasource'] = $this->fieldsHelper->createField($index, 'search_api_datasource', ['type' => 'string']);
    }
    if (empty($index_fields['search_api_id'])) {
      $index_fields['search_api_id'] = $this->fieldsHelper->createField($index, 'search_api_id', ['type' => 'string']);
    }
    return $index_fields;
  }

}

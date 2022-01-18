<?php

namespace Drupal\search_api_elastic\SearchAPI\Query;

use Drupal\search_api\Query\QueryInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds facet params.
 */
class FacetParamBuilder {

  /**
   * The default facet size.
   */
  protected const DEFAULT_FACET_SIZE = "10";

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Creates a new Facet builder.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * Fill the aggregation array of the request.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   Search API query.
   * @param array $indexFields
   *   The index field, keyed by field identifier.
   *
   * @return array
   *   The facets params.
   */
  public function buildFacetParams(QueryInterface $query, array $indexFields) {
    $aggs = [];
    $facets = $query->getOption('search_api_facets', []);
    if (empty($facets)) {
      return $aggs;
    }

    foreach ($facets as $facet_id => $facet) {
      $field = $facet['field'];
      if (!isset($indexFields[$field])) {
        $this->logger->warning('Unknown facet field: %field', ['%field' => $field]);
        continue;
      }
      // Default to term bucket aggregation.
      $aggs += $this->buildTermBucketAgg($facet_id, $facet);
    }

    return $aggs;
  }

  /**
   * Builds a bucket aggregation.
   *
   * @param string $facet_id
   *   The key.
   * @param array $facet
   *   The facet.
   *
   * @return array
   *   The bucket aggregation.
   */
  protected function buildTermBucketAgg(string $facet_id, array $facet): array {
    $agg = [
      $facet_id => ["terms" => ["field" => $facet['field']]],
    ];
    $size = $facet['limit'] ?? self::DEFAULT_FACET_SIZE;
    if ($size > 0) {
      $agg[$facet_id]["terms"]["size"] = $size;
    }

    // If operator is OR we need to set to global and nest the agg.
    if (isset($facet['operator']) && $facet['operator'] === 'or') {
      $agg = [
        $facet_id . '_global' => [
          'global' => (object) NULL,
          'aggs' => $agg,
        ],
      ];
    }

    return $agg;
  }

}

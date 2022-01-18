<?php

namespace Drupal\search_api_elastic\SearchAPI\Query;

use Drupal\search_api\Query\QueryInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides a facet result parser.
 */
class FacetResultParser {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Creates a new facet result parser.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * Parse the facet result.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   * @param array $response
   *   The response.
   *
   * @return array
   *   The facet data in the format expected by facets module.
   */
  public function parseFacetResult(QueryInterface $query, array $response): array {
    $facetData = [];
    $facets = $query->getOption('search_api_facets', []);

    foreach ($facets as $facet_id => $facet) {
      $terms = [];

      // Handle 'and' operator.
      if ($facet['operator'] === 'and') {
        $buckets = $response['aggregations'][$facet_id]['buckets'];
        array_walk($buckets, function ($value) use (&$terms) {
          $terms[] = [
            'count' => $value['doc_count'],
            'filter' => '"' . $value['key'] . '"',
          ];
        });
        $facetData[$facet_id] = $terms;
        continue;
      }
      if ($facet['operator'] === 'or') {
        if (!isset($response['aggregations'][$facet_id . '_global'])) {
          $this->logger->warning("Missing global facet ID %facet_id for 'or' operation", ['%facet_id' => $facet_id]);
          continue;
        }
        $buckets = $response['aggregations'][$facet_id . '_global'][$facet_id]['buckets'];
        array_walk($buckets, function ($value) use (&$terms) {
          $terms[] = [
            'count' => $value['doc_count'],
            'filter' => '"' . $value['key'] . '"',
          ];
        });
        $facetData[$facet_id] = $terms;
        continue;
      }

      $this->logger->warning("Invalid operator: %operator", ['%operator' => $facet['operator']]);
    }
    return $facetData;
  }

}

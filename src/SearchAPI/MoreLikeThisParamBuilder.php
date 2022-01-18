<?php

namespace Drupal\search_api_elastic\SearchAPI;

/**
 * Provides a param builder for 'More Like This' queries.
 */
class MoreLikeThisParamBuilder {

  /**
   * Setup the More like this clause of the ElasticSearch query.
   *
   * Adjusts $body to have a more like this query.
   *
   * @param array $mltOptions
   *   An associative array of query options with the keys:
   *   - id: To be used as the like_text in the more_like_this query.
   *   - fields: Array of fields.
   *
   * @return array
   *   The MLT query params.
   */
  public function buildMoreLikeThisQuery(array $mltOptions): array {

    $mltQuery['more_like_this'] = [];

    // Transform input parameter "id" to "ids" if available.
    if (isset($mltOptions['id'])) {
      $mltOptions['ids'] = is_array($mltOptions['id']) ? $mltOptions['id'] : [$mltOptions['id']];
      unset($mltOptions['id']);
    }

    // Input parameter: ids.
    if (isset($mltOptions['ids'])) {
      $mltQuery['more_like_this']['ids'] = $mltOptions['ids'];
    }

    // Input parameter: like.
    if (isset($mltOptions['like'])) {
      $mltQuery['more_like_this']['like'] = $mltOptions['like'];
    }

    // Input parameter: unlike.
    if (isset($mltOptions['unlike'])) {
      $mltQuery['more_like_this']['unlike'] = $mltOptions['unlike'];
    }

    // Input parameter: fields.
    $mltQuery['more_like_this']['fields'] = array_values(
      $mltOptions['fields']
    );
    // @todo Make this settings configurable in the view.
    $mltQuery['more_like_this']['max_query_terms'] = 1;
    $mltQuery['more_like_this']['min_doc_freq'] = 1;
    $mltQuery['more_like_this']['min_term_freq'] = 1;

    return $mltQuery;
  }

}

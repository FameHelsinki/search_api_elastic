<?php

namespace Drupal\Tests\search_api_elastic\Unit\SearchAPI\Query;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api_elastic\SearchAPI\MoreLikeThisParamBuilder;
use Drupal\search_api_elastic\SearchAPI\Query\FacetParamBuilder;
use Drupal\search_api_elastic\SearchAPI\Query\FilterBuilder;
use Drupal\search_api_elastic\SearchAPI\Query\QueryParamBuilder;
use Drupal\search_api_elastic\SearchAPI\Query\QuerySortBuilder;
use Drupal\search_api_elastic\SearchAPI\Query\SearchParamBuilder;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the query param builder.
 *
 * @coversDefaultClass \Drupal\search_api_elastic\SearchAPI\Query\QueryParamBuilder
 * @group search_api_elastic
 */
class QueryParamBuilderTest extends UnitTestCase {

  /**
   * @covers ::
   */
  public function testBuildQueryParams() {

    $fieldsHelper = $this->prophesize(FieldsHelperInterface::class);

    $sortBuilder = $this->prophesize(QuerySortBuilder::class);
    $sortBuilder->getSortSearchQuery(Argument::any())
      ->willReturn([]);

    $filterBuilder = $this->prophesize(FilterBuilder::class);
    $filterBuilder->buildFilters(Argument::any(), Argument::any())
      ->willReturn([]);

    $searchParamBuilder = $this->prophesize(SearchParamBuilder::class);
    $searchParamBuilder->buildSearchParams(Argument::any(), Argument::any(), Argument::any())
      ->willReturn([]);

    $mltParamBuilder = $this->prophesize(MoreLikeThisParamBuilder::class);
    $facetParamBuilder = $this->prophesize(FacetParamBuilder::class);
    $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
    $logger = new NullLogger();

    $queryParamBuilder = new QueryParamBuilder($fieldsHelper->reveal(), $sortBuilder->reveal(), $filterBuilder->reveal(), $searchParamBuilder->reveal(), $mltParamBuilder->reveal(), $facetParamBuilder->reveal(), $eventDispatcher->reveal(), $logger);

    $indexId = "foo";
    $index = $this->prophesize(IndexInterface::class);
    $index->id()
      ->willReturn($indexId);

    $field1 = $this->prophesize(FieldInterface::class);

    $fields = [$field1->reveal()];

    $index->getFields()
      ->willReturn($fields);

    $query = $this->prophesize(QueryInterface::class);
    $query->getOption('offset', Argument::any())
      ->willReturn(0);
    $query->getOption('limit', Argument::any())
      ->willReturn(10);
    $query->getOption('elasticsearch_exclude_source_fields', Argument::any())
      ->willReturn([]);
    $query->getOption('search_api_mlt')
      ->willReturn(NULL);
    $query->getOption('search_api_facets')
      ->willReturn(NULL);
    $query->getLanguages()
      ->willReturn(NULL);
    $query->getIndex()
      ->willReturn($index->reveal());
    $conditionGroup = $this->prophesize(ConditionGroupInterface::class);
    $query->getConditionGroup()
      ->willReturn($conditionGroup->reveal());

    $expected = ['index' => 'foo', 'body' => ['from' => 0, 'size' => 10]];
    $query->setOption('ElasticSearchParams', Argument::exact($expected))
      ->willReturn(Argument::any());
    $settings = ['fuzziness' => 'auto'];
    $queryParams = $queryParamBuilder->buildQueryParams($indexId, $query->reveal(), $settings);

    $expected = ['index' => 'foo', 'body' => ['from' => 0, 'size' => 10]];
    $this->assertEquals($expected, $queryParams);

  }

}

<?php

namespace Drupal\Tests\search_api_elastic\Unit\SearchAPI\Query;

use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_elastic\SearchAPI\Query\FacetParamBuilder;
use Drupal\Tests\UnitTestCase;
use Psr\Log\Test\TestLogger;

/**
 * Tests the facet param builder.
 *
 * @coversDefaultClass \Drupal\search_api_elastic\SearchAPI\Query\FacetParamBuilder
 * @group search_api_elastic
 */
class FacetParamBuilderTest extends UnitTestCase {

  /**
   * @covers ::buildFacetParams
   */
  public function testBuildFacetParams() {
    $logger = new TestLogger();
    $builder = new FacetParamBuilder($logger);

    $query = $this->prophesize(QueryInterface::class);
    $query->getOption('search_api_facets', [])
      ->willReturn([
        'facet1' => [
          'field' => 'field1',
          'operator' => 'and',
        ],
        'facet2' => [
          'field' => 'field1',
          'operator' => 'or',
        ],
      ]);

    $indexFields = [
      'field1' => [],
      'field2' => [],
    ];

    $aggs = $builder->buildFacetParams($query->reveal(), $indexFields);

    $expected = [
      'facet1' => ['terms' => ['field' => 'field1', 'size' => '10']],
      'facet2_global' => [
        'global' => (object) NULL,
        'aggs' => [
          'facet2' =>
            ['terms' => ['field' => 'field1', 'size' => '10']],
        ],
      ],
    ];

    $this->assertNotEmpty($aggs);
    $this->assertEquals($expected, $aggs);
  }

}

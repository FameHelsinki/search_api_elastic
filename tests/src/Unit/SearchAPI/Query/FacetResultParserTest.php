<?php

namespace Drupal\Tests\search_api_elastic\Unit\SearchAPI\Query;

use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_elastic\SearchAPI\Query\FacetResultParser;
use Drupal\Tests\UnitTestCase;
use Psr\Log\Test\TestLogger;

/**
 * Tests the facets result parser.
 *
 * @coversDefaultClass \Drupal\search_api_elastic\SearchAPI\Query\FacetResultParser
 * @group search_api_elastic
 */
class FacetResultParserTest extends UnitTestCase {

  /**
   * @covers ::parseFacetResult
   */
  public function testParseFacetResult() {
    $logger = new TestLogger();
    $parser = new FacetResultParser($logger);

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

    $response = [
      'aggregations' => [
        'facet1' => [
          'doc_count_error_upper_bound' => 0,
          'sum_other_doc_count' => 0,
          'buckets' => [
            [
              'key' => 'foo',
              'doc_count' => 100,
            ],
            [
              'key' => 'bar',
              'doc_count' => 200,
            ],
          ],
        ],
        'facet2_global' => [
          'facet2' => [
            'buckets' => [
              [
                'key' => 'whizz',
                'doc_count' => 400,
              ],
            ],
          ],
        ],
      ],
    ];

    $facetData = $parser->parseFacetResult($query->reveal(), $response);

    $expected = [
      'facet1' => [
        [
          'count' => 100,
          'filter' => '"foo"',
        ],
        [
          'count' => 200,
          'filter' => '"bar"',
        ],
      ],
      'facet2' => [
        [
          'count' => 400,
          'filter' => '"whizz"',
        ],
      ],
    ];
    $this->assertNotEmpty($facetData);
    $this->assertEquals($expected, $facetData);

  }

}

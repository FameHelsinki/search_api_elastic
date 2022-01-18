<?php

namespace Drupal\Tests\search_api_elastic\Unit\SearchAPI\Query;

use Drupal\search_api_elastic\SearchAPI\Query\FilterBuilder;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Query\Condition;
use Drupal\search_api\Query\ConditionGroup;
use Drupal\Tests\UnitTestCase;
use Psr\Log\Test\TestLogger;

/**
 * Tests the filter builder.
 *
 * @coversDefaultClass \Drupal\search_api_elastic\SearchAPI\Query\FilterBuilder
 * @group search_api_elastic
 */
class FilterBuilderTest extends UnitTestCase {

  /**
   * @covers ::buildFilters
   */
  public function testBuildFilters() {

    $index = $this->prophesize(IndexInterface::class);
    $indexId = "index_" . $this->randomMachineName();
    $index->id()->willReturn($indexId);

    $conditionGroup = (new ConditionGroup())
      ->addCondition('foo', 'bar')
      ->addCondition('whiz', 'bang');

    $field1 = new Field($index->reveal(), 'foo');
    $field2 = new Field($index->reveal(), 'whiz');
    $fields = [
      'foo' => $field1,
      'whiz' => $field2,
    ];

    $logger = new TestLogger();
    $filterBuilder = new FilterBuilder($logger);
    $filters = $filterBuilder->buildFilters($conditionGroup, $fields);

    $this->assertNotEmpty($filters);

    $expected = [
      'bool' => [
        'must' => [
          ['term' => ['foo' => 'bar']],
          ['term' => ['whiz' => 'bang']],
        ],
      ],
    ];

    $this->assertEquals($expected, $filters);

  }

  /**
   * @covers ::buildFilterTerm
   * @dataProvider filterTermProvider
   */
  public function testBuildFilterTerm($value, $operator, $expected) {
    $logger = new TestLogger();
    $filterBuilder = new FilterBuilder($logger);
    $condition = new Condition('foo', $value, $operator);
    $filterTerm = $filterBuilder->buildFilterTerm($condition);
    $this->assertEquals($expected, $filterTerm);
  }

  /**
   * Provides test data for term provider.
   */
  public function filterTermProvider(): array {
    return [
      'not equals with null value' => [
        'value' => NULL,
        'operator' => '<>',
        'expected' => ['exists' => ['field' => 'foo']],
      ],
      'equals with null value' => [
        'value' => NULL,
        'operator' => '=',
        'expected' => ['bool' => ['must_not' => ['exists' => ['field' => 'foo']]]],
      ],
      'equals' => [
        'value' => 'bar',
        'operator' => '=',
        'expected' => ['term' => ['foo' => 'bar']],
      ],
      'in array' => [
        'value' => ['bar', 'whiz'],
        'operator' => 'IN',
        'expected' => [
          'terms' => ['foo' => ['bar', 'whiz']],
        ],
      ],
      'not in array' => [
        'value' => ['bar', 'whiz'],
        'operator' => 'NOT IN',
        'expected' => [
          'bool' => [
            'must_not' => ['terms' => ['foo' => ['bar', 'whiz']]],
          ],
        ],
      ],
      'not equals' => [
        'value' => 'bar',
        'operator' => '<>',
        'expected' => [
          'bool' => [
            'must_not' => ['term' => ['foo' => 'bar']],
          ],
        ],
      ],
      'greater than' => [
        'value' => 'bar',
        'operator' => '>',
        'expected' => [
          'range' => [
            'foo' => [
              'from' => 'bar',
              'to' => NULL,
              'include_lower' => FALSE,
              'include_upper' => FALSE,
            ],
          ],
        ],
      ],
      'greater than or equal' => [
        'value' => 'bar',
        'operator' => '>=',
        'expected' => [
          'range' => [
            'foo' => [
              'from' => 'bar',
              'to' => NULL,
              'include_lower' => TRUE,
              'include_upper' => FALSE,
            ],
          ],
        ],
      ],
      'less than' => [
        'value' => 'bar',
        'operator' => '<',
        'expected' => [
          'range' => [
            'foo' => [
              'from' => NULL,
              'to' => 'bar',
              'include_lower' => FALSE,
              'include_upper' => FALSE,
            ],
          ],
        ],
      ],
      'less than or equal' => [
        'value' => 'bar',
        'operator' => '<=',
        'expected' => [
          'range' => [
            'foo' => [
              'from' => NULL,
              'to' => 'bar',
              'include_lower' => FALSE,
              'include_upper' => TRUE,
            ],
          ],
        ],
      ],
      'between' => [
        'value' => [1, 10],
        'operator' => 'BETWEEN',
        'expected' => [
          'range' =>
            [
              'foo' =>
                [
                  'from' => 1,
                  'to' => 10,
                  'include_lower' => FALSE,
                  'include_upper' => FALSE,
                ],
            ],
        ],
      ],
      'not between' => [
        'value' => [1, 10],
        'operator' => 'NOT BETWEEN',
        'expected' => [
          'bool' => [
            'must_not' => [
              'range' =>
                [
                  'foo' =>
                    [
                      'from' => 1,
                      'to' => 10,
                      'include_lower' => FALSE,
                      'include_upper' => FALSE,
                    ],
                ],
            ],
          ],
        ],
      ],
    ];
  }

}

<?php

namespace Drupal\Tests\search_api_elastic\Kernel;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Query\QueryInterface;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;
use Drupal\Tests\search_api\Kernel\BackendTestBase;
use ElasticSearch\Common\Exceptions\NoNodesAvailableException;

/**
 * Tests the end-to-end functionality of the backend.
 *
 * @group search_api_elastic
 */
class ElasticSearchBackendTest extends BackendTestBase {

  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'search_api_elastic',
    'search_api_elastic_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $serverId = 'elasticsearch_server';

  /**
   * {@inheritdoc}
   */
  protected $indexId = 'test_elasticsearch_index';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig([
      'search_api_elastic',
      'search_api_elastic_test',
    ]);
    if (!$this->serverAvailable()) {
      $this->markTestSkipped("ElasticSearch server not available");
    }
  }

  /**
   * Check if the server is available.
   */
  protected function serverAvailable(): bool {
    try {
      /** @var \Drupal\search_api\Entity\Server $server */
      $server = Server::load($this->serverId);
      if ($server->getBackend()->isAvailable()) {
        return TRUE;
      }
    }
    catch (NoNodesAvailableException $e) {
      // Ignore.
    }
    return FALSE;
  }

  /**
   * Tests various indexing scenarios for the search backend.
   *
   * Uses a single method to save time.
   */
  public function testBackend() {
    $this->recreateIndex();
    $this->insertExampleContent();
    $this->checkDefaultServer();
    $this->checkServerBackend();
    $this->checkDefaultIndex();
    $this->updateIndex();
    $this->searchNoResults();
    $this->indexItems($this->indexId);
    // Wait for the items to index.
    sleep(6);
    $this->searchSuccess();
  }

  /**
   * Re-creates the index.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function recreateIndex() {
    $server = Server::load($this->serverId);
    /** @var \Drupal\search_api_elastic\Plugin\search_api\backend\ElasticSearchBackend $backend */
    $backend = $server->getBackend();
    $index = Index::load($this->indexId);
    $client = $backend->getBackendClient();
    if ($client->indexExists($index)) {
      $client->removeIndex($index);
    }
    $client->addIndex($index);
  }

  /**
   * Tests whether some test searches have the correct results.
   */
  protected function searchSuccess() {
    // @codingStandardsIgnoreStart

    $results = $this->buildSearch('test')
    ->range(1, 2)
    ->execute();

    $this->assertEquals(4, $results->getResultCount(), 'Search for »test« returned correct number of results.');

    $this->assertEquals($this->getItemIds([
      2,
      3,
    ]), array_keys($results->getResultItems()), 'Search for »test« returned correct result.');
    $this->assertEmpty($results->getIgnoredSearchKeys());
    $this->assertEmpty($results->getWarnings());

    $id = $this->getItemIds([2])[0];
    $this->assertEquals($id, key($results->getResultItems()));
    $this->assertEquals($id, $results->getResultItems()[$id]->getId());
    $this->assertEquals('entity:entity_test_mulrev_changed', $results->getResultItems()[$id]->getDatasourceId());

    $results = $this->buildSearch('test foo')->execute();

    $this->assertResults([1, 2, 4], $results, 'Search for »test foo«');

    $results = $this->buildSearch('foo', ['type,item'])->execute();
    $this->assertResults([1, 2], $results, 'Search for »foo«');

    $keys = [
      '#conjunction' => 'AND',
      'test',
      [
        '#conjunction' => 'OR',
        'baz',
        'foobar',
      ],
      [
        '#conjunction' => 'OR',
        '#negation' => TRUE,
        'bar',
        'fooblob',
      ],
    ];
    $results = $this->buildSearch($keys)->execute();

    // $this->assertResults([4], $results, 'Complex search 1');
    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('name', 'bar');
    $conditions->addCondition('body', 'bar');
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertResults([
      1,
      2,
      3,
      5,
    ], $results, 'Search with multi-field fulltext filter');

    $results = $this->buildSearch()
      ->addCondition('keywords', ['grape', 'apple'], 'IN')
      ->execute();
    $this->assertResults([2, 3, 4, 5], $results, 'Query with IN filter');

    $results = $this->buildSearch()->addCondition('keywords', [
      'grape',
      'apple',
    ], 'NOT IN')->execute();
    $this->assertResults([1], $results, 'Query with NOT IN filter');

    $results = $this->buildSearch()->addCondition('width', [
      '0.9',
      '1.5',
    ], 'BETWEEN')->execute();
    $this->assertResults([4], $results, 'Query with BETWEEN filter');

    $results = $this->buildSearch()
      ->addCondition('width', ['0.9', '1.5'], 'NOT BETWEEN')
      ->execute();
    $this->assertResults([
      1,
      2,
      3,
      5,
    ], $results, 'Query with NOT BETWEEN filter');

    $results = $this->buildSearch()
      ->setLanguages(['und', 'en'])
      ->addCondition('keywords', ['grape', 'apple'], 'IN')
      ->execute();
    $this->assertResults([2, 3, 4, 5], $results, 'Query with IN filter');

    $results = $this->buildSearch()
      ->setLanguages(['und'])
      ->execute();
    $this->assertResults([], $results, 'Query with languages');

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR')
      ->addCondition('search_api_language', 'und')
      ->addCondition('width', ['0.9', '1.5'], 'BETWEEN');
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertResults([4], $results, 'Query with _language filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_language', 'und')
      ->addCondition('width', ['0.9', '1.5'], 'BETWEEN')
      ->execute();
    $this->assertResults([], $results, 'Query with _language filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_language', ['und', 'en'], 'IN')
      ->addCondition('width', ['0.9', '1.5'], 'BETWEEN')
      ->execute();
    $this->assertResults([4], $results, 'Query with _language filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_language', ['und', 'de'], 'NOT IN')
      ->addCondition('width', ['0.9', '1.5'], 'BETWEEN')
      ->execute();
    $this->assertResults([4], $results, 'Query with _language "NOT IN" filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_id', $this->getItemIds([1])[0])
      ->execute();
    $this->assertResults([1], $results, 'Query with _id filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_id', $this->getItemIds([2, 4]), 'NOT IN')
      ->execute();
    $this->assertResults([1, 3, 5], $results, 'Query with _id "NOT IN" filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_id', $this->getItemIds([3])[0], '>')
      ->execute();
    $this->assertResults([
      4,
      5,
    ], $results, 'Query with _id "greater than" filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_datasource', 'foobar')
      ->execute();
    $this->assertResults([], $results, 'Query for a non-existing datasource');

    $results = $this->buildSearch()
      ->addCondition('search_api_datasource', [
        'foobar',
        'entity:entity_test_mulrev_changed',
      ], 'IN')
      ->execute();
    $this->assertResults([
      1,
      2,
      3,
      4,
      5,
    ], $results, 'Query with _id "IN" filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_datasource', [
        'foobar',
        'entity:entity_test_mulrev_changed',
      ], 'NOT IN')
      ->execute();
    $this->assertResults([], $results, 'Query with _id "NOT IN" filter');

    // For a query without keys, all of these except for the last one should
    // have no effect. Therefore, we expect results with IDs in descending
    // order.
    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('search_api_relevance')
      ->sort('search_api_datasource', QueryInterface::SORT_DESC)
      ->sort('search_api_language')
      ->sort('search_api_id', QueryInterface::SORT_DESC)
      ->execute();
    $this->assertResults([5, 4, 3, 2, 1], $results, 'Query with magic sorts');

    // @codingStandardsIgnoreEnd
  }

  /**
   * {@inheritdoc}
   */
  protected function checkServerBackend() {
  }

  /**
   * {@inheritdoc}
   */
  protected function updateIndex() {
  }

  /**
   * {@inheritdoc}
   */
  protected function checkSecondServer() {
  }

  /**
   * {@inheritdoc}
   */
  protected function checkModuleUninstall() {
  }

}

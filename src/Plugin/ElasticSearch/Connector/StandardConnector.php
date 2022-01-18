<?php

namespace Drupal\search_api_elastic\Plugin\ElasticSearch\Connector;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\search_api_elastic\Connector\ElasticSearchConnectorInterface;
// use Elastica\Client;
// use ElasticSearch\ClientBuilder;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;

/**
 * Provides a standard ElasticSearch connector.
 *
 * @ElasticSearchConnector(
 *   id = "standard",
 *   label = @Translation("Standard"),
 *   description = @Translation("A standard connector without authentication")
 * )
 */
class StandardConnector extends PluginBase implements ElasticSearchConnectorInterface {

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(): string {
    return (string) $this->configuration['url'];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(): Client {
    // We only support one host.
    return ClientBuilder::create()
      ->setHosts([$this->configuration['url']])
      ->build();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'url' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ElasticSearch URL'),
      '#description' => $this->t('The URL of your ElasticSearch server, e.g. <code>http://127.0.0.1</code> or <code>https://www.example.com:443</code>.'),
      '#default_value' => $this->configuration['url'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $url = $form_state->getValue('url');
    if (!UrlHelper::isValid($url)) {
      $form_state->setErrorByName('url', $this->t("Invalid URL"));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['url'] = $form_state->getValue('url');
  }

}

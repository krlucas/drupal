<?php

namespace Drupal\filter\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\filter\Plugin\FilterBase;
use Drupal\Core\Render\RenderContext;
use Drupal\filter\FilterProcessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\filter\Exception\EntityNotFoundException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\filter\Exception\RecursiveRenderingException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter to display embedded entities based on data attributes.
 *
 * @Filter(
 *   id = "entity_embed",
 *   title = @Translation("Display embedded entities"),
 *   description = @Translation("Embeds entities using data attributes: data-entity-type, data-entity-uuid, and data-view-mode."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE
 * )
 */
class FilterEntityEmbed extends FilterBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs an EntityEmbedFilter object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, TranslationInterface $string_translation, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->stringTranslation = $string_translation;
    $this->logger = $logger_factory->get('filter');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('string_translation'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $document = Html::load($text);
    $metadata = [];

    foreach ($this->loadDrupalEntityElements($document) as $node) {
      // @todo A guard clause should be added here to prevent an entity from
      // referencing itself. Maybe based on the route context?
      try {
        $entity_type = $node->getAttribute('data-entity-type');
        $entity = $this->loadEntity($entity_type, $node);

        if (!$entity) {
          throw new EntityNotFoundException(
            sprintf('Unable to load embedded %s entity %s.', $entity_type, $id)
          );
        }

        static $depth = 0;
        $depth++;

        if ($depth > 2) {
          throw new RecursiveRenderingException(
            sprintf('Recursive rendering detected when rendering embedded %s entity %s.', $entity_type, $entity->id())
          );
        }

        $context = $this->getNodeAttributesAsArray($node);
        $context += ['data-langcode' => $langcode];

        $build = $this->buildRenderArray($entity, $context);
        $output = $this->renderer->executeInRenderContext(new RenderContext(), function () use ($build) {
          return $this->renderer->render($build);
        });
        $metadata[] = BubbleableMetadata::createFromRenderArray($build);

        $depth--;
      }
      catch (\Exception $e) {
        $this->logger->error($e->getMessage());
        $output = $this->t('An error occured while loading the entity: @message', [
          '@message' => $e->getMessage(),
        ]);
      }

      $this->replaceNodeContent($node, $output);
    }

    $result = new FilterProcessResult(Html::serialize($document));
    $this->mergeResultMetadata($result, $metadata);

    return $result;
  }

  /**
   * Build a render array for the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be rendered.
   * @param array $context
   *   (optional) Array of context values, corresponding to the attributes on
   *   the embed HTML tag.
   *
   * @return array
   *   A render array.
   */
  public function buildRenderArray(EntityInterface $entity, array $context = []) {
    // Merge in default attributes.
    $context += [
      'data-entity-type' => $entity->getEntityTypeId(),
      'data-entity-uuid' => $entity->uuid(),
      'data-entity-embed-display' => 'entity_reference:entity_reference_entity_view',
      'data-entity-embed-display-settings' => [],
    ];

    // The caption text is double-encoded, so decode it here.
    if (isset($context['data-caption'])) {
      $context['data-caption'] = Html::decodeEntities($context['data-caption']);
    }

    // Build and render the Entity Embed Display plugin, allowing modules to
    // alter the result before rendering.
    $build = [
      '#theme_wrappers' => ['entity_embed'],
      '#attributes' => ['class' => ['embedded-entity']],
      // '#entity' => $entity,
      '#context' => $context,
    ];
    $build += $this->entityTypeManager
      ->getViewBuilder($entity->getEntityTypeId())
      ->view($entity, $context['data-entity-embed-display']);
    unset($build['#theme']);

    // Maintain data-align if it is there.
    if (isset($context['data-align'])) {
      $build['#attributes']['data-align'] = $context['data-align'];
    }
    elseif ((isset($context['class']))) {
      $build['#attributes']['class'][] = $context['class'];
    }

    // Maintain data-caption if it is there.
    if (isset($context['data-caption'])) {
      $build['#attributes']['data-caption'] = $context['data-caption'];
    }

    // Make sure that access to the entity is respected.
    $build['#access'] = $entity->access('view', NULL, TRUE);

    return $build;
  }

  /**
   * Replace the contents of a DOMNode.
   *
   * @param \DOMNode $node
   *   A DOMNode object.
   * @param string $content
   *   The text or HTML that will replace the contents of $node.
   */
  protected function replaceNodeContent(\DOMNode &$node, $content) {
    if (strlen($content)) {
      // Load the content into a new DOMDocument and retrieve the DOM nodes.
      $replacement_nodes = Html::load($content)
        ->getElementsByTagName('body')
        ->item(0)
        ->childNodes;
    }
    else {
      $replacement_nodes = [$node->ownerDocument->createTextNode('')];
    }

    foreach ($replacement_nodes as $replacement_node) {
      // Import the replacement node from the new DOMDocument into the original
      // one, importing also the child nodes of the replacement node.
      $replacement_node = $node->ownerDocument->importNode($replacement_node, TRUE);
      $node->parentNode->insertBefore($replacement_node, $node);
    }

    $node->parentNode->removeChild($node);
  }

  /**
   * Convert the attributes on a DOMNode object to an array.
   *
   * This will also un-serialize any attribute values stored as JSON.
   *
   * @param \DOMNode $node
   *   A DOMNode object.
   *
   * @return array
   *   The attributes as an associative array, keyed by the attribute names.
   */
  public function getNodeAttributesAsArray(\DOMNode $node) {
    $return = [];

    // Convert the data attributes to the context array.
    foreach ($node->attributes as $attribute) {
      $key = $attribute->nodeName;
      $return[$key] = $attribute->nodeValue;

      // Check for JSON-encoded attributes.
      $data = json_decode($return[$key], TRUE, 10);
      if ($data !== NULL && json_last_error() === JSON_ERROR_NONE) {
        $return[$key] = $data;
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    if ($long) {
      return $this->t('
        <p>You can embed entities. Additional properties can be added to the embed tag like data-caption and data-align if supported. Example:</p>
        <code>&lt;drupal-entity data-entity-type="node" data-entity-uuid="07bf3a2e-1941-4a44-9b02-2d1d7a41ec0e" data-view-mode="teaser" /&gt;</code>');
    }
    else {
      return $this->t('You can embed entities.');
    }
  }

  /**
   * Load the <drupal-entity /> elements from the document.
   *
   * @param \DOMDocument $document
   *
   * @return \DOMNode[]
   *   An array of \DOMNode objects or an empty array if the query failed.
   */
  protected function loadDrupalEntityElements(\DOMDocument $document) {
    $xpath = new \DOMXPath($document);
    $nodes = $xpath->query('//drupal-entity[@data-entity-type and @data-entity-uuid and @data-entity-embed-display]');

    return $nodes ?: [];
  }

  /**
   * Load the entity by it's uuid specific in the \DOMNode.
   *
   * @param string $entity_type
   *   The machine name of the entity type.
   * @param \DOMNode $node
   *
   * @return \Drupal\Core\Entity\EntityInterface|FALSE
   *   The Entity object is returned if it exists or FALSE if the entity does
   *   not exist.
   */
  protected function loadEntity($entity_type, \DOMNode $node) {
    $uuid = $node->getAttribute('data-entity-uuid');

    $entities = $this->entityTypeManager
      ->getStorage($entity_type)
      ->loadByProperties(['uuid' => $uuid]);

    return current($entities);
  }

  /**
   * Merge the rendered entities' metadata into the filter result.
   *
   * @param FilterProcessResult $result
   * @param array $result_metadata
   *   An array of metadata of the rendered entities.
   */
  protected function mergeResultMetadata(FilterProcessResult $result, array $result_metadata) {
    foreach ($result_metadata as $metadata) {
      $result->merge($metadata);
    }
  }

}

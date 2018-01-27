<?php

namespace Drupal\media\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\media\Exception\MediaNotFoundException;
use Drupal\media\Exception\RecursiveRenderingException;

/**
 * Provides a filter to display embedded entities based on data attributes.
 *
 * @Filter(
 *   id = "media_embed",
 *   title = @Translation("Display embedded media entities"),
 *   description = @Translation("Embeds media entities using data attributes: data-entity-type, data-entity-uuid, and data-view-mode."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE
 * )
 */
class MediaEmbedFilter extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a MediaEmbedFilter object.
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
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
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    if (strpos($text, 'data-entity-type') !== FALSE && (strpos($text, 'data-entity-embed-display') !== FALSE || strpos($text, 'data-view-mode') !== FALSE)) {
      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);

      foreach ($xpath->query('//drupal-entity[@data-entity-type and (@data-entity-uuid or @data-entity-id) and (@data-entity-embed-display or @data-view-mode)]') as $node) {
        /** @var \DOMElement $node */
        $entity_type = $node->getAttribute('data-entity-type');
        $entity = NULL;
        $entity_output = '';

        // data-entity-embed-settings is deprecated, make sure we convert it to
        // data-entity-embed-display-settings.
        if (($settings = $node->getAttribute('data-entity-embed-settings')) && !$node->hasAttribute('data-entity-embed-display-settings')) {
          $node->setAttribute('data-entity-embed-display-settings', $settings);
          $node->removeAttribute('data-entity-embed-settings');
        }

        try {
          // Load the entity either by UUID (preferred) or ID.
          $id = NULL;
          $entity = NULL;
          if ($id = $node->getAttribute('data-entity-uuid')) {
            $entity = $this->entityTypeManager->getStorage($entity_type)
              ->loadByProperties(['uuid' => $id]);
            $entity = current($entity);
          }
          else {
            $id = $node->getAttribute('data-entity-id');
            $entity = $this->entityTypeManager->getStorage($entity_type)->load($id);
          }

          if ($entity) {
            // Protect ourselves from recursive rendering.
            static $depth = 0;
            $depth++;
            if ($depth > 20) {
              throw new RecursiveRenderingException(sprintf('Recursive rendering detected when rendering embedded %s entity %s.', $entity_type, $entity->id()));
            }

            // If a UUID was not used, but is available, add it to the HTML.
            if (!$node->getAttribute('data-entity-uuid') && $uuid = $entity->uuid()) {
              $node->setAttribute('data-entity-uuid', $uuid);
            }

            $context = $this->getNodeAttributesAsArray($node);
            $context += array('data-langcode' => $langcode);
            $build = $this->buildRenderArray($entity, $context);
            // We need to render the embedded entity:
            // - without replacing placeholders, so that the placeholders are
            //   only replaced at the last possible moment. Hence we cannot use
            //   either renderPlain() or renderRoot(), so we must use render().
            // - without bubbling beyond this filter, because filters must
            //   ensure that the bubbleable metadata for the changes they make
            //   when filtering text makes it onto the FilterProcessResult
            //   object that they return ($result). To prevent that bubbling, we
            //   must wrap the call to render() in a render context.
            $entity_output = $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$build) {
              return $this->renderer->render($build);
            });
            $result = $result->merge(BubbleableMetadata::createFromRenderArray($build));

            $depth--;
          }
          else {
            throw new MediaNotFoundException(sprintf('Unable to load embedded %s media entity %s.', $entity_type, $id));
          }
        }
        catch (\Exception $e) {
          watchdog_exception('media_embed', $e);
        }

        $this->replaceNodeContent($node, $entity_output);
      }

      $result->setProcessedText(Html::serialize($dom));
    }

    return $result;
  }

  /**
   * Build a render array for the media entity.
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
      '#theme_wrappers' => ['media_embed'],
      '#attributes' => ['class' => ['embedded-media']],
      '#entity' => $entity,
      '#context' => $context,
    ];
    $build += $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())->view($entity, 'default');
    // We're providing embed-specific theming, so don't use media's standard.
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
      $replacement_nodes = Html::load($content)->getElementsByTagName('body')
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

}

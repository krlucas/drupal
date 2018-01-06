<?php

namespace Drupal\Tests\media\Kernel;

use Drupal\filter\FilterPluginCollection;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Tests the media content input filter.
 *
 * @group media
 */
class MediaEmbedFilterTest extends MediaKernelTestBase {

  /**
   * @var \Drupal\filter\Plugin\FilterInterface[]
   */
  protected $filters;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['filter'];

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
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $manager = $this->container->get('plugin.manager.filter');
    $bag = new FilterPluginCollection($manager, []);
    $this->filters = $bag->getAll();
  }

  public function testMediaEmbedFilter() {
    $filter = $this->filters['media_embed'];

    $test = function ($input) use ($filter) {
      return $filter->process($input, 'und');
    };

    // Create media type, media entity, and rendered entity.
    $mediaType = $this->createMediaType('image');
    $media = $this->generateMedia('test.patch', $mediaType);
    $data_entity_uuid = $media->uuid();
    $build = $this->entityTypeManager->getViewBuilder($media->getEntityTypeId())->view($media, 'default');
    $rendered_media = $this->renderer->render($build);

    // Test data-entity-embed-display attribute.
    $input = '<drupal-entity data-entity-type="image" data-entity-embed-display="view_mode:image.default" data-entity-uuid="' . $data_entity_uuid . '"></drupal-entity>';
    $expected = $rendered_media;
    $this->assertSame($expected, $test($input)->getProcessedText());

    // Test data-view-mode attribute.
    $input = '<drupal-entity data-entity-type="image" data-view-mode="default" data-entity-uuid="' . $data_entity_uuid . '"></drupal-entity>';
    $expected = $rendered_media;
    $this->assertSame($expected, $test($input)->getProcessedText());
  }

}

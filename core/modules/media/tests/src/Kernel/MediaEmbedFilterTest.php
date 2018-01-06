<?php

namespace Drupal\Tests\media\Kernel;

use Drupal\filter\FilterPluginCollection;
use Drupal\media\Entity\Media;

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
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $manager = $this->container->get('plugin.manager.filter');
    $bag = new FilterPluginCollection($manager, []);
    $this->filters = $bag->getAll();
  }

  /**
   * Tests the media embed filter.
   */
  public function testMediaEmbedFilter() {
    $filter = $this->filters['media_embed'];

    $test = function ($input) use ($filter) {
      return $filter->process($input, 'und');
    };

    // Create media entity.
    $media = Media::create(['bundle' => $this->testMediaType->id()]);
    $media->save();
    $media_uuid = $media->uuid();

    // Render entity.
    $build = entity_view($media, 'default', 'und');
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    $rendered_media = $renderer->renderPlain($build);

    // Test data-entity-embed-display attribute.
    $input = '<drupal-entity data-entity-type="image" data-entity-embed-display="view_mode:image.default" data-entity-uuid="' . $media_uuid . '"></drupal-entity>';
    $expected = $rendered_media;
    $this->assertSame($expected, $test($input)->getProcessedText());

    // Test data-view-mode attribute.
    $input = '<drupal-entity data-entity-type="image" data-view-mode="default" data-entity-uuid="' . $media_uuid . '"></drupal-entity>';
    $expected = $rendered_media;
    $this->assertSame($expected, $test($input)->getProcessedText());
  }

}

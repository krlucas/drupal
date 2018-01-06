<?php


namespace Drupal\Tests\media\Kernel;

/**
 * Tests the media content input filter.
 *
 * @group media
 */
class MediaFilterTest extends MediaKernelTestBase {

  public function testFilter() {
    $mediaType = $this->createMediaType('image');
    $filepath = \Drupal::root() . '/' . drupal_get_path('module', 'media') . '/tests/fixtures/example_1.jpeg';
    $media = $this->generateMedia('test.patch', $mediaType);

  }

}

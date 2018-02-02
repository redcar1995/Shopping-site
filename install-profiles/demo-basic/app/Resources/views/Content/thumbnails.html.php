<?php
/**
 * @var \Pimcore\Templating\PhpEngine $this
 * @var \Pimcore\Templating\PhpEngine $view
 * @var \Pimcore\Templating\GlobalVariables $app
 */

$this->extend('layout.html.php');

?>

<?php
use Pimcore\Model\Asset;

?>

<?= $this->template('Includes/content-default.html.php') ?>

<?php
// this is just used for demonstration
$image = Asset::getById(53);
?>

<h2>
    <?= $this->translate('Original Dimensions of the Image'); ?>:
    <?php
    echo $image->getWidth() . 'x' . $image->getHeight();
    ?>
</h2>

<section class="thumbnail-examples">
    <?php
    $thumbnails = [
        'Cover'                 => 'exampleCover',
        'Contain'               => 'exampleContain',
        'Frame'                 => 'exampleFrame',
        'Rotate'                => 'exampleRotate',
        'Resize'                => 'exampleResize',
        'Scale by Width'        => 'exampleScaleWidth',
        'Scale by Height'       => 'exampleScaleHeight',
        'Contain &amp; Overlay' => 'exampleOverlay',
        'Rounded Corners'       => 'exampleCorners',
        'Sepia'                 => 'exampleSepia',
        'Grayscale'             => 'exampleGrayscale',
        'Mask'                  => 'exampleMask',
        'Combined 1'            => 'exampleCombined1',
        'Combined 2'            => 'exampleCombined2',
    ];
    ?>

    <?php
    $i = 0;
    foreach ($thumbnails as $title => $name): ?>

        <?php if ($i % 3 === 0): ?>
            <div class="row">
        <?php endif; ?>

        <div class="col-lg-4">
            <?php
            $thumbnail = $image->getThumbnail($name);
            ?>

            <div class="img-container">
                <?= $thumbnail->getHTML() ?>
            </div>

            <h3><?= $this->translate($title); ?></h3>

            <div>
                <?= $this->translate('Dimensions'); ?>:
                <?php
                echo $thumbnail->getWidth() . 'x' . $thumbnail->getHeight()
                ?>
            </div>
        </div>

        <?php
        $i++;
        if ($i % 3 === 0 || $i >= count($thumbnails)): ?>
            </div>
        <?php endif; ?>

    <?php endforeach; ?>
</section>

<?= $this->areablock('content_bottom'); ?>

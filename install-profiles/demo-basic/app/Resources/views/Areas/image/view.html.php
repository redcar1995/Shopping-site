<?php
/**
 * @var \Pimcore\Templating\PhpEngine $this
 * @var \Pimcore\Templating\PhpEngine $view
 * @var \Pimcore\Templating\GlobalVariables $app
 */
?>

<section class="area-image">

    <?php if(!$this->editmode) { ?>
        <a href="<?= $this->image("image")->getThumbnail("galleryLightbox"); ?>" class="thumbnail">
    <?php } ?>

        <?= $this->image("image", [
            "thumbnail" => "content"
        ]); ?>

    <?php if(!$this->editmode) { ?>
        </a>
    <?php } ?>

</section>

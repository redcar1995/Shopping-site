<?php
/**
 * @var \Pimcore\Templating\PhpEngine $this
 * @var \Pimcore\Templating\PhpEngine $view
 * @var \Pimcore\Templating\GlobalVariables $app
 */

$this->extend('layout.html.php');

?>

<?= $this->template('Includes/content-headline.html.php'); ?>
<?= $this->areablock('content'); ?>

<?php
/** @var \Pimcore\Model\DataObject\News $news */
foreach ($this->news as $news) { ?>
    <div class="media">

        <?php
        $detailLink = $this->path('news', [
            'id'     => $news->getId(),
            'text'   => $news->getTitle(),
            'prefix' => $this->document->getFullPath(),
        ]);
        ?>

        <?php if($news->getImage_1()) { ?>
            <a class="pull-left" href="<?= $detailLink; ?>">
                <?= $news->getImage_1()->getThumbnail("newsList")->getHTML(["class" => "media-object"]); ?>
            </a>
        <?php } ?>

        <div class="media-body">
            <h4 class="media-heading">
                <a href="<?= $detailLink; ?>"><?= $news->getTitle(); ?></a>
                <br />
                <small><i class="glyphicon glyphicon-calendar"></i> <?= $news->getDate()->format("d/m/Y"); ?></small>
            </h4>
            <?= $news->getShortText(); ?>
        </div>
    </div>
<?php } ?>

<!-- pagination start -->
<?= $this->render(
    "Includes/paging.html.php",
    get_object_vars($this->news->getPages("Sliding"))
); ?>
<!-- pagination end -->

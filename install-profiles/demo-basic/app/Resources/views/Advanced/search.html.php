<?php
/**
 * @var \Pimcore\Templating\PhpEngine $this
 * @var \Pimcore\Templating\PhpEngine $view
 * @var \Pimcore\Templating\GlobalVariables $app
 */

$this->extend('layout.html.php');

?>

<?php
    $queryString = $this->queryString;
?>

<?= $this->template('Includes/content-headline.html.php'); ?>

<?php if(!$queryString) { ?>
    <?= $this->areablock('content'); ?>
<?php } ?>

<div>

    <form class="form-inline" role="form">
        <div class="form-group">
            <input type="text" name="q" class="form-control" placeholder="<?= $this->translate("Keyword"); ?>" value="<?= $this->escape($queryString ?: '') ?>">
        </div>
        <button type="submit" name="submit" class="btn btn-default"><?= $this->translate("Search"); ?></button>
    </form>

    <?php if ($this->paginator) { ?>

        <?php $facets = $this->result->getFacets(); ?>
        <?php if(!empty($facets)) { ?>
            <div style="margin-top: 20px">
                Facets:
                <?php foreach ($facets as $label => $anchor) { ?>
                    <a class="btn btn-default btn-xs" href="<?= $this->pimcoreUrl(['facet' => $label, "page" => null]); ?>"><?= $anchor ?></a>
                <?php } ?>
            </div>
            <hr />
        <?php } ?>

        <?php foreach ($this->paginator as $item) { ?>
            <!-- see class Pimcore_Google_Cse_Item for all possible properties -->
            <div class="media <?= $item->getType(); ?>">
                <?php if($item->getImage()) { ?>
                    <!-- if an image is present this can be simply a string or an internal asset object -->

                    <?php if($item->getImage() instanceof Asset) { ?>
                        <a class="pull-left" href="<?= $item->getLink() ?>">
                            <?= $item->getImage()->getThumbnail("newsList")->getHTML(array(
                                "class" => "media-object"
                            )); ?>
                        </a>
                    <?php } else { ?>
                        <a class="pull-left" href="<?= $item->getLink() ?>">
                            <img width="64" src="<?= $item->getImage() ?>" />
                        </a>
                    <?php } ?>
                <?php } ?>


                <div class="media-body">
                    <h4 class="media-heading">
                        <a href="<?= $item->getLink() ?>">
                            <!-- if there's a document set for this result use the original title without suffixes ... -->
                            <!-- the same can be done with the description and every other element relating to the document -->
                            <?php if($item->getDocument() && $item->getDocument()->getTitle()) { ?>
                                <?= $item->getDocument()->getTitle() ?>
                            <?php } else { ?>
                                <?= $item->getTitle() ?>
                            <?php } ?>
                        </a>
                    </h4>
                    <?= $item->getHtmlSnippet() ?>
                    <br />
                    <small><?= $item->getHtmlFormattedUrl(); ?></small>
                </div>
            </div>
        <?php } ?>
        <?= $this->render("Includes/paging.html.php", get_object_vars($this->paginator->getPages("Sliding"))); ?>
    <?php } else if ($queryString) { ?>
        <div class="alert alert-error" style="margin-top: 30px">
            Sorry, something went wrong ...
        </div>
    <?php } else { ?>
        <div class="alert alert-info" style="margin-top: 30px">
            Type your keyword and press search
        </div>
    <?php } ?>
</div>



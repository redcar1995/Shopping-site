<?php
/**
 * @var \Pimcore\Templating\PhpEngine $this
 * @var \Pimcore\Templating\PhpEngine $view
 * @var \Pimcore\Templating\GlobalVariables $app
 */
?>

<section class="area-standard-teaser-row">
    <div class="row">
        <?php for($t=0; $t<3; $t++) { ?>
            <div class="col-sm-4">
                <?php if($this->editmode) { ?>
                    <div class="editmode-label">
                        <label>Type:</label>
                        <?= $this->select("type_".$t, [
                            "width" => 90,
                            "reload" => true,
                            "store" => [["direct","direct"], ["snippet","snippet"]]
                        ]); ?>
                    </div>
                <?php } ?>
                <?php
                    $type = $this->select("type_".$t)->getData();
                    if($type == "direct") {
                        echo $this->template("Snippets/standard-teaser.html.php", ["suffix" => $t+1]);
                    } else {
                        echo $this->snippet("teaser_".$t);
                    }
                ?>
            </div>
        <?php } ?>
    </div>
</section>

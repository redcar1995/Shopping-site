<?php
/**
 * @var \Pimcore\Templating\PhpEngine $this
 * @var \Pimcore\Templating\PhpEngine $view
 * @var \Pimcore\Templating\GlobalVariables $app
 */

$this->extend('layout.html.php');

?>

<?= $this->template('Includes/content-default.html.php') ?>

<?php if ($this->editmode): ?>

    <style type="text/css">
        .alert {
            margin-top: 60px;
        }
    </style>

    <div class="editable-roundup">

        <div class="alert alert-info">
            <h3>Checkbox</h3>
        </div>
        <?= $this->checkbox("myCheckbox") ?>

        <div class="clearfix"></div>

        <div class="alert alert-info">
            <h3>Date</h3>
        </div>
        <?= $this->date("myDate"); ?>

        <div class="alert alert-info">
            <h3>Single Relation</h3>
        </div>
        <?= $this->href("myHref"); ?>

        <div class="alert alert-info">
            <h3>Image</h3>
        </div>
        <?= $this->image("myImage"); ?>

        <div class="alert alert-info">
            <h3>Input</h3>
        </div>
        <?= $this->input("myInput"); ?>

        <div class="alert alert-info">
            <h3>Link</h3>
        </div>
        <?= $this->link("myLink"); ?>

        <div class="alert alert-info">
            <h3>Multiple Relations</h3>
        </div>
        <?= $this->multihref("myMultiHref"); ?>

        <div class="alert alert-info">
            <h3>Multi-Select</h3>
        </div>
        <?= $this->multiselect("myMultiselect", [
            "width"  => 200,
            "height" => 100,
            "store"  => [
                ["value1", "Text 1"],
                ["value2", "Text 2"],
                ["value3", "Text 3"],
                ["value4", "Text 4"],
            ]
        ]) ?>

        <div class="alert alert-info">
            <h3>Numeric</h3>
        </div>
        <?= $this->numeric("myNumeric"); ?>

        <div class="alert alert-info">
            <h3>Renderlet (drop an asset folder)</h3>
        </div>
        <?= $this->renderlet("myRenderlet", [
            "controller" => "content",
            "action"     => "gallery-renderlet"
        ]); ?>

        <div class="alert alert-info">
            <h3>Select</h3>
        </div>
        <?= $this->select("mySelect", [
            "store" => [
                ["option1", "Option One"],
                ["option2", "Option Two"],
                ["option3", "Option Three"]
            ]
        ]); ?>

        <div class="alert alert-info">
            <h3>Snippet</h3>
            <p>drop a document snippet here</p>
        </div>
        <?= $this->snippet("mySnippet") ?>

        <div class="alert alert-info">
            <h3>Table</h3>
            <p>of course you can create tables in the wysiwyg too</p>
        </div>
        <?= $this->table("tableName", [
            "width"    => 700,
            "height"   => 400,
            "defaults" => [
                "cols" => 6,
                "rows" => 10,
                "data" => [
                    ["Value 1", "Value 2", "Value 3"],
                    ["this", "is", "test"]
                ]
            ]
        ]) ?>

        <div class="alert alert-info">
            <h3>Textarea</h3>
        </div>
        <?= $this->textarea("myTextarea") ?>

        <div class="alert alert-info">
            <h3>Video</h3>
        </div>
        <?= $this->video("myVideo", [
            "attributes" => [
                "class"      => "video-js vjs-default-skin vjs-big-play-centered",
                "data-setup" => "{}"
            ],
            "thumbnail"  => "content",
            "height"     => 380
        ]); ?>

        <div class="alert alert-info">
            <h3>WYSIWYG</h3>
        </div>
        <?= $this->wysiwyg("myWysiwyg"); ?>
    </div>

<?php endif; ?>

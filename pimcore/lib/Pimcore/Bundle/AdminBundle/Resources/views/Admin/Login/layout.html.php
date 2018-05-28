<?php
/** @var $view \Pimcore\Templating\PhpEngine */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Welcome to Pimcore!</title>

    <meta charset="UTF-8">
    <meta name="robots" content="noindex, follow"/>

    <link rel="icon" type="image/png" href="/pimcore/static6/img/favicon/favicon-32x32.png"/>

    <link rel="stylesheet" href="/pimcore/static6/css/login.css" type="text/css"/>
    <script src="/pimcore/static6/js/lib/jquery-3.3.1.min.js"></script>

    <?php foreach ($this->pluginCssPaths as $pluginCssPath): ?>
        <link rel="stylesheet" type="text/css" href="<?= $pluginCssPath ?>?_dc=<?= $pluginDcValue; ?>"/>
    <?php endforeach; ?>
</head>
<body>

<?php $view->slots()->output('_content') ?>

<div id="footer">
    &copy; 2009-<?= date("Y") ?> <a href="http://www.pimcore.org/">pimcore GmbH</a>, a proud member of the
    <a href="http://www.elements.at/">elements group</a>
</div>

<?php $view->slots()->output('below_footer') ?>

</body>
</html>

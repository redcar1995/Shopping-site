<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">

    <style type="text/css">

        /* hide from ie on mac \*/
        html {
            height: 100%;
            overflow: hidden;
        }
        /* end hide */

        body {
            height: 100%;
            margin: 0;
            padding: 0;
            background: #000;
        }

        #videoContainer {
            text-align: center;
            position: absolute;
            top:50%;
            margin-top: -200px;
            width: 100%;
        }

        video {

        }

    </style>

</head>

<body>


<?php
    $previewImage = "";
    if(\Pimcore\Video::isAvailable()) {
        $previewImage = "/admin/asset/get-video-thumbnail?id=" . $this->asset->getId() . "&treepreview=true";
    }
?>

<div id="videoContainer">
    <video id="video" controls="controls" height="400" poster="<?= $previewImage ?>">
        <source src="/admin/asset/serve-video-preview?id=<?= $this->asset->getId() ?>" type="video/mp4" />
    </video>
</div>


</body>
</html>

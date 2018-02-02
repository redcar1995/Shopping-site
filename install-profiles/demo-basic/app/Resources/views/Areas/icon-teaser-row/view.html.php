<?php
/**
 * @var \Pimcore\Templating\PhpEngine $this
 * @var \Pimcore\Templating\PhpEngine $view
 * @var \Pimcore\Templating\GlobalVariables $app
 */
?>

<?php
    $icons = ["asterisk" => "2a", "plus" => "2b", "euro" => "20ac", "minus" => "2212", "cloud" => "2601", "envelope" => "2709", "pencil" => "270f", "glass" => "e001", "music" => "e002", "search" => "e003", "heart" => "e005", "star" => "e006", "star-empty" => "e007", "user" => "e008", "film" => "e009", "th-large" => "e010", "th" => "e011", "th-list" => "e012", "ok" => "e013", "remove" => "e014", "zoom-in" => "e015", "zoom-out" => "e016", "off" => "e017", "signal" => "e018", "cog" => "e019", "trash" => "e020", "home" => "e021", "file" => "e022", "time" => "e023", "road" => "e024", "download-alt" => "e025", "download" => "e026", "upload" => "e027", "inbox" => "e028", "play-circle" => "e029", "repeat" => "e030", "refresh" => "e031", "list-alt" => "e032", "lock" => "e033", "flag" => "e034", "headphones" => "e035", "volume-off" => "e036", "volume-down" => "e037", "volume-up" => "e038", "qrcode" => "e039", "barcode" => "e040", "tag" => "e041", "tags" => "e042", "book" => "e043", "bookmark" => "e044", "print" => "e045", "camera" => "e046", "font" => "e047", "bold" => "e048", "italic" => "e049", "text-height" => "e050", "text-width" => "e051", "align-left" => "e052", "align-center" => "e053", "align-right" => "e054", "align-justify" => "e055", "list" => "e056", "indent-left" => "e057", "indent-right" => "e058", "facetime-video" => "e059", "picture" => "e060", "map-marker" => "e062", "adjust" => "e063", "tint" => "e064", "edit" => "e065", "share" => "e066", "check" => "e067", "move" => "e068", "step-backward" => "e069", "fast-backward" => "e070", "backward" => "e071", "play" => "e072", "pause" => "e073", "stop" => "e074", "forward" => "e075", "fast-forward" => "e076", "step-forward" => "e077", "eject" => "e078", "chevron-left" => "e079", "chevron-right" => "e080", "plus-sign" => "e081", "minus-sign" => "e082", "remove-sign" => "e083", "ok-sign" => "e084", "question-sign" => "e085", "info-sign" => "e086", "screenshot" => "e087", "remove-circle" => "e088", "ok-circle" => "e089", "ban-circle" => "e090", "arrow-left" => "e091", "arrow-right" => "e092", "arrow-up" => "e093", "arrow-down" => "e094", "share-alt" => "e095", "resize-full" => "e096", "resize-small" => "e097", "exclamation-sign" => "e101", "gift" => "e102", "leaf" => "e103", "fire" => "e104", "eye-open" => "e105", "eye-close" => "e106", "warning-sign" => "e107", "plane" => "e108", "calendar" => "e109", "random" => "e110", "comment" => "e111", "magnet" => "e112", "chevron-up" => "e113", "chevron-down" => "e114", "retweet" => "e115", "shopping-cart" => "e116", "folder-close" => "e117", "folder-open" => "e118", "resize-vertical" => "e119", "resize-horizontal" => "e120", "hdd" => "e121", "bullhorn" => "e122", "bell" => "e123", "certificate" => "e124", "thumbs-up" => "e125", "thumbs-down" => "e126", "hand-right" => "e127", "hand-left" => "e128", "hand-up" => "e129", "hand-down" => "e130", "circle-arrow-right" => "e131", "circle-arrow-left" => "e132", "circle-arrow-up" => "e133", "circle-arrow-down" => "e134", "globe" => "e135", "wrench" => "e136", "tasks" => "e137", "filter" => "e138", "briefcase" => "e139", "fullscreen" => "e140", "dashboard" => "e141", "paperclip" => "e142", "heart-empty" => "e143", "link" => "e144", "phone" => "e145", "pushpin" => "e146", "usd" => "e148", "gbp" => "e149", "sort" => "e150", "sort-by-alphabet" => "e151", "sort-by-alphabet-alt" => "e152", "sort-by-order" => "e153", "sort-by-order-alt" => "e154", "sort-by-attributes" => "e155", "sort-by-attributes-alt" => "e156", "unchecked" => "e157", "expand" => "e158", "collapse-down" => "e159", "collapse-up" => "e160", "log-in" => "e161", "flash" => "e162", "log-out" => "e163", "new-window" => "e164", "record" => "e165", "save" => "e166", "open" => "e167", "saved" => "e168", "import" => "e169", "export" => "e170", "send" => "e171", "floppy-disk" => "e172", "floppy-saved" => "e173", "floppy-remove" => "e174", "floppy-save" => "e175", "floppy-open" => "e176", "credit-card" => "e177", "transfer" => "e178", "cutlery" => "e179", "header" => "e180", "compressed" => "e181", "earphone" => "e182", "phone-alt" => "e183", "tower" => "e184", "stats" => "e185", "sd-video" => "e186", "hd-video" => "e187", "subtitles" => "e188", "sound-stereo" => "e189", "sound-dolby" => "e190", "sound-5-1" => "e191", "sound-6-1" => "e192", "sound-7-1" => "e193", "copyright-mark" => "e194", "registration-mark" => "e195", "cloud-download" => "e197", "cloud-upload" => "e198", "tree-conifer" => "e199", "tree-deciduous" => "e200"];

    $iconStore = [];
    foreach ($icons as $name => $code) {
        $iconStore[] = array($name, "&#x" . $code . ";");
    }
?>
<section class="area-icon-teaser-row">
    <div class="row">
        <?php for($t=0; $t<3; $t++) { ?>
            <div class="col-sm-4">
                <div class="teaser-icon">
                    <div class="icon">
                        <div class="image">
                            <i class="glyphicon glyphicon-<?= $this->select("icon_".$t)->getData() ?>"></i>

                            <?php if($this->editmode) { ?>
                                <?= $this->select("icon_".$t, [
                                    "width" => 30,
                                    "store" => $iconStore,
                                    "reload" => true,
                                    "listClass" => "glyphicon-selection", // Ext 3.4
                                    "listConfig" => ["cls" => "glyphicon-selection"], // Ext 6
                                ]); ?>
                            <?php } ?>
                        </div>
                        <div class="info">
                            <h3 class="title"><?= $this->input("title_" . $t) ?></h3>
                            <p>
                                <?= $this->textarea("text_" . $t) ?>
                            </p>
                            <div class="more">
                                <?= $this->link("link_" . $t, ["class" => "btn btn-default"]) ?>
                            </div>
                        </div>
                    </div>
                    <div class="space"></div>
                </div>
            </div>
        <?php } ?>
    </div>
</section>

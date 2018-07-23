/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */


$(document).ready(function ($) {
    var documentBody = $('body');

    /**
     * Init Navigation Tabs
     */
    $('#tabs').tab();

    /**
     * Init Status Messages Fadeout
     */
    var initFadeOut = function () {
        setTimeout(function () {
            $('.js-fadeout').fadeOut('fast');
        }, 5000);
    };

    initFadeOut();

    /**
     * Init Modal
     */
    documentBody.on('click', '.js-modal', function (e) {
        var selector = $(this).data('modal');
        $("#" + selector).modal({                    // wire up the actual modal functionality and show the dialog
            "backdrop": "static",
            "keyboard": true,
            "show": true                     // ensure the modal is shown immediately
        });
    });

    /**
     * Init Modal Loadings
     */
    documentBody.on('click', '.modal .js-loading', function (e) {
        var text = $(this).data('msg');
        $(this).parent().children().hide();
        $('.modal-footer').append(
            "<div class='text-left row'> <div class='col col-sm-12'> <span>"
            + text +
            "</span>&nbsp;<img class='pull-right' src='/bundles/pimcoreadmin/img/video-loading.gif' alt='loading' style='margin-right: 40px;'><div><div>"
        );
        return true;
    });

});
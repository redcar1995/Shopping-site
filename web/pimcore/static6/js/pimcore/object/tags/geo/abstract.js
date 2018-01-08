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
/*global google */
pimcore.registerNS('pimcore.object.tags.geo.abstract');
pimcore.object.tags.geo.abstract = Class.create(pimcore.object.tags.abstract, {

    initialize: function (data, fieldConfig) {
        this.data = data;
        this.fieldConfig = fieldConfig;

        // extend google maps to support the getBounds() method
        if (this.isMapsAvailable() && !google.maps.Polygon.prototype.getBounds) {

            google.maps.Polygon.prototype.getBounds = function(latLng) {

                var bounds = new google.maps.LatLngBounds();
                var paths = this.getPaths();
                var path;

                for (var p = 0; p < paths.getLength(); p++) {
                    path = paths.getAt(p);
                    for (var i = 0; i < path.getLength(); i++) {
                        bounds.extend(path.getAt(i));
                    }
                }

                return bounds;
            };
        }
    },

    isMapsAvailable: function() {
        if(typeof google != "undefined" && pimcore.settings.google_maps_api_key) {
            return true;
        }
        return false;
    },

    getErrorLayout: function() {
        this.component = new Ext.Panel({
            title: t("geo_error_title"),
            height: 370,
            width: 650,
            border: true,
            bodyStyle: "padding: 10px",
            html: '<span style="color:red">' + t("geo_error_message") + '</span>'
        });

        return this.component;
    },

    getGridColumnConfig: function(field) {
        return {
            text: ts(field.label),
            width: 150,
            sortable: false,
            dataIndex: field.key,
            renderer: function (key, value, metaData, record) {
                this.applyPermissionStyle(key, value, metaData, record);

                if(record.data.inheritedFields[key] && record.data.inheritedFields[key].inherited == true) {
                    metaData.tdCls += ' grid_value_inherited';
                }

                if (value) {
                    var width = 200;

                    var mapUrl = this.getMapUrl(field, value, width, 100);

                    return '<img src="' + mapUrl + '" />';
                }
            }.bind(this, field.key)
        };
    },

    getLayoutShow: function () {
        this.component = this.getLayoutEdit();
        this.component.disable();

        return this.component;
    },

    updatePreviewImage: function () {
        var width = Ext.get('google_maps_container_' + this.mapImageID).getWidth();

        if (width > 640) {
            width = 640;
        }
        if (width < 10) {
            window.setTimeout(this.updatePreviewImage.bind(this), 1000);
        }

        Ext.get('google_maps_container_' + this.mapImageID).dom.innerHTML =
            '<img align="center" width="' + width + '" height="300" src="' +
                this.getMapUrl(this.fieldConfig, this.data, width) + '" />';
    },

    geocode: function () {
        if (!this.geocoder) {
            return;
        }

        var address = this.searchfield.getValue();
        this.geocoder.geocode( { 'address': address}, function(results, status) {
            if (this.isMapsAvailable() && status === google.maps.GeocoderStatus.OK) {
                this.gmap.setCenter(results[0].geometry.location, 16);
                this.gmap.setZoom(14);
            }
        }.bind(this));
    },

    getBoundsZoomLevel: function (bounds, mapDim) {
        var WORLD_DIM = { height: 256, width: 256 };
        var ZOOM_MAX = 21;

        function latRad(lat) {
            var sin = Math.sin(lat * Math.PI / 180);
            var radX2 = Math.log((1 + sin) / (1 - sin)) / 2;
            return Math.max(Math.min(radX2, Math.PI), -Math.PI) / 2;
        }

        function zoom(mapPx, worldPx, fraction) {
            return Math.floor(Math.log(mapPx / worldPx / fraction) / Math.LN2);
        }

        var ne = bounds.getNorthEast();
        var sw = bounds.getSouthWest();

        var latFraction = (latRad(ne.lat()) - latRad(sw.lat())) / Math.PI;

        var lngDiff = ne.lng() - sw.lng();
        var lngFraction = ((lngDiff < 0) ? (lngDiff + 360) : lngDiff) / 360;

        var latZoom = zoom(mapDim.height, WORLD_DIM.height, latFraction);
        var lngZoom = zoom(mapDim.width, WORLD_DIM.width, lngFraction);

        return Math.min(latZoom, lngZoom, ZOOM_MAX);
    }

});

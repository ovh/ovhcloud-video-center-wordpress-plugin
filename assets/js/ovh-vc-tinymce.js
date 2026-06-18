/**
 * OVHcloud Video Center – TinyMCE Plugin
 *
 * Adds an "OVHcloud Video Center" button to the classic editor toolbar.
 * Clicking it opens a dialog to insert [ovhcloud_vc_video] or [ovhcloud_vc_gallery] shortcodes.
 *
 * @package OVH_VideoCenter
 * @since   0.0.1
 */

/* global tinymce */
(function () {
    'use strict';

    tinymce.PluginManager.add('ovhcloud_videocenter', function (editor) {

        // Add toolbar button.
        editor.addButton('ovhcloud_videocenter', {
            title: 'OVHcloud Video Center',
            icon: 'media-video',
            onclick: function () {
                openShortcodeDialog(editor);
            }
        });

        /**
         * Open a TinyMCE dialog to configure and insert a shortcode.
         *
         * @param {tinymce.Editor} ed The editor instance.
         */
        function openShortcodeDialog(ed) {
            ed.windowManager.open({
                title: 'Insert OVHcloud Video',
                width: 450,
                height: 400,
                body: [
                    {
                        type: 'listbox',
                        name: 'shortcode_type',
                        label: 'Type',
                        values: [
                            { text: 'Single Video', value: 'single' },
                            { text: 'Video Gallery', value: 'list' }
                        ]
                    },
                    {
                        type: 'textbox',
                        name: 'vod_id',
                        label: 'Video ID (for Single Video)',
                        placeholder: 'vod_xxxxxxxxxxxxxxxxxxxxxx'
                    },
                    {
                        type: 'textbox',
                        name: 'tags',
                        label: 'Tags filter (for Video Gallery)',
                        placeholder: 'sport, music'
                    },
                    {
                        type: 'textbox',
                        name: 'columns',
                        label: 'Columns (for Video Gallery)',
                        value: '4'
                    },
                    {
                        type: 'textbox',
                        name: 'rows',
                        label: 'Rows (for Video Gallery)',
                        value: '2'
                    },
                    {
                        type: 'checkbox',
                        name: 'show_title',
                        label: 'Show Titles (for Video Gallery)',
                        checked: false
                    },
                    {
                        type: 'textbox',
                        name: 'size',
                        label: 'Size (Single: 100%/50%/WxH — Gallery: WxH in pixels)',
                        value: '',
                        tooltip: 'Single video: 100%, 50%, or WxH. Gallery: WxH in pixels (e.g. 320x180). Leave empty for auto.'
                    },
                    {
                        type: 'listbox',
                        name: 'orientation',
                        label: 'Orientation (Single Video only)',
                        values: [
                            { text: 'Horizontal (16:9)', value: 'horizontal' },
                            { text: 'Vertical (9:16)', value: 'vertical' }
                        ],
                        value: 'horizontal'
                    },
                    {
                        type: 'checkbox',
                        name: 'autoplay',
                        label: 'Autoplay',
                        checked: false
                    },
                    {
                        type: 'checkbox',
                        name: 'loop',
                        label: 'Loop',
                        checked: false
                    },
                    {
                        type: 'checkbox',
                        name: 'hide_title',
                        label: 'Hide Title',
                        checked: false
                    },
                    {
                        type: 'checkbox',
                        name: 'hide_controls',
                        label: 'Hide Controls',
                        checked: false
                    },
                    {
                        type: 'checkbox',
                        name: 'mute',
                        label: 'Mute',
                        checked: false
                    }
                ],
                onsubmit: function (e) {
                    var type = e.data.shortcode_type;
                    var vodId = e.data.vod_id;
                    var tags = e.data.tags;
                    var size = e.data.size || '100%';
                    var orientation = e.data.orientation || 'horizontal';
                    var shortcode = '';

                    if (type === 'single') {
                        if (!vodId) {
                            ed.windowManager.alert('Please enter a Video ID.');
                            return;
                        }
                        shortcode = '[ovhcloud_vc_video id="' + vodId + '" size="' + size + '" orientation="' + orientation + '"';
                        if (e.data.autoplay) {
                            shortcode += ' autoplay="true"';
                        }
                        if (e.data.loop) {
                            shortcode += ' loop="true"';
                        }
                        if (e.data.hide_title) {
                            shortcode += ' hide_title="true"';
                        }
                        if (e.data.hide_controls) {
                            shortcode += ' hide_controls="true"';
                        }
                        if (e.data.mute) {
                            shortcode += ' mute="true"';
                        }
                        shortcode += ']';
                    } else {
                        shortcode = '[ovhcloud_vc_gallery';
                        if (tags) {
                            shortcode += ' tags="' + tags + '"';
                        }
                        var cols = e.data.columns || '4';
                        var galleryRows = e.data.rows || '2';
                        shortcode += ' columns="' + cols + '" rows="' + galleryRows + '"';
                        if (size && size !== '100%') {
                            shortcode += ' size="' + size + '"';
                        }
                        if (e.data.show_title) {
                            shortcode += ' show_title="true"';
                        }
                        shortcode += ']';
                    }

                    ed.insertContent(shortcode);
                }
            });
        }
    });

})();

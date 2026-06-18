/**
 * OVH VideoCenter – Admin JavaScript
 *
 * Handles:
 * - Video listing with AJAX pagination and search
 * - Video upload (two-step: create VOD → client-side upload to pre-signed URL)
 * - Edit modal (update title/tags)
 * - Delete confirmation
 * - Preview modal (embed player)
 * - Connection test
 *
 * @package OVH_VideoCenter
 * @since   0.0.1
 */

/* global jQuery, ovhcloudVC */
(function ($) {
    'use strict';

    // Current state.
    var currentPage = 1;
    var currentSearch = '';

    // ---------------------------------------------------------------
    // Initialise on DOM ready.
    // ---------------------------------------------------------------
    $(document).ready(function () {
        // Video list page.
        if ($('#ovh-vc-videos-grid').length) {
            loadVideos();
            bindListEvents();
        }

        // Upload form.
        if ($('#ovh-vc-upload-form').length) {
            bindUploadEvents();
        }

        // Settings – test connection.
        if ($('#ovhcloud-vc-test-connection').length) {
            bindTestConnection();
        }
    });

    // ---------------------------------------------------------------
    // Video listing.
    // ---------------------------------------------------------------

    /**
     * Load videos via AJAX and render the card grid.
     */
    function loadVideos() {
        var $grid = $('#ovh-vc-videos-grid');
        $grid.html('<div class="ovh-vc-loading"><span class="spinner is-active"></span> ' + 'Loading videos…' + '</div>');

        $.ajax({
            url: ovhcloudVC.ajaxUrl,
            method: 'GET',
            data: {
                action: 'ovhcloud_vc_list_videos',
                nonce: ovhcloudVC.nonce,
                page_num: currentPage,
                per_page: 12,
                search: currentSearch
            },
            success: function (response) {
                if (!response.success) {
                    $grid.html('<div class="ovh-vc-empty-state"><p>' + escHtml(response.data.message) + '</p></div>');
                    return;
                }

                renderVideoGrid(response.data);
            },
            error: function () {
                $grid.html('<div class="ovh-vc-empty-state"><p>Failed to load videos.</p></div>');
            }
        });
    }

    /**
     * Render the video card grid from API data.
     *
     * @param {Object} data { items: [], pagination: {} }
     */
    function renderVideoGrid(data) {
        var $grid = $('#ovh-vc-videos-grid');
        $grid.empty();

        if (!data.items || data.items.length === 0) {
            $grid.html(
                '<div class="ovh-vc-empty-state">' +
                '<span class="dashicons dashicons-video-alt3"></span>' +
                '<p>No videos found.</p>' +
                '</div>'
            );
            $('#ovh-vc-pagination').empty();
            return;
        }

        data.items.forEach(function (vod) {
            var poster = vod._posterUrl || '';
            var embedUrl = vod._embedUrl || '';
            var title = vod.title || '(Untitled)';
            var tags = vod.tags || [];
            var date = vod.createdAt ? new Date(vod.createdAt).toLocaleDateString() : '';
            var status = (vod.status && vod.status.value) ? vod.status.value : 'unknown';

            var thumbContent = '';
            if (poster) {
                thumbContent = '<img src="' + escAttr(poster) + '" alt="' + escAttr(title) + '" loading="lazy" />';
            } else {
                thumbContent = '<span class="dashicons dashicons-video-alt3 ovh-vc-no-poster-icon"></span>';
            }

            var tagsHtml = '';
            if (tags.length) {
                tagsHtml = '<div class="ovh-vc-card-tags">';
                tags.forEach(function (tag) {
                    tagsHtml += '<span class="ovh-vc-tag">' + escHtml(tag) + '</span>';
                });
                tagsHtml += '</div>';
            }

            var statusClass = 'ovh-vc-status-' + status;

            var shortcode = '[ovhcloud_vc_video id="' + vod.vodId + '" size="100%" orientation="horizontal"]';

            var card =
                '<div class="ovh-vc-video-card" data-vod-id="' + escAttr(vod.vodId) + '">' +
                    '<div class="ovh-vc-card-thumb" data-embed-url="' + escAttr(embedUrl) + '" data-title="' + escAttr(title) + '">' +
                        thumbContent +
                        '<div class="ovh-vc-play-overlay"><span>&#9654;</span></div>' +
                    '</div>' +
                    '<div class="ovh-vc-card-body">' +
                        '<h3>' + escHtml(title) + '</h3>' +
                        tagsHtml +
                        '<div class="ovh-vc-card-date">' + escHtml(date) + '</div>' +
                        '<span class="ovh-vc-card-status ' + statusClass + '">' + escHtml(status) + '</span>' +
                        '<div class="ovh-vc-card-shortcode">' +
                            '<code class="ovh-vc-shortcode-text">' + escHtml(shortcode) + '</code>' +
                            '<button type="button" class="button button-small ovh-vc-copy-shortcode" data-shortcode="' + escAttr(shortcode) + '" title="Copy shortcode">' +
                                '<span class="dashicons dashicons-clipboard"></span>' +
                            '</button>' +
                        '</div>' +
                        '<div class="ovh-vc-card-actions">' +
                            '<button type="button" class="button ovh-vc-edit-btn" data-vod-id="' + escAttr(vod.vodId) + '" data-title="' + escAttr(vod.title || '') + '" data-tags="' + escAttr((tags || []).join(', ')) + '">Edit</button>' +
                            '<button type="button" class="button ovh-vc-delete-btn" data-vod-id="' + escAttr(vod.vodId) + '">Delete</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            $grid.append(card);
        });

        renderPagination(data.pagination);
    }

    /**
     * Render pagination controls.
     *
     * @param {Object} pagination { pageSize, pageNumber, totalItems }
     */
    function renderPagination(pagination) {
        var $pag = $('#ovh-vc-pagination');
        $pag.empty();

        if (!pagination || pagination.totalItems <= pagination.pageSize) {
            return;
        }

        var totalPages = Math.ceil(pagination.totalItems / pagination.pageSize);
        var current = pagination.pageNumber;

        for (var i = 1; i <= totalPages; i++) {
            var cls = (i === current) ? 'button current' : 'button';
            $pag.append('<button type="button" class="' + cls + ' ovh-vc-page-btn" data-page="' + i + '">' + i + '</button>');
        }

        $pag.append('<span class="ovh-vc-pagination-info">' + pagination.totalItems + ' videos</span>');
    }

    /**
     * Bind event handlers for the video list page.
     */
    function bindListEvents() {
        // Search.
        $('#ovh-vc-search-btn').on('click', function () {
            currentSearch = $('#ovh-vc-search').val();
            currentPage = 1;
            loadVideos();
        });

        $('#ovh-vc-search').on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#ovh-vc-search-btn').trigger('click');
            }
        });

        // Pagination.
        $(document).on('click', '.ovh-vc-page-btn', function () {
            currentPage = parseInt($(this).data('page'), 10);
            loadVideos();
        });

        // Preview (click thumbnail).
        $(document).on('click', '.ovh-vc-card-thumb', function (e) {
            e.preventDefault();
            var embedUrl = $(this).data('embed-url');
            if (!embedUrl) return;
            openPreviewModal(embedUrl);
        });

        // Close preview modal.
        $(document).on('click', '#ovh-vc-preview-modal .ovh-vc-modal-overlay, #ovh-vc-preview-modal .ovh-vc-modal-close', function () {
            closePreviewModal();
        });

        // Edit button.
        $(document).on('click', '.ovh-vc-edit-btn', function () {
            var vodId = $(this).data('vod-id');
            var title = $(this).data('title');
            var tags = $(this).data('tags');
            openEditModal(vodId, title, tags);
        });

        // Close edit modal.
        $(document).on('click', '.ovh-vc-modal-close, #ovh-vc-edit-modal .ovh-vc-modal-overlay', function () {
            closeEditModal();
        });

        // Submit edit form.
        $('#ovh-vc-edit-form').on('submit', function (e) {
            e.preventDefault();
            submitEdit();
        });

        // Copy shortcode button.
        $(document).on('click', '.ovh-vc-copy-shortcode', function (e) {
            e.stopPropagation();
            var $btn = $(this);
            var shortcode = $btn.data('shortcode');
            navigator.clipboard.writeText(shortcode).then(function () {
                var $icon = $btn.find('.dashicons');
                $icon.removeClass('dashicons-clipboard').addClass('dashicons-yes');
                setTimeout(function () {
                    $icon.removeClass('dashicons-yes').addClass('dashicons-clipboard');
                }, 1500);
            });
        });

        // Delete button.
        $(document).on('click', '.ovh-vc-delete-btn', function () {
            var vodId = $(this).data('vod-id');
            if (confirm(ovhcloudVC.i18n.confirmDelete)) {
                deleteVideo(vodId);
            }
        });
    }

    // ---------------------------------------------------------------
    // Preview modal.
    // ---------------------------------------------------------------

    function openPreviewModal(embedUrl) {
        var $modal = $('#ovh-vc-preview-modal');
        var $player = $('#ovh-vc-preview-player');
        $player.html('<iframe src="' + escAttr(embedUrl) + '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>');
        $modal.show();
    }

    function closePreviewModal() {
        var $modal = $('#ovh-vc-preview-modal');
        $('#ovh-vc-preview-player').empty();
        $modal.hide();
    }

    // ---------------------------------------------------------------
    // Edit modal.
    // ---------------------------------------------------------------

    function openEditModal(vodId, title, tags) {
        $('#ovh-vc-edit-vod-id').val(vodId);
        $('#ovh-vc-edit-title').val(title);
        $('#ovh-vc-edit-tags').val(tags);
        $('#ovh-vc-edit-result').hide();
        $('#ovh-vc-edit-modal').show();
    }

    function closeEditModal() {
        $('#ovh-vc-edit-modal').hide();
        $('#ovh-vc-edit-result').hide();
    }

    function submitEdit() {
        var vodId = $('#ovh-vc-edit-vod-id').val();
        var title = $('#ovh-vc-edit-title').val();
        var tags = $('#ovh-vc-edit-tags').val();
        var $result = $('#ovh-vc-edit-result');

        $result.removeClass('success error').text(ovhcloudVC.i18n.saving).show();

        $.ajax({
            url: ovhcloudVC.ajaxUrl,
            method: 'POST',
            data: {
                action: 'ovhcloud_vc_update_video',
                nonce: ovhcloudVC.nonce,
                vod_id: vodId,
                title: title,
                tags: tags
            },
            success: function (response) {
                if (response.success) {
                    $result.addClass('success').text(ovhcloudVC.i18n.saved);
                    setTimeout(function () {
                        closeEditModal();
                        loadVideos();
                    }, 800);
                } else {
                    $result.addClass('error').text(response.data.message);
                }
            },
            error: function () {
                $result.addClass('error').text('Request failed.');
            }
        });
    }

    // ---------------------------------------------------------------
    // Delete.
    // ---------------------------------------------------------------

    function deleteVideo(vodId) {
        $.ajax({
            url: ovhcloudVC.ajaxUrl,
            method: 'POST',
            data: {
                action: 'ovhcloud_vc_delete_video',
                nonce: ovhcloudVC.nonce,
                vod_id: vodId
            },
            success: function (response) {
                if (response.success) {
                    loadVideos();
                } else {
                    alert(response.data.message);
                }
            },
            error: function () {
                alert('Delete request failed.');
            }
        });
    }

    // ---------------------------------------------------------------
    // Upload (two-step flow).
    // ---------------------------------------------------------------

    function bindUploadEvents() {
        $('#ovh-vc-upload-form').on('submit', function (e) {
            e.preventDefault();
            startUpload();
        });
    }

    /**
     * Two-step upload:
     * 1. POST to WP AJAX → creates VOD object via API, returns uploadUrl.
     * 2. PUT the file directly to uploadUrl (client-side) with progress tracking.
     */
    function startUpload() {
        var title = $('#ovh-vc-video-title').val().trim();
        var tags = $('#ovh-vc-video-tags').val().trim();
        var fileInput = document.getElementById('ovh-vc-video-file');
        var file = fileInput.files[0];

        if (!title) {
            showUploadResult('error', 'Please enter a title.');
            return;
        }

        if (!file) {
            showUploadResult('error', 'Please select a video file.');
            return;
        }

        // Validate file type.
        var allowedTypes = ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska'];
        if (allowedTypes.indexOf(file.type) === -1 && !file.name.match(/\.(mp4|webm|mov|avi|mkv)$/i)) {
            showUploadResult('error', 'Unsupported video format. Please use MP4, WebM, MOV, AVI, or MKV.');
            return;
        }

        // Disable submit button.
        $('#ovh-vc-submit-upload').prop('disabled', true).val(ovhcloudVC.i18n.uploading);
        showUploadResult('', '');

        // Step 1: Create VOD object.
        $.ajax({
            url: ovhcloudVC.ajaxUrl,
            method: 'POST',
            data: {
                action: 'ovhcloud_vc_create_video',
                nonce: ovhcloudVC.nonce,
                title: title,
                tags: tags
            },
            success: function (response) {
                if (!response.success) {
                    showUploadResult('error', response.data.message);
                    resetUploadButton();
                    return;
                }

                var uploadUrl = response.data.uploadUrl;
                if (!uploadUrl) {
                    showUploadResult('error', 'No upload URL returned from API.');
                    resetUploadButton();
                    return;
                }

                // Step 2: Upload file to pre-signed URL.
                uploadFileToUrl(file, uploadUrl);
            },
            error: function () {
                showUploadResult('error', ovhcloudVC.i18n.uploadError);
                resetUploadButton();
            }
        });
    }

    /**
     * Multipart upload threshold: 50 MB.
     * Files larger than this use the multipart upload API with Content-Range
     * header format: "part: <number>/<total>".
     */
    var MULTIPART_THRESHOLD = 50 * 1024 * 1024; // 50 MB
    var CHUNK_SIZE = 50 * 1024 * 1024;           // 50 MB per part

    /**
     * Upload the file to the pre-signed URL with XHR for progress tracking.
     * Files ≤ 50 MB are sent as a single POST (no Content-Range).
     * Files > 50 MB use the multipart upload protocol with Content-Range: part N/T.
     *
     * @param {File}   file      The file object.
     * @param {string} uploadUrl The pre-signed upload URL.
     */
    function uploadFileToUrl(file, uploadUrl) {
        if (file.size > MULTIPART_THRESHOLD) {
            uploadFileMultipart(file, uploadUrl);
        } else {
            uploadFileSingle(file, uploadUrl);
        }
    }

    /**
     * Single-request upload for files ≤ 50 MB.
     * Omits Content-Range header so the server treats it as a single file upload.
     *
     * @param {File}   file      The file object.
     * @param {string} uploadUrl The pre-signed upload URL.
     */
    function uploadFileSingle(file, uploadUrl) {
        var $progress = $('#ovh-vc-upload-progress');
        var $fill = $('#ovh-vc-progress-fill');
        var $text = $('#ovh-vc-progress-text');

        $progress.show();

        var xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                var pct = Math.round((e.loaded / e.total) * 100);
                $fill.css('width', pct + '%');
                $text.text(pct + '%');
            }
        });

        xhr.addEventListener('load', function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                showUploadResult('success', ovhcloudVC.i18n.uploadSuccess);
                $('#ovh-vc-upload-form')[0].reset();
                $fill.css('width', '100%');
                $text.text('100%');
            } else {
                showUploadResult('error', ovhcloudVC.i18n.uploadError + ' (HTTP ' + xhr.status + ')');
            }
            resetUploadButton();
        });

        xhr.addEventListener('error', function () {
            showUploadResult('error', ovhcloudVC.i18n.uploadError);
            resetUploadButton();
        });

        xhr.open('POST', uploadUrl, true);
        xhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');
        xhr.send(file);
    }

    /**
     * Multipart upload for files > 50 MB.
     *
     * Uses the Upload API multipart protocol:
     * - Each part is POSTed with Content-Range: part <number>/<total>
     *   where number is 1-based and total is the total number of parts.
     * - Parts are sent sequentially with progress tracking.
     * - The server returns 202 for intermediate parts and 201 when the
     *   last part completes the upload.
     * - Content is sent as application/octet-stream (raw binary slice).
     *
     * @param {File}   file      The file object.
     * @param {string} uploadUrl The pre-signed upload URL.
     */
    function uploadFileMultipart(file, uploadUrl) {
        var $progress = $('#ovh-vc-upload-progress');
        var $fill = $('#ovh-vc-progress-fill');
        var $text = $('#ovh-vc-progress-text');

        $progress.show();

        var totalSize = file.size;
        var totalParts = Math.ceil(totalSize / CHUNK_SIZE);
        var partIndex = 0;   // 0-based internally, sent as 1-based to API
        var uploadedBytes = 0;

        function uploadNextPart() {
            if (partIndex >= totalParts) {
                // All parts uploaded successfully.
                showUploadResult('success', ovhcloudVC.i18n.uploadSuccess);
                $('#ovh-vc-upload-form')[0].reset();
                $fill.css('width', '100%');
                $text.text('100%');
                resetUploadButton();
                return;
            }

            var start = partIndex * CHUNK_SIZE;
            var end = Math.min(start + CHUNK_SIZE, totalSize);
            var partBlob = file.slice(start, end);
            var partNumber = partIndex + 1; // API uses 1-based numbering

            var xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', function (e) {
                if (e.lengthComputable) {
                    var totalUploaded = uploadedBytes + e.loaded;
                    var pct = Math.round((totalUploaded / totalSize) * 100);
                    $fill.css('width', pct + '%');
                    $text.text(pct + '% (part ' + partNumber + '/' + totalParts + ')');
                }
            });

            xhr.addEventListener('load', function () {
                // 201 = upload completed (last part), 202 = part accepted (more parts expected)
                if (xhr.status === 201 || xhr.status === 202) {
                    uploadedBytes += (end - start);
                    partIndex++;
                    uploadNextPart();
                } else {
                    var errorMsg = ovhcloudVC.i18n.uploadError + ' (HTTP ' + xhr.status + ' on part ' + partNumber + ')';
                    // Try to extract the API error message.
                    try {
                        var body = JSON.parse(xhr.responseText);
                        if (body.message) {
                            errorMsg = body.message + ' (part ' + partNumber + ')';
                        }
                    } catch (e) { /* ignore */ }
                    showUploadResult('error', errorMsg);
                    resetUploadButton();
                }
            });

            xhr.addEventListener('error', function () {
                showUploadResult('error', ovhcloudVC.i18n.uploadError + ' (part ' + partNumber + ')');
                resetUploadButton();
            });

            xhr.open('POST', uploadUrl, true);
            xhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');
            xhr.setRequestHeader('Content-Range', 'part ' + partNumber + '/' + totalParts);
            xhr.send(partBlob);
        }

        uploadNextPart();
    }

    /**
     * Show a result message on the upload form.
     */
    function showUploadResult(type, message) {
        var $result = $('#ovh-vc-upload-result');
        if (!message) {
            $result.hide();
            return;
        }
        $result.removeClass('success error').addClass(type).text(message).show();
    }

    /**
     * Reset the upload button to its original state.
     */
    function resetUploadButton() {
        $('#ovh-vc-submit-upload').prop('disabled', false).val('Upload Video');
    }

    // ---------------------------------------------------------------
    // Test connection.
    // ---------------------------------------------------------------

    function bindTestConnection() {
        $('#ovhcloud-vc-test-connection').on('click', function () {
            var $btn = $(this);
            var $result = $('#ovhcloud-vc-test-result');

            $btn.prop('disabled', true);
            $result.text('Testing…').css('color', '#666');

            $.ajax({
                url: ovhcloudVC.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'ovhcloud_vc_test_connection',
                    nonce: ovhcloudVC.nonce
                },
                success: function (response) {
                    if (response.success) {
                        var msg = ovhcloudVC.i18n.testSuccess;
                        if (response.data.spaceName) {
                            msg += ' (Space: ' + response.data.spaceName + ')';
                        }
                        $result.text(msg).css('color', '#00a32a');
                    } else {
                        $result.text(ovhcloudVC.i18n.testError + ' ' + response.data.message).css('color', '#d63638');
                    }
                    $btn.prop('disabled', false);
                },
                error: function () {
                    $result.text(ovhcloudVC.i18n.testError).css('color', '#d63638');
                    $btn.prop('disabled', false);
                }
            });
        });
    }

    // ---------------------------------------------------------------
    // Utility helpers.
    // ---------------------------------------------------------------

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function escAttr(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

})(jQuery);

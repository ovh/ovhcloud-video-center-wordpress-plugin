/**
 * OVHcloud Video Center – Gutenberg Block Edit Component
 *
 * Provides the editor UI for configuring the OVHcloud Video Center block.
 * In "single" mode, videos are fetched from the API and displayed
 * as a visual grid of poster thumbnails for the user to pick from.
 * The block is server-side rendered via ServerSideRender.
 *
 * @package OVH_VideoCenter
 * @since   0.0.1
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
    PanelBody,
    SelectControl,
    TextControl,
    ToggleControl,
    Placeholder,
    Spinner,
    Button,
    SearchControl,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import ServerSideRender from '@wordpress/server-side-render';
import apiFetch from '@wordpress/api-fetch';

/**
 * Video picker component – fetches videos and shows a poster grid.
 *
 * @param {Object}   props
 * @param {string}   props.vodId          Currently selected VOD ID.
 * @param {Function} props.onSelect       Callback when a video is selected.
 * @return {Element}
 */
function VideoPicker( { vodId, onSelect } ) {
    const [ videos, setVideos ] = useState( [] );
    const [ loading, setLoading ] = useState( true );
    const [ error, setError ] = useState( '' );
    const [ search, setSearch ] = useState( '' );
    const [ debouncedSearch, setDebouncedSearch ] = useState( '' );
    const [ page, setPage ] = useState( 1 );
    const [ totalPages, setTotalPages ] = useState( 1 );

    // Debounce search input – wait 400ms after last keystroke before firing API call.
    useEffect( () => {
        const timer = setTimeout( () => {
            setDebouncedSearch( search );
        }, 400 );
        return () => clearTimeout( timer );
    }, [ search ] );

    // Fetch videos whenever debounced search or page changes.
    useEffect( () => {
        setLoading( true );
        setError( '' );

        const params = new URLSearchParams( {
            action: 'ovhcloud_vc_list_videos',
            nonce: window.ovhcloudVCBlock?.nonce || '',
            page_num: page,
            per_page: 12,
            search: debouncedSearch,
        } );

        fetch( ( window.ovhcloudVCBlock?.ajaxUrl || '/wp-admin/admin-ajax.php' ) + '?' + params.toString(), {
            credentials: 'same-origin',
        } )
            .then( ( res ) => res.json() )
            .then( ( res ) => {
                if ( res.success && res.data?.items ) {
                    setVideos( res.data.items );
                    const pagination = res.data.pagination || {};
                    const total = Math.ceil(
                        ( pagination.totalItems || 0 ) / ( pagination.pageSize || 12 )
                    );
                    setTotalPages( total || 1 );
                } else {
                    setVideos( [] );
                    setError( res.data?.message || __( 'Failed to load videos.', 'ovhcloud-video-center' ) );
                }
                setLoading( false );
            } )
            .catch( () => {
                setError( __( 'Failed to load videos.', 'ovhcloud-video-center' ) );
                setLoading( false );
            } );
    }, [ debouncedSearch, page ] );

    return (
        <div className="ovh-vc-video-picker">
            <SearchControl
                label={ __( 'Search videos', 'ovhcloud-video-center' ) }
                value={ search }
                onChange={ ( val ) => {
                    setSearch( val );
                    setPage( 1 );
                } }
                help={ __( 'Title: partial match — Tags: exact match.', 'ovhcloud-video-center' ) }
            />

            { loading && (
                <div className="ovh-vc-picker-loading">
                    <Spinner />
                    <span>{ __( 'Loading videos…', 'ovhcloud-video-center' ) }</span>
                </div>
            ) }

            { ! loading && error && (
                <p className="ovh-vc-picker-error">{ error }</p>
            ) }

            { ! loading && ! error && videos.length === 0 && (
                <p className="ovh-vc-picker-empty">{ __( 'No videos found.', 'ovhcloud-video-center' ) }</p>
            ) }

            { videos.length > 0 && (
                <div style={ loading ? { opacity: 0.4, pointerEvents: 'none' } : {} }>
                    <div className="ovh-vc-picker-grid">
                        { videos.map( ( vod ) => {
                            const posterUrl = vod._posterUrl || '';
                            const isSelected = vod.vodId === vodId;
                            const title = vod.title || __( '(Untitled)', 'ovhcloud-video-center' );
                            return (
                                <button
                                    key={ vod.vodId }
                                    type="button"
                                    className={ `ovh-vc-picker-item${ isSelected ? ' is-selected' : '' }` }
                                    onClick={ () => onSelect( vod.vodId ) }
                                    title={ title }
                                >
                                    <div className="ovh-vc-picker-thumb">
                                        { posterUrl ? (
                                            <img src={ posterUrl } alt={ title } />
                                        ) : (
                                            <span className="dashicons dashicons-video-alt3"></span>
                                        ) }
                                        { isSelected && (
                                            <span className="ovh-vc-picker-check">✓</span>
                                        ) }
                                    </div>
                                    <span className="ovh-vc-picker-title">{ title }</span>
                                </button>
                            );
                        } ) }
                    </div>

                    { totalPages > 1 && (
                        <div className="ovh-vc-picker-pagination">
                            <Button
                                variant="secondary"
                                disabled={ page <= 1 }
                                onClick={ () => setPage( page - 1 ) }
                                size="small"
                            >
                                { __( '← Previous', 'ovhcloud-video-center' ) }
                            </Button>
                            <span className="ovh-vc-picker-page-info">
                                { page } / { totalPages }
                            </span>
                            <Button
                                variant="secondary"
                                disabled={ page >= totalPages }
                                onClick={ () => setPage( page + 1 ) }
                                size="small"
                            >
                                { __( 'Next →', 'ovhcloud-video-center' ) }
                            </Button>
                        </div>
                    ) }
                </div>
            ) }
        </div>
    );
}

/**
 * Orientation picker – shown as the first step when no orientation is set.
 *
 * @param {Object}   props
 * @param {Function} props.onSelect Callback with 'horizontal' or 'vertical'.
 * @return {Element}
 */
function OrientationPicker( { onSelect } ) {
    return (
        <div className="ovh-vc-orientation-picker">
            <button
                type="button"
                className="ovh-vc-orientation-card"
                onClick={ () => onSelect( 'horizontal' ) }
            >
                <div className="ovh-vc-orientation-icon ovh-vc-orientation-horizontal">
                    <svg viewBox="0 0 64 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="2" y="2" width="60" height="36" rx="3" stroke="currentColor" strokeWidth="2.5" fill="none" />
                        <polygon points="25,12 25,28 40,20" fill="currentColor" />
                    </svg>
                </div>
                <span className="ovh-vc-orientation-label">
                    { __( 'Horizontal', 'ovhcloud-video-center' ) }
                </span>
                <span className="ovh-vc-orientation-ratio">16:9</span>
            </button>
            <button
                type="button"
                className="ovh-vc-orientation-card"
                onClick={ () => onSelect( 'vertical' ) }
            >
                <div className="ovh-vc-orientation-icon ovh-vc-orientation-vertical">
                    <svg viewBox="0 0 40 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="2" y="2" width="36" height="60" rx="3" stroke="currentColor" strokeWidth="2.5" fill="none" />
                        <polygon points="15,22 15,42 28,32" fill="currentColor" />
                    </svg>
                </div>
                <span className="ovh-vc-orientation-label">
                    { __( 'Vertical', 'ovhcloud-video-center' ) }
                </span>
                <span className="ovh-vc-orientation-ratio">9:16</span>
            </button>
        </div>
    );
}

/**
 * Edit component for the OVHcloud Video Center block.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {Element} Block editor element.
 */
export default function Edit( { attributes, setAttributes } ) {
    const { mode, vodId, tags, size, orientation, columns, rows, showTitle, loop, autoplay, hideTitle, hideControls, mute } = attributes;
    const blockProps = useBlockProps();

    const hasOrientation = orientation === 'horizontal' || orientation === 'vertical';
    const isReady = ( mode === 'single' && hasOrientation ) || mode === 'list';
    const hasRequiredData = isReady && ( ( mode === 'single' && vodId ) || mode === 'list' );

    return (
        <div { ...blockProps }>
            { isReady && (
                <InspectorControls>
                    <PanelBody title={ __( 'Video Settings', 'ovhcloud-video-center' ) }>
                        <SelectControl
                            label={ __( 'Mode', 'ovhcloud-video-center' ) }
                            value={ mode }
                            options={ [
                                { label: __( 'Single Video', 'ovhcloud-video-center' ), value: 'single' },
                                { label: __( 'Video Gallery', 'ovhcloud-video-center' ), value: 'list' },
                            ] }
                            onChange={ ( value ) => setAttributes( { mode: value } ) }
                        />

                        { mode === 'single' && (
                            <TextControl
                                label={ __( 'Video ID', 'ovhcloud-video-center' ) }
                                value={ vodId }
                                onChange={ ( value ) => setAttributes( { vodId: value } ) }
                                placeholder="vod_xxxxxxxxxxxxxxxxxxxxxx"
                                help={ __( 'Or use the visual picker below.', 'ovhcloud-video-center' ) }
                            />
                        ) }

                        { mode === 'list' && (
                            <>
                                <TextControl
                                    label={ __( 'Tags Filter', 'ovhcloud-video-center' ) }
                                    value={ tags }
                                    onChange={ ( value ) => setAttributes( { tags: value } ) }
                                    placeholder="sport, music"
                                    help={ __( 'Comma-separated tags to filter videos. Leave empty for all videos.', 'ovhcloud-video-center' ) }
                                />
                                <TextControl
                                    label={ __( 'Columns', 'ovhcloud-video-center' ) }
                                    value={ columns }
                                    onChange={ ( value ) => setAttributes( { columns: value } ) }
                                    placeholder="4"
                                    help={ __( 'Number of columns in the gallery grid.', 'ovhcloud-video-center' ) }
                                />
                                <TextControl
                                    label={ __( 'Rows', 'ovhcloud-video-center' ) }
                                    value={ rows }
                                    onChange={ ( value ) => setAttributes( { rows: value } ) }
                                    placeholder="2"
                                    help={ __( 'Number of rows in the gallery grid.', 'ovhcloud-video-center' ) }
                                />
                                <ToggleControl
                                    label={ __( 'Show Titles', 'ovhcloud-video-center' ) }
                                    checked={ showTitle }
                                    onChange={ ( value ) => setAttributes( { showTitle: value } ) }
                                />
                                <TextControl
                                    label={ __( 'Thumbnail Size', 'ovhcloud-video-center' ) }
                                    value={ size }
                                    onChange={ ( value ) => setAttributes( { size: value } ) }
                                    placeholder="320x180"
                                    help={ __( 'Thumbnail size in pixels: WxH (e.g. 320x180).', 'ovhcloud-video-center' ) }
                                />
                            </>
                        ) }

                        { mode === 'single' && (
                            <>
                                <TextControl
                                    label={ __( 'Size', 'ovhcloud-video-center' ) }
                                    value={ size }
                                    onChange={ ( value ) => setAttributes( { size: value } ) }
                                    placeholder="100%"
                                    help={ __( 'Use percentage (100%, 50%) or pixel dimensions (640x360).', 'ovhcloud-video-center' ) }
                                />
                                <SelectControl
                                    label={ __( 'Orientation', 'ovhcloud-video-center' ) }
                                    value={ orientation }
                                    options={ [
                                        { label: __( 'Horizontal (16:9)', 'ovhcloud-video-center' ), value: 'horizontal' },
                                        { label: __( 'Vertical (9:16)', 'ovhcloud-video-center' ), value: 'vertical' },
                                    ] }
                                    onChange={ ( value ) => setAttributes( { orientation: value } ) }
                                />
                            </>
                        ) }
                    </PanelBody>

                    { mode === 'single' && (
                        <PanelBody title={ __( 'Player Options', 'ovhcloud-video-center' ) } initialOpen={ false }>
                            <ToggleControl
                                label={ __( 'Autoplay', 'ovhcloud-video-center' ) }
                                checked={ autoplay }
                                onChange={ ( value ) => setAttributes( { autoplay: value } ) }
                            />
                            <ToggleControl
                                label={ __( 'Loop', 'ovhcloud-video-center' ) }
                                checked={ loop }
                                onChange={ ( value ) => setAttributes( { loop: value } ) }
                            />
                            <ToggleControl
                                label={ __( 'Hide Title', 'ovhcloud-video-center' ) }
                                checked={ hideTitle }
                                onChange={ ( value ) => setAttributes( { hideTitle: value } ) }
                            />
                            <ToggleControl
                                label={ __( 'Hide Controls', 'ovhcloud-video-center' ) }
                                checked={ hideControls }
                                onChange={ ( value ) => setAttributes( { hideControls: value } ) }
                            />
                            <ToggleControl
                                label={ __( 'Mute', 'ovhcloud-video-center' ) }
                                checked={ mute }
                                onChange={ ( value ) => setAttributes( { mute: value } ) }
                            />
                        </PanelBody>
                    ) }
                </InspectorControls>
            ) }

            { /* Step 1: Orientation selection (single mode only) */ }
            { mode === 'single' && ! hasOrientation && (
                <Placeholder
                    icon="video-alt3"
                    label={ __( 'OVHcloud Video Center', 'ovhcloud-video-center' ) }
                    instructions={ __( 'Choose the video orientation to get started.', 'ovhcloud-video-center' ) }
                >
                    <OrientationPicker
                        onSelect={ ( value ) => setAttributes( { orientation: value } ) }
                    />
                </Placeholder>
            ) }

            { /* Step 2+: Video selection or config */ }
            { isReady && hasRequiredData && (
                <>
                    <ServerSideRender
                        block="ovhcloud-video-center/video"
                        attributes={ attributes }
                    />
                    { mode === 'single' && (
                        <div className="ovh-vc-picker-change">
                            <Button
                                variant="secondary"
                                onClick={ () => setAttributes( { vodId: '' } ) }
                                size="small"
                            >
                                { __( 'Change video', 'ovhcloud-video-center' ) }
                            </Button>
                        </div>
                    ) }
                </>
            ) }

            { isReady && ! hasRequiredData && (
                <Placeholder
                    icon="video-alt3"
                    label={ __( 'OVHcloud Video Center', 'ovhcloud-video-center' ) }
                    instructions={
                        mode === 'single'
                            ? __( 'Select a video from the list below, or enter a Video ID in the sidebar.', 'ovhcloud-video-center' )
                            : __( 'Configure the block in the sidebar to display videos.', 'ovhcloud-video-center' )
                    }
                >
                    { mode === 'single' && (
                        <VideoPicker
                            vodId={ vodId }
                            onSelect={ ( id ) => setAttributes( { vodId: id } ) }
                        />
                    ) }
                </Placeholder>
            ) }
        </div>
    );
}

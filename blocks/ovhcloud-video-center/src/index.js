/**
 * OVHcloud Video Center – Gutenberg Block Entry Point
 *
 * Registers the ovhcloud-video-center/video block.
 *
 * @package OVH_VideoCenter
 * @since   0.0.1
 */

import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import Save from './save';
import metadata from '../block.json';
import './editor.css';
import './style.css';

registerBlockType( metadata.name, {
    edit: Edit,
    save: Save,
} );

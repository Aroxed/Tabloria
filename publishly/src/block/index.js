import Edit from './edit.jsx';
import metadata from './block.json';

wp.blocks.registerBlockType(metadata.name, {
	...metadata,
	edit: Edit,
	save: () => null
});

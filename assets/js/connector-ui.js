/**
 * Custom Connectors-screen UI for the OpenAI-Compatible provider.
 *
 * Registers a custom render via `__experimentalRegisterConnector` so the
 * connector card on Settings → Connectors shows both the Base URL and the
 * API Key in a single panel.
 *
 * Module loading
 * --------------
 * In WP 7.0 only `@wordpress/connectors`, `@wordpress/a11y`, and
 * `@wordpress/route` are real script modules. Everything else (element, data,
 * core-data, components, i18n) is a classic script exposing a `window.wp.*`
 * global, loaded as part of the Connectors page's script bundle. This file
 * imports only `@wordpress/connectors` as a module and reads the rest from
 * globals — that's the pattern used by Gutenberg's own e2e test plugin for
 * connector extensibility.
 *
 * Stability caveat
 * ----------------
 * The `__experimental*` exports below are marked unstable by WP core. The
 * feature-detect at the bottom degrades gracefully (logs a warning, no fatal)
 * if any export is renamed or removed in a future WP release.
 *
 * @package WordPress\OpenAiAiProvider
 * @since 1.1.0
 */

import {
	__experimentalRegisterConnector as registerConnector,
	__experimentalConnectorItem as ConnectorItem,
} from '@wordpress/connectors';

// Pull React + WP packages from globals (see "Module loading" above).
const React = window.React;
const { createElement: h, useState, useRef } = React;
const { Button, TextControl } = window.wp.components;
const HStack = window.wp.components.__experimentalHStack;
const VStack = window.wp.components.__experimentalVStack;
const { useEntityProp } = window.wp.coreData;
const { dispatch } = window.wp.data;
const { __ } = window.wp.i18n;

const LOG_PREFIX = '[ai-provider-for-openai-compatible]';
// eslint-disable-next-line no-console
console.info( LOG_PREFIX, 'connector-ui module loaded' );

const CONNECTOR_ID = 'openai-compatible';
const BASE_URL_OPTION = 'ai_provider_for_openai_compatible_base_url';
const API_KEY_OPTION = 'connectors_ai_openai_compatible_api_key';
const DEFAULT_BASE_URL = 'https://api.openai.com/v1';
const TEXT_DOMAIN = 'ai-provider-for-openai-compatible';

/**
 * Custom render for the OpenAI-Compatible connector card.
 *
 * @param {object} props
 * @param {string} props.name
 * @param {string} props.description
 * @param {string} props.logo
 * @param {object} props.authentication
 */
function OpenAiCompatibleConnector( props ) {
	const { name, description, logo, authentication } = props;
	const keySource = authentication?.keySource ?? 'none';
	const initiallyConnected = !! authentication?.isConnected;
	const isExternallyConfigured =
		keySource === 'env' || keySource === 'constant';

	const [ storedBaseUrl, setStoredBaseUrl ] = useEntityProp(
		'root',
		'site',
		BASE_URL_OPTION
	);
	const [ storedApiKey, setStoredApiKey ] = useEntityProp(
		'root',
		'site',
		API_KEY_OPTION
	);

	const [ isExpanded, setIsExpanded ] = useState( false );
	const [ draftBaseUrl, setDraftBaseUrl ] = useState( '' );
	const [ draftApiKey, setDraftApiKey ] = useState( '' );
	const [ isBusy, setIsBusy ] = useState( false );
	const actionButtonRef = useRef( null );

	const hasStoredKey =
		isExternallyConfigured ||
		( typeof storedApiKey === 'string' && storedApiKey.length > 0 );
	const isConnected = initiallyConnected || hasStoredKey;

	function openPanel() {
		setDraftBaseUrl( storedBaseUrl || DEFAULT_BASE_URL );
		setDraftApiKey( '' );
		setIsExpanded( true );
	}

	async function saveSettings() {
		await dispatch( 'core' ).saveEditedEntityRecord(
			'root',
			'site',
			undefined
		);
	}

	async function handleSave() {
		setIsBusy( true );
		try {
			if ( draftBaseUrl !== storedBaseUrl ) {
				setStoredBaseUrl( draftBaseUrl );
			}
			if ( draftApiKey && draftApiKey !== storedApiKey ) {
				setStoredApiKey( draftApiKey );
			}
			await saveSettings();
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.error( LOG_PREFIX, 'save failed', error );
		} finally {
			setIsBusy( false );
			setIsExpanded( false );
			actionButtonRef.current?.focus();
		}
	}

	async function handleClear() {
		setIsBusy( true );
		try {
			setStoredApiKey( '' );
			await saveSettings();
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.error( LOG_PREFIX, 'clear failed', error );
		} finally {
			setIsBusy( false );
			setIsExpanded( false );
			actionButtonRef.current?.focus();
		}
	}

	const buttonLabel = isExpanded
		? __( 'Cancel', TEXT_DOMAIN )
		: isConnected
		? __( 'Manage', TEXT_DOMAIN )
		: __( 'Connect', TEXT_DOMAIN );

	const actionArea = h(
		HStack,
		{ spacing: 3, expanded: false },
		h(
			Button,
			{
				ref: actionButtonRef,
				variant: isExpanded || isConnected ? 'tertiary' : 'secondary',
				size: 'compact',
				onClick: () =>
					isExpanded ? setIsExpanded( false ) : openPanel(),
				isBusy,
				accessibleWhenDisabled: true,
			},
			buttonLabel
		)
	);

	const expandedContent =
		isExpanded &&
		h(
			VStack,
			{ spacing: 4, style: { padding: '16px 0' } },
			h( TextControl, {
				label: __( 'Base URL', TEXT_DOMAIN ),
				value: draftBaseUrl,
				onChange: setDraftBaseUrl,
				placeholder: DEFAULT_BASE_URL,
				disabled: isBusy,
				help: __(
					'Full URL including the version path. e.g. https://api.openai.com/v1, https://api.together.xyz/v1, http://localhost:11434/v1',
					TEXT_DOMAIN
				),
			} ),
			h( TextControl, {
				label: __( 'API Key', TEXT_DOMAIN ),
				type: 'password',
				value: draftApiKey,
				onChange: setDraftApiKey,
				placeholder: isExternallyConfigured
					? '••••••••••••••••'
					: hasStoredKey
					? __( 'Leave empty to keep the current key', TEXT_DOMAIN )
					: 'sk-...',
				disabled: isBusy || isExternallyConfigured,
				autoComplete: 'new-password',
				help: isExternallyConfigured
					? __(
							'Key is supplied by an environment variable or PHP constant and cannot be edited here.',
							TEXT_DOMAIN
					  )
					: undefined,
			} ),
			h(
				HStack,
				{ spacing: 3, justify: 'flex-start' },
				h(
					Button,
					{
						variant: 'primary',
						onClick: handleSave,
						isBusy,
						disabled: isBusy || isExternallyConfigured,
					},
					__( 'Save', TEXT_DOMAIN )
				),
				hasStoredKey &&
					! isExternallyConfigured &&
					h(
						Button,
						{
							variant: 'tertiary',
							isDestructive: true,
							onClick: handleClear,
							disabled: isBusy,
						},
						__( 'Clear API key', TEXT_DOMAIN )
					)
			)
		);

	return h(
		ConnectorItem,
		{
			className: 'connector-item--openai-compatible',
			logo,
			name,
			description,
			actionArea,
		},
		expandedContent
	);
}

// Feature-detect every experimental export before registering. If any went
// missing in a future WP release, leave the default ApiKeyConnector rendering
// instead of crashing the Connectors page.
if (
	typeof registerConnector === 'function' &&
	typeof ConnectorItem !== 'undefined'
) {
	try {
		registerConnector( CONNECTOR_ID, {
			render: OpenAiCompatibleConnector,
		} );
		// eslint-disable-next-line no-console
		console.info( LOG_PREFIX, 'registered custom connector render' );
	} catch ( error ) {
		// eslint-disable-next-line no-console
		console.warn(
			LOG_PREFIX,
			'connector registration failed; falling back to default API-key panel',
			error
		);
	}
} else {
	// eslint-disable-next-line no-console
	console.warn(
		LOG_PREFIX,
		'@wordpress/connectors experimental exports missing; not registering custom render'
	);
}

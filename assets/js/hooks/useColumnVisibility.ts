import { useEffect, useCallback, useRef } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
	fetchColumnVisibility,
	updateColumnVisibility,
	resetColumnVisibility,
} from '@/api/userPreferences';
import { useColumnVisibilityStore } from '@/store/useColumnVisibilityStore';

/**
 * Simple debounce implementation that does not require an external type package.
 */
function makeDebounce< T extends unknown[] >(
	fn: ( ...args: T ) => void,
	wait: number
): { call: ( ...args: T ) => void; cancel: () => void } {
	let timer: ReturnType< typeof setTimeout > | null = null;

	function call( ...args: T ): void {
		if ( timer !== null ) {
			clearTimeout( timer );
		}
		timer = setTimeout( () => {
			fn( ...args );
		}, wait );
	}

	function cancel(): void {
		if ( timer !== null ) {
			clearTimeout( timer );
			timer = null;
		}
	}

	return { call, cancel };
}

const QUERY_KEY = [ 'columnVisibility' ] as const;
const DEBOUNCE_MS = 500;

export interface UseColumnVisibilityReturn {
	hidden: Set< string >;
	isHydrated: boolean;
	isLoading: boolean;
	error: string | null;
	/** Debounced persist of current hidden set to the server. */
	persist: ( hidden: Set< string > ) => void;
	/** Reset server preferences to default and clear local store. */
	resetServer: () => void;
}

/**
 * Synchronizes column visibility between the Zustand store (local) and the
 * server (WP REST API). On first mount it fetches server state and hydrates
 * the store. Subsequent changes are debounced-saved to the server.
 */
export function useColumnVisibility(): UseColumnVisibilityReturn {
	const queryClient = useQueryClient();

	const hidden = useColumnVisibilityStore( ( s ) => s.hidden );
	const isHydrated = useColumnVisibilityStore( ( s ) => s.isHydrated );
	const isLoading = useColumnVisibilityStore( ( s ) => s.isLoading );
	const error = useColumnVisibilityStore( ( s ) => s.error );
	const setHidden = useColumnVisibilityStore( ( s ) => s.setHidden );
	const setHydrated = useColumnVisibilityStore( ( s ) => s.setHydrated );
	const setError = useColumnVisibilityStore( ( s ) => s.setError );

	// Fetch server preferences on mount.
	const { data: serverData } = useQuery( {
		queryKey: QUERY_KEY,
		queryFn: ( { signal } ) => fetchColumnVisibility( signal ),
		staleTime: 5 * 60_000,
	} );

	// Hydrate Zustand store from server data exactly once.
	useEffect( () => {
		if ( serverData && ! isHydrated ) {
			setHidden( serverData.hidden );
			setHydrated( true );
		}
	}, [ serverData, isHydrated, setHidden, setHydrated ] );

	// Mutation for saving.
	const saveMutation = useMutation( {
		mutationFn: ( hiddenArray: string[] ) =>
			updateColumnVisibility( hiddenArray ),
		onSuccess: ( data ) => {
			queryClient.setQueryData( QUERY_KEY, data );
			setError( null );
		},
		onError: ( err: Error ) => {
			setError( err.message );
		},
	} );

	// Mutation for resetting.
	const resetMutation = useMutation( {
		mutationFn: () => resetColumnVisibility(),
		onSuccess: ( data ) => {
			queryClient.setQueryData( QUERY_KEY, data );
			setHidden( data.hidden );
			setError( null );
		},
		onError: ( err: Error ) => {
			setError( err.message );
		},
	} );

	// Debounced save — stable reference across renders.
	const debouncedSaveRef = useRef(
		makeDebounce( ( hiddenArray: string[] ) => {
			saveMutation.mutate( hiddenArray );
		}, DEBOUNCE_MS )
	);

	// Clean up debounce on unmount.
	useEffect( () => {
		const debouncedSave = debouncedSaveRef.current;
		return () => {
			debouncedSave.cancel();
		};
	}, [] );

	const persist = useCallback( ( currentHidden: Set< string > ) => {
		debouncedSaveRef.current.call( [ ...currentHidden ] );
	}, [] );

	const resetServer = useCallback( () => {
		resetMutation.mutate();
	}, [ resetMutation ] );

	return {
		hidden,
		isHydrated,
		isLoading,
		error,
		persist,
		resetServer,
	};
}

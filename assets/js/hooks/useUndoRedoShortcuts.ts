import { useEffect } from 'react';
import { useChangesStore, useEditingStore } from '@/store';

export function useUndoRedoShortcuts(): void {
	useEffect( () => {
		function handleKeyDown( event: KeyboardEvent ): void {
			if ( event.key !== 'z' && event.key !== 'Z' ) {
				return;
			}

			const isModifier = event.metaKey || event.ctrlKey;
			if ( ! isModifier ) {
				return;
			}

			// Don't intercept undo/redo when inline editor is active
			// Let the browser handle native text undo in the input
			if ( useEditingStore.getState().activeCell !== null ) {
				return;
			}

			// Don't intercept when typing in unrelated inputs
			const target = event.target as HTMLElement;
			if (
				target.tagName === 'INPUT' ||
				target.tagName === 'TEXTAREA' ||
				target.isContentEditable
			) {
				// Only intercept if inside our app container
				if ( ! target.closest( '.iwbe-app' ) ) {
					return;
				}
			}

			event.preventDefault();

			const store = useChangesStore.getState();

			if ( event.shiftKey ) {
				store.redo();
			} else {
				store.undo();
			}
		}

		document.addEventListener( 'keydown', handleKeyDown );

		return () => {
			document.removeEventListener( 'keydown', handleKeyDown );
		};
	}, [] );
}

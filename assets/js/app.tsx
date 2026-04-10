import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App } from './components/App';

const CONTAINER_ID = 'ihumbak-woo-bulk-edit-app';

const queryClient = new QueryClient( {
	defaultOptions: {
		queries: {
			staleTime: 5 * 60 * 1000,
			retry: 1,
			refetchOnWindowFocus: false,
		},
	},
} );

const container = document.getElementById( CONTAINER_ID );

if ( container ) {
	const root = createRoot( container );
	root.render(
		<QueryClientProvider client={ queryClient }>
			<App />
		</QueryClientProvider>
	);
}

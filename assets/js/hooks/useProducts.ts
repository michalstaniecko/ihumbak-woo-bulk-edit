import { useQuery, keepPreviousData } from '@tanstack/react-query';
import type { UseQueryResult } from '@tanstack/react-query';
import { fetchProducts } from '@/api/products';
import type {
	ProductFilter,
	Sort,
	Pagination,
	ProductsResponse,
	FilterGroup,
} from '@/types/api';

const PRODUCTS_QUERY_KEY = 'products' as const;

interface UseProductsParams {
	/** Legacy flat filter array OR new filter group tree. */
	filters?: ProductFilter[] | FilterGroup;
	sort?: Sort;
	pagination?: Pagination;
	enabled?: boolean;
}

export function useProducts( {
	filters = [],
	sort = { field: 'name', order: 'asc' },
	pagination = { page: 1, per_page: 50 },
	enabled = true,
}: UseProductsParams = {} ): UseQueryResult< ProductsResponse > {
	return useQuery( {
		queryKey: [ PRODUCTS_QUERY_KEY, { filters, sort, pagination } ],
		queryFn: ( { signal } ) =>
			fetchProducts(
				{
					filters,
					sort,
					page: pagination.page,
					per_page: pagination.per_page,
				},
				signal
			),
		enabled,
		placeholderData: keepPreviousData,
	} );
}

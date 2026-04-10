import { apiFetch } from './client';
import { FieldsResponseSchema } from '@/types/api';
import type { FieldsResponse } from '@/types/api';

export function fetchFields( signal?: AbortSignal ): Promise< FieldsResponse > {
	return apiFetch( 'fields', FieldsResponseSchema, { signal } );
}

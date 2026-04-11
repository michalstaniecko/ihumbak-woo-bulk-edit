import type { Field } from '@/types/api';

export interface CellEditorProps {
	value: unknown;
	field: Field;
	productId: number;
	onConfirm: ( newValue: unknown ) => void;
	onCancel: () => void;
	validationError: string | null;
	width: number;
}

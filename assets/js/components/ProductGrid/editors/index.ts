import type { FieldType } from '@/types/api';
import type { ComponentType } from 'react';
import type { CellEditorProps } from './CellEditorProps';
import { TextEditor } from './TextEditor';
import { NumberEditor } from './NumberEditor';
import { SelectEditor } from './SelectEditor';

export type { CellEditorProps } from './CellEditorProps';

const EDITOR_MAP: Partial< Record< FieldType, ComponentType< CellEditorProps > > > = {
	text: TextEditor,
	textarea: TextEditor,
	custom_meta: TextEditor,
	date: TextEditor,
	number: NumberEditor,
	price: NumberEditor,
	integer: NumberEditor,
	select: SelectEditor,
	boolean: SelectEditor,
};

export function getEditorForField(
	fieldType: FieldType
): ComponentType< CellEditorProps > | null {
	return EDITOR_MAP[ fieldType ] ?? null;
}

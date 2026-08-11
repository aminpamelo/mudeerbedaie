import {
  Type,
  AlignLeft,
  Hash,
  Mail,
  Calendar,
  CircleDot,
  CheckSquare,
  ChevronDownSquare,
  Upload,
  Star,
  Phone,
  Heading,
  Text,
} from 'lucide-react';

/**
 * Single source of truth for the builder's field catalog. Each entry drives
 * both the "add field" palette and how the field renders/validates.
 */
export const FIELD_CATALOG = [
  { type: 'short_text', label: 'Teks Pendek', icon: Type, group: 'Asas', hasOptions: false },
  { type: 'long_text', label: 'Teks Panjang', icon: AlignLeft, group: 'Asas', hasOptions: false },
  { type: 'number', label: 'Nombor', icon: Hash, group: 'Asas', hasOptions: false },
  { type: 'email', label: 'Emel', icon: Mail, group: 'Asas', hasOptions: false },
  { type: 'date', label: 'Tarikh', icon: Calendar, group: 'Asas', hasOptions: false },

  { type: 'radio', label: 'Pilihan Tunggal', icon: CircleDot, group: 'Pilihan', hasOptions: true },
  { type: 'checkbox', label: 'Kotak Semak', icon: CheckSquare, group: 'Pilihan', hasOptions: true },
  { type: 'dropdown', label: 'Dropdown', icon: ChevronDownSquare, group: 'Pilihan', hasOptions: true },

  { type: 'file', label: 'Muat Naik Fail', icon: Upload, group: 'Fail', hasOptions: false },

  { type: 'rating', label: 'Rating', icon: Star, group: 'Lanjutan', hasOptions: false },
  { type: 'phone', label: 'No. Telefon', icon: Phone, group: 'Lanjutan', hasOptions: false },
  { type: 'section', label: 'Tajuk Seksyen', icon: Heading, group: 'Lanjutan', hasOptions: false },
  { type: 'paragraph', label: 'Teks Penerangan', icon: Text, group: 'Lanjutan', hasOptions: false },
];

export const FIELD_GROUPS = ['Asas', 'Pilihan', 'Fail', 'Lanjutan'];

const CATALOG_BY_TYPE = Object.fromEntries(FIELD_CATALOG.map((f) => [f.type, f]));

export function fieldMeta(type) {
  return CATALOG_BY_TYPE[type] ?? CATALOG_BY_TYPE.short_text;
}

export function fieldHasOptions(type) {
  return fieldMeta(type).hasOptions;
}

export function isLayoutField(type) {
  return type === 'section' || type === 'paragraph';
}

let counter = 0;

export function makeFieldId() {
  counter += 1;
  return `fld_${Math.random().toString(36).slice(2, 8)}${counter}`;
}

export function blankField(type) {
  const meta = fieldMeta(type);
  return {
    id: makeFieldId(),
    type,
    label: isLayoutField(type) ? '' : meta.label,
    help: '',
    required: false,
    options: meta.hasOptions ? ['Pilihan 1', 'Pilihan 2'] : [],
    settings: type === 'rating' ? { max: 5 } : {},
  };
}

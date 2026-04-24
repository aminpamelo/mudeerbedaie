import { ROLE_COMPAT } from './fieldTypes';

export const FIELD_TYPES_ALLOWED = [
  'text',
  'textarea',
  'email',
  'phone',
  'number',
  'url',
  'select',
  'radio',
  'checkbox_group',
  'file',
  'date',
  'datetime',
  'heading',
  'paragraph',
];

export const CHOICE_TYPES = ['select', 'radio', 'checkbox_group'];
export const DISPLAY_ONLY_TYPES = ['heading', 'paragraph'];

/**
 * Mirrors app/Services/Recruitment/FormSchemaValidator.php so the form builder
 * can surface validation errors without a server round-trip.
 *
 * Returns { valid: boolean, errors: string[] }.
 */
export function validateSchema(schema) {
  const errors = [];

  if (!schema || typeof schema !== 'object') {
    return { valid: false, errors: ['schema must be an object'] };
  }

  if (schema.version !== 1) {
    errors.push('schema.version must be 1');
  }

  const pages = Array.isArray(schema.pages) ? schema.pages : null;
  if (!pages || pages.length === 0) {
    errors.push('schema.pages must be a non-empty array');
    return { valid: false, errors };
  }

  const pageIds = new Set();
  const fieldIds = new Set();
  const rolesSeen = new Map();
  let hasDataField = false;

  pages.forEach((page, pi) => {
    const pid = page?.id;
    if (!pid) {
      errors.push(`page[${pi}].id is required`);
    } else if (pageIds.has(pid)) {
      errors.push(`page[${pi}].id '${pid}' is duplicated`);
    } else {
      pageIds.add(pid);
    }

    if (!page?.title) {
      errors.push(`page[${pi}].title is required`);
    }

    const fields = Array.isArray(page?.fields) ? page.fields : [];
    fields.forEach((field, fi) => {
      const fid = field?.id;
      const type = field?.type;

      if (!fid) {
        errors.push(`page[${pi}].field[${fi}].id is required`);
        return;
      }
      if (fieldIds.has(fid)) {
        errors.push(`field id '${fid}' is duplicated`);
      }
      fieldIds.add(fid);

      if (!FIELD_TYPES_ALLOWED.includes(type)) {
        errors.push(`field '${fid}' has invalid type '${type}'`);
        return;
      }

      const isDisplayOnly = DISPLAY_ONLY_TYPES.includes(type);

      if (isDisplayOnly) {
        if (!field.text) {
          errors.push(`field '${fid}' of type ${type} requires 'text'`);
        }
        return;
      }

      hasDataField = true;
      if (!field.label) {
        errors.push(`field '${fid}' label is required`);
      }

      if (CHOICE_TYPES.includes(type)) {
        const opts = Array.isArray(field.options) ? field.options : [];
        if (opts.length === 0) {
          errors.push(`field '${fid}' must have at least one option`);
        } else {
          const values = new Set();
          opts.forEach((opt, oi) => {
            if (!opt?.value) {
              errors.push(`field '${fid}' option[${oi}] value is required`);
            } else if (values.has(opt.value)) {
              errors.push(`field '${fid}' option value '${opt.value}' is duplicated`);
            } else {
              values.add(opt.value);
            }
            if (!opt?.label) {
              errors.push(`field '${fid}' option[${oi}] label is required`);
            }
          });
        }
      }

      const role = field.role;
      if (role !== undefined && role !== null && role !== '') {
        if (!ROLE_COMPAT[role]) {
          errors.push(`field '${fid}' has unknown role '${role}'`);
        } else {
          if (rolesSeen.has(role)) {
            errors.push(
              `role '${role}' is used by more than one field (also on '${rolesSeen.get(role)}')`
            );
          } else {
            rolesSeen.set(role, fid);
          }
          if (!ROLE_COMPAT[role].includes(type)) {
            errors.push(`field '${fid}' role '${role}' is incompatible with type '${type}'`);
          }
        }
      }
    });
  });

  if (!hasDataField) {
    errors.push('schema must contain at least one data-collecting field');
  }

  if (!rolesSeen.has('email')) {
    errors.push('schema must contain exactly one field with role "email"');
  }

  return { valid: errors.length === 0, errors };
}

import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Merge Tailwind class names while respecting conditional/falsey values.
 * Mirrors the helper used by the Live Host Desk bundle so Pocket pages can
 * share the same authoring idioms.
 */
export function cn(...inputs) {
  return twMerge(clsx(inputs));
}

/**
 * Greeting helper used on the Today screen. Kept here so it is available to
 * both the layout header and the page body without duplicating the logic.
 */
export function greetingFor(date = new Date()) {
  const hour = date.getHours();
  if (hour < 12) {
    return 'Good morning';
  }
  if (hour < 18) {
    return 'Good afternoon';
  }
  return 'Good evening';
}

/**
 * Extract the host's first name for a friendly greeting. Falls back to the
 * whole name if only a single word is provided.
 */
export function firstNameFrom(name) {
  if (!name) {
    return 'there';
  }
  return name.split(/\s+/)[0] || name;
}

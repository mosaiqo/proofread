/**
 * Typed re-export of the design tokens defined in `tokens.css`.
 *
 * The CSS custom properties remain the source of truth at runtime
 * (so theming works without JS). This module provides a compile-time
 * mirror that components can import for things like token catalogs.
 */

export interface TokenEntry {
  name: string
  var: string
  value: string
}

export const brandScale: TokenEntry[] = [
  { name: 'brand/50', var: '--color-brand-50', value: '#fff1ef' },
  { name: 'brand/100', var: '--color-brand-100', value: '#ffddd8' },
  { name: 'brand/200', var: '--color-brand-200', value: '#ffb7ae' },
  { name: 'brand/300', var: '--color-brand-300', value: '#ff8c7f' },
  { name: 'brand/400', var: '--color-brand-400', value: '#ff5c4a' },
  { name: 'brand/500', var: '--color-brand-500', value: '#ff2d20' },
  { name: 'brand/600', var: '--color-brand-600', value: '#e51c10' },
  { name: 'brand/700', var: '--color-brand-700', value: '#b5160d' },
  { name: 'brand/800', var: '--color-brand-800', value: '#82100a' },
  { name: 'brand/900', var: '--color-brand-900', value: '#520a06' },
]

export const accentScale: TokenEntry[] = [
  { name: 'accent/50', var: '--color-accent-50', value: '#fff4f2' },
  { name: 'accent/100', var: '--color-accent-100', value: '#ffe1dc' },
  { name: 'accent/200', var: '--color-accent-200', value: '#fecaca' },
  { name: 'accent/300', var: '--color-accent-300', value: '#fda4af' },
  { name: 'accent/400', var: '--color-accent-400', value: '#fb7185' },
  { name: 'accent/500', var: '--color-accent-500', value: '#f53003' },
  { name: 'accent/600', var: '--color-accent-600', value: '#d12200' },
  { name: 'accent/700', var: '--color-accent-700', value: '#9e1a00' },
]

export const neutralScale: TokenEntry[] = [
  { name: 'neutral/50', var: '--color-neutral-50', value: '#fbf9f7' },
  { name: 'neutral/100', var: '--color-neutral-100', value: '#f5f2ef' },
  { name: 'neutral/200', var: '--color-neutral-200', value: '#eae5e0' },
  { name: 'neutral/300', var: '--color-neutral-300', value: '#d6cfc7' },
  { name: 'neutral/400', var: '--color-neutral-400', value: '#a8a198' },
  { name: 'neutral/500', var: '--color-neutral-500', value: '#78716b' },
  { name: 'neutral/600', var: '--color-neutral-600', value: '#57534e' },
  { name: 'neutral/700', var: '--color-neutral-700', value: '#3f3b37' },
  { name: 'neutral/800', var: '--color-neutral-800', value: '#27231f' },
  { name: 'neutral/900', var: '--color-neutral-900', value: '#1a1714' },
  { name: 'neutral/950', var: '--color-neutral-950', value: '#100e0c' },
]

export const semanticTokens: TokenEntry[] = [
  { name: 'success', var: '--color-success', value: '#16a34a' },
  { name: 'warning', var: '--color-warning', value: '#d97706' },
  { name: 'danger', var: '--color-danger', value: '#dc2626' },
  { name: 'info', var: '--color-info', value: '#2563eb' },
]

export interface TypeSpecimen {
  name: string
  var: string
  size: string
  usage: string
}

export const typeScale: TypeSpecimen[] = [
  { name: 'xs', var: '--text-xs', size: '12px', usage: 'Labels, captions, metadata' },
  { name: 'sm', var: '--text-sm', size: '14px', usage: 'Secondary copy, UI text' },
  { name: 'base', var: '--text-base', size: '16px', usage: 'Body copy default' },
  { name: 'lg', var: '--text-lg', size: '18px', usage: 'Lead paragraphs' },
  { name: 'xl', var: '--text-xl', size: '20px', usage: 'Small headings (h4)' },
  { name: '2xl', var: '--text-2xl', size: '24px', usage: 'Section headings (h3)' },
  { name: '3xl', var: '--text-3xl', size: '30px', usage: 'Page headings (h2)' },
  { name: '4xl', var: '--text-4xl', size: '40px', usage: 'Hero subtitle, major titles' },
  { name: '5xl', var: '--text-5xl', size: '56px', usage: 'Hero headline' },
]

export interface SpacingEntry {
  name: string
  var: string
  value: string
  px: number
}

export const spacingScale: SpacingEntry[] = [
  { name: '0', var: '--space-0', value: '0', px: 0 },
  { name: '0.5', var: '--space-0_5', value: '2px', px: 2 },
  { name: '1', var: '--space-1', value: '4px', px: 4 },
  { name: '1.5', var: '--space-1_5', value: '6px', px: 6 },
  { name: '2', var: '--space-2', value: '8px', px: 8 },
  { name: '3', var: '--space-3', value: '12px', px: 12 },
  { name: '4', var: '--space-4', value: '16px', px: 16 },
  { name: '6', var: '--space-6', value: '24px', px: 24 },
  { name: '8', var: '--space-8', value: '32px', px: 32 },
  { name: '12', var: '--space-12', value: '48px', px: 48 },
  { name: '16', var: '--space-16', value: '64px', px: 64 },
  { name: '24', var: '--space-24', value: '96px', px: 96 },
  { name: '32', var: '--space-32', value: '128px', px: 128 },
]

export interface RadiusEntry {
  name: string
  var: string
  value: string
}

export const radiiScale: RadiusEntry[] = [
  { name: 'xs', var: '--radius-xs', value: '4px' },
  { name: 'sm', var: '--radius-sm', value: '6px' },
  { name: 'md', var: '--radius-md', value: '8px' },
  { name: 'lg', var: '--radius-lg', value: '12px' },
  { name: 'xl', var: '--radius-xl', value: '16px' },
  { name: 'full', var: '--radius-full', value: '9999px' },
]

export interface ShadowEntry {
  name: string
  var: string
}

export const shadowScale: ShadowEntry[] = [
  { name: 'sm', var: '--shadow-sm' },
  { name: 'md', var: '--shadow-md' },
  { name: 'lg', var: '--shadow-lg' },
]

export interface MotionEntry {
  name: string
  var: string
  value: string
}

export const motionTokens: MotionEntry[] = [
  { name: 'fast', var: '--duration-fast', value: '120ms' },
  { name: 'base', var: '--duration-base', value: '200ms' },
  { name: 'slow', var: '--duration-slow', value: '320ms' },
  { name: 'ease-out', var: '--ease-out', value: 'cubic-bezier(0.16, 1, 0.3, 1)' },
  { name: 'ease-in-out', var: '--ease-in-out', value: 'cubic-bezier(0.65, 0, 0.35, 1)' },
]

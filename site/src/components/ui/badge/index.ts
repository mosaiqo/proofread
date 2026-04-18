import { cva, type VariantProps } from 'class-variance-authority'

export { default as Badge } from './Badge.vue'

export const badgeVariants = cva(
  'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium transition-colors duration-fast focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
  {
    variants: {
      variant: {
        default:
          'border-transparent bg-brand-500 text-brand-foreground hover:bg-brand-600',
        secondary:
          'border-transparent bg-neutral-100 text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100',
        outline: 'text-foreground border-border',
        success:
          'border-transparent bg-success/10 text-success dark:bg-success/20',
        warning:
          'border-transparent bg-warning/10 text-warning dark:bg-warning/20',
        danger:
          'border-transparent bg-destructive/10 text-destructive dark:bg-destructive/20',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  },
)

export type BadgeVariants = VariantProps<typeof badgeVariants>

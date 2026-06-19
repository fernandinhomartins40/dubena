import { forwardRef, type ComponentPropsWithoutRef, type ElementRef } from 'react'
import * as CheckboxPrimitive from '@radix-ui/react-checkbox'
import { Check } from 'lucide-react'
import { cn } from '@/lib/cn'

export const Checkbox = forwardRef<
  ElementRef<typeof CheckboxPrimitive.Root>,
  ComponentPropsWithoutRef<typeof CheckboxPrimitive.Root>
>(function Checkbox({ className, ...props }, ref) {
  return (
    <CheckboxPrimitive.Root
      ref={ref}
      className={cn(
        'peer size-5 shrink-0 rounded border border-input shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1',
        'disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground transition-colors',
        className,
      )}
      {...props}
    >
      <CheckboxPrimitive.Indicator className="flex items-center justify-center">
        <Check className="size-4" />
      </CheckboxPrimitive.Indicator>
    </CheckboxPrimitive.Root>
  )
})

/** Checkbox + rótulo clicável, padrão de flag de formulário. */
export function CheckboxField({ label, checked, onChange, disabled }: {
  label: string; checked: boolean; onChange: (b: boolean) => void; disabled?: boolean
}) {
  return (
    <label className="flex items-center gap-2.5 py-2 text-sm cursor-pointer select-none">
      <Checkbox checked={checked} onCheckedChange={(v) => onChange(!!v)} disabled={disabled} />
      {label}
    </label>
  )
}

import { type ReactNode } from 'react';
import { type LucideIcon } from 'lucide-react';
import { cn } from '../ui/utils';

type PageHeaderProps = {
  title: string;
  icon: LucideIcon;
  subtitle?: string;
  action?: ReactNode;
  aside?: ReactNode;
  className?: string;
};

/**
 * Single source of truth for dashboard page headings.
 *
 * The icon is always supplied by the caller so it can be taken straight from a
 * dashboard's `tabs` configuration. That keeps the navigation icon and the page
 * title icon from drifting apart.
 */
export default function PageHeader({
  title,
  icon: Icon,
  subtitle,
  action,
  aside,
  className,
}: PageHeaderProps) {
  return (
    <div
      className={cn(
        'flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between lg:gap-6',
        className,
      )}
    >
      <div className="min-w-0">
        <h1 className="flex items-center gap-3 font-display text-2xl tracking-tight text-foreground sm:text-3xl">
          <Icon className="h-7 w-7 shrink-0 text-[#0F9488] sm:h-8 sm:w-8" aria-hidden="true" />
          <span className="min-w-0 break-words">{title}</span>
        </h1>

        {subtitle && (
          <p className="mt-1 text-sm text-muted-foreground sm:text-base">{subtitle}</p>
        )}
      </div>

      {(action || aside) && (
        <div className="flex w-full flex-wrap items-center gap-3 lg:w-auto lg:justify-end">
          {action}
          {aside}
        </div>
      )}
    </div>
  );
}

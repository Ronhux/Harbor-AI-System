import { useId } from 'react';
import { Monitor, Moon, Sun } from 'lucide-react';
import { useTheme, type Theme } from './ThemeProvider';
import { useLanguage } from '../i18n/useLanguage';

const options: { id: Theme; label: string; icon: React.ComponentType<{ className?: string }> }[] = [
  { id: 'light', label: 'Light', icon: Sun },
  { id: 'dark', label: 'Dark', icon: Moon },
  { id: 'system', label: 'System', icon: Monitor },
];

export default function ThemeSelector({ className }: { className?: string } = {}) {
  const { theme, setTheme } = useTheme();
  const { lang } = useLanguage();
  const id = useId();
  const label = lang === 'fil' ? 'Anyo' : 'Appearance';
  return (
    <div className={[
      'inline-flex shrink-0 items-center rounded-xl border border-border bg-card px-1 py-0.5 text-foreground shadow-sm',
      className,
    ].join(' ')}>
    <span className="sr-only">{label}</span>
    <div role="radiogroup" aria-label={label} className="flex">
      {options.map((option) => {
        const Icon = option.icon;
        const active = theme === option.id;
        return (
          <button
            key={option.id}
            type="button"
            role="radio"
            aria-checked={active}
            aria-labelledby={id}
            onClick={() => setTheme(option.id)}
            className={[
              'relative flex h-8 w-10 items-center justify-center rounded-lg transition-colors duration-200 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 focus-visible:ring-[#287650]',
              active
                ? 'bg-[#287650] text-white shadow-sm'
                : 'text-[#6B7A73] hover:bg-[#F0F2F1] hover:text-[#192B23]',
            ].join(' ')}
          >
            <Icon className={`h-4 w-4 shrink-0 transition-opacity duration-200 ${active ? 'opacity-100' : 'opacity-60'}`} aria-hidden="true" />
            {active && (
              <span className="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-white shadow-sm" aria-hidden="true" />
            )}
          </button>
        );
      })}
    </div>
  </div>
);
}

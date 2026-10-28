import { useLanguage } from './useLanguage';

/**
 * Compact EN | FIL segmented control.
 *
 * One shared style is used on Login, Registration and the dashboard shell so
 * the selector reads as part of the interface rather than a floating control.
 * A plain button pair is used instead of the Button primitive so the segment
 * stays visually quiet and never competes with the primary Login action.
 */
export default function LanguageSwitcher({ className }: { className?: string }) {
  const { lang, setLang, t } = useLanguage();

  const options: Array<{ code: 'en' | 'fil'; label: string }> = [
    { code: 'en', label: t('common.english') },
    { code: 'fil', label: t('common.filipino') },
  ];

  return (
    <div
      role="group"
      aria-label="Language"
      className={`inline-flex shrink-0 items-center rounded-full border border-[#DCD6C4] bg-card/70 p-0.5 ${className || ''}`}
    >
      {options.map((option) => {
        const active = lang === option.code;
        return (
          <button
            key={option.code}
            type="button"
            lang={option.code === 'fil' ? 'fil' : 'en'}
            aria-pressed={active}
            onClick={() => setLang(option.code)}
            className={[
              'min-h-8 rounded-full px-2.5 text-xs font-semibold leading-none transition-colors duration-150',
              'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0F9488]/45 focus-visible:ring-offset-1',
              active
                ? 'bg-[#0F9488] text-white shadow-sm'
                : 'text-[#5A6B7B] hover:bg-[#0F9488]/10 hover:text-foreground',
            ].join(' ')}
          >
            {option.label}
          </button>
        );
      })}
    </div>
  );
}

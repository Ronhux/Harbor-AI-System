import { createContext, useContext, useSyncExternalStore, useEffect, useState, type PropsWithChildren } from 'react';

export type Theme = 'light' | 'dark' | 'system';

declare global {
  interface Window {
    harborAppearance: {
      getPreference: () => Theme;
      setPreference: (value: Theme) => void;
      subscribe: (listener: () => void) => () => void;
      subscribeResolved: (listener: () => void) => () => void;
      getResolvedTheme: () => 'light' | 'dark';
      isDark: () => boolean;
    };
  }
}

const ThemeContext = createContext<{
  theme: Theme;
  resolvedTheme: 'light' | 'dark';
  setTheme: (value: Theme) => void;
}>({ theme: 'system', resolvedTheme: 'light', setTheme: () => {} });

export const useTheme = () => useContext(ThemeContext);

export default function ThemeProvider({ children }: PropsWithChildren) {
  const appearance = window.harborAppearance;
  const theme = useSyncExternalStore(appearance.subscribe, appearance.getPreference);
  const [resolvedTheme, setResolvedTheme] = useState<'light' | 'dark'>(
    appearance.getResolvedTheme()
  );

  useEffect(() => {
    const update = () => setResolvedTheme(appearance.getResolvedTheme());
    const unsub = appearance.subscribeResolved(update);
    return () => {
      unsub();
    };
  }, [appearance]);

  return (
    <ThemeContext.Provider
      value={{
        theme,
        resolvedTheme,
        setTheme: appearance.setPreference,
      }}
    >
      {children}
    </ThemeContext.Provider>
  );
}

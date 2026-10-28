import { useEffect, useState, PropsWithChildren } from 'react';
import { Lang, LangContext, LangContextType } from './useLanguage';
import { en } from './en';
import { fil } from './fil';

const STORAGE_KEY = 'harborai_lang';

function getNested(obj: any, path: string): string | undefined {
  return path.split('.').reduce((acc, k) => (acc && acc[k] !== undefined ? acc[k] : undefined), obj);
}

export default function LanguageProvider({ children }: PropsWithChildren) {
  const [lang, setLang] = useState<Lang>('en');

  useEffect(() => {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (stored === 'fil' || stored === 'en') setLang(stored);
    } catch {}
  }, []);

  const setLangPersist = (l: Lang) => {
    setLang(l);
    try {
      localStorage.setItem(STORAGE_KEY, l);
    } catch {}
  };

  const t = (path: string): string => {
    const dict = lang === 'fil' ? fil : en;
    const v = getNested(dict, path);
    if (typeof v === 'string') return v;
    const ven = getNested(en, path);
    if (typeof ven === 'string') return ven;
    return path;
  };

  const value: LangContextType = { lang, setLang: setLangPersist, t };
  return <LangContext.Provider value={value}>{children}</LangContext.Provider>;
}

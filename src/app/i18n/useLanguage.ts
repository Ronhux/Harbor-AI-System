import { createContext, useContext } from 'react';

export type Lang = 'en' | 'fil';

export type LangContextType = {
  lang: Lang;
  setLang: (l: Lang) => void;
  t: (path: string) => string;
};

export const LangContext = createContext<LangContextType>({
  lang: 'en',
  setLang: () => {},
  t: (k: string) => k,
});

export const useLanguage = () => useContext(LangContext);

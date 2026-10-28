import { createContext, useContext } from 'react';
/** Portals inherit React context, while public pages retain their own appearance. */
export const DashboardPortalTheme = createContext<'light' | 'dark' | undefined>(undefined);
export function useDashboardPortalTheme() { return useContext(DashboardPortalTheme); }
